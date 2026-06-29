<?php
require 'init.inc';
require_once __DIR__ . '/includes/periodical_issue_images.php';
require_once __DIR__ . '/includes/periodical_issue_files.php';
require_once __DIR__ . '/includes/periodicals_slugs.php';

function per_pub_is_eng(?string $lng): bool
{
	return $lng === 'eng';
}

function per_pub_pick(?string $en, ?string $ru, bool $isEng): string
{
	if ($isEng && trim((string) $en) !== '') {
		return (string) $en;
	}
	return (string) ($ru ?? '');
}

function per_pub_summary_html(?string $s): string
{
	$s = (string) $s;
	if ($s === '') {
		return '';
	}
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function per_pub_rich_html(?string $s): string
{
	$s = (string) $s;
	if ($s === '') {
		return '';
	}
	if (strpos($s, '<') !== false && strpos($s, '>') !== false) {
		return $s;
	}
	return per_pub_summary_html($s);
}

function per_pub_issue_label(array $issue, bool $isEng): string
{
	$title = per_pub_pick($issue['title_en'] ?? null, $issue['title_ru'] ?? null, $isEng);
	if ($title !== '') {
		return $title;
	}
	$no = trim((string) ($issue['issue_no'] ?? ''));
	$label = ($isEng ? 'No. ' : '№') . $no;
	if (!empty($issue['issue_year'])) {
		$label .= ' (' . (int) $issue['issue_year'] . ')';
	}
	return $label;
}

function per_pub_issue_preview_caption(array $issue, bool $isEng): string
{
	$year = (int) ($issue['issue_year'] ?? 0);
	$no = trim((string) ($issue['issue_no'] ?? ''));
	$parts = [];
	if ($year > 0) {
		$parts[] = (string) $year;
	}
	if ($no !== '') {
		$parts[] = ($isEng ? 'No. ' : '№') . $no;
	}
	if ($parts === []) {
		return per_pub_issue_label($issue, $isEng);
	}
	return implode(' ', $parts);
}

function per_pub_pages_display(?int $start, ?int $end, bool $isEng): string
{
	$start = (int) $start;
	$end = (int) $end;
	if ($start > 0 && $end > 0) {
		return ($isEng ? 'pp. ' : 'стр. ') . $start . '–' . $end;
	}
	if ($start > 0) {
		return ($isEng ? 'p. ' : 'стр. ') . $start;
	}
	return '';
}

function per_pub_date_display(?string $date, bool $isEng): string
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

function per_pub_get_issue_cover(int $issueId): ?array
{
	if ($issueId <= 0 || !per_issue_has_cover($issueId)) {
		return null;
	}
	per_issue_ensure_webp_previews($issueId);

	return [
		'jpg_url' => '/periodical-issues/' . $issueId . '.jpg',
		'thumb_src' => per_issue_cover_webp_url($issueId, 640),
		'display_src' => per_issue_cover_webp_url($issueId, 1280),
	];
}

function per_pub_load_issue_files(mysqli $db, int $issueId): array
{
	$formats = per_issue_file_format_options();
	$files = [];
	foreach (per_issue_load_files($db, $issueId) as $f) {
		if ((int) ($f['is_active'] ?? 1) !== 1) {
			continue;
		}
		if (($f['file_url'] ?? '') === '') {
			continue;
		}
		$fmt = (int) ($f['format'] ?? 0);
		$f['format_label'] = $formats[$fmt] ?? strtoupper(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
		$files[] = $f;
	}
	return $files;
}

function per_pub_load_publishers(mysqli $db, int $periodicalId): array
{
	$publishers = [];
	$z = db_select(
		$db,
		'SELECT pub.name_ru, pub.name_en FROM periodical_publishers pp '
		. 'INNER JOIN publishers pub ON pub.id = pp.publisher_id '
		. 'WHERE pp.periodical_id = ? ORDER BY pub.name_ru ASC',
		'i',
		$periodicalId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$publishers[] = $row;
	}
	return $publishers;
}

function per_pub_enrich_periodical(array $row, bool $isEng): array
{
	$row['title_display'] = per_pub_pick($row['title_en'] ?? null, $row['title_ru'] ?? null, $isEng);
	$row['description_html'] = per_pub_summary_html(
		per_pub_pick($row['description_en'] ?? null, $row['description_ru'] ?? null, $isEng)
	);
	$city = $isEng && trim((string) ($row['city_name_eng'] ?? '')) !== ''
		? $row['city_name_eng']
		: ($row['city_name'] ?? '');
	$country = $isEng && trim((string) ($row['country_name_eng'] ?? '')) !== ''
		? $row['country_name_eng']
		: ($row['country_name'] ?? '');
	$row['geo_display'] = '';
	$city = trim((string) $city);
	$country = trim((string) $country);
	if ($city !== '' && $country !== '') {
		$row['geo_display'] = $city . ', ' . $country;
	} elseif ($country !== '') {
		$row['geo_display'] = $country;
	} elseif ($city !== '') {
		$row['geo_display'] = $city;
	}
	$ys = (int) ($row['year_start'] ?? 0);
	$ye = (int) ($row['year_end'] ?? 0);
	$row['years_display'] = '';
	if ($ys > 0 && $ye > 0 && $ys !== $ye) {
		$row['years_display'] = $ys . '–' . $ye;
	} elseif ($ys > 0) {
		$row['years_display'] = (string) $ys;
	} elseif ($ye > 0) {
		$row['years_display'] = (string) $ye;
	}
	return $row;
}

$perSlug = per_slug_normalize_path((string) ($_GET['per'] ?? ''));
$issueSlug = per_slug_normalize_path((string) ($_GET['issue_slug'] ?? ''));
$articleSlug = per_slug_normalize_path((string) ($_GET['article_slug'] ?? ''));
$slugRoute = ($perSlug !== '');

$id = (int) ($_GET['id'] ?? 0);
$issueId = (int) ($_GET['issue'] ?? 0);
$articleId = (int) ($_GET['article'] ?? 0);

if ($slugRoute) {
	$resolved = per_pub_resolve_route($db, $perSlug, $issueSlug, $articleSlug, per_pub_is_eng($smarty->getTemplateVars('lng')));
	if (!$resolved['ok']) {
		http_response_code(404);
		$smarty->assign('periodical', null);
		$smarty->assign('per_not_found', true);
		$smarty->assign('title', per_pub_is_eng($smarty->getTemplateVars('lng')) ? 'Periodical not found' : 'Издание не найдено');
		$smarty->assign('per_url_catalog', per_pub_url_catalog(per_pub_is_eng($smarty->getTemplateVars('lng'))));
		per_pub_assign_lang_switch_urls($smarty, null, null, null);
		include 'right.php';
		$smarty->display('periodicals.tpl');
		exit;
	}
	$id = (int) $resolved['periodical_id'];
	$issueId = (int) $resolved['issue_id'];
	$articleId = (int) $resolved['article_id'];
} else {
	per_pub_maybe_redirect_legacy($db, false, $id, $issueId, $articleId, per_pub_is_eng($smarty->getTemplateVars('lng')));
}

$lng = $smarty->getTemplateVars('lng');
$isEng = per_pub_is_eng($lng);
$smarty->assign('per_url_catalog', per_pub_url_catalog($isEng));

// --- single periodical / issue / article ---
if ($id > 0) {
	$stmt = $db->prepare(
		'SELECT p.*, ci.name AS city_name, ci.name_eng AS city_name_eng, '
		. 'co.country_name, co.country_name_eng '
		. 'FROM periodicals p '
		. 'LEFT JOIN cities ci ON ci.id = p.city_id '
		. 'LEFT JOIN countries co ON co.id = ci.country_id '
		. 'WHERE p.id = ? AND p.is_active = 1 LIMIT 1'
	);
	if (!$stmt) {
		http_response_code(500);
		die('Database error');
	}
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$periodical = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$periodical) {
		http_response_code(404);
		$smarty->assign('periodical', null);
		$smarty->assign('per_not_found', true);
		$smarty->assign('title', $isEng ? 'Periodical not found' : 'Издание не найдено');
		per_pub_assign_lang_switch_urls($smarty, null, null, null);
	} else {
		$smarty->assign('per_not_found', false);
		$periodical = per_pub_enrich_periodical($periodical, $isEng);
		$periodical['publishers'] = per_pub_load_publishers($db, $id);
		$periodical['publishers_display'] = implode(', ', array_map(
			static fn(array $p): string => per_pub_pick($p['name_en'] ?? null, $p['name_ru'] ?? null, $isEng),
			$periodical['publishers']
		));
		$smarty->assign('periodical', $periodical);

		$titlePlain = title_plain($periodical['title_display']);
		$smarty->assign('title', $titlePlain);
		$metaEn = title_plain((string) ($periodical['meta_description_en'] ?? ''));
		$metaRu = title_plain((string) ($periodical['meta_description_ru'] ?? ''));
		if ($isEng && $metaEn !== '') {
			$descPlain = $metaEn;
		} elseif ($metaRu !== '') {
			$descPlain = $metaRu;
		} else {
			$descPlain = title_plain(strip_tags($periodical['description_html'] ?? ''));
		}
		$smarty->assign('description', $descPlain !== '' ? $descPlain : $titlePlain);

		$origin = zxpress_canonical_origin();
		$smarty->assign('og_title', $titlePlain);
		$smarty->assign('og_description', $descPlain !== '' ? $descPlain : $titlePlain);
		$smarty->assign('og_image', '');
		$smarty->assign('og_url', $origin . per_pub_url_periodical($periodical, $isEng));
		$smarty->assign('og_type', 'website');

		$articleLoaded = false;
		// --- article detail ---
		if ($articleId > 0 && $issueId > 0) {
			$stmt = $db->prepare(
				'SELECT pa.* FROM periodical_articles pa '
				. 'INNER JOIN periodical_issues pi ON pi.id = pa.issue_id AND pi.periodical_id = ? '
				. 'WHERE pa.id = ? AND pa.issue_id = ? AND pa.is_active = 1 AND pi.is_active = 1 LIMIT 1'
			);
			if ($stmt) {
				$stmt->bind_param('iii', $id, $articleId, $issueId);
				$stmt->execute();
				$article = $stmt->get_result()->fetch_assoc();
				$stmt->close();
				if ($article) {
					$articleLoaded = true;
					db_exec($db, 'UPDATE periodical_articles SET views = views + 1 WHERE id = ? LIMIT 1', 'i', $articleId);
					$article['title_display'] = per_pub_pick($article['title_en'] ?? null, $article['title_ru'] ?? null, $isEng);
					$article['abstract_html'] = per_pub_rich_html(
						per_pub_pick($article['abstract_en'] ?? null, $article['abstract_ru'] ?? null, $isEng)
					);
					$article['body_html'] = per_pub_rich_html(
						per_pub_pick($article['text_en'] ?? null, $article['text_ru'] ?? null, $isEng)
					);
					$article['pages_display'] = per_pub_pages_display(
						isset($article['page_start']) ? (int) $article['page_start'] : 0,
						isset($article['page_end']) ? (int) $article['page_end'] : 0,
						$isEng
					);
					$smarty->assign('per_article', $article);

					$issueRow = null;
					$issueStmt = $db->prepare('SELECT * FROM periodical_issues WHERE id = ? AND periodical_id = ? AND is_active = 1 LIMIT 1');
					if ($issueStmt) {
						$issueStmt->bind_param('ii', $issueId, $id);
						$issueStmt->execute();
						$issueRow = $issueStmt->get_result()->fetch_assoc();
						$issueStmt->close();
						if ($issueRow) {
							$issueRow['label'] = per_pub_issue_label($issueRow, $isEng);
							$issueRow['preview_caption'] = per_pub_issue_preview_caption($issueRow, $isEng);
							$issueRow['url'] = per_pub_url_issue($periodical, $issueRow, $isEng);
							$smarty->assign('per_issue', $issueRow);
						}
					}

					$artTitle = title_plain($article['title_display']);
					$smarty->assign('title', $artTitle . ' — ' . $titlePlain);
					$metaEn = title_plain((string) ($article['meta_description_en'] ?? ''));
					$metaRu = title_plain((string) ($article['meta_description_ru'] ?? ''));
					$artDesc = ($isEng && $metaEn !== '') ? $metaEn : ($metaRu !== '' ? $metaRu : title_plain(strip_tags($article['abstract_html'] ?? '')));
					$smarty->assign('description', $artDesc);
					$smarty->assign('og_title', $artTitle);
					$smarty->assign('og_description', $artDesc);
					if ($issueRow) {
						$smarty->assign('og_url', $origin . per_pub_url_article($periodical, $issueRow, $article, $isEng));
					}
					$smarty->assign('og_type', 'article');
					$smarty->assign('per_url_periodical', per_pub_url_periodical($periodical, $isEng));
					per_pub_assign_lang_switch_urls($smarty, $periodical, $issueRow, $article);
				}
			}
		}
		// --- issue detail ---
		if (!$articleLoaded && $issueId > 0) {
			$stmt = $db->prepare(
				'SELECT * FROM periodical_issues WHERE id = ? AND periodical_id = ? AND is_active = 1 LIMIT 1'
			);
			if ($stmt) {
				$stmt->bind_param('ii', $issueId, $id);
				$stmt->execute();
				$issue = $stmt->get_result()->fetch_assoc();
				$stmt->close();
				if ($issue) {
					$issue['label'] = per_pub_issue_label($issue, $isEng);
					$issue['preview_caption'] = per_pub_issue_preview_caption($issue, $isEng);
					$issue['title_display'] = per_pub_pick($issue['title_en'] ?? null, $issue['title_ru'] ?? null, $isEng);
					$issue['description_html'] = per_pub_summary_html(
						per_pub_pick($issue['description_en'] ?? null, $issue['description_ru'] ?? null, $isEng)
					);
					$issue['date_display'] = per_pub_date_display($issue['issue_date'] ?? null, $isEng);
					$issue['cover'] = per_pub_get_issue_cover($issueId);
					$issue['files'] = per_pub_load_issue_files($db, $issueId);
					$issue['url'] = per_pub_url_issue($periodical, $issue, $isEng);
					$smarty->assign('per_issue', $issue);

					$articles = [];
					$z = db_select(
						$db,
						'SELECT * FROM periodical_articles WHERE issue_id = ? AND is_active = 1 ORDER BY sort_order ASC, page_start ASC, id ASC',
						'i',
						$issueId
					);
					while ($z && ($a = mysqli_fetch_assoc($z))) {
						$a['title_display'] = per_pub_pick($a['title_en'] ?? null, $a['title_ru'] ?? null, $isEng);
						$a['abstract_html'] = per_pub_summary_html(
							per_pub_pick($a['abstract_en'] ?? null, $a['abstract_ru'] ?? null, $isEng)
						);
						$a['pages_display'] = per_pub_pages_display(
							isset($a['page_start']) ? (int) $a['page_start'] : 0,
							isset($a['page_end']) ? (int) $a['page_end'] : 0,
							$isEng
						);
						$a['url'] = per_pub_url_article($periodical, $issue, $a, $isEng);
						$articles[] = $a;
					}
					$smarty->assign('per_articles', $articles);

					$issueTitle = title_plain($issue['label']);
					$smarty->assign('title', $issueTitle . ' — ' . $titlePlain);
					$smarty->assign('og_title', $issueTitle . ' — ' . $titlePlain);
					$issueMetaEn = title_plain((string) ($issue['meta_description_en'] ?? ''));
					$issueMetaRu = title_plain((string) ($issue['meta_description_ru'] ?? ''));
					if ($isEng && $issueMetaEn !== '') {
						$issueDesc = $issueMetaEn;
					} elseif ($issueMetaRu !== '') {
						$issueDesc = $issueMetaRu;
					} else {
						$issueDesc = title_plain(strip_tags($issue['description_html'] ?? ''));
					}
					$smarty->assign('description', $issueDesc !== '' ? $issueDesc : $descPlain);
					$smarty->assign('og_description', $issueDesc !== '' ? $issueDesc : $descPlain);
					if (!empty($issue['cover'])) {
						$smarty->assign('og_image', $origin . $issue['cover']['display_src']);
					}
					$smarty->assign('og_url', $origin . per_pub_url_issue($periodical, $issue, $isEng));
					$smarty->assign('per_url_periodical', per_pub_url_periodical($periodical, $isEng));
					per_pub_assign_lang_switch_urls($smarty, $periodical, $issue, null);
				}
			}
		}
		// --- periodical: issues list ---
		elseif (!$articleLoaded && $issueId <= 0) {
			$perIssues = [];
			$z = db_select(
				$db,
				'SELECT pi.*, (SELECT COUNT(*) FROM periodical_articles pa WHERE pa.issue_id = pi.id AND pa.is_active = 1) AS articles_count '
				. 'FROM periodical_issues pi WHERE pi.periodical_id = ? AND pi.is_active = 1 '
				. 'ORDER BY (pi.issue_date IS NULL) ASC, pi.issue_date DESC, pi.issue_year DESC, CAST(pi.issue_no AS UNSIGNED) DESC, pi.issue_no DESC',
				'i',
				$id
			);
			while ($z && ($row = mysqli_fetch_assoc($z))) {
				$iid = (int) ($row['id'] ?? 0);
				$row['label'] = per_pub_issue_label($row, $isEng);
				$row['preview_caption'] = per_pub_issue_preview_caption($row, $isEng);
				$row['date_display'] = per_pub_date_display($row['issue_date'] ?? null, $isEng);
				$row['cover'] = per_pub_get_issue_cover($iid);
				$row['url'] = per_pub_url_issue($periodical, $row, $isEng);
				$perIssues[] = $row;
			}
			$smarty->assign('per_issues', $perIssues);
			$smarty->assign('per_url_periodical', per_pub_url_periodical($periodical, $isEng));
			per_pub_assign_lang_switch_urls($smarty, $periodical, null, null);
		}
	}

	include 'right.php';
	$smarty->display('periodicals.tpl');
	exit;
}

// --- catalog ---
per_pub_maybe_redirect_legacy($db, $slugRoute, 0, 0, 0, $isEng);

$rows = [];
$z = db_select(
	$db,
	'SELECT p.*, ci.name AS city_name, ci.name_eng AS city_name_eng, '
	. 'co.country_name, co.country_name_eng, '
	. '(SELECT COUNT(*) FROM periodical_issues pi WHERE pi.periodical_id = p.id AND pi.is_active = 1) AS issues_count '
	. 'FROM periodicals p '
	. 'LEFT JOIN cities ci ON ci.id = p.city_id '
	. 'LEFT JOIN countries co ON co.id = ci.country_id '
	. 'WHERE p.is_active = 1 '
	. 'ORDER BY p.title_ru ASC'
);
while ($z && ($row = mysqli_fetch_assoc($z))) {
	$row = per_pub_enrich_periodical($row, $isEng);
	$row['url'] = per_pub_url_periodical($row, $isEng);
	$rows[] = $row;
}
$smarty->assign('per_rows', $rows);
$smarty->assign('periodical', null);
$smarty->assign('per_not_found', false);

$smarty->assign('title', $isEng ? 'Periodicals' : 'Бумажные газеты и журналы');
$catalogDesc = $isEng
	? 'Paper periodicals archive on ZXPress.'
	: 'Архив бумажной периодики на ZXPress.';
$smarty->assign('description', $catalogDesc);

$origin = zxpress_canonical_origin();
$smarty->assign('og_title', $isEng ? 'Periodicals on ZXPress' : 'Бумажные газеты и журналы на ZXPress');
$smarty->assign('og_description', $catalogDesc);
$smarty->assign('og_image', '');
$smarty->assign('og_url', $origin . per_pub_url_catalog($isEng));
$smarty->assign('og_type', 'website');
per_pub_assign_lang_switch_urls($smarty, null, null, null);

include 'right.php';
$smarty->display('periodicals.tpl');
