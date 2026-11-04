<?php

declare(strict_types=1);

require_once __DIR__ . '/ezines_v2.php';
require_once __DIR__ . '/ezine_slugs.php';
require_once __DIR__ . '/article_text_render.php';

function ezines2_section(): string
{
    return 'ezines2';
}

/** @param bool|string $langOrEng true/eng → en, false → ru, or lang code */
function ezines2_normalize_lang(bool|string $langOrEng): string
{
    if (is_bool($langOrEng)) {
        return $langOrEng ? 'en' : 'ru';
    }
    $lang = strtolower(trim($langOrEng));
    if ($lang === 'eng') {
        return 'en';
    }

    return ezines_v2_lang_code($lang !== '' ? $lang : 'ru');
}

/**
 * UI language for ezines2 pages: ?ui_lang=cs|sk|…, else /cs/… path, else lng=eng → en, else ru.
 */
function ezines2_request_ui_lang(?string $smartyLng = null): string
{
    $fromGet = strtolower(trim((string) ($_GET['ui_lang'] ?? '')));
    if (in_array($fromGet, ['ru', 'en', 'cs', 'sk', 'es'], true)) {
        return $fromGet;
    }
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($path) && preg_match('#^/(cs|sk|es|en|eng|ru)(/|$)#', $path, $m)) {
        return $m[1] === 'eng' ? 'en' : $m[1];
    }
    if ($smartyLng === 'eng' || $smartyLng === 'en') {
        return 'en';
    }

    return 'ru';
}

function ezines2_path_prefix(bool|string $langOrEng): string
{
    $lang = ezines2_normalize_lang($langOrEng);
    if ($lang === 'en') {
        return '/en/' . ezines2_section();
    }

    return '/' . $lang . '/' . ezines2_section();
}

/**
 * @param array<string, mixed> $row
 */
function ezines2_pick_row_slug(array $row, string $lang): string
{
    $lang = ezines2_normalize_lang($lang);
    foreach ([
        (string) ($row['slug_' . $lang] ?? ''),
        (string) ($row['slug'] ?? ''),
        (string) ($row['slug_ru'] ?? ''),
        (string) ($row['slug_cs'] ?? ''),
        (string) ($row['slug_en'] ?? ''),
        (string) ($row['slug_sk'] ?? ''),
    ] as $slug) {
        $slug = trim($slug);
        if ($slug !== '') {
            return $slug;
        }
    }

    return '';
}

function ezines2_url_catalog(bool|string $langOrEng, string $filter = ''): string
{
    $base = ezines2_path_prefix($langOrEng);
    if ($filter === 'papers' || $filter === 'magazines' || $filter === 'reports') {
        return $base . '/' . $filter;
    }

    return $base;
}

/**
 * @param array<string, mixed> $ezine must include slug_ru/slug_en or slug
 */
function ezines2_url_ezine(array $ezine, bool|string $langOrEng): string
{
    $lang = ezines2_normalize_lang($langOrEng);
    $slug = ezines2_pick_row_slug($ezine, $lang);
    if ($slug === '') {
        return ezines2_url_catalog($lang);
    }

    return ezines2_path_prefix($lang) . '/' . rawurlencode($slug);
}

/**
 * @param array<string, mixed> $ezine
 * @param array<string, mixed> $issue
 */
function ezines2_url_issue(array $ezine, array $issue, bool|string $langOrEng): string
{
    $lang = ezines2_normalize_lang($langOrEng);
    $base = ezines2_url_ezine($ezine, $lang);
    $slug = ezines2_pick_row_slug($issue, $lang);
    if ($slug === '' || $base === ezines2_url_catalog($lang)) {
        return $base;
    }

    return $base . '/' . rawurlencode($slug);
}

/**
 * @param array<string, mixed> $ezine
 * @param array<string, mixed> $issue
 * @param array<string, mixed> $article
 */
function ezines2_url_article(array $ezine, array $issue, array $article, bool|string $langOrEng): string
{
    $lang = ezines2_normalize_lang($langOrEng);
    $base = ezines2_url_issue($ezine, $issue, $lang);
    $slug = ezines2_pick_row_slug($article, $lang);
    if ($slug === '') {
        $id = (int) ($article['id'] ?? 0);

        return $id > 0 ? ezines2_path_prefix($lang) . '/a/' . $id : ezines2_url_catalog($lang);
    }

    return $base . '/' . rawurlencode($slug);
}

