<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/press_map.php';

function map_ui_is_new(): bool
{
	return defined('MAP_UI_VARIANT') && MAP_UI_VARIANT === 'new';
}

$isEng = (($_GET['lng'] ?? '') === 'eng') || (($smarty->getTemplateVars('lng') ?? '') === 'eng');
$lng = $isEng ? 'eng' : '';

if (map_ui_is_new()) {
	$rawFilter = (string) ($_GET['filter'] ?? '');
	$filter = press_map_normalize_filter($rawFilter);
	if ($rawFilter !== '' && $filter === '') {
		header('Location: ' . press_map_url($isEng), true, 301);
		exit;
	}

	$points = press_map_points_for_filter($db, $isEng, $filter);
	$totalPubs = 0;
	foreach ($points as $p) {
		$totalPubs += (int) ($p['count'] ?? 0);
	}

	$typeCounts = press_map_type_counts($db);
	$countAll = $typeCounts[0] + $typeCounts[1];
	$countBooks = press_map_book_count($db);

	$catalogUrl = ezn_url_catalog_new($isEng);
	$mapUrl = press_map_url($isEng);
	$mapUrlPapers = press_map_url($isEng, 'papers');
	$mapUrlMagazines = press_map_url($isEng, 'magazines');
	$mapUrlBooks = press_map_url($isEng, 'books');

	$smarty->assign('ezines_catalog_url', $catalogUrl);
	$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
	$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
	$smarty->assign('map_catalog_url', $mapUrl);
	$smarty->assign('smn_nav_authors_active', false);
	$smarty->assign('smn_nav_ezines_active', false);
	$smarty->assign('smn_nav_gallery_active', false);
	$smarty->assign('smn_nav_map_active', true);

	$smarty->assign('map_filter', $filter);
	$smarty->assign('map_filters', [
		[
			'slug' => '',
			'url' => $mapUrl,
			'label' => $isEng ? 'All' : 'Все',
			'count' => $countAll,
			'active' => $filter === '',
		],
		[
			'slug' => 'papers',
			'url' => $mapUrlPapers,
			'label' => $isEng ? 'Newspapers' : 'Газеты',
			'count' => $typeCounts[0],
			'active' => $filter === 'papers',
		],
		[
			'slug' => 'magazines',
			'url' => $mapUrlMagazines,
			'label' => $isEng ? 'Magazines' : 'Журналы',
			'count' => $typeCounts[1],
			'active' => $filter === 'magazines',
		],
		[
			'slug' => 'books',
			'url' => $mapUrlBooks,
			'label' => $isEng ? 'Books' : 'Книги',
			'count' => $countBooks,
			'active' => $filter === 'books',
		],
	]);

	$smarty->assign('map_points_json', json_encode($points, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
	$cityCount = count($points);
	$smarty->assign('map_cities', $cityCount);
	$smarty->assign('map_publications', $totalPubs);
	$smarty->assign('map_publications_label', press_map_count_label($totalPubs, $isEng, $filter));
	$smarty->assign('map_cities_label', press_map_cities_label($cityCount, $isEng));
	$smarty->assign('top_cities', press_map_top_cities($points, $isEng, 50, $filter));

	if ($filter === 'papers') {
		$smarty->assign('title', $isEng
			? 'Map of ZX Spectrum electronic newspapers'
			: 'Карта электронных газет для ZX Spectrum');
		$smarty->assign('description', $isEng
			? 'Interactive map of ZX Spectrum electronic newspapers by city. Circle size reflects how many titles were published there.'
			: 'Интерактивная карта электронных газет для ZX Spectrum по городам. Размер кружка — число изданий в городе.');
		$smarty->assign('map_heading', $isEng
			? 'Map of ZX Spectrum electronic newspapers'
			: 'Карта электронных газет для ZX Spectrum');
		$smarty->assign('map_filter_label', $isEng ? 'Newspapers' : 'Газеты');
	} elseif ($filter === 'magazines') {
		$smarty->assign('title', $isEng
			? 'Map of ZX Spectrum electronic magazines'
			: 'Карта электронных журналов для ZX Spectrum');
		$smarty->assign('description', $isEng
			? 'Interactive map of ZX Spectrum electronic magazines by city. Circle size reflects how many titles were published there.'
			: 'Интерактивная карта электронных журналов для ZX Spectrum по городам. Размер кружка — число изданий в городе.');
		$smarty->assign('map_heading', $isEng
			? 'Map of ZX Spectrum electronic magazines'
			: 'Карта электронных журналов для ZX Spectrum');
		$smarty->assign('map_filter_label', $isEng ? 'Magazines' : 'Журналы');
	} elseif ($filter === 'books') {
		$smarty->assign('title', $isEng
			? 'Map of ZX Spectrum paper books and magazines'
			: 'Карта бумажных книг и журналов для ZX Spectrum');
		$smarty->assign('description', $isEng
			? 'Interactive map of ZX Spectrum paper books and magazines by city. Circle size reflects how many titles were published there.'
			: 'Интерактивная карта бумажных книг и журналов для ZX Spectrum по городам. Размер кружка — число изданий в городе.');
		$smarty->assign('map_heading', $isEng
			? 'Map of ZX Spectrum paper books and magazines'
			: 'Карта бумажных книг и журналов для ZX Spectrum');
		$smarty->assign('map_filter_label', $isEng ? 'Books' : 'Книги');
	} else {
		$smarty->assign('title', $isEng
			? 'Map of electronic and paper publications for ZX Spectrum'
			: 'Карта электронных и бумажных изданий для ZX Spectrum');
		$smarty->assign('description', $isEng
			? 'Interactive map of ZX Spectrum electronic and paper publications by city. Circle size reflects how many titles were published there.'
			: 'Интерактивная карта электронных и бумажных изданий для ZX Spectrum по городам. Размер кружка — число изданий в городе.');
		$smarty->assign('map_heading', $isEng
			? 'Map of electronic and paper publications for ZX Spectrum'
			: 'Карта электронных и бумажных изданий для ZX Spectrum');
		$smarty->assign('map_filter_label', '');
	}

	$smarty->assign('url_rus', htmlspecialchars(press_map_url(false, $filter), ENT_QUOTES, 'UTF-8'));
	$smarty->assign('url_eng', htmlspecialchars(press_map_url(true, $filter), ENT_QUOTES, 'UTF-8'));

	$smarty->display('map_new.tpl');
	exit;
}

// Classic map (legacy markers per publication)
$map = [];
$z = db_select(
	$db,
	"SELECT *, press.id AS idp, screens.id AS map_screen_id, screens.format AS map_screen_format "
	. "FROM press INNER JOIN cities ON cities.id = press.city "
	. "LEFT OUTER JOIN countries ON cities.country_id = countries.id "
	. "LEFT JOIN screens ON screens.id = (SELECT MIN(s2.id) FROM screens s2 WHERE s2.id_press = press.id) "
	. "WHERE press.type IN (0, 1) AND cities.country_id <> 0 "
);
while ($z && ($t = mysqli_fetch_array($z))) {
	$b = [];
	$b[0] = $t['lat'];
	$b[1] = $t['lng'];
	$thumb = '/img/empty_img.png';
	if (!empty($t['map_screen_id'])) {
		$fmt = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($t['map_screen_format'] ?? 'png'));
		if ($fmt === '') {
			$fmt = 'png';
		}
		$thumb = '/screens/1/' . (int) $t['map_screen_id'] . '.' . $fmt;
	}
	$b[2] = "<a href='issue.php?id=" . $t['idp'] . "'>" . $t['title'] . "</a><br><img src='" . $thumb . "' width=64 height=48>";
	$map[] = $b;
}
$smarty->assign('map', json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));

if ($_GET['lng'] ?? '') {
	$smarty->assign('title', 'Map of ZX Spectrum electronic newspapers and magazines');
} else {
	$smarty->assign('title', 'Карта электронных газет и журналов для ZX Spectrum');
}

include 'right.php';
$smarty->display('map.tpl');
