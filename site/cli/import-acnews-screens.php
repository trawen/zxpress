#!/usr/bin/env php
<?php
/**
 * Import captured ACNews screenshots (menu=type1, others=type2).
 *
 *   php site/cli/import-acnews-screens.php --dir=local/work/ACN_emu_screens --apply
 *   php site/cli/import-acnews-screens.php --dir=... --only=76,77 --apply
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$siteRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = '/cli/import-acnews-screens.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-acnews-screens';

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';
require_once $siteRoot . '/includes/screen_images.php';
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
				$only[] = $p;
			}
		}
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

if ($dir === '' || !is_dir($dir)) {
	fwrite(STDERR, "Need --dir=DIR with acnNN_menu.png / acnNN_0.png / acnNN_2.png\n");
	exit(1);
}

/** @var array<string, list<array{file:string,type:int}>> */
$byIssue = [];
foreach (glob($dir . '/acn*_*.png') ?: [] as $path) {
	$base = basename($path);
	if (!preg_match('/^acn(\d+)_(menu|0|2)\.png$/i', $base, $m)) {
		continue;
	}
	$issueKey = (string) (int) $m[1];
	if ($only !== [] && !in_array($issueKey, $only, true)) {
		continue;
	}
	$type = ($m[2] === 'menu') ? 1 : 2;
	$byIssue[$issueKey][] = ['file' => $path, 'type' => $type];
}
ksort($byIssue, SORT_NUMERIC);

fwrite(STDOUT, 'ACNews screens: dir=' . $dir . ' mode=' . ($dryRun ? 'dry-run' : 'APPLY') . ' issues=' . count($byIssue) . "\n");

$created = 0;
$skipped = 0;

foreach ($byIssue as $issueKey => $shots) {
	$z = db_select($db, 'SELECT id FROM issue WHERE id_press=? AND title=? LIMIT 1', 'is', ACN_PRESS_ID, $issueKey);
	$row = $z ? $z->fetch_assoc() : null;
	if (!$row) {
		fwrite(STDERR, "  #{$issueKey}: issue not found\n");
		continue;
	}
	$issueId = (int) $row['id'];

	$zCnt = db_select($db, 'SELECT COUNT(*) AS c FROM screens WHERE id_issue=?', 'i', $issueId);
	$have = (int) (($zCnt ? $zCnt->fetch_assoc()['c'] : 0) ?? 0);
	fwrite(STDOUT, "  #{$issueKey} issue={$issueId} existing_screens={$have} new=" . count($shots) . "\n");

	foreach ($shots as $shot) {
		$path = $shot['file'];
		$type = $shot['type'];
		$info = @getimagesize($path);
		$w = (int) ($info[0] ?? 0);
		$h = (int) ($info[1] ?? 0);
		fwrite(STDOUT, '    ' . basename($path) . " type={$type} {$w}x{$h}\n");
		if ($dryRun) {
			$created++;
			continue;
		}
		$ok = db_exec(
			$db,
			'INSERT INTO screens (id_press, id_issue, type, date, format) VALUES (?,?,?,?,?)',
			'iiiis',
			ACN_PRESS_ID,
			$issueId,
			$type,
			time(),
			'webp'
		);
		if (!$ok) {
			fwrite(STDERR, '    FAIL insert ' . basename($path) . "\n");
			continue;
		}
		$screenId = (int) mysqli_insert_id($db);
		$saved = screen_save_upload_as_webp($path, $screenId);
		if (empty($saved['ok'])) {
			db_exec($db, 'DELETE FROM screens WHERE id=? LIMIT 1', 'i', $screenId);
			screen_delete_files($screenId);
			fwrite(STDERR, '    FAIL webp ' . basename($path) . ': ' . ($saved['error'] ?? '') . "\n");
			continue;
		}
		if (function_exists('screen_after_saved_webp')) {
			screen_after_saved_webp($db, $screenId, $saved);
		} elseif ($w > 0 && $h > 0) {
			screen_set_dimensions($db, $screenId, $w, $h);
		}
		activity_log($db, [
			'verb' => 'uploaded',
			'object_type' => 'screen',
			'object_id' => $screenId,
			'parent_type' => 'issue',
			'parent_id' => $issueId,
			'action' => 'screen.uploaded',
			'event_scope' => ACTIVITY_SCOPE_CONTENT,
			'is_public' => 1,
			'title_ru' => 'ACNews #' . $issueKey,
			'title_en' => 'ACNews #' . $issueKey,
			'url_ru' => '/issue.php?id=' . $issueId,
			'meta' => ['source' => 'cli:import-acnews-screens', 'file' => basename($path), 'type' => $type],
		]);
		$created++;
	}
}

if (!$dryRun) {
	activity_batch_finalize($db);
}

fwrite(STDOUT, sprintf("\nDone created=%d skipped=%d%s\n", $created, $skipped, $dryRun ? ' (dry-run)' : ''));
exit(0);
