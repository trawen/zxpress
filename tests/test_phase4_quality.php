<?php
/**
 * Phase 4 Code Quality Verification Tests
 * Verifies: consolidated functions, fixed syntax, centralized Smarty config, error handling.
 * Run: php tests/test_phase4_quality.php
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

$initInc = file_get_contents(__DIR__ . '/../site/init.inc');

// --- Task 17: Consolidated functions ---

test('includes/functions.php exists',
    file_exists(__DIR__ . '/../site/includes/functions.php'));

$functionsPhp = file_get_contents(__DIR__ . '/../site/includes/functions.php');
test('functions.php: defines title()',
    strpos($functionsPhp, 'function title(') !== false);
test('functions.php: defines nl2p()',
    strpos($functionsPhp, 'function nl2p(') !== false);
test('functions.php: defines rusdate()',
    strpos($functionsPhp, 'function rusdate(') !== false);

test('init.inc: includes functions.php',
    strpos($initInc, "includes/functions.php") !== false);

// Duplicates removed
$filesToCheck = [
    'admin_articles.php',
    'admin_books.php',
    'admin_books_light.php',
];
foreach ($filesToCheck as $file) {
    $content = file_get_contents(__DIR__ . '/../site/' . $file);
    test("$file: no local title() definition",
        preg_match('/^function title\(/m', $content) === 0);
}

$nlFiles = ['news.php', 'admin_news.php', 'rss.php'];
foreach ($nlFiles as $file) {
    $content = file_get_contents(__DIR__ . '/../site/' . $file);
    test("$file: no local nl2p() definition",
        preg_match('/^function nl2p\(/m', $content) === 0);
    test("$file: no local rusdate() definition",
        preg_match('/^function rusdate\(/m', $content) === 0);
}

// --- Task 18: No <> operators, no unquoted array keys ---

$adminArticles = file_get_contents(__DIR__ . '/../site/admin_articles.php');
test('admin_articles.php: no <> operator',
    strpos($adminArticles, ' <> ') === false);
test('admin_articles.php: no unquoted $t[id]',
    preg_match('/\$t\[id\]/', $adminArticles) === 0);

$adminBooks = file_get_contents(__DIR__ . '/../site/admin_books.php');
test('admin_books.php: no <> operator',
    strpos($adminBooks, ' <> ') === false);

$adminBooksLight = file_get_contents(__DIR__ . '/../site/admin_books_light.php');
test('admin_books_light.php: no <> operator',
    strpos($adminBooksLight, ' <> ') === false);

// --- Task 19: Centralized Smarty config ---

test('init.inc: uses __DIR__ for SMARTY_DIR',
    strpos($initInc, "__DIR__ . '/smarty/libs/'") !== false);
test('init.inc: uses __DIR__ for template_dir',
    strpos($initInc, "__DIR__ . '/smarty/zxpress/templates/'") !== false);
test('init.inc: no hardcoded /home/zxpress path',
    strpos($initInc, '/home/zxpress/') === false);
test('init.inc: compile_check set centrally',
    strpos($initInc, 'compile_check = true') !== false);

// Verify compile_check removed from individual files
$sampleFiles = ['article.php', 'gallery.php', 'news.php', 'stats.php', 'issue.php'];
foreach ($sampleFiles as $file) {
    $content = file_get_contents(__DIR__ . '/../site/' . $file);
    test("$file: no local compile_check",
        strpos($content, 'compile_check') === false);
}

// --- Task 20: Centralized error handling ---

test('init.inc: has set_error_handler',
    strpos($initInc, 'set_error_handler') !== false);
test('init.inc: has set_exception_handler',
    strpos($initInc, 'set_exception_handler') !== false);

// --- Summary ---

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Phase 4 Quality Tests: {$passed}/{$total} passed";
if ($failed > 0) {
    echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
