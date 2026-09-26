#!/usr/bin/env python3
"""Apply gzipped echos_zxnet text_en migration parts.

Usage:
  python3 db/migration/echos_zxnet_text_en_fill/apply.py
  python3 db/migration/echos_zxnet_text_en_fill/apply.py --from 3
"""

from __future__ import annotations

import argparse
import gzip
import os
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
PARTS = HERE / "generated" / "parts"

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
MYSQL_USER = os.environ.get("DB_USER") or os.environ.get("MYSQL_USER", "zxpress_u")
MYSQL_PASS = os.environ.get("DB_PASS") or os.environ.get("MYSQL_PASSWORD", "changeme-app-password")
MYSQL_DB = os.environ.get("DB_NAME") or os.environ.get("MYSQL_DATABASE", "zxpress_db")


def mysql_cmd() -> list[str]:
    return [
        "docker",
        "exec",
        "-i",
        MYSQL_CONTAINER,
        "mysql",
        f"-u{MYSQL_USER}",
        f"-p{MYSQL_PASS}",
        MYSQL_DB,
        "--default-character-set=utf8mb4",
        "--max_allowed_packet=64M",
    ]


def apply_gz(path: Path) -> None:
    with gzip.open(path, "rb") as f:
        data = f.read()
    r = subprocess.run(mysql_cmd(), input=data, capture_output=True)
    if r.returncode != 0:
        err = (r.stderr or r.stdout or b"").decode("utf-8", errors="replace")
        raise SystemExit(f"Failed {path.name}:\n{err[:2000]}")


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--from",
        dest="from_idx",
        type=int,
        default=0,
        help="Skip data parts with index < N (setup always runs if present)",
    )
    args = ap.parse_args()

    if not PARTS.is_dir():
        raise SystemExit(f"Missing {PARTS} — run build.py first")

    files = sorted(PARTS.glob("*.sql.gz"))
    if not files:
        raise SystemExit(f"No *.sql.gz in {PARTS}")

    for path in files:
        name = path.name
        if name[:2].isdigit():
            idx = int(name[:2])
            if args.from_idx and idx < args.from_idx and name != "00_setup.sql.gz":
                print(f"skip {name}", flush=True)
                continue
        print(f"apply {name} ({path.stat().st_size/1024/1024:.2f} MiB gz)…", flush=True)
        apply_gz(path)
    print("done", flush=True)


if __name__ == "__main__":
    main()
