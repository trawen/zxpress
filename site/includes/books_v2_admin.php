<?php

declare(strict_types=1);

require_once __DIR__ . '/books_v2.php';

function books_v2_admin_unique_work_slug(mysqli $db, string $lang, string $base, int $workId): string
{
    $base = books_v2_slugify($base);
    if ($base === '') {
        $base = 'work-' . max(1, $workId);
    }
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = $db->prepare('SELECT work_id FROM book_work_i18n WHERE lang=? AND slug=? LIMIT 1');
        $stmt->bind_param('ss', $lang, $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || (int) $row['work_id'] === $workId) {
            return $slug;
        }
        $suffix = (string) $n;
        $room = BOOKS_V2_SLUG_MAX_LEN - strlen($suffix) - 1;
        $slug = rtrim(substr($base, 0, max(8, $room)), '-') . '-' . $suffix;
        $n++;
        if ($n > 200) {
            return rtrim(substr($base, 0, 170), '-') . '-' . $workId;
        }
    }
}

/**
 * @return list<array{id:int,code:string,name:string}>
 */
function books_v2_admin_languages(mysqli $db): array
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
function books_v2_admin_works(mysqli $db, string $letter): array
{
    $sql = "SELECT w.id, w.is_active, w.first_year, w.legacy_book_id,
            COALESCE(i_ru.title, i_en.title, CONCAT('Work #', w.id)) AS title,
            (SELECT GROUP_CONCAT(a.nickname ORDER BY c.sort_order SEPARATOR ', ')
               FROM book_credits c INNER JOIN authors a ON a.id=c.author_id
               WHERE c.work_id=w.id AND c.role='author') AS authors,
            (SELECT GROUP_CONCAT(i.lang ORDER BY i.lang SEPARATOR ',') FROM book_work_i18n i WHERE i.work_id=w.id) AS langs,
            (SELECT COUNT(*) FROM book_editions e WHERE e.work_id=w.id) AS editions_count,
            (SELECT COUNT(*) FROM book_chapters ch INNER JOIN book_editions e2 ON e2.id=ch.edition_id WHERE e2.work_id=w.id) AS chapters_count
         FROM book_works w
         LEFT JOIN book_work_i18n i_ru ON i_ru.work_id=w.id AND i_ru.lang='ru'
         LEFT JOIN book_work_i18n i_en ON i_en.work_id=w.id AND i_en.lang='en'
         ORDER BY title ASC, w.id ASC";
    $rows = [];
    $z = db_select($db, $sql);
    $letter = mb_strtoupper($letter);
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $title = plain_text_normalize_for_storage((string) ($row['title'] ?? ''));
        $row['title'] = $title;
        $first = mb_strtoupper(mb_substr($title, 0, 1));
        $row['letter'] = $first;
        if ($letter !== '' && $letter !== 'ALL' && $first !== $letter) {
            continue;
        }
        $rows[] = $row;
    }

    return $rows;
}

/**
 * @param list<array{id:int,code:string}> $languages
 * @return array<string,array<string,string>>
 */
