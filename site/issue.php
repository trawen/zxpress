<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';

$smarty->assign('issue_archive_hidden', htmlspecialchars($_GET['issue_archive_hidden'] ?? '', ENT_QUOTES, 'UTF-8'));

$pressSlug = per_slug_normalize_path((string) ($_GET['press_slug'] ?? ''));
$issueSlug = per_slug_normalize_path((string) ($_GET['issue_slug'] ?? ''));
$slugRoute = ($pressSlug !== '');

$id = (int) ($_GET['id'] ?? 0);
$isEng = ($_GET['lng'] ?? '') === 'eng';

if ($slugRoute) {
	$pressId = ezn_find_press_id($db, $pressSlug, $isEng);
	if ($pressId <= 0) {
		http_response_code(404);
		$smarty->assign('press', null);
		$smarty->assign('title', $isEng ? 'Publication not found' : 'Издание не найдено');
		include 'right.php';
		$smarty->display('issue.tpl');
		exit;
	}
	$id = $pressId;
	if ($issueSlug !== '') {
		$issueIdForAnchor = ezn_find_issue_id($db, $id, $issueSlug, $isEng);
		if ($issueIdForAnchor > 0) {
			$stmt_anchor = $db->prepare('SELECT title FROM issue WHERE id=? LIMIT 1');
			if ($stmt_anchor) {
				$stmt_anchor->bind_param('i', $issueIdForAnchor);
				$stmt_anchor->execute();
				$anchorRow = $stmt_anchor->get_result()->fetch_assoc();
				$stmt_anchor->close();
				if (is_array($anchorRow) && ($anchorRow['title'] ?? '') !== '') {
					$smarty->assign('issue_anchor', (string) $anchorRow['title']);
				}
			}
		}
	}
} elseif ($id > 0) {
	ezn_maybe_redirect_press_legacy($db, $id, $isEng);
} else {
	$id = 1;
}




//$smarty->debugging = true;

