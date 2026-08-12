#!/usr/bin/env php
<?php
/**
 * Read-only DB helper for generate-issue-description.mjs
 *
 *   php local/scripts/issue-description-db.php dump --issue=ID
 *   php local/scripts/issue-description-db.php dump --issue=press-slug          # все выпуски
 *   php local/scripts/issue-description-db.php dump --issue=press-slug/issue-slug
 *   php local/scripts/issue-description-db.php dump --press=slug                # все выпуски
 *   php local/scripts/issue-description-db.php dump --press=slug --issue-slug=slug
 *   php local/scripts/issue-description-db.php dump --all                       # все выпуски с meta
 *   php local/scripts/issue-description-db.php list [--all]                     # только id[]
 *
 * Один выпуск: {"issue":...,"articles":[...]}
 * Несколько:   {"batch":true,"items":[{issue,articles},...]}
 * list:        {"ids":[1,2,...]}
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
	fwrite(STDOUT, "Usage:\n  dump --all | --issue=ID|PRESS|PRESS/ISSUE | --press=SLUG [--issue-slug=SLUG]\n  list [--all]\n");
	exit($cmd === '' ? 1 : 0);
}

if ($cmd !== 'dump' && $cmd !== 'list') {
	fwrite(STDERR, "[issue-desc-db] ERROR unknown command: {$cmd} (dump|list)\n");
	exit(1);
}

$db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[issue-desc-db] ERROR DB: ' . mysqli_connect_error() . "\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

if ($cmd === 'list') {
	// list без фильтров = все id с meta; с --issue/--press — те же правила, что dump
	$optsForList = $opts;
	if (
		!isset($opts['all'])
		&& trim((string) ($opts['issue'] ?? '')) === ''
		&& trim((string) ($opts['press'] ?? '')) === ''
	) {
		$optsForList['all'] = true;
	}
	$issueIds = resolve_issue_ids($db, $optsForList);
	echo json_encode(['ids' => $issueIds], JSON_UNESCAPED_UNICODE) . "\n";
	fwrite(STDERR, '[issue-desc-db] list: ' . count($issueIds) . " id(s)\n");
	exit(0);
}

$issueIds = resolve_issue_ids($db, $opts);
if ($issueIds === []) {
	fwrite(STDERR, "[issue-desc-db] ERROR issue not found\n");
	exit(1);
}

if (count($issueIds) === 1) {
	$payload = dump_issue($db, $issueIds[0]);
} else {
	// --all / большие batch: не тащим всё в один JSON — только ids
	if (isset($opts['all']) || count($issueIds) > 80) {
		echo json_encode(
			['batch' => true, 'ids_only' => true, 'ids' => $issueIds],
			JSON_UNESCAPED_UNICODE
		) . "\n";
		fwrite(STDERR, '[issue-desc-db] ids_only: ' . count($issueIds) . " issue(s)\n");
		exit(0);
	}
	$items = [];
	foreach ($issueIds as $id) {
		$items[] = dump_issue($db, $id);
	}
	$payload = ['batch' => true, 'items' => $items];
	fwrite(STDERR, '[issue-desc-db] batch: ' . count($items) . " issue(s)\n");
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
function resolve_issue_ids(mysqli $db, array $opts): array
{
	$rawIssue = trim((string) ($opts['issue'] ?? ''));
	$press = trim((string) ($opts['press'] ?? ''));
	$issueSlug = trim((string) ($opts['issue-slug'] ?? $opts['issue_slug'] ?? ''));

	// Pure digits → issue.id
	if ($rawIssue !== '' && ctype_digit($rawIssue)) {
		return [(int) $rawIssue];
	}

	// --issue=press/issue-slug
	if ($rawIssue !== '' && str_contains($rawIssue, '/')) {
		[$pressPart, $slugPart] = explode('/', $rawIssue, 2);
		if ($press === '') {
			$press = trim($pressPart);
		}
		if ($issueSlug === '') {
			$issueSlug = trim($slugPart);
		}
		$rawIssue = '';
	}

	if ($rawIssue !== '') {
		if ($press !== '') {
			// --press=X --issue=slug → issue slug
			if ($issueSlug === '') {
				$issueSlug = $rawIssue;
			}
		} elseif ($issueSlug !== '') {
			// --issue=press --issue-slug=N
			$press = $rawIssue;
		} else {
			// --issue=slug alone: all issues of press, or unique issue slug
			$pressId = find_press_id($db, $rawIssue);
			if ($pressId > 0) {
				$issues = list_press_issues($db, $pressId);
				if ($issues === []) {
					fwrite(STDERR, "[issue-desc-db] ERROR press «{$rawIssue}» has no issues\n");
					return [];
				}
				return array_map(static fn(array $row): int => (int) $row['id'], $issues);
			}

			$byIssue = find_issue_ids_by_slug($db, $rawIssue);
			if (count($byIssue) === 1) {
				return [(int) $byIssue[0]];
			}
			if (count($byIssue) > 1) {
				fwrite(STDERR, "[issue-desc-db] ERROR slug «{$rawIssue}» неоднозначен (несколько выпусков). Используйте --issue=PRESS/SLUG\n");
				return [];
			}
			fwrite(STDERR, "[issue-desc-db] ERROR неизвестный slug «{$rawIssue}»\n");
			return [];
		}
	}

	// --press=SLUG without issue → all issues
	if ($press !== '' && $issueSlug === '') {
		$pressId = find_press_id($db, $press);
		if ($pressId <= 0) {
			fwrite(STDERR, "[issue-desc-db] ERROR press «{$press}» not found\n");
			return [];
		}
		$issues = list_press_issues($db, $pressId);
		if ($issues === []) {
			fwrite(STDERR, "[issue-desc-db] ERROR press «{$press}» has no issues\n");
			return [];
		}
		return array_map(static fn(array $row): int => (int) $row['id'], $issues);
	}

	if ($press !== '' && $issueSlug !== '') {
		$id = find_issue_id_by_press_and_slug($db, $press, $issueSlug);
		return $id > 0 ? [$id] : [];
	}

	// --all или пустые фильтры: все выпуски, у которых есть meta статей
	if (isset($opts['all']) || ($rawIssue === '' && $press === '' && $issueSlug === '')) {
		return list_all_issue_ids_with_meta($db);
	}

	return [];
}

/**
 * @return list<int>
 */
