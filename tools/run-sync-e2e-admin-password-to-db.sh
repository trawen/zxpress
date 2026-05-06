#!/usr/bin/env bash
# Apply E2E_ADMIN_* from merged .env files to local Compose MySQL (password_hash).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
exec docker compose run --rm --entrypoint php \
	-v "$ROOT:/app" -w /app \
	php /app/tools/sync-e2e-admin-password-to-db.php "$@"
