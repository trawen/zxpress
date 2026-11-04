<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/books_v2_admin.php';

$id = (int) ($_GET['id'] ?? 0);
$workId = (int) ($_GET['work_id'] ?? 0);
$error = '';

if ($id > 0 && $workId <= 0) {
    $stmt = $db->prepare('SELECT work_id FROM book_editions WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $workId = (int) ($row['work_id'] ?? 0);
}

$bookTitle = '';
if ($workId > 0) {
    $z = db_select(
        $db,
        "SELECT COALESCE(
            (SELECT i.title FROM book_work_i18n i
              INNER JOIN languages l ON l.code=i.lang AND l.id=w.original_language_id
              WHERE i.work_id=w.id LIMIT 1),
            (SELECT i.title FROM book_work_i18n i WHERE i.work_id=w.id AND i.lang='ru' LIMIT 1),
            (SELECT i.title FROM book_work_i18n i WHERE i.work_id=w.id ORDER BY i.lang LIMIT 1),
            CONCAT('Книга #', w.id)
         ) AS title
         FROM book_works w
        WHERE w.id=? LIMIT 1",
        'i',
        $workId
    );
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $bookTitle = trim((string) ($row['title'] ?? ''));
    }
}

if (($_POST['action'] ?? '') === 'upload_cover') {
    header('Content-Type: application/json; charset=utf-8');
    $upl = (isset($_FILES['file']) && is_array($_FILES['file'])) ? $_FILES['file'] : [];
    $origName = (string) ($upl['name'] ?? '');
    $tmp = (string) ($upl['tmp_name'] ?? '');
    $errCode = (int) ($upl['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($id <= 0) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Сначала сохраните издание'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($errCode !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $origName === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не удалось загрузить обложку'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $added = books_v2_admin_add_cover($db, $id, $origName, $tmp);
    if ($added['id'] <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $added['error'] !== '' ? $added['error'] : 'Не удалось сохранить обложку'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'cover' => $added], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_POST['action'] ?? '') === 'upload_file') {
    header('Content-Type: application/json; charset=utf-8');
    $upl = (isset($_FILES['file']) && is_array($_FILES['file'])) ? $_FILES['file'] : [];
    $origName = (string) ($upl['name'] ?? '');
    $tmp = (string) ($upl['tmp_name'] ?? '');
    $errCode = (int) ($upl['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($id <= 0) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Сначала сохраните издание'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($errCode !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $origName === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Не удалось загрузить файл'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $added = books_v2_admin_add_edition_file($db, $id, $origName, $tmp);
    if ($added['error'] !== '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $added['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'file' => $added], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_POST['action'] ?? '') === 'delete_file') {
    header('Content-Type: application/json; charset=utf-8');
    $fileId = (int) ($_POST['file_id'] ?? 0);
    if (!books_v2_admin_delete_file($db, $workId, $fileId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Файл не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_POST['action'] ?? '') === 'delete_image') {
    header('Content-Type: application/json; charset=utf-8');
    $imageId = (int) ($_POST['image_id'] ?? 0);
    if (!books_v2_admin_delete_cover($db, $workId, $imageId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Обложка не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['delete_edition']) && $id > 0) {
        $db->query('DELETE FROM book_editions WHERE id=' . $id . ' LIMIT 1');
        admin2_flash_set('ok', 'Издание удалено');
        admin2_redirect('/admin2/work.php?id=' . $workId);
    }
    $saved = books_v2_admin_save_edition($db, $id, $workId);
    if ($saved['error'] !== '') {
        $error = $saved['error'];
        $id = (int) $saved['id'];
    } else {
        admin2_flash_set('ok', 'Сохранено');
        admin2_redirect('/admin2/edition.php?id=' . (int) $saved['id'] . '&work_id=' . $workId);
    }
}

$edition = null;
$i18n = ['slug' => '', 'edition_statement' => '', 'note' => ''];
$isbns = [];
$publishersLinked = [];
$seriesLink = ['series_id' => 0, 'number_in_series' => ''];
$covers = [];
$bookFiles = [];
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM book_editions WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $edition = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $z = db_select($db, 'SELECT * FROM book_edition_i18n WHERE edition_id=' . $id . ' LIMIT 1');
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $i18n = $row;
    }
    $z = db_select($db, 'SELECT isbn_raw FROM book_edition_isbns WHERE edition_id=' . $id . ' ORDER BY sort_order, id');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $isbns[] = (string) $row['isbn_raw'];
    }
    $z = db_select($db, 'SELECT publisher_id, role FROM book_edition_publishers WHERE edition_id=' . $id . ' ORDER BY sort_order');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $publishersLinked[] = $row;
    }
    $z = db_select($db, 'SELECT series_id, number_in_series FROM book_edition_series WHERE edition_id=' . $id . ' LIMIT 1');
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $seriesLink = $row;
    }
    $covers = books_v2_admin_covers($db, $id);
    $bookFiles = books_v2_admin_files($db, $id);
}

