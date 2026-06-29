<?php

require_once __DIR__ . '/periodicals_slugs.php';

const EZN_ARTICLE_SLUG_MAX_LEN = 100;

function ezn_truncate_article_slug(string $slug): string
{
    $slug = trim($slug, '-');
    if ($slug === '' || strlen($slug) <= EZN_ARTICLE_SLUG_MAX_LEN) {
        return $slug;
    }

    $cut = substr($slug, 0, EZN_ARTICLE_SLUG_MAX_LEN);
    $lastHyphen = strrpos($cut, '-');
    if ($lastHyphen !== false && $lastHyphen > 0) {
        $cut = substr($cut, 0, $lastHyphen);
    }

    return rtrim($cut, '-');
}

function ezn_prepare_article_slug_input(string $input): string
{
    return ezn_truncate_article_slug(per_slugify($input));
}

function ezn_admin_resolve_article_slugs(
    mysqli $db,
    string $inputRu,
    string $inputEn,
    callable $defaultRu,
    callable $defaultEn,
    int $excludeId,
    int $issueId
): array {
    $slugRu = ezn_prepare_article_slug_input($inputRu);
    if ($slugRu === '') {
        $slugRu = ezn_truncate_article_slug(per_slugify((string) $defaultRu()));
    }
    $slugEn = ezn_prepare_article_slug_input($inputEn);
    if ($slugEn === '') {
        $slugEn = ezn_truncate_article_slug(per_slugify((string) $defaultEn()));
    }

    return [
        'slug_ru' => per_slug_make_unique_lang($db, 'articles', 'slug_ru', $slugRu, $excludeId, 'id_issue', $issueId),
        'slug_en' => per_slug_make_unique_lang($db, 'articles', 'slug_en', $slugEn, $excludeId, 'id_issue', $issueId),
    ];
}

function ezn_is_eng(?string $lng): bool
{
    return $lng === 'eng';
}

function ezn_path_prefix(bool $isEng): string
{
    return $isEng ? '/eng' : '/ru';
}

function ezn_section(): string
{
    return 'ezines';
}

function ezn_public_path_prefix(bool $isEng): string
{
    return ezn_path_prefix($isEng) . '/' . ezn_section();
}

function ezn_default_press_ru(array $row): string
{
    return per_slug_from_title((string) ($row['title'] ?? ''));
}

function ezn_default_press_en(array $row): string
{
    return ezn_default_press_ru($row);
}

function ezn_default_issue_ru(array $row): string
{
    return per_slugify((string) ($row['title'] ?? ''));
}

function ezn_default_issue_en(array $row): string
{
    return ezn_default_issue_ru($row);
}

function ezn_default_article_ru(array $row): string
{
    $meta = trim((string) ($row['meta_description_ru'] ?? ''));
    if ($meta !== '') {
        return ezn_truncate_article_slug(per_slug_from_title($meta));
    }

    return ezn_truncate_article_slug(per_slug_from_title((string) ($row['title'] ?? '')));
}

function ezn_default_article_en(array $row): string
{
    $meta = trim((string) ($row['meta_description_en'] ?? ''));
    if ($meta !== '') {
        return ezn_truncate_article_slug(per_slug_from_title($meta));
    }

    $titleEn = per_slug_from_title((string) ($row['title_eng'] ?? ''));
    if ($titleEn !== '') {
        return ezn_truncate_article_slug($titleEn);
    }

    return ezn_default_article_ru($row);
}

function ezn_find_press_id(mysqli $db, string $slug, bool $isEng): int
{
    if ($slug === '') {
        return 0;
    }

    $primary = per_slug_column($isEng);
    $fallback = $isEng ? 'slug_ru' : 'slug_en';
    $stmt = $db->prepare(
        'SELECT id FROM press WHERE (' . $primary . '=? OR ' . $fallback . '=?) LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ss', $slug, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['id'] ?? 0);
}

function ezn_find_issue_id(mysqli $db, int $pressId, string $slug, bool $isEng): int
{
    if ($pressId <= 0 || $slug === '') {
        return 0;
    }

    $primary = per_slug_column($isEng);
    $fallback = $isEng ? 'slug_ru' : 'slug_en';
    $stmt = $db->prepare(
        'SELECT id FROM issue WHERE id_press=? AND (' . $primary . '=? OR ' . $fallback . '=?) LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iss', $pressId, $slug, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['id'] ?? 0);
}

