#!/usr/bin/env bash
# Playwright MCP for Cursor: headed browser + repo config (see docs/mcp-playwright.md).
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export DISPLAY="${DISPLAY:-:0}"
export PLAYWRIGHT_MCP_EXECUTABLE_PATH="${PLAYWRIGHT_MCP_EXECUTABLE_PATH:-/usr/bin/chromium}"
export PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-${HOME}/.cache/ms-playwright}"
export NO_PROXY="${NO_PROXY:-zxpress.ru,zxpress-local,localhost,127.0.0.1}"
export no_proxy="${no_proxy:-${NO_PROXY}}"
if [[ "${DEBUG_MCP:-}" == "1" ]]; then
  echo "[FIX] mcp-playwright REPO_ROOT=${REPO_ROOT} DISPLAY=${DISPLAY}" >&2
fi
exec npx -y @playwright/mcp@latest \
  --browser chromium \
  --config "${REPO_ROOT}/local_mcp/.playwright-mcp.local.json" \
  --init-page "${REPO_ROOT}/local_mcp/.playwright-mcp-init.js" \
  "$@"
