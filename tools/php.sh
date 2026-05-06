#!/usr/bin/env bash
# Canonical PHP CLI for this repo when `php` is not on PATH (Cursor sandboxes, minimal CI shells).
# Prefers host PHP if available; otherwise: docker run php:8.5-cli (same major as AGENTS.md / Dockerfile).
#
# Usage (paths relative to repo root — required when Docker fallback mounts the tree at /app):
#   ./tools/php.sh -l site/includes/functions.php
#   ./tools/php.sh tests/run_all.php
#   ./tools/php.sh tests/run_all.php security
#   ./tools/php.sh tests/security/test_xss_escaping.php
#
# For PHPUnit inside Compose test stack, use ./tools/phpunit-docker.sh instead.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
if command -v php >/dev/null 2>&1; then
	exec command php "$@"
fi
exec docker run --rm -v "$ROOT:/app" -w /app php:8.5-cli php "$@"
