#!/usr/bin/env bash
# Call OdiRouter /messages via curl (works in Cursor allowlist; node fetch/proxy often hangs).
# Usage: odi_curl.sh SYSTEM_FILE USER_FILE OUT_FILE [MAX_TOKENS] [MODEL]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
# script lives in db/migration/acnews_76_80 → repo root is ../../..
# shellcheck disable=SC1091
set -a
# load key without exporting everything blindly
API_KEY="$(python3 - <<'PY' "$ROOT"
import re, sys
from pathlib import Path
root = Path(sys.argv[1])
for name in (".env", "local/.env"):
    p = root / name
    if not p.exists():
        continue
    text = p.read_text(encoding="utf-8")
    m = re.search(r"^ODIROUTER_API_KEY=(.*)$", text, re.M)
    if m:
        print(m.group(1).strip().strip('"').strip("'"))
        break
PY
)"
MODEL_DEFAULT="$(python3 - <<'PY' "$ROOT"
import re, sys
from pathlib import Path
root = Path(sys.argv[1])
for name in (".env", "local/.env"):
    p = root / name
    if not p.exists():
        continue
    text = p.read_text(encoding="utf-8")
    m = re.search(r"^ODIROUTER_MODEL=(.*)$", text, re.M)
    if m:
        print(m.group(1).strip().strip('"').strip("'"))
        break
else:
    print("gemini-3.1-flash-lite")
PY
)"
MODEL_LONG="$(python3 - <<'PY' "$ROOT"
import re, sys
from pathlib import Path
root = Path(sys.argv[1])
for name in (".env", "local/.env"):
    p = root / name
    if not p.exists():
        continue
    text = p.read_text(encoding="utf-8")
    m = re.search(r"^ODIROUTER_MODEL_LONG=(.*)$", text, re.M)
    if m:
        print(m.group(1).strip().strip('"').strip("'"))
        break
else:
    print("gemini-3.7-flash")
PY
)"
API_KEY_LONG="$(python3 - <<'PY' "$ROOT"
import re, sys
from pathlib import Path
root = Path(sys.argv[1])
for name in (".env", "local/.env"):
    p = root / name
    if not p.exists():
        continue
    text = p.read_text(encoding="utf-8")
    m = re.search(r"^ODIROUTER_API_KEY_LONG=(.*)$", text, re.M)
    if m:
        print(m.group(1).strip().strip('"').strip("'"))
        break
PY
)"
set +a

SYSTEM_FILE="${1:?}"
USER_FILE="${2:?}"
OUT_FILE="${3:?}"
MAX_TOKENS="${4:-8192}"
FORCE_MODEL="${5:-}"
LONG_FLAG="${6:-0}"

if [[ -n "$FORCE_MODEL" ]]; then
  MODEL="$FORCE_MODEL"
elif [[ "$LONG_FLAG" == "1" ]]; then
  MODEL="$MODEL_LONG"
  if [[ -n "${API_KEY_LONG:-}" ]]; then API_KEY="$API_KEY_LONG"; fi
else
  MODEL="$MODEL_DEFAULT"
fi

BASE="${ODIROUTER_BASE_URL:-https://api.odirouter.ai/v1}"
BASE="${BASE%/}"

REQ="$(mktemp)"
python3 - <<'PY' "$SYSTEM_FILE" "$USER_FILE" "$REQ" "$MODEL" "$MAX_TOKENS"
import json, sys
system = open(sys.argv[1], encoding="utf-8").read()
user = open(sys.argv[2], encoding="utf-8").read()
out = sys.argv[3]
model = sys.argv[4]
max_tokens = int(sys.argv[5])
payload = {
    "model": model,
    "max_tokens": max_tokens,
    "temperature": 0.1,
    "system": system,
    "messages": [{"role": "user", "content": user}],
}
open(out, "w", encoding="utf-8").write(json.dumps(payload, ensure_ascii=False))
PY

RESP="$(mktemp)"
HTTP_CODE=0
for attempt in 1 2 3 4 5 6 7 8; do
  set +e
  HTTP_CODE=$(curl -sS -m 300 -o "$RESP" -w "%{http_code}" -X POST "$BASE/messages" \
    -H "content-type: application/json" \
    -H "Authorization: Bearer $API_KEY" \
    -H "x-api-key: $API_KEY" \
    -H "anthropic-version: 2023-06-01" \
    --data-binary @"$REQ")
  RC=$?
  set -e
  if [[ $RC -ne 0 ]]; then
    sleep $((attempt * 2))
    continue
  fi
  if [[ "$HTTP_CODE" == "429" || "$HTTP_CODE" == "502" || "$HTTP_CODE" == "503" || "$HTTP_CODE" == "504" ]]; then
    sleep $((attempt * 3))
    continue
  fi
  break
done

if [[ "$HTTP_CODE" != "200" ]]; then
  echo "ODIROUTER HTTP $HTTP_CODE" >&2
  head -c 400 "$RESP" >&2 || true
  echo >&2
  rm -f "$REQ" "$RESP"
  exit 1
fi

python3 - <<'PY' "$RESP" "$OUT_FILE"
import json, sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
content = data.get("content")
if isinstance(content, list):
    text = "".join((c.get("text") or "") for c in content)
else:
    text = str(content or "")
open(sys.argv[2], "w", encoding="utf-8").write(text)
PY
rm -f "$REQ" "$RESP"
