#!/usr/bin/env python3
"""Seed Outlet i18n JSON stubs from OUTLET_texts.

  python3 db/migration/outlet_articles/seed.py --from=11 --to=40
  python3 db/migration/outlet_articles/seed.py --from=1 --to=10
"""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SRC = ROOT / "local/work/OUTLET_texts"
OUT = ROOT / "local/work/OUTLET_texts/articles"
IMPORTS = ROOT / "site/cli/imports/outlet_i18n"
MIRROR = ROOT / "db/migration/outlet_articles/i18n"

TITLE_EN = {
    "editorial": "Editorial",
    "edit_l": "Editorial (late)",
    "letters": "Letters",
    "letters1": "Letters (1)",
    "letters2": "Letters (2)",
    "letters3": "Letters (3)",
    "outboard": "Outboard",
    "shout": "Shout",
    "shout2": "Shout (2)",
    "shout3": "Shout (3)",
    "outclass": "Outclass",
    "outclas01": "Outclass",
    "lookout": "Lookout",
    "lookahead": "Lookahead",
    "lookahed": "Lookahead",
    "checkout": "Checkout",
    "checkout1": "Checkout (1)",
    "checkout2": "Checkout (2)",
    "checktwo": "Checkout (2)",
    "drivechat": "Drive Chat",
    "drivchat": "Drive Chat",
    "ace": "ACE magazine review",
    "copyright": "Copyright note",
    "getout": "Get Out",
    "instructn": "Instructions",
    "instruct2": "Instructions (2)",
    "inst_n": "Instructions",
    "inst_n2": "Instructions (2)",
    "aaagh": "Aaagh+",
    "marinfo": "Marinfo",
    "t2_pginfo": "Tasword 2 page info",
    "addender": "Addender",
    "adventure": "Adventure",
    "compo": "Competition",
    "decplace": "Decimal places",
    "interlude": "Interlude",
    "output": "Output",
    "saminfo": "SAM info",
    "sstvinfo": "SSTV info",
    "timebomb": "Timebomb",
}

TITLE_RU = {
    "editorial": "Редакторская колонка",
    "edit_l": "Редакторская колонка (доп.)",
    "letters": "Письма",
    "letters1": "Письма (1)",
    "letters2": "Письма (2)",
    "letters3": "Письма (3)",
    "outboard": "Доска объявлений",
    "shout": "Мнение",
    "shout2": "Мнение (2)",
    "shout3": "Мнение (3)",
    "outclass": "Программирование",
    "outclas01": "Программирование",
    "lookout": "Обзоры",
    "lookahead": "Анонсы",
    "lookahed": "Анонсы",
    "checkout": "Обзор",
    "checkout1": "Обзор (1)",
    "checkout2": "Обзор (2)",
    "checktwo": "Обзор (2)",
    "drivechat": "Дисководы",
    "drivchat": "Дисководы",
    "ace": "Обзор журнала ACE",
    "copyright": "Авторские права",
    "getout": "Get Out",
    "instructn": "Инструкции",
    "instruct2": "Инструкции (2)",
    "inst_n": "Инструкции",
    "inst_n2": "Инструкции (2)",
    "aaagh": "Aaagh+",
    "marinfo": "Marinfo",
    "t2_pginfo": "Tasword 2: сведения о страницах",
    "addender": "Дополнение",
    "adventure": "Приключения",
    "compo": "Конкурс",
    "decplace": "Десятичные знаки",
    "interlude": "Интерлюдия",
    "output": "Output",
    "saminfo": "SAM Coupé",
    "sstvinfo": "SSTV",
    "timebomb": "Timebomb",
}

SECTION_CATS = {
    "editorial": [123, 130],
    "edit_l": [123, 130],
    "letters": [153, 154, 175, 176],
    "letters1": [153, 154, 175, 176],
    "letters2": [153, 154, 175, 176],
    "letters3": [153, 154, 175, 176],
    "outboard": [118],
    "shout": [101, 135],
    "shout2": [101, 135],
    "shout3": [101, 135],
    "outclass": [29, 62],
    "outclas01": [29, 62],
    "lookout": [2, 124],
    "lookahead": [2, 124],
    "lookahed": [2, 124],
    "checkout": [2, 124],
    "checkout1": [2, 124],
    "checkout2": [2, 124],
    "checktwo": [2, 124],
    "drivechat": [10, 16],
    "drivchat": [10, 16],
    "ace": [123, 131],
    "instructn": [29, 62],
    "instruct2": [29, 62],
    "inst_n": [29, 62],
    "inst_n2": [29, 62],
    "t2_pginfo": [29, 62],
    "aaagh": [117],
    "copyright": [116, 121],
    "getout": [116, 121],
    "marinfo": [116, 121],
    "addender": [116, 121],
    "adventure": [1, 3],
    "compo": [117, 121],
    "decplace": [29, 54],
    "interlude": [117, 116],
    "output": [123, 130],
    "saminfo": [10, 132],
    "sstvinfo": [10, 132],
    "timebomb": [1, 2],
}


def issue_list(from_n: int, to_n: int) -> list[tuple[str, str]]:
    out: list[tuple[str, str]] = []
    for n in range(from_n, to_n + 1):
        if n <= 3:
            # combined early disk — include once when range touches 1..3
            if ("OUTLET001-003", "1-3") not in out and from_n <= 3:
                out.append(("OUTLET001-003", "1-3"))
            continue
        out.append((f"OUTLET{n:03d}", f"{n:03d}"))
    return out


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
    ap.add_argument("--to", dest="to_n", type=int, default=10)
    ap.add_argument("--force", action="store_true", help="overwrite even completed JSON")
    args = ap.parse_args()
    if args.from_n > args.to_n:
        raise SystemExit("--from must be <= --to")

    OUT.mkdir(parents=True, exist_ok=True)
    IMPORTS.mkdir(parents=True, exist_ok=True)
    n = skipped = 0
    for folder, issue in issue_list(args.from_n, args.to_n):
        src_dir = SRC / folder
        if not src_dir.is_dir():
            print(f"MISSING {folder}")
            continue
        issue_dir = MIRROR / issue
        issue_dir.mkdir(parents=True, exist_ok=True)
        for f in sorted(src_dir.glob("*.txt")):
            if f.name.startswith("00_"):
                continue
            m = re.match(r"^(\d+)_(.+)\.txt$", f.name)
            if not m:
                continue
            num, slug = m.group(1), m.group(2)
            article_id = f"{num}_{slug}"
            fname = f"{issue}_{article_id}.json".replace("/", "-")
            dest_main = OUT / fname
            if not args.force and already_done(dest_main):
                skipped += 1
                continue
            plain = norm_plain(f.read_text(encoding="utf-8", errors="replace"))
            title_en = TITLE_EN.get(slug, slug.replace("_", " ").title())
            title_ru = TITLE_RU.get(slug, title_en)
            data = {
                "press": "Outlet",
                "press_id": 314,
                "issue": issue,
                "article_id": article_id,
                "title_en": title_en,
                "title_ru": title_ru,
                "meta_description_en": "",
                "meta_description_ru": "",
                "authors": [],
                "tags": [],
                "category_ids": SECTION_CATS.get(slug, [116, 121]),
                "body_en_plain": plain,
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