function books_v2_admin_work_i18n(mysqli $db, int $workId, array $languages): array
{
    $by = [];
    foreach ($languages as $lang) {
        $code = (string) $lang['code'];
        $by[$code] = ['title' => '', 'subtitle' => '', 'slug' => '', 'annotation' => '', 'meta_description' => ''];
    }
    if ($workId <= 0) {
        return $by;
    }
    $stmt = $db->prepare('SELECT * FROM book_work_i18n WHERE work_id=?');
    $stmt->bind_param('i', $workId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $code = (string) $row['lang'];
        $by[$code] = [
            'title' => (string) ($row['title'] ?? ''),
            'subtitle' => (string) ($row['subtitle'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'annotation' => (string) ($row['annotation'] ?? ''),
            'meta_description' => (string) ($row['meta_description'] ?? ''),
        ];
    }
    $stmt->close();

    return $by;
}

/**
 * @param list<string> $codes
 * @return array<string,array<string,string>>
 */
function books_v2_admin_posted_i18n(array $codes): array
{
    $raw = $_POST['i18n'] ?? [];
    if (!is_array($raw)) {
        $raw = [];
    }
    $out = [];
    foreach ($codes as $code) {
        $row = is_array($raw[$code] ?? null) ? $raw[$code] : [];
        $out[$code] = [
            'title' => plain_text_normalize_for_storage(trim((string) ($row['title'] ?? ''))),
            'subtitle' => plain_text_normalize_for_storage(trim((string) ($row['subtitle'] ?? ''))),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'annotation' => (string) ($row['annotation'] ?? ''),
            'meta_description' => plain_text_normalize_for_storage(trim((string) ($row['meta_description'] ?? ''))),
        ];
    }

    return $out;
}

function books_v2_admin_i18n_empty(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }

    return true;
}

/**
 * @return array{id:int,error:string}
 */
function books_v2_admin_save_work(mysqli $db, int $id, array $langById): array
{
    $langId = (int) ($_POST['original_language_id'] ?? 1);
    if ($langId <= 0) {
        $langId = 1;
    }
    $orig = $langById[$langId] ?? 'ru';
    $codes = array_values($langById);
    $i18n = books_v2_admin_posted_i18n($codes);
    $title = $i18n[$orig]['title'] ?? '';
    if ($title === '') {
        return ['id' => $id, 'error' => 'Название на языке оригинала обязательно'];
    }
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $wasCreate = $id <= 0;

    if ($id <= 0) {
        $db->query(
            'INSERT INTO book_works (original_language_id, is_active) VALUES ('
            . (int) $langId . ',' . $active . ')'
        );
        $id = (int) $db->insert_id;
    } else {
        $db->query(
            'UPDATE book_works SET original_language_id=' . (int) $langId
            . ', is_active=' . $active
            . ' WHERE id=' . $id . ' LIMIT 1'
        );
    }

    foreach ($codes as $code) {
        $row = $i18n[$code];
        if ($code !== $orig && books_v2_admin_i18n_empty($row)) {
            $del = $db->prepare('DELETE FROM book_work_i18n WHERE work_id=? AND lang=? LIMIT 1');
            $del->bind_param('is', $id, $code);
            $del->execute();
            $del->close();
            continue;
        }
        if ($row['title'] === '') {
            continue;
        }
        $slug = books_v2_admin_unique_work_slug($db, $code, $row['slug'] !== '' ? $row['slug'] : $row['title'], $id);
        $stmt = $db->prepare(
            'INSERT INTO book_work_i18n (work_id, lang, title, subtitle, slug, annotation, meta_description)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), slug=VALUES(slug),
               annotation=VALUES(annotation), meta_description=VALUES(meta_description)'
        );
        $stmt->bind_param('issssss', $id, $code, $row['title'], $row['subtitle'], $slug, $row['annotation'], $row['meta_description']);
        $stmt->execute();
        $stmt->close();
    }

    $db->query('DELETE FROM book_credits WHERE work_id=' . (int) $id);
    $authors = $_POST['credit_author'] ?? [];
    $roles = $_POST['credit_role'] ?? [];
    if (is_array($authors)) {
        $sort = 0;
        foreach ($authors as $i => $authorId) {
            $authorId = (int) $authorId;
            if ($authorId <= 0) {
                continue;
            }
            $role = (string) ($roles[$i] ?? 'author');
            $allowed = ['author', 'coauthor', 'compiler', 'editor', 'translator', 'illustrator', 'designer', 'foreword'];
            if (!in_array($role, $allowed, true)) {
                $role = 'author';
            }
            $stmt = $db->prepare('INSERT INTO book_credits (work_id, edition_id, author_id, role, sort_order) VALUES (?,NULL,?,?,?)');
            $stmt->bind_param('iisi', $id, $authorId, $role, $sort);
            $stmt->execute();
            $stmt->close();
            $sort++;
        }
    }

    $db->query('DELETE FROM book_work_rubrics WHERE work_id=' . (int) $id);
    $rubrics = $_POST['rubric_id'] ?? [];
    if (is_array($rubrics)) {
        foreach ($rubrics as $rid) {
            $rid = (int) $rid;
            if ($rid > 0) {
                $db->query('INSERT IGNORE INTO book_work_rubrics (work_id, rubric_id) VALUES (' . $id . ',' . $rid . ')');
            }
        }
    }

    $editionId = books_v2_admin_save_main_edition($db, $id, $langId, $orig);
    $coverError = books_v2_admin_save_covers($db, $editionId);

    activity_log($db, [
        'verb' => $wasCreate ? 'created' : 'updated',
        'object_type' => 'book',
        'object_id' => $id,
        'action' => $wasCreate ? 'book_work.created' : 'book_work.updated',
        'event_scope' => ACTIVITY_SCOPE_METADATA,
        'is_public' => 0,
        'title_ru' => $title,
        'title_en' => $i18n['en']['title'] ?? $title,
    ]);

    return ['id' => $id, 'error' => $coverError];
}

/**
 * @return list<array<string,mixed>>
 */
function books_v2_admin_covers(mysqli $db, int $editionId): array
{
    if ($editionId <= 0) {
        return [];
    }
    $covers = [];
    $z = db_select(
        $db,
        'SELECT id, path, sort_order, legacy_picture_id FROM book_edition_images WHERE edition_id=? ORDER BY sort_order, id',
        'i',
        $editionId
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $urls = books_v2_admin_cover_urls($row);
        $row['preview_url'] = $urls['preview_url'];
        $row['original_url'] = $urls['original_url'];
        $covers[] = $row;
    }

    return $covers;
}

/**
 * @param array<string,mixed> $row
 * @return array{preview_url:string,original_url:string}
 */
function books_v2_admin_cover_urls(array $row): array
{
    $path = str_replace('\\', '/', (string) ($row['path'] ?? ''));
    if (preg_match('#^pictures/(\d+)\.(jpe?g|png|webp)$#i', $path, $m)) {
        $ext = strtolower($m[2] === 'jpeg' ? 'jpg' : $m[2]);

        return [
            'preview_url' => '/pictures/thumbs/' . $m[1] . '.jpg',
            'original_url' => '/pictures/' . $m[1] . '.' . $ext,
        ];
    }
    if (preg_match('#^pictures/book-(\d+)\.webp$#', $path, $m)) {
        $leaf = 'book-' . $m[1];
        $preview = zx_storage_path('pictures', 'thumbs/' . $leaf . '.jpg');
        $v = is_file($preview) ? (string) filemtime($preview) : (string) time();

        return [
            'preview_url' => '/pictures/thumbs/' . $leaf . '.jpg?v=' . $v,
            'original_url' => '/pictures/' . $leaf . '.webp',
        ];
    }
    $url = '/' . ltrim($path, '/');

    return ['preview_url' => $url, 'original_url' => $url];
}

function books_v2_admin_delete_cover(mysqli $db, int $workId, int $imageId): bool
{
    if ($workId <= 0 || $imageId <= 0) {
        return false;
    }
    $z = db_select(
        $db,
        'SELECT i.id, i.path, i.legacy_picture_id
         FROM book_edition_images i
         INNER JOIN book_editions e ON e.id=i.edition_id
         WHERE i.id=? AND e.work_id=? LIMIT 1',
        'ii',
        $imageId,
        $workId
    );
    $row = $z ? mysqli_fetch_assoc($z) : null;
    if (!$row) {
        return false;
    }
    db_exec($db, 'DELETE FROM book_edition_images WHERE id=? LIMIT 1', 'i', $imageId);
    $path = (string) ($row['path'] ?? '');
    if ($row['legacy_picture_id'] === null && preg_match('#^pictures/(book-\d+)\.webp$#', $path, $m)) {
        @unlink(zx_storage_path('pictures', $m[1] . '.webp'));
        @unlink(zx_storage_path('pictures', 'thumbs/' . $m[1] . '.jpg'));
    }

    return true;
}

/** Sort existing covers and store newly uploaded files. Returns an error string. */
function books_v2_admin_save_covers(mysqli $db, int $editionId): string
{
    if ($editionId <= 0) {
        return '';
    }
    $z = db_select($db, 'SELECT id, sort_order FROM book_edition_images WHERE edition_id=? ORDER BY sort_order, id', 'i', $editionId);
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $imageId = (int) $row['id'];
        $key = 'sort_order_' . $imageId;
        if (!array_key_exists($key, $_POST)) {
            continue;
        }
        $newSort = (int) $_POST[$key];
        if ($newSort !== (int) $row['sort_order']) {
            db_exec($db, 'UPDATE book_edition_images SET sort_order=? WHERE id=? AND edition_id=? LIMIT 1', 'iii', $newSort, $imageId, $editionId);
        }
    }

    $upl = (isset($_FILES['upload_covers']) && is_array($_FILES['upload_covers'])) ? $_FILES['upload_covers'] : [];
    $names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
    if ($names === []) {
        return '';
    }
    require_once __DIR__ . '/letters_admin.php';

    $nextSort = 0;
    $rowMax = db_select($db, 'SELECT COALESCE(MAX(sort_order), 0) AS mx FROM book_edition_images WHERE edition_id=?', 'i', $editionId);
    $maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
    if ($maxRow) {
        $nextSort = (int) $maxRow['mx'];
    }
    $errors = [];
    $tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];
    foreach ($names as $i => $origName) {
        $origName = (string) $origName;
        $tmp = (string) ($tmps[$i] ?? '');
        $errCode = (int) ($upl['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errCode !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp) || $origName === '') {
            if ($origName !== '' && $errCode !== UPLOAD_ERR_NO_FILE) {
                $errors[] = $origName . ': ' . letters_upload_error_message($errCode);
            }
            continue;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true, 'image/gif' => true];
        if (!isset($allowed[$mime])) {
            $errors[] = $origName . ': недопустимый тип';
            continue;
        }
        $nextSort++;
        $blank = '';
        $ins = $db->prepare(
            'INSERT INTO book_edition_images (edition_id, image_type, path, sort_order) VALUES (?,\'cover_front\',?,?)'
        );
        $ins->bind_param('isi', $editionId, $blank, $nextSort);
        $ins->execute();
        $imageId = (int) $ins->insert_id;
        $ins->close();
        if ($imageId <= 0) {
            $errors[] = $origName . ': не удалось создать запись';
            continue;
        }
        $leaf = 'book-' . $imageId;
        $originalPath = zx_storage_path('pictures', $leaf . '.webp');
        $saved = letters_save_original_webp($tmp, $originalPath, null);
        if (!$saved['ok']) {
            db_exec($db, 'DELETE FROM book_edition_images WHERE id=? LIMIT 1', 'i', $imageId);
            $errors[] = $origName . ': не удалось сохранить файл';
            continue;
        }
        $path = 'pictures/' . $leaf . '.webp';
        $width = (int) $saved['width'];
        $height = (int) $saved['height'];
        db_exec(
            $db,
            'UPDATE book_edition_images SET path=?, width=?, height=? WHERE id=? LIMIT 1',
            'siii',
            $path,
            $width,
            $height,
            $imageId
        );
        if (!letters_make_jpeg_preview($originalPath, zx_storage_path('pictures', 'thumbs/' . $leaf . '.jpg'), 480, 85)) {
            $errors[] = $origName . ': файл сохранён, но превью не создалось';
        }
    }

    return $errors === [] ? '' : ('Обложки: ' . implode('; ', $errors));
}

