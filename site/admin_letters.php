<?php
require 'init.inc';
require_once __DIR__ . '/includes/letters_publish.php';
require_once __DIR__ . '/includes/letters_slugs.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
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

$ENTITY_TYPE_LETTER = 1;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $author_from = zx_post_int('author_from');
    $author_to = zx_post_int('author_to');
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

    if ($author_from <= 0 || $author_to <= 0 || $title_ru === '') {
        $smarty->assign('error', 'Заполни: От кого, Кому, Заголовок (RU)');
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
            $smarty->assign('error', 'Ошибка сохранения: ' . $e->getMessage());
            $save_ok = false;
        }

        if (!$save_ok && empty($smarty->getTemplateVars('error'))) {
            $smarty->assign('error', 'Ошибка сохранения: ' . $db->error);
        }

        if ($save_ok) {

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
                    db_exec($db, "DELETE FROM images WHERE id=? LIMIT 1", "i", $imgId);
                    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
                        @unlink(zx_storage_path('letters', $imgId . '.' . $ext));
                    }
                    @unlink(zx_storage_path('letters_preview', $imgId . '.jpg'));
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
        }

        if ($uploadErrors !== []) {
            $smarty->assign('error', 'Письмо сохранено, но с файлами: ' . implode('; ', $uploadErrors));
            // Fall through to re-render form with error (no redirect).
        } else {
            header("Location: /admin_letters.php?id=" . $id, true, 303);
            exit;
        }
        }
    }
}

// Authors for selects
$authors = [];
$z = db_select($db, "SELECT id, nickname, name_ru, name_en, is_active FROM authors ORDER BY nickname ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $authors[] = $t;
}
$smarty->assign('authors', $authors);

// Letters list (with nicknames + publish labels)
$statusFilter = letters_publish_status_from_input((string) ($_GET['status'] ?? ''));
// Empty GET status means "all" — detect by raw presence.
$filterRaw = trim((string) ($_GET['status'] ?? ''));
$filterAll = ($filterRaw === '' || $filterRaw === 'all');

$letters_list = [];
$listSql = "SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick
     FROM letters l
     LEFT JOIN authors af ON af.id=l.author_from
     LEFT JOIN authors at ON at.id=l.author_to";
if (!$filterAll) {
    $listSql .= ' WHERE l.publish_status=' . (int) $statusFilter;
}
$listSql .= ' ORDER BY
     CASE l.publish_status
       WHEN ' . LETTER_STATUS_QUEUED . ' THEN 0
       WHEN ' . LETTER_STATUS_DRAFT . ' THEN 1
       WHEN ' . LETTER_STATUS_PUBLISHED . ' THEN 2
       ELSE 3
     END ASC,
     l.queued_at ASC,
     l.id DESC';
$z = db_select($db, $listSql);

$queuePos = 0;
$queuedRows = db_select(
    $db,
    'SELECT id FROM letters WHERE publish_status=? ORDER BY queued_at ASC, id ASC',
    'i',
    LETTER_STATUS_QUEUED
);
$queueIndexById = [];
while ($queuedRows && ($qr = mysqli_fetch_assoc($queuedRows))) {
    $queuePos++;
    $queueIndexById[(int) $qr['id']] = $queuePos;
}

while ($z && ($t = mysqli_fetch_array($z))) {
    $st = (int) ($t['publish_status'] ?? LETTER_STATUS_DRAFT);
    $t['publish_status'] = $st;
    $t['publish_label'] = letters_publish_status_label($st);
    $lid = (int) ($t['id'] ?? 0);
    if ($st === LETTER_STATUS_QUEUED && isset($queueIndexById[$lid])) {
        $t['queue_pos'] = $queueIndexById[$lid];
        $t['publish_label'] = 'очередь #' . $queueIndexById[$lid];
    }
    $letters_list[] = $t;
}
$smarty->assign('letters_list', $letters_list);
$smarty->assign('status_filter', $filterAll ? 'all' : (string) $statusFilter);
$smarty->assign('letter_status_draft', LETTER_STATUS_DRAFT);
$smarty->assign('letter_status_queued', LETTER_STATUS_QUEUED);
$smarty->assign('letter_status_published', LETTER_STATUS_PUBLISHED);
$smarty->assign('letter_status_deleted', LETTER_STATUS_DELETED);

$letter = null;
if ($id > 0) {
    $stmt = $db->prepare(
        "SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick
         FROM letters l
         LEFT JOIN authors af ON af.id=l.author_from
         LEFT JOIN authors at ON at.id=l.author_to
         WHERE l.id=? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $letter = $stmt->get_result()->fetch_assoc();
    }
}
if ($letter && !empty($letter['date'])) {
    $letter['date'] = date('d.m.Y', strtotime((string) $letter['date']));
}
if ($letter) {
    $letter['publish_status'] = (int) ($letter['publish_status'] ?? LETTER_STATUS_DRAFT);
}
$smarty->assign('letter', $letter);

// Letter images list (admin)
$images = [];
if ($id > 0) {
    $zImg = db_select($db, "SELECT * FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", $ENTITY_TYPE_LETTER, $id);
    while ($zImg && ($img = mysqli_fetch_array($zImg))) {
        $imgId = (int) ($img['id'] ?? 0);
        $fmt = (int) ($img['format'] ?? 1);
        $ext = 'jpg';
        if ($fmt === 2) {
            $ext = 'png';
        } elseif ($fmt === 3) {
            $ext = 'webp';
        } elseif ($fmt === 4) {
            $ext = 'gif';
        }
        $img['original_path'] = zx_storage_path('letters', $imgId . '.' . $ext);
        $img['preview_path'] = zx_storage_path('letters_preview', $imgId . '.jpg');
        $img['original_url'] = '/letters/' . $imgId . '.' . $ext;
        $img['preview_url'] = '/letters/preview/' . $imgId . '.jpg?v=' . (is_file($img['preview_path']) ? (string) filemtime($img['preview_path']) : (string) time());
        $images[] = $img;
    }
}
$smarty->assign('images', $images);

// Admin top expects press_list
$press_list = [];
$z = db_select($db, "SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . " - " . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Письма');
$smarty->display('admin_letters.tpl');

