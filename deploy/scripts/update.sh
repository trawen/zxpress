#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

cd "$PROJECT_DIR"

echo "=== zxpress.ru — Update ==="

echo "[1/7] Creating pre-update backup..."
"$SCRIPT_DIR/backup.sh"

echo "[2/7] Pulling latest code..."
git pull --ff-only || { echo "ERROR: git pull failed. Resolve conflicts first."; exit 1; }

echo "[3/7] Validating env contract..."
"$SCRIPT_DIR/../tests/validate_env_contract.sh"

echo "[4/7] Rendering runtime search config..."
"$SCRIPT_DIR/render-manticore-conf.sh"
echo "[5/7] Rebuilding PHP image..."
docker compose -f "$COMPOSE_FILE" build php

echo "[6/7] Rolling restart..."
docker compose -f "$COMPOSE_FILE" up -d --no-deps php
sleep 5
docker compose -f "$COMPOSE_FILE" up -d --no-deps nginx

echo "[7/7] Verifying health..."
"$SCRIPT_DIR/health-check.sh"

echo "[deploy] [INFO] Running smoke HTTP checks..."
set +e
LOG_LEVEL="${LOG_LEVEL:-STANDARD}" "$SCRIPT_DIR/../tests/smoke_http.sh"
smoke_code=$?
set -e
echo "[deploy] [INFO] smoke_http.sh exit code=${smoke_code}"
if [ "$smoke_code" -ne 0 ]; then
  exit "$smoke_code"
fi

echo "[deploy] [INFO] Re-indexing ManticoreSearch..."
docker compose -f "$COMPOSE_FILE" exec -T -u manticore manticore indexer --all --rotate 2>/dev/null || echo "[manticore-fix] WARN indexer returned non-zero"
sleep 2
docker compose -f "$COMPOSE_FILE" exec -T manticore mysql -P9306 -h0 -e "RELOAD TABLES" 2>/dev/null || echo "[manticore-fix] WARN RELOAD TABLES failed"

echo ""
echo "=== Update complete ==="
docker compose -f "$COMPOSE_FILE" ps
