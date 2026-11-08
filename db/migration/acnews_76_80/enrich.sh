#!/usr/bin/env bash
# Enrich ACNews 76–80 using direct curl (sandbox allowlist-friendly).
#   bash db/migration/acnews_76_80/enrich.sh
#   bash db/migration/acnews_76_80/enrich.sh 76/02_chess
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT"
TEXTS="$ROOT/local/work/ACN7680_texts"
OUT="$TEXTS/articles"
GEN="$ROOT/db/migration/acnews_76_80/generated"
CATS="$ROOT/db/migration/subxtc_article_categories/categories_catalog.txt"
PROGRESS="$GEN/enrich_progress.json"
ONLY="${1:-}"
mkdir -p "$OUT" "$GEN"

API_KEY=$(python3 -c 'import re;print(re.search(r"^ODIROUTER_API_KEY=(.*)$",open(".env").read(),re.M).group(1).strip().strip(chr(34)))')
API_KEY_LONG=$(python3 -c 'import re;m=re.search(r"^ODIROUTER_API_KEY_LONG=(.*)$",open(".env").read(),re.M);print(m.group(1).strip().strip(chr(34)) if m else "")')
MODEL=$(python3 -c 'import re;print(re.search(r"^ODIROUTER_MODEL=(.*)$",open(".env").read(),re.M).group(1).strip().strip(chr(34)))')
MODEL_LONG=$(python3 -c 'import re;m=re.search(r"^ODIROUTER_MODEL_LONG=(.*)$",open(".env").read(),re.M);print(m.group(1).strip().strip(chr(34)) if m else "gemini-3.7-flash")')
BASE="https://api.odirouter.ai/v1"

odi() {
  # $1 system file, $2 user file, $3 out file, $4 max_tokens, $5 long(0/1)
  local sysf="$1" userf="$2" outf="$3" max_tokens="$4" long="$5"
  local key="$API_KEY" model="$MODEL"
  if [[ "$long" == "1" ]]; then
    model="$MODEL_LONG"
    [[ -n "$API_KEY_LONG" ]] && key="$API_KEY_LONG"
  fi
  python3 - "$sysf" "$userf" "$GEN/_req.json" "$model" "$max_tokens" <<'PY'
import json,sys
sys_t=open(sys.argv[1],encoding='utf-8').read()
user_t=open(sys.argv[2],encoding='utf-8').read()
payload={"model":sys.argv[4],"max_tokens":int(sys.argv[5]),"temperature":0.1,"system":sys_t,"messages":[{"role":"user","content":user_t}]}
open(sys.argv[3],"w",encoding="utf-8").write(json.dumps(payload,ensure_ascii=False))
PY
  local code="" attempt=1
  while [[ $attempt -le 8 ]]; do
    code=$(curl -sS -m 300 -o "$GEN/_resp.json" -w "%{http_code}" -X POST "$BASE/messages" \
      -H "content-type: application/json" \
      -H "Authorization: Bearer $key" \
      -H "x-api-key: $key" \
      -H "anthropic-version: 2023-06-01" \
      --data-binary @"$GEN/_req.json" || true)
    if [[ "$code" == "200" ]]; then
      python3 - "$GEN/_resp.json" "$outf" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
c=d.get('content')
text=''.join((x.get('text') or '') for x in c) if isinstance(c,list) else str(c or '')
open(sys.argv[2],'w',encoding='utf-8').write(text.strip())
PY
      return 0
    fi
    echo "  retry $attempt http=$code" >&2
    sleep $((attempt * 2))
    attempt=$((attempt + 1))
  done
  echo "ODI fail http=$code" >&2
  return 1
}

python3 - "$TEXTS/manifest.json" "$OUT" "$GEN" "$CATS" "$PROGRESS" "$ONLY" <<'PY'
import json, os, re, subprocess, sys, time
from pathlib import Path

manifest_path, out_dir, gen, cats_file, progress_path, only = sys.argv[1:7]
manifest = json.loads(Path(manifest_path).read_text(encoding="utf-8"))
out_dir = Path(out_dir); gen = Path(gen); progress_path = Path(progress_path)
catalog_lines=[]; ids=set()
for line in Path(cats_file).read_text(encoding="utf-8").splitlines():
    s=line.strip()
    if not s or s.startswith("#"): continue
    m=re.match(r"^(\d+)\t(.+)$", s)
    if not m: continue
    ids.add(int(m.group(1))); catalog_lines.append(f"{m.group(1)}\t{m.group(2)}")
catalog="\n".join(catalog_lines)
progress=json.loads(progress_path.read_text(encoding="utf-8")) if progress_path.exists() else {"done":{}}
done=progress.setdefault("done",{})

