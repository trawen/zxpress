#!/usr/bin/env python3
"""Apply generated issue-emulator screenshot migration (SQL + copy/convert files).

Not run by build.py. Usage:
  python3 db/migration/issue_emulator_screenshots/apply.py
  python3 db/migration/issue_emulator_screenshots/apply.py --sql-only
  python3 db/migration/issue_emulator_screenshots/apply.py --files-only
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
SQL = OUT / "01_screens_activity.sql"
MANIFEST = OUT / "copy_manifest.tsv"
SCREENS_DIR = ROOT / "data" / "uploads" / "screens" / "1"

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
        MYSQL_DB,
    ]
    with sql_path.open("rb") as f:
        r = subprocess.run(cmd, stdin=f, capture_output=True)
    if r.returncode != 0:
        err = (r.stderr or r.stdout or b"").decode("utf-8", errors="replace")
        raise SystemExit(f"SQL apply failed:\n{err}")
    print(f"Applied SQL: {sql_path}")


def find_cwebp() -> str:
    from shutil import which

    path = which("cwebp")
    if not path:
        raise SystemExit(
            "Need cwebp for lossless WebP (brew install webp). Pillow not required."
        )
    return path


def png_to_lossless_webp(cwebp: str, src: Path, dst: Path) -> None:
    r = subprocess.run(
        [cwebp, "-quiet", "-lossless", "-exact", str(src), "-o", str(dst)],
        capture_output=True,
    )
    if r.returncode != 0 or not dst.is_file():
        err = (r.stderr or r.stdout or b"").decode("utf-8", errors="replace")
        raise SystemExit(f"cwebp failed for {src}:\n{err}")


def copy_files() -> None:
    if not MANIFEST.is_file():
        raise SystemExit(f"Missing {MANIFEST} — run build.py first")
    cwebp = find_cwebp()
    SCREENS_DIR.mkdir(parents=True, exist_ok=True)
    n = 0
    for line in MANIFEST.read_text(encoding="utf-8").splitlines():
        if not line.strip() or line.startswith("#"):
            continue
        sid_s, src_s = line.split("\t", 1)
        sid = int(sid_s)
        src = Path(src_s)
        if not src.is_file():
            print(f"skip missing: {src}", file=sys.stderr)
            continue
        png_dst = SCREENS_DIR / f"{sid}.png"
        webp_dst = SCREENS_DIR / f"{sid}.webp"
        png_dst.write_bytes(src.read_bytes())
        png_to_lossless_webp(cwebp, src, webp_dst)
        n += 1
        if n % 100 == 0:
            print(f"  … {n}")
    print(f"Copied/converted {n} screens → {SCREENS_DIR}")


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
