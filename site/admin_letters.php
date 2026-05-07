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

function letters_make_webp_preview(string $tmpFile, string $dstPath, int $maxWidth = 1280, int $quality = 80): bool
{
    if (!function_exists('imagewebp')) {
        error_log('[FIX] admin_letters: imagewebp() not available, skip preview');
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
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($tmpFile);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($tmpFile);
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

    $ok = @imagewebp($dst, $dstPath, $quality);
    imagedestroy($dst);
    imagedestroy($src);
    return (bool) $ok;
}

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

        // Upload scan/file (original + preview)
        $upl = (isset($_FILES['upload_file']) && is_array($_FILES['upload_file'])) ? $_FILES['upload_file'] : [];
        $tmp = (string) ($upl['tmp_name'] ?? '');
        $origName = (string) ($upl['name'] ?? '');
        if ($tmp !== '' && is_file($tmp) && $origName !== '') {
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowed[$mime])) {
                error_log('[FIX] admin_letters: rejected upload mime=' . $mime . ' name=' . $origName);
            } else {
                $ext = $allowed[$mime];
                $leaf = $id . '.' . $ext;

                // remove old known originals
                foreach (['jpg', 'jpeg', 'png'] as $oldExt) {
                    @unlink(zx_storage_path('letters', $id . '.' . $oldExt));
                }

                zx_storage_copy_uploaded_file('letters', $leaf, $tmp);

                // preview is always webp
                $previewPath = zx_storage_path('letters_preview', $id . '.webp');
                letters_make_webp_preview($tmp, $previewPath, 1280, 80);
            }
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

if ($id === 0 && count($letters_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($letters_list[0]['id'] ?? 0);
}

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

// Existing file paths for display (admin)
$file_info = [
    'original' => null,
    'preview' => null,
];
if ($id > 0) {
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $p = zx_storage_path('letters', $id . '.' . $ext);
        if (is_file($p)) {
            $file_info['original'] = $p;
            break;
        }
    }
    $pPrev = zx_storage_path('letters_preview', $id . '.webp');
    if (is_file($pPrev)) {
        $file_info['preview'] = $pPrev;
    }
}
$smarty->assign('file_info', $file_info);

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

