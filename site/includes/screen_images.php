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
	unset($format);
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
 * @return array{ok:bool,error?:string,width?:int,height?:int}
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

	return ['ok' => true, 'width' => $w, 'height' => $h];
}

/**
 * @return array{0:int,1:int}
 */
function screen_probe_file_dimensions(string $path): array
{
	if ($path === '' || !is_file($path)) {
		return [0, 0];
	}
	$info = @getimagesize($path);
	if (!$info) {
		return [0, 0];
	}

	return [(int) ($info[0] ?? 0), (int) ($info[1] ?? 0)];
}

function screen_set_dimensions(mysqli $db, int $screenId, int $width, int $height): bool
{
	if ($screenId <= 0 || $width <= 0 || $height <= 0) {
		return false;
	}

	return (bool) db_exec($db, 'UPDATE screens SET width=?, height=? WHERE id=? LIMIT 1', 'iii', $width, $height, $screenId);
}

/** Persist width/height after a successful WebP write (upload / replace). */
function screen_after_saved_webp(mysqli $db, int $screenId, array $saveResult): void
{
	if (empty($saveResult['ok'])) {
		return;
	}
	$w = (int) ($saveResult['width'] ?? 0);
	$h = (int) ($saveResult['height'] ?? 0);
	if ($w > 0 && $h > 0) {
		screen_set_dimensions($db, $screenId, $w, $h);
	}
}

/**
 * Convert an existing on-disk screenshot (png/jpg/jpeg/webp) to lossless WebP.
 * Source files are kept by default; pass $deleteSource=true to remove non-webp originals.
 *
 * @return array{ok:bool,error?:string,skipped?:bool}
 */
function screen_convert_existing_to_webp(int $screenId, ?string $format = null, bool $deleteSource = false): array
{
	if ($screenId <= 0) {
		return ['ok' => false, 'error' => 'bad id'];
	}

	$webpPath = screen_storage_path($screenId, 'webp');
	if (is_file($webpPath)) {
		if ($deleteSource) {
			foreach (['png', 'jpg', 'jpeg'] as $ext) {
				$path = screen_storage_path($screenId, $ext);
				if (is_file($path)) {
					@unlink($path);
				}
			}
		}
		[$pw, $ph] = screen_probe_file_dimensions($webpPath);

		return ['ok' => true, 'skipped' => true, 'width' => $pw, 'height' => $ph];
	}

	$srcPath = '';
	$srcExt = '';
	$try = [];
	if ($format !== null && $format !== '') {
		$try[] = screen_normalize_format($format);
	}
	$try = array_merge($try, ['png', 'jpg', 'jpeg', 'webp']);
	foreach (array_unique($try) as $ext) {
		$path = screen_storage_path($screenId, $ext);
		if (is_file($path)) {
			$srcPath = $path;
			$srcExt = $ext;
			break;
		}
	}
	if ($srcPath === '') {
		return ['ok' => false, 'error' => 'source missing'];
	}

	if ($srcExt === 'webp' && $srcPath === $webpPath) {
		[$pw, $ph] = screen_probe_file_dimensions($webpPath);

		return ['ok' => true, 'skipped' => true, 'width' => $pw, 'height' => $ph];
	}

	$converted = screen_save_upload_as_webp($srcPath, $screenId);
	if (empty($converted['ok'])) {
		return $converted;
	}

	if ($deleteSource && $srcExt !== 'webp' && is_file($srcPath)) {
		@unlink($srcPath);
	}

	return [
		'ok' => true,
		'width' => (int) ($converted['width'] ?? 0),
		'height' => (int) ($converted['height'] ?? 0),
	];
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

/** Display frame for gallery thumbs: 160×120 px + 1px line (see .smn-gallery-img). */
function screen_gallery_thumb_width(): int
{
	return 160;
}

function screen_gallery_thumb_height(): int
{
	return 120;
}

/**
 * CSS class for thumb framing inside 160×120 + 1px line:
 * — 320×240 emu → full bleed; 256×192 SCREEN$ → 128×96 + black pad; other → letterbox.
 */
function screen_gallery_frame_class(int $w, int $h): string
{
	if ($w <= 0 || $h <= 0) {
		return '';
	}
	// Turbo80 / bordered emu capture (2× display size).
	if ($w >= 300 && $h >= 220) {
		return 'smn-gallery-img--frame-emu';
	}
	// Native Spectrum bitmap (or exact half-scale export).
	if (($w === 256 && $h === 192) || ($w === 128 && $h === 96)) {
		return 'smn-gallery-img--frame-spectrum';
	}
	if (abs($w * 3 - $h * 4) <= max(4, (int) round($w * 0.02))) {
		if ($w > 280) {
			return 'smn-gallery-img--frame-emu';
		}

		return 'smn-gallery-img--frame-spectrum';
	}

	return 'smn-gallery-img--frame-letterbox';
}

/**
 * @param array<string,mixed> $row
 */
function screen_enrich_gallery_thumb(array &$row, string $idKey = 'id'): void
{
	screen_apply_public_format($row, $idKey, 'format');
	$w = (int) ($row['width'] ?? 0);
	$h = (int) ($row['height'] ?? 0);
	$row['gallery_frame_class'] = screen_gallery_frame_class($w, $h);
	$row['gallery_img_width'] = screen_gallery_thumb_width();
	$row['gallery_img_height'] = screen_gallery_thumb_height();
}

/**
 * Thumbnail on the press issues list — only from this issue's screens; null if none.
 *
 * @param list<array<string,mixed>> $screens
 * @return array<string,mixed>|null
 */
function issue_press_list_cover(array $screens): ?array
{
	if ($screens === []) {
		return null;
	}
	$pick = $screens[0];
	foreach ($screens as $row) {
		$rowType = (int) ($row['type'] ?? 99);
		$pickType = (int) ($pick['type'] ?? 99);
		if ($rowType < $pickType) {
			$pick = $row;
		}
	}
	screen_enrich_gallery_thumb($pick);

	return $pick;
}

/** Drop activity thumbs that still point at a removed screen (feed / batches). */
function screen_after_deleted(mysqli $db, int $screenId): void
{
	if ($screenId <= 0) {
		return;
	}
	$publicUrl = screen_public_url($screenId);
	if ($publicUrl !== '') {
		db_exec($db, 'UPDATE activity SET thumb_url=NULL WHERE thumb_url=? LIMIT 100', 's', $publicUrl);
	}
	db_exec(
		$db,
		"UPDATE activity SET thumb_url=NULL WHERE object_type='screen' AND object_id=? LIMIT 100",
		'i',
		$screenId
	);
}
