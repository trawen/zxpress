#!/usr/bin/env bash
# Generate issue description_* / meta_description_* via Gemini (CDP).
# Requires local Docker DB + Chrome with --remote-debugging-port=9222.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
exec node local/scripts/generate-issue-description.mjs "$@"
