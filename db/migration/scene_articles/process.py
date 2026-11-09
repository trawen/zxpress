#!/usr/bin/env python3
"""Scene+ i18n via OdiRouter (curl). Source: Austrian German (de-AT) → EN + RU.

Short articles: ONE call (EN+RU markdown + titles/meta/authors/tags/cats).
Long articles: chunked EN + chunked RU + ONE enrich call.

  python3 db/migration/scene_articles/process.py --resume --workers=2
  python3 db/migration/scene_articles/process.py --only=01_01_EDITOR
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
ARTICLES = ROOT / "local/work/SCENE_texts/articles"
IMPORTS = ROOT / "site/cli/imports/scene_i18n"
MIRROR = ROOT / "db/migration/scene_articles/i18n"
CATS_FILE = ROOT / "db/migration/subxtc_article_categories/categories_catalog.txt"
PROGRESS = Path(__file__).resolve().parent / "generated" / "process_progress.json"
GEN = Path(__file__).resolve().parent / "generated"

ONESHOT_CHARS = 12_000

ONESHOT_SYSTEM_TMPL = """Ты обрабатываешь статью австрийского дискмага Scene+ (ZX Spectrum, Wien) для архива zxpress.ru.
Исходный язык: австрийский немецкий (de-AT).

Сделай ВСЁ за один ответ. Верни ТОЛЬКО JSON-объект:
{{
  "title_en": "short English title",
  "title_ru": "короткий русский заголовок",
  "meta_description_en": "≤220 chars English",
  "meta_description_ru": "≤220 символов по-русски",
  "authors": ["Real Name", "..."],
  "tags": [{{"tag_name":"≤24","tag_alias":"latin-kebab"}}],
  "category_ids": [ids from catalog],
  "body_en": "full English markdown translation",
  "body_ru": "full Russian markdown translation"
}}

