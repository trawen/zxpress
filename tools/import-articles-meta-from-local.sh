#!/usr/bin/env bash
# Sync local SEO description files to the server and run import there.
#
# Run on YOUR Mac (where zxpress-claude-descriptions lives), not on the server.
#
# One-time setup:
#   export ZXPRESS_SSH=dockeruser@your-server.example   # SSH login
#   export ZXPRESS_REPO=/home/dockeruser/zxpress         # repo path on server (optional)
#
# Usage:
#   bash tools/import-articles-meta-from-local.sh --dry-run
#   bash tools/import-articles-meta-from-local.sh --apply
#
# Env:
#   LOCAL_DESCRIPTIONS_DIR  — local folder with seo-ru/seo-en files
#                             (default: ~/Projects/zxpress-claude-descriptions/descriptions)
#   ZXPRESS_SSH               — SSH target (required)
#   ZXPRESS_REPO              — server repo root (default: /home/dockeruser/zxpress)
set -euo pipefail

LOCAL_DIR="${LOCAL_DESCRIPTIONS_DIR:-$HOME/Projects/zxpress-claude-descriptions/descriptions}"
REMOTE_REPO="${ZXPRESS_REPO:-/home/dockeruser/zxpress}"
REMOTE_IMPORT_DIR="${REMOTE_REPO}/data/import/article-descriptions"

if [[ -z "${ZXPRESS_SSH:-}" ]]; then
	echo "ERROR: set ZXPRESS_SSH, e.g. export ZXPRESS_SSH=dockeruser@your-server" >&2
	exit 1
fi

if [[ ! -d "$LOCAL_DIR" ]]; then
	echo "ERROR: local descriptions dir not found: $LOCAL_DIR" >&2
	echo "Set LOCAL_DESCRIPTIONS_DIR or create the folder." >&2
	exit 1
fi

IMPORT_ARGS=("$@")
if [[ ${#IMPORT_ARGS[@]} -eq 0 ]]; then
	IMPORT_ARGS=(--dry-run)
fi

echo "==> rsync $LOCAL_DIR/ -> ${ZXPRESS_SSH}:${REMOTE_IMPORT_DIR}/"
ssh "$ZXPRESS_SSH" "mkdir -p '$REMOTE_IMPORT_DIR'"
rsync -av --delete "$LOCAL_DIR/" "${ZXPRESS_SSH}:${REMOTE_IMPORT_DIR}/"

echo "==> import on server (${IMPORT_ARGS[*]})"
ssh "$ZXPRESS_SSH" "cd '$REMOTE_REPO' && bash tools/run-import-articles-meta-descriptions.sh ${IMPORT_ARGS[*]}"
