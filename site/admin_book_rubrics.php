<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function br_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function br_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function br_image_exts(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
}

function br_find_image(int $rubricId): ?array
{
    if ($rubricId <= 0) {
        return null;
    }

    foreach (br_image_exts() as $ext) {
        $path = zx_storage_path('book_rubrics', $rubricId . '.' . $ext);
        if (is_file($path)) {
            return ['path' => $path, 'ext' => $ext];
        }
    }

    return null;
}

function br_delete_image(int $rubricId): void
{
    foreach (br_image_exts() as $ext) {
        $path = zx_storage_path('book_rubrics', $rubricId . '.' . $ext);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function br_allowed_upload_ext(string $tmpFile): ?string
{
    $info = @getimagesize($tmpFile);
    if (!$info) {
        return null;
    }

    $mime = $info['mime'] ?? '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    return $allowed[$mime] ?? null;
}

function br_save_image(int $rubricId, string $tmpFile): bool
{
    $ext = br_allowed_upload_ext($tmpFile);
    if ($ext === null) {
        return false;
    }

    br_delete_image($rubricId);

    return zx_storage_copy_uploaded_file('book_rubrics', $rubricId . '.' . $ext, $tmpFile);
}

function br_sync_book_links(mysqli $db, int $rubricId, array $bookIds): void
{
    db_exec($db, 'DELETE FROM book_rubric_links WHERE rubric_id=?', 'i', $rubricId);

    foreach ($bookIds as $bookId) {
        $bookId = (int) $bookId;
        if ($bookId <= 0) {
            continue;
        }
        db_exec(
            $db,
            'INSERT IGNORE INTO book_rubric_links (book_id, rubric_id) VALUES (?, ?)',
            'ii',
            $bookId,
            $rubricId
        );
    }
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = null;

if (isset($_GET['serve_image'])) {
    $imgId = (int) $_GET['serve_image'];
    $img = br_find_image($imgId);
    if (!$img) {
        header('HTTP/1.1 404 Not Found');
        exit;
    }

    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    $ext = $img['ext'];
    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($img['path']));
    readfile($img['path']);
    exit;
}

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $name_ru = plain_text_normalize_for_storage(br_post_string('name_ru'));
    $name_en = plain_text_normalize_for_storage(br_post_string('name_en'));
    $description_ru = plain_text_normalize_for_storage(br_post_string('description_ru'));
    $description_en = plain_text_normalize_for_storage(br_post_string('description_en'));
    $sort_order = br_post_int('sort_order');
    $is_active = !empty($_POST['is_active']) ? 1 : 0;

    $bookIds = [];
    if (isset($_POST['book_ids']) && is_array($_POST['book_ids'])) {
        foreach ($_POST['book_ids'] as $bookId) {
            $bookIds[] = (int) $bookId;
        }
    }

    if ($name_ru === '') {
        $error = 'Название (RU) обязательно';
    } else {
        $wasCreate = ($id === 0);
        if ($id === 0) {
            db_exec(
                $db,
                'INSERT INTO book_rubrics (name_ru, name_en, description_ru, description_en, sort_order, is_active, created_at) VALUES (?,?,?,?,?,?,NOW())',
                'ssssii',
                $name_ru,
                ($name_en !== '' ? $name_en : ''),
                ($description_ru !== '' ? $description_ru : null),
                ($description_en !== '' ? $description_en : null),
                $sort_order,
                $is_active
            );
            $id = (int) mysqli_insert_id($db);
        } else {
            db_exec(
                $db,
                'UPDATE book_rubrics SET name_ru=?, name_en=?, description_ru=?, description_en=?, sort_order=?, is_active=? WHERE id=? LIMIT 1',
                'ssssiii',
                $name_ru,
                ($name_en !== '' ? $name_en : ''),
                ($description_ru !== '' ? $description_ru : null),
                ($description_en !== '' ? $description_en : null),
                $sort_order,
                $is_active,
                $id
            );
        }

        br_sync_book_links($db, $id, $bookIds);

        if (!empty($_POST['delete_image'])) {
            br_delete_image($id);
        } elseif (!empty($_FILES['upload_image']['tmp_name']) && is_uploaded_file($_FILES['upload_image']['tmp_name'])) {
            if (!br_save_image($id, (string) $_FILES['upload_image']['tmp_name'])) {
                $error = 'Не удалось загрузить изображение (допустимы JPEG, PNG, WebP, GIF)';
            }
        }

        if ($error === null) {
            activity_log($db, [
                'verb' => $wasCreate ? 'created' : 'updated',
                'object_type' => 'book_rubric',
                'object_id' => $id,
                'action' => $wasCreate ? 'book_rubric.created' : 'book_rubric.updated',
                'event_scope' => ACTIVITY_SCOPE_METADATA,
                'is_public' => 0,
                'title_ru' => $name_ru,
                'title_en' => $name_en !== '' ? $name_en : $name_ru,
                'after' => ['is_active' => $is_active, 'book_ids' => $bookIds],
            ]);
            header('Location: /admin_book_rubrics.php?id=' . $id, true, 303);
            exit;
        }
    }
}

$smarty->assign('error', $error);

$rubrics_list = [];
$z = db_select($db, 'SELECT id, name_ru, name_en, sort_order, is_active FROM book_rubrics ORDER BY sort_order ASC, name_ru ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $rubrics_list[] = $t;
}
$smarty->assign('rubrics_list', $rubrics_list);

if ($id === 0 && count($rubrics_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($rubrics_list[0]['id'] ?? 0);
}

$rubric = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM book_rubrics WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rubric = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$smarty->assign('rubric', $rubric);

$linked_book_ids = [];
if ($id > 0) {
    $z = db_select($db, 'SELECT book_id FROM book_rubric_links WHERE rubric_id=?', 'i', $id);
    while ($z && ($t = mysqli_fetch_array($z))) {
        $linked_book_ids[(int) $t['book_id']] = true;
    }
}

$books_list = [];
$z = db_select($db, 'SELECT id, title1, title2 FROM books ORDER BY title1 ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] .= ' — ' . $t['title2'];
    }
    $t['linked'] = !empty($linked_book_ids[(int) $t['id']]);
    $books_list[] = $t;
}
$smarty->assign('books_list', $books_list);

if ($id > 0 && br_find_image($id)) {
    $smarty->assign('rubric_image_url', '/admin_book_rubrics.php?serve_image=' . $id);
} else {
    $smarty->assign('rubric_image_url', '');
}

$press_list = [];
$z = db_select($db, 'SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . ' - ' . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Рубрики книг');
$smarty->display('admin_book_rubrics.tpl');
