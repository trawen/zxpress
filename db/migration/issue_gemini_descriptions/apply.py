#!/usr/bin/env python3
"""Apply generated Gemini issue-description SQL. Not run by build.py.

Usage:
  python3 db/migration/issue_gemini_descriptions/apply.py
"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
SQL = HERE / "generated" / "01_update_issue_descriptions.sql"

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
MYSQL_USER = os.environ.get("DB_USER") or os.environ.get("MYSQL_USER", "zxpress_u")
MYSQL_PASS = os.environ.get("DB_PASS") or os.environ.get("MYSQL_PASSWORD", "changeme-app-password")
MYSQL_DB = os.environ.get("DB_NAME") or os.environ.get("MYSQL_DATABASE", "zxpress_db")


def main() -> None:
    if not SQL.is_file():
        raise SystemExit(f"Missing {SQL} — run build.py first")
    cmd = [
        "docker",
        "exec",
        "-i",
        MYSQL_CONTAINER,
        "mysql",
        f"-u{MYSQL_USER}",
        f"-p{MYSQL_PASS}",
        MYSQL_DB,
    ]
    with SQL.open("rb") as f:
        r = subprocess.run(cmd, stdin=f, capture_output=True)
    if r.returncode != 0:
        err = (r.stderr or r.stdout or b"").decode("utf-8", errors="replace")
        raise SystemExit(f"SQL apply failed:\n{err}")
    print(f"Applied {SQL}", file=sys.stderr)


if __name__ == "__main__":
    main()
