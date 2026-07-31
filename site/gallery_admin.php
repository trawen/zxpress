<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

error_reporting(E_ERROR | E_WARNING | E_PARSE);

$nm_view = 50;
$page = intval($_GET['page'] ?? 1);
if ($page < 1) {$page = 1;}

if (($_POST['save'] ?? '') == "save") {
csrf_verify();

$id_pack = explode("_", $_POST['id_pack'] ?? '');

foreach ($id_pack as $value) {
    
	if ($value) {
		$s = intval($_POST['press_'.$value] ?? 0);
		$i = intval($_POST['issue_'.$value] ?? 0);
		// if ($s > 0) {
			// $z = mysqli_query($db,"SELECT * FROM issue WHERE id_press='$s' LIMIT 1" );	error_log(mysqli_error($db));
			// $t = mysqli_fetch_array($z);
			// $i = $t['id'];
		// }

		$t = intval($_POST['type_'.$value] ?? 0);
		$vid = intval($value);
		$stmt_u = $db->prepare("UPDATE screens SET type=?, id_press=?, id_issue=? WHERE id=? LIMIT 1");
		if ($stmt_u) {
			$stmt_u->bind_param("iiii", $t, $s, $i, $vid);
			$stmt_u->execute();
			if ($stmt_u->affected_rows >= 0) {
				$updatedScreens++;
				activity_log($db, [
					'verb' => 'updated',
					'object_type' => 'screen',
					'object_id' => $vid,
					'parent_type' => $i > 0 ? 'issue' : ($s > 0 ? 'press' : null),
					'parent_id' => $i > 0 ? $i : ($s > 0 ? $s : null),
					'action' => 'screen.linked',
					'event_scope' => ACTIVITY_SCOPE_METADATA,
					'is_public' => 0,
					'after' => ['type' => $t, 'id_press' => $s, 'id_issue' => $i],
				]);
			}
		}
		error_log(mysqli_error($db));
	}
	
}

	  header("Location: /gallery_admin.php?page=$page");
	  exit;	

}

////////////////////////////////////////////////////////



$z = db_select($db, "SELECT * FROM press ORDER BY title ASC");
error_log(mysqli_error($db));

$p = "<option value='0'>-</option>";

while ($z && ($t = mysqli_fetch_array($z))) {

$p = $p . "<option value='".$t['id']."'>".$t['title']. "</option>";
$press_title[$t['id']] = $t['title'];

}

$p = $p . "</select>";

$smarty->assign('screens_select', $p);

////////////////////////////////////////////////////////



$from = max(0, ($page - 1) * $nm_view);

$z = db_select($db, "SELECT COUNT(*) FROM screens");
$p = $z ? mysqli_fetch_array($z) : false;
$nm_pages = ceil($p[0] / $nm_view);


$a = 0;
for ($n=0; $n < $nm_pages; $n++) {
	$pg[$a]=$n+1;
	$a++;
}

$smarty->assign('view_pages', $pg);
$smarty->assign('view_tk_page', $page);


// Prefetch all issues grouped by press id to avoid N+1 queries
$z_issues = db_select($db, "SELECT * FROM issue ORDER BY id_press, LENGTH(title) ASC, title ASC");
$issues_by_press = [];
while ($z_issues && ($ti = mysqli_fetch_array($z_issues))) {
    $issues_by_press[$ti['id_press']][] = $ti;
}

$stmt_screens = $db->prepare("SELECT * FROM screens LIMIT ?, ?");
$stmt_screens->bind_param("ii", $from, $nm_view);
$stmt_screens->execute();
$z = $stmt_screens->get_result();

$n = 0;
$id_pack = "";
while ($t = mysqli_fetch_array($z)) {

    $id_press = $t['id_press'];
	$p = "<select name='issue_".$t['id']."'><option value='0'>-</option>";

	if (isset($issues_by_press[$id_press])) {
	    foreach ($issues_by_press[$id_press] as $t2) {
	        $p .= "<option value='".$t2['id']."'";
	        if ($t2['id'] == $t['id_issue']) {$p .= " selected";}
	        $p .= ">".$t2['title']. "</option>";
	    }
	}

	$p = $p . "</select>";

$t['press_title'] = $press_title[$id_press];
$t['issue'] = $p;
$id_pack = $id_pack . $t['id'] . "_";
$t['nm'] = $n & 1;
$c[$n] = $t;
$n++;

}

$smarty->assign('id_pack', $id_pack);
$smarty->assign('screens', $c);

//include "left.php";
//include "right.php";

$smarty->display('gallery_admin.tpl');



?>