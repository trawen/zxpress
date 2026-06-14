<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_category_images.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function ec_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function ec_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function ec_nullable_text(string $value): ?string
{
    return $value !== '' ? $value : null;
}

function ec_refresh_articles_count(mysqli $db, int $categoryId): void
{
    if ($categoryId <= 0) {
        return;
    }
    db_exec(
        $db,
        'UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=?) WHERE id=? LIMIT 1',
        'ii',
        $categoryId,
        $categoryId
    );
}

function ec_category_list_label(array $cat, array $byId): string
{
    $prefix = '';
    $parentId = (int) ($cat['parent_id'] ?? 0);
    if ($parentId > 0 && isset($byId[$parentId])) {
        $prefix = $byId[$parentId]['name_ru'] . ' → ';
    }

    return $prefix . '[' . (int) ($cat['sort_order'] ?? 0) . '] ' . ($cat['name_ru'] ?? '');
}

/** @param array<int, list<array<string, mixed>>> $byParent */
function ec_build_category_tree(array $byParent, int $parentId): array
{
    $tree = [];
    $siblings = $byParent[$parentId] ?? [];
    $siblingCount = count($siblings);
    foreach ($siblings as $idx => $row) {
        $cid = (int) ($row['id'] ?? 0);
        $row['can_move_up'] = $idx > 0 ? 1 : 0;
        $row['can_move_down'] = $idx < $siblingCount - 1 ? 1 : 0;
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

/**
 * Swap sort_order with adjacent sibling category (same parent).
 */
function ec_move_category_order(mysqli $db, int $categoryId, string $direction): bool
{
    if ($categoryId <= 0 || !in_array($direction, ['up', 'down'], true)) {
        return false;
    }

    $stmt = $db->prepare('SELECT id, parent_id, sort_order FROM ezine_categories WHERE id=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$current) {
        return false;
    }

    $parentId = isset($current['parent_id']) && $current['parent_id'] !== null
        ? (int) $current['parent_id']
        : null;

    if ($parentId === null) {
        $z = db_select(
            $db,
            'SELECT id, sort_order FROM ezine_categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name_ru ASC, id ASC'
        );
    } else {
        $z = db_select(
            $db,
            'SELECT id, sort_order FROM ezine_categories WHERE parent_id=? ORDER BY sort_order ASC, name_ru ASC, id ASC',
            'i',
            $parentId
        );
    }

    $siblings = [];
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $siblings[] = [
            'id' => (int) ($row['id'] ?? 0),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    $index = null;
    foreach ($siblings as $i => $sibling) {
        if ($sibling['id'] === $categoryId) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return false;
    }

    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapIndex < 0 || $swapIndex >= count($siblings)) {
        return false;
    }

    $moved = $siblings[$index];
    $siblings[$index] = $siblings[$swapIndex];
    $siblings[$swapIndex] = $moved;

    foreach ($siblings as $i => $sibling) {
        if ($sibling['sort_order'] === $i) {
            continue;
        }
        if (!db_exec(
            $db,
            'UPDATE ezine_categories SET sort_order=? WHERE id=? LIMIT 1',
            'ii',
            $i,
            $sibling['id']
        )) {
            return false;
        }
    }

    return true;
}

/**
 * Cross-link articles between selected categories (symmetric merge).
 *
 * @param list<int> $categoryIds
 * @return array{added: int, categories: int, error: ?string}
 */
function ec_merge_category_article_links(mysqli $db, array $categoryIds): array
{
    $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static function ($id) {
        return $id > 0;
    })));
    sort($categoryIds);

    if (count($categoryIds) < 2) {
        return ['added' => 0, 'categories' => count($categoryIds), 'error' => 'Выберите не менее двух категорий'];
    }

    $z = db_select_in_ints($db, 'SELECT id FROM ezine_categories WHERE id IN (__IN__) ORDER BY id ASC', $categoryIds);
    $validIds = [];
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $validIds[] = (int) ($row['id'] ?? 0);
    }
    if (count($validIds) < 2) {
        return ['added' => 0, 'categories' => count($validIds), 'error' => 'Не найдено достаточно категорий для склейки'];
    }

    /** @var array<int, list<int>> $articlesByCategory */
    $articlesByCategory = [];
    foreach ($validIds as $cid) {
        $articlesByCategory[$cid] = [];
        $rows = db_select($db, 'SELECT article_id FROM ezine_article_categories WHERE category_id=?', 'i', $cid);
        while ($rows && ($row = mysqli_fetch_assoc($rows))) {
            $articleId = (int) ($row['article_id'] ?? 0);
            if ($articleId > 0) {
                $articlesByCategory[$cid][] = $articleId;
            }
        }
    }

    $added = 0;
    foreach ($validIds as $sourceCat) {
        foreach ($validIds as $targetCat) {
            if ($sourceCat === $targetCat) {
                continue;
            }
            foreach ($articlesByCategory[$sourceCat] as $articleId) {
                if (!db_exec(
                    $db,
                    'INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order) VALUES (?,?,0)',
                    'ii',
                    $targetCat,
                    $articleId
                )) {
                    return ['added' => $added, 'categories' => count($validIds), 'error' => 'Не удалось добавить привязку статьи'];
                }
                if (mysqli_affected_rows($db) > 0) {
                    $added++;
                }
            }
        }
    }

    foreach ($validIds as $cid) {
        ec_refresh_articles_count($db, $cid);
    }

    return ['added' => $added, 'categories' => count($validIds), 'error' => null];
}

