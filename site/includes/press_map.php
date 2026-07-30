<?php

require_once __DIR__ . '/ezine_slugs.php';

function press_map_coord_key(float $lat, float $lng): string
{
	return round($lat, 4) . '|' . round($lng, 4);
}

/** @return ''|'papers'|'magazines'|'books' */
function press_map_normalize_filter(?string $raw): string
{
	$raw = strtolower(trim((string) $raw));
	if ($raw === 'papers' || $raw === 'magazines' || $raw === 'books') {
		return $raw;
	}

	return '';
}

/**
 * @return list<int>
 */
function press_map_types_for_filter(string $filter): array
{
	if ($filter === 'papers') {
		return [0];
	}
	if ($filter === 'magazines') {
		return [1];
	}

	return [0, 1];
}

function press_map_types_sql(array $types): string
{
	$clean = [];
	foreach ($types as $type) {
		$clean[] = (string) (int) $type;
	}
	if ($clean === []) {
		$clean = ['0', '1'];
	}

	return implode(', ', $clean);
}

function press_map_url(bool $isEng, string $filter = ''): string
{
	$base = ezn_path_prefix($isEng) . '/map';
	$filter = press_map_normalize_filter($filter);

	return $filter !== '' ? $base . '/' . $filter : $base;
}

function press_map_count_label(int $n, bool $isEng, string $filter = ''): string
{
	$filter = press_map_normalize_filter($filter);
	if ($isEng) {
		if ($filter === 'papers') {
			return $n === 1 ? 'newspaper' : 'newspapers';
		}
		if ($filter === 'magazines') {
			return $n === 1 ? 'magazine' : 'magazines';
		}
		if ($filter === 'books') {
			return $n === 1 ? 'book' : 'books';
		}

		return $n === 1 ? 'publication' : 'publications';
	}

	if ($filter === 'papers') {
		return getNumEnding($n, ['газета', 'газеты', 'газет']);
	}
	if ($filter === 'magazines') {
		return getNumEnding($n, ['журнал', 'журналы', 'журналов']);
	}
	if ($filter === 'books') {
		return getNumEnding($n, ['книга', 'книги', 'книг']);
	}

	return getNumEnding($n, ['издание', 'издания', 'изданий']);
}

function press_map_cities_label(int $n, bool $isEng): string
{
	if ($isEng) {
		return $n === 1 ? 'city' : 'cities';
	}

	return getNumEnding($n, ['городе', 'городах', 'городах']);
}

function press_map_font_size(int $value, int $min, int $max): int
{
	if ($max <= $min) {
		return 16;
	}
	$ratio = ($value - $min) / ($max - $min);

	return (int) round(12 + $ratio * 10);
}

/**
 * Counts of mappable newspapers / magazines (with city coordinates).
 *
 * @return array{0: int, 1: int}
 */
function press_map_type_counts(mysqli $db): array
{
	$counts = [0 => 0, 1 => 0];
	$z = db_select(
		$db,
		'SELECT p.type, COUNT(p.id) AS cnt '
		. 'FROM press p '
		. 'INNER JOIN cities c ON c.id = p.city '
		. 'WHERE p.type IN (0, 1) AND c.country_id <> 0 AND c.lat <> 0 AND c.lng <> 0 '
		. 'GROUP BY p.type'
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$type = (int) ($row['type'] ?? -1);
		if ($type === 0 || $type === 1) {
			$counts[$type] = (int) ($row['cnt'] ?? 0);
		}
	}

	return $counts;
}

function press_map_book_count(mysqli $db): int
{
	$z = db_select(
		$db,
		'SELECT COUNT(b.id) AS cnt '
		. 'FROM books b '
		. 'INNER JOIN cities c ON c.id = b.city_id '
		. 'WHERE c.country_id <> 0 AND c.lat <> 0 AND c.lng <> 0'
	);
	$row = $z ? mysqli_fetch_assoc($z) : null;

	return (int) ($row['cnt'] ?? 0);
}

