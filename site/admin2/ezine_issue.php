<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/ezines_v2_admin.php';
require_once dirname(__DIR__) . '/includes/ezines2_public.php';

$id = (int) ($_GET['id'] ?? 0);
$ezineId = (int) ($_GET['ezine_id'] ?? 0);
$error = '';
$languages = ezines_v2_admin_languages($db);

if (!ezines_v2_ready($db)) {
    $smarty->assign('error', 'Схема ezines_v2 не применена');
    admin2_render('admin2/ezine_issue_form.tpl', 'Выпуск', 'ezines');
    exit;
}

if ($id > 0 && $ezineId <= 0) {
    $st = $db->prepare('SELECT ezine_id FROM ezine_issues WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $ezineId = (int) (($st->get_result()->fetch_assoc()['ezine_id'] ?? 0));
    $st->close();
}

if (($_POST['action'] ?? '') === 'delete_file') {
    header('Content-Type: application/json; charset=utf-8');
    $fileId = (int) ($_POST['file_id'] ?? 0);
    $issueId = (int) ($_POST['issue_id'] ?? $id);
    if ($issueId <= 0 || !ezines_v2_admin_delete_issue_file($db, $issueId, $fileId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Файл не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST['save'])) {
    $ezineId = (int) ($_POST['ezine_id'] ?? $ezineId);
    $saved = ezines_v2_admin_save_issue($db, $id, $ezineId, $languages);
    if (!$saved['ok']) {
        $error = $saved['error'];
        $id = $saved['id'] > 0 ? $saved['id'] : $id;
    } else {
        $id = (int) $saved['id'];
        $fileError = ezines_v2_admin_save_issue_files($db, $id);
        if ($fileError !== '') {
            admin2_flash_set('error', 'Сохранено, но файлы: ' . $fileError);
        } else {
            admin2_flash_set('ok', 'Сохранено');
        }
        admin2_redirect('/admin2/ezine_issue.php?id=' . $id . '&ezine_id=' . $ezineId);
    }
}

$issue = null;
$i18n = ezines_v2_admin_empty_ezine_i18n($languages);
$articles = [];
$issueFiles = [];
if ($id > 0) {
    $st = $db->prepare('SELECT * FROM ezine_issues WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $issue = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
    if (!$issue) {
        $smarty->assign('error', 'Выпуск не найден');
        admin2_render('admin2/ezine_issue_form.tpl', 'Выпуск', 'ezines');
        exit;
    }
    $ezineId = (int) $issue['ezine_id'];
    $i18n = ezines_v2_admin_load_issue_i18n($db, $id, $languages);
    $articles = ezines_v2_admin_articles($db, $id);
    $issueFiles = ezines_v2_admin_issue_files($db, $id);
} elseif ($error !== '') {
    $i18n = ezines_v2_admin_post_i18n_meta($languages);
}

$ezineTitle = '';
if ($ezineId > 0) {
    $st = $db->prepare(
        "SELECT COALESCE(i_ru.title, i_en.title, CONCAT('Ezine #', e.id)) AS title
         FROM ezines e
         LEFT JOIN ezine_i18n i_ru ON i_ru.ezine_id=e.id AND i_ru.lang='ru'
         LEFT JOIN ezine_i18n i_en ON i_en.ezine_id=e.id AND i_en.lang='en'
         WHERE e.id=? LIMIT 1"
    );
    $st->bind_param('i', $ezineId);
    $st->execute();
    $ezineTitle = (string) (($st->get_result()->fetch_assoc()['title'] ?? ''));
    $st->close();
}

$dateFmt = '';
if ($issue && (int) ($issue['issue_date'] ?? 0) > 0) {
    $dateFmt = date('d.m.Y', (int) $issue['issue_date']);
}

$pageTitle = $issue
    ? ('Выпуск #' . $id . ' · ' . ($i18n['ru']['title'] !== '' ? $i18n['ru']['title'] : ($i18n['en']['title'] ?? '')))
    : 'Новый выпуск';

$smarty->assign('error', $error);
$smarty->assign('issue', $issue);
$smarty->assign('issue_i18n', $i18n);
$smarty->assign('languages', $languages);
$smarty->assign('articles', $articles);
$smarty->assign('issue_files', $issueFiles);
$smarty->assign('file_type_labels', ezines_v2_admin_file_type_labels());
$smarty->assign('ezine_id', $ezineId);
$smarty->assign('ezine_title', $ezineTitle);
$smarty->assign('issue_date_fmt', $dateFmt);
$smarty->assign('admin2_kicker', $ezineTitle !== '' ? $ezineTitle : 'Эзин');
$smarty->assign('admin2_kicker_url', '/admin2/ezine.php?id=' . $ezineId);
if ($issue) {
    $smarty->assign('admin2_status', !empty($issue['is_active']) ? 'on' : 'off');
}
admin2_render('admin2/ezine_issue_form.tpl', $pageTitle, 'ezines');
