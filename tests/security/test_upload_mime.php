<?php
/**
 * Upload MIME validation regression tests (finfo).
 * Run: php tests/security/test_upload_mime.php
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

$hasFinfo = function ($content) {
	return strpos($content, 'finfo_file') !== false || strpos($content, 'finfo_open') !== false;
};

$adminBooks = file_get_contents(__DIR__ . '/../../site/admin_books.php');
test('admin_books.php: finfo for upload validation', $hasFinfo($adminBooks));

$adminBooksLight = file_get_contents(__DIR__ . '/../../site/admin_books_light.php');
test('admin_books_light.php: finfo for upload validation', $hasFinfo($adminBooksLight));

$adminArticles = file_get_contents(__DIR__ . '/../../site/admin_articles.php');
test('admin_articles.php: finfo for upload validation', $hasFinfo($adminArticles));

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Upload MIME regression tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
