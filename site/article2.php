<?php

declare(strict_types=1);

require 'init.inc';
require_once __DIR__ . '/includes/ezines2_public.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/comments_scope.php';
require_once __DIR__ . '/includes/article_jsonld.php';

if (!function_exists('ezn_article_root_urls')) {
    require_once __DIR__ . '/includes/ezine_slugs.php';
}

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
$articleSlug = trim((string) ($_GET['article_slug'] ?? ''));
$articleIdParam = (int) ($_GET['id'] ?? 0);

$articleId = 0;
$ezine = null;
$issue = null;

if ($articleIdParam > 0 && $ezineSlug === '') {
    $articleId = $articleIdParam;
} else {
    $resolved = ezines2_resolve_slugs($db, $ezineSlug, $issueSlug, $articleSlug, $uiLang);
    $articleId = $resolved['article_id'];
    if ($resolved['ezine_id'] > 0) {
        $ezine = ezines2_load_ezine($db, $resolved['ezine_id'], $uiLang);
    }
    if ($resolved['issue_id'] > 0) {
        $issue = ezines2_load_issue($db, $resolved['issue_id'], $uiLang);
    }
}

$loaded = ezines2_load_article($db, $articleId, $uiLang);
if ($loaded === null) {
    http_response_code(404);
    $smarty->display('404.tpl');
    exit;
}

$article = $loaded['article'];
if ($issue === null) {
    $issue = ezines2_load_issue($db, (int) $article['issue_id'], $uiLang);
}
if ($ezine === null && $issue) {
    $ezine = ezines2_load_ezine($db, (int) $issue['ezine_id'], $uiLang);
}
if ($ezine === null || $issue === null) {
    http_response_code(404);
    $smarty->display('404.tpl');
    exit;
}

$canonical = ezines2_url_article($ezine, $issue, $article, $uiLang);
// Redirect numeric /ezines2/a/{id} to slug form when possible.
$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
if (preg_match('#/ezines2/a/\d+#', $path) && !str_contains($canonical, '/a/')) {
    header('Location: ' . $canonical, true, 301);
    exit;
}

$body = $loaded['body'];
if ($body === null || trim($body['body']) === '') {
    http_response_code(404);
    $smarty->display('404.tpl');
    exit;
}

$textType = ezines_v2_text_type_from_format($body['format']);
$rendered = ezn_render_article_body($body['body'], $textType);
$article['text'] = function_exists('ezn_article_root_urls')
    ? ezn_article_root_urls($rendered['html'])
    : $rendered['html'];

$displayTitle = (string) ($article['display_title'] ?? '');
if ($displayTitle === '') {
    $displayTitle = $isEng && trim((string) ($article['title_eng'] ?? '')) !== ''
        ? (string) $article['title_eng']
        : (string) ($article['title'] ?? '');
}
$article['title'] = $displayTitle;
$article['title_plain'] = title_plain($displayTitle);
$article['title_html'] = $displayTitle;

$articleHasMd = false;
$articleMdHtml = '';
if ($rendered['mode'] !== 'markdown' && $loaded['md_alt'] !== '') {
    $mdRendered = ezn_render_article_body($loaded['md_alt'], EZN_TEXT_TYPE_MARKDOWN);
    $articleMdHtml = function_exists('ezn_article_root_urls')
        ? ezn_article_root_urls($mdRendered['html'])
        : $mdRendered['html'];
    $articleHasMd = $articleMdHtml !== '';
}

$press = $ezine;
$press['title_plain'] = title_plain((string) ($press['title'] ?? ''));
$press['id'] = (int) $ezine['id'];
$pressRow = $press;
$issueRow = $issue;

$dateTs = (int) ($issue['date'] ?? 0);
if ($dateTs > 0) {
    if ($isEng) {
        $issue['date'] = date('d F Y', $dateTs);
    } elseif (isset($months) && is_array($months)) {
        $issue['date'] = date('d ' . ($months[date('m', $dateTs)] ?? '') . ' Y', $dateTs);
    } else {
        $issue['date'] = date('d.m.Y', $dateTs);
    }
} else {
    $issue['date'] = '';
}

$siblings = [];
$st = $db->prepare(
    'SELECT a.id, a.sort_order,
            COALESCE(i_want.title, i_ru.title, "") AS title,
            i_ru.slug AS slug_ru, i_en.slug AS slug_en
     FROM ezine_articles a
     LEFT JOIN ezine_article_i18n i_want ON i_want.article_id=a.id AND i_want.lang=?
     LEFT JOIN ezine_article_i18n i_ru ON i_ru.article_id=a.id AND i_ru.lang=\'ru\'
     LEFT JOIN ezine_article_i18n i_en ON i_en.article_id=a.id AND i_en.lang=\'en\'
     WHERE a.issue_id=? AND a.is_active=1
     ORDER BY a.sort_order ASC, a.id ASC'
);
$issueId = (int) $issue['id'];
$st->bind_param('si', $uiLang, $issueId);
$st->execute();
$sr = $st->get_result();
while ($sr && ($t = $sr->fetch_assoc())) {
    $t['title_html'] = (string) ($t['title'] ?? '');
    $t['public_url'] = ezines2_url_article($ezine, $issue, $t, $uiLang);
    $siblings[] = $t;
}
$st->close();

$meta = $isEng
    ? trim((string) ($article['meta_description_en'] ?? ''))
    : trim((string) ($article['meta_description_ru'] ?? ''));
if ($meta === '') {
    $meta = title_plain($displayTitle);
}

$smarty->assign('article', $article);
$smarty->assign('issue', $issue);
$smarty->assign('press', $press);
$smarty->assign('screens', null);
$smarty->assign('articles', $siblings);
$smarty->assign('article_text_mode', $rendered['mode']);
$smarty->assign('article_text_use_pre', $rendered['use_pre'] ? 1 : 0);
$smarty->assign('article_text_mono', $rendered['mono'] ? 1 : 0);
$smarty->assign('article_has_md', $articleHasMd);
$smarty->assign('article_md_html', $articleMdHtml);
$smarty->assign('article_content_langs', $loaded['content_langs']);
$smarty->assign('article_public_url', $canonical);
$smarty->assign('canonical', $canonical);
$origin = function_exists('zxpress_canonical_origin') ? zxpress_canonical_origin() : '';
$smarty->assign('canonical_url', $origin . $canonical);
$smarty->assign('description', $meta);
$smarty->assign('title', title_plain($displayTitle));
$smarty->assign('og_title', title_plain($displayTitle));
$smarty->assign('ezines_catalog_url', ezines2_url_catalog($uiLang));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_ezines_active', true);
$smarty->assign('ui_lang', $uiLang);
$smarty->assign('url_rus', htmlspecialchars(ezines2_url_article($ezine, $issue, $article, 'ru'), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(ezines2_url_article($ezine, $issue, $article, 'en'), ENT_QUOTES, 'UTF-8'));
$smarty->assign('id_article', (int) $article['legacy_article_id']);
$smarty->assign('id', (int) $article['id']);
$smarty->assign('breadcrumbs', [
    ['title' => $isEng ? 'Ezines v2' : 'Эзины v2', 'url' => ezines2_url_catalog($uiLang)],
    ['title' => (string) ($ezine['title'] ?? ''), 'url' => ezines2_url_ezine($ezine, $uiLang)],
    ['title' => (string) ($issue['title'] ?? ''), 'url' => ezines2_url_issue($ezine, $issue, $uiLang)],
    ['title' => title_plain($displayTitle), 'url' => $canonical],
]);

$smarty->display('article_new.tpl');
