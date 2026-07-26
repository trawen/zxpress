#!/usr/bin/env bash
# Backfill issue.description_* / meta_description_* from article titles.
# Apply migration first: db/migration/issue_descriptions.sql
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
exec docker compose run --rm --entrypoint php \
	-v "$ROOT:/app" -w /app \
	php /app/tools/backfill-issue-descriptions.php "$@"
