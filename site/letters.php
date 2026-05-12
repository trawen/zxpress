<?php
// Backward-compatible entrypoint for old links.
// New URL: /snailmail.php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/snailmail.php' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $target, true, 301);
exit;