MD_SYSTEM = """Ты конвертируешь текст газеты ACNews (Alone Coder, ZX Spectrum / ATM / NedoOS) в Markdown для zxpress.ru.

Правила:
- Заголовки: ## / ###
- Списки — markdown-списки; таблицы где уместно
- ASCII-арт, рамки, моноширинные блоки, листинги, словари → ```text … ```
- Имена людей, URL, e-mail, названия программ/платформ не меняй
- Не переводи и не сокращай содержимое
- Верни ТОЛЬКО markdown фрагмента, без пояснений"""

def chunk(text, n):
    if len(text)<=n: return [text]
    out=[]; rest=text
    while len(rest)>n:
        cut=rest.rfind("\n\n",0,n)
        if cut<n*0.5: cut=rest.rfind("\n",0,n)
        if cut<n*0.3: cut=n
        out.append(rest[:cut]); rest=rest[cut:]
    if rest: out.append(rest)
    return out

def call_odi(system, user, out_path, max_tokens=8192, long=False):
    sysf=gen/"_system.txt"; userf=gen/"_user.txt"
    sysf.write_text(system, encoding="utf-8"); userf.write_text(user, encoding="utf-8")
    # invoke parent bash function via env marker file — instead call curl through bash -c wrapper exported
    env=os.environ.copy()
    # Call the shell helper defined in enrich.sh by re-execing a tiny bash snippet that sources nothing
    # but duplicates odi using the same allowlisted curl pattern.
    script=f'''
set -euo pipefail
cd "{Path.cwd()}"
API_KEY=$(python3 -c 'import re;print(re.search(r"^ODIROUTER_API_KEY=(.*)$",open(".env").read(),re.M).group(1).strip().strip(chr(34)))')
API_KEY_LONG=$(python3 -c 'import re;m=re.search(r"^ODIROUTER_API_KEY_LONG=(.*)$",open(".env").read(),re.M);print(m.group(1).strip().strip(chr(34)) if m else "")')
MODEL=$(python3 -c 'import re;print(re.search(r"^ODIROUTER_MODEL=(.*)$",open(".env").read(),re.M).group(1).strip().strip(chr(34)))')
MODEL_LONG=$(python3 -c 'import re;m=re.search(r"^ODIROUTER_MODEL_LONG=(.*)$",open(".env").read(),re.M);print(m.group(1).strip().strip(chr(34)) if m else "gemini-3.7-flash")')
key="$API_KEY"; model="$MODEL"
if [[ "{int(long)}" == "1" ]]; then model="$MODEL_LONG"; [[ -n "$API_KEY_LONG" ]] && key="$API_KEY_LONG"; fi
python3 - <<'P2' "{sysf}" "{userf}" "{gen}/_req.json" "$model" "{max_tokens}"
import json,sys
payload={{"model":sys.argv[4],"max_tokens":int(sys.argv[5]),"temperature":0.1,"system":open(sys.argv[1],encoding="utf-8").read(),"messages":[{{"role":"user","content":open(sys.argv[2],encoding="utf-8").read()}}]}}
open(sys.argv[3],"w",encoding="utf-8").write(json.dumps(payload,ensure_ascii=False))
P2
code=""
for attempt in 1 2 3 4 5 6 7 8; do
  code=$(curl -sS -m 300 -o "{gen}/_resp.json" -w "%{{http_code}}" -X POST "https://api.odirouter.ai/v1/messages" \
    -H "content-type: application/json" -H "Authorization: Bearer $key" -H "x-api-key: $key" \
    -H "anthropic-version: 2023-06-01" --data-binary @"{gen}/_req.json" || true)
  if [[ "$code" == "200" ]]; then
    python3 - <<'P3' "{gen}/_resp.json" "{out_path}"
import json,sys
d=json.load(open(sys.argv[1],encoding="utf-8"))
c=d.get("content")
text="".join((x.get("text") or "") for x in c) if isinstance(c,list) else str(c or "")
open(sys.argv[2],"w",encoding="utf-8").write(text.strip())
P3
    exit 0
  fi
  echo "  retry $attempt http=$code" >&2
  sleep $((attempt*2))
done
echo "ODI fail http=$code" >&2
exit 1
'''
    r=subprocess.run(["bash","-lc", script], check=False)
    if r.returncode!=0:
        raise RuntimeError("odi call failed")
    return Path(out_path).read_text(encoding="utf-8").strip()

