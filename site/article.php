<?php

error_reporting(E_ALL);


require 'init.inc';
require_once __DIR__ . '/includes/ezine_categories.php';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/comments_scope.php';

function article_public_meta_description(array $article, ?string $lng): string
{
	$metaRu = title_plain((string) ($article['meta_description_ru'] ?? ''));
	$metaEn = title_plain((string) ($article['meta_description_en'] ?? ''));
	if ($lng === 'eng') {
		if ($metaEn !== '') {
			return $metaEn;
		}
		if ($metaRu !== '') {
			return $metaRu;
		}
		$titleEng = title_plain((string) ($article['title_eng'] ?? ''));
		if ($titleEng !== '') {
			return $titleEng;
		}
		return title_plain((string) ($article['title'] ?? ''));
	}
	if ($metaRu !== '') {
		return $metaRu;
	}
	return title_plain((string) ($article['title'] ?? ''));
}

function article_show_not_found($smarty): void {
	global $db, $article_breadcrumbs;
	if (!is_array($article_breadcrumbs)) {
		$article_breadcrumbs = [];
	}
	http_response_code(404);
	$smarty->assign('article', null);
	$smarty->assign('article_not_found', true);
	$smarty->assign('title', 'Статья не найдена');
	if (!article_ui_is_new()) {
		include 'right.php';
	}
	$smarty->display(article_ui_template());
	exit;
}

function article_ui_is_new(): bool
{
	return defined('EZINES_UI_VARIANT') && EZINES_UI_VARIANT === 'new';
}

function article_ui_template(): string
{
	return article_ui_is_new() ? 'article_new.tpl' : 'article.tpl';
}

function article_ui_render($smarty, bool $isEng): void
{
	$catalogUrl = article_ui_is_new() ? ezn_url_catalog_new($isEng) : ezn_url_catalog($isEng);
	$smarty->assign('ezines_catalog_url', $catalogUrl);
	$smarty->assign('ezines_classic_url', ezn_url_catalog($isEng));
	$smarty->assign('letters_catalog_url', ezn_path_prefix($isEng) . '/snailmail-new');
	$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
	$smarty->assign('smn_nav_authors_active', false);
	$smarty->assign('smn_nav_ezines_active', true);

	if (!article_ui_is_new()) {
		global $db;
		include __DIR__ . '/right.php';
	}
	$smarty->display(article_ui_template());
}

$pressSlug = per_slug_normalize_path((string) ($_GET['press_slug'] ?? ''));
$issueSlug = per_slug_normalize_path((string) ($_GET['issue_slug'] ?? ''));
$articleSlug = per_slug_normalize_path((string) ($_GET['article_slug'] ?? ''));
$slugRoute = ($pressSlug !== '' && $issueSlug !== '' && $articleSlug !== '');

$id = (int) ($_GET['id'] ?? 0);
$isEng = ($smarty->getTemplateVars('lng') === 'eng');

if ($slugRoute) {
	$resolved = ezn_resolve_article_route($db, $pressSlug, $issueSlug, $articleSlug, $isEng);
	if (!$resolved['ok']) {
		article_show_not_found($smarty);
	}
	$id = (int) $resolved['article_id'];
} elseif ($id > 0) {
	ezn_maybe_redirect_article_legacy($db, $id, $isEng);
} else {
	article_show_not_found($smarty);
}

$smarty->assign('id_article', $id);
$smarty->assign('id', $id);

