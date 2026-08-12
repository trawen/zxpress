<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function updates_ui_is_new(): bool
{
	return true;
}

function updates_ui_template(): string
{
	return 'updates_new.tpl';
}

function updates_url(bool $isEng, int $page = 1): string
{
	$base = ezn_path_prefix($isEng) . '/updates';
	$qs = [];
	if ($page > 1) {
		$qs['page'] = $page;
	}
	if ($qs === []) {
		return $base;
	}
	return $base . '?' . http_build_query($qs);
}

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');
$page = max(1, (int) ($_GET['page'] ?? 1));

$catalogUrl = updates_url($isEng, 1);
$smarty->assign('updates_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', false);
$smarty->assign('smn_nav_guestbook_active', false);
$smarty->assign('smn_nav_updates_active', true);

$num = 250;
$from = ($page - 1) * $num;

$z = db_select(
	$db,
	'SELECT COUNT(*) FROM log '
		. 'INNER JOIN articles ON articles.id = log.id_article '
		. 'WHERE log.type = 1 AND articles.temp = 0'
);
$p = $z ? mysqli_fetch_array($z) : false;
$total = (int) ($p[0] ?? 0);
$nm_pages = max(1, (int) ceil($total / $num));
if ($page > $nm_pages) {
	$page = $nm_pages;
	$from = ($page - 1) * $num;
}

$pages = [];
for ($n = 1; $n <= $nm_pages; $n++) {
	$pages[] = $n;
}
$smarty->assign('pages', $pages);
$smarty->assign('tk_page', $page);
$smarty->assign('updates_total_pages', $nm_pages);

$z = db_select(
	$db,
	'SELECT log.date AS log_date, '
		. 'articles.id AS article_id, articles.title, articles.title_eng, '
		. 'articles.slug_ru AS article_slug_ru, articles.slug_en AS article_slug_en, '
		. 'press.id AS press_id, press.title AS press_title, '
		. 'press.slug_ru AS press_slug_ru, press.slug_en AS press_slug_en, '
		. 'issue.id AS issue_id, issue.title AS issue_title, '
		. 'issue.slug_ru AS issue_slug_ru, issue.slug_en AS issue_slug_en '
		. 'FROM log '
		. 'INNER JOIN press ON press.id = log.id_press '
		. 'INNER JOIN issue ON issue.id = log.id_issue '
		. 'INNER JOIN articles ON articles.id = log.id_article '
		. 'WHERE log.type = 1 AND articles.temp = 0 '
		. 'ORDER BY log.date DESC LIMIT ?, ?',
	'ii',
	$from,
	$num
);

$update = [];
$p_press = null;
$p_issue = null;
$last = null;
$groupIndex = 0;
while ($z && ($t = mysqli_fetch_array($z))) {
	$pressTitle = (string) ($t['press_title'] ?? '');
	$issueTitle = (string) ($t['issue_title'] ?? '');
	if ($p_press === $pressTitle && $p_issue === $issueTitle) {
		$t['print'] = 0;
		$t['show_rule'] = 0;
	} else {
		$t['print'] = 1;
		$t['show_rule'] = $groupIndex > 0 ? 1 : 0;
		$p_press = $pressTitle;
		$p_issue = $issueTitle;
		$groupIndex++;
	}

	$dateTs = (int) ($t['log_date'] ?? 0);
	$dateLabel = $isEng
		? date('j F', $dateTs)
		: (date('d ', $dateTs) . ($months[date('m', $dateTs)] ?? ''));
	if ($last !== $dateLabel) {
		$last = $dateLabel;
		$t['date'] = $dateLabel;
	} else {
		$t['date'] = '';
	}

	$titleEng = trim((string) ($t['title_eng'] ?? ''));
	$articleTitle = ($isEng && $titleEng !== '')
		? (string) ($t['title_eng'] ?? '')
		: (string) ($t['title'] ?? '');
	$t['title_list'] = article_title_list_html($articleTitle);

	// Classic template expects article id in `id`.
	$t['id'] = (int) ($t['article_id'] ?? 0);

	$pressRow = [
		'id' => (int) ($t['press_id'] ?? 0),
		'title' => $pressTitle,
		'slug_ru' => (string) ($t['press_slug_ru'] ?? ''),
		'slug_en' => (string) ($t['press_slug_en'] ?? ''),
	];
	$issueRow = [
		'id' => (int) ($t['issue_id'] ?? 0),
		'title' => $issueTitle,
		'slug_ru' => (string) ($t['issue_slug_ru'] ?? ''),
		'slug_en' => (string) ($t['issue_slug_en'] ?? ''),
	];
	$articleRow = [
		'id' => (int) ($t['article_id'] ?? 0),
		'title' => (string) ($t['title'] ?? ''),
		'title_eng' => (string) ($t['title_eng'] ?? ''),
		'slug_ru' => (string) ($t['article_slug_ru'] ?? ''),
		'slug_en' => (string) ($t['article_slug_en'] ?? ''),
	];

	$t['issue_public_url'] = ezn_url_issue($pressRow, $issueRow, $isEng);
	$t['article_public_url'] = ezn_url_article($pressRow, $issueRow, $articleRow, $isEng);

	$update[] = $t;
}
$smarty->assign('updates', $update);

$smarty->assign('title', $isEng ? 'Additions and updates on zxpress' : 'Список поступлений и обновлений на zxpress');
$smarty->assign(
	'description',
	$isEng
		? 'Additions and updates on zxpress'
		: 'Список поступлений и обновлений на zxpress'
);

$smarty->assign('url_rus', htmlspecialchars(updates_url(false, $page), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(updates_url(true, $page), ENT_QUOTES, 'UTF-8'));

$smarty->display(updates_ui_template());
