<?php
require 'init.inc';




$smarty->assign('a', htmlspecialchars($_GET['x'] ?? '', ENT_QUOTES, 'UTF-8'));


$z = db_select($db, "SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id ORDER BY title ASC");

$f = 0;
$n = 0;
$a = "#";
while ($z && ($t = mysqli_fetch_array($z))) {

$t['title_plain'] = title_plain($t['title'] ?? '');
$s = strtoupper(mb_substr($t['title_plain'], 0, 1, 'UTF-8'));
if ($f AND $a !=$s AND ($s < "0" OR $s>"9") ) {

	$a = strtoupper(mb_substr($t['title_plain'], 0, 1, 'UTF-8'));
	//$t['years_to'] = date("Y", $t['years_to']);
	//$t['years_from'] = date("Y", $t['years_from']);
	$t['off'] = 0;

	$c[$n] = $t;
	$n++;
	$t['letter'] = $a;
}

$t['off'] = 1;
$t['years_to'] = date("Y", $t['years_to']);
$t['years_from'] = date("Y", $t['years_from']);
$t['flag'] = $country_id[$t['country_id']];

$t['finish'] = intval( (100/$t['numbers']) * $t['online_issues']);

$c[$n] = $t;

$t['letter'] = "";
$n++;
$f = 1;
}
$smarty->assign('catalog', $c);



if ($_GET['lng']) {
	$smarty->assign('title', "Library of electronic newspapers and magazines for ZX Sepectrum");
}
else {
	$smarty->assign('title', "Библиотека электронных газет и журналов для ZX Spectrum");
}



include "right.php";

$smarty->display('ezines.tpl');

?>
