<?php

require_once __DIR__ . '/periodical_issue_files.php';

const PER_SLUG_MAX_LEN = 255;

function per_slugify(string $text): string
{
    $text = str_replace([',', '.', '…', ';', ':'], ' ', $text);
    $slug = per_issue_slug_part($text);
    if ($slug === '') {
        return '';
    }
    $slug = str_replace(['.', ','], '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    if ($slug === '') {
        return '';
    }
    if (strlen($slug) > PER_SLUG_MAX_LEN) {
        $slug = rtrim(substr($slug, 0, PER_SLUG_MAX_LEN), '-');
    }

    return $slug;
}

function per_slug_from_title(string $title): string
{
    return per_slugify(trim($title));
}

function per_slug_default_issue_ru(array $issue): string
{
    $parts = [];
    $year = trim((string) ($issue['issue_year'] ?? ''));
    if ($year !== '' && is_numeric($year)) {
        $parts[] = $year;
    }

    $issueNo = per_issue_slug_part(trim((string) ($issue['issue_no'] ?? '')));
    if ($issueNo !== '') {
        $parts[] = $issueNo;
    }

    if ($parts !== []) {
        return per_slugify(implode('-', $parts));
    }

    return per_slug_from_title((string) ($issue['title_ru'] ?? ''));
}

function per_slug_default_issue_en(array $issue): string
{
    $titleEn = per_slug_from_title((string) ($issue['title_en'] ?? ''));
    if ($titleEn !== '') {
        return $titleEn;
    }

    return per_slug_default_issue_ru($issue);
}

function per_slug_default_article_ru(array $article): string
{
    $meta = trim((string) ($article['meta_description_ru'] ?? ''));
    if ($meta !== '') {
        return per_slug_from_title($meta);
    }

    return per_slug_from_title((string) ($article['title_ru'] ?? ''));
}

function per_slug_default_article_en(array $article): string
{
    $meta = trim((string) ($article['meta_description_en'] ?? ''));
    if ($meta !== '') {
        return per_slug_from_title($meta);
    }

    $titleEn = per_slug_from_title((string) ($article['title_en'] ?? ''));
    if ($titleEn !== '') {
        return $titleEn;
    }

    return per_slug_default_article_ru($article);
}

function per_slug_default_publisher_ru(array $publisher): string
{
    $meta = trim((string) ($publisher['meta_description_ru'] ?? ''));
    if ($meta !== '') {
        return per_slug_from_title($meta);
    }

    return per_slug_from_title((string) ($publisher['name_ru'] ?? ''));
}

function per_slug_default_publisher_en(array $publisher): string
{
    $meta = trim((string) ($publisher['meta_description_en'] ?? ''));
    if ($meta !== '') {
        return per_slug_from_title($meta);
    }

    $nameEn = per_slug_from_title((string) ($publisher['name_en'] ?? ''));
    if ($nameEn !== '') {
        return $nameEn;
    }

    return per_slug_default_publisher_ru($publisher);
}

function per_slug_normalize_path(string $slug): string
{
    $slug = trim($slug, " \t\n\r\0\x0B/");
    if ($slug === '') {
        return '';
    }

    return per_slugify(rawurldecode($slug));
}

