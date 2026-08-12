<?php
require 'init.inc';
require_once __DIR__ . '/includes/books_public.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function books_ui_template(): string
{
	return 'books_new.tpl';
}

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');

$catalogUrl = books_url_catalog($isEng);
$smarty->assign('books_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', false);
$smarty->assign('smn_nav_books_active', true);
$smarty->assign('url_rus', htmlspecialchars(books_url_catalog(false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(books_url_catalog(true), ENT_QUOTES, 'UTF-8'));

if ($isEng) {
	$smarty->assign('title', 'Library of paper books and magazines for ZX Spectrum');
	$smarty->assign('description', 'Paper books and magazines for ZX Spectrum — covers, publishers and scans from the ZXPRESS library.');
} else {
	$smarty->assign('title', 'Библиотека бумажных книг и журналов для ZX Spectrum');
	$smarty->assign('description', 'Бумажные книги и журналы для ZX Spectrum — обложки, издательства и сканы из библиотеки ZXPRESS.');
}

$z = db_select($db, 'SELECT id, title1, title2, publisher, date, series, image_id, online, file_id FROM books ORDER BY title1 ASC');

$books = [];
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['title1'] = plain_text_decode_entities((string) ($t['title1'] ?? ''));
	$t['title2'] = plain_text_decode_entities((string) ($t['title2'] ?? ''));
	$t['series'] = plain_text_decode_entities((string) ($t['series'] ?? ''));
	$t['publisher'] = plain_text_decode_entities((string) ($t['publisher'] ?? ''));
	$year = (int) date('Y', (int) $t['date']);
	$t['date'] = $year;
	$t['year_display'] = ($year > 1970) ? (string) $year : '';
	$publisher = trim((string) $t['publisher']);
	$t['publisher_display'] = ($publisher !== '' && $publisher !== '«»') ? $publisher : '';
	$t['public_url'] = books_url_book((int) $t['id'], $isEng);
	$imageId = (int) ($t['image_id'] ?? 0);
	$t['cover_src'] = $imageId > 0
		? ('/pictures/thumbs/' . $imageId . '.jpg')
		: '/img/nobook.jpg';
	$t['can_read'] = ((int) ($t['online'] ?? 0)) > 0;
	$t['can_download'] = ((int) ($t['file_id'] ?? 0)) > 0;
	$books[] = $t;
}

$smarty->assign('books', $books);
$smarty->assign('books_total', count($books));

$smarty->display(books_ui_template());
