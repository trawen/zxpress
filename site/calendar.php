<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function calendar_public_url(bool $isEng): string
{
	return ($isEng ? '/en' : '/ru') . '/calendar';
}

/**
 * @return list<string>
 */
function calendar_month_names(bool $isEng): array
{
	if ($isEng) {
		return [
			1 => 'January',
			2 => 'February',
			3 => 'March',
			4 => 'April',
			5 => 'May',
			6 => 'June',
			7 => 'July',
			8 => 'August',
			9 => 'September',
			10 => 'October',
			11 => 'November',
			12 => 'December',
		];
	}

	return [
		1 => 'Январь',
		2 => 'Февраль',
		3 => 'Март',
		4 => 'Апрель',
		5 => 'Май',
		6 => 'Июнь',
		7 => 'Июль',
		8 => 'Август',
		9 => 'Сентябрь',
		10 => 'Октябрь',
		11 => 'Ноябрь',
		12 => 'Декабрь',
	];
}

/**
 * @return list<string>
 */
function calendar_weekday_names(bool $isEng): array
{
	return $isEng
		? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
		: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
}

function calendar_issue_count_label(int $count, bool $isEng): string
{
	if ($isEng) {
		return $count . ' ' . ($count === 1 ? 'issue' : 'issues');
	}

	return $count . ' ' . getNumEnding($count, ['выпуск', 'выпуска', 'выпусков']);
}

function calendar_format_day_label(int $ts, bool $isEng): string
{
	if ($isEng) {
		return date('j F Y', $ts);
	}

	return date('d ', $ts) . ($GLOBALS['months'][date('m', $ts)] ?? '') . date(' Y', $ts);
}

function calendar_location_label(array $row, bool $isEng): string
{
	$city = trim((string) ($isEng ? ($row['city_name_eng'] ?? '') : ($row['city_name_ru'] ?? '')));
	if ($city === '') {
		$city = trim((string) ($row['city_name_ru'] ?? ''));
	}

	$country = trim((string) ($isEng ? ($row['country_name_eng'] ?? '') : ($row['country_name_ru'] ?? '')));
	if ($country === '') {
		$country = trim((string) ($row['country_name_ru'] ?? ''));
	}

	if ($city !== '' && $country !== '') {
		return $city . ' (' . $country . ')';
	}
	if ($city !== '') {
		return $city;
	}
	if ($country !== '') {
		return $country;
	}

	return '';
}

/**
 * @return array{years:list<array<string,mixed>>,days:array<string,array<string,mixed>>,total_issues:int,start_year:int,end_year:int}
 */
