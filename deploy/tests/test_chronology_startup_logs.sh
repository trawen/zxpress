#!/usr/bin/env bash
# Requires: docker compose stack running (php container).
# Asserts php startup chronology does not target legacy data/generated/zxpress_dinamic.png
# and that successful write targets cache/chronology (see entrypoint + regenerate_chronology_graph.php).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail=0
log_err() { printf '[ERROR] %s\n' "$*" >&2; fail=1; }

if ! command -v docker >/dev/null 2>&1; then
	log_err "docker not found"
	exit 1
fi

if ! docker compose ps --services 2>/dev/null | grep -qx 'php'; then
	log_err "compose project has no php service (run from repo root with docker compose up -d)"
	exit 1
fi

if ! docker compose ps php 2>/dev/null | grep -q 'Up'; then
	log_err "php container is not running"
	exit 1
fi

# Recent php logs only (avoid unbounded history).
LOGS="$(docker compose logs --tail 600 php 2>&1)"

if printf '%s\n' "$LOGS" | grep -qE 'data/generated/zxpress_dinamic\.png|/generated/zxpress_dinamic\.png'; then
	log_err "legacy chronology PNG path still appears in php logs (expected cache/chronology only)"
	printf '%s\n' "$LOGS" | grep -nE 'data/generated/zxpress_dinamic|/generated/zxpress_dinamic' >&2 || true
fi

if printf '%s\n' "$LOGS" | grep -q 'failed to write .*generated'; then
	log_err "chronology failed to write under legacy generated/ path"
fi

if ! printf '%s\n' "$LOGS" | grep -qE 'cache/chronology/zxpress_dinamic\.png'; then
	log_err "expected successful chronology path cache/chronology/zxpress_dinamic.png in recent php logs"
fi

if [ "$fail" -ne 0 ]; then
	echo "test_chronology_startup_logs.sh: FAIL"
	exit 1
fi

echo "test_chronology_startup_logs.sh: OK"
