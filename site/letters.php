<?php
// Backward-compatible entrypoint for old /letters.php links.
require 'init.inc';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

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

$authorParam = '';
if (isset($_GET['author'])) {
	$authorParam = trim((string) $_GET['author']);
} elseif (isset($_GET['from'])) {
	$authorParam = trim((string) $_GET['from']);
}
if ($authorParam !== '') {
	$resolved = authors_resolve_filter($db, $authorParam, $isEng);
	if (!empty($resolved['row'])) {
		$page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
		header('Location: ' . authors_url($resolved['row'], $isEng, $page), true, 301);
		exit;
	}
}

$qs = [];
if (isset($_GET['p']) && (int) $_GET['p'] > 1) {
	$qs['p'] = (int) $_GET['p'];
}
$url = letters_url_catalog($isEng);
if ($qs !== []) {
	$url .= '?' . http_build_query($qs);
}
header('Location: ' . $url, true, 301);
exit;
