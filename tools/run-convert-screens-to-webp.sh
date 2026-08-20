#!/usr/bin/env bash
# Convert legacy screenshot png/jpg → lossless WebP.
# Usage:
#   ./tools/run-convert-screens-to-webp.sh
#   ./tools/run-convert-screens-to-webp.sh --apply
#   ./tools/run-convert-screens-to-webp.sh --apply --id=3719
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
SCRIPT=/home/zxpress/web/zxpress.ru/public_html/cli/convert-screens-to-webp.php
if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx zxpress_php; then
	exec docker exec -i zxpress_php php "$SCRIPT" "$@"
fi
if docker compose ps --status running --services 2>/dev/null | grep -qx php; then
	exec docker compose exec -T php php "$SCRIPT" "$@"
fi
exec docker compose run --rm --entrypoint php \
	-v "$ROOT/site:/home/zxpress/web/zxpress.ru/public_html:ro" \
	php "$SCRIPT" "$@"
