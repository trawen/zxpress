#!/usr/bin/env bash
# Rebuild Manticore indexes and RELOAD TABLES (run after MySQL restore).
# Logging: [restore] INFO|ERROR to stderr.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

log_info() { echo "[restore] INFO $*" >&2; }
log_error() { echo "[restore] ERROR $*" >&2; }

cd "$PROJECT_DIR"

log_info "manticore_index_start"
if ! bash "$SCRIPT_DIR/manticore-index-all.sh"; then
	log_error "manticore indexer failed"
	exit 1
fi

if ! docker compose -f "$COMPOSE_FILE" exec -T manticore mysql -P9306 -h0 -e "RELOAD TABLES" 2>/dev/null; then
	log_error "RELOAD TABLES failed"
	exit 1
fi

log_info "manticore_index_done reload=ok"
