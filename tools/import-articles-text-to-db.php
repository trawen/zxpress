#!/usr/bin/env php
<?php
/**
 * Import article body files into articles.text_ru / articles.text_en.
 *
 *   data/content-store/articles/{id}      → text_ru
 *   data/content-store/articles-eng/{id}  → text_en
 *
 * Usage:
 *   php tools/import-articles-text-to-db.php --dry-run
 *   php tools/import-articles-text-to-db.php --apply
 *   php tools/import-articles-text-to-db.php --apply --force
 *   php tools/import-articles-text-to-db.php --apply --limit=100
 *   php tools/import-articles-text-to-db.php --apply --id=28
 *
 * Does not delete files. Leaves text_type unchanged (0 = file).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
$force = false;
$limit = 0;
$onlyId = 0;

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--force') {
		$force = true;
	} elseif (str_starts_with($arg, '--limit=')) {
		$limit = max(0, (int) substr($arg, 8));
	} elseif (str_starts_with($arg, '--id=')) {
		$onlyId = max(0, (int) substr($arg, 5));
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--force] [--limit=N] [--id=N]\n");
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
	: dirname(__DIR__) . '/site';

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';

/** @var mysqli $db */
if (!isset($db) || !($db instanceof mysqli)) {
	fwrite(STDERR, "No mysqli \$db from init.inc\n");
	exit(1);
}

$cols = $db->query("SHOW COLUMNS FROM articles WHERE Field IN ('text_ru','text_en')");
if (!$cols || $cols->num_rows < 2) {
	fwrite(STDERR, "ERROR: articles.text_ru / text_en missing — run db/migration/articles_text_ru_en.sql\n");
	exit(1);
}

$ruDir = rtrim(zx_storage_dir('articles'), '/');
$enDir = rtrim(zx_storage_dir('articles_eng'), '/');
if (!is_dir($ruDir)) {
	fwrite(STDERR, "ERROR: RU articles dir missing: {$ruDir}\n");
	exit(1);
}

/**
 * @return string|null null = file missing / unreadable
 */
function import_read_article_file(string $path): ?string
{
	if (!is_file($path) || !is_readable($path)) {
		return null;
	}
	$raw = file_get_contents($path);
	if ($raw === false) {
		return null;
	}
	if ($raw !== '' && !mb_check_encoding($raw, 'UTF-8')) {
		$conv = @mb_convert_encoding($raw, 'UTF-8', 'CP1251, Windows-1251, ISO-8859-1');
		if (is_string($conv)) {
			$raw = $conv;
		} else {
			$fixed = iconv('UTF-8', 'UTF-8//IGNORE', $raw);
			$raw = $fixed === false ? '' : $fixed;
		}
	}
	return $raw;
}

/** @return list<int> */
function import_collect_ids(string $ruDir, string $enDir): array
{
	$ids = [];
	foreach ([$ruDir, $enDir] as $dir) {
		if (!is_dir($dir)) {
			continue;
		}
		$dh = opendir($dir);
		if ($dh === false) {
			continue;
		}
		while (($name = readdir($dh)) !== false) {
			if (!ctype_digit($name)) {
				continue;
			}
			if (is_file($dir . '/' . $name)) {
				$ids[(int) $name] = true;
			}
		}
		closedir($dh);
	}
	$list = array_keys($ids);
	sort($list, SORT_NUMERIC);
	return $list;
}

$ids = import_collect_ids($ruDir, $enDir);
if ($onlyId > 0) {
	$ids = in_array($onlyId, $ids, true) ? [$onlyId] : [];
}
if ($limit > 0) {
	$ids = array_slice($ids, 0, $limit);
}

$total = count($ids);
fwrite(STDOUT, sprintf(
	"mode=%s force=%s ids=%d ru=%s en=%s\n",
	$dryRun ? 'dry-run' : 'apply',
	$force ? 'yes' : 'no',
	$total,
	$ruDir,
	$enDir
));

$sel = $db->prepare(
	'SELECT id, text_ru, text_en FROM articles WHERE id=? LIMIT 1'
);
if (!$sel) {
	fwrite(STDERR, "prepare SELECT failed: {$db->error}\n");
	exit(1);
}

$upd = null;
if (!$dryRun) {
	$upd = $db->prepare('UPDATE articles SET text_ru=?, text_en=? WHERE id=? LIMIT 1');
	if (!$upd) {
		fwrite(STDERR, "prepare UPDATE failed: {$db->error}\n");
		exit(1);
	}
	$db->begin_transaction();
}

$stats = [
	'updated' => 0,
	'skipped_exists' => 0,
	'skipped_no_row' => 0,
	'skipped_no_files' => 0,
	'missing_ru_file' => 0,
	'missing_en_file' => 0,
	'write_fail' => 0,
	'bytes_ru' => 0,
	'bytes_en' => 0,
];

$done = 0;
foreach ($ids as $id) {
	$done++;

	$ruFile = import_read_article_file($ruDir . '/' . $id);
	$enFile = import_read_article_file($enDir . '/' . $id);
	if ($ruFile === null) {
		$stats['missing_ru_file']++;
	}
	if ($enFile === null) {
		$stats['missing_en_file']++;
	}
	if ($ruFile === null && $enFile === null) {
		$stats['skipped_no_files']++;
		continue;
	}

	$sel->bind_param('i', $id);
	$sel->execute();
	$res = $sel->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	if ($res) {
		$res->free();
	}
	if (!is_array($row)) {
		$stats['skipped_no_row']++;
		continue;
	}

	$curRu = $row['text_ru'];
	$curEn = $row['text_en'];
	$hasRu = is_string($curRu) && $curRu !== '';
	$hasEn = is_string($curEn) && $curEn !== '';

	if (!$force && $hasRu && $hasEn) {
		$stats['skipped_exists']++;
		continue;
	}

	// Fill only missing sides unless --force (then overwrite from files that exist).
	if ($force) {
		$textRu = $ruFile !== null ? $ruFile : $curRu;
		$textEn = $enFile !== null ? $enFile : $curEn;
	} else {
		$textRu = $hasRu ? $curRu : $ruFile;
		$textEn = $hasEn ? $curEn : $enFile;
	}

	if ($ruFile !== null) {
		$stats['bytes_ru'] += strlen($ruFile);
	}
	if ($enFile !== null) {
		$stats['bytes_en'] += strlen($enFile);
	}

	if ($dryRun) {
		$stats['updated']++;
	} else {
		$upd->bind_param('ssi', $textRu, $textEn, $id);
		if (!$upd->execute()) {
			$stats['write_fail']++;
			fwrite(STDERR, "write_fail id={$id}: {$upd->error}\n");
		} else {
			$stats['updated']++;
		}
		if ($stats['updated'] > 0 && $stats['updated'] % 200 === 0) {
			$db->commit();
			$db->begin_transaction();
			fwrite(STDOUT, sprintf(
				"progress %d/%d updated=%d skipped_exists=%d\n",
				$done,
				$total,
				$stats['updated'],
				$stats['skipped_exists']
			));
		}
	}

	if ($dryRun && $done % 2000 === 0) {
		fwrite(STDOUT, "scanned {$done}/{$total}\n");
	}
}

if (!$dryRun) {
	$db->commit();
}

fwrite(STDOUT, 'stats ' . json_encode($stats, JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, $dryRun ? "dry-run done (no writes)\n" : "apply done\n");
exit($stats['write_fail'] > 0 ? 1 : 0);
