#!/usr/bin/env python3
"""Build SQL that fills echos_subjs2.title_en for ZXNet echoes.

Default echoes: zx.spectrum, real.speccy (after tools/fill-echos-subjs-title-en.py).

Writes:
  generated/01_update_title_en.sql
  generated/summary.json
  db/migrate/YYYYMMDDHHMMSS_echos_subjs2_zxnet_title_en.sql

Idempotent: only updates rows where title_en is empty.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
GEN = HERE / "generated"
DEFAULT_ECHOES = ("zx.spectrum", "real.speccy")
MIGRATE_NAME = "echos_subjs2_zxnet_title_en.sql"
# Remove older one-echo migrate artifacts when rebuilding.
LEGACY_MIGRATE_GLOB = "*_echos_subjs2_zx_spectrum_title_en.sql"

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
ROOT_PW = os.environ.get("MYSQL_ROOT_PASSWORD", "changeme-root-password")
MYSQL_DB = os.environ.get("MYSQL_DATABASE") or os.environ.get("DB_NAME", "zxpress_db")


def mysql_query(sql: str) -> str:
    return subprocess.check_output(
        [
            "docker",
            "exec",
            "-i",
            MYSQL_CONTAINER,
            "mysql",
            "-uroot",
            f"-p{ROOT_PW}",
            MYSQL_DB,
            "--default-character-set=utf8mb4",
            "-N",
            "-B",
            "-e",
            sql,
        ],
        stderr=subprocess.DEVNULL,
    ).decode("utf-8", errors="replace")


def sql_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def echo_var_name(title: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "_", title.lower()).strip("_")
    return f"@echo_{slug}_id"


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--echo",
        action="append",
        dest="echoes",
        default=None,
        help="Echo title (repeatable). Default: zx.spectrum + real.speccy",
    )
    args = ap.parse_args()
    echoes = tuple(args.echoes) if args.echoes else DEFAULT_ECHOES

    GEN.mkdir(parents=True, exist_ok=True)
    migrate_dir = HERE.parents[1] / "migrate"

    echo_meta: list[dict[str, object]] = []
    all_rows: list[tuple[str, int, str]] = []  # echo_title, id, title_en

    for title in echoes:
        raw_id = mysql_query(
            f"SELECT id FROM echos_titles2 WHERE title={sql_quote(title)} LIMIT 1"
        ).strip()
        if not raw_id:
            print(f"echo not found: {title!r}", file=sys.stderr)
            return 1
        echo_id = int(raw_id.splitlines()[0])
        raw = mysql_query(
            "SELECT id, title_en FROM echos_subjs2 "
            f"WHERE echo_id={echo_id} "
            "AND title_en IS NOT NULL AND TRIM(title_en)<>'' "
            "ORDER BY id"
        )
        n = 0
        for line in raw.splitlines():
            if not line.strip():
                continue
            id_s, title_en = line.split("\t", 1)
            all_rows.append((title, int(id_s), title_en))
            n += 1
        echo_meta.append({"title": title, "echo_id": echo_id, "rows": n})
        if n == 0:
            print(
                f"no title_en for {title!r} — run fill-echos-subjs-title-en.py --echo={title}",
                file=sys.stderr,
            )
            return 1

    stamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    migrate_path = migrate_dir / f"{stamp}_{MIGRATE_NAME}"
    sql_path = GEN / "01_update_title_en.sql"

    for old in migrate_dir.glob(LEGACY_MIGRATE_GLOB):
        old.unlink()
    for old in migrate_dir.glob(f"*_{MIGRATE_NAME}"):
        old.unlink()

    lines = [
        "-- Fill echos_subjs2.title_en for ZXNet echoes: "
        + ", ".join(echoes)
        + ".\n"
        f"-- Generated {stamp} UTC from local DB; Google Translate gtx via\n"
        "-- tools/fill-echos-subjs-title-en.py --echo=...\n"
        "-- Idempotent: only empty title_en; scoped per echo title.\n"
        "SET NAMES utf8mb4;\n"
    ]
    for title in echoes:
        lines.append(
            f"SET {echo_var_name(title)} := (SELECT id FROM echos_titles2 "
            f"WHERE title={sql_quote(title)} LIMIT 1);\n"
        )
    lines.append("START TRANSACTION;\n")

    for title, id_, en in all_rows:
        var = echo_var_name(title)
        lines.append(
            "UPDATE echos_subjs2 SET title_en="
            + sql_quote(en)
            + f" WHERE id={id_} AND echo_id={var}"
            + " AND (title_en IS NULL OR TRIM(title_en)='') LIMIT 1;\n"
        )
    lines.append("COMMIT;\n")
    body = "".join(lines)

    sql_path.write_text(body, encoding="utf-8")
    migrate_path.write_text(body, encoding="utf-8")

    summary = {
        "echoes": echo_meta,
        "rows_total": len(all_rows),
        "sql": str(sql_path.relative_to(HERE.parent.parent.parent)),
        "migrate": str(migrate_path.relative_to(HERE.parent.parent.parent)),
        "generated_at_utc": stamp,
    }
    (GEN / "summary.json").write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
