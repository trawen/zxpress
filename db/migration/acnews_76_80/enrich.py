#!/usr/bin/env python3
"""Enrich ACNews #76–#80 via OdiRouter (curl). No translation.

  python3 db/migration/acnews_76_80/enrich.py --resume
  python3 db/migration/acnews_76_80/enrich.py --only=76/02_chess
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
TEXTS = ROOT / "local/work/ACN7680_texts"
OUT = TEXTS / "articles"
CATS_FILE = ROOT / "db/migration/subxtc_article_categories/categories_catalog.txt"
PROGRESS = Path(__file__).resolve().parent / "generated" / "enrich_progress.json"

MD_SYSTEM = """Ты конвертируешь текст газеты ACNews (Alone Coder, ZX Spectrum / ATM / NedoOS) в Markdown для zxpress.ru.

Правила:
- Заголовки: ## / ###
- Списки — markdown-списки; таблицы где уместно
- ASCII-арт, рамки, моноширинные блоки, листинги, словари → ```text … ```
- Имена людей, URL, e-mail, названия программ/платформ не меняй
- Не переводи и не сокращай содержимое
- Верни ТОЛЬКО markdown фрагмента, без пояснений"""


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


def _env_val(name: str, default: str = "") -> str:
    env = (ROOT / ".env").read_text(encoding="utf-8")
    m = re.search(rf"^{re.escape(name)}=(.*)$", env, re.M)
    if not m:
        return default
    return m.group(1).strip().strip('"').strip("'")


def odi_call(system: str, user: str, *, max_tokens: int = 8192, long: bool = False) -> str:
    """POST via curl with request JSON inside the workspace (sandbox-friendly)."""
    gen = Path(__file__).resolve().parent / "generated"
    gen.mkdir(parents=True, exist_ok=True)
    model = _env_val("ODIROUTER_MODEL_LONG" if long else "ODIROUTER_MODEL", "gemini-3.1-flash-lite")
    api_key = _env_val("ODIROUTER_API_KEY_LONG" if long else "ODIROUTER_API_KEY")
    if long and not api_key:
        api_key = _env_val("ODIROUTER_API_KEY")
    if not api_key:
        raise RuntimeError("ODIROUTER_API_KEY missing")
    base = (_env_val("ODIROUTER_BASE_URL", "https://api.odirouter.ai/v1") or "https://api.odirouter.ai/v1").rstrip("/")
    req = gen / f"_odi_req_{os.getpid()}.json"
    resp = gen / f"_odi_resp_{os.getpid()}.json"
    payload = {
        "model": model,
        "max_tokens": max_tokens,
        "temperature": 0.1,
        "system": system,
        "messages": [{"role": "user", "content": user}],
    }
    req.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    last_err = ""
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
            try:
                req.unlink(missing_ok=True)
                resp.unlink(missing_ok=True)
            except OSError:
                pass
            return text.strip()
        last_err = f"http={code} rc={proc.returncode} err={(proc.stderr or '')[:200]}"
        if code in {"429", "502", "503", "504"} or proc.returncode != 0:
            time.sleep(min(60, attempt * 3))
            continue
        break
    raise RuntimeError(f"ODIROUTER failed: {last_err}")


def plain_to_md(plain: str, title: str, lang: str) -> str:
    if not plain.strip():
        return ""
    long = len(plain) > 25000
    max_chunk = 6000 if long else 8500
    parts = []
    chunks = chunk_text(plain, max_chunk)
    for i, ch in enumerate(chunks, 1):
        print(f"  {lang}{i}/{len(chunks)}", flush=True)
        user = (
            f"Язык: {'русский' if lang == 'ru' else 'английский'}\n"
            f"Статья: {title}\n"
            f"Фрагмент {i}/{len(chunks)}. Конвертируй в Markdown.\n\n"
            f"{ch}"
        )
        parts.append(odi_call(MD_SYSTEM, user, max_tokens=16384, long=long or len(ch) > 7000))
    return "\n\n".join(parts)


def extract_json(raw: str) -> dict:
    t = raw.strip()
    m = re.search(r"```(?:json)?\s*([\s\S]*?)```", t, re.I)
    body = m.group(1).strip() if m else t
    start = body.find("{")
    end = body.rfind("}")
    if start < 0 or end <= start:
        raise ValueError("no JSON object")
    return json.loads(body[start : end + 1])


def clip(s: str, n: int) -> str:
    t = re.sub(r"\s+", " ", str(s or "")).strip()
    if len(t) <= n:
        return t
    return re.sub(r"\s+\S*$", "", t[: n - 1]) + "…"


def meta_tags_cats(item: dict, catalog: str, ids: set[int]) -> dict:
    system = f"""Ты размечаешь статью газеты ACNews для архива zxpress.ru.