$stmt = mysqli_prepare($db, "SELECT * FROM articles WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_array($z);

if (!is_array($article)) {
	article_show_not_found($smarty);
}

get_parents($id,1);
$smarty->assign('breadcrumbs',  array_reverse($article_breadcrumbs) );

$stmt = mysqli_prepare($db, "SELECT * FROM articles, issue WHERE articles.id=? AND issue.id=articles.id_issue");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$issue = mysqli_fetch_array($z);
if (!is_array($issue)) {
	article_show_not_found($smarty);
}

$dateTs = (int) ($issue['date'] ?? 0);
$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');
if ($dateTs > 0) {
	$issue['date'] = $isEng
		? date('d F Y', $dateTs)
		: date('d ' . ($months[date('m', $dateTs)] ?? '') . ' Y', $dateTs);
} else {
	$issue['date'] = '';
}

$smarty->assign('issue', $issue);
$id_issue = intval($issue['id']);
$id_press = intval($issue['id_press']);

$stmt = mysqli_prepare($db, "SELECT * FROM screens WHERE id_press=? AND id_issue=? ORDER BY type ASC LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $id_press, $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);

$screens = mysqli_fetch_array($z);

if ($screens == NULL) {
	$stmt = mysqli_prepare($db, "SELECT * FROM screens WHERE id_press=? ORDER BY type ASC LIMIT 1");
	mysqli_stmt_bind_param($stmt, "i", $id_press);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
	$screens = mysqli_fetch_array($z);
}

$smarty->assign('screens', $screens);


$stmt = mysqli_prepare($db, "SELECT issue.id, issue.title, issue.slug_ru, issue.slug_en, issue.id_press, press.id AS press_id, press.title AS press_title, press.slug_ru AS press_slug_ru, press.slug_en AS press_slug_en, cities.* FROM issue INNER JOIN press ON press.id=issue.id_press LEFT JOIN cities ON press.city=cities.id WHERE issue.id=?");
mysqli_stmt_bind_param($stmt, "i", $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$press = mysqli_fetch_array($z);
if (is_array($press)) {
	$press['id'] = (int) ($press['press_id'] ?? $press['id'] ?? 0);
	$press['title'] = (string) ($press['press_title'] ?? $press['title'] ?? '');
	$press['slug_ru'] = (string) ($press['press_slug_ru'] ?? '');
	$press['slug_en'] = (string) ($press['press_slug_en'] ?? '');
	$press['title_plain'] = title_plain($press['title']);
}
$smarty->assign('press', $press);



$article['name_plain'] = title_plain($article['name'] ?? '');
	$aid = (int)$article['id'];
	if ($_GET['lng'] == 'eng' && $id <= 10948) {
		$baseDir = realpath(zx_storage_dir('articles_eng'));
	} else {
		$baseDir = realpath(zx_storage_dir('articles'));
	}
	$article['text'] = '';
	if ($baseDir !== false) {
		$candidate = $baseDir . '/' . $aid;
		$resolved = realpath($candidate);
		if ($resolved !== false && is_file($resolved)
			&& strpos($resolved, $baseDir . DIRECTORY_SEPARATOR) === 0) {
			$article['text'] = ezn_article_root_urls((string) file_get_contents($resolved));
		}
	}

	$article['title_plain_meta'] = title_plain($article['title'] ?? '');
	$article['title_eng_plain_meta'] = title_plain($article['title_eng'] ?? '');
	$article['title_html'] = article_title_list_html($article['title'] ?? '');
	$article['title_eng_html'] = article_title_list_html($article['title_eng'] ?? '');
	if (strpos($article['title_html'], '&amp;#') !== false || strpos($article['title_eng_html'], '&amp;#') !== false) {
		static $zxpress_title_entity_diag = 0;
		if ($zxpress_title_entity_diag < 6) {
			error_log('[FIX] article.php suspicious escaped entity in rendered title id=' . $aid);
			$zxpress_title_entity_diag++;
		}
	}

	if ($article['text'] !== '' && strpos($article['text'], '<') !== false) {
		static $zxpress_article_markup_diag = 0;
		if ($zxpress_article_markup_diag < 6 && getenv('LOG_LEVEL') === 'DEBUG') {
			error_log('[FIX] article.php HTML body loaded id=' . $aid . ' len=' . strlen($article['text']) . ' (article.tpl uses nofilter)');
			$zxpress_article_markup_diag++;
		}
	}

if ((int) ($article['temp'] ?? 0) === 0) {
	$hasTitle = title_plain($article['title'] ?? '') !== ''
		|| title_plain($article['title_eng'] ?? '') !== '';
	$hasBody = title_plain(strip_tags($article['text'] ?? '')) !== '';
	if (!$hasTitle && !$hasBody) {
		article_show_not_found($smarty);
	}
}

$smarty->assign('article', $article);
$smarty->assign('article_not_found', false);

$pressRow = [
	'id' => $id_press,
	'title' => (string) ($press['title'] ?? ''),
	'slug_ru' => (string) ($press['press_slug_ru'] ?? $press['slug_ru'] ?? ''),
	'slug_en' => (string) ($press['press_slug_en'] ?? $press['slug_en'] ?? ''),
];
$issueRow = [
	'id' => $id_issue,
	'id_press' => $id_press,
	'title' => (string) ($issue['title'] ?? ''),
	'slug_ru' => (string) ($issue['slug_ru'] ?? ''),
	'slug_en' => (string) ($issue['slug_en'] ?? ''),
];
$smarty->assign('press_public_url', ezn_url_press($pressRow, $isEng));
$smarty->assign('issue_public_url', ezn_url_issue($pressRow, $issueRow, $isEng));
$smarty->assign('article_public_url', ezn_url_article($pressRow, $issueRow, $article, $isEng));
ezn_assign_lang_switch_urls($smarty, $pressRow, $issueRow, $article);

$smarty->assign('title', title_plain($article['title'] ?? ''));
$articleDescPlain = article_public_meta_description($article, $smarty->getTemplateVars('lng'));
$smarty->assign('description', $articleDescPlain);

$origin = zxpress_canonical_origin();
$articleCanonical = ezn_url_article($pressRow, $issueRow, $article, $isEng);
$smarty->assign('canonical_url', $origin . $articleCanonical);
$smarty->assign('hreflang_ru', $origin . ezn_url_for_lang(false, $pressRow, $issueRow, $article));
$smarty->assign('hreflang_en', $origin . ezn_url_for_lang(true, $pressRow, $issueRow, $article));
$smarty->assign('og_title', $article['title_plain_meta'] ?? title_plain($article['title'] ?? ''));
$smarty->assign('og_description', $articleDescPlain);
$smarty->assign('og_type', 'article');
$smarty->assign('og_url', $origin . $articleCanonical);

$lng = $smarty->getTemplateVars('lng');
$smarty->assign('ezine_category_branch', ec_article_public_category_branch($db, $id, $lng));

$relatedCategory = null;
$relatedCategoryArticles = [];
if (article_ui_is_new()) {
	$relatedBundle = ec_article_related_from_same_category($db, $id, $lng, 5);
	$relatedCategory = $relatedBundle['category'];
	$relatedCategoryArticles = $relatedBundle['articles'];
}
$smarty->assign('related_category', $relatedCategory);
$smarty->assign('related_category_articles', $relatedCategoryArticles);

 //TAGS (hidden for now)
/*
$stmt = mysqli_prepare($db, "SELECT *, tags.id AS id FROM tags, tags_articles WHERE tags_articles.id_article=? AND tags.id=tags_articles.id_tag ORDER BY tags.`count` DESC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {

	if ($tags) {$atg .=",";}

	$tags[] = $t;
	$atg .= $t['id'];

}
$smarty->assign('tags', $tags);
*/


// other articles from issue
$stmt = mysqli_prepare($db, "SELECT * FROM articles WHERE id_issue=? ORDER BY number, title");
mysqli_stmt_bind_param($stmt, "i", $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {

	if ($id == $t['id']) {$t['current'] = 1;}
	$t['title_html'] = article_title_list_html($t['title'] ?? '');
	$t['title_eng_html'] = article_title_list_html($t['title_eng'] ?? '');
	$t['public_url'] = ezn_url_article($pressRow, $issueRow, $t, $isEng);
	$art[] = $t;

}
$smarty->assign('other_articles', $art);


if (!$skip) {
	$stmt = mysqli_prepare($db, "UPDATE articles SET views=views+1 WHERE id=?");
	mysqli_stmt_bind_param($stmt, "i", $id);
	mysqli_stmt_execute($stmt);
}

if (article_ui_is_new()) {
	$comments_target_id = comments_id_ezine_article((int) $id);
	$comments_target_ids = comments_ezine_article_read_ids($db, (int) $id);
	$smarty->assign('comments_enabled', true);
	$smarty->assign('comments_form_action', ezn_url_article($pressRow, $issueRow, $article, $isEng));
	$smarty->assign('comments_invite', $isEng
		? 'Share your thoughts about the article'
		: 'Поделитесь вашим мнением о статье');
	require __DIR__ . '/comments.php';
} else {
	$smarty->assign('comments_enabled', false);
}

article_ui_render($smarty, $isEng);
?>
