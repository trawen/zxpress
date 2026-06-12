<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_category_images.php';

function ec_cat_name(array $row, ?string $lng): string
{
    if ($lng === 'eng') {
        $en = trim((string) ($row['name_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    }

    return (string) ($row['name_ru'] ?? '');
}

function ec_cat_title(array $row, ?string $lng): string
{
    if ($lng === 'eng') {
        $en = trim((string) ($row['title_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    } else {
        $ru = trim((string) ($row['title_ru'] ?? ''));
        if ($ru !== '') {
            return $ru;
        }
    }

    return ec_cat_name($row, $lng);
}

function ec_cat_description(array $row, ?string $lng): string
{
    if ($lng === 'eng') {
        $en = trim((string) ($row['description_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    }

    return trim((string) ($row['description_ru'] ?? ''));
}

function ec_cat_description_html(array $row, ?string $lng): string
{
    $s = ec_cat_description($row, $lng);
    if ($s === '') {
        return '';
    }

    return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function ec_cat_meta_description(array $row, ?string $lng): string
{
    $metaRu = title_plain((string) ($row['meta_description_ru'] ?? ''));
    $metaEn = title_plain((string) ($row['meta_description_en'] ?? ''));
    if ($lng === 'eng') {
        if ($metaEn !== '') {
            return $metaEn;
        }
        if ($metaRu !== '') {
            return $metaRu;
        }
        return ec_cat_name($row, $lng);
    }
    if ($metaRu !== '') {
        return $metaRu;
    }

    return ec_cat_name($row, 'ru');
}

/** @param array<int, list<array<string, mixed>>> $byParent */
function ec_build_category_tree(array $byParent, int $parentId): array
{
    $tree = [];
    foreach ($byParent[$parentId] ?? [] as $row) {
        $cid = (int) ($row['id'] ?? 0);
        $childTree = ec_build_category_tree($byParent, $cid);
        if ($childTree !== []) {
            $row['tree'] = $childTree;
        }
        $tree[] = $row;
    }
    if ($tree !== []) {
        $tree[count($tree) - 1]['last'] = 1;
    }

    return $tree;
}

/** @param array<int, list<array<string, mixed>>> $byParent */
function ec_category_descendant_ids(array $byParent, int $categoryId): array
{
    $ids = [$categoryId];
    foreach ($byParent[$categoryId] ?? [] as $child) {
        $cid = (int) ($child['id'] ?? 0);
        if ($cid > 0) {
            $ids = array_merge($ids, ec_category_descendant_ids($byParent, $cid));
        }
    }

    return $ids;
}

/** @param array<int, array<string, mixed>> $byId */
function ec_category_breadcrumbs(array $byId, int $id): array
{
    $crumbs = [];
    $cur = $id;
    $guard = 0;
    while ($cur > 0 && isset($byId[$cur]) && $guard < 32) {
        array_unshift($crumbs, $byId[$cur]);
        $cur = (int) ($byId[$cur]['parent_id'] ?? 0);
        $guard++;
    }

    return $crumbs;
}

$id = (int) ($_GET['id'] ?? 0);
$lng = $smarty->getTemplateVars('lng');
$titleOnly = isset($_GET['title']) && (string) $_GET['title'] === '1';
$smarty->assign('title_only', $titleOnly);

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
$smarty->assign('category_tree', $category_tree);

$category = null;
$category_not_found = false;
$articles = [];
$breadcrumbs = [];

if ($id > 0) {
    if (!isset($categories_by_id[$id])) {
        $category_not_found = true;
        http_response_code(404);
        $smarty->assign('title', 'Категория не найдена');
    } else {
        $category = $categories_by_id[$id];
        $breadcrumbs = ec_category_breadcrumbs($categories_by_id, $id);
        $categoryIds = ec_category_descendant_ids($by_parent, $id);

        $z = db_select_in_ints(
            $db,
            'SELECT a.id AS id_article, a.title, a.title_eng, a.number, a.id_issue, a.id_press, '
            . 'i.title AS issue_title, i.date AS issue_date, '
            . 'p.title AS press_name, p.id AS press_id, eac.sort_order '
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
        while ($z && ($t = mysqli_fetch_array($z))) {
            $articleId = (int) ($t['id_article'] ?? 0);
            if ($articleId <= 0 || isset($seenArticles[$articleId])) {
                continue;
            }
            $seenArticles[$articleId] = true;

            $issueId = (int) ($t['id_issue'] ?? 0);
            $t['show'] = ($lastIssue !== $issueId) ? 1 : 0;
            $lastIssue = $issueId;

            if ($lng === 'eng') {
                $t['date'] = date('d F Y', (int) ($t['issue_date'] ?? 0));
            } else {
                $t['date'] = date('d ' . $months[date('m', (int) ($t['issue_date'] ?? 0))] . ' Y', (int) ($t['issue_date'] ?? 0));
            }

            $t['title_list'] = article_title_list_html($t['title'] ?? '');
            $t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
            $t['press_name_plain'] = title_plain($t['press_name'] ?? '');
            $t['issue_title_plain'] = title_plain($t['issue_title'] ?? '');

            $articles[] = $t;
        }

        $smarty->assign('title', ec_cat_title($category, $lng));
        $smarty->assign('description', ec_cat_meta_description($category, $lng));
    }
} else {
    if ($lng === 'eng') {
        $smarty->assign('title', 'Ezine article categories');
        $smarty->assign('description', 'Categories of articles from ZX Spectrum magazines and newspapers.');
    } else {
        $smarty->assign('title', 'Категории статей журналов');
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

include 'right.php';
$smarty->display('ezine_categories.tpl');