$stmt = mysqli_prepare($db, "SELECT * FROM issue, screens WHERE issue.id_press=? AND screens.id_issue=issue.id ORDER BY issue.title DESC, screens.type ASC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {$c[] = $t;}
$smarty->assign('screens', $c);


$stmt = mysqli_prepare($db, "SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id LEFT OUTER JOIN countries ON cities.country_id=countries.id WHERE press.id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$t = mysqli_fetch_array($z);

$t['title_plain'] = title_plain($t['title'] ?? '');

if (($_GET['lng'] ?? '') === 'eng') {
	$cityEng = trim((string) ($t['name_eng'] ?? ''));
	if ($cityEng !== '') {
		$t['name'] = $cityEng;
	}
	$countryEng = trim((string) ($t['country_name_eng'] ?? ''));
	if ($countryEng !== '') {
		$t['country_name'] = $countryEng;
	}
}


if ($t['years_to'] != $t['years_from']) {

	if ($_GET['lng']) {

		$t['years_to'] = date("F Y", $t['years_to'] );

	}
	else {

		$t['years_to'] = date("".$mnt[date("m", $t['years_to'])]." Y", $t['years_to'] );

	}

}
else {

	unset($t['years_to']);

}

if ($t['years_from']) {

	if ($_GET['lng']) {

		$t['years_from'] = date("F Y", $t['years_from'] );

	}
	else {

		$t['years_from'] = date($mnt[date("m", $t['years_from'])]." Y", $t['years_from'] );


	}


}

$num = array("выпуск","выпуска","выпусков");
$smarty->assign("num", getNumEnding($t['numbers'], $num));
$t['public_url'] = ezn_url_press($t, ($_GET['lng'] ?? '') === 'eng');
$pressRow = [
	'id' => (int) $t['id'],
	'title' => (string) ($t['title'] ?? ''),
	'slug_ru' => (string) ($t['slug_ru'] ?? ''),
	'slug_en' => (string) ($t['slug_en'] ?? ''),
];
$smarty->assign('press', $t);

$isEngIssue = ($_GET['lng'] ?? '') === 'eng';
$issueCanonRow = null;
$issueCanonicalPath = ezn_url_press($pressRow, $isEngIssue);
if ($issueSlug !== '') {
	$issueIdForCanonical = ezn_find_issue_id($db, $id, $issueSlug, $isEngIssue);
	if ($issueIdForCanonical > 0) {
		$stmtIssueCanon = $db->prepare('SELECT id, id_press, title, slug_ru, slug_en FROM issue WHERE id=? LIMIT 1');
		if ($stmtIssueCanon) {
			$stmtIssueCanon->bind_param('i', $issueIdForCanonical);
			$stmtIssueCanon->execute();
			$issueCanonRow = $stmtIssueCanon->get_result()->fetch_assoc();
			$stmtIssueCanon->close();
			if (is_array($issueCanonRow)) {
				$issueCanonicalPath = ezn_url_issue($pressRow, $issueCanonRow, $isEngIssue);
			}
		}
	}
}
$origin = zxpress_canonical_origin();
$smarty->assign('canonical_url', $origin . $issueCanonicalPath);
$smarty->assign('hreflang_ru', $origin . ezn_url_for_lang(false, $pressRow, $issueCanonRow, null));
$smarty->assign('hreflang_en', $origin . ezn_url_for_lang(true, $pressRow, $issueCanonRow, null));
ezn_assign_lang_switch_urls($smarty, $pressRow, null, null);



$type[0] = "Электронная газета для ZX Spectrum";
$type[1] = "Электронный журнал для ZX Spectrum";
$smarty->assign('title', "«".$t['title_plain']."», ".$t['name']." (".$t['country_name'].") — ".$type[$t['type']]);




$stmt = mysqli_prepare($db, "SELECT * FROM issue, files WHERE id_press=? AND files.id_issue=issue.id ORDER BY title ASC");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {$is[] = $t;}
$smarty->assign('issues', $is);



// Prefetch all articles for this press with issue data in one query
$stmt = mysqli_prepare($db, "SELECT articles.*, issue.title AS issue_title, issue.slug_ru AS issue_slug_ru, issue.slug_en AS issue_slug_en, issue.date AS issue_date, issue.id AS issue_id FROM articles JOIN issue ON articles.id_issue=issue.id WHERE articles.temp=0 AND issue.id_press=? ORDER BY issue.title DESC, articles.number, articles.title");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);

$isEngIssue = ($_GET['lng'] ?? '') === 'eng';

$a = 0;
$prev_issue = null;
while ($t2 = mysqli_fetch_array($z)) {
	if ($t2['issue_id'] !== $prev_issue) {
		$t2['print_title'] = 1;
		$prev_issue = $t2['issue_id'];
	} else {
		$t2['print_title'] = 0;
	}
	$t2['issue'] = $t2['issue_title'];
	if ($_GET['lng']) {
		$t2['date'] = date("d F Y", $t2['issue_date']);
	} else {
		$t2['date'] = date("d ".$months[date("m", $t2['issue_date'])]." Y", $t2['issue_date']);
	}
	$t2['title_list'] = article_title_list_html($t2['title'] ?? '');
	$t2['title_eng_list'] = article_title_list_html($t2['title_eng'] ?? '');
	$issueRow = [
		'id' => (int) $t2['issue_id'],
		'id_press' => $id,
		'title' => (string) $t2['issue_title'],
		'slug_ru' => (string) ($t2['issue_slug_ru'] ?? ''),
		'slug_en' => (string) ($t2['issue_slug_en'] ?? ''),
	];
	$t2['issue_url'] = ezn_url_issue($pressRow, $issueRow, $isEngIssue);
	$t2['public_url'] = ezn_url_article($pressRow, $issueRow, $t2, $isEngIssue);
	$art[$a] = $t2;
	$a++;
}
$smarty->assign('articles', $art);


$n =0;
$last_issue = intval($prev_issue ?? 0);
$stmt = mysqli_prepare($db, "SELECT * FROM covers WHERE id_issue=?");
mysqli_stmt_bind_param($stmt, "i", $last_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
while ($t = mysqli_fetch_array($z)) {
$cov[$n]=$t;
$n++;
}
$smarty->assign('covers', $cov);



include "right.php";

$smarty->display('issue.tpl');



?>
