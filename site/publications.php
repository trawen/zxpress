<?php
require 'init.inc';

const PUB_IMAGE_ENTITY_TYPE = 2;
const PUB_ART_IMAGE_ENTITY_TYPE = 3;
const PUB_FILES_ENTITY_TYPE = 2;
const PUB_PER_PAGE = 20;
const PUB_TYPE_LABELS = [1 => 'Журнал', 2 => 'Книга', 3 => 'Газета'];

function pub_format_ext(int $format): string
{
	if ($format === 2) return 'png';
	if ($format === 3) return 'webp';
	if ($format === 4) return 'gif';
	return 'jpg';
}

function pub_cover_original_url(int $imageId, int $format): string
{
	return '/publications/' . $imageId . '.' . pub_format_ext($format);
}

function pub_cover_preview_url(int $imageId): string
{
	return '/publications/preview/' . $imageId . '.jpg';
}

function pub_file_ext(int $format): string
{
	if ($format === 2) return 'doc';
	if ($format === 3) return 'html';
	if ($format === 4) return 'txt';
	return 'pdf';
}

function pub_summary_html(?string $s): string
{
	$s = (string) $s;
	if ($s === '') return '';
	return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function pub_get_cover(mysqli $db, int $pubId): ?array
{
	$et = PUB_IMAGE_ENTITY_TYPE;
	$stmt = $db->prepare(
		'SELECT id, format FROM images WHERE entity_type=? AND entity_id=? AND is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 1'
	);
	if (!$stmt) return null;
	$stmt->bind_param('ii', $et, $pubId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$row) return null;

	$imgId = (int) ($row['id'] ?? 0);
	$fmt = (int) ($row['format'] ?? 1);
	if ($imgId <= 0) return null;

	$prev = pub_cover_preview_url($imgId);
	$prevPath = zx_storage_path('publications_preview', $imgId . '.jpg');
	return [
		'image_id' => $imgId,
		'format' => $fmt,
		'original_url' => pub_cover_original_url($imgId, $fmt),
		'preview_url' => $prev,
		'thumb_src' => is_file($prevPath) ? $prev : pub_cover_original_url($imgId, $fmt),
	];
}

function pub_get_article_files(mysqli $db, int $articleId): array
{
	$et = PUB_FILES_ENTITY_TYPE;
	$files = [];
	$stmt = $db->prepare('SELECT * FROM files_ WHERE entity_type=? AND entity_id=? AND is_active=1 ORDER BY id ASC');
	if (!$stmt) return $files;
	$stmt->bind_param('ii', $et, $articleId);
	$stmt->execute();
	$rs = $stmt->get_result();
	while ($rs && ($f = $rs->fetch_assoc())) {
		$fId = (int) ($f['id'] ?? 0);
		$fmt = (int) ($f['format'] ?? 1);
		if ($fId <= 0) continue;

		$ext = pub_file_ext($fmt);
		$basePath = zx_storage_dir('content_files');
		$actualExt = $ext;
		foreach ([$ext, 'docx', 'pdf', 'doc', 'html', 'txt'] as $tryExt) {
			if (is_file($basePath . '/' . $fId . '.' . $tryExt)) {
				$actualExt = $tryExt;
				break;
			}
		}

		$f['file_url'] = '/content-files/' . $fId . '.' . $actualExt;
		$f['format_label'] = strtoupper($actualExt);
		$sz = (int) ($f['size'] ?? 0);
		if ($sz >= 1048576) {
			$f['size_display'] = round($sz / 1048576, 1) . ' МБ';
		} elseif ($sz >= 1024) {
			$f['size_display'] = round($sz / 1024, 1) . ' КБ';
		} elseif ($sz > 0) {
			$f['size_display'] = $sz . ' Б';
		} else {
			$f['size_display'] = '';
		}
		$files[] = $f;
	}
	$stmt->close();
	return $files;
}

// --- routing ---

$id = (int) ($_GET['id'] ?? 0);
$articleId = (int) ($_GET['article'] ?? 0);
$typeFilter = (int) ($_GET['type'] ?? 0);
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * PUB_PER_PAGE;
$lng = $_GET['lng'] ?? null;

// =====================================================================
// Single publication view (with articles list)
// =====================================================================
if ($id > 0) {
	$stmt = $db->prepare(
		'SELECT p.*, c.country_name, ci.name AS city_name '
		. 'FROM publications p '
		. 'LEFT JOIN countries c ON c.id = p.country_id '
		. 'LEFT JOIN cities ci ON ci.id = p.city_id '
		. 'WHERE p.id = ? AND p.is_active = 1 LIMIT 1'
	);
	if (!$stmt) { http_response_code(500); die('Database error'); }
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$pub = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$pub) {
		http_response_code(404);
		$smarty->assign('pub', null);
		$smarty->assign('pub_not_found', true);
		$smarty->assign('title', 'Публикация не найдена');
		$smarty->assign('og_title', '');
		$smarty->assign('og_description', '');
		$smarty->assign('og_image', '');
		$smarty->assign('og_url', '');
		$smarty->assign('og_type', '');
	} else {
		$smarty->assign('pub_not_found', false);

		$pub['type_label'] = PUB_TYPE_LABELS[$pub['type']] ?? '';
		$pub['summary_html'] = pub_summary_html($pub['summary_ru'] ?? null);
		$pub['published_display'] = '';
		if (!empty($pub['published_at'])) {
			$pub['published_display'] = date('d.m.Y', strtotime((string) $pub['published_at']));
		}
		$pub['geo_display'] = '';
		$city = trim((string) ($pub['city_name'] ?? ''));
		$country = trim((string) ($pub['country_name'] ?? ''));
		if ($city !== '' && $country !== '') {
			$pub['geo_display'] = $city . ', ' . $country;
		} elseif ($country !== '') {
			$pub['geo_display'] = $country;
		} elseif ($city !== '') {
			$pub['geo_display'] = $city;
		}

		$cover = pub_get_cover($db, $id);
		$smarty->assign('pub_cover', $cover);

		// All images for detail view
		$pub_images = [];
		$stImg = $db->prepare(
			'SELECT id, format, sort_order FROM images WHERE entity_type = ? AND entity_id = ? AND is_active = 1 '
			. 'ORDER BY sort_order ASC, id ASC'
		);
		$et = PUB_IMAGE_ENTITY_TYPE;
		if ($stImg) {
			$stImg->bind_param('ii', $et, $id);
			$stImg->execute();
			$rs = $stImg->get_result();
			while ($rs && ($img = $rs->fetch_assoc())) {
				$imgId = (int) ($img['id'] ?? 0);
				$fmt = (int) ($img['format'] ?? 1);
				if ($imgId <= 0) continue;
				$img['original_url'] = pub_cover_original_url($imgId, $fmt);
				$img['preview_url'] = pub_cover_preview_url($imgId);
				$prevPath = zx_storage_path('publications_preview', $imgId . '.jpg');
				$img['display_src'] = is_file($prevPath) ? $img['preview_url'] : $img['original_url'];
				$pub_images[] = $img;
			}
			$stImg->close();
		}
		$smarty->assign('pub_images', $pub_images);

		// Articles
		$articles = [];
		$stArt = $db->prepare(
			'SELECT * FROM publication_articles WHERE publication_id = ? AND is_active = 1 ORDER BY COALESCE(page_from, 99999) ASC, id ASC'
		);
		if ($stArt) {
			$stArt->bind_param('i', $id);
			$stArt->execute();
			$rs = $stArt->get_result();
			while ($rs && ($a = $rs->fetch_assoc())) {
				$a['summary_html'] = pub_summary_html($a['summary_ru'] ?? null);
				$a['pages_display'] = '';
				$pf = (int) ($a['page_from'] ?? 0);
				$pt = (int) ($a['page_to'] ?? 0);
				if ($pf > 0 && $pt > 0) {
					$a['pages_display'] = 'стр. ' . $pf . '–' . $pt;
				} elseif ($pf > 0) {
					$a['pages_display'] = 'стр. ' . $pf;
				}
				$a['files'] = pub_get_article_files($db, (int) $a['id']);

				// First image as article cover
				$aId = (int) $a['id'];
				$artEt = PUB_ART_IMAGE_ENTITY_TYPE;
				$stCov = $db->prepare('SELECT id, format FROM images WHERE entity_type=? AND entity_id=? AND is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 1');
				$a['cover'] = null;
				if ($stCov) {
					$stCov->bind_param('ii', $artEt, $aId);
					$stCov->execute();
					$covRow = $stCov->get_result()->fetch_assoc();
					$stCov->close();
					if ($covRow) {
						$cImgId = (int) $covRow['id'];
						$cFmt = (int) $covRow['format'];
						$prevUrl = pub_cover_preview_url($cImgId);
						$prevPath = zx_storage_path('publications_preview', $cImgId . '.jpg');
						$a['cover'] = [
							'original_url' => pub_cover_original_url($cImgId, $cFmt),
							'thumb_src' => is_file($prevPath) ? $prevUrl : pub_cover_original_url($cImgId, $cFmt),
						];
					}
				}

				$articles[] = $a;
			}
			$stArt->close();
		}
		$smarty->assign('pub_articles', $articles);

		$smarty->assign('pub', $pub);
		$titlePlain = title_plain((string) ($pub['title_ru'] ?? ''));
		$smarty->assign('title', $titlePlain);
		$descPlain = title_plain(strip_tags((string) ($pub['summary_ru'] ?? '')));
		$smarty->assign('description', $descPlain);

		$origin = zxpress_canonical_origin();
		$ogImage = ($cover ? $origin . $cover['preview_url'] : '');
		$smarty->assign('og_title', $titlePlain);
		$smarty->assign('og_description', $descPlain);
		$smarty->assign('og_image', $ogImage);
		$smarty->assign('og_url', $origin . '/publications.php?id=' . $id);
		$smarty->assign('og_type', 'article');
	}

	// --- Single article detail view ---
	if ($articleId > 0 && !empty($pub)) {
		$stA = $db->prepare(
			'SELECT * FROM publication_articles WHERE id = ? AND publication_id = ? AND is_active = 1 LIMIT 1'
		);
		if ($stA) {
			$stA->bind_param('ii', $articleId, $id);
			$stA->execute();
			$artDetail = $stA->get_result()->fetch_assoc();
			$stA->close();
			if ($artDetail) {
				db_exec($db, 'UPDATE publication_articles SET view_count = view_count + 1 WHERE id = ? LIMIT 1', 'i', $articleId);
				$artDetail['summary_html'] = pub_summary_html($artDetail['summary_ru'] ?? null);
				$artDetail['body_html'] = pub_summary_html($artDetail['body_ru'] ?? null);
				$pf = (int) ($artDetail['page_from'] ?? 0);
				$pt = (int) ($artDetail['page_to'] ?? 0);
				$artDetail['pages_display'] = '';
				if ($pf > 0 && $pt > 0) {
					$artDetail['pages_display'] = 'стр. ' . $pf . '–' . $pt;
				} elseif ($pf > 0) {
					$artDetail['pages_display'] = 'стр. ' . $pf;
				}
				$artDetail['files'] = pub_get_article_files($db, $articleId);

				// Article images
				$artImages = [];
				$stAI = $db->prepare(
					'SELECT id, format, sort_order FROM images WHERE entity_type = ? AND entity_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC'
				);
				$artEt = PUB_ART_IMAGE_ENTITY_TYPE;
				if ($stAI) {
					$stAI->bind_param('ii', $artEt, $articleId);
					$stAI->execute();
					$rsAI = $stAI->get_result();
					while ($rsAI && ($aImg = $rsAI->fetch_assoc())) {
						$aImgId = (int) ($aImg['id'] ?? 0);
						$aFmt = (int) ($aImg['format'] ?? 1);
						if ($aImgId <= 0) continue;
						$aImg['original_url'] = pub_cover_original_url($aImgId, $aFmt);
						$aImg['preview_url'] = pub_cover_preview_url($aImgId);
						$prevPath = zx_storage_path('publications_preview', $aImgId . '.jpg');
						$aImg['display_src'] = is_file($prevPath) ? $aImg['preview_url'] : $aImg['original_url'];
						$artImages[] = $aImg;
					}
					$stAI->close();
				}
				$smarty->assign('pub_article_images', $artImages);

				$smarty->assign('pub_article_detail', $artDetail);

				$artTitle = title_plain((string) ($artDetail['title_ru'] ?? ''));
				$smarty->assign('title', $artTitle . ' — ' . $titlePlain);

				$origin = zxpress_canonical_origin();
				$smarty->assign('og_title', $artTitle);
				$smarty->assign('og_description', title_plain(strip_tags((string) ($artDetail['summary_ru'] ?? ''))));
				$smarty->assign('og_url', $origin . '/publications.php?id=' . $id . '&article=' . $articleId);
			}
		}
	}

	include 'right.php';
	$smarty->display('publications.tpl');
	exit;
}

