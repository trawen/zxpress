#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

cd "$PROJECT_DIR"

echo "=== zxpress.ru — Initial Deploy ==="

if [ ! -f .env ]; then
    echo "ERROR: .env file not found. Copy .env.example to .env and fill in real values."
    exit 1
fi

echo "[1/6] Validating env contract..."
"$SCRIPT_DIR/../tests/validate_env_contract.sh"

echo "[2/6] Rendering runtime search config..."
"$SCRIPT_DIR/render-manticore-conf.sh"
echo "[config-hardcode] INFO rendered manticore config before build/start"

echo "[3/6] Building PHP image..."
docker compose -f "$COMPOSE_FILE" build php

echo "[4/6] Starting services..."
docker compose -f "$COMPOSE_FILE" up -d

echo "[5/6] Waiting for services to become healthy..."
sleep 5
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

echo "[6/6] Indexing ManticoreSearch..."
docker compose -f "$COMPOSE_FILE" exec -T -u manticore manticore indexer --all --rotate 2>/dev/null || echo "[manticore-fix] WARN indexer returned non-zero (may need manual indexing)"
sleep 2
echo "[manticore-fix] INFO sending RELOAD TABLES to pick up indexes on fresh volume..."
docker compose -f "$COMPOSE_FILE" exec -T manticore mysql -P9306 -h0 -e "RELOAD TABLES" 2>/dev/null || echo "[manticore-fix] WARN RELOAD TABLES failed"
sleep 1

TABLE_COUNT=$(docker compose -f "$COMPOSE_FILE" exec -T manticore mysql -P9306 -h0 -N -e "SHOW TABLES" 2>/dev/null | wc -l || echo "0")
echo "[manticore-fix] INFO table_count=$TABLE_COUNT (expected 4)"
if [ "$TABLE_COUNT" -lt 3 ]; then
  echo "[manticore-fix] WARN fewer tables than expected — search may not work"
fi

echo ""
echo "=== Deploy complete ==="
docker compose -f "$COMPOSE_FILE" ps
