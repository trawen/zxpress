#!/usr/bin/env python3
"""Build batched SQL for echos_zxnet.text_en (zx.spectrum + real.speccy).

Streams HEX(text_en) from local Docker MySQL, writes gzipped part files with
bounded INSERT … VALUES batches (safe for max_allowed_packet).

Usage:
  MYSQL_ROOT_PASSWORD=… python3 db/migration/echos_zxnet_text_en_fill/build.py

Output (gitignored parts):
  generated/parts/00_setup.sql.gz
  generated/parts/01_….sql.gz …
  generated/parts/zz_finish.sql.gz
  generated/summary.json
"""

from __future__ import annotations

import argparse
import gzip
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
GEN = HERE / "generated"
PARTS = GEN / "parts"
DEFAULT_ECHOES = ("zx.spectrum", "real.speccy")

# Keep each INSERT statement under this many payload bytes (quoted).
MAX_INSERT_BYTES = 512 * 1024
# Rotate to a new part file around this uncompressed size.
MAX_PART_BYTES = 4 * 1024 * 1024
# Hard cap rows per INSERT (extra safety).
MAX_ROWS_PER_INSERT = 80

MYSQL_CONTAINER = os.environ.get("ZXPRESS_MYSQL_CONTAINER", "zxpress_db")
ROOT_PW = os.environ.get("MYSQL_ROOT_PASSWORD", "changeme-root-password")
MYSQL_DB = os.environ.get("MYSQL_DATABASE") or os.environ.get("DB_NAME", "zxpress_db")

STAGING = "_mig_echos_zxnet_text_en"


def sql_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


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


def echo_ids(titles: tuple[str, ...]) -> list[tuple[str, int]]:
    out: list[tuple[str, int]] = []
    for title in titles:
        raw = mysql_query(
            f"SELECT id FROM echos_titles2 WHERE title={sql_quote(title)} LIMIT 1"
        ).strip()
        if not raw:
            raise SystemExit(f"echo not found: {title!r}")
        out.append((title, int(raw.splitlines()[0])))
    return out


def write_gz(path: Path, text: str) -> int:
    data = text.encode("utf-8")
    with gzip.open(path, "wb", compresslevel=6) as f:
        f.write(data)
    return len(data)


class PartWriter:
    def __init__(
        self,
        echoes: list[tuple[str, int]],
        *,
        max_insert_bytes: int,
        max_part_bytes: int,
        max_rows_per_insert: int,
    ) -> None:
        self.echoes = echoes
        self.max_insert_bytes = max_insert_bytes
        self.max_part_bytes = max_part_bytes
        self.max_rows_per_insert = max_rows_per_insert
        self.part_idx = 0
        self.parts_meta: list[dict[str, object]] = []
        self._buf: list[str] = []
        self._buf_bytes = 0
        self._rows_in_part = 0
        self._insert_vals: list[str] = []
        self._insert_bytes = 0
        self.total_rows = 0
        PARTS.mkdir(parents=True, exist_ok=True)
        for old in PARTS.glob("*.sql.gz"):
            old.unlink()

    def _echo_vars_sql(self) -> str:
        lines = ["SET NAMES utf8mb4;\n"]
        for title, _ in self.echoes:
            var = "@echo_" + title.replace(".", "_") + "_id"
            lines.append(
                f"SET {var} := (SELECT id FROM echos_titles2 WHERE title={sql_quote(title)} LIMIT 1);\n"
            )
        return "".join(lines)

    def write_setup(self) -> None:
        body = (
            self._echo_vars_sql()
            + f"DROP TABLE IF EXISTS `{STAGING}`;\n"
            + f"CREATE TABLE `{STAGING}` (\n"
            + "  `id` INT UNSIGNED NOT NULL,\n"
            + "  `text_en` LONGTEXT NOT NULL,\n"
            + "  PRIMARY KEY (`id`)\n"
            + ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n"
        )
        path = PARTS / "00_setup.sql.gz"
        nbytes = write_gz(path, body)
        self.parts_meta.append(
            {"file": path.name, "kind": "setup", "rows": 0, "bytes_uncompressed": nbytes}
        )

    def write_finish(self) -> None:
        self._flush_insert()
        self._flush_part(final=True)
        body = (
            "SET NAMES utf8mb4;\n"
            f"DROP TABLE IF EXISTS `{STAGING}`;\n"
        )
        path = PARTS / "zz_finish.sql.gz"
        nbytes = write_gz(path, body)
        self.parts_meta.append(
            {"file": path.name, "kind": "finish", "rows": 0, "bytes_uncompressed": nbytes}
        )

    def _flush_insert(self) -> None:
        if not self._insert_vals:
            return
        stmt = (
            f"INSERT INTO `{STAGING}` (`id`, `text_en`) VALUES\n"
            + ",\n".join(self._insert_vals)
            + ";\n"
        )
        self._buf.append(stmt)
        self._buf_bytes += len(stmt.encode("utf-8"))
        self._insert_vals = []
        self._insert_bytes = 0

    def _apply_staging_sql(self) -> str:
        # echo_id filter via pre-set session vars from setup; re-set in each part
        # in case connections are not persistent across files.
        ids_list = ", ".join(
            f"(SELECT id FROM echos_titles2 WHERE title={sql_quote(t)} LIMIT 1)"
            for t, _ in self.echoes
        )
        return (
            f"UPDATE `echos_zxnet` z\n"
            f"INNER JOIN `{STAGING}` m ON m.id = z.id\n"
            f"SET z.text_en = m.text_en\n"
            f"WHERE (z.text_en IS NULL OR z.text_en = '')\n"
            f"  AND z.echo_id IN ({ids_list});\n"
            f"TRUNCATE TABLE `{STAGING}`;\n"
        )

    def _flush_part(self, *, final: bool = False) -> None:
        self._flush_insert()
        if not self._buf and not final:
            return
        if not self._buf:
            return
        self.part_idx += 1
        apply = self._apply_staging_sql()
        body = (
            self._echo_vars_sql()
            + "START TRANSACTION;\n"
            + "".join(self._buf)
            + apply
            + "COMMIT;\n"
        )
        path = PARTS / f"{self.part_idx:02d}_data.sql.gz"
        nbytes = write_gz(path, body)
        self.parts_meta.append(
            {
                "file": path.name,
                "kind": "data",
                "rows": self._rows_in_part,
                "bytes_uncompressed": nbytes,
            }
        )
        print(
            f"wrote {path.name} rows={self._rows_in_part} uncompressed={nbytes/1024/1024:.2f}MiB",
            flush=True,
        )
        self._buf = []
        self._buf_bytes = 0
        self._rows_in_part = 0

    def add_row(self, id_: int, text_en: str) -> None:
        val = f"({id_},{sql_quote(text_en)})"
        val_len = len(val.encode("utf-8"))
        if self._insert_vals and (
            self._insert_bytes + val_len > self.max_insert_bytes
            or len(self._insert_vals) >= self.max_rows_per_insert
        ):
            self._flush_insert()
        if self._buf_bytes + self._insert_bytes + val_len > self.max_part_bytes and (
            self._buf or self._insert_vals
        ):
            self._flush_part()
        self._insert_vals.append(val)
        self._insert_bytes += val_len
        self._rows_in_part += 1
        self.total_rows += 1


