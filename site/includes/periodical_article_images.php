<?php

require_once __DIR__ . '/periodical_issue_images.php';

const PER_ARTICLE_IMAGES_ENTITY_TYPE = 5;
const PER_ARTICLE_IMAGE_WEBP_QUALITY = 70;
const PER_ARTICLE_IMAGE_WEBP_WIDTHS = [640, 1280];

function per_article_image_format_from_ext(string $ext): int
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

function per_article_image_format_ext(int $format): string
{
    return match ($format) {
        2 => 'png',
        3 => 'webp',
        4 => 'gif',
        default => 'jpg',
    };
}

function per_article_image_original_path(int $imageId, int $format): string
{
    return zx_storage_path(
        'periodical_article_images_original',
        $imageId . '.' . per_article_image_format_ext($format)
    );
}

function per_article_image_preview_path(int $imageId, int $width): string
{
    if ($width === 640) {
        return zx_storage_path('periodical_article_images_preview_640', $imageId . '.webp');
    }

    return zx_storage_path('periodical_article_images_preview_1280', $imageId . '.webp');
}

function per_article_image_original_url(int $imageId, int $format): string
{
    return '/images/periodicals/articles/original/' . $imageId . '.' . per_article_image_format_ext($format);
}

function per_article_image_preview_url(int $imageId, int $width): string
{
    return '/images/periodicals/articles/preview-' . $width . '/' . $imageId . '.webp';
}

function per_article_image_paths(int $imageId, int $format): array
{
    $paths = [per_article_image_original_path($imageId, $format)];
    foreach (PER_ARTICLE_IMAGE_WEBP_WIDTHS as $width) {
        $paths[] = per_article_image_preview_path($imageId, $width);
    }

    return $paths;
}

function per_article_image_delete_files(int $imageId, int $format): void
{
    if ($imageId <= 0) {
        return;
    }

    foreach (per_article_image_paths($imageId, $format) as $path) {
        if (!is_file($path)) {
            continue;
        }
        if (!@unlink($path)) {
            error_log('[FIX] per_article_image_delete_files: unlink failed image_id=' . $imageId . ' path=' . $path);
        }
    }

    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
        $alt = zx_storage_path('periodical_article_images_original', $imageId . '.' . $ext);
        if (is_file($alt)) {
            @unlink($alt);
        }
    }
}

function per_article_image_save_webp_previews(int $imageId, string $originalPath): bool
{
    foreach (PER_ARTICLE_IMAGE_WEBP_WIDTHS as $width) {
        if (!per_issue_make_webp_preview(
            $originalPath,
            per_article_image_preview_path($imageId, $width),
            $width,
            PER_ARTICLE_IMAGE_WEBP_QUALITY
        )) {
            return false;
        }
    }

    return true;
}

function per_article_image_save_upload(mysqli $db, int $articleId, string $tmpFile): ?int
{
    if ($articleId <= 0 || !is_file($tmpFile)) {
        return null;
    }

    $ext = per_issue_allowed_upload_ext($tmpFile);
    if ($ext === null) {
        return null;
    }

    $info = @getimagesize($tmpFile);
    if (!$info) {
        return null;
    }

    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if ($width <= 0 || $height <= 0) {
        return null;
    }

    $format = per_article_image_format_from_ext($ext);

    $rowMax = db_select(
        $db,
        'SELECT COALESCE(MAX(sort_order), 0) AS mx FROM images WHERE entity_type=? AND entity_id=?',
        'ii',
        PER_ARTICLE_IMAGES_ENTITY_TYPE,
        $articleId
    );
    $maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
    $sortOrder = (int) ($maxRow['mx'] ?? 0) + 1;

    $saved = db_exec(
        $db,
        'INSERT INTO images (entity_type, entity_id, format, width, height, sort_order, is_active) VALUES (?,?,?,?,?,?,1)',
        'iiiiii',
        PER_ARTICLE_IMAGES_ENTITY_TYPE,
        $articleId,
        $format,
        $width,
        $height,
        $sortOrder
    );
    if (!$saved) {
        return null;
    }

    $imageId = (int) mysqli_insert_id($db);
    if ($imageId <= 0) {
        return null;
    }

    $originalPath = per_article_image_original_path($imageId, $format);
    if (!zx_storage_copy_uploaded_file('periodical_article_images_original', basename($originalPath), $tmpFile)) {
        db_exec($db, 'DELETE FROM images WHERE id=? LIMIT 1', 'i', $imageId);
        return null;
    }

    if (!per_article_image_save_webp_previews($imageId, $originalPath)) {
        per_article_image_delete_files($imageId, $format);
        db_exec($db, 'DELETE FROM images WHERE id=? LIMIT 1', 'i', $imageId);
        return null;
    }

    return $imageId;
}

function per_article_image_enrich_row(array $row): array
{
    $imageId = (int) ($row['id'] ?? 0);
    $format = (int) ($row['format'] ?? 1);
    $row['original_url'] = per_article_image_original_url($imageId, $format);
    $row['preview_url'] = per_article_image_preview_url($imageId, 640);
    $row['preview_url_hd'] = per_article_image_preview_url($imageId, 1280);

    return $row;
}

function per_article_image_load_for_article(mysqli $db, int $articleId): array
{
    if ($articleId <= 0) {
        return [];
    }

    $images = [];
    $z = db_select(
        $db,
        'SELECT * FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC',
        'ii',
        PER_ARTICLE_IMAGES_ENTITY_TYPE,
        $articleId
    );
    while ($z && ($img = mysqli_fetch_array($z))) {
        $images[] = per_article_image_enrich_row($img);
    }

    return $images;
}

function per_article_image_handle_admin_post(mysqli $db, int $articleId): void
{
    if ($articleId <= 0) {
        return;
    }

    $zImg = db_select(
        $db,
        'SELECT id, sort_order FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC',
        'ii',
        PER_ARTICLE_IMAGES_ENTITY_TYPE,
        $articleId
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
            db_exec($db, 'UPDATE images SET sort_order=? WHERE id=? LIMIT 1', 'ii', $newSort, $imgId);
        }
    }

    $zImg = db_select(
        $db,
        'SELECT id, format FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC',
        'ii',
        PER_ARTICLE_IMAGES_ENTITY_TYPE,
        $articleId
    );
    while ($zImg && ($img = mysqli_fetch_array($zImg))) {
        $imgId = (int) ($img['id'] ?? 0);
        if ($imgId <= 0) {
            continue;
        }
        if (!empty($_POST['delete_image_' . $imgId])) {
            per_article_image_delete_files($imgId, (int) ($img['format'] ?? 1));
            db_exec($db, 'DELETE FROM images WHERE id=? LIMIT 1', 'i', $imgId);
        }
    }

    $upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
    $names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
    $tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];

    for ($i = 0, $n = count($names); $i < $n; $i++) {
        $tmp = (string) ($tmps[$i] ?? '');
        $origName = (string) ($names[$i] ?? '');
        if ($tmp === '' || !is_file($tmp) || $origName === '') {
            continue;
        }

        per_article_image_save_upload($db, $articleId, $tmp);
    }
}
