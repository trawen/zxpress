#!/usr/bin/env python3
"""Apply generated vtrd ZX-Ревю migration (SQL + copy ZIP files).

Not run by build.py. Usage:
  python3 db/migration/vtrd_revue_files/apply.py
  python3 db/migration/vtrd_revue_files/apply.py --sql-only
  python3 db/migration/vtrd_revue_files/apply.py --files-only
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
OUT = HERE / "generated"
SQL = OUT / "01_books_files_activity.sql"
MANIFEST = OUT / "copy_manifest.tsv"
BOOKS_FILES_DIR = ROOT / "data" / "uploads" / "books-files"

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
MYSQL_USER = os.environ.get("DB_USER") or os.environ.get("MYSQL_USER", "zxpress_u")
MYSQL_PASS = os.environ.get("DB_PASS") or os.environ.get("MYSQL_PASSWORD", "changeme-app-password")
MYSQL_DB = os.environ.get("DB_NAME") or os.environ.get("MYSQL_DATABASE", "zxpress_db")


def run_mysql_file(sql_path: Path) -> None:
    cmd = [
        "docker",
        "exec",
        "-i",
        MYSQL_CONTAINER,
        "mysql",
        f"-u{MYSQL_USER}",
        f"-p{MYSQL_PASS}",
        "--default-character-set=utf8mb4",
        MYSQL_DB,
    ]
    with sql_path.open("rb") as f:
        r = subprocess.run(cmd, stdin=f, capture_output=True)
    if r.returncode != 0:
        err = (r.stderr or r.stdout or b"").decode("utf-8", errors="replace")
        raise SystemExit(f"SQL apply failed:\n{err}")
    print(f"Applied SQL: {sql_path}")


def copy_files() -> None:
    if not MANIFEST.is_file():
        raise SystemExit(f"Missing {MANIFEST} — run build.py first")
    BOOKS_FILES_DIR.mkdir(parents=True, exist_ok=True)
    n = 0
    skipped = 0
    for line in MANIFEST.read_text(encoding="utf-8").splitlines():
        if not line.strip() or line.startswith("#"):
            continue
        dest_name, src_s = line.split("\t", 1)
        src = Path(src_s)
        if not src.is_file():
            # Fallback: same name already under books-files or FILES_DIR env
            alt = BOOKS_FILES_DIR / dest_name
            env_dir = os.environ.get("ZXPRESS_VTRD_FILES_DIR")
            if env_dir:
                cand = Path(env_dir) / dest_name
                if cand.is_file():
                    src = cand
                elif alt.is_file():
                    skipped += 1
                    continue
                else:
                    print(f"skip missing: {src_s}", file=sys.stderr)
                    continue
            elif alt.is_file():
                skipped += 1
                continue
            else:
                print(f"skip missing: {src_s}", file=sys.stderr)
                continue
        dst = BOOKS_FILES_DIR / dest_name
        if dst.is_file() and dst.stat().st_size == src.stat().st_size:
            skipped += 1
            continue
        dst.write_bytes(src.read_bytes())
        n += 1
    print(f"Copied {n} files → {BOOKS_FILES_DIR} (skipped existing/same: {skipped})")


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--sql-only", action="store_true")
    ap.add_argument("--files-only", action="store_true")
    args = ap.parse_args()
    if not args.files_only:
        if not SQL.is_file():
            raise SystemExit(f"Missing {SQL} — run build.py first")
        run_mysql_file(SQL)
    if not args.sql_only:
        copy_files()


if __name__ == "__main__":
    main()
