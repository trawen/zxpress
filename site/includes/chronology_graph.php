<?php
/**
 * Chronology page: PNG chart from `calendar` (issues per month / year trend).
 * Used by chronology.php and regenerate_chronology_graph.php (CLI / entrypoint).
 *
 * Year range: from `calendar.date_cal` (see chronology_year_bounds); graph/nav/CLI share the same bounds.
 * Fallback when no data: 1993–2013 (legacy fixed range).
 *
 * Requires: functions.php (db_select), mysqli $db
 */

/**
 * Allowed unix range for calendar.date_cal (exclude garbage / overflow years in charts and lists).
 *
 * @return array{min:int,max:int}
 */
function chronology_sane_timestamp_bounds(): array {
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	$minTs = strtotime('1980-01-01 00:00:00');
	$maxTs = strtotime('2040-12-31 23:59:59');
	if ($minTs === false || $maxTs === false) {
		error_log('[chronology] ERROR sane_timestamp_bounds strtotime failed');
		$cached = ['min' => 315532800, 'max' => 2240611199];
		return $cached;
	}
	$cached = ['min' => (int) $minTs, 'max' => (int) $maxTs];
	return $cached;
}

/**
 * @return array{min:int,max:int}
 */
function chronology_year_bounds(mysqli $db): array {
	$fallback = ['min' => 1993, 'max' => 2013];
	$tb = chronology_sane_timestamp_bounds();
	$minTs = $tb['min'];
	$maxTs = $tb['max'];

	$sql = 'SELECT
		MIN(YEAR(FROM_UNIXTIME(date_cal))) AS y_min,
		MAX(YEAR(FROM_UNIXTIME(date_cal))) AS y_max
		FROM calendar
		WHERE date_cal >= ? AND date_cal <= ?';
	$st = mysqli_prepare($db, $sql);
	if ($st === false) {
		error_log('[chronology] WARN bounds prepare failed: ' . mysqli_error($db));
		return $fallback;
	}
	mysqli_stmt_bind_param($st, 'ii', $minTs, $maxTs);
	if (!mysqli_stmt_execute($st)) {
		error_log('[chronology] WARN bounds execute failed: ' . mysqli_stmt_error($st));
		mysqli_stmt_close($st);
		return $fallback;
	}
	$res = mysqli_stmt_get_result($st);
	mysqli_stmt_close($st);
	if (!$res) {
		error_log('[chronology] WARN bounds no result');
		return $fallback;
	}
	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);
	if (!$row || $row['y_min'] === null || $row['y_max'] === null) {
		error_log('[chronology] WARN bounds empty calendar; fallback=1993-2013');
		return $fallback;
	}
	$ymin = (int) $row['y_min'];
	$ymax = (int) $row['y_max'];
	if ($ymin > $ymax) {
		error_log('[chronology] WARN bounds ymin>ymax; fallback=1993-2013');
		return $fallback;
	}
	$ymin = max(1980, min(2040, $ymin));
	$ymax = max(1980, min(2040, $ymax));
	if ($ymin > $ymax) {
		error_log('[chronology] WARN bounds clamped empty; fallback=1993-2013');
		return $fallback;
	}

	error_log('[chronology] INFO bounds min=' . $ymin . ' max=' . $ymax);

	chronology_warn_year_cal_mismatch($db, $minTs, $maxTs);

	return ['min' => $ymin, 'max' => $ymax];
}

/**
 * Log when stored year_cal disagrees with the year derived from date_cal.
 */
/**
 * Calendar year for chronology section headers: when date_cal’s year disagrees with stored year_cal
 * but year_cal is a plausible 4-digit year, trust year_cal (fixes bad timestamps while keeping editorial year).
 */