// =====================================================================
// Catalog
// =====================================================================

// Type filters
$type_filters = [];
$z = db_select(
	$db,
	'SELECT DISTINCT p.type FROM publications p WHERE p.is_active = 1 ORDER BY p.type ASC'
);
while ($z && ($row = mysqli_fetch_assoc($z))) {
	$t = (int) $row['type'];
	if (isset(PUB_TYPE_LABELS[$t])) {
		$type_filters[] = ['type' => $t, 'label' => PUB_TYPE_LABELS[$t]];
	}
}
$smarty->assign('pub_type_filters', $type_filters);
$smarty->assign('filter_type', $typeFilter);

$where = 'p.is_active = 1';
$types = '';
$params = [];
if ($typeFilter > 0) {
	$where .= ' AND p.type = ?';
	$types .= 'i';
	$params[] = $typeFilter;
}

$sqlCount = "SELECT COUNT(*) AS c FROM publications p WHERE $where";
$stmt = $db->prepare($sqlCount);
if (!$stmt) { http_response_code(500); die('Database error'); }
if ($types !== '') {
	$stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cntRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int) ($cntRow['c'] ?? 0);
$totalPages = max(1, (int) ceil($total / PUB_PER_PAGE));
if ($page > $totalPages) {
	$page = $totalPages;
	$offset = ($page - 1) * PUB_PER_PAGE;
}

