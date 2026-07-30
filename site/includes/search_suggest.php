<?php
/**
 * Layered live-search: press/book → letters → zxnet → articles → tags.
 */

require_once __DIR__ . '/ezine_slugs.php';
require_once __DIR__ . '/books_public.php';
require_once __DIR__ . '/letters_slugs.php';
require_once __DIR__ . '/zxnet_slugs.php';

function search_suggest_like_escape(string $s): string
{
	return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function search_suggest_normalize_q(string $q): string
{
	$q = trim($q);
	$q = str_replace(['ё', 'Ё'], ['е', 'Е'], $q);
	$q = preg_replace('/\s+/u', ' ', $q) ?? $q;
	return $q;
}

function search_suggest_plain_label(string $s): string
{
	$s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$s = preg_replace('/\s+/u', ' ', $s) ?? $s;
	return trim($s);
}

function search_suggest_fold_yo(string $s): string
{
	return str_replace(['ё', 'Ё'], ['е', 'Е'], $s);
}

/**
 * Truncate label for dropdown; keep the query match in view when possible.
 */
function search_suggest_truncate_label(string $label, string $q, int $max = 120): string
{
	$label = search_suggest_plain_label($label);
	if ($label === '' || mb_strlen($label) <= $max) {
		return $label;
	}

	$qFold = mb_strtolower(search_suggest_fold_yo(trim($q)), 'UTF-8');
	$hay = mb_strtolower(search_suggest_fold_yo($label), 'UTF-8');
	$pos = ($qFold !== '') ? mb_strpos($hay, $qFold) : false;
	$matchLen = ($pos !== false) ? mb_strlen($qFold) : 0;

	if ($pos === false && $qFold !== '') {
		foreach (preg_split('/\s+/u', $qFold, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
			if (mb_strlen($word) < 3) {
				continue;
			}
			$p = mb_strpos($hay, $word);
			if ($p !== false) {
				$pos = $p;
				$matchLen = mb_strlen($word);
				break;
			}
		}
	}

	if ($pos === false || ($pos + $matchLen) <= ($max - 1)) {
		return rtrim(mb_substr($label, 0, $max - 1)) . '…';
	}

	// Match is past the usual head truncate — keep context around it.
	$budget = $max - 1; // leading ellipsis
	$before = min(24, (int) $pos);
	$start = max(0, (int) $pos - $before);
	$chunk = mb_substr($label, $start, $budget);
	if (mb_strlen($label) > $start + mb_strlen($chunk)) {
		$chunk = rtrim(mb_substr($chunk, 0, max(1, mb_strlen($chunk) - 1))) . '…';
	}
	return '…' . ltrim($chunk);
}

/**
 * Escape label and wrap the first query match in <mark> (case- and ё/е-insensitive).
 */
function search_suggest_highlight(string $label, string $q): string
{
	$label = search_suggest_plain_label($label);
	$q = search_suggest_fold_yo(trim($q));
	if ($label === '' || $q === '') {
		return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	$hay = mb_strtolower(search_suggest_fold_yo($label), 'UTF-8');
	$needle = mb_strtolower($q, 'UTF-8');
	$pos = mb_strpos($hay, $needle);
	if ($pos === false) {
		$words = [];
		foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
			// Skip tiny tokens ("2", "в") — they cause noisy false highlights.
			if (mb_strlen($word) >= 3) {
				$words[] = $word;
			}
		}
		return search_suggest_highlight_words($label, $words);
	}

	$len = mb_strlen($q);
	$before = mb_substr($label, 0, $pos);
	$match = mb_substr($label, $pos, $len);
	$after = mb_substr($label, $pos + $len);
	return htmlspecialchars($before, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
		. '<mark class="smn-suggest-hit">' . htmlspecialchars($match, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</mark>'
		. htmlspecialchars($after, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param list<string> $words
 */
function search_suggest_highlight_words(string $label, array $words): string
{
	if ($words === []) {
		return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	$parts = [];
	$offset = 0;
	$labelLen = mb_strlen($label);
	$hayFull = mb_strtolower(search_suggest_fold_yo($label), 'UTF-8');

	while ($offset < $labelLen) {
		$bestPos = null;
		$bestLen = 0;
		$restHay = mb_substr($hayFull, $offset);
		foreach ($words as $word) {
			$word = search_suggest_fold_yo(trim($word));
			if ($word === '') {
				continue;
			}
			$needle = mb_strtolower($word, 'UTF-8');
			$p = mb_strpos($restHay, $needle);
			if ($p === false) {
				continue;
			}
			$abs = $offset + $p;
			$wlen = mb_strlen($word);
			if ($bestPos === null || $abs < $bestPos || ($abs === $bestPos && $wlen > $bestLen)) {
				$bestPos = $abs;
				$bestLen = $wlen;
			}
		}
		if ($bestPos === null) {
			$parts[] = htmlspecialchars(mb_substr($label, $offset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			break;
		}
		if ($bestPos > $offset) {
			$parts[] = htmlspecialchars(mb_substr($label, $offset, $bestPos - $offset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
		$parts[] = '<mark class="smn-suggest-hit">'
			. htmlspecialchars(mb_substr($label, $bestPos, $bestLen), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
			. '</mark>';
		$offset = $bestPos + $bestLen;
	}

	return implode('', $parts);
}

/**
 * @param array{type:string,label:string,meta?:string,url?:string,kind?:string} $item
 * @return array{type:string,label:string,label_html:string,meta?:string,url?:string,kind?:string}
 */
function search_suggest_with_highlight(array $item, string $q): array
{
	$item['label_html'] = search_suggest_highlight((string) ($item['label'] ?? ''), $q);
	return $item;
}

/**
 * @return list<array{type:string,label:string,meta?:string,url?:string,kind?:string}>
 */
function search_suggest_press(mysqli $db, string $q, bool $isEng, int $limit): array
{
	if ($limit <= 0 || $q === '') {
		return [];
	}
	$esc = search_suggest_like_escape($q);
	$contains = '%' . $esc . '%';
	$prefix = $esc . '%';
	$sql = 'SELECT id, title, type, slug_ru, slug_en'
		. ' FROM press'
		. ' WHERE type IN (0, 1) AND title LIKE ?'
		. ' ORDER BY (title LIKE ?) DESC, title ASC'
		. ' LIMIT ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('ssi', $contains, $prefix, $limit);
	$stmt->execute();
	$res = $stmt->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$type = (int) ($row['type'] ?? 0);
		if ($isEng) {
			$kind = $type === 1 ? 'magazine' : 'newspaper';
			$meta = $type === 1 ? 'Magazine' : 'Newspaper';
		} else {
			$kind = $type === 1 ? 'magazine' : 'newspaper';
			$meta = $type === 1 ? 'Журнал' : 'Газета';
		}
		$url = ezn_url_press([
			'id' => (int) $row['id'],
			'title' => (string) $row['title'],
			'slug_ru' => (string) ($row['slug_ru'] ?? ''),
			'slug_en' => (string) ($row['slug_en'] ?? ''),
		], $isEng);
		$out[] = [
			'type' => 'press',
			'label' => search_suggest_plain_label((string) $row['title']),
			'meta' => $meta,
			'kind' => $kind,
			'url' => $url,
		];
	}
	$stmt->close();
	return $out;
}

/**
 * @return list<array{type:string,label:string,meta?:string,url?:string}>
 */
function search_suggest_books(mysqli $db, string $q, bool $isEng, int $limit): array
{
	if ($limit <= 0 || $q === '') {
		return [];
	}
	$esc = search_suggest_like_escape($q);
	$contains = '%' . $esc . '%';
	$prefix1 = $esc . '%';
	$sql = 'SELECT id, title1, title2, series'
		. ' FROM books'
		. ' WHERE title1 LIKE ? OR title2 LIKE ? OR series LIKE ?'
		. ' ORDER BY (title1 LIKE ?) DESC, (title2 LIKE ?) DESC, title1 ASC'
		. ' LIMIT ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('sssssi', $contains, $contains, $contains, $prefix1, $prefix1, $limit);
	$stmt->execute();
	$res = $stmt->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$t1 = trim((string) ($row['title1'] ?? ''));
		$t2 = trim((string) ($row['title2'] ?? ''));
		$label = $t1;
		if ($t2 !== '' && mb_stripos($t2, $q) !== false && mb_stripos($t1, $q) === false) {
			$label = $t2;
		} elseif ($t2 !== '' && $t1 !== '') {
			$label = $t1 . ' — ' . $t2;
			if (mb_strlen($label) > 96) {
				$label = $t1;
			}
		}
		$out[] = [
			'type' => 'book',
			'label' => search_suggest_plain_label($label),
			'meta' => $isEng ? 'Book' : 'Книга',
			'url' => books_url_book((int) $row['id'], $isEng),
		];
	}
	$stmt->close();
	return $out;
}

/**
 * @return list<array{type:string,label:string,meta?:string,url?:string}>
 */
function search_suggest_articles(mysqli $db, string $q, bool $isEng, int $limit): array
{
	if ($limit <= 0 || $q === '') {
		return [];
	}
	$esc = search_suggest_like_escape($q);
	$contains = '%' . $esc . '%';
	$prefix = $esc . '%';
	$sql = 'SELECT a.id, a.title, a.title_eng, p.title AS press_title, i.title AS issue_title'
		. ' FROM articles a'
		. ' INNER JOIN issue i ON i.id = a.id_issue'
		. ' INNER JOIN press p ON p.id = a.id_press'
		. ' WHERE a.temp = 0 AND (a.title LIKE ? OR a.title_eng LIKE ? OR a.text_ru LIKE ? OR a.text_en LIKE ?)'
		. ' ORDER BY (a.title LIKE ?) DESC, (a.title_eng LIKE ?) DESC, a.title ASC'
		. ' LIMIT ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('ssssssi', $contains, $contains, $contains, $contains, $prefix, $prefix, $limit);
	$stmt->execute();
	$res = $stmt->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$titleRu = trim((string) ($row['title'] ?? ''));
		$titleEn = trim((string) ($row['title_eng'] ?? ''));
		$label = $isEng && $titleEn !== '' ? $titleEn : $titleRu;
		if ($label === '') {
			$label = $titleEn !== '' ? $titleEn : $titleRu;
		}
		$label = search_suggest_plain_label($label);
		$label = search_suggest_truncate_label($label, $q, 120);
		$pressTitle = search_suggest_plain_label((string) ($row['press_title'] ?? ''));
		if ($isEng) {
			$meta = $pressTitle !== '' ? ('Article - ' . $pressTitle) : 'Article';
		} else {
			$meta = $pressTitle !== '' ? ('Статья - ' . $pressTitle) : 'Статья';
		}
		$url = ezn_canonical_article_url($db, (int) $row['id'], $isEng);
		$out[] = [
			'type' => 'article',
			'label' => $label,
			'meta' => $meta,
			'url' => $url ?? ('/article.php?id=' . (int) $row['id'] . ($isEng ? '&lng=eng' : '')),
		];
	}
	$stmt->close();
	return $out;
}

/**
 * @return list<array{type:string,label:string,meta?:string,url?:string}>
 */
function search_suggest_letters(mysqli $db, string $q, bool $isEng, int $limit): array
{
	if ($limit <= 0 || $q === '') {
		return [];
	}
	$esc = search_suggest_like_escape($q);
	$contains = '%' . $esc . '%';
	$prefix = $esc . '%';
	$sql = 'SELECT id, title_ru, title_en, slug_ru, slug_en'
		. ' FROM letters'
		. ' WHERE is_active = 1 AND (title_ru LIKE ? OR title_en LIKE ?)'
		. ' ORDER BY (title_ru LIKE ?) DESC, (title_en LIKE ?) DESC, title_ru ASC'
		. ' LIMIT ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('ssssi', $contains, $contains, $prefix, $prefix, $limit);
	$stmt->execute();
	$res = $stmt->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$titleRu = search_suggest_plain_label((string) ($row['title_ru'] ?? ''));
		$titleEn = search_suggest_plain_label((string) ($row['title_en'] ?? ''));
		$label = $isEng && $titleEn !== '' ? $titleEn : $titleRu;
		if ($label === '') {
			$label = $titleEn !== '' ? $titleEn : $titleRu;
		}
		if ($label === '') {
			continue;
		}
		if (mb_strlen($label) > 120) {
			$label = rtrim(mb_substr($label, 0, 117)) . '…';
		}
		$out[] = [
			'type' => 'letter',
			'label' => $label,
			'meta' => $isEng ? 'Letter' : 'Письмо',
			'url' => letters_url_letter($row, $isEng),
		];
	}
	$stmt->close();
	return $out;
}

/**
 * @return list<array{type:string,label:string,meta?:string,url?:string}>
 */
function search_suggest_zxnet(mysqli $db, string $q, bool $isEng, int $limit): array
{
	if ($limit <= 0 || $q === '') {
		return [];
	}
	$esc = search_suggest_like_escape($q);
	$contains = '%' . $esc . '%';
	$prefix = $esc . '%';
	$sql = 'SELECT s.id, s.echo_id, s.title, s.title_en, s.slug_ru, s.slug_en, e.title AS echo_title'
		. ' FROM echos_subjs2 s'
		. ' INNER JOIN echos_titles2 e ON e.id = s.echo_id'
		. ' WHERE (s.title LIKE ? OR s.title_en LIKE ?) AND LOWER(e.title) <> ?'
		. ' ORDER BY (s.title LIKE ?) DESC, (s.title_en LIKE ?) DESC, s.title ASC'
		. ' LIMIT ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return [];
	}
	$hiddenEcho = 'e2e.talk';
	$stmt->bind_param('sssssi', $contains, $contains, $hiddenEcho, $prefix, $prefix, $limit);
	$stmt->execute();
	$res = $stmt->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$titleRu = search_suggest_plain_label((string) ($row['title'] ?? ''));
		$titleEn = search_suggest_plain_label((string) ($row['title_en'] ?? ''));
		$label = $isEng && $titleEn !== '' ? $titleEn : $titleRu;
		if ($label === '') {
			$label = $titleEn !== '' ? $titleEn : $titleRu;
		}
		if ($label === '') {
			continue;
		}
		if (mb_strlen($label) > 120) {
			$label = rtrim(mb_substr($label, 0, 117)) . '…';
		}
		$echoTitle = search_suggest_plain_label((string) ($row['echo_title'] ?? ''));
		$meta = $isEng ? 'ZXNet topic' : 'Тема ZXNet';
		if ($echoTitle !== '') {
			$meta .= ' — ' . $echoTitle;
		}
		$topicSlug = zxnet_row_slug($row, $isEng);
		$out[] = [
			'type' => 'zxnet',
			'label' => $label,
			'meta' => $meta,
			'url' => zxnet_url_topic($echoTitle, $topicSlug, $isEng, (int) ($row['id'] ?? 0)),
		];
	}
	$stmt->close();
	return $out;
}

/**
 * @return list<array{type:string,label:string,meta?:string,url?:string}>
 */
function search_suggest_tags(mysqli $db, string $q, bool $isEng, int $limit): array
{
	if ($limit <= 0 || $q === '') {
		return [];
	}
	$esc = search_suggest_like_escape($q);
	$contains = '%' . $esc . '%';
	$prefix = $esc . '%';
	$sql = 'SELECT id, tag_name, tag_alias'
		. ' FROM tags'
		. ' WHERE tag_name LIKE ? OR tag_alias LIKE ?'
		. ' ORDER BY (tag_name LIKE ?) DESC, (tag_alias LIKE ?) DESC, tag_name ASC'
		. ' LIMIT ?';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return [];
	}
	$stmt->bind_param('ssssi', $contains, $contains, $prefix, $prefix, $limit);
	$stmt->execute();
	$res = $stmt->get_result();
	$out = [];
	while ($row = $res->fetch_assoc()) {
		$name = search_suggest_plain_label((string) ($row['tag_name'] ?? ''));
		$alias = search_suggest_plain_label((string) ($row['tag_alias'] ?? ''));
		$label = $name;
		if ($label === '' && $alias !== '') {
			$label = $alias;
		}
		if ($label === '') {
			continue;
		}
		$id = (int) ($row['id'] ?? 0);
		$out[] = [
			'type' => 'tag',
			'label' => $label,
			'meta' => $isEng ? 'Tag' : 'Тег',
			'url' => '/tag.php?id=' . $id . ($isEng ? '&lng=eng' : ''),
		];
	}
	$stmt->close();
	return $out;
}

/**
 * Ranked live suggestions for the search box.
 *
 * @return list<array{type:string,label:string,meta?:string,url?:string,kind?:string}>
 */
function search_suggest_all(mysqli $db, string $q, bool $isEng, bool $uiNew = false): array
{
	$q = search_suggest_normalize_q($q);
	if (mb_strlen($q) < 2) {
		return [];
	}

	if ($uiNew) {
		if (!defined('EZINES_SECTION')) {
			define('EZINES_SECTION', 'ezines');
		}
		if (!defined('BOOKS_UI_VARIANT')) {
			define('BOOKS_UI_VARIANT', 'new');
		}
		if (!defined('LETTERS_SECTION')) {
			define('LETTERS_SECTION', 'snailmail');
		}
		if (!defined('ZXNET_UI_VARIANT')) {
			define('ZXNET_UI_VARIANT', 'new');
		}
	}

	// Keep entity groups short so later hits stay visible in the dropdown.
	$maxPress = 2;
	$maxBooks = 2;
	$maxLetters = 2;
	$maxZxnet = 2;
	$maxArticles = 10;
	$maxTags = 3;
	$maxTotal = 22;

	$press = search_suggest_press($db, $q, $isEng, $maxPress);
	$books = search_suggest_books($db, $q, $isEng, $maxBooks);
	$letters = search_suggest_letters($db, $q, $isEng, $maxLetters);
	$zxnet = search_suggest_zxnet($db, $q, $isEng, $maxZxnet);
	$articles = [];
	$tags = search_suggest_tags($db, $q, $isEng, $maxTags);

	// Prefer: press/books → letters → zxnet → articles → tags (bottom).
	$seen = [];
	$out = [];
	$maxMain = max(0, $maxTotal - $maxTags);
	foreach (array_merge($press, $books, $letters, $zxnet, $articles) as $item) {
		$key = mb_strtolower((string) ($item['label'] ?? ''));
		if ($key === '' || isset($seen[$key])) {
			continue;
		}
		$seen[$key] = true;
		$out[] = search_suggest_with_highlight($item, $q);
		if (count($out) >= $maxMain) {
			break;
		}
	}
	foreach ($tags as $item) {
		$key = mb_strtolower((string) ($item['label'] ?? ''));
		if ($key === '' || isset($seen[$key])) {
			continue;
		}
		$seen[$key] = true;
		$out[] = search_suggest_with_highlight($item, $q);
		if (count($out) >= $maxTotal) {
			break;
		}
	}
	return $out;
}
