#!/bin/bash
# Validate env template and .gitignore rules for secret-bearing paths.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
GITIGNORE="$ROOT/.gitignore"
FAILED=0

fail() {
	echo "FAIL: $1" >&2
	FAILED=1
}

[[ -f "$ROOT/.env.example" ]] || fail ".env.example missing"

grep -qxF 'conf/manticore.conf' "$GITIGNORE" 2>/dev/null \
	|| grep -q '^conf/manticore.conf$' "$GITIGNORE" \
	|| fail ".gitignore should list conf/manticore.conf"

grep -qE '^conf/zxpress_db\.mysql\.\*$' "$GITIGNORE" \
	|| fail ".gitignore should list conf/zxpress_db.mysql.*"

echo "validate_env_contract.sh: OK"
exit "$FAILED"
