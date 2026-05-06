#!/usr/bin/env bash
# Quarantine and remove uploads text directories after cutover.
# Usage:
#   bash tools/clean-uploads-text-store.sh --dry-run
#   bash tools/clean-uploads-text-store.sh --apply

set -eu

log_info() { printf 'INFO: %s\n' "$*" >&2; }
log_warn() { printf 'WARN: %s\n' "$*" >&2; }
log_error() { printf 'ERROR: %s\n' "$*" >&2; }

mode="${1:-"--dry-run"}"
if [[ "$mode" != "--dry-run" && "$mode" != "--apply" ]]; then
	log_error "unknown argument: $mode"
	log_info "usage: bash tools/clean-uploads-text-store.sh [--dry-run|--apply]"
	exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
uploads_root="${repo_root}/data/uploads"

declare -a targets=(
	"${uploads_root}/articles"
	"${uploads_root}/articles-eng"
	"${uploads_root}/chapters"
)

ts="$(date '+%Y%m%d-%H%M%S')"
quarantine_root="${repo_root}/data/_quarantine/uploads-text-retire-${ts}"

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

move_dir() {
	local src="$1"
	local dst="$2"
	if mv "$src" "$dst" 2>/dev/null; then
		return 0
	fi
	local csrc
	local cdst
	csrc="$(container_path_for_host "$src")" || return 1
	cdst="$(container_path_for_host "$dst")" || return 1
	log_warn "host move failed, retrying via docker compose exec php mv"
	docker compose exec -T php sh -lc "mkdir -p \"$(dirname "$cdst")\" && mv \"$csrc\" \"$cdst\""
}

for dir in "${targets[@]}"; do
	if [[ ! -d "$dir" ]]; then
		log_warn "skip missing directory: $dir"
		continue
	fi
	log_info "candidate: $dir"
done

if [[ "$mode" == "--dry-run" ]]; then
	log_info "dry-run mode; no directories moved"
	exit 0
fi

mkdir -p "$quarantine_root"

for dir in "${targets[@]}"; do
	if [[ ! -d "$dir" ]]; then
		continue
	fi
	name="$(basename "$dir")"
	dest="${quarantine_root}/${name}"
	move_dir "$dir" "$dest"
	log_info "moved: $dir -> $dest"
done

log_info "uploads text directories retired to quarantine: $quarantine_root"
