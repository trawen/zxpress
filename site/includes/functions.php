<?php
/**
 * Shared utility functions.
 * Included from init.inc.
 */

/**
 * Format article title: collapse multiple spaces, bold the part before " - ".
 */
function title($title) {
	$flag = 0;
	$t = '';
	$l = strlen($title);
	for ($n = 0; $n < $l; $n++) {
		$s = substr($title, $n, 1);
		if ($s != " " && $flag == 0) { $t .= $s; }
		elseif ($s == " " && $flag == 0) { $t .= $s; $flag = 1; }
		elseif ($s != " " && $flag == 1) { $t .= $s; $flag = 0; }
	}
	$title = $t;

	$p1 = strpos(" " . $title, "<b>");
	if ($p1 == false) {
		$p1 = strpos($title, " - ");
		if ($p1) {
			$title = "<b>" . substr($title, 0, $p1) . "</b>" . substr($title, $p1);
		}
	}
	return str_replace("'", "&#039;", $title);
}

/**
 * Convert newlines to <p> tags.
 */
function nl2p($string) {
	$paragraphs = '';
	foreach (explode("\n", $string) as $line) {
		if (trim($line)) {
			$paragraphs .= "<p class='news'>" . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "</p>";
		}
	}
	return $paragraphs;
}

/**
 * Log when admin normalization changed a P-field (rate-limited per request).
 */
function log_content_plain_normalized(string $label, string $before, string $after): void {
	static $n = 0;
	if ($n >= 16 || $before === $after) {
		return;
	}
	error_log('[content-hygiene] INFO normalized ' . $label . ' len_in=' . strlen($before) . ' len_out=' . strlen($after));
	$n++;
}

/**
 * Strip BBCode/tags, decode entities, collapse runs of spaces (same rule as title() spacing).
 * Use for P-fields at save time; title_plain() delegates here for display/meta.
 */
function plain_text_normalize_for_storage(string $s): string {
	$t = preg_replace('/\[\/?[a-zA-Z*]+(?:=[^\]]*)?\]/', '', $s);
	$t = strip_tags($t);
	$t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	$flag = 0;
	$out = '';
	$l = strlen($t);
	for ($n = 0; $n < $l; $n++) {
		$ch = substr($t, $n, 1);
		if ($ch != ' ' && $flag == 0) {
			$out .= $ch;
		} elseif ($ch == ' ' && $flag == 0) {
			$out .= $ch;
			$flag = 1;
		} elseif ($ch != ' ' && $flag == 1) {
			$out .= $ch;
			$flag = 0;
		}
	}
	if (getenv('CONTENT_NORMALIZE_DEBUG')) {
		static $dbg = 0;
		if ($dbg < 32 && $s !== $out) {
			error_log('[plain_text_normalize_for_storage] DEBUG changed len_in=' . strlen($s) . ' len_out=' . strlen($out));
			$dbg++;
		}
	}
	return trim($out);
}

/**
 * Return plain-text title (no HTML tags) for use in <title>, <meta>, alt attributes.
 * Strips BBCode-style markup ([b], [url=...], etc.) and decodes entities — do not use title()
 * here (title() injects &#039; and <b>, which breaks plain text and double-escapes in Smarty).
 */
function title_plain($title) {
	return plain_text_normalize_for_storage((string) $title);
}

/**
 * Add name_plain on each node (recursive tree with optional children in tree[]).
 * Used for menu/rubrics trees under Smarty escape_html.
 *
 * @param list<array<string,mixed>>|mixed $tree
 * @return list<array<string,mixed>>|mixed
 */
function content_tree_add_name_plain($tree) {
	if (!is_array($tree)) {
		return $tree;
	}
	foreach ($tree as &$n) {
		if (is_array($n)) {
			$n['name_plain'] = title_plain((string) ($n['name'] ?? ''));
			if (!empty($n['tree']) && is_array($n['tree'])) {
				$n['tree'] = content_tree_add_name_plain($n['tree']);
			}
		}
	}
	unset($n);
	return $tree;
}

/**
 * Plain-text breadcrumb for rubrics tree (walk rubrics.parent upward).
 */
function rubrics_breadcrumb_plain_labels(mysqli $db, int $startId): string {
	$names = [];
	$cur = $startId;
	for ($guard = 0; $cur > 0 && $guard < 64; $guard++) {
		$stmt = $db->prepare('SELECT id, parent, name FROM rubrics WHERE id=? LIMIT 1');
		if (!$stmt) {
			break;
		}
		$stmt->bind_param('i', $cur);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if (!$row) {
			break;
		}
		$names[] = title_plain((string) ($row['name'] ?? ''));
		$cur = (int) ($row['parent'] ?? 0);
	}
	return implode(' → ', array_reverse($names));
}

