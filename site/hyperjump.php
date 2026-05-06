<?php
require 'init.inc';

if (isset($_GET['logged_out'])) {
	$smarty->assign('logout_notice', 1);
}
if (isset($_GET['session_timeout'])) {
	$smarty->assign('session_timeout_notice', 1);
}

$smarty->assign('bycity', $c);
$smarty->assign('title', "Вход");

include "right.php";

$smarty->display('input.tpl');



?>
