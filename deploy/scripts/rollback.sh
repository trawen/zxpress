#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
BACKUP_DIR="$PROJECT_DIR/backups"

cd "$PROJECT_DIR"

log() {
    local level="$1"; shift
    echo "[rollback] ${level} $*"
}

echo "=== zxpress.ru — Rollback ==="

COMMITS="${1:-1}"
ROLLBACK_REASON="${ROLLBACK_REASON:-manual}"

log INFO "trigger_reason=$ROLLBACK_REASON commits=$COMMITS"
echo "[1/5] Rolling back git ($COMMITS commits)..."
git log --oneline -n "$((COMMITS + 1))"
echo ""
git revert --no-commit HEAD~"${COMMITS}"..HEAD

echo "[2/5] Rebuilding PHP image..."
docker compose -f "$COMPOSE_FILE" build php

echo "[3/5] Restarting services..."
docker compose -f "$COMPOSE_FILE" up -d --no-deps php
sleep 5
docker compose -f "$COMPOSE_FILE" up -d --no-deps nginx

echo "[4/5] Verifying health..."
"$SCRIPT_DIR/health-check.sh"

echo "[5/5] Running post-rollback checks..."
bash "$PROJECT_DIR/deploy/tests/run_unit_php.sh"
bash "$PROJECT_DIR/deploy/tests/test_legacy_storage_mounts.sh"
log INFO "post_rollback_checks=pass"

echo ""
echo "=== Rollback complete ==="
echo "Changes are staged but NOT committed. Review and commit manually:"
echo "  git commit -m 'revert: rollback $COMMITS commit(s)'"
