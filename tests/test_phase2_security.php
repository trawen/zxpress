<?php
/**
 * Phase 2 Security Verification Tests
 * Verifies: XSS sanitization, CSRF tokens, server-side CAPTCHA, libs2 deleted, no hardcoded redirects.
 * Run: php tests/test_phase2_security.php
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

// --- Task 5: XSS — $_REQUEST escaped before Smarty assign ---

$issuePhp = file_get_contents(__DIR__ . '/../site/issue.php');
$bookPhp = file_get_contents(__DIR__ . '/../site/book.php');
$ezinesPhp = file_get_contents(__DIR__ . '/../site/ezines.php');
$adminArticlesPhp = file_get_contents(__DIR__ . '/../site/admin_articles.php');
$initInc = file_get_contents(__DIR__ . '/../site/init.inc');

test('issue.php: id uses intval()',
    preg_match('/\$id\s*=\s*intval\(/', $issuePhp) === 1);

test('issue.php: issue_archive_hidden escaped with htmlspecialchars()',
    strpos($issuePhp, "htmlspecialchars(\$_GET['issue_archive_hidden']") !== false
    || strpos($issuePhp, "htmlspecialchars(\$_REQUEST['issue_archive_hidden']") !== false);

test('book.php: id uses intval()',
    preg_match('/\$id\s*=\s*intval\(/', $bookPhp) === 1);

test('ezines.php: user input escaped with htmlspecialchars()',
    strpos($ezinesPhp, 'htmlspecialchars(') !== false);

test('init.inc: no $_SERVER assigned to Smarty',
    strpos($initInc, "\$smarty->assign('\$_SERVER'") === false
    && strpos($initInc, '$smarty->assign("$_SERVER"') === false);

// --- Task 6: CSRF tokens in admin forms ---

test('csrf.php helper exists',
    file_exists(__DIR__ . '/../site/includes/csrf.php'));

$csrfPhp = file_get_contents(__DIR__ . '/../site/includes/csrf.php');
test('csrf.php: defines csrf_token() function',
    strpos($csrfPhp, 'function csrf_token(') !== false);
test('csrf.php: defines csrf_verify() function',
    strpos($csrfPhp, 'function csrf_verify(') !== false);

$adminFiles = [
    'admin_articles.php',
    'admin_books.php',
    'admin_books_light.php',
    'admin_issue.php',
    'admin_news.php',
];

foreach ($adminFiles as $file) {
    $content = file_get_contents(__DIR__ . '/../site/' . $file);
    test("$file: calls csrf_verify()",
        strpos($content, 'csrf_verify(') !== false);
}

$adminTemplates = [
    'admin_articles.tpl',
    'admin_books.tpl',
    'admin_books_light.tpl',
    'admin_issue.tpl',
    'admin_news.tpl',
];

foreach ($adminTemplates as $tpl) {
    $content = file_get_contents(__DIR__ . '/../site/smarty/zxpress/templates/' . $tpl);
    test("$tpl: includes csrf_token hidden field",
        strpos($content, 'csrf_token') !== false);
}

// --- Task 7: CAPTCHA uses server-side session ---

$commentsPhp = file_get_contents(__DIR__ . '/../site/comments.php');
$confirmCodePhp = file_get_contents(__DIR__ . '/../site/confirm_code.php');

test('comments.php: no confirm_id extraction (old obfuscation removed)',
    strpos($commentsPhp, 'confirm_id') === false);
test('comments.php: validates against $_SESSION[captcha_code]',
    strpos($commentsPhp, "SESSION['captcha_code']") !== false
    || strpos($commentsPhp, 'SESSION["captcha_code"]') !== false);
test('confirm_code.php: reads code from $_SESSION not $_REQUEST',
    strpos($confirmCodePhp, "SESSION['captcha_code']") !== false
    || strpos($confirmCodePhp, 'SESSION["captcha_code"]') !== false);
test('confirm_code.php: validates token from $_SESSION',
    strpos($confirmCodePhp, "SESSION['captcha_token']") !== false);
test('confirm_code.php: no $_REQUEST[cc] (old insecure param removed)',
    strpos($confirmCodePhp, "\$_REQUEST['cc']") === false);

$functionsPhp = file_get_contents(__DIR__ . '/../site/includes/functions.php');
test('functions.php: generate_captcha stores captcha_code in session',
    strpos($functionsPhp, "\$_SESSION['captcha_code']") !== false);
test('init.inc: no old $cc variable assigned to Smarty',
    preg_match("/assign\s*\(\s*'cc'\s*,/", $initInc) === 0);

// Templates: no confirm_id hidden field, no {$cc}
$commentsTpl = file_get_contents(__DIR__ . '/../site/smarty/zxpress/templates/comments.tpl');
$guestbookTpl = file_get_contents(__DIR__ . '/../site/smarty/zxpress/templates/guestbook.tpl');

test('comments.tpl: uses captcha_token not $cc for image URL',
    strpos($commentsTpl, 'captcha_token') !== false
    && strpos($commentsTpl, '?cc={$cc}') === false);
test('guestbook.tpl: uses captcha_token not $cc for image URL',
    strpos($guestbookTpl, 'captcha_token') !== false
    && strpos($guestbookTpl, '?cc={$cc}') === false);
test('guestbook.tpl: no confirm_id hidden field',
    strpos($guestbookTpl, 'confirm_id') === false);

// --- Task 8: libs2 deleted ---

test('smarty/libs2/: directory deleted',
    !is_dir(__DIR__ . '/../site/smarty/libs2'));

// --- Task 9: No hardcoded http:// redirects ---

$adminNewsPhp = file_get_contents(__DIR__ . '/../site/admin_news.php');
$chapterPhp = file_get_contents(__DIR__ . '/../site/chapter.php');

test('admin_news.php: no hardcoded http://zxpress.ru redirect',
    strpos($adminNewsPhp, 'http://zxpress.ru') === false);
test('chapter.php: no hardcoded http://zxpress.ru redirect',
    strpos($chapterPhp, 'http://zxpress.ru') === false);

// --- Summary ---

$total = $passed + $failed;
echo "\n" . str_repeat('=', 40) . "\n";
echo "Phase 2 Security Tests: {$passed}/{$total} passed";
if ($failed > 0) {
    echo " ({$failed} FAILED)";
}
echo "\n";
exit($failed > 0 ? 1 : 0);
