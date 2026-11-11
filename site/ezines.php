<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/screen_images.php';

function ezines_ui_is_new(): bool
{
	return true;
}

function ezines_ui_template(): string
{
	return 'ezines_new.tpl';
}

function ezines_public_type_label(int $type, bool $isEng): string
{
	if ($type === 1) {
		return $isEng ? 'magazine' : 'журнал';
	}
	if ($type === 2) {
		return $isEng ? 'report' : 'отчёт';
	}
	return $isEng ? 'newspaper' : 'газета';
}

function ezines_public_years_label(int $from, int $to): string
{
	if ($from !== 1970 && $to !== 1970 && $from !== $to) {
		return $from . '—' . $to;
	}
	if ($from !== 1970) {
		return (string) $from;
	}
	if ($to !== 1970) {
		return (string) $to;
	}
	return '';
}

/** @return ''|'papers'|'magazines'|'reports' */
function ezines_normalize_filter(?string $raw): string
{
	$raw = (string) $raw;
	if ($raw === 'papers' || $raw === 'magazines' || $raw === 'reports') {
		return $raw;
	}

	return '';
}

function ezines_type_for_filter(string $filter): ?int
{
	$filter = ezines_normalize_filter($filter);
	if ($filter === 'papers') {
		return 0;
	}
	if ($filter === 'magazines') {
		return 1;
	}
	if ($filter === 'reports') {
		return 2;
	}

	return null;
}

/**
 * @return array{0:int,1:int,2:int}
 */
function ezines_type_counts(mysqli $db): array
{
	$counts = [0 => 0, 1 => 0, 2 => 0];
	$z = db_select($db, 'SELECT type, COUNT(*) AS cnt FROM press GROUP BY type');
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$type = (int) ($row['type'] ?? -1);
		if ($type === 0 || $type === 1 || $type === 2) {
			$counts[$type] = (int) ($row['cnt'] ?? 0);
		}
	}

	return $counts;
}

/**
 * Up to two catalog thumbs per press from splash (0), menu (1), text (2).
 * Prefer splash + menu from one later issue; text is only a fallback when
 * splash and/or menu are missing. Issue order matches the press overview:
 * LENGTH(title) DESC, title DESC. Display order: splash, menu, then text.
 *
 * @return array<int, list<array{id:int,format:string,src:string,type:int,width:int,height:int}>>
 */
