<?php
/**
 * Architecture smoke tests (static analysis).
 * Run: php tests/test_architecture.php
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

$base = __DIR__ . '/../site/';
$init = file_get_contents($base . 'init.inc');

test('init.inc requires includes/auth.php', strpos($init, "includes/auth.php") !== false);
test('init.inc requires includes/locale.php', strpos($init, "includes/locale.php") !== false);
test('init.inc requires includes/sape_integration.php', strpos($init, "includes/sape_integration.php") !== false);
test('init.inc enables Smarty escape_html', strpos($init, '$smarty->escape_html = true') !== false);

$functions = file_get_contents($base . 'includes/functions.php');
foreach (['getNumEnding', 'friendly_url', 'get_parents', 'niceurl'] as $fn) {
	test("includes/functions.php defines {$fn}()", preg_match('/function\s+' . preg_quote($fn, '/') . '\s*\(/', $functions) === 1);
}

$right = file_get_contents($base . 'right.php');
test('right.php defines setup_sidebar()', strpos($right, 'function setup_sidebar(') !== false);

$upload = file_get_contents($base . 'includes/upload.php');
test('includes/upload.php defines validate_upload()', strpos($upload, 'function validate_upload(') !== false);

$adminHelpers = file_get_contents($base . 'includes/admin_helpers.php');
test('includes/admin_helpers.php defines admin_log()', strpos($adminHelpers, 'function admin_log(') !== false);

$requestUses = preg_match_all('/\$_REQUEST\b/', $init);
test('init.inc: $_REQUEST only for id intval normalization', $requestUses === 4);

test('includes/FeedWriter/Feed.php exists', is_file($base . 'includes/FeedWriter/Feed.php'));

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Architecture smoke tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