function press_map_book_title(array $row): string
{
	$title = trim(plain_text_decode_entities((string) ($row['title1'] ?? '')));
	if ($title === '') {
		$title = trim(plain_text_decode_entities((string) ($row['title2'] ?? '')));
	}

	return $title !== '' ? $title : ('#' . (int) ($row['id'] ?? 0));
}

function press_map_book_url(int $id, bool $isEng): string
{
	require_once __DIR__ . '/books_public.php';

	return books_url_book($id, $isEng);
}

/**
 * Books keyed by city coordinates.
 *
 * @return array<string, list<array{title: string, url: string, numbers: int}>>
 */
function press_map_books_by_coord(mysqli $db, bool $isEng): array
{
	$z = db_select(
		$db,
		'SELECT b.id, b.title1, b.title2, b.pages, c.lat, c.lng '
		. 'FROM books b '
		. 'INNER JOIN cities c ON c.id = b.city_id '
		. 'WHERE c.country_id <> 0 AND c.lat <> 0 AND c.lng <> 0 '
		. 'ORDER BY b.pages DESC, b.title1 ASC'
	);

	$byCoord = [];
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$key = press_map_coord_key((float) ($row['lat'] ?? 0), (float) ($row['lng'] ?? 0));
		$byCoord[$key][] = [
			'title' => press_map_book_title($row),
			'url' => press_map_book_url((int) ($row['id'] ?? 0), $isEng),
			// Books do not have "issue counts"; keep neutral weight in the cloud list.
			'numbers' => 1,
		];
	}

	return $byCoord;
}

/**
 * Aggregate paper books by city coordinates.
 *
 * @return list<array<string, mixed>>
 */
function press_map_book_points(mysqli $db, bool $isEng = false): array
{
	$z = db_select(
		$db,
		'SELECT c.id AS city_id, c.name AS city_ru, c.name_eng AS city_en, '
		. 'c.lat, c.lng, c.country_id, '
		. 'co.country_name AS country_ru, co.country_name_eng AS country_en, '
		. 'COUNT(b.id) AS cnt '
		. 'FROM books b '
		. 'INNER JOIN cities c ON c.id = b.city_id '
		. 'LEFT JOIN countries co ON co.id = c.country_id '
		. 'WHERE c.country_id <> 0 AND c.lat <> 0 AND c.lng <> 0 '
		. 'GROUP BY c.id, c.name, c.name_eng, c.lat, c.lng, c.country_id, co.country_name, co.country_name_eng '
		. 'ORDER BY cnt DESC'
	);

	$merged = [];
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$lat = (float) ($row['lat'] ?? 0);
		$lng = (float) ($row['lng'] ?? 0);
		if ($lat == 0.0 && $lng == 0.0) {
			continue;
		}
		$cnt = (int) ($row['cnt'] ?? 0);
		if ($cnt <= 0) {
			continue;
		}
		$key = press_map_coord_key($lat, $lng);
		if (!isset($merged[$key])) {
			$merged[$key] = [
				'lat' => $lat,
				'lng' => $lng,
				'count' => $cnt,
				'city_ru' => (string) ($row['city_ru'] ?? ''),
				'city_en' => (string) ($row['city_en'] ?? ''),
				'country_ru' => (string) ($row['country_ru'] ?? ''),
				'country_en' => (string) ($row['country_en'] ?? ''),
				'country_id' => (int) ($row['country_id'] ?? 0),
				'_part_count' => $cnt,
			];
			continue;
		}
		if ($cnt > (int) ($merged[$key]['_part_count'] ?? 0)) {
			$merged[$key]['city_ru'] = (string) ($row['city_ru'] ?? '');
			$merged[$key]['city_en'] = (string) ($row['city_en'] ?? '');
			$merged[$key]['country_ru'] = (string) ($row['country_ru'] ?? '');
			$merged[$key]['country_en'] = (string) ($row['country_en'] ?? '');
			$merged[$key]['country_id'] = (int) ($row['country_id'] ?? 0);
			$merged[$key]['_part_count'] = $cnt;
		}
		$merged[$key]['count'] += $cnt;
	}

	$byCoord = press_map_books_by_coord($db, $isEng);

	$points = [];
	foreach ($merged as $key => $point) {
		unset($point['_part_count']);
		$pubs = [];
		foreach ($byCoord[$key] ?? [] as $pub) {
			$pubs[] = [
				'title' => $pub['title'],
				'url' => $pub['url'],
				'numbers' => $pub['numbers'],
			];
		}
		$point['publications'] = $pubs;
		$points[] = $point;
	}

	usort($points, static function (array $a, array $b): int {
		return $b['count'] <=> $a['count'];
	});

	return $points;
}

