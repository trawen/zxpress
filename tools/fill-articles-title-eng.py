#!/usr/bin/env python3
"""Fill articles.title_eng from title (Google Translate gtx for Cyrillic).

Mirrors tools/fill-echos-subjs-title-en.py.
Requires docker container zxpress_db.
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

CYR = re.compile(r"[А-Яа-яЁё]")
PROGRESS = Path("/tmp/articles_title_eng_progress.json")
WORKERS = 6
# articles.title_eng is varchar(1024)
MAX_LEN = 1024


def mysql_query(sql: str) -> str:
    return subprocess.check_output(
        [
            "docker",
            "exec",
            "-i",
            "zxpress_db",
            "mysql",
            "-uroot",
            "-proot_zxpress_2024",
            "zxpress_db",
            "--default-character-set=utf8mb4",
            "-N",
            "-B",
            "-e",
            sql,
        ],
        stderr=subprocess.DEVNULL,
    ).decode("utf-8", errors="replace")


def mysql_exec_file(path: Path) -> None:
    with path.open("rb") as f:
        subprocess.check_call(
            [
                "docker",
                "exec",
                "-i",
                "zxpress_db",
                "mysql",
                "-uroot",
                "-proot_zxpress_2024",
                "zxpress_db",
                "--default-character-set=utf8mb4",
            ],
            stdin=f,
            stderr=subprocess.DEVNULL,
        )


def sql_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def translate(text: str, retries: int = 4) -> str:
    url = (
        "https://translate.googleapis.com/translate_a/single"
        f"?client=gtx&sl=ru&tl=en&dt=t&q={urllib.parse.quote(text)}"
    )
    last_err: Exception | None = None
    for attempt in range(retries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "zxpress-articles-title-eng/1.0"})
            with urllib.request.urlopen(req, timeout=30) as r:
                data = json.loads(r.read().decode("utf-8"))
            parts = []
            for chunk in data[0] or []:
                if chunk and chunk[0]:
                    parts.append(chunk[0])
            out = "".join(parts).strip()
            if out:
                return out
            raise RuntimeError("empty translation")
        except Exception as e:  # noqa: BLE001
            last_err = e
            time.sleep(0.6 * (attempt + 1))
    raise RuntimeError(f"translate failed for {text!r}: {last_err}")


def clip(s: str) -> str:
    s = s.strip()
    if len(s) <= MAX_LEN:
        return s
    return s[: MAX_LEN - 1].rstrip() + "…"


def main() -> int:
    raw = mysql_query(
        "SELECT id, REPLACE(REPLACE(IFNULL(title,''), CHAR(13), ' '), CHAR(10), ' ') "
        "FROM articles "
        "WHERE temp=0 AND (title_eng IS NULL OR TRIM(title_eng)='') "
        "ORDER BY id"
    )
    rows: list[tuple[int, str]] = []
    for line in raw.splitlines():
        if not line.strip():
            continue
        id_s, title = line.split("\t", 1)
        rows.append((int(id_s), title))

    progress: dict[str, str] = {}
    if PROGRESS.exists():
        progress = json.loads(PROGRESS.read_text(encoding="utf-8"))

    unique_cyr: list[str] = []
    seen: set[str] = set()
    non_cyr_copy = 0
    for _, title in rows:
        t = title.strip()
        if not t:
            continue
        if not CYR.search(t):
            non_cyr_copy += 1
            continue
        if t in seen or t in progress:
            continue
        seen.add(t)
        unique_cyr.append(t)

    print(
        f"rows_missing_title_eng={len(rows)} unique_cyr_to_translate={len(unique_cyr)} "
        f"non_cyr_copy={non_cyr_copy} cached={len(progress)}",
        flush=True,
    )

    done = 0
    failed = 0

    def work(title: str) -> tuple[str, str | None]:
        try:
            return title, clip(translate(title))
        except Exception as e:  # noqa: BLE001
            print(f"FAIL {title!r}: {e}", flush=True)
            return title, None

    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        futs = [pool.submit(work, t) for t in unique_cyr]
        for fut in as_completed(futs):
            title, en = fut.result()
            done += 1
            if en is None:
                failed += 1
            else:
                progress[title] = en
            if done % 50 == 0 or done == len(unique_cyr):
                PROGRESS.write_text(json.dumps(progress, ensure_ascii=False), encoding="utf-8")
                print(f"translated {done}/{len(unique_cyr)} failed={failed}", flush=True)

    PROGRESS.write_text(json.dumps(progress, ensure_ascii=False), encoding="utf-8")

    sql_path = Path("/tmp/articles_title_eng_update.sql")
    with sql_path.open("w", encoding="utf-8") as f:
        f.write("SET NAMES utf8mb4;\nSTART TRANSACTION;\n")
        for id_, title in rows:
            t = title.strip()
            if not t:
                continue
            if CYR.search(t):
                en = progress.get(t, "")
                if not en:
                    continue
            else:
                en = clip(t)
            f.write(
                f"UPDATE articles SET title_eng={sql_quote(en)} WHERE id={id_} "
                f"AND (title_eng IS NULL OR TRIM(title_eng)='') LIMIT 1;\n"
            )
        f.write("COMMIT;\n")

    print(f"applying SQL updates from {sql_path} ...", flush=True)
    mysql_exec_file(sql_path)

    stats = mysql_query(
        "SELECT COUNT(*), "
        "SUM(title_eng IS NOT NULL AND TRIM(title_eng)<>''), "
        "SUM(title REGEXP '[А-Яа-яЁё]' AND (title_eng IS NULL OR TRIM(title_eng)='')), "
        "SUM(title NOT REGEXP '[А-Яа-яЁё]' AND TRIM(title)<>'' AND (title_eng IS NULL OR TRIM(title_eng)='')) "
        "FROM articles WHERE temp=0"
    ).strip()
    print("stats total/has_en/cyr_missing/noncyr_missing:", stats, flush=True)
    print("failed_translations:", failed, flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
