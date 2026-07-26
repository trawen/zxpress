<?php

require_once __DIR__ . '/storage_paths.php';
require_once __DIR__ . '/periodical_issue_images.php';

const LETTERS_PREVIEW_256_WIDTH = 256;
const LETTERS_PREVIEW_256_QUALITY = 80;

function letters_preview_256_path(int $imageId): string
{
	return zx_storage_path('letters_preview_256', $imageId . '.webp');
}

function letters_preview_256_url(int $imageId): string
{
	return '/letters/preview-256/' . $imageId . '.webp';
}

function letters_preview_256_exists(int $imageId): bool
{
	$path = letters_preview_256_path($imageId);

	return is_file($path) && filesize($path) > 0;
}

/**
 * Find original letter image on disk for a given images.id.
 * Originals live as content-store/letters/{id}.{ext}
 */
function letters_original_path_for_id(int $imageId): ?string
{
	if ($imageId <= 0) {
		return null;
	}

	$base = zx_storage_path('letters', (string) $imageId);
	foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
		$path = $base . '.' . $ext;
		if (is_file($path) && filesize($path) > 0) {
			return $path;
		}
	}

	return null;
}

function letters_make_preview_256(string $srcPath, int $imageId): bool
{
	return per_issue_make_webp_preview(
		$srcPath,
		letters_preview_256_path($imageId),
		LETTERS_PREVIEW_256_WIDTH,
		LETTERS_PREVIEW_256_QUALITY
	);
}

/**
 * Ensure preview-256 exists for image id (create from original if missing).
 */
function letters_ensure_preview_256(int $imageId, bool $force = false): bool
{
	if (!$force && letters_preview_256_exists($imageId)) {
		return true;
	}

	$src = letters_original_path_for_id($imageId);
	if ($src === null) {
		return false;
	}

	return letters_make_preview_256($src, $imageId);
}
