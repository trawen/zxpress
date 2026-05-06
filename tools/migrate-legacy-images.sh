#!/usr/bin/env bash
# Move legacy chapter images into data/image-archive with verification.
# Default mode is dry-run; use --apply to execute copy checks.

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
  bash tools/migrate-legacy-images.sh
  bash tools/migrate-legacy-images.sh --apply
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
src="${repo_root}/data/legacy/chapters_images"
dst="${repo_root}/data/image-archive"

if [[ ! -d "${src}" ]]; then
  log_error "source directory does not exist: ${src}"
  exit 1
fi

mkdir -p "${dst}"

src_count="$(find "${src}" -type f | wc -l | tr -d '[:space:]')"
src_size="$(du -sb "${src}" | awk '{print $1}')"
dst_count_before="$(find "${dst}" -type f | wc -l | tr -d '[:space:]')"
dst_size_before="$(du -sb "${dst}" | awk '{print $1}')"

log_info "mode=${mode}"
log_info "source=${src} files=${src_count} size_bytes=${src_size}"
log_info "target=${dst} files_before=${dst_count_before} size_bytes_before=${dst_size_before}"

if [[ "${mode}" == "dry-run" ]]; then
  log_info "dry-run: no files copied"
  exit 0
fi

if [[ "${src_count}" -eq 0 ]]; then
  log_warn "source has no files, migration is a no-op"
  exit 0
fi

cp -a -n "${src}/." "${dst}/"

missing=0
while IFS= read -r rel; do
  if [[ ! -f "${dst}/${rel}" ]]; then
    missing=$((missing + 1))
  fi
done < <(cd "${src}" && find . -type f -print | sed 's#^\./##')

dst_count_after="$(find "${dst}" -type f | wc -l | tr -d '[:space:]')"
dst_size_after="$(du -sb "${dst}" | awk '{print $1}')"
log_info "target files_after=${dst_count_after} size_bytes_after=${dst_size_after}"

if [[ "${missing}" -ne 0 ]]; then
  log_error "verification failed, missing_files=${missing}"
  exit 1
fi

log_info "verification passed: all source files exist in image-archive"
