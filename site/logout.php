<?php
/**
 * Admin logout — POST + CSRF only.
 */
require 'init.inc';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	header('Location: /hyperjump.php', true, 303);
	exit;
}

csrf_verify();

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
	session_destroy();
}

header('Location: /hyperjump.php?logged_out=1', true, 303);
exit;
