#!/usr/bin/env bash
# Retire legacy/archive and legacy/cat with quarantine-first workflow.
# Default mode is dry-run; use --apply to move/remove.

set -eu

log_info() {
  printf 'INFO: %s\n' "$*" >&2
}

log_warn() {
  printf 'WARN: %s\n' "$*" >&2
}

log_error() {
  printf 'ERROR: %s\n' "$*" >&2
}

usage() {
  cat <<'EOF'
Usage:
  bash tools/clean-legacy-storage.sh
  bash tools/clean-legacy-storage.sh --apply
EOF
}

mode="dry-run"
if [[ "${1:-}" == "--apply" ]]; then
  mode="apply"
elif [[ "${1:-}" == "--dry-run" || -z "${1:-}" ]]; then
  mode="dry-run"
elif [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
  usage
  exit 0
else
  log_error "unknown argument: ${1:-}"
  usage
  exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
legacy_root="${repo_root}/data/legacy"
stamp="$(date +%Y%m%d-%H%M%S)"
quarantine_root="${repo_root}/data/_quarantine/legacy-retire-${stamp}"
targets=(
  "archive"
  "cat"
)

log_info "mode=${mode}"
log_info "legacy_root=${legacy_root}"

if [[ "${mode}" == "apply" ]]; then
  mkdir -p "${quarantine_root}"
  log_info "quarantine_root=${quarantine_root}"
fi

for rel in "${targets[@]}"; do
  path="${legacy_root}/${rel}"
  if [[ ! -e "${path}" ]]; then
    log_info "skip missing: ${path}"
    continue
  fi

  if [[ -d "${path}" ]]; then
    if [[ -z "$(find "${path}" -mindepth 1 -print -quit)" ]]; then
      if [[ "${mode}" == "apply" ]]; then
        rmdir "${path}"
        log_info "removed empty dir: ${path}"
      else
        log_info "would remove empty dir: ${path}"
      fi
      continue
    fi

    if [[ "${mode}" == "apply" ]]; then
      mv "${path}" "${quarantine_root}/${rel}"
      log_info "moved to quarantine: ${path} -> ${quarantine_root}/${rel}"
    else
      log_warn "would quarantine non-empty dir: ${path}"
    fi
    continue
  fi

  log_warn "skip non-directory target: ${path}"
done

if [[ "${mode}" == "apply" ]]; then
  log_info "restore hint: mv \"${quarantine_root}/archive\" \"${legacy_root}/archive\" (and same for cat)"
fi

log_info "done"
