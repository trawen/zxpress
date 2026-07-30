<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_category_images.php';
require_once __DIR__ . '/includes/ezine_categories.php';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function categories_ui_is_new(): bool
{
	return defined('CATEGORIES_UI_VARIANT') && CATEGORIES_UI_VARIANT === 'new';
}

function categories_ui_template(): string
{
	return categories_ui_is_new() ? 'categories_new.tpl' : 'ezine_categories.tpl';
}

function categories_page_url(bool $isEng, int $id = 0, bool $titleOnly = false): string
{
	if (categories_ui_is_new()) {
		return ec_public_category_url($id, $isEng, $titleOnly);
	}

	$base = '/ezine-categories.php';
	$qs = [];
	if ($id > 0) {
		$qs['id'] = $id;
	}
	if ($isEng) {
		$qs['lng'] = 'eng';
	}
	if ($titleOnly) {
		$qs['title'] = '1';
	}
	if ($qs === []) {
		return $base;
	}

	return $base . '?' . http_build_query($qs);
}

/**
 * @param list<array<string, mixed>> $nodes
 * @return list<array<string, mixed>>
 */
function categories_enrich_tree(array $nodes, bool $isEng, ?string $lng, bool $titleOnly): array
{
	$out = [];
	foreach ($nodes as $node) {
		$cid = (int) ($node['id'] ?? 0);
		$node['display_name'] = ec_cat_name($node, $lng);
		$node['public_url'] = categories_page_url($isEng, $cid, $titleOnly);
		if (!empty($node['tree']) && is_array($node['tree'])) {
			$node['tree'] = categories_enrich_tree($node['tree'], $isEng, $lng, $titleOnly);
		}
		$out[] = $node;
	}

	return $out;
}

$id = (int) ($_GET['id'] ?? 0);
$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');
$titleOnly = isset($_GET['title']) && (string) $_GET['title'] === '1';
$smarty->assign('title_only', $titleOnly);

$catalogUrl = categories_page_url($isEng, 0, false);
$smarty->assign('categories_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', false);
$smarty->assign('smn_nav_guestbook_active', false);
$smarty->assign('smn_nav_updates_active', false);
$smarty->assign('smn_nav_categories_active', categories_ui_is_new());

$categories_raw = [];
$z = db_select($db, 'SELECT * FROM ezine_categories ORDER BY sort_order ASC, name_ru ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
	$categories_raw[] = $t;
}

$categories_by_id = [];
$by_parent = [];
foreach ($categories_raw as $row) {
	$cid = (int) ($row['id'] ?? 0);
	$categories_by_id[$cid] = $row;
	$pid = (int) ($row['parent_id'] ?? 0);
	$by_parent[$pid][] = $row;
}

$category_tree = $id === 0 ? ec_build_category_tree($by_parent, 0) : [];
if (categories_ui_is_new() && $category_tree !== []) {
	$category_tree = categories_enrich_tree($category_tree, $isEng, $lng, $titleOnly);
}
$smarty->assign('category_tree', $category_tree);

$category = null;
$category_not_found = false;
$articles = [];
$breadcrumbs = [];

