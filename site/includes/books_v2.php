<?php

declare(strict_types=1);

require_once __DIR__ . '/periodicals_slugs.php';

/**
 * Split legacy books.authors string into candidate person names.
 *
 * @return list<string>
 */
function books_v2_parse_authors(string $raw): array
{
    $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/\s*[,;]\s*/u', $raw) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        $part = trim($part, " \t\n\r\0\x0B.");
        if ($part === '') {
            continue;
        }
        foreach (books_v2_split_compact_author_list($part) as $name) {
            $out[] = $name;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Split a single segment that packs several "SURNAME First Patronymic" names without commas.
 *
 * @return list<string>
 */
function books_v2_split_compact_author_list(string $segment): array
{
    $segment = trim($segment);
    if ($segment === '') {
        return [];
    }

    // Drop role labels that appear mid-list in some legacy rows.
    $segment = preg_replace('/\b(РЕДАКТОР|EDITOR|СОСТАВИТЕЛЬ)\b/ui', ' ', $segment) ?? $segment;
    $segment = preg_replace('/\s+/u', ' ', trim($segment)) ?? $segment;

    // SURNAME (2+ uppercase letters) + First + Patronymic, repeated.
    $re = '/\b([А-ЯЁA-Z]{2,}(?:-[А-ЯЁA-Z]+)?)\s+([А-ЯЁA-Z][а-яёa-z\-]+(?:\s+[А-ЯЁA-Z]\.)*)\s+([А-ЯЁA-Z][а-яёa-z\-]+)\b/u';
    if (preg_match_all($re, $segment, $m, PREG_SET_ORDER) && count($m) >= 2) {
        $names = [];
        foreach ($m as $hit) {
            $names[] = trim($hit[1] . ' ' . $hit[2] . ' ' . $hit[3]);
        }

        return $names;
    }

    return [$segment];
}

/**
 * Digits-only ISBN (keeps trailing X for ISBN-10).
 */
function books_v2_isbn_digits(string $raw): string
{
    $raw = strtoupper(trim($raw));
    $raw = preg_replace('/[^0-9X]/', '', $raw) ?? '';

    return $raw;
}

/**
 * Convert ISBN-10 to ISBN-13 (978-prefix). Returns null if invalid.
 */
function books_v2_isbn10_to_isbn13(string $isbn10): ?string
{
    $isbn10 = books_v2_isbn_digits($isbn10);
    if (strlen($isbn10) !== 10) {
        return null;
    }
    if (!preg_match('/^[0-9]{9}[0-9X]$/', $isbn10)) {
        return null;
    }

    $body = '978' . substr($isbn10, 0, 9);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int) $body[$i];
        $sum += ($i % 2 === 0) ? $digit : $digit * 3;
    }
    $check = (10 - ($sum % 10)) % 10;

    return $body . (string) $check;
}

function books_v2_isbn13_checksum_ok(string $isbn13): bool
{
    $isbn13 = books_v2_isbn_digits($isbn13);
    if (strlen($isbn13) !== 13 || !ctype_digit($isbn13)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int) $isbn13[$i];
        $sum += ($i % 2 === 0) ? $digit : $digit * 3;
    }
    $check = (10 - ($sum % 10)) % 10;

    return $check === (int) $isbn13[12];
}

function books_v2_isbn10_checksum_ok(string $isbn10): bool
{
    $isbn10 = books_v2_isbn_digits($isbn10);
    if (strlen($isbn10) !== 10 || !preg_match('/^[0-9]{9}[0-9X]$/', $isbn10)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((int) $isbn10[$i]) * (10 - $i);
    }
    $check = $isbn10[9] === 'X' ? 10 : (int) $isbn10[9];

    return ($sum + $check) % 11 === 0;
}

/**
 * @return array{isbn_raw:string,isbn13:?string,is_invalid:bool}
 */
function books_v2_normalize_isbn(string $raw): array
{
    $isbnRaw = trim($raw);
    $digits = books_v2_isbn_digits($isbnRaw);
    if ($digits === '' || $isbnRaw === '') {
        return ['isbn_raw' => $isbnRaw, 'isbn13' => null, 'is_invalid' => $isbnRaw !== ''];
    }

    if (strlen($digits) === 13) {
        $ok = books_v2_isbn13_checksum_ok($digits);

        return ['isbn_raw' => $isbnRaw, 'isbn13' => $ok ? $digits : $digits, 'is_invalid' => !$ok];
    }

    if (strlen($digits) === 10) {
        $ok10 = books_v2_isbn10_checksum_ok($digits);
        $isbn13 = books_v2_isbn10_to_isbn13($digits);
        if ($isbn13 === null) {
            return ['isbn_raw' => $isbnRaw, 'isbn13' => null, 'is_invalid' => true];
        }

        return ['isbn_raw' => $isbnRaw, 'isbn13' => $isbn13, 'is_invalid' => !$ok10];
    }

    return ['isbn_raw' => $isbnRaw, 'isbn13' => null, 'is_invalid' => true];
}

/** Max length for book_*_i18n.slug (VARCHAR(191)). */
const BOOKS_V2_SLUG_MAX_LEN = 191;

function books_v2_slugify(string $text): string
{
    $slug = per_slugify($text);
    if (strlen($slug) > BOOKS_V2_SLUG_MAX_LEN) {
        $slug = rtrim(substr($slug, 0, BOOKS_V2_SLUG_MAX_LEN), '-');
    }

    return $slug;
}

/**
 * content_edition_id must point to an edition that owns its chapters (content_edition_id IS NULL).
 */
function books_v2_content_edition_depth_ok(?int $contentEditionId, array $editionsById): bool
{
    if ($contentEditionId === null || $contentEditionId <= 0) {
        return true;
    }
    if (!isset($editionsById[$contentEditionId])) {
        return false;
    }
    $target = $editionsById[$contentEditionId];
    $nested = $target['content_edition_id'] ?? null;

    return $nested === null || (int) $nested === 0;
}

function books_v2_strip_html_title(string $html): string
{
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function books_v2_year_from_unix(int $ts): ?int
{
    if ($ts <= 0) {
        return null;
    }
    $y = (int) gmdate('Y', $ts);
    if ($y < 1900 || $y > 2100) {
        return null;
    }

    return $y;
}
