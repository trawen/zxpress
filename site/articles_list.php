<?php
require 'init.inc';

$tag = intval($_GET['tag']);
if (!$tag) {$tag = 1;}


$temp = intval($_GET['temp']);
$smarty->assign('temp', $temp);




$z = db_select($db, "SELECT * FROM articles, issue, tags_articles, press WHERE articles.temp=? AND tags_articles.id_tag=? AND articles.id=tags_articles.id_article AND issue.id=articles.id_issue AND press.id=issue.id_press ORDER BY issue.date", "ii", $temp, $tag);
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {

	if ($t['title'] != $tit OR ($t['title'] = $tit AND $t[9] != $num) ) {$t['print'] = 1;}	else {$t['print'] = 0;}
	$tit = $t['title'];
	$num = $t[9];

	if ($_GET['lng']) {

		$t['date'] = date("d F Y", $t['date'] );
	}
	else {

		$t['date'] = date("d ".$months[date("m", $t['date'])]." Y", $t['date'] );
	}

	$t['title_list'] = article_title_list_html($t['title'] ?? '');
	$t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');

	$articles[$n] = $t;
	$n++;

}
$smarty->assign('articles', $articles);




$z = db_select($db, "SELECT * FROM tags WHERE id=?", "i", $tag);
$t = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('tag_name', $t['tag_alias'] ?? '');




if ($_GET['lng']) {
	$smarty->assign('title', "Articles on the theme «".($t['tag_alias'] ?? '')."»");
}
else {
	$smarty->assign('title', "Статьи на тему «".($t['tag_name'] ?? '')."»");
}


include "right.php";

$smarty->display('articles_list.tpl');

?>