Правила:
- body_en / body_ru: полный перевод с de-AT; разверни колоночные переносы в абзацы; ASCII/listings в ```text
- сохрани имена, адреса, цены, e-mail, названия программ/групп как есть
- authors: люди из byline/подписи (не рубрики); [] если анонимно
- tags: 3–8 (можно scene-plus, zx-spectrum, austria, …)
- category_ids: 1–4 ТОЛЬКО из каталога
Каталог (id\\tname_ru):
{catalog}
"""

EN_MD_SYSTEM = """You translate Austrian German (de-AT) Scene+ diskmag text (ZX Spectrum) into English Markdown for zxpress.ru.
Use ##/### headings; put ASCII/listings in ```text fences; reflow column wraps into paragraphs.
Do not translate names, addresses, prices, e-mails, or program/group titles.
Do not shorten. Return ONLY the markdown fragment, no commentary."""

RU_MD_SYSTEM = """Ты переводишь текст австрийского дискмага Scene+ (ZX Spectrum, de-AT) на русский и сразу оформляешь Markdown для zxpress.ru.
Заголовки ##/###; ASCII/listings в ```text; разверни колоночные переносы.
Имена, адреса, цены, e-mail, названия программ/групп не переводи.
Не сокращай. Верни ТОЛЬКО markdown фрагмента, без пояснений."""

ENRICH_SYSTEM_TMPL = """Ты размечаешь статью дискмага Scene+ (Австрия) для zxpress.ru.
Верни ТОЛЬКО JSON:
{{
  "authors": ["Name", "..."],
  "tags": [{{"tag_name":"≤24","tag_alias":"latin-kebab"}}],
  "category_ids": [ids],
  "meta_description_ru": "≤220",
  "meta_description_en": "≤220",
  "title_ru": "...",
  "title_en": "..."
}}
authors — реальные люди (byline), иначе []. tags 3–8. category_ids 1–4 из каталога.
Каталог:
{catalog}
"""


def _env_val(name: str, default: str = "") -> str:
    env = (ROOT / ".env").read_text(encoding="utf-8")
    m = re.search(rf"^{re.escape(name)}=(.*)$", env, re.M)
    if not m:
        return default
    return m.group(1).strip().strip('"').strip("'")


def chunk_text(text: str, max_len: int) -> list[str]:
    if len(text) <= max_len:
        return [text]
    chunks: list[str] = []
    rest = text
    while len(rest) > max_len:
        cut = rest.rfind("\n\n", 0, max_len)
        if cut < max_len * 0.5:
            cut = rest.rfind("\n", 0, max_len)
        if cut < max_len * 0.3:
            cut = max_len
        chunks.append(rest[:cut])
        rest = rest[cut:]
    if rest:
        chunks.append(rest)
    return chunks


def odi_call(system: str, user: str, *, max_tokens: int = 8192, long: bool = False) -> str:
    GEN.mkdir(parents=True, exist_ok=True)
    model = _env_val(
        "ODIROUTER_MODEL_LONG" if long else "ODIROUTER_MODEL",
        "gemini-3.5-flash",
    )
    api_key = _env_val("ODIROUTER_API_KEY_LONG" if long else "ODIROUTER_API_KEY")
    if long and not api_key:
        api_key = _env_val("ODIROUTER_API_KEY")
    if not api_key:
        raise RuntimeError("ODIROUTER_API_KEY missing")
    base = (_env_val("ODIROUTER_BASE_URL", "https://api.odirouter.ai/v1") or "").rstrip("/")
    req = GEN / f"_odi_req_{os.getpid()}_{time.time_ns()}.json"
    resp = GEN / f"_odi_resp_{os.getpid()}_{time.time_ns()}.json"
    payload = {
        "model": model,
        "max_tokens": max_tokens,
        "temperature": 0.1,
        "system": system,
        "messages": [{"role": "user", "content": user}],
    }
    req.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    last_err = ""
    try:
        for attempt in range(1, 9):
            proc = subprocess.run(
                [
                    "curl",
                    "-sS",
                    "-m",
                    "300",
                    "-o",
                    str(resp),
                    "-w",
                    "%{http_code}",
                    "-X",
                    "POST",
                    f"{base}/messages",
                    "-H",
                    "content-type: application/json",
                    "-H",
                    f"Authorization: Bearer {api_key}",
                    "-H",
                    f"x-api-key: {api_key}",
                    "-H",
                    "anthropic-version: 2023-06-01",
                    "--data-binary",
                    f"@{req}",
                ],
                capture_output=True,
                text=True,
            )
            code = (proc.stdout or "").strip()
            if proc.returncode == 0 and code == "200":
                data = json.loads(resp.read_text(encoding="utf-8"))
                content = data.get("content")
                if isinstance(content, list):
                    text = "".join((c.get("text") or "") for c in content)
                else:
                    text = str(content or "")
                return text.strip()
            last_err = f"http={code} rc={proc.returncode} err={(proc.stderr or '')[:200]}"
            if code in {"429", "502", "503", "504"} or proc.returncode != 0:
                time.sleep(min(60, attempt * 3))
                continue
            break
        raise RuntimeError(f"ODIROUTER failed: {last_err}")
    finally:
        for p in (req, resp):
            try:
                p.unlink(missing_ok=True)
            except OSError:
                pass


def extract_json(raw: str) -> dict:
    t = raw.strip()
    m = re.search(r"```(?:json)?\s*([\s\S]*?)```", t, re.I)
    body = m.group(1).strip() if m else t
    start = body.find("{")
    end = body.rfind("}")
    if start < 0 or end <= start:
        raise ValueError(f"no JSON object: {raw[:200]}")
    return json.loads(body[start : end + 1])


def clip(s: str, n: int) -> str:
    t = re.sub(r"\s+", " ", str(s or "")).strip()
    if len(t) <= n:
        return t
    return re.sub(r"\s+\S*$", "", t[: n - 1]) + "…"


def load_cats() -> tuple[str, set[int]]:
    lines = []
    ids: set[int] = set()
    for line in CATS_FILE.read_text(encoding="utf-8").splitlines():
        s = line.strip()
        if not s or s.startswith("#"):
            continue
        m = re.match(r"^(\d+)\t(.+)$", s)
        if not m:
            continue
        cid = int(m.group(1))
        ids.add(cid)
        lines.append(f"{cid}\t{m.group(2)}")
    return "\n".join(lines), ids


def long_threshold() -> int:
    return int(_env_val("ODIROUTER_LONG_CHARS", "25000") or "25000")


def apply_enrich_fields(data: dict, raw: dict, cat_ids: set[int]) -> None:
    authors = []
    for a in raw.get("authors") or []:
        name = re.sub(r"\s+", " ", str(a).strip())
        if name and name not in authors:
            authors.append(name)
    tags = []
    for t in raw.get("tags") or []:
        if isinstance(t, str):
            name = t.strip()[:24]
            alias = re.sub(r"[^a-z0-9-]+", "-", name.lower()).strip("-")[:32]
        elif isinstance(t, dict):
            name = str(t.get("tag_name") or "").strip()[:24]
            alias = re.sub(
                r"[^a-z0-9-]+",
                "-",
                str(t.get("tag_alias") or name).strip().lower(),
            ).strip("-")[:32]
        else:
            continue
        if name and alias:
            tags.append({"tag_name": name, "tag_alias": alias})
    cats = []
    for n in raw.get("category_ids") or []:
        try:
            cid = int(n)
        except (TypeError, ValueError):
            continue
        if cid in cat_ids and cid not in cats:
            cats.append(cid)
    if not cats:
        cats = list(data.get("category_ids") or [])
    if raw.get("title_ru"):
        data["title_ru"] = str(raw["title_ru"]).strip()
    if raw.get("title_en"):
        data["title_en"] = str(raw["title_en"]).strip()
    if raw.get("meta_description_ru"):
        data["meta_description_ru"] = clip(raw["meta_description_ru"], 220)
    if raw.get("meta_description_en"):
        data["meta_description_en"] = clip(raw["meta_description_en"], 220)
    data["authors"] = authors
    data["tags"] = tags[:8]
    data["category_ids"] = cats[:4]


def write_all(data: dict, fname: str) -> None:
    out = {k: v for k, v in data.items() if not k.startswith("_")}
    text = json.dumps(out, ensure_ascii=False, indent=2) + "\n"
    issue = str(out["issue"])
    article_id = str(out["article_id"])
    (ARTICLES / fname).write_text(text, encoding="utf-8")
    IMPORTS.mkdir(parents=True, exist_ok=True)
    (IMPORTS / fname).write_text(text, encoding="utf-8")
    issue_dir = MIRROR / issue
    issue_dir.mkdir(parents=True, exist_ok=True)
    (issue_dir / f"{article_id}.json").write_text(text, encoding="utf-8")


def is_complete(data: dict) -> bool:
    return bool(
        (data.get("body_en") or "").strip()
        and (data.get("body_ru") or "").strip()
        and data.get("tags")
        and (data.get("meta_description_ru") or "").strip()
    )


def source_plain(data: dict) -> str:
    return (data.get("body_de_plain") or data.get("body_en_plain") or "").strip()


def process_oneshot(data: dict, catalog: str, cat_ids: set[int]) -> dict:
    plain = source_plain(data)
    system = ONESHOT_SYSTEM_TMPL.format(catalog=catalog)
    user = (
        f"Issue: Scene+ #{data.get('issue')}\n"
        f"article_id: {data.get('article_id')}\n"
        f"source_lang: de-AT\n"
        f"seed_title_en: {data.get('title_en')}\n"
        f"seed_title_ru: {data.get('title_ru')}\n"
        f"seed_category_ids: {data.get('category_ids')}\n\n"
        f"GERMAN (de-AT) SOURCE:\n{plain}"
    )
    print("  oneshot", flush=True)
    raw = extract_json(
        odi_call(
            system,
            user,
            max_tokens=16384,
            long=len(plain) > long_threshold() or len(plain) > 9000,
        )
    )
    body_en = str(raw.get("body_en") or "").strip()
    body_ru = str(raw.get("body_ru") or "").strip()
    if not body_en or not body_ru:
        raise RuntimeError("oneshot missing body_en/body_ru")
    data["body_en"] = body_en + ("\n" if not body_en.endswith("\n") else "")
    data["body_ru"] = body_ru + ("\n" if not body_ru.endswith("\n") else "")
    data["body_en_plain"] = data.get("body_en_plain") or ""
    data["body_ru_plain"] = data.get("body_ru_plain") or ""
    apply_enrich_fields(data, raw, cat_ids)
    return data


def translate_chunks(plain: str, system: str, title: str, lang_label: str) -> str:
    long = len(plain) > long_threshold()
    chunks = chunk_text(plain, 8000 if long else 11000)
    parts = []
    for i, ch in enumerate(chunks, 1):
        print(f"  {lang_label}{i}/{len(chunks)}", flush=True)
        user = (
            f"Article: {title}\n"
            f"Fragment {i}/{len(chunks)}. Translate from Austrian German (de-AT).\n\n"
            f"{ch}"
        )
        parts.append(
            odi_call(
                system,
                user,
                max_tokens=16384,
                long=long or len(ch) > 8000,
            )
        )
    return "\n\n".join(p.strip() for p in parts).strip() + "\n"


def process_long(data: dict, catalog: str, cat_ids: set[int]) -> dict:
    plain = source_plain(data)
    title = str(data.get("title_en") or data.get("article_id") or "")

    if not (data.get("body_en") or "").strip():
        data["body_en"] = translate_chunks(plain, EN_MD_SYSTEM, title, "en")

    if not (data.get("body_ru") or "").strip():
        data["body_ru"] = translate_chunks(plain, RU_MD_SYSTEM, title, "ru")

    write_all(data, f"{data.get('issue')}_{data.get('article_id')}.json")

    if not data.get("tags") or not (data.get("meta_description_ru") or "").strip():
        print("  enrich", flush=True)
        system = ENRICH_SYSTEM_TMPL.format(catalog=catalog)
        user = (
            f"Scene+ #{data.get('issue')} / {data.get('article_id')}\n"
            f"title_en: {data.get('title_en')}\n"
            f"title_ru: {data.get('title_ru')}\n\n"
            f"DE excerpt:\n{plain[:2500]}\n\n"
            f"EN excerpt:\n{(data.get('body_en') or '')[:2500]}\n\n"
            f"RU excerpt:\n{(data.get('body_ru') or '')[:2500]}"
        )
        raw = extract_json(odi_call(system, user, max_tokens=2048, long=False))
        apply_enrich_fields(data, raw, cat_ids)
    return data


def process_one(path: Path, catalog: str, cat_ids: set[int], *, force_long: bool = False) -> str:
    data = json.loads(path.read_text(encoding="utf-8"))
    fname = path.name
    if is_complete(data):
        return fname
    plain = source_plain(data)
    use_oneshot = (
        not force_long
        and len(plain) <= ONESHOT_CHARS
        and not (data.get("body_ru") or "").strip()
        and not (data.get("body_en") or "").strip()
    )
    if use_oneshot:
        try:
            data = process_oneshot(data, catalog, cat_ids)
        except Exception as e:  # noqa: BLE001
            print(f"  oneshot-fail→long ({e})", flush=True)
            data = process_long(data, catalog, cat_ids)
    else:
        data = process_long(data, catalog, cat_ids)
    if not is_complete(data):
        if (data.get("body_en") or "").strip() and (data.get("body_ru") or "").strip():
            if not data.get("tags") or not (data.get("meta_description_ru") or "").strip():
                print("  enrich-fix", flush=True)
                system = ENRICH_SYSTEM_TMPL.format(catalog=catalog)
                user = (
                    f"Scene+ #{data.get('issue')} / {data.get('article_id')}\n"
                    f"DE excerpt:\n{plain[:3500]}\n\n"
                    f"EN excerpt:\n{(data.get('body_en') or '')[:3500]}\n\n"
                    f"RU excerpt:\n{(data.get('body_ru') or '')[:3500]}"
                )
                raw = extract_json(odi_call(system, user, max_tokens=2048, long=False))
                apply_enrich_fields(data, raw, cat_ids)
    write_all(data, fname)
    if not is_complete(data):
        raise RuntimeError("still incomplete after process")
    return fname


def load_progress() -> dict:
    if PROGRESS.exists():
        return json.loads(PROGRESS.read_text(encoding="utf-8"))
    return {"done": {}}


def save_progress(p: dict) -> None:
    PROGRESS.parent.mkdir(parents=True, exist_ok=True)
    PROGRESS.write_text(json.dumps(p, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def list_articles(only: str) -> list[Path]:
    files = sorted(ARTICLES.glob("*.json"))
    out = []
    for p in files:
        if p.name in {"MANIFEST.json"} or p.name.startswith("_"):
            continue
        if only and only not in p.name:
            continue
        out.append(p)
    return out


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--resume", action="store_true")
    ap.add_argument("--only", default="")
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--workers", type=int, default=2)
    args = ap.parse_args()

    model = _env_val("ODIROUTER_MODEL", "?")
    print(
        f"Scene+ process model={model} oneshot<={ONESHOT_CHARS} workers={args.workers}",
        flush=True,
    )

    catalog, cat_ids = load_cats()
    files = list_articles(args.only)
    files.sort(key=lambda p: len(source_plain(json.loads(p.read_text(encoding="utf-8")))))
    if args.limit:
        files = files[: args.limit]

    progress = load_progress()
    done = progress.setdefault("done", {})

    todo = []
    for p in files:
        data = json.loads(p.read_text(encoding="utf-8"))
        if args.resume and (done.get(p.name, {}).get("all") == "ok" or is_complete(data)):
            continue
        todo.append(p)

    print(f"pending {len(todo)}/{len(files)}", flush=True)
    ok = fail = 0

    def work(p: Path) -> tuple[str, bool, str]:
        try:
            process_one(p, catalog, cat_ids)
            return p.name, True, ""
        except Exception as e:  # noqa: BLE001
            return p.name, False, str(e)

    if args.workers <= 1:
        for i, p in enumerate(todo, 1):
            print(f"[{i}/{len(todo)}] {p.name} …", flush=True)
            name, success, err = work(p)
            if success:
                done[name] = {**(done.get(name) or {}), "all": "ok", "at": time.time()}
                ok += 1
                print(" OK", flush=True)
            else:
                done[name] = {
                    **(done.get(name) or {}),
                    "all": "fail",
                    "error": err,
                }
                fail += 1
                print(f" FAIL {err}", flush=True)
            save_progress(progress)
    else:
        with ThreadPoolExecutor(max_workers=args.workers) as ex:
            futs = {ex.submit(work, p): p for p in todo}
            for i, fut in enumerate(as_completed(futs), 1):
                name, success, err = fut.result()
                print(
                    f"[{i}/{len(todo)}] {name} {'OK' if success else 'FAIL ' + err}",
                    flush=True,
                )
                if success:
                    done[name] = {
                        **(done.get(name) or {}),
                        "all": "ok",
                        "at": time.time(),
                    }
                    ok += 1
                else:
                    done[name] = {
                        **(done.get(name) or {}),
                        "all": "fail",
                        "error": err,
                    }
                    fail += 1
                save_progress(progress)

    print(f"done ok={ok} fail={fail}", flush=True)


if __name__ == "__main__":
    main()
