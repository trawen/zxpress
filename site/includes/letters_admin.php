<?php

declare(strict_types=1);

require_once __DIR__ . '/letters_publish.php';
require_once __DIR__ . '/letters_slugs.php';
require_once __DIR__ . '/letters_images.php';
require_once __DIR__ . '/authors_slugs.php';
require_once __DIR__ . '/storage_paths.php';

const LETTERS_ADMIN_ENTITY_TYPE = 1;

function letters_resolve_author_id(mysqli $db, int $selectedId, string $newNick): int
{
    $newNick = plain_text_normalize_for_storage(trim($newNick));
    if ($newNick !== '') {
        $stmt = $db->prepare('SELECT id FROM authors WHERE LOWER(nickname)=LOWER(?) LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $newNick);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && (int) $row['id'] > 0) {
                return (int) $row['id'];
            }
        }

        $slugs = authors_resolve_slugs($db, '', '', $newNick, '', '', 0);
        $ok = db_exec(
            $db,
            'INSERT INTO authors (nickname, name_ru, name_en, group_name, slug_ru, slug_en, country_id, city_id, user_id, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)',
            'ssssssiiii',
            $newNick,
            null,
            null,
            null,
            $slugs['slug_ru'],
            $slugs['slug_en'],
            null,
            null,
            null,
            1
        );
        if (!$ok) {
            throw new RuntimeException('Не удалось создать автора «' . $newNick . '»: ' . $db->error);
        }
        $id = (int) mysqli_insert_id($db);
        if ($id <= 0) {
            throw new RuntimeException('Не удалось создать автора «' . $newNick . '»');
        }
        activity_log($db, [
            'verb' => 'created',
            'object_type' => 'author',
            'object_id' => $id,
            'action' => 'author.created',
            'event_scope' => ACTIVITY_SCOPE_METADATA,
            'is_public' => 0,
            'title_ru' => $newNick,
            'title_en' => $newNick,
            'after' => ['is_active' => 1, 'source' => 'admin_letters'],
        ]);

        return $id;
    }

    return $selectedId > 0 ? $selectedId : 0;
}

/**
 * @return array{id:int,nickname:string,existed:bool}
 */
function letters_admin_create_author(
    mysqli $db,
    string $nickname,
    string $nameRu,
    string $nameEn,
    string $groupName
): array {
    $nickname = plain_text_normalize_for_storage(trim($nickname));
    $nameRu = plain_text_normalize_for_storage(trim($nameRu));
    $nameEn = plain_text_normalize_for_storage(trim($nameEn));
    $groupName = plain_text_normalize_for_storage(trim($groupName));
    if ($nickname === '') {
        throw new RuntimeException('Ник обязателен');
    }
    if (mb_strlen($nickname) > 100) {
        throw new RuntimeException('Ник длиннее 100 символов');
    }

    $stmt = $db->prepare('SELECT id, nickname FROM authors WHERE LOWER(nickname)=LOWER(?) LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Не удалось проверить автора: ' . $db->error);
    }
    $stmt->bind_param('s', $nickname);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing && (int) $existing['id'] > 0) {
        return [
            'id' => (int) $existing['id'],
            'nickname' => plain_text_normalize_for_storage((string) $existing['nickname']),
            'existed' => true,
        ];
    }

    $slugs = authors_resolve_slugs($db, '', '', $nickname, $nameRu, $nameEn, 0);
    $ok = db_exec(
        $db,
        'INSERT INTO authors (nickname, name_ru, name_en, group_name, slug_ru, slug_en, country_id, city_id, user_id, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)',
        'ssssssiiii',
        $nickname,
        ($nameRu !== '' ? $nameRu : null),
        ($nameEn !== '' ? $nameEn : null),
        ($groupName !== '' ? $groupName : null),
        $slugs['slug_ru'],
        $slugs['slug_en'],
        null,
        null,
        null,
        1
    );
    if (!$ok) {
        throw new RuntimeException('Не удалось создать автора «' . $nickname . '»: ' . $db->error);
    }
    $id = (int) mysqli_insert_id($db);
    if ($id <= 0) {
        throw new RuntimeException('Не удалось создать автора «' . $nickname . '»');
    }
    activity_log($db, [
        'verb' => 'created',
        'object_type' => 'author',
        'object_id' => $id,
        'action' => 'author.created',
        'event_scope' => ACTIVITY_SCOPE_METADATA,
        'is_public' => 0,
        'title_ru' => $nickname,
        'title_en' => $nickname,
        'after' => ['is_active' => 1, 'source' => 'admin2'],
    ]);

    return ['id' => $id, 'nickname' => $nickname, 'existed' => false];
}

