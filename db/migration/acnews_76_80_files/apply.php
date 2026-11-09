#!/usr/bin/env php
<?php
/**
 * Attach ACNews #76–#80 download files (ZIP from vtrd.in / local).
 *
 *   php db/migration/acnews_76_80_files/apply.php --dry-run
 *   php db/migration/acnews_76_80_files/apply.php --apply
 *   ZXPRESS_ACN_FILES_DIR=/path php db/migration/acnews_76_80_files/apply.php --apply
 *
 * Expected files in source dir: ACN76.zip ACN77.zip ACN78.zip ACN79.zip ACN80.zip
 * (missing files are skipped with a warning — vtrd currently stops at #78).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$root = dirname(__DIR__, 3);
$siteRoot = $root . '/site';
if (!is_file($siteRoot . '/init.inc') && is_file('/home/zxpress/web/zxpress.ru/public_html/init.inc')) {
	$siteRoot = '/home/zxpress/web/zxpress.ru/public_html';
}

$_SERVER['REQUEST_URI'] = '/cli/acnews_76_80_files';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-acnews-files';

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';
require_once $siteRoot . '/includes/activity.php';

const ACN_PRESS_ID = 8;
const ACN_SOURCE = 'migration:acnews_76_80_files';

$dryRun = true;
$sourceDir = getenv('ZXPRESS_ACN_FILES_DIR') ?: '';
foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif (str_starts_with($arg, '--dir=')) {
		$sourceDir = rtrim(substr($arg, 6), '/');
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

if ($sourceDir === '') {
	$candidates = [
		$root . '/local/work/vtrd-acn',
		'/tmp/vtrd-acn',
		dirname($siteRoot) . '/../local/work/vtrd-acn',
	];
	foreach ($candidates as $cand) {
		if (is_dir($cand)) {
			$sourceDir = $cand;
			break;
		}
	}
}
if ($sourceDir === '' || !is_dir($sourceDir)) {
	fwrite(STDERR, "Need --dir=DIR with ACN76.zip … ACN80.zip\n");
	exit(1);
}

/** @var list<array{title:string,file:string}> */
$items = [
	['title' => '76', 'file' => 'ACN76.zip'],
	['title' => '77', 'file' => 'ACN77.zip'],
	['title' => '78', 'file' => 'ACN78.zip'],
	['title' => '79', 'file' => 'ACN79.zip'],
	['title' => '80', 'file' => 'ACN80.zip'],
];

fwrite(STDOUT, 'ACNews files: dir=' . $sourceDir . ' mode=' . ($dryRun ? 'dry-run' : 'APPLY') . "\n");

$created = 0;
$skipped = 0;
$missing = 0;

foreach ($items as $item) {
	$title = $item['title'];
	$fileName = $item['file'];
	$src = $sourceDir . '/' . $fileName;

	$zIssue = db_select(
		$db,
		'SELECT id, title FROM issue WHERE id_press=? AND title=? LIMIT 1',
		'is',
		ACN_PRESS_ID,
		$title
	);
	$issue = $zIssue ? $zIssue->fetch_assoc() : null;
	if (!$issue) {
		fwrite(STDERR, "  #{$title}: issue missing in DB — skip\n");
		$missing++;
		continue;
	}
	$issueId = (int) $issue['id'];

	if (!is_file($src) || filesize($src) < 1024) {
		fwrite(STDERR, "  #{$title}: file missing/too small — {$src}\n");
		$missing++;
		continue;
	}

	$zFile = db_select(
		$db,
		'SELECT id, size FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
		'iis',
		ACN_PRESS_ID,
		$issueId,
		$fileName
	);
	$existing = $zFile ? $zFile->fetch_assoc() : null;
	$sizeKb = max(1, (int) ceil(filesize($src) / 1000));
	$storagePath = zx_storage_path('files', $fileName);
	$storageOk = is_file($storagePath) && filesize($storagePath) >= 1024;

	fwrite(
		STDOUT,
		sprintf(
			"  #%s issue=%d %s bytes=%d size_kb=%d db=%s storage=%s\n",
			$title,
			$issueId,
			$fileName,
			filesize($src),
			$sizeKb,
			$existing ? 'exists#' . $existing['id'] : 'new',
			$storageOk ? 'ok' : 'need-copy'
		)
	);

	if ($dryRun) {
		if (!$existing) {
			$created++;
		} else {
			$skipped++;
		}
		continue;
	}

	if (!zx_storage_copy_uploaded_file('files', $fileName, $src)) {
		fwrite(STDERR, "  #{$title}: storage copy failed\n");
		continue;
	}

	if ($existing) {
		db_exec(
			$db,
			'UPDATE files SET size=?, date=?, `delete`=0 WHERE id=? LIMIT 1',
			'iii',
			$sizeKb,
			time(),
			(int) $existing['id']
		);
		$skipped++;
		continue;
	}

	$ok = db_exec(
		$db,
		'INSERT INTO files (`id`, `id_issue`, `id_press`, `date`, `name`, `type`, `file_title`, `size`, `downloads`, `delete`, `file_comment`) '
		. "VALUES (NULL, ?, ?, ?, ?, 0, '', ?, 0, 0, '')",
		'iiisi',
		$issueId,
		ACN_PRESS_ID,
		time(),
		$fileName,
		$sizeKb
	);
	if (!$ok) {
		fwrite(STDERR, "  #{$title}: INSERT failed\n");
		@unlink(zx_storage_path('files', $fileName));
		continue;
	}
	$fileId = (int) mysqli_insert_id($db);
	activity_log($db, [
		'verb' => 'created',
		'object_type' => 'file',
		'object_id' => $fileId,
		'parent_type' => 'issue',
		'parent_id' => $issueId,
		'action' => 'file.created',
		'event_scope' => ACTIVITY_SCOPE_CONTENT,
		'is_public' => 1,
		'title_ru' => 'ACNews #' . $title,
		'title_en' => 'ACNews #' . $title,
		'url_ru' => '/issue.php?id=' . $issueId,
		'meta' => ['source' => ACN_SOURCE, 'name' => $fileName],
	]);
	$created++;
}

if (!$dryRun) {
	activity_batch_finalize($db);
}

fwrite(STDOUT, sprintf("\nDone created=%d skipped=%d missing=%d%s\n", $created, $skipped, $missing, $dryRun ? ' (dry-run)' : ''));
exit($missing > 0 && $created === 0 ? 2 : 0);
