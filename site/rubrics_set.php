<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

csrf_verify();

//error_reporting(E_ALL);

$menu = intval($_POST['menu']);
$article = intval($_POST['article']);

if ($menu == 777) {

	$stmt = $db->prepare("DELETE FROM menu_articles WHERE id_article=? LIMIT 1");
	if ($stmt) {
		$stmt->bind_param("i", $article);
		$stmt->execute();
	}

}
else {

	$stmt = $db->prepare("DELETE FROM menu_articles WHERE id_article=?");
	if ($stmt) {
		$stmt->bind_param("i", $article);
		$stmt->execute();
	}
	$stmt2 = $db->prepare("INSERT INTO menu_articles (`id_menu`, `id_article`) VALUES (?, ?)");
	if ($stmt2) {
		$stmt2->bind_param("ii", $menu, $article);
		$stmt2->execute();
	}

}

?>
