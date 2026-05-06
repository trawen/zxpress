<?php
require 'init.inc';

$smarty->assign('issue_archive_hidden', htmlspecialchars($_GET['issue_archive_hidden'] ?? '', ENT_QUOTES, 'UTF-8'));

$id = intval($_GET['id']);
if (!$id) {$id = 1;}

$months = array(
		"01"=>"января",
		"02"=>"февраля",
		"03"=>"марта",
		"04"=>"апреля",
		"05"=>"мая",
		"06"=>"июня",
		"07"=>"июля",
		"08"=>"августа",
		"09"=>"сентября",
		"10"=>"октября",
		"11"=>"ноября",
		"12"=>"декабря");


$z = db_select($db, "SELECT * FROM pictures WHERE book_id=? ORDER BY pictures.type ASC", "i", $id);

$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {
$c[$n] = $t;
$n++;
}
$smarty->assign('screens', $c);


$z = db_select(
	$db,
	"SELECT books.*, cities.name AS city, cities.name_eng AS city_eng FROM books LEFT JOIN cities ON books.city_id=cities.id WHERE books.id=? LIMIT 1",
	"i",
	$id
);


$t = $z ? mysqli_fetch_array($z) : false;
if ($t) {
	$t['date'] = date("Y", $t['date']);
	if ((int)($t['city_id'] ?? 0) > 0 && empty($t['city'])) {
		error_log('[FIX] book.php missing city row id=' . $id . ' city_id=' . (int)$t['city_id']);
	}

	if ($t['annotation'] and strtolower(substr($t['annotation'], 0, 3)) != "<p>") {$t['annotation'] = "<p>".$t['annotation']."</p>";}
} else {
	http_response_code(404);
	error_log('[FIX] book.php not found id=' . $id);
}

$smarty->assign('press', $t);


$title = $t ? $t['title1'] : '';
if ($t && $t['title2']) {$title = $title . " — ".$t['title2'];}

$smarty->assign('title', $title);




$z = db_select($db, "SELECT * FROM books_files WHERE book_id=?", "i", $id);

$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {
	$f[$n] = $t;
	$n++;
}
$smarty->assign('files', $f);




//OTHER ARTICLES
$z = db_select($db, "SELECT * FROM chapters WHERE ch_id_book=? ORDER BY ch_date ASC", "i", $id);
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {
$art[$n] = $t;
$n++;
}
$smarty->assign('other_articles', $art);




include "right.php";

$smarty->display('book.tpl');



?>
