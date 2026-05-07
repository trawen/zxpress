<?php
/**
 * Runtime storage paths outside read-only site/.
 */

function zx_data_root(): string
{
	static $root = null;
	static $logged = false;
	if ($root !== null) {
		return $root;
	}

	$env = trim((string) getenv('ZXPRESS_DATA_ROOT'));
	if ($env !== '') {
		$root = rtrim($env, '/');
		if (!$logged) {
			error_log('[FIX] storage_paths data_root=' . $root . ' source=env');
			$logged = true;
		}
		return $root;
	}

	$root = rtrim(dirname(__DIR__, 2) . '/data', '/');
	if (!$logged) {
		error_log('[FIX] storage_paths data_root=' . $root . ' source=default');
		$logged = true;
	}
	return $root;
}

function zx_storage_rel_map(): array
{
	return [
		'articles' => 'content-store/articles',
		'articles_eng' => 'content-store/articles-eng',
		'chapters' => 'content-store/chapters',
		'letters' => 'content-store/letters',
		'letters_preview' => 'content-store/letters/preview',
		'pictures' => 'uploads/pictures',
		'screens' => 'uploads/screens',
		'illustrations' => 'uploads/illustrations',
		'books_files' => 'uploads/books-files',
		'files' => 'uploads/files',
		'news_files' => 'uploads/news-files',
		'archive' => 'legacy/archive',
		'cat' => 'legacy/cat',
		'chapters_images' => 'image-archive',
		'sape' => 'integrations/sape',
		'chronology_png' => 'cache/chronology/zxpress_dinamic.png',
		'smarty_templates_c' => 'cache/smarty/templates_c',
		'smarty_cache' => 'cache/smarty/cache',
	];
}

function zx_tmp_root(): string
{
	static $root = null;
	static $logged = false;
	if ($root !== null) {
		return $root;
	}

	$candidates = [];
	$env = trim((string) getenv('ZXPRESS_TMP_ROOT'));
	if ($env !== '') {
		$candidates[] = rtrim($env, '/');
	}
	$candidates[] = '/home/zxpress/tmp';
	$candidates[] = zx_data_root() . '/tmp';

	foreach ($candidates as $candidate) {
		if ($candidate === '') {
			continue;
		}
		if (is_dir($candidate) && is_writable($candidate)) {
			$root = $candidate;
			if (!$logged) {
				error_log('[FIX] storage_paths tmp_root=' . $root);
				$logged = true;
			}
			return $root;
		}
	}

	$root = zx_data_root() . '/tmp';
	if (!$logged) {
		error_log('[FIX] storage_paths tmp_root_fallback=' . $root);
		$logged = true;
	}
	return $root;
}

function zx_storage_dir(string $key): string
{
	if ($key === 'tmp') {
		return zx_tmp_root();
	}

	$map = zx_storage_rel_map();
	if (!isset($map[$key])) {
		throw new InvalidArgumentException('Unknown storage key: ' . $key);
	}

	return zx_data_root() . '/' . $map[$key];
}

function zx_storage_path(string $key, string $leaf = ''): string
{
	$base = zx_storage_dir($key);
	if ($leaf === '') {
		return $base;
	}

	$leaf = ltrim($leaf, '/');
	if (strpos($leaf, '..') !== false) {
		throw new InvalidArgumentException('Unsafe storage leaf');
	}

	return $base . '/' . $leaf;
}

function zx_storage_write(string $key, string $leaf, string $content): bool
{
	$path = zx_storage_path($key, $leaf);
	$dir = dirname($path);

	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
		error_log('[FIX] zx_storage_write: mkdir failed key=' . $key . ' dir=' . $dir . ' leaf=' . $leaf);
		return false;
	}

	$bytes = @file_put_contents($path, $content);
	if ($bytes === false) {
		error_log('[FIX] zx_storage_write: file_put_contents failed key=' . $key . ' path=' . $path . ' leaf=' . $leaf);
		return false;
	}

	return true;
}

function zx_storage_copy_uploaded_file(string $key, string $leaf, string $tmpPath): bool
{
	if ($tmpPath === '' || !is_file($tmpPath)) {
		error_log('[FIX] zx_storage_copy_uploaded_file: invalid tmp file key=' . $key . ' leaf=' . $leaf);
		return false;
	}

	$path = zx_storage_path($key, $leaf);
	$dir = dirname($path);

	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
		error_log('[FIX] zx_storage_copy_uploaded_file: mkdir failed key=' . $key . ' dir=' . $dir . ' leaf=' . $leaf);
		return false;
	}

	if (!@copy($tmpPath, $path)) {
		error_log('[FIX] zx_storage_copy_uploaded_file: copy failed key=' . $key . ' path=' . $path . ' leaf=' . $leaf . ' tmp=' . $tmpPath);
		return false;
	}

	return true;
}
