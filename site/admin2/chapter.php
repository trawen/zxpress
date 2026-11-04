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
$workId = 0;
$bookTitle = '';
$z = db_select(
    $db,
    "SELECT w.id AS work_id,
            COALESCE(
                (SELECT i.title FROM book_work_i18n i
                  INNER JOIN languages l ON l.code=i.lang AND l.id=w.original_language_id
                  WHERE i.work_id=w.id LIMIT 1),
                (SELECT i.title FROM book_work_i18n i WHERE i.work_id=w.id AND i.lang='ru' LIMIT 1),
                (SELECT i.title FROM book_work_i18n i WHERE i.work_id=w.id ORDER BY i.lang LIMIT 1)
            ) AS title
       FROM book_editions e
       INNER JOIN book_works w ON w.id=e.work_id
      WHERE e.id=? LIMIT 1",
    'i',
    $editionId
);
if ($z && ($row = mysqli_fetch_assoc($z))) {
    $workId = (int) $row['work_id'];
    $bookTitle = trim((string) ($row['title'] ?? ''));
    if (books_v2_admin_main_edition_id($db, $workId) === $editionId) {
        $backUrl = '/admin2/work.php?id=' . $workId;
    }
}

if (($_GET['action'] ?? '') === 'translate_i18n') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $translated = books_v2_admin_translate_chapter_from_ru([
            'title' => (string) ($_POST['title'] ?? ''),
            'body' => (string) ($_POST['body'] ?? ''),
            'format' => (string) ($_POST['format'] ?? 'html'),
        ]);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'i18n' => $translated], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'preview_body') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    require_once dirname(__DIR__) . '/includes/books_public.php';
    $rendered = books_render_chapter_body(
        (string) ($_POST['body'] ?? ''),
        (string) ($_POST['format'] ?? 'html')
    );
    echo json_encode(['ok' => true, 'html' => $rendered['html'], 'mode' => $rendered['mode']], JSON_UNESCAPED_UNICODE);
    exit;
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

$languages = books_v2_admin_languages($db);
$formats = books_v2_admin_body_formats();
$i18n = [];
foreach ($languages as $lang) {
    $i18n[(string) $lang['code']] = ['title' => '', 'slug' => '', 'body' => '', 'format' => 'html'];
}
$chapter = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM book_chapters WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $chapter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $z = db_select($db, 'SELECT lang, title, slug FROM book_chapter_i18n WHERE chapter_id=' . $id);
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $code = (string) $row['lang'];
        if (!isset($i18n[$code])) {
            continue;
        }
        $i18n[$code]['title'] = (string) $row['title'];
        $i18n[$code]['slug'] = (string) $row['slug'];
    }
    $z = db_select($db, 'SELECT lang, body, format FROM book_chapter_bodies WHERE chapter_id=' . $id);
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $code = (string) $row['lang'];
        if (!isset($i18n[$code])) {
            continue;
        }
        $i18n[$code]['body'] = (string) $row['body'];
        $storedFormat = (string) ($row['format'] ?? 'html');
        if (isset($formats[$storedFormat])) {
            $i18n[$code]['format'] = $storedFormat;
        }
    }
}
if ($error !== '' && is_array($_POST['i18n'] ?? null)) {
    foreach ($i18n as $code => $row) {
        $raw = $_POST['i18n'][$code] ?? null;
        if (!is_array($raw)) {
            continue;
        }
        $i18n[$code]['title'] = (string) ($raw['title'] ?? '');
        $i18n[$code]['slug'] = (string) ($raw['slug'] ?? '');
        $i18n[$code]['body'] = (string) ($raw['body'] ?? '');
        $storedFormat = (string) ($raw['body_format'] ?? 'html');
        $i18n[$code]['format'] = isset($formats[$storedFormat]) ? $storedFormat : 'html';
    }
}

$smarty->assign('error', $error);
$smarty->assign('chapter', $chapter);
$smarty->assign('chapter_i18n', $i18n);
$smarty->assign('languages', $languages);
$smarty->assign('body_formats', $formats);
$smarty->assign('edition_id', $editionId);
if ($workId > 0 && $bookTitle !== '') {
    $smarty->assign('admin2_kicker', $bookTitle);
    $smarty->assign('admin2_kicker_url', '/admin2/work.php?id=' . $workId);
}
$smarty->assign('chapter_types', books_v2_admin_chapter_types());
$pageTitle = 'Новая глава';
if ($chapter) {
    $chapterNumber = 1;
    $sortOrder = (int) ($chapter['sort_order'] ?? 0);
    $z = db_select(
        $db,
        'SELECT COUNT(*) AS n FROM book_chapters
          WHERE edition_id=? AND (sort_order < ? OR (sort_order = ? AND id <= ?))',
        'iiii',
        $editionId,
        $sortOrder,
        $sortOrder,
        $id
    );
    if ($z && ($row = mysqli_fetch_assoc($z))) {
        $chapterNumber = max(1, (int) $row['n']);
    }
    $pageTitle = (string) $chapterNumber;
    $chapterTitle = trim((string) ($i18n['ru']['title'] ?? ''));
    if ($chapterTitle === '') {
        foreach ($i18n as $row) {
            $chapterTitle = trim((string) ($row['title'] ?? ''));
            if ($chapterTitle !== '') {
                break;
            }
        }
    }
    if ($chapterTitle !== '') {
        $pageTitle .= ' — ' . $chapterTitle;
    }
    $smarty->assign('admin2_status', !empty($chapter['is_active']) ? 'on' : 'off');
}
admin2_render('admin2/chapter_form.tpl', $pageTitle, 'books');