def plain_to_md(plain, title, lang):
    if not plain.strip(): return ""
    long=len(plain)>25000
    parts=[]
    chunks=chunk(plain, 6000 if long else 8500)
    for i,ch in enumerate(chunks,1):
        print(f"  {lang}{i}/{len(chunks)}", flush=True)
        user=f"Язык: {'русский' if lang=='ru' else 'английский'}\nСтатья: {title}\nФрагмент {i}/{len(chunks)}. Конвертируй в Markdown.\n\n{ch}"
        outp=gen/f"_md_{lang}_{i}.txt"
        parts.append(call_odi(MD_SYSTEM, user, outp, max_tokens=16384, long=long or len(ch)>7000))
    return "\n\n".join(parts)

def clip(s,n):
    t=re.sub(r"\s+"," ",str(s or "")).strip()
    return t if len(t)<=n else re.sub(r"\s+\S*$","",t[:n-1])+"…"

def meta_tags(item):
    system=f"""Ты размечаешь статью газеты ACNews для архива zxpress.ru.
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
    user=(f"Выпуск: ACNews #{item['issue']}\n"
          f"title_ru: {item['title_ru']}\n"
          f"title_en: {item['title_en']}\n\n"
          f"Фрагмент RU:\n{(item['body_ru_plain'] or '')[:3500] or '(нет)'}\n\n"
          f"Фрагмент EN:\n{(item['body_en_plain'] or '')[:3500] or '(нет)'}")
    print("  meta", flush=True)
    raw=call_odi(system, user, gen/"_meta.txt", max_tokens=2048, long=False)
    m=re.search(r"```(?:json)?\s*([\s\S]*?)```", raw, re.I)
    body=m.group(1).strip() if m else raw
    start=body.find("{"); end=body.rfind("}")
    data=json.loads(body[start:end+1])
    tags=[]
    for t in data.get("tags") or []:
        name=str(t.get("tag_name") or "").strip()[:24]
        alias=re.sub(r"[^a-z0-9-]+","-",str(t.get("tag_alias") or "").strip().lower()).strip("-")[:32]
        if name and alias: tags.append({"tag_name":name,"tag_alias":alias})
    cats=[]
    for n in data.get("category_ids") or []:
        try: cid=int(n)
        except Exception: continue
        if cid in ids and cid not in cats: cats.append(cid)
    return {
        "meta_description_ru": clip(data.get("meta_description_ru"),220),
        "meta_description_en": clip(data.get("meta_description_en"),220),
        "tags": tags[:6],
        "category_ids": cats[:3],
    }

items=[]
texts=Path(manifest_path).parent
for issue,cfg in manifest["issues"].items():
    for a in cfg["articles"]:
        key=f"{issue}/{a['id']}"
        if only and only not in key and only not in a["id"]:
            continue
        ru=(texts/issue/f"{a['id']}_ru.txt").read_text(encoding="utf-8")
        en_path=texts/issue/f"{a['id']}_en.txt"
        en=en_path.read_text(encoding="utf-8") if en_path.exists() else ""
        items.append({"key":key,"issue":issue,"id":a["id"],"title_ru":a["title_ru"],"title_en":a["title_en"],
                      "body_ru_plain":ru,"body_en_plain":en,"chars":len(ru)+len(en)})
items.sort(key=lambda x:(x["chars"],x["key"]))
todo=[it for it in items if not done.get(it["key"],{}).get("ok")]
print(f"acnews enrich: {len(todo)}/{len(items)} pending", flush=True)
ok=fail=0
for i,item in enumerate(todo,1):
    print(f"[{i}/{len(todo)}] {item['key']} …", flush=True)
    try:
        body_ru=plain_to_md(item["body_ru_plain"], item["title_ru"], "ru")
        body_en=plain_to_md(item["body_en_plain"], item["title_en"], "en")
        meta=meta_tags(item)
        out={
            "press":"ACNews","press_id":8,"issue":item["issue"],"article_id":item["id"],
            "title_ru":item["title_ru"],"title_en":item["title_en"],
            "meta_description_ru":meta["meta_description_ru"],
            "meta_description_en":meta["meta_description_en"],
            "tags":meta["tags"],"category_ids":meta["category_ids"],
            "body_ru_plain":item["body_ru_plain"],"body_en_plain":item["body_en_plain"],
            "body_ru":body_ru,"body_en":body_en,"text_type":3,
        }
        out_file=out_dir/f"{item['issue']}_{item['id']}.json"
        out_file.write_text(json.dumps(out,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
        done[item["key"]]={"ok":True,"file":out_file.name}
        progress_path.write_text(json.dumps(progress,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
        ok+=1; print(" OK", flush=True)
    except Exception as e:
        fail+=1; done[item["key"]]={"ok":False,"error":str(e)}
        progress_path.write_text(json.dumps(progress,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
        print(f" FAIL {e}", flush=True)
print(f"done ok={ok} fail={fail}", flush=True)
PY