/**
 * @return list<array<string, mixed>>
 */
function press_map_points_for_filter(mysqli $db, bool $isEng, string $filter): array
{
	$filter = press_map_normalize_filter($filter);
	if ($filter === 'books') {
		return press_map_book_points($db, $isEng);
	}

	return press_map_points($db, $isEng, press_map_types_for_filter($filter));
}

/**
 * Publications keyed by city coordinates.
 *
 * @param list<int> $types
 * @return array<string, list<array{title: string, url: string, numbers: int}>>
 */
function press_map_publications_by_coord(mysqli $db, bool $isEng, array $types): array
{
	$typesSql = press_map_types_sql($types);
	$z = db_select(
		$db,
		'SELECT p.id, p.title, p.slug_ru, p.slug_en, p.numbers, c.lat, c.lng '
		. 'FROM press p '
		. 'INNER JOIN cities c ON c.id = p.city '
		. 'WHERE p.type IN (' . $typesSql . ') AND c.country_id <> 0 AND c.lat <> 0 AND c.lng <> 0 '
		. 'ORDER BY p.numbers DESC, p.title ASC'
	);

	$byCoord = [];
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$key = press_map_coord_key((float) ($row['lat'] ?? 0), (float) ($row['lng'] ?? 0));
		$pressRow = [
			'id' => (int) ($row['id'] ?? 0),
			'title' => (string) ($row['title'] ?? ''),
			'slug_ru' => (string) ($row['slug_ru'] ?? ''),
			'slug_en' => (string) ($row['slug_en'] ?? ''),
		];
		$byCoord[$key][] = [
			'title' => title_plain($row['title'] ?? ''),
			'url' => ezn_url_press($pressRow, $isEng),
			'numbers' => (int) ($row['numbers'] ?? 0),
		];
	}

	return $byCoord;
}

/**
 * Aggregate electronic newspapers and/or magazines by city (excludes reports, type=2).
 *
 * @param list<int>|null $types
 * @return list<array<string, mixed>>
 */