function ezines2_find_ezine_id(mysqli $db, string $slug, string $lang): int
{
    $slug = trim($slug);
    if ($slug === '' || !ezines_v2_ready($db)) {
        return 0;
    }
    $lang = ezines_v2_lang_code($lang);
    $stmt = $db->prepare(
        'SELECT ezine_id FROM ezine_i18n WHERE lang=? AND slug=? LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ss', $lang, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (int) $row['ezine_id'];
    }
    // Fallback other site locales
    $stmt = $db->prepare(
        'SELECT ezine_id FROM ezine_i18n WHERE slug=? ORDER BY lang ASC LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['ezine_id'] : 0;
}

function ezines2_find_issue_id(mysqli $db, int $ezineId, string $slug, string $lang): int
{
    $slug = trim($slug);
    if ($ezineId <= 0 || $slug === '') {
        return 0;
    }
    $lang = ezines_v2_lang_code($lang);
    $stmt = $db->prepare(
        'SELECT issue_id FROM ezine_issue_i18n WHERE ezine_id=? AND lang=? AND slug=? LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iss', $ezineId, $lang, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (int) $row['issue_id'];
    }
    $stmt = $db->prepare(
        'SELECT issue_id FROM ezine_issue_i18n WHERE ezine_id=? AND slug=? ORDER BY lang ASC LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('is', $ezineId, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['issue_id'] : 0;
}

function ezines2_find_article_id(mysqli $db, int $issueId, string $slug, string $lang): int
{
    $slug = trim($slug);
    if ($issueId <= 0 || $slug === '') {
        return 0;
    }
    $lang = ezines_v2_lang_code($lang);
    $stmt = $db->prepare(
        'SELECT article_id FROM ezine_article_i18n WHERE issue_id=? AND lang=? AND slug=? LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iss', $issueId, $lang, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (int) $row['article_id'];
    }
    $stmt = $db->prepare(
        'SELECT article_id FROM ezine_article_i18n WHERE issue_id=? AND slug=? ORDER BY lang ASC LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('is', $issueId, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['article_id'] : 0;
}

/**
 * @return array<string, mixed>|null
 */
function ezines2_load_ezine(mysqli $db, int $ezineId, string $uiLang): ?array
{
    if ($ezineId <= 0 || !ezines_v2_ready($db)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM ezines WHERE id=? AND is_active=1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $ezineId);
    $stmt->execute();
    $ezine = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$ezine) {
        return null;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_i18n', 'ezine_id', $ezineId);
    $orig = (string) ($ezine['original_language'] ?? 'ru');
    $code = ezines_v2_lang_code($uiLang);
    $ezine['title'] = ezines_v2_i18n_pick($byLang, $code, 'title', $orig);
    $ezine['description'] = ezines_v2_i18n_pick($byLang, $code, 'description', $orig);
    $ezine['meta_description'] = ezines_v2_i18n_pick($byLang, $code, 'meta_description', $orig);
    $ezine['slug_ru'] = (string) ($byLang['ru']['slug'] ?? '');
    $ezine['slug_en'] = (string) ($byLang['en']['slug'] ?? '');
    $ezine['slug_cs'] = (string) ($byLang['cs']['slug'] ?? '');
    $ezine['slug_sk'] = (string) ($byLang['sk']['slug'] ?? '');
    $ezine['slug'] = ezines_v2_i18n_pick($byLang, $code, 'slug', $orig);
    $ezine['description_ru'] = (string) ($byLang['ru']['description'] ?? '');
    $ezine['description_en'] = (string) ($byLang['en']['description'] ?? '');
    $ezine['meta_description_ru'] = (string) ($byLang['ru']['meta_description'] ?? '');
    $ezine['meta_description_en'] = (string) ($byLang['en']['meta_description'] ?? '');
    // Legacy-shaped aliases for reused templates
    $ezine['id_press'] = (int) ($ezine['legacy_press_id'] ?? 0);
    $ezine['press_id'] = $ezineId;
    $ezine['city'] = (int) ($ezine['city_id'] ?? 0);

    return $ezine;
}

/**
 * @return array<string, mixed>|null
 */
function ezines2_load_issue(mysqli $db, int $issueId, string $uiLang): ?array
{
    if ($issueId <= 0 || !ezines_v2_ready($db)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM ezine_issues WHERE id=? AND is_active=1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $issueId);
    $stmt->execute();
    $issue = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$issue) {
        return null;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_issue_i18n', 'issue_id', $issueId);
    $ezine = ezines2_load_ezine($db, (int) $issue['ezine_id'], $uiLang);
    $orig = (string) ($ezine['original_language'] ?? 'ru');
    $code = ezines_v2_lang_code($uiLang);
    $issue['title'] = ezines_v2_i18n_pick($byLang, $code, 'title', $orig);
    $issue['description'] = ezines_v2_i18n_pick($byLang, $code, 'description', $orig);
    $issue['meta_description'] = ezines_v2_i18n_pick($byLang, $code, 'meta_description', $orig);
    $issue['slug_ru'] = (string) ($byLang['ru']['slug'] ?? '');
    $issue['slug_en'] = (string) ($byLang['en']['slug'] ?? '');
    $issue['slug_cs'] = (string) ($byLang['cs']['slug'] ?? '');
    $issue['slug_sk'] = (string) ($byLang['sk']['slug'] ?? '');
    $issue['slug'] = ezines_v2_i18n_pick($byLang, $code, 'slug', $orig);
    $issue['description_ru'] = (string) ($byLang['ru']['description'] ?? '');
    $issue['description_en'] = (string) ($byLang['en']['description'] ?? '');
    $issue['meta_description_ru'] = (string) ($byLang['ru']['meta_description'] ?? '');
    $issue['meta_description_en'] = (string) ($byLang['en']['meta_description'] ?? '');
    $issue['date'] = (int) ($issue['issue_date'] ?? 0);
    $issue['missing'] = (int) ($issue['is_missing'] ?? 0);
    $issue['id_press'] = (int) ($ezine['legacy_press_id'] ?? 0);
    $issue['legacy_issue_id'] = (int) ($issue['legacy_issue_id'] ?? 0);

    return $issue;
}

/**
 * @return array{
 *   article: array<string, mixed>,
 *   body: ?array{body:string,format:string,lang:string},
 *   md_alt: string,
 *   content_langs: list<string>
 * }|null
 */
function ezines2_load_article(mysqli $db, int $articleId, string $uiLang): ?array
{
    if ($articleId <= 0 || !ezines_v2_ready($db)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM ezine_articles WHERE id=? AND is_active=1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) {
        return null;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_article_i18n', 'article_id', $articleId);
    $bodies = ezines_v2_load_article_bodies($db, $articleId);
    $orig = (string) ($row['original_language'] ?? 'ru');
    $code = ezines_v2_lang_code($uiLang);

    $article = $row;
    $article['title'] = (string) ($byLang['ru']['title'] ?? ezines_v2_i18n_pick($byLang, $orig, 'title', $orig));
    $article['title_eng'] = (string) ($byLang['en']['title'] ?? '');
    $article['display_title'] = ezines_v2_i18n_pick($byLang, $code, 'title', $orig);
    $article['slug_ru'] = (string) ($byLang['ru']['slug'] ?? '');
    $article['slug_en'] = (string) ($byLang['en']['slug'] ?? '');
    $article['slug'] = ezines_v2_i18n_pick($byLang, $code, 'slug', $orig);
    $article['meta_description_ru'] = (string) ($byLang['ru']['meta_description'] ?? '');
    $article['meta_description_en'] = (string) ($byLang['en']['meta_description'] ?? '');
    $article['number'] = (int) ($row['sort_order'] ?? 1);
    $article['temp'] = ((int) ($row['is_active'] ?? 1) === 1) ? 0 : 1;
    $article['id_issue'] = (int) ($row['issue_id'] ?? 0);
    $article['legacy_article_id'] = (int) ($row['legacy_article_id'] ?? 0);

    $body = ezines_v2_pick_primary_body($bodies, $code, $orig);
    $contentLangs = [];
    foreach ($bodies as $b) {
        if (!(int) ($b['is_primary'] ?? 0)) {
            continue;
        }
        if (trim((string) ($b['body'] ?? '')) === '') {
            continue;
        }
        $contentLangs[] = (string) $b['lang'];
    }

    return [
        'article' => $article,
        'body' => $body,
        'md_alt' => ezines_v2_pick_markdown_alt($bodies, $code, $orig),
        'content_langs' => array_values(array_unique($contentLangs)),
    ];
}

/**
 * Resolve /ezines2/{ezine}/{issue?}/{article?} slugs.
 *
 * @return array{ezine_id:int,issue_id:int,article_id:int}
 */
function ezines2_resolve_slugs(
    mysqli $db,
    string $ezineSlug,
    string $issueSlug = '',
    string $articleSlug = '',
    string $uiLang = 'ru'
): array {
    $out = ['ezine_id' => 0, 'issue_id' => 0, 'article_id' => 0];
    $ezineId = ezines2_find_ezine_id($db, $ezineSlug, $uiLang);
    if ($ezineId <= 0) {
        return $out;
    }
    $out['ezine_id'] = $ezineId;
    if ($issueSlug === '') {
        return $out;
    }
    $issueId = ezines2_find_issue_id($db, $ezineId, $issueSlug, $uiLang);
    if ($issueId <= 0) {
        return $out;
    }
    $out['issue_id'] = $issueId;
    if ($articleSlug === '') {
        return $out;
    }
    $out['article_id'] = ezines2_find_article_id($db, $issueId, $articleSlug, $uiLang);

    return $out;
}
