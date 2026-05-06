#!/usr/bin/env bash
# Blocking gate before deleting uploads text directories.
# Fails on blocking mismatches in articles/chapters.

set -eu

log_info() { printf 'INFO: %s\n' "$*" >&2; }
log_error() { printf 'ERROR: %s\n' "$*" >&2; }

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"

log_info "pre-purge gate: running strict uploads audit"
if ! bash "${repo_root}/tools/audit-uploads-text-storage.sh" --strict; then
  log_error "pre-purge gate failed: blocking mismatches remain"
  exit 1
fi

log_info "pre-purge gate passed"
