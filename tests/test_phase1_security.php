<?php
/**
 * Phase 1 Security Verification Tests
 * Verifies: intval() on IDs, realpath() path validation, no mysql_error() in output, dead scripts deleted.
 * Run: php tests/test_phase1_security.php
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

// --- Task 1: SQLi prevention — intval() on $id ---

$printPhp = file_get_contents(__DIR__ . '/../site/print.php');
$bookArticlesPhp = file_get_contents(__DIR__ . '/../site/book_articles.php');

test('print.php: $id uses intval()',
    preg_match('/\$id\s*=\s*intval\(/', $printPhp) === 1);

test('book_articles.php: $id uses intval()',
    preg_match('/\$id\s*=\s*intval\(/', $bookArticlesPhp) === 1);

test('print.php: no raw $_REQUEST[\'id\'] without intval',
    preg_match('/\$id\s*=\s*\$_REQUEST/', $printPhp) === 0);

test('book_articles.php: no raw $_REQUEST[\'id\'] without intval',
    preg_match('/\$id\s*=\s*\$_REQUEST/', $bookArticlesPhp) === 0);

// --- Task 1: Path traversal prevention — realpath() validation ---

test('print.php: uses realpath() for article file path',
    strpos($printPhp, 'realpath(') !== false);

test('print.php: checks allowed directory for path traversal',
    strpos($printPhp, 'strpos($articlePath, $allowedDir') !== false);

test('book_articles.php: uses realpath() for chapter file path',
    strpos($bookArticlesPhp, 'realpath(') !== false);

test('book_articles.php: checks allowed directory for path traversal',
    strpos($bookArticlesPhp, 'strpos($chapterPath, $allowedDir') !== false);

// --- Task 2: No mysql_error() exposed to output ---

$filesToCheck = [
    'print.php',
    'gallery.php',
    'echos_subjs.php',
    'stats.php',
];

foreach ($filesToCheck as $file) {
    $content = file_get_contents(__DIR__ . '/../site/' . $file);
    test("$file: no echo mysql_error()",
        preg_match('/echo\s+mysql_error\s*\(/', $content) === 0);
}

// --- Task 3: Dangerous/dead scripts deleted ---

$deletedScripts = [
    'sem.php',
    'FIX.php',
    'sph.php',
    'rubrics_count.php',
    'prce.php',
    'xxx.php',
    'zzz.php',
    'test_marker.php',
    'wos_dvd.php',
    'article_.php',
    'pclzip.lib.php',
];

foreach ($deletedScripts as $script) {
    test("$script: deleted (should not exist)",
        !file_exists(__DIR__ . '/../site/' . $script));
}

// --- Summary ---

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Phase 1 Security Tests: {$passed}/{$total} passed";
if ($failed > 0) {
    echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
