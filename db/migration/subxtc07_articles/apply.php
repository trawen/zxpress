#!/usr/bin/env php
<?php
/**
 * Thin wrapper → site/cli/import-subxtc07-articles.php
 *
 * Defaults: press_id=213, issue_id=2665 (Subliminal Extacy #07).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__, 3);
$candidates = [
    $root . '/site/cli/import-subxtc07-articles.php',
    '/home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc07-articles.php',
];
$cli = null;
foreach ($candidates as $cand) {
    if (is_file($cand)) {
        $cli = $cand;
        break;
    }
}
if ($cli === null) {
    fwrite(STDERR, "Cannot find import-subxtc07-articles.php\n");
    exit(1);
}

$args = array_slice($argv, 1);
if ($args === []) {
    $args = ['--dry-run'];
}

passthru(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cli) . ' ' . implode(' ', array_map('escapeshellarg', $args)),
    $code
);
exit($code);