function calendar_build_view(mysqli $db, bool $isEng): array
{
	$months = calendar_month_names($isEng);
	$years = [];
	$dayCounts = [];
	$dayDetails = [];
	$totalIssues = 0;

	$z = db_select(
		$db,
		'SELECT i.id, i.id_press, i.title AS issue_title, i.date, i.slug_ru AS issue_slug_ru, i.slug_en AS issue_slug_en, '
		. 'p.id AS press_id, p.title AS press_title, p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en, '
		. 'c.name AS city_name_ru, c.name_eng AS city_name_eng, '
		. 'co.country_name AS country_name_ru, co.country_name_eng AS country_name_eng '
		. 'FROM issue i '
		. 'INNER JOIN press p ON p.id=i.id_press '
		. 'LEFT JOIN cities c ON p.city=c.id '
		. 'LEFT JOIN countries co ON c.country_id=co.id '
		. 'WHERE i.date > 0 '
		. 'ORDER BY i.date DESC, p.title ASC, i.sort_order ASC, i.id ASC'
	);

	while ($z && ($row = $z->fetch_assoc())) {
		$ts = (int) ($row['date'] ?? 0);
		if ($ts <= 0) {
			continue;
		}

		$year = (int) date('Y', $ts);
		$month = (int) date('n', $ts);
		$day = (int) date('j', $ts);
		$key = date('Y-m-d', $ts);

		$pressRow = [
			'id' => (int) ($row['press_id'] ?? 0),
			'title' => (string) ($row['press_title'] ?? ''),
			'slug_ru' => (string) ($row['press_slug_ru'] ?? ''),
			'slug_en' => (string) ($row['press_slug_en'] ?? ''),
		];
		$issueRow = [
			'id' => (int) ($row['id'] ?? 0),
			'id_press' => (int) ($row['id_press'] ?? 0),
			'title' => (string) ($row['issue_title'] ?? ''),
			'slug_ru' => (string) ($row['issue_slug_ru'] ?? ''),
			'slug_en' => (string) ($row['issue_slug_en'] ?? ''),
		];

		$location = calendar_location_label($row, $isEng);
		$pressTitle = title_plain((string) ($row['press_title'] ?? ''));
		$issueTitle = trim((string) ($row['issue_title'] ?? ''));

		if (!isset($dayDetails[$key])) {
			$dayDetails[$key] = [
				'label' => calendar_format_day_label($ts, $isEng),
				'count' => 0,
				'items' => [],
			];
		}
		$dayDetails[$key]['count']++;
		$dayDetails[$key]['items'][] = [
			'title' => $pressTitle,
			'issue_title' => $issueTitle,
			'url' => ezn_url_issue($pressRow, $issueRow, $isEng),
			'location' => $location,
		];

		$dayCounts[$year][$month][$day] = ($dayCounts[$year][$month][$day] ?? 0) + 1;
		$totalIssues++;
	}

	if ($dayCounts === []) {
		return [
			'years' => [],
			'days' => [],
			'total_issues' => 0,
			'start_year' => 0,
			'end_year' => 0,
		];
	}

	$yearKeys = array_keys($dayCounts);
	sort($yearKeys, SORT_NUMERIC);
	$startYear = (int) reset($yearKeys);
	$endYear = (int) end($yearKeys);

	foreach (array_reverse($yearKeys) as $year) {
		$yearMonths = [];
		for ($month = 1; $month <= 12; $month++) {
			$firstTs = mktime(0, 0, 0, $month, 1, (int) $year);
			$daysInMonth = (int) date('t', $firstTs);
			$firstWeekday = (int) date('N', $firstTs);
			$weeks = [];
			$week = [];

			for ($i = 1; $i < $firstWeekday; $i++) {
				$week[] = null;
			}

			for ($day = 1; $day <= $daysInMonth; $day++) {
				$key = sprintf('%04d-%02d-%02d', (int) $year, $month, $day);
				$count = (int) ($dayCounts[$year][$month][$day] ?? 0);
				$week[] = [
					'day' => $day,
					'key' => $key,
					'count' => $count,
					'has_items' => $count > 0,
				];

				if (count($week) === 7) {
					$weeks[] = $week;
					$week = [];
				}
			}

			if ($week !== []) {
				while (count($week) < 7) {
					$week[] = null;
				}
				$weeks[] = $week;
			}

			$monthTotal = 0;
			foreach (($dayCounts[$year][$month] ?? []) as $cnt) {
				$monthTotal += (int) $cnt;
			}

			$yearMonths[] = [
				'month' => $month,
				'name' => $months[$month],
				'weeks' => $weeks,
				'total' => $monthTotal,
			];
		}

		$years[] = [
			'year' => (int) $year,
			'months' => $yearMonths,
			'total' => array_sum(array_map(static function ($monthDays): int {
				return array_sum(array_map('intval', $monthDays));
			}, $dayCounts[$year] ?? [])),
		];
	}

	return [
		'years' => $years,
		'days' => $dayDetails,
		'total_issues' => $totalIssues,
		'start_year' => $startYear,
		'end_year' => $endYear,
	];
}

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');
$catalogUrl = ezn_url_catalog_new($isEng);
$calendarUrl = calendar_public_url($isEng);
$data = calendar_build_view($db, $isEng);

$smarty->assign('ezines_catalog_url', $catalogUrl);
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('calendar_catalog_url', $calendarUrl);
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_guestbook_active', false);
$smarty->assign('smn_nav_updates_active', false);
$smarty->assign('smn_nav_map_active', false);
$smarty->assign('smn_nav_calendar_active', true);
$smarty->assign('calendar_weekdays', calendar_weekday_names($isEng));
$smarty->assign('calendar_years', $data['years']);
$smarty->assign('calendar_days_json', json_encode($data['days'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}');
$smarty->assign('calendar_total_issues', $data['total_issues']);
$smarty->assign('calendar_total_issues_label', calendar_issue_count_label((int) $data['total_issues'], $isEng));
$smarty->assign('calendar_start_year', $data['start_year']);
$smarty->assign('calendar_end_year', $data['end_year']);

$smarty->assign(
	'title',
	$isEng
		? 'ZX Spectrum release calendar by year'
		: 'Календарь выхода ZX Spectrum изданий по годам'
);
$smarty->assign(
	'description',
	$isEng
		? 'Year-by-year calendar of ZX Spectrum electronic publications with daily issue counts and release details.'
		: 'Календарь выхода электронных изданий для ZX Spectrum по годам с количеством выпусков по дням и подробностями по каждому дню.'
);
$smarty->assign('url_rus', htmlspecialchars(calendar_public_url(false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(calendar_public_url(true), ENT_QUOTES, 'UTF-8'));

$smarty->display('calendar_new.tpl');
