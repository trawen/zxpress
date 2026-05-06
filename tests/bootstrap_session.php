<?php

declare(strict_types=1);

/**
 * Session isolation helpers for unit tests that touch $_SESSION (e.g. CSRF).
 *
 * csrf.php only reads/writes $_SESSION; it does not call session_start().
 * Tests reset the superglobal so each test starts clean without PHP session storage.
 */

function csrf_test_reset_session(): void
{
	$_SESSION = [];
}
