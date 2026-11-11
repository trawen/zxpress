#!/usr/bin/env php
<?php
/**
 * Import electronic ezine series from staged zxart downloads (files only, no screens).
 *
 * Expects manifest at data/tmp/zxart_import/manifest.json (created on host).
 *
 *   ZXPRESS_DATA_ROOT=/home/zxpress/web/zxpress.ru/data \
 *     php site/cli/import-zxart-series-files.php --dry-run
 *   ZXPRESS_DATA_ROOT=/home/zxpress/web/zxpress.ru/data \
 *     php site/cli/import-zxart-series-files.php --apply
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
$only = [];

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif (str_starts_with($arg, '--only=')) {
		foreach (explode(',', substr($arg, 7)) as $p) {
			$p = trim($p);
			if ($p !== '') {
				$only[] = $p;
			}
		}
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--only=Libra,BBSman,...]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/cli/import-zxart-series-files.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-zxart-series-files';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/activity.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

/** @var mysqli $db */
if (!isset($db) || !($db instanceof mysqli)) {
	fwrite(STDERR, "No mysqli \$db\n");
	exit(1);
}

const SOURCE = 'import-zxart-series-files.php';

// Staged on host under data/tmp (not PHP tmpfs).
$importDir = rtrim(zx_data_root(), '/') . '/tmp/zxart_import';
$manifestPath = $importDir . '/manifest.json';
if (!is_file($manifestPath)) {
	fwrite(STDERR, "Missing manifest: {$manifestPath}\n");
	exit(1);
}

/** @var list<array<string,mixed>> $rows */
$rows = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($rows) || $rows === []) {
	fwrite(STDERR, "Empty/invalid manifest\n");
	exit(1);
}

/** @var array<string, array{type:int, title:string, title_en:string}> $SERIES */
$SERIES = [
	'Libra' => ['type' => 1, 'title' => 'Libra', 'title_en' => 'Libra'],
	'BBSman' => ['type' => 0, 'title' => 'BBSman', 'title_en' => 'BBSman'],
	'Hypegy' => ['type' => 1, 'title' => 'Hypegy', 'title_en' => 'Hypegy'],
	'Чушь' => ['type' => 0, 'title' => 'Чушь', 'title_en' => 'Chush'],
];

function zxi_issue_title_from_zxart(string $title): string
{
	if (preg_match('/[#№]\s*0*(\d+)\s*$/u', $title, $m)) {
		return sprintf('%02d', (int) $m[1]);
	}
	if (preg_match('/(\d+)\s*$/u', $title, $m)) {
		return sprintf('%02d', (int) $m[1]);
	}

	return '01';
}

function zxi_year_ts(?int $year): int
{
	if ($year === null || $year < 1971 || $year > 2100) {
		return 0;
	}

	return (int) gmmktime(0, 0, 0, 1, 1, $year);
}

function zxi_safe_file_name(string $name): string
{
	$name = basename(str_replace(['\\', '/'], '-', $name));
	$name = preg_replace('/[^\w.\-()+]+/u', '_', $name) ?? $name;
	if (strlen($name) > 32) {
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		$stem = pathinfo($name, PATHINFO_FILENAME);
		$maxStem = max(1, 32 - (strlen($ext) + ($ext !== '' ? 1 : 0)));
		$name = substr($stem, 0, $maxStem) . ($ext !== '' ? '.' . $ext : '');
	}

	return $name !== '' ? $name : 'issue.zip';
}

/**
 * @param array{type:int,title:string,title_en:string} $cfg
 * @return array{id:int,created:bool}
 */
