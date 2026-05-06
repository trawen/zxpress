<?php
/**
 * One-off CLI: rewrite users.password from legacy plaintext to password_hash().
 * Skips rows that already look like bcrypt ($2...) or legacy MD5 (32 hex).
 *
 * Usage (from repo root, DB reachable as in docker compose):
 *   bash tools/run-migrate-users-password.sh
 *   php tools/migrate-users-password-plaintext-to-bcrypt.php --dry-run
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "[FIX] CLI only.\n");
	exit(1);
}

$dry = in_array('--dry-run', $argv, true) || in_array('-n', $argv, true);

$root = dirname(__DIR__);
function zxpress_load_dotenv(string $path): void
{
	if (!is_readable($path)) {
		return;
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
		$val = trim(substr($t, $eq + 1));
		if (
			(strlen($val) >= 2 && $val[0] === '"' && $val[strlen($val) - 1] === '"')
			|| (strlen($val) >= 2 && $val[0] === "'" && $val[strlen($val) - 1] === "'")
		) {
			$val = substr($val, 1, -1);
		}
		if (getenv($key) === false) {
			putenv("$key=$val");
			$_ENV[$key] = $val;
		}
	}
}

zxpress_load_dotenv($root . '/.env');

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
		fwrite(STDERR, "[FIX] users.password is varchar($maxLen); bcrypt needs 60+. Set MYSQL_ROOT_PASSWORD in .env and re-run, or as DBA run:\n");
		fwrite(STDERR, $alterSql . "\n");
		exit(1);
	}
	$root = @mysqli_connect($dbHost, 'root', $rootPass, $dbName);
	if (!$root) {
		fwrite(STDERR, '[FIX] root connect for ALTER failed: ' . mysqli_connect_error() . "\n");
		exit(1);
	}
	if (!$root->query($alterSql)) {
		fwrite(STDERR, '[FIX] ALTER failed: ' . $root->error . "\n");
		exit(1);
	}
	$root->close();
	fwrite(STDERR, "[FIX] users.password widened to VARCHAR(255) (root ALTER)\n");
}

function zxpress_stored_looks_like_bcrypt(string $s): bool
{
	return strlen($s) >= 3 && str_starts_with($s, '$2');
}

function zxpress_stored_looks_like_md5_hex(string $s): bool
{
	return strlen($s) === 32 && ctype_xdigit($s);
}

$res = $mysqli->query('SELECT id, username, password FROM users ORDER BY id');
if (!$res) {
	fwrite(STDERR, '[FIX] SELECT failed: ' . $mysqli->error . "\n");
	exit(1);
}

$updated = 0;
$skipped = 0;
while ($row = $res->fetch_assoc()) {
	$id = (int) $row['id'];
	$user = (string) $row['username'];
	$stored = trim((string) $row['password']);
	if ($stored === '') {
		fwrite(STDERR, "[FIX] skip id=$id user=$user (empty password)\n");
		$skipped++;
		continue;
	}
	if (zxpress_stored_looks_like_bcrypt($stored)) {
		$skipped++;
		continue;
	}
	if (zxpress_stored_looks_like_md5_hex($stored)) {
		$skipped++;
		continue;
	}

	$plain = $stored;
	$newHash = password_hash($plain, PASSWORD_DEFAULT);
	if ($dry) {
		echo "[FIX] dry-run id=$id user=$user len=" . strlen($plain) . " -> bcrypt\n";
		$updated++;
		continue;
	}

	$stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
	if (!$stmt) {
		fwrite(STDERR, '[FIX] prepare failed: ' . $mysqli->error . "\n");
		exit(1);
	}
	$stmt->bind_param('si', $newHash, $id);
	if (!$stmt->execute()) {
		fwrite(STDERR, "[FIX] UPDATE failed id=$id: " . $stmt->error . "\n");
		exit(1);
	}
	$stmt->close();
	fwrite(STDERR, '[FIX] migrate password: id=' . $id . ' user=' . $user . " plaintext->bcrypt\n");
	$updated++;
}

$res->free();

$md5Res = $mysqli->query(
	"SELECT id, username FROM users WHERE CHAR_LENGTH(password) = 32 AND password REGEXP '^[0-9a-f]{32}$' ORDER BY id",
);
if ($md5Res instanceof mysqli_result && $md5Res->num_rows > 0) {
	fwrite(
		STDERR,
		'[FIX] WARNING: ' . $md5Res->num_rows
			. " user(s) still have MD5 in users.password — web login expects bcrypt only. "
			. "Set a new hash manually, e.g. php -r \"echo password_hash('new_secret', PASSWORD_DEFAULT), PHP_EOL;\"\n",
	);
	while ($m = $md5Res->fetch_assoc()) {
		fwrite(STDERR, '  id=' . (int) $m['id'] . ' username=' . $m['username'] . "\n");
	}
}
if ($md5Res instanceof mysqli_result) {
	$md5Res->free();
}

$mysqli->close();

fwrite(
	STDERR,
	'[FIX] migrate-users-password-plaintext-to-bcrypt: '
		. ($dry ? 'dry-run ' : '')
		. "updated={$updated} skipped_md5_or_bcrypt_or_empty={$skipped}\n",
);
exit(0);
