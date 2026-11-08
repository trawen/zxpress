#!/usr/bin/env php
<?php
/**
 * Wrapper: import Emulate RU i18n (press 66).
 *
 *   php db/migration/emulate_articles/apply.php --dry-run
 *   php db/migration/emulate_articles/apply.php --apply
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$cli = $root . '/site/cli/import-emulate-articles.php';
if (!is_file($cli)) {
    fwrite(STDERR, "Missing {$cli}\n");
    exit(1);
}
passthru('php ' . escapeshellarg($cli) . ' ' . implode(' ', array_map('escapeshellarg', array_slice($argv, 1))));
