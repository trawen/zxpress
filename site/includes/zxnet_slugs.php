<?php

require_once __DIR__ . '/ezine_slugs.php';
require_once __DIR__ . '/letters_slugs.php';

const ZXNET_SLUG_MAX_LEN = 128;

function zxnet_slugify(string $text): string
{
	$slug = letters_slugify($text);
	if (strlen($slug) > ZXNET_SLUG_MAX_LEN) {
		$slug = rtrim(substr($slug, 0, ZXNET_SLUG_MAX_LEN), '-');
	}
	return $slug;
}

function zxnet_slug_exists(
	mysqli $db,
	string $column,
	int $echoId,
	string $slug,
	int $excludeId = 0
): bool {
	if (!in_array($column, ['slug_ru', 'slug_en'], true) || $slug === '' || $echoId <= 0) {
		return false;
	}

	$sql = 'SELECT id FROM echos_subjs2 WHERE echo_id=? AND ' . $column . '=?';
	$types = 'is';
	$params = [$echoId, $slug];
	if ($excludeId > 0) {
		$sql .= ' AND id<>?';
		$types .= 'i';
		$params[] = $excludeId;
	}
	$sql .= ' LIMIT 1';

	$stmt = $db->prepare($sql);
	if (!$stmt) {
		throw new RuntimeException('zxnet slug check prepare failed: ' . $db->error);
	}
	$stmt->bind_param($types, ...$params);
	$stmt->execute();
	$exists = (bool) $stmt->get_result()->fetch_assoc();
	$stmt->close();

	return $exists;
}

function zxnet_slug_make_unique(
	mysqli $db,
	string $column,
	int $echoId,
	string $value,
	int $excludeId = 0,
	string $fallback = 'topic'
): string {
	$base = zxnet_slugify($value);
	if ($base === '') {
		$base = zxnet_slugify($fallback);
	}
	if ($base === '') {
		$base = 'topic';
	}

	$candidate = $base;
	$suffix = 2;
	while (zxnet_slug_exists($db, $column, $echoId, $candidate, $excludeId)) {
		$suffixPart = '-' . $suffix;
		$maxBaseLen = ZXNET_SLUG_MAX_LEN - strlen($suffixPart);
		$candidate = rtrim(substr($base, 0, max(1, $maxBaseLen)), '-') . $suffixPart;
		$suffix++;
	}

	return $candidate;
}

/**
 * @return array{slug_ru:string,slug_en:string}
 */
function zxnet_resolve_slugs(
	mysqli $db,
	int $echoId,
	string $titleRu,
	string $titleEn,
	int $excludeId = 0
): array {
	$ruSeed = trim($titleRu);
	$enSeed = trim($titleEn) !== '' ? trim($titleEn) : $ruSeed;
	$fallback = 'topic-' . ($excludeId > 0 ? $excludeId : 'x');

	return [
		'slug_ru' => zxnet_slug_make_unique($db, 'slug_ru', $echoId, $ruSeed, $excludeId, $fallback),
		'slug_en' => zxnet_slug_make_unique($db, 'slug_en', $echoId, $enSeed, $excludeId, $fallback),
	];
}

function zxnet_row_slug(array $row, bool $isEng): string
{
	if ($isEng) {
		$en = trim((string) ($row['slug_en'] ?? ''));
		if ($en !== '') {
			return $en;
		}
	}

	$ru = trim((string) ($row['slug_ru'] ?? ''));
	if ($ru !== '') {
		return $ru;
	}

	return trim((string) ($row['slug_en'] ?? ''));
}

/**
 * Resolve topic by slug (preferred) or numeric id (legacy).
 *
 * @return array|null subject row
 */
function zxnet_find_subj(mysqli $db, int $echoId, string $key, bool $isEng): ?array
{
	$key = trim($key, " \t\n\r\0\x0B/");
	if ($echoId <= 0 || $key === '') {
		return null;
	}

	$slug = zxnet_slugify(rawurldecode($key));
	if ($slug !== '') {
		$primary = $isEng ? 'slug_en' : 'slug_ru';
		$fallback = $isEng ? 'slug_ru' : 'slug_en';
		$stmt = $db->prepare(
			'SELECT * FROM echos_subjs2 WHERE echo_id=? AND (' . $primary . '=? OR ' . $fallback . '=?) LIMIT 1'
		);
		if ($stmt) {
			$stmt->bind_param('iss', $echoId, $slug, $slug);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			if (is_array($row)) {
				return $row;
			}
		}
	}

	if (ctype_digit($key)) {
		$id = (int) $key;
		$stmt = $db->prepare('SELECT * FROM echos_subjs2 WHERE echo_id=? AND id=? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('ii', $echoId, $id);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			if (is_array($row)) {
				return $row;
			}
		}
	}

	return null;
}

function zxnet_ui_is_new(): bool
{
	return !defined('ZXNET_UI_VARIANT') || ZXNET_UI_VARIANT === 'new';
}

function zxnet_url_catalog(bool $isEng): string
{
	if (zxnet_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/zxnet';
	}
	return ezn_path_prefix($isEng) . '/zxnet-old';
}

function zxnet_url_echo(string $title, bool $isEng): string
{
	$seg = rawurlencode($title);
	if (zxnet_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/zxnet/' . $seg;
	}
	return ezn_path_prefix($isEng) . '/zxnet-old/' . $seg;
}

function zxnet_url_topic(string $echoTitle, string $topicSlug, bool $isEng, int $fallbackId = 0): string
{
	$seg = rawurlencode($echoTitle);
	$topicSeg = $topicSlug !== '' ? rawurlencode($topicSlug) : (string) max(0, $fallbackId);
	if ($topicSeg === '' || $topicSeg === '0') {
		return zxnet_url_echo($echoTitle, $isEng);
	}
	if (zxnet_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/zxnet/' . $seg . '/' . $topicSeg;
	}
	return ezn_path_prefix($isEng) . '/zxnet-old/' . $seg . '/' . $topicSeg;
}