function ec_delete_category(mysqli $db, int $categoryId): bool
{
    if ($categoryId <= 0) {
        return false;
    }

    ec_category_delete_images($categoryId);

    return db_exec($db, 'DELETE FROM ezine_categories WHERE id=? LIMIT 1', 'i', $categoryId);
}

function ec_read_article_text(int $articleId): string
{
    if ($articleId <= 0) {
        return '';
    }

    $path = zx_storage_path('articles', (string) $articleId);
    $text = @file_get_contents($path);

    return $text !== false ? $text : '';
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = null;
$merge_message = null;
$delete_message = null;

if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1') {
    $delete_message = 'Категория удалена вместе с привязками статей';
}

if (isset($_GET['serve_image'])) {
    $imgId = (int) $_GET['serve_image'];
    $img = ec_category_original_path($imgId);
    if (!$img) {
        header('HTTP/1.1 404 Not Found');
        exit;
    }

    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    $ext = $img['ext'];
    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($img['path']));
    readfile($img['path']);
    exit;
}

if (isset($_GET['article_text'])) {
    $articleId = (int) $_GET['article_text'];
    header('Content-Type: application/json; charset=utf-8');

    if ($articleId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare('SELECT id, title FROM articles WHERE id=? LIMIT 1');
    $row = null;
    if ($stmt) {
        $stmt->bind_param('i', $articleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'id' => $articleId,
        'title' => article_title_list_html($row['title'] ?? ''),
        'text' => ec_read_article_text($articleId),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['delete_category']) && (string) $_POST['delete_category'] !== '') {
    csrf_verify();

    $deleteId = (int) $_POST['delete_category'];
    if ($deleteId > 0 && ec_delete_category($db, $deleteId)) {
        $redirect = '/admin_ezine_categories.php?deleted=1';
        if ($id > 0 && $id !== $deleteId) {
            $redirect .= '&id=' . $id;
        }
        header('Location: ' . $redirect, true, 303);
        exit;
    }

    $error = 'Не удалось удалить категорию';
}

if (isset($_POST['move_category']) && (string) $_POST['move_category'] !== '') {
    csrf_verify();

    if (preg_match('/^(\d+):(up|down)$/', (string) $_POST['move_category'], $m)) {
        $moveId = (int) $m[1];
        $moveDir = (string) $m[2];
        if ($moveId > 0 && ec_move_category_order($db, $moveId, $moveDir)) {
            $redirect = '/admin_ezine_categories.php';
            if ($id > 0) {
                $redirect .= '?id=' . $id;
            }
            header('Location: ' . $redirect, true, 303);
            exit;
        }
    }

    $error = 'Не удалось изменить порядок категории';
}

if (($_POST['merge'] ?? '') === 'Склеить') {
    csrf_verify();

    $mergeCategoryIds = [];
    if (isset($_POST['merge_category_ids']) && is_array($_POST['merge_category_ids'])) {
        foreach ($_POST['merge_category_ids'] as $rawId) {
            $mergeCategoryIds[] = (int) $rawId;
        }
    }

    $mergeResult = ec_merge_category_article_links($db, $mergeCategoryIds);
    if ($mergeResult['error'] !== null) {
        $error = $mergeResult['error'];
    } else {
        $merge_message = 'Склеено категорий: ' . $mergeResult['categories']
            . ', добавлено привязок: ' . $mergeResult['added'];
    }
}

if (isset($_POST['unlink']) && (string) $_POST['unlink'] !== '') {
    csrf_verify();

    if (preg_match('/^(\d+):(\d+)$/', (string) $_POST['unlink'], $m)) {
        $unlinkCatId = (int) $m[1];
        $unlinkArticleId = (int) $m[2];
        if ($unlinkCatId > 0 && $unlinkArticleId > 0) {
            db_exec(
                $db,
                'DELETE FROM ezine_article_categories WHERE category_id=? AND article_id=? LIMIT 1',
                'ii',
                $unlinkCatId,
                $unlinkArticleId
            );
            ec_refresh_articles_count($db, $unlinkCatId);
            if ($id > 0) {
                ec_refresh_articles_count($db, $id);
            }
            $redirectId = $id > 0 ? $id : $unlinkCatId;
            header('Location: /admin_ezine_categories.php?id=' . $redirectId . '#articles', true, 303);
            exit;
        }
    }

    $error = 'Не удалось отвязать статью';
}

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    if (!empty($_POST['delete']) && $id > 0) {
        if (ec_delete_category($db, $id)) {
            header('Location: /admin_ezine_categories.php?deleted=1', true, 303);
            exit;
        }
        $error = 'Не удалось удалить категорию';
    } else {
        $name_ru = plain_text_normalize_for_storage(ec_post_string('name_ru'));
        $name_en = plain_text_normalize_for_storage(ec_post_string('name_en'));
        $title_ru = plain_text_normalize_for_storage(ec_post_string('title_ru'));
        $title_en = plain_text_normalize_for_storage(ec_post_string('title_en'));
        $description_ru = ec_post_string('description_ru');
        $description_en = ec_post_string('description_en');
        $meta_description_ru = plain_text_normalize_for_storage(ec_post_string('meta_description_ru'));
        $meta_description_en = plain_text_normalize_for_storage(ec_post_string('meta_description_en'));
        $parent_id = ec_post_int('parent_id');
        $sort_order = max(0, min(255, ec_post_int('sort_order')));

        if ($parent_id === $id) {
            $parent_id = 0;
        }

        if ($name_ru === '') {
            $error = 'Название (RU) обязательно';
        } elseif ($name_en === '') {
            $error = 'Название (EN) обязательно';
        } else {
            $parentParam = $parent_id > 0 ? $parent_id : null;
            $saved = false;

            if ($id === 0) {
                $saved = db_exec(
                    $db,
                    'INSERT INTO ezine_categories (name_ru, name_en, title_ru, title_en, description_ru, description_en, meta_description_ru, meta_description_en, parent_id, sort_order) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?,?)',
                    'ssssssssii',
                    $name_ru,
                    $name_en,
                    $title_ru,
                    $title_en,
                    ec_nullable_text($description_ru),
                    ec_nullable_text($description_en),
                    ec_nullable_text($meta_description_ru),
                    ec_nullable_text($meta_description_en),
                    $parentParam,
                    $sort_order
                );
                if ($saved) {
                    $id = (int) mysqli_insert_id($db);
                }
            } else {
                $saved = db_exec(
                    $db,
                    'UPDATE ezine_categories SET name_ru=?, name_en=?, title_ru=?, title_en=?, description_ru=?, description_en=?, meta_description_ru=?, meta_description_en=?, parent_id=?, sort_order=? WHERE id=? LIMIT 1',
                    'ssssssssiii',
                    $name_ru,
                    $name_en,
                    $title_ru,
                    $title_en,
                    ec_nullable_text($description_ru),
                    ec_nullable_text($description_en),
                    ec_nullable_text($meta_description_ru),
                    ec_nullable_text($meta_description_en),
                    $parentParam,
                    $sort_order,
                    $id
                );
            }

            if ($saved && $id > 0) {
                ec_refresh_articles_count($db, $id);

                if (!empty($_POST['delete_image'])) {
                    ec_category_delete_images($id);
                } elseif (!empty($_FILES['upload_image']['tmp_name']) && is_uploaded_file((string) $_FILES['upload_image']['tmp_name'])) {
                    if (!ec_category_save_image($id, (string) $_FILES['upload_image']['tmp_name'])) {
                        $error = 'Не удалось загрузить изображение (допустимы JPEG, PNG, WebP, GIF)';
                    }
                }

                if ($error === null) {
                    header('Location: /admin_ezine_categories.php?id=' . $id, true, 303);
                    exit;
                }
            }

            if ($error === null) {
                $error = 'Не удалось сохранить категорию';
            }
        }
    }
}

$categories_raw = [];
$z = db_select($db, 'SELECT * FROM ezine_categories ORDER BY sort_order ASC, name_ru ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $categories_raw[] = $t;
}

$categories_by_id = [];
$by_parent = [];
foreach ($categories_raw as $row) {
    $categories_by_id[(int) $row['id']] = $row;
    $by_parent[(int) ($row['parent_id'] ?? 0)][] = $row;
}

$categories_list = [];
foreach ($categories_raw as $row) {
    $row['list_label'] = ec_category_list_label($row, $categories_by_id);
    $categories_list[] = $row;
}
$smarty->assign('categories_list', $categories_list);
$smarty->assign('parent_categories', $categories_list);
$smarty->assign('category_tree', ec_build_category_tree($by_parent, 0));

if ($id === 0 && count($categories_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($categories_list[0]['id'] ?? 0);
}

$category = null;
if ($id > 0) {
    ec_refresh_articles_count($db, $id);
    $stmt = $db->prepare('SELECT * FROM ezine_categories WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$smarty->assign('category', $category);

$linked_articles = [];
if ($id > 0) {
    $z = db_select(
        $db,
        'SELECT a.id AS id_article, a.title, a.title_eng, a.number, a.id_issue, a.id_press, '
        . 'i.title AS issue_title, i.date AS issue_date, '
        . 'p.title AS press_name, eac.sort_order '
        . 'FROM ezine_article_categories eac '
        . 'INNER JOIN articles a ON a.id = eac.article_id '
        . 'INNER JOIN issue i ON i.id = a.id_issue '
        . 'INNER JOIN press p ON p.id = i.id_press '
        . 'WHERE eac.category_id=? '
        . 'ORDER BY i.date ASC, eac.sort_order ASC, a.number ASC, a.title ASC',
        'i',
        $id
    );

    $lastIssue = null;
    while ($z && ($t = mysqli_fetch_assoc($z))) {
        $issueId = (int) ($t['id_issue'] ?? 0);
        $t['show'] = ($lastIssue !== $issueId) ? 1 : 0;
        $lastIssue = $issueId;

        $issueDate = (int) ($t['issue_date'] ?? 0);
        $monthKey = date('m', $issueDate);
        $t['date'] = date('d ' . ($months[$monthKey] ?? '') . ' Y', $issueDate);

        $t['title_list'] = article_title_list_html($t['title'] ?? '');
        $t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
        $t['press_name_plain'] = title_plain($t['press_name'] ?? '');
        $t['issue_title_plain'] = title_plain($t['issue_title'] ?? '');

        $linked_articles[] = $t;
    }

    /** @var array<int, list<array{id: int, name_ru: string}>> $categoriesByArticle */
    $categoriesByArticle = [];
    $zCat = db_select(
        $db,
        'SELECT eac.article_id, eac.category_id, ec.name_ru '
        . 'FROM ezine_article_categories eac '
        . 'INNER JOIN ezine_categories ec ON ec.id = eac.category_id '
        . 'WHERE eac.article_id IN ('
        . 'SELECT eac2.article_id FROM ezine_article_categories eac2 WHERE eac2.category_id=?'
        . ') '
        . 'ORDER BY eac.article_id ASC, ec.sort_order ASC, ec.name_ru ASC',
        'i',
        $id
    );
    while ($zCat && ($row = mysqli_fetch_assoc($zCat))) {
        $aid = (int) ($row['article_id'] ?? 0);
        $catId = (int) ($row['category_id'] ?? 0);
        if ($aid <= 0 || $catId <= 0) {
            continue;
        }
        $categoriesByArticle[$aid][] = [
            'id' => $catId,
            'name_ru' => (string) ($row['name_ru'] ?? ''),
        ];
    }

    foreach ($linked_articles as $idx => $article) {
        $aid = (int) ($article['id_article'] ?? 0);
        $linked_articles[$idx]['article_categories'] = $categoriesByArticle[$aid] ?? [];
    }
}
$smarty->assign('linked_articles', $linked_articles);

$press_list = [];
$z = db_select($db, 'SELECT * FROM press ORDER BY title ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

if ($id > 0 && ec_category_original_path($id)) {
    $smarty->assign('category_image_url', '/admin_ezine_categories.php?serve_image=' . $id);
} else {
    $smarty->assign('category_image_url', '');
}

$smarty->assign('error', $error);
$smarty->assign('merge_message', $merge_message);
$smarty->assign('delete_message', $delete_message);
$smarty->assign('title', 'Админка: Категории электронных газет и журналов');
$smarty->display('admin_ezine_categories.tpl');