function books_v2_admin_main_edition_id(mysqli $db, int $workId): int
{
    if ($workId <= 0) {
        return 0;
    }
    $z = db_select(
        $db,
        'SELECT e.id FROM book_works w
         INNER JOIN book_editions e ON e.id=w.primary_edition_id AND e.work_id=w.id
         WHERE w.id=? LIMIT 1',
        'i',
        $workId
    );
    $row = $z ? mysqli_fetch_assoc($z) : null;
    if ($row) {
        return (int) $row['id'];
    }
    $z = db_select($db, 'SELECT id FROM book_editions WHERE work_id=? ORDER BY sort_order, id LIMIT 1', 'i', $workId);
    $row = $z ? mysqli_fetch_assoc($z) : null;

    return $row ? (int) $row['id'] : 0;
}

/** Posted Y-m-d, or NULL when empty or not a real date. */
function books_v2_admin_date_sql(mysqli $db, string $key): string
{
    $raw = trim((string) ($_POST[$key] ?? ''));
    $dt = DateTime::createFromFormat('!Y-m-d', $raw);
    if ($raw === '' || !$dt || $dt->format('Y-m-d') !== $raw) {
        return 'NULL';
    }

    return "'" . $db->real_escape_string($raw) . "'";
}

function books_v2_admin_sync_work_year(mysqli $db, int $workId): void
{
    if ($workId <= 0) {
        return;
    }
    db_exec(
        $db,
        'UPDATE book_works SET first_year=(
            SELECT MIN(e.publish_year) FROM book_editions e WHERE e.work_id=? AND e.publish_year IS NOT NULL
         ) WHERE id=? LIMIT 1',
        'ii',
        $workId,
        $workId
    );
}