function zx_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function zx_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function letters_parse_date(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    // Accept both legacy dd.mm.yyyy and HTML date input yyyy-mm-dd
    $dt = DateTime::createFromFormat('d.m.Y', $raw) ?: DateTime::createFromFormat('Y-m-d', $raw);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d');
}

const LETTERS_ORIGINAL_WEBP_QUALITY = 85;

/**
 * @return resource|\GdImage|false
 */
function letters_load_image_resource(string $srcPath, string $mime)
{
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($srcPath);
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($srcPath);
    }
    if ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp')) {
            error_log('[FIX] admin_letters: imagecreatefromwebp() not available (GD without WebP)');
            return false;
        }

        return @imagecreatefromwebp($srcPath);
    }
    if ($mime === 'image/gif') {
        if (!function_exists('imagecreatefromgif')) {
            error_log('[FIX] admin_letters: imagecreatefromgif() not available');
            return false;
        }

        return @imagecreatefromgif($srcPath);
    }

    return false;
}

/**
 * Optional crop box in natural image pixels. Invalid/empty → no crop.
 *
 * @return array{x:int,y:int,w:int,h:int}|null
 */
function letters_normalize_crop_box(?float $x, ?float $y, ?float $w, ?float $h, int $imgW, int $imgH): ?array
{
    if ($imgW <= 0 || $imgH <= 0) {
        return null;
    }
    if ($w === null || $h === null || $w < 1 || $h < 1) {
        return null;
    }

    $cx = (int) floor((float) $x);
    $cy = (int) floor((float) $y);
    $cw = (int) round((float) $w);
    $ch = (int) round((float) $h);

    if ($cx < 0) {
        $cw += $cx;
        $cx = 0;
    }
    if ($cy < 0) {
        $ch += $cy;
        $cy = 0;
    }
    if ($cx >= $imgW || $cy >= $imgH) {
        return null;
    }
    if ($cx + $cw > $imgW) {
        $cw = $imgW - $cx;
    }
    if ($cy + $ch > $imgH) {
        $ch = $imgH - $cy;
    }
    if ($cw < 1 || $ch < 1) {
        return null;
    }

    // Treat near-full-frame crop as "no crop" (UI may send full image by mistake).
    if ($cx === 0 && $cy === 0 && $cw === $imgW && $ch === $imgH) {
        return null;
    }

    return ['x' => $cx, 'y' => $cy, 'w' => $cw, 'h' => $ch];
}

/**
 * Save (optionally cropped) original as WebP @ 85%.
 *
 * @return array{ok:bool,width:int,height:int}
 */
function letters_save_original_webp(string $tmpFile, string $dstPath, ?array $crop = null, int $quality = LETTERS_ORIGINAL_WEBP_QUALITY): array
{
    $fail = ['ok' => false, 'width' => 0, 'height' => 0];
    if (!function_exists('imagewebp')) {
        error_log('[FIX] admin_letters: imagewebp() not available');
        return $fail;
    }

    $info = @getimagesize($tmpFile);
    if (!$info) {
        return $fail;
    }
    $srcW = (int) ($info[0] ?? 0);
    $srcH = (int) ($info[1] ?? 0);
    if ($srcW <= 0 || $srcH <= 0) {
        return $fail;
    }

    $mime = (string) ($info['mime'] ?? '');
    $src = letters_load_image_resource($tmpFile, $mime);
    if (!$src) {
        error_log('[FIX] admin_letters: cannot load upload mime=' . $mime);
        return $fail;
    }

    $box = null;
    if (is_array($crop)) {
        $box = letters_normalize_crop_box(
            isset($crop['x']) ? (float) $crop['x'] : null,
            isset($crop['y']) ? (float) $crop['y'] : null,
            isset($crop['w']) ? (float) $crop['w'] : (isset($crop['width']) ? (float) $crop['width'] : null),
            isset($crop['h']) ? (float) $crop['h'] : (isset($crop['height']) ? (float) $crop['height'] : null),
            $srcW,
            $srcH
        );
    }

    $outW = $srcW;
    $outH = $srcH;
    $work = $src;
    if ($box !== null) {
        $outW = $box['w'];
        $outH = $box['h'];
        $cropped = imagecreatetruecolor($outW, $outH);
        if (!$cropped) {
            imagedestroy($src);
            return $fail;
        }
        if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($cropped, 0, 0, $outW, $outH, $transparent);
            }
        }
        imagecopy($cropped, $src, 0, 0, $box['x'], $box['y'], $outW, $outH);
        imagedestroy($src);
        $work = $cropped;
    }

    $dir = dirname($dstPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($work);
        return $fail;
    }

    $ok = @imagewebp($work, $dstPath, max(0, min(100, $quality)));
    imagedestroy($work);
    if (!$ok) {
        error_log('[FIX] admin_letters: imagewebp failed path=' . $dstPath);
        return $fail;
    }

    return ['ok' => true, 'width' => $outW, 'height' => $outH];
}

