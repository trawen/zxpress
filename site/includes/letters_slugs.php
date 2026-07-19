<?php

const LETTERS_SLUG_MAX_LEN = 191;

/**
 * Convert a title to a lowercase ASCII slug containing only a-z, 0-9 and "-".
 */
function letters_slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    if ($text === '') {
        return '';
    }

    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $slug = strtr($text, $map);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim(preg_replace('/-+/', '-', $slug) ?? $slug, '-');
    if (strlen($slug) > LETTERS_SLUG_MAX_LEN) {
        $slug = rtrim(substr($slug, 0, LETTERS_SLUG_MAX_LEN), '-');
    }

    return $slug;
}

function letters_slug_exists(
    mysqli $db,
    string $column,
    string $slug,
    int $excludeId = 0
): bool {
    if (!in_array($column, ['slug_ru', 'slug_en'], true) || $slug === '') {
        return false;
    }

    $sql = 'SELECT id FROM letters WHERE ' . $column . '=?';
    $types = 's';
    $params = [$slug];
    if ($excludeId > 0) {
        $sql .= ' AND id<>?';
        $types .= 'i';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('letters slug check prepare failed: ' . $db->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function letters_slug_make_unique(
    mysqli $db,
    string $column,
    string $value,
    int $excludeId = 0,
    string $fallback = 'letter'
): string {
    $base = letters_slugify($value);
    if ($base === '') {
        $base = letters_slugify($fallback);
    }
    if ($base === '') {
        $base = 'letter';
    }

    $candidate = $base;
    $suffix = 2;
    while (letters_slug_exists($db, $column, $candidate, $excludeId)) {
        $suffixPart = '-' . $suffix;
        $maxBaseLen = LETTERS_SLUG_MAX_LEN - strlen($suffixPart);
        $candidate = rtrim(substr($base, 0, max(1, $maxBaseLen)), '-') . $suffixPart;
        $suffix++;
    }

    return $candidate;
}

/**
 * Resolve user-entered slugs; empty values are generated from titles.
 *
 * @return array{slug_ru:string,slug_en:string}
 */
function letters_resolve_slugs(
    mysqli $db,
    string $slugRuInput,
    string $slugEnInput,
    string $titleRu,
    string $titleEn,
    int $excludeId = 0
): array {
    $ruSeed = trim($slugRuInput) !== '' ? $slugRuInput : $titleRu;
    $enTitle = trim($titleEn) !== '' ? $titleEn : $titleRu;
    $enSeed = trim($slugEnInput) !== '' ? $slugEnInput : $enTitle;

    return [
        'slug_ru' => letters_slug_make_unique($db, 'slug_ru', $ruSeed, $excludeId, 'letter-' . $excludeId),
        'slug_en' => letters_slug_make_unique($db, 'slug_en', $enSeed, $excludeId, 'letter-' . $excludeId),
    ];
}

function letters_slug_normalize_path(string $slug): string
{
    $slug = trim($slug, " \t\n\r\0\x0B/");
    if ($slug === '') {
        return '';
    }

    return letters_slugify(rawurldecode($slug));
}

function letters_path_prefix(bool $isEng): string
{
    return $isEng ? '/en' : '/ru';
}

function letters_row_slug(array $row, bool $isEng): string
{
    if ($isEng) {
        $en = trim((string) ($row['slug_en'] ?? ''));
        if ($en !== '') {
            return $en;
        }
    }

    $ru = trim((string) ($row['slug_ru'] ?? ''));
    if ($ru !== '') {
        return $ru;
    }

    return trim((string) ($row['slug_en'] ?? ''));
}

function letters_url_catalog(bool $isEng): string
{
    return letters_path_prefix($isEng) . '/snailmail';
}

function letters_url_letter(array $row, bool $isEng): string
{
    $slug = letters_row_slug($row, $isEng);
    if ($slug === '') {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return letters_url_catalog($isEng);
        }

        return '/snailmail.php?id=' . $id . ($isEng ? '&lng=eng' : '');
    }

    return letters_path_prefix($isEng) . '/snailmail/' . $slug;
}

function letters_find_id_by_slug(mysqli $db, string $slug, bool $isEng): int
{
    $slug = letters_slug_normalize_path($slug);
    if ($slug === '') {
        return 0;
    }

    $primary = $isEng ? 'slug_en' : 'slug_ru';
    $fallback = $isEng ? 'slug_ru' : 'slug_en';
    $stmt = $db->prepare(
        'SELECT id FROM letters WHERE is_active=1 AND (' . $primary . '=? OR ' . $fallback . '=?) LIMIT 1'
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

function letters_canonical_letter_url(mysqli $db, int $letterId, bool $isEng): ?string
{
    if ($letterId <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id, slug_ru, slug_en FROM letters WHERE id=? AND is_active=1 LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $letterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    return letters_url_letter($row, $isEng);
}

function letters_assign_lang_switch_urls($smarty, ?array $letter): void
{
    if ($letter === null) {
        $smarty->assign('url_rus', htmlspecialchars(letters_url_catalog(false), ENT_QUOTES, 'UTF-8'));
        $smarty->assign('url_eng', htmlspecialchars(letters_url_catalog(true), ENT_QUOTES, 'UTF-8'));
        return;
    }

    $smarty->assign('url_rus', htmlspecialchars(letters_url_letter($letter, false), ENT_QUOTES, 'UTF-8'));
    $smarty->assign('url_eng', htmlspecialchars(letters_url_letter($letter, true), ENT_QUOTES, 'UTF-8'));
}

/**
 * 301 from snailmail.php?id=… / bare snailmail.php to /{lang}/snailmail[/{slug}].
 */
function letters_maybe_redirect_legacy(mysqli $db, bool $slugRoute, int $letterId, bool $isEng): void
{
    if ($slugRoute) {
        return;
    }
    if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'snailmail.php') {
        return;
    }

    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (!is_string($path)) {
        return;
    }

    // Catalog: /snailmail.php → /ru/snailmail (keep from/p query via Location only for bare catalog)
    if ($letterId <= 0 && !isset($_GET['id'])) {
        if ($path === '/snailmail.php') {
            $qs = [];
            if (isset($_GET['from']) && (int) $_GET['from'] > 0) {
                $qs['from'] = (int) $_GET['from'];
            }
            if (isset($_GET['p']) && (int) $_GET['p'] > 1) {
                $qs['p'] = (int) $_GET['p'];
            }
            $url = letters_url_catalog($isEng);
            if ($qs !== []) {
                $url .= '?' . http_build_query($qs);
            }
            header('Location: ' . $url, true, 301);
            exit;
        }

        return;
    }

    if ($letterId <= 0) {
        return;
    }

    $canonical = letters_canonical_letter_url($db, $letterId, $isEng);
    if ($canonical === null || str_starts_with($canonical, '/snailmail.php')) {
        return;
    }

    header('Location: ' . $canonical, true, 301);
    exit;
}
