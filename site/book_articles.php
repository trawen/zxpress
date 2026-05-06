<?php
require 'init.inc';
//require "comments.php";

//error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);
if (!$id) {$id = 1;}

$skip = intval($_GET['skip']);
$show = intval($_GET['show']) * 1;

$months = array(
		"01"=>"января",
		"02"=>"февраля",
		"03"=>"марта",
		"04"=>"апреля",
		"05"=>"мая",
		"06"=>"июня",
		"07"=>"июля",
		"08"=>"августа",
		"09"=>"сентября",
		"10"=>"октября",
		"11"=>"ноября",
		"12"=>"декабря");

//$smarty->debugging = true;

$stmt = mysqli_prepare($db, "SELECT * FROM articles, issue WHERE temp=? AND articles.id=? AND issue.id=articles.id_issue");
mysqli_stmt_bind_param($stmt, "ii", $show, $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$issue = mysqli_fetch_array($z);
$issue['date'] = date("d ".$months[date("m", $issue['date'])]." Y", $issue['date'] );
$smarty->assign('issue', $issue);


$stmt = mysqli_prepare($db, "SELECT * FROM books, chapters WHERE ch_id=? AND books.id=ch_id_book");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$press = mysqli_fetch_array($z);
$press['date'] = date(" Y", $press['date'] );
$ch_id_book = intval($press['ch_id_book']);
$smarty->assign('press', $press);


$stmt = mysqli_prepare($db, "SELECT * FROM chapters WHERE ch_id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_array($z);
if ($article) {
$chapterPath = realpath(zx_storage_path('chapters', (string) $article['ch_id']));
$allowedDir = realpath(zx_storage_dir('chapters'));
if ($chapterPath && $allowedDir && strpos($chapterPath, $allowedDir . DIRECTORY_SEPARATOR) === 0) {
	$article['text'] = file_get_contents($chapterPath);
} else {
	$article['text'] = '';
	error_log("book_articles.php: path traversal attempt for ch_id=" . $article['ch_id']);
}
$smarty->assign('article', $article);
}

$tit = strip_tags($article['ch_title']);
$tit = str_replace ( "\r", " " , $tit);
$tit = str_replace ( "\n", " " , $tit);
$tit = str_replace ( "\t", " " , $tit);
$tit = str_replace ( "  ", " " , $tit);

$smarty->assign('title', $press['title1']." - ".$tit );


//TAGS
$stmt = mysqli_prepare($db, "SELECT * FROM tags, tags_articles WHERE tag_type=1 AND tags_articles.id_article=? AND tags.id=tags_articles.id_tag");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$n = 0;
while ($t = mysqli_fetch_array($z)) {
	$tg[$n] = $t;
	$keywords = $keywords . $t['tag_name'] . " ";
	$n++;
}
$smarty->assign('article_tags', $tg);


//OTHER ARTICLES
$stmt = mysqli_prepare($db, "SELECT * FROM chapters WHERE ch_id_book=? ORDER BY ch_date ASC");
mysqli_stmt_bind_param($stmt, "i", $ch_id_book);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$n = 0;
while ($t = mysqli_fetch_array($z)) {
$art[$n] = $t;
$n++;
}
$smarty->assign('other_articles', $art);


$smarty->assign('id_article', $id);
$smarty->assign('keywords', $keywords);

// Comments form on this page must be processed server-side (validation/CSRF/captcha).
if (!empty($_POST['submit'])) {
	error_log('[FIX] book_articles.php comment submit detected for id=' . $id);
}
require "comments.php";


if (!$skip) {
$stmt = mysqli_prepare($db, "UPDATE chapters SET ch_views=ch_views+1 WHERE ch_id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
}

include "right.php";

$smarty->display('book_articles.tpl');
?>
