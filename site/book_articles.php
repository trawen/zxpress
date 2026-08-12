<?php
require 'init.inc';
require_once __DIR__ . '/includes/books_public.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function book_articles_ui_template(): string
{
	return 'book_articles_new.tpl';
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	$id = 1;
}

$skip = (int) ($_GET['skip'] ?? 0);

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

$stmt = mysqli_prepare($db, 'SELECT * FROM books, chapters WHERE ch_id=? AND books.id=ch_id_book LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$press = mysqli_fetch_array($z);

$articleNotFound = false;
$chIdBook = 0;

if ($press) {
	foreach (['title1', 'title2', 'series', 'publisher', 'authors', 'ch_title'] as $field) {
		if (isset($press[$field])) {
			$press[$field] = plain_text_decode_entities((string) $press[$field]);
		}
	}
	$year = (int) date('Y', (int) ($press['date'] ?? 0));
	$press['date'] = $year;
	$press['year_display'] = ($year > 1970) ? (string) $year : '';
	$press['date_label'] = $press['year_display'] !== ''
		? ($isEng ? $press['year_display'] : ($press['year_display'] . ' г.'))
		: '';
	$imageId = (int) ($press['image_id'] ?? 0);
	$press['cover_src'] = $imageId > 0
		? ('/pictures/thumbs/' . $imageId . '.jpg')
		: '';
	$chIdBook = (int) ($press['ch_id_book'] ?? 0);
	$press['public_url'] = books_url_book((int) ($press['id'] ?? 0), $isEng);
} else {
	$articleNotFound = true;
	http_response_code(404);
	error_log('[FIX] book_articles.php book/chapter not found id=' . $id);
}

$smarty->assign('press', $press);

$stmt = mysqli_prepare($db, 'SELECT * FROM chapters WHERE ch_id=? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_array($z);

if ($article) {
	$article['ch_title'] = plain_text_decode_entities((string) ($article['ch_title'] ?? ''));
	$article['ch_title_plain'] = title_plain(strip_tags((string) $article['ch_title']));
	$chapterPath = realpath(zx_storage_path('chapters', (string) $article['ch_id']));
	$allowedDir = realpath(zx_storage_dir('chapters'));
	if ($chapterPath && $allowedDir && strpos($chapterPath, $allowedDir . DIRECTORY_SEPARATOR) === 0) {
		$article['text'] = books_chapter_root_urls((string) file_get_contents($chapterPath));
	} else {
		$article['text'] = '';
		error_log('book_articles.php: path traversal attempt for ch_id=' . $article['ch_id']);
	}
} else {
	$articleNotFound = true;
	http_response_code(404);
	error_log('[FIX] book_articles.php chapter not found id=' . $id);
	$article = [
		'ch_id' => $id,
		'ch_title' => '',
		'ch_title_plain' => $isEng ? 'Chapter not found' : 'Глава не найдена',
		'text' => '',
	];
}

$smarty->assign('article', $article);
$smarty->assign('article_not_found', $articleNotFound);

$bookTitle = $press ? (string) ($press['title1'] ?? '') : '';
$chapterTitlePlain = (string) ($article['ch_title_plain'] ?? '');
$pageTitle = $bookTitle !== '' && $chapterTitlePlain !== ''
	? ($bookTitle . ' — ' . $chapterTitlePlain)
	: ($chapterTitlePlain !== '' ? $chapterTitlePlain : ($isEng ? 'Chapter' : 'Глава'));
$smarty->assign('title', $pageTitle);
$smarty->assign('description', $chapterTitlePlain !== '' ? $chapterTitlePlain : $pageTitle);

$tags = [];
$keywords = '';
$stmt = mysqli_prepare(
	$db,
	'SELECT * FROM tags, tags_articles WHERE tag_type=1 AND tags_articles.id_article=? AND tags.id=tags_articles.id_tag'
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($z && ($t = mysqli_fetch_array($z))) {
	$tags[] = $t;
	$keywords .= $t['tag_name'] . ' ';
}
$smarty->assign('article_tags', $tags);
$smarty->assign('keywords', $keywords);

$otherArticles = [];
if ($chIdBook > 0) {
	$stmt = mysqli_prepare($db, 'SELECT * FROM chapters WHERE ch_id_book=? ORDER BY ch_date ASC');
	mysqli_stmt_bind_param($stmt, 'i', $chIdBook);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
	while ($z && ($t = mysqli_fetch_array($z))) {
		$t['ch_title'] = plain_text_decode_entities((string) ($t['ch_title'] ?? ''));
		$t['public_url'] = books_url_chapter((int) $t['ch_id'], $isEng);
		$t['current'] = ((int) $t['ch_id'] === $id);
		$otherArticles[] = $t;
	}
}
$smarty->assign('other_articles', $otherArticles);
$smarty->assign('id_article', $id);

$smarty->assign('url_rus', htmlspecialchars(books_url_chapter($id, false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(books_url_chapter($id, true), ENT_QUOTES, 'UTF-8'));

if (!empty($_POST['submit'])) {
	error_log('[FIX] book_articles.php comment submit detected for id=' . $id);
}

if (!$articleNotFound) {
	require_once __DIR__ . '/includes/comments_scope.php';
	$comments_target_id = comments_id_book_chapter($id);
	$smarty->assign('comments_enabled', true);
	$smarty->assign('comments_form_action', books_url_chapter($id, $isEng));
	$smarty->assign('comments_invite', $isEng
		? 'Share your thoughts about this chapter'
		: 'Поделитесь вашим мнением о главе');
	require __DIR__ . '/comments.php';
} else {
	$smarty->assign('comments_enabled', false);
}

if (!$skip && !$articleNotFound) {
	$stmt = mysqli_prepare($db, 'UPDATE chapters SET ch_views=ch_views+1 WHERE ch_id=?');
	mysqli_stmt_bind_param($stmt, 'i', $id);
	mysqli_stmt_execute($stmt);
}

$smarty->display(book_articles_ui_template());
