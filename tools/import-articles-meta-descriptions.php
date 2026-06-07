#!/usr/bin/env php
<?php
/**
 * Import seo-ru / seo-en from description files into articles.meta_description_*.
 *
 * Each file should contain lines like:
 *   seo-ru: Russian meta description
 *   seo-en: English meta description
 *
 * Article id is taken from the filename (leading digits), e.g. 12345.txt or 12345-title.txt
 *
 * SEO files live on your Mac; the database is on the server. Typical flow:
 *
 *   bash tools/import-articles-meta-from-local.sh --dry-run   # Mac: rsync + import on server
 *   bash tools/import-articles-meta-from-local.sh --apply
 *
 * Requires: export ZXPRESS_SSH=user@server
 *
 * Or manually: rsync descriptions to server data/import/article-descriptions/,
 * then on the server: bash tools/run-import-articles-meta-descriptions.sh --apply
 *
 * Apply migration first if needed: db/migration/articles_meta_description.sql
 *
 * Override server input dir: --dir=/path/on/server/descriptions
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

const META_MAX_LEN = 512;

$dryRun = true;
$descriptionsDir = getenv('DESCRIPTIONS_DIR') ?: '';

foreach ($argv as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif (str_starts_with($arg, '--dir=')) {
		$descriptionsDir = substr($arg, 6);
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: php tools/import-articles-meta-descriptions.php [--dry-run|--apply] [--dir=PATH]\n");
		exit(0);
	}
}

$root = realpath(__DIR__ . '/..') ?: '';
require_once $root . '/site/includes/functions.php';

if ($descriptionsDir === '') {
	$descriptionsDir = $root . '/data/import/article-descriptions';
}

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[import-articles-meta] WARN DB_PASS empty — set DB_PASS or ALLOW_EMPTY_DB_PASSWORD=1\n");
	exit(1);
}

if (!is_dir($descriptionsDir)) {
	fwrite(STDERR, "[import-articles-meta] ERROR descriptions dir not found: {$descriptionsDir}\n");
	exit(1);
}

$db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[import-articles-meta] ERROR DB connection failed: ' . mysqli_connect_error() . "\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

if (!articles_meta_columns_exist($db)) {
	fwrite(STDERR, "[import-articles-meta] ERROR columns meta_description_ru/en missing in articles — run db/migration/articles_meta_description.sql\n");
	exit(1);
}

fwrite(STDERR, '[import-articles-meta] INFO mode=' . ($dryRun ? 'dry-run' : 'apply') . " dir={$descriptionsDir}\n");

$files = list_description_files($descriptionsDir);
if ($files === []) {
	fwrite(STDERR, "[import-articles-meta] WARN no files in {$descriptionsDir}\n");
	exit(0);
}

$stats = [
	'processed' => 0,
	'updated' => 0,
	'skipped_no_id' => 0,
	'skipped_no_fields' => 0,
	'skipped_missing_article' => 0,
	'missing_seo_ru' => 0,
	'missing_seo_en' => 0,
];

foreach ($files as $path) {
	$basename = basename($path);
	$id = extract_article_id_from_filename($basename);
	if ($id <= 0) {
		fwrite(STDERR, "[import-articles-meta] WARN {$basename}: cannot extract article id from filename\n");
		$stats['skipped_no_id']++;
		continue;
	}

	$content = file_get_contents($path);
	if ($content === false) {
		fwrite(STDERR, "[import-articles-meta] WARN {$basename}: cannot read file\n");
		continue;
	}

	$seoRu = extract_seo_field($content, 'seo-ru');
	$seoEn = extract_seo_field($content, 'seo-en');

	$missingRu = ($seoRu === null);
	$missingEn = ($seoEn === null);

	if ($missingRu) {
		fwrite(STDERR, "[import-articles-meta] WARN {$basename} (id={$id}): seo-ru not found\n");
		$stats['missing_seo_ru']++;
	}
	if ($missingEn) {
		fwrite(STDERR, "[import-articles-meta] WARN {$basename} (id={$id}): seo-en not found\n");
		$stats['missing_seo_en']++;
	}

	if ($missingRu && $missingEn) {
		$stats['skipped_no_fields']++;
		continue;
	}

	$stats['processed']++;

	if (!article_exists($db, $id)) {
		fwrite(STDERR, "[import-articles-meta] WARN {$basename} (id={$id}): article not found in database\n");
		$stats['skipped_missing_article']++;
		continue;
	}

	$metaRu = $seoRu !== null ? normalize_meta($seoRu) : null;
	$metaEn = $seoEn !== null ? normalize_meta($seoEn) : null;

	$setParts = [];
	$params = [];
	$types = '';

	if ($metaRu !== null) {
		$setParts[] = 'meta_description_ru=?';
		$params[] = $metaRu;
		$types .= 's';
	}
	if ($metaEn !== null) {
		$setParts[] = 'meta_description_en=?';
		$params[] = $metaEn;
		$types .= 's';
	}

	$sql = 'UPDATE articles SET ' . implode(', ', $setParts) . ' WHERE id=? LIMIT 1';
	$params[] = $id;
	$types .= 'i';

	if ($dryRun) {
		fwrite(STDOUT, "[dry-run] {$basename} id={$id}"
			. ($metaRu !== null ? ' ru=' . preview_meta($metaRu) : '')
			. ($metaEn !== null ? ' en=' . preview_meta($metaEn) : '')
			. "\n");
		$stats['updated']++;
		continue;
	}

	if (!db_exec($db, $sql, $types, ...$params)) {
		fwrite(STDERR, "[import-articles-meta] ERROR {$basename} (id={$id}): update failed\n");
		continue;
	}

	fwrite(STDOUT, "[ok] {$basename} id={$id}"
		. ($metaRu !== null ? ' ru=' . strlen($metaRu) . 'b' : '')
		. ($metaEn !== null ? ' en=' . strlen($metaEn) . 'b' : '')
		. "\n");
	$stats['updated']++;
}

fwrite(STDERR, '[import-articles-meta] INFO done'
	. " files=" . count($files)
	. " processed={$stats['processed']}"
	. " updated={$stats['updated']}"
	. " missing_seo_ru={$stats['missing_seo_ru']}"
	. " missing_seo_en={$stats['missing_seo_en']}"
	. " skipped_no_id={$stats['skipped_no_id']}"
	. " skipped_no_fields={$stats['skipped_no_fields']}"
	. " skipped_missing_article={$stats['skipped_missing_article']}"
	. "\n");

exit(0);

function articles_meta_columns_exist(mysqli $db): bool
{
	foreach (['meta_description_ru', 'meta_description_en'] as $col) {
		$stmt = $db->prepare('SHOW COLUMNS FROM articles LIKE ?');
		if (!$stmt) {
			return false;
		}
		$stmt->bind_param('s', $col);
		$stmt->execute();
		$res = $stmt->get_result();
		if (!$res || $res->num_rows === 0) {
			return false;
		}
	}
	return true;
}

/** @return list<string> */
function list_description_files(string $dir): array
{
	$paths = [];
	foreach (scandir($dir) ?: [] as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $name;
		if (!is_file($path)) {
			continue;
		}
		$paths[] = $path;
	}
	sort($paths, SORT_STRING);
	return $paths;
}