function zxi_ensure_press(mysqli $db, array $cfg, int $numbers, int $yearsFrom, int $yearsTo, bool $dryRun): array
{
	$title = $cfg['title'];
	$z = db_select($db, 'SELECT id FROM press WHERE title=? LIMIT 1', 's', $title);
	if ($z && ($row = $z->fetch_assoc())) {
		$id = (int) ($row['id'] ?? 0);
		if (!$dryRun && $id > 0) {
			db_exec(
				$db,
				'UPDATE press SET type=?, numbers=?, online_issues=?, years_from=IF(?=0, years_from, IF(years_from=0 OR ?<years_from, ?, years_from)), '
				. 'years_to=IF(?=0, years_to, IF(years_to=0 OR ?>years_to, ?, years_to)) WHERE id=? LIMIT 1',
				'iiiiiiiiii',
				$cfg['type'],
				$numbers,
				$numbers,
				$yearsFrom,
				$yearsFrom,
				$yearsFrom,
				$yearsTo,
				$yearsTo,
				$yearsTo,
				$id
			);
		}

		return ['id' => $id, 'created' => false];
	}

	if ($dryRun) {
		return ['id' => 0, 'created' => true];
	}

	$type = $cfg['type'];
	$city = 0;
	$language = 0;
	$ex = '';
	$onlineArticles = 0;
	$stmt = $db->prepare(
		'INSERT INTO press (title, type, city, years_from, years_to, numbers, online_issues, online_articles, language, ex, '
		. 'slug_ru, slug_en, description_ru, description_en, meta_description_ru, meta_description_en) '
		. 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
	);
	if (!$stmt) {
		throw new RuntimeException('prepare press: ' . $db->error);
	}
	$slugRu = '';
	$slugEn = '';
	$descRu = '';
	$descEn = '';
	$metaRu = '';
	$metaEn = '';
	$stmt->bind_param(
		'siiiiiiiisssssss',
		$title,
		$type,
		$city,
		$yearsFrom,
		$yearsTo,
		$numbers,
		$numbers,
		$onlineArticles,
		$language,
		$ex,
		$slugRu,
		$slugEn,
		$descRu,
		$descEn,
		$metaRu,
		$metaEn
	);
	if (!$stmt->execute()) {
		$err = $stmt->error;
		$stmt->close();
		throw new RuntimeException('insert press: ' . $err);
	}
	$id = (int) $stmt->insert_id;
	$stmt->close();

	$pressRow = ['id' => $id, 'title' => $title];
	$slugs = per_admin_resolve_slugs(
		$db,
		'press',
		'',
		'',
		static fn () => ezn_default_press_ru($pressRow),
		static fn () => ezn_default_press_en($pressRow),
		$id
	);
	// Prefer latin slug for Чушь EN
	if ($cfg['title_en'] !== $title && ($slugs['slug_en'] === '' || $slugs['slug_en'] === $slugs['slug_ru'])) {
		$slugs['slug_en'] = per_slug_make_unique_lang(
			$db,
			'press',
			'slug_en',
			per_slug_from_title($cfg['title_en']),
			$id
		);
	}
	db_exec($db, 'UPDATE press SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1', 'ssi', $slugs['slug_ru'], $slugs['slug_en'], $id);

	return ['id' => $id, 'created' => true];
}

/**
 * @return array{id:int,created:bool,title:string}
 */
function zxi_ensure_issue(mysqli $db, int $pressId, string $issueTitle, int $sortOrder, int $issueDate, bool $dryRun): array
{
	$z = db_select($db, 'SELECT id FROM issue WHERE id_press=? AND title=? LIMIT 1', 'is', $pressId, $issueTitle);
	if ($z && ($row = $z->fetch_assoc())) {
		return ['id' => (int) ($row['id'] ?? 0), 'created' => false, 'title' => $issueTitle];
	}
	if ($dryRun) {
		return ['id' => 0, 'created' => true, 'title' => $issueTitle];
	}

	$views = 0;
	$stmt = $db->prepare(
		'INSERT INTO issue (id_press, title, date, sort_order, views, missing) VALUES (?,?,?,?,?,0)'
	);
	if (!$stmt) {
		throw new RuntimeException('prepare issue: ' . $db->error);
	}
	$stmt->bind_param('isiii', $pressId, $issueTitle, $issueDate, $sortOrder, $views);
	if (!$stmt->execute()) {
		$err = $stmt->error;
		$stmt->close();
		throw new RuntimeException('insert issue: ' . $err);
	}
	$id = (int) $stmt->insert_id;
	$stmt->close();

	$issueRow = ['id' => $id, 'id_press' => $pressId, 'title' => $issueTitle];
	$slugs = per_admin_resolve_slugs(
		$db,
		'issue',
		'',
		'',
		static fn () => ezn_default_issue_ru($issueRow),
		static fn () => ezn_default_issue_en($issueRow),
		$id,
		'id_press',
		$pressId
	);
	db_exec($db, 'UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1', 'ssi', $slugs['slug_ru'], $slugs['slug_en'], $id);

	return ['id' => $id, 'created' => true, 'title' => $issueTitle];
}

/**
 * @return array{id:int,created:bool,name:string}
 */
