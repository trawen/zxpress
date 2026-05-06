#!/usr/bin/env bash
# Quarantine orphan/non-numeric files in uploads text storage.
# Usage:
#   bash tools/remediate-uploads-text-orphans.sh --dry-run
#   bash tools/remediate-uploads-text-orphans.sh --apply

set -eu

log_info() { printf 'INFO [FIX]: %s\n' "$*" >&2; }
log_warn() { printf 'WARN [FIX]: %s\n' "$*" >&2; }
log_error() { printf 'ERROR [FIX]: %s\n' "$*" >&2; }

mode="${1:-"--dry-run"}"
if [[ "$mode" != "--dry-run" && "$mode" != "--apply" ]]; then
	log_error "unknown argument: $mode"
	log_info "usage: bash tools/remediate-uploads-text-orphans.sh [--dry-run|--apply]"
	exit 2
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"

uploads_root="${repo_root}/data/uploads"
articles_dir="${uploads_root}/articles"
articles_eng_dir="${uploads_root}/articles-eng"
chapters_dir="${uploads_root}/chapters"

for dir in "$articles_dir" "$articles_eng_dir" "$chapters_dir"; do
	if [[ ! -d "$dir" ]]; then
		log_error "missing directory: $dir"
		exit 1
	fi
done

ts="$(date '+%Y%m%d-%H%M%S')"
quarantine_root="${repo_root}/data/_quarantine/uploads-text-orphans-${ts}"
manifest="${quarantine_root}/manifest.tsv"

tmp_dir="$(mktemp -d "/tmp/remediate-uploads-text-orphans.XXXXXX")"
trap 'rm -rf "${tmp_dir}"' EXIT

build_numeric_file_list() {
	local dir="$1"
	local out="$2"
	find "$dir" -maxdepth 1 -type f -printf '%f\n' | awk '/^[0-9]+$/{print}' | LC_ALL=C sort -u > "$out"
}

build_non_numeric_file_list() {
	local dir="$1"
	local out="$2"
	find "$dir" -maxdepth 1 -type f -printf '%f\n' | awk '!/^[0-9]+$/{print}' | LC_ALL=C sort -u > "$out"
}

query_db_sets() {
	log_info "querying DB ids via docker compose exec db mysql"
	docker compose exec -T db sh -lc 'mysql -N -B -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT id FROM articles"' | LC_ALL=C sort -u > "${tmp_dir}/articles.db"
	docker compose exec -T db sh -lc 'mysql -N -B -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT ch_id FROM chapters"' | LC_ALL=C sort -u > "${tmp_dir}/chapters.db"
	awk '$1 <= 10948 {print $1}' "${tmp_dir}/articles.db" | LC_ALL=C sort -u > "${tmp_dir}/articles_eng_expected.db"
}

plan_moves_for_key() {
	local key="$1"
	local dir="$2"
	local expected="$3"

	local numeric_list="${tmp_dir}/${key}.numeric"
	local non_numeric_list="${tmp_dir}/${key}.non_numeric"
	local orphan_numeric="${tmp_dir}/${key}.orphan_numeric"
	local moves="${tmp_dir}/${key}.moves"

	build_numeric_file_list "$dir" "$numeric_list"
	build_non_numeric_file_list "$dir" "$non_numeric_list"
	comm -13 "$expected" "$numeric_list" > "$orphan_numeric"

	: > "$moves"
	while IFS= read -r name; do
		[[ -z "$name" ]] && continue
		printf '%s\t%s\torphan-numeric\n' "$key" "$name" >> "$moves"
	done < "$orphan_numeric"

	while IFS= read -r name; do
		[[ -z "$name" ]] && continue
		printf '%s\t%s\tnon-numeric\n' "$key" "$name" >> "$moves"
	done < "$non_numeric_list"

	cat "$moves" >> "${tmp_dir}/all.moves"
}

path_for_key() {
	case "$1" in
	articles) printf '%s\n' "$articles_dir" ;;
	articles-eng) printf '%s\n' "$articles_eng_dir" ;;
	chapters) printf '%s\n' "$chapters_dir" ;;
	*) return 1 ;;
	esac
}

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

move_with_fallback() {
	local src="$1"
	local dest="$2"

	if mv "$src" "$dest" 2>/dev/null; then
		return 0
	fi

	local csrc
	local cdest
	csrc="$(container_path_for_host "$src")" || {
		log_error "cannot map source path to container: $src"
		return 1
	}
	cdest="$(container_path_for_host "$dest")" || {
		log_error "cannot map destination path to container: $dest"
		return 1
	}
	log_warn "local move failed; retrying via docker compose exec php mv"
	docker compose exec -T php mv "$csrc" "$cdest" < /dev/null
}

mkdir -p "$tmp_dir"
: > "${tmp_dir}/all.moves"
query_db_sets

plan_moves_for_key "articles" "$articles_dir" "${tmp_dir}/articles.db"
plan_moves_for_key "articles-eng" "$articles_eng_dir" "${tmp_dir}/articles_eng_expected.db"
plan_moves_for_key "chapters" "$chapters_dir" "${tmp_dir}/chapters.db"

total_moves="$(wc -l < "${tmp_dir}/all.moves" | tr -d '[:space:]')"
if [[ "$total_moves" -eq 0 ]]; then
	log_info "no orphan/non-numeric files found; nothing to remediate"
	exit 0
fi

log_info "planned remediation entries: ${total_moves}"
awk -F'\t' '{count[$1]++} END {for (k in count) printf "INFO [FIX]: %s entries=%d\n", k, count[k]}' "${tmp_dir}/all.moves" >&2

if [[ "$mode" == "--dry-run" ]]; then
	log_info "dry-run mode; no files moved"
	exit 0
fi

mkdir -p "$quarantine_root"
printf 'key\tfilename\treason\tsource\tdestination\n' > "$manifest"

while IFS=$'\t' read -r key name reason; do
	[[ -z "$key" || -z "$name" ]] && continue
	src_dir="$(path_for_key "$key")"
	src="${src_dir}/${name}"
	dest_dir="${quarantine_root}/${key}/${reason}"
	dest="${dest_dir}/${name}"

	if [[ ! -f "$src" ]]; then
		log_warn "skip missing source: $src"
		continue
	fi

	mkdir -p "$dest_dir"
	move_with_fallback "$src" "$dest"
	printf '%s\t%s\t%s\t%s\t%s\n' "$key" "$name" "$reason" "$src" "$dest" >> "$manifest"
done < "${tmp_dir}/all.moves"

log_info "remediation applied; quarantine: ${quarantine_root}"
log_info "manifest: ${manifest}"
