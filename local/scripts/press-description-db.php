#!/usr/bin/env php
<?php
/**
 * Read-only DB helper for generate-press-description.mjs
 *
 *   php local/scripts/press-description-db.php dump --all
 *   php local/scripts/press-description-db.php dump --press=z80
 *   php local/scripts/press-description-db.php dump --press=z80,on-line,nicron
 *   php local/scripts/press-description-db.php dump --press=123
 *   php local/scripts/press-description-db.php list [--all]
 *
 * Один:      {"press":...,"issues":[...]}
 * Несколько: {"batch":true,"items":[{press,issues},...]}
 * list / big --all: {"ids":[...]} или {"batch":true,"ids_only":true,"ids":[...]}
 *
 * stdout: JSON only. Logs → stderr. Never writes to DB.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/site/includes/functions.php';

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[press-desc-db] ERROR set DB_PASS or ALLOW_EMPTY_DB_PASSWORD=1\n");
	exit(1);
}

$cmd = $argv[1] ?? '';
$opts = parse_opts(array_slice($argv, 2));

if ($cmd === '--help' || $cmd === '-h' || $cmd === '') {
	fwrite(STDOUT, "Usage:\n  dump --all | --press=ID|SLUG[,SLUG…]\n  list [--all]\n");
	exit($cmd === '' ? 1 : 0);
}

if ($cmd !== 'dump' && $cmd !== 'list') {
	fwrite(STDERR, "[press-desc-db] ERROR unknown command: {$cmd} (dump|list)\n");
	exit(1);
}

$db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[press-desc-db] ERROR DB: ' . mysqli_connect_error() . "\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

if ($cmd === 'list') {
	$optsForList = $opts;
	if (!isset($opts['all']) && trim((string) ($opts['press'] ?? '')) === '') {
		$optsForList['all'] = true;
	}
	$pressIds = resolve_press_ids($db, $optsForList);
	echo json_encode(['ids' => $pressIds], JSON_UNESCAPED_UNICODE) . "\n";
	fwrite(STDERR, '[press-desc-db] list: ' . count($pressIds) . " id(s)\n");
	exit(0);
}

$pressIds = resolve_press_ids($db, $opts);
if ($pressIds === []) {
	fwrite(STDERR, "[press-desc-db] ERROR press not found / no issues with meta\n");
	exit(1);
}

if (count($pressIds) === 1) {
	$payload = dump_press($db, $pressIds[0]);
} else {
	if (isset($opts['all']) || count($pressIds) > 40) {
		echo json_encode(
			['batch' => true, 'ids_only' => true, 'ids' => $pressIds],
			JSON_UNESCAPED_UNICODE
		) . "\n";
		fwrite(STDERR, '[press-desc-db] ids_only: ' . count($pressIds) . " press(es)\n");
		exit(0);
	}
	$items = [];
	foreach ($pressIds as $id) {
		$items[] = dump_press($db, $id);
	}
	$payload = ['batch' => true, 'items' => $items];
	fwrite(STDERR, '[press-desc-db] batch: ' . count($items) . " press(es)\n");
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);

/**
 * @param list<string> $argv
 * @return array<string, string|bool>
 */
function parse_opts(array $argv): array
{
	$opts = [];
	foreach ($argv as $arg) {
		if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
			[$k, $v] = explode('=', substr($arg, 2), 2);
			$opts[$k] = $v;
		} elseif (str_starts_with($arg, '--')) {
			$opts[substr($arg, 2)] = true;
		}
	}
	return $opts;
}

/**
 * @param array<string, string|bool> $opts
 * @return list<int>
 */
function resolve_press_ids(mysqli $db, array $opts): array
{
	$rawPress = trim((string) ($opts['press'] ?? ''));
	$minIssues = max(0, (int) ($opts['min-issues'] ?? 0));

	if (isset($opts['all']) || $rawPress === '') {
		return list_all_press_ids_with_issue_meta($db, $minIssues);
	}

	$parts = array_values(array_filter(array_map('trim', explode(',', $rawPress))));
	$ids = [];
	$seen = [];
	foreach ($parts as $part) {
		$id = resolve_one_press_id($db, $part);
		if ($id <= 0) {
			fwrite(STDERR, "[press-desc-db] ERROR press «{$part}» not found\n");
			continue;
		}
		if (isset($seen[$id])) {
			continue;
		}
		$seen[$id] = true;
		$ids[] = $id;
	}
	return $ids;
}