function ezines_press_screens_map(mysqli $db): array
{
	$byPress = [];
	$dimCols = screen_table_has_dimensions($db) ? ', s.width, s.height' : '';
	$z = db_select(
		$db,
		'SELECT s.id_press, s.id_issue, s.id, s.format, s.type' . $dimCols
		. ' FROM screens s'
		. ' INNER JOIN issue i ON i.id = s.id_issue'
		. ' WHERE s.type IN (0, 1, 2)'
		. ' ORDER BY s.id_press ASC,'
		. ' LENGTH(i.title) DESC, i.title DESC, s.type ASC, s.id ASC'
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$pressId = (int) ($row['id_press'] ?? 0);
		$issueId = (int) ($row['id_issue'] ?? 0);
		$type = (int) ($row['type'] ?? -1);
		$screenId = (int) ($row['id'] ?? 0);
		if ($pressId <= 0 || $issueId <= 0 || $screenId <= 0 || $type < 0 || $type > 2) {
			continue;
		}
		if (!isset($byPress[$pressId])) {
			$byPress[$pressId] = [
				'issues' => [],
				'issue_order' => [],
				'by_type' => [],
			];
		}
		if (!isset($byPress[$pressId]['issues'][$issueId])) {
			$byPress[$pressId]['issues'][$issueId] = [];
			$byPress[$pressId]['issue_order'][] = $issueId;
		}
		if (!isset($byPress[$pressId]['issues'][$issueId][$type])) {
			$format = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($row['format'] ?? '')) ?: 'png');
			$entry = [
				'id' => $screenId,
				'format' => $format,
				'type' => $type,
				'width' => (int) ($row['width'] ?? 0),
				'height' => (int) ($row['height'] ?? 0),
				'src' => '/screens/1/' . $screenId . '.' . $format,
			];
			$byPress[$pressId]['issues'][$issueId][$type] = $entry;
			if (!isset($byPress[$pressId]['by_type'][$type])) {
				$byPress[$pressId]['by_type'][$type] = $entry;
			}
		}
	}

	$pickPair = static function (array $byType): array {
		$pair = [];
		if (isset($byType[0])) {
			$pair[] = $byType[0];
		}
		if (isset($byType[1])) {
			$pair[] = $byType[1];
		}
		// Text (type=2) only fills missing splash/menu slots.
		if (count($pair) < 2 && isset($byType[2])) {
			$pair[] = $byType[2];
		}
		return array_slice($pair, 0, 2);
	};

	$out = [];
	foreach ($byPress as $pressId => $pack) {
		$pair = [];
		// Best: splash + menu from the same later issue.
		foreach ($pack['issue_order'] as $issueId) {
			$issueScreens = $pack['issues'][$issueId] ?? [];
			if (isset($issueScreens[0], $issueScreens[1])) {
				$pair = [$issueScreens[0], $issueScreens[1]];
				break;
			}
		}
		// Next: any same-issue pair that can be completed with text fallback.
		if ($pair === []) {
			foreach ($pack['issue_order'] as $issueId) {
				$candidate = $pickPair($pack['issues'][$issueId] ?? []);
				if (count($candidate) === 2) {
					$pair = $candidate;
					break;
				}
				if ($pair === [] && $candidate !== []) {
					$pair = $candidate;
				}
			}
		}
		// Last resort: best splash/menu/text across issues.
		if (count($pair) < 2) {
			$pair = $pickPair($pack['by_type']);
		}
		if ($pair !== []) {
			$out[(int) $pressId] = $pair;
		}
	}

	return $out;
}

/**
 * Yearly publication histogram for the catalog (optionally limited to a press type).
 * Counts press titles active in each year (years_from..years_to), matching the frontend filter.
 *
 * @return array{bars:list<array{year:int,count:int,height:float,label:bool}>,total:int,max:int,start_year:int,end_year:int}|null
 */
function ezines_catalog_year_chart(mysqli $db, ?int $typeFilter): ?array
{
	$counts = [];
	if ($typeFilter === null) {
		$z = db_select(
			$db,
			'SELECT YEAR(FROM_UNIXTIME(years_from)) AS yf, YEAR(FROM_UNIXTIME(years_to)) AS yt'
			. ' FROM press'
		);
	} else {
		$stmt = mysqli_prepare(
			$db,
			'SELECT YEAR(FROM_UNIXTIME(years_from)) AS yf, YEAR(FROM_UNIXTIME(years_to)) AS yt'
			. ' FROM press WHERE type = ?'
		);
		if (!$stmt) {
			return null;
		}
		mysqli_stmt_bind_param($stmt, 'i', $typeFilter);
		mysqli_stmt_execute($stmt);
		$z = mysqli_stmt_get_result($stmt);
	}

	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$from = (int) ($row['yf'] ?? 0);
		$to = (int) ($row['yt'] ?? 0);
		if ($from === 1970) {
			$from = 0;
		}
		if ($to === 1970) {
			$to = 0;
		}
		if ($from && $to) {
			if ($to < $from) {
				continue;
			}
			for ($year = $from; $year <= $to; $year++) {
				$counts[$year] = (int) ($counts[$year] ?? 0) + 1;
			}
		} elseif ($from) {
			$counts[$from] = (int) ($counts[$from] ?? 0) + 1;
		} elseif ($to) {
			$counts[$to] = (int) ($counts[$to] ?? 0) + 1;
		}
	}
	if ($counts === []) {
		return null;
	}

	$startYear = (int) min(array_keys($counts));
	$endYear = (int) max(array_keys($counts));
	if ($endYear < $startYear) {
		return null;
	}

	$max = max($counts);
	if ($max <= 0) {
		return null;
	}

	$total = array_sum($counts);
	$span = $endYear - $startYear;
	$bars = [];
	for ($year = $startYear; $year <= $endYear; $year++) {
		$count = (int) ($counts[$year] ?? 0);
		// Keep a visible stub for non-zero years so sparse decades stay readable.
		$height = $count > 0 ? max(4.0, round($count / $max * 100, 2)) : 0.0;
		$label = ($year === $startYear || $year === $endYear || $year % 5 === 0);
		// When the range is short, label every year.
		if ($span <= 12) {
			$label = true;
		}
		$bars[] = [
			'year' => $year,
			'count' => $count,
			'height' => $height,
			'label' => $label,
		];
	}

	return [
		'bars' => $bars,
		'total' => $total,
		'max' => $max,
		'start_year' => $startYear,
		'end_year' => $endYear,
	];
}

