<?php
/**
 * Activity feed from universal activity / activity_batch tables.
 * URL: /ru/updates-activity  (also /updates_activity.php)
 */
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function activity_page_url(bool $isEng, int $page, bool $showAll, string $domain): string
{
	$base = ($isEng ? '/en' : '/ru') . '/updates-activity';
	$qs = [];
	if ($showAll) {
		$qs['all'] = '1';
	}
	if ($domain !== '') {
		$qs['domain'] = $domain;
	}
	if ($page > 1) {
		$qs['page'] = $page;
	}
	if ($qs === []) {
		return $base;
	}
	return $base . '?' . http_build_query($qs);
}

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$showAll = !empty($_GET['all']);
$domain = trim((string) ($_GET['domain'] ?? ''));

$ready = activity_tables_ready($db);
$batches = [];
$total = 0;
$totalPages = 1;

if ($ready) {
	$where = [];
	$types = '';
	$params = [];
	if (!$showAll) {
		$where[] = 'b.is_public=1';
		$where[] = 'b.public_items_count>0';
	}
	if ($domain !== '') {
		$where[] = 'b.domain=?';
		$types .= 's';
		$params[] = $domain;
	}
	$whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

	$zCnt = db_select($db, "SELECT COUNT(*) FROM activity_batch b $whereSql", $types, ...$params);
	$total = (int) (($zCnt && ($r = $zCnt->fetch_row())) ? $r[0] : 0);
	$totalPages = max(1, (int) ceil($total / $perPage));
	if ($page > $totalPages) {
		$page = $totalPages;
	}
	$from = ($page - 1) * $perPage;

	$z = db_select(
		$db,
		"SELECT b.* FROM activity_batch b $whereSql ORDER BY b.created_at DESC, b.id DESC LIMIT ?, ?",
		$types . 'ii',
		...array_merge($params, [$from, $perPage])
	);
	$lastDateKey = null;
	$groupIndex = 0;
	$lastTimeLabel = null;
	$lastDomainLabel = null;
	while ($z && ($b = $z->fetch_assoc())) {
		$bid = (int) $b['id'];
		$created = (int) $b['created_at'];
		$events = [];
		$ze = db_select(
			$db,
			'SELECT * FROM activity WHERE batch_id=? ORDER BY id ASC',
			'i',
			$bid
		);
		while ($ze && ($e = $ze->fetch_assoc())) {
			if (!$showAll && !(int) ($e['is_public'] ?? 0)) {
				continue;
			}
			$e['object_label'] = activity_object_label((string) $e['object_type'], $isEng);
			$titleEn = trim((string) ($e['title_en'] ?? ''));
			$e['title_display'] = ($isEng && $titleEn !== '')
				? $titleEn
				: (string) ($e['title_ru'] ?? '');
			$urlEn = trim((string) ($e['url_en'] ?? ''));
			$urlRu = trim((string) ($e['url_ru'] ?? ''));
			$e['url_display'] = ($isEng && $urlEn !== '') ? $urlEn : $urlRu;
			$events[] = $e;
		}
		$b['events'] = $events;

		$dateKey = date('Y-m-d', $created);
		$dateLabel = $isEng
			? date('j F', $created)
			: (date('d ', $created) . ($months[date('m', $created)] ?? ''));
		$timeLabel = date('H:i', $created);
		$domainLabel = activity_domain_label((string) ($b['domain'] ?? ''), $isEng);
		if ($lastDateKey !== $dateKey) {
			$b['date'] = $dateLabel;
			$b['show_rule'] = $groupIndex > 0 ? 1 : 0;
			$lastDateKey = $dateKey;
			$lastTimeLabel = null;
			$lastDomainLabel = null;
			$groupIndex++;
		} else {
			$b['date'] = '';
			$b['show_rule'] = 0;
		}
		if ($lastTimeLabel !== $timeLabel) {
			$b['time_label'] = $timeLabel;
			$lastTimeLabel = $timeLabel;
		} else {
			$b['time_label'] = '';
		}
		if ($lastDomainLabel !== $domainLabel) {
			$b['domain_label'] = $domainLabel;
			$lastDomainLabel = $domainLabel;
		} else {
			$b['domain_label'] = '';
		}

		$titleEn = trim((string) ($b['title_en'] ?? ''));
		$b['title_display'] = ($isEng && $titleEn !== '')
			? $titleEn
			: (string) ($b['title_ru'] ?? '');
		$sumEn = trim((string) ($b['summary_en'] ?? ''));
		$b['summary_display'] = ($isEng && $sumEn !== '')
			? $sumEn
			: (string) ($b['summary_ru'] ?? '');
		$urlEn = trim((string) ($b['url_en'] ?? ''));
		$urlRu = trim((string) ($b['url_ru'] ?? ''));
		$b['url_display'] = ($isEng && $urlEn !== '') ? $urlEn : $urlRu;

		$present = activity_feed_present_batch($b, $events, $isEng);
		$b['title_display'] = $present['title'];
		$b['title_press'] = $present['title_press'];
		$b['title_suffix'] = $present['title_suffix'];
		$b['summary_display'] = $present['summary'];
		$b['details_label'] = $present['details_label'];
		$b['is_screens_batch'] = $present['is_screens'] ? 1 : 0;
		$batches[] = $b;
	}
}

$domains = [];
if ($ready) {
	$zd = db_select($db, 'SELECT domain, COUNT(*) AS c FROM activity_batch GROUP BY domain ORDER BY c DESC');
	while ($zd && ($d = $zd->fetch_assoc())) {
		$d['label'] = activity_domain_label((string) ($d['domain'] ?? ''), $isEng);
		$domains[] = $d;
	}
}

$catalogUrl = activity_page_url($isEng, 1, $showAll, $domain);
$pagesBaseUrl = activity_page_url($isEng, 1, $showAll, $domain);

$smarty->assign('updates_catalog_url', ezn_path_prefix($isEng) . '/updates-activity');
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', false);
$smarty->assign('smn_nav_guestbook_active', false);
$smarty->assign('smn_nav_updates_active', true);

$smarty->assign('activity_ready', $ready ? 1 : 0);
$smarty->assign('activity_batches', $batches);
$smarty->assign('activity_total', $total);
$smarty->assign('activity_page', $page);
$smarty->assign('activity_total_pages', $totalPages);
$smarty->assign('activity_show_all', $showAll ? 1 : 0);
$smarty->assign('activity_domain', $domain);
$smarty->assign('activity_domains', $domains);
$smarty->assign('activity_base_url', ($isEng ? '/en' : '/ru') . '/updates-activity');
$smarty->assign('activity_catalog_url', $catalogUrl);
$smarty->assign('activity_pages_base_url', $pagesBaseUrl);
$smarty->assign('activity_page_join', strpos($pagesBaseUrl, '?') !== false ? '&' : '?');
$smarty->assign('tk_page', $page);
$smarty->assign('updates_total_pages', $totalPages);

$smarty->assign('title', $isEng ? 'Activity feed' : 'Лента обновлений');
$smarty->assign(
	'description',
	$isEng
		? 'Recent site activity: uploads, edits and publications'
		: 'Недавняя активность на сайте: загрузки, правки и публикации'
);
$smarty->assign('url_rus', htmlspecialchars(activity_page_url(false, $page, $showAll, $domain), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(activity_page_url(true, $page, $showAll, $domain), ENT_QUOTES, 'UTF-8'));

$smarty->display('updates_activity.tpl');
