<?php
/**
 * Dynamic sitemap.xml for Russian pretty URLs of the new public site.
 * Excludes legacy *.php paths and periodicals.
 */

require_once __DIR__ . '/ezine_slugs.php';
require_once __DIR__ . '/letters_slugs.php';
require_once __DIR__ . '/authors_slugs.php';
require_once __DIR__ . '/books_public.php';
require_once __DIR__ . '/press_map.php';
require_once __DIR__ . '/zxnet_slugs.php';

/**
 * Resolve languages.id for Russian editions (books, press). Null if unknown — no language filter.
 */
function sitemap_russian_language_id(mysqli $db): ?int
{
	static $resolved = false;
	static $id = null;
	if ($resolved) {
		return $id;
	}
	$resolved = true;

	$z = db_select($db, 'SELECT id, name FROM languages ORDER BY id ASC');
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$name = mb_strtolower(trim((string) ($row['name'] ?? '')), 'UTF-8');
		if ($name === '' || !preg_match('/рус|russian|^ru$|русский/u', $name)) {
			continue;
		}
		$id = (int) $row['id'];
		return $id;
	}

	return null;
}

/**
 * Resolve languages.id for non-Russian editions to exclude from the sitemap.
 */
function sitemap_english_language_id(mysqli $db): ?int
{
	static $resolved = false;
	static $id = null;
	if ($resolved) {
		return $id;
	}
	$resolved = true;

	$z = db_select($db, 'SELECT id, name FROM languages ORDER BY id ASC');
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$name = mb_strtolower(trim((string) ($row['name'] ?? '')), 'UTF-8');
		if ($name === '' || !preg_match('/англ|english|^en$|английский/u', $name)) {
			continue;
		}
		$id = (int) $row['id'];
		return $id;
	}

	return null;
}

/**
 * Press uses language=0 as legacy «Russian»; books use languages.id directly.
 */
function sitemap_press_language_and_clause(?int $ruLangId, ?int $enLangId): string
{
	$parts = [];
	if ($enLangId !== null) {
		$parts[] = 'p.language != ' . (int) $enLangId;
	}
	if ($ruLangId !== null) {
		$parts[] = '(p.language = 0 OR p.language = ' . (int) $ruLangId . ')';
	}
	if ($parts === []) {
		return '';
	}

	return ' AND ' . implode(' AND ', $parts);
}

