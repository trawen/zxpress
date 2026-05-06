#!/usr/bin/env bash
# Run plaintext->bcrypt migration with repo mounted; uses Compose DB network + .env.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
exec docker compose run --rm --entrypoint php \
	-v "$ROOT:/app" -w /app \
	php /app/tools/migrate-users-password-plaintext-to-bcrypt.php "$@"
