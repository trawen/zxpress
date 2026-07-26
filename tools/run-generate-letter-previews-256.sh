#!/usr/bin/env bash
# Generate letters/preview-256/*.webp (256px wide, quality 80).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
SCRIPT=/home/zxpress/web/zxpress.ru/public_html/cli/generate-letter-previews-256.php
if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx zxpress_php; then
	exec docker exec -i zxpress_php php "$SCRIPT" "$@"
fi
if docker compose ps --status running --services 2>/dev/null | grep -qx php; then
	exec docker compose exec -T php php "$SCRIPT" "$@"
fi
exec "$ROOT/tools/php.sh" "$ROOT/site/cli/generate-letter-previews-256.php" "$@"
