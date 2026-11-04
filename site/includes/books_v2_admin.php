<?php

declare(strict_types=1);

require_once __DIR__ . '/books_v2.php';

/**
 * Fill EN and ES book fields from the Russian tab via Google Translate.
 * Slug is built from the translated title.
 *
 * @param array{title?:string,subtitle?:string,imprint?:string,annotation?:string,meta_description?:string} $source
 * @return array<string,array{title:string,subtitle:string,imprint:string,slug:string,annotation:string,meta_description:string}>
 */
function books_v2_admin_translate_from_ru(mysqli $db, int $workId, array $source): array
{
    require_once __DIR__ . '/admin_translate.php';

    $fields = [
        'title' => 255,
        'subtitle' => 255,
        'imprint' => 0,
        'annotation' => 0,
        'meta_description' => 255,
    ];
    $clean = [];
    foreach ($fields as $field => $_max) {
        $clean[$field] = trim((string) ($source[$field] ?? ''));
    }
    if (
        $clean['title'] === ''
        && $clean['subtitle'] === ''
        && $clean['imprint'] === ''
        && $clean['annotation'] === ''
        && $clean['meta_description'] === ''
    ) {
        throw new RuntimeException('Нечего переводить: заполните русскую вкладку');
    }

    $out = [];
    foreach (['en', 'es'] as $lang) {
        $row = [];
        foreach ($fields as $field => $max) {
            $text = $clean[$field];
            if ($text === '') {
                $row[$field] = '';
                continue;
            }
            $translated = $field === 'imprint'
                ? trim(admin_translate_markdown($text, $lang))
                : trim(admin_translate_text($text, $lang));
            if ($max > 0 && mb_strlen($translated) > $max) {
                $translated = rtrim(mb_substr($translated, 0, $max));
            }
            $row[$field] = $translated;
        }
        $slugSource = $row['title'] !== '' ? $row['title'] : $row['subtitle'];
        $row['slug'] = $slugSource !== '' ? books_v2_admin_unique_work_slug($db, $lang, $slugSource, $workId) : '';
        $out[$lang] = $row;
    }

    return $out;
}

/**
 * Keep HTML tags, translate the text between them.
 */
function books_v2_admin_translate_html(string $html, string $tl): string
{
    require_once __DIR__ . '/admin_translate.php';
    if ($html === '' || !admin_translate_has_cyrillic($html)) {
        return $html;
    }
    $store = [];
    $i = 0;
    $stashed = preg_replace_callback(
        '/<[^>]*>/',
        static function (array $m) use (&$store, &$i): string {
            $key = '⟦T' . ($i++) . '⟧';
            $store[$key] = $m[0];

            return $key;
        },
        $html
    );
    $translated = admin_translate_text(is_string($stashed) ? $stashed : $html, $tl);
    foreach ($store as $key => $tag) {
        $translated = str_replace($key, $tag, $translated);
    }

    return $translated;
}

/**
 * Fill EN and ES chapter title and body from the Russian tab.
 * Slug is built from the translated title. Format is copied.
 *
 * @param array{title?:string,body?:string,format?:string} $source
 * @return array<string,array{title:string,slug:string,body:string,format:string}>
 */
