#!/usr/bin/env bash
# Rebuild Manticore indexes inside the running container.
# Strategy:
# 1) Normalize ownership of /var/lib/manticore as root (no host sudo needed)
# 2) Run indexer as the regular manticore service user (least privilege)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$PROJECT_DIR"

log_info() { echo "[restore] INFO $*" >&2; }
log_warn() { echo "[restore] WARN $*" >&2; }

log_info "manticore_permissions_prepare path=/var/lib/manticore"
if ! docker compose exec -T -u 0 manticore sh -lc '
	set -eu
	mkdir -p /var/lib/manticore
	chown -R manticore:manticore /var/lib/manticore || true
'; then
	log_warn "ownership normalize failed, trying indexer anyway"
fi

# Run with least privilege (service user) and rotate in-place.
log_info "[FIX] manticore_index_run user=manticore rotate=1"
docker compose exec -T manticore sh -lc 'su -s /bin/sh -c "indexer --all --rotate $*" manticore' sh "$@"