function per_row_slug(array $row, bool $isEng): string
{
    if ($isEng) {
        $en = trim((string) ($row['slug_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    }

    return trim((string) ($row['slug_ru'] ?? ''));
}

function per_slug_column(bool $isEng): string
{
    return $isEng ? 'slug_en' : 'slug_ru';
}

function per_slug_exists_lang(
    mysqli $db,
    string $table,
    string $column,
    string $slug,
    int $excludeId = 0,
    string $scopeColumn = '',
    int $scopeId = 0
): bool {
    $allowedTables = ['periodicals', 'periodical_issues', 'periodical_articles', 'publishers', 'press', 'issue', 'articles'];
    $allowedColumns = ['slug_ru', 'slug_en'];
    $allowedScopes = ['', 'periodical_id', 'issue_id', 'id_press', 'id_issue'];
    if (!in_array($table, $allowedTables, true) || !in_array($column, $allowedColumns, true)) {
        return false;
    }
    if (!in_array($scopeColumn, $allowedScopes, true)) {
        return false;
    }

    $sql = 'SELECT id FROM ' . $table . ' WHERE ' . $column . '=?';
    $types = 's';
    $params = [$slug];

    if ($scopeColumn !== '' && $scopeId > 0) {
        $sql .= ' AND ' . $scopeColumn . '=?';
        $types .= 'i';
        $params[] = $scopeId;
    }

    if ($excludeId > 0) {
        $sql .= ' AND id<>?';
        $types .= 'i';
        $params[] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool) $row;
}

function per_slug_make_unique_lang(
    mysqli $db,
    string $table,
    string $column,
    string $slug,
    int $excludeId = 0,
    string $scopeColumn = '',
    int $scopeId = 0
): string {
    $base = per_slugify($slug);
    if ($base === '') {
        $base = 'item';
    }

    $candidate = $base;
    $suffix = 2;
    while (per_slug_exists_lang($db, $table, $column, $candidate, $excludeId, $scopeColumn, $scopeId)) {
        $suffixPart = '-' . $suffix;
        $maxBaseLen = PER_SLUG_MAX_LEN - strlen($suffixPart);
        $trimmedBase = rtrim(substr($base, 0, max(1, $maxBaseLen)), '-');
        $candidate = $trimmedBase . $suffixPart;
        $suffix++;
    }

    return $candidate;
}

function per_slug_scope_for_row(string $table, array $row): array
{
    if ($table === 'periodical_issues') {
        return ['periodical_id', (int) ($row['periodical_id'] ?? 0)];
    }
    if ($table === 'periodical_articles') {
        return ['issue_id', (int) ($row['issue_id'] ?? 0)];
    }
    if ($table === 'issue') {
        return ['id_press', (int) ($row['id_press'] ?? 0)];
    }
    if ($table === 'articles') {
        return ['id_issue', (int) ($row['id_issue'] ?? 0)];
    }

    return ['', 0];
}

function per_pub_path_prefix(bool $isEng): string
{
    return $isEng ? '/en' : '/ru';
}

function per_pub_url_catalog(bool $isEng): string
{
    return per_pub_path_prefix($isEng) . '/periodicals';
}

function per_pub_url_periodical(array $row, bool $isEng): string
{
    $slug = per_row_slug($row, $isEng);
    if ($slug === '') {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return per_pub_url_catalog($isEng);
        }

        return '/periodicals.php?id=' . $id . ($isEng ? '?lng=eng' : '');
    }

    return per_pub_path_prefix($isEng) . '/periodicals/' . $slug;
}

function per_pub_url_issue(array $periodical, array $issue, bool $isEng): string
{
    $perSlug = per_row_slug($periodical, $isEng);
    $issueSlug = per_row_slug($issue, $isEng);
    if ($perSlug === '' || $issueSlug === '') {
        $pid = (int) ($periodical['id'] ?? 0);
        $iid = (int) ($issue['id'] ?? 0);
        if ($pid <= 0 || $iid <= 0) {
            return per_pub_url_catalog($isEng);
        }

        return '/periodicals.php?id=' . $pid . '&issue=' . $iid . ($isEng ? '&lng=eng' : '');
    }

    return per_pub_path_prefix($isEng) . '/periodicals/' . $perSlug . '/' . $issueSlug;
}

function per_pub_url_article(array $periodical, array $issue, array $article, bool $isEng): string
{
    $perSlug = per_row_slug($periodical, $isEng);
    $issueSlug = per_row_slug($issue, $isEng);
    $articleSlug = per_row_slug($article, $isEng);
    if ($perSlug === '' || $issueSlug === '' || $articleSlug === '') {
        $pid = (int) ($periodical['id'] ?? 0);
        $iid = (int) ($issue['id'] ?? 0);
        $aid = (int) ($article['id'] ?? 0);
        if ($pid <= 0 || $iid <= 0 || $aid <= 0) {
            return per_pub_url_catalog($isEng);
        }

        return '/periodicals.php?id=' . $pid . '&issue=' . $iid . '&article=' . $aid . ($isEng ? '&lng=eng' : '');
    }

    return per_pub_path_prefix($isEng) . '/periodicals/' . $perSlug . '/' . $issueSlug . '/' . $articleSlug;
}

function per_pub_url_for_lang(
    bool $isEng,
    ?array $periodical,
    ?array $issue,
    ?array $article
): string {
    if ($article !== null && $issue !== null && $periodical !== null) {
        return per_pub_url_article($periodical, $issue, $article, $isEng);
    }
    if ($issue !== null && $periodical !== null) {
        return per_pub_url_issue($periodical, $issue, $isEng);
    }
    if ($periodical !== null) {
        return per_pub_url_periodical($periodical, $isEng);
    }

    return per_pub_url_catalog($isEng);
}

function per_pub_assign_lang_switch_urls(
    $smarty,
    ?array $periodical,
    ?array $issue,
    ?array $article
): void {
    $smarty->assign('url_rus', htmlspecialchars(per_pub_url_for_lang(false, $periodical, $issue, $article), ENT_QUOTES, 'UTF-8'));
    $smarty->assign('url_eng', htmlspecialchars(per_pub_url_for_lang(true, $periodical, $issue, $article), ENT_QUOTES, 'UTF-8'));
}

function per_pub_find_id_by_slug(
    mysqli $db,
    string $table,
    string $slug,
    bool $isEng,
    string $andWhere = '',
    string $andTypes = '',
    array $andParams = []
): int {
    $primary = per_slug_column($isEng);
    $fallback = $isEng ? 'slug_ru' : 'slug_en';

    $sql = 'SELECT id FROM ' . $table . ' WHERE is_active=1 AND (' . $primary . '=? OR ' . $fallback . '=?)';
    if ($andWhere !== '') {
        $sql .= ' AND ' . $andWhere;
    }
    $sql .= ' LIMIT 1';

    $types = 'ss' . $andTypes;
    $params = [$slug, $slug, ...$andParams];

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['id'] ?? 0);
}

function per_pub_resolve_route(
    mysqli $db,
    string $perSlug,
    string $issueSlug,
    string $articleSlug,
    bool $isEng
): array {
    $result = [
        'ok' => false,
        'periodical_id' => 0,
        'issue_id' => 0,
        'article_id' => 0,
    ];

    if ($perSlug === '') {
        return $result;
    }

    $periodicalId = per_pub_find_id_by_slug($db, 'periodicals', $perSlug, $isEng);
    if ($periodicalId <= 0) {
        return $result;
    }

    $result['periodical_id'] = $periodicalId;
    $result['ok'] = true;

    if ($issueSlug === '') {
        return $result;
    }

    $issueId = per_pub_find_id_by_slug(
        $db,
        'periodical_issues',
        $issueSlug,
        $isEng,
        'periodical_id=?',
        'i',
        [$periodicalId]
    );
    if ($issueId <= 0) {
        $result['ok'] = false;

        return $result;
    }

    $result['issue_id'] = $issueId;

    if ($articleSlug === '') {
        return $result;
    }

    $articleId = per_pub_find_id_by_slug(
        $db,
        'periodical_articles',
        $articleSlug,
        $isEng,
        'issue_id=?',
        'i',
        [$issueId]
    );
    if ($articleId <= 0) {
        $result['ok'] = false;

        return $result;
    }

    $result['article_id'] = $articleId;

    return $result;
}

function per_pub_canonical_url_from_ids(
    mysqli $db,
    int $periodicalId,
    int $issueId,
    int $articleId,
    bool $isEng
): ?string {
    if ($periodicalId <= 0) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM periodicals WHERE id=? AND is_active=1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $periodicalId);
    $stmt->execute();
    $periodical = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$periodical || per_row_slug($periodical, $isEng) === '') {
        return null;
    }

    if ($issueId <= 0) {
        return per_pub_url_periodical($periodical, $isEng);
    }

    $stmt = $db->prepare(
        'SELECT * FROM periodical_issues WHERE id=? AND periodical_id=? AND is_active=1 LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $issueId, $periodicalId);
    $stmt->execute();
    $issue = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$issue || per_row_slug($issue, $isEng) === '') {
        return null;
    }

    if ($articleId <= 0) {
        return per_pub_url_issue($periodical, $issue, $isEng);
    }

    $stmt = $db->prepare(
        'SELECT * FROM periodical_articles WHERE id=? AND issue_id=? AND is_active=1 LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $articleId, $issueId);
    $stmt->execute();
    $article = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$article || per_row_slug($article, $isEng) === '') {
        return null;
    }

    return per_pub_url_article($periodical, $issue, $article, $isEng);
}

