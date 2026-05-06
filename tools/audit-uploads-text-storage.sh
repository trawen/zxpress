#!/usr/bin/env bash
# Audit uploads text storage consistency against DB ids.
# Usage: bash tools/audit-uploads-text-storage.sh

set -eu

log_info() { printf 'INFO: %s\n' "$*" >&2; }
log_warn() { printf 'WARN: %s\n' "$*" >&2; }
log_error() { printf 'ERROR: %s\n' "$*" >&2; }

strict_mode=0
strict_eng_mode=0
if [[ "${1:-}" == "--strict" ]]; then
  strict_mode=1
elif [[ "${1:-}" == "--strict-eng" ]]; then
  strict_mode=1
  strict_eng_mode=1
elif [[ -n "${1:-}" ]]; then
  log_error "unknown argument: ${1:-}"
  log_info "usage: bash tools/audit-uploads-text-storage.sh [--strict|--strict-eng]"
  exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
uploads_root="${repo_root}/data/uploads"

articles_dir="${uploads_root}/articles"
articles_eng_dir="${uploads_root}/articles-eng"
chapters_dir="${uploads_root}/chapters"
tmp_dir=""
if mkdir -p "${repo_root}/data/tmp/audit-uploads-text-storage" 2>/dev/null; then
  tmp_dir="${repo_root}/data/tmp/audit-uploads-text-storage"
else
  tmp_dir="$(mktemp -d "/tmp/audit-uploads-text-storage.XXXXXX")"
  log_warn "data/tmp is not writable, using temp dir ${tmp_dir}"
fi
trap 'rm -rf "${tmp_dir}"' EXIT

require_dir() {
  local dir="$1"
  if [[ ! -d "${dir}" ]]; then
    log_error "missing directory: ${dir}"
    exit 1
  fi
}

require_dir "${articles_dir}"
require_dir "${articles_eng_dir}"
require_dir "${chapters_dir}"

profile_dir() {
  local key="$1"
  local dir="$2"

  local file_count
  local size_bytes
  local zero_count
  local non_numeric_count

  file_count="$(find "${dir}" -type f | wc -l | tr -d '[:space:]')"
  size_bytes="$(du -sb "${dir}" | awk '{print $1}')"
  zero_count="$(find "${dir}" -type f -size 0c | wc -l | tr -d '[:space:]')"
  non_numeric_count="$(find "${dir}" -maxdepth 1 -type f -printf '%f\n' | awk '!/^[0-9]+$/{c++} END{print c+0}')"

  log_info "${key}: files=${file_count} size_bytes=${size_bytes} zero_byte_files=${zero_count} non_numeric_files=${non_numeric_count}"
  if [[ "${zero_count}" -gt 0 ]]; then
    log_warn "${key}: found zero-byte files"
  fi
  if [[ "${non_numeric_count}" -gt 0 ]]; then
    log_warn "${key}: found non-numeric filenames"
  fi
}

build_numeric_file_list() {
  local dir="$1"
  local out="$2"
  find "${dir}" -maxdepth 1 -type f -printf '%f\n' | awk '/^[0-9]+$/{print}' | LC_ALL=C sort -u > "${out}"
}

build_numeric_file_list "${articles_dir}" "${tmp_dir}/articles.files"
build_numeric_file_list "${articles_eng_dir}" "${tmp_dir}/articles_eng.files"
build_numeric_file_list "${chapters_dir}" "${tmp_dir}/chapters.files"

profile_dir "uploads/articles" "${articles_dir}"
profile_dir "uploads/articles-eng" "${articles_eng_dir}"
profile_dir "uploads/chapters" "${chapters_dir}"

log_info "querying DB ids via docker compose exec db mysql"
docker compose exec -T db sh -lc 'mysql -N -B -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT id FROM articles"' | LC_ALL=C sort -u > "${tmp_dir}/articles.db"
docker compose exec -T db sh -lc 'mysql -N -B -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT ch_id FROM chapters"' | LC_ALL=C sort -u > "${tmp_dir}/chapters.db"
awk '$1 <= 10948 {print $1}' "${tmp_dir}/articles.db" | LC_ALL=C sort -u > "${tmp_dir}/articles_eng_expected.db"

count_lines() {
  local path="$1"
  wc -l < "${path}" | tr -d '[:space:]'
}

compare_sets() {
  local label="$1"
  local expected="$2"
  local actual="$3"
  local severity="$4" # error|warn

  local missing_file="${tmp_dir}/${label}.missing"
  local orphan_file="${tmp_dir}/${label}.orphan"
  comm -23 "${expected}" "${actual}" > "${missing_file}"
  comm -13 "${expected}" "${actual}" > "${orphan_file}"

  local expected_count actual_count missing_count orphan_count
  expected_count="$(count_lines "${expected}")"
  actual_count="$(count_lines "${actual}")"
  missing_count="$(count_lines "${missing_file}")"
  orphan_count="$(count_lines "${orphan_file}")"

  log_info "${label}: expected=${expected_count} actual=${actual_count} missing=${missing_count} orphan=${orphan_count}"

  if [[ "${missing_count}" -gt 0 || "${orphan_count}" -gt 0 ]]; then
    if [[ "${severity}" == "error" ]]; then
      log_error "${label}: mismatch detected (blocking)"
      blocking_failed=1
    else
      log_warn "${label}: mismatch detected (non-blocking)"
      eng_failed=1
    fi
  fi
}

blocking_failed=0
eng_failed=0
compare_sets "articles" "${tmp_dir}/articles.db" "${tmp_dir}/articles.files" "error"
compare_sets "chapters" "${tmp_dir}/chapters.db" "${tmp_dir}/chapters.files" "error"
compare_sets "articles_eng" "${tmp_dir}/articles_eng_expected.db" "${tmp_dir}/articles_eng.files" "warn"

if [[ "${strict_mode}" -eq 1 && "${blocking_failed}" -ne 0 ]]; then
  log_error "strict mode: blocking mismatches detected"
  exit 1
fi
if [[ "${strict_eng_mode}" -eq 1 && "${eng_failed}" -ne 0 ]]; then
  log_error "strict-eng mode: EN mismatches detected"
  exit 1
fi

log_info "audit complete"
