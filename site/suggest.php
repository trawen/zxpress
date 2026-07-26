<?php
/**
 * AJAX autocomplete: press/book → letters → zxnet → article titles.
 * Returns JSON array of objects: {type, label, meta?, url?}.
 */

require __DIR__ . '/init.inc';
require_once __DIR__ . '/includes/search_suggest.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$q = (string) ($_GET['q'] ?? '');
$lng = strtolower((string) ($_GET['lng'] ?? ''));
$isEng = ($lng === 'eng' || $lng === 'en');
$uiNew = (($_GET['ui'] ?? '') === 'new');

$suggestions = search_suggest_all($db, $q, $isEng, $uiNew);

echo json_encode($suggestions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