function books_v2_admin_translate_chapter_from_ru(array $source): array
{
    require_once __DIR__ . '/admin_translate.php';

    $title = trim((string) ($source['title'] ?? ''));
    $body = trim((string) ($source['body'] ?? ''));
    $format = (string) ($source['format'] ?? 'html');
    if (!isset(books_v2_admin_body_formats()[$format])) {
        $format = 'html';
    }
    if ($title === '' && $body === '') {
        throw new RuntimeException('Нечего переводить: заполните русскую вкладку');
    }

    $out = [];
    foreach (['en', 'es'] as $lang) {
        $translatedTitle = $title === '' ? '' : trim(admin_translate_text($title, $lang));
        if (mb_strlen($translatedTitle) > 512) {
            $translatedTitle = rtrim(mb_substr($translatedTitle, 0, 512));
        }
        if ($body === '') {
            $translatedBody = '';
        } elseif ($format === 'markdown') {
            $translatedBody = admin_translate_markdown($body, $lang);
        } elseif ($format === 'html') {
            $translatedBody = books_v2_admin_translate_html($body, $lang);
        } else {
            $translatedBody = admin_translate_text($body, $lang);
        }
        $slug = $translatedTitle !== '' ? books_v2_slugify($translatedTitle) : '';
        $out[$lang] = [
            'title' => $translatedTitle,
            'slug' => $slug,
            'body' => $translatedBody,
            'format' => $format,
        ];
    }

    return $out;
}

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
        $by[$code] = ['title' => '', 'subtitle' => '', 'imprint' => '', 'slug' => '', 'annotation' => '', 'meta_description' => ''];
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
            'imprint' => (string) ($row['imprint'] ?? ''),
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
            'imprint' => (string) ($row['imprint'] ?? ''),
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
    $wasCreate = $id <= 0;

    if ($id <= 0) {
        $db->query(
            'INSERT INTO book_works (original_language_id, is_active) VALUES ('
            . (int) $langId . ',1)'
        );
        $id = (int) $db->insert_id;
    } else {
        $db->query(
            'UPDATE book_works SET original_language_id=' . (int) $langId
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
            'INSERT INTO book_work_i18n (work_id, lang, title, subtitle, imprint, slug, annotation, meta_description)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), imprint=VALUES(imprint),
               slug=VALUES(slug), annotation=VALUES(annotation), meta_description=VALUES(meta_description)'
        );
        $stmt->bind_param(
            'isssssss',
            $id,
            $code,
            $row['title'],
            $row['subtitle'],
            $row['imprint'],
            $slug,
            $row['annotation'],
            $row['meta_description']
        );
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
    $fileError = books_v2_admin_save_files($db, $editionId);
    $saveError = trim($coverError . ($coverError !== '' && $fileError !== '' ? ' ' : '') . $fileError);

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

    return ['id' => $id, 'error' => $saveError];
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
        $added = books_v2_admin_add_cover($db, $editionId, $origName, $tmp);
        if ($added['error'] !== '') {
            $errors[] = $added['error'];
        }
    }

    return $errors === [] ? '' : ('Обложки: ' . implode('; ', $errors));
}

/**
 * Store one cover image on the edition.
 *
 * @return array{error:string,id:int,preview_url:string,original_url:string,sort_order:int}
 */
function books_v2_admin_add_cover(mysqli $db, int $editionId, string $origName, string $tmpPath): array
{
    $fail = static function (string $message): array {
        return ['error' => $message, 'id' => 0, 'preview_url' => '', 'original_url' => '', 'sort_order' => 0];
    };
    if ($editionId <= 0 || !is_file($tmpPath)) {
        return $fail($origName . ': не удалось сохранить файл');
    }
    require_once __DIR__ . '/letters_admin.php';
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true, 'image/gif' => true];
    if (!isset($allowed[$mime])) {
        return $fail($origName . ': недопустимый тип');
    }
    $nextSort = 1;
    $rowMax = db_select($db, 'SELECT COALESCE(MAX(sort_order), 0) AS mx FROM book_edition_images WHERE edition_id=?', 'i', $editionId);
    $maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
    if ($maxRow) {
        $nextSort = (int) $maxRow['mx'] + 1;
    }
    $blank = '';
    $ins = $db->prepare(
        'INSERT INTO book_edition_images (edition_id, image_type, path, sort_order) VALUES (?,\'cover_front\',?,?)'
    );
    $ins->bind_param('isi', $editionId, $blank, $nextSort);
    $ins->execute();
    $imageId = (int) $ins->insert_id;
    $ins->close();
    if ($imageId <= 0) {
        return $fail($origName . ': не удалось создать запись');
    }
    $leaf = 'book-' . $imageId;
    $originalPath = zx_storage_path('pictures', $leaf . '.webp');
    $saved = letters_save_original_webp($tmpPath, $originalPath, null);
    if (!$saved['ok']) {
        db_exec($db, 'DELETE FROM book_edition_images WHERE id=? LIMIT 1', 'i', $imageId);

        return $fail($origName . ': не удалось сохранить файл');
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
    $previewError = '';
    if (!letters_make_jpeg_preview($originalPath, zx_storage_path('pictures', 'thumbs/' . $leaf . '.jpg'), 480, 85)) {
        $previewError = $origName . ': файл сохранён, но превью не создалось';
    }
    $urls = books_v2_admin_cover_urls([
        'path' => $path,
        'id' => $imageId,
    ]);

    return [
        'error' => $previewError,
        'id' => $imageId,
        'preview_url' => $urls['preview_url'],
        'original_url' => $urls['original_url'],
        'sort_order' => $nextSort,
    ];
}