/**
 * Year, pages, circulation, ISBN, publishers and series of the work's main edition,
 * posted together with the work form. City stays on the edition page.
 */
function books_v2_admin_save_main_edition(mysqli $db, int $workId, int $langId, string $langCode): int
{
    $year = (int) ($_POST['publish_year'] ?? 0);
    $pages = (int) ($_POST['pages'] ?? 0);
    $circ = (int) ($_POST['circulation'] ?? 0);
    $signedSql = books_v2_admin_date_sql($db, 'signed_for_print');
    $yearSql = $year > 0 ? (string) $year : 'NULL';
    $pagesSql = $pages > 0 ? (string) $pages : 'NULL';
    $circSql = $circ > 0 ? (string) $circ : 'NULL';

    $editionId = books_v2_admin_main_edition_id($db, $workId);
    if ($editionId <= 0) {
        $db->query(
            'INSERT INTO book_editions (work_id, edition_kind, language_id, publish_year, signed_for_print, pages, circulation, is_active, sort_order)
             VALUES (' . $workId . ",'original'," . $langId . ',' . $yearSql . ',' . $signedSql . ',' . $pagesSql . ',' . $circSql . ',1,0)'
        );
        $editionId = (int) $db->insert_id;
    } else {
        $db->query(
            'UPDATE book_editions SET publish_year=' . $yearSql
            . ', signed_for_print=' . $signedSql
            . ', pages=' . $pagesSql
            . ', circulation=' . $circSql
            . ", language_id=IF(edition_kind='original', " . $langId . ', language_id)'
            . ' WHERE id=' . $editionId . ' LIMIT 1'
        );
    }
    $db->query('UPDATE book_works SET primary_edition_id=' . $editionId . ' WHERE id=' . $workId . ' LIMIT 1');

    $z = db_select($db, 'SELECT 1 FROM book_edition_i18n WHERE edition_id=? LIMIT 1', 'i', $editionId);
    if (!($z && mysqli_fetch_row($z))) {
        $slug = 'edition-' . $editionId;
        $ei = $db->prepare(
            'INSERT INTO book_edition_i18n (edition_id, lang, slug, title_override, edition_statement, note, meta_description)
             VALUES (?,?,?,\'\',\'\',NULL,\'\')'
        );
        $ei->bind_param('iss', $editionId, $langCode, $slug);
        $ei->execute();
        $ei->close();
    }

    books_v2_admin_save_edition_links($db, $editionId);
    books_v2_admin_sync_work_year($db, $workId);

    return $editionId;
}

function books_v2_admin_save_edition_links(mysqli $db, int $id): void
{
    $db->query('DELETE FROM book_edition_isbns WHERE edition_id=' . (int) $id);
    $isbns = $_POST['isbn_raw'] ?? [];
    if (is_array($isbns)) {
        $sort = 0;
        foreach ($isbns as $rawIsbn) {
            $rawIsbn = trim((string) $rawIsbn);
            if ($rawIsbn === '') {
                continue;
            }
            $norm = books_v2_normalize_isbn($rawIsbn);
            $inv = $norm['is_invalid'] ? 1 : 0;
            $primary = $sort === 0 ? 1 : 0;
            if ($norm['isbn13'] === null) {
                $db->query(
                    'INSERT INTO book_edition_isbns (edition_id, isbn_raw, isbn13, is_primary, is_invalid, sort_order) VALUES ('
                    . (int) $id . ",'" . $db->real_escape_string($norm['isbn_raw']) . "',NULL," . $primary . ',' . $inv . ',' . $sort . ')'
                );
            } else {
                $ins = $db->prepare(
                    'INSERT INTO book_edition_isbns (edition_id, isbn_raw, isbn13, is_primary, is_invalid, sort_order) VALUES (?,?,?,?,?,?)'
                );
                $ins->bind_param('issiii', $id, $norm['isbn_raw'], $norm['isbn13'], $primary, $inv, $sort);
                $ins->execute();
                $ins->close();
            }
            $sort++;
        }
    }

    $db->query('DELETE FROM book_edition_publishers WHERE edition_id=' . (int) $id);
    $pubs = $_POST['publisher_id'] ?? [];
    $proles = $_POST['publisher_role'] ?? [];
    if (is_array($pubs)) {
        $sort = 0;
        foreach ($pubs as $i => $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            $role = (string) ($proles[$i] ?? 'publisher');
            if (!in_array($role, ['publisher', 'copublisher', 'printer', 'distributor'], true)) {
                $role = 'publisher';
            }
            $db->query(
                'INSERT IGNORE INTO book_edition_publishers (edition_id, publisher_id, role, sort_order) VALUES ('
                . (int) $id . ',' . $pid . ",'" . $db->real_escape_string($role) . "'," . $sort . ')'
            );
            $sort++;
        }
    }

    $db->query('DELETE FROM book_edition_series WHERE edition_id=' . (int) $id);
    $seriesId = (int) ($_POST['series_id'] ?? 0);
    if ($seriesId > 0) {
        $num = $db->real_escape_string(trim((string) ($_POST['number_in_series'] ?? '')));
        $db->query(
            'INSERT INTO book_edition_series (edition_id, series_id, number_in_series) VALUES ('
            . (int) $id . ',' . $seriesId . ",'" . $num . "')"
        );
    }
}

/**
 * @return array{id:int,title:string,existed:bool}
 */
function books_v2_admin_create_series(mysqli $db, string $title): array
{
    $title = plain_text_normalize_for_storage(trim($title));
    if ($title === '') {
        throw new InvalidArgumentException('Название серии обязательно');
    }
    if (mb_strlen($title) > 255) {
        throw new InvalidArgumentException('Название серии длиннее 255 символов');
    }
    $lang = 'ru';
    $z = db_select($db, 'SELECT series_id, title FROM book_series_i18n WHERE lang=? AND LOWER(title)=LOWER(?) LIMIT 1', 'ss', $lang, $title);
    $row = $z ? mysqli_fetch_assoc($z) : null;
    if ($row) {
        return ['id' => (int) $row['series_id'], 'title' => (string) $row['title'], 'existed' => true];
    }

    $db->query('INSERT INTO book_series (publisher_id) VALUES (NULL)');
    $seriesId = (int) $db->insert_id;
    $base = books_v2_slugify($title);
    if ($base === '') {
        $base = 'series-' . $seriesId;
    }
    $slug = $base;
    $n = 2;
    while (true) {
        $z = db_select($db, 'SELECT 1 FROM book_series_i18n WHERE lang=? AND slug=? LIMIT 1', 'ss', $lang, $slug);
        if (!($z && mysqli_fetch_row($z))) {
            break;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    $ins = $db->prepare('INSERT INTO book_series_i18n (series_id, lang, title, slug, description) VALUES (?,?,?,?,NULL)');
    $ins->bind_param('isss', $seriesId, $lang, $title, $slug);
    $ins->execute();
    $ins->close();

    return ['id' => $seriesId, 'title' => $title, 'existed' => false];
}

/**
 * @return array{id:int,error:string}
 */
function books_v2_admin_save_edition(mysqli $db, int $id, int $workId): array
{
    if ($workId <= 0) {
        return ['id' => $id, 'error' => 'Нет произведения'];
    }
    $kinds = ['original', 'reprint', 'revised', 'translation', 'facsimile', 'electronic'];
    $kind = (string) ($_POST['edition_kind'] ?? 'original');
    if (!in_array($kind, $kinds, true)) {
        $kind = 'original';
    }
    $langId = max(1, (int) ($_POST['language_id'] ?? 1));
    $year = (int) ($_POST['publish_year'] ?? 0);
    $pages = (int) ($_POST['pages'] ?? 0);
    $circ = (int) ($_POST['circulation'] ?? 0);
    $city = (int) ($_POST['city_id'] ?? 0);
    $contentId = (int) ($_POST['content_edition_id'] ?? 0);
    $basedId = (int) ($_POST['based_on_edition_id'] ?? 0);
    $active = !empty($_POST['is_active']) ? 1 : 0;

    $map = [];
    $z = db_select($db, 'SELECT id, content_edition_id FROM book_editions WHERE work_id=' . (int) $workId);
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $map[(int) $row['id']] = ['content_edition_id' => $row['content_edition_id']];
    }
    if ($contentId > 0 && !books_v2_content_edition_depth_ok($contentId, $map)) {
        return ['id' => $id, 'error' => 'content_edition_id должен указывать на издание со своими главами'];
    }
    if ($id > 0 && $contentId === $id) {
        return ['id' => $id, 'error' => 'Издание не может брать главы само у себя'];
    }

    $yearSql = $year > 0 ? (string) $year : 'NULL';
    $signedSql = books_v2_admin_date_sql($db, 'signed_for_print');
    $pagesSql = $pages > 0 ? (string) $pages : 'NULL';
    $circSql = $circ > 0 ? (string) $circ : 'NULL';
    $citySql = $city > 0 ? (string) $city : 'NULL';
    $contentSql = $contentId > 0 ? (string) $contentId : 'NULL';
    $basedSql = $basedId > 0 ? (string) $basedId : 'NULL';
    $kindSql = "'" . $db->real_escape_string($kind) . "'";

    if ($id <= 0) {
        $db->query(
            'INSERT INTO book_editions (work_id, edition_kind, based_on_edition_id, content_edition_id, language_id, publish_year, signed_for_print, city_id, pages, circulation, is_active, sort_order)
             VALUES (' . $workId . ',' . $kindSql . ',' . $basedSql . ',' . $contentSql . ',' . $langId . ','
            . $yearSql . ',' . $signedSql . ',' . $citySql . ',' . $pagesSql . ',' . $circSql . ',' . $active . ',0)'
        );
        $id = (int) $db->insert_id;
    } else {
        $db->query(
            'UPDATE book_editions SET edition_kind=' . $kindSql
            . ', based_on_edition_id=' . $basedSql
            . ', content_edition_id=' . $contentSql
            . ', language_id=' . $langId
            . ', publish_year=' . $yearSql
            . ', signed_for_print=' . $signedSql
            . ', city_id=' . $citySql
            . ', pages=' . $pagesSql
            . ', circulation=' . $circSql
            . ', is_active=' . $active
            . ' WHERE id=' . $id . ' AND work_id=' . $workId . ' LIMIT 1'
        );
    }

    $stmtLang = $db->prepare('SELECT code FROM languages WHERE id=? LIMIT 1');
    $stmtLang->bind_param('i', $langId);
    $stmtLang->execute();
    $langRow = $stmtLang->get_result()->fetch_assoc();
    $stmtLang->close();
    $code = (string) ($langRow['code'] ?? 'ru');
    $statement = plain_text_normalize_for_storage(trim((string) ($_POST['edition_statement'] ?? '')));
    $note = trim((string) ($_POST['note'] ?? ''));
    $slug = books_v2_slugify(trim((string) ($_POST['edition_slug'] ?? '')));
    if ($slug === '') {
        $slug = 'edition-' . $id;
    }
    $noteParam = $note !== '' ? $note : null;
    $ei = $db->prepare(
        'INSERT INTO book_edition_i18n (edition_id, lang, slug, title_override, edition_statement, note, meta_description)
         VALUES (?,?,?,\'\',?,?,\'\')
         ON DUPLICATE KEY UPDATE slug=VALUES(slug), edition_statement=VALUES(edition_statement), note=VALUES(note)'
    );
    $ei->bind_param('issss', $id, $code, $slug, $statement, $noteParam);
    $ei->execute();
    $ei->close();

    books_v2_admin_save_edition_links($db, $id);
    books_v2_admin_sync_work_year($db, $workId);

    return ['id' => $id, 'error' => ''];
}

/** @return array<string,string> */
function books_v2_admin_chapter_types(): array
{
    return [
        'part' => 'часть',
        'chapter' => 'глава',
        'section' => 'раздел',
        'preface' => 'предисловие',
        'appendix' => 'приложение',
        'afterword' => 'послесловие',
    ];
}

/**
 * @return array{id:int,error:string}
 */
function books_v2_admin_save_chapter(mysqli $db, int $id, int $editionId): array
{
    if ($editionId <= 0) {
        return ['id' => $id, 'error' => 'Нет издания'];
    }
    $type = (string) ($_POST['chapter_type'] ?? 'chapter');
    if (!isset(books_v2_admin_chapter_types()[$type])) {
        $type = 'chapter';
    }
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $pageStart = max(0, (int) ($_POST['page_start'] ?? 0));
    $pageEnd = max(0, (int) ($_POST['page_end'] ?? 0));
    if ($pageStart > 0 && $pageEnd > 0 && $pageEnd < $pageStart) {
        return ['id' => $id, 'error' => 'Страница «до» меньше страницы «от»'];
    }
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $title = plain_text_normalize_for_storage(trim((string) ($_POST['title'] ?? '')));
    if ($title === '') {
        return ['id' => $id, 'error' => 'Название главы обязательно'];
    }
    $body = (string) ($_POST['body'] ?? '');

    $startSql = $pageStart > 0 ? (string) $pageStart : 'NULL';
    $endSql = $pageEnd > 0 ? (string) $pageEnd : 'NULL';
    if ($id <= 0) {
        $stmt = $db->prepare(
            'INSERT INTO book_chapters (edition_id, chapter_type, sort_order, page_start, page_end, is_active) VALUES (?,?,?,' . $startSql . ',' . $endSql . ',?)'
        );
        $stmt->bind_param('isii', $editionId, $type, $sort, $active);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $stmt = $db->prepare(
            'UPDATE book_chapters SET chapter_type=?, sort_order=?, page_start=' . $startSql . ', page_end=' . $endSql . ', is_active=? WHERE id=? AND edition_id=? LIMIT 1'
        );
        $stmt->bind_param('siiii', $type, $sort, $active, $id, $editionId);
        $stmt->execute();
        $stmt->close();
    }

    $langId = 1;
    $ls = $db->prepare('SELECT language_id FROM book_editions WHERE id=? LIMIT 1');
    $ls->bind_param('i', $editionId);
    $ls->execute();
    $er = $ls->get_result()->fetch_assoc();
    $ls->close();
    if ($er) {
        $langId = (int) $er['language_id'];
    }
    $cs = $db->prepare('SELECT code FROM languages WHERE id=? LIMIT 1');
    $cs->bind_param('i', $langId);
    $cs->execute();
    $cr = $cs->get_result()->fetch_assoc();
    $cs->close();
    $code = (string) ($cr['code'] ?? 'ru');
    $slug = books_v2_slugify(trim((string) ($_POST['slug'] ?? '')) !== '' ? (string) $_POST['slug'] : $title);
    if ($slug === '') {
        $slug = 'ch-' . $id;
    }
    $ci = $db->prepare(
        'INSERT INTO book_chapter_i18n (chapter_id, lang, title, slug, meta_description)
         VALUES (?,?,?,?,\'\')
         ON DUPLICATE KEY UPDATE title=VALUES(title), slug=VALUES(slug)'
    );
    $ci->bind_param('isss', $id, $code, $title, $slug);
    $ci->execute();
    $ci->close();

    $hash = sha1($body);
    $bs = $db->prepare(
        'INSERT INTO book_chapter_bodies (chapter_id, format, body, source_hash) VALUES (?,\'html\',?,?)
         ON DUPLICATE KEY UPDATE body=VALUES(body), source_hash=VALUES(source_hash)'
    );
    $bs->bind_param('iss', $id, $body, $hash);
    $bs->execute();
    $bs->close();

    return ['id' => $id, 'error' => ''];
}
