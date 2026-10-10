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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['delete_edition']) && $id > 0) {
        $db->query('DELETE FROM book_editions WHERE id=' . $id . ' LIMIT 1');
        admin2_flash_set('ok', 'Издание удалено');
        admin2_redirect('/admin2/work.php?id=' . $workId);
    }
    $saved = books_v2_admin_save_edition($db, $id, $workId);
    if ($saved['error'] !== '') {
        $error = $saved['error'];
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
        "SELECT c.id, c.sort_order, c.chapter_type, COALESCE(ci.title, CONCAT('#', c.id)) AS title
         FROM book_chapters c
         LEFT JOIN book_chapter_i18n ci ON ci.chapter_id=c.id
         WHERE c.edition_id=" . $id . ' ORDER BY c.sort_order, c.id'
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $chapters[] = $row;
    }
}
$languages = books_v2_admin_languages($db);

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
$smarty->assign('chapter_types', books_v2_admin_chapter_types());
$smarty->assign('languages', $languages);
$smarty->assign('work_id', $workId);
$smarty->assign('edition_kinds', ['original', 'reprint', 'revised', 'translation', 'facsimile', 'electronic']);
$smarty->assign('publisher_roles', ['publisher', 'copublisher', 'printer', 'distributor']);

admin2_render('admin2/edition_form.tpl', $edition ? ('Издание #' . $id) : 'Новое издание', 'books');
