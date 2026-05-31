#!/bin/bash
# Static checks on nginx config and repo configs (no live HTTP / Docker required).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NGINX_CONF="$ROOT/conf/nginx-site.conf"
HEADERS_CONF="$ROOT/conf/security-headers.conf"
FAILED=0

fail() {
	echo "FAIL: $1" >&2
	FAILED=1
}

if [[ ! -f "$NGINX_CONF" ]]; then
	fail "missing $NGINX_CONF"
	exit 1
fi
if [[ ! -f "$HEADERS_CONF" ]]; then
	fail "missing $HEADERS_CONF"
	exit 1
fi

# Shared headers live in security-headers.conf (included from nginx-site.conf).
grep -q 'X-Frame-Options' "$HEADERS_CONF" || fail "X-Frame-Options not in $HEADERS_CONF"
grep -q 'X-Content-Type-Options' "$HEADERS_CONF" || fail "X-Content-Type-Options not in $HEADERS_CONF"
grep -q 'Referrer-Policy' "$HEADERS_CONF" || fail "Referrer-Policy not in $HEADERS_CONF"
grep -qE 'Content-Security-Policy|CSP' "$NGINX_CONF" || fail "Content-Security-Policy not in nginx-site.conf"
grep -qE 'location = /sitemap\.xml' "$NGINX_CONF" || fail "location = /sitemap.xml not in nginx-site.conf"
grep -q 'sitemap\.php' "$NGINX_CONF" || fail "sitemap.xml must fastcgi to sitemap.php"
grep -qE "form-action 'self'" "$NGINX_CONF" || fail "CSP must include form-action 'self' (otherwise browsers block login POST with default-src 'none')"
# HSTS may be commented until HTTPS is verified — still must be present as policy text
grep -qE 'Strict-Transport-Security|HSTS' "$HEADERS_CONF" "$NGINX_CONF" || fail "HSTS (Strict-Transport-Security) not mentioned in nginx/security headers"

# Hardcoded DB/password literals in tracked configs (skip env-generated conf/manticore.conf — gitignored)
if grep -RIn --include='*.conf' --include='*.yml' --include='*.yaml' --include='*.ini' \
	-E '(sql_pass|password|MYSQL_.*PASSWORD)\s*[=:]\s*["\x27]?[^{$%][^"\x27\s]{3,}' \
	"$ROOT/conf" "$ROOT/docker-compose.yml" 2>/dev/null | grep -vF 'manticore.conf:' | grep -v '\${' | grep -v 'PLACEHOLDER' | grep -v 'mysql_native_password'; then
	fail "possible hardcoded credential pattern in conf or docker-compose.yml"
fi

echo "smoke_http.sh: OK"
exit "$FAILED"
