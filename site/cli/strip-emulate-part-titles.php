#!/usr/bin/env php
<?php
/**
 * Normalize Emulate (press 66) article titles:
 * - strip "Часть N —" / "Part N -" prefixes
 * - uppercase first letter
 * - drop trailing period
 *
 *   php site/cli/strip-emulate-part-titles.php --dry-run
 *   php site/cli/strip-emulate-part-titles.php --apply
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/cli/strip-emulate-part-titles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-strip-emulate-titles';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

const EMULATE_PRESS_ID = 66;

function strip_emulate_part_prefix(string $title): string
{
	$t = trim($title);
	$stripped = preg_replace('/^(?:Часть|Part)\s+\d+\s*[—–\-:—:]\s*/iu', '', $t, 1);
	if (!is_string($stripped)) {
		$stripped = $t;
	}
	$stripped = trim($stripped);
	if ($stripped === '') {
		$stripped = $t;
	}
	while ($stripped !== '' && str_ends_with($stripped, '.') && !str_ends_with($stripped, '...')) {
		$stripped = rtrim(substr($stripped, 0, -1));
	}
	if ($stripped === '') {
		return $t;
	}
	if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
		$first = mb_substr($stripped, 0, 1, 'UTF-8');
		$rest = mb_substr($stripped, 1, null, 'UTF-8');
		return mb_strtoupper($first, 'UTF-8') . $rest;
	}

	return strtoupper($stripped[0]) . substr($stripped, 1);
}

$z = db_select(
	$db,
	'SELECT id, id_issue, id_press, number, title, title_eng, temp, views, file, name, date,'
		. ' text_ru, text_en, text_type, text_type_ru, text_type_en,'
		. ' meta_description_ru, meta_description_en, slug_ru, slug_en'
		. ' FROM articles WHERE id_press=? AND temp=0 ORDER BY id_issue, number',
	'i',
	EMULATE_PRESS_ID
);

$scanned = 0;
$changed = 0;
while ($z && ($r = $z->fetch_assoc())) {
	$scanned++;
	$title = (string) ($r['title'] ?? '');
	$eng = (string) ($r['title_eng'] ?? '');
	$newTitle = strip_emulate_part_prefix($title);
	$newEng = strip_emulate_part_prefix($eng);
	if ($newTitle === $title && $newEng === $eng) {
		continue;
	}
	$changed++;
	fwrite(
		STDOUT,
		sprintf(
			"#%d number=%d\n  RU: %s => %s\n  EN: %s => %s\n",
			(int) $r['id'],
			(int) $r['number'],
			$title,
			$newTitle,
			$eng,
			$newEng
		)
	);
	if ($dryRun) {
		continue;
	}

	$ok = db_exec(
		$db,
		'UPDATE articles SET title=?, title_eng=? WHERE id=? LIMIT 1',
		'ssi',
		$newTitle,
		$newEng,
		(int) $r['id']
	);
	if (!$ok) {
		fwrite(STDERR, 'FAIL id=' . (int) $r['id'] . "\n");
		continue;
	}

	$row = $r;
	$row['title'] = $newTitle;
	$row['title_eng'] = $newEng;

	$v2 = ezines_v2_find_by_legacy_article($db, (int) $r['id']);
	if ($v2 !== null) {
		ezines_v2_sync_article($db, $row, (int) $v2['issue_id'], 'en');
	}
}

fwrite(STDOUT, ($dryRun ? 'DRY' : 'APPLY') . " scanned={$scanned} changed={$changed}\n");
exit(0);
