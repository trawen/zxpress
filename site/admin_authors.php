<?php
require 'init.inc';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/admin_translate.php';

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

/**
 * Resolve country_id from cities.country_id when city is set.
 */
function authors_country_id_for_city(mysqli $db, int $cityId): int
{
    if ($cityId <= 0) {
        return 0;
    }
    $z = db_select($db, 'SELECT country_id FROM cities WHERE id=? LIMIT 1', 'i', $cityId);
    $row = $z ? mysqli_fetch_assoc($z) : null;

    return (int) ($row['country_id'] ?? 0);
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
    $error = '';

    if ($city_id > 0) {
        $cityCountry = authors_country_id_for_city($db, $city_id);
        if ($cityCountry > 0) {
            $country_id = $cityCountry;
        }
    }

    if ($name_en === '' && $name_ru !== '') {
        try {
            $translated = plain_text_normalize_for_storage(admin_translate_title($name_ru));
            if ($translated !== '') {
                $name_en = $translated;
            }
        } catch (Throwable $e) {
            error_log('[admin_authors] name_en translate failed: ' . $e->getMessage());
            $error = 'Имя EN не перевелось через Google: ' . $e->getMessage();
        }
    }

    if ($error === '' && $nickname === '') {
        $error = 'Ник обязателен';
    }

    if ($error !== '') {
        $smarty->assign('error', $error);
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

        $wasCreate = ($id === 0);
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

        if ($id > 0) {
            activity_log($db, [
                'verb' => $wasCreate ? 'created' : 'updated',
                'object_type' => 'author',
                'object_id' => $id,
                'action' => $wasCreate ? 'author.created' : 'author.updated',
                'event_scope' => ACTIVITY_SCOPE_METADATA,
                'is_public' => 0,
                'title_ru' => $nickname,
                'title_en' => $nickname,
                'after' => ['is_active' => $is_active],
            ]);
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
$z = db_select(
    $db,
    'SELECT a.*, c.name AS city_name, co.country_name,
            (SELECT COUNT(*) FROM letters l WHERE l.author_from = a.id OR l.author_to = a.id) AS letters_from_count
     FROM authors a
     LEFT JOIN cities c ON c.id = a.city_id
     LEFT JOIN countries co ON co.id = COALESCE(NULLIF(a.country_id, 0), c.country_id)
     ORDER BY a.nickname ASC'
);
while ($z && ($t = mysqli_fetch_array($z))) {
    foreach (['nickname', 'name_ru', 'name_en', 'group_name'] as $field) {
        if (isset($t[$field]) && is_string($t[$field]) && $t[$field] !== '') {
            // Legacy rows may store HTML entities (Ice&#039;Di); Smarty escape_html would show them literally.
            $t[$field] = plain_text_normalize_for_storage($t[$field]);
        }
    }
    foreach (['city_name', 'country_name'] as $field) {
        if (isset($t[$field]) && is_string($t[$field]) && $t[$field] !== '') {
            $t[$field] = plain_text_normalize_for_storage($t[$field]);
        }
    }
    $t['letters_from_count'] = (int) ($t['letters_from_count'] ?? 0);
    $authors_list[] = $t;
}
$smarty->assign('authors_list', $authors_list);

// Default to first author if id is missing
if ($id === 0 && count($authors_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($authors_list[0]['id'] ?? 0);
}

$author = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM authors WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $author = $stmt->get_result()->fetch_assoc();
        if (is_array($author)) {
            foreach (['nickname', 'name_ru', 'name_en', 'group_name'] as $field) {
                if (isset($author[$field]) && is_string($author[$field]) && $author[$field] !== '') {
                    $author[$field] = plain_text_normalize_for_storage($author[$field]);
                }
            }
        }
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

