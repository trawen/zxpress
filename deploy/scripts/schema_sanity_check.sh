#!/usr/bin/env bash
# Quick post-restore check: table count in zxpress_db (compare with expectations / repo schema).
# Does not replace a full mysqldump --no-data diff; see docs/operations.md.
# Logging: [restore] INFO to stderr.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

log_info() { echo "[restore] INFO $*" >&2; }

load_env() {
	local env_file="$PROJECT_DIR/.env"
	[ -f "$env_file" ] || return 1
	set -a
	# shellcheck disable=SC1090
	source "$env_file"
	set +a
}

load_env || exit 1
ROOT_PW="${MYSQL_ROOT_PASSWORD:-}"
DB_NAME_VAL="${DB_NAME:-zxpress_db}"
if [ -z "$ROOT_PW" ]; then
	echo "[restore] ERROR MYSQL_ROOT_PASSWORD empty" >&2
	exit 1
fi

cd "$PROJECT_DIR"

CNT=$(docker compose -f "$COMPOSE_FILE" exec -T db mysql -uroot -p"${ROOT_PW}" -N -e \
	"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME_VAL}'" 2>/dev/null || echo "0")

log_info "schema_sanity table_count=${CNT} database=${DB_NAME_VAL}"
