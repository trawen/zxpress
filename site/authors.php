<?php

define('LETTERS_SECTION', 'snailmail');
define('LETTERS_UI_VARIANT', 'new');

require 'init.inc';
require_once __DIR__ . '/includes/letters_publish.php';
require_once __DIR__ . '/includes/letters_public.php';

const AUTHORS_LETTERS_PER_PAGE = 10;

$lng = $smarty->getTemplateVars('lng');
$isEng = letters_public_is_eng($lng);

$authorSlug = trim((string) ($_GET['author_slug'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * AUTHORS_LETTERS_PER_PAGE;

letters_maybe_publish_next($db);

$origin = zxpress_canonical_origin();
$lettersCatalogUrl = letters_url_catalog($isEng);
$authorsCatalogUrl = authors_url_catalog($isEng);
$smarty->assign('letters_catalog_url', $lettersCatalogUrl);
$smarty->assign('authors_catalog_url', $authorsCatalogUrl);
$smarty->assign('smn_nav_authors_active', true);

// --- authors catalog ---
if ($authorSlug === '') {
	$authors = [];
	$z = db_select(
		$db,
		'SELECT a.id, a.nickname, a.group_name, a.name_ru, a.name_en, a.slug_ru, a.slug_en, '
		. 'c.name AS city_name, c.name_eng AS city_name_eng, '
		. 'co.country_name, co.country_name_eng, '
		. 'COUNT(DISTINCT l.id) AS letter_count '
		. 'FROM authors a '
		. 'INNER JOIN letters l ON l.is_active = 1 AND (l.author_from = a.id OR l.author_to = a.id) '
		. 'LEFT JOIN cities c ON c.id = a.city_id '
		. 'LEFT JOIN countries co ON co.id = a.country_id '
		. 'WHERE COALESCE(a.is_active, 1) = 1 '
		. 'GROUP BY a.id, a.nickname, a.group_name, a.name_ru, a.name_en, a.slug_ru, a.slug_en, '
		. 'c.name, c.name_eng, co.country_name, co.country_name_eng '
		. 'ORDER BY a.nickname ASC'
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$row['author_handle'] = letters_public_author_line(
			$row['nickname'] ?? null,
			$row['group_name'] ?? null,
			null,
			null,
			false,
		);
		$row['author_display'] = $row['author_handle'];
		$city = $isEng && trim((string) ($row['city_name_eng'] ?? '')) !== ''
			? (string) $row['city_name_eng']
			: trim((string) ($row['city_name'] ?? ''));
		$country = $isEng && trim((string) ($row['country_name_eng'] ?? '')) !== ''
			? (string) $row['country_name_eng']
			: trim((string) ($row['country_name'] ?? ''));
		$row['author_geo'] = ($city !== '' && $country !== '') ? ('(' . $city . ', ' . $country . ')') : '';
		$row['letter_count'] = (int) ($row['letter_count'] ?? 0);
		$row['letter_count_label'] = authors_letters_count_label($row['letter_count'], $isEng);
		$row['author_url'] = authors_url($row, $isEng);
		$authors[] = $row;
	}

	$title = $isEng ? 'Authors of articles, letters and publications' : 'Авторы статей, писем и публикаций';
	$desc = $isEng
		? 'Authors of articles, letters and publications on ZXPRESS.'
		: 'Авторы статей, писем и публикаций на ZXPRESS.';

	$smarty->assign('authors_catalog', true);
	$smarty->assign('author_not_found', false);
	$smarty->assign('author', null);
	$smarty->assign('authors_rows', $authors);
	$smarty->assign('authors_total', count($authors));
	$smarty->assign('letters_rows', []);
	$smarty->assign('title', $title);
	$smarty->assign('description', $desc);
	$smarty->assign('og_title', $title);
	$smarty->assign('og_description', $desc);
	$smarty->assign('og_image', $origin . '/img/snailmail.png');
	$smarty->assign('og_url', $origin . $authorsCatalogUrl);
	$smarty->assign('og_type', 'website');
	authors_assign_catalog_lang_switch_urls($smarty);
	$smarty->display('authors.tpl');
	exit;
}

// --- single author ---
$authorFilter = authors_resolve_filter($db, $authorSlug, $isEng);
$authorId = (int) ($authorFilter['id'] ?? 0);
$authorRow = $authorFilter['row'] ?? null;

if ($authorId <= 0 || !$authorRow) {
	http_response_code(404);
	$smarty->assign('authors_catalog', false);
	$smarty->assign('author', null);
	$smarty->assign('author_not_found', true);
	$smarty->assign('authors_rows', []);
	$smarty->assign('letters_rows', []);
	$smarty->assign('letters_page', 1);
	$smarty->assign('letters_total_pages', 1);
	$smarty->assign('letters_total', 0);
	$smarty->assign('title', $isEng ? 'Author not found' : 'Автор не найден');
	$smarty->assign('description', '');
	$smarty->assign('og_title', '');
	$smarty->assign('og_description', '');
	$smarty->assign('og_image', '');
	$smarty->assign('og_url', '');
	$smarty->assign('og_type', '');
	letters_assign_lang_switch_urls($smarty, null);
	$smarty->display('authors.tpl');
	exit;
}

$canonicalSlug = authors_row_slug($authorRow, $isEng);
$incomingSlug = letters_slug_normalize_path($authorSlug);
if ($canonicalSlug !== '' && $incomingSlug !== '' && $canonicalSlug !== $incomingSlug) {
	header('Location: ' . authors_url($authorRow, $isEng, $page), true, 301);
	exit;
}

$stAuth = $db->prepare(
	'SELECT a.id, a.nickname, a.name_ru, a.name_en, a.group_name, a.slug_ru, a.slug_en, '
	. 'c.name AS city_name, c.name_eng AS city_name_eng, '
	. 'co.country_name, co.country_name_eng '
	. 'FROM authors a '
	. 'LEFT JOIN cities c ON c.id = a.city_id '
	. 'LEFT JOIN countries co ON co.id = a.country_id '
	. 'WHERE a.id = ? LIMIT 1'
);
if ($stAuth) {
	$stAuth->bind_param('i', $authorId);
	$stAuth->execute();
	$full = $stAuth->get_result()->fetch_assoc();
	$stAuth->close();
	if ($full) {
		$authorRow = $full;
	}
}

$authorHandle = letters_public_author_line(
	$authorRow['nickname'] ?? null,
	$authorRow['group_name'] ?? null,
	null,
	null,
	false,
);
$authorPerson = letters_public_author_person_name(
	$authorRow['name_en'] ?? null,
	$authorRow['name_ru'] ?? null,
	$isEng,
);
$authorDisplay = letters_public_author_line(
	$authorRow['nickname'] ?? null,
	$authorRow['group_name'] ?? null,
	null,
	null,
	false,
	$authorPerson,
);
$city = $isEng && trim((string) ($authorRow['city_name_eng'] ?? '')) !== ''
	? (string) $authorRow['city_name_eng']
	: trim((string) ($authorRow['city_name'] ?? ''));
$country = $isEng && trim((string) ($authorRow['country_name_eng'] ?? '')) !== ''
	? (string) $authorRow['country_name_eng']
	: trim((string) ($authorRow['country_name'] ?? ''));
$authorGeo = ($city !== '' && $country !== '') ? ($city . ', ' . $country) : '';

$authorPageUrl = authors_url($authorRow, $isEng, $page);
$authorCanonicalUrl = authors_url($authorRow, $isEng);

$sqlCount = 'SELECT COUNT(*) AS c FROM letters l '
	. 'WHERE l.is_active = 1 AND (l.author_from = ? OR l.author_to = ?)';
$stmt = $db->prepare($sqlCount);
if (!$stmt) {
	http_response_code(500);
	die('Database error');
}
$stmt->bind_param('ii', $authorId, $authorId);
$stmt->execute();
$total = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int) ceil($total / AUTHORS_LETTERS_PER_PAGE));
if ($page > $totalPages) {
	$page = $totalPages;
	$offset = ($page - 1) * AUTHORS_LETTERS_PER_PAGE;
	$authorPageUrl = authors_url($authorRow, $isEng, $page);
}

$sqlList = letters_public_list_select_sql()
	. 'WHERE l.is_active = 1 AND (l.author_from = ? OR l.author_to = ?) '
	. 'ORDER BY COALESCE(l.published_at, l.created_at) DESC, l.id DESC '
	. 'LIMIT ? OFFSET ?';
$stmt = $db->prepare($sqlList);
if (!$stmt) {
	http_response_code(500);
	die('Database error');
}
$perPage = AUTHORS_LETTERS_PER_PAGE;
$stmt->bind_param('iiii', $authorId, $authorId, $perPage, $offset);
$stmt->execute();
$z = $stmt->get_result();
$letters_rows = [];
while ($z && ($row = $z->fetch_assoc())) {
	$lid = (int) ($row['id'] ?? 0);
	$row = letters_public_enrich_row($row, $isEng);
	$row['cover'] = letters_public_first_cover($db, $lid);
	$letters_rows[] = $row;
}
$stmt->close();

if ($isEng) {
	$title = $authorDisplay;
	$desc = 'Paper letters involving ' . $authorDisplay . ' — ZX Spectrum scene correspondence.';
} else {
	$title = $authorDisplay;
	$desc = 'Бумажные письма с участием ' . $authorDisplay . ' — переписка участников ZX Spectrum сцены.';
}
if ($authorGeo !== '') {
	$title .= ' (' . $authorGeo . ')';
}

$smarty->assign('authors_catalog', false);
$smarty->assign('author_not_found', false);
$smarty->assign('author', $authorRow);
$smarty->assign('author_display', $authorDisplay);
$smarty->assign('author_handle', $authorHandle);
$smarty->assign('author_person', $authorPerson);
$smarty->assign('author_geo', $authorGeo);
$smarty->assign('author_page_url', $authorPageUrl);
$smarty->assign('author_canonical_url', $authorCanonicalUrl);
$smarty->assign('authors_rows', []);
$smarty->assign('letters_rows', $letters_rows);
$smarty->assign('letters_page', $page);
$smarty->assign('letters_total_pages', $totalPages);
$smarty->assign('letters_total', $total);
$smarty->assign('title', $title);
$smarty->assign('description', $desc);
$smarty->assign('og_title', $title);
$smarty->assign('og_description', $desc);
$smarty->assign('og_image', $origin . '/img/snailmail.png');
$smarty->assign('og_url', $origin . $authorCanonicalUrl);
$smarty->assign('og_type', 'profile');
authors_assign_lang_switch_urls($smarty, $authorRow, $page);

$smarty->display('authors.tpl');