function ezn_find_article_id(mysqli $db, int $issueId, string $slug, bool $isEng): int
{
    if ($issueId <= 0 || $slug === '') {
        return 0;
    }

    $primary = per_slug_column($isEng);
    $fallback = $isEng ? 'slug_ru' : 'slug_en';
    $stmt = $db->prepare(
        'SELECT id FROM articles WHERE id_issue=? AND temp=0 AND (' . $primary . '=? OR ' . $fallback . '=?) LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iss', $issueId, $slug, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['id'] ?? 0);
}

function ezn_resolve_article_route(
    mysqli $db,
    string $pressSlug,
    string $issueSlug,
    string $articleSlug,
    bool $isEng
): array {
    $result = [
        'ok' => false,
        'press_id' => 0,
        'issue_id' => 0,
        'article_id' => 0,
    ];

    if ($pressSlug === '' || $issueSlug === '' || $articleSlug === '') {
        return $result;
    }

    $pressId = ezn_find_press_id($db, $pressSlug, $isEng);
    if ($pressId <= 0) {
        return $result;
    }

    $issueId = ezn_find_issue_id($db, $pressId, $issueSlug, $isEng);
    if ($issueId <= 0) {
        return $result;
    }

    $articleId = ezn_find_article_id($db, $issueId, $articleSlug, $isEng);
    if ($articleId <= 0) {
        return $result;
    }

    $result['ok'] = true;
    $result['press_id'] = $pressId;
    $result['issue_id'] = $issueId;
    $result['article_id'] = $articleId;

    return $result;
}

function ezn_url_press(array $press, bool $isEng): string
{
    $slug = per_row_slug($press, $isEng);
    if ($slug === '') {
        $id = (int) ($press['id'] ?? 0);
        if ($id <= 0) {
            return '/ezines.php' . ($isEng ? '?lng=eng' : '');
        }

        return '/issue.php?id=' . $id . ($isEng ? '&lng=eng' : '');
    }

    return ezn_public_path_prefix($isEng) . '/' . $slug;
}

function ezn_url_issue(array $press, array $issue, bool $isEng): string
{
    $pressSlug = per_row_slug($press, $isEng);
    $issueSlug = per_row_slug($issue, $isEng);
    if ($pressSlug === '' || $issueSlug === '') {
        return ezn_url_press($press, $isEng) . '#' . rawurlencode((string) ($issue['title'] ?? ''));
    }

    return ezn_public_path_prefix($isEng) . '/' . $pressSlug . '/' . $issueSlug;
}

function ezn_url_article(array $press, array $issue, array $article, bool $isEng): string
{
    $pressSlug = per_row_slug($press, $isEng);
    $issueSlug = per_row_slug($issue, $isEng);
    $articleSlug = per_row_slug($article, $isEng);
    if ($pressSlug === '' || $issueSlug === '' || $articleSlug === '') {
        $aid = (int) ($article['id'] ?? 0);
        if ($aid <= 0) {
            return ezn_url_press($press, $isEng);
        }

        return '/article.php?id=' . $aid . ($isEng ? '&lng=eng' : '');
    }

    return ezn_public_path_prefix($isEng) . '/' . $pressSlug . '/' . $issueSlug . '/' . $articleSlug;
}

function ezn_url_for_lang(
    bool $isEng,
    ?array $press,
    ?array $issue,
    ?array $article
): string {
    if ($article !== null && $issue !== null && $press !== null) {
        return ezn_url_article($press, $issue, $article, $isEng);
    }
    if ($issue !== null && $press !== null) {
        return ezn_url_issue($press, $issue, $isEng);
    }
    if ($press !== null) {
        return ezn_url_press($press, $isEng);
    }

    return '/ezines.php' . ($isEng ? '?lng=eng' : '');
}

function ezn_assign_lang_switch_urls(
    $smarty,
    ?array $press,
    ?array $issue,
    ?array $article
): void {
    $smarty->assign('url_rus', htmlspecialchars(ezn_url_for_lang(false, $press, $issue, $article), ENT_QUOTES, 'UTF-8'));
    $smarty->assign('url_eng', htmlspecialchars(ezn_url_for_lang(true, $press, $issue, $article), ENT_QUOTES, 'UTF-8'));
}

