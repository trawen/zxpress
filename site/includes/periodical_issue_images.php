<?php

function per_issue_cover_jpg_path(int $issueId): string
{
    return zx_storage_path('periodical_issues', $issueId . '.jpg');
}

function per_issue_cover_webp_path(int $issueId, int $width): string
{
    if ($width === 640) {
        return zx_storage_path('periodical_issues_preview_640', $issueId . '.webp');
    }

    return zx_storage_path('periodical_issues_preview_1280', $issueId . '.webp');
}

function per_issue_has_cover(int $issueId): bool
{
    if ($issueId <= 0) {
        return false;
    }

    return is_file(per_issue_cover_jpg_path($issueId));
}

function per_issue_delete_cover(int $issueId): void
{
    if ($issueId <= 0) {
        return;
    }

    $paths = [
        per_issue_cover_jpg_path($issueId),
        per_issue_cover_webp_path($issueId, 640),
        per_issue_cover_webp_path($issueId, 1280),
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function per_issue_allowed_upload_ext(string $tmpFile): ?string
{
    $info = @getimagesize($tmpFile);
    if (!$info) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    return $allowed[$info['mime'] ?? ''] ?? null;
}

function per_issue_load_image_resource(string $srcPath, string $mime)
{
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($srcPath);
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($srcPath);
    }
    if ($mime === 'image/webp') {
        if (!function_exists('imagecreatefromwebp')) {
            error_log('[FIX] periodical_issue_images: imagecreatefromwebp() not available');
            return false;
        }

        return @imagecreatefromwebp($srcPath);
    }
    if ($mime === 'image/gif') {
        if (!function_exists('imagecreatefromgif')) {
            error_log('[FIX] periodical_issue_images: imagecreatefromgif() not available');
            return false;
        }

        return @imagecreatefromgif($srcPath);
    }

    return false;
}

function per_issue_save_jpg_from_tmp(string $tmpFile, string $dstPath, int $quality = 90): bool
{
    if (!function_exists('imagejpeg')) {
        error_log('[FIX] periodical_issue_images: imagejpeg() not available');
        return false;
    }

    $info = @getimagesize($tmpFile);
    if (!$info) {
        return false;
    }

    $mime = $info['mime'] ?? '';
    $src = per_issue_load_image_resource($tmpFile, $mime);
    if (!$src) {
        return false;
    }

    $dir = dirname($dstPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($src);
        return false;
    }

    $ok = @imagejpeg($src, $dstPath, max(0, min(100, $quality)));
    imagedestroy($src);

    return (bool) $ok;
}

function per_issue_make_webp_preview(string $srcPath, string $dstPath, int $maxWidth, int $quality = 80): bool
{
    if (!function_exists('imagewebp')) {
        error_log('[FIX] periodical_issue_images: imagewebp() not available');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) {
        return false;
    }

    [$w, $h] = $info;
    if ($w <= 0 || $h <= 0) {
        return false;
    }

    $mime = $info['mime'] ?? '';
    $src = per_issue_load_image_resource($srcPath, $mime);
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

    if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);

    $dir = dirname($dstPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        imagedestroy($dst);
        imagedestroy($src);
        return false;
    }

    $ok = @imagewebp($dst, $dstPath, max(0, min(100, $quality)));
    imagedestroy($dst);
    imagedestroy($src);

    return (bool) $ok;
}

function per_issue_save_cover(int $issueId, string $tmpFile): bool
{
    if ($issueId <= 0 || per_issue_allowed_upload_ext($tmpFile) === null) {
        return false;
    }

    per_issue_delete_cover($issueId);

    $jpgPath = per_issue_cover_jpg_path($issueId);
    if (!per_issue_save_jpg_from_tmp($tmpFile, $jpgPath)) {
        per_issue_delete_cover($issueId);
        return false;
    }

    if (!per_issue_make_webp_preview($jpgPath, per_issue_cover_webp_path($issueId, 640), 640)) {
        per_issue_delete_cover($issueId);
        return false;
    }

    if (!per_issue_make_webp_preview($jpgPath, per_issue_cover_webp_path($issueId, 1280), 1280)) {
        per_issue_delete_cover($issueId);
        return false;
    }

    return true;
}
