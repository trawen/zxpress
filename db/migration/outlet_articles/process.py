#!/usr/bin/env python3
"""Outlet i18n via OdiRouter (curl).

Short articles: ONE call (EN+RU markdown + titles/meta/authors/tags/cats).
Long articles: chunked RU markdown + local EN unwrap + ONE enrich call.

  python3 db/migration/outlet_articles/process.py --resume --workers=4
  python3 db/migration/outlet_articles/process.py --only=004_05_editorial
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
ARTICLES = ROOT / "local/work/OUTLET_texts/articles"
IMPORTS = ROOT / "site/cli/imports/outlet_i18n"
MIRROR = ROOT / "db/migration/outlet_articles/i18n"
CATS_FILE = ROOT / "db/migration/subxtc_article_categories/categories_catalog.txt"
PROGRESS = Path(__file__).resolve().parent / "generated" / "process_progress.json"
GEN = Path(__file__).resolve().parent / "generated"

# under this size → single OdiRouter call for everything
ONESHOT_CHARS = 12_000

ONESHOT_SYSTEM_TMPL = """Ты обрабатываешь статью дискмага Outlet (ZX Spectrum / Chezron) для архива zxpress.ru.

Сделай ВСЁ за один ответ. Верни ТОЛЬКО JSON-объект:
{{
  "title_en": "short English title",
  "title_ru": "короткий русский заголовок",
  "meta_description_en": "≤220 chars English",
  "meta_description_ru": "≤220 символов по-русски",
  "authors": ["Real Name", "..."],
  "tags": [{{"tag_name":"≤24","tag_alias":"latin-kebab"}}],
  "category_ids": [ids from catalog],
  "body_en": "full English markdown",
  "body_ru": "full Russian markdown translation"
}}

Правила:
- body_en: разверни 32-колоночные переносы в нормальные абзацы; ASCII/listings в ```text
- body_ru: полный перевод на русский в том же markdown-стиле; имена/адреса/цены/e-mail не переводи
- authors: люди из byline/From: (не названия рубрик); [] если анонимно
- tags: 3–8
- category_ids: 1–4 ТОЛЬКО из каталога
Каталог (id\\tname_ru):
{catalog}
"""

RU_MD_SYSTEM = """Ты переводишь текст дискмага Outlet (ZX Spectrum) на русский и сразу оформляешь Markdown для zxpress.ru.
Заголовки ##/###; ASCII/listings в ```text; разверни 32-колоночные переносы.
Имена, адреса, цены, e-mail, названия программ не переводи.
Не сокращай. Верни ТОЛЬКО markdown фрагмента, без пояснений."""

ENRICH_SYSTEM_TMPL = """Ты размечаешь статью дискмага Outlet для zxpress.ru.
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
authors — реальные люди (byline/From:), иначе []. tags 3–8. category_ids 1–4 из каталога.
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