/** @return array<string,int> */
function books_v2_admin_book_file_types(): array
{
    return [
        'pdf' => 1,
        'djvu' => 2,
        'html' => 3,
        'htm' => 3,
        'txt' => 4,
        'jpg' => 5,
        'jpeg' => 5,
        'png' => 5,
        'doc' => 6,
        'docx' => 6,
        'rtf' => 6,
        'odt' => 6,
        'zip' => 0,
        'rar' => 0,
        'epub' => 0,
        'fb2' => 0,
    ];
}

function books_v2_admin_book_file_label(int $type, string $ext): string
{
    $map = [1 => 'PDF', 2 => 'DJVU', 3 => 'HTML', 4 => 'TXT', 5 => 'JPG', 6 => 'Word'];
    if (isset($map[$type])) {
        return $map[$type];
    }

    return $ext !== '' ? strtoupper($ext) : 'Файл';
}

/**
 * @return list<array<string,mixed>>
 */
function books_v2_admin_files(mysqli $db, int $editionId): array
{
    if ($editionId <= 0) {
        return [];
    }
    $files = [];
    $z = db_select(
        $db,
        'SELECT id, file_name, file_type, file_size, sort_order
         FROM book_edition_files WHERE edition_id=? ORDER BY sort_order, id',
        'i',
        $editionId
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $name = (string) $row['file_name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $row['type_label'] = books_v2_admin_book_file_label((int) $row['file_type'], $ext);
        $row['public_url'] = '/books_files/' . rawurlencode($name);
        $files[] = $row;
    }

    return $files;
}

function books_v2_admin_book_filename(string $origName): string
{
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $base = pathinfo($origName, PATHINFO_FILENAME);
    $base = strtolower((string) preg_replace('/[^a-z0-9._-]+/i', '_', $base));
    $base = trim($base, '._-');
    if ($base === '') {
        $base = 'file';
    }
    $max = 64 - strlen($ext) - 1;
    if (strlen($base) > $max) {
        $base = rtrim(substr($base, 0, max(1, $max)), '._-');
    }
    $name = $base . '.' . $ext;
    $n = 1;
    while (is_file(zx_storage_path('books_files', $name))) {
        $suffix = '_' . $n;
        $room = 64 - strlen($ext) - 1 - strlen($suffix);
        $stem = rtrim(substr($base, 0, max(1, $room)), '._-');
        $name = $stem . $suffix . '.' . $ext;
        $n++;
        if ($n > 200) {
            break;
        }
    }

    return $name;
}

/**
 * @return array{error:string,id:int,file_name:string,type_label:string,file_size:int,public_url:string}
 */
function books_v2_admin_file_fail(string $message): array
{
    return ['error' => $message, 'id' => 0, 'file_name' => '', 'type_label' => '', 'file_size' => 0, 'public_url' => ''];
}

/**
 * Copy one uploaded document onto the edition.
 *
 * @return array{error:string,id:int,file_name:string,type_label:string,file_size:int,public_url:string}
 */
function books_v2_admin_add_edition_file(mysqli $db, int $editionId, string $origName, string $tmpPath): array
{
    $types = books_v2_admin_book_file_types();
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!isset($types[$ext]) || !is_file($tmpPath)) {
        return books_v2_admin_file_fail($origName . ': недопустимый тип');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (in_array($mime, ['application/x-php', 'text/x-php', 'application/x-httpd-php'], true)) {
        return books_v2_admin_file_fail($origName . ': недопустимый тип');
    }

    $filename = books_v2_admin_book_filename($origName);
    $dest = zx_storage_path('books_files', $filename);
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return books_v2_admin_file_fail($origName . ': не удалось сохранить файл');
    }
    if (!@copy($tmpPath, $dest)) {
        return books_v2_admin_file_fail($origName . ': не удалось сохранить файл');
    }

    $type = $types[$ext];
    $sizeKb = (int) max(1, (int) ceil(filesize($dest) / 1000));
    $uploaded = time();
    $nextSort = 0;
    $rowMax = db_select($db, 'SELECT COALESCE(MAX(sort_order), 0) AS mx FROM book_edition_files WHERE edition_id=?', 'i', $editionId);
    $maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
    if ($maxRow) {
        $nextSort = (int) $maxRow['mx'] + 1;
    }
    $author = '';
    $ins = $db->prepare(
        'INSERT INTO book_edition_files
         (edition_id, file_name, file_type, file_size, downloads, author, upload_date, sort_order)
         VALUES (?,?,?,?,0,?,?,?)'
    );
    $ins->bind_param('isiisii', $editionId, $filename, $type, $sizeKb, $author, $uploaded, $nextSort);
    $ins->execute();
    $fileId = (int) $ins->insert_id;
    $ins->close();
    if ($fileId <= 0) {
        @unlink($dest);
        return books_v2_admin_file_fail($origName . ': не удалось создать запись');
    }

    $work = db_select(
        $db,
        'SELECT w.legacy_book_id FROM book_editions e INNER JOIN book_works w ON w.id=e.work_id WHERE e.id=? LIMIT 1',
        'i',
        $editionId
    );
    $workRow = $work ? mysqli_fetch_assoc($work) : null;
    $legacyBookId = (int) ($workRow['legacy_book_id'] ?? 0);
    if ($legacyBookId > 0) {
        $comment = '';
        $legacy = $db->prepare(
            'INSERT INTO books_files (book_id, file_name, file_type, author, downloads, file_size, comment, upload_date)
             VALUES (?,?,?,?,0,?,?,?)'
        );
        $legacy->bind_param('isisisi', $legacyBookId, $filename, $type, $author, $sizeKb, $comment, $uploaded);
        $legacy->execute();
        $legacyId = (int) $legacy->insert_id;
        $legacy->close();
        if ($legacyId > 0) {
            db_exec($db, 'UPDATE book_edition_files SET legacy_file_id=? WHERE id=? LIMIT 1', 'ii', $legacyId, $fileId);
        }
    }

    return [
        'error' => '',
        'id' => $fileId,
        'file_name' => $filename,
        'type_label' => books_v2_admin_book_file_label($type, $ext),
        'file_size' => $sizeKb,
        'public_url' => '/books_files/' . rawurlencode($filename),
    ];
}

