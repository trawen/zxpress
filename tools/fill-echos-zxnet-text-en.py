#!/usr/bin/env python3
"""Fill echos_zxnet.text_en from text (Google Translate gtx for Cyrillic).

Usage:
  python3 tools/fill-echos-zxnet-text-en.py
  python3 tools/fill-echos-zxnet-text-en.py --echo=zx.spectrum --echo=real.speccy

Resumable: skips rows that already have text_en.
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
FAILS = Path("/tmp/zxnet_text_en_fails.json")
WORKERS = 8
CHUNK = 1400
FLUSH_EVERY = 80
BATCH = 160

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


def mysql_exec_sql(sql: str) -> None:
    p = subprocess.run(
        mysql_cmd_base(),
        input=sql.encode("utf-8"),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if p.returncode != 0:
        err = p.stderr.decode("utf-8", errors="replace")
        raise RuntimeError(f"mysql failed rc={p.returncode}: {err[:1000]}")


def sql_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def echo_ids_for(titles: list[str]) -> list[int]:
    ids: list[int] = []
    for title in titles:
        raw = mysql_query(
            "SELECT id FROM echos_titles2 WHERE title=" + sql_quote(title) + " LIMIT 1"
        ).strip()
        if not raw:
            raise SystemExit(f"echo not found: {title!r}")
        ids.append(int(raw.splitlines()[0]))
    return ids


def echo_scope_sql(echo_ids: list[int] | None) -> str:
    if not echo_ids:
        return ""
    return " AND echo_id IN (" + ",".join(str(i) for i in echo_ids) + ")"


def translate_chunk(text: str, retries: int = 5) -> str:
    if text == "" or not CYR.search(text):
        return text
    url = (
        "https://translate.googleapis.com/translate_a/single"
        f"?client=gtx&sl=auto&tl=en&dt=t&q={urllib.parse.quote(text)}"
    )
    last_err: Exception | None = None
    for attempt in range(retries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "zxpress-text-en/1.0"})
            with urllib.request.urlopen(req, timeout=60) as r:
                data = json.loads(r.read().decode("utf-8"))
            parts = []
            for chunk in data[0] or []:
                if chunk and chunk[0]:
                    parts.append(chunk[0])
            out = "".join(parts)
            if out.strip() == "" and text.strip() != "":
                raise RuntimeError("empty translation")
            return out
        except Exception as e:  # noqa: BLE001
            last_err = e
            time.sleep(0.8 * (attempt + 1))
    raise RuntimeError(f"translate failed: {last_err}")


def split_chunks(text: str, limit: int = CHUNK) -> list[str]:
    if len(text) <= limit:
        return [text]
    chunks: list[str] = []
    i = 0
    n = len(text)
    while i < n:
        if n - i <= limit:
            chunks.append(text[i:])
            break
        end = i + limit
        nl = text.rfind("\n", i + limit // 3, end)
        if nl >= i:
            end = nl + 1
        else:
            sp = text.rfind(" ", i + limit // 3, end)
            if sp >= i:
                end = sp + 1
        chunks.append(text[i:end])
        i = end
    return chunks


UUE_MARK = re.compile(
    r"(?i)(begin\s+\d{3}\s+\S+|section\s+\d+\s+of\s+file|iS-UUE|table\s+zip)"
)


def looks_like_binary_payload(text: str) -> bool:
    if "\x00" in text:
        return True
    if UUE_MARK.search(text) and len(text) > 800:
        return True
    lines = [ln for ln in text.splitlines() if ln.strip()]
    if len(lines) >= 20:
        uueish = sum(1 for ln in lines if re.match(r"^M[\x20-\x60]{40,}", ln))
        if uueish >= max(8, len(lines) // 4):
            return True
    return False


def translate_message(text: str) -> str:
    normalized = text.replace("\r\n", "\n").replace("\n\r", "\n").replace("\r", "\n")
    if looks_like_binary_payload(normalized):
        return normalized.replace("\x00", "")
    out = "".join(translate_chunk(c) for c in split_chunks(normalized))
    return out.replace("\x00", "")


def flush_updates(updates: dict[int, str]) -> None:
    if not updates:
        return
    parts = ["SET NAMES utf8mb4;", "START TRANSACTION;"]
    for id_, en in updates.items():
        en = en.replace("\x00", "")
        parts.append(
            f"UPDATE echos_zxnet SET text_en={sql_quote(en)} "
            f"WHERE id={id_} AND (text_en IS NULL OR text_en='') LIMIT 1;"
        )
    parts.append("COMMIT;")
    try:
        mysql_exec_sql("\n".join(parts) + "\n")
        return
    except Exception as batch_err:
        print(f"batch flush failed, falling back per-row: {batch_err}", flush=True)
    for id_, en in updates.items():
        en = en.replace("\x00", "")
        try:
            mysql_exec_sql(
                "SET NAMES utf8mb4;\n"
                f"UPDATE echos_zxnet SET text_en={sql_quote(en)} "
                f"WHERE id={id_} AND (text_en IS NULL OR text_en='') LIMIT 1;\n"
            )
        except Exception as row_err:
            print(f"FAIL flush id={id_}: {row_err}", flush=True)


def load_pending(echo_ids: list[int] | None) -> list[tuple[int, str]]:
    scope = echo_scope_sql(echo_ids)
    print("dumping pending Cyrillic messages (HEX)...", flush=True)
    raw = mysql_query(
        "SELECT id, HEX(text) FROM echos_zxnet "
        "WHERE text REGEXP '[А-Яа-яЁё]' "
        "  AND (text_en IS NULL OR text_en = '') "
        f"{scope} "
        "ORDER BY id"
    )
    rows: list[tuple[int, str]] = []
    for line in raw.splitlines():
        if not line.strip():
            continue
        id_s, hx = line.split("\t", 1)
        rows.append((int(id_s), bytes.fromhex(hx).decode("utf-8", errors="replace")))
    print(f"pending={len(rows)}", flush=True)
    return rows


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "--echo",
        action="append",
        dest="echoes",
        default=None,
        help="Limit to echo title(s). Repeatable. Empty = all echoes.",
    )
    args = ap.parse_args()

    echo_ids: list[int] | None = None
    if args.echoes:
        echo_ids = echo_ids_for(args.echoes)
        print(f"echoes={args.echoes} ids={echo_ids}", flush=True)

    scope = echo_scope_sql(echo_ids)
    print("copying non-Cyrillic texts...", flush=True)
    mysql_exec_sql(
        "SET NAMES utf8mb4;\n"
        "UPDATE echos_zxnet\n"
        "SET text_en = text\n"
        "WHERE (text_en IS NULL OR text_en = '')\n"
        "  AND text NOT REGEXP '[А-Яа-яЁё]'\n"
        f"  {scope};\n"
    )

    pending = load_pending(echo_ids)
    fails: dict[str, str] = {}
    if FAILS.exists():
        fails = json.loads(FAILS.read_text(encoding="utf-8"))

    done = 0
    failed = 0
    pending_updates: dict[int, str] = {}
    t0 = time.time()

    def work(item: tuple[int, str]) -> tuple[int, str | None, str | None]:
        msg_id, src = item
        try:
            return msg_id, translate_message(src), None
        except Exception as e:  # noqa: BLE001
            return msg_id, None, str(e)

    for start in range(0, len(pending), BATCH):
        batch = pending[start : start + BATCH]
        with ThreadPoolExecutor(max_workers=WORKERS) as pool:
            futs = [pool.submit(work, item) for item in batch]
            for fut in as_completed(futs):
                msg_id, en, err = fut.result()
                done += 1
                if en is None:
                    failed += 1
                    fails[str(msg_id)] = err or "unknown"
                    print(f"FAIL id={msg_id}: {err}", flush=True)
                else:
                    pending_updates[msg_id] = en

                if len(pending_updates) >= FLUSH_EVERY:
                    flush_updates(pending_updates)
                    pending_updates.clear()
                    FAILS.write_text(json.dumps(fails, ensure_ascii=False), encoding="utf-8")
                    elapsed = max(1.0, time.time() - t0)
                    rate = done / elapsed
                    eta = (len(pending) - done) / rate if rate > 0 else 0
                    print(
                        f"progress {done}/{len(pending)} failed={failed} "
                        f"rate={rate:.2f}/s eta={eta/3600:.1f}h",
                        flush=True,
                    )

        if pending_updates:
            flush_updates(pending_updates)
            pending_updates.clear()
            FAILS.write_text(json.dumps(fails, ensure_ascii=False), encoding="utf-8")
        print(f"batch {min(start + BATCH, len(pending))}/{len(pending)} failed={failed}", flush=True)

    stats = mysql_query(
        "SELECT COUNT(*), "
        "SUM(text_en IS NOT NULL AND text_en<>''), "
        "SUM(text REGEXP '[А-Яа-яЁё]' AND (text_en IS NULL OR text_en='')), "
        "SUM(text NOT REGEXP '[А-Яа-яЁё]' AND (text_en IS NULL OR text_en='')) "
        f"FROM echos_zxnet WHERE 1=1 {scope}"
    ).strip()
    print("stats total/has_en/cyr_missing/noncyr_missing:", stats, flush=True)
    print("failed:", failed, flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
