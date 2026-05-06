#!/usr/bin/env php
<?php
/**
 * Scan DB plain-text (P) columns for suspicious markup (HTML/entities/BBCode).
 * Keep column list in sync with .ai-factory/CONTENT-FIELDS.md
 *
 * Usage (from repo root, with DB env set):
 *   docker compose -f docker-compose.yml -f docker-compose.test.yml run --rm -v "$PWD:/app" -w /app php php tools/scan-content-markup.php
 * Options:
 *   --dry-run   default; no writes (this tool is read-only anyway)
 *   --limit=N   max sample rows printed per table.column (default 30)
 *   --scan-article-files  also read site/articles/{id} for '<' etc.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$printLimit = 30;
$scanArticleFiles = false;
foreach ($argv as $arg) {
	if ($arg === '--dry-run') {
		/* read-only tool; flag kept for plan compatibility */
	}
	if (str_starts_with($arg, '--limit=')) {
		$printLimit = max(0, (int) substr($arg, 8));
	}
	if ($arg === '--scan-article-files') {
		$scanArticleFiles = true;
	}
}

$root = realpath(__DIR__ . '/..') ?: '';
$site = $root . '/site';
require_once $site . '/includes/functions.php';

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[scan-content-markup] WARN DB_PASS empty — set DB_PASS or ALLOW_EMPTY_DB_PASSWORD=1\n");
	exit(1);
}

fwrite(STDERR, "[scan-content-markup] INFO start site={$site}\n");

$db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[scan-content-markup] WARN DB connection failed: ' . mysqli_connect_error() . "\n");
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

$looksBad = static function (string $s): bool {
	if ($s === '') {
		return false;
	}
	return str_contains($s, '<')
		|| str_contains($s, '</')
		|| str_contains($s, '&lt;')
		|| str_contains($s, '&gt;')
		|| str_contains($s, '&#')
		|| str_contains($s, '[');
};

$totalHits = 0;
$tableHits = [];

foreach ($pColumns as [$table, $column, $idCol]) {
	$key = "{$table}.{$column}";
	$q = "SELECT `{$idCol}` AS _id, `{$column}` AS _val FROM `{$table}` WHERE CHAR_LENGTH(`{$column}`) > 0 AND ("
		. " LOCATE('<', `{$column}`) > 0 OR LOCATE('</', `{$column}`) > 0 OR LOCATE('&lt;', `{$column}`) > 0 OR"
		. " LOCATE('&gt;', `{$column}`) > 0 OR LOCATE('&#', `{$column}`) > 0 OR LOCATE('[', `{$column}`) > 0"
		. ") LIMIT 5000";
	$res = mysqli_query($db, $q);
	if ($res === false) {
		fwrite(STDERR, "[scan-content-markup] WARN SQL error {$key}: " . mysqli_error($db) . "\n");
		continue;
	}
	$n = 0;
	$shown = 0;
	while ($row = mysqli_fetch_assoc($res)) {
		$val = (string) ($row['_val'] ?? '');
		if (!$looksBad($val)) {
			continue;
		}
		$n++;
		if ($shown < $printLimit) {
			$snippet = mb_substr(preg_replace('/\s+/', ' ', $val) ?? '', 0, 120);
			echo "{$key} id={$row['_id']} sample=" . $snippet . "\n";
			$shown++;
		}
	}
	mysqli_free_result($res);
	if ($n > 0) {
		$tableHits[$key] = $n;
		$totalHits += $n;
	}
}

foreach ($tableHits as $k => $n) {
	fwrite(STDERR, "[scan-content-markup] INFO hits {$k} count={$n}\n");
}
fwrite(STDERR, "[scan-content-markup] INFO total_suspicious_rows={$totalHits}\n");

if ($scanArticleFiles) {
	$articlesDir = realpath($site . '/articles');
	$engDir = realpath($site . '/articles_eng');
	$q = 'SELECT id FROM articles WHERE temp = 0 LIMIT 20000';
	$res = mysqli_query($db, $q);
	if ($res) {
		$fileHits = 0;
		while ($row = mysqli_fetch_assoc($res)) {
			$aid = (int) $row['id'];
			foreach ([$articlesDir, $engDir] as $dir) {
				if ($dir === false) {
					continue;
				}
				$path = $dir . '/' . $aid;
				if (!is_file($path)) {
					continue;
				}
				$chunk = (string) file_get_contents($path, false, null, 0, 262144);
				if ($looksBad($chunk)) {
					echo "article_file id={$aid} dir=" . basename($dir) . " suspicious=1\n";
					$fileHits++;
					break;
				}
			}
		}
		mysqli_free_result($res);
		fwrite(STDERR, "[scan-content-markup] INFO article_files_flagged={$fileHits}\n");
	}
}

fwrite(STDERR, "[scan-content-markup] INFO end\n");
mysqli_close($db);