function books_v2_admin_file_name_used(mysqli $db, string $name): bool
{
    $z = db_select($db, 'SELECT id FROM book_edition_files WHERE file_name=? LIMIT 1', 's', $name);
    if ($z && mysqli_fetch_assoc($z)) {
        return true;
    }
    $z = db_select($db, 'SELECT id FROM books_files WHERE file_name=? LIMIT 1', 's', $name);

    return (bool) ($z && mysqli_fetch_assoc($z));
}

function books_v2_admin_delete_file(mysqli $db, int $workId, int $fileId): bool
{
    if ($workId <= 0 || $fileId <= 0) {
        return false;
    }
    $z = db_select(
        $db,
        'SELECT f.id, f.file_name, f.legacy_file_id
         FROM book_edition_files f
         INNER JOIN book_editions e ON e.id=f.edition_id
         WHERE f.id=? AND e.work_id=? LIMIT 1',
        'ii',
        $fileId,
        $workId
    );
    $row = $z ? mysqli_fetch_assoc($z) : null;
    if (!$row) {
        return false;
    }
    $name = (string) $row['file_name'];
    db_exec($db, 'DELETE FROM book_edition_files WHERE id=? LIMIT 1', 'i', $fileId);
    $legacyId = (int) ($row['legacy_file_id'] ?? 0);
    if ($legacyId > 0) {
        db_exec($db, 'DELETE FROM books_files WHERE id=? LIMIT 1', 'i', $legacyId);
    }
    if ($name !== '' && !books_v2_admin_file_name_used($db, $name)) {
        @unlink(zx_storage_path('books_files', $name));
    }

    return true;
}

