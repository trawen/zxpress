<?php
require 'init.inc';

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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = null;

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    if (!empty($_POST['delete']) && $id > 0) {
        db_exec($db, 'DELETE FROM ezine_categories WHERE id=? LIMIT 1', 'i', $id);
        header('Location: /admin_ezine_categories.php', true, 303);
        exit;
    }

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
            header('Location: /admin_ezine_categories.php?id=' . $id, true, 303);
            exit;
        }

        $error = 'Не удалось сохранить категорию';
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
        'SELECT a.id, a.title, a.number, eac.sort_order '
        . 'FROM ezine_article_categories eac '
        . 'INNER JOIN articles a ON a.id = eac.article_id '
        . 'WHERE eac.category_id=? '
        . 'ORDER BY eac.sort_order ASC, a.title ASC',
        'i',
        $id
    );
    while ($z && ($t = mysqli_fetch_array($z))) {
        $linked_articles[] = $t;
    }
}
$smarty->assign('linked_articles', $linked_articles);

$press_list = [];
$z = db_select($db, 'SELECT * FROM press ORDER BY title ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('error', $error);
$smarty->assign('title', 'Админка: Категории статей журналов');
$smarty->display('admin_ezine_categories.tpl');
