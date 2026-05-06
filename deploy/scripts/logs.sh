#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

cd "$PROJECT_DIR"

SERVICE="${1:-}"
LINES="${2:-100}"

if [ -z "$SERVICE" ]; then
    echo "Usage: $0 <service|all> [lines]"
    echo ""
    echo "Services: nginx, php, manticore, db"
    echo "  $0 all         — tail all services"
    echo "  $0 php 200     — last 200 lines of PHP logs"
    echo "  $0 db          — last 100 lines of DB logs"
    exit 0
fi

if [ "$SERVICE" = "all" ]; then
    docker compose -f "$COMPOSE_FILE" logs --tail="$LINES" -f
else
    docker compose -f "$COMPOSE_FILE" logs --tail="$LINES" -f "$SERVICE"
fi
