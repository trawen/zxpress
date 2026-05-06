<?php
require 'init.inc';

//error_reporting(E_ALL);

$page = intval($_GET['page']);
if (!$page) {$page = 1;}

$num = intval($_GET['num']);
if (!$num) {$num = 100;}

$id = intval($_GET['id']);




//$smarty->debugging = true;

$from = (($page-1) * $num);

if ($id > 0) {
	$stmt = mysqli_prepare($db, "SELECT COUNT(*) FROM screens WHERE id_press=?");
	mysqli_stmt_bind_param($stmt, "i", $id);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
}
else {
	$z = db_select($db, "SELECT COUNT(*) FROM screens");
}

$p = $z ? mysqli_fetch_array($z) : false;

$nm_pages = ceil($p[0] / $num);


$a = 0;
for ($n=0; $n < $nm_pages; $n++) {$pg[$a]=$n+1; $a++;}

$smarty->assign('pages', $pg);
$smarty->assign('tk_page', $page);

$gallerySql = 'SELECT screens.id AS gallery_screen_id, screens.type AS gallery_screen_type, screens.format AS gallery_format, '
	. 'issue.id AS gallery_issue_id, issue.title AS gallery_issue_title, '
	. 'press.id AS gallery_press_id, press.title AS gallery_press_title '
	. 'FROM screens INNER JOIN issue ON issue.id = screens.id_issue INNER JOIN press ON press.id = screens.id_press ';

if ($id > 0) {
	$stmt = mysqli_prepare($db, $gallerySql . 'WHERE press.id=? ORDER BY issue.title DESC, screens.type ASC LIMIT ?, ?');
	mysqli_stmt_bind_param($stmt, 'iii', $id, $from, $num);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
}
else {
	$stmt = mysqli_prepare($db, $gallerySql . 'ORDER BY press.title ASC, issue.title ASC, screens.type ASC LIMIT ?, ?');
	mysqli_stmt_bind_param($stmt, 'ii', $from, $num);
	mysqli_stmt_execute($stmt);
	$z = mysqli_stmt_get_result($stmt);
}

$n = 0;
$c = [];
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['gallery_press_title_plain'] = title_plain((string) ($t['gallery_press_title'] ?? ''));
	$t['gallery_issue_title_plain'] = title_plain((string) ($t['gallery_issue_title'] ?? ''));
	$t['gallery_label_plain'] = $t['gallery_press_title_plain'] . ' #' . $t['gallery_issue_title_plain'];
	$c[$n] = $t;
	$n++;
}
$smarty->assign('screens', $c);

$smarty->assign('id', $id);
$smarty->assign('num', $num);

include "right.php";



if ($_GET['lng']) {
	$smarty->assign('title', "Gallery of electronic newspapers and magazines for ZX Spectrum");
}
else {
	$smarty->assign('title', "Галерея электронных газет и журналов для ZX Spectrum");
}



$smarty->display('gallery.tpl');
?>
