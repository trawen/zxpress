#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

cd "$PROJECT_DIR"

echo "=== Health Check ==="

FAILED=0

check_service() {
    local name="$1"
    local status
    status=$(docker compose -f "$COMPOSE_FILE" ps --format json "$name" 2>/dev/null | grep -o '"Health":"[^"]*"' | head -1 || echo "")

    if echo "$status" | grep -q "healthy"; then
        echo "  [OK] $name — healthy"
    elif docker compose -f "$COMPOSE_FILE" ps "$name" 2>/dev/null | grep -q "Up"; then
        echo "  [UP] $name — running (no healthcheck)"
    else
        echo "  [FAIL] $name — not running"
        FAILED=1
    fi
}

check_service nginx
check_service php
check_service manticore
check_service db

echo ""
if [ "$FAILED" -eq 0 ]; then
    echo "All services OK."
else
    echo "ERROR: One or more services are unhealthy."
    docker compose -f "$COMPOSE_FILE" ps
    exit 1
fi
