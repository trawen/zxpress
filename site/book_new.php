<?php
/**
 * books_v2 public book page.
 * Public URL: /{lang}/books-new/{id}
 */
require 'init.inc';
require_once __DIR__ . '/includes/books_public.php';
require_once __DIR__ . '/includes/authors_slugs.php';

$smarty->assign('issue_archive_hidden', htmlspecialchars($_GET['issue_archive_hidden'] ?? '', ENT_QUOTES, 'UTF-8'));

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	$id = 1;
}

$catalogUrl = books_v2_url_catalog($isEng);
$smarty->assign('books_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', false);
$smarty->assign('smn_nav_books_active', true);

$detail = books_public_book_detail($db, $id, $isEng);
$bookNotFound = ($detail === null);
$press = $detail['press'] ?? false;
$screens = $detail['screens'] ?? [];
$files = $detail['files'] ?? [];
$chapters = $detail['chapters'] ?? [];
$printings = $detail['printings'] ?? [];

if ($bookNotFound) {
	http_response_code(404);
	error_log('[FIX] book_new.php not found id=' . $id);
}

$smarty->assign('press', $press);
$smarty->assign('book_not_found', $bookNotFound);
$smarty->assign('screens', $screens);
$smarty->assign('files', $files);
$smarty->assign('other_articles', $chapters);
$smarty->assign('printings', $printings);

$title = $press ? (string) $press['title1'] : ($isEng ? 'Book not found' : 'Книга не найдена');
if ($press && trim((string) ($press['title2'] ?? '')) !== '') {
	$title .= ' — ' . $press['title2'];
}
$smarty->assign('title', $title);

$descPlain = '';
if ($press) {
	$meta = trim((string) ($press['meta_description'] ?? ''));
	if ($meta !== '') {
		$descPlain = title_plain($meta);
	} else {
		$descPlain = title_plain(strip_tags((string) ($press['annotation'] ?? '')));
	}
	if ($descPlain === '') {
		$descPlain = title_plain($title);
	}
}
$smarty->assign('description', $descPlain);

$publicId = $press ? (int) ($press['id'] ?? $id) : $id;
$smarty->assign('url_rus', htmlspecialchars(books_v2_url_book($publicId, false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(books_v2_url_book($publicId, true), ENT_QUOTES, 'UTF-8'));

$smarty->display('book_new.tpl');
