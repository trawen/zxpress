#!/usr/bin/env python3
"""Fill echos_zxnet.name_from_en / name_to_en via PK updates (tiny MySQL /tmp)."""

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
MAX_LEN = 32
WORKERS = 8
MAP_PATH = Path("/tmp/zxnet_name_en_map.json")

MANUAL = {
    "Всем": "All",
    "Всем!": "All!",
    "Всем !": "All!",
    "Алл": "All",
    "Аll": "All",
    "All": "All",
    "all": "all",
    "Аll!": "All!",
}


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


def mysql_exec_sql(sql: str) -> None:
    p = subprocess.run(
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
        input=sql.encode("utf-8"),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if p.returncode != 0:
        raise RuntimeError(p.stderr.decode("utf-8", errors="replace")[:800])


def sql_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def clip(s: str) -> str:
    s = s.strip().replace("\x00", "")
    if len(s) <= MAX_LEN:
        return s
    return s[:MAX_LEN]


def translate(text: str, retries: int = 5) -> str:
    if text in MANUAL:
        return MANUAL[text]
    if not CYR.search(text):
        return clip(text)
    url = (
        "https://translate.googleapis.com/translate_a/single"
        f"?client=gtx&sl=ru&tl=en&dt=t&q={urllib.parse.quote(text)}"
    )
    last_err: Exception | None = None
    for attempt in range(retries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "zxpress-name-en/1.0"})
            with urllib.request.urlopen(req, timeout=30) as r:
                data = json.loads(r.read().decode("utf-8"))
            parts = []
            for chunk in data[0] or []:
                if chunk and chunk[0]:
                    parts.append(chunk[0])
            out = "".join(parts).strip()
            if not out:
                raise RuntimeError("empty")
            return clip(out)
        except Exception as e:  # noqa: BLE001
            last_err = e
            time.sleep(0.5 * (attempt + 1))
    raise RuntimeError(f"{text!r}: {last_err}")


def build_mapping() -> dict[str, str]:
    if MAP_PATH.exists():
        mapping = json.loads(MAP_PATH.read_text(encoding="utf-8"))
        print(f"loaded cached map={len(mapping)}", flush=True)
        return mapping

    raw = mysql_query(
        "SELECT hx FROM ("
        "  SELECT DISTINCT HEX(name_from) AS hx FROM echos_zxnet"
        "  UNION"
        "  SELECT DISTINCT HEX(name_to) AS hx FROM echos_zxnet"
        ") t ORDER BY hx"
    )
    names = []
    for hx in raw.splitlines():
        if not hx.strip():
            continue
        names.append(bytes.fromhex(hx).decode("utf-8", errors="replace"))
    print(f"unique_names={len(names)}", flush=True)

    mapping: dict[str, str] = {}
    pending: list[str] = []
    for name in names:
        if name in MANUAL:
            mapping[name] = MANUAL[name]
        elif not CYR.search(name):
            mapping[name] = clip(name)
        else:
            pending.append(name)

    print(f"to_translate={len(pending)} copy_as_is={len(mapping)}", flush=True)
    failed = 0

    def work(name: str) -> tuple[str, str | None]:
        try:
            return name, translate(name)
        except Exception as e:  # noqa: BLE001
            print(f"FAIL {name!r}: {e}", flush=True)
            return name, None

    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        futs = [pool.submit(work, n) for n in pending]
        done = 0
        for fut in as_completed(futs):
            name, en = fut.result()
            done += 1
            if en is None:
                failed += 1
            else:
                mapping[name] = en
            if done % 50 == 0 or done == len(pending):
                print(f"translated {done}/{len(pending)} failed={failed}", flush=True)

    MAP_PATH.write_text(json.dumps(mapping, ensure_ascii=False, indent=2, sort_keys=True), encoding="utf-8")
    print(f"saved map failed={failed}", flush=True)
    if failed:
        raise SystemExit(1)
    return mapping


def main() -> int:
    mapping = build_mapping()

    print("dumping id/name pairs...", flush=True)
    raw = mysql_query(
        "SELECT id, HEX(name_from), HEX(name_to) FROM echos_zxnet "
        "WHERE name_from_en IS NULL OR name_from_en='' "
        "   OR name_to_en IS NULL OR name_to_en='' "
        "ORDER BY id"
    )
    rows: list[tuple[int, str, str]] = []
    for line in raw.splitlines():
        if not line.strip():
            continue
        id_s, hx_from, hx_to = line.split("\t", 2)
        rows.append(
            (
                int(id_s),
                bytes.fromhex(hx_from).decode("utf-8", errors="replace"),
                bytes.fromhex(hx_to).decode("utf-8", errors="replace"),
            )
        )
    print(f"rows_to_update={len(rows)}", flush=True)

    batch: list[str] = ["SET NAMES utf8mb4;"]
    flushed = 0
    missing = 0

    def flush() -> None:
        nonlocal batch, flushed
        if len(batch) <= 1:
            return
        mysql_exec_sql("\n".join(batch) + "\n")
        flushed += len(batch) - 1
        batch = ["SET NAMES utf8mb4;"]
        print(f"flushed updates~{flushed}", flush=True)

    for msg_id, name_from, name_to in rows:
        en_from = mapping.get(name_from)
        en_to = mapping.get(name_to)
        if en_from is None or en_to is None:
            missing += 1
            continue
        batch.append(
            f"UPDATE echos_zxnet SET name_from_en={sql_quote(en_from)}, "
            f"name_to_en={sql_quote(en_to)} WHERE id={msg_id} LIMIT 1;"
        )
        if len(batch) >= 200:
            flush()

    flush()

    stats = mysql_query(
        "SELECT "
        "SUM(name_from_en IS NOT NULL AND name_from_en<>''), "
        "SUM(name_to_en IS NOT NULL AND name_to_en<>''), "
        "SUM(name_from_en IS NULL OR name_from_en=''), "
        "SUM(name_to_en IS NULL OR name_to_en='') "
        "FROM echos_zxnet"
    ).strip()
    print("stats from_en/to_en/from_missing/to_missing:", stats, flush=True)
    print("missing_in_map_rows:", missing, flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
