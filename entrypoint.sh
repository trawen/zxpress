#!/bin/bash
set -e

# Initialize app code volume from host source on first start.
SRC_SITE="${APP_SRC_ROOT:-/src-site}"
APP_ROOT="${APP_ROOT:-/home/zxpress/web/zxpress.ru/public_html}"
BOOTSTRAP_MARKER="$APP_ROOT/.volume-initialized"

log_info() {
    echo "[docker-runtime] INFO $*"
}

# APP_ROOT is the bind-mounted ./site tree (read-only in compose). Do not mkdir here.
# Runtime paths are resolved in PHP via site/includes/storage_paths.php into ../data.
# Bind-mount: host ./site is mounted at both /src-site and APP_ROOT — same inode; skip tar (stale volume pattern).
skip_tar_bootstrap=0
if [ -f "$SRC_SITE/ezines.php" ] && [ -f "$APP_ROOT/ezines.php" ]; then
    id_src=$(stat -c '%d:%i' "$SRC_SITE/ezines.php" 2>/dev/null || echo "")
    id_root=$(stat -c '%d:%i' "$APP_ROOT/ezines.php" 2>/dev/null || echo "")
    if [ -n "$id_src" ] && [ "$id_src" = "$id_root" ]; then
        skip_tar_bootstrap=1
        log_info "[FIX] APP_ROOT is bind-mounted from src-site (same inode); skip tar bootstrap"
    fi
fi

if [ "$skip_tar_bootstrap" -eq 0 ] && [ ! -f "$BOOTSTRAP_MARKER" ]; then
    log_info "first_start app_root=$APP_ROOT src_root=$SRC_SITE"
    if [ ! -d "$SRC_SITE" ]; then
        echo "[docker-runtime] ERROR missing source root: $SRC_SITE" >&2
        exit 1
    fi
    log_info "initializing empty app root from src (named volume / first boot)"
    if ! tar -C "$SRC_SITE" \
        --exclude='./archive' \
        --exclude='./cat' \
        --exclude='./chapters_images' \
        --exclude='./articles' \
        --exclude='./articles_eng' \
        --exclude='./chapters' \
        --exclude='./pictures' \
        --exclude='./screens' \
        --exclude='./illustrations' \
        --exclude='./books_files' \
        --exclude='./files' \
        --exclude='./news_files' \
        --exclude='./tmp' \
        --exclude='./smarty/zxpress/templates_c' \
        --exclude='./smarty/zxpress/cache' \
        --exclude='./df148749a7a956a4334286aea4e556e8' \
        -cf - . | tar --no-same-owner --no-same-permissions --no-overwrite-dir --delay-directory-restore -C "$APP_ROOT" -xf -; then
        log_info "tar_copy_warn non-fatal metadata restore errors ignored"
    fi
    if [ ! -f "$APP_ROOT/ezines.php" ]; then
        echo "[docker-runtime] ERROR bootstrap incomplete: ezines.php missing in $APP_ROOT" >&2
        exit 1
    fi
    touch "$BOOTSTRAP_MARKER" 2>/dev/null || log_info "[FIX] WARN could not write bootstrap marker (readonly app tree?)"
fi

if [ ! -f "$APP_ROOT/ezines.php" ]; then
    echo "[docker-runtime] ERROR ezines.php missing in $APP_ROOT" >&2
    exit 1
fi

log_info "writable storage: ./data mounted at APP_ROOT/../data; no entrypoint mkdir"

# Wait until MySQL accepts connections (php container often starts before db is ready).
wait_for_db() {
    local i=0
    while [ "$i" -lt 90 ]; do
        if php -r '
            $h = getenv("DB_HOST") ?: "db";
            $u = getenv("DB_USER") ?: "zxpress_u";
            $p = getenv("DB_PASS") ?: "";
            $n = getenv("DB_NAME") ?: "zxpress_db";
            if ($p === "" && getenv("ALLOW_EMPTY_DB_PASSWORD") !== "1") { exit(1); }
            $m = @mysqli_connect($h, $u, $p, $n);
            exit($m ? 0 : 1);
        ' 2>/dev/null; then
            log_info "mysql ready host=${DB_HOST:-db} name=${DB_NAME:-zxpress_db}"
            return 0
        fi
        i=$((i + 1))
        sleep 1
    done
    log_info "WARN mysql not ready after ${i}s — chronology regenerate may fail until DB is up"
    return 1
}

# Regenerate chronology chart PNG from DB.
# Script path: prefer /src-site; PNG path lives in writable data/ mount.
chronology_graph_startup() {
    local app_root="${APP_ROOT:-/home/zxpress/web/zxpress.ru/public_html}"
    local data_root="${ZXPRESS_DATA_ROOT:-/home/zxpress/web/zxpress.ru/data}"
    local src_site="${APP_SRC_ROOT:-/src-site}"
    local script_src="${src_site}/regenerate_chronology_graph.php"
    local script_vol="${app_root}/regenerate_chronology_graph.php"
    local png="${data_root}/cache/chronology/zxpress_dinamic.png"
    local script=""
    local i=0
    local out=""
    # Prefer APP_ROOT copy so relative helpers resolve against runtime tree.
    if [ -f "$script_vol" ]; then
        script="$script_vol"
    elif [ -f "$script_src" ]; then
        script="$script_src"
    else
        log_info "chronology: regenerate script missing (no $script_src or $script_vol), skip"
        return 0
    fi
    wait_for_db || true
    while [ "$i" -lt 30 ]; do
        # Explicit if-branch: on success chown+log+return; on failure log output and retry (clearer than `&& { … }` with set -e).
        if out=$(ZXPRESS_DATA_ROOT="$data_root" php "$script" "$png" 2>&1); then
            chown www-data:www-data "$png" 2>/dev/null || log_info "[FIX] WARN chown skipped for chronology png"
            log_info "chronology graph regenerated -> $png"
            if [ -n "$out" ]; then
                log_info "chronology: $out"
            fi
            return 0
        fi
        if [ -n "$out" ]; then
            log_info "chronology retry $((i + 1))/30: $out"
        fi
        i=$((i + 1))
        sleep 2
    done
    log_info "chronology graph not regenerated after retries (will update on first chronology.php visit)"
}
chronology_graph_startup

# Session + fallback tmp cleanup (Option B): background subshell is acceptable here because
# `exec php-fpm` replaces this shell — FPM becomes PID 1. The cleanup loop
# remains a child of the old shell process; trade-off: one extra long-lived
# process vs. in-process timers inside php-fpm (not available by default).
DATA_ROOT="${ZXPRESS_DATA_ROOT:-/home/zxpress/web/zxpress.ru/data}"
FALLBACK_TMP="${DATA_ROOT}/tmp"
TMP_RETENTION_MIN="${TMP_RETENTION_MIN:-1440}"
(while true; do
    sleep 600
    find /home/zxpress/tmp -maxdepth 1 -name 'sess_*' -mmin +30 -delete 2>/dev/null || true
    if [ -d "$FALLBACK_TMP" ]; then
        removed_count="$(find "$FALLBACK_TMP" -maxdepth 1 -type f -mmin +"$TMP_RETENTION_MIN" -print -delete 2>/dev/null | wc -l | tr -d '[:space:]')"
        if [ "${removed_count:-0}" -gt 0 ]; then
            log_info "[FIX] fallback tmp cleanup removed=${removed_count} path=$FALLBACK_TMP age_min=$TMP_RETENTION_MIN"
        fi
    fi
done) &

# Start PHP-FPM in foreground
exec php-fpm
