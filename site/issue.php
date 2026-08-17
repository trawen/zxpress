<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/comments_scope.php';

function issue_ui_is_new(): bool
{
	return true;
}

function issue_ui_template(): string
{
	return 'issue_new.tpl';
}

function issue_ui_render($smarty, bool $isEng): void
{
	$catalogUrl = ezn_url_catalog($isEng);
	$smarty->assign('ezines_catalog_url', $catalogUrl);
	$smarty->assign('ezines_classic_url', ezn_url_catalog($isEng));
	$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
	$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
	$smarty->assign('smn_nav_authors_active', false);
	$smarty->assign('smn_nav_ezines_active', true);

	$smarty->display(issue_ui_template());
}

function issue_month_short(string $mm, bool $isEng, bool $genitive = false): string
{
	if ($isEng) {
		static $en = [
			'01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
			'05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
			'09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
		];
		return $en[$mm] ?? '';
	}
	static $nom = [
		'01' => 'янв', '02' => 'фев', '03' => 'мар', '04' => 'апр',
		'05' => 'май', '06' => 'июн', '07' => 'июл', '08' => 'авг',
		'09' => 'сен', '10' => 'окт', '11' => 'ноя', '12' => 'дек',
	];
	static $gen = [
		'01' => 'янв', '02' => 'фев', '03' => 'мар', '04' => 'апр',
		'05' => 'мая', '06' => 'июн', '07' => 'июл', '08' => 'авг',
		'09' => 'сен', '10' => 'окт', '11' => 'ноя', '12' => 'дек',
	];
	return ($genitive ? $gen : $nom)[$mm] ?? '';
}

function issue_format_month_year(int $ts, bool $isEng): string
{
	if ($ts <= 0) {
		return '';
	}
	$mm = date('m', $ts);
	return issue_month_short($mm, $isEng, false) . ' ' . date('Y', $ts);
}

function issue_format_date(int $ts, bool $isEng): string
{
	if ($ts <= 0) {
		return '';
	}
	$mm = date('m', $ts);
	return date('d ', $ts) . issue_month_short($mm, $isEng, true) . date(' Y', $ts);
}

$smarty->assign('issue_archive_hidden', htmlspecialchars($_GET['issue_archive_hidden'] ?? '', ENT_QUOTES, 'UTF-8'));

$pressSlug = per_slug_normalize_path((string) ($_GET['press_slug'] ?? ''));
$issueSlug = per_slug_normalize_path((string) ($_GET['issue_slug'] ?? ''));
$slugRoute = ($pressSlug !== '');

$id = (int) ($_GET['id'] ?? 0);
$isEng = ($_GET['lng'] ?? '') === 'eng';
$currentIssueId = 0;
$viewMode = 'press';

// Reserved catalog filter segments (must not be treated as press slugs).
if ($issueSlug === '' && ($pressSlug === 'papers' || $pressSlug === 'magazines' || $pressSlug === 'reports')) {
	$fallbackUrl = ezn_url_catalog_new($isEng);
	$fallbackUrl .= '?filter=' . rawurlencode($pressSlug);
	header('Location: ' . $fallbackUrl, true, 302);
	exit;
}

// Reserved section slugs that may be captured by legacy /{lang}/ezines/{slug} rewrites.
if ($issueSlug === '') {
	$sectionRedirects = [
		'books' => '/books',
		'zxnet' => '/zxnet',
		'snailmail' => '/snailmail',
		'authors' => '/authors',
		'gallery' => '/gallery',
		'search' => '/search',
		'updates' => '/updates',
		'guestbook' => '/guestbook',
		'map' => '/map',
		'categories' => '/categories',
		'periodicals' => '/periodicals',
	];
	if (isset($sectionRedirects[$pressSlug])) {
		header('Location: ' . ezn_path_prefix($isEng) . $sectionRedirects[$pressSlug], true, 302);
		exit;
	}
}

