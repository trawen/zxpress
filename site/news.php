<?php
require 'init.inc'; // 123

//error_reporting(E_ALL);

$id = intval($_GET['id']);
$description = '';

if ($id) {

	$z = db_select($db, "SELECT *, UNIX_TIMESTAMP(date) as date FROM news WHERE id=?", "i", $id);
	$t = $z ? mysqli_fetch_array($z) : false;
	if ($t) {

		$description = strip_tags(cut($t['text']));
		$t['text'] = nl2p($t['text']);
		$t['date'] = rusdate($t['date']);

 		$smarty->assign('news', $t);
 		db_exec($db, "UPDATE news SET views=views+1 WHERE id=?", "i", $id);



	}
	$smarty->assign('id', $id);
	$smarty->assign('title', $t['title'] ?? '');
	$smarty->assign('description', $description ?? '');

}
else {

	$z = db_select($db, "SELECT *, UNIX_TIMESTAMP(date) as date, n.id AS id FROM news n ORDER BY date DESC");
	while ($z && ($t = mysqli_fetch_array($z))) {

		$pos = mb_strpos($t['text'], "<cut>");
		$t['cut'] = $pos;
		if ($pos) {

			$t['text'] = mb_substr($t['text'], 0, $pos-1);

		}

		$t['niceurl'] = niceurl($t['title']);
		$t['text'] = nl2p($t['text']);
		$t['date'] = rusdate($t['date']);
 		$news[] = $t;

	}
	$smarty->assign('news', $news);

}

include "right.php";

$smarty->display('news.tpl');

function cut($text) {

	$pos = mb_strpos($text, "<cut>");
	if ($pos) {

		return mb_substr($text, 0, $pos-1);

	}

}

// nl2p() and rusdate() moved to includes/functions.php

?>
