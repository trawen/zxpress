#!/usr/bin/env python3
"""Seed site/cli/imports/restore_i18n/*.json from local/work/RESTORE_texts (cs + ru).

Does not generate meta descriptions — use db/migration/tools/fill_meta_descriptions.py.

  python3 db/migration/restore_articles/seed_i18n.py
  python3 db/migration/restore_articles/seed_i18n.py --force
"""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
TEXTS = ROOT / "local/work/RESTORE_texts"
OUT = ROOT / "site/cli/imports/restore_i18n"
MIRROR = Path(__file__).resolve().parent / "i18n"

PRESS = "Restore"
PRESS_ID = 326  # ezine_id / legacy mapping used by import-restore-ezines-v2.php
SOURCE_LANG = "cs"

ISSUES = [
    {"num": 0, "dir": "RESTOR00"},
    {"num": 1, "dir": "RESTOR01"},
    {"num": 2, "dir": "RESTOR02"},
    {"num": 3, "dir": "RESTOR03"},
    {"num": 4, "dir": "RESTOR04"},
    {"num": 5, "dir": "RESTOR05"},
]


def parse_index(path: Path) -> list[dict[str, str]]:
    if not path.is_file():
        return []
    text = path.read_text(encoding="utf-8")
    out: list[dict[str, str]] = []
    for title, href in re.findall(r"\[([^\]]+)\]\(([^)]+\.md)\)", text):
        out.append({"title": title.strip(), "file": Path(href).name})
    return out


def read_md(path: Path) -> str:
    if not path.is_file():
        return ""
    body = path.read_text(encoding="utf-8").replace("\r\n", "\n")
    body = re.sub(r"\A#\s+[^\n]*\n+", "", body, count=1)
    return body.strip() + "\n"


def article_id(num: int, sort_order: int, stem: str) -> str:
    # drop leading NNN_ from stems like 000_intro___ / 001_001dkong
    clean = re.sub(r"^\d+_", "", stem)
    clean = re.sub(r"[^A-Za-z0-9]+", "_", clean).strip("_").upper() or "ART"
    return f"{num:02d}_{sort_order:02d}_{clean}"


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--force", action="store_true", help="Overwrite existing JSON files")
    ap.add_argument("--source", default=str(TEXTS))
    ap.add_argument("--out", default=str(OUT))
    args = ap.parse_args()

    source = Path(args.source)
    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    MIRROR.mkdir(parents=True, exist_ok=True)

    written = skipped = 0
    for iss in ISSUES:
        num = iss["num"]
        dname = iss["dir"]
        cs_dir = source / dname
        ru_dir = source / "ru" / dname
        cs_idx = parse_index(cs_dir / "INDEX.md")
        ru_idx = {r["file"]: r for r in parse_index(ru_dir / "INDEX.md")}
        if not cs_idx:
            print(f"WARN: no CS index for {dname}")
            continue
        for i, art in enumerate(cs_idx, start=1):
            fname = art["file"]
            stem = Path(fname).stem
            aid = article_id(num, i, stem)
            out_name = f"{aid}.json"
            out_path = out / out_name
            if out_path.exists() and not args.force:
                # keep existing metas/tags if re-seed without --force
                skipped += 1
                continue
            body_cs = read_md(cs_dir / fname)
            body_ru = read_md(ru_dir / fname)
            title_cs = art["title"]
            title_ru = (ru_idx.get(fname) or {}).get("title") or ""
            data: dict = {
                "article_id": aid,
                "press": PRESS,
                "press_id": PRESS_ID,
                "issue": f"{num:02d}",
                "sort_order": i,
                "source_file": fname,
                "source_lang": SOURCE_LANG,
                "title_cs": title_cs,
                "title_ru": title_ru,
                "title_en": "",
                "meta_description_cs": "",
                "meta_description_ru": "",
                "meta_description_en": "",
                "body_cs": body_cs,
                "body_ru": body_ru,
                "body_en": "",
                "category_ids": [],
                "tags": [],
            }
            if out_path.exists() and args.force:
                old = json.loads(out_path.read_text(encoding="utf-8"))
                # preserve already generated fields
                for k in (
                    "meta_description_ru",
                    "meta_description_en",
                    "meta_description_cs",
                    "title_en",
                    "body_en",
                    "category_ids",
                    "tags",
                    "authors",
                ):
                    if old.get(k):
                        data[k] = old[k]
            text = json.dumps(data, ensure_ascii=False, indent=2) + "\n"
            out_path.write_text(text, encoding="utf-8")
            (MIRROR / out_name).write_text(text, encoding="utf-8")
            written += 1
            print(f"write {out_name}")
    print(f"done written={written} skipped={skipped} out={out}")


if __name__ == "__main__":
    main()