if ($slugRoute) {
	$pressId = ezn_find_press_id($db, $pressSlug, $isEng);
	if ($pressId <= 0) {
		http_response_code(404);
		$smarty->assign('press', null);
		$smarty->assign('screens', null);
		$smarty->assign('articles', null);
		$smarty->assign('issues_list', []);
		$smarty->assign('current_issue', null);
		$smarty->assign('view_mode', 'press');
		$smarty->assign('press_not_found', true);
		$smarty->assign('title', $isEng ? 'Publication not found' : 'Издание не найдено');
		$smarty->assign('url_rus', htmlspecialchars(ezn_url_catalog_new(false), ENT_QUOTES, 'UTF-8'));
		$smarty->assign('url_eng', htmlspecialchars(ezn_url_catalog_new(true), ENT_QUOTES, 'UTF-8'));
		issue_ui_render($smarty, $isEng);
		exit;
	}
	$id = $pressId;
	if ($issueSlug !== '') {
		$currentIssueId = ezn_find_issue_id($db, $id, $issueSlug, $isEng);
		if ($currentIssueId <= 0) {
			http_response_code(404);
			$smarty->assign('press', null);
			$smarty->assign('screens', null);
			$smarty->assign('articles', null);
			$smarty->assign('issues_list', []);
			$smarty->assign('current_issue', null);
			$smarty->assign('view_mode', 'issue');
			$smarty->assign('issue_not_found', true);
			$smarty->assign('title', $isEng ? 'Issue not found' : 'Выпуск не найден');
			$smarty->assign('url_rus', htmlspecialchars(ezn_url_catalog_new(false), ENT_QUOTES, 'UTF-8'));
			$smarty->assign('url_eng', htmlspecialchars(ezn_url_catalog_new(true), ENT_QUOTES, 'UTF-8'));
			issue_ui_render($smarty, $isEng);
			exit;
		}
		$viewMode = 'issue';
	}
} elseif ($id > 0) {
	ezn_maybe_redirect_press_legacy($db, $id, $isEng, $issueSlug);
} else {
	$id = 1;
}

$c = [];
$screensByIssue = [];
$stmt = mysqli_prepare($db, "SELECT * FROM issue, screens WHERE issue.id_press=? AND screens.id_issue=issue.id ORDER BY issue.sort_order DESC, screens.type ASC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {
	$c[] = $t;
	$issueId = (int) ($t['id_issue'] ?? 0);
	if ($issueId > 0) {
		$screensByIssue[$issueId][] = $t;
	}
}
$smarty->assign('screens', $c);
$smarty->assign('screens_by_issue', $screensByIssue);