function ezines_ui_render($smarty, bool $isEng, string $filter = ''): void
{
	$filter = ezines_normalize_filter($filter);
	$classicUrl = ezn_url_catalog($isEng);
	$smarty->assign('ezines_catalog_url', ezn_url_catalog_new($isEng));
	$smarty->assign('ezines_classic_url', $classicUrl);
	$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
	$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
	$smarty->assign('smn_nav_authors_active', false);
	$smarty->assign('smn_nav_ezines_active', true);
	$smarty->assign('url_rus', htmlspecialchars(ezn_url_catalog_new(false, $filter), ENT_QUOTES, 'UTF-8'));
	$smarty->assign('url_eng', htmlspecialchars(ezn_url_catalog_new(true, $filter), ENT_QUOTES, 'UTF-8'));

	$smarty->display(ezines_ui_template());
}

$smarty->assign('a', htmlspecialchars($_GET['x'] ?? '', ENT_QUOTES, 'UTF-8'));

$lng = $smarty->getTemplateVars('lng');
$isEng = ezn_is_eng(is_string($lng) ? $lng : null);

$rawFilter = (string) ($_GET['filter'] ?? '');
if ($rawFilter === '') {
	$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	if (is_string($path)) {
		if (preg_match('#^/(?:ru|en|eng)/ezines/(papers|magazines|reports)/?$#', $path, $m)) {
			$rawFilter = (string) ($m[1] ?? '');
		} elseif (preg_match('#^/(?:ru|en|eng)/ezines/([^/]+)/?$#', $path, $m)) {
			$reservedSections = [
				'books' => '/books',
				'zxnet' => '/zxnet',
				'snailmail' => '/snailmail',
				'authors' => '/authors',
				'gallery' => '/gallery',
				'search' => '/search',
				'updates' => '/updates',
				'calendar' => '/calendar',
				'guestbook' => '/guestbook',
				'map' => '/map',
				'categories' => '/categories',
				'periodicals' => '/periodicals',
			];
			$slug = per_slug_normalize_path((string) ($m[1] ?? ''));
			if (isset($reservedSections[$slug])) {
				header('Location: ' . ezn_path_prefix($isEng) . $reservedSections[$slug], true, 302);
				exit;
			}
		}
	}
}
$filter = ezines_normalize_filter($rawFilter);
if ($rawFilter !== '' && $filter === '') {
	header('Location: ' . ezn_url_catalog_new($isEng), true, 301);
	exit;
}

$typeFilter = ezines_type_for_filter($filter);
$sql = 'SELECT press.*, press.id AS id, cities.name AS city_name, cities.name_eng AS city_name_eng, '
	. 'cities.country_id AS country_id, co.country_name AS country_ru, co.country_name_eng AS country_en '
	. 'FROM press '
	. 'LEFT OUTER JOIN cities ON press.city=cities.id '
	. 'LEFT JOIN countries co ON co.id = cities.country_id';
if ($typeFilter === null) {
	$z = db_select($db, $sql . ' ORDER BY press.title ASC');
} else {
	$stmt = mysqli_prepare($db, $sql . ' WHERE press.type=? ORDER BY press.title ASC');
	mysqli_stmt_bind_param($stmt, 'i', $typeFilter);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
}

