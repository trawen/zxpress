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

/** Public catalog: /{lang}/categories */
function ec_public_catalog_url(bool $isEng): string
{
    require_once __DIR__ . '/ezine_slugs.php';

    return ezn_path_prefix($isEng) . '/categories';
}

/** Public category: /{lang}/categories/{id} */
function ec_public_category_url(int $id, bool $isEng, bool $titleOnly = false): string
{
    $url = ec_public_catalog_url($isEng);
    if ($id > 0) {
        $url .= '/' . $id;
    }
    if ($titleOnly) {
        $url .= '?title=1';
    }

    return $url;
}

/**
 * Breadcrumb of one category branch for a public article.
 *
 * @return list<array{id: int, name: string, public_url: string}>
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

    $isEng = ($lng === 'eng');
    $leafId = ec_pick_deepest_linked_category($linkedIds, $byId);
    $branch = [];
    foreach (ec_category_breadcrumbs($byId, $leafId) as $row) {
        $cid = (int) ($row['id'] ?? 0);
        $branch[] = [
            'id' => $cid,
            'name' => ec_cat_name($row, $lng),
            'public_url' => ec_public_category_url($cid, $isEng),
        ];
    }

    return $branch;
}

/**
 * Up to $limit other articles from the same (deepest) category as $articleId.
 *
 * @return array{category: ?array{id:int,name:string,public_url:string}, articles: list<array<string,mixed>>}
 */
function ec_article_related_from_same_category(mysqli $db, int $articleId, ?string $lng, int $limit = 5): array
{
    $empty = ['category' => null, 'articles' => []];
    if ($articleId <= 0 || $limit <= 0) {
        return $empty;
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
        return $empty;
    }

    $byId = [];
    $zAll = db_select($db, 'SELECT * FROM ezine_categories');
    while ($zAll && ($row = mysqli_fetch_assoc($zAll))) {
        $byId[(int) ($row['id'] ?? 0)] = $row;
    }

    $leafId = ec_pick_deepest_linked_category($linkedIds, $byId);
    if ($leafId <= 0 || !isset($byId[$leafId])) {
        return $empty;
    }

    $isEng = ($lng === 'eng');
    $catRow = $byId[$leafId];
    $category = [
        'id' => $leafId,
        'name' => ec_cat_name($catRow, $lng),
        'public_url' => ec_public_category_url($leafId, $isEng),
    ];

    require_once __DIR__ . '/ezine_slugs.php';

    $stmt = $db->prepare(
        'SELECT a.id, a.title, a.title_eng, a.number, a.id_issue, a.id_press, '
        . 'a.slug_ru, a.slug_en, '
        . 'i.title AS issue_title, i.date AS issue_date, '
        . 'i.slug_ru AS issue_slug_ru, i.slug_en AS issue_slug_en, '
        . 'p.title AS press_title, '
        . 'p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en '
        . 'FROM ezine_article_categories eac '
        . 'INNER JOIN articles a ON a.id = eac.article_id AND a.temp = 0 AND a.id <> ? '
        . 'INNER JOIN issue i ON i.id = a.id_issue '
        . 'INNER JOIN press p ON p.id = i.id_press '
        . 'WHERE eac.category_id = ? '
        . 'ORDER BY eac.sort_order ASC, i.date DESC, a.number ASC, a.title ASC '
        . 'LIMIT ?'
    );
    if (!$stmt) {
        return ['category' => $category, 'articles' => []];
    }
    $stmt->bind_param('iii', $articleId, $leafId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $articles = [];
    while ($res && ($t = $res->fetch_assoc())) {
        $pressRow = [
            'id' => (int) ($t['id_press'] ?? 0),
            'title' => (string) ($t['press_title'] ?? ''),
            'slug_ru' => (string) ($t['press_slug_ru'] ?? ''),
            'slug_en' => (string) ($t['press_slug_en'] ?? ''),
        ];
        $issueRow = [
            'id' => (int) ($t['id_issue'] ?? 0),
            'id_press' => (int) ($t['id_press'] ?? 0),
            'title' => (string) ($t['issue_title'] ?? ''),
            'slug_ru' => (string) ($t['issue_slug_ru'] ?? ''),
            'slug_en' => (string) ($t['issue_slug_en'] ?? ''),
        ];
        $articles[] = [
            'id' => (int) ($t['id'] ?? 0),
            'title_html' => article_title_list_html($t['title'] ?? ''),
            'title_eng_html' => article_title_list_html($t['title_eng'] ?? ''),
            'press_name_plain' => title_plain($t['press_title'] ?? ''),
            'issue_title' => (string) ($t['issue_title'] ?? ''),
            'public_url' => ezn_url_article($pressRow, $issueRow, $t, $isEng),
        ];
    }
    $stmt->close();

    return ['category' => $category, 'articles' => $articles];
}