$stmt = mysqli_prepare($db, "SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id LEFT OUTER JOIN countries ON cities.country_id=countries.id WHERE press.id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$t = mysqli_fetch_array($z);

$t['title_plain'] = title_plain($t['title'] ?? '');

$pressDescRu = trim((string) ($t['description_ru'] ?? ''));
$pressDescEn = trim((string) ($t['description_en'] ?? ''));
$pressMetaRu = trim((string) ($t['meta_description_ru'] ?? ''));
$pressMetaEn = trim((string) ($t['meta_description_en'] ?? ''));
$t['description'] = $isEng
	? ($pressDescEn !== '' ? $pressDescEn : $pressDescRu)
	: ($pressDescRu !== '' ? $pressDescRu : $pressDescEn);
$t['meta_description'] = $isEng
	? ($pressMetaEn !== '' ? $pressMetaEn : $pressMetaRu)
	: ($pressMetaRu !== '' ? $pressMetaRu : $pressMetaEn);

if (($_GET['lng'] ?? '') === 'eng') {
	$cityEng = trim((string) ($t['name_eng'] ?? ''));
	if ($cityEng !== '') {
		$t['name'] = $cityEng;
	}
	$countryEng = trim((string) ($t['country_name_eng'] ?? ''));
	if ($countryEng !== '') {
		$t['country_name'] = $countryEng;
	}
}

if ($t['years_to'] != $t['years_from']) {
	$t['years_to'] = issue_format_month_year((int) $t['years_to'], $isEng);
} else {
	unset($t['years_to']);
}

if ($t['years_from']) {
	$t['years_from'] = issue_format_month_year((int) $t['years_from'], $isEng);
}

$num = ['выпуск', 'выпуска', 'выпусков'];
$smarty->assign('num', getNumEnding($t['numbers'], $num));
$t['public_url'] = ezn_url_press($t, $isEng);
$pressRow = [
	'id' => (int) $t['id'],
	'title' => (string) ($t['title'] ?? ''),
	'slug_ru' => (string) ($t['slug_ru'] ?? ''),
	'slug_en' => (string) ($t['slug_en'] ?? ''),
];
$smarty->assign('press', $t);
$smarty->assign('press_url', ezn_url_press($pressRow, $isEng));

$issueCanonRow = null;
$issueCanonicalPath = ezn_url_press($pressRow, $isEng);
$currentIssue = null;

if ($currentIssueId > 0) {
	$stmtIssueCanon = $db->prepare(
		'SELECT id, id_press, title, date, slug_ru, slug_en, description_ru, description_en, meta_description_ru, meta_description_en'
		. ' FROM issue WHERE id=? LIMIT 1'
	);
	if ($stmtIssueCanon) {
		$stmtIssueCanon->bind_param('i', $currentIssueId);
		$stmtIssueCanon->execute();
		$issueCanonRow = $stmtIssueCanon->get_result()->fetch_assoc();
		$stmtIssueCanon->close();
		if (is_array($issueCanonRow)) {
			$issueCanonicalPath = ezn_url_issue($pressRow, $issueCanonRow, $isEng);
			$dateTs = (int) ($issueCanonRow['date'] ?? 0);
			$dateDisplay = issue_format_date($dateTs, $isEng);
			if ($dateDisplay === '01 января 1970' || $dateDisplay === '01 January 1970') {
				$dateDisplay = '';
			}
			$descRu = trim((string) ($issueCanonRow['description_ru'] ?? ''));
			$descEn = trim((string) ($issueCanonRow['description_en'] ?? ''));
			$metaRu = trim((string) ($issueCanonRow['meta_description_ru'] ?? ''));
			$metaEn = trim((string) ($issueCanonRow['meta_description_en'] ?? ''));
			$description = $isEng
				? ($descEn !== '' ? $descEn : $descRu)
				: ($descRu !== '' ? $descRu : $descEn);
			$metaDescription = $isEng
				? ($metaEn !== '' ? $metaEn : $metaRu)
				: ($metaRu !== '' ? $metaRu : $metaEn);
			$currentIssue = [
				'id' => (int) $issueCanonRow['id'],
				'title' => (string) ($issueCanonRow['title'] ?? ''),
				'slug_ru' => (string) ($issueCanonRow['slug_ru'] ?? ''),
				'slug_en' => (string) ($issueCanonRow['slug_en'] ?? ''),
				'date_display' => $dateDisplay,
				'public_url' => $issueCanonicalPath,
				'screens' => $screensByIssue[$currentIssueId] ?? [],
				'download_url' => '',
				'emulator_url' => '',
				'description' => $description,
				'meta_description' => $metaDescription,
			];
			if ($metaDescription !== '') {
				$smarty->assign('description', $metaDescription);
			}
			$stmtFile = $db->prepare('SELECT name FROM files WHERE id_issue=? AND `delete`=0 ORDER BY id ASC LIMIT 1');
			if ($stmtFile) {
				$stmtFile->bind_param('i', $currentIssueId);
				$stmtFile->execute();
				$fileRow = $stmtFile->get_result()->fetch_assoc();
				$stmtFile->close();
				$fileName = basename((string) ($fileRow['name'] ?? ''));
				if ($fileName === '' || $fileName === '.' || $fileName === '..') {
					$stmtPressFile = $db->prepare(
						'SELECT name FROM files WHERE id_press=? AND id_issue=0 AND `delete`=0 ORDER BY id ASC LIMIT 1'
					);
					if ($stmtPressFile) {
						$stmtPressFile->bind_param('i', $id);
						$stmtPressFile->execute();
						$fileRow = $stmtPressFile->get_result()->fetch_assoc();
						$stmtPressFile->close();
						$fileName = basename((string) ($fileRow['name'] ?? ''));
					}
				}
				if ($fileName !== '' && $fileName !== '.' && $fileName !== '..') {
					$fileUrl = '/files/' . rawurlencode($fileName);
					$currentIssue['download_url'] = $fileUrl;
					$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
					if (in_array($fileExt, ['zip', 'trd', 'scl'], true)) {
						$currentIssue['emulator_url'] = $fileUrl;
					}
				}
			}
			$viewMode = 'issue';
		}
	}
}

if ($viewMode === 'press' && ($t['meta_description'] ?? '') !== '') {
	$smarty->assign('description', (string) $t['meta_description']);
}

$origin = zxpress_canonical_origin();
$smarty->assign('canonical_url', $origin . $issueCanonicalPath);
$smarty->assign('hreflang_ru', $origin . ezn_url_for_lang(false, $pressRow, $issueCanonRow, null));
$smarty->assign('hreflang_en', $origin . ezn_url_for_lang(true, $pressRow, $issueCanonRow, null));
ezn_assign_lang_switch_urls($smarty, $pressRow, $issueCanonRow, null);

$type[0] = 'Электронная газета для ZX Spectrum';
$type[1] = 'Электронный журнал для ZX Spectrum';
$type[2] = 'Электронный отчёт для ZX Spectrum';
if ($viewMode === 'issue' && $currentIssue) {
	$smarty->assign(
		'title',
		'«' . $t['title_plain'] . '» #' . $currentIssue['title'] . ', ' . $t['name'] . ' (' . $t['country_name'] . ') — ' . $type[$t['type']]
	);
} else {
	$smarty->assign('title', '«' . $t['title_plain'] . '», ' . $t['name'] . ' (' . $t['country_name'] . ') — ' . $type[$t['type']]);
}

$is = [];
$stmt = mysqli_prepare($db, 'SELECT * FROM issue, files WHERE issue.id_press=? AND files.id_issue=issue.id ORDER BY issue.sort_order ASC, issue.title ASC');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_array($z)) {
	$is[] = $row;
}
$smarty->assign('issues', $is);

