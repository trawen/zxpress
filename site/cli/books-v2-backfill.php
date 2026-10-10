#!/usr/bin/env php
<?php
/**
 * Idempotent backfill: legacy books/chapters → books_v2 tables.
 *
 * Usage:
 *   php site/cli/books-v2-backfill.php --dry-run
 *   php site/cli/books-v2-backfill.php --apply
 *   php site/cli/books-v2-backfill.php --apply --limit=20
 *   php site/cli/books-v2-backfill.php --apply --book-id=12
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$dryRun = true;
$limit = 0;
$onlyBookId = 0;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    } elseif (str_starts_with($arg, '--book-id=')) {
        $onlyBookId = max(0, (int) substr($arg, 10));
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--limit=N] [--book-id=N]\n");
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/books-v2-backfill.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-books-v2-backfill';

$siteRoot = is_dir('/home/zxpress/web/zxpress.ru/public_html')
    ? '/home/zxpress/web/zxpress.ru/public_html'
    : dirname(__DIR__);

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';
require_once $siteRoot . '/includes/books_v2.php';
require_once $siteRoot . '/includes/authors_slugs.php';

/** @var mysqli $db */
if (!isset($db) || !($db instanceof mysqli)) {
    fwrite(STDERR, "No mysqli \$db\n");
    exit(1);
}

$chk = $db->query("SHOW TABLES LIKE 'book_works'");
if (!$chk || $chk->num_rows < 1) {
    fwrite(STDERR, "ERROR: book_works missing — run db/migration/books_v2.sql\n");
    exit(1);
}

$stats = [
    'books_seen' => 0,
    'works_upserted' => 0,
    'editions_upserted' => 0,
    'chapters_upserted' => 0,
    'bodies_upserted' => 0,
    'credits' => 0,
    'authors_created' => 0,
    'publishers_linked' => 0,
    'isbns' => 0,
    'files' => 0,
    'images' => 0,
    'rubrics' => 0,
    'series' => 0,
    'author_unmatched' => [],
    'isbn_invalid' => [],
    'chapter_body_missing' => [],
];

function bv2_log(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

function bv2_lang_code(mysqli $db, int $languageId): string
{
    static $cache = [];
    if (isset($cache[$languageId])) {
        return $cache[$languageId];
    }
    $stmt = $db->prepare('SELECT code FROM languages WHERE id=? LIMIT 1');
    $code = 'ru';
    if ($stmt) {
        $stmt->bind_param('i', $languageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && trim((string) $row['code']) !== '') {
            $code = (string) $row['code'];
        }
    }
    $cache[$languageId] = $code;

    return $code;
}

function bv2_slug_with_suffix(string $base, string $suffix): string
{
    $max = BOOKS_V2_SLUG_MAX_LEN;
    $suffix = ltrim($suffix, '-');
    if ($suffix === '') {
        return books_v2_slugify($base);
    }
    $room = $max - strlen($suffix) - 1;
    if ($room < 8) {
        return books_v2_slugify($suffix);
    }
    $trimmed = rtrim(substr($base, 0, $room), '-');

    return books_v2_slugify($trimmed . '-' . $suffix);
}

function bv2_unique_work_slug(mysqli $db, string $lang, string $base, int $legacyBookId): string
{
    $base = books_v2_slugify($base);
    if ($base === '') {
        $base = 'book-' . $legacyBookId;
    }
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = $db->prepare(
            'SELECT w.legacy_book_id FROM book_work_i18n i
             INNER JOIN book_works w ON w.id = i.work_id
             WHERE i.lang=? AND i.slug=? LIMIT 1'
        );
        if (!$stmt) {
            return $slug;
        }
        $stmt->bind_param('ss', $lang, $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return $slug;
        }
        if ((int) ($row['legacy_book_id'] ?? 0) === $legacyBookId) {
            return $slug;
        }
        $slug = bv2_slug_with_suffix($base, (string) $n);
        $n++;
        if ($n > 500) {
            return bv2_slug_with_suffix($base, (string) $legacyBookId);
        }
    }
}