function ezn_canonical_article_url(mysqli $db, int $articleId, bool $isEng): ?string
{
    if ($articleId <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT a.*, i.id AS issue_row_id, i.id_press, i.title AS issue_title, i.slug_ru AS issue_slug_ru, i.slug_en AS issue_slug_en, '
        . 'p.id AS press_row_id, p.title AS press_title, p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en '
        . 'FROM articles a '
        . 'INNER JOIN issue i ON i.id=a.id_issue '
        . 'INNER JOIN press p ON p.id=i.id_press '
        . 'WHERE a.id=? AND a.temp=0 LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    $press = [
        'id' => (int) ($row['press_row_id'] ?? 0),
        'title' => (string) ($row['press_title'] ?? ''),
        'slug_ru' => (string) ($row['press_slug_ru'] ?? ''),
        'slug_en' => (string) ($row['press_slug_en'] ?? ''),
    ];
    $issue = [
        'id' => (int) ($row['issue_row_id'] ?? 0),
        'id_press' => (int) ($row['id_press'] ?? 0),
        'title' => (string) ($row['issue_title'] ?? ''),
        'slug_ru' => (string) ($row['issue_slug_ru'] ?? ''),
        'slug_en' => (string) ($row['issue_slug_en'] ?? ''),
    ];
    $article = [
        'id' => $articleId,
        'slug_ru' => (string) ($row['slug_ru'] ?? ''),
        'slug_en' => (string) ($row['slug_en'] ?? ''),
    ];

    return ezn_url_article($press, $issue, $article, $isEng);
}

function ezn_canonical_press_url(mysqli $db, int $pressId, bool $isEng): ?string
{
    if ($pressId <= 0) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, title, slug_ru, slug_en FROM press WHERE id=? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $pressId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    return ezn_url_press($row, $isEng);
}

function ezn_canonical_issue_url_by_ids(mysqli $db, int $pressId, int $issueId, bool $isEng): ?string
{
    if ($pressId <= 0 || $issueId <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT p.id AS press_row_id, p.title AS press_title, p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en, '
        . 'i.id AS issue_row_id, i.id_press, i.title AS issue_title, i.slug_ru AS issue_slug_ru, i.slug_en AS issue_slug_en '
        . 'FROM press p INNER JOIN issue i ON i.id_press=p.id '
        . 'WHERE p.id=? AND i.id=? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $pressId, $issueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    $press = [
        'id' => (int) ($row['press_row_id'] ?? 0),
        'title' => (string) ($row['press_title'] ?? ''),
        'slug_ru' => (string) ($row['press_slug_ru'] ?? ''),
        'slug_en' => (string) ($row['press_slug_en'] ?? ''),
    ];
    $issue = [
        'id' => (int) ($row['issue_row_id'] ?? 0),
        'id_press' => (int) ($row['id_press'] ?? 0),
        'title' => (string) ($row['issue_title'] ?? ''),
        'slug_ru' => (string) ($row['issue_slug_ru'] ?? ''),
        'slug_en' => (string) ($row['issue_slug_en'] ?? ''),
    ];

    return ezn_url_issue($press, $issue, $isEng);
}

function ezn_maybe_redirect_article_legacy(mysqli $db, int $articleId, bool $isEng): void
{
    if ($articleId <= 0) {
        return;
    }
    if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'article.php') {
        return;
    }

    $canonical = ezn_canonical_article_url($db, $articleId, $isEng);
    if ($canonical === null || str_starts_with($canonical, '/article.php')) {
        return;
    }

    header('Location: ' . $canonical, true, 301);
    exit;
}

function ezn_maybe_redirect_press_legacy(mysqli $db, int $pressId, bool $isEng, string $issueSlug = ''): void
{
    if ($pressId <= 0) {
        return;
    }
    if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'issue.php') {
        return;
    }

    $stmt = $db->prepare('SELECT id, title, slug_ru, slug_en FROM press WHERE id=? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $pressId);
    $stmt->execute();
    $press = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$press) {
        return;
    }

    $url = ezn_url_press($press, $isEng);
    if (str_starts_with($url, '/issue.php')) {
        return;
    }

    if ($issueSlug !== '') {
        $issueId = ezn_find_issue_id($db, $pressId, $issueSlug, $isEng);
        if ($issueId > 0) {
            $stmtIssue = $db->prepare('SELECT id, id_press, title, slug_ru, slug_en FROM issue WHERE id=? LIMIT 1');
            if ($stmtIssue) {
                $stmtIssue->bind_param('i', $issueId);
                $stmtIssue->execute();
                $issue = $stmtIssue->get_result()->fetch_assoc();
                $stmtIssue->close();
                if ($issue) {
                    $url = ezn_url_issue($press, $issue, $isEng);
                }
            }
        }
    }

    header('Location: ' . $url, true, 301);
    exit;
}

function ezn_resolve_lang_from_request(): ?string
{
    $lng = $_GET['lng'] ?? null;
    if ($lng === 'en') {
        return 'eng';
    }
    if ($lng === 'eng') {
        return 'eng';
    }

    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (!is_string($path)) {
        return null;
    }
    if (str_starts_with($path, '/eng/') || str_starts_with($path, '/en/')) {
        return 'eng';
    }

    return null;
}