/** Store documents uploaded with the book form. Returns an error string. */
function books_v2_admin_save_files(mysqli $db, int $editionId): string
{
    if ($editionId <= 0) {
        return '';
    }
    $upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
    $names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
    if ($names === []) {
        return '';
    }
    $errors = [];
    $tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];
    foreach ($names as $i => $origName) {
        $origName = (string) $origName;
        $tmp = (string) ($tmps[$i] ?? '');
        $errCode = (int) ($upl['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errCode !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $origName === '') {
            if ($origName !== '' && $errCode !== UPLOAD_ERR_NO_FILE) {
                $errors[] = $origName . ': не удалось загрузить';
            }
            continue;
        }
        $added = books_v2_admin_add_edition_file($db, $editionId, $origName, $tmp);
        if ($added['error'] !== '') {
            $errors[] = $added['error'];
        }
    }

    return $errors === [] ? '' : ('Файлы: ' . implode('; ', $errors));
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
    books_v2_admin_save_printings($db, $editionId);
    books_v2_admin_sync_work_year($db, $workId);

    return $editionId;
}

/**
 * Extra print runs of the same edition (same content/page). Not separate public pages.
 *
 * @return list<array{printing_no:int,year:?int,circulation:?int}>
 */
function books_v2_admin_printings(mysqli $db, int $editionId): array
{
    if ($editionId <= 0) {
        return [];
    }
    $out = [];
    $z = db_select(
        $db,
        'SELECT printing_no, year, circulation FROM book_edition_printings
          WHERE edition_id=? ORDER BY printing_no ASC, id ASC',
        'i',
        $editionId
    );
    while ($z && ($row = mysqli_fetch_assoc($z))) {
        $out[] = [
            'printing_no' => (int) ($row['printing_no'] ?? 0),
            'year' => isset($row['year']) && $row['year'] !== null && $row['year'] !== '' ? (int) $row['year'] : null,
            'circulation' => isset($row['circulation']) && $row['circulation'] !== null && $row['circulation'] !== ''
                ? (int) $row['circulation']
                : null,
        ];
    }

    return $out;
}

function books_v2_admin_save_printings(mysqli $db, int $editionId): void
{
    if ($editionId <= 0) {
        return;
    }
    $db->query('DELETE FROM book_edition_printings WHERE edition_id=' . (int) $editionId);
    $years = $_POST['printing_year'] ?? [];
    $circs = $_POST['printing_circulation'] ?? [];
    if (!is_array($years)) {
        $years = [];
    }
    if (!is_array($circs)) {
        $circs = [];
    }
    $n = 1;
    $count = max(count($years), count($circs));
    for ($i = 0; $i < $count; $i++) {
        $year = (int) ($years[$i] ?? 0);
        $circ = (int) ($circs[$i] ?? 0);
        if ($year <= 0 && $circ <= 0) {
            continue;
        }
        $yearSql = $year > 0 ? (string) $year : 'NULL';
        $circSql = $circ > 0 ? (string) $circ : 'NULL';
        $db->query(
            'INSERT INTO book_edition_printings (edition_id, printing_no, year, circulation, note) VALUES ('
            . (int) $editionId . ',' . $n . ',' . $yearSql . ',' . $circSql . ",'')"
        );
        $n++;
    }
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
            . $yearSql . ',' . $signedSql . ',' . $citySql . ',' . $pagesSql . ',' . $circSql . ',1,0)'
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
    $coverError = books_v2_admin_save_covers($db, $id);
    $fileError = books_v2_admin_save_files($db, $id);
    books_v2_admin_sync_work_year($db, $workId);
    $saveError = trim($coverError . ($coverError !== '' && $fileError !== '' ? ' ' : '') . $fileError);

    return ['id' => $id, 'error' => $saveError];
}