function per_pub_maybe_redirect_legacy(
    mysqli $db,
    bool $slugRoute,
    int $periodicalId,
    int $issueId,
    int $articleId,
    bool $isEng
): void {
    if ($slugRoute) {
        return;
    }

    if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'periodicals.php') {
        return;
    }

    if ($periodicalId <= 0 && !isset($_GET['id'])) {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($path === '/periodicals.php') {
            header('Location: ' . per_pub_url_catalog($isEng), true, 301);
            exit;
        }

        return;
    }

    if ($periodicalId <= 0) {
        return;
    }

    $canonical = per_pub_canonical_url_from_ids($db, $periodicalId, $issueId, $articleId, $isEng);
    if ($canonical === null) {
        return;
    }

    header('Location: ' . $canonical, true, 301);
    exit;
}

function per_admin_resolve_slugs(
    mysqli $db,
    string $table,
    string $inputRu,
    string $inputEn,
    callable $defaultRu,
    callable $defaultEn,
    int $excludeId = 0,
    string $scopeColumn = '',
    int $scopeId = 0
): array {
    $slugRu = per_slugify($inputRu);
    if ($slugRu === '') {
        $slugRu = per_slugify((string) $defaultRu());
    }
    $slugEn = per_slugify($inputEn);
    if ($slugEn === '') {
        $slugEn = per_slugify((string) $defaultEn());
    }

    return [
        'slug_ru' => per_slug_make_unique_lang($db, $table, 'slug_ru', $slugRu, $excludeId, $scopeColumn, $scopeId),
        'slug_en' => per_slug_make_unique_lang($db, $table, 'slug_en', $slugEn, $excludeId, $scopeColumn, $scopeId),
    ];
}
