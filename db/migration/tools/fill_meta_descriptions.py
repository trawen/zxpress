#!/usr/bin/env python3
"""Fill meta_description_ru / meta_description_en in i18n JSON files.

Pipeline: take an English article chunk (≤3000 chars by default) → OdiRouter →
write meta_description_ru + meta_description_en back into the JSON.

  # batch over a directory (preferred body_en, optional fallback)
  python3 db/migration/tools/fill_meta_descriptions.py \\
      --i18n-dir=site/cli/imports/restore_i18n --resume --workers=4

  # single file / stdin English chunk → print JSON metas (no write)
  python3 db/migration/tools/fill_meta_descriptions.py --text-file=chunk.txt
  echo '...' | python3 db/migration/tools/fill_meta_descriptions.py --stdin

  # force rewrite existing metas
  python3 db/migration/tools/fill_meta_descriptions.py --i18n-dir=... --force
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
GEN = Path(__file__).resolve().parent / "generated"
DEFAULT_MAX_CHARS = 3000
DEFAULT_META_CHARS = 220

SYSTEM_EN = """You write SEO meta descriptions for ZX Spectrum diskmag articles on zxpress.ru.

Input is an English article excerpt (up to ~3000 characters).
Return ONLY a JSON object:
{
  "meta_description_en": "≤220 chars, English, factual summary of what the article is about",
  "meta_description_ru": "≤220 символов, по-русски, фактическое краткое описание содержания"
}

Rules:
- No clickbait, no quotes of the whole opening sentence unless needed
- Mention concrete topics, products, events, people when present
- Do not invent facts absent from the excerpt
- Prefer one dense sentence (or two short ones)
- Keep proper names / program titles in original spelling
"""

SYSTEM_ANY = """You write SEO meta descriptions for ZX Spectrum diskmag articles on zxpress.ru.

Input is an article excerpt (Czech/Slovak/Russian/English — up to ~3000 characters).
Return ONLY a JSON object:
{
  "meta_description_en": "≤220 chars, English, factual summary of what the article is about",
  "meta_description_ru": "≤220 символов, по-русски, фактическое краткое описание содержания"
}

