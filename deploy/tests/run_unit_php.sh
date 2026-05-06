#!/usr/bin/env bash
set -euo pipefail

LOG_LEVEL="${LOG_LEVEL:-STANDARD}"

log() {
  local level="$1"; shift
  echo "[unit-runner] ${level} $*"
}

log INFO "Starting unit tests (LOG_LEVEL=${LOG_LEVEL})"

PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
INCLUDES_HOST_DIR="${PROJECT_DIR}/site/includes"

if [ ! -d "$INCLUDES_HOST_DIR" ]; then
  echo "[unit-runner] ERROR includes mount not found: $INCLUDES_HOST_DIR"
  exit 1
fi

log INFO "Running tests via docker run (mount: $INCLUDES_HOST_DIR)"

# Normal CSRF tests
log INFO "Running csrf tests"
docker run --rm \
  -e LOG_LEVEL="$LOG_LEVEL" \
  -v "${INCLUDES_HOST_DIR}:/app/includes:ro" \
  php:8.5-fpm \
  php /app/includes/__tests/unit_csrf.php

# CSRF mismatch tests: expected die -> non-zero exit, but we require PASS line
log INFO "Running csrf mismatch test"
set +e
out=$(docker run --rm \
  -e LOG_LEVEL="$LOG_LEVEL" \
  -v "${INCLUDES_HOST_DIR}:/app/includes:ro" \
  php:8.5-fpm \
  php /app/includes/__tests/unit_csrf.php --mismatch 2>&1)
code=$?
set -e

echo "$out" | tail -n 20

if ! echo "$out" | grep -q "\\[unit_csrf\\] INFO PASS"; then
  echo "[unit-runner] ERROR csrf mismatch test did not print PASS"
  exit 1
fi

if [ "$code" -eq 0 ]; then
  log WARN "csrf mismatch test exited 0 (unexpected but output had PASS)"
fi

# Search client tests
log INFO "Running search_client tests"
docker run --rm \
  -e LOG_LEVEL="$LOG_LEVEL" \
  -v "${INCLUDES_HOST_DIR}:/app/includes:ro" \
  php:8.5-fpm \
  php /app/includes/__tests/unit_search_client.php

log INFO "Unit tests completed"
