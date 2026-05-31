<?php
/**
 * Unit tests for sitemap_builder.php (no database).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/sitemap_builder.php';

$failures = 0;

function sitemap_test_assert(bool $ok, string $msg): void
{
	global $failures;
	if (!$ok) {
		echo "[unit_sitemap] FAIL $msg\n";
		$failures++;
		return;
	}
	echo "[unit_sitemap] PASS $msg\n";
}

sitemap_test_assert(
	sitemap_xml_escape('https://zxpress.ru/article.php?id=1&x="') === 'https://zxpress.ru/article.php?id=1&amp;x=&quot;',
	'xml escape'
);

$today = sitemap_lastmod_today();
sitemap_test_assert(preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) === 1, 'lastmod today format');
sitemap_test_assert($today === gmdate('Y-m-d'), 'lastmod today is current UTC date');

$entry = sitemap_url_entry('https://zxpress.ru', '/article.php?id=42', $today);
sitemap_test_assert($entry['loc'] === 'https://zxpress.ru/article.php?id=42', 'url entry loc');
sitemap_test_assert($entry['lastmod'] === $today, 'url entry lastmod');

ob_start();
sitemap_emit_url('https://zxpress.ru/books.php', '2024-06-01', 'weekly', '0.9');
$xml = ob_get_clean();
sitemap_test_assert(strpos($xml, '<loc>https://zxpress.ru/books.php</loc>') !== false, 'emit url loc');
sitemap_test_assert(strpos($xml, '<lastmod>2024-06-01</lastmod>') !== false, 'emit url lastmod');
sitemap_test_assert(strpos($xml, 'lng=eng') === false, 'no english locale in urls');

exit($failures > 0 ? 1 : 0);
