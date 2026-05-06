#!/usr/bin/env bash
# Runs deploy/scripts/manticore-index-all.sh and fails on permission / indexer fatal errors.
# Requires: docker compose stack with healthy db + manticore (indexer reads MySQL).
# Note: full reindex; may take several seconds.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail=0
log_err() { printf '[ERROR] %s\n' "$*" >&2; fail=1; }

if ! command -v docker >/dev/null 2>&1; then
	log_err "docker not found"
	exit 1
fi

if ! docker compose ps --services 2>/dev/null | grep -qx 'manticore'; then
	log_err "compose project has no manticore service"
	exit 1
fi

if ! docker compose ps manticore 2>/dev/null | grep -q 'Up'; then
	log_err "manticore container is not running"
	exit 1
fi

if ! docker compose ps db 2>/dev/null | grep -q 'Up'; then
	log_err "db container is not running (indexer needs MySQL)"
	exit 1
fi

OUT="$(mktemp)"
trap 'rm -f "$OUT"' EXIT

set +e
bash deploy/scripts/manticore-index-all.sh >"$OUT" 2>&1
rc=$?
set -e

if [ "$rc" -ne 0 ]; then
	log_err "manticore-index-all.sh exited with $rc"
	sed -n '1,80p' "$OUT" >&2
	exit 1
fi

if grep -qi 'permission denied' "$OUT"; then
	log_err "indexer output contains Permission denied"
	grep -ni 'permission denied' "$OUT" >&2 || true
	exit 1
fi

if grep -qiE 'FATAL: failed to open|will not index' "$OUT"; then
	log_err "indexer output contains fatal open/index errors"
	grep -niE 'FATAL: failed to open|will not index' "$OUT" >&2 || true
	exit 1
fi

echo "test_manticore_index_permissions.sh: OK"
