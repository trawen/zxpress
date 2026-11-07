#!/usr/bin/env php
<?php
/**
 * Fill screens.width / screens.height from on-disk WebP (one-off / repair).
 *
 *   php cli/backfill-screen-dimensions.php [--dry-run|--apply] [--limit=N] [--id=N]
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
		fwrite(STDOUT, "Usage: php cli/backfill-screen-dimensions.php [--dry-run|--apply] [--limit=N] [--id=N]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/cli/backfill-screen-dimensions.php';
$_SERVER['SCRIPT_NAME'] = '/cli/backfill-screen-dimensions.php';

require_once __DIR__ . '/../init.inc';
require_once __DIR__ . '/../includes/screen_images.php';

if (!isset($db) || !($db instanceof mysqli)) {
	fwrite(STDERR, "No mysqli \$db\n");
	exit(1);
}

$sql = 'SELECT id, width, height FROM screens';
$params = [];
$types = '';
$where = ['(width IS NULL OR height IS NULL OR width=0 OR height=0)'];
if ($onlyId > 0) {
	$where = ['id=?'];
	$types = 'i';
	$params[] = $onlyId;
}
$sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC';
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
$miss = 0;
while ($row = mysqli_fetch_assoc($z)) {
	$id = (int) ($row['id'] ?? 0);
	if ($id <= 0) {
		continue;
	}
	$path = screen_storage_path($id, 'webp');
	[$w, $h] = screen_probe_file_dimensions($path);
	if ($w <= 0 || $h <= 0) {
		$miss++;
		fwrite(STDERR, "#{$id}: no webp or unreadable\n");
		continue;
	}
	if ($dryRun) {
		fwrite(STDOUT, "#{$id}: would set {$w}x{$h}\n");
		$ok++;
		continue;
	}
	if (screen_set_dimensions($db, $id, $w, $h)) {
		fwrite(STDOUT, "#{$id}: {$w}x{$h}\n");
		$ok++;
	}
}

fwrite(STDOUT, ($dryRun ? 'Dry-run: ' : '') . "updated={$ok} missing_file={$miss}\n");
exit(0);
