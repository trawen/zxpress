<?php

declare(strict_types=1);

require_once __DIR__ . '/ezines_v2.php';
require_once __DIR__ . '/ezines_v2_sync.php';
require_once __DIR__ . '/ezine_slugs.php';
require_once __DIR__ . '/periodicals_slugs.php';

/**
 * @return list<array<string,mixed>>
 */
function ezines_v2_admin_languages(mysqli $db): array
{
    $out = [];
    $z = db_select($db, 'SELECT id, code, name FROM languages ORDER BY sort_order ASC, id ASC');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $out[] = $row;
    }

    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function ezines_v2_admin_list(mysqli $db, string $letter = ''): array
{
    $letter = mb_strtoupper(trim($letter), 'UTF-8');
    $sql = "SELECT e.id, e.is_active, e.type, e.years_from, e.years_to, e.legacy_press_id, e.original_language,
            COALESCE(i_ru.title, i_en.title, CONCAT('Ezine #', e.id)) AS title,
            (SELECT GROUP_CONCAT(i.lang ORDER BY i.lang SEPARATOR ',') FROM ezine_i18n i WHERE i.ezine_id=e.id) AS langs,
            (SELECT COUNT(*) FROM ezine_issues iss WHERE iss.ezine_id=e.id) AS issues_count,
            (SELECT COUNT(*) FROM ezine_articles a
               INNER JOIN ezine_issues iss2 ON iss2.id=a.issue_id WHERE iss2.ezine_id=e.id) AS articles_count
         FROM ezines e
         LEFT JOIN ezine_i18n i_ru ON i_ru.ezine_id=e.id AND i_ru.lang='ru'
         LEFT JOIN ezine_i18n i_en ON i_en.ezine_id=e.id AND i_en.lang='en'";
    if ($letter !== '') {
        $sql .= " WHERE UPPER(LEFT(COALESCE(i_ru.title, i_en.title, ''), 1)) = ?";
        $stmt = $db->prepare($sql . ' ORDER BY title ASC');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $letter);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $db->query($sql . ' ORDER BY title ASC');
    }
    $out = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $out[] = $row;
    }

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,description:string,meta_description:string}>
 */
function ezines_v2_admin_empty_ezine_i18n(array $languages): array
{
    $out = [];
    foreach ($languages as $lang) {
        $code = (string) ($lang['code'] ?? '');
        if ($code === '') {
            continue;
        }
        $out[$code] = [
            'title' => '',
            'slug' => '',
            'description' => '',
            'meta_description' => '',
        ];
    }

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,description:string,meta_description:string}>
 */
