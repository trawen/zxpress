<?php
require 'init.inc';

if (($_GET['lng'] ?? '') === 'eng') {
	$smarty->assign('title', 'About the project');
} else {
	$smarty->assign('title', 'Кто? Что? Зачем? Почему?');
}

include "right.php";

$smarty->display('whois.tpl');

?>
