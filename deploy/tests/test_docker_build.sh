#!/bin/bash
# Static Dockerfile / .dockerignore checks (no docker build required).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DOCKERFILE="$ROOT/Dockerfile"
IGNORE="$ROOT/.dockerignore"
FAILED=0

fail() {
	echo "FAIL: $1" >&2
	FAILED=1
}

if [[ ! -f "$DOCKERFILE" ]]; then
	fail "missing $DOCKERFILE"
	exit 1
fi

grep -qE '^FROM[[:space:]]+[^[:space:]]+[[:space:]]+AS[[:space:]]+builder' "$DOCKERFILE" \
	|| fail "Dockerfile should use multi-stage build (FROM ... AS builder)"

grep -q 'fsockopen' "$DOCKERFILE" || fail "Dockerfile HEALTHCHECK should use fsockopen"

if [[ ! -f "$IGNORE" ]]; then
	fail "missing .dockerignore"
else
	grep -qE '^\.env$|^[.]env$' "$IGNORE" || fail ".dockerignore should exclude .env"
	grep -q 'site/\*\*' "$IGNORE" || fail ".dockerignore should exclude site/** (or equivalent sensitive paths)"
fi

echo "test_docker_build.sh: OK"
exit "$FAILED"