if ($id > 0) {
	if (!isset($categories_by_id[$id])) {
		$category_not_found = true;
		http_response_code(404);
		$smarty->assign('title', $isEng ? 'Category not found' : 'Категория не найдена');
	} else {
		$category = $categories_by_id[$id];
		$breadcrumbs = ec_category_breadcrumbs($categories_by_id, $id);
		foreach ($breadcrumbs as &$bc) {
			$bcId = (int) ($bc['id'] ?? 0);
			$bc['display_name'] = ec_cat_name($bc, $lng);
			$bc['public_url'] = categories_page_url($isEng, $bcId, $titleOnly);
		}
		unset($bc);

		$categoryIds = ec_category_descendant_ids($by_parent, $id);

		$z = db_select_in_ints(
			$db,
			'SELECT a.id AS id_article, a.title, a.title_eng, a.number, a.id_issue, a.id_press, '
			. 'a.slug_ru AS article_slug_ru, a.slug_en AS article_slug_en, '
			. 'i.title AS issue_title, i.date AS issue_date, '
			. 'i.slug_ru AS issue_slug_ru, i.slug_en AS issue_slug_en, '
			. 'p.title AS press_name, p.id AS press_id, '
			. 'p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en, '
			. 'eac.sort_order '
			. 'FROM ezine_article_categories eac '
			. 'INNER JOIN articles a ON a.id = eac.article_id AND a.temp = 0 '
			. 'INNER JOIN issue i ON i.id = a.id_issue '
			. 'INNER JOIN press p ON p.id = i.id_press '
			. 'WHERE eac.category_id IN (__IN__) '
			. 'ORDER BY eac.sort_order ASC, i.date DESC, a.number ASC, a.title ASC',
			$categoryIds
		);

		$lastIssue = null;
		$seenArticles = [];
		$groupIndex = 0;
		while ($z && ($t = mysqli_fetch_array($z))) {
			$articleId = (int) ($t['id_article'] ?? 0);
			if ($articleId <= 0 || isset($seenArticles[$articleId])) {
				continue;
			}
			$seenArticles[$articleId] = true;

			$issueId = (int) ($t['id_issue'] ?? 0);
			$isNewIssue = ($lastIssue !== $issueId);
			$t['show'] = $isNewIssue ? 1 : 0;
			$t['show_rule'] = ($isNewIssue && $groupIndex > 0) ? 1 : 0;
			if ($isNewIssue) {
				$lastIssue = $issueId;
				$groupIndex++;
			}

			if ($lng === 'eng') {
				$t['date'] = date('d F Y', (int) ($t['issue_date'] ?? 0));
			} else {
				$t['date'] = date('d ' . ($months[date('m', (int) ($t['issue_date'] ?? 0))] ?? '') . ' Y', (int) ($t['issue_date'] ?? 0));
			}

			$t['title_list'] = article_title_list_html($t['title'] ?? '');
			$t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
			$t['press_name_plain'] = title_plain($t['press_name'] ?? '');
			$t['issue_title_plain'] = title_plain($t['issue_title'] ?? '');

			$pressRow = [
				'id' => (int) ($t['press_id'] ?? 0),
				'title' => (string) ($t['press_name'] ?? ''),
				'slug_ru' => (string) ($t['press_slug_ru'] ?? ''),
				'slug_en' => (string) ($t['press_slug_en'] ?? ''),
			];
			$issueRow = [
				'id' => $issueId,
				'title' => (string) ($t['issue_title'] ?? ''),
				'slug_ru' => (string) ($t['issue_slug_ru'] ?? ''),
				'slug_en' => (string) ($t['issue_slug_en'] ?? ''),
			];
			$articleRow = [
				'id' => $articleId,
				'title' => (string) ($t['title'] ?? ''),
				'title_eng' => (string) ($t['title_eng'] ?? ''),
				'slug_ru' => (string) ($t['article_slug_ru'] ?? ''),
				'slug_en' => (string) ($t['article_slug_en'] ?? ''),
			];

			if (categories_ui_is_new()) {
				$t['issue_public_url'] = ezn_url_issue($pressRow, $issueRow, $isEng);
				$t['article_public_url'] = ezn_url_article($pressRow, $issueRow, $articleRow, $isEng);
			} else {
				$t['issue_public_url'] = '/issue.php?id=' . (int) $pressRow['id']
					. ($isEng ? '&lng=eng' : '')
					. '#' . rawurlencode((string) ($t['issue_title'] ?? ''));
				$t['article_public_url'] = '/article.php?id=' . $articleId
					. ($isEng ? '&lng=eng' : '');
			}

			$articles[] = $t;
		}

		$smarty->assign('title', ec_cat_title($category, $lng));
		$smarty->assign('description', ec_cat_meta_description($category, $lng));
	}
} else {
	if ($lng === 'eng') {
		$smarty->assign('title', 'Electronic newspaper and magazine categories');
		$smarty->assign('description', 'Categories of articles from ZX Spectrum magazines and newspapers.');
	} else {
		$smarty->assign('title', 'Категории электронных газет и журналов');
		$smarty->assign('description', 'Категории статей из журналов и газет для ZX Spectrum.');
	}
}

$smarty->assign('category_id', $id);
$smarty->assign('category', $category);
$smarty->assign('category_not_found', $category_not_found);
$smarty->assign('category_breadcrumbs', $breadcrumbs);
$smarty->assign('category_articles', $articles);

if ($category) {
	$smarty->assign('category_title', ec_cat_title($category, $lng));
	$smarty->assign('category_description_html', ec_cat_description_html($category, $lng));
	if (ec_category_has_public_image((int) ($category['id'] ?? 0))) {
		$smarty->assign('category_image_url', ec_category_public_image_url((int) $category['id']));
	} else {
		$smarty->assign('category_image_url', '');
	}
}

$smarty->assign('url_rus', htmlspecialchars(categories_page_url(false, $id, $titleOnly), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(categories_page_url(true, $id, $titleOnly), ENT_QUOTES, 'UTF-8'));

if (!categories_ui_is_new()) {
	include __DIR__ . '/right.php';
}

$smarty->display(categories_ui_template());
