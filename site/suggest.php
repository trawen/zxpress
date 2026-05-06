<?php
/**
 * AJAX autocomplete endpoint using ManticoreSearch CALL QSUGGEST.
 * Returns JSON array of suggestions for the given query prefix.
 */

require_once __DIR__ . '/includes/search_client.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$q = trim($_GET['q'] ?? '');
$q = normalize_query($q);

if (mb_strlen($q) < 2) {
    echo '[]';
    exit;
}

$q_esc = manticore_escape($q);
$sql = "CALL QSUGGEST('{$q_esc}', 'test1_articles', 5 AS limit)";

$url = 'http://' . MANTICORE_HOST . ':' . MANTICORE_PORT . '/sql?mode=raw';
$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => 'query=' . urlencode($sql),
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo '[]';
    exit;
}

$data = json_decode($response, true);

$suggestions = [];
if (!empty($data[0]['data'])) {
    foreach ($data[0]['data'] as $row) {
        if (!empty($row['suggest'])) {
            $suggestions[] = $row['suggest'];
        }
    }
}

echo json_encode($suggestions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
