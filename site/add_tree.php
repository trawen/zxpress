<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

csrf_verify();

//error_reporting(E_ALL);

$id = intval($_POST['id'] ?? 0);
$name = $_POST['name'] ?? '';

if ($id) {

	$stmt = $db->prepare("INSERT INTO menu (`id`, `name`, `parent`) VALUES (NULL, ?, ?)");
	if ($stmt) {
		$stmt->bind_param("si", $name, $id);
		$stmt->execute();
	}
	$stmt2 = $db->prepare("UPDATE menu SET childrens=childrens+1 WHERE id=? LIMIT 1");
	if ($stmt2) {
		$stmt2->bind_param("i", $id);
		$stmt2->execute();
	}
	exit;
}
else {

	$stmt = $db->prepare("INSERT INTO menu (`id`,`name`) VALUES (NULL,?)");
	if ($stmt) {
		$stmt->bind_param("s", $name);
		$stmt->execute();
	}
	$id = mysqli_insert_id($db);
	echo $id;
	exit;
}

?>
