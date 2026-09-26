#!/usr/bin/env python3
"""Apply generated zx.spectrum title_en SQL. Not run by build.py.

Usage:
  python3 db/migration/echos_subjs2_zx_spectrum_title_en/apply.py

On prod, prefer: commit db/migrate/*_echos_subjs2_zx_spectrum_title_en.sql
and run ./deploy/scripts/update.sh (or remote-update) so schema_migrations applies it.
"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
GEN_SQL = HERE / "generated" / "01_update_title_en.sql"
MIGRATE_DIR = HERE.parents[1] / "migrate"

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
MYSQL_USER = os.environ.get("DB_USER") or os.environ.get("MYSQL_USER", "zxpress_u")
MYSQL_PASS = os.environ.get("DB_PASS") or os.environ.get("MYSQL_PASSWORD", "changeme-app-password")
MYSQL_DB = os.environ.get("DB_NAME") or os.environ.get("MYSQL_DATABASE", "zxpress_db")


def resolve_sql() -> Path:
    if GEN_SQL.is_file():
        return GEN_SQL
    matches = sorted(MIGRATE_DIR.glob("*_echos_subjs2_zxnet_title_en.sql"))
    if not matches:
        matches = sorted(MIGRATE_DIR.glob("*_echos_subjs2_zx_spectrum_title_en.sql"))
    if matches:
        return matches[-1]
    raise SystemExit(f"Missing {GEN_SQL} — run build.py first")


def main() -> None:
    sql = resolve_sql()
    cmd = [
        "docker",
        "exec",
        "-i",
        MYSQL_CONTAINER,
        "mysql",
        f"-u{MYSQL_USER}",
        f"-p{MYSQL_PASS}",
        MYSQL_DB,
        "--default-character-set=utf8mb4",
    ]
    with sql.open("rb") as f:
        r = subprocess.run(cmd, stdin=f, capture_output=True)
    if r.returncode != 0:
        err = (r.stderr or r.stdout or b"").decode("utf-8", errors="replace")
        raise SystemExit(f"SQL apply failed:\n{err}")
    print(f"Applied {sql}", file=sys.stderr)


if __name__ == "__main__":
    main()
