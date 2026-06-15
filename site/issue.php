<?php
require 'init.inc';

$smarty->assign('issue_archive_hidden', htmlspecialchars($_GET['issue_archive_hidden'] ?? '', ENT_QUOTES, 'UTF-8'));

$id = intval($_GET['id']);
if (!$id) {$id = 1;}




//$smarty->debugging = true;

$stmt = mysqli_prepare($db, "SELECT * FROM issue, screens WHERE issue.id_press=? AND screens.id_issue=issue.id ORDER BY issue.title DESC, screens.type ASC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {$c[] = $t;}
$smarty->assign('screens', $c);


$stmt = mysqli_prepare($db, "SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id LEFT OUTER JOIN countries ON cities.country_id=countries.id WHERE press.id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$t = mysqli_fetch_array($z);

$t['title_plain'] = title_plain($t['title'] ?? '');

if (($_GET['lng'] ?? '') === 'eng') {
	$cityEng = trim((string) ($t['name_eng'] ?? ''));
	if ($cityEng !== '') {
		$t['name'] = $cityEng;
	}
	$countryEng = trim((string) ($t['country_name_eng'] ?? ''));
	if ($countryEng !== '') {
		$t['country_name'] = $countryEng;
	}
}


if ($t['years_to'] != $t['years_from']) {

	if ($_GET['lng']) {

		$t['years_to'] = date("F Y", $t['years_to'] );

	}
	else {

		$t['years_to'] = date("".$mnt[date("m", $t['years_to'])]." Y", $t['years_to'] );

	}

}
else {

	unset($t['years_to']);

}

if ($t['years_from']) {

	if ($_GET['lng']) {

		$t['years_from'] = date("F Y", $t['years_from'] );

	}
	else {

		$t['years_from'] = date($mnt[date("m", $t['years_from'])]." Y", $t['years_from'] );


	}


}

$num = array("выпуск","выпуска","выпусков");
$smarty->assign("num", getNumEnding($t['numbers'], $num));
$smarty->assign('press', $t);



$type[0] = "Электронная газета для ZX Spectrum";
$type[1] = "Электронный журнал для ZX Spectrum";
$smarty->assign('title', "«".$t['title_plain']."», ".$t['name']." (".$t['country_name'].") — ".$type[$t['type']]);




$stmt = mysqli_prepare($db, "SELECT * FROM issue, files WHERE id_press=? AND files.id_issue=issue.id ORDER BY title ASC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {$is[] = $t;}
$smarty->assign('issues', $is);



// Prefetch all articles for this press with issue data in one query
$stmt = mysqli_prepare($db, "SELECT articles.*, issue.title AS issue_title, issue.date AS issue_date, issue.id AS issue_id FROM articles JOIN issue ON articles.id_issue=issue.id WHERE articles.temp=0 AND issue.id_press=? ORDER BY issue.title DESC, articles.number, articles.title");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);

$a = 0;
$prev_issue = null;
while ($t2 = mysqli_fetch_array($z)) {
	if ($t2['issue_id'] !== $prev_issue) {
		$t2['print_title'] = 1;
		$prev_issue = $t2['issue_id'];
	} else {
		$t2['print_title'] = 0;
	}
	$t2['issue'] = $t2['issue_title'];
	if ($_GET['lng']) {
		$t2['date'] = date("d F Y", $t2['issue_date']);
	} else {
		$t2['date'] = date("d ".$months[date("m", $t2['issue_date'])]." Y", $t2['issue_date']);
	}
	$t2['title_list'] = article_title_list_html($t2['title'] ?? '');
	$t2['title_eng_list'] = article_title_list_html($t2['title_eng'] ?? '');
	$art[$a] = $t2;
	$a++;
}
$smarty->assign('articles', $art);


$n =0;
$last_issue = intval($prev_issue ?? 0);
$stmt = mysqli_prepare($db, "SELECT * FROM covers WHERE id_issue=?");
mysqli_stmt_bind_param($stmt, "i", $last_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {
$cov[$n]=$t;
$n++;
}
$smarty->assign('covers', $cov);



include "right.php";

$smarty->display('issue.tpl');



?>