function extract_article_id_from_filename(string $basename): int
{
	$name = pathinfo($basename, PATHINFO_FILENAME);
	if (preg_match('/^(\d+)/', $name, $m)) {
		return (int) $m[1];
	}
	return 0;
}

function extract_seo_field(string $content, string $key): ?string
{
	$pattern = '/^' . preg_quote($key, '/') . '\s*:\s*(.*)$/mi';
	if (!preg_match($pattern, $content, $m)) {
		return null;
	}
	$value = trim($m[1]);
	return $value === '' ? null : $value;
}

function normalize_meta(string $value): string
{
	$normalized = plain_text_normalize_for_storage($value);
	if (strlen($normalized) > META_MAX_LEN) {
		$normalized = substr($normalized, 0, META_MAX_LEN);
	}
	return $normalized;
}

function preview_meta(string $value): string
{
	$short = mb_strlen($value) > 60 ? mb_substr($value, 0, 57) . '...' : $value;
	return '"' . str_replace('"', '\\"', $short) . '"';
}

function article_exists(mysqli $db, int $id): bool
{
	$stmt = $db->prepare('SELECT 1 FROM articles WHERE id=? LIMIT 1');
	if (!$stmt) {
		return false;
	}
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$res = $stmt->get_result();
	return (bool) ($res && $res->fetch_row());
}