function chronology_row_display_year(array $row, int $dateCalTs): int {
	$yFromDate = (int) date('Y', $dateCalTs);
	$ycRaw = isset($row['year_cal']) ? trim((string) $row['year_cal']) : '';
	if ($ycRaw === '' || !preg_match('/^\d{4}\z/', $ycRaw)) {
		return $yFromDate;
	}
	$yCal = (int) $ycRaw;
	$tb = chronology_sane_timestamp_bounds();
	$yMin = (int) date('Y', $tb['min']);
	$yMax = (int) date('Y', $tb['max']);
	if ($yCal < $yMin || $yCal > $yMax) {
		return $yFromDate;
	}
	if ($yCal !== $yFromDate) {
		static $chronology_year_cal_fix_logs = 0;
		if ($chronology_year_cal_fix_logs < 12) {
			error_log('[FIX] chronology year_display: use year_cal=' . $yCal . ' not date Y=' . $yFromDate . ' id_cal=' . (int) ($row['id_cal'] ?? 0));
			$chronology_year_cal_fix_logs++;
		}
		return $yCal;
	}
	return $yFromDate;
}

function chronology_warn_year_cal_mismatch(mysqli $db, int $minTs, int $maxTs): void {
	$sql = 'SELECT COUNT(*) AS c FROM calendar
		WHERE date_cal >= ? AND date_cal <= ?
		AND CAST(year_cal AS UNSIGNED) <> YEAR(FROM_UNIXTIME(date_cal))';
	$st = mysqli_prepare($db, $sql);
	if ($st === false) {
		return;
	}
	mysqli_stmt_bind_param($st, 'ii', $minTs, $maxTs);
	if (!mysqli_stmt_execute($st)) {
		mysqli_stmt_close($st);
		return;
	}
	$res = mysqli_stmt_get_result($st);
	mysqli_stmt_close($st);
	if (!$res) {
		return;
	}
	$row = mysqli_fetch_assoc($res);
	mysqli_free_result($res);
	$c = isset($row['c']) ? (int) $row['c'] : 0;
	if ($c > 0) {
		error_log('[chronology] WARN year_cal vs date_cal mismatch rows=' . $c);
	}
}

/**
 * Layout for the PNG: shared by chronology_render_png and the page (width/height for Smarty).
 * Policy: fit width into max 1400px by shrinking monthly column width $wm when there are many years.
 *
 * @return array{canvasW:int,hscr:int,xd:int,yd:int,wm:float,wy:float,hm:float,yearMin:int,yearMax:int}
 */
function chronology_chart_layout(int $yearMin, int $yearMax): array {
	$hscr = 340;
	$xd = 16;
	$yd = 32;
	$hm = 4.0;
	$rightPad = 16;
	$maxCanvas = 1400;
	$minCanvas = 480;
	$defaultWm = 3.05;

	$numYears = $yearMax - $yearMin + 1;
	if ($numYears < 1) {
		$numYears = 1;
	}

	// Horizontal budget for year columns (one column width = $wy = 12 * $wm months).
	$budget = $maxCanvas - $xd - $rightPad;
	if ($budget < 1) {
		$budget = 1;
	}
	// Shrink $wm when many years so total width stays within $maxCanvas (never raise $wm above fit).
	$wm = min($defaultWm, $budget / (12 * $numYears));
	$wy = 12 * $wm;
	$needW = $xd + $numYears * $wy + $rightPad;
	$canvasW = (int) ceil($needW);
	if ($canvasW > $maxCanvas) {
		$canvasW = $maxCanvas;
	}
	$canvasW = max($minCanvas, $canvasW);

	return [
		'canvasW' => $canvasW,
		'hscr' => $hscr,
		'xd' => $xd,
		'yd' => $yd,
		'wm' => $wm,
		'wy' => $wy,
		'hm' => $hm,
		'yearMin' => $yearMin,
		'yearMax' => $yearMax,
	];
}

/**
 * @return array<string,int> keys like "1997_3" => count
 */
function chronology_build_month_counts(mysqli $db): array {
	$d = [];
	$tb = chronology_sane_timestamp_bounds();
	$z = db_select($db, 'SELECT date_cal FROM calendar ORDER BY date_cal DESC');
	if (!$z) {
		return $d;
	}
	while ($t = mysqli_fetch_assoc($z)) {
		$ts = (int) $t['date_cal'];
		if ($ts < $tb['min'] || $ts > $tb['max']) {
			continue;
		}
		$yr = (int) date('Y', $ts);
		$mr = (int) date('n', $ts);
		$k = $yr . '_' . $mr;
		$d[$k] = ($d[$k] ?? 0) + 1;
	}
	mysqli_free_result($z);

	return $d;
}