function sitemap_xml_escape(string $s): string
{
	return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** ISO date (Y-m-d) for lastmod — always the day the sitemap is generated. */
function sitemap_lastmod_today(): string
{
	return gmdate('Y-m-d');
}

/**
 * Only Russian pretty paths of the new UI (no legacy *.php).
 */
function sitemap_is_pretty_ru_path(string $path): bool
{
	$path = trim($path);
	if ($path === '') {
		return false;
	}
	$pathOnly = parse_url($path, PHP_URL_PATH);
	if (!is_string($pathOnly) || $pathOnly === '') {
		$pathOnly = strtok($path, '?#') ?: $path;
	}
	$pathOnly = '/' . ltrim($pathOnly, '/');
	if (str_contains($pathOnly, '.php')) {
		return false;
	}
	if (str_contains($path, '#')) {
		return false;
	}

	return str_starts_with($pathOnly, '/ru/');
}

/**
 * @return array{loc: string, lastmod: string}
 */
function sitemap_url_entry(string $origin, string $path, string $lastmod): array
{
	$base = rtrim($origin, '/');
	$path = '/' . ltrim($path, '/');
	return [
		'loc' => $base . $path,
		'lastmod' => $lastmod,
	];
}

function sitemap_emit_url(string $loc, string $lastmod, string $changefreq, string $priority): void
{
	echo "  <url>\n";
	echo '    <loc>' . sitemap_xml_escape($loc) . "</loc>\n";
	if ($lastmod !== '') {
		echo '    <lastmod>' . $lastmod . "</lastmod>\n";
	}
	echo '    <changefreq>' . $changefreq . "</changefreq>\n";
	echo '    <priority>' . $priority . "</priority>\n";
	echo "  </url>\n";
}

function sitemap_emit_path(
	string $origin,
	string $path,
	string $lastmod,
	string $changefreq,
	string $priority
): void {
	if (!sitemap_is_pretty_ru_path($path)) {
		return;
	}
	$entry = sitemap_url_entry($origin, $path, $lastmod);
	sitemap_emit_url($entry['loc'], $entry['lastmod'], $changefreq, $priority);
}

/**
 * Stream sitemap XML to stdout.
 */
function sitemap_render(mysqli $db, string $origin): void
{
	$ruLangId = sitemap_russian_language_id($db);
	$enLangId = sitemap_english_language_id($db);
	$pressLangAnd = sitemap_press_language_and_clause($ruLangId, $enLangId);
	$lastmodToday = sitemap_lastmod_today();
	$origin = rtrim($origin, '/');

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

	// --- Section index pages (new RU UI) ---
	$sectionIndexes = [
		['/ru/ezines', 'weekly', '0.9'],
		['/ru/ezines/papers', 'weekly', '0.8'],
		['/ru/ezines/magazines', 'weekly', '0.8'],
		['/ru/ezines/reports', 'weekly', '0.8'],
		['/ru/books', 'weekly', '0.9'],
		['/ru/snailmail', 'weekly', '0.9'],
		['/ru/authors', 'weekly', '0.8'],
		['/ru/zxnet', 'weekly', '0.8'],
		['/ru/map', 'weekly', '0.8'],
		['/ru/map/papers', 'weekly', '0.7'],
		['/ru/map/magazines', 'weekly', '0.7'],
		['/ru/map/books', 'weekly', '0.7'],
		['/ru/calendar', 'weekly', '0.8'],
		['/ru/categories', 'weekly', '0.8'],
		['/ru/gallery', 'weekly', '0.7'],
		['/ru/guestbook', 'monthly', '0.5'],
		['/ru/updates-activity', 'daily', '0.7'],
	];
	foreach ($sectionIndexes as [$path, $freq, $prio]) {
		sitemap_emit_path($origin, $path, $lastmodToday, $freq, $prio);
	}

	// --- Articles (pretty /ru/ezines/... only) ---
	$sqlArticles = 'SELECT a.id FROM articles a '
		. 'INNER JOIN issue i ON i.id = a.id_issue '
		. 'INNER JOIN press p ON p.id = i.id_press '
		. 'WHERE a.temp = 0' . $pressLangAnd . ' '
		. 'ORDER BY a.id ASC';
	$z = db_select($db, $sqlArticles);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$id = (int) ($row['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$canonical = ezn_canonical_article_url($db, $id, false);
		if ($canonical === null) {
			continue;
		}
		sitemap_emit_path($origin, $canonical, $lastmodToday, 'monthly', '0.7');
	}

	// --- Press pages ---
	$sqlPress = 'SELECT p.id AS press_id FROM press p '
		. 'INNER JOIN issue i ON i.id_press = p.id '
		. 'INNER JOIN articles a ON a.id_issue = i.id AND a.temp = 0 '
		. 'WHERE 1=1' . $pressLangAnd . ' '
		. 'GROUP BY p.id ORDER BY p.id ASC';
	$zPress = db_select($db, $sqlPress);
	while ($zPress && ($row = mysqli_fetch_assoc($zPress))) {
		$pressId = (int) ($row['press_id'] ?? 0);
		if ($pressId <= 0) {
			continue;
		}
		$canonical = ezn_canonical_press_url($db, $pressId, false);
		if ($canonical === null) {
			continue;
		}
		sitemap_emit_path($origin, $canonical, $lastmodToday, 'monthly', '0.6');
	}

	// --- Issue pages (pretty slug pairs only) ---
	$sqlIssues = 'SELECT i.id AS issue_id, i.id_press AS press_id FROM issue i '
		. 'INNER JOIN press p ON p.id = i.id_press '
		. 'INNER JOIN articles a ON a.id_issue = i.id AND a.temp = 0 '
		. 'WHERE 1=1' . $pressLangAnd . ' '
		. 'GROUP BY i.id, i.id_press ORDER BY i.id ASC';
	$zIss = db_select($db, $sqlIssues);
	while ($zIss && ($row = mysqli_fetch_assoc($zIss))) {
		$pressId = (int) ($row['press_id'] ?? 0);
		$issueId = (int) ($row['issue_id'] ?? 0);
		if ($pressId <= 0 || $issueId <= 0) {
			continue;
		}
		$canonical = ezn_canonical_issue_url_by_ids($db, $pressId, $issueId, false);
		if ($canonical === null) {
			continue;
		}
		sitemap_emit_path($origin, $canonical, $lastmodToday, 'monthly', '0.65');
	}

	// --- Books ---
	if ($ruLangId !== null) {
		$zBooks = db_select($db, 'SELECT id FROM books WHERE language = ? ORDER BY id ASC', 'i', $ruLangId);
	} else {
		$zBooks = db_select($db, 'SELECT id FROM books ORDER BY id ASC');
	}
	while ($zBooks && ($row = mysqli_fetch_assoc($zBooks))) {
		$id = (int) ($row['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		sitemap_emit_path($origin, books_url_book($id, false), $lastmodToday, 'monthly', '0.7');
	}

	// --- Online book chapters ---
	if ($ruLangId !== null) {
		$zCh = db_select(
			$db,
			'SELECT c.ch_id FROM chapters c '
			. 'INNER JOIN books b ON b.id = c.ch_id_book '
			. 'WHERE b.language = ? AND b.online = 1 '
			. 'ORDER BY c.ch_id ASC',
			'i',
			$ruLangId
		);
	} else {
		$zCh = db_select(
			$db,
			'SELECT c.ch_id FROM chapters c '
			. 'INNER JOIN books b ON b.id = c.ch_id_book '
			. 'WHERE b.online = 1 '
			. 'ORDER BY c.ch_id ASC'
		);
	}
	while ($zCh && ($row = mysqli_fetch_assoc($zCh))) {
		$chId = (int) ($row['ch_id'] ?? 0);
		if ($chId <= 0) {
			continue;
		}
		sitemap_emit_path($origin, books_url_chapter($chId, false), $lastmodToday, 'monthly', '0.6');
	}

	// --- Snailmail letters ---
	$zLet = db_select($db, 'SELECT id, slug_ru, slug_en FROM letters WHERE is_active = 1 ORDER BY id ASC');
	while ($zLet && ($row = mysqli_fetch_assoc($zLet))) {
		$id = (int) ($row['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		sitemap_emit_path($origin, letters_url_letter($row, false), $lastmodToday, 'monthly', '0.7');
	}

	// --- Authors ---
	$zAuth = db_select(
		$db,
		'SELECT id, slug_ru, slug_en FROM authors '
		. 'WHERE COALESCE(is_active, 1) = 1 AND slug_ru IS NOT NULL AND slug_ru <> \'\' '
		. 'ORDER BY id ASC'
	);
	while ($zAuth && ($row = mysqli_fetch_assoc($zAuth))) {
		$path = authors_url($row, false);
		if ($path === '' || rtrim($path, '/') === '/ru/authors') {
			continue;
		}
		sitemap_emit_path($origin, $path, $lastmodToday, 'monthly', '0.6');
	}

	// --- Article categories ---
	$rCat = @$db->query("SHOW TABLES LIKE 'ezine_categories'");
	if ($rCat && $rCat->num_rows > 0) {
		$zCat = db_select($db, 'SELECT id FROM ezine_categories ORDER BY id ASC');
		while ($zCat && ($row = mysqli_fetch_assoc($zCat))) {
			$cid = (int) ($row['id'] ?? 0);
			if ($cid <= 0) {
				continue;
			}
			sitemap_emit_path($origin, '/ru/categories/' . $cid, $lastmodToday, 'monthly', '0.6');
		}
	}

	// --- ZXNet echoes + topics ---
	$rEcho = @$db->query("SHOW TABLES LIKE 'echos_titles2'");
	if ($rEcho && $rEcho->num_rows > 0) {
		$zEcho = db_select(
			$db,
			'SELECT t.id, t.title FROM echos_titles2 t '
			. 'WHERE EXISTS (SELECT 1 FROM echos_subjs2 s WHERE s.echo_id = t.id) '
			. 'ORDER BY t.id ASC'
		);
		while ($zEcho && ($row = mysqli_fetch_assoc($zEcho))) {
			$title = (string) ($row['title'] ?? '');
			$echoId = (int) ($row['id'] ?? 0);
			if ($title === '' || $echoId <= 0) {
				continue;
			}
			sitemap_emit_path($origin, zxnet_url_echo($title, false), $lastmodToday, 'monthly', '0.6');

			$zTopic = db_select(
				$db,
				'SELECT id, slug_ru, slug_en FROM echos_subjs2 WHERE echo_id=? ORDER BY id ASC',
				'i',
				$echoId
			);
			while ($zTopic && ($topic = mysqli_fetch_assoc($zTopic))) {
				$topicId = (int) ($topic['id'] ?? 0);
				$slug = zxnet_row_slug($topic, false);
				if ($topicId <= 0) {
					continue;
				}
				sitemap_emit_path(
					$origin,
					zxnet_url_topic($title, $slug, false, $topicId),
					$lastmodToday,
					'monthly',
					'0.5'
				);
			}
		}
	}

	echo '</urlset>' . "\n";
}