function zxi_ensure_file(
	mysqli $db,
	int $pressId,
	int $issueId,
	string $srcPath,
	string $fileName,
	bool $dryRun
): array {
	$z = db_select(
		$db,
		'SELECT id FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
		'iis',
		$pressId,
		$issueId,
		$fileName
	);
	if ($z && ($row = $z->fetch_assoc())) {
		$id = (int) ($row['id'] ?? 0);
		$disk = zx_storage_path('files', $fileName);
		if (!$dryRun && $id > 0 && (!is_file($disk) || filesize($disk) < 32)) {
			if (!zx_storage_copy_uploaded_file('files', $fileName, $srcPath)) {
				throw new RuntimeException('copy file failed: ' . $fileName);
			}
			$sizeKb = max(1, (int) ceil(filesize($srcPath) / 1000));
			db_exec($db, 'UPDATE files SET size=?, date=? WHERE id=? LIMIT 1', 'iii', $sizeKb, time(), $id);
		}

		return ['id' => $id, 'created' => false, 'name' => $fileName];
	}

	if ($dryRun) {
		return ['id' => 0, 'created' => true, 'name' => $fileName];
	}

	if (!is_file($srcPath) || filesize($srcPath) < 32) {
		throw new RuntimeException('source missing/small: ' . $srcPath);
	}
	if (!zx_storage_copy_uploaded_file('files', $fileName, $srcPath)) {
		throw new RuntimeException('copy into storage failed: ' . $fileName);
	}

	$sizeKb = max(1, (int) ceil(filesize($srcPath) / 1000));
	$type = 0;
	$fileTitle = '';
	$fileComment = '';
	$tm = time();
	$downloads = 0;
	$del = 0;
	$stmt = $db->prepare(
		'INSERT INTO files (id_issue, id_press, date, name, type, file_title, size, downloads, `delete`, file_comment) '
		. 'VALUES (?,?,?,?,?,?,?,?,?,?)'
	);
	if (!$stmt) {
		throw new RuntimeException('prepare files: ' . $db->error);
	}
	// id_issue i, id_press i, date i, name s, type i, file_title s, size i, downloads i, delete i, file_comment s
	$stmt->bind_param(
		'iiisisiiis',
		$issueId,
		$pressId,
		$tm,
		$fileName,
		$type,
		$fileTitle,
		$sizeKb,
		$downloads,
		$del,
		$fileComment
	);
	if (!$stmt->execute()) {
		$err = $stmt->error;
		$stmt->close();
		throw new RuntimeException('insert files: ' . $err);
	}
	$id = (int) $stmt->insert_id;
	$stmt->close();

	return ['id' => $id, 'created' => true, 'name' => $fileName];
}

function zxi_activity(
	mysqli $db,
	int $pressId,
	int $issueId,
	string $issueTitle,
	int $fileId,
	string $fileName,
	bool $issueCreated,
	bool $fileCreated
): void {
	if (!$issueCreated && !$fileCreated) {
		return;
	}
	$batchId = activity_batch_begin($db, 'ezine', 'issue', $issueId, SOURCE);
	if ($issueCreated) {
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

// Group by series
/** @var array<string, list<array<string,mixed>>> $bySeries */
$bySeries = [];
foreach ($rows as $row) {
	$series = (string) ($row['series'] ?? '');
	if ($series === '' || !isset($SERIES[$series])) {
		continue;
	}
	if ($only !== [] && !in_array($series, $only, true)) {
		continue;
	}
	$bySeries[$series][] = $row;
}

fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '[apply] ') . 'zxart series file import: ' . implode(', ', array_keys($bySeries)) . "\n");

$report = ['press_created' => 0, 'issues_created' => 0, 'files_created' => 0, 'press_ids' => []];

