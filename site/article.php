<?php

error_reporting(E_ALL);


require 'init.inc';

$id = intval($_GET['id']);
$smarty->assign('id_article', $id);
$smarty->assign('id', $id);
get_parents($id,1);
$smarty->assign('breadcrumbs',  array_reverse($article_breadcrumbs) );

$stmt = mysqli_prepare($db, "SELECT * FROM articles, issue WHERE articles.id=? AND issue.id=articles.id_issue");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$issue = mysqli_fetch_array($z);
{

	$issue['date'] = date("d ".$months[date("m", $issue['date'])]." Y", $issue['date'] );

}
$smarty->assign('issue', $issue);
$id_issue = intval($issue['id']);
$id_press = intval($issue['id_press']);

$stmt = mysqli_prepare($db, "SELECT * FROM screens WHERE id_press=? AND id_issue=? ORDER BY type ASC LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $id_press, $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);

$screens = mysqli_fetch_array($z);

if ($screens == NULL) {
	$stmt = mysqli_prepare($db, "SELECT * FROM screens WHERE id_press=? ORDER BY type ASC LIMIT 1");
	mysqli_stmt_bind_param($stmt, "i", $id_press);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
	$screens = mysqli_fetch_array($z);
}

$smarty->assign('screens', $screens);


$stmt = mysqli_prepare($db, "SELECT * FROM issue, press, cities WHERE issue.id=? AND press.id=issue.id_press AND press.city=cities.id");
mysqli_stmt_bind_param($stmt, "i", $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$press = mysqli_fetch_array($z);
if (is_array($press)) {
	$press['title_plain'] = title_plain($press['title'] ?? '');
}
$smarty->assign('press', $press);



$stmt = mysqli_prepare($db, "SELECT * FROM articles WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_array($z);


if ($article) {

	$article['name_plain'] = title_plain($article['name'] ?? '');
	$aid = (int)$article['id'];
	if ($_GET['lng'] == 'eng' && $id <= 10948) {
		$baseDir = realpath(zx_storage_dir('articles_eng'));
	} else {
		$baseDir = realpath(zx_storage_dir('articles'));
	}
	$article['text'] = '';
	if ($baseDir !== false) {
		$candidate = $baseDir . '/' . $aid;
		$resolved = realpath($candidate);
		if ($resolved !== false && is_file($resolved)
			&& strpos($resolved, $baseDir . DIRECTORY_SEPARATOR) === 0) {
			$article['text'] = (string)file_get_contents($resolved);
		}
	}

	$article['title_plain_meta'] = title_plain($article['title'] ?? '');
	$article['title_eng_plain_meta'] = title_plain($article['title_eng'] ?? '');
	$article['title_html'] = article_title_list_html($article['title'] ?? '');
	$article['title_eng_html'] = article_title_list_html($article['title_eng'] ?? '');
	if (strpos($article['title_html'], '&amp;#') !== false || strpos($article['title_eng_html'], '&amp;#') !== false) {
		static $zxpress_title_entity_diag = 0;
		if ($zxpress_title_entity_diag < 6) {
			error_log('[FIX] article.php suspicious escaped entity in rendered title id=' . $aid);
			$zxpress_title_entity_diag++;
		}
	}

	if ($article['text'] !== '' && strpos($article['text'], '<') !== false) {
		static $zxpress_article_markup_diag = 0;
		if ($zxpress_article_markup_diag < 6 && getenv('LOG_LEVEL') === 'DEBUG') {
			error_log('[FIX] article.php HTML body loaded id=' . $aid . ' len=' . strlen($article['text']) . ' (article.tpl uses nofilter)');
			$zxpress_article_markup_diag++;
		}
	}

}


$smarty->assign('article', $article);

$smarty->assign('title', $article ? title_plain($article['title'] ?? '') : '' );


 //TAGS
$stmt = mysqli_prepare($db, "SELECT *, tags.id AS id FROM tags, tags_articles WHERE tags_articles.id_article=? AND tags.id=tags_articles.id_tag");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {

	if ($tags) {$atg .=",";}

	$tags[] = $t;
	$atg .= $t['id'];

}
$smarty->assign('tags', $tags);


// other articles from issue
$stmt = mysqli_prepare($db, "SELECT * FROM articles WHERE id_issue=? ORDER BY number, title");
mysqli_stmt_bind_param($stmt, "i", $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {

	if ($id == $t['id']) {$t['current'] = 1;}
	$t['title_html'] = article_title_list_html($t['title'] ?? '');
	$t['title_eng_html'] = article_title_list_html($t['title_eng'] ?? '');
	$art[] = $t;

}
$smarty->assign('other_articles', $art);


if (!$skip) {
	$stmt = mysqli_prepare($db, "UPDATE articles SET views=views+1 WHERE id=?");
	mysqli_stmt_bind_param($stmt, "i", $id);
	mysqli_stmt_execute($stmt);
}

include "right.php";

$smarty->display('article.tpl');
?>
