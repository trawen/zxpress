#!/usr/bin/env python3
"""Seed Scene+ i18n JSON stubs from SCENE_texts (German / de-AT source).

  python3 db/migration/scene_articles/seed.py --from=1 --to=1
"""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SRC = ROOT / "local/work/SCENE_texts"
OUT = ROOT / "local/work/SCENE_texts/articles"
IMPORTS = ROOT / "site/cli/imports/scene_i18n"
MIRROR = ROOT / "db/migration/scene_articles/i18n"

# slug from filename after NN_ (e.g. EDITOR_1, BRIEFE_1)
TITLE_EN = {
    "EDITOR_1": "Editorial",
    "INHALT_1": "Contents",
    "BRIEFE_1": "Letters",
    "ARTIKEL_1": "Articles",
    "NEWS_1": "News",
}

TITLE_RU = {
    "EDITOR_1": "Редакторская колонка",
    "INHALT_1": "Содержание",
    "BRIEFE_1": "Письма",
    "ARTIKEL_1": "Статьи",
    "NEWS_1": "Новости",
}

SECTION_CATS = {
    "EDITOR_1": [123, 130],
    "INHALT_1": [116, 121],
    "BRIEFE_1": [153, 154, 175, 176],
    "ARTIKEL_1": [123, 130, 96],
    "NEWS_1": [123, 130, 125],
}


def norm_plain(text: str) -> str:
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    lines = [ln.rstrip() for ln in text.split("\n")]
    return "\n".join(lines).strip() + "\n"


def already_done(path: Path) -> bool:
    if not path.exists():
        return False
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return False
    return bool((data.get("body_ru") or "").strip() and (data.get("body_en") or "").strip())


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--from", dest="from_n", type=int, default=1)
    ap.add_argument("--to", dest="to_n", type=int, default=1)
    ap.add_argument("--force", action="store_true", help="overwrite even completed JSON")
    args = ap.parse_args()
    if args.from_n > args.to_n:
        raise SystemExit("--from must be <= --to")

    OUT.mkdir(parents=True, exist_ok=True)
    IMPORTS.mkdir(parents=True, exist_ok=True)
    n = skipped = 0
    for num in range(args.from_n, args.to_n + 1):
        folder = f"{num:02d}"
        issue = folder
        src_dir = SRC / folder
        if not src_dir.is_dir():
            print(f"MISSING {folder}")
            continue
        issue_dir = MIRROR / issue
        issue_dir.mkdir(parents=True, exist_ok=True)
        for f in sorted(src_dir.glob("*.txt")):
            if f.name in {"ALL.txt"} or f.name.startswith("00_"):
                continue
            m = re.match(r"^(\d+)_(.+)\.txt$", f.name)
            if not m:
                continue
            art_num, slug = m.group(1), m.group(2)
            article_id = f"{art_num}_{slug}"
            fname = f"{issue}_{article_id}.json"
            dest_main = OUT / fname
            if not args.force and already_done(dest_main):
                skipped += 1
                continue
            plain = norm_plain(f.read_text(encoding="utf-8", errors="replace"))
            title_en = TITLE_EN.get(slug, slug.replace("_", " ").title())
            title_ru = TITLE_RU.get(slug, title_en)
            data = {
                "press": "Scene+",
                "press_id": 315,
                "issue": issue,
                "article_id": article_id,
                "source_lang": "de-AT",
                "title_en": title_en,
                "title_ru": title_ru,
                "meta_description_en": "",
                "meta_description_ru": "",
                "authors": [],
                "tags": [],
                "category_ids": SECTION_CATS.get(slug, [116, 121]),
                "body_de_plain": plain,
                "body_en_plain": "",
                "body_ru_plain": "",
                "body_en": "",
                "body_ru": "",
                "text_type": 3,
                "_src": f"{folder}/{f.name}",
            }
            text = json.dumps(data, ensure_ascii=False, indent=2) + "\n"
            for dest in (dest_main, IMPORTS / fname, issue_dir / f"{article_id}.json"):
                dest.write_text(text, encoding="utf-8")
            n += 1
            print(fname, len(plain))
    print(f"seeded {n} articles (skipped done={skipped}) range={args.from_n}-{args.to_n}")


if __name__ == "__main__":
    main()
