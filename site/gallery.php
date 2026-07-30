<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function gallery_ui_is_new(): bool
{
	return !defined('GALLERY_UI_VARIANT') || GALLERY_UI_VARIANT === 'new';
}

function gallery_ui_template(): string
{
	return gallery_ui_is_new() ? 'gallery_new.tpl' : 'gallery.tpl';
}

function gallery_url_catalog(bool $isEng, bool $isNew = false): string
{
	if ($isNew) {
		return ezn_path_prefix($isEng) . '/gallery';
	}
	return ezn_path_prefix($isEng) . '/gallery-old';
}

function gallery_per_page_default(): int
{
	return gallery_ui_is_new() ? 80 : 100;
}

function gallery_normalize_per_page(int $num): int
{
	if (gallery_ui_is_new()) {
		return gallery_per_page_default();
	}
	$allowed = [50, 100, 150, 500];
	if (!in_array($num, $allowed, true)) {
		return gallery_per_page_default();
	}
	return $num;
}

$lng = $smarty->getTemplateVars('lng');
$isEng = ezn_is_eng(is_string($lng) ? $lng : null);

$page = max(1, (int) ($_GET['page'] ?? ($_GET['p'] ?? 1)));
$num = gallery_normalize_per_page((int) ($_GET['num'] ?? 0));
$id = gallery_ui_is_new() ? 0 : max(0, (int) ($_GET['id'] ?? 0));

$from = ($page - 1) * $num;

if ($id > 0) {
	$stmt = mysqli_prepare($db, 'SELECT COUNT(*) FROM screens WHERE id_press=?');
	mysqli_stmt_bind_param($stmt, 'i', $id);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
} else {
	$z = db_select($db, 'SELECT COUNT(*) FROM screens');
}

$p = $z ? mysqli_fetch_array($z) : false;
$totalScreens = (int) ($p[0] ?? 0);
$nm_pages = $num > 0 ? (int) ceil($totalScreens / $num) : 0;
if ($nm_pages < 1) {
	$nm_pages = 1;
}
if ($page > $nm_pages) {
	$page = $nm_pages;
	$from = ($page - 1) * $num;
}

$pg = [];
for ($n = 0; $n < $nm_pages; $n++) {
	$pg[] = $n + 1;
}

$smarty->assign('pages', $pg);
$smarty->assign('tk_page', $page);
$smarty->assign('gallery_page', $page);
$smarty->assign('gallery_total_pages', $nm_pages);
$smarty->assign('gallery_total', $totalScreens);
$smarty->assign('gallery_prev_page', $page > 1 ? $page - 1 : 0);
$smarty->assign('gallery_next_page', $page < $nm_pages ? $page + 1 : 0);

$gallerySql = 'SELECT screens.id AS gallery_screen_id, screens.type AS gallery_screen_type, screens.format AS gallery_format, '
	. 'issue.id AS gallery_issue_id, issue.title AS gallery_issue_title, issue.date AS gallery_issue_date, '
	. 'press.id AS gallery_press_id, press.title AS gallery_press_title, press.type AS gallery_press_type '
	. 'FROM screens INNER JOIN issue ON issue.id = screens.id_issue INNER JOIN press ON press.id = screens.id_press ';

if ($id > 0) {
	$orderBy = 'ORDER BY LENGTH(issue.title) DESC, issue.title DESC, screens.type ASC';
	$stmt = mysqli_prepare($db, $gallerySql . 'WHERE press.id=? ' . $orderBy . ' LIMIT ?, ?');
	mysqli_stmt_bind_param($stmt, 'iii', $id, $from, $num);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
} else {
	// New gallery: by issue date (newest first); classic: alphabetical by press.
	$orderBy = gallery_ui_is_new()
		? 'ORDER BY (issue.date = 0) ASC, issue.date DESC, press.title ASC, LENGTH(issue.title) DESC, issue.title DESC, screens.type ASC'
		: 'ORDER BY press.title ASC, LENGTH(issue.title) ASC, issue.title ASC, screens.type ASC';
	$stmt = mysqli_prepare($db, $gallerySql . $orderBy . ' LIMIT ?, ?');
	mysqli_stmt_bind_param($stmt, 'ii', $from, $num);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
}

$c = [];
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['gallery_press_title_plain'] = title_plain((string) ($t['gallery_press_title'] ?? ''));
	$t['gallery_issue_title_plain'] = title_plain((string) ($t['gallery_issue_title'] ?? ''));
	// Avoid "CAFe'2003 #CAFe'2003" when issue title duplicates the press name.
	if ($t['gallery_issue_title_plain'] === '' || $t['gallery_issue_title_plain'] === $t['gallery_press_title_plain']) {
		$t['gallery_label_plain'] = $t['gallery_press_title_plain'];
	} else {
		$t['gallery_label_plain'] = $t['gallery_press_title_plain'] . ' #' . $t['gallery_issue_title_plain'];
	}
	$pressType = (int) ($t['gallery_press_type'] ?? 0);
	if ($pressType === 0) {
		$t['gallery_type_label'] = $isEng ? 'Newspaper' : 'Газета';
	} elseif ($pressType === 2) {
		$t['gallery_type_label'] = $isEng ? 'Report' : 'Отчёт';
	} else {
		$t['gallery_type_label'] = $isEng ? 'Magazine' : 'Журнал';
	}
	$t['gallery_alt'] = $t['gallery_label_plain'] . ' — ' . $t['gallery_type_label']
		. ($isEng ? ' for ZX Spectrum' : ' для ZX Spectrum');
	$t['gallery_issue_url'] = '/issue.php?id=' . (int) $t['gallery_press_id']
		. ($isEng ? '&lng=eng' : '')
		. '#' . (string) ($t['gallery_issue_title'] ?? '');
	$t['gallery_img_src'] = '/screens/1/' . (int) $t['gallery_screen_id'] . '.png';
	$c[] = $t;
}
$smarty->assign('screens', $c);

$smarty->assign('id', $id);
$smarty->assign('num', $num);

$catalogUrl = gallery_url_catalog($isEng, gallery_ui_is_new());
$smarty->assign('gallery_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', gallery_ui_is_new());
$smarty->assign('url_rus', htmlspecialchars(gallery_url_catalog(false, gallery_ui_is_new()), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(gallery_url_catalog(true, gallery_ui_is_new()), ENT_QUOTES, 'UTF-8'));

if ($isEng) {
	$smarty->assign('title', 'Gallery of electronic newspapers and magazines for ZX Spectrum');
	$smarty->assign('description', 'Screenshots of ZX Spectrum electronic newspapers and magazines.');
} else {
	$smarty->assign('title', 'Галерея электронных газет и журналов для ZX Spectrum');
	$smarty->assign('description', 'Скриншоты электронных газет и журналов для ZX Spectrum.');
}

if (!gallery_ui_is_new()) {
	include __DIR__ . '/right.php';
}

$smarty->display(gallery_ui_template());
