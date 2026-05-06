<?php
/**
 * CLI: rebuild zxpress_dinamic.png from the calendar table.
 * Used by Docker entrypoint after DB is up (minimal bootstrap — correct exit codes).
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit(1);
}

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "[chronology] DB_PASS empty — set DB_PASS or ALLOW_EMPTY_DB_PASSWORD=1\n");
	exit(1);
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/storage_paths.php';
require_once __DIR__ . '/includes/chronology_graph.php';

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, '[chronology] DB connection failed: ' . mysqli_connect_error() . "\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

// Optional argv[1]: absolute path for PNG.
// Default must always point to runtime data/cache location, not legacy generated/.
$out = isset($argv[1]) && $argv[1] !== '' ? $argv[1] : zx_storage_path('chronology_png');
$outDir = dirname($out);
if (!is_dir($outDir)) {
	if (@mkdir($outDir, 0775, true)) {
		error_log('[FIX] chronology created output directory: ' . $outDir);
	} else {
		fwrite(STDERR, "[FIX] chronology failed to create output directory: {$outDir}\n");
		exit(1);
	}
}
error_log('[FIX] chronology output path: ' . $out);
if (generate_chronology_graph_png($db, $out)) {
	fwrite(STDERR, "[chronology] wrote {$out}\n");
	mysqli_close($db);
	exit(0);
}

mysqli_close($db);
fwrite(STDERR, "[chronology] failed to write {$out}\n");
exit(1);