/**
 * Decode HTML/XML entities in legacy DB strings so Smarty auto-escape (or |escape) applies once.
 * Does not strip tags — use strip_tags() first if the field must be plain text only.
 */
function plain_text_decode_entities(?string $s): string {
	$s = (string) $s;
	if ($s === '') {
		return '';
	}
	return html_entity_decode($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Article title for HTML lists/links: limited BBCode → safe HTML, then title() “bold before —”.
 * Placeholders avoid double-escaping trusted <b>/<a> segments.
 */
function article_title_list_html(?string $title): string {
	$raw = plain_text_decode_entities(strip_tags((string) $title));
	$parts = [];
	$i = 0;
	$put = static function (string $html) use (&$parts, &$i): string {
		$key = '@@TITLEPH' . $i++ . '@@';
		$parts[$key] = $html;
		return $key;
	};
	$raw = preg_replace_callback(
		'/\[url=(["\']?)([^\]\s]+)\1\](.*?)\[\/url\]/is',
		static function (array $m) use ($put): string {
			$url = trim($m[2]);
			$text = htmlspecialchars($m[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			if ($url === '' || !preg_match('#\Ahttps?://#i', $url)) {
				return $put($text);
			}
			return $put('<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $text . '</a>');
		},
		$raw
	);
	for ($k = 0; $k < 8; $k++) {
		$prev = $raw;
		$raw = preg_replace_callback(
			'/\[i\](.*?)\[\/i\]/is',
			static function (array $m) use ($put): string {
				return $put('<i>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</i>');
			},
			$raw
		);
		$raw = preg_replace_callback(
			'/\[u\](.*?)\[\/u\]/is',
			static function (array $m) use ($put): string {
				return $put('<u>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</u>');
			},
			$raw
		);
		$raw = preg_replace_callback(
			'/\[b\](.*?)\[\/b\]/is',
			static function (array $m) use ($put): string {
				return $put('<b>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>');
			},
			$raw
		);
		if ($raw === $prev) {
			break;
		}
	}
	$raw = preg_replace('/\[\/?[a-zA-Z*]+(?:=[^\]]*)?\]/', '', $raw);
	$raw = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	if ($parts !== []) {
		$raw = strtr($raw, $parts);
	}
	return title($raw);
}

/**
 * Generate a 6-char captcha code using cryptographically secure randomness.
 * Stores code and token in session. Returns the token for template embedding.
 */
function generate_captcha(): string {
    $captcha_code = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha_code .= chr(random_int(97, 122));
    }
    $_SESSION['captcha_code'] = $captcha_code;
    $captcha_token = bin2hex(random_bytes(16));
    $_SESSION['captcha_token'] = $captcha_token;
    return $captcha_token;
}

/**
 * Format date with Russian month/day names.
 */
function rusdate($d, $format = 'j %MONTH% H:i', $offset = 0) {
	$montharr = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
	$dayarr = array('понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье');

	$d += 3600 * $offset;

	$sarr = array('/%MONTH%/i', '/%DAYWEEK%/i');
	$rarr = array($montharr[date("m", $d) - 1], $dayarr[date("N", $d) - 1]);

	$format = preg_replace($sarr, $rarr, $format);
	return date($format, $d);
}

/**
 * Функция возвращает окончание для множественного числа слова на основании числа и массива окончаний
 * @param  $number Integer Число на основе которого нужно сформировать окончание
 * @param  $endingsArray  Array Массив слов или окончаний для чисел (1, 4, 5),
 *         например array('яблоко', 'яблока', 'яблок')
 * @return String
 */
function getNumEnding($number, $endingArray)
{
	$number = $number % 100;
	if ($number >= 11 && $number <= 19) {
		$ending = $endingArray[2];
	} else {
		$i = $number % 10;
		switch ($i) {
			case (1):
				$ending = $endingArray[0];
				break;
			case (2):
			case (3):
			case (4):
				$ending = $endingArray[1];
				break;
			default:
				$ending = $endingArray[2];
		}
	}
	return $ending;
}

function word_limiter($text, $limit = 30)
{
	if (strlen($text) > $limit) {
		$words = str_word_count($text, 2, $chars);
		$words = array_reverse($words, true);
		foreach ($words as $length => $word) {
			if ($length + strlen($word) >= $limit) {
				array_shift($words);
			} else {
				break;
			}
		}
		$words = array_reverse($words);
		$text = implode(" ", $words);
	}
	return $text;
}

function friendly_url($params, $smarty = "")
{
	if (is_array($params)) {
		$string = $params['string'];
	} else {
		$string = $params;
	}

	$string = html_entity_decode(strip_tags($string), ENT_QUOTES);

	$replace = array(
		"'" => "",
		"`" => "",
		"а" => "a", "А" => "a",
		"б" => "b", "Б" => "b",
		"в" => "v", "В" => "v",
		"г" => "g", "Г" => "g",
		"д" => "d", "Д" => "d",
		"е" => "e", "Е" => "e",
		"ё" => "yo", "Ё" => "yo",
		"ж" => "zh", "Ж" => "zh",
		"з" => "z", "З" => "z",
		"и" => "i", "И" => "i",
		"й" => "y", "Й" => "y",
		"к" => "k", "К" => "k",
		"л" => "l", "Л" => "l",
		"м" => "m", "М" => "m",
		"н" => "n", "Н" => "n",
		"о" => "o", "О" => "o",
		"п" => "p", "П" => "p",
		"р" => "r", "Р" => "r",
		"с" => "s", "С" => "s",
		"т" => "t", "Т" => "t",
		"у" => "u", "У" => "u",
		"ф" => "f", "Ф" => "f",
		"х" => "h", "Х" => "h",
		"ц" => "c", "Ц" => "c",
		"ч" => "ch", "Ч" => "ch",
		"ш" => "sh", "Ш" => "sh",
		"щ" => "shch", "Щ" => "shch",
		"ъ" => "", "Ъ" => "",
		"ы" => "y", "Ы" => "y",
		"ь" => "", "Ь" => "",
		"э" => "e", "Э" => "e",
		"ю" => "yu", "Ю" => "yu",
		"я" => "ya", "Я" => "ya",
		"і" => "i", "І" => "i",
		"ї" => "yi", "Ї" => "yi",
		"є" => "e", "Є" => "e"
	);
	$str = iconv("UTF-8", "UTF-8//IGNORE", strtr($string, $replace));
	$str = preg_replace("/[^a-z0-9-]/i", " ", $str);
	$str = word_limiter(trim($str), 100);
	$str = preg_replace("/ +/", "-", $str);
	$str = preg_replace("/-{2,}/", "-", $str);
	return strtolower($str);
}

function get_parents($id, $first = 0)
{
	global $article_breadcrumbs;
	global $db;

	$id = intval($id);

	if ($first) {
		$stmt = $db->prepare("SELECT id_menu FROM menu_articles WHERE id_article=? LIMIT 1");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_array();
		$id = intval($row[0] ?? 0);
	}

	$stmt = $db->prepare("SELECT * FROM menu WHERE id=? LIMIT 1");
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$t = $stmt->get_result()->fetch_array();
	if ($t) {
		$t['name_plain'] = title_plain($t['name'] ?? '');
		$article_breadcrumbs[] = $t;
		get_parents($t['parent']);
	}
}

/**
 * Canonical origin (scheme + host, optional non-default port) for absolute URLs in feeds.
 * Priority: SITE_URL or CANONICAL_BASE_URL (trimmed); values may be full URL or host-only.
 * Then current request (HTTPS / X-Forwarded-Proto + HTTP_HOST). CLI or missing host: https://zxpress.ru
 */
function zxpress_canonical_origin(): string {
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	foreach (['SITE_URL', 'CANONICAL_BASE_URL'] as $envKey) {
		$raw = getenv($envKey);
		if ($raw === false || $raw === '') {
			continue;
		}
		$raw = trim($raw);
		$try = $raw;
		if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $try)) {
			$try = 'https://' . ltrim($try, '/');
		}
		$parsed = parse_url($try);
		if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
			$port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
			$cached = $parsed['scheme'] . '://' . $parsed['host'] . $port;
			return $cached;
		}
	}
	if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
		$cached = 'https://zxpress.ru';
		static $cliLogged = false;
		if (!$cliLogged) {
			error_log('[FIX] canonical: fallback origin=' . $cached . ' (cli or missing HTTP_HOST)');
			$cliLogged = true;
		}
		return $cached;
	}
	$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	$scheme = $https ? 'https' : 'http';
	$host = (string) $_SERVER['HTTP_HOST'];
	$host = preg_replace('/[\s\r\n].*$/s', '', $host);
	$host = preg_replace('/[^a-zA-Z0-9.:_-]/', '', $host);
	if ($host === '') {
		$cached = 'https://zxpress.ru';
		static $emptyHostLogged = false;
		if (!$emptyHostLogged) {
			error_log('[FIX] canonical: empty host after sanitize, fallback origin=' . $cached);
			$emptyHostLogged = true;
		}
		return $cached;
	}
	$cached = $scheme . '://' . $host;
	static $reqLogged = false;
	if (!$reqLogged) {
		error_log('[FIX] canonical: using request host=' . $host . ' scheme=' . $scheme);
		$reqLogged = true;
	}
	return $cached;
}

