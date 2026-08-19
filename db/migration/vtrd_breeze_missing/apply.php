#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$root = dirname(__DIR__, 3);
require_once $root . '/site/init.inc';
require_once $root . '/site/includes/activity.php';
require_once $root . '/site/includes/ezine_slugs.php';
require_once $root . '/site/includes/storage_paths.php';

const BREEZE_PRESS_SLUG = 'breeze';
const BREEZE_SOURCE_PREFIX = 'migration:vtrd_breeze_missing';

$downloadDir = getenv('ZXPRESS_VTRD_BREEZE_DIR');
if (!is_string($downloadDir) || trim($downloadDir) === '') {
	$downloadDir = $root . '/local/work/vtrd-breeze';
}
$downloadDir = rtrim($downloadDir, '/');

$items = [
	['title' => '01', 'sort_order' => 10, 'file_name' => 'BREEZE01.ZIP', 'url' => 'https://vtrd.in/press/breeze/BREEZE01.zip'],
	['title' => '02', 'sort_order' => 20, 'file_name' => 'BREEZE02.ZIP', 'url' => 'https://vtrd.in/press/breeze/BREEZE02.zip'],
	['title' => '03', 'sort_order' => 30, 'file_name' => 'BREEZE03.ZIP', 'url' => 'https://vtrd.in/press/breeze/BREEZE03.zip'],
	['title' => '04', 'sort_order' => 40, 'file_name' => 'BREEZE04.ZIP', 'url' => 'https://vtrd.in/press/breeze/BREEZE04.zip'],
	['title' => '05', 'sort_order' => 50, 'file_name' => 'BREEZE05.ZIP', 'url' => 'https://vtrd.in/press/breeze/BREEZE05.zip'],
];

/**
 * @return array{id:int,title:string,slug_ru:string,slug_en:string,numbers:int}|null
 */
function breeze_find_press(mysqli $db): ?array
{
	$z = db_select($db, 'SELECT id, title, slug_ru, slug_en, numbers FROM press WHERE slug_ru=? LIMIT 1', 's', BREEZE_PRESS_SLUG);
	$row = $z ? $z->fetch_assoc() : null;
	if (!$row) {
		return null;
	}

	return [
		'id' => (int) ($row['id'] ?? 0),
		'title' => (string) ($row['title'] ?? ''),
		'slug_ru' => (string) ($row['slug_ru'] ?? ''),
		'slug_en' => (string) ($row['slug_en'] ?? ''),
		'numbers' => (int) ($row['numbers'] ?? 0),
	];
}

/**
 * @return array{id:int,title:string,slug_ru:string,slug_en:string,sort_order:int,date:int}|null
 */
function breeze_find_issue(mysqli $db, int $pressId, string $title): ?array
{
	$z = db_select(
		$db,
		'SELECT id, title, slug_ru, slug_en, sort_order, date FROM issue WHERE id_press=? AND title=? LIMIT 1',
		'is',
		$pressId,
		$title
	);
	$row = $z ? $z->fetch_assoc() : null;
	if (!$row) {
		return null;
	}

	return [
		'id' => (int) ($row['id'] ?? 0),
		'title' => (string) ($row['title'] ?? ''),
		'slug_ru' => (string) ($row['slug_ru'] ?? ''),
		'slug_en' => (string) ($row['slug_en'] ?? ''),
		'sort_order' => (int) ($row['sort_order'] ?? 0),
		'date' => (int) ($row['date'] ?? 0),
	];
}

function breeze_file_row_id(mysqli $db, int $pressId, int $issueId, string $fileName): int
{
	$z = db_select(
		$db,
		'SELECT id FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
		'iis',
		$pressId,
		$issueId,
		$fileName
	);
	$row = $z ? $z->fetch_assoc() : null;
	return (int) ($row['id'] ?? 0);
}

