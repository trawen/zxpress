<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function pub_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function pub_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function pub_nullable_text(string $value): ?string
{
    return $value !== '' ? $value : null;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = null;

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $name_ru = plain_text_normalize_for_storage(pub_post_string('name_ru'));
    $name_en = plain_text_normalize_for_storage(pub_post_string('name_en'));
    $alias_ru = plain_text_normalize_for_storage(pub_post_string('alias_ru'));
    $alias_en = plain_text_normalize_for_storage(pub_post_string('alias_en'));
    $form_ru = plain_text_normalize_for_storage(pub_post_string('form_ru'));
    $form_en = plain_text_normalize_for_storage(pub_post_string('form_en'));
    $description_ru = plain_text_normalize_for_storage(pub_post_string('description_ru'));
    $description_en = plain_text_normalize_for_storage(pub_post_string('description_en'));
    $city_id = pub_post_int('city_id');
    $active = !empty($_POST['active']) ? 1 : 0;

    if ($name_ru === '') {
        $error = 'Название (RU) обязательно';
    } else {
        $saved = false;
        if ($id === 0) {
            $saved = db_exec(
                $db,
                'INSERT INTO publishers (name_ru, name_en, alias_ru, alias_en, form_ru, form_en, description_ru, description_en, city_id, active) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?)',
                'ssssssssii',
                $name_ru,
                $name_en,
                $alias_ru,
                $alias_en,
                $form_ru,
                $form_en,
                pub_nullable_text($description_ru),
                pub_nullable_text($description_en),
                ($city_id > 0 ? $city_id : 1),
                $active
            );
            if ($saved) {
                $id = (int) mysqli_insert_id($db);
            }
        } else {
            $saved = db_exec(
                $db,
                'UPDATE publishers SET name_ru=?, name_en=?, alias_ru=?, alias_en=?, form_ru=?, form_en=?, description_ru=?, description_en=?, city_id=?, active=? WHERE id=? LIMIT 1',
                'ssssssssiii',
                $name_ru,
                $name_en,
                $alias_ru,
                $alias_en,
                $form_ru,
                $form_en,
                pub_nullable_text($description_ru),
                pub_nullable_text($description_en),
                ($city_id > 0 ? $city_id : 1),
                $active,
                $id
            );
        }

        if ($saved) {
            header('Location: /admin_publishers.php?id=' . $id, true, 303);
            exit;
        }

        if ((int) mysqli_errno($db) === 1062) {
            $error = 'Издательство с таким названием (RU) уже существует';
        } else {
            $error = 'Не удалось сохранить издательство';
        }
    }
}

$smarty->assign('error', $error);

$publishers_list = [];
$z = db_select($db, 'SELECT id, name_ru, name_en, alias_ru, active FROM publishers ORDER BY name_ru ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $publishers_list[] = $t;
}
$smarty->assign('publishers_list', $publishers_list);

if ($id === 0 && count($publishers_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($publishers_list[0]['id'] ?? 0);
}

$publisher = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM publishers WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $publisher = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$smarty->assign('publisher', $publisher);

$books_count = 0;
if ($id > 0) {
    $z = db_select($db, 'SELECT COUNT(*) AS c FROM book_publishers WHERE publisher_id=?', 'i', $id);
    if ($z && ($row = mysqli_fetch_array($z))) {
        $books_count = (int) ($row['c'] ?? 0);
    }
}
$smarty->assign('books_count', $books_count);

$cities = [];
$z = db_select($db, 'SELECT id, name FROM cities ORDER BY name ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $cities[] = $t;
}
$smarty->assign('cities', $cities);

$press_list = [];
$z = db_select($db, 'SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . ' - ' . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Издательства');
$smarty->display('admin_publishers.tpl');
