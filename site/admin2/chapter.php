<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/books_v2_admin.php';

$id = (int) ($_GET['id'] ?? 0);
$editionId = (int) ($_GET['edition_id'] ?? 0);
$error = '';

if ($id > 0 && $editionId <= 0) {
    $stmt = $db->prepare('SELECT edition_id FROM book_chapters WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $editionId = (int) ($row['edition_id'] ?? 0);
}

$backUrl = '/admin2/edition.php?id=' . $editionId;
$backLabel = '← к изданию #' . $editionId;
$z = db_select($db, 'SELECT work_id FROM book_editions WHERE id=? LIMIT 1', 'i', $editionId);
if ($z && ($row = mysqli_fetch_assoc($z))) {
    $workId = (int) $row['work_id'];
    if (books_v2_admin_main_edition_id($db, $workId) === $editionId) {
        $backUrl = '/admin2/work.php?id=' . $workId;
        $backLabel = '← к книге #' . $workId;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['delete_chapter']) && $id > 0) {
        $db->query('DELETE FROM book_chapters WHERE id=' . $id . ' LIMIT 1');
        admin2_flash_set('ok', 'Глава удалена');
        admin2_redirect($backUrl);
    }
    $saved = books_v2_admin_save_chapter($db, $id, $editionId);
    if ($saved['error'] !== '') {
        $error = $saved['error'];
    } else {
        $next = (int) $saved['id'];
        if (($_POST['save'] ?? '') === 'Сохранить и добавить еще') {
            admin2_flash_set('ok', 'Глава #' . $next . ' сохранена');
            admin2_redirect('/admin2/chapter.php?edition_id=' . $editionId);
        }
        admin2_flash_set('ok', 'Сохранено');
        admin2_redirect('/admin2/chapter.php?id=' . $next . '&edition_id=' . $editionId);
    }
}

$chapter = null;
$i18n = ['title' => '', 'slug' => ''];
$body = '';
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM book_chapters WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $chapter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $z = db_select($db, 'SELECT title, slug FROM book_chapter_i18n WHERE chapter_id=' . $id . ' LIMIT 1');
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $i18n = $row;
    }
    $z = db_select($db, 'SELECT body FROM book_chapter_bodies WHERE chapter_id=' . $id . ' LIMIT 1');
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $body = (string) $row['body'];
    }
}

$smarty->assign('error', $error);
$smarty->assign('chapter', $chapter);
$smarty->assign('chapter_i18n', $i18n);
$smarty->assign('body', $body);
$smarty->assign('edition_id', $editionId);
$smarty->assign('back_url', $backUrl);
$smarty->assign('back_label', $backLabel);
$smarty->assign('chapter_types', books_v2_admin_chapter_types());
admin2_render('admin2/chapter_form.tpl', $chapter ? ('Глава #' . $id) : 'Новая глава', 'books');
