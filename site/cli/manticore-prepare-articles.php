#!/usr/bin/env php
<?php
/**
 * Rebuild articles.search_text for Manticore indexing.
 *
 * Batched to avoid OOM on full corpus (~18k rows).
 *
 * Usage:
 *   php cli/manticore-prepare-articles.php --dry-run
 *   php cli/manticore-prepare-articles.php --apply
 *   php cli/manticore-prepare-articles.php --apply --id=123
 *   php cli/manticore-prepare-articles.php --apply --limit=100
 *   php cli/manticore-prepare-articles.php --apply --batch=200 --after-id=0
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

@ini_set('memory_limit', '512M');

$dryRun = true;
$limit = 0;
$onlyId = 0;
$batch = 200;
$afterId = 0;

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif (str_starts_with($arg, '--limit=')) {
		$limit = max(0, (int) substr($arg, 8));
	} elseif (str_starts_with($arg, '--id=')) {
		$onlyId = max(0, (int) substr($arg, 5));
	} elseif (str_starts_with($arg, '--batch=')) {
		$batch = max(1, (int) substr($arg, 8));
	} elseif (str_starts_with($arg, '--after-id=')) {
		$afterId = max(0, (int) substr($arg, 11));
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--limit=N] [--id=N] [--batch=N] [--after-id=N]\n");
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

$scanned = 0;
$changed = 0;
$empty = 0;
$cursor = $afterId;

$upd = null;
if (!$dryRun) {
	$upd = $db->prepare('UPDATE articles SET search_text=? WHERE id=? LIMIT 1');
	if (!$upd) {
		fwrite(STDERR, "UPDATE prepare failed: {$db->error}\n");
		exit(1);
	}
}

while (true) {
	if ($onlyId > 0) {
		$sql = 'SELECT id, text_ru, text_en, text_type, text_type_ru, text_type_en '
			. 'FROM articles WHERE id=? LIMIT 1';
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			fwrite(STDERR, "prepare failed: {$db->error}\n");
			exit(1);
		}
		$stmt->bind_param('i', $onlyId);
	} else {
		$take = $batch;
		if ($limit > 0) {
			$remaining = $limit - $scanned;
			if ($remaining <= 0) {
				break;
			}
			$take = min($take, $remaining);
		}
		$sql = 'SELECT id, text_ru, text_en, text_type, text_type_ru, text_type_en '
			. 'FROM articles WHERE id>? ORDER BY id ASC LIMIT ' . (int) $take;
		$stmt = $db->prepare($sql);
		if (!$stmt) {
			fwrite(STDERR, "prepare failed: {$db->error}\n");
			exit(1);
		}
		$stmt->bind_param('i', $cursor);
	}

	$stmt->execute();
	$res = $stmt->get_result();
	$rows = [];
	while ($row = $res->fetch_assoc()) {
		$rows[] = $row;
	}
	$stmt->close();

	if ($rows === []) {
		break;
	}

	foreach ($rows as $row) {
		$scanned++;
		$id = (int) $row['id'];
		$cursor = $id;
		$text = article_search_build_text($row);
		if ($text === '') {
			$empty++;
		}
		if ($dryRun) {
			if ($scanned <= 5 || $onlyId > 0) {
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
		/** @var mysqli_stmt $upd */
		$upd->bind_param('si', $text, $id);
		if (!$upd->execute()) {
			fwrite(STDERR, "UPDATE failed id={$id}: {$upd->error}\n");
			exit(1);
		}
		$changed++;
	}

	if ($onlyId > 0) {
		break;
	}
	if ($scanned % 1000 === 0 || count($rows) < $batch) {
		fwrite(STDOUT, sprintf("… scanned=%d last_id=%d\n", $scanned, $cursor));
	}
	// Free peak memory between batches (Parsedown / large bodies).
	gc_collect_cycles();
}

if ($upd instanceof mysqli_stmt) {
	$upd->close();
}

fwrite(
	STDOUT,
	sprintf(
		"DONE mode=%s scanned=%d written=%d empty=%d last_id=%d\n",
		$dryRun ? 'dry-run' : 'apply',
		$scanned,
		$changed,
		$empty,
		$cursor
	)
);
