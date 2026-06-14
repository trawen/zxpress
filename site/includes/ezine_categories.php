<?php

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

/** @param array<int, array<string, mixed>> $byId */
function ec_category_depth(array $byId, int $categoryId): int
{
    $depth = 0;
    $cur = $categoryId;
    $guard = 0;
    while ($cur > 0 && isset($byId[$cur]) && $guard < 32) {
        $depth++;
        $cur = (int) ($byId[$cur]['parent_id'] ?? 0);
        $guard++;
    }

    return $depth;
}

/**
 * Pick the deepest linked category; tie-break by sort_order, then id.
 *
 * @param list<int> $linkedIds
 * @param array<int, array<string, mixed>> $byId
 */
function ec_pick_deepest_linked_category(array $linkedIds, array $byId): int
{
    $pick = $linkedIds[0];
    $pickDepth = -1;
    $pickSort = 256;
    foreach ($linkedIds as $cid) {
        $depth = ec_category_depth($byId, $cid);
        $sort = (int) ($byId[$cid]['sort_order'] ?? 0);
        if ($depth > $pickDepth || ($depth === $pickDepth && $sort < $pickSort) || ($depth === $pickDepth && $sort === $pickSort && $cid < $pick)) {
            $pickDepth = $depth;
            $pickSort = $sort;
            $pick = $cid;
        }
    }

    return $pick;
}

/**
 * Breadcrumb of one category branch for a public article.
 *
 * @return list<array{id: int, name: string}>
 */
function ec_article_public_category_branch(mysqli $db, int $articleId, ?string $lng): array
{
    if ($articleId <= 0) {
        return [];
    }

    $linkedIds = [];
    $z = db_select(
        $db,
        'SELECT category_id FROM ezine_article_categories WHERE article_id=? ORDER BY sort_order ASC, category_id ASC',
        'i',
        $articleId
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $cid = (int) ($row['category_id'] ?? 0);
        if ($cid > 0) {
            $linkedIds[] = $cid;
        }
    }
    if ($linkedIds === []) {
        return [];
    }

    $byId = [];
    $zAll = db_select($db, 'SELECT * FROM ezine_categories');
    while ($zAll && ($row = mysqli_fetch_assoc($zAll))) {
        $byId[(int) ($row['id'] ?? 0)] = $row;
    }

    $leafId = ec_pick_deepest_linked_category($linkedIds, $byId);
    $branch = [];
    foreach (ec_category_breadcrumbs($byId, $leafId) as $row) {
        $branch[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => ec_cat_name($row, $lng),
        ];
    }

    return $branch;
}