function letters_make_jpeg_preview(string $tmpFile, string $dstPath, int $maxWidth = 1280, int $quality = 85): bool
{
    if (!function_exists('imagejpeg')) {
        error_log('[FIX] admin_letters: imagejpeg() not available, skip preview');
        return false;
    }

    $info = @getimagesize($tmpFile);
    if (!$info) {
        return false;
    }
    [$w, $h] = $info;
    if ($w <= 0 || $h <= 0) {
        return false;
    }

    $mime = $info['mime'] ?? '';
    $src = letters_load_image_resource($tmpFile, (string) $mime);
    if (!$src) {
        error_log('[FIX] admin_letters: preview unsupported mime=' . $mime);
        return false;
    }

    $outW = $w;
    $outH = $h;
    if ($w > $maxWidth) {
        $outW = $maxWidth;
        $outH = (int) round(($h * $outW) / $w);
    }

    $dst = imagecreatetruecolor($outW, $outH);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);

    $dir = dirname($dstPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($dst);
        imagedestroy($src);
        return false;
    }

    $ok = @imagejpeg($dst, $dstPath, $quality);
    imagedestroy($dst);
    imagedestroy($src);
    return (bool) $ok;
}

/**
 * @return array{x:float,y:float,w:float,h:float}|null
 */
function letters_crop_from_post(int $index): ?array
{
    $xs = $_POST['crop_x'] ?? null;
    $ys = $_POST['crop_y'] ?? null;
    $ws = $_POST['crop_w'] ?? null;
    $hs = $_POST['crop_h'] ?? null;
    if (!is_array($xs) || !is_array($ys) || !is_array($ws) || !is_array($hs)) {
        return null;
    }
    if (!isset($xs[$index], $ys[$index], $ws[$index], $hs[$index])) {
        return null;
    }
    $w = (float) $ws[$index];
    $h = (float) $hs[$index];
    if ($w < 1 || $h < 1) {
        return null;
    }

    return [
        'x' => (float) $xs[$index],
        'y' => (float) $ys[$index],
        'w' => $w,
        'h' => $h,
    ];
}

function letters_images_format_from_ext(string $ext): int
{
    $ext = strtolower($ext);
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return 1;
    }
    if ($ext === 'png') {
        return 2;
    }
    if ($ext === 'webp') {
        return 3;
    }
    if ($ext === 'gif') {
        return 4;
    }
    return 1;
}

function letters_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'файл слишком большой (лимит загрузки)',
        UPLOAD_ERR_PARTIAL => 'файл загружен частично',
        UPLOAD_ERR_NO_TMP_DIR => 'нет временной директории на сервере',
        UPLOAD_ERR_CANT_WRITE => 'нет места во временной директории на сервере',
        UPLOAD_ERR_EXTENSION => 'загрузка остановлена расширением PHP',
        default => 'ошибка загрузки #' . $code,
    };
}

/**
 * @return array{id:int,error:string,redirect:string}
 */
