#!/usr/bin/env bash
set -euo pipefail

LOG_LEVEL="${LOG_LEVEL:-STANDARD}"

log() {
  local level="$1"; shift
  echo "[legacy-mount-test] ${level} $*"
}

PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$PROJECT_DIR"

# Legacy placeholders that must stay out of site/
SITE_PLACEHOLDERS=(
  "site/archive"
  "site/cat"
  "site/chapters_images"
)

# Data targets required by current runtime contract
DATA_TARGETS=(
  "data/image-archive"
)

log INFO "Checking host filesystem state"
for src in "${SITE_PLACEHOLDERS[@]}"; do
  if [ -e "$src" ]; then
    log ERROR "Expected source path to be absent after migration: $src"
    exit 1
  fi
  log INFO "OK source absent: $src"
done

for dst in "${DATA_TARGETS[@]}"; do
  if [ ! -d "$dst" ]; then
    log ERROR "Expected target directory is missing: $dst"
    exit 1
  fi
  log INFO "OK target exists: $dst"
done

# Validate compose mounts are present in rendered config.
log INFO "Checking docker compose config for bind-mount contract"
compose_config="$(docker compose config)"

# App root must bind-mount ./site (named app_code never picked up host edits after first boot).
if printf '%s\n' "$compose_config" | grep -qE 'source: app_code|^[[:space:]]*app_code:[[:space:]]*$'; then
  log ERROR "Named volume app_code must not be used; bind-mount ./site to public_html"
  exit 1
fi
if ! printf '%s\n' "$compose_config" | grep -q '/home/zxpress/web/zxpress.ru/public_html'; then
  log ERROR "public_html mount missing from compose config"
  exit 1
fi
log INFO "OK app root bind-mount to public_html (no app_code volume)"

if ! printf '%s\n' "$compose_config" | awk 'index($0, "target: /src-site") > 0 {found=1} END{exit found?0:1}'; then
  log ERROR "Expected source code mount target /src-site is missing"
  exit 1
fi
log INFO "OK source code mount to /src-site is configured"

if ! printf '%s\n' "$compose_config" | awk 'index($0, "target: /home/zxpress/web/zxpress.ru/data") > 0 {found=1} END{exit found?0:1}'; then
  log ERROR "Expected unified data mount target is missing"
  exit 1
fi
log INFO "OK unified /data mount is configured"

log INFO "PASS storage contract is valid"
