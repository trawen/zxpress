#!/usr/bin/env php
<?php
/**
 * Attach per-issue ACNews download zips (ACN01.zip …).
 *
 *   php site/cli/import-acnews-issue-files.php --dir=DIR --dry-run
 *   php site/cli/import-acnews-issue-files.php --dir=DIR --apply
 *   php site/cli/import-acnews-issue-files.php --dir=DIR --only=01,59,62 --apply
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$siteRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = '/cli/import-acnews-issue-files.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-acnews-issue-files';

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';
require_once $siteRoot . '/includes/activity.php';

const ACN_PRESS_ID = 8;

$dryRun = true;
$dir = '';
$only = [];

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif (str_starts_with($arg, '--dir=')) {
		$dir = rtrim(substr($arg, 6), '/');
	} elseif (str_starts_with($arg, '--only=')) {
		foreach (explode(',', substr($arg, 7)) as $p) {
			$p = trim($p);
			if ($p !== '') {
				$only[] = (string) (int) $p; // normalize "01" / "1"
			}
		}
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

if ($dir === '' || !is_dir($dir)) {
	fwrite(STDERR, "Need --dir=DIR with ACN##.zip files\n");
	exit(1);
}

/** @var array<string, string> $byNum file path keyed by issue title as stored ("01") */
$byNum = [];
foreach (glob($dir . '/ACN*.[Zz][Ii][Pp]') ?: [] as $path) {
	$base = basename($path);
	if (!preg_match('/^ACN(\d+)\.zip$/i', $base, $m)) {
		continue;
	}
	$n = (int) $m[1];
	$title = sprintf('%02d', $n); // ACNews issue titles are zero-padded
	if ($only !== [] && !in_array((string) $n, $only, true) && !in_array($title, $only, true)) {
		continue;
	}
	$byNum[$title] = $path;
}
ksort($byNum, SORT_STRING);

fwrite(STDOUT, 'ACNews issue files: dir=' . $dir . ' mode=' . ($dryRun ? 'dry-run' : 'APPLY') . ' files=' . count($byNum) . "\n");

$created = 0;
$updated = 0;
$missing = 0;

foreach ($byNum as $title => $src) {
	$fileName = 'ACN' . $title . '.zip';
	$zIssue = db_select(
		$db,
		'SELECT id, title FROM issue WHERE id_press=? AND title=? LIMIT 1',
		'is',
		ACN_PRESS_ID,
		$title
	);
	$issue = $zIssue ? $zIssue->fetch_assoc() : null;
	if (!$issue) {
		fwrite(STDERR, "  #{$title}: issue missing — skip\n");
		$missing++;
		continue;
	}
	$issueId = (int) $issue['id'];
	if (!is_file($src) || filesize($src) < 64) {
		fwrite(STDERR, "  #{$title}: file missing/too small — {$src}\n");
		$missing++;
		continue;
	}

	$sizeKb = max(1, (int) ceil(filesize($src) / 1000));
	$zFile = db_select(
		$db,
		'SELECT id, size FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
		'iis',
		ACN_PRESS_ID,
		$issueId,
		$fileName
	);
	$existing = $zFile ? $zFile->fetch_assoc() : null;

	fwrite(
		STDOUT,
		sprintf(
			"  #%s issue=%d %s bytes=%d kb=%d %s\n",
			$title,
			$issueId,
			$fileName,
			filesize($src),
			$sizeKb,
			$existing ? 'update#' . $existing['id'] : 'new'
		)
	);

	if ($dryRun) {
		if ($existing) {
			$updated++;
		} else {
			$created++;
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
		$updated++;
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
		'meta' => ['source' => 'cli:import-acnews-issue-files', 'name' => $fileName],
	]);
	$created++;
}

if (!$dryRun) {
	activity_batch_finalize($db);
}

fwrite(STDOUT, sprintf("\nDone created=%d updated=%d missing=%d%s\n", $created, $updated, $missing, $dryRun ? ' (dry-run)' : ''));
exit(0);
