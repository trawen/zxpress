#!/usr/bin/env php
<?php
/**
 * Verify E2E plain password vs DB hash (no mysqli — hash passed as base64 argv).
 * Usage:
 *   HASH_B64=$(docker compose exec -T db mysql ... | base64 -w0)
 *   PLAIN_B64=$(printf %s "$E2E_ADMIN_PASS" | base64 -w0)
 *   bash tools/php.sh tools/verify-e2e-user-db.php "$PLAIN_B64" "$HASH_B64" "$LEVEL"
 */
declare(strict_types=1);

if ($argc < 4) {
	fwrite(STDERR, "usage: verify-e2e-user-db.php <plain_b64> <hash_b64> <level_json>\n");
	exit(2);
}

$plain = (string) base64_decode((string) $argv[1], true);
$stored = (string) base64_decode((string) $argv[2], true);
if ($plain === '' && $argv[1] !== '') {
	fwrite(STDERR, "[FIX] invalid plain_b64\n");
	exit(2);
}
if ($stored === '' && $argv[2] !== '') {
	fwrite(STDERR, "[FIX] invalid hash_b64\n");
	exit(2);
}

$levelRaw = (string) $argv[3];
$level = $levelRaw === 'null' ? null : (is_numeric($levelRaw) ? (int) $levelRaw : $levelRaw);

$root = dirname(__DIR__);
require_once $root . '/site/includes/auth.php';

$len = strlen($stored);
$prefix = substr($stored, 0, min(7, max(0, $len)));
$fmt = 'unknown';
if ($len >= 60 && str_starts_with($stored, '$2')) {
	$fmt = 'bcrypt_or_argon';
} elseif ($len === 32 && ctype_xdigit($stored)) {
	$fmt = 'md5_hex';
} elseif ($stored === '') {
	$fmt = 'empty';
}

echo "[FIX] DB password column: length={$len} format={$fmt} prefix={$prefix}…\n";
echo '[FIX] DB level: ' . var_export($level, true) . "\n";

$ok = admin_password_verify($plain, $stored);
echo '[FIX] admin_password_verify: ' . ($ok ? 'MATCH' : 'NO_MATCH') . "\n";

$lvlOk = zxpress_user_is_admin_level($level);
echo '[FIX] admin level (1 or NULL): ' . ($lvlOk ? 'OK' : 'FAIL') . "\n";

exit($ok && $lvlOk ? 0 : 4);