def iter_rows(echo_id_list: list[int]):
    ids_sql = ",".join(str(i) for i in echo_id_list)
    print("streaming HEX(text_en) from MySQL…", flush=True)
    proc = subprocess.Popen(
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
            "SELECT id, HEX(text_en) FROM echos_zxnet "
            f"WHERE echo_id IN ({ids_sql}) "
            "AND text_en IS NOT NULL AND text_en <> '' "
            "ORDER BY id",
        ],
        stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL,
        text=True,
        bufsize=1 << 20,
    )
    assert proc.stdout is not None
    n = 0
    for line in proc.stdout:
        line = line.rstrip("\n")
        if not line:
            continue
        id_s, hx = line.split("\t", 1)
        text = bytes.fromhex(hx).decode("utf-8", errors="replace").replace("\x00", "")
        n += 1
        if n % 5000 == 0:
            print(f"  streamed {n}…", flush=True)
        yield int(id_s), text
    rc = proc.wait()
    if rc != 0:
        raise SystemExit(f"mysql stream failed rc={rc}")


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--echo",
        action="append",
        dest="echoes",
        default=None,
        help="Echo title (repeatable). Default: zx.spectrum + real.speccy",
    )
    ap.add_argument(
        "--max-insert-bytes",
        type=int,
        default=MAX_INSERT_BYTES,
        help=f"Max quoted payload per INSERT (default {MAX_INSERT_BYTES})",
    )
    ap.add_argument(
        "--max-part-bytes",
        type=int,
        default=MAX_PART_BYTES,
        help=f"Rotate part file around this size (default {MAX_PART_BYTES})",
    )
    args = ap.parse_args()

    titles = tuple(args.echoes) if args.echoes else DEFAULT_ECHOES
    echoes = echo_ids(titles)
    echo_id_list = [eid for _, eid in echoes]

    GEN.mkdir(parents=True, exist_ok=True)
    writer = PartWriter(
        echoes,
        max_insert_bytes=args.max_insert_bytes,
        max_part_bytes=args.max_part_bytes,
        max_rows_per_insert=MAX_ROWS_PER_INSERT,
    )
    writer.write_setup()

    for id_, text_en in iter_rows(echo_id_list):
        writer.add_row(id_, text_en)

    writer.write_finish()

    stamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    gz_total = sum(p.stat().st_size for p in PARTS.glob("*.sql.gz"))
    summary = {
        "echoes": [{"title": t, "echo_id": i} for t, i in echoes],
        "rows": writer.total_rows,
        "parts": writer.parts_meta,
        "max_insert_bytes": args.max_insert_bytes,
        "max_part_bytes": args.max_part_bytes,
        "max_rows_per_insert": MAX_ROWS_PER_INSERT,
        "parts_dir": str(PARTS.relative_to(HERE.parent.parent.parent)),
        "parts_gz_bytes": gz_total,
        "generated_at_utc": stamp,
        "apply": "python3 db/migration/echos_zxnet_text_en_fill/apply.py",
    }
    (GEN / "summary.json").write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    tar_path = GEN / "echos_zxnet_text_en_parts.tar.gz"
    subprocess.check_call(
        ["tar", "-czf", str(tar_path), "-C", str(PARTS), "."],
    )
    summary["tarball"] = str(tar_path.relative_to(HERE.parent.parent.parent))
    summary["tarball_bytes"] = tar_path.stat().st_size
    (GEN / "summary.json").write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    print(json.dumps(summary, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
