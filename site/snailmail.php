<?php
require 'init.inc';
require_once __DIR__ . '/includes/letters_publish.php';
require_once __DIR__ . '/includes/letters_public.php';

if (!defined('LETTERS_PER_PAGE')) {
	define('LETTERS_PER_PAGE', 20);
}

function letters_ui_is_new(): bool
{
	return !defined('LETTERS_UI_VARIANT') || LETTERS_UI_VARIANT === 'new';
}

function letters_ui_template(): string
{
	return letters_ui_is_new() ? 'letters_new.tpl' : 'letters.tpl';
}

function letters_ui_render($smarty): void
{
	$lng = $smarty->getTemplateVars('lng');
	$isEng = letters_public_is_eng(is_string($lng) ? $lng : null);
	if (!$smarty->getTemplateVars('authors_catalog_url')) {
		$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
	}
	if ($smarty->getTemplateVars('smn_nav_authors_active') === null) {
		$smarty->assign('smn_nav_authors_active', false);
	}
	if (!letters_ui_is_new()) {
		global $db;
		include __DIR__ . '/right.php';
	}
	$smarty->display(letters_ui_template());
}

$lng = $smarty->getTemplateVars('lng');
$isEng = letters_public_is_eng($lng);

$letterSlug = trim((string) ($_GET['letter_slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);
$authorParam = trim((string) ($_GET['author'] ?? ''));
if ($authorParam === '' && isset($_GET['from'])) {
	$authorParam = trim((string) $_GET['from']);
}
$authorFilter = authors_resolve_filter($db, $authorParam, $isEng);
$filterAuthor = (int) ($authorFilter['id'] ?? 0);
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * LETTERS_PER_PAGE;

// Legacy ?author= / ?from= on catalog → /{lang}/authors/{slug}
if (
	$letterSlug === ''
	&& $id <= 0
	&& $filterAuthor > 0
	&& !empty($authorFilter['row'])
) {
	header('Location: ' . authors_url($authorFilter['row'], $isEng, $page), true, 301);
	exit;
}

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
		letters_ui_render($smarty);
		exit;
	}
}

letters_maybe_redirect_legacy($db, $letterSlug !== '', $id, $isEng);

if ($id > 0) {
	$stmt = $db->prepare(
		'SELECT l.*, '
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

	letters_ui_render($smarty);
	exit;
}

// --- catalog ---

$author_filters = [];
$z = db_select(
	$db,
	'SELECT a.id, a.nickname, a.group_name, a.name_ru, a.name_en, a.slug_ru, a.slug_en, COUNT(DISTINCT l.id) AS letter_count '
	. 'FROM authors a '
	. 'INNER JOIN letters l ON l.is_active = 1 AND (l.author_from = a.id OR l.author_to = a.id) '
	. 'WHERE COALESCE(a.is_active, 1) = 1 '
	. 'GROUP BY a.id, a.nickname, a.group_name, a.name_ru, a.name_en, a.slug_ru, a.slug_en '
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
	$row['letter_count'] = (int) ($row['letter_count'] ?? 0);
	$row['author_slug'] = authors_row_slug($row, $isEng);
	if ($row['author_slug'] === '') {
		$row['author_slug'] = (string) (int) ($row['id'] ?? 0);
	}
	$row['author_url'] = authors_url($row, $isEng);
	$author_filters[] = $row;
}
$smarty->assign('letter_author_filters', $author_filters);
$smarty->assign('filter_author', 0);
$smarty->assign('filter_author_slug', '');
$smarty->assign('filter_author_display', '');
$smarty->assign('filter_from', 0);
$smarty->assign('filter_from_author_display', '');

$where = 'l.is_active = 1';
$sqlCount = "SELECT COUNT(*) AS c FROM letters l WHERE $where";
$stmt = $db->prepare($sqlCount);
if (!$stmt) {
	http_response_code(500);
	die('Database error');
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

$sqlList = letters_public_list_select_sql()
	. "WHERE $where "
	. 'ORDER BY COALESCE(l.published_at, l.created_at) DESC, l.id DESC '
	. 'LIMIT ? OFFSET ?';
$stmt = $db->prepare($sqlList);
if (!$stmt) {
	http_response_code(500);
	die('Database error');
}
$perPage = LETTERS_PER_PAGE;
$stmt->bind_param('ii', $perPage, $offset);
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

$smarty->assign('letters_rows', $letters_rows);
$smarty->assign('letters_page', $page);
$smarty->assign('letters_total_pages', $totalPages);
$smarty->assign('letters_total', $total);
$smarty->assign('letters_prev_page', $page > 1 ? $page - 1 : 0);
$smarty->assign('letters_next_page', $page < $totalPages ? $page + 1 : 0);
$smarty->assign('letter', null);
$smarty->assign('letter_not_found', false);

if ($isEng) {
	$smarty->assign('title', 'Snailmail letters');
	$catalogDesc = 'Paper letters from the mid-1990s from members of the ZX Spectrum scene. Swapping and snailmail.';
} else {
	$smarty->assign('title', 'Бумажные письма');
	$catalogDesc = 'Бумажные письма середины 90-х годов от участников ZX Spectrum сцены. Swapping и Snailmail.';
}
$smarty->assign('description', $catalogDesc);

$origin = zxpress_canonical_origin();
$catalogUrl = letters_url_catalog($isEng);
$smarty->assign('og_title', $isEng
	? 'Snailmail letters from the ZX Spectrum scene'
	: 'Бумажные письма участников ZX Spectrum сцены');
$smarty->assign('og_description', $catalogDesc);
$smarty->assign('og_image', $origin . '/img/snailmail.png');
$smarty->assign('og_url', $origin . $catalogUrl);
$smarty->assign('og_type', 'website');
$smarty->assign('letters_catalog_url', $catalogUrl);
letters_assign_lang_switch_urls($smarty, null);

letters_ui_render($smarty);