foreach ($bySeries as $series => $issues) {
	$cfg = $SERIES[$series];
	$years = [];
	foreach ($issues as $it) {
		$y = isset($it['year']) ? (int) $it['year'] : 0;
		if ($y > 1970) {
			$years[] = $y;
		}
	}
	$yf = $years !== [] ? zxi_year_ts(min($years)) : 0;
	$yt = $years !== [] ? zxi_year_ts(max($years)) : 0;
	$numbers = count($issues);

	$pressInfo = zxi_ensure_press($db, $cfg, $numbers, $yf, $yt, $dryRun);
	$pressId = (int) $pressInfo['id'];
	if ($pressInfo['created']) {
		$report['press_created']++;
	}
	$report['press_ids'][$series] = $pressId;
	fwrite(STDOUT, "  {$series}: press_id=" . ($pressId ?: '(new)') . ($pressInfo['created'] ? ' CREATE' : ' exists') . "\n");

	// stable sort by issue number
	usort($issues, static function (array $a, array $b): int {
		return zxi_issue_title_from_zxart((string) ($a['title'] ?? ''))
			<=> zxi_issue_title_from_zxart((string) ($b['title'] ?? ''));
	});

	foreach ($issues as $it) {
		$issueTitle = zxi_issue_title_from_zxart((string) ($it['title'] ?? '01'));
		$issueNo = (int) $issueTitle;
		$sortOrder = max(10, $issueNo * 10 + 10);
		$issueDate = zxi_year_ts(isset($it['year']) ? (int) $it['year'] : 0);
		$fileOrig = (string) ($it['file_orig'] ?? 'issue.zip');
		$fileName = zxi_safe_file_name($fileOrig);
		$src = $importDir . '/' . (string) ($it['file_src'] ?? '');

		fwrite(STDOUT, "    #{$issueTitle} {$fileName} ... ");

		if ($dryRun) {
			$wouldIssue = true;
			$wouldFile = true;
			if ($pressId > 0) {
				$zi = db_select($db, 'SELECT id FROM issue WHERE id_press=? AND title=? LIMIT 1', 'is', $pressId, $issueTitle);
				if ($zi && ($r = $zi->fetch_assoc())) {
					$wouldIssue = false;
					$iid = (int) $r['id'];
					$zf = db_select(
						$db,
						'SELECT id FROM files WHERE id_press=? AND id_issue=? AND name=? AND `delete`=0 LIMIT 1',
						'iis',
						$pressId,
						$iid,
						$fileName
					);
					$wouldFile = !($zf && $zf->fetch_assoc());
				}
			}
			fwrite(STDOUT, ($wouldIssue ? 'would create issue' : 'issue ok') . ', ' . ($wouldFile ? 'would upload file' : 'file ok') . "\n");
			if ($wouldIssue) {
				$report['issues_created']++;
			}
			if ($wouldFile) {
				$report['files_created']++;
			}
			continue;
		}

		$issueInfo = zxi_ensure_issue($db, $pressId, $issueTitle, $sortOrder, $issueDate, false);
		$issueId = (int) $issueInfo['id'];
		$fileInfo = zxi_ensure_file($db, $pressId, $issueId, $src, $fileName, false);
		zxi_activity(
			$db,
			$pressId,
			$issueId,
			$issueTitle,
			(int) $fileInfo['id'],
			$fileName,
			(bool) $issueInfo['created'],
			(bool) $fileInfo['created']
		);
		if ($issueInfo['created']) {
			$report['issues_created']++;
		}
		if ($fileInfo['created']) {
			$report['files_created']++;
		}
		fwrite(
			STDOUT,
			($issueInfo['created'] ? 'issue+' : 'issue=') . $issueId
			. ' ' . ($fileInfo['created'] ? 'file+' : 'file=') . $fileInfo['id']
			. "\n"
		);
	}

	if (!$dryRun && $pressId > 0) {
		$countZ = db_select($db, 'SELECT COUNT(*) AS c FROM issue WHERE id_press=?', 'i', $pressId);
		$c = $countZ && ($r = $countZ->fetch_assoc()) ? (int) ($r['c'] ?? 0) : $numbers;
		db_exec($db, 'UPDATE press SET online_issues=?, numbers=? WHERE id=? LIMIT 1', 'iii', $c, $c, $pressId);
		if (function_exists('ezines_v2_ready') && ezines_v2_ready($db)) {
			$pz = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
			$pressRow = $pz ? $pz->fetch_assoc() : null;
			if ($pressRow) {
				$ezineId = ezines_v2_sync_press($db, $pressRow, 'ru');
				if ($ezineId > 0) {
					$iz = db_select($db, 'SELECT * FROM issue WHERE id_press=? ORDER BY sort_order ASC, id ASC', 'i', $pressId);
					while ($iz && ($issueRow = $iz->fetch_assoc())) {
						ezines_v2_sync_issue($db, $issueRow, $ezineId);
					}
					fwrite(STDOUT, "    ezines_v2 synced ezine_id={$ezineId}\n");
				} else {
					fwrite(STDERR, "    WARN ezines_v2_sync_press failed\n");
				}
			}
		}
	}
}

fwrite(STDOUT, 'DONE ' . json_encode($report, JSON_UNESCAPED_UNICODE) . "\n");
exit(0);