function breeze_download_file(string $url, string $destPath): void
{
	$dir = dirname($destPath);
	if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
		throw new RuntimeException('mkdir failed: ' . $dir);
	}
	if (is_file($destPath) && filesize($destPath) > 1024) {
		return;
	}

	$ctx = stream_context_create([
		'http' => [
			'timeout' => 90,
			'follow_location' => 1,
			'max_redirects' => 5,
			'header' => "User-Agent: zxpress-migration/1.0\r\n",
		],
		'https' => [
			'timeout' => 90,
			'follow_location' => 1,
			'max_redirects' => 5,
			'header' => "User-Agent: zxpress-migration/1.0\r\n",
		],
	]);

	$data = @file_get_contents($url, false, $ctx);
	if (!is_string($data) || strlen($data) < 1024) {
		throw new RuntimeException('download failed or file too small: ' . $url);
	}
	if (@file_put_contents($destPath, $data) === false) {
		throw new RuntimeException('write failed: ' . $destPath);
	}
}

function breeze_copy_to_storage(string $srcPath, string $fileName): void
{
	if (!is_file($srcPath)) {
		throw new RuntimeException('missing source file: ' . $srcPath);
	}
	if (!zx_storage_copy_uploaded_file('files', $fileName, $srcPath)) {
		throw new RuntimeException('storage copy failed for ' . $fileName);
	}
}

/**
 * @return array{id:int,created:bool}
 */
function breeze_ensure_issue(mysqli $db, int $pressId, string $title, int $sortOrder): array
{
	$existing = breeze_find_issue($db, $pressId, $title);
	if ($existing) {
		return ['id' => $existing['id'], 'created' => false];
	}

	$stmt = $db->prepare('INSERT INTO issue (`id`, `id_press`, `title`, `date`, `sort_order`, `views`) VALUES (NULL, ?, ?, 0, ?, 0)');
	if (!$stmt) {
		throw new RuntimeException('prepare insert issue failed');
	}
	$stmt->bind_param('isi', $pressId, $title, $sortOrder);
	if (!$stmt->execute()) {
		$stmt->close();
		throw new RuntimeException('insert issue failed: ' . $stmt->error);
	}
	$stmt->close();

	$issueId = (int) $db->insert_id;
	if ($issueId <= 0) {
		throw new RuntimeException('issue insert returned invalid id');
	}

	$issueRow = ['id' => $issueId, 'id_press' => $pressId, 'title' => $title];
	$slugs = per_admin_resolve_slugs(
		$db,
		'issue',
		'',
		'',
		static fn (): string => ezn_default_issue_ru($issueRow),
		static fn (): string => ezn_default_issue_en($issueRow),
		$issueId,
		'id_press',
		$pressId
	);
	db_exec($db, 'UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1', 'ssi', $slugs['slug_ru'], $slugs['slug_en'], $issueId);

	return ['id' => $issueId, 'created' => true];
}

/**
 * @return array{id:int,created:bool}
 */
function breeze_ensure_file(mysqli $db, int $pressId, int $issueId, string $fileName, string $srcPath): array
{
	$fileId = breeze_file_row_id($db, $pressId, $issueId, $fileName);
	if ($fileId > 0) {
		$storagePath = zx_storage_path('files', $fileName);
		if (!is_file($storagePath) || filesize($storagePath) < 1024) {
			breeze_copy_to_storage($srcPath, $fileName);
		}
		return ['id' => $fileId, 'created' => false];
	}

	$sizeKb = max(1, (int) ceil(filesize($srcPath) / 1000));
	$now = time();
	$stmt = $db->prepare(
		'INSERT INTO files (`id`, `id_issue`, `id_press`, `date`, `name`, `type`, `file_title`, `size`, `downloads`, `delete`, `file_comment`) '
		. "VALUES (NULL, ?, ?, ?, ?, 0, '', ?, 0, 0, '')"
	);
	if (!$stmt) {
		throw new RuntimeException('prepare insert file failed');
	}
	$stmt->bind_param('iiisi', $issueId, $pressId, $now, $fileName, $sizeKb);
	if (!$stmt->execute()) {
		$stmt->close();
		throw new RuntimeException('insert file failed: ' . $stmt->error);
	}
	$stmt->close();

	$fileId = (int) $db->insert_id;
	if ($fileId <= 0) {
		throw new RuntimeException('file insert returned invalid id');
	}

	try {
		breeze_copy_to_storage($srcPath, $fileName);
	} catch (Throwable $e) {
		db_exec($db, 'DELETE FROM files WHERE id=? LIMIT 1', 'i', $fileId);
		throw $e;
	}

	return ['id' => $fileId, 'created' => true];
}

