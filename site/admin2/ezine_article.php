<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/ezines_v2_admin.php';
require_once dirname(__DIR__) . '/includes/ezines2_public.php';

$id = (int) ($_GET['id'] ?? 0);
$issueId = (int) ($_GET['issue_id'] ?? 0);
$error = '';
$languages = ezines_v2_admin_languages($db);

if (!ezines_v2_ready($db)) {
    $smarty->assign('error', 'Схема ezines_v2 не применена');
    admin2_render('admin2/ezine_article_form.tpl', 'Статья', 'ezines');
    exit;
}

if ($id > 0 && $issueId <= 0) {
    $st = $db->prepare('SELECT issue_id FROM ezine_articles WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $issueId = (int) (($st->get_result()->fetch_assoc()['issue_id'] ?? 0));
    $st->close();
}

if (isset($_POST['save'])) {
    $issueId = (int) ($_POST['issue_id'] ?? $issueId);
    $saved = ezines_v2_admin_save_article($db, $id, $issueId, $languages);
    if (!$saved['ok']) {
        $error = $saved['error'];
        $id = $saved['id'] > 0 ? $saved['id'] : $id;
    } else {
        $btn = (string) ($_POST['save'] ?? '');
        if ($btn === 'Сохранить и добавить еще') {
            admin2_flash_set('ok', 'Сохранено');
            admin2_redirect('/admin2/ezine_article.php?id=0&issue_id=' . $issueId);
        }
        admin2_flash_set('ok', 'Сохранено');
        admin2_redirect('/admin2/ezine_article.php?id=' . (int) $saved['id'] . '&issue_id=' . $issueId);
    }
}

$article = null;
$i18n = ezines_v2_admin_empty_article_i18n($languages);
if ($id > 0) {
    $st = $db->prepare('SELECT * FROM ezine_articles WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $article = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
    if (!$article) {
        $smarty->assign('error', 'Статья не найдена');
        admin2_render('admin2/ezine_article_form.tpl', 'Статья', 'ezines');
        exit;
    }
    $issueId = (int) $article['issue_id'];
    $i18n = ezines_v2_admin_load_article_i18n($db, $id, $languages);
} elseif ($error !== '') {
    $i18n = ezines_v2_admin_post_article_i18n($languages);
}

$ezineId = 0;
$issueTitle = '';
$ezineTitle = '';
if ($issueId > 0) {
    $st = $db->prepare(
        "SELECT i.ezine_id,
                COALESCE(ii_ru.title, ii_en.title, CONCAT('#', i.id)) AS issue_title,
                COALESCE(ei_ru.title, ei_en.title, CONCAT('Ezine #', i.ezine_id)) AS ezine_title
         FROM ezine_issues i
         LEFT JOIN ezine_issue_i18n ii_ru ON ii_ru.issue_id=i.id AND ii_ru.lang='ru'
         LEFT JOIN ezine_issue_i18n ii_en ON ii_en.issue_id=i.id AND ii_en.lang='en'
         LEFT JOIN ezine_i18n ei_ru ON ei_ru.ezine_id=i.ezine_id AND ei_ru.lang='ru'
         LEFT JOIN ezine_i18n ei_en ON ei_en.ezine_id=i.ezine_id AND ei_en.lang='en'
         WHERE i.id=? LIMIT 1"
    );
    $st->bind_param('i', $issueId);
    $st->execute();
    $meta = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
    $ezineId = (int) ($meta['ezine_id'] ?? 0);
    $issueTitle = (string) ($meta['issue_title'] ?? '');
    $ezineTitle = (string) ($meta['ezine_title'] ?? '');
}

$bodyFormats = [
    'html_pre' => 'HTML pre',
    'text_pre' => 'Text pre',
    'markdown' => 'Markdown',
    'html' => 'HTML',
    'legacy' => 'Legacy',
];

$pageTitle = $article
    ? ('Статья #' . $id . ' · ' . ($i18n['ru']['title'] !== '' ? $i18n['ru']['title'] : ($i18n['en']['title'] ?? '')))
    : 'Новая статья';

$publicUrl = '';
if ($article && $ezineId > 0) {
    $ezine = ezines2_load_ezine($db, $ezineId, 'ru');
    $issue = ezines2_load_issue($db, $issueId, 'ru');
    if ($ezine && $issue) {
        $publicUrl = ezines2_url_article($ezine, $issue, array_merge($article, [
            'slug_ru' => $i18n['ru']['slug'] ?? '',
            'slug_en' => $i18n['en']['slug'] ?? '',
        ]), false);
    }
}

$smarty->assign('error', $error);
$smarty->assign('article', $article);
$smarty->assign('article_i18n', $i18n);
$smarty->assign('languages', $languages);
$smarty->assign('body_formats', $bodyFormats);
$smarty->assign('issue_id', $issueId);
$smarty->assign('ezine_id', $ezineId);
$smarty->assign('issue_title', $issueTitle);
$smarty->assign('ezine_title', $ezineTitle);
$smarty->assign('public_url', $publicUrl);
$smarty->assign('admin2_kicker', $issueTitle !== '' ? ($ezineTitle . ' · ' . $issueTitle) : 'Выпуск');
$smarty->assign('admin2_kicker_url', '/admin2/ezine_issue.php?id=' . $issueId . '&ezine_id=' . $ezineId);
if ($article) {
    $smarty->assign('admin2_status', !empty($article['is_active']) ? 'on' : 'off');
}
admin2_render('admin2/ezine_article_form.tpl', $pageTitle, 'ezines');
