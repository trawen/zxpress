<?php
/**
 * Local stand-in for turbo80.com/api/fetch.php — PlusD/G+DOS system tape.
 * Turbo80 requests relative api/fetch.php?d=<id> from the admin host page;
 * admin_issue_emulator_t80.js rewrites that to /t80/api/fetch.php.
 */
declare(strict_types=1);

header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

$id = preg_replace('/[^0-9]/', '', (string) ($_GET['d'] ?? '')) ?? '';
if ($id === '') {
	http_response_code(400);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'missing d';
	exit;
}

$path = __DIR__ . '/systems/' . $id . '.bin';
if (!is_file($path)) {
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'unknown system id';
	exit;
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
