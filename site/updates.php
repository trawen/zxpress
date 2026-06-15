<?php
require 'init.inc';

//$smarty->debugging = true;

$page = intval($_GET['page']);
if (!$page) {$page = 1;}

$num = 250;
$from = (($page-1) * $num);
$isEng = (($_GET['lng'] ?? '') === 'eng');
$z = db_select($db, "SELECT COUNT(*) FROM log WHERE type=1");
$p = $z ? mysqli_fetch_array($z) : false;
$nm_pages = ceil($p[0] / $num);

for ($n=1; $n < $nm_pages-1; $n++) {$pg[]=$n;}

$smarty->assign('pages', $pg);
$smarty->assign('tk_page', $page);


$z = db_select($db, "SELECT *, issue.title AS issue_title, issue.id AS issue_id, press.title AS press_title, press.id AS press_id, articles.title AS title FROM log, press, issue, articles WHERE log.type=1 AND articles.temp=0 AND press.id=log.id_press AND issue.id=log.id_issue AND articles.id=log.id_article ORDER BY log.date DESC LIMIT ?, ?", "ii", $from, $num);

while ($z && ($t = mysqli_fetch_array($z))) {

	if ($p_press == $t['press_title'] and $p_issue == $t['issue_title']) {

		$t['print'] = 0;

	}
	else {

		$t['print'] = 1;
		$p_press = $t['press_title'];
		$p_issue = $t['issue_title'];

	}


	$t['date'] = $isEng
		? date("j F", $t['date'])
		: date("d ", $t['date']) . $months[date("m", $t['date'])];

	if ($last != $t['date']) {$last = $t['date'];} else {$t['date'] = "";}

	$articleTitle = ($isEng && trim((string) ($t['title_eng'] ?? '')) !== '')
		? ($t['title_eng'] ?? '')
		: ($t['title'] ?? '');
	$t['title_list'] = article_title_list_html($articleTitle);

	$update[] = $t;

}
$smarty->assign('updates', $update);



if ($_GET['lng']) {
	$smarty->assign('title', "ZXPress updates list");
}
else {
	$smarty->assign('title', "Список последних обновлений");
}




include "right.php";

$smarty->display('updates.tpl');
?>
