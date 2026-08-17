#!/usr/bin/env bash
# Run production update.sh over SSH from your laptop.
#
# Usage (from repo root on Mac):
#   ./deploy/scripts/remote-update.sh --fast
#   ./deploy/scripts/remote-update.sh
#   ./deploy/scripts/remote-update.sh --skip-reindex --skip-backup
#
# Env (optional):
#   ZXPRESS_SSH   — default: dockeruser@153.80.250.105
#   ZXPRESS_REPO  — default: /home/dockeruser/zxpress
set -euo pipefail

REMOTE_SSH="${ZXPRESS_SSH:-dockeruser@153.80.250.105}"
REMOTE_REPO="${ZXPRESS_REPO:-/home/dockeruser/zxpress}"

if [ "$#" -eq 0 ]; then
	echo "[remote-update] INFO ssh=${REMOTE_SSH} repo=${REMOTE_REPO} args=<none>"
else
	echo "[remote-update] INFO ssh=${REMOTE_SSH} repo=${REMOTE_REPO} args=$*"
fi

# Quote remote argv so flags like --fast survive SSH.
quoted_args=""
if [ "$#" -gt 0 ]; then
	quoted_args=$(printf '%q ' "$@")
fi

ssh -t "$REMOTE_SSH" "cd $(printf '%q' "$REMOTE_REPO") && git pull --ff-only && ./deploy/scripts/update.sh ${quoted_args}"
