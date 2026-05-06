#!/usr/bin/env php
<?php
/**
 * Normalize P-type text columns in MySQL (plain_text_normalize_for_storage).
 * Keep column list in sync with .ai-factory/CONTENT-FIELDS.md
 *
 * Usage:
 *   php tools/migrate-content-plain.php --dry-run
 *   php tools/migrate-content-plain.php --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
foreach ($argv as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	}
	if ($arg === '--dry-run') {
		$dryRun = true;
	}
}

fwrite(STDERR, "[migrate-content-plain] WARN Take a database backup before --apply.\n");

$root = realpath(__DIR__ . '/..') ?: '';
require_once $root . '/site/includes/functions.php';

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[migrate-content-plain] WARN DB_PASS empty — set DB_PASS or ALLOW_EMPTY_DB_PASSWORD=1\n");
	exit(1);
}

fwrite(STDERR, '[migrate-content-plain] INFO mode=' . ($dryRun ? 'dry-run' : 'apply') . "\n");

$db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[migrate-content-plain] WARN DB connection failed: ' . mysqli_connect_error() . "\n");
	exit(1);
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
	['books', 'series', 'id'],
	['books', 'publisher', 'id'],
	['books', 'authors', 'id'],
	['books_files', 'comment', 'id'],
	['books_files', 'author', 'id'],
	['chapters', 'ch_title', 'ch_id'],
	['news', 'title', 'id'],
	['news', 'source', 'id'],
	['comments', 'nickname', 'id'],
	['comments', 'text', 'id'],
	['calendar', 'title_cal', 'id_cal'],
	['calendar', 'number_cal', 'id_cal'],
	['calendar', 'year_cal', 'id_cal'],
	['echos_zxnet', 'name_from', 'id'],
	['echos_zxnet', 'name_to', 'id'],
	['echos_zxnet', 'tag', 'id'],
	['echos_zxnet', 'tear', 'id'],
	['echos_zxnet', 'origin', 'id'],
	['echos_subjs', 'title', 'id'],
	['echos_subjs2', 'title', 'id'],
	['echos_titles', 'title', 'id'],
	['echos_titles2', 'title', 'id'],
	['files', 'file_title', 'id'],
	['files', 'file_comment', 'id'],
	['menu', 'name', 'id'],
	['menu', 'description', 'id'],
	['tags', 'tag_name', 'id'],
	['tags', 'tag_alias', 'id'],
	['tag', 'name', 'id'],
	['artifacts', 'caption_ru', 'id'],
	['artifacts', 'caption_en', 'id'],
	['artifacts_img', 'caption_ru', 'id'],
	['artifacts_img', 'caption_en', 'id'],
	['blog', 'title', 'id'],
	['indexes', 'title', 'id'],
	['indexes', 'name', 'id'],
	['search', 'text', 'id'],
	['cities', 'name', 'id'],
	['cities', 'name_eng', 'id'],
	['countries', 'country_name', 'id'],
	['countries', 'country_name_eng', 'id'],
	['publishers', 'name', 'id'],
	['publishers', 'alias', 'id'],
	['rubrics', 'name', 'id'],
];

$totalChanged = 0;
foreach ($pColumns as [$table, $column, $idCol]) {
	$key = "{$table}.{$column}";
	$sel = "SELECT `{$idCol}` AS _id, `{$column}` AS _val FROM `{$table}`";
	$res = mysqli_query($db, $sel);
	if ($res === false) {
		fwrite(STDERR, "[migrate-content-plain] WARN skip {$key}: " . mysqli_error($db) . "\n");
		continue;
	}
	$colChanged = 0;
	while ($row = mysqli_fetch_assoc($res)) {
		$old = (string) ($row['_val'] ?? '');
		$new = plain_text_normalize_for_storage($old);
		if ($old === $new) {
			continue;
		}
		$colChanged++;
		$id = $row['_id'];
		if (!$dryRun) {
			$u = mysqli_prepare($db, "UPDATE `{$table}` SET `{$column}`=? WHERE `{$idCol}`=? LIMIT 1");
			if ($u) {
				$iid = (int) $id;
				mysqli_stmt_bind_param($u, 'si', $new, $iid);
				if (!mysqli_stmt_execute($u)) {
					fwrite(STDERR, "[migrate-content-plain] WARN UPDATE fail {$key} id={$id}: " . mysqli_stmt_error($u) . "\n");
				}
				mysqli_stmt_close($u);
			}
		}
	}
	mysqli_free_result($res);
	if ($colChanged > 0) {
		fwrite(STDERR, "[migrate-content-plain] INFO {$key} rows_to_normalize={$colChanged}\n");
	}
	$totalChanged += $colChanged;
}

fwrite(STDERR, "[migrate-content-plain] INFO total_rows_needing_normalize={$totalChanged}\n");
if ($dryRun && $totalChanged > 0) {
	fwrite(STDERR, "[migrate-content-plain] INFO Re-run with --apply after backup to write changes.\n");
}
fwrite(STDERR, "[migrate-content-plain] INFO After --apply on production, run deploy/scripts/manticore-index-all.sh\n");

mysqli_close($db);
