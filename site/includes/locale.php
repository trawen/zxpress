<?php
/**
 * Locale, language links, and calendar sidebar data. Included from init.inc only.
 *
 * @param Smarty $smarty
 * @param mysqli $db
 */
function setup_locale($smarty, $db, array $months): void
{
	$smarty->assign('today_time', date("G:i", time()));

	$lng = $_GET['lng'] ?? null;
	if ($lng === 'en') {
		$lng = 'eng';
	}
	if ($lng === null) {
		$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
		if (is_string($requestPath) && (str_starts_with($requestPath, '/eng/') || str_starts_with($requestPath, '/en/'))) {
			$lng = 'eng';
		}
	}

	if ($lng === 'eng') {
		$smarty->assign('today_month', date("j F", time()));
		$smarty->assign('eng_link', "&lng=eng");
	} else {
		$smarty->assign('today_month', date("j", time()) . " " . $months[date("m", time())]);
	}

	$monday = date('m', time()) . date('d', time());
	$stmt_cal = $db->prepare("SELECT * FROM calendar WHERE monday_cal=? ORDER BY year_cal DESC");
	$stmt_cal->bind_param("s", $monday);
	$stmt_cal->execute();
	$z = $stmt_cal->get_result();

	$mnd = array();
	$n = 0;
	while ($t = mysqli_fetch_array($z)) {
		$mnd[$n] = $t;
		$n++;
	}
	$smarty->assign('monday', $mnd);

	if ($lng == "eng") {
		$smarty->assign('sl', "?lng=eng");
		$smarty->assign('dl', "&lng=eng");
	}

	$smarty->assign('lng', $lng);

	$skipBg = isset($_GET['skip-bg']) && (string) $_GET['skip-bg'] === '1';
	$smarty->assign('skip_bg', $skipBg);

	$_safe_uri = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8');

	if (strrpos($_SERVER['REQUEST_URI'], "lng=eng")) {
		$r1 = str_replace("&lng=eng", "", $_safe_uri);
		$r2 = str_replace("?lng=eng", "", $_safe_uri);

		if (strlen($r1) > strlen($r2)) {
			$smarty->assign('url_rus', $r2);
		} else {
			$smarty->assign('url_rus', $r1);
		}

		$smarty->assign('url_eng', $_safe_uri);
	} else {
		$smarty->assign('url_rus', $_safe_uri);

		if (strrpos($_SERVER['REQUEST_URI'], "?")) {
			$smarty->assign('url_eng', $_safe_uri . "&lng=eng");
		} else {
			$smarty->assign('url_eng', $_safe_uri . "?lng=eng");
		}
	}
}
