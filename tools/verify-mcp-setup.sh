#!/usr/bin/env bash
# Проверка: файлы MCP + Chromium + возможность стартовать обёртки (локальный Cursor).
# Запуск: из корня репо: bash tools/verify-mcp-setup.sh
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"
ERR=0
say_ok() { echo "OK  $*"; }
say_fail() { echo "FAIL $*"; ERR=1; }

echo "=== ZXpress MCP sanity check (repo: ${REPO_ROOT}) ==="

if [[ -f "${REPO_ROOT}/.cursor/mcp.json" ]]; then
  say_ok ".cursor/mcp.json exists"
else
  say_fail ".cursor/mcp.json missing"
fi

for f in tools/mcp-playwright.sh tools/mcp-chrome-devtools.sh tools/run-playwright-mcp-server.sh tools/smoke-open-chromium.sh; do
  if [[ -f "${REPO_ROOT}/${f}" ]]; then
    if [[ -x "${REPO_ROOT}/${f}" ]]; then
      say_ok "${f} exists and is executable"
    else
      say_fail "${f} not executable (run: chmod +x ${f})"
    fi
  else
    say_fail "${f} missing"
  fi
done

for f in local_mcp/.playwright-mcp.local.json local_mcp/.playwright-mcp-init.js; do
  if [[ -f "${REPO_ROOT}/${f}" ]]; then
    say_ok "${f} exists"
  else
    say_fail "${f} missing"
  fi
done

if command -v python3 >/dev/null 2>&1; then
  python3 -m json.tool "${REPO_ROOT}/.cursor/mcp.json" >/dev/null
  say_ok ".cursor/mcp.json is valid JSON"
  python3 -m json.tool "${REPO_ROOT}/local_mcp/.playwright-mcp.local.json" >/dev/null
  say_ok "local_mcp/.playwright-mcp.local.json is valid JSON"
else
  say_fail "python3 not found (cannot validate JSON)"
fi

CHROME="${CHROME_PATH:-/usr/bin/chromium}"
if [[ -x "${CHROME}" ]]; then
  say_ok "Chromium executable: ${CHROME}"
else
  say_fail "Chromium not executable at ${CHROME} (set CHROME_PATH or install chromium)"
fi

if command -v npx >/dev/null 2>&1; then
  say_ok "npx is on PATH"
else
  say_fail "npx not on PATH (install Node.js / npm)"
fi

echo "--- Quick spawn (3s timeout, expect no immediate crash) ---"
set +e
OUT_CHROME="$(timeout 3 bash "${REPO_ROOT}/tools/mcp-chrome-devtools.sh" 2>&1)"
RC_CHROME=$?
set -e
if echo "${OUT_CHROME}" | grep -q '/opt/google/chrome/chrome'; then
  say_fail "chrome-devtools wrapper still mentions /opt/google/chrome (bad)"
else
  say_ok "chrome-devtools wrapper: no /opt/google/chrome error in first 3s"
fi
if [[ "${RC_CHROME}" -eq 124 ]]; then
  say_ok "chrome-devtools: still running after 3s (timeout expected)"
fi

set +e
OUT_PW="$(timeout 3 bash "${REPO_ROOT}/tools/mcp-playwright.sh" 2>&1)"
RC_PW=$?
set -e
if [[ "${RC_PW}" -eq 124 ]]; then
  say_ok "playwright wrapper: no immediate exit (timeout 3s — server likely up)"
else
  echo "${OUT_PW}" | head -20
  say_fail "playwright wrapper exited with ${RC_PW} (see lines above)"
fi

echo "--- HTTP local stack ---"
if command -v curl >/dev/null 2>&1; then
  CODE="$(env -u http_proxy -u https_proxy -u HTTP_PROXY -u HTTPS_PROXY curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:80/" 2>/dev/null || echo 000)"
  if [[ "${CODE}" == "200" ]]; then
    say_ok "http://127.0.0.1:80/ returns 200"
  else
    say_fail "http://127.0.0.1:80/ returned ${CODE} (start docker compose / nginx)"
  fi
else
  say_fail "curl not found"
fi

echo "--- Playwright MCP HTTP (8931) — optional ---"
if command -v curl >/dev/null 2>&1; then
  MCPCODE="$(env -u http_proxy -u https_proxy curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:8931/mcp" 2>/dev/null || echo 000)"
  if [[ "${MCPCODE}" != "000" ]]; then
    say_ok "http://127.0.0.1:8931/mcp responds (HTTP ${MCPCODE}) — HTTP server running"
  else
    say_ok "port 8931 idle (OK for default stdio MCP)"
  fi
fi

echo "=== Summary: exit ${ERR} (0=all checks passed) ==="
echo "No window? Run: bash tools/smoke-open-chromium.sh"
exit "${ERR}"
