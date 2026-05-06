<?php
/**
 * SQL injection regression tests — prepared statements in SQLi-hardened paths.
 * Run: php tests/security/test_sql_injection.php
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

$base = __DIR__ . '/../../site/';

// mysqli_query must not interpolate PHP $title (whole variable; not $title1, etc.)
$noRawTitleInMysqli = function ($content) {
	return preg_match('/mysqli_query\s*\([^;]*\$title\b/', $content) === 0;
};

$adminArticles = file_get_contents($base . 'admin_articles.php');
test('admin_articles.php: uses $db->prepare', strpos($adminArticles, '$db->prepare') !== false);
test('admin_articles.php: no raw $title in mysqli_query', $noRawTitleInMysqli($adminArticles));

$adminBooks = file_get_contents($base . 'admin_books.php');
test('admin_books.php: uses $db->prepare', strpos($adminBooks, '$db->prepare') !== false);
test('admin_books.php: no raw $title in mysqli_query', $noRawTitleInMysqli($adminBooks));

$adminBooksLight = file_get_contents($base . 'admin_books_light.php');
test('admin_books_light.php: uses $db->prepare', strpos($adminBooksLight, '$db->prepare') !== false);
test('admin_books_light.php: no raw $title in mysqli_query', $noRawTitleInMysqli($adminBooksLight));

$galleryAdmin = file_get_contents($base . 'gallery_admin.php');
test('gallery_admin.php: uses $db->prepare for paging', strpos($galleryAdmin, '$db->prepare') !== false);
test('gallery_admin.php: no raw $page inside LIMIT clause', preg_match('/LIMIT\s+[^;]*\$page\b/', $galleryAdmin) === 0);

$hidden = file_get_contents($base . 'hidden.php');
test('hidden.php: uses $db->prepare for tag query', strpos($hidden, '$db->prepare') !== false);

$init = file_get_contents($base . 'init.inc');
test('init.inc: get_parents lives in includes (not duplicated here)', strpos($init, 'function get_parents') === false);

$functions = file_get_contents($base . 'includes/functions.php');
test('includes/functions.php: defines get_parents', strpos($functions, 'function get_parents') !== false);
test('includes/functions.php: get_parents uses $db->prepare', preg_match('/function\s+get_parents[\s\S]*\$db->prepare/', $functions) === 1);

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "SQL injection regression tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