function bv2_unique_chapter_slug(mysqli $db, int $editionId, string $lang, string $base, int $legacyChId): string
{
    $base = books_v2_slugify($base);
    if ($base === '') {
        $base = 'ch-' . $legacyChId;
    }
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = $db->prepare(
            'SELECT c.legacy_ch_id FROM book_chapter_i18n i
             INNER JOIN book_chapters c ON c.id = i.chapter_id
             WHERE c.edition_id=? AND i.lang=? AND i.slug=? LIMIT 1'
        );
        if (!$stmt) {
            return $slug;
        }
        $stmt->bind_param('iss', $editionId, $lang, $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return $slug;
        }
        if ((int) ($row['legacy_ch_id'] ?? 0) === $legacyChId) {
            return $slug;
        }
        $slug = bv2_slug_with_suffix($base, (string) $n);
        $n++;
        if ($n > 500) {
            return bv2_slug_with_suffix($base, (string) $legacyChId);
        }
    }
}

function bv2_find_or_create_author(mysqli $db, string $name, bool $dryRun, array &$stats): int
{
    $name = plain_text_normalize_for_storage($name);
    if ($name === '') {
        return 0;
    }
    // authors.nickname / name_* are VARCHAR(100); very long fragments are not person names.
    if (mb_strlen($name) > 100) {
        return 0;
    }

    $stmt = $db->prepare(
        'SELECT id FROM authors
         WHERE LOWER(nickname)=LOWER(?) OR LOWER(COALESCE(name_ru,\'\'))=LOWER(?)
            OR LOWER(COALESCE(name_en,\'\'))=LOWER(?)
         LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('sss', $name, $name, $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int) $row['id'];
        }
    }

    if ($dryRun) {
        $stats['authors_created']++;

        return -1;
    }

    $slugs = authors_resolve_slugs($db, '', '', $name, $name, $name, 0);
    $slugRu = $slugs['slug_ru'] ?? books_v2_slugify($name);
    $slugEn = $slugs['slug_en'] ?? $slugRu;
    $ins = $db->prepare(
        'INSERT INTO authors (nickname, name_ru, name_en, slug_ru, slug_en, is_active)
         VALUES (?,?,?,?,?,1)'
    );
    if (!$ins) {
        return 0;
    }
    $ins->bind_param('sssss', $name, $name, $name, $slugRu, $slugEn);
    if (!$ins->execute()) {
        $ins->close();

        return 0;
    }
    $id = (int) $ins->insert_id;
    $ins->close();
    $stats['authors_created']++;

    return $id;
}

function bv2_find_publisher_id(mysqli $db, string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    $stmt = $db->prepare(
        'SELECT id FROM publishers
         WHERE name_ru=? OR alias_ru=? OR name_en=? OR alias_en=?
         LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ssss', $name, $name, $name, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : 0;
}

function bv2_find_or_create_series(mysqli $db, string $title, string $lang, bool $dryRun, array &$stats): int
{
    $title = plain_text_normalize_for_storage(trim($title));
    if ($title === '') {
        return 0;
    }
    $stmt = $db->prepare('SELECT series_id FROM book_series_i18n WHERE lang=? AND title=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ss', $lang, $title);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int) $row['series_id'];
        }
    }
    if ($dryRun) {
        $stats['series']++;

        return -1;
    }
    $db->query('INSERT INTO book_series (publisher_id) VALUES (NULL)');
    $seriesId = (int) $db->insert_id;
    $slug = books_v2_slugify($title);
    if ($slug === '') {
        $slug = 'series-' . $seriesId;
    }
    // unique slug
    $base = $slug;
    $n = 2;
    while (true) {
        $chk = $db->prepare('SELECT series_id FROM book_series_i18n WHERE lang=? AND slug=? LIMIT 1');
        $chk->bind_param('ss', $lang, $slug);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$exists) {
            break;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    $ins = $db->prepare(
        'INSERT INTO book_series_i18n (series_id, lang, title, slug, description) VALUES (?,?,?,?,NULL)'
    );
    $ins->bind_param('isss', $seriesId, $lang, $title, $slug);
    $ins->execute();
    $ins->close();
    $stats['series']++;

    return $seriesId;
}

$sql = 'SELECT * FROM books';
if ($onlyBookId > 0) {
    $sql .= ' WHERE id=' . (int) $onlyBookId;
}
$sql .= ' ORDER BY id ASC';
if ($limit > 0) {
    $sql .= ' LIMIT ' . (int) $limit;
}

$books = $db->query($sql);
if (!$books) {
    fwrite(STDERR, 'SELECT books failed: ' . $db->error . "\n");
    exit(1);
}

bv2_log(($dryRun ? '[dry-run] ' : '[apply] ') . 'books-v2 backfill start');

while ($book = $books->fetch_assoc()) {
    $stats['books_seen']++;
    $legacyId = (int) $book['id'];
    $langId = (int) ($book['language'] ?? 1);
    if ($langId <= 0) {
        $langId = 1;
    }
    $langCode = bv2_lang_code($db, $langId);
    $title1 = plain_text_normalize_for_storage((string) ($book['title1'] ?? ''));
    $title2 = plain_text_normalize_for_storage((string) ($book['title2'] ?? ''));
    if ($title1 === '') {
        $title1 = 'Книга ' . $legacyId;
    }
    if (mb_strlen($title1) > 255) {
        $title1 = mb_substr($title1, 0, 255);
    }
    if (mb_strlen($title2) > 255) {
        $title2 = mb_substr($title2, 0, 255);
    }
    $annotation = (string) ($book['annotation'] ?? '');
    $year = books_v2_year_from_unix((int) ($book['date'] ?? 0));
    $cityId = (int) ($book['city_id'] ?? 0);
    if ($cityId <= 0) {
        $cityId = null;
    }
    $pages = (int) ($book['pages'] ?? 0);
    $circulation = (int) ($book['circulation'] ?? 0);
    $isActive = 1;

    $slug = bv2_unique_work_slug($db, $langCode, $title1, $legacyId);

    // Existing work?
    $workId = 0;
    $editionId = 0;
    $ex = $db->prepare('SELECT id, primary_edition_id FROM book_works WHERE legacy_book_id=? LIMIT 1');
    $ex->bind_param('i', $legacyId);
    $ex->execute();
    $exRow = $ex->get_result()->fetch_assoc();
    $ex->close();
    if ($exRow) {
        $workId = (int) $exRow['id'];
        $editionId = (int) ($exRow['primary_edition_id'] ?? 0);
    }

    if (!$dryRun) {
        $yearSql = $year === null ? 'NULL' : (string) (int) $year;
        if ($workId <= 0) {
            $db->query(
                'INSERT INTO book_works (original_language_id, first_year, is_active, legacy_book_id)
                 VALUES (' . (int) $langId . ',' . $yearSql . ',' . (int) $isActive . ',' . (int) $legacyId . ')'
            );
            $workId = (int) $db->insert_id;
        } else {
            $db->query(
                'UPDATE book_works SET original_language_id=' . (int) $langId
                . ', first_year=' . $yearSql
                . ', is_active=' . (int) $isActive
                . ' WHERE id=' . (int) $workId . ' LIMIT 1'
            );
        }

        $i18n = $db->prepare(
            'INSERT INTO book_work_i18n (work_id, lang, title, subtitle, slug, annotation, meta_description)
             VALUES (?,?,?,?,?,?,\'\')
             ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle),
               slug=VALUES(slug), annotation=VALUES(annotation)'
        );
        $i18n->bind_param('isssss', $workId, $langCode, $title1, $title2, $slug, $annotation);
        $i18n->execute();
        $i18n->close();

        if ($editionId <= 0) {
            $edEx = $db->prepare('SELECT id FROM book_editions WHERE legacy_book_id=? LIMIT 1');
            $edEx->bind_param('i', $legacyId);
            $edEx->execute();
            $edRow = $edEx->get_result()->fetch_assoc();
            $edEx->close();
            $editionId = $edRow ? (int) $edRow['id'] : 0;
        }

        $pubYear = $year;
        $pagesVal = $pages > 0 ? $pages : null;
        $circVal = $circulation > 0 ? $circulation : null;
        $citySql = $cityId === null ? 'NULL' : (string) (int) $cityId;
        $pagesSql = $pagesVal === null ? 'NULL' : (string) (int) $pagesVal;
        $circSql = $circVal === null ? 'NULL' : (string) (int) $circVal;
        $yearSql = $pubYear === null ? 'NULL' : (string) (int) $pubYear;
        if ($editionId <= 0) {
            $db->query(
                'INSERT INTO book_editions
                 (work_id, edition_kind, language_id, publish_year, city_id, pages, circulation, is_active, sort_order, legacy_book_id)
                 VALUES ('
                . (int) $workId . ',\'original\',' . (int) $langId . ',' . $yearSql . ','
                . $citySql . ',' . $pagesSql . ',' . $circSql . ',1,0,' . (int) $legacyId . ')'
            );
            $editionId = (int) $db->insert_id;
        } else {
            $db->query(
                'UPDATE book_editions SET work_id=' . (int) $workId
                . ', language_id=' . (int) $langId
                . ', publish_year=' . $yearSql
                . ', city_id=' . $citySql
                . ', pages=' . $pagesSql
                . ', circulation=' . $circSql
                . ' WHERE id=' . (int) $editionId . ' LIMIT 1'
            );
        }

        $edSlug = books_v2_slugify($title1 !== '' ? $title1 : ('edition-' . $legacyId));
        $ei = $db->prepare(
            'INSERT INTO book_edition_i18n (edition_id, lang, slug, title_override, edition_statement, note, meta_description)
             VALUES (?,?,?,\'\',\'\',NULL,\'\')
             ON DUPLICATE KEY UPDATE slug=VALUES(slug)'
        );
        $ei->bind_param('iss', $editionId, $langCode, $edSlug);
        $ei->execute();
        $ei->close();

        $db->query(
            'UPDATE book_works SET primary_edition_id=' . (int) $editionId
            . ' WHERE id=' . (int) $workId . ' LIMIT 1'
        );

        $stats['works_upserted']++;
        $stats['editions_upserted']++;
    } else {
        $stats['works_upserted']++;
        $stats['editions_upserted']++;
    }

    // Credits from authors string
    $authorNames = books_v2_parse_authors((string) ($book['authors'] ?? ''));
    $sort = 0;
    foreach ($authorNames as $aname) {
        $authorId = bv2_find_or_create_author($db, $aname, $dryRun, $stats);
        if ($authorId === 0) {
            $stats['author_unmatched'][] = $legacyId . ': ' . $aname;
            continue;
        }
        if ($authorId < 0) {
            // dry-run created placeholder
            $stats['credits']++;
            continue;
        }
        if (!$dryRun && $workId > 0) {
            $cchk = $db->prepare(
                'SELECT id FROM book_credits WHERE work_id=? AND author_id=? AND role=\'author\' LIMIT 1'
            );
            $cchk->bind_param('ii', $workId, $authorId);
            $cchk->execute();
            $exists = $cchk->get_result()->fetch_assoc();
            $cchk->close();
            if (!$exists) {
                $cins = $db->prepare(
                    'INSERT INTO book_credits (work_id, edition_id, author_id, role, sort_order)
                     VALUES (?,NULL,?,"author",?)'
                );
                $cins->bind_param('iii', $workId, $authorId, $sort);
                $cins->execute();
                $cins->close();
            }
        }
        $stats['credits']++;
        $sort++;
    }
    if ($authorNames === [] && trim((string) ($book['authors'] ?? '')) !== '') {
        $stats['author_unmatched'][] = $legacyId . ': ' . trim((string) $book['authors']);
    }

    // Publishers
    if (!$dryRun && $editionId > 0) {
        $bp = $db->prepare('SELECT publisher_id FROM book_publishers WHERE book_id=?');
        $bp->bind_param('i', $legacyId);
        $bp->execute();
        $bpRes = $bp->get_result();
        $linked = 0;
        while ($bpRow = $bpRes->fetch_assoc()) {
            $pid = (int) $bpRow['publisher_id'];
            $db->query(
                'INSERT IGNORE INTO book_edition_publishers (edition_id, publisher_id, role, sort_order)
                 VALUES (' . (int) $editionId . ',' . $pid . ',\'publisher\',' . $linked . ')'
            );
            $linked++;
            $stats['publishers_linked']++;
        }
        $bp->close();
        if ($linked === 0) {
            $pid = bv2_find_publisher_id($db, (string) ($book['publisher'] ?? ''));
            if ($pid > 0) {
                $db->query(
                    'INSERT IGNORE INTO book_edition_publishers (edition_id, publisher_id, role, sort_order)
                     VALUES (' . (int) $editionId . ',' . $pid . ',\'publisher\',0)'
                );
                $stats['publishers_linked']++;
            }
        }
    }

    // ISBN
    $isbnRaw = trim((string) ($book['isbn'] ?? ''));
    if ($isbnRaw !== '' && $isbnRaw !== '0') {
        $norm = books_v2_normalize_isbn($isbnRaw);
        if ($norm['is_invalid']) {
            $stats['isbn_invalid'][] = $legacyId . ': ' . $isbnRaw;
        }
        if (!$dryRun && $editionId > 0) {
            $ichk = $db->prepare(
                'SELECT id FROM book_edition_isbns WHERE edition_id=? AND isbn_raw=? LIMIT 1'
            );
            $ichk->bind_param('is', $editionId, $norm['isbn_raw']);
            $ichk->execute();
            $iex = $ichk->get_result()->fetch_assoc();
            $ichk->close();
            if (!$iex) {
                $isbn13 = $norm['isbn13'];
                $inv = $norm['is_invalid'] ? 1 : 0;
                if ($isbn13 === null) {
                    $db->query(
                        'INSERT INTO book_edition_isbns
                         (edition_id, isbn_raw, isbn13, is_primary, is_invalid, sort_order)
                         VALUES (' . (int) $editionId . ','
                        . "'" . $db->real_escape_string($norm['isbn_raw']) . "',NULL,1,"
                        . $inv . ',0)'
                    );
                } else {
                    $iins = $db->prepare(
                        'INSERT INTO book_edition_isbns
                         (edition_id, isbn_raw, isbn13, is_primary, is_invalid, sort_order)
                         VALUES (?,?,?,1,?,0)'
                    );
                    $iins->bind_param('issi', $editionId, $norm['isbn_raw'], $isbn13, $inv);
                    $iins->execute();
                    $iins->close();
                }
            }
        }
        $stats['isbns']++;
    }

    // Series
    $seriesTitle = trim((string) ($book['series'] ?? ''));
    if ($seriesTitle !== '') {
        $seriesId = bv2_find_or_create_series($db, $seriesTitle, $langCode, $dryRun, $stats);
        if (!$dryRun && $seriesId > 0 && $editionId > 0) {
            $db->query(
                'INSERT IGNORE INTO book_edition_series (edition_id, series_id, number_in_series)
                 VALUES (' . (int) $editionId . ',' . (int) $seriesId . ',\'\')'
            );
        }
    }

    // Rubrics
    if (!$dryRun && $workId > 0) {
        $rr = $db->prepare('SELECT rubric_id FROM book_rubric_links WHERE book_id=?');
        $rr->bind_param('i', $legacyId);
        $rr->execute();
        $rrRes = $rr->get_result();
        while ($rrow = $rrRes->fetch_assoc()) {
            $rid = (int) $rrow['rubric_id'];
            $db->query(
                'INSERT IGNORE INTO book_work_rubrics (work_id, rubric_id) VALUES ('
                . (int) $workId . ',' . $rid . ')'
            );
            $stats['rubrics']++;
        }
        $rr->close();
    }

    // Files
    if (!$dryRun && $editionId > 0) {
        $ff = $db->prepare('SELECT * FROM books_files WHERE book_id=?');
        $ff->bind_param('i', $legacyId);
        $ff->execute();
        $ffRes = $ff->get_result();
        $so = 0;
        while ($frow = $ffRes->fetch_assoc()) {
            $legacyFileId = (int) $frow['id'];
            $fchk = $db->prepare('SELECT id FROM book_edition_files WHERE legacy_file_id=? LIMIT 1');
            $fchk->bind_param('i', $legacyFileId);
            $fchk->execute();
            $fex = $fchk->get_result()->fetch_assoc();
            $fchk->close();
            if ($fex) {
                $stats['files']++;
                continue;
            }
            $fname = (string) $frow['file_name'];
            $ftype = (int) $frow['file_type'];
            $fsize = (int) $frow['file_size'];
            $fdl = (int) $frow['downloads'];
            $fauthor = (string) $frow['author'];
            $fup = (int) $frow['upload_date'];
            $fins = $db->prepare(
                'INSERT INTO book_edition_files
                 (edition_id, file_name, file_type, file_size, downloads, author, upload_date, legacy_file_id, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $fins->bind_param('isiiisiii', $editionId, $fname, $ftype, $fsize, $fdl, $fauthor, $fup, $legacyFileId, $so);
            $fins->execute();
            $fileId = (int) $fins->insert_id;
            $fins->close();
            $comment = (string) ($frow['comment'] ?? '');
            $fi = $db->prepare(
                'INSERT INTO book_edition_file_i18n (file_id, lang, title, comment) VALUES (?,?,\'\',?)'
            );
            $fi->bind_param('iss', $fileId, $langCode, $comment);
            $fi->execute();
            $fi->close();
            $stats['files']++;
            $so++;
        }
        $ff->close();
    }

    // Images / pictures
    if (!$dryRun && $editionId > 0) {
        $pp = $db->prepare('SELECT * FROM pictures WHERE book_id=? ORDER BY type ASC, id ASC');
        $pp->bind_param('i', $legacyId);
        $pp->execute();
        $ppRes = $pp->get_result();
        $so = 0;
        while ($prow = $ppRes->fetch_assoc()) {
            $legacyPic = (int) $prow['id'];
            $pchk = $db->prepare('SELECT id FROM book_edition_images WHERE legacy_picture_id=? LIMIT 1');
            $pchk->bind_param('i', $legacyPic);
            $pchk->execute();
            $pex = $pchk->get_result()->fetch_assoc();
            $pchk->close();
            if ($pex) {
                $stats['images']++;
                continue;
            }
            $ptype = (int) $prow['type'];
            $imageType = match ($ptype) {
                1 => 'cover_back',
                2 => 'scan',
                default => 'cover_front',
            };
            // Prefer primary cover when books.image_id matches
            if ((int) ($book['image_id'] ?? 0) === $legacyPic) {
                $imageType = 'cover_front';
            }
            $path = 'pictures/' . $legacyPic . '.jpg';
            $pins = $db->prepare(
                'INSERT INTO book_edition_images
                 (edition_id, image_type, path, sort_order, legacy_picture_id)
                 VALUES (?,?,?,?,?)'
            );
            $pins->bind_param('issii', $editionId, $imageType, $path, $so, $legacyPic);
            $pins->execute();
            $pins->close();
            $stats['images']++;
            $so++;
        }
        $pp->close();
    }

    // Chapters
    $chSql = 'SELECT * FROM chapters WHERE ch_id_book=' . (int) $legacyId . ' ORDER BY ch_number ASC, ch_id ASC';
    $chs = $db->query($chSql);
    $chSort = 0;
    while ($chs && ($ch = $chs->fetch_assoc())) {
        $legacyCh = (int) $ch['ch_id'];
        $chTitleRaw = (string) ($ch['ch_title'] ?? '');
        $chTitle = books_v2_strip_html_title($chTitleRaw);
        if ($chTitle === '') {
            $chTitle = 'Глава ' . $legacyCh;
        }
        if (mb_strlen($chTitle) > 512) {
            $chTitle = mb_substr($chTitle, 0, 512);
        }
        $views = (int) ($ch['ch_views'] ?? 0);
        $chNumber = (int) ($ch['ch_number'] ?? 0);
        $sortOrder = $chNumber > 0 ? $chNumber : $chSort;
        $chType = ((int) ($ch['ch_type'] ?? 0) === 0) ? 'chapter' : 'chapter';

        if (!$dryRun && $editionId > 0) {
            $cEx = $db->prepare('SELECT id FROM book_chapters WHERE legacy_ch_id=? LIMIT 1');
            $cEx->bind_param('i', $legacyCh);
            $cEx->execute();
            $cRow = $cEx->get_result()->fetch_assoc();
            $cEx->close();
            $chapterId = $cRow ? (int) $cRow['id'] : 0;
            if ($chapterId <= 0) {
                $cins = $db->prepare(
                    'INSERT INTO book_chapters
                     (edition_id, chapter_type, sort_order, views, is_active, legacy_ch_id)
                     VALUES (?, ?, ?, ?, 1, ?)'
                );
                $cins->bind_param('isiii', $editionId, $chType, $sortOrder, $views, $legacyCh);
                $cins->execute();
                $chapterId = (int) $cins->insert_id;
                $cins->close();
            } else {
                $cupd = $db->prepare(
                    'UPDATE book_chapters SET edition_id=?, sort_order=?, views=? WHERE id=? LIMIT 1'
                );
                $cupd->bind_param('iiii', $editionId, $sortOrder, $views, $chapterId);
                $cupd->execute();
                $cupd->close();
            }

            $chSlug = bv2_unique_chapter_slug($db, $editionId, $langCode, $chTitle, $legacyCh);
            $ci = $db->prepare(
                'INSERT INTO book_chapter_i18n (chapter_id, lang, title, slug, meta_description)
                 VALUES (?,?,?,?,\'\')
                 ON DUPLICATE KEY UPDATE title=VALUES(title), slug=VALUES(slug)'
            );
            $ci->bind_param('isss', $chapterId, $langCode, $chTitle, $chSlug);
            $ci->execute();
            $ci->close();

            $path = zx_storage_path('chapters', (string) $legacyCh);
            $body = '';
            if (is_file($path) && is_readable($path)) {
                $body = (string) file_get_contents($path);
            } else {
                $stats['chapter_body_missing'][] = $legacyCh;
            }
            $hash = $body !== '' ? sha1($body) : '';
            $bchk = $db->prepare('SELECT chapter_id, source_hash FROM book_chapter_bodies WHERE chapter_id=? LIMIT 1');
            $bchk->bind_param('i', $chapterId);
            $bchk->execute();
            $brow = $bchk->get_result()->fetch_assoc();
            $bchk->close();
            if (!$brow) {
                $bins = $db->prepare(
                    'INSERT INTO book_chapter_bodies (chapter_id, format, body, source_hash)
                     VALUES (?,\'html\',?,?)'
                );
                $bins->bind_param('iss', $chapterId, $body, $hash);
                $bins->execute();
                $bins->close();
                $stats['bodies_upserted']++;
            } elseif ((string) ($brow['source_hash'] ?? '') !== $hash && $body !== '') {
                $bupd = $db->prepare(
                    'UPDATE book_chapter_bodies SET body=?, source_hash=?, format=\'html\' WHERE chapter_id=? LIMIT 1'
                );
                $bupd->bind_param('ssi', $body, $hash, $chapterId);
                $bupd->execute();
                $bupd->close();
                $stats['bodies_upserted']++;
            }

            $stats['chapters_upserted']++;
        } else {
            $stats['chapters_upserted']++;
        }
        $chSort++;
    }
}

bv2_log('--- report ---');
foreach (['books_seen', 'works_upserted', 'editions_upserted', 'chapters_upserted', 'bodies_upserted', 'credits', 'authors_created', 'publishers_linked', 'isbns', 'files', 'images', 'rubrics', 'series'] as $k) {
    bv2_log($k . ': ' . $stats[$k]);
}
if ($stats['author_unmatched'] !== []) {
    bv2_log('author_unmatched (' . count($stats['author_unmatched']) . '):');
    foreach (array_slice($stats['author_unmatched'], 0, 40) as $line) {
        bv2_log('  ' . $line);
    }
}
if ($stats['isbn_invalid'] !== []) {
    bv2_log('isbn_invalid (' . count($stats['isbn_invalid']) . '):');
    foreach (array_slice($stats['isbn_invalid'], 0, 40) as $line) {
        bv2_log('  ' . $line);
    }
}
if ($stats['chapter_body_missing'] !== []) {
    bv2_log('chapter_body_missing: ' . count($stats['chapter_body_missing']));
}
bv2_log('done');
exit(0);
