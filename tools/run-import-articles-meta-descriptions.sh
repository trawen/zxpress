#!/usr/bin/env bash
# Import article meta descriptions from data/import/article-descriptions/ into MySQL.
# Run on the server; uses Compose DB network + .env (same as other tools/* runners).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
exec docker compose run --rm --entrypoint php \
	-v "$ROOT:/app" -w /app \
	php /app/tools/import-articles-meta-descriptions.php "$@"