function press_map_points(mysqli $db, bool $isEng = false, ?array $types = null): array
{
	if ($types === null) {
		$types = [0, 1];
	}
	$typesSql = press_map_types_sql($types);

	$z = db_select(
		$db,
		'SELECT c.id AS city_id, c.name AS city_ru, c.name_eng AS city_en, '
		. 'c.lat, c.lng, c.country_id, '
		. 'co.country_name AS country_ru, co.country_name_eng AS country_en, '
		. 'COUNT(p.id) AS cnt '
		. 'FROM press p '
		. 'INNER JOIN cities c ON c.id = p.city '
		. 'LEFT JOIN countries co ON co.id = c.country_id '
		. 'WHERE p.type IN (' . $typesSql . ') AND c.country_id <> 0 AND c.lat <> 0 AND c.lng <> 0 '
		. 'GROUP BY c.id, c.name, c.name_eng, c.lat, c.lng, c.country_id, co.country_name, co.country_name_eng '
		. 'ORDER BY cnt DESC'
	);

	$merged = [];
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$lat = (float) ($row['lat'] ?? 0);
		$lng = (float) ($row['lng'] ?? 0);
		if ($lat == 0.0 && $lng == 0.0) {
			continue;
		}
		$cnt = (int) ($row['cnt'] ?? 0);
		if ($cnt <= 0) {
			continue;
		}
		$key = press_map_coord_key($lat, $lng);
		if (!isset($merged[$key])) {
			$merged[$key] = [
				'lat' => $lat,
				'lng' => $lng,
				'count' => $cnt,
				'city_ru' => (string) ($row['city_ru'] ?? ''),
				'city_en' => (string) ($row['city_en'] ?? ''),
				'country_ru' => (string) ($row['country_ru'] ?? ''),
				'country_en' => (string) ($row['country_en'] ?? ''),
				'country_id' => (int) ($row['country_id'] ?? 0),
				'_part_count' => $cnt,
			];
			continue;
		}
		if ($cnt > (int) ($merged[$key]['_part_count'] ?? 0)) {
			$merged[$key]['city_ru'] = (string) ($row['city_ru'] ?? '');
			$merged[$key]['city_en'] = (string) ($row['city_en'] ?? '');
			$merged[$key]['country_ru'] = (string) ($row['country_ru'] ?? '');
			$merged[$key]['country_en'] = (string) ($row['country_en'] ?? '');
			$merged[$key]['country_id'] = (int) ($row['country_id'] ?? 0);
			$merged[$key]['_part_count'] = $cnt;
		}
		$merged[$key]['count'] += $cnt;
	}

	$byCoord = press_map_publications_by_coord($db, $isEng, $types);

	$points = [];
	foreach ($merged as $key => $point) {
		unset($point['_part_count']);
		$pubs = [];
		foreach ($byCoord[$key] ?? [] as $pub) {
			$pubs[] = [
				'title' => $pub['title'],
				'url' => $pub['url'],
				'numbers' => $pub['numbers'],
			];
		}
		$point['publications'] = $pubs;
		$points[] = $point;
	}

	usort($points, static function (array $a, array $b): int {
		return $b['count'] <=> $a['count'];
	});

	return $points;
}

/**
 * TOP cities with publication titles (font size by issue count).
 *
 * @param list<array<string, mixed>> $points
 * @return list<array<string, mixed>>
 */
function press_map_top_cities(array $points, bool $isEng, int $limit = 50, string $filter = ''): array
{
	$limit = max(1, $limit);
	$filter = press_map_normalize_filter($filter);
	$top = array_slice($points, 0, $limit);
	if ($top === []) {
		return [];
	}

	$minNumbers = PHP_INT_MAX;
	$maxNumbers = 0;
	foreach ($top as $p) {
		foreach ($p['publications'] ?? [] as $pub) {
			$numbers = max(1, (int) ($pub['numbers'] ?? 0));
			$minNumbers = min($minNumbers, $numbers);
			$maxNumbers = max($maxNumbers, $numbers);
		}
	}
	if ($minNumbers === PHP_INT_MAX) {
		$minNumbers = 1;
		$maxNumbers = 1;
	}

	$cities = [];
	foreach ($top as $p) {
		$pubs = [];
		foreach ($p['publications'] ?? [] as $pub) {
			$numbers = (int) ($pub['numbers'] ?? 0);
			$pubs[] = [
				'title' => (string) ($pub['title'] ?? ''),
				'numbers' => $numbers,
				'public_url' => (string) ($pub['url'] ?? ''),
				'font_size' => press_map_font_size(max(1, $numbers), $minNumbers, $maxNumbers),
			];
		}

		$count = (int) ($p['count'] ?? 0);
		$cities[] = [
			'city_ru' => (string) ($p['city_ru'] ?? ''),
			'city_en' => (string) ($p['city_en'] ?? ''),
			'country_ru' => (string) ($p['country_ru'] ?? ''),
			'country_en' => (string) ($p['country_en'] ?? ''),
			'country_id' => (int) ($p['country_id'] ?? 0),
			'count' => $count,
			'count_label' => press_map_count_label($count, $isEng, $filter),
			'publications' => $pubs,
		];
	}

	return $cities;
}