function resolve_one_press_id(mysqli $db, string $press): int
{
	if (ctype_digit($press)) {
		$id = (int) $press;
		$st = $db->prepare('SELECT id FROM press WHERE id=? LIMIT 1');
		if (!$st) {
			return 0;
		}
		$st->bind_param('i', $id);
		$st->execute();
		$row = $st->get_result()->fetch_assoc();
		$st->close();
		return $row ? (int) $row['id'] : 0;
	}

	$st = $db->prepare(
		'SELECT id FROM press WHERE slug_ru=? OR slug_en=? OR title=? LIMIT 1'
	);
	if (!$st) {
		return 0;
	}
	$st->bind_param('sss', $press, $press, $press);
	$st->execute();
	$row = $st->get_result()->fetch_assoc();
	$st->close();
	return $row ? (int) $row['id'] : 0;
}

/**
 * @return list<int>
 */
function list_all_press_ids_with_issue_meta(mysqli $db, int $minIssues = 0): array
{
	$sql = 'SELECT p.id FROM press p'
		. ' INNER JOIN issue i ON i.id_press=p.id'
		. ' WHERE TRIM(IFNULL(i.meta_description_ru,\'\'))<>\'\''
		. '    OR TRIM(IFNULL(i.meta_description_en,\'\'))<>\'\''
		. ' GROUP BY p.id'
		. ' HAVING COUNT(DISTINCT i.id) >= 1';
	if ($minIssues > 0) {
		// total issues of the press (not only with meta) must be > minIssues-1 i.e. >= minIssues
		// Caller passes min-issues as threshold of total issue rows.
		$sql = 'SELECT p.id FROM press p'
			. ' INNER JOIN issue i_all ON i_all.id_press=p.id'
			. ' INNER JOIN issue i ON i.id_press=p.id'
			. '   AND (TRIM(IFNULL(i.meta_description_ru,\'\'))<>\'\''
			. '     OR TRIM(IFNULL(i.meta_description_en,\'\'))<>\'\')'
			. ' GROUP BY p.id'
			. ' HAVING COUNT(DISTINCT i_all.id) >= ' . (int) $minIssues
			. ' ORDER BY p.id ASC';
	} else {
		$sql .= ' ORDER BY p.id ASC';
	}
	$res = $db->query($sql);
	if (!$res) {
		fwrite(STDERR, '[press-desc-db] ERROR list all: ' . $db->error . "\n");
		return [];
	}
	$ids = [];
	while ($row = $res->fetch_assoc()) {
		$ids[] = (int) $row['id'];
	}
	return $ids;
}

function dump_press(mysqli $db, int $pressId): array
{
	$st = $db->prepare(
		'SELECT id, title, type, numbers, slug_ru, slug_en,'
		. ' description_ru, description_en, meta_description_ru, meta_description_en'
		. ' FROM press WHERE id=? LIMIT 1'
	);
	$st->bind_param('i', $pressId);
	$st->execute();
	$press = $st->get_result()->fetch_assoc();
	$st->close();
	if (!$press) {
		fwrite(STDERR, "[press-desc-db] ERROR press #{$pressId} missing\n");
		exit(1);
	}

	$st = $db->prepare(
		'SELECT id, title, date, slug_ru, slug_en,'
		. ' description_ru, description_en, meta_description_ru, meta_description_en'
		. ' FROM issue WHERE id_press=? ORDER BY id ASC'
	);
	$st->bind_param('i', $pressId);
	$st->execute();
	$res = $st->get_result();
	$issues = [];
	while ($row = $res->fetch_assoc()) {
		$metaRu = trim((string) ($row['meta_description_ru'] ?? ''));
		$metaEn = trim((string) ($row['meta_description_en'] ?? ''));
		if ($metaRu === '' && $metaEn === '') {
			continue;
		}
		$issues[] = [
			'id' => (int) $row['id'],
			'title' => (string) ($row['title'] ?? ''),
			'date' => (string) ($row['date'] ?? ''),
			'slug_ru' => (string) ($row['slug_ru'] ?? ''),
			'meta_description_ru' => $metaRu,
			'meta_description_en' => $metaEn,
			'description_ru' => trim((string) ($row['description_ru'] ?? '')),
			'description_en' => trim((string) ($row['description_en'] ?? '')),
		];
	}
	$st->close();

	return [
		'press' => [
			'id' => (int) $press['id'],
			'title' => title_plain((string) $press['title']),
			'type' => (int) ($press['type'] ?? 0),
			'numbers' => (int) ($press['numbers'] ?? 0),
			'slug_ru' => (string) ($press['slug_ru'] ?? ''),
			'slug_en' => (string) ($press['slug_en'] ?? ''),
			'description_ru' => (string) ($press['description_ru'] ?? ''),
			'description_en' => (string) ($press['description_en'] ?? ''),
			'meta_description_ru' => (string) ($press['meta_description_ru'] ?? ''),
			'meta_description_en' => (string) ($press['meta_description_en'] ?? ''),
		],
		'issues' => $issues,
	];
}
