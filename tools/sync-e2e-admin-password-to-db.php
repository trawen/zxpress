<?php
/**
 * Set users.password for E2E_ADMIN_USER to password_hash(E2E_ADMIN_PASS, PASSWORD_DEFAULT).
 * Env merge matches e2e/playwright.config.ts: .env, then .env.e2e, then e2e/.env.e2e (later non-empty wins).
 *
 * Usage: bash tools/run-sync-e2e-admin-password-to-db.sh
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "[FIX] CLI only.\n");
	exit(1);
}

$root = dirname(__DIR__);

/** @return array<string, string> */
function zxpress_parse_dotenv_file(string $path): array
{
	$out = [];
	if (!is_readable($path)) {
		return $out;
	}
	foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
		$t = trim($line);
		if ($t === '' || str_starts_with($t, '#')) {
			continue;
		}
		$eq = strpos($t, '=');
		if ($eq === false) {
			continue;
		}
		$key = trim(substr($t, 0, $eq));
		if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
			continue;
		}
		$val = trim(substr($t, $eq + 1));
		if (
			(strlen($val) >= 2 && $val[0] === '"' && $val[strlen($val) - 1] === '"')
			|| (strlen($val) >= 2 && $val[0] === "'" && $val[strlen($val) - 1] === "'")
		) {
			$val = substr($val, 1, -1);
		}
		$out[$key] = $val;
	}
	return $out;
}

/** Later files override earlier when the new value is non-empty (after trim). */
function zxpress_merge_dotenv_layers(string $root): array
{
	$m = zxpress_parse_dotenv_file($root . '/.env');
	foreach (zxpress_parse_dotenv_file($root . '/.env.e2e') as $k => $v) {
		if (trim($v) !== '') {
			$m[$k] = $v;
		}
	}
	foreach (zxpress_parse_dotenv_file($root . '/e2e/.env.e2e') as $k => $v) {
		if (trim($v) !== '') {
			$m[$k] = $v;
		}
	}
	return $m;
}

$env = zxpress_merge_dotenv_layers($root);
foreach ($env as $k => $v) {
	putenv($k . '=' . $v);
	$_ENV[$k] = $v;
}

$user = trim((string) ($env['E2E_ADMIN_USER'] ?? 'admin'));
$plain = (string) ($env['E2E_ADMIN_PASS'] ?? '');
if ($plain === '') {
	fwrite(STDERR, "[FIX] E2E_ADMIN_PASS empty after merging .env / .env.e2e / e2e/.env.e2e\n");
	exit(2);
}

$dbHost = getenv('DB_HOST') ?: 'db';
$dbUser = getenv('DB_USER') ?: 'zxpress_u';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'zxpress_db';

if ($dbPass === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[FIX] DB_PASS empty.\n");
	exit(1);
}

$mysqli = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if (!$mysqli) {
	fwrite(STDERR, '[FIX] DB connect failed: ' . mysqli_connect_error() . "\n");
	exit(1);
}
$mysqli->set_charset('utf8mb4');

$alterSql = 'ALTER TABLE users MODIFY COLUMN `password` VARCHAR(255) COLLATE utf8mb3_unicode_ci NOT NULL';

$lenRes = $mysqli->query(
	"SELECT CHARACTER_MAXIMUM_LENGTH AS ml FROM INFORMATION_SCHEMA.COLUMNS "
	. "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password'",
);
$maxLen = $lenRes ? (int) ($lenRes->fetch_assoc()['ml'] ?? 0) : 0;
if ($lenRes) {
	$lenRes->free();
}

if ($maxLen > 0 && $maxLen < 255) {
	$rootPass = getenv('MYSQL_ROOT_PASSWORD') ?: '';
	if ($rootPass === '') {
		fwrite(STDERR, "[FIX] users.password is varchar($maxLen); set MYSQL_ROOT_PASSWORD in .env for ALTER.\n");
		exit(1);
	}
	$rootM = @mysqli_connect($dbHost, 'root', $rootPass, $dbName);
	if (!$rootM) {
		fwrite(STDERR, '[FIX] root connect for ALTER failed: ' . mysqli_connect_error() . "\n");
		exit(1);
	}
	if (!$rootM->query($alterSql)) {
		fwrite(STDERR, '[FIX] ALTER failed: ' . $rootM->error . "\n");
		exit(1);
	}
	$rootM->close();
	fwrite(STDERR, "[FIX] users.password widened to VARCHAR(255)\n");
}

$hash = password_hash($plain, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE username = ? LIMIT 1');
if (!$stmt) {
	fwrite(STDERR, '[FIX] prepare failed: ' . $mysqli->error . "\n");
	exit(1);
}
$stmt->bind_param('ss', $hash, $user);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$mysqli->close();

if ($affected !== 1) {
	fwrite(STDERR, "[FIX] UPDATE affected_rows=$affected for username=" . $user . " (row missing?)\n");
	exit(3);
}

fwrite(STDERR, "[FIX] users.password set to bcrypt for username=" . $user . " (len " . strlen($hash) . ")\n");
exit(0);
