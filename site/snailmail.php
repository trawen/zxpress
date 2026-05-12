<?php
require 'init.inc';

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
	return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
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

$id = (int) ($_GET['id'] ?? 0);
$fromAuthor = (int) ($_GET['from'] ?? 0);
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * LETTERS_PER_PAGE;

$lng = $_GET['lng'] ?? null;

if ($id > 0) {
	$stmt = $db->prepare(
		'SELECT l.*, '
		. 'af.nickname AS from_nick, at.nickname AS to_nick, '
		. 'af.group_name AS from_group_name, at.group_name AS to_group_name, '
		. 'cfn.name AS from_city_name, cnf.country_name AS from_country_name, '
		. 'ctn.name AS to_city_name, cnt.country_name AS to_country_name '
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
		$smarty->assign('title', 'Письмо не найдено');
		$smarty->assign('og_title', '');
		$smarty->assign('og_description', '');
		$smarty->assign('og_image', '');
		$smarty->assign('og_url', '');
		$smarty->assign('og_type', '');
	} else {
		$smarty->assign('letter_not_found', false);
		db_exec($db, 'UPDATE letters SET view_count = view_count + 1 WHERE id = ? LIMIT 1', 'i', $id);

		$letter['date_display'] = '';
		if (!empty($letter['date'])) {
			$letter['date_display'] = date('d.m.Y', strtotime((string) $letter['date']));
		}
		$letter['summary_html'] = letters_public_summary_html($letter['summary_ru'] ?? null);
		$letter['body_html'] = letters_public_summary_html($letter['body_ru'] ?? null);
		$letter['from_author_display'] = letters_public_author_line(
			$letter['from_nick'] ?? null,
			$letter['from_group_name'] ?? null,
			$letter['from_city_name'] ?? null,
			$letter['from_country_name'] ?? null,
		);
		$letter['to_author_display'] = letters_public_author_line(
			$letter['to_nick'] ?? null,
			$letter['to_group_name'] ?? null,
			$letter['to_city_name'] ?? null,
			$letter['to_country_name'] ?? null,
		);

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
		$titlePlain = title_plain((string) ($letter['title_ru'] ?? ''));
		$smarty->assign('title', $titlePlain);
		$descPlain = title_plain(strip_tags((string) ($letter['summary_ru'] ?? '')));
		$smarty->assign('description', $descPlain);

		$origin = zxpress_canonical_origin();
		$ogImage = '';
		if (!empty($images)) {
			$ogImage = $origin . $images[0]['preview_url'];
		}
		$smarty->assign('og_title', $titlePlain);
		$smarty->assign('og_description', $descPlain);
		$smarty->assign('og_image', $ogImage);
		$smarty->assign('og_url', $origin . '/snailmail.php?id=' . $id);
		$smarty->assign('og_type', 'article');
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
	. 'ctn.name AS to_city_name, cnt.country_name AS to_country_name '
	. 'FROM letters l '
	. 'LEFT JOIN authors af ON af.id = l.author_from '
	. 'LEFT JOIN authors at ON at.id = l.author_to '
	. 'LEFT JOIN cities cfn ON cfn.id = af.city_id '
	. 'LEFT JOIN countries cnf ON cnf.id = af.country_id '
	. 'LEFT JOIN cities ctn ON ctn.id = at.city_id '
	. 'LEFT JOIN countries cnt ON cnt.id = at.country_id '
	. "WHERE $where "
	. 'ORDER BY (l.date IS NULL) ASC, l.date DESC, l.id DESC '
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
	$row['date_display'] = '';
	if (!empty($row['date'])) {
		$row['date_display'] = date('d.m.Y', strtotime((string) $row['date']));
	}
	$row['published_display'] = '';
	if (!empty($row['created_at'])) {
		$row['published_display'] = date('d.m.Y', strtotime((string) $row['created_at']));
	}
	$row['summary_html'] = letters_public_summary_html($row['summary_ru'] ?? null);
	$row['from_author_display'] = letters_public_author_line(
		$row['from_nick'] ?? null,
		$row['from_group_name'] ?? null,
		$row['from_city_name'] ?? null,
		$row['from_country_name'] ?? null,
	);
	$row['to_author_display'] = letters_public_author_line(
		$row['to_nick'] ?? null,
		$row['to_group_name'] ?? null,
		$row['to_city_name'] ?? null,
		$row['to_country_name'] ?? null,
	);
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

$smarty->assign('title', 'Письма');
$catalogDesc = 'Бумажные письма середины 90-х годов от участников ZX Spectrum сцены. Swapping и Snailmail.';
$smarty->assign('description', $catalogDesc);

$origin = zxpress_canonical_origin();
$smarty->assign('og_title', 'Бумажные письма участников ZX Spectrum сцены');
$smarty->assign('og_description', $catalogDesc);
$smarty->assign('og_image', $origin . '/img/snailmail.png');
$ogUrl = $origin . '/snailmail.php';
if ($fromAuthor > 0) {
	$ogUrl .= '?from=' . $fromAuthor;
}
$smarty->assign('og_url', $ogUrl);
$smarty->assign('og_type', 'website');

include 'right.php';
$smarty->display('letters.tpl');

