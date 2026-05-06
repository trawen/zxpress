#!/usr/bin/env bash
# Copy uploads text storage into content-store (idempotent).
# Usage:
#   bash tools/migrate-uploads-text-store.sh --dry-run
#   bash tools/migrate-uploads-text-store.sh --apply

set -eu

log_info() { printf 'INFO: %s\n' "$*" >&2; }
log_warn() { printf 'WARN: %s\n' "$*" >&2; }
log_error() { printf 'ERROR: %s\n' "$*" >&2; }

mode="${1:-"--dry-run"}"
if [[ "$mode" != "--dry-run" && "$mode" != "--apply" ]]; then
	log_error "unknown argument: $mode"
	log_info "usage: bash tools/migrate-uploads-text-store.sh [--dry-run|--apply]"
	exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"

declare -A SRC
declare -A DST
SRC[articles]="${repo_root}/data/uploads/articles"
SRC[articles-eng]="${repo_root}/data/uploads/articles-eng"
SRC[chapters]="${repo_root}/data/uploads/chapters"
DST[articles]="${repo_root}/data/content-store/articles"
DST[articles-eng]="${repo_root}/data/content-store/articles-eng"
DST[chapters]="${repo_root}/data/content-store/chapters"

container_data_root="/home/zxpress/web/zxpress.ru/data"
container_path_for_host() {
	local host_path="$1"
	local host_data_root="${repo_root}/data"
	case "$host_path" in
	"${host_data_root}"/*)
		printf '%s%s\n' "$container_data_root" "${host_path#${host_data_root}}"
		;;
	*)
		return 1
		;;
	esac
}

copy_tree() {
	local src="$1"
	local dst="$2"
	if cp -a -n "$src/." "$dst/." 2>/dev/null; then
		return 0
	fi
	local csrc
	local cdst
	csrc="$(container_path_for_host "$src")" || return 1
	cdst="$(container_path_for_host "$dst")" || return 1
	log_warn "host copy failed, retrying via docker compose exec php cp"
	docker compose exec -T php sh -lc "mkdir -p \"$cdst\" && cp -a -n \"$csrc\"/. \"$cdst\"/."
}

count_files() {
	local dir="$1"
	find "$dir" -maxdepth 1 -type f | wc -l | tr -d '[:space:]'
}

for key in articles articles-eng chapters; do
	if [[ ! -d "${SRC[$key]}" ]]; then
		log_error "missing source directory: ${SRC[$key]}"
		exit 1
	fi
done

if [[ "$mode" == "--dry-run" ]]; then
	for key in articles articles-eng chapters; do
		src_count="$(count_files "${SRC[$key]}")"
		dst_count="0"
		if [[ -d "${DST[$key]}" ]]; then
			dst_count="$(count_files "${DST[$key]}")"
		fi
		log_info "${key}: source_files=${src_count} destination_files=${dst_count}"
	done
	log_info "dry-run mode; no files copied"
	exit 0
fi

for key in articles articles-eng chapters; do
	mkdir -p "${DST[$key]}"
	copy_tree "${SRC[$key]}" "${DST[$key]}"
	src_count="$(count_files "${SRC[$key]}")"
	dst_count="$(count_files "${DST[$key]}")"
	log_info "${key}: source_files=${src_count} destination_files=${dst_count}"
	if [[ "$dst_count" -lt "$src_count" ]]; then
		log_error "${key}: destination has fewer files than source"
		exit 1
	fi
done

log_info "content-store backfill complete"
