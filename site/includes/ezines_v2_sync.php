<?php

declare(strict_types=1);

require_once __DIR__ . '/ezines_v2.php';

/**
 * Upsert ezine (+ i18n) from a legacy press row. Returns ezines.id or 0.
 *
 * @param array<string, mixed> $press
 */
function ezines_v2_sync_press(mysqli $db, array $press, string $originalLanguage = 'ru'): int
{
    if (!ezines_v2_ready($db)) {
        return 0;
    }
    $legacyId = (int) ($press['id'] ?? 0);
    if ($legacyId <= 0) {
        return 0;
    }
    $originalLanguage = ezines_v2_lang_code($originalLanguage);
    $type = (int) ($press['type'] ?? 0);
    $cityId = (int) ($press['city'] ?? 0);
    $yearsFrom = (int) ($press['years_from'] ?? 0);
    $yearsTo = (int) ($press['years_to'] ?? 0);
    $numbers = (int) ($press['numbers'] ?? 0);
    $onlineIssues = (int) ($press['online_issues'] ?? 0);
    $onlineArticles = (int) ($press['online_articles'] ?? 0);
    $ex = (string) ($press['ex'] ?? '');

    $existing = ezines_v2_find_by_legacy_press($db, $legacyId);
    if ($existing === null) {
        if ($cityId > 0) {
            $stmt = $db->prepare(
                'INSERT INTO ezines
                 (legacy_press_id, original_language, type, city_id, years_from, years_to, numbers, online_issues, online_articles, ex)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param(
                'isiiiiiiis',
                $legacyId,
                $originalLanguage,
                $type,
                $cityId,
                $yearsFrom,
                $yearsTo,
                $numbers,
                $onlineIssues,
                $onlineArticles,
                $ex
            );
        } else {
            $stmt = $db->prepare(
                'INSERT INTO ezines
                 (legacy_press_id, original_language, type, city_id, years_from, years_to, numbers, online_issues, online_articles, ex)
                 VALUES (?,?,?,NULL,?,?,?,?,?,?)'
            );
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param(
                'isiiiiiis',
                $legacyId,
                $originalLanguage,
                $type,
                $yearsFrom,
                $yearsTo,
                $numbers,
                $onlineIssues,
                $onlineArticles,
                $ex
            );
        }
        if (!$stmt->execute()) {
            $stmt->close();

            return 0;
        }
        $ezineId = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $ezineId = (int) $existing['id'];
        if ($cityId > 0) {
            $stmt = $db->prepare(
                'UPDATE ezines SET original_language=?, type=?, city_id=?, years_from=?, years_to=?,
                 numbers=?, online_issues=?, online_articles=?, ex=? WHERE id=? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param(
                    'siiiiiiisi',
                    $originalLanguage,
                    $type,
                    $cityId,
                    $yearsFrom,
                    $yearsTo,
                    $numbers,
                    $onlineIssues,
                    $onlineArticles,
                    $ex,
                    $ezineId
                );
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $db->prepare(
                'UPDATE ezines SET original_language=?, type=?, city_id=NULL, years_from=?, years_to=?,
                 numbers=?, online_issues=?, online_articles=?, ex=? WHERE id=? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param(
                    'siiiiiisi',
                    $originalLanguage,
                    $type,
                    $yearsFrom,
                    $yearsTo,
                    $numbers,
                    $onlineIssues,
                    $onlineArticles,
                    $ex,
                    $ezineId
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $title = trim((string) ($press['title'] ?? ''));
    if ($title === '') {
        $title = 'Press #' . $legacyId;
    }

    ezines_v2_upsert_ezine_i18n($db, $ezineId, $originalLanguage, [
        'title' => $title,
        'slug' => (string) ($press['slug_' . $originalLanguage] ?? ($originalLanguage === 'en' ? ($press['slug_en'] ?? '') : ($press['slug_ru'] ?? ''))),
        'description' => (string) ($press['description_' . $originalLanguage] ?? ($originalLanguage === 'en' ? ($press['description_en'] ?? '') : ($press['description_ru'] ?? ''))),
        'meta_description' => (string) ($press['meta_description_' . $originalLanguage] ?? ''),
    ]);

    // Always sync ru/en catalog fields when present.
    foreach (['ru', 'en'] as $lang) {
        $slug = trim((string) ($press['slug_' . $lang] ?? ''));
        $desc = (string) ($press['description_' . $lang] ?? '');
        $meta = (string) ($press['meta_description_' . $lang] ?? '');
        if ($lang === $originalLanguage && $slug === '' && $desc === '' && $meta === '') {
            continue;
        }
        if ($lang !== $originalLanguage && $slug === '' && $desc === '' && $meta === '') {
            continue;
        }
        ezines_v2_upsert_ezine_i18n($db, $ezineId, $lang, [
            'title' => $lang === $originalLanguage ? $title : ($title !== '' ? $title : 'Press #' . $legacyId),
            'slug' => $slug,
            'description' => $desc,
            'meta_description' => $meta,
        ]);
    }

    return $ezineId;
}

/**
 * @param array{title?:string,slug?:string,description?:string,meta_description?:string} $fields
 */
function ezines_v2_upsert_ezine_i18n(mysqli $db, int $ezineId, string $lang, array $fields): void
{
    $lang = ezines_v2_lang_code($lang);
    $title = (string) ($fields['title'] ?? '');
    $slug = ezines_v2_null_if_empty((string) ($fields['slug'] ?? ''));
    $description = (string) ($fields['description'] ?? '');
    $meta = ezines_v2_clip_meta((string) ($fields['meta_description'] ?? ''), 256);
    $descBind = $description === '' ? null : $description;

    $stmt = $db->prepare(
        'INSERT INTO ezine_i18n (ezine_id, lang, title, slug, description, meta_description)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           title=VALUES(title),
           slug=VALUES(slug),
           description=VALUES(description),
           meta_description=VALUES(meta_description)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isssss', $ezineId, $lang, $title, $slug, $descBind, $meta);
    $stmt->execute();
    $stmt->close();
}

/**
 * @param array<string, mixed> $issue
 */
function ezines_v2_sync_issue(mysqli $db, array $issue, int $ezineId): int
{
    if (!ezines_v2_ready($db) || $ezineId <= 0) {
        return 0;
    }
    $legacyId = (int) ($issue['id'] ?? 0);
    if ($legacyId <= 0) {
        return 0;
    }
    $issueDate = (int) ($issue['date'] ?? 0);
    $sortOrder = (int) ($issue['sort_order'] ?? 0);
    $views = (int) ($issue['views'] ?? 0);
    $missing = (int) ($issue['missing'] ?? 0);

    $existing = ezines_v2_find_by_legacy_issue($db, $legacyId);
    if ($existing === null) {
        $stmt = $db->prepare(
            'INSERT INTO ezine_issues (ezine_id, legacy_issue_id, issue_date, sort_order, views, is_missing)
             VALUES (?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('iiiiii', $ezineId, $legacyId, $issueDate, $sortOrder, $views, $missing);
        if (!$stmt->execute()) {
            $stmt->close();

            return 0;
        }
        $issueId = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $issueId = (int) $existing['id'];
        $stmt = $db->prepare(
            'UPDATE ezine_issues SET ezine_id=?, issue_date=?, sort_order=?, views=?, is_missing=?
             WHERE id=? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('iiiiii', $ezineId, $issueDate, $sortOrder, $views, $missing, $issueId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $title = trim((string) ($issue['title'] ?? ''));
    foreach (['ru', 'en'] as $lang) {
        $slug = trim((string) ($issue['slug_' . $lang] ?? ''));
        $desc = (string) ($issue['description_' . $lang] ?? '');
        $meta = (string) ($issue['meta_description_' . $lang] ?? '');
        if ($lang === 'en' && $slug === '' && $desc === '' && $meta === '' && $title === '') {
            continue;
        }
        ezines_v2_upsert_issue_i18n($db, $issueId, $ezineId, $lang, [
            'title' => $title,
            'slug' => $slug,
            'description' => $desc,
            'meta_description' => $meta,
        ]);
    }

    return $issueId;
}

/**
 * @param array{title?:string,slug?:string,description?:string,meta_description?:string} $fields
 */
function ezines_v2_upsert_issue_i18n(mysqli $db, int $issueId, int $ezineId, string $lang, array $fields): void
{
    $lang = ezines_v2_lang_code($lang);
    $title = (string) ($fields['title'] ?? '');
    $slug = ezines_v2_null_if_empty((string) ($fields['slug'] ?? ''));
    $description = (string) ($fields['description'] ?? '');
    $meta = ezines_v2_clip_meta((string) ($fields['meta_description'] ?? ''), 512);
    $descBind = $description === '' ? null : $description;

    $stmt = $db->prepare(
        'INSERT INTO ezine_issue_i18n (issue_id, lang, ezine_id, title, slug, description, meta_description)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           ezine_id=VALUES(ezine_id),
           title=VALUES(title),
           slug=VALUES(slug),
           description=VALUES(description),
           meta_description=VALUES(meta_description)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isissss', $issueId, $lang, $ezineId, $title, $slug, $descBind, $meta);
    $stmt->execute();
    $stmt->close();
}

/**
 * @param array<string, mixed> $article
 */
function ezines_v2_sync_article(mysqli $db, array $article, int $issueId, string $originalLanguage = 'ru'): int
{
    if (!ezines_v2_ready($db) || $issueId <= 0) {
        return 0;
    }
    $legacyId = (int) ($article['id'] ?? 0);
    if ($legacyId <= 0) {
        return 0;
    }
    $originalLanguage = ezines_v2_lang_code($originalLanguage);
    $sortOrder = (int) ($article['number'] ?? 1);
    $views = (int) ($article['views'] ?? 0);
    $isActive = ((int) ($article['temp'] ?? 0) === 0) ? 1 : 0;
    $file = (string) ($article['file'] ?? '');
    $name = (string) ($article['name'] ?? '');
    $publishedAt = (int) ($article['date'] ?? 0);

    $existing = ezines_v2_find_by_legacy_article($db, $legacyId);
    if ($existing === null) {
        $stmt = $db->prepare(
            'INSERT INTO ezine_articles
             (issue_id, legacy_article_id, original_language, sort_order, views, is_active, legacy_file, legacy_name, published_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param(
            'iisiiissi',
            $issueId,
            $legacyId,
            $originalLanguage,
            $sortOrder,
            $views,
            $isActive,
            $file,
            $name,
            $publishedAt
        );
        if (!$stmt->execute()) {
            $stmt->close();

            return 0;
        }
        $articleId = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $articleId = (int) $existing['id'];
        $stmt = $db->prepare(
            'UPDATE ezine_articles SET issue_id=?, original_language=?, sort_order=?, views=?, is_active=?,
             legacy_file=?, legacy_name=?, published_at=? WHERE id=? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param(
                'isiiissii',
                $issueId,
                $originalLanguage,
                $sortOrder,
                $views,
                $isActive,
                $file,
                $name,
                $publishedAt,
                $articleId
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    $titleRu = trim((string) ($article['title'] ?? ''));
    $titleEn = trim((string) ($article['title_eng'] ?? ''));
    if ($titleRu !== '' || trim((string) ($article['slug_ru'] ?? '')) !== '' || trim((string) ($article['meta_description_ru'] ?? '')) !== '') {
        ezines_v2_upsert_article_i18n($db, $articleId, $issueId, 'ru', [
            'title' => $titleRu,
            'slug' => (string) ($article['slug_ru'] ?? ''),
            'meta_description' => (string) ($article['meta_description_ru'] ?? ''),
        ]);
    }
    if ($titleEn !== '' || trim((string) ($article['slug_en'] ?? '')) !== '' || trim((string) ($article['meta_description_en'] ?? '')) !== '') {
        ezines_v2_upsert_article_i18n($db, $articleId, $issueId, 'en', [
            'title' => $titleEn,
            'slug' => (string) ($article['slug_en'] ?? ''),
            'meta_description' => (string) ($article['meta_description_en'] ?? ''),
        ]);
    }

    $textRu = (string) ($article['text_ru'] ?? '');
    $textEn = (string) ($article['text_en'] ?? '');
    if ($textRu !== '') {
        ezines_v2_upsert_article_body(
            $db,
            $articleId,
            'ru',
            ezines_v2_format_from_text_type((int) ($article['text_type_ru'] ?? $article['text_type'] ?? 2)),
            $textRu,
            true
        );
    }
    if ($textEn !== '') {
        ezines_v2_upsert_article_body(
            $db,
            $articleId,
            'en',
            ezines_v2_format_from_text_type((int) ($article['text_type_en'] ?? 2)),
            $textEn,
            true
        );
    }

    return $articleId;
}

/**
 * @param array{title?:string,slug?:string,meta_description?:string} $fields
 */
function ezines_v2_upsert_article_i18n(mysqli $db, int $articleId, int $issueId, string $lang, array $fields): void
{
    $lang = ezines_v2_lang_code($lang);
    $title = (string) ($fields['title'] ?? '');
    $slug = ezines_v2_null_if_empty((string) ($fields['slug'] ?? ''));
    $meta = ezines_v2_clip_meta((string) ($fields['meta_description'] ?? ''), 512);

    $stmt = $db->prepare(
        'INSERT INTO ezine_article_i18n (article_id, lang, issue_id, title, slug, meta_description)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           issue_id=VALUES(issue_id),
           title=VALUES(title),
           slug=VALUES(slug),
           meta_description=VALUES(meta_description)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isisss', $articleId, $lang, $issueId, $title, $slug, $meta);
    $stmt->execute();
    $stmt->close();
}

function ezines_v2_upsert_article_body(
    mysqli $db,
    int $articleId,
    string $lang,
    string $format,
    string $body,
    bool $isPrimary
): void {
    $lang = ezines_v2_lang_code($lang);
    $hash = $body === '' ? '' : sha1($body);
    $primary = $isPrimary ? 1 : 0;
    $stmt = $db->prepare(
        'INSERT INTO ezine_article_bodies (article_id, lang, format, body, is_primary, source_hash)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           body=VALUES(body),
           is_primary=VALUES(is_primary),
           source_hash=VALUES(source_hash)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isssis', $articleId, $lang, $format, $body, $primary, $hash);
    $stmt->execute();
    $stmt->close();
}

/**
 * Sync authors_articles → ezine_article_authors for one article.
 */
function ezines_v2_sync_article_authors(mysqli $db, int $legacyArticleId, int $articleId): void
{
    if ($legacyArticleId <= 0 || $articleId <= 0 || !ezines_v2_ready($db)) {
        return;
    }
    $db->query('DELETE FROM ezine_article_authors WHERE article_id=' . (int) $articleId);
    $stmt = $db->prepare('SELECT id_author FROM authors_articles WHERE id_article=? ORDER BY id ASC');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $legacyArticleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $sort = 0;
    $ins = $db->prepare('INSERT IGNORE INTO ezine_article_authors (article_id, author_id, sort_order) VALUES (?,?,?)');
    while ($res && ($row = $res->fetch_assoc())) {
        $authorId = (int) ($row['id_author'] ?? 0);
        if ($authorId <= 0 || !$ins) {
            continue;
        }
        $ins->bind_param('iii', $articleId, $authorId, $sort);
        $ins->execute();
        $sort++;
    }
    if ($ins) {
        $ins->close();
    }
    $stmt->close();
}
