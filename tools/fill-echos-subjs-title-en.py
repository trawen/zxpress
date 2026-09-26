#!/usr/bin/env python3
"""Fill echos_subjs2.title_en from title (Google Translate gtx for Cyrillic).

Usage:
  python3 tools/fill-echos-subjs-title-en.py
  python3 tools/fill-echos-subjs-title-en.py --echo=zx.spectrum
  MYSQL_ROOT_PASSWORD=... ZXPRESS_MYSQL_CONTAINER=zxpress_db \\
    python3 tools/fill-echos-subjs-title-en.py --echo=zx.spectrum
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

CYR = re.compile(r"[А-Яа-яЁё]")
PROGRESS = Path("/tmp/zxnet_title_en_progress.json")
WORKERS = 6
MAX_LEN = 128

CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
DB_NAME = os.environ.get("MYSQL_DATABASE") or os.environ.get("DB_NAME", "zxpress_db")
ROOT_PW = os.environ.get("MYSQL_ROOT_PASSWORD", "changeme-root-password")


def mysql_cmd_base() -> list[str]:
    return [
        "docker",
        "exec",
        "-i",
        CONTAINER,
        "mysql",
        "-uroot",
        f"-p{ROOT_PW}",
        DB_NAME,
        "--default-character-set=utf8mb4",
    ]


def mysql_query(sql: str) -> str:
    return subprocess.check_output(
        mysql_cmd_base() + ["-N", "-B", "-e", sql],
        stderr=subprocess.DEVNULL,
    ).decode("utf-8", errors="replace")


def mysql_exec_file(path: Path) -> None:
    with path.open("rb") as f:
        subprocess.check_call(
            mysql_cmd_base(),
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
            req = urllib.request.Request(url, headers={"User-Agent": "zxpress-title-en/1.0"})
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


def echo_id_for(title: str) -> int | None:
    raw = mysql_query(
        "SELECT id FROM echos_titles2 WHERE title=" + sql_quote(title) + " LIMIT 1"
    ).strip()
    if not raw:
        return None
    return int(raw.splitlines()[0].strip())


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--echo",
        default="",
        help="Limit to one echo title (e.g. zx.spectrum). Empty = all echoes.",
    )
    ap.add_argument(
        "--dry-run",
        action="store_true",
        help="Translate and write SQL, but do not apply to MySQL.",
    )
    args = ap.parse_args()

    where = "WHERE (title_en IS NULL OR TRIM(title_en)='')"
    echo_id: int | None = None
    if args.echo:
        echo_id = echo_id_for(args.echo)
        if echo_id is None:
            print(f"echo not found: {args.echo!r}", flush=True)
            return 1
        where += f" AND echo_id={echo_id}"
        print(f"echo={args.echo!r} id={echo_id}", flush=True)

    # Fast path: non-Cyrillic titles → copy as-is.
    copy_sql = (
        "SET NAMES utf8mb4;\n"
        "UPDATE echos_subjs2 SET title_en = LEFT(TRIM(title), 128) "
        f"{where} AND title NOT REGEXP '[А-Яа-яЁё]' AND TRIM(title)<>'';\n"
    )
    if not args.dry_run:
        print("copying non-Cyrillic titles → title_en ...", flush=True)
        p = subprocess.run(
            mysql_cmd_base(),
            input=copy_sql.encode("utf-8"),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )
        if p.returncode != 0:
            print(p.stderr.decode("utf-8", errors="replace")[:800], flush=True)
            return 1

    raw = mysql_query(
        "SELECT id, REPLACE(REPLACE(IFNULL(title,''), CHAR(13), ' '), CHAR(10), ' ') "
        f"FROM echos_subjs2 {where} ORDER BY id"
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
    for _, title in rows:
        t = title.strip()
        if not t or not CYR.search(t):
            continue
        if t in seen or t in progress:
            continue
        seen.add(t)
        unique_cyr.append(t)

    print(
        f"rows_missing={len(rows)} unique_cyr_to_translate={len(unique_cyr)} "
        f"cached={len(progress)}",
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

    sql_path = Path("/tmp/zxnet_title_en_update.sql")
    with sql_path.open("w", encoding="utf-8") as f:
        f.write("SET NAMES utf8mb4;\nSTART TRANSACTION;\n")
        for id_, title in rows:
            t = title.strip()
            if not t:
                en = ""
            elif CYR.search(t):
                en = progress.get(t, "")
                if not en:
                    continue
            else:
                en = clip(t)
            f.write(
                f"UPDATE echos_subjs2 SET title_en={sql_quote(en)} "
                f"WHERE id={id_} AND (title_en IS NULL OR TRIM(title_en)='') LIMIT 1;\n"
            )
        f.write("COMMIT;\n")

    print(f"SQL written to {sql_path}", flush=True)
    if args.dry_run:
        print("dry-run: not applying SQL", flush=True)
    else:
        print(f"applying SQL updates from {sql_path} ...", flush=True)
        mysql_exec_file(sql_path)

    stats_where = f"WHERE echo_id={echo_id}" if echo_id is not None else ""
    stats = mysql_query(
        "SELECT COUNT(*), "
        "SUM(title_en IS NOT NULL AND TRIM(title_en)<>''), "
        "SUM(title REGEXP '[А-Яа-яЁё]' AND (title_en IS NULL OR TRIM(title_en)='')), "
        "SUM(title NOT REGEXP '[А-Яа-яЁё]' AND TRIM(title)<>'' AND (title_en IS NULL OR TRIM(title_en)='')) "
        f"FROM echos_subjs2 {stats_where}"
    ).strip()
    print("stats total/has_en/cyr_missing/noncyr_missing:", stats, flush=True)
    print("failed_translations:", failed, flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
