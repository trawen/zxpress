#!/usr/bin/env bash
# Chrome DevTools MCP: force Chromium path (Linux has no Google Chrome at /opt/google/chrome).
set -euo pipefail
export CHROME_PATH="${CHROME_PATH:-/usr/bin/chromium}"
if [[ ! -x "${CHROME_PATH}" ]]; then
  echo "[FIX] mcp-chrome-devtools: CHROME_PATH not executable: ${CHROME_PATH}" >&2
  exit 1
fi
if [[ "${DEBUG_MCP:-}" == "1" ]]; then
  echo "[FIX] mcp-chrome-devtools CHROME_PATH=${CHROME_PATH}" >&2
fi
exec npx -y chrome-devtools-mcp@latest \
  --no-usage-statistics \
  --chrome-path "${CHROME_PATH}" \
  --chrome-arg "--host-resolver-rules=MAP zxpress.ru 127.0.0.1,MAP zxpress-local 127.0.0.1" \
  "$@"
