<?php
require 'init.inc';
require_once __DIR__ . '/includes/letters_publish.php';
require_once __DIR__ . '/includes/letters_slugs.php';

const LETTERS_ENTITY_TYPE = 1;
const LETTERS_PER_PAGE = 20;

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

/** Nickname, optional group_name after "/", no spaces; optional (city, country) when both are set (if $includeGeo). */
function letters_public_author_line(
	?string $nickname,
	?string $groupName,
	?string $cityName,
	?string $countryName,
	bool $includeGeo = true,
): string {
	$nick = trim((string) $nickname);
	$out = $nick;
	$gn = trim((string) $groupName);
	if ($gn !== '') {
		$out .= ($out !== '' ? '/' : '') . $gn;
	}
	// Strip any spaces around "/" (DB or legacy formatting); does not touch "(city, country)".
	$out = preg_replace('#\s*/\s*#u', '/', $out);
	if ($includeGeo) {
		$city = trim((string) $cityName);
		$country = trim((string) $countryName);
		if ($city !== '' && $country !== '') {
			$out .= ($out !== '' ? ' ' : '') . '(' . $city . ', ' . $country . ')';
		}
	}
	return $out;
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
	$prevPath = zx_storage_path('letters_preview', $imgId . '.jpg');
	$thumbSrc = is_file($prevPath) ? $prev : $orig;

	return [
		'image_id' => $imgId,
		'format' => $fmt,
		'original_url' => $orig,
		'preview_url' => $prev,
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
	$city = $isEng && trim((string) ($row[$prefix . '_city_name_eng'] ?? '')) !== ''
		? $row[$prefix . '_city_name_eng']
		: ($row[$prefix . '_city_name'] ?? null);
	$country = $isEng && trim((string) ($row[$prefix . '_country_name_eng'] ?? '')) !== ''
		? $row[$prefix . '_country_name_eng']
		: ($row[$prefix . '_country_name'] ?? null);

	return letters_public_author_line(
		$row[$prefix . '_nick'] ?? null,
		$row[$prefix . '_group_name'] ?? null,
		$city,
		$country,
	);
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
	$row['public_url'] = letters_url_letter($row, $isEng);
	return $row;
}

function letters_public_lng_suffix(bool $isEng): string
{
	return $isEng ? '&lng=eng' : '';
}

$lng = $smarty->getTemplateVars('lng');
$isEng = letters_public_is_eng($lng);

$letterSlug = trim((string) ($_GET['letter_slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$fromAuthor = (int) ($_GET['from'] ?? 0);
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * LETTERS_PER_PAGE;

// Lazy auto-publish: at most one queued letter per calendar day.
letters_maybe_publish_next($db);

if ($letterSlug !== '') {
	$id = letters_find_id_by_slug($db, $letterSlug, $isEng);
	if ($id <= 0) {
		http_response_code(404);
		$smarty->assign('letter', null);
		$smarty->assign('letter_images', []);
		$smarty->assign('letter_not_found', true);
		$smarty->assign('title', $isEng ? 'Letter not found' : 'Бумажное письмо не найдено');
		$smarty->assign('og_title', '');
		$smarty->assign('og_description', '');
		$smarty->assign('og_image', '');
		$smarty->assign('og_url', '');
		$smarty->assign('og_type', '');
		$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
		letters_assign_lang_switch_urls($smarty, null);
		$smarty->display('letters.tpl');
		exit;
	}
}

letters_maybe_redirect_legacy($db, $letterSlug !== '', $id, $isEng);

if ($id > 0) {
	$stmt = $db->prepare(
		'SELECT l.*, '
		. 'af.nickname AS from_nick, at.nickname AS to_nick, '
		. 'af.group_name AS from_group_name, at.group_name AS to_group_name, '
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
		. 'LEFT JOIN countries cnt ON cnt.id = at.country_id '
		. 'WHERE l.id = ? AND l.is_active = 1 LIMIT 1'
	);
	if (!$stmt) {
		http_response_code(500);
		die('Database error');
	}
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$letter = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$letter) {
		http_response_code(404);
		$smarty->assign('letter', null);
		$smarty->assign('letter_images', []);
		$smarty->assign('letter_not_found', true);
		$smarty->assign('title', $isEng ? 'Letter not found' : 'Бумажное письмо не найдено');
		$smarty->assign('og_title', '');
		$smarty->assign('og_description', '');
		$smarty->assign('og_image', '');
		$smarty->assign('og_url', '');
		$smarty->assign('og_type', '');
	} else {
		$smarty->assign('letter_not_found', false);
		db_exec($db, 'UPDATE letters SET view_count = view_count + 1 WHERE id = ? LIMIT 1', 'i', $id);

		$letter = letters_public_enrich_row($letter, $isEng);

		if ($letterSlug !== '') {
			$canonicalSlug = letters_row_slug($letter, $isEng);
			$incomingSlug = letters_slug_normalize_path($letterSlug);
			if ($canonicalSlug !== '' && $incomingSlug !== '' && $canonicalSlug !== $incomingSlug) {
				header('Location: ' . letters_url_letter($letter, $isEng), true, 301);
				exit;
			}
		}

		$images = [];
		$stImg = $db->prepare(
			'SELECT id, format, sort_order FROM images WHERE entity_type = ? AND entity_id = ? AND is_active = 1 '
			. 'ORDER BY sort_order ASC, id ASC'
		);
		$et = LETTERS_ENTITY_TYPE;
		if ($stImg) {
			$stImg->bind_param('ii', $et, $id);
			$stImg->execute();
			$rs = $stImg->get_result();
			while ($rs && ($img = $rs->fetch_assoc())) {
				$imgId = (int) ($img['id'] ?? 0);
				$fmt = (int) ($img['format'] ?? 1);
				if ($imgId <= 0) {
					continue;
				}
				$img['original_url'] = letters_public_original_url($imgId, $fmt);
				$img['preview_url'] = letters_public_preview_url($imgId);
				$prevPath = zx_storage_path('letters_preview', $imgId . '.jpg');
				$img['display_src'] = is_file($prevPath) ? $img['preview_url'] : $img['original_url'];
				$images[] = $img;
			}
			$stImg->close();
		}

		$smarty->assign('letter', $letter);
		$smarty->assign('letter_images', $images);
		$titlePlain = title_plain($letter['title_display']);
		$smarty->assign('title', $titlePlain);
		$descPlain = letters_public_meta_description($letter, $lng);
		$smarty->assign('description', $descPlain);

		$origin = zxpress_canonical_origin();
		$ogImage = '';
		if (!empty($images)) {
			$ogImage = $origin . $images[0]['preview_url'];
		}
		$smarty->assign('og_title', $titlePlain);
		$smarty->assign('og_description', $descPlain);
		$smarty->assign('og_image', $ogImage);
		$smarty->assign('og_url', $origin . $letter['public_url']);
		$smarty->assign('og_type', 'article');
		$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
		letters_assign_lang_switch_urls($smarty, $letter);
	}

	if (!isset($letter) || !$letter) {
		$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
		letters_assign_lang_switch_urls($smarty, null);
	}

	include 'right.php';
	$smarty->display('letters.tpl');
	exit;
}

// --- catalog (optional filter by author_from) ---

$author_filters = [];
$z = db_select(
	$db,
	'SELECT DISTINCT a.id, a.nickname, a.group_name '
	. 'FROM authors a '
	. 'INNER JOIN letters l ON l.author_from = a.id AND l.is_active = 1 '
	. 'WHERE COALESCE(a.is_active, 1) = 1 '
	. 'ORDER BY a.nickname ASC'
);
while ($z && ($row = mysqli_fetch_assoc($z))) {
	$row['author_display'] = letters_public_author_line(
		$row['nickname'] ?? null,
		$row['group_name'] ?? null,
		null,
		null,
		false,
	);
	$author_filters[] = $row;
}
$smarty->assign('letter_author_filters', $author_filters);
$smarty->assign('filter_from', $fromAuthor > 0 ? $fromAuthor : 0);

$filterFromAuthorDisplay = '';
if ($fromAuthor > 0) {
	foreach ($author_filters as $af) {
		if ((int) ($af['id'] ?? 0) === $fromAuthor) {
			$filterFromAuthorDisplay = (string) ($af['author_display'] ?? '');
			break;
		}
	}
	if ($filterFromAuthorDisplay === '') {
		$stAuth = $db->prepare('SELECT nickname, group_name FROM authors WHERE id = ? LIMIT 1');
		if ($stAuth) {
			$stAuth->bind_param('i', $fromAuthor);
			$stAuth->execute();
			$authRow = $stAuth->get_result()->fetch_assoc();
			$stAuth->close();
			if ($authRow) {
				$filterFromAuthorDisplay = letters_public_author_line(
					$authRow['nickname'] ?? null,
					$authRow['group_name'] ?? null,
					null,
					null,
					false,
				);
			}
		}
	}
}
$smarty->assign('filter_from_author_display', $filterFromAuthorDisplay);

$where = 'l.is_active = 1';
$types = '';
$params = [];
if ($fromAuthor > 0) {
	$where .= ' AND l.author_from = ?';
	$types .= 'i';
	$params[] = $fromAuthor;
}

$sqlCount = "SELECT COUNT(*) AS c FROM letters l WHERE $where";
$stmt = $db->prepare($sqlCount);
if (!$stmt) {
	http_response_code(500);
	die('Database error');
}
if ($types !== '') {
	$stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cntRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int) ($cntRow['c'] ?? 0);
$totalPages = max(1, (int) ceil($total / LETTERS_PER_PAGE));
if ($page > $totalPages) {
	$page = $totalPages;
	$offset = ($page - 1) * LETTERS_PER_PAGE;
}

$sqlList = 'SELECT l.*, '
	. 'af.nickname AS from_nick, at.nickname AS to_nick, '
	. 'af.group_name AS from_group_name, at.group_name AS to_group_name, '
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
	. 'LEFT JOIN countries cnt ON cnt.id = at.country_id '
	. "WHERE $where "
	. 'ORDER BY COALESCE(l.published_at, l.created_at) DESC, l.id DESC '
	. 'LIMIT ? OFFSET ?';
$typesList = $types . 'ii';
$paramsList = $params;
$paramsList[] = LETTERS_PER_PAGE;
$paramsList[] = $offset;

$stmt = $db->prepare($sqlList);
if (!$stmt) {
	http_response_code(500);
	die('Database error');
}
$stmt->bind_param($typesList, ...$paramsList);
$stmt->execute();
$z = $stmt->get_result();
$letters_rows = [];
while ($z && ($row = $z->fetch_assoc())) {
	$lid = (int) ($row['id'] ?? 0);
	$row = letters_public_enrich_row($row, $isEng);
	$cover = letters_public_first_cover($db, $lid);
	$row['cover'] = $cover;
	$letters_rows[] = $row;
}
$stmt->close();

$smarty->assign('letters_rows', $letters_rows);
$smarty->assign('letters_page', $page);
$smarty->assign('letters_total_pages', $totalPages);
$smarty->assign('letters_total', $total);
$smarty->assign('letter', null);
$smarty->assign('letter_not_found', false);

if ($filterFromAuthorDisplay !== '') {
	if ($isEng) {
		$smarty->assign('title', 'All letters from ' . $filterFromAuthorDisplay);
		$catalogDesc = 'Letters from ' . $filterFromAuthorDisplay . ' — paper correspondence of ZX Spectrum scene members.';
	} else {
		$smarty->assign('title', 'Все бумажные письма от ' . $filterFromAuthorDisplay);
		$catalogDesc = 'Бумажные письма от ' . $filterFromAuthorDisplay . ' — бумажная переписка участников ZX Spectrum сцены.';
	}
} else {
	if ($isEng) {
		$smarty->assign('title', 'Snailmail letters');
		$catalogDesc = 'Paper letters from the mid-1990s from members of the ZX Spectrum scene. Swapping and snailmail.';
	} else {
		$smarty->assign('title', 'Бумажные письма');
		$catalogDesc = 'Бумажные письма середины 90-х годов от участников ZX Spectrum сцены. Swapping и Snailmail.';
	}
}
$smarty->assign('description', $catalogDesc);

$origin = zxpress_canonical_origin();
$smarty->assign('og_title', $filterFromAuthorDisplay !== ''
	? ($isEng
		? 'All letters from ' . $filterFromAuthorDisplay
		: 'Все бумажные письма от ' . $filterFromAuthorDisplay)
	: ($isEng
		? 'Snailmail letters from the ZX Spectrum scene'
		: 'Бумажные письма участников ZX Spectrum сцены'));
$smarty->assign('og_description', $catalogDesc);
$smarty->assign('og_image', $origin . '/img/snailmail.png');
$catalogUrl = letters_url_catalog($isEng);
if ($fromAuthor > 0) {
	$catalogUrl .= '?from=' . $fromAuthor;
}
$smarty->assign('og_url', $origin . $catalogUrl);
$smarty->assign('og_type', 'website');
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
letters_assign_lang_switch_urls($smarty, null);

include 'right.php';
$smarty->display('letters.tpl');

