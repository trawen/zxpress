#!/usr/bin/env bash
# Remove only empty legacy storage placeholders under site/.
# Default mode is dry-run; pass --apply to actually remove directories/files.

set -eu

log_info() {
  printf 'INFO: %s\n' "$*" >&2
}

log_warn() {
  printf 'WARN: %s\n' "$*" >&2
}

usage() {
  cat <<'EOF'
Usage:
  bash tools/clean-empty-site-storage-dirs.sh        # dry-run
  bash tools/clean-empty-site-storage-dirs.sh --apply
EOF
}

apply=0
if [[ "${1:-}" == "--apply" ]]; then
  apply=1
elif [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
  usage
  exit 0
elif [[ $# -gt 0 ]]; then
  log_warn "unknown argument: $1"
  usage
  exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
site_root="${repo_root}/site"

if [[ ! -d "${site_root}" ]]; then
  log_warn "site directory not found at ${site_root}"
  exit 1
fi

# Keep list synced with storage contract in data/README.md.
targets=(
  "archive"
  "articles"
  "articles_eng"
  "articles_index"
  "articles_index2"
  "articles_web"
  "books_files"
  "cat"
  "chapters"
  "chapters_images"
  "chapters_index"
  "df148749a7a956a4334286aea4e556e8"
  "files"
  "illustrations"
  "news_files"
  "pictures"
  "screens"
  "smarty/zxpress/cache"
  "smarty/zxpress/templates_c"
  "tmp"
  "zxpress_dinamic.png"
)

if [[ "${apply}" -eq 1 ]]; then
  log_info "apply mode: removing only empty placeholders"
else
  log_info "dry-run mode: no files will be removed (use --apply to execute)"
fi

for rel in "${targets[@]}"; do
  path="${site_root}/${rel}"
  if [[ ! -e "${path}" ]]; then
    log_info "skip missing: ${path}"
    continue
  fi

  if [[ -d "${path}" ]]; then
    if rmdir "${path}" 2>/dev/null; then
      if [[ "${apply}" -eq 1 ]]; then
        log_info "removed empty dir: ${path}"
      else
        mkdir -p "${path}"
        log_info "would remove empty dir: ${path}"
      fi
    else
      log_warn "skip non-empty or protected dir: ${path}"
    fi
    continue
  fi

  # Files: remove only zero-byte placeholders.
  size="$(wc -c < "${path}")"
  if [[ "${size}" -ne 0 ]]; then
    log_warn "skip non-empty file: ${path}"
    continue
  fi

  if [[ "${apply}" -eq 1 ]]; then
    rm -f "${path}"
    log_info "removed empty file: ${path}"
  else
    log_info "would remove empty file: ${path}"
  fi
done

log_info "done"
