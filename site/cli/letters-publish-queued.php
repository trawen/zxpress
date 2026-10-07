#!/usr/bin/env php
<?php
/**
 * Publish at most one queued letter (Europe/Moscow calendar day).
 * Safe to run from cron every hour — no-ops if already published today or queue empty.
 *
 * Usage:
 *   docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/letters-publish-queued.php
 */

declare(strict_types=1);

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/cli/letters-publish-queued.php';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'cli';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'zxpress-cli-letters-publish';

require dirname(__DIR__) . '/init.inc';
require_once dirname(__DIR__) . '/includes/letters_publish.php';

$reason = null;
$id = letters_maybe_publish_next($db, $reason);

if ($id) {
	fwrite(STDOUT, "published id={$id} reason={$reason}\n");
	exit(0);
}

fwrite(STDOUT, "noop reason=" . ($reason ?? 'unknown') . "\n");
exit(0);