function letters_admin_save(mysqli $db, int $id, string $backUrl): array
{
    $error = '';
    $redirect = '';
    $ENTITY_TYPE_LETTER = LETTERS_ADMIN_ENTITY_TYPE;

    $author_from = 0;
    $author_to = 0;
    try {
        $author_from = letters_resolve_author_id(
            $db,
            zx_post_int('author_from'),
            zx_post_string('author_from_new')
        );
        $author_to = letters_resolve_author_id(
            $db,
            zx_post_int('author_to'),
            zx_post_string('author_to_new')
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $author_from = 0;
        $author_to = 0;
    }
    $title_ru = plain_text_normalize_for_storage(zx_post_string('title_ru'));
    $title_en = plain_text_normalize_for_storage(zx_post_string('title_en'));
    if ($title_en === '') {
        // DB column is NOT NULL; if EN is omitted, mirror RU.
        $title_en = $title_ru;
    }
    $slugs = letters_resolve_slugs(
        $db,
        zx_post_string('slug_ru'),
        zx_post_string('slug_en'),
        $title_ru,
        $title_en,
        $id
    );
    $slug_ru = $slugs['slug_ru'];
    $slug_en = $slugs['slug_en'];
    $summary_ru = trim((string) ($_POST['summary_ru'] ?? ''));
    $summary_en = trim((string) ($_POST['summary_en'] ?? ''));
    $meta_description_ru = plain_text_normalize_for_storage(zx_post_string('meta_description_ru'));
    $meta_description_en = plain_text_normalize_for_storage(zx_post_string('meta_description_en'));
    $body_ru = trim((string) ($_POST['body_ru'] ?? ''));
    $body_en = trim((string) ($_POST['body_en'] ?? ''));
    $date_raw = zx_post_string('date');
    $date_db = letters_parse_date($date_raw);
    $publishStatus = letters_publish_status_from_input((string) ($_POST['publish_status'] ?? 'draft'));

    $prevPublish = [
        'publish_status' => LETTER_STATUS_DRAFT,
        'queued_at' => null,
        'published_at' => null,
        'deleted_at' => null,
    ];
    if ($id > 0) {
        $prevStmt = $db->prepare(
            'SELECT publish_status, queued_at, published_at, deleted_at FROM letters WHERE id=? LIMIT 1'
        );
        if ($prevStmt) {
            $prevStmt->bind_param('i', $id);
            $prevStmt->execute();
            $prevRow = $prevStmt->get_result()->fetch_assoc();
            $prevStmt->close();
            if (is_array($prevRow)) {
                $prevPublish = $prevRow;
            }
        }
    }
    $publishFields = letters_publish_apply_status($publishStatus, $prevPublish);
    $is_active = (int) $publishFields['is_active'];
    $queued_at = $publishFields['queued_at'];
    $published_at = $publishFields['published_at'];
    $deleted_at = $publishFields['deleted_at'];
    $publish_status = (int) $publishFields['publish_status'];

    if ($error !== '') {
        // author create failed above
    } elseif ($author_from <= 0 || $author_to <= 0 || $title_ru === '') {
        $error = 'Заполни: От кого, Кому (выбор или новый ник), Заголовок (RU)';
    } else {
        $save_ok = false;
        try {
            if ($id === 0) {
                $save_ok = db_exec(
                    $db,
                    'INSERT INTO letters (author_from, author_to, title_ru, title_en, slug_ru, slug_en, summary_ru, summary_en, meta_description_ru, meta_description_en, body_ru, body_en, date, is_active, publish_status, queued_at, published_at, deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    'iisssssssssssiisss',
                    $author_from,
                    $author_to,
                    $title_ru,
                    $title_en,
                    $slug_ru,
                    $slug_en,
                    ($summary_ru !== '' ? $summary_ru : null),
                    ($summary_en !== '' ? $summary_en : null),
                    ($meta_description_ru !== '' ? $meta_description_ru : null),
                    ($meta_description_en !== '' ? $meta_description_en : null),
                    ($body_ru !== '' ? $body_ru : null),
                    ($body_en !== '' ? $body_en : null),
                    $date_db,
                    $is_active,
                    $publish_status,
                    $queued_at,
                    $published_at,
                    $deleted_at
                );
                if ($save_ok) {
                    $id = (int) mysqli_insert_id($db);
                }
            } else {
                $save_ok = db_exec(
                    $db,
                    'UPDATE letters SET author_from=?, author_to=?, title_ru=?, title_en=?, slug_ru=?, slug_en=?, summary_ru=?, summary_en=?, meta_description_ru=?, meta_description_en=?, body_ru=?, body_en=?, date=?, is_active=?, publish_status=?, queued_at=?, published_at=?, deleted_at=? WHERE id=? LIMIT 1',
                    'iisssssssssssiisssi',
                    $author_from,
                    $author_to,
                    $title_ru,
                    $title_en,
                    $slug_ru,
                    $slug_en,
                    ($summary_ru !== '' ? $summary_ru : null),
                    ($summary_en !== '' ? $summary_en : null),
                    ($meta_description_ru !== '' ? $meta_description_ru : null),
                    ($meta_description_en !== '' ? $meta_description_en : null),
                    ($body_ru !== '' ? $body_ru : null),
                    ($body_en !== '' ? $body_en : null),
                    $date_db,
                    $is_active,
                    $publish_status,
                    $queued_at,
                    $published_at,
                    $deleted_at,
                    $id
                );
            }
        } catch (mysqli_sql_exception $e) {
            $dbName = '';
            $dbRes = $db->query('SELECT DATABASE()');
            if ($dbRes && ($dbRow = $dbRes->fetch_row())) {
                $dbName = (string) ($dbRow[0] ?? '');
            }
            error_log('[admin_letters] save failed db=' . $dbName . ' err=' . $e->getMessage());
            $error = 'Ошибка сохранения: ' . $e->getMessage();
            $save_ok = false;
        }

        if (!$save_ok && $error === '') {
            $error = 'Ошибка сохранения: ' . $db->error;
        }

        if ($save_ok) {
            $reqIdBefore = (int) ($_GET['id'] ?? 0);
            $letterCreated = ($reqIdBefore === 0);
            activity_log($db, [
                'verb' => ($letterCreated ? 'created' : ($publish_status === LETTER_STATUS_PUBLISHED ? 'published' : 'updated')),
                'object_type' => 'letter',
                'object_id' => $id,
                'action' => $letterCreated ? 'letter.created' : 'letter.updated',
                'event_scope' => ($publish_status === LETTER_STATUS_PUBLISHED || $is_active === 1) ? ACTIVITY_SCOPE_CONTENT : ACTIVITY_SCOPE_METADATA,
                'is_public' => ($publish_status === LETTER_STATUS_PUBLISHED || $is_active === 1) ? 1 : 0,
                'title_ru' => $title_ru,
                'title_en' => $title_en !== '' ? $title_en : $title_ru,
                'url_ru' => '/snailmail.php?id=' . $id,
                'after' => [
                    'publish_status' => $publish_status,
                    'is_active' => $is_active,
                ],
            ]);

        // Update sort order for existing images
        if ($id > 0) {
            $zImg = db_select(
                $db,
                "SELECT id, sort_order FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC",
                "ii",
                $ENTITY_TYPE_LETTER,
                $id
            );
            while ($zImg && ($img = mysqli_fetch_array($zImg))) {
                $imgId = (int) ($img['id'] ?? 0);
                if ($imgId <= 0) {
                    continue;
                }
                $key = 'sort_order_' . $imgId;
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $newSort = (int) ($_POST[$key] ?? 0);
                $oldSort = (int) ($img['sort_order'] ?? 0);
                if ($newSort !== $oldSort) {
                    db_exec($db, "UPDATE images SET sort_order=? WHERE id=? LIMIT 1", "ii", $newSort, $imgId);
                }
            }
        }

        // Delete selected images
        if ($id > 0) {
            $zImg = db_select($db, "SELECT id, format FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", $ENTITY_TYPE_LETTER, $id);
            while ($zImg && ($img = mysqli_fetch_array($zImg))) {
                $imgId = (int) ($img['id'] ?? 0);
                if ($imgId <= 0) {
                    continue;
                }
                if (!empty($_POST['delete_image_' . $imgId])) {
                    letters_admin_delete_image($db, $id, $imgId);
                }
            }
        }

        // Upload multiple scans/files (already cropped client-side if user cropped; saved as WebP originals)
        $upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
        $names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
        $tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];
        $uploadErrors = [];

        // Determine next sort_order
        $nextSort = 0;
        $rowMax = db_select($db, "SELECT COALESCE(MAX(sort_order), 0) AS mx FROM images WHERE entity_type=? AND entity_id=?", "ii", $ENTITY_TYPE_LETTER, $id);
        $maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
        if ($maxRow && isset($maxRow['mx'])) {
            $nextSort = (int) $maxRow['mx'];
        }

        for ($i = 0; $i < count($names); $i++) {
            $tmp = (string) ($tmps[$i] ?? '');
            $origName = (string) ($names[$i] ?? '');
            $errCode = (int) ($upl['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp) || $origName === '') {
                if ($origName !== '' && $errCode !== UPLOAD_ERR_NO_FILE) {
                    $uploadErrors[] = $origName . ': ' . letters_upload_error_message($errCode);
                }
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($allowed[$mime])) {
                error_log('[FIX] admin_letters: rejected upload mime=' . $mime . ' name=' . $origName);
                $uploadErrors[] = $origName . ': недопустимый тип ' . $mime;
                continue;
            }

            $format = 3; // always store original as WebP
            $nextSort++;
            // Client already applies crop into the uploaded file; optional server-side crop coords are a fallback.
            $crop = letters_crop_from_post($i);

            db_exec(
                $db,
                "INSERT INTO images (entity_type, entity_id, format, sort_order, is_active) VALUES (?,?,?,?,1)",
                "iiii",
                $ENTITY_TYPE_LETTER,
                $id,
                $format,
                $nextSort
            );
            $imgId = (int) mysqli_insert_id($db);
            if ($imgId <= 0) {
                $uploadErrors[] = $origName . ': не удалось создать запись в БД';
                continue;
            }

            $originalPath = zx_storage_path('letters', $imgId . '.webp');
            $saved = letters_save_original_webp($tmp, $originalPath, $crop, LETTERS_ORIGINAL_WEBP_QUALITY);
            if (!$saved['ok']) {
                db_exec($db, 'DELETE FROM images WHERE id=? LIMIT 1', 'i', $imgId);
                error_log('[FIX] admin_letters: failed to save webp original id=' . $imgId . ' name=' . $origName);
                $uploadErrors[] = $origName . ': не удалось сохранить WebP';
                continue;
            }

            $outW = (int) $saved['width'];
            $outH = (int) $saved['height'];
            if ($outW > 0 && $outH > 0) {
                db_exec(
                    $db,
                    'UPDATE images SET width=?, height=? WHERE id=? LIMIT 1',
                    'iii',
                    $outW,
                    $outH,
                    $imgId
                );
            }

            if (!letters_make_jpeg_preview($originalPath, zx_storage_path('letters_preview', $imgId . '.jpg'), 1280, 85)) {
                $uploadErrors[] = $origName . ': оригинал сохранён, но превью не создалось';
            }
            if (!letters_make_preview_256($originalPath, $imgId)) {
                $uploadErrors[] = $origName . ': оригинал сохранён, но preview-256 не создалось';
            }
        }

        if ($uploadErrors !== []) {
            $error = 'Письмо сохранено, но с файлами: ' . implode('; ', $uploadErrors);
            // Fall through to re-render form with error (no redirect).
        } else {
            $redirect = $backUrl . '?id=' . $id;
            return ['id' => $id, 'error' => $error, 'redirect' => $redirect];
        }
        }
    }

    return ['id' => $id, 'error' => $error, 'redirect' => $redirect];
}

/**
 * @return list<array<string,mixed>>
 */
function letters_admin_authors(mysqli $db): array
{
    $authors = [];
    $z = db_select($db, 'SELECT id, nickname, name_ru, name_en, is_active FROM authors ORDER BY nickname ASC');
    while ($z && ($t = mysqli_fetch_assoc($z))) {
        $authors[] = $t;
    }

    return $authors;
}

/**
 * @return array{letters:list<array>,groups:list<array>,status:string,author_id:int,author_nick:string,counts:array{all:int,draft:int,queued:int,published:int}}
 */
function letters_admin_list(mysqli $db, string $statusRaw, int $authorFilterId): array
{
    $statusFilter = letters_publish_status_from_input($statusRaw);
    $filterAll = ($statusRaw === '' || $statusRaw === 'all');
    if ($authorFilterId < 0) {
        $authorFilterId = 0;
    }
    $authorNick = '';
    if ($authorFilterId > 0) {
        $stmt = $db->prepare('SELECT nickname FROM authors WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $authorFilterId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $authorNick = plain_text_normalize_for_storage((string) ($row['nickname'] ?? ''));
        if ($authorNick === '') {
            $authorFilterId = 0;
        }
    }

    $countSql = 'SELECT publish_status, COUNT(*) AS n FROM letters';
    if ($authorFilterId > 0) {
        $countSql .= ' WHERE author_from=' . $authorFilterId . ' OR author_to=' . $authorFilterId;
    }
    $countSql .= ' GROUP BY publish_status';
    $counts = ['all' => 0, 'draft' => 0, 'queued' => 0, 'published' => 0];
    $zc = db_select($db, $countSql);
    while ($zc && ($crow = mysqli_fetch_assoc($zc))) {
        $n = (int) ($crow['n'] ?? 0);
        $counts['all'] += $n;
        $pst = (int) ($crow['publish_status'] ?? -1);
        if ($pst === LETTER_STATUS_DRAFT) {
            $counts['draft'] = $n;
        } elseif ($pst === LETTER_STATUS_QUEUED) {
            $counts['queued'] = $n;
        } elseif ($pst === LETTER_STATUS_PUBLISHED) {
            $counts['published'] = $n;
        }
    }

    $sql = 'SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick,
            (SELECT i.id FROM images i
              WHERE i.entity_type=' . LETTERS_ADMIN_ENTITY_TYPE . ' AND i.entity_id=l.id AND i.is_active=1
              ORDER BY i.sort_order ASC, i.id ASC LIMIT 1) AS cover_id
         FROM letters l
         LEFT JOIN authors af ON af.id=l.author_from
         LEFT JOIN authors at ON at.id=l.author_to';
    $where = [];
    if (!$filterAll) {
        $where[] = 'l.publish_status=' . (int) $statusFilter;
    }
    if ($authorFilterId > 0) {
        $where[] = '(l.author_from=' . $authorFilterId . ' OR l.author_to=' . $authorFilterId . ')';
    }
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(af.nickname, \'\') ASC, af.id ASC,
        CASE l.publish_status
          WHEN ' . LETTER_STATUS_QUEUED . ' THEN 0
          WHEN ' . LETTER_STATUS_DRAFT . ' THEN 1
          WHEN ' . LETTER_STATUS_PUBLISHED . ' THEN 2
          ELSE 3
        END ASC, l.queued_at ASC, l.id DESC';

    $letters = [];
    $groups = [];
    $groupIndex = [];
    $z = db_select($db, $sql);
    while ($z && ($t = mysqli_fetch_assoc($z))) {
        $st = (int) ($t['publish_status'] ?? LETTER_STATUS_DRAFT);
        $t['publish_status'] = $st;
        $t['publish_label'] = letters_publish_status_label($st);
        $t['publish_icon'] = match ($st) {
            LETTER_STATUS_QUEUED => 'clock',
            LETTER_STATUS_PUBLISHED => 'check',
            LETTER_STATUS_DELETED => 'trash-2',
            default => 'pencil',
        };
        $t['publish_tone'] = match ($st) {
            LETTER_STATUS_QUEUED => 'queued',
            LETTER_STATUS_PUBLISHED => 'published',
            LETTER_STATUS_DELETED => 'deleted',
            default => 'draft',
        };
        $coverId = (int) ($t['cover_id'] ?? 0);
        $t['thumb_url'] = '';
        if ($coverId > 0) {
            if (letters_preview_256_exists($coverId)) {
                $t['thumb_url'] = letters_preview_256_url($coverId);
            } else {
                $jpg = zx_storage_path('letters_preview', $coverId . '.jpg');
                if (is_file($jpg)) {
                    $t['thumb_url'] = '/letters/preview/' . $coverId . '.jpg';
                }
            }
        }
        $fromId = (int) ($t['author_from'] ?? 0);
        $toId = (int) ($t['author_to'] ?? 0);
        $fromNick = trim((string) ($t['from_nick'] ?? ''));
        $toNick = trim((string) ($t['to_nick'] ?? ''));
        $pubRaw = trim((string) ($t['published_at'] ?? ''));
        $pubLabel = '—';
        if ($pubRaw !== '' && $pubRaw !== '0000-00-00 00:00:00') {
            $ts = strtotime($pubRaw);
            if ($ts !== false) {
                $pubLabel = date('d.m.Y', $ts);
            }
        }
        $t['list_published'] = $pubLabel;
        if ($authorFilterId > 0) {
            if ($fromId === $authorFilterId) {
                $peerId = $toId;
                $groupNick = $toNick !== '' ? $toNick : ('#' . $toId);
            } else {
                $peerId = $fromId;
                $groupNick = $fromNick !== '' ? $fromNick : ('#' . $fromId);
            }
            $groupKey = 'peer:' . $peerId;
        } else {
            $groupKey = 'from:' . $fromId;
            $groupNick = $fromNick !== '' ? $fromNick : '—';
        }
        $t['list_peer'] = $groupNick;
        if (!isset($groupIndex[$groupKey])) {
            $groupIndex[$groupKey] = count($groups);
            $groups[] = ['key' => $groupKey, 'nick' => $groupNick, 'letters' => []];
        }
        $groups[$groupIndex[$groupKey]]['letters'][] = $t;
        $letters[] = $t;
    }
    if ($authorFilterId > 0 && $groups !== []) {
        usort($groups, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['nick'] ?? ''), (string) ($b['nick'] ?? ''));
        });
    }

    return [
        'letters' => $letters,
        'groups' => $groups,
        'status' => $filterAll ? 'all' : (string) $statusFilter,
        'author_id' => $authorFilterId,
        'author_nick' => $authorNick,
        'counts' => $counts,
    ];
}