/** @return array<string,string> */
function books_v2_admin_body_formats(): array
{
    return [
        'html' => 'HTML',
        'markdown' => 'MD',
        'txt' => 'TXT',
    ];
}

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
    $formats = books_v2_admin_body_formats();
    $codes = [];
    foreach (books_v2_admin_languages($db) as $lang) {
        $codes[] = (string) $lang['code'];
    }
    $posted = $_POST['i18n'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    $rows = [];
    $hasTitle = false;
    foreach ($codes as $code) {
        $raw = $posted[$code] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $title = plain_text_normalize_for_storage(trim((string) ($raw['title'] ?? '')));
        $body = (string) ($raw['body'] ?? '');
        $format = (string) ($raw['body_format'] ?? 'html');
        if (!isset($formats[$format])) {
            $format = 'html';
        }
        if ($title !== '') {
            $hasTitle = true;
        }
        $rows[$code] = [
            'title' => $title,
            'slug' => trim((string) ($raw['slug'] ?? '')),
            'body' => $body,
            'format' => $format,
        ];
    }
    if (!$hasTitle) {
        return ['id' => $id, 'error' => 'Название главы обязательно'];
    }

    $startSql = $pageStart > 0 ? (string) $pageStart : 'NULL';
    $endSql = $pageEnd > 0 ? (string) $pageEnd : 'NULL';
    if ($id <= 0) {
        $stmt = $db->prepare(
            'INSERT INTO book_chapters (edition_id, chapter_type, sort_order, page_start, page_end, is_active) VALUES (?,?,?,' . $startSql . ',' . $endSql . ',1)'
        );
        $stmt->bind_param('isi', $editionId, $type, $sort);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $stmt = $db->prepare(
            'UPDATE book_chapters SET chapter_type=?, sort_order=?, page_start=' . $startSql . ', page_end=' . $endSql . ' WHERE id=? AND edition_id=? LIMIT 1'
        );
        $stmt->bind_param('siii', $type, $sort, $id, $editionId);
        $stmt->execute();
        $stmt->close();
    }

    foreach ($codes as $code) {
        $row = $rows[$code];
        if ($row['title'] === '' && trim($row['body']) === '') {
            $del = $db->prepare('DELETE FROM book_chapter_i18n WHERE chapter_id=? AND lang=? LIMIT 1');
            $del->bind_param('is', $id, $code);
            $del->execute();
            $del->close();
            $delBody = $db->prepare('DELETE FROM book_chapter_bodies WHERE chapter_id=? AND lang=? LIMIT 1');
            $delBody->bind_param('is', $id, $code);
            $delBody->execute();
            $delBody->close();
            continue;
        }
        $slug = books_v2_slugify($row['slug'] !== '' ? $row['slug'] : $row['title']);
        if ($slug === '') {
            $slug = 'ch-' . $id;
        }
        $ci = $db->prepare(
            'INSERT INTO book_chapter_i18n (chapter_id, lang, title, slug, meta_description)
             VALUES (?,?,?,?,\'\')
             ON DUPLICATE KEY UPDATE title=VALUES(title), slug=VALUES(slug)'
        );
        $ci->bind_param('isss', $id, $code, $row['title'], $slug);
        $ci->execute();
        $ci->close();

        $hash = sha1($row['format'] . "\n" . $row['body']);
        $bs = $db->prepare(
            'INSERT INTO book_chapter_bodies (chapter_id, lang, format, body, source_hash) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE format=VALUES(format), body=VALUES(body), source_hash=VALUES(source_hash)'
        );
        $bs->bind_param('issss', $id, $code, $row['format'], $row['body'], $hash);
        $bs->execute();
        $bs->close();
    }

    return ['id' => $id, 'error' => ''];
}
