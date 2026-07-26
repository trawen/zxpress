#!/usr/bin/env php
<?php
/**
 * Generate WebP letter previews at 256px width (quality 80).
 * Output: data/content-store/letters/preview-256/{id}.webp
 *
 * Usage:
 *   ./tools/run-generate-letter-previews-256.sh
 *   ./tools/run-generate-letter-previews-256.sh --force
 */

declare(strict_types=1);

// Prefer repo data/ when running from bind-mounted site tree.
if (getenv('ZXPRESS_DATA_ROOT') === false || getenv('ZXPRESS_DATA_ROOT') === '') {
	$guess = dirname(__DIR__, 2) . '/data';
	if (is_dir($guess . '/content-store/letters')) {
		putenv('ZXPRESS_DATA_ROOT=' . $guess);
	}
}

require_once dirname(__DIR__) . '/includes/storage_paths.php';
require_once dirname(__DIR__) . '/includes/letters_images.php';

$force = in_array('--force', $argv, true);
$lettersDir = zx_storage_path('letters', '');
$lettersDir = rtrim($lettersDir, '/');
$outDir = zx_storage_path('letters_preview_256', '');
$outDir = rtrim($outDir, '/');

if (!is_dir($lettersDir)) {
	fwrite(STDERR, "Letters dir missing: {$lettersDir}\n");
	exit(1);
}

if (!is_dir($outDir) && !@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
	fwrite(STDERR, "Cannot create output dir: {$outDir}\n");
	exit(1);
}

$files = glob($lettersDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [];
sort($files, SORT_NATURAL);

$ok = 0;
$skip = 0;
$fail = 0;

foreach ($files as $src) {
	$base = basename($src);
	if (!preg_match('/^(\d+)\.(jpe?g|png|webp|gif)$/i', $base, $m)) {
		continue;
	}
	$imageId = (int) $m[1];
	if ($imageId <= 0) {
		continue;
	}

	$dst = letters_preview_256_path($imageId);
	if (!$force && is_file($dst) && filesize($dst) > 0) {
		$skip++;
		fwrite(STDOUT, "#{$imageId}: skip (exists)\n");
		continue;
	}

	if (!letters_make_preview_256($src, $imageId)) {
		$fail++;
		fwrite(STDERR, "#{$imageId}: FAIL {$base}\n");
		continue;
	}

	$ok++;
	$size = filesize($dst) ?: 0;
	fwrite(STDOUT, "#{$imageId}: ok → preview-256/{$imageId}.webp ({$size} bytes)\n");
}

fwrite(STDOUT, "Done. created={$ok} skipped={$skip} failed={$fail} out={$outDir}\n");
exit($fail > 0 ? 2 : 0);