function chronology_month_count(array $d, int $y, int $m): int {
	return (int) ($d[$y . '_' . $m] ?? 0);
}

/**
 * First readable TTF for imagettftext, or null (caller falls back to imagestring).
 */
function chronology_resolve_ttf_font(): ?string {
	$candidates = [];
	$env = getenv('CHRONOLOGY_FONT');
	if ($env !== false && $env !== '') {
		$candidates[] = $env;
	}
	$candidates[] = __DIR__ . '/../fonts/DejaVuSans.ttf';
	$candidates[] = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
	$candidates[] = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';

	foreach ($candidates as $path) {
		if ($path !== '' && is_readable($path)) {
			return $path;
		}
	}

	return null;
}

/**
 * Draw centered text; uses TrueType if available, else built-in GD font.
 *
 * @param GdImage|resource $im
 */
function chronology_draw_label_centered($im, ?string $fontPath, float $size, float $xCenter, float $yTop, int $color, string $text): void {
	if ($fontPath !== null && function_exists('imagettfbbox') && function_exists('imagettftext')) {
		$bbox = @imagettfbbox($size, 0, $fontPath, $text);
		if (is_array($bbox) && count($bbox) >= 8) {
			$w = abs($bbox[2] - $bbox[0]);
			$x = $xCenter - $w / 2;
			$yBaseline = $yTop - $bbox[7];
			imagettftext($im, $size, 0, (int) round($x), (int) round($yBaseline), $color, $fontPath, $text);

			return;
		}
	}

	$font = 2;
	$lw = imagefontwidth($font) * strlen($text);
	imagestring($im, $font, (int) ($xCenter - $lw / 2), (int) $yTop, $text, $color);
}

/**
 * Render chronology chart to PNG (truecolor, grid + monthly detail + yearly trend).
 *
 * @param array<string,int> $d from chronology_build_month_counts()
 * @param string $outputPath absolute filesystem path
 */
