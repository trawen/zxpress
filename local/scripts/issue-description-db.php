#!/usr/bin/env php
<?php
/**
 * Read-only DB helper for generate-issue-description.mjs
 *
 *   php local/scripts/issue-description-db.php dump --issue=ID
 *   php local/scripts/issue-description-db.php dump --press=slug --issue-slug=slug
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
	fwrite(STDERR, "[issue-desc-db] ERROR set DB_PASS or ALLOW_EMPTY_DB_PASSWORD=1\n");
	exit(1);
}

$cmd = $argv[1] ?? '';
$opts = parse_opts(array_slice($argv, 2));

if ($cmd === '--help' || $cmd === '-h' || $cmd === '') {
	fwrite(STDOUT, "Usage:\n  dump --issue=ID | --press=SLUG --issue-slug=SLUG\n");
	exit($cmd === '' ? 1 : 0);
}

if ($cmd !== 'dump') {
	fwrite(STDERR, "[issue-desc-db] ERROR unknown command: {$cmd} (only dump)\n");
	exit(1);
}

$db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[issue-desc-db] ERROR DB: ' . mysqli_connect_error() . "\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

$issueId = resolve_issue_id($db, $opts);
if ($issueId <= 0) {
	fwrite(STDERR, "[issue-desc-db] ERROR issue not found\n");
	exit(1);
}

$payload = dump_issue($db, $issueId);
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
 */
function resolve_issue_id(mysqli $db, array $opts): int
{
	if (isset($opts['issue']) && is_numeric($opts['issue'])) {
		return (int) $opts['issue'];
	}

	$press = trim((string) ($opts['press'] ?? ''));
	$issueSlug = trim((string) ($opts['issue-slug'] ?? $opts['issue_slug'] ?? ''));
	if ($press === '' || $issueSlug === '') {
		return 0;
	}

	$sql = 'SELECT i.id FROM issue i'
		. ' INNER JOIN press p ON p.id=i.id_press'
		. ' WHERE (p.slug_ru=? OR p.slug_en=? OR p.title=?)'
		. ' AND (i.slug_ru=? OR i.slug_en=? OR i.title=?)'
		. ' LIMIT 1';
	$st = $db->prepare($sql);
	if (!$st) {
		return 0;
	}
	$st->bind_param('ssssss', $press, $press, $press, $issueSlug, $issueSlug, $issueSlug);
	$st->execute();
	$res = $st->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$st->close();
	return $row ? (int) $row['id'] : 0;
}

function dump_issue(mysqli $db, int $issueId): array
{
	$st = $db->prepare(
		'SELECT i.id, i.id_press, i.title AS issue_title, i.date, i.slug_ru, i.slug_en,'
		. ' i.description_ru, i.description_en, i.meta_description_ru, i.meta_description_en,'
		. ' p.title AS press_title, p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en'
		. ' FROM issue i INNER JOIN press p ON p.id=i.id_press WHERE i.id=? LIMIT 1'
	);
	$st->bind_param('i', $issueId);
	$st->execute();
	$issue = $st->get_result()->fetch_assoc();
	$st->close();
	if (!$issue) {
		fwrite(STDERR, "[issue-desc-db] ERROR issue #{$issueId} missing\n");
		exit(1);
	}

	$st = $db->prepare(
		'SELECT id, title, meta_description_ru, meta_description_en, slug_ru, slug_en'
		. ' FROM articles WHERE id_issue=? ORDER BY id ASC'
	);
	$st->bind_param('i', $issueId);
	$st->execute();
	$res = $st->get_result();
	$articles = [];
	while ($row = $res->fetch_assoc()) {
		$metaRu = trim((string) ($row['meta_description_ru'] ?? ''));
		$metaEn = trim((string) ($row['meta_description_en'] ?? ''));
		$title = trim((string) ($row['title'] ?? ''));
		if ($metaRu === '' && $metaEn === '') {
			continue;
		}
		$articles[] = [
			'id' => (int) $row['id'],
			'title' => $title,
			'meta_description_ru' => $metaRu,
			'meta_description_en' => $metaEn,
		];
	}
	$st->close();

	return [
		'issue' => [
			'id' => (int) $issue['id'],
			'id_press' => (int) $issue['id_press'],
			'title' => (string) $issue['issue_title'],
			'date' => (string) ($issue['date'] ?? ''),
			'slug_ru' => (string) ($issue['slug_ru'] ?? ''),
			'slug_en' => (string) ($issue['slug_en'] ?? ''),
			'description_ru' => (string) ($issue['description_ru'] ?? ''),
			'description_en' => (string) ($issue['description_en'] ?? ''),
			'meta_description_ru' => (string) ($issue['meta_description_ru'] ?? ''),
			'meta_description_en' => (string) ($issue['meta_description_en'] ?? ''),
			'press_title' => title_plain((string) $issue['press_title']),
			'press_slug_ru' => (string) ($issue['press_slug_ru'] ?? ''),
			'press_slug_en' => (string) ($issue['press_slug_en'] ?? ''),
		],
		'articles' => $articles,
	];
}
