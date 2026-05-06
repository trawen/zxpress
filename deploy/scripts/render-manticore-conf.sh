#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_DIR/.env"
TEMPLATE_FILE="$PROJECT_DIR/conf/manticore.conf.template"
OUTPUT_FILE="$PROJECT_DIR/conf/manticore.conf"

log() {
  local level="$1"; shift
  echo "[config-hardcode] ${level} $*"
}

if [ ! -f "$ENV_FILE" ]; then
  log ERROR "missing_env_file path=$ENV_FILE"
  exit 1
fi

if [ ! -f "$TEMPLATE_FILE" ]; then
  log ERROR "missing_template path=$TEMPLATE_FILE"
  exit 1
fi

set -a
# shellcheck source=/dev/null
source "$ENV_FILE"
set +a

: "${MANTICORE_SQL_HOST:=db}"
: "${MANTICORE_SQL_PORT:=3306}"
: "${MANTICORE_DB_NAME:=zxpress_db}"
: "${MANTICORE_DB_USER:=manticore_ro}"
: "${MANTICORE_DB_PASS:?MANTICORE_DB_PASS must be set in .env}"

export MANTICORE_SQL_HOST MANTICORE_SQL_PORT MANTICORE_DB_NAME MANTICORE_DB_USER MANTICORE_DB_PASS

TMPFILE="$(mktemp)"
cleanup() { rm -f "$TMPFILE"; }
trap cleanup EXIT

envsubst '${MANTICORE_SQL_HOST} ${MANTICORE_SQL_PORT} ${MANTICORE_DB_NAME} ${MANTICORE_DB_USER} ${MANTICORE_DB_PASS}' \
  < "$TEMPLATE_FILE" > "$TMPFILE"

if install -m 0644 "$TMPFILE" "$OUTPUT_FILE" 2>/dev/null; then
  log INFO "rendered_conf output=$OUTPUT_FILE sql_host=$MANTICORE_SQL_HOST sql_port=$MANTICORE_SQL_PORT db_name=$MANTICORE_DB_NAME db_user=$MANTICORE_DB_USER db_pass=[redacted]"
  exit 0
fi

ALT="${OUTPUT_FILE}.generated"
if cp "$TMPFILE" "$ALT" 2>/dev/null; then
  log ERROR "cannot_write path=$OUTPUT_FILE (permission denied). Wrote $ALT — fix ownership on conf/ or merge manually: cp -f $ALT $OUTPUT_FILE"
  exit 1
fi

log ERROR "cannot_write path=$OUTPUT_FILE and cannot write $ALT"
exit 1
