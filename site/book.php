<?php
require 'init.inc';
require_once __DIR__ . '/includes/books_public.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function book_ui_template(): string
{
	return 'book_new.tpl';
}

$smarty->assign('issue_archive_hidden', htmlspecialchars($_GET['issue_archive_hidden'] ?? '', ENT_QUOTES, 'UTF-8'));

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	$id = 1;
}

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

$screens = [];
$z = db_select($db, 'SELECT * FROM pictures WHERE book_id=? ORDER BY pictures.type ASC', 'i', $id);
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['img_src'] = '/pictures/' . (int) $t['id'] . '.jpg';
	$screens[] = $t;
}
$smarty->assign('screens', $screens);

$z = db_select(
	$db,
	'SELECT books.*, cities.name AS city, cities.name_eng AS city_eng '
	. 'FROM books LEFT JOIN cities ON books.city_id=cities.id WHERE books.id=? LIMIT 1',
	'i',
	$id
);

$press = $z ? mysqli_fetch_array($z) : false;
$bookNotFound = false;

if ($press) {
	foreach (['title1', 'title2', 'series', 'publisher', 'authors', 'annotation', 'isbn'] as $field) {
		$press[$field] = plain_text_decode_entities((string) ($press[$field] ?? ''));
	}
	$year = (int) date('Y', (int) $press['date']);
	$press['date'] = $year;
	$press['year_display'] = ($year > 1970) ? (string) $year : '';
	$publisher = trim((string) $press['publisher']);
	$press['publisher_display'] = ($publisher !== '' && $publisher !== '«»') ? $publisher : '';
	$cityName = $isEng
		? trim((string) ($press['city_eng'] ?? ''))
		: trim((string) ($press['city'] ?? ''));
	if ($cityName === '' && !$isEng) {
		$cityName = trim((string) ($press['city'] ?? ''));
	}
	$press['city_display'] = $cityName;
	$annotation = trim((string) ($press['annotation'] ?? ''));
	if ($annotation !== '' && strtolower(substr($annotation, 0, 3)) !== '<p>') {
		$annotation = '<p>' . $annotation . '</p>';
	}
	$press['annotation'] = $annotation;
	if ((int) ($press['city_id'] ?? 0) > 0 && $cityName === '') {
		error_log('[FIX] book.php missing city row id=' . $id . ' city_id=' . (int) $press['city_id']);
	}
} else {
	$bookNotFound = true;
	http_response_code(404);
	error_log('[FIX] book.php not found id=' . $id);
}

$smarty->assign('press', $press);
$smarty->assign('book_not_found', $bookNotFound);

$title = $press ? (string) $press['title1'] : ($isEng ? 'Book not found' : 'Книга не найдена');
if ($press && trim((string) $press['title2']) !== '') {
	$title .= ' — ' . $press['title2'];
}
$smarty->assign('title', $title);

$descPlain = '';
if ($press) {
	$descPlain = title_plain(strip_tags((string) ($press['annotation'] ?? '')));
	if ($descPlain === '') {
		$descPlain = title_plain($title);
	}
}
$smarty->assign('description', $descPlain);

$files = [];
$z = db_select($db, 'SELECT * FROM books_files WHERE book_id=?', 'i', $id);
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['file_type_label'] = books_file_type_label((int) ($t['file_type'] ?? 0), $isEng);
	$t['public_url'] = '/books_files/' . rawurlencode((string) ($t['file_name'] ?? ''));
	$files[] = $t;
}
$smarty->assign('files', $files);

$chapters = [];
$z = db_select($db, 'SELECT * FROM chapters WHERE ch_id_book=? ORDER BY ch_date ASC', 'i', $id);
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['ch_title'] = plain_text_decode_entities((string) ($t['ch_title'] ?? ''));
	$t['public_url'] = books_url_chapter((int) $t['ch_id'], $isEng);
	$chapters[] = $t;
}
$smarty->assign('other_articles', $chapters);

$smarty->assign('url_rus', htmlspecialchars(books_url_book($id, false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(books_url_book($id, true), ENT_QUOTES, 'UTF-8'));

$smarty->display(book_ui_template());
