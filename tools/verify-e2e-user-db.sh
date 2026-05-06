#!/usr/bin/env bash
# Verify local Docker MySQL users row vs E2E_* from .env files (same merge as Playwright).
# Does not print plaintext password or full hash.
# Usage: bash tools/verify-e2e-user-db.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

set -a
# shellcheck disable=SC1091
source .env
set +a

CREDS=$(node <<'NODE'
const fs = require('fs');
const path = require('path');
const root = process.cwd();
function parse(t) {
  const o = {};
  for (const line of t.split('\n')) {
    const s = line.trim();
    if (!s || s.startsWith('#')) continue;
    const i = s.indexOf('=');
    if (i === -1) continue;
    const k = s.slice(0, i).trim();
    let v = s.slice(i + 1).trim();
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'")))
      v = v.slice(1, -1);
    o[k] = v;
  }
  return o;
}
let m = {};
for (const f of [path.join(root, '.env'), path.join(root, '.env.e2e'), path.join(root, 'e2e', '.env.e2e')]) {
  try {
    if (fs.existsSync(f)) Object.assign(m, parse(fs.readFileSync(f, 'utf8')));
  } catch (e) {}
}
const user = (m.E2E_ADMIN_USER || 'admin').trim();
const pass = (m.E2E_ADMIN_PASS || '').trim();
if (!pass) {
  console.error('E2E_ADMIN_PASS empty');
  process.exit(2);
}
process.stdout.write(JSON.stringify({ user, pass }));
NODE
)

USER=$(node -e "console.log(JSON.parse(process.argv[1]).user)" "$CREDS")
PLAIN=$(node -e "console.log(JSON.parse(process.argv[1]).pass)" "$CREDS")

echo "[FIX] verify-e2e-user-db.sh user=${USER} plain_len=${#PLAIN}"

U_ESC=$(printf "%s" "$USER" | sed "s/'/''/g")

ROW=$(docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -B \
  -e "SELECT password, IFNULL(\`level\`, 'NULL') FROM users WHERE username = '${U_ESC}';")

if [[ -z "${ROW}" ]]; then
  echo "[FIX] MySQL: no row for username=${USER}"
  exit 3
fi

export ROW
export PLAIN
PY_OUT=$(python3 <<'PY'
import os, base64, json
row = os.environ["ROW"]
parts = row.split("\t", 1)
if len(parts) != 2:
    raise SystemExit("bad mysql row")
pw, lev = parts[0], parts[1].strip()
plain = os.environ["PLAIN"]
lev_out = "null" if lev == "NULL" else lev
print(json.dumps({
    "plain_b64": base64.b64encode(plain.encode("utf-8")).decode("ascii"),
    "hash_b64": base64.b64encode(pw.encode("utf-8")).decode("ascii"),
    "level": lev_out,
}))
PY
)

PLAIN_B64=$(node -e "console.log(JSON.parse(process.argv[1]).plain_b64)" "$PY_OUT")
HASH_B64=$(node -e "console.log(JSON.parse(process.argv[1]).hash_b64)" "$PY_OUT")
LEVEL=$(node -e "console.log(JSON.parse(process.argv[1]).level)" "$PY_OUT")

bash tools/php.sh tools/verify-e2e-user-db.php "$PLAIN_B64" "$HASH_B64" "$LEVEL"
