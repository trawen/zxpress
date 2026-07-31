<?php
/**
 * Test page: activity feed from universal activity / activity_batch tables.
 * URL: /ru/updates-activity  (also /updates_activity.php)
 */
require 'init.inc';

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
	while ($z && ($b = $z->fetch_assoc())) {
		$bid = (int) $b['id'];
		$events = [];
		$ze = db_select(
			$db,
			'SELECT * FROM activity WHERE batch_id=? ORDER BY id ASC',
			'i',
			$bid
		);
		while ($ze && ($e = $ze->fetch_assoc())) {
			$e['object_label'] = activity_object_label((string) $e['object_type'], $isEng);
			$events[] = $e;
		}
		$b['events'] = $events;
		$b['date_label'] = $isEng
			? date('j F Y H:i', (int) $b['created_at'])
			: (date('d ', (int) $b['created_at'])
				. ($months[date('m', (int) $b['created_at'])] ?? '')
				. date(' Y H:i', (int) $b['created_at']));
		$batches[] = $b;
	}
}

$domains = [];
if ($ready) {
	$zd = db_select($db, 'SELECT domain, COUNT(*) AS c FROM activity_batch GROUP BY domain ORDER BY c DESC');
	while ($zd && ($d = $zd->fetch_assoc())) {
		$domains[] = $d;
	}
}

$prefix = $isEng ? '/en' : '/ru';
$baseUrl = $prefix . '/updates-activity';
$qs = [];
if ($showAll) {
	$qs['all'] = '1';
}
if ($domain !== '') {
	$qs['domain'] = $domain;
}

$smarty->assign('activity_ready', $ready ? 1 : 0);
$smarty->assign('activity_batches', $batches);
$smarty->assign('activity_total', $total);
$smarty->assign('activity_page', $page);
$smarty->assign('activity_total_pages', $totalPages);
$smarty->assign('activity_show_all', $showAll ? 1 : 0);
$smarty->assign('activity_domain', $domain);
$smarty->assign('activity_domains', $domains);
$smarty->assign('activity_base_url', $baseUrl);
$smarty->assign('activity_qs', $qs);
$smarty->assign('title', $isEng ? 'Activity feed (test)' : 'Лента activity (тест)');
$smarty->assign('description', $isEng
	? 'Test page for universal activity_batch / activity tables'
	: 'Тестовая страница универсальных таблиц activity_batch / activity');
$smarty->assign('url_rus', htmlspecialchars('/ru/updates-activity', ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars('/en/updates-activity', ENT_QUOTES, 'UTF-8'));

$smarty->display('updates_activity.tpl');
