#!/usr/bin/env python3
"""Translate missing article bodies into data/content-store/articles-eng/{id}.

Uses Google Translate gtx (same approach as tools/fill-echos-zxnet-text-en.py).
Does not need MySQL — works purely on content-store files.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RU_DIR = ROOT / "data" / "content-store" / "articles"
EN_DIR = ROOT / "data" / "content-store" / "articles-eng"
PROGRESS = Path("/tmp/articles_eng_bodies_progress.json")
FAILS = Path("/tmp/articles_eng_bodies_fails.json")

CYR = re.compile(r"[А-Яа-яЁё]")
WORKERS = 6
CHUNK = 1400
FLUSH_EVERY = 40


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
            req = urllib.request.Request(url, headers={"User-Agent": "zxpress-articles-eng/1.0"})
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
        # Prefer splitting on tag boundaries / newlines / spaces.
        tag = text.rfind(">", i + limit // 3, end)
        nl = text.rfind("\n", i + limit // 3, end)
        sp = text.rfind(" ", i + limit // 3, end)
        cut = max(tag, nl, sp)
        if cut >= i:
            end = cut + 1
        chunks.append(text[i:end])
        i = end
    return chunks


def translate_body(text: str) -> str:
    normalized = text.replace("\r\n", "\n").replace("\n\r", "\n").replace("\r", "\n")
    if not CYR.search(normalized):
        return normalized
    out = "".join(translate_chunk(c) for c in split_chunks(normalized))
    return out.replace("\x00", "")


def pending_ids(limit: int = 0, only_gt: int = 0) -> list[int]:
    ru = {int(p.name) for p in RU_DIR.iterdir() if p.is_file() and p.name.isdigit()}
    en = {int(p.name) for p in EN_DIR.iterdir() if p.is_file() and p.name.isdigit()}
    missing = sorted(ru - en)
    if only_gt > 0:
        missing = [i for i in missing if i > only_gt]
    # Prefer articles that actually contain Cyrillic.
    out: list[int] = []
    for aid in missing:
        raw = (RU_DIR / str(aid)).read_bytes()
        try:
            text = raw.decode("utf-8")
        except UnicodeDecodeError:
            text = raw.decode("cp1251", errors="replace")
        if not CYR.search(text):
            # Non-Cyrillic: just copy as English body.
            EN_DIR.mkdir(parents=True, exist_ok=True)
            (EN_DIR / str(aid)).write_text(text.replace("\x00", ""), encoding="utf-8")
            continue
        out.append(aid)
        if limit > 0 and len(out) >= limit:
            break
    return out


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--limit", type=int, default=0, help="Max Cyrillic articles to translate (0=all)")
    ap.add_argument("--only-gt", type=int, default=0, help="Only article ids greater than N")
    ap.add_argument("--workers", type=int, default=WORKERS)
    args = ap.parse_args()

    EN_DIR.mkdir(parents=True, exist_ok=True)
    pending = pending_ids(limit=args.limit, only_gt=args.only_gt)
    print(f"pending_cyrillic={len(pending)} workers={args.workers}", flush=True)

    done_map: dict[str, str] = {}
    if PROGRESS.exists():
        done_map = json.loads(PROGRESS.read_text(encoding="utf-8"))
    fails: dict[str, str] = {}
    if FAILS.exists():
        fails = json.loads(FAILS.read_text(encoding="utf-8"))

    todo = [aid for aid in pending if str(aid) not in done_map and not (EN_DIR / str(aid)).exists()]
    print(f"todo={len(todo)} already_done_cache={len(done_map)}", flush=True)

    done = 0
    failed = 0

    def work(aid: int) -> tuple[int, bool, str | None]:
        path = RU_DIR / str(aid)
        raw = path.read_bytes()
        try:
            text = raw.decode("utf-8")
        except UnicodeDecodeError:
            text = raw.decode("cp1251", errors="replace")
        try:
            en = translate_body(text)
            (EN_DIR / str(aid)).write_text(en, encoding="utf-8")
            return aid, True, None
        except Exception as e:  # noqa: BLE001
            return aid, False, str(e)

    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as pool:
        futs = [pool.submit(work, aid) for aid in todo]
        for fut in as_completed(futs):
            aid, ok, err = fut.result()
            done += 1
            if ok:
                done_map[str(aid)] = "ok"
            else:
                failed += 1
                fails[str(aid)] = err or "unknown"
                print(f"FAIL id={aid}: {err}", flush=True)
            if done % FLUSH_EVERY == 0 or done == len(todo):
                PROGRESS.write_text(json.dumps(done_map, ensure_ascii=False), encoding="utf-8")
                FAILS.write_text(json.dumps(fails, ensure_ascii=False), encoding="utf-8")
                print(f"progress {done}/{len(todo)} failed={failed}", flush=True)

    print(f"done={done} failed={failed}", flush=True)
    print(f"en_files_now={sum(1 for p in EN_DIR.iterdir() if p.is_file() and p.name.isdigit())}", flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
