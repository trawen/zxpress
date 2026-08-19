<?php

require_once __DIR__ . '/storage_paths.php';

/**
 * Spectrum issue screenshots: uploads/screens/1/{id}.webp
 * Public site always serves WebP.
 */

function screen_normalize_format(?string $format): string
{
	$format = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $format) ?? '');

	return $format !== '' ? $format : 'webp';
}

function screen_storage_path(int $screenId, string $format = 'webp'): string
{
	return zx_storage_path('screens', '1/' . $screenId . '.' . screen_normalize_format($format));
}

function screen_public_url(int $screenId, ?string $format = null): string
{
	if ($screenId <= 0) {
		return '';
	}

	return '/screens/1/' . $screenId . '.webp';
}

/**
 * Force screens-table row format to webp for templates.
 *
 * @param array<string,mixed> $row
 */
function screen_apply_public_format(array &$row, string $idKey = 'id', string $formatKey = 'format'): void
{
	if ((int) ($row[$idKey] ?? 0) <= 0) {
		return;
	}
	$row[$formatKey] = 'webp';
}

/**
 * Save upload as lossless RGB WebP (no alpha). Accepts png/jpeg/webp.
 *
 * @return array{ok:bool,error?:string}
 */
function screen_save_upload_as_webp(string $tmpPath, int $screenId): array
{
	if ($screenId <= 0 || $tmpPath === '' || !is_file($tmpPath)) {
		return ['ok' => false, 'error' => 'нет файла'];
	}
	if (!function_exists('imagewebp')) {
		error_log('[FIX] screen_images: imagewebp() not available');

		return ['ok' => false, 'error' => 'WebP не поддерживается'];
	}

	$info = @getimagesize($tmpPath);
	if (!$info) {
		return ['ok' => false, 'error' => 'не удалось прочитать изображение'];
	}
	$mime = (string) ($info['mime'] ?? '');
	$src = false;
	if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
		$src = @imagecreatefrompng($tmpPath);
	} elseif (($mime === 'image/jpeg' || $mime === 'image/jpg') && function_exists('imagecreatefromjpeg')) {
		$src = @imagecreatefromjpeg($tmpPath);
	} elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
		$src = @imagecreatefromwebp($tmpPath);
	}
	if (!$src) {
		return ['ok' => false, 'error' => 'неподдерживаемый формат'];
	}

	if (function_exists('imagepalettetotruecolor')) {
		@imagepalettetotruecolor($src);
	}
	imagealphablending($src, true);
	imagesavealpha($src, false);

	$w = imagesx($src);
	$h = imagesy($src);
	$dst = imagecreatetruecolor($w, $h);
	if ($dst === false) {
		imagedestroy($src);

		return ['ok' => false, 'error' => 'не удалось создать холст'];
	}
	$bg = imagecolorallocate($dst, 0, 0, 0);
	imagefilledrectangle($dst, 0, 0, $w, $h, $bg);
	imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
	imagedestroy($src);

	$outPath = screen_storage_path($screenId, 'webp');
	$dir = dirname($outPath);
	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
		imagedestroy($dst);

		return ['ok' => false, 'error' => 'не удалось создать каталог'];
	}

	$webpQuality = defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 100;
	$ok = @imagewebp($dst, $outPath, $webpQuality);
	imagedestroy($dst);
	if (!$ok || !is_file($outPath)) {
		error_log('[FIX] screen_images: imagewebp failed path=' . $outPath);

		return ['ok' => false, 'error' => 'не удалось сохранить WebP'];
	}

	return ['ok' => true];
}

function screen_delete_files(int $screenId, ?string $format = null): void
{
	if ($screenId <= 0) {
		return;
	}
	$exts = ['webp', 'png', 'jpg', 'jpeg'];
	if ($format !== null && $format !== '') {
		$exts[] = screen_normalize_format($format);
	}
	foreach (array_unique($exts) as $ext) {
		$path = screen_storage_path($screenId, $ext);
		if (is_file($path)) {
			@unlink($path);
		}
	}
}