function list_all_issue_ids_with_meta(mysqli $db): array
{
	$sql = 'SELECT DISTINCT i.id FROM issue i'
		. ' INNER JOIN articles a ON a.id_issue=i.id'
		. ' WHERE TRIM(IFNULL(a.meta_description_ru,\'\'))<>\'\''
		. '    OR TRIM(IFNULL(a.meta_description_en,\'\'))<>\'\''
		. ' ORDER BY i.id ASC';
	$res = $db->query($sql);
	if (!$res) {
		fwrite(STDERR, '[issue-desc-db] ERROR list all: ' . $db->error . "\n");
		return [];
	}
	$ids = [];
	while ($row = $res->fetch_assoc()) {
		$ids[] = (int) $row['id'];
	}
	return $ids;
}

function find_press_id(mysqli $db, string $press): int
{
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
 * @return list<array{id:int,title:string,slug_ru:string}>
 */
function list_press_issues(mysqli $db, int $pressId): array
{
	$st = $db->prepare(
		'SELECT id, title, slug_ru FROM issue WHERE id_press=? ORDER BY id ASC'
	);
	if (!$st) {
		return [];
	}
	$st->bind_param('i', $pressId);
	$st->execute();
	$res = $st->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$out[] = [
			'id' => (int) $row['id'],
			'title' => (string) $row['title'],
			'slug_ru' => (string) ($row['slug_ru'] ?? ''),
		];
	}
	$st->close();
	return $out;
}

/**
 * @return list<int>
 */
function find_issue_ids_by_slug(mysqli $db, string $slug): array
{
	$st = $db->prepare(
		'SELECT id FROM issue WHERE slug_ru=? OR slug_en=? OR title=? ORDER BY id ASC'
	);
	if (!$st) {
		return [];
	}
	$st->bind_param('sss', $slug, $slug, $slug);
	$st->execute();
	$res = $st->get_result();
	$ids = [];
	while ($row = $res->fetch_assoc()) {
		$ids[] = (int) $row['id'];
	}
	$st->close();
	return $ids;
}

function find_issue_id_by_press_and_slug(mysqli $db, string $press, string $issueSlug): int
{
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
