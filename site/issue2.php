<?php

declare(strict_types=1);

require 'init.inc';
require_once __DIR__ . '/includes/ezines2_public.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/comments_scope.php';

if (!ezines_v2_ready($db)) {
    http_response_code(503);
    echo 'ezines2 schema not ready';
    exit;
}

$lng = $smarty->getTemplateVars('lng');
$uiLang = ezines2_request_ui_lang(is_string($lng) ? $lng : null);
$isEng = $uiLang === 'en';

$ezineSlug = trim((string) ($_GET['ezine_slug'] ?? $_GET['press_slug'] ?? ''));
$issueSlug = trim((string) ($_GET['issue_slug'] ?? ''));

$resolved = ezines2_resolve_slugs($db, $ezineSlug, $issueSlug, '', $uiLang);
if ($resolved['ezine_id'] <= 0) {
    http_response_code(404);
    $smarty->display('404.tpl');
    exit;
}

$ezine = ezines2_load_ezine($db, $resolved['ezine_id'], $uiLang);
if ($ezine === null) {
    http_response_code(404);
    $smarty->display('404.tpl');
    exit;
}

$currentIssue = null;
if ($issueSlug !== '') {
    if ($resolved['issue_id'] <= 0) {
        http_response_code(404);
        $smarty->display('404.tpl');
        exit;
    }
    $issueRow = ezines2_load_issue($db, $resolved['issue_id'], $uiLang);
    if ($issueRow === null) {
        http_response_code(404);
        $smarty->display('404.tpl');
        exit;
    }
    $dateTs = (int) ($issueRow['date'] ?? 0);
    $dateDisplay = '';
    if ($dateTs > 0) {
        $dateDisplay = $isEng ? date('d F Y', $dateTs) : date('d.m.Y', $dateTs);
    }
    $articles = [];
    $st = $db->prepare(
        'SELECT a.id, a.sort_order, a.views, a.legacy_article_id, a.original_language,
                COALESCE(
                  NULLIF(TRIM(i_want.title), \'\'),
                  NULLIF(TRIM(i_orig.title), \'\'),
                  NULLIF(TRIM(i_ru.title), \'\'),
                  NULLIF(TRIM(i_any.title), \'\'),
                  \'\'
                ) AS title,
                i_ru.slug AS slug_ru, i_en.slug AS slug_en, i_cs.slug AS slug_cs, i_sk.slug AS slug_sk
         FROM ezine_articles a
         LEFT JOIN ezine_article_i18n i_want ON i_want.article_id=a.id AND i_want.lang=?
         LEFT JOIN ezine_article_i18n i_orig ON i_orig.article_id=a.id AND i_orig.lang=a.original_language
         LEFT JOIN ezine_article_i18n i_ru ON i_ru.article_id=a.id AND i_ru.lang=\'ru\'
         LEFT JOIN ezine_article_i18n i_en ON i_en.article_id=a.id AND i_en.lang=\'en\'
         LEFT JOIN ezine_article_i18n i_cs ON i_cs.article_id=a.id AND i_cs.lang=\'cs\'
         LEFT JOIN ezine_article_i18n i_sk ON i_sk.article_id=a.id AND i_sk.lang=\'sk\'
         LEFT JOIN ezine_article_i18n i_any ON i_any.article_id=a.id
           AND i_any.lang=(SELECT x.lang FROM ezine_article_i18n x WHERE x.article_id=a.id ORDER BY x.lang LIMIT 1)
         WHERE a.issue_id=? AND a.is_active=1
         ORDER BY a.sort_order ASC, a.id ASC'
    );
    $st->bind_param('si', $uiLang, $resolved['issue_id']);
    $st->execute();
    $ar = $st->get_result();
    while ($ar && ($a = $ar->fetch_assoc())) {
        $title = (string) ($a['title'] ?? '');
        $a['title'] = $title;
        $a['title_html'] = article_title_list_html($title);
        $a['title_list'] = $a['title_html'];
        $a['title_eng_list'] = $a['title_html'];
        $a['title_plain'] = title_plain($title);
        $a['public_url'] = ezines2_url_article($ezine, $issueRow, $a, $uiLang);
        $articles[] = $a;
    }
    $st->close();

    $downloadUrl = '';
    $emulatorUrl = '';
    $legacyIssueId = (int) ($issueRow['legacy_issue_id'] ?? 0);
    $legacyPressId = (int) ($ezine['legacy_press_id'] ?? 0);
    if ($legacyIssueId > 0) {
        $stmtFile = $db->prepare('SELECT name FROM files WHERE id_issue=? AND `delete`=0 ORDER BY id ASC LIMIT 1');
        if ($stmtFile) {
            $stmtFile->bind_param('i', $legacyIssueId);
            $stmtFile->execute();
            $fileRow = $stmtFile->get_result()->fetch_assoc();
            $stmtFile->close();
            $fileName = basename((string) ($fileRow['name'] ?? ''));
            if (($fileName === '' || $fileName === '.' || $fileName === '..') && $legacyPressId > 0) {
                $stmtPressFile = $db->prepare(
                    'SELECT name FROM files WHERE id_press=? AND id_issue=0 AND `delete`=0 ORDER BY id ASC LIMIT 1'
                );
                if ($stmtPressFile) {
                    $stmtPressFile->bind_param('i', $legacyPressId);
                    $stmtPressFile->execute();
                    $fileRow = $stmtPressFile->get_result()->fetch_assoc();
                    $stmtPressFile->close();
                    $fileName = basename((string) ($fileRow['name'] ?? ''));
                }
            }
            if ($fileName !== '' && $fileName !== '.' && $fileName !== '..') {
                $downloadUrl = '/files/' . rawurlencode($fileName);
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($fileExt, ['zip', 'trd', 'scl'], true)) {
                    $emulatorUrl = $downloadUrl;
                }
            }
        }
    }

    $currentIssue = [
        'id' => (int) $issueRow['id'],
        'title' => (string) ($issueRow['title'] ?? ''),
        'missing' => (int) ($issueRow['missing'] ?? 0),
        'slug_ru' => (string) ($issueRow['slug_ru'] ?? ''),
        'slug_en' => (string) ($issueRow['slug_en'] ?? ''),
        'date_display' => $dateDisplay,
        'public_url' => ezines2_url_issue($ezine, $issueRow, $uiLang),
        'description' => (string) ($issueRow['description'] ?? ''),
        'meta_description' => (string) ($issueRow['meta_description'] ?? ''),
        'articles' => $articles,
        'screens' => [],
        'download_url' => $downloadUrl,
        'emulator_url' => $emulatorUrl,
        'legacy_issue_id' => $legacyIssueId,
    ];
    if ($currentIssue['meta_description'] !== '') {
        $smarty->assign('description', $currentIssue['meta_description']);
    }
}

$issues = [];
$issuesList = [];
$ezineOrigLang = ezines_v2_lang_code((string) ($ezine['original_language'] ?? 'ru'));
$st = $db->prepare(
    'SELECT i.id, i.issue_date, i.sort_order, i.is_missing, i.views, i.legacy_issue_id,
            COALESCE(
              NULLIF(TRIM(ii_want.title), \'\'),
              NULLIF(TRIM(ii_orig.title), \'\'),
              NULLIF(TRIM(ii_ru.title), \'\'),
              NULLIF(TRIM(ii_any.title), \'\'),
              \'\'
            ) AS title,
            ii_ru.slug AS slug_ru, ii_en.slug AS slug_en, ii_cs.slug AS slug_cs, ii_sk.slug AS slug_sk
     FROM ezine_issues i
     LEFT JOIN ezine_issue_i18n ii_want ON ii_want.issue_id=i.id AND ii_want.lang=?
     LEFT JOIN ezine_issue_i18n ii_orig ON ii_orig.issue_id=i.id AND ii_orig.lang=?
     LEFT JOIN ezine_issue_i18n ii_ru ON ii_ru.issue_id=i.id AND ii_ru.lang=\'ru\'
     LEFT JOIN ezine_issue_i18n ii_en ON ii_en.issue_id=i.id AND ii_en.lang=\'en\'
     LEFT JOIN ezine_issue_i18n ii_cs ON ii_cs.issue_id=i.id AND ii_cs.lang=\'cs\'
     LEFT JOIN ezine_issue_i18n ii_sk ON ii_sk.issue_id=i.id AND ii_sk.lang=\'sk\'
     LEFT JOIN ezine_issue_i18n ii_any ON ii_any.issue_id=i.id
       AND ii_any.lang=(SELECT x.lang FROM ezine_issue_i18n x WHERE x.issue_id=i.id ORDER BY x.lang LIMIT 1)
     WHERE i.ezine_id=? AND i.is_active=1
     ORDER BY i.sort_order ASC, i.id ASC'
);
$st->bind_param('ssi', $uiLang, $ezineOrigLang, $resolved['ezine_id']);
$st->execute();
$ir = $st->get_result();
while ($ir && ($row = $ir->fetch_assoc())) {
    $row['public_url'] = ezines2_url_issue($ezine, $row, $uiLang);
    $dateTs = (int) ($row['issue_date'] ?? 0);
    $dateDisplay = '';
    if ($dateTs > 0) {
        $dateDisplay = $isEng ? date('d F Y', $dateTs) : date('d.m.Y', $dateTs);
    }
    $row['date_display'] = $dateDisplay;
    $row['missing'] = (int) ($row['is_missing'] ?? 0);
    $issues[] = $row;
    $issuesList[] = [
        'id' => (int) $row['id'],
        'title' => (string) ($row['title'] ?? ''),
        'missing' => (int) ($row['is_missing'] ?? 0),
        'date_display' => $dateDisplay,
        'public_url' => (string) $row['public_url'],
        'screens' => [],
        'cover' => null,
    ];
}
$st->close();

$press = $ezine;
$press['title_plain'] = title_plain((string) ($press['title'] ?? ''));
$press['public_url'] = ezines2_url_ezine($ezine, $uiLang);
$press['id'] = (int) $ezine['id'];
if (!isset($press['numbers']) || (int) $press['numbers'] <= 0) {
    $press['numbers'] = count($issuesList);
}

$canonical = $currentIssue
    ? ezines2_url_issue($ezine, $currentIssue, $uiLang)
    : ezines2_url_ezine($ezine, $uiLang);

$pressUrl = ezines2_url_ezine($ezine, $uiLang);
$titlePlain = title_plain((string) ($press['title'] ?? ''));
$pageTitle = $currentIssue
    ? ('«' . $titlePlain . '» #' . $currentIssue['title'])
    : ('«' . $titlePlain . '»');

$viewMode = $currentIssue ? 'issue' : 'press';
$numEndings = ['выпуск', 'выпуска', 'выпусков'];
if ($uiLang === 'en') {
    $numLabel = ((int) $press['numbers'] === 1) ? 'issue' : 'issues';
} elseif ($uiLang === 'cs') {
    $n = (int) $press['numbers'];
    $numLabel = ($n === 1) ? 'číslo' : (($n >= 2 && $n <= 4) ? 'čísla' : 'čísel');
} else {
    $numLabel = getNumEnding((int) $press['numbers'], $numEndings);
}

$smarty->assign('press', $press);
$smarty->assign('press_url', $pressUrl);
$smarty->assign('issues', $issues);
$smarty->assign('issues_list', $issuesList);
$smarty->assign('view_mode', $viewMode);
$smarty->assign('num', $numLabel);
$smarty->assign('current_issue', $currentIssue);
$smarty->assign('articles', $currentIssue['articles'] ?? []);
$smarty->assign('screens', []);
$smarty->assign('screens_by_issue', []);
$smarty->assign('ezines_catalog_url', ezines2_url_catalog($uiLang));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_ezines_active', true);
$smarty->assign('ui_lang', $uiLang);
$smarty->assign('url_rus', htmlspecialchars(ezines2_url_ezine($ezine, 'ru'), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(ezines2_url_ezine($ezine, 'en'), ENT_QUOTES, 'UTF-8'));
$smarty->assign('canonical', $canonical);
$origin = function_exists('zxpress_canonical_origin') ? zxpress_canonical_origin() : '';
$smarty->assign('canonical_url', $origin . $canonical);
$smarty->assign('title', $pageTitle);
if (($press['meta_description'] ?? '') !== '' && $currentIssue === null) {
    $smarty->assign('description', (string) $press['meta_description']);
}

$smarty->display('issue_new.tpl');
