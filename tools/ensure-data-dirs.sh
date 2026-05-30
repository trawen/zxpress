#!/usr/bin/env bash
# Create the runtime data/ tree expected by docker-compose.yml and data/README.md.
# Does NOT create mirror directories under site/ — content lives in data/ only.
#
# Ownership: inside the PHP container, www-data is UID 33. On the host, adjust
#   chown/chmod if uploads fail with permission errors (e.g. chown -R 33:33 data/uploads).

set -eu

log_info() {
  printf 'INFO: %s\n' "$*" >&2
}

log_warn() {
  printf 'WARN: %s\n' "$*" >&2
}

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"

if [[ ! -f "${repo_root}/docker-compose.yml" ]]; then
  log_warn "docker-compose.yml not found; expected repository root at ${repo_root}"
  exit 1
fi

data_root="${repo_root}/data"

ensure_dir() {
  local rel="$1"
  local path="${data_root}/${rel}"
  if [[ ! -d "${path}" ]]; then
    mkdir -p "${path}"
    log_info "created ${path}"
  else
    log_info "exists ${path}"
  fi
}

log_info "ensuring data directories under ${data_root}"

ensure_dir "content-store/articles"
ensure_dir "content-store/articles-eng"
ensure_dir "content-store/chapters"
ensure_dir "content-store/letters"
ensure_dir "content-store/letters/preview"
ensure_dir "content-store/publications"
ensure_dir "content-store/publications/preview"
ensure_dir "content-store/files"
ensure_dir "uploads/pictures"
ensure_dir "uploads/screens"
ensure_dir "uploads/illustrations"
ensure_dir "uploads/books-files"
ensure_dir "uploads/files"
ensure_dir "uploads/news-files"
ensure_dir "image-archive"
ensure_dir "cache/chronology"
ensure_dir "cache/smarty/templates_c"
ensure_dir "cache/smarty/cache"
ensure_dir "integrations/sape"
ensure_dir "tmp"

# Chronology PNG runtime target; entrypoint regenerates content.
chronology_png="${data_root}/cache/chronology/zxpress_dinamic.png"
if [[ ! -f "${chronology_png}" ]]; then
  : >"${chronology_png}"
  log_info "created empty ${chronology_png} (writable bind over RO site/)"
fi

log_info "done"
