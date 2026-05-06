<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
	http_response_code(403);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Forbidden';
	exit;
}

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

$articles = [];
$tit = null;
$num = null;

$z = db_select($db, "SELECT * FROM articles, issue, press WHERE articles.temp=1 AND issue.id=articles.id_issue AND press.id=issue.id_press ORDER BY issue.date");
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {

	if ($tit === null || $t['title'] != $tit || ($t['title'] == $tit && $t[9] != $num)) {
		$t['print'] = 1;
	} else {
		$t['print'] = 0;
	}
	$tit = $t['title'];
	$num = $t[9];

	$t['date'] = date("d ".$months[date("m", $t['date'])]." Y", $t['date'] );
	$t['title_list'] = article_title_list_html($t['title'] ?? '');
	$t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
	$articles[$n] = $t;
	$n++;

}
$smarty->assign('articles', $articles);



$tag = intval($_GET['tag'] ?? 0);
$stmt_tag = $db->prepare("SELECT * FROM tags WHERE id=? LIMIT 1");
$stmt_tag->bind_param("i", $tag);
$stmt_tag->execute();
$tagRes = $stmt_tag->get_result();
$t = $tagRes ? $tagRes->fetch_assoc() : false;
$smarty->assign('tag_name', is_array($t) ? ($t['tag_name'] ?? '') : '');

$smarty->assign('title', "Отключенные статьи");


include "right.php";

$smarty->display('hidden.tpl');

?>
