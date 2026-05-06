<?php
require 'init.inc';





$z = db_select($db, "SELECT * FROM cities, press  WHERE city=cities.id ORDER BY title ASC ");


$f = 0;
$n = 0;
$a = "#";
while ($z && ($t = mysqli_fetch_array($z))) {

$t['title_plain'] = title_plain($t['title'] ?? '');
$s = strtoupper(mb_substr($t['title_plain'], 0, 1, 'UTF-8'));
if ($f AND $a <> $s AND ($s < "0" OR $s>"9") ) {

	$a = strtoupper(mb_substr($t['title_plain'], 0, 1, 'UTF-8'));
	$t['years_to'] = date("Y", $t['years_to']);
	$t['years_from'] = date("Y", $t['years_from']);
	$t['off'] = 0;

	$c[$n] = $t;
	$n++;
	$t['letter'] = $a;
}

$t['off'] = 1;
$t['years_to'] = date("Y", $t['years_to']);
$t['years_from'] = date("Y", $t['years_from']);
$t['flag'] = $country_id[$t['country_id']];
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



$z = db_select($db, "SELECT * FROM books ORDER BY title1 ASC ");


$f = 0;
$n = 0;
$a = "#";
while ($z && ($t = mysqli_fetch_array($z))) {

	$t['date'] = date("Y", $t['date']);
	$t['title1_plain'] = title_plain($t['title1'] ?? '');
	$t['title2_plain'] = title_plain($t['title2'] ?? '');
	$t['publisher_plain'] = title_plain($t['publisher'] ?? '');

	$b[$n] = $t;
	$n++;

}

$smarty->assign('books', $b);



include "right.php";

$smarty->display('catalog.tpl');

?>