$f = 0;
$n = 0;
$a = '#';
$c = [];
$screensMap = ezines_press_screens_map($db);
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['title_plain'] = title_plain($t['title'] ?? '');
	$t['public_url'] = ezn_url_press($t, $isEng);
	$pressId = (int) ($t['id'] ?? 0);
	$t['screens'] = $screensMap[$pressId] ?? [];
	foreach ($t['screens'] as &$screenRow) {
		if (is_array($screenRow) && (int) ($screenRow['id'] ?? 0) > 0) {
			screen_enrich_gallery_thumb($screenRow);
		}
	}
	unset($screenRow);
	$t['splash'] = $t['screens'][0] ?? null;
	$s = strtoupper(mb_substr($t['title_plain'], 0, 1, 'UTF-8'));
	$isAlphaLetter = ($s < '0' || $s > '9');
	if ($isAlphaLetter && (!$f || $a !== $s)) {
		if ($f && $a !== $s) {
			$spacer = $t;
			$spacer['off'] = 0;
			$spacer['letter'] = '';
			$c[$n] = $spacer;
			$n++;
		}
		$a = $s;
		$t['letter'] = $a;
	} else {
		$t['letter'] = '';
	}

	$t['off'] = 1;
	$t['years_to'] = date('Y', $t['years_to']);
	$t['years_from'] = date('Y', $t['years_from']);
	$t['flag'] = $country_id[$t['country_id']] ?? '';
	$numbers = (int) ($t['numbers'] ?? 0);
	$onlineIssues = (int) ($t['online_issues'] ?? 0);
	$t['finish'] = $numbers > 0 ? (int) ((100 / $numbers) * $onlineIssues) : 0;
	$t['type_label'] = ezines_public_type_label((int) ($t['type'] ?? 0), $isEng);
	$t['years_label'] = ezines_public_years_label((int) $t['years_from'], (int) $t['years_to']);
	$t['issues_count'] = $numbers;
	if ($numbers > 0) {
		if ($isEng) {
			$t['issues_count_label'] = $numbers . ' ' . ($numbers === 1 ? 'issue' : 'issues');
		} else {
			$t['issues_count_label'] = $numbers . ' ' . getNumEnding($numbers, ['номер', 'номера', 'номеров']);
		}
	} else {
		$t['issues_count_label'] = '';
	}
	$city = trim((string) ($isEng
		? (($t['city_name_eng'] ?? '') !== '' ? ($t['city_name_eng'] ?? '') : ($t['city_name'] ?? ''))
		: (($t['city_name'] ?? '') !== '' ? ($t['city_name'] ?? '') : ($t['city_name_eng'] ?? ''))
	));
	$country = trim((string) ($isEng
		? (($t['country_en'] ?? '') !== '' ? ($t['country_en'] ?? '') : ($t['country_ru'] ?? ''))
		: (($t['country_ru'] ?? '') !== '' ? ($t['country_ru'] ?? '') : ($t['country_en'] ?? ''))
	));
	$t['city_name_label'] = $city;
	$t['country_label'] = $country;
	if ($city !== '' && $country !== '') {
		$t['city_label'] = $city . ' (' . $country . ')';
	} else {
		$t['city_label'] = $city !== '' ? $city : $country;
	}

	$c[$n] = $t;
	$n++;
	$f = 1;
}
$smarty->assign('catalog', $c);

$countryFilters = [];
foreach ($c as $row) {
	if ((int) ($row['off'] ?? 0) !== 1) {
		continue;
	}
	$countryId = (int) ($row['country_id'] ?? 0);
	$label = trim((string) ($row['country_label'] ?? ''));
	if ($countryId <= 0 || $label === '') {
		continue;
	}
	if (!isset($countryFilters[$countryId])) {
		$countryFilters[$countryId] = [
			'id' => $countryId,
			'label' => $label,
			'count' => 0,
		];
	}
	$countryFilters[$countryId]['count']++;
}
$countryFilters = array_values($countryFilters);
usort($countryFilters, static function (array $a, array $b): int {
	return strcmp(
		mb_strtolower((string) $a['label'], 'UTF-8'),
		mb_strtolower((string) $b['label'], 'UTF-8')
	);
});
$smarty->assign('country_filters', $countryFilters);

