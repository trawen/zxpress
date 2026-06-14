<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function pea_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function pea_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function pea_nullable_text(string $value): ?string
{
    return $value !== '' ? $value : null;
}

function pea_nullable_int(?int $value): ?int
{
    return ($value !== null && $value > 0) ? $value : null;
}

$issue_id = (int) ($_GET['issue_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
$error = null;

$issue = null;
$periodical = null;

if ($issue_id > 0) {
    $stmt = $db->prepare(
        'SELECT pi.*, p.id AS periodical_id, p.title_ru AS periodical_title_ru, p.title_en AS periodical_title_en '
        . 'FROM periodical_issues pi INNER JOIN periodicals p ON p.id = pi.periodical_id WHERE pi.id=? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $issue_id);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$issue && $id > 0) {
    $stmt = $db->prepare(
        'SELECT pa.issue_id FROM periodical_articles pa WHERE pa.id=? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $issue_id = (int) ($row['issue_id'] ?? 0);
            $stmt2 = $db->prepare(
                'SELECT pi.*, p.id AS periodical_id, p.title_ru AS periodical_title_ru, p.title_en AS periodical_title_en '
                . 'FROM periodical_issues pi INNER JOIN periodicals p ON p.id = pi.periodical_id WHERE pi.id=? LIMIT 1'
            );
            if ($stmt2) {
                $stmt2->bind_param('i', $issue_id);
                $stmt2->execute();
                $issue = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            }
        }
    }
}

if (!$issue) {
    http_response_code(404);
    die('Выпуск не найден. <a href="admin_periodicals.php">Назад</a>');
}

$periodical = [
    'id' => (int) ($issue['periodical_id'] ?? 0),
    'title_ru' => (string) ($issue['periodical_title_ru'] ?? ''),
    'title_en' => (string) ($issue['periodical_title_en'] ?? ''),
];

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $title_ru = plain_text_normalize_for_storage(pea_post_string('title_ru'));
    $title_en = plain_text_normalize_for_storage(pea_post_string('title_en'));
    $abstract_ru = trim((string) ($_POST['abstract_ru'] ?? ''));
    $abstract_en = trim((string) ($_POST['abstract_en'] ?? ''));
    $text_ru = trim((string) ($_POST['text_ru'] ?? ''));
    $text_en = trim((string) ($_POST['text_en'] ?? ''));
    $page_start = pea_post_int('page_start');
    $page_end = pea_post_int('page_end');
    $sort_order = max(0, pea_post_int('sort_order'));
    $language_id = pea_post_int('language_id');
    $original_language_id = pea_post_int('original_language_id');
    $meta_description_ru = plain_text_normalize_for_storage(pea_post_string('meta_description_ru'));
    $meta_description_en = plain_text_normalize_for_storage(pea_post_string('meta_description_en'));
    $is_active = !empty($_POST['is_active']) ? 1 : 0;

    if ($title_ru === '') {
        $error = 'Заголовок (RU) обязателен';
    } elseif ($language_id <= 0) {
        $error = 'Выберите язык статьи';
    } else {
        if ($id === 0) {
            $saved = db_exec(
                $db,
                'INSERT INTO periodical_articles (issue_id, original_language_id, language_id, title_ru, title_en, abstract_ru, abstract_en, text_ru, text_en, page_start, page_end, sort_order, is_active, meta_description_ru, meta_description_en) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                'iiissssssiiiiss',
                $issue_id,
                pea_nullable_int($original_language_id),
                $language_id,
                $title_ru,
                $title_en,
                pea_nullable_text($abstract_ru),
                pea_nullable_text($abstract_en),
                pea_nullable_text($text_ru),
                pea_nullable_text($text_en),
                pea_nullable_int($page_start),
                pea_nullable_int($page_end),
                $sort_order,
                $is_active,
                $meta_description_ru,
                $meta_description_en
            );
            if ($saved) {
                $id = (int) mysqli_insert_id($db);
            }
        } else {
            $saved = db_exec(
                $db,
                'UPDATE periodical_articles SET original_language_id=?, language_id=?, title_ru=?, title_en=?, abstract_ru=?, abstract_en=?, text_ru=?, text_en=?, page_start=?, page_end=?, sort_order=?, is_active=?, meta_description_ru=?, meta_description_en=? '
                . 'WHERE id=? AND issue_id=? LIMIT 1',
                'iissssssiiiissii',
                pea_nullable_int($original_language_id),
                $language_id,
                $title_ru,
                $title_en,
                pea_nullable_text($abstract_ru),
                pea_nullable_text($abstract_en),
                pea_nullable_text($text_ru),
                pea_nullable_text($text_en),
                pea_nullable_int($page_start),
                pea_nullable_int($page_end),
                $sort_order,
                $is_active,
                $meta_description_ru,
                $meta_description_en,
                $id,
                $issue_id
            );
        }

        if (!empty($saved)) {
            header('Location: /admin_periodical_articles.php?issue_id=' . $issue_id . '&id=' . $id, true, 303);
            exit;
        }

        $error = 'Не удалось сохранить статью';
    }
}

$articles_list = [];
$z = db_select(
    $db,
    'SELECT id, title_ru, page_start, page_end, sort_order, is_active FROM periodical_articles WHERE issue_id=? '
    . 'ORDER BY sort_order ASC, COALESCE(page_start, 99999) ASC, id ASC',
    'i',
    $issue_id
);
while ($z && ($t = mysqli_fetch_array($z))) {
    $articles_list[] = $t;
}
$smarty->assign('articles_list', $articles_list);

if ($id === 0 && count($articles_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($articles_list[0]['id'] ?? 0);
}

$article = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM periodical_articles WHERE id=? AND issue_id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ii', $id, $issue_id);
        $stmt->execute();
        $article = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$smarty->assign('article', $article);

$languages = [];
$z = db_select($db, 'SELECT id, name FROM languages ORDER BY name ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $languages[] = $t;
}
$smarty->assign('languages', $languages);

$issue_label = trim((string) ($issue['title_ru'] ?? ''));
if ($issue_label === '') {
    $issue_label = '№' . (string) ($issue['issue_no'] ?? '');
}
if (!empty($issue['issue_year'])) {
    $issue_label .= ' (' . (int) $issue['issue_year'] . ')';
}

$smarty->assign('issue', $issue);
$smarty->assign('issue_id', $issue_id);
$smarty->assign('issue_label', $issue_label);
$smarty->assign('periodical', $periodical);
$smarty->assign('error', $error);

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

$smarty->assign('title', 'Админка: Статьи выпуска — ' . ($periodical['title_ru'] ?? ''));
$smarty->display('admin_periodical_articles.tpl');
