#!/usr/bin/env php
<?php
/**
 * Set Emulate (press 66) city to Kidderminster, England (UK).
 *
 *   php site/cli/set-emulate-city.php --dry-run
 *   php site/cli/set-emulate-city.php --apply
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$dryRun = true;
foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/cli/set-emulate-city.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-set-emulate-city';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

const EMULATE_PRESS_ID = 66;
const UK_COUNTRY_ID = 7;
const CITY_NAME_RU = 'Киддерминстер';
const CITY_NAME_EN = 'Kidderminster';
// Approx. centre of Kidderminster, Worcestershire, England
const CITY_LAT = 52.3885;
const CITY_LNG = -2.2490;

$z = db_select(
	$db,
	'SELECT id, name, name_eng, country_id FROM cities
		WHERE name_eng=? OR name=? LIMIT 1',
	'ss',
	CITY_NAME_EN,
	CITY_NAME_RU
);
$city = $z ? $z->fetch_assoc() : null;
$cityId = $city ? (int) $city['id'] : 0;

if ($cityId <= 0) {
	fwrite(STDOUT, "City missing — will insert " . CITY_NAME_EN . " (country_id=" . UK_COUNTRY_ID . ")\n");
	if (!$dryRun) {
		$ok = db_exec(
			$db,
			'INSERT INTO cities (name, name_eng, country_id, lat, lng) VALUES (?,?,?,?,?)',
			'ssidd',
			CITY_NAME_RU,
			CITY_NAME_EN,
			UK_COUNTRY_ID,
			CITY_LAT,
			CITY_LNG
		);
		if (!$ok) {
			fwrite(STDERR, "FAIL insert city\n");
			exit(1);
		}
		$cityId = (int) mysqli_insert_id($db);
		fwrite(STDOUT, "Inserted city id={$cityId}\n");
	}
} else {
	fwrite(
		STDOUT,
		"City exists id={$cityId} name={$city['name']} / {$city['name_eng']} country={$city['country_id']}\n"
	);
	if ((int) $city['country_id'] !== UK_COUNTRY_ID && !$dryRun) {
		db_exec($db, 'UPDATE cities SET country_id=? WHERE id=? LIMIT 1', 'ii', UK_COUNTRY_ID, $cityId);
	}
}

$press = db_select($db, 'SELECT id, title, city FROM press WHERE id=? LIMIT 1', 'i', EMULATE_PRESS_ID);
$pressRow = $press ? $press->fetch_assoc() : null;
if (!$pressRow) {
	fwrite(STDERR, "Press " . EMULATE_PRESS_ID . " not found\n");
	exit(1);
}
fwrite(STDOUT, "Press {$pressRow['title']} city={$pressRow['city']} -> {$cityId}\n");

if (!$dryRun && $cityId > 0) {
	db_exec($db, 'UPDATE press SET city=? WHERE id=? LIMIT 1', 'ii', $cityId, EMULATE_PRESS_ID);

	if (ezines_v2_ready($db)) {
		$zPress = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', EMULATE_PRESS_ID);
		$full = $zPress ? $zPress->fetch_assoc() : null;
		if (is_array($full)) {
			ezines_v2_sync_press($db, $full, 'en');
		}
	}
}

fwrite(STDOUT, ($dryRun ? 'DRY' : 'APPLY') . " done city_id={$cityId}\n");
exit(0);