$typeCounts = ezines_type_counts($db);
$countAll = $typeCounts[0] + $typeCounts[1] + $typeCounts[2];
$yearChart = ezines_catalog_year_chart($db, $typeFilter);
if (is_array($yearChart)) {
	if ($isEng) {
		$yearChart['total_label'] = $yearChart['total'] . ' ' . ($yearChart['total'] === 1 ? 'title' : 'titles');
	} else {
		$yearChart['total_label'] = $yearChart['total'] . ' ' . getNumEnding($yearChart['total'], ['издание', 'издания', 'изданий']);
	}
}
$smarty->assign('year_chart', $yearChart);
$smarty->assign('ezines_filter', $filter);
$smarty->assign('ezines_filters', [
	[
		'slug' => '',
		'url' => ezn_url_catalog_new($isEng),
		'label' => $isEng ? 'All' : 'Все',
		'count' => $countAll,
		'active' => $filter === '',
	],
	[
		'slug' => 'papers',
		'url' => ezn_url_catalog_new($isEng, 'papers'),
		'label' => $isEng ? 'Newspapers' : 'Газеты',
		'count' => $typeCounts[0],
		'active' => $filter === 'papers',
	],
	[
		'slug' => 'magazines',
		'url' => ezn_url_catalog_new($isEng, 'magazines'),
		'label' => $isEng ? 'Magazines' : 'Журналы',
		'count' => $typeCounts[1],
		'active' => $filter === 'magazines',
	],
	[
		'slug' => 'reports',
		'url' => ezn_url_catalog_new($isEng, 'reports'),
		'label' => $isEng ? 'Reports' : 'Другое',
		'count' => $typeCounts[2],
		'active' => $filter === 'reports',
	],
]);

$origin = function_exists('zxpress_canonical_origin') ? zxpress_canonical_origin() : 'https://zxpress.ru';
if ($isEng) {
	if ($filter === 'papers') {
		$title = 'ZX Spectrum electronic newspapers';
		$desc = 'Catalog of ZX Spectrum electronic newspapers (disk papers).';
	} elseif ($filter === 'magazines') {
		$title = 'ZX Spectrum electronic magazines';
		$desc = 'Catalog of ZX Spectrum electronic magazines (diskmags).';
	} elseif ($filter === 'reports') {
		$title = 'ZX Spectrum electronic reports';
		$desc = 'Catalog of ZX Spectrum electronic scene reports.';
	} else {
		$title = 'Library of electronic newspapers and magazines for ZX Spectrum';
		$desc = 'This section is devoted to electronic newspapers and magazines published by the ZX Spectrum user community from the early 1990s through the 2020s.';
	}
} else {
	if ($filter === 'papers') {
		$title = 'Электронные газеты для ZX Spectrum';
		$desc = 'Каталог электронных газет для ZX Spectrum.';
	} elseif ($filter === 'magazines') {
		$title = 'Электронные журналы для ZX Spectrum';
		$desc = 'Каталог электронных журналов для ZX Spectrum.';
	} elseif ($filter === 'reports') {
		$title = 'Электронные отчёты для ZX Spectrum';
		$desc = 'Каталог электронных отчётов сцены ZX Spectrum.';
	} else {
		$title = 'Библиотека электронных газет и журналов для ZX Spectrum';
		$desc = 'Этот раздел посвящён электронным газетам и журналам, выпускавшимся сообществом пользователей ZX Spectrum с начала 1990-х до 2020-х годов.';
	}
}
$smarty->assign('title', $title);
$smarty->assign('description', $desc);
$smarty->assign('og_title', $title);
$smarty->assign('og_description', $desc);
$smarty->assign('og_type', 'website');
$smarty->assign('og_url', $origin . ezn_url_catalog_new($isEng, $filter));
$smarty->assign('og_image', $origin . '/img/banner.png');

ezines_ui_render($smarty, $isEng, $filter);