function letters_admin_load(mysqli $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick
         FROM letters l
         LEFT JOIN authors af ON af.id=l.author_from
         LEFT JOIN authors at ON at.id=l.author_to
         WHERE l.id=? LIMIT 1'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $letter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$letter) {
        return null;
    }
    if (!empty($letter['date'])) {
        $letter['date'] = date('d.m.Y', strtotime((string) $letter['date']));
    }
    $letter['publish_status'] = (int) ($letter['publish_status'] ?? LETTER_STATUS_DRAFT);

    return $letter;
}

function letters_admin_delete_image(mysqli $db, int $letterId, int $imageId): bool
{
    if ($letterId <= 0 || $imageId <= 0) {
        return false;
    }
    $z = db_select(
        $db,
        'SELECT id FROM images WHERE id=? AND entity_type=? AND entity_id=? LIMIT 1',
        'iii',
        $imageId,
        LETTERS_ADMIN_ENTITY_TYPE,
        $letterId
    );
    $row = $z ? mysqli_fetch_assoc($z) : null;
    if (!$row) {
        return false;
    }
    db_exec(
        $db,
        'DELETE FROM images WHERE id=? AND entity_type=? AND entity_id=? LIMIT 1',
        'iii',
        $imageId,
        LETTERS_ADMIN_ENTITY_TYPE,
        $letterId
    );
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
        @unlink(zx_storage_path('letters', $imageId . '.' . $ext));
    }
    @unlink(zx_storage_path('letters_preview', $imageId . '.jpg'));
    @unlink(letters_preview_256_path($imageId));
    activity_log($db, [
        'verb' => 'updated',
        'object_type' => 'letter',
        'object_id' => $letterId,
        'action' => 'letter.image_deleted',
        'event_scope' => ACTIVITY_SCOPE_METADATA,
        'is_public' => 0,
        'url_ru' => '/snailmail.php?id=' . $letterId,
        'meta' => ['image_id' => $imageId],
    ]);

    return true;
}

/**
 * @return list<array<string,mixed>>
 */
function letters_admin_images(mysqli $db, int $id): array
{
    if ($id <= 0) {
        return [];
    }
    $images = [];
    $zImg = db_select(
        $db,
        'SELECT * FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC',
        'ii',
        LETTERS_ADMIN_ENTITY_TYPE,
        $id
    );
    while ($zImg && ($img = mysqli_fetch_assoc($zImg))) {
        $imgId = (int) ($img['id'] ?? 0);
        $fmt = (int) ($img['format'] ?? 1);
        $ext = match ($fmt) {
            2 => 'png',
            3 => 'webp',
            4 => 'gif',
            default => 'jpg',
        };
        $img['preview_path'] = zx_storage_path('letters_preview', $imgId . '.jpg');
        $img['preview_url'] = '/letters/preview/' . $imgId . '.jpg?v=' . (is_file($img['preview_path']) ? (string) filemtime($img['preview_path']) : (string) time());
        $img['original_url'] = '/letters/' . $imgId . '.' . $ext;
        $images[] = $img;
    }

    return $images;
}

