<?php

error_reporting(0);

function file_force_download($file) {
  if (file_exists($file)) {
    // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
    // если этого не сделать файл будет читаться в память полностью!
    if (ob_get_level()) {
      ob_end_clean();
    }
    // заставляем браузер показать окно сохранения файла
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    // читаем файл и отправляем его пользователю
    if ($fd = fopen($file, 'rb')) {
      while (!feof($fd)) {
        print fread($fd, 1024);
      }
      fclose($fd);
    }
    exit;
  }
}


require 'init.inc';

$id = intval($_GET['id'] ?? 0) * 1;

$stmt = $db->prepare("SELECT *, issue.title AS number_title, articles.title AS article_title FROM issue, articles, press WHERE articles.id=? AND issue.id=id_issue AND press.id=issue.id_press LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$z = $stmt->get_result();
$t = mysqli_fetch_array($z);

if ($t['temp'] == 1) {

    echo "Запрещенная информация! :(";
    exit;

}

$title = $t['title']."#".$t['number_title']." - ".$t['article_title'];
$title = trim(mb_strtolower($title));
$title = htmlspecialchars_decode($title);
$title = str_replace("<b>", "", $title);
$title = str_replace("</b>", "", $title);
$t = preg_replace('/ {2,}/',' ',$title);

if (mb_strlen($t) > 60) {

	for ($a=3; $a<24; $a++) {
    
		unset($arr_str);
		unset($arr);
		$old_title = $title;
		$arr_str = explode(" ", $t);
		$arr = array_slice($arr_str, 0, $a);
		$title = implode(" ", $arr);
	
		if (mb_strlen($title) > 60) {$add = 1; break;}
		$old_title = $title;
	
	}
}
else {$title = $t;}	


$baseDir = realpath(zx_storage_dir('articles'));
$text = '';
if ($baseDir !== false) {
	$candidate = $baseDir . '/' . $id;
	$resolved = realpath($candidate);
	if ($resolved !== false && is_file($resolved)
		&& strpos($resolved, $baseDir . DIRECTORY_SEPARATOR) === 0) {
		$text = (string)file_get_contents($resolved);
	}
}
$text = preg_replace('#<span[^>]*>#is', '', $text);
$text = preg_replace('#</span>#is', '', $text);
$text = preg_replace('#<div[^>]*>#is', '', $text);
$text = preg_replace('#</div>#is', '', $text);
$text = preg_replace('#<img[^>]*>#is', '', $text);

$title = str_replace("&#039", "'", $title);
$title = str_replace(chr(34), "'", $title);
//$title = str_replace("'", "`", $title);
$title = str_replace(",", ".", $title);
$title = str_replace(":", "-", $title);
//$title = str_replace("&", "+", $title);
$title = str_replace("*", ".", $title);

$title = str_replace("/", "^", $title);
$title = str_replace("_-_", "-", $title);



$title = trim(str_replace(array("/","|","\\","?",":",";","\n","\r"), "", $title));

if (substr($title,-1) == "." or substr($title,-1) == "-") {$title = substr($title, 0, -1);}
//if ($add) {$title = $title . "…";}




function do_translit($st) { 
    $replacement = array( 
        "й"=>"i","ц"=>"c","у"=>"u","к"=>"k","е"=>"e","н"=>"n", 
        "г"=>"g","ш"=>"sh","щ"=>"sh","з"=>"z","х"=>"h","ъ"=>"", 
        "ф"=>"f","ы"=>"y","в"=>"v","а"=>"a","п"=>"p","р"=>"r", 
        "о"=>"o","л"=>"l","д"=>"d","ж"=>"zh","э"=>"e","ё"=>"e", 
        "я"=>"ya","ч"=>"ch","с"=>"s","м"=>"m","и"=>"i","т"=>"t", 
        "ь"=>"","б"=>"b","ю"=>"yu", 
    ); 
    
    foreach($replacement as $i=>$u) { 
        $st = mb_eregi_replace($i,$u,$st); 
    } 
    return $st; 
} 

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Content-Disposition: attachment; filename='.do_translit($title).'.txt');
header('Pragma: public');

echo $text;

?>