Rules:
- No clickbait
- Mention concrete topics, products, events, people when present
- Do not invent facts absent from the excerpt
- Prefer one dense sentence (or two short ones)
- Keep proper names / program titles in original spelling
"""


def _env_val(name: str, default: str = "") -> str:
    env_path = ROOT / ".env"
    if not env_path.exists():
        return default
    env = env_path.read_text(encoding="utf-8")
    m = re.search(rf"^{re.escape(name)}=(.*)$", env, re.M)
    if not m:
        return default
    return m.group(1).strip().strip('"').strip("'")


def odi_call(system: str, user: str, *, max_tokens: int = 1024) -> str:
    GEN.mkdir(parents=True, exist_ok=True)
    model = _env_val("ODIROUTER_MODEL", "gemini-3.5-flash")
    api_key = _env_val("ODIROUTER_API_KEY")
    if not api_key:
        raise RuntimeError("ODIROUTER_API_KEY missing")
    base = (_env_val("ODIROUTER_BASE_URL", "https://api.odirouter.ai/v1") or "").rstrip("/")
    req = GEN / f"_meta_req_{os.getpid()}_{time.time_ns()}.json"
    resp = GEN / f"_meta_resp_{os.getpid()}_{time.time_ns()}.json"
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
                    "180",
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
        raise ValueError(f"no JSON object: {raw[:240]}")
    return json.loads(body[start : end + 1])


def clip(s: str, n: int) -> str:
    t = re.sub(r"\s+", " ", str(s or "")).strip()
    if len(t) <= n:
        return t
    return re.sub(r"\s+\S*$", "", t[: n - 1]).rstrip(".,;:") + "…"


def strip_md_heading(text: str) -> str:
    t = text.replace("\r\n", "\n").strip()
    t = re.sub(r"\A#\s+[^\n]*\n+", "", t, count=1)
    return t.strip()


def take_chunk(text: str, max_chars: int) -> str:
    t = strip_md_heading(text)
    if len(t) <= max_chars:
        return t
    cut = t.rfind("\n\n", 0, max_chars)
    if cut < max_chars * 0.5:
        cut = t.rfind("\n", 0, max_chars)
    if cut < max_chars * 0.3:
        cut = max_chars
    return t[:cut].rstrip()


def pick_source(data: dict, source_field: str, fallback_field: str) -> tuple[str, str]:
    """Return (chunk_text, field_used)."""
    for field in (source_field, fallback_field):
        if not field:
            continue
        val = data.get(field)
        if isinstance(val, str) and val.strip():
            return val, field
        # plain variants
        plain = data.get(field + "_plain") if not field.endswith("_plain") else None
        if isinstance(plain, str) and plain.strip():
            return plain, field + "_plain"
    return "", ""


def generate_metas(
    chunk: str,
    *,
    title: str = "",
    press: str = "",
    issue: str = "",
    meta_chars: int = DEFAULT_META_CHARS,
    from_english: bool = True,
) -> dict[str, str]:
    label = "English excerpt" if from_english else "Article excerpt"
    system = SYSTEM_EN if from_english else SYSTEM_ANY
    user = (
        f"Press: {press or 'unknown'}\n"
        f"Issue: {issue or '?'}\n"
        f"Title: {title or '?'}\n\n"
        f"{label}:\n{chunk}"
    )
    raw = extract_json(odi_call(system, user, max_tokens=1024))
    return {
        "meta_description_en": clip(raw.get("meta_description_en"), meta_chars),
        "meta_description_ru": clip(raw.get("meta_description_ru"), meta_chars),
    }


def process_file(
    path: Path,
    *,
    source_field: str,
    fallback_field: str,
    max_chars: int,
    meta_chars: int,
    force: bool,
    dry_run: bool,
) -> str:
    data = json.loads(path.read_text(encoding="utf-8"))
    has_ru = bool(str(data.get("meta_description_ru") or "").strip())
    has_en = bool(str(data.get("meta_description_en") or "").strip())
    if has_ru and has_en and not force:
        return f"skip {path.name} (metas present)"

    text, field = pick_source(data, source_field, fallback_field)
    if not text:
        return f"FAIL {path.name}: no {source_field}/{fallback_field}"

    title = str(
        data.get("title_en")
        or data.get("title_ru")
        or data.get("title_cs")
        or data.get("title")
        or path.stem
    )
    press = str(data.get("press") or "")
    issue = str(data.get("issue") or data.get("issue_key") or "")

    chunk = take_chunk(text, max_chars)
    from_english = field.startswith("body_en") or field in {"en", "body_en_plain"}
    metas = generate_metas(
        chunk,
        title=title,
        press=press,
        issue=issue,
        meta_chars=meta_chars,
        from_english=from_english,
    )
    if dry_run:
        return (
            f"dry-run {path.name} src={field} chars={len(chunk)} "
            f"en={metas['meta_description_en']!r} ru={metas['meta_description_ru']!r}"
        )

    data["meta_description_en"] = metas["meta_description_en"]
    data["meta_description_ru"] = metas["meta_description_ru"]
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return (
        f"ok {path.name} src={field} "
        f"en={len(metas['meta_description_en'])}c ru={len(metas['meta_description_ru'])}c"
    )


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--i18n-dir", default="", help="Directory with *.json translation files")
    ap.add_argument("--only", default="", help="Substring filter on filename")
    ap.add_argument("--source-field", default="body_en", help="Preferred text field (English)")
    ap.add_argument(
        "--fallback-field",
        default="body_cs",
        help="Fallback if source empty (will be translated to EN first)",
    )
    ap.add_argument("--max-chars", type=int, default=DEFAULT_MAX_CHARS)
    ap.add_argument("--meta-chars", type=int, default=DEFAULT_META_CHARS)
    ap.add_argument("--force", action="store_true")
    ap.add_argument("--resume", action="store_true", help="Skip files that already have both metas")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--workers", type=int, default=3)
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--text-file", default="", help="Single English chunk file → print metas JSON")
    ap.add_argument("--stdin", action="store_true", help="Read English chunk from stdin → print metas JSON")
    ap.add_argument("--title", default="")
    ap.add_argument("--press", default="")
    ap.add_argument("--issue", default="")
    args = ap.parse_args()

    # Single-shot mode
    if args.stdin or args.text_file:
        if args.stdin:
            raw = sys.stdin.read()
        else:
            raw = Path(args.text_file).read_text(encoding="utf-8")
        en_chunk = take_chunk(raw, args.max_chars)
        metas = generate_metas(
            en_chunk,
            title=args.title,
            press=args.press,
            issue=args.issue,
            meta_chars=args.meta_chars,
            from_english=True,
        )
        print(json.dumps(metas, ensure_ascii=False, indent=2))
        return

    if not args.i18n_dir:
        ap.error("Need --i18n-dir or --text-file/--stdin")

    i18n = Path(args.i18n_dir)
    if not i18n.is_absolute():
        i18n = ROOT / i18n
    if not i18n.is_dir():
        raise SystemExit(f"Not a directory: {i18n}")

    files = sorted(i18n.glob("*.json"))
    if args.only:
        files = [p for p in files if args.only in p.name]
    if args.resume and not args.force:
        # process_file already skips; resume flag is alias for default skip behavior
        pass
    if args.limit > 0:
        files = files[: args.limit]
    if not files:
        raise SystemExit("No JSON files matched")

    print(f"files={len(files)} workers={args.workers} source={args.source_field} fallback={args.fallback_field}")
    ok = fail = skip = 0
    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as ex:
        futs = {
            ex.submit(
                process_file,
                p,
                source_field=args.source_field,
                fallback_field=args.fallback_field,
                max_chars=args.max_chars,
                meta_chars=args.meta_chars,
                force=args.force,
                dry_run=args.dry_run,
            ): p
            for p in files
        }
        for fut in as_completed(futs):
            try:
                msg = fut.result()
            except Exception as e:  # noqa: BLE001
                fail += 1
                print(f"FAIL {futs[fut].name}: {e}", flush=True)
                continue
            print(msg, flush=True)
            if msg.startswith("ok"):
                ok += 1
            elif msg.startswith("skip"):
                skip += 1
            elif msg.startswith("dry-run"):
                ok += 1
            else:
                fail += 1
    print(f"done ok={ok} skip={skip} fail={fail}")


if __name__ == "__main__":
    main()
