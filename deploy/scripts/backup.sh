#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
BACKUP_DIR="$PROJECT_DIR/backups"

cd "$PROJECT_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

echo "=== zxpress.ru — Backup ($TIMESTAMP) ==="

echo "[1/3] Dumping MySQL database..."
docker compose -f "$COMPOSE_FILE" exec -T db \
    mysqldump -u root -p"${MYSQL_ROOT_PASSWORD:-}" \
    --single-transaction --routines --triggers \
    zxpress_db | gzip > "$BACKUP_DIR/zxpress_db_${TIMESTAMP}.sql.gz"

echo "  → $BACKUP_DIR/zxpress_db_${TIMESTAMP}.sql.gz"

echo "[2/3] Archiving critical data directories..."
tar -C "$PROJECT_DIR" -czf "$BACKUP_DIR/zxpress_data_${TIMESTAMP}.tar.gz" \
    data/uploads \
    data/legacy \
    data/integrations/sape
echo "  → $BACKUP_DIR/zxpress_data_${TIMESTAMP}.tar.gz"

echo "[3/3] Cleaning old backups (keeping last 7)..."
ls -t "$BACKUP_DIR"/zxpress_db_*.sql.gz 2>/dev/null | tail -n +8 | xargs rm -f 2>/dev/null || true
ls -t "$BACKUP_DIR"/zxpress_data_*.tar.gz 2>/dev/null | tail -n +8 | xargs rm -f 2>/dev/null || true

echo ""
echo "=== Backup complete ==="
ls -lh "$BACKUP_DIR"/zxpress_db_*.sql.gz 2>/dev/null | tail -5
ls -lh "$BACKUP_DIR"/zxpress_data_*.tar.gz 2>/dev/null | tail -5
