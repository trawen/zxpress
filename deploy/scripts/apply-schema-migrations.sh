#!/usr/bin/env bash
# Apply pending files from db/migrate/*.sql. Invoked by update.sh after git pull.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"
MIGRATE_DIR="$PROJECT_DIR/db/migrate"

cd "$PROJECT_DIR"

mysql_stdin() {
	docker compose -f "$COMPOSE_FILE" exec -T db \
		sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 -N -B -D "${MYSQL_DATABASE:-zxpress_db}"'
}

echo "[migrate] Applying schema migrations from db/migrate/"

if ! docker compose -f "$COMPOSE_FILE" exec -T db sh -lc 'mysqladmin ping -h localhost -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null; then
	echo "[migrate] ERROR db is not ready" >&2
	exit 1
fi

mysql_stdin <<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
  filename VARCHAR(191) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL

applied="$(mysql_stdin <<<'SELECT filename FROM schema_migrations' | tr -d '\r')"
applied_set=" "
while IFS= read -r name; do
	[ -n "$name" ] && applied_set="${applied_set}${name} "
done <<< "$applied"

shopt -s nullglob
files=("$MIGRATE_DIR"/*.sql)
if [ ${#files[@]} -eq 0 ]; then
	echo "[migrate] No SQL files in db/migrate/"
	exit 0
fi

pending=0
for file in "${files[@]}"; do
	base="$(basename "$file")"
	case "$applied_set" in
		*" ${base} "*)
			echo "[migrate] skip ${base}"
			continue
			;;
	esac
	echo "[migrate] apply ${base}"
	if ! mysql_stdin < "$file" >/dev/null; then
		echo "[migrate] ERROR failed: ${base}" >&2
		exit 1
	fi
	printf "INSERT INTO schema_migrations (filename) VALUES ('%s');\n" "$base" | mysql_stdin >/dev/null
	pending=$((pending + 1))
done

if [ "$pending" -eq 0 ]; then
	echo "[migrate] Nothing pending"
else
	echo "[migrate] Applied ${pending} file(s)"
fi
