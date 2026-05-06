#!/usr/bin/env bash
# Запуск Playwright MCP по HTTP (отдельный процесс с DISPLAY) — видимый Chromium + подключение CLI/IDE по URL.
# Использование: в отдельном терминале (tmux/screen ок):  bash tools/run-playwright-mcp-server.sh
# Затем в .cursor/mcp.json сервер playwright смотрит на http://127.0.0.1:${PORT}/mcp
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export DISPLAY="${DISPLAY:-:0}"
export PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-${HOME}/.cache/ms-playwright}"
export NO_PROXY="${NO_PROXY:-zxpress.ru,zxpress-local,localhost,127.0.0.1}"
export no_proxy="${no_proxy:-${NO_PROXY}}"
PORT="${PLAYWRIGHT_MCP_PORT:-8931}"
HOST="${PLAYWRIGHT_MCP_HOST:-127.0.0.1}"
if [[ "${DEBUG_MCP:-}" == "1" ]]; then
  echo "[FIX] run-playwright-mcp-server DISPLAY=${DISPLAY} PORT=${PORT} HOST=${HOST}" >&2
fi
echo "Playwright MCP: http://${HOST}:${PORT}/mcp  (Ctrl+C to stop)" >&2
exec npx -y @playwright/mcp@latest \
  --browser chromium \
  --config "${REPO_ROOT}/local_mcp/.playwright-mcp.local.json" \
  --init-page "${REPO_ROOT}/local_mcp/.playwright-mcp-init.js" \
  --host "${HOST}" \
  --port "${PORT}" \
  "$@"
