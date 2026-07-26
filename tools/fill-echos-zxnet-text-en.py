#!/usr/bin/env python3
"""Fill echos_zxnet.text_en from text (Google Translate gtx for Cyrillic)."""

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
FAILS = Path("/tmp/zxnet_text_en_fails.json")
WORKERS = 8
CHUNK = 1400
FLUSH_EVERY = 80
BATCH = 160


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
        err = p.stderr.decode("utf-8", errors="replace")
        raise RuntimeError(f"mysql failed rc={p.returncode}: {err[:1000]}")


def sql_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


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
    # Dense non-text lines typical of UUE bodies.
    lines = [ln for ln in text.splitlines() if ln.strip()]
    if len(lines) >= 20:
        uueish = sum(1 for ln in lines if re.match(r"^M[\x20-\x60]{40,}", ln))
        if uueish >= max(8, len(lines) // 4):
            return True
    return False


def translate_message(text: str) -> str:
    normalized = text.replace("\r\n", "\n").replace("\n\r", "\n").replace("\r", "\n")
    if looks_like_binary_payload(normalized):
        # Keep original payload; translating UUE/binary dumps is useless and can
        # introduce NUL / corruption.
        return normalized.replace("\x00", "")
    out = "".join(translate_chunk(c) for c in split_chunks(normalized))
    return out.replace("\x00", "")


def flush_updates(updates: dict[int, str]) -> None:
    if not updates:
        return
    parts = ["SET NAMES utf8mb4;", "START TRANSACTION;"]
    for id_, en in updates.items():
        en = en.replace("\x00", "")
        parts.append(f"UPDATE echos_zxnet SET text_en={sql_quote(en)} WHERE id={id_} LIMIT 1;")
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
                f"UPDATE echos_zxnet SET text_en={sql_quote(en)} WHERE id={id_} LIMIT 1;\n"
            )
        except Exception as row_err:
            print(f"FAIL flush id={id_}: {row_err}", flush=True)


def load_pending() -> list[tuple[int, str]]:
    print("dumping pending Cyrillic messages (HEX)...", flush=True)
    raw = mysql_query(
        "SELECT id, HEX(text) FROM echos_zxnet "
        "WHERE text REGEXP '[А-Яа-яЁё]' "
        "  AND (text_en IS NULL OR text_en = '') "
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
    print("copying non-Cyrillic texts...", flush=True)
    mysql_exec_sql(
        "SET NAMES utf8mb4;\n"
        "UPDATE echos_zxnet\n"
        "SET text_en = text\n"
        "WHERE (text_en IS NULL OR text_en = '')\n"
        "  AND text NOT REGEXP '[А-Яа-яЁё]';\n"
    )

    pending = load_pending()
    fails: dict[str, str] = {}
    if FAILS.exists():
        fails = json.loads(FAILS.read_text(encoding="utf-8"))

    done = 0
    failed = 0
    pending_updates: dict[int, str] = {}

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
                    print(f"progress {done}/{len(pending)} failed={failed}", flush=True)

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
        "FROM echos_zxnet"
    ).strip()
    print("stats total/has_en/cyr_missing/noncyr_missing:", stats, flush=True)
    print("failed:", failed, flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
