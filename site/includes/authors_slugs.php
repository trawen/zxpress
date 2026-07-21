<?php

require_once __DIR__ . '/letters_slugs.php';

const AUTHORS_SLUG_MAX_LEN = LETTERS_SLUG_MAX_LEN;

function authors_slug_exists(
    mysqli $db,
    string $column,
    string $slug,
    int $excludeId = 0
): bool {
    if (!in_array($column, ['slug_ru', 'slug_en'], true) || $slug === '') {
        return false;
    }

    $sql = 'SELECT id FROM authors WHERE ' . $column . '=?';
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
        throw new RuntimeException('authors slug check prepare failed: ' . $db->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function authors_slug_make_unique(
    mysqli $db,
    string $column,
    string $value,
    int $excludeId = 0,
    string $fallback = 'author'
): string {
    $base = letters_slugify($value);
    if ($base === '') {
        $base = letters_slugify($fallback);
    }
    if ($base === '') {
        $base = 'author';
    }

    $candidate = $base;
    $suffix = 2;
    while (authors_slug_exists($db, $column, $candidate, $excludeId)) {
        $suffixPart = '-' . $suffix;
        $maxBaseLen = AUTHORS_SLUG_MAX_LEN - strlen($suffixPart);
        $candidate = rtrim(substr($base, 0, max(1, $maxBaseLen)), '-') . $suffixPart;
        $suffix++;
    }

    return $candidate;
}

/**
 * Resolve user-entered author slugs; empty values are generated from nickname / names.
 *
 * @return array{slug_ru:string,slug_en:string}
 */
function authors_resolve_slugs(
    mysqli $db,
    string $slugRuInput,
    string $slugEnInput,
    string $nickname,
    string $nameRu,
    string $nameEn,
    int $excludeId = 0
): array {
    $ruSeed = trim($slugRuInput) !== ''
        ? $slugRuInput
        : (trim($nickname) !== '' ? $nickname : $nameRu);
    $enSeed = trim($slugEnInput) !== ''
        ? $slugEnInput
        : (trim($nameEn) !== '' ? $nameEn : (trim($nickname) !== '' ? $nickname : $nameRu));

    return [
        'slug_ru' => authors_slug_make_unique($db, 'slug_ru', $ruSeed, $excludeId, 'author-' . $excludeId),
        'slug_en' => authors_slug_make_unique($db, 'slug_en', $enSeed, $excludeId, 'author-' . $excludeId),
    ];
}

function authors_row_slug(array $row, bool $isEng): string
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

function authors_find_id_by_slug(mysqli $db, string $slug, bool $isEng): int
{
    $slug = letters_slug_normalize_path($slug);
    if ($slug === '') {
        return 0;
    }

    $primary = $isEng ? 'slug_en' : 'slug_ru';
    $fallback = $isEng ? 'slug_ru' : 'slug_en';
    $stmt = $db->prepare(
        'SELECT id FROM authors WHERE ' . $primary . '=? OR ' . $fallback . '=? LIMIT 1'
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

/**
 * @return array{id:int,slug:string,row:?array}
 */
function authors_resolve_filter(mysqli $db, string $param, bool $isEng): array
{
    $param = trim($param);
    if ($param === '') {
        return ['id' => 0, 'slug' => '', 'row' => null];
    }

    $row = null;
    $id = 0;

    if (ctype_digit($param)) {
        $id = (int) $param;
        $stmt = $db->prepare('SELECT id, nickname, slug_ru, slug_en FROM authors WHERE id=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
        if (!$row) {
            return ['id' => 0, 'slug' => '', 'row' => null];
        }
        $id = (int) $row['id'];
    } else {
        $id = authors_find_id_by_slug($db, $param, $isEng);
        if ($id <= 0) {
            return ['id' => 0, 'slug' => '', 'row' => null];
        }
        $stmt = $db->prepare('SELECT id, nickname, slug_ru, slug_en FROM authors WHERE id=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
        if (!$row) {
            return ['id' => 0, 'slug' => '', 'row' => null];
        }
    }

    $slug = authors_row_slug($row, $isEng);
    if ($slug === '') {
        $slug = (string) $id;
    }

    return ['id' => $id, 'slug' => $slug, 'row' => $row];
}

function authors_url(array $row, bool $isEng, int $page = 0): string
{
    $slug = authors_row_slug($row, $isEng);
    if ($slug === '') {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return authors_url_catalog($isEng);
        }
        $slug = (string) $id;
    }

    $url = authors_url_catalog($isEng) . '/' . $slug;
    if ($page > 1) {
        $url .= '?p=' . $page;
    }

    return $url;
}

function authors_url_catalog(bool $isEng): string
{
    return letters_path_prefix($isEng) . '/authors';
}

function authors_assign_lang_switch_urls($smarty, array $row, int $page = 0): void
{
    $smarty->assign('url_rus', htmlspecialchars(authors_url($row, false, $page), ENT_QUOTES, 'UTF-8'));
    $smarty->assign('url_eng', htmlspecialchars(authors_url($row, true, $page), ENT_QUOTES, 'UTF-8'));
}

function authors_assign_catalog_lang_switch_urls($smarty): void
{
    $smarty->assign('url_rus', htmlspecialchars(authors_url_catalog(false), ENT_QUOTES, 'UTF-8'));
    $smarty->assign('url_eng', htmlspecialchars(authors_url_catalog(true), ENT_QUOTES, 'UTF-8'));
}

/** Russian plural: 1 письмо, 2 письма, 5 писем (and 11–14 → many). */
function zx_plural_ru(int $n, string $one, string $few, string $many): string
{
    $nAbs = abs($n) % 100;
    $n1 = $nAbs % 10;
    if ($nAbs > 10 && $nAbs < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }
    if ($n1 === 1) {
        return $one;
    }

    return $many;
}

/** e.g. "1 — письмо", "5 — писем" / "1 — letter", "5 — letters". */
function authors_letters_count_label(int $count, bool $isEng): string
{
    if ($isEng) {
        return $count . ' — ' . ($count === 1 ? 'letter' : 'letters');
    }

    return $count . ' — ' . zx_plural_ru($count, 'письмо', 'письма', 'писем');
}
