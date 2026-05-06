<?php
require 'init.inc';

//error_reporting(E_ALL);

$e = mb_substr(trim((string)($_GET['e'] ?? '')), 0, 32);
$subj_id = intval($_GET['id']);

$stmt_echo = $db->prepare("SELECT * FROM echos_titles2 WHERE title=? LIMIT 1");
$stmt_echo->bind_param("s", $e);
$stmt_echo->execute();
$z = $stmt_echo->get_result();
$t = mysqli_fetch_array($z);
if (is_array($t)) {
	$t['title'] = plain_text_decode_entities((string) ($t['title'] ?? ''));
	$t['description'] = plain_text_decode_entities((string) ($t['description'] ?? ''));
}
$smarty->assign('echo', $t);
$id = $t ? (int)$t['id'] : 0;
$smarty->assign('id', $id);


if ($subj_id) {

	$stmt_subj = $db->prepare("SELECT title FROM echos_subjs2 WHERE echo_id=? AND id=? LIMIT 1");
	$stmt_subj->bind_param("ii", $id, $subj_id);
	$stmt_subj->execute();
	$z = $stmt_subj->get_result();
	$t = mysqli_fetch_array($z);
	$smarty->assign('subj_title', $t ? plain_text_decode_entities((string) $t[0]) : '');

	$stmt_topic = $db->prepare("SELECT * FROM echos_zxnet WHERE echo_id=? AND subj_id=? ORDER BY date");
	$stmt_topic->bind_param("ii", $id, $subj_id);
	$stmt_topic->execute();
	$z = $stmt_topic->get_result();
	$topic = [];
	$zxnet_entity_fix_logs = 0;
	while ($z && ($t = mysqli_fetch_array($z))) {

		$rawText = (string) ($t['text'] ?? '');
		$decoded = plain_text_decode_entities($rawText);
		if ($decoded !== $rawText && preg_match('/&(quot|amp|lt|gt|apos);|&#\d+;/i', $rawText)) {
			if ($zxnet_entity_fix_logs < 20) {
				error_log('[FIX] zxnet decoded HTML entities in topic body id=' . (int) ($t['id'] ?? 0));
				$zxnet_entity_fix_logs++;
			}
		}
		$t['text'] = $decoded;
		$t['name_from'] = plain_text_decode_entities((string) ($t['name_from'] ?? ''));
		$t['name_to'] = plain_text_decode_entities((string) ($t['name_to'] ?? ''));
		$t['date'] = date("d M Y", $t['date']);
		$topic[] = $t;

	}
	$smarty->assign('topic', $topic);

}
elseif ($id) {



	// `city`.`id` AS `cityid`
	$z = db_select($db, "SELECT * FROM echos_subjs2 WHERE echo_id=? ORDER BY title", "i", $id);
	while ($z && ($t = mysqli_fetch_array($z))) {

		$t['title'] = plain_text_decode_entities((string) ($t['title'] ?? ''));
		$t['date_from'] = date("d.m.Y", $t['date_from']);
		$t['date_to'] = date("d.m.y", $t['date_to']);

		$subjs[] = $t;

	}
	$smarty->assign('subjs', $subjs);

}
else {

	$z = db_select($db, "SELECT * FROM echos_titles2 ORDER BY title");
	while ($z && ($t = mysqli_fetch_array($z))) {

		$t['title'] = plain_text_decode_entities((string) ($t['title'] ?? ''));
		$t['date_from'] = $mnt[date("m", $t['date_from'])].date(" Y", $t['date_from']);
		$t['date_to'] = date("d.m.y", $t['date_to']);
		$echos[] = $t;

	}
	$smarty->assign('echos', $echos);

	$smarty->assign('title', "ZXNet эхоконференция «CODE.ZX»");

}




include "right.php";

$smarty->display('zxnet.tpl');

?>