$sqlList = 'SELECT p.*, c.country_name, ci.name AS city_name '
	. 'FROM publications p '
	. 'LEFT JOIN countries c ON c.id = p.country_id '
	. 'LEFT JOIN cities ci ON ci.id = p.city_id '
	. "WHERE $where "
	. 'ORDER BY p.published_at DESC, p.id DESC '
	. 'LIMIT ? OFFSET ?';
$typesList = $types . 'ii';
$paramsList = $params;
$paramsList[] = PUB_PER_PAGE;
$paramsList[] = $offset;

$stmt = $db->prepare($sqlList);
if (!$stmt) { http_response_code(500); die('Database error'); }
$stmt->bind_param($typesList, ...$paramsList);
$stmt->execute();
$z = $stmt->get_result();
$pub_rows = [];
while ($z && ($row = $z->fetch_assoc())) {
	$pid = (int) ($row['id'] ?? 0);
	$row['type_label'] = PUB_TYPE_LABELS[$row['type']] ?? '';
	$row['summary_html'] = pub_summary_html($row['summary_ru'] ?? null);
	$row['published_display'] = '';
	if (!empty($row['published_at'])) {
		$row['published_display'] = date('d.m.Y', strtotime((string) $row['published_at']));
	}
	$row['created_display'] = '';
	if (!empty($row['created_at'])) {
		$row['created_display'] = date('d.m.Y', strtotime((string) $row['created_at']));
	}
	$row['geo_display'] = '';
	$city = trim((string) ($row['city_name'] ?? ''));
	$country = trim((string) ($row['country_name'] ?? ''));
	if ($city !== '' && $country !== '') {
		$row['geo_display'] = $city . ', ' . $country;
	} elseif ($country !== '') {
		$row['geo_display'] = $country;
	} elseif ($city !== '') {
		$row['geo_display'] = $city;
	}

	// Article count
	$stCnt = $db->prepare('SELECT COUNT(*) AS c FROM publication_articles WHERE publication_id = ? AND is_active = 1');
	if ($stCnt) {
		$stCnt->bind_param('i', $pid);
		$stCnt->execute();
		$cRow = $stCnt->get_result()->fetch_assoc();
		$row['articles_count'] = (int) ($cRow['c'] ?? 0);
		$stCnt->close();
	} else {
		$row['articles_count'] = 0;
	}

	$row['cover'] = pub_get_cover($db, $pid);
	$pub_rows[] = $row;
}
$stmt->close();

$smarty->assign('pub_rows', $pub_rows);
$smarty->assign('pub_page', $page);
$smarty->assign('pub_total_pages', $totalPages);
$smarty->assign('pub_total', $total);
$smarty->assign('pub', null);
$smarty->assign('pub_not_found', false);

$smarty->assign('title', 'Публикации');
$catalogDesc = 'Журналы, книги и газеты ZX Spectrum сцены.';
$smarty->assign('description', $catalogDesc);

$origin = zxpress_canonical_origin();
$smarty->assign('og_title', 'Публикации ZX Spectrum сцены');
$smarty->assign('og_description', $catalogDesc);
$smarty->assign('og_image', '');
$ogUrl = $origin . '/publications.php';
if ($typeFilter > 0) {
	$ogUrl .= '?type=' . $typeFilter;
}
$smarty->assign('og_url', $ogUrl);
$smarty->assign('og_type', 'website');

include 'right.php';
$smarty->display('publications.tpl');
