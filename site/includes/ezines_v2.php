<?php

declare(strict_types=1);

require_once __DIR__ . '/article_text_render.php';

/**
 * Whether ezines_v2 tables exist (safe for hosts mid-migrate).
 */
function ezines_v2_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $z = $db->query("SHOW TABLES LIKE 'ezines'");
    $ready = $z instanceof mysqli_result && $z->num_rows > 0;

    return $ready;
}

/**
 * Normalize UI / content lang to languages.code.
 */
function ezines_v2_lang_code(string $lng): string
{
    $lng = strtolower(trim($lng));
    if ($lng === 'eng' || $lng === 'en') {
        return 'en';
    }
    if ($lng === 'spa' || $lng === 'esp' || $lng === 'es') {
        return 'es';
    }
    if (preg_match('/^[a-z]{2}$/', $lng)) {
        return $lng;
    }

    return 'ru';
}

/**
 * Pick a non-empty i18n field: want → original → ru → any.
 *
 * @param array<string, array<string, mixed>> $byLang
 */
function ezines_v2_i18n_pick(array $byLang, string $lang, string $field, string $original = 'ru'): string
{
    $order = [];
    $lang = ezines_v2_lang_code($lang);
    // cs/sk are near-siblings: prefer the other when the requested UI lang has no row.
    $sibling = $lang === 'sk' ? 'cs' : ($lang === 'cs' ? 'sk' : '');
    foreach ([$lang, $sibling, $original, 'ru'] as $code) {
        $code = ezines_v2_lang_code($code);
        if ($code !== '' && !in_array($code, $order, true)) {
            $order[] = $code;
        }
    }
    foreach (array_keys($byLang) as $code) {
        $code = (string) $code;
        if (!in_array($code, $order, true)) {
            $order[] = $code;
        }
    }
    foreach ($order as $code) {
        $value = trim((string) ($byLang[$code][$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Map legacy articles.text_type_* int → body format enum.
 */
function ezines_v2_format_from_text_type(int $textType): string
{
    return match (ezn_normalize_article_text_type($textType)) {
        EZN_TEXT_TYPE_TEXT_PRE => 'text_pre',
        EZN_TEXT_TYPE_MARKDOWN => 'markdown',
        EZN_TEXT_TYPE_HTML_PRE => 'html_pre',
        default => 'html_pre',
    };
}

/**
 * Map body format enum → legacy text_type int.
 */
function ezines_v2_text_type_from_format(string $format): int
{
    return match ($format) {
        'text_pre' => EZN_TEXT_TYPE_TEXT_PRE,
        'markdown' => EZN_TEXT_TYPE_MARKDOWN,
        'legacy' => EZN_TEXT_TYPE_LEGACY,
        'html' => EZN_TEXT_TYPE_HTML_PRE,
        default => EZN_TEXT_TYPE_HTML_PRE,
    };
}

/**
 * @return array<string, array<string, mixed>>
 */
function ezines_v2_load_i18n(mysqli $db, string $table, string $idCol, int $id): array
{
    $allowed = [
        'ezine_i18n' => 'ezine_id',
        'ezine_issue_i18n' => 'issue_id',
        'ezine_article_i18n' => 'article_id',
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $idCol) {
        return [];
    }
    $out = [];
    $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `{$idCol}`=?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $out[(string) $row['lang']] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function ezines_v2_load_article_bodies(mysqli $db, int $articleId): array
{
    $out = [];
    $stmt = $db->prepare(
        'SELECT lang, format, body, is_primary, source_hash, updated_at
         FROM ezine_article_bodies WHERE article_id=? ORDER BY lang, is_primary DESC, format'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $out[] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * Primary body for lang with fallback chain.
 *
 * @param list<array<string, mixed>> $bodies
 * @return array{body:string,format:string,lang:string}|null
 */
function ezines_v2_pick_primary_body(array $bodies, string $wantLang, string $original = 'ru'): ?array
{
    $primaries = [];
    foreach ($bodies as $row) {
        if (!(int) ($row['is_primary'] ?? 0)) {
            continue;
        }
        $primaries[(string) $row['lang']] = $row;
    }
    if ($primaries === []) {
        return null;
    }
    $order = [];
    $wantLang = ezines_v2_lang_code($wantLang);
    $sibling = $wantLang === 'sk' ? 'cs' : ($wantLang === 'cs' ? 'sk' : '');
    foreach ([$wantLang, $sibling, $original, 'ru'] as $code) {
        $code = ezines_v2_lang_code($code);
        if ($code !== '' && !in_array($code, $order, true)) {
            $order[] = $code;
        }
    }
    foreach (array_keys($primaries) as $code) {
        if (!in_array($code, $order, true)) {
            $order[] = $code;
        }
    }
    foreach ($order as $code) {
        if (!isset($primaries[$code])) {
            continue;
        }
        $row = $primaries[$code];
        $body = (string) ($row['body'] ?? '');
        if ($body === '') {
            continue;
        }

        return [
            'body' => $body,
            'format' => (string) ($row['format'] ?? 'html_pre'),
            'lang' => $code,
        ];
    }

    return null;
}

/**
 * Non-primary markdown alternate for a language (or original).
 *
 * @param list<array<string, mixed>> $bodies
 */
function ezines_v2_pick_markdown_alt(array $bodies, string $wantLang, string $original = 'ru'): string
{
    $order = [];
    foreach ([$wantLang, $original, 'ru'] as $code) {
        $code = ezines_v2_lang_code($code);
        if (!in_array($code, $order, true)) {
            $order[] = $code;
        }
    }
    foreach ($bodies as $row) {
        if ((string) ($row['format'] ?? '') !== 'markdown') {
            continue;
        }
        if ((int) ($row['is_primary'] ?? 0) === 1) {
            continue;
        }
        $lang = (string) ($row['lang'] ?? '');
        $body = (string) ($row['body'] ?? '');
        if ($body === '' || !in_array($lang, $order, true)) {
            continue;
        }
        // Prefer earlier in order via second pass
    }
    foreach ($order as $code) {
        foreach ($bodies as $row) {
            if ((string) ($row['format'] ?? '') !== 'markdown') {
                continue;
            }
            if ((int) ($row['is_primary'] ?? 0) === 1) {
                continue;
            }
            if ((string) ($row['lang'] ?? '') !== $code) {
                continue;
            }
            $body = (string) ($row['body'] ?? '');
            if ($body !== '') {
                return $body;
            }
        }
    }

    return '';
}

/**
 * @return array<string, mixed>|null
 */
function ezines_v2_find_by_legacy_press(mysqli $db, int $legacyPressId): ?array
{
    if ($legacyPressId <= 0 || !ezines_v2_ready($db)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM ezines WHERE legacy_press_id=? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $legacyPressId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row ?: null;
}

/**
 * @return array<string, mixed>|null
 */
function ezines_v2_find_by_legacy_issue(mysqli $db, int $legacyIssueId): ?array
{
    if ($legacyIssueId <= 0 || !ezines_v2_ready($db)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM ezine_issues WHERE legacy_issue_id=? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $legacyIssueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row ?: null;
}

/**
 * @return array<string, mixed>|null
 */
function ezines_v2_find_by_legacy_article(mysqli $db, int $legacyArticleId): ?array
{
    if ($legacyArticleId <= 0 || !ezines_v2_ready($db)) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM ezine_articles WHERE legacy_article_id=? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $legacyArticleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Overlay press legacy row with v2 i18n for the UI lang (keeps legacy keys).
 *
 * @param array<string, mixed> $press
 * @return array<string, mixed>
 */
function ezines_v2_overlay_press(mysqli $db, array $press, string $uiLang): array
{
    $legacyId = (int) ($press['id'] ?? $press['press_id'] ?? 0);
    $ezine = ezines_v2_find_by_legacy_press($db, $legacyId);
    if ($ezine === null) {
        return $press;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_i18n', 'ezine_id', (int) $ezine['id']);
    $orig = (string) ($ezine['original_language'] ?? 'ru');
    $code = ezines_v2_lang_code($uiLang);
    $title = ezines_v2_i18n_pick($byLang, $code, 'title', $orig);
    $desc = ezines_v2_i18n_pick($byLang, $code, 'description', $orig);
    $meta = ezines_v2_i18n_pick($byLang, $code, 'meta_description', $orig);
    if ($title !== '') {
        $press['title'] = $title;
        $press['press_title'] = $title;
    }
    if ($desc !== '') {
        $press['description'] = $desc;
        if ($code === 'en') {
            $press['description_en'] = $desc;
        } else {
            $press['description_ru'] = $desc;
        }
    }
    if ($meta !== '') {
        $press['meta_description'] = $meta;
        if ($code === 'en') {
            $press['meta_description_en'] = $meta;
        } else {
            $press['meta_description_ru'] = $meta;
        }
    }
    foreach (['ru', 'en'] as $slugLang) {
        $slug = trim((string) ($byLang[$slugLang]['slug'] ?? ''));
        if ($slug !== '') {
            $press['slug_' . $slugLang] = $slug;
            if ($slugLang === 'ru') {
                $press['press_slug_ru'] = $slug;
            } else {
                $press['press_slug_en'] = $slug;
            }
        }
    }
    $press['ezine_v2_id'] = (int) $ezine['id'];
    $press['ezine_original_language'] = $orig;

    return $press;
}

/**
 * @param array<string, mixed> $issue
 * @return array<string, mixed>
 */
function ezines_v2_overlay_issue(mysqli $db, array $issue, string $uiLang): array
{
    $legacyId = (int) ($issue['id'] ?? 0);
    $row = ezines_v2_find_by_legacy_issue($db, $legacyId);
    if ($row === null) {
        return $issue;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_issue_i18n', 'issue_id', (int) $row['id']);
    $ezine = null;
    $ezineId = (int) ($row['ezine_id'] ?? 0);
    if ($ezineId > 0) {
        $st = $db->prepare('SELECT original_language FROM ezines WHERE id=? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $ezineId);
            $st->execute();
            $ezine = $st->get_result()->fetch_assoc() ?: null;
            $st->close();
        }
    }
    $orig = (string) ($ezine['original_language'] ?? 'ru');
    $code = ezines_v2_lang_code($uiLang);
    $title = ezines_v2_i18n_pick($byLang, $code, 'title', $orig);
    $desc = ezines_v2_i18n_pick($byLang, $code, 'description', $orig);
    $meta = ezines_v2_i18n_pick($byLang, $code, 'meta_description', $orig);
    if ($title !== '') {
        $issue['title'] = $title;
    }
    if ($desc !== '') {
        $issue['description'] = $desc;
        if ($code === 'en') {
            $issue['description_en'] = $desc;
        } else {
            $issue['description_ru'] = $desc;
        }
    }
    if ($meta !== '') {
        $issue['meta_description'] = $meta;
        if ($code === 'en') {
            $issue['meta_description_en'] = $meta;
        } else {
            $issue['meta_description_ru'] = $meta;
        }
    }
    foreach (['ru', 'en'] as $slugLang) {
        $slug = trim((string) ($byLang[$slugLang]['slug'] ?? ''));
        if ($slug !== '') {
            $issue['slug_' . $slugLang] = $slug;
        }
    }
    $issue['ezine_issue_v2_id'] = (int) $row['id'];

    return $issue;
}

/**
 * Overlay legacy article row fields + return body/md from v2 when present.
 *
 * @param array<string, mixed> $article
 * @return array{
 *   article: array<string, mixed>,
 *   body: ?array{body:string,format:string,lang:string},
 *   md_alt: string,
 *   content_langs: list<string>
 * }
 */
function ezines_v2_overlay_article(mysqli $db, array $article, string $uiLang): array
{
    $legacyId = (int) ($article['id'] ?? 0);
    $row = ezines_v2_find_by_legacy_article($db, $legacyId);
    if ($row === null) {
        return [
            'article' => $article,
            'body' => null,
            'md_alt' => '',
            'content_langs' => [],
        ];
    }
    $articleId = (int) $row['id'];
    $orig = (string) ($row['original_language'] ?? 'ru');
    $byLang = ezines_v2_load_i18n($db, 'ezine_article_i18n', 'article_id', $articleId);
    $bodies = ezines_v2_load_article_bodies($db, $articleId);
    $code = ezines_v2_lang_code($uiLang);

    $title = ezines_v2_i18n_pick($byLang, $code, 'title', $orig);
    if ($title !== '') {
        if ($code === 'en') {
            $article['title_eng'] = $title;
        } else {
            $article['title'] = $title;
        }
    }
    $ruTitle = trim((string) ($byLang['ru']['title'] ?? ''));
    $enTitle = trim((string) ($byLang['en']['title'] ?? ''));
    if ($ruTitle !== '') {
        $article['title'] = $ruTitle;
    }
    if ($enTitle !== '') {
        $article['title_eng'] = $enTitle;
    }

    $meta = ezines_v2_i18n_pick($byLang, $code, 'meta_description', $orig);
    if ($meta !== '') {
        if ($code === 'en') {
            $article['meta_description_en'] = $meta;
        } else {
            $article['meta_description_ru'] = $meta;
        }
    }
    foreach (['ru', 'en'] as $slugLang) {
        $slug = trim((string) ($byLang[$slugLang]['slug'] ?? ''));
        if ($slug !== '') {
            $article['slug_' . $slugLang] = $slug;
        }
        $m = trim((string) ($byLang[$slugLang]['meta_description'] ?? ''));
        if ($m !== '') {
            $article['meta_description_' . $slugLang] = $m;
        }
    }

    $body = ezines_v2_pick_primary_body($bodies, $code, $orig);
    if ($body !== null) {
        $tt = ezines_v2_text_type_from_format($body['format']);
        if ($body['lang'] === 'en') {
            $article['text_en'] = $body['body'];
            $article['text_type_en'] = $tt;
        } else {
            $article['text_ru'] = $body['body'];
            $article['text_type_ru'] = $tt;
            $article['text_type'] = $tt;
        }
        // Also expose wanted-lang body into the active column for renderers that only look at UI lang.
        if ($code === 'en') {
            $article['text_en'] = $body['body'];
            $article['text_type_en'] = $tt;
        } else {
            $article['text_ru'] = $body['body'];
            $article['text_type_ru'] = $tt;
        }
    }

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
    $contentLangs = array_values(array_unique($contentLangs));

    $article['ezine_article_v2_id'] = $articleId;
    $article['original_language'] = $orig;
    $article['content_langs'] = $contentLangs;

    return [
        'article' => $article,
        'body' => $body,
        'md_alt' => ezines_v2_pick_markdown_alt($bodies, $code, $orig),
        'content_langs' => $contentLangs,
    ];
}

function ezines_v2_null_if_empty(string $value): ?string
{
    $value = trim($value);

    return $value === '' ? null : $value;
}

/** Clip UTF-8 string to VARCHAR length used by ezines v2 i18n meta columns. */
function ezines_v2_clip_meta(string $value, int $maxLen = 512): string
{
    if ($maxLen <= 0) {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') <= $maxLen) {
            return $value;
        }

        return mb_substr($value, 0, $maxLen, 'UTF-8');
    }
    if (strlen($value) <= $maxLen) {
        return $value;
    }

    return substr($value, 0, $maxLen);
}
