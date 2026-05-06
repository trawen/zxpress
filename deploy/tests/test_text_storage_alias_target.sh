#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CONF="${ROOT}/conf/nginx-site.conf"

fail=0

check_alias() {
	local route="$1"
	local target="$2"
	if ! grep -Eq "location \^\~ ${route} \{ alias ${target};" "$CONF"; then
		echo "[ERROR] alias mismatch: ${route} -> ${target} not found in ${CONF}"
		fail=1
	fi
}

check_alias "/articles/" "/home/zxpress/web/zxpress.ru/data/content-store/articles/"
check_alias "/articles_eng/" "/home/zxpress/web/zxpress.ru/data/content-store/articles-eng/"
check_alias "/chapters/" "/home/zxpress/web/zxpress.ru/data/content-store/chapters/"

if [ "$fail" -ne 0 ]; then
	echo "test_text_storage_alias_target.sh: FAIL"
	exit 1
fi

echo "test_text_storage_alias_target.sh: OK"
