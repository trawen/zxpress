<?php
/**
 * books_v2 public catalog.
 * Public URL: /{lang}/books-new
 */
require 'init.inc';
require_once __DIR__ . '/includes/books_public.php';
require_once __DIR__ . '/includes/authors_slugs.php';

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');

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
$smarty->assign('url_rus', htmlspecialchars(books_v2_url_catalog(false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(books_v2_url_catalog(true), ENT_QUOTES, 'UTF-8'));

if ($isEng) {
	$smarty->assign('title', 'Library of paper books and magazines for ZX Spectrum');
	$smarty->assign('description', 'Paper books and magazines for ZX Spectrum — covers, publishers and scans from the ZXPRESS library.');
} else {
	$smarty->assign('title', 'Библиотека бумажных книг и журналов для ZX Spectrum');
	$smarty->assign('description', 'Бумажные книги и журналы для ZX Spectrum — обложки, издательства и сканы из библиотеки ZXPRESS.');
}

$books = books_public_catalog($db, $isEng);
$smarty->assign('books', $books);
$smarty->assign('books_total', count($books));

$smarty->display('books_new.tpl');