def unwrap_32col_to_md(plain: str, title: str = "") -> str:
    """Local EN markdown: reflow 32-col wraps, fence dense ASCII blocks. No LLM."""
    lines = plain.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    out: list[str] = []
    if title:
        out.append(f"## {title}\n")
    para: list[str] = []
    fence: list[str] = []

    def flush_para() -> None:
        nonlocal para
        if not para:
            return
        out.append(" ".join(para).strip())
        out.append("")
        para = []

    def flush_fence() -> None:
        nonlocal fence
        if not fence:
            return
        out.append("```text")
        out.extend(fence)
        out.append("```")
        out.append("")
        fence = []

    def is_ascii_line(s: str) -> bool:
        t = s.rstrip()
        if not t:
            return False
        if re.search(r"[│┌┐└┘├┤┬┴┼╔╗╚╝═─━┃]{2,}", t):
            return True
        if re.fullmatch(r"[\s\|`_\-=+\*#<>/\\\[\]().,:;!?]{8,}", t):
            return True
        # dense punctuation / box drawing ratio
        letters = sum(ch.isalpha() for ch in t)
        return letters <= max(2, len(t) // 6) and len(t) >= 10

    for ln in lines:
        if is_ascii_line(ln):
            flush_para()
            fence.append(ln.rstrip())
            continue
        flush_fence()
        if not ln.strip():
            flush_para()
            continue
        # soft-wrap join: previous didn't end sentence and this starts lowercase/continuation
        para.append(ln.strip())
    flush_fence()
    flush_para()
    return "\n".join(out).strip() + "\n"


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


def process_oneshot(data: dict, catalog: str, cat_ids: set[int]) -> dict:
    plain = data["body_en_plain"]
    system = ONESHOT_SYSTEM_TMPL.format(catalog=catalog)
    user = (
        f"Issue: Outlet #{data.get('issue')}\n"
        f"article_id: {data.get('article_id')}\n"
        f"seed_title_en: {data.get('title_en')}\n"
        f"seed_title_ru: {data.get('title_ru')}\n"
        f"seed_category_ids: {data.get('category_ids')}\n\n"
        f"ENGLISH SOURCE:\n{plain}"
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
    # keep plain EN; optional RU plain unused
    data["body_ru_plain"] = data.get("body_ru_plain") or ""
    apply_enrich_fields(data, raw, cat_ids)
    return data


def process_long(data: dict, catalog: str, cat_ids: set[int]) -> dict:
    plain = data["body_en_plain"]
    # EN markdown locally — no LLM
    if not (data.get("body_en") or "").strip():
        print("  en-local", flush=True)
        data["body_en"] = unwrap_32col_to_md(plain, str(data.get("title_en") or ""))

    if not (data.get("body_ru") or "").strip():
        long = len(plain) > long_threshold()
        chunks = chunk_text(plain, 8000 if long else 11000)
        parts = []
        for i, ch in enumerate(chunks, 1):
            print(f"  ru{i}/{len(chunks)}", flush=True)
            user = (
                f"Статья: {data.get('title_en')}\n"
                f"Фрагмент {i}/{len(chunks)}. Переведи в русский Markdown.\n\n"
                f"{ch}"
            )
            parts.append(
                odi_call(
                    RU_MD_SYSTEM,
                    user,
                    max_tokens=16384,
                    long=long or len(ch) > 8000,
                )
            )
        data["body_ru"] = "\n\n".join(p.strip() for p in parts).strip() + "\n"
        data["body_ru_plain"] = data.get("body_ru_plain") or ""

    # persist bodies even if enrich fails later
    write_all(data, f"{data.get('issue')}_{data.get('article_id')}.json".replace("/", "-"))

    if not data.get("tags") or not (data.get("meta_description_ru") or "").strip():
        print("  enrich", flush=True)
        system = ENRICH_SYSTEM_TMPL.format(catalog=catalog)
        user = (
            f"Outlet #{data.get('issue')} / {data.get('article_id')}\n"
            f"title_en: {data.get('title_en')}\n"
            f"title_ru: {data.get('title_ru')}\n\n"
            f"EN excerpt:\n{plain[:2500]}\n\n"
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
    plain = data.get("body_en_plain") or ""
    use_oneshot = (
        not force_long
        and len(plain) <= ONESHOT_CHARS
        and not (data.get("body_ru") or "").strip()
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
        # oneshot may have returned bodies but empty tags — finish enrich once
        if (data.get("body_en") or "").strip() and (data.get("body_ru") or "").strip():
            if not data.get("tags") or not (data.get("meta_description_ru") or "").strip():
                print("  enrich-fix", flush=True)
                system = ENRICH_SYSTEM_TMPL.format(catalog=catalog)
                user = (
                    f"Outlet #{data.get('issue')} / {data.get('article_id')}\n"
                    f"EN excerpt:\n{plain[:3500]}\n\n"
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
    ap.add_argument("--workers", type=int, default=4)
    args = ap.parse_args()

    model = _env_val("ODIROUTER_MODEL", "?")
    print(
        f"Outlet process model={model} oneshot<={ONESHOT_CHARS} workers={args.workers}",
        flush=True,
    )

    catalog, cat_ids = load_cats()
    files = list_articles(args.only)
    files.sort(
        key=lambda p: len(
            json.loads(p.read_text(encoding="utf-8")).get("body_en_plain") or ""
        )
    )
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
