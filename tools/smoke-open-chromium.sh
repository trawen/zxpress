#!/usr/bin/env bash
# Минимальная проверка: есть ли вообще видимое окно Chromium на этом DISPLAY (без MCP).
# Запуск: bash tools/smoke-open-chromium.sh
# Если окна нет — чинить DISPLAY/Wayland/сессию, а не MCP.
set -euo pipefail
export DISPLAY="${DISPLAY:-:0}"
URL="${1:-http://127.0.0.1:80/}"
BIN="${CHROME_PATH:-/usr/bin/chromium}"
if [[ ! -x "${BIN}" ]]; then
  echo "No executable at ${BIN}" >&2
  exit 1
fi
echo "Opening ${BIN} -> ${URL} (DISPLAY=${DISPLAY})" >&2
exec "${BIN}" --new-window "${URL}"
