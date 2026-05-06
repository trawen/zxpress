<?php
error_reporting(E_ALL);

require 'init.inc';



$id = intval($_GET['id']);
$r = intval($_GET['r']);
$smarty->assign('r_id', $r);
$smarty->assign('id', $id);
$id_rubrics[] = $id;

// function get_parents($id) {

// 	Global $h1;
// 	Global $db;

// 	$z = mysqli_query($db,"SELECT * FROM rubrics WHERE id='$id' LIMIT 1" );
// 	$t = mysqli_fetch_array($z);
// 	if ($t) {

// 		$h1[] = $t['name'];
// 		get_parents($t['parent']);

// 	}

// }

function get_childrens($id) {

	Global $id_rubrics;
	Global $db;

	$stmt = $db->prepare("SELECT * FROM rubrics WHERE parent=?");
	if (!$stmt) {
		return;
	}
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$z = $stmt->get_result();
	while ($z && ($t = mysqli_fetch_array($z))) {

		if ($t) {

			$id_rubrics[] = $t['id'];

			if ($t['childrens']) {

				get_childrens($t['id']);

			}
		}
	}
}



function rubrics_tree($id, $first = false) {

	Global $db;
	$tree = [];

	if ($first) {

		$stmt = $db->prepare("SELECT * FROM rubrics WHERE id=? LIMIT 1");
		if ($stmt) {
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$z = $stmt->get_result();
			$t = $z ? mysqli_fetch_array($z) : false;
		} else {
			$t = false;
		}
		if ($t) {
			$t['tree'] = rubrics_tree($id);
			$t['last'] = 1;
			$tree[] = $t;
		}

	}
	else {

		$stmt = $db->prepare("SELECT * FROM rubrics WHERE parent=? ORDER BY name");
		if ($stmt) {
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$z = $stmt->get_result();
		} else {
			$z = false;
		}
		while ($z && ($t = mysqli_fetch_array($z))) {

			if ($t['childrens']) {

				$t['tree'] = rubrics_tree($t['id']);

			}
			$tree[] = $t;
		}
		if (!empty($tree)) { $tree[count($tree)-1]['last'] = 1; }
	}
	return $tree;
}

$tree = content_tree_add_name_plain(rubrics_tree($r, 1));
$smarty->assign('rubrics', $tree);


if ($id) {

	$smarty->assign('h1', rubrics_breadcrumb_plain_labels($db, $id));

	get_childrens($id);

	$z = db_select_in_ints($db, "SELECT *, issue.date AS date, articles.title AS title, press.title AS press_name, issue.title AS nm_issue FROM rubrics_articles, articles, issue, press WHERE id_rubrics IN (__IN__) AND articles.id=id_article AND issue.id=id_issue AND press.id=issue.id_press ORDER BY issue.date ASC", $id_rubrics);
	$ra = [];
	while ($z && ($t = mysqli_fetch_array($z))) {

		$t['date'] = $_GET['lng'] ? date("d F Y", $t['date']) : date($mnt[date("m", $t['date'])]." Y", $t['date']);

		if ($last != $t['id_issue']) {

			$t['show'] = 1;
			$last = $t['id_issue'];

		}
		else {

			$t['show'] = 0;

		}

		$t['title_list'] = article_title_list_html($t['title'] ?? '');
		$t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
		$t['press_name_plain'] = title_plain($t['press_name'] ?? '');

		$ra[] = $t;

	}
	$smarty->assign('rubrics_articles', $ra);
}




include "right.php";

$smarty->display('rubrics.tpl');
?>
