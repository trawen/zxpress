<?php
require 'init.inc';

// function getFilesSize($path)
// {
    // $fileSize = 0;
    // $dir = scandir($path);

    // foreach($dir as $file)
    // {
        // if (($file!='.') && ($file!='..')) {$fileSize += filesize($path . '/' . $file);}
    // }

    // return $fileSize;
// }

//$smarty->debugging = true;

$z = db_select($db, "SELECT COUNT(*) FROM press WHERE type='0'");
$t = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('magazines', ($t && isset($t[0])) ? $t[0] : 0);

$z = db_select($db, "SELECT COUNT(*) FROM press WHERE type='1'");
$t = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('papers', $t ? $t[0] : 0);

$z = db_select($db, "SELECT COUNT(*) FROM articles");
$t = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('articles', $t ? $t[0] : 0);

$z = db_select($db, "SELECT COUNT(*) FROM screens");
$t = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('screens', $t ? $t[0] : 0);

$z = db_select($db, "SELECT COUNT(*) FROM issue");
$t = $z ? mysqli_fetch_array($z) : false;
$smarty->assign('issues', $t ? $t[0] : 0);

$z = db_select($db, "SELECT press.city, cities.name, cities.country_id, COUNT(*) AS kl FROM press JOIN cities ON press.city=cities.id WHERE press.city!=207 AND press.type<2 GROUP BY press.city, cities.name, cities.country_id ORDER BY kl DESC LIMIT 20");

$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {
$c[$n] = $t;
$n++;
}

$smarty->assign('bycity', $c);


$z = db_select($db, "SELECT * FROM press WHERE type=1 ORDER BY numbers DESC LIMIT 9");

$n = 0;
unset($c);
while ($z && ($t = mysqli_fetch_array($z))) {

$c[$n] = $t;
$n++;

}
$smarty->assign('mag_issues', $c);


$z = db_select($db, "SELECT * FROM press WHERE type=0 ORDER BY numbers DESC LIMIT 10");

$n = 0;
unset($c);
while ($z && ($t = mysqli_fetch_array($z))) {

$c[$n] = $t;
$n++;

}
$smarty->assign('pap_issues', $c);



$z = db_select($db, "SELECT log.id_user, users.username AS name, COUNT(*) AS kl FROM log JOIN users ON log.id_user=users.id WHERE log.type=1 AND log.id_user!=0 GROUP BY log.id_user, users.username ORDER BY kl DESC LIMIT 10");

$n = 0;
unset($c);
while ($z && ($t = mysqli_fetch_array($z))) {
$c[$n] = $t;
$n++;
}

$smarty->assign('users', $c);



$n = 0;
unset($c);
$z = db_select($db, "SELECT * FROM articles ORDER BY views DESC LIMIT 20");
while ($z && ($t = mysqli_fetch_array($z))) {


$t['date'] = date("d ".$months[date("m", $t['date'])]." Y", $t['date'] );
$t['title_list'] = article_title_list_html($t['title'] ?? '');
$c[$n] = $t;
$n++;

}

$smarty->assign('top_articles', $c);



$n = 0;
$z = db_select($db, "SELECT * FROM search ORDER BY date DESC LIMIT 20");
while ($z && ($t = mysqli_fetch_array($z))) {

	$t['date'] = date("d ".$months[date("m", $t['date'])]." Y", $t['date'] );
	$s[$n] = $t;
	$n++;

}
$smarty->assign('search_stat', $s);




if ($_GET['lng']) {
	$smarty->assign('title', "Statistics");
}
else {
	$smarty->assign('title', "Статистика");
}



include "right.php";

$smarty->display('stats.tpl');

?>
