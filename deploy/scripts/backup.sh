#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
BACKUP_DIR="$PROJECT_DIR/backups"

cd "$PROJECT_DIR"

# Prefer already-exported env; otherwise load project .env (never commit secrets).
if [ -z "${MYSQL_ROOT_PASSWORD:-}" ] && [ -f "$PROJECT_DIR/.env" ]; then
	set -a
	# shellcheck disable=SC1091
	source "$PROJECT_DIR/.env"
	set +a
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

echo "=== zxpress.ru — Backup ($TIMESTAMP) ==="

echo "[1/3] Dumping MySQL database..."
# Use password from the db container env (compose env_file) so empty host env
# does not make mysqldump prompt interactively for "-p".
docker compose -f "$COMPOSE_FILE" exec -T db \
	sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers zxpress_db' \
	| gzip > "$BACKUP_DIR/zxpress_db_${TIMESTAMP}.sql.gz"

echo "  → $BACKUP_DIR/zxpress_db_${TIMESTAMP}.sql.gz"

echo "[2/3] Archiving critical data directories..."
TAR_PATHS=()
for rel in data/uploads data/legacy data/integrations/sape; do
	if [ -e "$PROJECT_DIR/$rel" ]; then
		TAR_PATHS+=("$rel")
	else
		echo "  WARN: skip missing path $rel"
	fi
done
if [ "${#TAR_PATHS[@]}" -eq 0 ]; then
	echo "  WARN: no data paths to archive"
else
	tar -C "$PROJECT_DIR" -czf "$BACKUP_DIR/zxpress_data_${TIMESTAMP}.tar.gz" "${TAR_PATHS[@]}"
	echo "  → $BACKUP_DIR/zxpress_data_${TIMESTAMP}.tar.gz"
fi

echo "[3/3] Cleaning old backups (keeping last 7)..."
ls -t "$BACKUP_DIR"/zxpress_db_*.sql.gz 2>/dev/null | tail -n +8 | xargs rm -f 2>/dev/null || true
ls -t "$BACKUP_DIR"/zxpress_data_*.tar.gz 2>/dev/null | tail -n +8 | xargs rm -f 2>/dev/null || true

echo ""
echo "=== Backup complete ==="
ls -lh "$BACKUP_DIR"/zxpress_db_*.sql.gz 2>/dev/null | tail -5
ls -lh "$BACKUP_DIR"/zxpress_data_*.tar.gz 2>/dev/null | tail -5
