#!/usr/bin/env php
<?php
/**
 * Fail if P-columns contain obvious HTML/BBCode junk (CI / deploy gate).
 * Substrings configurable via ZXPRESS_P_FORBIDDEN (JSON array) or built-in defaults.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$root = realpath(__DIR__ . '/../..') ?: '';
require_once $root . '/site/includes/functions.php';

$custom = getenv('ZXPRESS_P_FORBIDDEN');
/** @var list<string> $forbidden */
$forbidden = $custom ? json_decode($custom, true) : null;
if (!is_array($forbidden)) {
	$forbidden = ['</span>', '<div', '<span', '</div>', '&lt;', '[url=', '[/url]'];
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[check-content-p-columns] SKIP DB_PASS empty\n");
	exit(0);
}

try {
	$port = (int) (getenv('DB_PORT') ?: 3306);
	$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
} catch (Throwable $e) {
	fwrite(STDERR, '[check-content-p-columns] SKIP no DB: ' . $e->getMessage() . "\n");
	exit(0);
}
if (!$db) {
	fwrite(STDERR, "[check-content-p-columns] SKIP empty handle\n");
	exit(0);
}
mysqli_set_charset($db, 'utf8mb4');

/** @var list<array{0:string,1:string,2:string}> */
$pColumns = [
	['articles', 'title', 'id'],
	['articles', 'title_eng', 'id'],
	['press', 'title', 'id'],
	['issue', 'title', 'id'],
	['books', 'title1', 'id'],
	['books', 'title2', 'id'],
	['chapters', 'ch_title', 'ch_id'],
	['news', 'title', 'id'],
	['menu', 'name', 'id'],
	['tags', 'tag_name', 'id'],
];

$violations = 0;
$maxPrint = 25;
foreach ($pColumns as [$table, $column, $idCol]) {
	$sel = "SELECT `{$idCol}` AS _id, `{$column}` AS _val FROM `{$table}` WHERE CHAR_LENGTH(`{$column}`) > 0 LIMIT 20000";
	$res = mysqli_query($db, $sel);
	if ($res === false) {
		continue;
	}
	while ($row = mysqli_fetch_assoc($res)) {
		$val = (string) ($row['_val'] ?? '');
		foreach ($forbidden as $bad) {
			if ($bad !== '' && str_contains($val, $bad)) {
				if ($violations < $maxPrint) {
					echo "{$table}.{$column} id={$row['_id']} hit=" . json_encode($bad, JSON_UNESCAPED_UNICODE) . "\n";
				}
				$violations++;
				break;
			}
		}
	}
	mysqli_free_result($res);
}

mysqli_close($db);

if ($violations > 0) {
	fwrite(STDERR, "[check-content-p-columns] ERROR violations={$violations}\n");
	exit(1);
}

fwrite(STDERR, "[check-content-p-columns] OK\n");
exit(0);
