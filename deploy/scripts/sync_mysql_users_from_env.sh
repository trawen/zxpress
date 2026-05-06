#!/usr/bin/env bash
# Align MySQL users zxpress_u and manticore_ro with .env (passwords + grants).
# Run after restore from an old dump so DB_PASS / MANTICORE_DB_PASS match the app and Manticore indexer.
# Requires: MYSQL_ROOT_PASSWORD, DB_USER/DB_PASS, MANTICORE_DB_PASS, DB_NAME in .env.
# Logging: [restore] INFO|WARN|ERROR to stderr.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

log_info() { echo "[restore] INFO $*" >&2; }
log_warn() { echo "[restore] WARN $*" >&2; }
log_error() { echo "[restore] ERROR $*" >&2; }

load_env() {
	local env_file="$PROJECT_DIR/.env"
	if [ ! -f "$env_file" ]; then
		log_error ".env not found at $env_file"
		exit 1
	fi
	set -a
	# shellcheck disable=SC1090
	source "$env_file"
	set +a
}

load_env

ROOT_PW="${MYSQL_ROOT_PASSWORD:-}"
DB_USER_NAME="${DB_USER:-zxpress_u}"
DB_PASS_VAL="${DB_PASS:-}"
DB_NAME_VAL="${DB_NAME:-zxpress_db}"
MANTICORE_USER="${MANTICORE_DB_USER:-manticore_ro}"
MANTICORE_PASS="${MANTICORE_DB_PASS:-}"

if [ -z "$ROOT_PW" ]; then
	log_error "MYSQL_ROOT_PASSWORD is empty"
	exit 1
fi
if [ -z "$DB_PASS_VAL" ]; then
	log_error "DB_PASS is empty"
	exit 1
fi
if [ -z "$MANTICORE_PASS" ]; then
	log_error "MANTICORE_DB_PASS is empty"
	exit 1
fi

cd "$PROJECT_DIR"

# Escape single quotes in passwords for SQL string literals
sql_escape() {
	printf '%s' "$1" | sed "s/'/''/g"
}

EU="$(sql_escape "$DB_USER_NAME")"
EP="$(sql_escape "$DB_PASS_VAL")"
MU="$(sql_escape "$MANTICORE_USER")"
MP="$(sql_escape "$MANTICORE_PASS")"

log_info "user_sync start db_user=${DB_USER_NAME} manticore_user=${MANTICORE_USER} db_name=${DB_NAME_VAL}"

SQL=$(cat <<EOF
CREATE USER IF NOT EXISTS '${EU}'@'%' IDENTIFIED BY '${EP}';
ALTER USER '${EU}'@'%' IDENTIFIED BY '${EP}';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${EU}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB_NAME_VAL}.* TO '${EU}'@'%';

CREATE USER IF NOT EXISTS '${MU}'@'%' IDENTIFIED BY '${MP}';
ALTER USER '${MU}'@'%' IDENTIFIED BY '${MP}';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${MU}'@'%';
GRANT SELECT ON ${DB_NAME_VAL}.* TO '${MU}'@'%';

FLUSH PRIVILEGES;
EOF
)

if ! printf '%s\n' "$SQL" | docker compose -f "$COMPOSE_FILE" exec -T db \
	mysql -uroot -p"${ROOT_PW}" --default-character-set=utf8mb4; then
	log_error "user_sync failed"
	exit 1
fi

log_info "user_sync ok zxpress_u=ok manticore_ro=ok"
