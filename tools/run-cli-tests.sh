#!/usr/bin/env bash
# Run tests/run_all.php via tools/php.sh (host PHP or php:8.5-cli).
# Stock image is used instead of zxpress php-fpm (hardened.ini disables passthru).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ROOT/tools/php.sh" tests/run_all.php "$@"
