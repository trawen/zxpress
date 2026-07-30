#!/usr/bin/env python3
"""Fill ezine_categories title_en / description_en from RU (Google Translate gtx).

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
PROGRESS = Path("/tmp/ezine_categories_en_progress.json")
WORKERS = 4
TITLE_MAX = 255


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
            req = urllib.request.Request(
                url, headers={"User-Agent": "zxpress-ezine-categories-en/1.0"}
            )
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


def clip(s: str, max_len: int) -> str:
    s = s.strip()
    if len(s) <= max_len:
        return s
    return s[: max_len - 1].rstrip() + "…"


def needs_en(ru: str, en: str) -> bool:
    ru = ru.strip()
    en = en.strip()
    if not ru:
        return False
    return en == "" or en == ru


def main() -> int:
    # Use 0x1F as field separator so description newlines don't break TSV.
    raw = mysql_query(
        "SELECT id,"
        " IFNULL(title_ru,''),"
        " IFNULL(title_en,''),"
        " REPLACE(REPLACE(IFNULL(description_ru,''), CHAR(13), ' '), CHAR(10), ' '),"
        " REPLACE(REPLACE(IFNULL(description_en,''), CHAR(13), ' '), CHAR(10), ' ')"
        " FROM ezine_categories ORDER BY id"
    )
    rows: list[tuple[int, str, str, str, str]] = []
    for line in raw.splitlines():
        if not line.strip():
            continue
        parts = line.split("\t")
        while len(parts) < 5:
            parts.append("")
        id_s, title_ru, title_en, desc_ru, desc_en = parts[:5]
        rows.append((int(id_s), title_ru, title_en, desc_ru, desc_en))

    progress: dict[str, str] = {}
    if PROGRESS.exists():
        progress = json.loads(PROGRESS.read_text(encoding="utf-8"))

    to_translate: list[str] = []
    seen: set[str] = set()
    need_title = 0
    need_desc = 0
    for _, title_ru, title_en, desc_ru, desc_en in rows:
        if needs_en(title_ru, title_en):
            need_title += 1
            t = title_ru.strip()
            if CYR.search(t) and t not in seen and t not in progress:
                seen.add(t)
                to_translate.append(t)
        if needs_en(desc_ru, desc_en):
            need_desc += 1
            t = desc_ru.strip()
            if CYR.search(t) and t not in seen and t not in progress:
                seen.add(t)
                to_translate.append(t)

    print(
        f"rows={len(rows)} need_title_en={need_title} need_description_en={need_desc} "
        f"unique_cyr_to_translate={len(to_translate)} cached={len(progress)}",
        flush=True,
    )

    done = 0
    failed = 0

    def work(text: str) -> tuple[str, str | None]:
        try:
            return text, translate(text)
        except Exception as e:  # noqa: BLE001
            print(f"FAIL {text!r}: {e}", flush=True)
            return text, None

    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        futs = [pool.submit(work, t) for t in to_translate]
        for fut in as_completed(futs):
            text, en = fut.result()
            done += 1
            if en is None:
                failed += 1
            else:
                progress[text] = en
            if done % 20 == 0 or done == len(to_translate):
                PROGRESS.write_text(json.dumps(progress, ensure_ascii=False), encoding="utf-8")
                print(f"translated {done}/{len(to_translate)} failed={failed}", flush=True)

    PROGRESS.write_text(json.dumps(progress, ensure_ascii=False), encoding="utf-8")

    sql_path = Path("/tmp/ezine_categories_en_update.sql")
    updates = 0
    with sql_path.open("w", encoding="utf-8") as f:
        f.write("SET NAMES utf8mb4;\nSTART TRANSACTION;\n")
        for id_, title_ru, title_en, desc_ru, desc_en in rows:
            sets: list[str] = []
            if needs_en(title_ru, title_en):
                t = title_ru.strip()
                en = clip(progress.get(t, t if not CYR.search(t) else ""), TITLE_MAX)
                if en:
                    sets.append(f"title_en={sql_quote(en)}")
            if needs_en(desc_ru, desc_en):
                t = desc_ru.strip()
                en = progress.get(t, t if not CYR.search(t) else "")
                if en:
                    sets.append(f"description_en={sql_quote(en)}")
            if sets:
                updates += 1
                f.write(f"UPDATE ezine_categories SET {', '.join(sets)} WHERE id={id_} LIMIT 1;\n")
        f.write("COMMIT;\n")

    print(f"applying {updates} row updates from {sql_path} ...", flush=True)
    if updates:
        mysql_exec_file(sql_path)

    stats = mysql_query(
        "SELECT "
        "SUM(title_ru IS NOT NULL AND TRIM(title_ru)<>''), "
        "SUM(title_ru IS NOT NULL AND TRIM(title_ru)<>'' AND title_en IS NOT NULL AND TRIM(title_en)<>''), "
        "SUM(description_ru IS NOT NULL AND TRIM(description_ru)<>''), "
        "SUM(description_ru IS NOT NULL AND TRIM(description_ru)<>'' AND description_en IS NOT NULL AND TRIM(description_en)<>'') "
        "FROM ezine_categories"
    ).strip()
    print("stats has_title_ru/has_title_en/has_desc_ru/has_desc_en:", stats, flush=True)
    print("failed_translations:", failed, flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