function niceurl($str)
{
	$replace = array(
		"а" => "a", "А" => "a",
		"б" => "b", "Б" => "b",
		"в" => "v", "В" => "v",
		"г" => "g", "Г" => "g",
		"д" => "d", "Д" => "d",
		"е" => "e", "Е" => "e",
		"ё" => "e", "Ё" => "e",
		"ж" => "zh", "Ж" => "zh",
		"з" => "z", "З" => "z",
		"и" => "i", "И" => "i",
		"й" => "y", "Й" => "y",
		"к" => "k", "К" => "k",
		"л" => "l", "Л" => "l",
		"м" => "m", "М" => "m",
		"н" => "n", "Н" => "n",
		"о" => "o", "О" => "o",
		"п" => "p", "П" => "p",
		"р" => "r", "Р" => "r",
		"с" => "s", "С" => "s",
		"т" => "t", "Т" => "t",
		"у" => "u", "У" => "u",
		"ф" => "f", "Ф" => "f",
		"х" => "h", "Х" => "h",
		"ц" => "c", "Ц" => "c",
		"ч" => "ch", "Ч" => "ch",
		"ш" => "sh", "Ш" => "sh",
		"щ" => "sch", "Щ" => "sch",
		"ъ" => "", "Ъ" => "",
		"ы" => "y", "Ы" => "y",
		"ь" => "", "Ь" => "",
		"э" => "e", "Э" => "e",
		"ю" => "yu", "Ю" => "yu",
		"я" => "ya", "Я" => "ya",
		"і" => "i", "І" => "i",
		"ї" => "yi", "Ї" => "yi",
		"є" => "e", "Є" => "e"
	);

	$str = preg_replace("'<b>.*?</b>'si", "", $str);
	$str = html_entity_decode($str, ENT_QUOTES);
	$str = iconv("UTF-8", "UTF-8//IGNORE", strtr(strip_tags($str), $replace));
	$str = preg_replace("/[^a-z0-9-]/i", " ", $str);
	$str = preg_replace("/ +/", "-", trim($str));
	$str = preg_replace('|-+|', '-', $str);
	$str = substr($str, 0, 60);
	return ltrim(strtolower($str), "-");
}