function chronology_render_png(array $d, string $outputPath, int $yearMin, int $yearMax): bool {
	if (!function_exists('imagecreatetruecolor')) {
		error_log('[chronology] ERROR GD extension missing');
		return false;
	}

	if ($yearMin > $yearMax) {
		$t = $yearMin;
		$yearMin = $yearMax;
		$yearMax = $t;
		error_log('[chronology] WARN render swapped yearMin/yearMax');
	}

	$layout = chronology_chart_layout($yearMin, $yearMax);
	$canvasW = $layout['canvasW'];
	$hscr = $layout['hscr'];
	$xd = $layout['xd'];
	$yd = $layout['yd'];
	$wm = $layout['wm'];
	$wy = $layout['wy'];
	$hm = $layout['hm'];

	$im1 = imagecreatetruecolor($canvasW, $hscr);
	if ($im1 === false) {
		return false;
	}

	imagealphablending($im1, true);
	if (function_exists('imageantialias')) {
		imageantialias($im1, true);
	}

	$bg = imagecolorallocate($im1, 250, 248, 245);
	imagefilledrectangle($im1, 0, 0, $canvasW - 1, $hscr - 1, $bg);

	$gridMajor = imagecolorallocate($im1, 232, 226, 214);
	$gridMinor = imagecolorallocate($im1, 240, 236, 228);
	$axisLine = imagecolorallocate($im1, 184, 176, 160);
	$frameLine = imagecolorallocate($im1, 214, 208, 171);
	$textColor = imagecolorallocate($im1, 73, 60, 47);
	$monthLine = imagecolorallocate($im1, 160, 148, 135);
	$yearLine = imagecolorallocate($im1, 92, 74, 51);
	$yearLabel = imagecolorallocate($im1, 128, 0, 0);
	$pointOutline = imagecolorallocate($im1, 255, 255, 255);

	$ttf = chronology_resolve_ttf_font();
	$fontYear = 11.0;
	$fontTotal = 11.5;

	for ($g = 0; $g < ($hscr - 28); $g += 32) {
		imageline($im1, 0, $hscr - $yd - $g, $canvasW - 1, $hscr - $yd - $g, $gridMinor);
	}
	imageline($im1, 0, $hscr - $yd, $canvasW - 1, $hscr - $yd, $axisLine);
	imagerectangle($im1, 0, 0, $canvasW - 1, $hscr - 1, $frameLine);

	$ysm = [];
	for ($yy = $yearMin; $yy <= $yearMax; $yy++) {
		$ysm[$yy] = 0;
	}

	$off = 0;
	$last_x = 0.0;
	$last_y = 0.0;
	imagesetthickness($im1, 1);

	for ($y = $yearMin; $y <= $yearMax; $y++) {
		imageline(
			$im1,
			(int) ($xd + (($y - $yearMin) * $wy)),
			14,
			(int) ($xd + (($y - $yearMin) * $wy)),
			$hscr - 14,
			$gridMajor
		);
		$xYearCenter = $xd + (($y - $yearMin) * $wy) + $wy / 2;
		chronology_draw_label_centered($im1, $ttf, $fontYear, $xYearCenter, (float) ($hscr - $yd + 4), $textColor, (string) $y);

		for ($m = 1; $m <= 12; $m++) {
			$cnt = chronology_month_count($d, $y, $m);
			$nx = $xd + (($y - $yearMin) * $wy) + ($m - 1) * $wm;
			$ny = $hscr - ($yd + ($hm * $cnt));

			if ($off) {
				imageline($im1, (int) $last_x, (int) $last_y, (int) $nx, (int) $ny, $monthLine);
			}
			$off = 1;
			$last_x = $nx;
			$last_y = $ny;
			$ysm[$y] += $cnt;
		}
	}

	$last_x = 0.0;
	$last_y = 0.0;
	$off = 0;
	imagesetthickness($im1, 3);

	for ($y = $yearMin; $y <= $yearMax; $y++) {
		$m = 12;
		$xt = $xd + (($y - $yearMin) * $wy) + ($m - 1) * $wm - $wy / 2;
		$rawYt = $hscr - ($yd + (0.53 * $ysm[$y]));
		$yt = max(28.0, min((float) ($hscr - $yd - 6), $rawYt));

		if ($off) {
			imageline($im1, (int) $last_x, (int) $last_y, (int) $xt, (int) $yt, $yearLine);
			imagefilledellipse($im1, (int) $last_x, (int) $last_y, 8, 8, $yearLine);
			imagefilledellipse($im1, (int) $last_x, (int) $last_y, 4, 4, $pointOutline);
		}
		$label = (string) (int) $ysm[$y];
		chronology_draw_label_centered($im1, $ttf, $fontTotal, $xt, (float) ($yt - 22.0), $yearLabel, $label);

		$off = 1;
		$last_x = $xt;
		$last_y = $yt;
	}

	if ($off) {
		imagefilledellipse($im1, (int) $last_x, (int) $last_y, 8, 8, $yearLine);
		imagefilledellipse($im1, (int) $last_x, (int) $last_y, 4, 4, $pointOutline);
	}

	imagesetthickness($im1, 1);

	$ok = imagepng($im1, $outputPath);
	imagedestroy($im1);

	if ($ok) {
		error_log('[chronology] INFO chart PNG written path=' . $outputPath . ' yearMin=' . $yearMin . ' yearMax=' . $yearMax . ' canvas=' . $canvasW . 'x' . $hscr);
	}

	return (bool) $ok;
}

/**
 * @param mysqli $db
 * @param string $outputPath absolute path to zxpress_dinamic.png
 */
function generate_chronology_graph_png(mysqli $db, string $outputPath): bool {
	$d = chronology_build_month_counts($db);
	$bounds = chronology_year_bounds($db);

	return chronology_render_png($d, $outputPath, $bounds['min'], $bounds['max']);
}
