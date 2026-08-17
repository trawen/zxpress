#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.yml"

SKIP_BACKUP=0
SKIP_REINDEX=0
NO_BUILD=0

usage() {
	cat <<'EOF'
Usage: update.sh [options]

Default: full safe update (backup + pull + build php + restart + smoke + reindex).

Options:
  --fast           Shortcut: --skip-backup --no-build --skip-reindex
                   Use for routine PHP/template deploys (bind-mounted site/).
  --skip-backup    Skip pre-update MySQL/data backup
  --no-build       Skip `docker compose build php` (restart only)
  --skip-reindex   Skip full Manticore indexer --all
  -h, --help       Show this help

Examples:
  ./deploy/scripts/update.sh              # full release
  ./deploy/scripts/update.sh --fast       # quick code pull + restart
  ./deploy/scripts/update.sh --skip-reindex --skip-backup
EOF
}

while [ $# -gt 0 ]; do
	case "$1" in
		--fast)
			SKIP_BACKUP=1
			NO_BUILD=1
			SKIP_REINDEX=1
			;;
		--skip-backup) SKIP_BACKUP=1 ;;
		--no-build) NO_BUILD=1 ;;
		--skip-reindex) SKIP_REINDEX=1 ;;
		-h | --help)
			usage
			exit 0
			;;
		-*)
			echo "ERROR: unknown option: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			echo "ERROR: unexpected argument: $1" >&2
			usage >&2
			exit 1
			;;
	esac
	shift
done

cd "$PROJECT_DIR"

echo "=== zxpress.ru — Update ==="
echo "[deploy] INFO flags skip_backup=${SKIP_BACKUP} no_build=${NO_BUILD} skip_reindex=${SKIP_REINDEX}"

if [ "$SKIP_BACKUP" -eq 1 ]; then
	echo "[skip] Creating pre-update backup"
else
	echo "[1/7] Creating pre-update backup..."
	"$SCRIPT_DIR/backup.sh"
fi

echo "[2/7] Pulling latest code..."
git pull --ff-only || { echo "ERROR: git pull failed. Resolve conflicts first."; exit 1; }

echo "[3/7] Validating env contract..."
"$SCRIPT_DIR/../tests/validate_env_contract.sh"

echo "[4/7] Rendering runtime search config..."
"$SCRIPT_DIR/render-manticore-conf.sh"

if [ "$NO_BUILD" -eq 1 ]; then
	echo "[skip] Rebuilding PHP image (--no-build)"
else
	echo "[5/7] Rebuilding PHP image..."
	docker compose -f "$COMPOSE_FILE" build php
fi

echo "[6/7] Rolling restart..."
docker compose -f "$COMPOSE_FILE" up -d --no-deps php
sleep 5
# --force-recreate: nginx bind-mounts conf/nginx-site.conf; if the file was
# replaced (new inode), a plain restart keeps the stale (deleted) mount.
docker compose -f "$COMPOSE_FILE" up -d --force-recreate --no-deps nginx

echo "[7/7] Verifying health..."
"$SCRIPT_DIR/health-check.sh"

echo "[deploy] [INFO] Running smoke HTTP checks..."
set +e
LOG_LEVEL="${LOG_LEVEL:-STANDARD}" "$SCRIPT_DIR/../tests/smoke_http.sh"
smoke_code=$?
set -e
echo "[deploy] [INFO] smoke_http.sh exit code=${smoke_code}"
if [ "$smoke_code" -ne 0 ]; then
	exit "$smoke_code"
fi

if [ "$SKIP_REINDEX" -eq 1 ]; then
	echo "[skip] Re-indexing ManticoreSearch (--skip-reindex)"
else
	echo "[deploy] [INFO] Re-indexing ManticoreSearch..."
	docker compose -f "$COMPOSE_FILE" exec -T -u manticore manticore indexer --all --rotate 2>/dev/null || echo "[manticore-fix] WARN indexer returned non-zero"
	sleep 2
	docker compose -f "$COMPOSE_FILE" exec -T manticore mysql -P9306 -h0 -e "RELOAD TABLES" 2>/dev/null || echo "[manticore-fix] WARN RELOAD TABLES failed"
fi

echo ""
echo "=== Update complete ==="
docker compose -f "$COMPOSE_FILE" ps