/**
 * Execute prepared SELECT. Use empty $types when SQL has no placeholders.
 *
 * @param string $types mysqli bind types, e.g. 'ii'
 * @return mysqli_result|false
 */
function db_select(mysqli $db, string $sql, string $types = '', ...$params) {
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return false;
	}
	if ($types !== '') {
		if (!$stmt->bind_param($types, ...$params)) {
			return false;
		}
	}
	if (!$stmt->execute()) {
		return false;
	}
	return $stmt->get_result();
}

/**
 * Execute INSERT/UPDATE/DELETE with optional bound parameters.
 */
function db_exec(mysqli $db, string $sql, string $types = '', ...$params): bool {
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return false;
	}
	if ($types !== '') {
		if (!$stmt->bind_param($types, ...$params)) {
			return false;
		}
	}
	return $stmt->execute();
}

/**
 * SELECT with WHERE col IN (?,?,...) for a list of positive integers.
 * Pass $sql containing the literal "__IN__" where the placeholder list should go.
 *
 * @return mysqli_result|false
 */
function db_select_in_ints(mysqli $db, string $sql, array $ids) {
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($v) {
		return $v > 0;
	})));
	if (count($ids) === 0) {
		return false;
	}
	$ph = implode(',', array_fill(0, count($ids), '?'));
	$sql = str_replace('__IN__', $ph, $sql);
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return false;
	}
	$types = str_repeat('i', count($ids));
	$stmt->bind_param($types, ...$ids);
	if (!$stmt->execute()) {
		return false;
	}
	return $stmt->get_result();
}
