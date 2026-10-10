<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/books_v2_admin.php';

$id = (int) ($_GET['id'] ?? 0);
$error = '';
$languages = books_v2_admin_languages($db);
$langById = [];
foreach ($languages as $lang) {
    $langById[(int) $lang['id']] = (string) $lang['code'];
}

if (($_POST['action'] ?? '') === 'delete_image') {
    header('Content-Type: application/json; charset=utf-8');
    $imageId = (int) ($_POST['image_id'] ?? 0);
    if (!books_v2_admin_delete_cover($db, $id, $imageId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Обложка не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['delete_work']) && $id > 0) {
        $db->query('DELETE FROM book_works WHERE id=' . $id . ' LIMIT 1');
        admin2_flash_set('ok', 'Книга удалена');
        admin2_redirect('/admin2/books.php');
    }
    $saved = books_v2_admin_save_work($db, $id, $langById);
    if ($saved['error'] !== '') {
        $error = $saved['error'];
        $id = (int) $saved['id'];
    } else {
        $id = (int) $saved['id'];
        if (($_POST['save'] ?? '') === 'Сохранить и добавить еще') {
            admin2_flash_set('ok', 'Сохранено #' . $id);
            admin2_redirect('/admin2/work.php');
        }
        admin2_flash_set('ok', 'Сохранено');
        admin2_redirect('/admin2/work.php?id=' . $id);
    }
}

$work = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM book_works WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $work = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$credits = [];
$editions = [];
$rubricIds = [];
$edition = null;
$isbns = [];
$publishersLinked = [];
$seriesLink = ['series_id' => 0, 'number_in_series' => ''];
$chapters = [];
$covers = [];
$mainEditionId = 0;
if ($work) {
    $z = db_select($db, 'SELECT author_id, role, sort_order FROM book_credits WHERE work_id=' . $id . ' ORDER BY sort_order, id');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $credits[] = $row;
    }
    $mainEditionId = books_v2_admin_main_edition_id($db, $id);
    $z = db_select(
        $db,
        'SELECT e.id, e.edition_kind, e.publish_year, e.is_active,
                (SELECT COUNT(*) FROM book_chapters c WHERE c.edition_id=e.id) AS chapters_count
         FROM book_editions e WHERE e.work_id=' . $id . ' ORDER BY e.sort_order, e.id'
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $row['is_main'] = (int) $row['id'] === $mainEditionId ? 1 : 0;
        $editions[] = $row;
    }
    $z = db_select($db, 'SELECT rubric_id FROM book_work_rubrics WHERE work_id=' . $id);
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $rubricIds[] = (int) $row['rubric_id'];
    }
}
if ($mainEditionId > 0) {
    $z = db_select($db, 'SELECT * FROM book_editions WHERE id=? LIMIT 1', 'i', $mainEditionId);
    $edition = $z ? mysqli_fetch_assoc($z) : null;
    $z = db_select($db, 'SELECT isbn_raw FROM book_edition_isbns WHERE edition_id=' . $mainEditionId . ' ORDER BY sort_order, id');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $isbns[] = (string) $row['isbn_raw'];
    }
    $z = db_select($db, 'SELECT publisher_id, role FROM book_edition_publishers WHERE edition_id=' . $mainEditionId . ' ORDER BY sort_order');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $publishersLinked[] = $row;
    }
    $z = db_select($db, 'SELECT series_id, number_in_series FROM book_edition_series WHERE edition_id=' . $mainEditionId . ' LIMIT 1');
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $seriesLink = $row;
    }
    $z = db_select(
        $db,
        "SELECT c.id, c.sort_order, c.chapter_type, COALESCE(ci.title, CONCAT('#', c.id)) AS title
         FROM book_chapters c
         LEFT JOIN book_chapter_i18n ci ON ci.chapter_id=c.id
         WHERE c.edition_id=" . $mainEditionId . ' ORDER BY c.sort_order, c.id'
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $chapters[] = $row;
    }
    $covers = books_v2_admin_covers($db, $mainEditionId);
}

$authors = [];
$z = db_select($db, 'SELECT id, nickname FROM authors ORDER BY nickname ASC');
while ($z && ($row = mysqli_fetch_assoc($z))) {
    $authors[] = $row;
}
$rubrics = [];
$z = db_select($db, 'SELECT id, name_ru FROM book_rubrics ORDER BY name_ru ASC');
while ($z && ($row = mysqli_fetch_assoc($z))) {
    $row['on'] = in_array((int) $row['id'], $rubricIds, true) ? 1 : 0;
    $rubrics[] = $row;
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

$workI18n = books_v2_admin_work_i18n($db, $id, $languages);
$smarty->assign('error', $error);
$smarty->assign('work', $work);
$smarty->assign('work_i18n', $workI18n);
$smarty->assign('languages', $languages);
$smarty->assign('credits', $credits);
$smarty->assign('editions', $editions);
$smarty->assign('edition', $edition);
$smarty->assign('main_edition_id', $mainEditionId);
$smarty->assign('isbns', $isbns);
$smarty->assign('publishers_linked', $publishersLinked);
$smarty->assign('series_link', $seriesLink);
$smarty->assign('chapters', $chapters);
$smarty->assign('covers', $covers);
$smarty->assign('chapter_types', books_v2_admin_chapter_types());
$smarty->assign('authors', $authors);
$smarty->assign('rubrics', $rubrics);
$smarty->assign('publishers', $publishers);
$smarty->assign('series', $series);
$smarty->assign('credit_roles', [
    'author' => 'автор',
    'coauthor' => 'соавтор',
    'compiler' => 'составитель',
    'editor' => 'редактор',
    'translator' => 'переводчик',
    'illustrator' => 'иллюстратор',
    'designer' => 'дизайнер',
    'foreword' => 'автор предисловия',
]);
$smarty->assign('publisher_roles', [
    'publisher' => 'издательство',
    'copublisher' => 'соиздатель',
    'printer' => 'типография',
    'distributor' => 'распространитель',
]);

$title = 'Новая книга';
if ($work) {
    $title = 'Книга #' . $id;
    $orig = $langById[(int) $work['original_language_id']] ?? 'ru';
    $bookTitle = trim((string) ($workI18n[$orig]['title'] ?? ''));
    if ($bookTitle === '') {
        $bookTitle = trim((string) ($workI18n['ru']['title'] ?? ''));
    }
    if ($bookTitle !== '') {
        $title .= ' — ' . $bookTitle;
    }
}
admin2_render('admin2/work_form.tpl', $title, 'books');
