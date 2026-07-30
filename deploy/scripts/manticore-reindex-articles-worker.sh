#!/bin/sh
set -eu

# Background worker:
# - waits for "articles.pending" marker in runtime data
# - runs fast reindex for fulltext docs (test1_articles)
# - then reloads Manticore tables so searchd sees fresh segments

DATA_ROOT="${ZXPRESS_DATA_ROOT:-/home/zxpress/web/zxpress.ru/data}"
QUEUE_DIR="$DATA_ROOT/manticore-reindex"
PENDING_FILE="$QUEUE_DIR/articles.pending"
LOCK_FILE="$QUEUE_DIR/articles.reindex.lock"

MANTICORE_MYSQL_HOST="${MANTICORE_MYSQL_HOST:-manticore}"
MANTICORE_MYSQL_PORT="${MANTICORE_MYSQL_PORT:-9306}"

POLL_SECONDS="${MANTICORE_REINDEX_POLL_SECONDS:-5}"
DEBOUNCE_SECONDS="${MANTICORE_REINDEX_DEBOUNCE_SECONDS:-15}"

mkdir -p "$QUEUE_DIR"
touch "$LOCK_FILE"

exec 9>"$LOCK_FILE"

log() { echo "[manticore-reindex-worker] $*" >&2; }

while true; do
	if [ -f "$PENDING_FILE" ]; then
		pending_ts="$(cat "$PENDING_FILE" 2>/dev/null || echo 0)"
		case "$pending_ts" in
			''|*[!0-9]*) pending_ts=0 ;;
		esac

		now_ts="$(date +%s)"
		age=$((now_ts - pending_ts))

		# Debounce: wait a bit to batch multiple admin saves.
		if [ "$pending_ts" -gt 0 ] && [ "$age" -ge "$DEBOUNCE_SECONDS" ]; then
			# Ensure single reindex at a time.
			if flock -n 9; then
				start_ts="$pending_ts"
				log "start reindex test1_articles (pending_ts=$pending_ts age=${age}s)"

				# Reindex only articles fulltext (chapters are independent).
				# We still use --rotate; /var/run/manticore/searchd.pid is shared via
				# manticore_run volume so rotation can work even from this worker.
				if indexer test1_articles --rotate >/tmp/manticore-reindex-indexer.log 2>&1; then
					:
				else
					log "indexer failed; see /tmp/manticore-reindex-indexer.log"
				fi

				if mysql -P"$MANTICORE_MYSQL_PORT" -h"$MANTICORE_MYSQL_HOST" -e "RELOAD TABLES" >/tmp/manticore-reindex-reload.log 2>&1; then
					:
				else
					log "RELOAD TABLES failed; see /tmp/manticore-reindex-reload.log"
				fi

				# If new save happened while indexing, keep the latest marker.
				latest_ts="$(cat "$PENDING_FILE" 2>/dev/null || echo 0)"
				case "$latest_ts" in
					''|*[!0-9]*) latest_ts=0 ;;
				esac

				if [ "$latest_ts" -eq "$start_ts" ]; then
					rm -f "$PENDING_FILE" 2>/dev/null || true
					log "reindex done; marker cleared"
				else
					log "new changes detected during reindex (latest_ts=$latest_ts); marker kept"
				fi
			else
				log "skip: lock is busy"
			fi
		fi
	fi

	sleep "$POLL_SECONDS"
done