$siblings = [];
if ($workId > 0) {
    $z = db_select($db, 'SELECT id, edition_kind, publish_year, content_edition_id FROM book_editions WHERE work_id=' . $workId . ' ORDER BY id');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $siblings[] = $row;
    }
}
$cities = [];
$z = db_select($db, 'SELECT id, name FROM cities ORDER BY name ASC');
while ($z && ($row = mysqli_fetch_assoc($z))) {
    $cities[] = $row;
}
$publishers = [];
$z = db_select($db, 'SELECT id, name_ru FROM publishers ORDER BY name_ru ASC');
while ($z && ($row = mysqli_fetch_assoc($z))) {
    $publishers[] = $row;
}
$series = [];
$z = db_select($db, "SELECT series_id, title FROM book_series_i18n WHERE lang='ru' ORDER BY title ASC");
while ($z && ($row = mysqli_fetch_assoc($z))) {
    $series[] = $row;
}
$chapters = [];
if ($id > 0) {
    $z = db_select(
        $db,
        "SELECT c.id, c.sort_order, c.chapter_type,
            COALESCE(
              (SELECT ci.title FROM book_chapter_i18n ci WHERE ci.chapter_id=c.id AND ci.lang='ru' LIMIT 1),
              (SELECT ci.title FROM book_chapter_i18n ci WHERE ci.chapter_id=c.id ORDER BY ci.lang LIMIT 1),
              CONCAT('#', c.id)
            ) AS title
         FROM book_chapters c
         WHERE c.edition_id=" . $id . ' ORDER BY c.sort_order, c.id'
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $chapters[] = $row;
    }
}
$languages = books_v2_admin_languages($db);
$editionKinds = [
    'original' => 'оригинал',
    'reprint' => 'переиздание',
    'revised' => 'исправленное',
    'translation' => 'перевод',
    'facsimile' => 'факсимиле',
    'electronic' => 'электронное',
];

$smarty->assign('error', $error);
$smarty->assign('edition', $edition);
$smarty->assign('edition_i18n', $i18n);
$smarty->assign('isbns', $isbns);
$smarty->assign('publishers_linked', $publishersLinked);
$smarty->assign('series_link', $seriesLink);
$smarty->assign('siblings', $siblings);
$smarty->assign('cities', $cities);
$smarty->assign('publishers', $publishers);
$smarty->assign('series', $series);
$smarty->assign('chapters', $chapters);
$smarty->assign('covers', $covers);
$smarty->assign('book_files', $bookFiles);
$smarty->assign('chapter_types', books_v2_admin_chapter_types());
$smarty->assign('languages', $languages);
$smarty->assign('work_id', $workId);
$smarty->assign('edition_kinds', $editionKinds);
$smarty->assign('publisher_roles', [
    'publisher' => 'издательство',
    'copublisher' => 'соиздатель',
    'printer' => 'типография',
    'distributor' => 'распространитель',
]);

if ($workId > 0 && $bookTitle !== '') {
    $smarty->assign('admin2_kicker', $bookTitle);
    $smarty->assign('admin2_kicker_url', '/admin2/work.php?id=' . $workId);
}

$pageTitle = 'Новое издание';
if ($edition) {
    $kindLabel = $editionKinds[(string) ($edition['edition_kind'] ?? '')] ?? (string) ($edition['edition_kind'] ?? 'издание');
    $pageTitle = 'Издание #' . $id . ' — ' . $kindLabel;
    $year = (int) ($edition['publish_year'] ?? 0);
    if ($year > 0) {
        $pageTitle .= ' ' . $year;
    }
    $smarty->assign('admin2_status', !empty($edition['is_active']) ? 'on' : 'off');
}
admin2_render('admin2/edition_form.tpl', $pageTitle, 'books');
