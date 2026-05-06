<?php
/**
 * HTTP header injection regression tests.
 * Run: php tests/security/test_header_injection.php
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

$dphp = file_get_contents(__DIR__ . '/../../site/d.php');
test('d.php: preg_replace sanitizes Content-Disposition filename', strpos($dphp, 'preg_replace') !== false && strpos($dphp, 'Content-Disposition') !== false);
test('d.php: safe_name used in Content-Disposition header',
	strpos($dphp, '$safe_name') !== false
	&& strpos($dphp, 'filename="\' . $safe_name . \'.zip"') !== false);

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Header injection regression tests: {$passed}/{$total} passed";
if ($failed > 0) {
	echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
