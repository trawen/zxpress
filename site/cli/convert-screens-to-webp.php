#!/usr/bin/env php
<?php
/**
 * Convert legacy screenshot files (png/jpg/jpeg) to lossless WebP and set format=webp.
 *
 * Usage (inside php container):
 *   php /home/zxpress/web/zxpress.ru/public_html/cli/convert-screens-to-webp.php
 *   php .../cli/convert-screens-to-webp.php --apply
 *   php .../cli/convert-screens-to-webp.php --apply --delete-source
 *   php .../cli/convert-screens-to-webp.php --apply --limit=100
 *   php .../cli/convert-screens-to-webp.php --apply --id=3719
 *
 * Or via wrapper:
 *   ./tools/run-convert-screens-to-webp.sh --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
$deleteSource = false;
$limit = 0;
$onlyId = 0;

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--delete-source') {
		$deleteSource = true;
	} elseif ($arg === '--keep-source') {
		// Back-compat: sources are kept by default now.
		$deleteSource = false;
	} elseif (str_starts_with($arg, '--limit=')) {
		$limit = max(0, (int) substr($arg, 8));
	} elseif (str_starts_with($arg, '--id=')) {
		$onlyId = max(0, (int) substr($arg, 5));
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: php cli/convert-screens-to-webp.php [--dry-run|--apply] [--delete-source] [--limit=N] [--id=N]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/cli/convert-screens-to-webp.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/cli/convert-screens-to-webp.php';

require_once __DIR__ . '/../init.inc';
require_once __DIR__ . '/../includes/screen_images.php';

if (!isset($db) || !($db instanceof mysqli)) {
	fwrite(STDERR, "No mysqli \$db from init.inc\n");
	exit(1);
}

if (!function_exists('imagewebp')) {
	fwrite(STDERR, "imagewebp() is not available in this PHP build\n");
	exit(1);
}

$sql = 'SELECT id, format FROM screens';
$types = '';
$params = [];
$where = [];
if ($onlyId > 0) {
	$where[] = 'id=?';
	$types .= 'i';
	$params[] = $onlyId;
} else {
	$where[] = "(format IS NULL OR format='' OR LOWER(format) IN ('png','jpg','jpeg') OR format<>'webp')";
}
$sql .= ($where !== [] ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY id ASC';
if ($limit > 0) {
	$sql .= ' LIMIT ' . (int) $limit;
}

$z = $types !== ''
	? db_select($db, $sql, $types, ...$params)
	: $db->query($sql);

if (!$z) {
	fwrite(STDERR, 'Query failed: ' . $db->error . "\n");
	exit(1);
}

$ok = 0;
$skipped = 0;
$failed = 0;
$would = 0;

while ($row = (is_object($z) && method_exists($z, 'fetch_assoc') ? $z->fetch_assoc() : null)) {
	$id = (int) ($row['id'] ?? 0);
	$format = (string) ($row['format'] ?? '');
	if ($id <= 0) {
		continue;
	}

	$webpExists = is_file(screen_storage_path($id, 'webp'));
	$needsConvert = !$webpExists || !in_array(strtolower($format), ['webp'], true);

	if (!$needsConvert && $webpExists && strtolower($format) === 'webp') {
		$skipped++;
		fwrite(STDOUT, "#{$id}: already webp\n");
		continue;
	}

	if ($dryRun) {
		$would++;
		$src = '';
		foreach (array_unique(array_filter([$format, 'png', 'jpg', 'jpeg', 'webp'])) as $ext) {
			$ext = screen_normalize_format((string) $ext);
			$path = screen_storage_path($id, $ext);
			if (is_file($path)) {
				$src = $path;
				break;
			}
		}
		fwrite(STDOUT, "#{$id}: would convert format={$format} src=" . ($src !== '' ? $src : 'MISSING') . "\n");
		continue;
	}

	$result = screen_convert_existing_to_webp($id, $format !== '' ? $format : null, $deleteSource);
	if (!empty($result['ok'])) {
		db_exec($db, 'UPDATE screens SET format=? WHERE id=? LIMIT 1', 'si', 'webp', $id);
		if (!empty($result['skipped'])) {
			$skipped++;
			fwrite(STDOUT, "#{$id}: skipped (webp present), format set\n");
		} else {
			$ok++;
			fwrite(STDOUT, "#{$id}: converted → webp\n");
		}
		db_exec(
			$db,
			'UPDATE activity SET thumb_url=? WHERE object_type IN (\'screen\',\'illustration\') AND object_id=? AND (thumb_url IS NULL OR thumb_url=\'\' OR thumb_url NOT LIKE ?)',
			'sis',
			screen_public_url($id),
			$id,
			'%/' . $id . '.webp'
		);
	} else {
		$failed++;
		fwrite(STDERR, "#{$id}: FAIL " . (string) ($result['error'] ?? 'unknown') . "\n");
	}
}

fwrite(STDOUT, $dryRun
	? "Dry-run done. would={$would} skipped={$skipped}\n"
	: "Done. converted={$ok} skipped={$skipped} failed={$failed}\n"
);
exit($failed > 0 ? 2 : 0);
