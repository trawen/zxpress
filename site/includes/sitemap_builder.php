<?php
/**
 * Dynamic sitemap.xml for Russian public pages in articles, books, and snailmail sections.
 */

require_once __DIR__ . '/ezine_slugs.php';

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

/**
 * Stream sitemap XML to stdout.
 */
function sitemap_render(mysqli $db, string $origin): void
{
	$ruLangId = sitemap_russian_language_id($db);
	$enLangId = sitemap_english_language_id($db);
	$pressLangAnd = sitemap_press_language_and_clause($ruLangId, $enLangId);
	$lastmodToday = sitemap_lastmod_today();

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

	// --- Section index pages (Russian, no lng=eng) ---
	sitemap_emit_url(rtrim($origin, '/') . '/ru/ezines', $lastmodToday, 'weekly', '0.9');
	sitemap_emit_url(rtrim($origin, '/') . '/books.php', $lastmodToday, 'weekly', '0.9');
	sitemap_emit_url(rtrim($origin, '/') . '/snailmail.php', $lastmodToday, 'weekly', '0.9');

	// --- Articles (press / ezines): public Russian articles only ---
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
		$path = ($canonical !== null && !str_starts_with($canonical, '/article.php'))
			? $canonical
			: 'article.php?id=' . $id;
		$entry = sitemap_url_entry($origin, $path, $lastmodToday);
		sitemap_emit_url($entry['loc'], $entry['lastmod'], 'monthly', '0.7');
	}

	// Press issue listings (Russian press with at least one public article)
	$sqlIssues = 'SELECT p.id AS press_id FROM press p '
		. 'INNER JOIN issue i ON i.id_press = p.id '
		. 'INNER JOIN articles a ON a.id_issue = i.id AND a.temp = 0 '
		. 'WHERE 1=1' . $pressLangAnd . ' '
		. 'GROUP BY p.id ORDER BY p.id ASC';
	$zIss = db_select($db, $sqlIssues);
	while ($zIss && ($row = mysqli_fetch_assoc($zIss))) {
		$pressId = (int) ($row['press_id'] ?? 0);
		if ($pressId <= 0) {
			continue;
		}
		$canonical = ezn_canonical_press_url($db, $pressId, false);
		$path = ($canonical !== null && !str_starts_with($canonical, '/issue.php'))
			? $canonical
			: 'issue.php?id=' . $pressId;
		$entry = sitemap_url_entry($origin, $path, $lastmodToday);
		sitemap_emit_url($entry['loc'], $entry['lastmod'], 'monthly', '0.6');
	}

	// --- Books (Russian editions) ---
	if ($ruLangId !== null) {
		$sqlBooks = 'SELECT id FROM books WHERE language = ? ORDER BY id ASC';
		$zBooks = db_select($db, $sqlBooks, 'i', $ruLangId);
	} else {
		$sqlBooks = 'SELECT id FROM books ORDER BY id ASC';
		$zBooks = db_select($db, $sqlBooks);
	}
	while ($zBooks && ($row = mysqli_fetch_assoc($zBooks))) {
		$id = (int) ($row['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$entry = sitemap_url_entry($origin, 'book.php?id=' . $id, $lastmodToday);
		sitemap_emit_url($entry['loc'], $entry['lastmod'], 'monthly', '0.7');
	}

	// Online book chapters (book_articles.php)
	if ($ruLangId !== null) {
		$sqlCh = 'SELECT c.ch_id FROM chapters c '
			. 'INNER JOIN books b ON b.id = c.ch_id_book '
			. 'WHERE b.language = ? AND b.online = 1 '
			. 'ORDER BY c.ch_id ASC';
		$zCh = db_select($db, $sqlCh, 'i', $ruLangId);
	} else {
		$sqlCh = 'SELECT c.ch_id FROM chapters c '
			. 'INNER JOIN books b ON b.id = c.ch_id_book '
			. 'WHERE b.online = 1 '
			. 'ORDER BY c.ch_id ASC';
		$zCh = db_select($db, $sqlCh);
	}
	while ($zCh && ($row = mysqli_fetch_assoc($zCh))) {
		$chId = (int) ($row['ch_id'] ?? 0);
		if ($chId <= 0) {
			continue;
		}
		$entry = sitemap_url_entry($origin, 'book_articles.php?id=' . $chId, $lastmodToday);
		sitemap_emit_url($entry['loc'], $entry['lastmod'], 'monthly', '0.6');
	}

	// --- Snailmail (active letters, Russian UI URLs) ---
	$zLet = db_select($db, 'SELECT id FROM letters WHERE is_active = 1 ORDER BY id ASC');
	while ($zLet && ($row = mysqli_fetch_assoc($zLet))) {
		$id = (int) ($row['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$entry = sitemap_url_entry($origin, 'snailmail.php?id=' . $id, $lastmodToday);
		sitemap_emit_url($entry['loc'], $entry['lastmod'], 'monthly', '0.7');
	}

	echo '</urlset>' . "\n";
}