Не переводи текст. Верни ТОЛЬКО JSON-объект:
{{
  "meta_description_ru": "до 220 символов, по-русски",
  "meta_description_en": "до 220 символов, по-английски",
  "tags": [{{"tag_name":"до 24 символов","tag_alias":"latin-kebab"}}],
  "category_ids": [числа id из каталога]
}}
Правила тегов: 3–6 штук; tag_name ≤24; alias латиницей/цифры/дефис.
Категории: 1–3 id ТОЛЬКО из каталога ниже (предпочтительны листовые).
Каталог категорий (id\\tname_ru):
{catalog}"""
    user = (
        f"Выпуск: ACNews #{item['issue']}\n"
        f"title_ru: {item['title_ru']}\n"
        f"title_en: {item['title_en']}\n\n"
        f"Фрагмент RU:\n{(item['body_ru_plain'] or '')[:3500] or '(нет)'}\n\n"
        f"Фрагмент EN:\n{(item['body_en_plain'] or '')[:3500] or '(нет)'}"
    )
    print("  meta", flush=True)
    data = extract_json(odi_call(system, user, max_tokens=2048, long=False))
    tags = []
    for t in data.get("tags") or []:
        name = str(t.get("tag_name") or "").strip()[:24]
        alias = re.sub(r"[^a-z0-9-]+", "-", str(t.get("tag_alias") or "").strip().lower()).strip("-")[:32]
        if name and alias:
            tags.append({"tag_name": name, "tag_alias": alias})
    cats = []
    for n in data.get("category_ids") or []:
        try:
            cid = int(n)
        except (TypeError, ValueError):
            continue
        if cid in ids and cid not in cats:
            cats.append(cid)
    return {
        "meta_description_ru": clip(data.get("meta_description_ru"), 220),
        "meta_description_en": clip(data.get("meta_description_en"), 220),
        "tags": tags[:6],
        "category_ids": cats[:3],
    }


def load_progress() -> dict:
    if PROGRESS.exists():
        return json.loads(PROGRESS.read_text(encoding="utf-8"))
    return {"done": {}}


def save_progress(p: dict) -> None:
    PROGRESS.parent.mkdir(parents=True, exist_ok=True)
    PROGRESS.write_text(json.dumps(p, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--resume", action="store_true")
    ap.add_argument("--only", default="")
    ap.add_argument("--limit", type=int, default=0)
    args = ap.parse_args()

    OUT.mkdir(parents=True, exist_ok=True)
    manifest = json.loads((TEXTS / "manifest.json").read_text(encoding="utf-8"))
    catalog, cat_ids = load_cats()

    items = []
    for issue, cfg in manifest["issues"].items():
        for a in cfg["articles"]:
            key = f"{issue}/{a['id']}"
            if args.only and args.only not in key and args.only not in a["id"]:
                continue
            ru = (TEXTS / issue / f"{a['id']}_ru.txt").read_text(encoding="utf-8")
            en_path = TEXTS / issue / f"{a['id']}_en.txt"
            en = en_path.read_text(encoding="utf-8") if en_path.exists() else ""
            items.append(
                {
                    "key": key,
                    "issue": issue,
                    "id": a["id"],
                    "title_ru": a["title_ru"],
                    "title_en": a["title_en"],
                    "body_ru_plain": ru,
                    "body_en_plain": en,
                    "chars": len(ru) + len(en),
                }
            )
    items.sort(key=lambda x: (x["chars"], x["key"]))
    if args.limit:
        items = items[: args.limit]

    progress = load_progress()
    done = progress.setdefault("done", {})
    todo = [it for it in items if not (args.resume and done.get(it["key"], {}).get("ok"))]
    print(f"acnews enrich: {len(todo)}/{len(items)} pending", flush=True)

    ok = fail = 0
    for i, item in enumerate(todo, 1):
        print(f"[{i}/{len(todo)}] {item['key']} …", flush=True)
        try:
            body_ru = plain_to_md(item["body_ru_plain"], item["title_ru"], "ru")
            body_en = plain_to_md(item["body_en_plain"], item["title_en"], "en")
            meta = meta_tags_cats(item, catalog, cat_ids)
            out = {
                "press": "ACNews",
                "press_id": 8,
                "issue": item["issue"],
                "article_id": item["id"],
                "title_ru": item["title_ru"],
                "title_en": item["title_en"],
                "meta_description_ru": meta["meta_description_ru"],
                "meta_description_en": meta["meta_description_en"],
                "tags": meta["tags"],
                "category_ids": meta["category_ids"],
                "body_ru_plain": item["body_ru_plain"],
                "body_en_plain": item["body_en_plain"],
                "body_ru": body_ru,
                "body_en": body_en,
                "text_type": 3,
            }
            out_file = OUT / f"{item['issue']}_{item['id']}.json"
            out_file.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
            done[item["key"]] = {"ok": True, "file": out_file.name}
            save_progress(progress)
            ok += 1
            print(" OK", flush=True)
        except Exception as e:  # noqa: BLE001
            fail += 1
            done[item["key"]] = {"ok": False, "error": str(e)}
            save_progress(progress)
            print(f" FAIL {e}", flush=True)
    print(f"done ok={ok} fail={fail}", flush=True)


if __name__ == "__main__":
    main()
