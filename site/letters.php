<?php
// Backward-compatible entrypoint for old /letters.php links.
require 'init.inc';
require_once __DIR__ . '/includes/letters_slugs.php';

$lng = $smarty->getTemplateVars('lng');
$isEng = is_string($lng) && $lng === 'eng';
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
	$canonical = letters_canonical_letter_url($db, $id, $isEng);
	if ($canonical !== null) {
		header('Location: ' . $canonical, true, 301);
		exit;
	}
}

$qs = [];
if (isset($_GET['from']) && (int) $_GET['from'] > 0) {
	$qs['from'] = (int) $_GET['from'];
}
if (isset($_GET['p']) && (int) $_GET['p'] > 1) {
	$qs['p'] = (int) $_GET['p'];
}
$url = letters_url_catalog($isEng);
if ($qs !== []) {
	$url .= '?' . http_build_query($qs);
}
header('Location: ' . $url, true, 301);
exit;
