<?php
require 'init.inc';

$_REQUEST['id'] = 0;

require "comments.php";




if (!empty($_GET['lng'])) {
	$smarty->assign('title', "Guestbook");
}
else {
	$smarty->assign('title', "Гостевая книга");
}


include "right.php";

$smarty->display('guestbook.tpl');

?>
