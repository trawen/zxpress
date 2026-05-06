#!/usr/bin/env bash
# Run PHPUnit inside the php service with the repo mounted at /app (no host PHP required).
# Usage: ./tools/phpunit-docker.sh --testsuite unit
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
# ZXPRESS_INTEGRATION_TESTS: enable integration suite. DB/Manticore: use service .env (db, manticore).
# HTTP smoke from inside this container must use nginx hostname, not 127.0.0.1 (docs/testing.md).
exec docker compose -f docker-compose.yml -f docker-compose.test.yml run --rm \
	-e ZXPRESS_INTEGRATION_TESTS="${ZXPRESS_INTEGRATION_TESTS:-1}" \
	-e HTTP_SMOKE_BASE_URL="${HTTP_SMOKE_BASE_URL:-http://nginx}" \
	-v "$ROOT:/app" -w /app --entrypoint php php tools/phpunit.phar --configuration phpunit.xml.dist "$@"
