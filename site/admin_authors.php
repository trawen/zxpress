<?php
require 'init.inc';
require_once __DIR__ . '/includes/authors_slugs.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function zx_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function zx_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $nickname = plain_text_normalize_for_storage(zx_post_string('nickname'));
    $name_ru = plain_text_normalize_for_storage(zx_post_string('name_ru'));
    $name_en = plain_text_normalize_for_storage(zx_post_string('name_en'));
    $group_name = plain_text_normalize_for_storage(zx_post_string('group_name'));
    $country_id = zx_post_int('country_id');
    $city_id = zx_post_int('city_id');
    $user_id = zx_post_int('user_id');
    $is_active = !empty($_POST['is_active']) ? 1 : 0;

    if ($nickname === '') {
        $smarty->assign('error', 'Ник обязателен');
    } else {
        $slugs = authors_resolve_slugs(
            $db,
            zx_post_string('slug_ru'),
            zx_post_string('slug_en'),
            $nickname,
            $name_ru,
            $name_en,
            $id
        );
        $slug_ru = $slugs['slug_ru'];
        $slug_en = $slugs['slug_en'];

        if ($id === 0) {
            db_exec(
                $db,
                "INSERT INTO authors (nickname, name_ru, name_en, group_name, slug_ru, slug_en, country_id, city_id, user_id, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)",
                "ssssssiiii",
                $nickname,
                ($name_ru !== '' ? $name_ru : null),
                ($name_en !== '' ? $name_en : null),
                ($group_name !== '' ? $group_name : null),
                $slug_ru,
                $slug_en,
                ($country_id > 0 ? $country_id : null),
                ($city_id > 0 ? $city_id : null),
                ($user_id > 0 ? $user_id : null),
                $is_active
            );
            $id = (int) mysqli_insert_id($db);
        } else {
            db_exec(
                $db,
                "UPDATE authors SET nickname=?, name_ru=?, name_en=?, group_name=?, slug_ru=?, slug_en=?, country_id=?, city_id=?, user_id=?, is_active=? WHERE id=? LIMIT 1",
                "ssssssiiiii",
                $nickname,
                ($name_ru !== '' ? $name_ru : null),
                ($name_en !== '' ? $name_en : null),
                ($group_name !== '' ? $group_name : null),
                $slug_ru,
                $slug_en,
                ($country_id > 0 ? $country_id : null),
                ($city_id > 0 ? $city_id : null),
                ($user_id > 0 ? $user_id : null),
                $is_active,
                $id
            );
        }

        header("Location: /admin_authors.php?id=" . $id, true, 303);
        exit;
    }
}

// Lists for dropdowns
$countries = [];
$z = db_select($db, "SELECT * FROM countries ORDER BY country_name ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $countries[] = $t;
}
$smarty->assign('countries', $countries);

$cities = [];
$z = db_select($db, "SELECT * FROM cities ORDER BY name ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $cities[] = $t;
}
$smarty->assign('cities', $cities);

$users = [];
$z = db_select($db, "SELECT id, username, `level` FROM users ORDER BY username ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $users[] = $t;
}
$smarty->assign('users', $users);

// Authors list
$authors_list = [];
$z = db_select($db, "SELECT * FROM authors ORDER BY nickname ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $authors_list[] = $t;
}
$smarty->assign('authors_list', $authors_list);

// Default to first author if id is missing
if ($id === 0 && count($authors_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($authors_list[0]['id'] ?? 0);
}

$author = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM authors WHERE id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $author = $stmt->get_result()->fetch_assoc();
    }
}
$smarty->assign('author', $author);

// Admin top expects press_list for the "Перейти к изданию" select
$press_list = [];
$z = db_select($db, "SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . " - " . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Авторы');
$smarty->display('admin_authors.tpl');

