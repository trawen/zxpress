<?php
require 'init.inc';

//$smarty->debugging = true;

require_once __DIR__ . '/includes/chronology_graph.php';

$bounds = chronology_year_bounds($db);
$y = [];
for ($a = $bounds['min']; $a <= $bounds['max']; $a++) {
	$y[] = $a;
}
$smarty->assign('years', $y);

$layout = chronology_chart_layout($bounds['min'], $bounds['max']);
$smarty->assign('chronology_png_w', (string) $layout['canvasW']);
$smarty->assign('chronology_png_h', (string) $layout['hscr']);

$d = chronology_build_month_counts($db);

$tb = chronology_sane_timestamp_bounds();
$z = db_select($db, "SELECT * FROM calendar ORDER BY date_cal DESC");
$n = 0;
$l = null;
$ml = 0;
$mnd = [];
while ($z && ($t = mysqli_fetch_array($z))) {

	$ts = (int) $t['date_cal'];
	if ($ts < $tb['min'] || $ts > $tb['max']) {
		error_log('[FIX] chronology skipped row (date_cal out of range) id_cal=' . (int) ($t['id_cal'] ?? 0) . ' date_cal=' . $ts);
		continue;
	}
	$yrDisplay = chronology_row_display_year($t, $ts);
	$t['year_display'] = (string) $yrDisplay;

	if ($l !== $yrDisplay) {
		$t['y'] = 1;
		$l = $yrDisplay;
	} else {
		$yr_count[$yrDisplay] = ($yr_count[$yrDisplay] ?? 0) + 1;
	}

	if ($ml != date('m', $ts)) {
		$t['m'] = 1;
		$ml = date('m', $ts);
	}

	if (($_GET['lng'] ?? '') === 'eng') {

		$t['date'] = date('j F', $ts);

	}
	else {

		$t['date'] = date('d ', $ts) . $months[date('m', $ts)];

	}
	$t['title_cal_plain'] = title_plain((string) ($t['title_cal'] ?? ''));
	$mnd[$n] = $t;
	$n++;

}
$smarty->assign('chronology', $mnd);

$chronologyPng = zx_storage_dir('chronology_png');
chronology_render_png($d, $chronologyPng, $bounds['min'], $bounds['max']);
// Cache-bust: browsers aggressively cache zxpress_dinamic.png; mtime changes after each render.
$smarty->assign('chronology_png_ver', is_readable($chronologyPng) ? (string) filemtime($chronologyPng) : (string) time());

if ($_GET['lng']) {
	$smarty->assign('title', "Chronology of the issuance of electronic newspapers and magazines on ZX Spectrum");
}
else {
	$smarty->assign('title', "Хронология выпуска электронных газет и журналов на ZX Spectrum");
}


include "right.php";

$smarty->display('chronology.tpl');
