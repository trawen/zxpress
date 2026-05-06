<?php
/**
 * XSS escaping regression tests — Smarty escape_html, nl2p(), safe nl2br chains.
 * Run: php tests/security/test_xss_escaping.php
 */

$passed = 0;
$failed = 0;

function test($name, $condition) {
	global $passed, $failed;
	if ($condition) {
		$passed++;
	} else {
		$failed++;
		echo "FAIL: {$name}\n";
	}
}

$initInc = file_get_contents(__DIR__ . '/../../site/init.inc');
test('init.inc: Smarty escape_html enabled', strpos($initInc, '$smarty->escape_html = true') !== false);

$t = __DIR__ . '/../../site/smarty/zxpress/templates/';
$commentsTpl = file_get_contents($t . 'comments.tpl');
test('comments.tpl: comment body uses escape+nl2br+nofilter chain', strpos($commentsTpl, "|escape:'htmlall'|nl2br nofilter}") !== false);

$guestbookTpl = file_get_contents($t . 'guestbook.tpl');
test('guestbook.tpl: comment text uses safe nl2br chain', strpos($guestbookTpl, "|escape:'htmlall'|nl2br nofilter}") !== false);

$zxnetTpl = file_get_contents($t . 'zxnet.tpl');
test('zxnet.tpl: topic body is trusted legacy H (CONTENT-FIELDS echos_zxnet.text)', strpos($zxnetTpl, '{$topic[n].text nofilter}') !== false);

$articleTpl = file_get_contents($t . 'article.tpl');
test('article.tpl: og:description uses plain title_plain_meta (safe meta text)', strpos($articleTpl, 'property="og:description" content="{$article.title_plain_meta}"') !== false);
test('article.tpl: body is trusted legacy HTML from disk (|nofilter like chapter.tpl)', strpos($articleTpl, '{$article.text nofilter}') !== false);
test('article.tpl: must not auto-escape article body (no bare {$article.text} in pre)', !preg_match('/<pre[^>]*id="text"[^>]*>\\{\\$article\\.text\\}(?!\\s+nofilter)/', $articleTpl));

$printTpl = file_get_contents($t . 'print.tpl');
test('print.tpl: body trusted legacy HTML (nofilter)', strpos($printTpl, '{$article.text nofilter}') !== false);

$bookTpl = file_get_contents($t . 'book.tpl');
test('book.tpl: annotation is HTML from admin (nofilter)', strpos($bookTpl, '{$press.annotation nofilter}') !== false);
test('book.tpl: chapter titles in TOC may contain legacy markup (nofilter)', strpos($bookTpl, '{$other_articles[n].ch_title nofilter}') !== false);

$chapterTpl = file_get_contents($t . 'chapter.tpl');
test('chapter.tpl: ch_title headings use nofilter', strpos($chapterTpl, '{$press.ch_title nofilter}') !== false);
test('chapter.tpl: TOC ch_title links use nofilter', strpos($chapterTpl, '{$other_articles[n].ch_title nofilter}') !== false);

$bookArticlesTpl = file_get_contents($t . 'book_articles.tpl');
test('book_articles.tpl: ch_title uses nofilter', strpos($bookArticlesTpl, '{$press.ch_title nofilter}') !== false);
test('book_articles.tpl: TOC ch_title nofilter', strpos($bookArticlesTpl, '{$other_articles[n].ch_title nofilter}') !== false);

test('zxnet.tpl: echo description may contain legacy HTML', strpos($zxnetTpl, '{$echo.description nofilter}') !== false);

$newsTpl = file_get_contents($t . 'news.tpl');
test('news.tpl: YouTube iframe uses regex_replace sanitizer', strpos($newsTpl, 'youtube.com/embed/{$n.url|regex_replace:') !== false);
test('news.tpl: single-news body nofilter for admin HTML', strpos($newsTpl, '{$news.text nofilter}') !== false);
test('news.tpl: list item body nofilter', strpos($newsTpl, '{$n.text nofilter}') !== false);

$topTpl = file_get_contents($t . 'top.tpl');
test('top.tpl: title uses strip_tags (escape_html applies after)', strpos($topTpl, '<title>{$title|strip_tags}') !== false);

$rightTpl = file_get_contents($t . 'right.tpl');
$rightPhp = file_get_contents(__DIR__ . '/../../site/right.php');
test('right.php: random_articles titles sanitized via sidebar_article_link_titles()', strpos($rightPhp, 'sidebar_article_link_titles') !== false);
test('right.tpl: sidebar link text is trusted list HTML (|nofilter after PHP helper)', strpos($rightTpl, '{$r.title_eng nofilter}') !== false && strpos($rightTpl, '{$r.title nofilter}') !== false);

$mapTpl = file_get_contents($t . 'map.tpl');
test('map.tpl: JSON map uses nofilter for inline script', strpos($mapTpl, '{$map nofilter}') !== false);

$functions = file_get_contents(__DIR__ . '/../../site/includes/functions.php');
test('includes/functions.php: nl2p uses htmlspecialchars', preg_match('/function\s+nl2p\s*\([^)]*\)\s*\{[^}]*htmlspecialchars\s*\(/s', $functions) === 1);

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "XSS escaping regression tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
