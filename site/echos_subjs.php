<?php
require 'init.inc';

$id = intval($_GET['id']);

//$smarty->debugging = true;

$stmt_echo = $db->prepare("SELECT title FROM echos_titles WHERE id=? LIMIT 1");
$stmt_echo->bind_param("i", $id);
$stmt_echo->execute();
$t = $stmt_echo->get_result()->fetch_array();
$smarty->assign('echo', $t[0]);

// Aggregate counts + date ranges in one query, then batch update
$stmt = mysqli_prepare($db, "SELECT subj_id, COUNT(*) AS nm, MIN(date) AS date_from, MAX(date) AS date_to FROM echos WHERE subj_id IN (SELECT id FROM echos_subjs WHERE echo_id=?) GROUP BY subj_id");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$agg = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_array($agg)) {
	$upd = mysqli_prepare($db, "UPDATE echos_subjs SET nm=?, date_from=?, date_to=? WHERE id=? LIMIT 1");
	mysqli_stmt_bind_param($upd, "iiii", $row['nm'], $row['date_from'], $row['date_to'], $row['subj_id']);
	mysqli_stmt_execute($upd);
}

$stmt = mysqli_prepare($db, "SELECT * FROM echos_subjs WHERE echo_id=? ORDER BY title");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {
	$t['date_from'] = date("d.m.Y", $t['date_from']);
	$t['date_to'] = date("d.m.y", $t['date_to']);
	$subjs[$n] = $t;
	$n++;
}
$smarty->assign('subjs', $subjs);




if ($_GET['lng']) {
	$smarty->assign('title', "Articles on the theme «".$t['tag_alias']."»");
}
else {
	$smarty->assign('title', "Статьи на тему «".$t['tag_name']."»");
}



include "right.php";

$smarty->display('echos_subjs.tpl');

?>