// Issues index for press overview (no article dump).
$issuesList = [];
if ($viewMode === 'press') {
	$stmtIssues = mysqli_prepare(
		$db,
		'SELECT id, id_press, title, date, slug_ru, slug_en FROM issue WHERE id_press=? ORDER BY sort_order DESC, id DESC'
	);
	if ($stmtIssues) {
		$stmtIssues->bind_param('i', $id);
		$stmtIssues->execute();
		$zIssues = $stmtIssues->get_result();
		while ($row = $zIssues->fetch_assoc()) {
			$issueId = (int) ($row['id'] ?? 0);
			$dateTs = (int) ($row['date'] ?? 0);
			$dateDisplay = issue_format_date($dateTs, $isEng);
			if ($dateDisplay === '01 января 1970' || $dateDisplay === '01 January 1970') {
				$dateDisplay = '';
			}
			$issueRow = [
				'id' => $issueId,
				'id_press' => $id,
				'title' => (string) ($row['title'] ?? ''),
				'slug_ru' => (string) ($row['slug_ru'] ?? ''),
				'slug_en' => (string) ($row['slug_en'] ?? ''),
			];
			$issuesList[] = [
				'id' => $issueId,
				'title' => (string) ($row['title'] ?? ''),
				'date_display' => $dateDisplay,
				'public_url' => ezn_url_issue($pressRow, $issueRow, $isEng),
				'screens' => $screensByIssue[$issueId] ?? [],
				'cover' => ($screensByIssue[$issueId][0] ?? null),
			];
		}
		$stmtIssues->close();
	}
}
$smarty->assign('issues_list', $issuesList);

// Timeline data
if ($id > 0) {
	$timelineDates = [];
	$stmtT = mysqli_prepare($db, 'SELECT date FROM issue WHERE id_press=? AND date > 0 ORDER BY date ASC');
	if ($stmtT) {
		mysqli_stmt_bind_param($stmtT, 'i', $id);
		mysqli_stmt_execute($stmtT);
		$resT = mysqli_stmt_get_result($stmtT);
		while ($rowT = mysqli_fetch_assoc($resT)) {
			$timelineDates[] = (int) $rowT['date'];
		}
		mysqli_stmt_close($stmtT);
	}

	if (count($timelineDates) > 2) {
		$minT = $timelineDates[0];
		$maxT = $timelineDates[count($timelineDates) - 1];
		$rangeT = $maxT - $minT;
		if ($rangeT > 0) {
			$dots = [];
			foreach ($timelineDates as $idx => $d) {
				$dots[] = [
					'pos' => round(($d - $minT) / $rangeT * 100, 2),
					'is_edge' => ($idx === 0 || $idx === count($timelineDates) - 1),
				];
			}
			$years = [];
			$startYear = (int) date('Y', $minT);
			$endYear = (int) date('Y', $maxT);
			for ($y = $startYear + 1; $y <= $endYear; $y++) {
				$yTs = strtotime("$y-01-01");
				if ($yTs > $minT && $yTs < $maxT) {
					$years[] = [
						'year' => $y,
						'pos' => round(($yTs - $minT) / $rangeT * 100, 2),
					];
				}
			}
			$smarty->assign('timeline', [
				'dots' => $dots,
				'years' => $years,
				'start_date' => date('d.m.Y', $minT),
				'end_date' => date('d.m.Y', $maxT),
			]);
		}
	}
}

