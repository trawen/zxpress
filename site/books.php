<?php
require 'init.inc';


if ($_GET['lng']) {
	$smarty->assign('title', "Library of paper books and magazines for ZX Sepectrum");
}
else {
	$smarty->assign('title', "Библиотека бумажных книг и журналов для ZX Spectrum");
}

$z = db_select($db, "SELECT id,title1,title2,publisher,date,series,image_id,online,file_id FROM books ORDER BY title1 ASC ");

$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {

	$t['date'] = date("Y", $t['date']);

	$b[$n] = $t;
	$n++;

}

$smarty->assign('books', $b);


include "right.php";

$smarty->display('books.tpl');

?>