function ezines_v2_admin_load_ezine_i18n(mysqli $db, int $ezineId, array $languages): array
{
    $out = ezines_v2_admin_empty_ezine_i18n($languages);
    if ($ezineId <= 0) {
        return $out;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_i18n', 'ezine_id', $ezineId);
    foreach ($byLang as $code => $row) {
        $out[$code] = [
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'meta_description' => (string) ($row['meta_description'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,description:string,meta_description:string}>
 */
function ezines_v2_admin_post_i18n_meta(array $languages): array
{
    $raw = $_POST['i18n'] ?? [];
    $out = ezines_v2_admin_empty_ezine_i18n($languages);
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($languages as $lang) {
        $code = (string) ($lang['code'] ?? '');
        if ($code === '' || !isset($raw[$code]) || !is_array($raw[$code])) {
            continue;
        }
        $row = $raw[$code];
        $out[$code] = [
            'title' => trim((string) ($row['title'] ?? '')),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'description' => (string) ($row['description'] ?? ''),
            'meta_description' => trim((string) ($row['meta_description'] ?? '')),
        ];
    }

    return $out;
}

/**
 * @param array<string, array{title:string,slug:string,description:string,meta_description:string}> $i18n
 * @return array{ok:bool,id:int,error:string}
 */
function ezines_v2_admin_save_ezine(mysqli $db, int $id, array $languages): array
{
    $orig = ezines_v2_lang_code((string) ($_POST['original_language'] ?? 'ru'));
    $type = (int) ($_POST['type'] ?? 0);
    $cityId = (int) ($_POST['city_id'] ?? 0);
    $yearsFrom = (int) ($_POST['years_from'] ?? 0);
    $yearsTo = (int) ($_POST['years_to'] ?? 0);
    $numbers = (int) ($_POST['numbers'] ?? 0);
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $i18n = ezines_v2_admin_post_i18n_meta($languages);

    $hasTitle = false;
    foreach ($i18n as $row) {
        if (trim($row['title']) !== '') {
            $hasTitle = true;
            break;
        }
    }
    if (!$hasTitle) {
        return ['ok' => false, 'id' => $id, 'error' => 'Нужно название хотя бы на одном языке'];
    }

    if ($id <= 0) {
        if ($cityId > 0) {
            $stmt = $db->prepare(
                'INSERT INTO ezines (original_language, type, city_id, years_from, years_to, numbers, ex, is_active)
                 VALUES (?,?,?,?,?,?,\'\',?)'
            );
            $stmt->bind_param('siiiiii', $orig, $type, $cityId, $yearsFrom, $yearsTo, $numbers, $isActive);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO ezines (original_language, type, city_id, years_from, years_to, numbers, ex, is_active)
                 VALUES (?, ?, NULL, ?, ?, ?, \'\', ?)'
            );
            $stmt->bind_param('siiiii', $orig, $type, $yearsFrom, $yearsTo, $numbers, $isActive);
        }
        if (!$stmt || !$stmt->execute()) {
            return ['ok' => false, 'id' => 0, 'error' => 'Не удалось создать издание'];
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        if ($cityId > 0) {
            $stmt = $db->prepare(
                'UPDATE ezines SET original_language=?, type=?, city_id=?, years_from=?, years_to=?, numbers=?, is_active=?
                 WHERE id=? LIMIT 1'
            );
            $stmt->bind_param('siiiiiii', $orig, $type, $cityId, $yearsFrom, $yearsTo, $numbers, $isActive, $id);
        } else {
            $stmt = $db->prepare(
                'UPDATE ezines SET original_language=?, type=?, city_id=NULL, years_from=?, years_to=?, numbers=?, is_active=?
                 WHERE id=? LIMIT 1'
            );
            $stmt->bind_param('siiiiii', $orig, $type, $yearsFrom, $yearsTo, $numbers, $isActive, $id);
        }
        if (!$stmt || !$stmt->execute()) {
            return ['ok' => false, 'id' => $id, 'error' => 'Не удалось сохранить издание'];
        }
        $stmt->close();
    }

    foreach ($i18n as $lang => $row) {
        $title = $row['title'];
        $slug = $row['slug'];
        $desc = $row['description'];
        $meta = $row['meta_description'];
        if ($title === '' && $slug === '' && trim($desc) === '' && $meta === '') {
            $del = $db->prepare('DELETE FROM ezine_i18n WHERE ezine_id=? AND lang=? LIMIT 1');
            if ($del) {
                $del->bind_param('is', $id, $lang);
                $del->execute();
                $del->close();
            }
            continue;
        }
        if ($title === '') {
            $title = 'Ezine #' . $id;
        }
        if ($slug === '') {
            $slug = per_slug_from_title($title);
        }
        ezines_v2_upsert_ezine_i18n($db, $id, $lang, [
            'title' => $title,
            'slug' => $slug,
            'description' => $desc,
            'meta_description' => $meta,
        ]);
    }

    // Dual-write ru/en into legacy press when linked.
    $st = $db->prepare('SELECT legacy_press_id FROM ezines WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $legacyPressId = (int) (($st->get_result()->fetch_assoc()['legacy_press_id'] ?? 0));
    $st->close();
    if ($legacyPressId > 0) {
        $titleRu = $i18n['ru']['title'] !== '' ? $i18n['ru']['title'] : ($i18n[$orig]['title'] ?? '');
        $slugRu = $i18n['ru']['slug'] ?? '';
        $slugEn = $i18n['en']['slug'] ?? '';
        $descRu = $i18n['ru']['description'] ?? '';
        $descEn = $i18n['en']['description'] ?? '';
        $metaRu = $i18n['ru']['meta_description'] ?? '';
        $metaEn = $i18n['en']['meta_description'] ?? '';
        $upd = $db->prepare(
            'UPDATE press SET title=?, type=?, city=?, numbers=?, slug_ru=?, slug_en=?,
             description_ru=?, description_en=?, meta_description_ru=?, meta_description_en=?
             WHERE id=? LIMIT 1'
        );
        if ($upd) {
            $cityBind = $cityId > 0 ? $cityId : 0;
            $upd->bind_param(
                'siiissssssi',
                $titleRu,
                $type,
                $cityBind,
                $numbers,
                $slugRu,
                $slugEn,
                $descRu,
                $descEn,
                $metaRu,
                $metaEn,
                $legacyPressId
            );
            $upd->execute();
            $upd->close();
        }
    }

    return ['ok' => true, 'id' => $id, 'error' => ''];
}

/**
 * @return list<array<string,mixed>>
 */
function ezines_v2_admin_issues(mysqli $db, int $ezineId): array
{
    $out = [];
    $stmt = $db->prepare(
        "SELECT i.id, i.issue_date, i.sort_order, i.is_missing, i.is_active, i.legacy_issue_id, i.views,
                COALESCE(ii_ru.title, ii_en.title, CONCAT('#', i.id)) AS title,
                (SELECT COUNT(*) FROM ezine_articles a WHERE a.issue_id=i.id) AS articles_count
         FROM ezine_issues i
         LEFT JOIN ezine_issue_i18n ii_ru ON ii_ru.issue_id=i.id AND ii_ru.lang='ru'
         LEFT JOIN ezine_issue_i18n ii_en ON ii_en.issue_id=i.id AND ii_en.lang='en'
         WHERE i.ezine_id=?
         ORDER BY i.sort_order ASC, i.id ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $ezineId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $out[] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,description:string,meta_description:string}>
 */
function ezines_v2_admin_load_issue_i18n(mysqli $db, int $issueId, array $languages): array
{
    $out = ezines_v2_admin_empty_ezine_i18n($languages);
    if ($issueId <= 0) {
        return $out;
    }
    $byLang = ezines_v2_load_i18n($db, 'ezine_issue_i18n', 'issue_id', $issueId);
    foreach ($byLang as $code => $row) {
        $out[$code] = [
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'meta_description' => (string) ($row['meta_description'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return array{ok:bool,id:int,error:string}
 */
function ezines_v2_admin_save_issue(mysqli $db, int $id, int $ezineId, array $languages): array
{
    if ($ezineId <= 0) {
        return ['ok' => false, 'id' => 0, 'error' => 'Нет издания'];
    }
    $issueDate = (int) ($_POST['issue_date'] ?? 0);
    $dateRaw = trim((string) ($_POST['issue_date_fmt'] ?? ''));
    if ($dateRaw !== '' && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateRaw, $m)) {
        $issueDate = (int) mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]);
    }
    $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
    $isMissing = !empty($_POST['is_missing']) ? 1 : 0;
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $i18n = ezines_v2_admin_post_i18n_meta($languages);

    if ($id <= 0) {
        $stmt = $db->prepare(
            'INSERT INTO ezine_issues (ezine_id, issue_date, sort_order, is_missing, is_active) VALUES (?,?,?,?,?)'
        );
        if (!$stmt || !$stmt->bind_param('iiiii', $ezineId, $issueDate, $sortOrder, $isMissing, $isActive) || !$stmt->execute()) {
            return ['ok' => false, 'id' => 0, 'error' => 'Не удалось создать выпуск'];
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $stmt = $db->prepare(
            'UPDATE ezine_issues SET issue_date=?, sort_order=?, is_missing=?, is_active=? WHERE id=? AND ezine_id=? LIMIT 1'
        );
        if (!$stmt || !$stmt->bind_param('iiiiii', $issueDate, $sortOrder, $isMissing, $isActive, $id, $ezineId) || !$stmt->execute()) {
            return ['ok' => false, 'id' => $id, 'error' => 'Не удалось сохранить выпуск'];
        }
        $stmt->close();
    }

    foreach ($i18n as $lang => $row) {
        $title = $row['title'];
        $slug = $row['slug'];
        $desc = $row['description'];
        $meta = $row['meta_description'];
        if ($title === '' && $slug === '' && trim($desc) === '' && $meta === '') {
            $del = $db->prepare('DELETE FROM ezine_issue_i18n WHERE issue_id=? AND lang=? LIMIT 1');
            if ($del) {
                $del->bind_param('is', $id, $lang);
                $del->execute();
                $del->close();
            }
            continue;
        }
        if ($slug === '' && $title !== '') {
            $slug = per_slugify($title);
        }
        ezines_v2_upsert_issue_i18n($db, $id, $ezineId, $lang, [
            'title' => $title,
            'slug' => $slug,
            'description' => $desc,
            'meta_description' => $meta,
        ]);
    }

    ezines_v2_admin_ensure_legacy_issue($db, $id);

    return ['ok' => true, 'id' => $id, 'error' => ''];
}

/**
 * @return list<array<string,mixed>>
 */
function ezines_v2_admin_articles(mysqli $db, int $issueId): array
{
    $out = [];
    $stmt = $db->prepare(
        "SELECT a.id, a.sort_order, a.is_active, a.views, a.legacy_article_id, a.original_language,
                COALESCE(ai_ru.title, ai_en.title, CONCAT('Article #', a.id)) AS title,
                (SELECT GROUP_CONCAT(i.lang ORDER BY i.lang SEPARATOR ',') FROM ezine_article_i18n i WHERE i.article_id=a.id) AS langs
         FROM ezine_articles a
         LEFT JOIN ezine_article_i18n ai_ru ON ai_ru.article_id=a.id AND ai_ru.lang='ru'
         LEFT JOIN ezine_article_i18n ai_en ON ai_en.article_id=a.id AND ai_en.lang='en'
         WHERE a.issue_id=?
         ORDER BY a.sort_order ASC, a.id ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $issueId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $out[] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,meta_description:string,body:string,format:string}>
 */
function ezines_v2_admin_empty_article_i18n(array $languages): array
{
    $out = [];
    foreach ($languages as $lang) {
        $code = (string) ($lang['code'] ?? '');
        if ($code === '') {
            continue;
        }
        $out[$code] = [
            'title' => '',
            'slug' => '',
            'meta_description' => '',
            'body' => '',
            'format' => 'html_pre',
        ];
    }

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,meta_description:string,body:string,format:string}>
 */
function ezines_v2_admin_load_article_i18n(mysqli $db, int $articleId, array $languages): array
{
    $out = ezines_v2_admin_empty_article_i18n($languages);
    if ($articleId <= 0) {
        return $out;
    }
    $meta = ezines_v2_load_i18n($db, 'ezine_article_i18n', 'article_id', $articleId);
    $bodies = ezines_v2_load_article_bodies($db, $articleId);
    $primaryByLang = [];
    foreach ($bodies as $b) {
        if (!(int) ($b['is_primary'] ?? 0)) {
            continue;
        }
        $primaryByLang[(string) $b['lang']] = $b;
    }
    foreach ($languages as $lang) {
        $code = (string) ($lang['code'] ?? '');
        if ($code === '') {
            continue;
        }
        $out[$code]['title'] = (string) ($meta[$code]['title'] ?? '');
        $out[$code]['slug'] = (string) ($meta[$code]['slug'] ?? '');
        $out[$code]['meta_description'] = (string) ($meta[$code]['meta_description'] ?? '');
        if (isset($primaryByLang[$code])) {
            $out[$code]['body'] = (string) ($primaryByLang[$code]['body'] ?? '');
            $out[$code]['format'] = (string) ($primaryByLang[$code]['format'] ?? 'html_pre');
        }
    }

    return $out;
}

/**
 * @return array<string, array{title:string,slug:string,meta_description:string,body:string,format:string}>
 */
function ezines_v2_admin_post_article_i18n(array $languages): array
{
    $raw = $_POST['i18n'] ?? [];
    $out = ezines_v2_admin_empty_article_i18n($languages);
    if (!is_array($raw)) {
        return $out;
    }
    $allowedFormats = ['html_pre', 'text_pre', 'markdown', 'html', 'legacy'];
    foreach ($languages as $lang) {
        $code = (string) ($lang['code'] ?? '');
        if ($code === '' || !isset($raw[$code]) || !is_array($raw[$code])) {
            continue;
        }
        $row = $raw[$code];
        $format = (string) ($row['format'] ?? 'html_pre');
        if (!in_array($format, $allowedFormats, true)) {
            $format = 'html_pre';
        }
        $out[$code] = [
            'title' => trim((string) ($row['title'] ?? '')),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'meta_description' => trim((string) ($row['meta_description'] ?? '')),
            'body' => (string) ($row['body'] ?? ''),
            'format' => $format,
        ];
    }

    return $out;
}

/**
 * @return array{ok:bool,id:int,error:string}
 */
function ezines_v2_admin_save_article(mysqli $db, int $id, int $issueId, array $languages): array
{
    if ($issueId <= 0) {
        return ['ok' => false, 'id' => 0, 'error' => 'Нет выпуска'];
    }
    $orig = ezines_v2_lang_code((string) ($_POST['original_language'] ?? 'ru'));
    $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 1));
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $i18n = ezines_v2_admin_post_article_i18n($languages);

    $hasContent = false;
    foreach ($i18n as $row) {
        if ($row['title'] !== '' || trim($row['body']) !== '') {
            $hasContent = true;
            break;
        }
    }
    if (!$hasContent) {
        return ['ok' => false, 'id' => $id, 'error' => 'Нужны title или текст хотя бы на одном языке'];
    }

    if ($id <= 0) {
        $stmt = $db->prepare(
            'INSERT INTO ezine_articles (issue_id, original_language, sort_order, is_active) VALUES (?,?,?,?)'
        );
        if (!$stmt || !$stmt->bind_param('isii', $issueId, $orig, $sortOrder, $isActive) || !$stmt->execute()) {
            return ['ok' => false, 'id' => 0, 'error' => 'Не удалось создать статью'];
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $stmt = $db->prepare(
            'UPDATE ezine_articles SET original_language=?, sort_order=?, is_active=? WHERE id=? AND issue_id=? LIMIT 1'
        );
        if (!$stmt || !$stmt->bind_param('siiii', $orig, $sortOrder, $isActive, $id, $issueId) || !$stmt->execute()) {
            return ['ok' => false, 'id' => $id, 'error' => 'Не удалось сохранить статью'];
        }
        $stmt->close();
    }

    foreach ($i18n as $lang => $row) {
        $emptyMeta = $row['title'] === '' && $row['slug'] === '' && $row['meta_description'] === '';
        $emptyBody = trim($row['body']) === '';
        if ($emptyMeta && $emptyBody) {
            $del = $db->prepare('DELETE FROM ezine_article_i18n WHERE article_id=? AND lang=? LIMIT 1');
            if ($del) {
                $del->bind_param('is', $id, $lang);
                $del->execute();
                $del->close();
            }
            $delB = $db->prepare('DELETE FROM ezine_article_bodies WHERE article_id=? AND lang=? AND is_primary=1');
            if ($delB) {
                $delB->bind_param('is', $id, $lang);
                $delB->execute();
                $delB->close();
            }
            continue;
        }
        $slug = $row['slug'];
        if ($slug === '' && $row['title'] !== '') {
            $slug = ezn_truncate_article_slug(per_slug_from_title($row['title']));
        }
        ezines_v2_upsert_article_i18n($db, $id, $issueId, $lang, [
            'title' => $row['title'],
            'slug' => $slug,
            'meta_description' => $row['meta_description'],
        ]);
        if (!$emptyBody) {
            // Replace previous primary format for this lang if format changed.
            $db->query(
                'DELETE FROM ezine_article_bodies WHERE article_id=' . (int) $id
                . ' AND lang=\'' . $db->real_escape_string($lang) . '\' AND is_primary=1'
            );
            ezines_v2_upsert_article_body($db, $id, $lang, $row['format'], $row['body'], true);
        }
    }

    // Dual-write ru/en to legacy articles when linked.
    $st = $db->prepare('SELECT legacy_article_id FROM ezine_articles WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $legacyId = (int) (($st->get_result()->fetch_assoc()['legacy_article_id'] ?? 0));
    $st->close();
    if ($legacyId > 0) {
        $title = $i18n['ru']['title'] ?? '';
        $titleEng = $i18n['en']['title'] ?? '';
        $slugRu = $i18n['ru']['slug'] ?? '';
        $slugEn = $i18n['en']['slug'] ?? '';
        $metaRu = $i18n['ru']['meta_description'] ?? '';
        $metaEn = $i18n['en']['meta_description'] ?? '';
        $textRu = $i18n['ru']['body'] ?? '';
        $textEn = $i18n['en']['body'] ?? '';
        $ttRu = ezines_v2_text_type_from_format($i18n['ru']['format'] ?? 'html_pre');
        $ttEn = ezines_v2_text_type_from_format($i18n['en']['format'] ?? 'html_pre');
        $temp = $isActive ? 0 : 1;
        $upd = $db->prepare(
            'UPDATE articles SET title=?, title_eng=?, number=?, temp=?, text_type=?, text_type_ru=?, text_type_en=?,
             text_ru=?, text_en=?, meta_description_ru=?, meta_description_en=?, slug_ru=?, slug_en=?
             WHERE id=? LIMIT 1'
        );
        if ($upd) {
            $upd->bind_param(
                'ssiiiiissssssi',
                $title,
                $titleEng,
                $sortOrder,
                $temp,
                $ttRu,
                $ttRu,
                $ttEn,
                $textRu,
                $textEn,
                $metaRu,
                $metaEn,
                $slugRu,
                $slugEn,
                $legacyId
            );
            $upd->execute();
            $upd->close();
        }
    }

    return ['ok' => true, 'id' => $id, 'error' => ''];
}

/**
 * @return list<array{id:int,name:string}>
 */
function ezines_v2_admin_cities(mysqli $db): array
{
    $out = [];
    $z = db_select($db, 'SELECT id, name FROM cities ORDER BY name ASC');
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $out[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
    }

    return $out;
}

/**
 * @return array<int, string>
 */
function ezines_v2_admin_file_type_labels(): array
{
    return [
        0 => 'SCL/TRD',
        2 => 'FDI',
        3 => 'UDI',
        4 => 'TD0',
        5 => 'TAP',
        6 => 'TZX',
        7 => 'PDF',
        8 => 'TXT',
        9 => 'HTML',
        10 => 'DJVU',
    ];
}

/** @return list<string> */
function ezines_v2_admin_upload_extensions(): array
{
    return ['zip', 'rar', 'trd', 'scl', 'udi', 'fdi', 'tap', 'tzx', 'td0', 'pdf', 'txt', 'html', 'htm', 'djvu'];
}

function ezines_v2_admin_file_type_from_ext(string $ext): int
{
    return match ($ext) {
        'fdi' => 2,
        'udi' => 3,
        'td0' => 4,
        'tap' => 5,
        'tzx' => 6,
        'pdf' => 7,
        'txt' => 8,
        'html', 'htm' => 9,
        'djvu' => 10,
        default => 0,
    };
}

function ezines_v2_admin_upload_mime_allowed(string $ext, string $mime): bool
{
    $binary = ['trd', 'scl', 'udi', 'fdi', 'tap', 'tzx', 'td0'];
    if (in_array($ext, $binary, true)) {
        return true;
    }
    $allowed = [
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar' => ['application/x-rar-compressed', 'application/x-rar', 'application/vnd.rar', 'application/octet-stream'],
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'html' => ['text/html', 'application/octet-stream'],
        'htm' => ['text/html', 'application/octet-stream'],
        'djvu' => ['image/vnd.djvu', 'application/octet-stream'],
    ];
    if (!isset($allowed[$ext])) {
        return false;
    }
    if ($mime === '') {
        return true;
    }

    return in_array($mime, $allowed[$ext], true);
}

/**
 * Create/link legacy press for a v2 ezine. Returns legacy press id or 0.
 */
function ezines_v2_admin_ensure_legacy_press(mysqli $db, int $ezineId): int
{
    if ($ezineId <= 0) {
        return 0;
    }
    $st = $db->prepare('SELECT * FROM ezines WHERE id=? LIMIT 1');
    if (!$st) {
        return 0;
    }
    $st->bind_param('i', $ezineId);
    $st->execute();
    $ezine = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$ezine) {
        return 0;
    }
    $legacyId = (int) ($ezine['legacy_press_id'] ?? 0);
    if ($legacyId > 0) {
        return $legacyId;
    }

    $i18n = ezines_v2_load_i18n($db, 'ezine_i18n', 'ezine_id', $ezineId);
    $title = (string) ($i18n['ru']['title'] ?? '');
    if ($title === '') {
        $title = (string) ($i18n['en']['title'] ?? '');
    }
    if ($title === '') {
        $title = 'Ezine #' . $ezineId;
    }
    $slugRu = (string) ($i18n['ru']['slug'] ?? '');
    $slugEn = (string) ($i18n['en']['slug'] ?? '');
    $descRu = (string) ($i18n['ru']['description'] ?? '');
    $descEn = (string) ($i18n['en']['description'] ?? '');
    $metaRu = (string) ($i18n['ru']['meta_description'] ?? '');
    $metaEn = (string) ($i18n['en']['meta_description'] ?? '');
    $type = (int) ($ezine['type'] ?? 0);
    $city = (int) ($ezine['city_id'] ?? 0);
    $yearsFrom = (int) ($ezine['years_from'] ?? 0);
    $yearsTo = (int) ($ezine['years_to'] ?? 0);
    $numbers = (int) ($ezine['numbers'] ?? 0);
    $onlineIssues = (int) ($ezine['online_issues'] ?? 0);
    $onlineArticles = (int) ($ezine['online_articles'] ?? 0);
    $language = 0;
    $ex = (string) ($ezine['ex'] ?? '');

    $stmt = $db->prepare(
        'INSERT INTO press
         (title, type, city, years_from, years_to, numbers, online_issues, online_articles, language, ex,
          slug_ru, slug_en, description_ru, description_en, meta_description_ru, meta_description_en)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param(
        'siiiiiiiisssssss',
        $title,
        $type,
        $city,
        $yearsFrom,
        $yearsTo,
        $numbers,
        $onlineIssues,
        $onlineArticles,
        $language,
        $ex,
        $slugRu,
        $slugEn,
        $descRu,
        $descEn,
        $metaRu,
        $metaEn
    );
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }
    $legacyId = (int) $stmt->insert_id;
    $stmt->close();
    if ($legacyId <= 0) {
        return 0;
    }
    $upd = $db->prepare('UPDATE ezines SET legacy_press_id=? WHERE id=? AND (legacy_press_id IS NULL OR legacy_press_id=0) LIMIT 1');
    if ($upd) {
        $upd->bind_param('ii', $legacyId, $ezineId);
        $upd->execute();
        $upd->close();
    }

    return $legacyId;
}

/**
 * Create/link legacy issue and keep it in sync. Returns legacy issue id or 0.
 */
function ezines_v2_admin_ensure_legacy_issue(mysqli $db, int $issueId): int
{
    if ($issueId <= 0) {
        return 0;
    }
    $st = $db->prepare('SELECT * FROM ezine_issues WHERE id=? LIMIT 1');
    if (!$st) {
        return 0;
    }
    $st->bind_param('i', $issueId);
    $st->execute();
    $issue = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$issue) {
        return 0;
    }

    $ezineId = (int) ($issue['ezine_id'] ?? 0);
    $legacyPressId = ezines_v2_admin_ensure_legacy_press($db, $ezineId);
    if ($legacyPressId <= 0) {
        return 0;
    }

    $i18n = ezines_v2_load_i18n($db, 'ezine_issue_i18n', 'issue_id', $issueId);
    $title = (string) ($i18n['ru']['title'] ?? '');
    if ($title === '') {
        $title = (string) ($i18n['cs']['title'] ?? '');
    }
    if ($title === '') {
        $title = (string) ($i18n['en']['title'] ?? '');
    }
    if ($title === '') {
        $title = (string) $issueId;
    }
    $slugRu = (string) ($i18n['ru']['slug'] ?? '');
    if ($slugRu === '') {
        $slugRu = (string) ($i18n['cs']['slug'] ?? '');
    }
    $slugEn = (string) ($i18n['en']['slug'] ?? '');
    if ($slugEn === '') {
        $slugEn = $slugRu;
    }
    // Unique (id_press, slug_*) forbids multiple empty strings.
    if ($slugRu === '') {
        $slugRu = 'issue-' . $issueId;
    }
    if ($slugEn === '') {
        $slugEn = $slugRu;
    }
    $descRu = (string) ($i18n['ru']['description'] ?? '');
    if ($descRu === '') {
        $descRu = (string) ($i18n['cs']['description'] ?? '');
    }
    $descEn = (string) ($i18n['en']['description'] ?? '');
    $metaRu = (string) ($i18n['ru']['meta_description'] ?? '');
    if ($metaRu === '') {
        $metaRu = (string) ($i18n['cs']['meta_description'] ?? '');
    }
    $metaEn = (string) ($i18n['en']['meta_description'] ?? '');
    $sortOrder = (int) ($issue['sort_order'] ?? 0);
    $issueDate = (int) ($issue['issue_date'] ?? 0);
    $missing = !empty($issue['is_missing']) ? 1 : 0;
    $views = (int) ($issue['views'] ?? 0);

    $legacyIssueId = (int) ($issue['legacy_issue_id'] ?? 0);
    if ($legacyIssueId <= 0) {
        $stmt = $db->prepare(
            'INSERT INTO issue
             (id_press, title, date, sort_order, views, missing, slug_ru, slug_en,
              description_ru, description_en, meta_description_ru, meta_description_en)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param(
            'isiiiissssss',
            $legacyPressId,
            $title,
            $issueDate,
            $sortOrder,
            $views,
            $missing,
            $slugRu,
            $slugEn,
            $descRu,
            $descEn,
            $metaRu,
            $metaEn
        );
        try {
            if (!$stmt->execute()) {
                $stmt->close();

                return 0;
            }
        } catch (Throwable $e) {
            error_log('[FIX] ensure_legacy_issue insert failed: ' . $e->getMessage());
            $stmt->close();

            return 0;
        }
        $legacyIssueId = (int) $stmt->insert_id;
        $stmt->close();
        if ($legacyIssueId <= 0) {
            return 0;
        }
        $link = $db->prepare(
            'UPDATE ezine_issues SET legacy_issue_id=? WHERE id=? AND (legacy_issue_id IS NULL OR legacy_issue_id=0) LIMIT 1'
        );
        if ($link) {
            $link->bind_param('ii', $legacyIssueId, $issueId);
            $link->execute();
            $link->close();
        }
    } else {
        $upd = $db->prepare(
            'UPDATE issue SET id_press=?, title=?, date=?, sort_order=?, missing=?, slug_ru=?, slug_en=?,
             description_ru=?, description_en=?, meta_description_ru=?, meta_description_en=?
             WHERE id=? LIMIT 1'
        );
        if ($upd) {
            $upd->bind_param(
                'isiiissssssi',
                $legacyPressId,
                $title,
                $issueDate,
                $sortOrder,
                $missing,
                $slugRu,
                $slugEn,
                $descRu,
                $descEn,
                $metaRu,
                $metaEn,
                $legacyIssueId
            );
            $upd->execute();
            $upd->close();
        }
    }

    return $legacyIssueId;
}

/**
 * @return list<array<string,mixed>>
 */
function ezines_v2_admin_issue_files(mysqli $db, int $issueId): array
{
    $legacyIssueId = 0;
    $legacyPressId = 0;
    $st = $db->prepare(
        'SELECT i.legacy_issue_id, e.legacy_press_id
         FROM ezine_issues i
         INNER JOIN ezines e ON e.id=i.ezine_id
         WHERE i.id=? LIMIT 1'
    );
    if ($st) {
        $st->bind_param('i', $issueId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $legacyIssueId = (int) ($row['legacy_issue_id'] ?? 0);
        $legacyPressId = (int) ($row['legacy_press_id'] ?? 0);
    }
    if ($legacyIssueId <= 0) {
        return [];
    }

    $out = [];
    $labels = ezines_v2_admin_file_type_labels();
    $stmt = $db->prepare(
        'SELECT id, id_issue, id_press, name, type, size, date, file_title, downloads
         FROM files
         WHERE id_issue=? AND `delete`=0
         ORDER BY id ASC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $legacyIssueId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($f = $res->fetch_assoc())) {
        $name = (string) ($f['name'] ?? '');
        $type = (int) ($f['type'] ?? 0);
        $f['type_label'] = $labels[$type] ?? ('тип ' . $type);
        $f['public_url'] = $name !== '' ? ('/files/' . rawurlencode($name)) : '';
        $f['legacy_press_id'] = $legacyPressId;
        $out[] = $f;
    }
    $stmt->close();

    return $out;
}

function ezines_v2_admin_unique_file_name(mysqli $db, string $uploadName): string
{
    $base = basename($uploadName);
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    $stem = pathinfo($base, PATHINFO_FILENAME);
    $stem = (string) preg_replace('/[^a-zA-Z0-9._-]+/', '_', $stem);
    $stem = trim($stem, '._-');
    if ($stem === '') {
        $stem = 'file';
    }
    $maxLen = 32;
    $extPart = $ext !== '' ? ('.' . $ext) : '';
    $maxStem = max(1, $maxLen - strlen($extPart));
    $stem = substr($stem, 0, $maxStem);
    $name = $stem . $extPart;
    $n = 1;
    while (true) {
        $used = false;
        $chk = $db->prepare('SELECT id FROM files WHERE name=? LIMIT 1');
        if ($chk) {
            $chk->bind_param('s', $name);
            $chk->execute();
            $used = (bool) $chk->get_result()->fetch_assoc();
            $chk->close();
        }
        if (!$used && !is_file(zx_storage_path('files', $name))) {
            break;
        }
        $suffix = '_' . $n;
        $room = max(1, $maxLen - strlen($extPart) - strlen($suffix));
        $name = substr($stem, 0, $room) . $suffix . $extPart;
        $n++;
        if ($n > 200) {
            break;
        }
    }

    return $name;
}

/**
 * @return array{ok:bool,error:string,id:int}
 */
function ezines_v2_admin_add_issue_file(
    mysqli $db,
    int $issueId,
    string $origName,
    string $tmpPath,
    int $sizeBytes,
    int $type,
    string $fileTitle
): array {
    $legacyIssueId = ezines_v2_admin_ensure_legacy_issue($db, $issueId);
    if ($legacyIssueId <= 0) {
        return ['ok' => false, 'error' => 'Нет legacy-выпуска для файла', 'id' => 0];
    }
    $st = $db->prepare(
        'SELECT e.legacy_press_id
         FROM ezine_issues i
         INNER JOIN ezines e ON e.id=i.ezine_id
         WHERE i.id=? LIMIT 1'
    );
    $legacyPressId = 0;
    if ($st) {
        $st->bind_param('i', $issueId);
        $st->execute();
        $legacyPressId = (int) (($st->get_result()->fetch_assoc()['legacy_press_id'] ?? 0));
        $st->close();
    }
    if ($legacyPressId <= 0) {
        return ['ok' => false, 'error' => 'Нет legacy-издания для файла', 'id' => 0];
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, ezines_v2_admin_upload_extensions(), true)) {
        return ['ok' => false, 'error' => 'недопустимое расширение' . ($ext !== '' ? ' .' . $ext : ''), 'id' => 0];
    }
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'error' => 'файл не получен сервером', 'id' => 0];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = ($finfo && $tmpPath !== '') ? (string) (finfo_file($finfo, $tmpPath) ?: '') : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!ezines_v2_admin_upload_mime_allowed($ext, $mime)) {
        return ['ok' => false, 'error' => 'недопустимый тип файла', 'id' => 0];
    }

    if ($type < 0) {
        $type = ezines_v2_admin_file_type_from_ext($ext);
    }
    $labels = ezines_v2_admin_file_type_labels();
    if (!isset($labels[$type])) {
        $type = ezines_v2_admin_file_type_from_ext($ext);
    }

    $name = ezines_v2_admin_unique_file_name($db, $origName);
    $fileTitle = mb_substr(trim($fileTitle), 0, 32, 'UTF-8');
    $sizeKb = (int) ceil(max(0, $sizeBytes) / 1000);
    $tm = time();

    $stmt = $db->prepare(
        'INSERT INTO files (`id`, `id_issue`, `id_press`, `date`, `name`, `type`, `file_title`, `size`, `downloads`, `delete`, `file_comment`)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, 0, \'\')'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'не удалось сохранить запись в базе', 'id' => 0];
    }
    $stmt->bind_param('iiisisi', $legacyIssueId, $legacyPressId, $tm, $name, $type, $fileTitle, $sizeKb);
    $ok = $stmt->execute();
    $fileId = (int) $stmt->insert_id;
    $stmt->close();
    if (!$ok || $fileId <= 0) {
        return ['ok' => false, 'error' => 'не удалось сохранить запись в базе', 'id' => 0];
    }
    if (!zx_storage_copy_uploaded_file('files', $name, $tmpPath)) {
        $del = $db->prepare('DELETE FROM files WHERE id=? LIMIT 1');
        if ($del) {
            $del->bind_param('i', $fileId);
            $del->execute();
            $del->close();
        }

        return ['ok' => false, 'error' => 'не удалось сохранить файл на диск', 'id' => 0];
    }

    return ['ok' => true, 'error' => '', 'id' => $fileId];
}

function ezines_v2_admin_delete_issue_file(mysqli $db, int $issueId, int $fileId): bool
{
    if ($issueId <= 0 || $fileId <= 0) {
        return false;
    }
    $st = $db->prepare(
        'SELECT i.legacy_issue_id, e.legacy_press_id
         FROM ezine_issues i
         INNER JOIN ezines e ON e.id=i.ezine_id
         WHERE i.id=? LIMIT 1'
    );
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $issueId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $legacyIssueId = (int) ($row['legacy_issue_id'] ?? 0);
    $legacyPressId = (int) ($row['legacy_press_id'] ?? 0);
    if ($legacyIssueId <= 0 || $legacyPressId <= 0) {
        return false;
    }

    $f = $db->prepare(
        'SELECT id, name FROM files WHERE id=? AND id_press=? AND id_issue=? LIMIT 1'
    );
    if (!$f) {
        return false;
    }
    $f->bind_param('iii', $fileId, $legacyPressId, $legacyIssueId);
    $f->execute();
    $file = $f->get_result()->fetch_assoc();
    $f->close();
    if (!$file) {
        return false;
    }
    $name = (string) ($file['name'] ?? '');
    $del = $db->prepare('DELETE FROM files WHERE id=? AND id_press=? LIMIT 1');
    if (!$del) {
        return false;
    }
    $del->bind_param('ii', $fileId, $legacyPressId);
    $ok = $del->execute();
    $del->close();
    if ($ok && $name !== '') {
        $chk = $db->prepare('SELECT id FROM files WHERE name=? LIMIT 1');
        $still = false;
        if ($chk) {
            $chk->bind_param('s', $name);
            $chk->execute();
            $still = (bool) $chk->get_result()->fetch_assoc();
            $chk->close();
        }
        if (!$still) {
            @unlink(zx_storage_path('files', $name));
        }
    }

    return $ok;
}

/**
 * Update titles/types and accept new uploads for an issue. Returns error string (empty = ok).
 */
function ezines_v2_admin_save_issue_files(mysqli $db, int $issueId): string
{
    if ($issueId <= 0) {
        return '';
    }
    $legacyIssueId = ezines_v2_admin_ensure_legacy_issue($db, $issueId);
    if ($legacyIssueId <= 0) {
        $hasUpload = false;
        $upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
        $names = $upl['name'] ?? null;
        if (is_array($names)) {
            foreach ($names as $n) {
                if (trim((string) $n) !== '') {
                    $hasUpload = true;
                    break;
                }
            }
        } elseif (is_string($names) && trim($names) !== '') {
            $hasUpload = true;
        }
        return $hasUpload ? 'Не удалось создать legacy-выпуск для файлов' : '';
    }

    $st = $db->prepare(
        'SELECT e.legacy_press_id FROM ezine_issues i
         INNER JOIN ezines e ON e.id=i.ezine_id WHERE i.id=? LIMIT 1'
    );
    $legacyPressId = 0;
    if ($st) {
        $st->bind_param('i', $issueId);
        $st->execute();
        $legacyPressId = (int) (($st->get_result()->fetch_assoc()['legacy_press_id'] ?? 0));
        $st->close();
    }

    $existing = ezines_v2_admin_issue_files($db, $issueId);
    foreach ($existing as $file) {
        $fid = (int) $file['id'];
        $title = mb_substr(trim((string) ($_POST['file_title_' . $fid] ?? $file['file_title'] ?? '')), 0, 32, 'UTF-8');
        $type = (int) ($_POST['file_type_' . $fid] ?? $file['type'] ?? 0);
        $labels = ezines_v2_admin_file_type_labels();
        if (!isset($labels[$type])) {
            $type = (int) ($file['type'] ?? 0);
        }
        $upd = $db->prepare(
            'UPDATE files SET file_title=?, type=? WHERE id=? AND id_press=? AND id_issue=? LIMIT 1'
        );
        if ($upd) {
            $upd->bind_param('siiii', $title, $type, $fid, $legacyPressId, $legacyIssueId);
            $upd->execute();
            $upd->close();
        }
    }

    $upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
    if ($upl === []) {
        return '';
    }
    $names = $upl['name'] ?? [];
    $tmps = $upl['tmp_name'] ?? [];
    $errs = $upl['error'] ?? [];
    $sizes = $upl['size'] ?? [];
    if (!is_array($names)) {
        $names = [$names];
        $tmps = [$tmps];
        $errs = [$errs];
        $sizes = [$sizes];
    }
    $defaultType = (int) ($_POST['upload_file_type'] ?? -1);
    $defaultTitle = trim((string) ($_POST['upload_file_title'] ?? ''));
    $errors = [];
    foreach ($names as $i => $origName) {
        $origName = (string) $origName;
        $errCode = (int) ($errs[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($origName === '' || $errCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errCode !== UPLOAD_ERR_OK) {
            $errors[] = $origName . ': ошибка загрузки #' . $errCode;
            continue;
        }
        $type = $defaultType;
        $added = ezines_v2_admin_add_issue_file(
            $db,
            $issueId,
            $origName,
            (string) ($tmps[$i] ?? ''),
            (int) ($sizes[$i] ?? 0),
            $type,
            $defaultTitle
        );
        if (!$added['ok']) {
            $errors[] = $origName . ': ' . $added['error'];
        }
    }

    return $errors === [] ? '' : implode('; ', $errors);
}
