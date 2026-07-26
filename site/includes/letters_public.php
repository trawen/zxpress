<?php

require_once __DIR__ . '/letters_slugs.php';
require_once __DIR__ . '/authors_slugs.php';
require_once __DIR__ . '/letters_images.php';

const LETTERS_ENTITY_TYPE = 1;

/**
 * @return 'jpg'|'png'|'webp'|'gif'
 */
function letters_public_format_ext(int $format): string
{
	if ($format === 2) {
		return 'png';
	}
	if ($format === 3) {
		return 'webp';
	}
	if ($format === 4) {
		return 'gif';
	}
	return 'jpg';
}

function letters_public_original_url(int $imageId, int $format): string
{
	return '/letters/' . $imageId . '.' . letters_public_format_ext($format);
}

function letters_public_preview_url(int $imageId): string
{
	return '/letters/preview/' . $imageId . '.jpg';
}

function letters_public_summary_html(?string $s): string
{
	$s = (string) $s;
	if ($s === '') {
		return '';
	}
	// Do not nl2br: .pub-body / .letter-summary / .pub-list-summary--lg use white-space: pre-line.
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Nickname, optional group after "^", optional person name in (); optional (city, country). */
function letters_public_author_line(
	?string $nickname,
	?string $groupName,
	?string $cityName,
	?string $countryName,
	bool $includeGeo = true,
	?string $personName = null,
): string {
	$nick = trim((string) $nickname);
	$out = $nick;
	$gn = trim((string) $groupName);
	if ($gn !== '') {
		$out .= ($out !== '' ? '^' : '') . $gn;
	}
	// Normalize separators around "^" (and legacy "/"); does not touch "(…)".
	$out = preg_replace('#\s*[/^]\s*#u', '^', $out);
	$person = trim((string) $personName);
	if ($person !== '') {
		$out .= ($out !== '' ? ' ' : '') . '(' . $person . ')';
	}
	if ($includeGeo) {
		$city = trim((string) $cityName);
		$country = trim((string) $countryName);
		if ($city !== '' && $country !== '') {
			$out .= ($out !== '' ? ' ' : '') . '(' . $city . ', ' . $country . ')';
		}
	}
	return $out;
}

function letters_public_author_person_name(?string $nameEn, ?string $nameRu, bool $isEng): string
{
	if ($isEng) {
		$en = trim((string) $nameEn);
		if ($en !== '') {
			return $en;
		}
	}
	$ru = trim((string) $nameRu);
	if ($ru !== '') {
		return $ru;
	}

	return trim((string) $nameEn);
}

function letters_public_first_cover(mysqli $db, int $letterId): ?array
{
	$stmt = $db->prepare(
		'SELECT id, format FROM images WHERE entity_type=? AND entity_id=? AND is_active=1 '
		. 'ORDER BY sort_order ASC, id ASC LIMIT 1'
	);
	if (!$stmt) {
		return null;
	}
	$et = LETTERS_ENTITY_TYPE;
	$stmt->bind_param('ii', $et, $letterId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$row) {
		return null;
	}
	$imgId = (int) ($row['id'] ?? 0);
	$fmt = (int) ($row['format'] ?? 1);
	if ($imgId <= 0) {
		return null;
	}
	$orig = letters_public_original_url($imgId, $fmt);
	$prev = letters_public_preview_url($imgId);
	$prev256 = letters_preview_256_url($imgId);
	$prev256Path = letters_preview_256_path($imgId);
	$prevPath = zx_storage_path('letters_preview', $imgId . '.jpg');
	if (is_file($prev256Path) && filesize($prev256Path) > 0) {
		$thumbSrc = $prev256;
	} elseif (is_file($prevPath)) {
		$thumbSrc = $prev;
	} else {
		$thumbSrc = $orig;
	}

	return [
		'image_id' => $imgId,
		'format' => $fmt,
		'original_url' => $orig,
		'preview_url' => $prev,
		'preview_256_url' => $prev256,
		'thumb_src' => $thumbSrc,
	];
}

function letters_public_meta_description(array $letter, ?string $lng): string
{
	$metaRu = title_plain((string) ($letter['meta_description_ru'] ?? ''));
	$metaEn = title_plain((string) ($letter['meta_description_en'] ?? ''));
	if ($lng === 'eng') {
		if ($metaEn !== '') {
			return $metaEn;
		}
		if ($metaRu !== '') {
			return $metaRu;
		}
		$summaryEn = title_plain(strip_tags((string) ($letter['summary_en'] ?? '')));
		if ($summaryEn !== '') {
			return $summaryEn;
		}
		return title_plain(strip_tags((string) ($letter['summary_ru'] ?? '')));
	}
	if ($metaRu !== '') {
		return $metaRu;
	}
	return title_plain(strip_tags((string) ($letter['summary_ru'] ?? '')));
}

function letters_public_is_eng(?string $lng): bool
{
	return $lng === 'eng';
}

function letters_public_pick_text(?string $en, ?string $ru, bool $isEng): string
{
	if ($isEng && trim((string) $en) !== '') {
		return (string) $en;
	}
	return (string) ($ru ?? '');
}

function letters_public_date_display($date, bool $isEng): string
{
	if ($date === null || $date === '') {
		return '';
	}
	$ts = strtotime((string) $date);
	if ($ts === false) {
		return '';
	}
	return $isEng ? date('j F Y', $ts) : date('d.m.Y', $ts);
}

function letters_public_author_from_row(array $row, string $prefix, bool $isEng): string
{
	$parts = letters_public_author_parts_from_row($row, $prefix, $isEng);
	if ($parts['geo'] !== '') {
		return $parts['name'] . ' ' . $parts['geo'];
	}
	return $parts['name'];
}

/** @return array{name: string, geo: string} */
function letters_public_author_parts_from_row(array $row, string $prefix, bool $isEng): array
{
	$city = $isEng && trim((string) ($row[$prefix . '_city_name_eng'] ?? '')) !== ''
		? $row[$prefix . '_city_name_eng']
		: ($row[$prefix . '_city_name'] ?? null);
	$country = $isEng && trim((string) ($row[$prefix . '_country_name_eng'] ?? '')) !== ''
		? $row[$prefix . '_country_name_eng']
		: ($row[$prefix . '_country_name'] ?? null);

	// Lists / letter pages: nick^group only; real name is shown on the author page.
	$name = letters_public_author_line(
		$row[$prefix . '_nick'] ?? null,
		$row[$prefix . '_group_name'] ?? null,
		null,
		null,
		false,
	);
	$geo = '';
	$city = trim((string) $city);
	$country = trim((string) $country);
	if ($city !== '' && $country !== '') {
		$geo = '(' . $city . ', ' . $country . ')';
	}

	return ['name' => $name, 'geo' => $geo];
}

function letters_public_enrich_row(array $row, bool $isEng): array
{
	$row['title_display'] = letters_public_pick_text($row['title_en'] ?? null, $row['title_ru'] ?? null, $isEng);
	$summary = letters_public_pick_text($row['summary_en'] ?? null, $row['summary_ru'] ?? null, $isEng);
	$body = letters_public_pick_text($row['body_en'] ?? null, $row['body_ru'] ?? null, $isEng);
	$row['summary_html'] = letters_public_summary_html($summary !== '' ? $summary : null);
	$row['body_html'] = letters_public_summary_html($body !== '' ? $body : null);
	$row['date_display'] = letters_public_date_display($row['date'] ?? null, $isEng);
	$row['published_display'] = letters_public_date_display($row['created_at'] ?? null, $isEng);
	$row['from_author_display'] = letters_public_author_from_row($row, 'from', $isEng);
	$row['to_author_display'] = letters_public_author_from_row($row, 'to', $isEng);
	$fromParts = letters_public_author_parts_from_row($row, 'from', $isEng);
	$toParts = letters_public_author_parts_from_row($row, 'to', $isEng);
	$row['from_author_name'] = $fromParts['name'];
	$row['from_author_geo'] = $fromParts['geo'];
	$row['to_author_name'] = $toParts['name'];
	$row['to_author_geo'] = $toParts['geo'];
	$fromSlugRow = [
		'id' => (int) ($row['author_from'] ?? 0),
		'slug_ru' => $row['from_slug_ru'] ?? '',
		'slug_en' => $row['from_slug_en'] ?? '',
	];
	$toSlugRow = [
		'id' => (int) ($row['author_to'] ?? 0),
		'slug_ru' => $row['to_slug_ru'] ?? '',
		'slug_en' => $row['to_slug_en'] ?? '',
	];
	$row['from_author_slug'] = authors_row_slug($fromSlugRow, $isEng);
	$row['to_author_slug'] = authors_row_slug($toSlugRow, $isEng);
	if ($row['from_author_slug'] === '' && $fromSlugRow['id'] > 0) {
		$row['from_author_slug'] = (string) $fromSlugRow['id'];
	}
	if ($row['to_author_slug'] === '' && $toSlugRow['id'] > 0) {
		$row['to_author_slug'] = (string) $toSlugRow['id'];
	}
	$row['from_author_url'] = $fromSlugRow['id'] > 0 ? authors_url($fromSlugRow, $isEng) : '';
	$row['to_author_url'] = $toSlugRow['id'] > 0 ? authors_url($toSlugRow, $isEng) : '';
	$row['public_url'] = letters_url_letter($row, $isEng);
	return $row;
}

function letters_public_lng_suffix(bool $isEng): string
{
	return $isEng ? '&lng=eng' : '';
}

/** SQL fragment selecting letter + from/to author display fields. */
function letters_public_list_select_sql(): string
{
	return 'SELECT l.*, '
		. 'af.nickname AS from_nick, at.nickname AS to_nick, '
		. 'af.name_ru AS from_name_ru, af.name_en AS from_name_en, '
		. 'at.name_ru AS to_name_ru, at.name_en AS to_name_en, '
		. 'af.group_name AS from_group_name, at.group_name AS to_group_name, '
		. 'af.slug_ru AS from_slug_ru, af.slug_en AS from_slug_en, '
		. 'at.slug_ru AS to_slug_ru, at.slug_en AS to_slug_en, '
		. 'cfn.name AS from_city_name, cnf.country_name AS from_country_name, '
		. 'cfn.name_eng AS from_city_name_eng, cnf.country_name_eng AS from_country_name_eng, '
		. 'ctn.name AS to_city_name, cnt.country_name AS to_country_name, '
		. 'ctn.name_eng AS to_city_name_eng, cnt.country_name_eng AS to_country_name_eng '
		. 'FROM letters l '
		. 'LEFT JOIN authors af ON af.id = l.author_from '
		. 'LEFT JOIN authors at ON at.id = l.author_to '
		. 'LEFT JOIN cities cfn ON cfn.id = af.city_id '
		. 'LEFT JOIN countries cnf ON cnf.id = af.country_id '
		. 'LEFT JOIN cities ctn ON ctn.id = at.city_id '
		. 'LEFT JOIN countries cnt ON cnt.id = at.country_id ';
}
