<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function ezines_ui_is_new(): bool
{
	return defined('EZINES_UI_VARIANT') && EZINES_UI_VARIANT === 'new';
}

function ezines_ui_template(): string
{
	return ezines_ui_is_new() ? 'ezines_new.tpl' : 'ezines.tpl';
}

function ezines_public_type_label(int $type, bool $isEng): string
{
	if ($type === 1) {
		return $isEng ? 'magazine' : 'журнал';
	}
	if ($type === 2) {
		return $isEng ? 'report' : 'репортаж';
	}
	return $isEng ? 'newspaper' : 'газета';
}

function ezines_public_years_label(int $from, int $to): string
{
	if ($from !== 1970 && $to !== 1970 && $from !== $to) {
		return $from . '-' . $to;
	}
	if ($from !== 1970) {
		return (string) $from;
	}
	if ($to !== 1970) {
		return (string) $to;
	}
	return '';
}

function ezines_ui_render($smarty, bool $isEng): void
{
	$catalogUrl = ezines_ui_is_new() ? ezn_url_catalog_new($isEng) : ezn_url_catalog($isEng);
	$classicUrl = ezn_url_catalog($isEng);
	$smarty->assign('ezines_catalog_url', $catalogUrl);
	$smarty->assign('ezines_classic_url', $classicUrl);
	$smarty->assign('letters_catalog_url', ezn_path_prefix($isEng) . '/snailmail-new');
	$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
	$smarty->assign('smn_nav_authors_active', false);
	$smarty->assign('smn_nav_ezines_active', true);
	$smarty->assign('url_rus', htmlspecialchars(ezines_ui_is_new() ? ezn_url_catalog_new(false) : ezn_url_catalog(false), ENT_QUOTES, 'UTF-8'));
	$smarty->assign('url_eng', htmlspecialchars(ezines_ui_is_new() ? ezn_url_catalog_new(true) : ezn_url_catalog(true), ENT_QUOTES, 'UTF-8'));

	if (!ezines_ui_is_new()) {
		global $db;
		include __DIR__ . '/right.php';
	}
	$smarty->display(ezines_ui_template());
}

$smarty->assign('a', htmlspecialchars($_GET['x'] ?? '', ENT_QUOTES, 'UTF-8'));

$lng = $smarty->getTemplateVars('lng');
$isEng = ezn_is_eng(is_string($lng) ? $lng : null);

$z = db_select($db, 'SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id ORDER BY title ASC');

$f = 0;
$n = 0;
$a = '#';
$c = [];
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['title_plain'] = title_plain($t['title'] ?? '');
	$t['public_url'] = ezn_url_press($t, $isEng);
	$s = strtoupper(mb_substr($t['title_plain'], 0, 1, 'UTF-8'));
	$isAlphaLetter = ($s < '0' || $s > '9');
	if ($isAlphaLetter && (!$f || $a !== $s)) {
		if ($f && $a !== $s) {
			$spacer = $t;
			$spacer['off'] = 0;
			$spacer['letter'] = '';
			$c[$n] = $spacer;
			$n++;
		}
		$a = $s;
		$t['letter'] = $a;
	} else {
		$t['letter'] = '';
	}

	$t['off'] = 1;
	$t['years_to'] = date('Y', $t['years_to']);
	$t['years_from'] = date('Y', $t['years_from']);
	$t['flag'] = $country_id[$t['country_id']] ?? '';
	$numbers = (int) ($t['numbers'] ?? 0);
	$onlineIssues = (int) ($t['online_issues'] ?? 0);
	$t['finish'] = $numbers > 0 ? (int) ((100 / $numbers) * $onlineIssues) : 0;
	$t['type_label'] = ezines_public_type_label((int) ($t['type'] ?? 0), $isEng);
	$t['years_label'] = ezines_public_years_label((int) $t['years_from'], (int) $t['years_to']);
	$t['issues_count'] = $numbers;
	$city = trim((string) ($t['name'] ?? ''));
	$t['city_label'] = $city;

	$c[$n] = $t;
	$n++;
	$f = 1;
}
$smarty->assign('catalog', $c);

$origin = function_exists('zxpress_canonical_origin') ? zxpress_canonical_origin() : 'https://zxpress.ru';
if ($isEng) {
	$title = 'Library of electronic newspapers and magazines for ZX Spectrum';
	$desc = 'This section is devoted to electronic newspapers and magazines published by the ZX Spectrum user community from the early 1990s through the 2020s.';
} else {
	$title = 'Библиотека электронных газет и журналов для ZX Spectrum';
	$desc = 'Этот раздел посвящён электронным газетам и журналам, выпускавшимся сообществом пользователей ZX Spectrum с начала 1990-х до 2020-х годов.';
}
$smarty->assign('title', $title);
$smarty->assign('description', $desc);
$smarty->assign('og_title', $title);
$smarty->assign('og_description', $desc);
$smarty->assign('og_type', 'website');
$smarty->assign('og_url', $origin . (ezines_ui_is_new() ? ezn_url_catalog_new($isEng) : ezn_url_catalog($isEng)));
$smarty->assign('og_image', $origin . '/img/banner.png');

ezines_ui_render($smarty, $isEng);