// Articles: issue page = one issue; press page = none.
$art = [];
$prev_issue = null;
if ($viewMode === 'issue') {
	$sql = 'SELECT articles.*, issue.title AS issue_title, issue.slug_ru AS issue_slug_ru, issue.slug_en AS issue_slug_en, issue.date AS issue_date, issue.id AS issue_id'
		. ' FROM articles JOIN issue ON articles.id_issue=issue.id'
		. ' WHERE articles.temp=0 AND issue.id_press=?';
	if ($currentIssueId > 0) {
		$sql .= ' AND issue.id=?';
	}
	$sql .= ' ORDER BY issue.sort_order DESC, articles.number, articles.title';
	$stmt = mysqli_prepare($db, $sql);
	if ($currentIssueId > 0) {
		mysqli_stmt_bind_param($stmt, 'ii', $id, $currentIssueId);
	} else {
		mysqli_stmt_bind_param($stmt, 'i', $id);
	}
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);

	$a = 0;
	while ($t2 = mysqli_fetch_array($z)) {
		if ($t2['issue_id'] !== $prev_issue) {
			$t2['print_title'] = 1;
			$prev_issue = $t2['issue_id'];
		} else {
			$t2['print_title'] = 0;
		}
		$t2['issue'] = $t2['issue_title'];
		$t2['date'] = issue_format_date((int) $t2['issue_date'], $isEng);
		$t2['title_list'] = article_title_list_html($t2['title'] ?? '');
		$t2['title_eng_list'] = article_title_list_html($t2['title_eng'] ?? '');
		$issueRow = [
			'id' => (int) $t2['issue_id'],
			'id_press' => $id,
			'title' => (string) $t2['issue_title'],
			'slug_ru' => (string) ($t2['issue_slug_ru'] ?? ''),
			'slug_en' => (string) ($t2['issue_slug_en'] ?? ''),
		];
		$t2['issue_url'] = ezn_url_issue($pressRow, $issueRow, $isEng);
		$t2['public_url'] = ezn_url_article($pressRow, $issueRow, $t2, $isEng);
		$art[$a] = $t2;
		$a++;
	}
}
$smarty->assign('articles', $art);

$n = 0;
$cov = [];
$last_issue = $currentIssueId > 0 ? $currentIssueId : (int) ($prev_issue ?? 0);
$stmt = mysqli_prepare($db, 'SELECT * FROM covers WHERE id_issue=?');
mysqli_stmt_bind_param($stmt, 'i', $last_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_array($z)) {
	$cov[$n] = $row;
	$n++;
}
$smarty->assign('covers', $cov);

$smarty->assign('view_mode', $viewMode);
$smarty->assign('current_issue', $currentIssue);

$prevIssueNav = null;
$nextIssueNav = null;
if ($viewMode === 'issue' && $currentIssueId > 0 && is_array($pressRow)) {
	$stmtNav = mysqli_prepare(
		$db,
		'SELECT id, id_press, title, slug_ru, slug_en FROM issue WHERE id_press=? ORDER BY sort_order ASC, id ASC'
	);
	if ($stmtNav) {
		$stmtNav->bind_param('i', $id);
		$stmtNav->execute();
		$navRows = [];
		$zNav = $stmtNav->get_result();
		while ($row = $zNav->fetch_assoc()) {
			$navRows[] = $row;
		}
		$stmtNav->close();
		$navCount = count($navRows);
		for ($i = 0; $i < $navCount; $i++) {
			if ((int) ($navRows[$i]['id'] ?? 0) !== $currentIssueId) {
				continue;
			}
			if ($i > 0) {
				$prevRow = $navRows[$i - 1];
				$prevIssueNav = [
					'title' => (string) ($prevRow['title'] ?? ''),
					'public_url' => ezn_url_issue($pressRow, $prevRow, $isEng),
				];
			}
			if ($i + 1 < $navCount) {
				$nextRow = $navRows[$i + 1];
				$nextIssueNav = [
					'title' => (string) ($nextRow['title'] ?? ''),
					'public_url' => ezn_url_issue($pressRow, $nextRow, $isEng),
				];
			}
			break;
		}
	}
}
$smarty->assign('prev_issue_nav', $prevIssueNav);
$smarty->assign('next_issue_nav', $nextIssueNav);

if (empty($smarty->getTemplateVars('press_not_found')) && empty($smarty->getTemplateVars('issue_not_found'))) {
	if ($viewMode === 'issue' && $currentIssueId > 0 && is_array($currentIssue)) {
		$comments_target_id = comments_id_ezine_issue($currentIssueId);
		$commentsFormAction = ezn_url_issue($pressRow, $currentIssue, $isEng);
	} else {
		$comments_target_id = comments_id_ezine_press($id);
		$commentsFormAction = ezn_url_press($pressRow, $isEng);
	}
	$smarty->assign('comments_enabled', true);
	$smarty->assign('comments_form_action', $commentsFormAction);
	if ($viewMode === 'issue' && $currentIssueId > 0) {
		$smarty->assign('comments_invite', $isEng
			? 'Share your thoughts about this issue'
			: 'Поделитесь вашим мнением о выпуске');
	} else {
		$smarty->assign('comments_invite', $isEng
			? 'Share your thoughts about this publication'
			: 'Поделитесь вашим мнением об издании');
	}
	require __DIR__ . '/comments.php';
} else {
	$smarty->assign('comments_enabled', false);
}

issue_ui_render($smarty, $isEng);
