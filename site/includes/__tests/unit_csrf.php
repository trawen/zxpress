<?php
declare(strict_types=1);

$LOG_LEVEL = strtoupper(getenv('LOG_LEVEL') ?: 'STANDARD');
$INCLUDES_DIR = dirname(__DIR__); // .../site/includes
$CSRF_PATH = $INCLUDES_DIR . '/csrf.php';

require_once $CSRF_PATH;

function log_unit(string $level, string $msg): void
{
    global $LOG_LEVEL;

    if ($level === 'DEBUG' && $LOG_LEVEL !== 'DEBUG') {
        return;
    }

    echo "[unit_csrf] {$level} {$msg}\n";
}

function fail(string $msg): void
{
    log_unit('ERROR', 'FAIL ' . $msg);
    exit(1);
}

$modeMismatch = in_array('--mismatch', $argv, true);

ini_set('session.save_path', '/tmp');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($modeMismatch) {
    // cssrf_verify() calls die() on mismatch, so we capture the die output
    // and transform it into a single PASS/FAIL summary line.
    ob_start();

    register_shutdown_function(function () {
        $buf = '';
        if (ob_get_level() > 0) {
            $buf = (string) ob_get_contents();
            @ob_end_clean();
        }

        $ok = strpos($buf, 'CSRF token mismatch') !== false;
        if ($ok) {
            log_unit('INFO', 'PASS csrf_verify mismatch expected die');
        } else {
            log_unit('ERROR', 'FAIL csrf_verify mismatch did not die with expected message');
        }
    });

    $_POST['csrf_token'] = 'wrong-token';
    csrf_verify(); // expected to terminate process

    // Should never reach here
    log_unit('ERROR', 'FAIL reached after csrf_verify');
    exit(1);
}

// Normal verification
$token1 = csrf_token();
if (empty($token1) || !is_string($token1)) {
    fail('csrf_token returned empty/non-string');
}

$token2 = csrf_token();
if ($token1 !== $token2) {
    fail('csrf_token not stable within same session');
}

$field = csrf_field();
if (strpos($field, $token1) === false) {
    fail('csrf_field did not include the current token');
}

// Success path: should not die
$_POST['csrf_token'] = $token1;
csrf_verify();

log_unit('INFO', 'PASS csrf token + verify success');
exit(0);
