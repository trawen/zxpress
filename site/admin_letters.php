<?php
require 'init.inc';

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
    $src = null;
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($tmpFile);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($tmpFile);
    } elseif ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp')) {
            error_log('[FIX] admin_letters: imagecreatefromwebp() not available (GD without WebP)');
            return false;
        }
        $src = @imagecreatefromwebp($tmpFile);
    } elseif ($mime === 'image/gif') {
        if (!function_exists('imagecreatefromgif')) {
            error_log('[FIX] admin_letters: imagecreatefromgif() not available');
            return false;
        }
        $src = @imagecreatefromgif($tmpFile);
    } else {
        error_log('[FIX] admin_letters: preview unsupported mime=' . $mime);
        return false;
    }
    if (!$src) {
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
    $summary_ru = trim((string) ($_POST['summary_ru'] ?? ''));
    $summary_en = trim((string) ($_POST['summary_en'] ?? ''));
    $body_ru = trim((string) ($_POST['body_ru'] ?? ''));
    $body_en = trim((string) ($_POST['body_en'] ?? ''));
    $date_raw = zx_post_string('date');
    $date_db = letters_parse_date($date_raw);
    $is_active = !empty($_POST['is_active']) ? 1 : 0;

    if ($author_from <= 0 || $author_to <= 0 || $title_ru === '') {
        $smarty->assign('error', 'Заполни: От кого, Кому, Заголовок (RU)');
    } else {
        if ($id === 0) {
            db_exec(
                $db,
                "INSERT INTO letters (author_from, author_to, title_ru, title_en, summary_ru, summary_en, body_ru, body_en, date, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)",
                "iisssssssi",
                $author_from,
                $author_to,
                $title_ru,
                $title_en,
                ($summary_ru !== '' ? $summary_ru : null),
                ($summary_en !== '' ? $summary_en : null),
                ($body_ru !== '' ? $body_ru : null),
                ($body_en !== '' ? $body_en : null),
                $date_db,
                $is_active
            );
            $id = (int) mysqli_insert_id($db);
        } else {
            db_exec(
                $db,
                "UPDATE letters SET author_from=?, author_to=?, title_ru=?, title_en=?, summary_ru=?, summary_en=?, body_ru=?, body_en=?, date=?, is_active=? WHERE id=? LIMIT 1",
                "iisssssssii",
                $author_from,
                $author_to,
                $title_ru,
                $title_en,
                ($summary_ru !== '' ? $summary_ru : null),
                ($summary_en !== '' ? $summary_en : null),
                ($body_ru !== '' ? $body_ru : null),
                ($body_en !== '' ? $body_en : null),
                $date_db,
                $is_active,
                $id
            );
        }

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

        // Upload multiple scans/files (originals + jpeg previews)
        $upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
        $names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
        $tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];

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
            if ($tmp === '' || !is_file($tmp) || $origName === '') {
                continue;
            }

            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($allowed[$mime])) {
                error_log('[FIX] admin_letters: rejected upload mime=' . $mime . ' name=' . $origName);
                continue;
            }

            $ext = $allowed[$mime];
            $format = letters_images_format_from_ext($ext);
            $nextSort++;

            // Create DB row first to get image id (filename)
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
                continue;
            }

            zx_storage_copy_uploaded_file('letters', $imgId . '.' . $ext, $tmp);
            letters_make_jpeg_preview($tmp, zx_storage_path('letters_preview', $imgId . '.jpg'), 1280, 85);
        }

        header("Location: /admin_letters.php?id=" . $id, true, 303);
        exit;
    }
}

// Authors for selects
$authors = [];
$z = db_select($db, "SELECT id, nickname, name_ru, name_en, is_active FROM authors ORDER BY nickname ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $authors[] = $t;
}
$smarty->assign('authors', $authors);

// Letters list (with nicknames)
$letters_list = [];
$z = db_select(
    $db,
    "SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick
     FROM letters l
     LEFT JOIN authors af ON af.id=l.author_from
     LEFT JOIN authors at ON at.id=l.author_to
     ORDER BY l.id DESC"
);
while ($z && ($t = mysqli_fetch_array($z))) {
    $letters_list[] = $t;
}
$smarty->assign('letters_list', $letters_list);

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
        $img['preview_url'] = '/letters/preview/' . $imgId . '.jpg';
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

