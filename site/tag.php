<?php
require 'init.inc';

//error_reporting(E_ALL);



$id = intval($_GET['id']);

// $page = intval($_GET['page']);
// if (!$page) {$page = 1;}

// $num = 250;
// $from = (($page-1) * $num);
// $z = mysqli_query($db,"SELECT COUNT(*) FROM log WHERE type=1");
// $p = mysqli_fetch_array($z);
// $nm_pages = ceil($p[0] / $num);

// for ($n=1; $n < $nm_pages-1; $n++) {$pg[]=$n;}

// $smarty->assign('pages', $pg);
// $smarty->assign('tk_page', $page);


$z = db_select($db, "SELECT tag_name FROM tags WHERE id=? LIMIT 1", "i", $id);
$tag_row = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('tag', $tag_row ? $tag_row[0] : '');

if ($id) {

	$z = db_select($db, "SELECT *, issue.date AS date, articles.title AS title, press.title AS press_name FROM tags_articles, articles, issue, press WHERE id_tag=? AND articles.id=id_article AND issue.id=id_issue AND press.id=issue.id_press ORDER BY articles.date ASC", "i", $id);

}
else {

	$z = db_select($db, "SELECT *,id AS id_article FROM articles WHERE NOT EXISTS (SELECT * FROM tags_articles AS ta WHERE ta.id_article = articles.id) LIMIT 5000,1000");
	error_log(mysqli_error($db));

}

// Prefetch rubrics to avoid N+1 queries
if ($_GET['rubrics']) {
	$rubrics_by_article = [];
	$z_rub = db_select($db, "SELECT id_article, id_menu FROM menu_articles");
	while ($z_rub && ($rub = mysqli_fetch_array($z_rub))) {
		$rubrics_by_article[$rub['id_article']][] = $rub['id_menu'];
	}
}

$articles = [];
while ($z && ($t = mysqli_fetch_array($z))) {

	$t['date'] = $_GET['lng'] ? date("d F Y", $t['date']) : date($mnt[date("m", $t['date'])]." Y", $t['date']);

	if ($last != $t['id_issue']) {

		$t['show'] = 1;
		$last = $t['id_issue'];

	}
	else {

		$t['show'] = 0;

	}
	
	if ($_GET['rubrics'] && isset($rubrics_by_article)) {
		$t['rubrics'] = $rubrics_by_article[$t['id_article']] ?? [];
	}

	$t['title_list'] = article_title_list_html($t['title'] ?? '');
	$t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
	$t['press_name_plain'] = title_plain($t['press_name'] ?? '');

	$articles[] = $t;
	
}
$smarty->assign('articles', $articles);
$smarty->assign('count', count($articles));


if ($_GET['lng']) {
	$smarty->assign('title', "ZXPress updates list");
}
else {
	$smarty->assign('title', "Список последних обновлений");
}

$r = [];
$z = db_select($db, "SELECT * FROM menu");
while ($z && ($t = mysqli_fetch_array($z))) {

	$t['name_plain'] = title_plain($t['name'] ?? '');
	$r[] = $t;

}
$smarty->assign('rubrics', $r);


include "right.php";

$smarty->display('tag.tpl');
?>