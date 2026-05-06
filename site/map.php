<?php
require 'init.inc';

$z = db_select(
	$db,
	"SELECT *, press.id AS idp, screens.id AS map_screen_id, screens.format AS map_screen_format "
	. "FROM press INNER JOIN cities ON cities.id = press.city "
	. "LEFT OUTER JOIN countries ON cities.country_id = countries.id "
	. "LEFT JOIN screens ON screens.id = (SELECT MIN(s2.id) FROM screens s2 WHERE s2.id_press = press.id) "
	. "WHERE cities.country_id <> 0 "
);
while ($z && ($t = mysqli_fetch_array($z))) {

	$b[0] = $t['lat'];//+rand(10)/10;
	$b[1] = $t['lng'];//+rand(10)/10;
	// Map marker thumb: first screenshot for this press (same pattern as issue.tpl / search.tpl), else empty placeholder.
	$thumb = '/img/empty_img.png';
	if (!empty($t['map_screen_id'])) {
		$fmt = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($t['map_screen_format'] ?? 'png'));
		if ($fmt === '') {
			$fmt = 'png';
		}
		$thumb = '/screens/1/' . (int) $t['map_screen_id'] . '.' . $fmt;
	}
	$b[2] = "<a href='issue.php?id=".$t['idp']."'>".$t['title']."</a><br><img src='".$thumb."' width=64 height=48>";
	$map[] = $b;

}
$smarty->assign('map', json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));



if ($_GET['lng']) {
	$smarty->assign('title', "ZXPress updates list");
}
else {
	$smarty->assign('title', "Список последних обновлений");
}




include "right.php";

$smarty->display('map.tpl');
?>
