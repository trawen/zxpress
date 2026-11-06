<?php
declare(strict_types=1);

$INCLUDES_DIR = dirname(__DIR__);
require_once $INCLUDES_DIR . '/article_search_text.php';

function fail(string $msg): void
{
	echo "[unit_article_search_text] ERROR FAIL {$msg}\n";
	exit(1);
}

function assertEq($actual, $expected, string $msg): void
{
	if ($actual !== $expected) {
		$a = is_string($actual) ? $actual : var_export($actual, true);
		$e = is_string($expected) ? $expected : var_export($expected, true);
		fail("{$msg}. expected=" . $e . " actual=" . $a);
	}
}

function assertContains(string $haystack, string $needle, string $msg): void
{
	if (!str_contains($haystack, $needle)) {
		fail("{$msg}. missing={$needle} in=" . substr($haystack, 0, 200));
	}
}

function assertNotContains(string $haystack, string $needle, string $msg): void
{
	if (str_contains($haystack, $needle)) {
		fail("{$msg}. unexpected={$needle}");
	}
}

// Hard hyphenation
assertEq(
	article_search_join_hard_hyphens("re-\nleased at Lovebyte"),
	'released at Lovebyte',
	'join hard hyphen LF'
);
assertEq(
	article_search_join_hard_hyphens("демо-\r\nпати"),
	'демопати',
	'join hard hyphen CRLF cyrillic'
);

// Pseudographics
$withBoxes = "Hello\n╔═══╗\n║ X ║\n╚═══╝\nWorld";
$stripped = article_search_strip_pseudographics($withBoxes);
assertContains($stripped, 'Hello', 'keep Hello');
assertContains($stripped, 'World', 'keep World');
assertNotContains($stripped, '╔', 'strip box drawing');
assertNotContains($stripped, '║', 'strip box drawing vertical');

// Markdown strip
$md = "### Title\n\nSee [Add Mortem](https://demozoo.org/productions/370512) by **Joker**.";
$plain = article_search_strip_markdown($md);
assertContains($plain, 'Title', 'md keep heading text');
assertContains($plain, 'Add Mortem', 'md keep link text');
assertContains($plain, 'Joker', 'md keep bold text');
assertNotContains($plain, '###', 'md strip heading marks');
assertNotContains($plain, 'https://', 'md strip raw url from link syntax via html');
assertNotContains($plain, '**', 'md strip bold marks');

// Full prepare: md path
$prepMd = article_search_prepare_source($md, true);
assertContains($prepMd, 'Add Mortem', 'prepare md');

// Full prepare: txt path with hyphen + ascii banner
$txt = <<<'TXT'
____,------,----,____,----,__
    /          /    /    /
IMPORTANT!

If you're looking for demowatching recommendations,  you came to
the right place, starting off with a bunch of  tiny  intros  re-
leased  at Lovebyte 2024 from multiple developers.
TXT;
$prepTxt = article_search_prepare_source($txt, false);
assertContains($prepTxt, 'IMPORTANT', 'keep IMPORTANT');
assertContains($prepTxt, 'released at Lovebyte', 'joined hyphen word');
assertNotContains($prepTxt, "re-\n", 'no leftover hard hyphen');
assertNotContains($prepTxt, '____,------', 'ascii banner scaffolding removed');

echo "[unit_article_search_text] OK\n";
exit(0);
