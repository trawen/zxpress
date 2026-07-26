#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$siteRoot = is_dir('/src-site') ? '/src-site' : (dirname(__DIR__) . '/site');
require_once $siteRoot . '/includes/zxnet_slugs.php';

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'zxpress_u');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'zxpress_db');

if (DB_PASS === '' && getenv('ALLOW_EMPTY_DB_PASSWORD') !== '1') {
	fwrite(STDERR, "DB_PASS empty\n");
	exit(1);
}

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
	fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . "\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

$rs = $db->query(
	'SELECT id, echo_id, title, title_en, slug_ru, slug_en FROM echos_subjs2 '
	. "WHERE slug_ru IS NULL OR slug_ru='' OR slug_en IS NULL OR slug_en='' "
	. 'ORDER BY id ASC'
);
if (!$rs) {
	throw new RuntimeException('echos_subjs2 query failed: ' . $db->error);
}

$upd = $db->prepare('UPDATE echos_subjs2 SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
if (!$upd) {
	throw new RuntimeException('UPDATE prepare failed: ' . $db->error);
}

$updated = 0;
while ($row = $rs->fetch_assoc()) {
	$id = (int) ($row['id'] ?? 0);
	$echoId = (int) ($row['echo_id'] ?? 0);
	if ($id <= 0 || $echoId <= 0) {
		continue;
	}

	$slugs = zxnet_resolve_slugs(
		$db,
		$echoId,
		(string) ($row['title'] ?? ''),
		(string) ($row['title_en'] ?? ''),
		$id
	);

	if (trim((string) ($row['slug_ru'] ?? '')) !== '') {
		$slugs['slug_ru'] = (string) $row['slug_ru'];
	}
	if (trim((string) ($row['slug_en'] ?? '')) !== '') {
		$slugs['slug_en'] = (string) $row['slug_en'];
	}

	$upd->bind_param('ssi', $slugs['slug_ru'], $slugs['slug_en'], $id);
	if (!$upd->execute()) {
		throw new RuntimeException('UPDATE failed for #' . $id . ': ' . $upd->error);
	}
	$updated++;
	if ($updated % 500 === 0) {
		fwrite(STDERR, "updated {$updated}...\n");
	}
}
$upd->close();
$rs->free();

fwrite(STDOUT, "Updated {$updated} topic(s).\n");
