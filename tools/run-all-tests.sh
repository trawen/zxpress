#!/usr/bin/env bash
# Run full local test stack: Docker Compose (test ports) + PHPUnit + CLI + Playwright.
# Requires: Docker, Node 20+ (for Playwright). PHP on host is optional — without it,
# PHPUnit and run_all.php run via: docker compose run --rm -v repo:/app php ...
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Always run tests with direct network path (ignore host/corporate proxy settings).
unset http_proxy https_proxy HTTP_PROXY HTTPS_PROXY ALL_PROXY all_proxy

php_exec() {
	if command -v php >/dev/null 2>&1; then
		command php "$@"
		return
	fi
	# docker compose run does not pass ZXPRESS_INTEGRATION_TESTS; DB/Manticore come from
	# service env_file (.env → db, manticore). Do not override DB_* with 127.0.0.1:3307 —
	# that only works from the host (see docs/testing.md). HTTP smoke must hit nginx by name.
	docker compose -f docker-compose.yml -f docker-compose.test.yml run --rm \
		-e ZXPRESS_INTEGRATION_TESTS="${ZXPRESS_INTEGRATION_TESTS:-1}" \
		-e HTTP_SMOKE_BASE_URL=http://nginx \
		-v "$ROOT:/app" -w /app --entrypoint php php "$@"
}

cli_tests() {
	if command -v php >/dev/null 2>&1; then
		command php tests/run_all.php "$@"
		return
	fi
	docker run --rm -v "$ROOT:/app" -w /app php:8.5-cli php tests/run_all.php "$@"
}

docker compose -f docker-compose.yml -f docker-compose.test.yml up -d --wait

export ZXPRESS_INTEGRATION_TESTS=1
export DB_HOST=127.0.0.1
export DB_PORT=3307
export DB_USER=zxpress_u
export DB_PASS="${DB_PASS:-changeme-app-password}"
export MANTICORE_HOST=127.0.0.1
export MANTICORE_PORT=9308
export HTTP_SMOKE_BASE_URL=http://127.0.0.1:80

php_exec tools/phpunit.phar --configuration phpunit.xml.dist --testsuite unit
php_exec tools/phpunit.phar --configuration phpunit.xml.dist --testsuite integration
cli_tests security
cli_tests
bash deploy/tests/test_storage_migration_docs.sh
bash deploy/tests/test_text_storage_alias_target.sh
bash deploy/tests/test_chronology_startup_logs.sh
bash deploy/tests/test_manticore_index_permissions.sh
bash deploy/tests/smoke_http.sh

cd e2e
npm ci
npx playwright install chromium
export PLAYWRIGHT_SKIP_WEBSERVER=1
# Default E2E target is canonical staging (see .ai-factory/PLAN.md). Override with BASE_URL=http://127.0.0.1:80 for local stack only.
export BASE_URL="${BASE_URL:-https://zxpress.pst-labs.ru}"
# Same as e2e/package.json: never route Playwright through a host proxy.
env -u http_proxy -u https_proxy -u HTTP_PROXY -u HTTPS_PROXY -u ALL_PROXY -u all_proxy \
	npx playwright test
