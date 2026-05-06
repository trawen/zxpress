<?php
/**
 * CSRF protection regression tests.
 * Run: php tests/security/test_csrf.php
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

$t = __DIR__ . '/../../site/smarty/zxpress/templates/';
$commentsTpl = file_get_contents($t . 'comments.tpl');
test('comments.tpl: csrf_token hidden input', strpos($commentsTpl, 'name="csrf_token"') !== false && strpos($commentsTpl, '{$csrf_token}') !== false);

$guestbookTpl = file_get_contents($t . 'guestbook.tpl');
test('guestbook.tpl: csrf_token hidden input', strpos($guestbookTpl, 'name="csrf_token"') !== false && strpos($guestbookTpl, '{$csrf_token}') !== false);

$commentsPhp = file_get_contents(__DIR__ . '/../../site/comments.php');
test('comments.php: csrf_verify() called', strpos($commentsPhp, 'csrf_verify()') !== false);

$adminNewsUpload = file_get_contents(__DIR__ . '/../../site/admin_news_upload.php');
test('admin_news_upload.php: csrf_verify() present', substr_count($adminNewsUpload, 'csrf_verify()') >= 1);
test('admin_news_upload.php: csrf_token in form', strpos($adminNewsUpload, 'csrf_token') !== false);

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "CSRF regression tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
