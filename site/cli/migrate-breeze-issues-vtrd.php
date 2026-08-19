#!/usr/bin/env php
<?php
/**
 * Import missing Breeze issues (01–05) from vtrd.in into local DB + files storage.
 *
 * Goals:
 *  - download ZIPs only (no screenshots)
 *  - insert missing `issue` rows under press_id=33
 *  - insert `files` rows (ZIPs) under the corresponding issue
 *  - write activity events so `/ru/updates-activity` reflects the changes
 *
 * Usage:
 *   php cli/migrate-breeze-issues-vtrd.php --dry-run
 *   php cli/migrate-breeze-issues-vtrd.php --apply
 *   php cli/migrate-breeze-issues-vtrd.php --apply --force-download
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
$forceDownload = false;
$onlyIssue = 0;
$zipDir = '';

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--force-download') {
		$forceDownload = true;
	} elseif (str_starts_with($arg, '--only=')) {
		$onlyIssue = max(0, (int) substr($arg, 7));
	} elseif (str_starts_with($arg, '--zip-dir=')) {
		$zipDir = trim((string) substr($arg, 10));
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--force-download] [--only=N] [--zip-dir=PATH]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

// init.inc expects some SERVER vars; keep it simple for CLI.
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'zxpress.ru';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../init.inc';
require_once __DIR__ . '/../includes/ezine_slugs.php';

if (!isset($db) || !($db instanceof mysqli)) {
	fwrite(STDERR, "No mysqli \$db from init.inc\n");
	exit(1);
}

mysqli_set_charset($db, 'utf8mb4');

const PRESS_ID = 33; // https://zxpress.ru/ru/ezines/breeze
const ZIP_BASE_URL = 'https://vtrd.in/press/breeze';
const MAX_DOWNLOAD_BYTES = 200 * 1024 * 1024; // 200MB

function zxr_issue_title_from_no(int $issueNo): string
{
	return sprintf('%02d', $issueNo);
}

function zxr_zip_file_name(int $issueNo): string
{
	return 'BREEZE' . sprintf('%02d', $issueNo) . '.ZIP';
}

function zxr_zip_download_url(int $issueNo): string
{
	return ZIP_BASE_URL . '/BREEZE' . sprintf('%02d', $issueNo) . '.zip';
}

function zxr_download_to_file(string $url, string $tmpPath, int $maxBytes): int
{
	$local = @fopen($tmpPath, 'wb');
	if (!is_resource($local)) {
		throw new RuntimeException('Tmp write failed: ' . $tmpPath);
	}

	$bytes = 0;
	$remote = @fopen($url, 'rb');
	if (is_resource($remote)) {
		try {
			while (!feof($remote)) {
				$chunk = fread($remote, 1024 * 1024);
				if ($chunk === false) {
					break;
				}
				$bytes += strlen($chunk);
				if ($bytes > $maxBytes) {
					throw new RuntimeException('File too large: ' . $bytes . ' bytes');
				}
				$ok = fwrite($local, $chunk);
				if ($ok === false) {
					throw new RuntimeException('Write failed: ' . $tmpPath);
				}
			}
		} finally {
			@fclose($remote);
			@fclose($local);
		}

		return $bytes;
	}

	@fclose($local);
	@unlink($tmpPath);

	if (!function_exists('curl_init')) {
		throw new RuntimeException('Download failed: ' . $url);
	}

	$fp = @fopen($tmpPath, 'wb');
	if (!is_resource($fp)) {
		throw new RuntimeException('Tmp write failed: ' . $tmpPath);
	}

	$ch = curl_init($url);
	if ($ch === false) {
		@fclose($fp);
		throw new RuntimeException('curl_init failed');
	}

	curl_setopt_array($ch, [
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_FAILONERROR => true,
		CURLOPT_FILE => $fp,
	]);

	$ok = curl_exec($ch);
	$err = $ok === false ? (string) curl_error($ch) : '';
	curl_close($ch);
	@fclose($fp);

	if ($ok === false) {
		@unlink($tmpPath);
		throw new RuntimeException('Download failed: ' . $url . ($err !== '' ? (' (' . $err . ')') : ''));
	}

	$bytes = (int) (is_file($tmpPath) ? filesize($tmpPath) : 0);
	if ($bytes <= 0) {
		@unlink($tmpPath);
		throw new RuntimeException('Downloaded file missing: ' . $tmpPath);
	}
	if ($bytes > $maxBytes) {
		@unlink($tmpPath);
		throw new RuntimeException('File too large: ' . $bytes . ' bytes');
	}

	return $bytes;
}

function zxr_ensure_issue(mysqli $db, int $pressId, int $issueNo): array
{
	$issueTitle = zxr_issue_title_from_no($issueNo);

	$issueId = 0;
	$z = db_select($db, 'SELECT id FROM issue WHERE id_press=? AND title=? LIMIT 1', 'is', $pressId, $issueTitle);
	if ($z && ($row = $z->fetch_assoc())) {
		return ['issueId' => (int) ($row['id'] ?? 0), 'issueTitle' => $issueTitle, 'created' => false];
	}

	// sort_order convention for numeric issues: issueNo*10 + 10 (existing 06=>70, 09=>100, 10=>110).
	$sortOrder = max(0, $issueNo * 10 + 10);
	$issueDate = 0;
	$views = 0;

	$stmt = $db->prepare(
		'INSERT INTO issue (`id`, `id_press`, `title`, `date`, `sort_order`, `views`) VALUES (NULL, ?, ?, ?, ?, ?)'
	);
	if (!$stmt) {
		throw new RuntimeException('Prepare INSERT issue failed: ' . $db->error);
	}
	$stmt->bind_param('isiii', $pressId, $issueTitle, $issueDate, $sortOrder, $views);
	if (!$stmt->execute()) {
		$err = $stmt->error;
		$stmt->close();
		throw new RuntimeException('INSERT issue failed: ' . $err);
	}
	$issueId = (int) $db->insert_id;
	$stmt->close();

	if ($issueId <= 0) {
		throw new RuntimeException('INSERT issue id=0');
	}

	$issueRow = ['id' => $issueId, 'id_press' => $pressId, 'title' => $issueTitle];
	$slugs = per_admin_resolve_slugs(
		$db,
		'issue',
		'',
		'',
		static fn () => ezn_default_issue_ru($issueRow),
		static fn () => ezn_default_issue_en($issueRow),
		$issueId,
		'id_press',
		$pressId
	);
	$stmtSlug = $db->prepare('UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
	if ($stmtSlug) {
		$stmtSlug->bind_param('ssi', $slugs['slug_ru'], $slugs['slug_en'], $issueId);
		$stmtSlug->execute();
		$stmtSlug->close();
	}

	return ['issueId' => $issueId, 'issueTitle' => $issueTitle, 'created' => true];
}

function zxr_ensure_file(mysqli $db, int $pressId, int $issueId, int $issueNo, bool $forceDownload, string $zipDir): array
{
	$fileName = zxr_zip_file_name($issueNo);

	$existingId = 0;
	$z = db_select(
		$db,
		'SELECT id, size FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
		'iis',
		$pressId,
		$issueId,
		$fileName
	);
	if ($z && ($row = $z->fetch_assoc())) {
		$existingId = (int) ($row['id'] ?? 0);
		$diskPath = zx_storage_path('files', $fileName);
		$diskOk = $diskPath !== '' && is_file($diskPath);

		if ($diskOk && !$forceDownload) {
			return ['fileId' => $existingId, 'fileName' => $fileName, 'created' => false];
		}
	}

	if ($zipDir !== '') {
		$srcLower = rtrim($zipDir, '/')
			. '/BREEZE' . sprintf('%02d', $issueNo) . '.zip';
		$srcUpper = rtrim($zipDir, '/')
			. '/BREEZE' . sprintf('%02d', $issueNo) . '.ZIP';

		$srcPath = is_file($srcLower) ? $srcLower : (is_file($srcUpper) ? $srcUpper : '');
		if ($srcPath === '') {
			throw new RuntimeException('Zip file not found in --zip-dir: ' . $fileName);
		}

		$bytes = (int) filesize($srcPath);
		$sizeKb = (int) ceil($bytes / 1000);

		if (!zx_storage_copy_uploaded_file('files', $fileName, $srcPath)) {
			throw new RuntimeException('Failed to copy into storage from zip-dir: ' . $fileName);
		}
	} else {
		$tmpPath = zx_storage_path('tmp', 'breeze_' . $fileName . '_' . time());
		$bytes = zxr_download_to_file(zxr_zip_download_url($issueNo), $tmpPath, MAX_DOWNLOAD_BYTES);
		if (!is_file($tmpPath) || $bytes <= 0) {
			throw new RuntimeException('Downloaded file missing: ' . $fileName);
		}

		$sizeKb = (int) ceil($bytes / 1000);

		if (!zx_storage_copy_uploaded_file('files', $fileName, $tmpPath)) {
			@unlink($tmpPath);
			throw new RuntimeException('Failed to copy into storage: ' . $fileName);
		}
		@unlink($tmpPath);
	}

	if ($existingId > 0) {
		$tm = time();
		$type = 0;
		$fileTitle = '';
		db_exec(
			$db,
			'UPDATE files SET type=?, file_title=?, date=?, size=?, `delete`=0 WHERE id=? AND id_press=? AND id_issue=? AND name=? LIMIT 1',
			'isiiiiis',
			$type,
			$fileTitle,
			$tm,
			$sizeKb,
			$existingId,
			$pressId,
			$issueId,
			$fileName
		);
		return ['fileId' => $existingId, 'fileName' => $fileName, 'created' => false];
	}

	$stmtIns = $db->prepare(
		'INSERT INTO files (`id`, `id_issue`, `id_press`, `date`, `name`, `type`, `file_title`, `size`, `downloads`, `delete`, `file_comment`) '
		. 'VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, 0, \'\')'
	);
	if (!$stmtIns) {
		throw new RuntimeException('Prepare INSERT files failed: ' . $db->error);
	}

	$type = 0; // zip
	$fileTitle = '';
	$tm = time();
	$stmtIns->bind_param('iiisisi', $issueId, $pressId, $tm, $fileName, $type, $fileTitle, $sizeKb);
	if (!$stmtIns->execute()) {
		$err = $stmtIns->error;
		$stmtIns->close();
		throw new RuntimeException('INSERT files failed: ' . $err);
	}
	$fileId = (int) $db->insert_id;
	$stmtIns->close();

	if ($fileId <= 0) {
		throw new RuntimeException('INSERT files id=0 for ' . $fileName);
	}

	return ['fileId' => $fileId, 'fileName' => $fileName, 'created' => true];
}

function zxr_activity_for_issue(
	mysqli $db,
	int $pressId,
	int $issueId,
	string $issueTitle,
	int $fileId,
	string $fileName,
	bool $issueLog,
	bool $fileCreated
): void {
	if (!$issueLog && !$fileCreated) {
		return;
	}

	$batchId = activity_batch_begin($db, 'ezine', 'issue', $issueId, 'migrate-breeze-issues-vtrd.php');

	if ($issueLog) {
		activity_log($db, [
			'verb' => 'created',
			'object_type' => 'issue',
			'object_id' => $issueId,
			'parent_type' => 'press',
			'parent_id' => $pressId,
			'action' => 'issue.created',
			'event_scope' => ACTIVITY_SCOPE_CONTENT,
			'is_public' => 1,
			'title_ru' => $issueTitle,
			'title_en' => $issueTitle,
			'url_ru' => '/issue.php?id=' . $issueId,
		]);
	}

	if ($fileCreated) {
		activity_log($db, [
			'verb' => 'uploaded',
			'object_type' => 'file',
			'object_id' => $fileId,
			'parent_type' => 'issue',
			'parent_id' => $issueId,
			'action' => 'file.uploaded',
			'event_scope' => ACTIVITY_SCOPE_CONTENT,
			'is_public' => 1,
			'title_ru' => $fileName,
			'title_en' => $fileName,
		]);
	}

	activity_batch_finalize($db, $batchId);
}

$MIGRATION_SOURCE = 'migrate-breeze-issues-vtrd.php';

function zxr_has_migration_activity_for_issue(mysqli $db, int $issueId, string $source): bool
{
	$z = db_select(
		$db,
		'SELECT id FROM activity_batch WHERE root_type=\'issue\' AND root_id=? AND source=? LIMIT 1',
		'is',
		$issueId,
		$source
	);

	return (bool) ($z && $z->fetch_assoc());
}

$issueNos = [1, 2, 3, 4, 5];
$issueNos = ($onlyIssue > 0 && in_array($onlyIssue, $issueNos, true)) ? [$onlyIssue] : $issueNos;

$press = db_select($db, 'SELECT id, title FROM press WHERE id=? LIMIT 1', 'i', PRESS_ID);
if (!$press || !($p = $press->fetch_assoc())) {
	throw new RuntimeException('Press id not found: ' . PRESS_ID);
}

$pressTitle = (string) ($p['title'] ?? '');
fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '[apply] ') . 'Breeze import into press="' . $pressTitle . "\"\n");

$report = [
	'issuesCreated' => 0,
	'filesCreated' => 0,
	'issuesSkipped' => 0,
	'filesSkipped' => 0,
];

foreach ($issueNos as $issueNo) {
	$issueTitle = zxr_issue_title_from_no($issueNo);
	fwrite(STDOUT, 'Issue ' . $issueTitle . ' ... ');

	$issueCreated = false;
	$issueId = 0;
	$fileCreated = false;
	$fileId = 0;
	$fileName = zxr_zip_file_name($issueNo);

	if ($dryRun) {
		$z = db_select($db, 'SELECT id FROM issue WHERE id_press=? AND title=? LIMIT 1', 'is', PRESS_ID, $issueTitle);
		if ($z && ($row = $z->fetch_assoc())) {
			$issueId = (int) ($row['id'] ?? 0);
			$issueCreated = false;
		} else {
			$issueCreated = true;
		}

		if ($issueId > 0) {
			$zf = db_select(
				$db,
				'SELECT id FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
				'iis',
				PRESS_ID,
				$issueId,
				$fileName
			);
			$fileCreated = !($zf && $zf->fetch_assoc());
		} else {
			$fileCreated = true; // issue missing => file would be missing too
		}
	} else {
		$issueInfo = zxr_ensure_issue($db, PRESS_ID, $issueNo);
		$issueId = (int) ($issueInfo['issueId'] ?? 0);
		$issueCreated = (bool) ($issueInfo['created'] ?? false);

	$fileInfo = zxr_ensure_file($db, PRESS_ID, $issueId, $issueNo, $forceDownload, $zipDir);
		$fileId = (int) ($fileInfo['fileId'] ?? 0);
		$fileCreated = (bool) ($fileInfo['created'] ?? false);
	}

	if ($dryRun) {
		fwrite(STDOUT, ($issueCreated ? 'would create issue + ' : ($fileCreated ? 'would upload file + ' : 'skip + ')));
		fwrite(STDOUT, ($fileCreated ? 'create activity' : 'no activity') . "\n");

		if ($issueCreated) {
			$report['issuesCreated']++;
		} else {
			$report['issuesSkipped']++;
		}
		if ($fileCreated) {
			$report['filesCreated']++;
		} else {
			$report['filesSkipped']++;
		}

		continue;
	}

	$issueLog = $issueCreated || !zxr_has_migration_activity_for_issue($db, $issueId, $MIGRATION_SOURCE);
	zxr_activity_for_issue($db, PRESS_ID, $issueId, $issueTitle, $fileId, $fileName, $issueLog, $fileCreated);

	if ($issueCreated) {
		$report['issuesCreated']++;
	} else {
		$report['issuesSkipped']++;
	}
	if ($fileCreated) {
		$report['filesCreated']++;
	} else {
		$report['filesSkipped']++;
	}

	fwrite(STDOUT, ($issueCreated || $fileCreated) ? "OK\n" : "skip\n");
}

fwrite(STDOUT, 'Summary: ' . json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

exit(0);

