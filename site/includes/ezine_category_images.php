<?php

function ec_image_exts(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
}

function ec_category_original_path(int $categoryId): ?array
{
    if ($categoryId <= 0) {
        return null;
    }

    foreach (ec_image_exts() as $ext) {
        $path = zx_storage_path('ezine_categories_original', $categoryId . '.' . $ext);
        if (is_file($path)) {
            return ['path' => $path, 'ext' => $ext];
        }
    }

    return null;
}

function ec_category_webp_path(int $categoryId): string
{
    return zx_storage_path('ezine_categories_webp', $categoryId . '.webp');
}

function ec_category_has_public_image(int $categoryId): bool
{
    if ($categoryId <= 0) {
        return false;
    }

    return is_file(ec_category_webp_path($categoryId));
}

function ec_category_public_image_url(int $categoryId): string
{
    return '/ezine-categories/' . $categoryId . '.webp';
}

function ec_category_delete_images(int $categoryId): void
{
    if ($categoryId <= 0) {
        return;
    }

    foreach (ec_image_exts() as $ext) {
        $path = zx_storage_path('ezine_categories_original', $categoryId . '.' . $ext);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $webp = ec_category_webp_path($categoryId);
    if (is_file($webp)) {
        @unlink($webp);
    }
}

function ec_category_allowed_upload_ext(string $tmpFile): ?string
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

function ec_category_load_image_resource(string $srcPath, string $mime)
{
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($srcPath);
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($srcPath);
    }
    if ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp')) {
            error_log('[FIX] ezine_category_images: imagecreatefromwebp() not available');
            return false;
        }

        return @imagecreatefromwebp($srcPath);
    }
    if ($mime === 'image/gif') {
        if (!function_exists('imagecreatefromgif')) {
            error_log('[FIX] ezine_category_images: imagecreatefromgif() not available');
            return false;
        }

        return @imagecreatefromgif($srcPath);
    }

    return false;
}

function ec_category_make_webp(string $srcPath, string $dstPath, int $quality = 80): bool
{
    if (!function_exists('imagewebp')) {
        error_log('[FIX] ezine_category_images: imagewebp() not available');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) {
        return false;
    }

    $mime = $info['mime'] ?? '';
    $src = ec_category_load_image_resource($srcPath, $mime);
    if (!$src) {
        return false;
    }

    if ($mime === 'image/png') {
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }

    $dir = dirname($dstPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($src);
        return false;
    }

    $ok = @imagewebp($src, $dstPath, max(0, min(100, $quality)));
    imagedestroy($src);

    return (bool) $ok;
}

function ec_category_save_image(int $categoryId, string $tmpFile): bool
{
    $ext = ec_category_allowed_upload_ext($tmpFile);
    if ($ext === null) {
        return false;
    }

    ec_category_delete_images($categoryId);

    if (!zx_storage_copy_uploaded_file('ezine_categories_original', $categoryId . '.' . $ext, $tmpFile)) {
        return false;
    }

    $originalPath = zx_storage_path('ezine_categories_original', $categoryId . '.' . $ext);
    if (!ec_category_make_webp($originalPath, ec_category_webp_path($categoryId), 80)) {
        ec_category_delete_images($categoryId);
        return false;
    }

    return true;
}