function breeze_refresh_press_numbers(mysqli $db, int $pressId): void
{
	db_exec(
		$db,
		'UPDATE press p SET p.numbers=(SELECT COUNT(*) FROM issue i WHERE i.id_press=p.id) WHERE p.id=? LIMIT 1',
		'i',
		$pressId
	);
}

$press = breeze_find_press($db);
if (!$press || $press['id'] <= 0) {
	fwrite(STDERR, "Press 'breeze' not found\n");
	exit(1);
}

$createdIssues = 0;
$createdFiles = 0;
$downloaded = 0;

foreach ($items as $item) {
	$title = (string) $item['title'];
	$fileName = (string) $item['file_name'];
	$url = (string) $item['url'];
	$sortOrder = (int) $item['sort_order'];
	$source = BREEZE_SOURCE_PREFIX . ':' . $title;
	$downloadPath = $downloadDir . '/' . $fileName;

	$fileExistsBefore = is_file($downloadPath) && filesize($downloadPath) > 1024;
	if (!$fileExistsBefore) {
		breeze_download_file($url, $downloadPath);
		$downloaded++;
	}

	$issue = breeze_ensure_issue($db, $press['id'], $title, $sortOrder);
	$file = breeze_ensure_file($db, $press['id'], $issue['id'], $fileName, $downloadPath);

	if (!$issue['created'] && !$file['created']) {
		continue;
	}

	$batchId = activity_batch_begin($db, 'ezine', 'issue', $issue['id'], $source, 0);
	$createdAt = time();
	$issueUrl = '/issue.php?id=' . $issue['id'];

	if ($issue['created']) {
		activity_log($db, [
			'batch_id' => $batchId,
			'created_at' => $createdAt,
			'actor_user_id' => 0,
			'verb' => 'created',
			'object_type' => 'issue',
			'object_id' => $issue['id'],
			'parent_type' => 'press',
			'parent_id' => $press['id'],
			'action' => 'issue.created',
			'event_scope' => ACTIVITY_SCOPE_CONTENT,
			'is_public' => 1,
			'title_ru' => $title,
			'title_en' => $title,
			'url_ru' => $issueUrl,
			'url_en' => $issueUrl,
			'meta' => ['source' => $source, 'file_name' => $fileName],
		]);
		$createdIssues++;
	}

	if ($file['created']) {
		activity_log($db, [
			'batch_id' => $batchId,
			'created_at' => $createdAt,
			'actor_user_id' => 0,
			'verb' => 'uploaded',
			'object_type' => 'file',
			'object_id' => $file['id'],
			'parent_type' => 'issue',
			'parent_id' => $issue['id'],
			'action' => 'file.uploaded',
			'event_scope' => ACTIVITY_SCOPE_CONTENT,
			'is_public' => 1,
			'title_ru' => $fileName,
			'title_en' => $fileName,
			'url_ru' => $issueUrl,
			'url_en' => $issueUrl,
			'meta' => ['source' => $source, 'file_name' => $fileName, 'issue_title' => $title, 'id_press' => $press['id'], 'id_issue' => $issue['id']],
		]);
		$createdFiles++;
	}

	activity_batch_finalize($db, $batchId);
}

breeze_refresh_press_numbers($db, $press['id']);

fwrite(
	STDOUT,
	sprintf(
		"Breeze migration done: downloaded=%d created_issues=%d created_files=%d dir=%s\n",
		$downloaded,
		$createdIssues,
		$createdFiles,
		$downloadDir
	)
);
