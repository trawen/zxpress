<?php
require 'init.inc';

//error_reporting(E_ALL);

$id = intval($_GET['id']);

$filesDir = realpath(zx_storage_dir('files'));
if ($filesDir === false) {
	http_response_code(500);
	exit;
}

$rand = bin2hex(random_bytes(16));
$filename = zx_storage_path('tmp', $rand . '_' . $id . '.zip');

$zip = new ZipArchive;
$res = $zip->open($filename, ZipArchive::CREATE);
if ($res === TRUE) {
	chmod($filename, 0644);

	$s = db_select($db, "SELECT * FROM issue,files WHERE id_press=? AND id_issue=issue.id", "i", $id);
	while ($s && ($t = mysqli_fetch_array($s))) {

		$safeName = basename((string)($t['name'] ?? ''));
		if ($safeName === '' || $safeName === '.' || $safeName === '..') {
			continue;
		}
		$fullPath = realpath($filesDir . '/' . $safeName);
		if ($fullPath === false || !is_file($fullPath)) {
			continue;
		}
		if (strpos($fullPath, $filesDir . DIRECTORY_SEPARATOR) !== 0) {
			continue;
		}
		$zip->addFile($fullPath, $safeName);

	}

	$zip->setArchiveComment('Copyright (C) 2009-2017 www.zxpress.ru');
	$readmePath = realpath(zx_storage_path('files', 'readme.txt'));
	if ($readmePath !== false && is_file($readmePath)
		&& strpos($readmePath, $filesDir . DIRECTORY_SEPARATOR) === 0) {
		$zip->addFile($readmePath, "readme.txt");
	}
	$zip->close();

	if (file_exists ($filename)) {

		$sql = db_select($db, "SELECT title FROM press WHERE id=?", "i", $id);
		$name = $sql ? mysqli_fetch_array($sql) : false;
		if (!$name || !isset($name[0])) {
			unlink($filename);
			header("HTTP/1.0 404 Not Found");
			exit;
		}

		if (ob_get_level()) {
      		ob_end_clean();
    	}

    	header('Content-Description: File Transfer');
    	header('Content-Type: application/zip');
    	$safe_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name[0]);
    	header('Content-Disposition: attachment; filename="' . $safe_name . '.zip"');
    	header('Content-Transfer-Encoding: binary');
    	header('Expires: 0');
    	header('Cache-Control: must-revalidate');
    	header('Pragma: public');
    	header('Content-Length: ' . filesize($filename));
       	readfile($filename);
       	unlink($filename);
		exit;

	}
	else {header("HTTP/1.0 404 Not Found");}

}

?>
