#!/usr/bin/env php
<?php
/**
 * Rebuild articles.search_text for Manticore indexing.
 *
 * Usage:
 *   php cli/manticore-prepare-articles.php --dry-run
 *   php cli/manticore-prepare-articles.php --apply
 *   php cli/manticore-prepare-articles.php --apply --id=123
 *   php cli/manticore-prepare-articles.php --apply --limit=100
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
$limit = 0;
$onlyId = 0;

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif (str_starts_with($arg, '--limit=')) {
		$limit = max(0, (int) substr($arg, 8));
	} elseif (str_starts_with($arg, '--id=')) {
		$onlyId = max(0, (int) substr($arg, 5));
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--limit=N] [--id=N]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'zxpress.ru';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$siteRoot = is_dir('/home/zxpress/web/zxpress.ru/public_html')
	? '/home/zxpress/web/zxpress.ru/public_html'
	: dirname(__DIR__);

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/article_search_text.php';

/** @var mysqli $db */
if (!isset($db) || !($db instanceof mysqli)) {
	fwrite(STDERR, "No mysqli \$db from init.inc\n");
	exit(1);
}

if (!article_search_text_column_exists($db)) {
	fwrite(STDERR, "ERROR: articles.search_text missing — run db/migration/articles_search_text.sql\n");
	exit(1);
}

$sql = 'SELECT id, text_ru, text_en, text_type, text_type_ru, text_type_en FROM articles';
$types = '';
$params = [];
if ($onlyId > 0) {
	$sql .= ' WHERE id=?';
	$types = 'i';
	$params[] = $onlyId;
}
$sql .= ' ORDER BY id ASC';
if ($limit > 0) {
	$sql .= ' LIMIT ' . (int) $limit;
}

$stmt = $db->prepare($sql);
if (!$stmt) {
	fwrite(STDERR, "prepare failed: {$db->error}\n");
	exit(1);
}
if ($types !== '') {
	$stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$scanned = 0;
$changed = 0;
$empty = 0;

while ($row = $res->fetch_assoc()) {
	$scanned++;
	$id = (int) $row['id'];
	$text = article_search_build_text($row);
	if ($text === '') {
		$empty++;
	}
	if ($dryRun) {
		if ($scanned <= 5 || ($onlyId > 0)) {
			fwrite(STDOUT, sprintf(
				"#%d len=%d preview=%s\n",
				$id,
				strlen($text),
				substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 120)
			));
		}
		$changed++;
		continue;
	}
	$upd = $db->prepare('UPDATE articles SET search_text=? WHERE id=? LIMIT 1');
	if (!$upd) {
		fwrite(STDERR, "UPDATE prepare failed id={$id}: {$db->error}\n");
		exit(1);
	}
	$upd->bind_param('si', $text, $id);
	if (!$upd->execute()) {
		fwrite(STDERR, "UPDATE failed id={$id}: {$upd->error}\n");
		$upd->close();
		exit(1);
	}
	$upd->close();
	$changed++;
}

$stmt->close();

fwrite(
	STDOUT,
	sprintf(
		"DONE mode=%s scanned=%d written=%d empty=%d\n",
		$dryRun ? 'dry-run' : 'apply',
		$scanned,
		$changed,
		$empty
	)
);
