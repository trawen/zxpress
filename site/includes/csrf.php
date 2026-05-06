<?php
/**
 * CSRF token helpers.
 * Included from init.inc after session_start().
 */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        error_log(
            '[FIX] CSRF token mismatch uri=' . ($_SERVER['REQUEST_URI'] ?? '')
            . ' sid=' . (session_id() ?: '')
        );
        http_response_code(403);
        die('CSRF token mismatch');
    }
}
