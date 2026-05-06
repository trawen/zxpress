#!/usr/bin/env bash
# Import a gzipped mysqldump into zxpress_db (Docker Compose service `db`).
# Default: creates a safety mysqldump of zxpress_db before import (scenario C).
# Usage: from project root, with .env containing MYSQL_ROOT_PASSWORD.
# Logging: [restore] INFO|WARN|ERROR to stderr.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
BACKUP_DIR="$PROJECT_DIR/backups"

log_info() { echo "[restore] INFO $*" >&2; }
log_warn() { echo "[restore] WARN $*" >&2; }
log_error() { echo "[restore] ERROR $*" >&2; }

usage() {
	echo "Usage: $0 [--skip-backup] [--force-mysql] <path-to-dump.sql.gz>" >&2
	echo "  --skip-backup   Do not run mysqldump of current zxpress_db before import (risky)." >&2
	echo "  --force-mysql   Pass --force to mysql client (continue on SQL errors)." >&2
	echo "  Env: MYSQL_ROOT_PASSWORD from .env (source-compatible)." >&2
}

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

SKIP_BACKUP=0
MYSQL_FORCE=0
while [ $# -gt 0 ]; do
	case "$1" in
		--skip-backup) SKIP_BACKUP=1 ;;
		--force-mysql) MYSQL_FORCE=1 ;;
		--help | -h)
			usage
			exit 0
			;;
		-*)
			log_error "unknown option: $1"
			usage
			exit 1
			;;
		*) break ;;
	esac
	shift
done

DUMP="${1:-}"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
	log_error "missing or invalid dump file"
	usage
	exit 1
fi

load_env

if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
	log_error "MYSQL_ROOT_PASSWORD is empty (set in .env)"
	exit 1
fi

# Legacy mysqldumps (e.g. 5.5) often omit CREATE DATABASE / USE; without -D mysql fails at first DROP TABLE.
TARGET_DB="${DB_NAME:-${MYSQL_DATABASE:-zxpress_db}}"
log_info "[FIX] import_target_db=$TARGET_DB (mysql -D for dumps without USE)"

if ! gzip -t "$DUMP" 2>/dev/null; then
	log_error "gzip integrity check failed: $DUMP"
	exit 1
fi

SIZE=$(stat -c%s "$DUMP" 2>/dev/null || stat -f%z "$DUMP" 2>/dev/null || echo "?")
log_info "preflight_ok dump_path=$DUMP size_bytes=$SIZE"

cd "$PROJECT_DIR"

if [ "$SKIP_BACKUP" -eq 0 ]; then
	if docker compose -f "$COMPOSE_FILE" exec -T db \
		mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "USE zxpress_db" 2>/dev/null; then
		mkdir -p "$BACKUP_DIR"
		TS=$(date +%Y%m%d_%H%M%S)
		PRE="$BACKUP_DIR/pre_restore_${TS}.sql.gz"
		log_info "scenario=C safety_backup_start -> $PRE"
		if ! docker compose -f "$COMPOSE_FILE" exec -T db \
			mysqldump -u root -p"${MYSQL_ROOT_PASSWORD}" \
			--single-transaction --routines --triggers \
			zxpress_db | gzip > "$PRE"; then
			log_error "mysqldump safety backup failed"
			exit 1
		fi
		log_info "safety_backup_ok path=$PRE"
	else
		log_warn "zxpress_db not present or not reachable; skipping safety mysqldump"
	fi
else
	log_warn "skip-backup set: no pre-import mysqldump"
fi

MYSQL_ARGS=( -uroot -p"${MYSQL_ROOT_PASSWORD}" --default-character-set=utf8mb4 -D "$TARGET_DB" )
if [ "$MYSQL_FORCE" -eq 1 ]; then
	MYSQL_ARGS+=( --force )
	log_info "mysql client --force enabled"
fi

log_info "import_start"
set +e
gunzip -c "$DUMP" | docker compose -f "$COMPOSE_FILE" exec -T db mysql "${MYSQL_ARGS[@]}"
IMPORT_RC=$?
set -e
if [ "$IMPORT_RC" -ne 0 ]; then
	log_error "mysql import failed exit=$IMPORT_RC"
	exit "$IMPORT_RC"
fi
log_info "import_done exit=0"
