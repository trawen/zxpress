<?php
/**
 * Activity feed from universal activity / activity_batch tables.
 * URL: /ru/updates-activity  (also /updates_activity.php)
 */
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/books_public.php';

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

/**
 * Object the batch belongs to: explicit root, shared parent of all events, or the single event itself.
 *
 * @param array<string,mixed> $batch
 * @return array{0:string,1:int}
 */
function activity_feed_batch_root_ref(array $batch): array
{
	$rootType = (string) ($batch['root_type'] ?? '');
	$rootId = (int) ($batch['root_id'] ?? 0);
	if ($rootType !== '' && $rootId > 0) {
		return [$rootType, $rootId];
	}

	$events = $batch['events'] ?? [];
	if ($events === []) {
		return ['', 0];
	}

	$parents = [];
	foreach ($events as $e) {
		$parents[(string) ($e['parent_type'] ?? '') . ':' . (int) ($e['parent_id'] ?? 0)] = [
			(string) ($e['parent_type'] ?? ''),
			(int) ($e['parent_id'] ?? 0),
		];
	}
	if (count($parents) === 1) {
		$parent = reset($parents);
		if ($parent[0] !== '' && $parent[1] > 0) {
			return $parent;
		}
	}

	if (count($events) === 1) {
		$only = $events[0];
		return [(string) ($only['object_type'] ?? ''), (int) ($only['object_id'] ?? 0)];
	}

	return ['', 0];
}

/**
 * Titles + public urls for batch parents (press / issue / book), keyed by "type:id".
 *
 * @param list<array{0:string,1:int}> $refs
 * @return array<string,array{title:string,url:string}>
 */
function activity_feed_resolve_roots(mysqli $db, array $refs, bool $isEng): array
{
	$out = [];
	$byType = [];
	foreach ($refs as [$type, $id]) {
		if ($id > 0 && $type !== '') {
			$byType[$type][$id] = $id;
		}
	}

	if (!empty($byType['press'])) {
		$ids = implode(',', array_map('intval', $byType['press']));
		$z = $db->query("SELECT id, title, slug_ru, slug_en FROM press WHERE id IN ($ids)");
		while ($z && ($row = $z->fetch_assoc())) {
			$out['press:' . (int) $row['id']] = [
				'title' => title_plain((string) ($row['title'] ?? '')),
				'url' => ezn_url_press($row, $isEng),
			];
		}
	}

	if (!empty($byType['issue'])) {
		$ids = implode(',', array_map('intval', $byType['issue']));
		$z = $db->query(
			'SELECT i.id, i.title, i.slug_ru, i.slug_en, p.id AS press_id, p.title AS press_title, '
			. 'p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en '
			. "FROM issue i LEFT JOIN press p ON p.id=i.id_press WHERE i.id IN ($ids)"
		);
		while ($z && ($row = $z->fetch_assoc())) {
			$press = [
				'id' => (int) ($row['press_id'] ?? 0),
				'slug_ru' => (string) ($row['press_slug_ru'] ?? ''),
				'slug_en' => (string) ($row['press_slug_en'] ?? ''),
			];
			$pressTitle = title_plain((string) ($row['press_title'] ?? ''));
			$issueTitle = title_plain((string) ($row['title'] ?? ''));
			$label = $pressTitle !== '' ? $pressTitle : $issueTitle;
			if ($pressTitle !== '' && $issueTitle !== '') {
				$label = $pressTitle . ' · #' . $issueTitle;
			}
			$out['issue:' . (int) $row['id']] = [
				'title' => $label,
				'url' => $press['id'] > 0 ? ezn_url_issue($press, $row, $isEng) : ('/issue.php?id=' . (int) $row['id']),
			];
		}
	}

	if (!empty($byType['book'])) {
		$ids = implode(',', array_map('intval', $byType['book']));
		$z = $db->query("SELECT id, title1 FROM books WHERE id IN ($ids)");
		while ($z && ($row = $z->fetch_assoc())) {
			$out['book:' . (int) $row['id']] = [
				'title' => title_plain((string) ($row['title1'] ?? '')),
				'url' => books_url_book((int) $row['id'], $isEng),
			];
		}
	}

	return $out;
}

/**
 * Public issue urls keyed by issue id.
 *
 * @param list<int> $ids
 * @return array<int,string>
 */
function activity_feed_resolve_issue_urls(mysqli $db, array $ids, bool $isEng): array
{
	$out = [];
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	if ($ids === []) {
		return $out;
	}

	$idList = implode(',', $ids);
	$z = $db->query(
		'SELECT i.id, i.slug_ru, i.slug_en, p.id AS press_id, p.slug_ru AS press_slug_ru, p.slug_en AS press_slug_en '
		. "FROM issue i LEFT JOIN press p ON p.id=i.id_press WHERE i.id IN ($idList)"
	);
	while ($z && ($row = $z->fetch_assoc())) {
		$press = [
			'id' => (int) ($row['press_id'] ?? 0),
			'slug_ru' => (string) ($row['press_slug_ru'] ?? ''),
			'slug_en' => (string) ($row['press_slug_en'] ?? ''),
		];
		$out[(int) $row['id']] = $press['id'] > 0
			? ezn_url_issue($press, $row, $isEng)
			: ('/issue.php?id=' . (int) $row['id']);
	}

	return $out;
}

function activity_feed_time_bucket_5m(int $ts): int
{
	if ($ts <= 0) {
		return 0;
	}

	return (int) (floor($ts / 300) * 300);
}

/**
 * Prefer screenshots/scans first, keep original order внутри группы.
 *
 * @param list<array<string,mixed>> $events
 * @return list<array<string,mixed>>
 */
function activity_feed_group_events(array $events): array
{
	$visual = [];
	$other = [];
	foreach ($events as $e) {
		$type = (string) ($e['object_type'] ?? '');
		if (!empty($e['thumb_url']) || $type === 'screen' || $type === 'illustration' || $type === 'periodical_issue_image') {
			$visual[] = $e;
		} else {
			$other[] = $e;
		}
	}

	return array_merge($visual, $other);
}

/**
 * Merge adjacent feed batches by edition title + 5-minute bucket.
 *
 * @param list<array<string,mixed>> $batches
 * @return list<array<string,mixed>>
 */
function activity_feed_merge_batches(array $batches, bool $isEng): array
{
	$merged = [];
	foreach ($batches as $b) {
		$titleKey = trim((string) ($b['title_press'] ?? $b['title_display'] ?? ''));
		$bucket = activity_feed_time_bucket_5m((int) ($b['created_at'] ?? 0));
		$domain = (string) ($b['domain'] ?? '');
		$isPublic = (int) ($b['is_public'] ?? 0);

		$canMerge = $titleKey !== '' && $bucket > 0 && $merged !== [];
		if ($canMerge) {
			$lastIndex = count($merged) - 1;
			$prev = $merged[$lastIndex];
			if (
				(string) ($prev['_merge_title'] ?? '') === $titleKey
				&& (int) ($prev['_merge_bucket'] ?? 0) === $bucket
				&& (string) ($prev['domain'] ?? '') === $domain
			) {
				$prevEvents = $prev['events'] ?? [];
				$curEvents = $b['events'] ?? [];
				$prev['events'] = activity_feed_group_events(array_merge($prevEvents, $curEvents));
				$prev['is_public'] = $isPublic && (int) ($prev['is_public'] ?? 0) ? 1 : 0;
				$prev['public_items_count'] = count($prev['events']);

				$present = activity_feed_present_batch($prev, $prev['events'], $isEng, [
					'title' => (string) ($prev['title_press'] ?? ''),
					'url' => (string) ($prev['url_display'] ?? ''),
				]);
				$prev['title_display'] = $present['title'];
				$prev['title_press'] = $present['title_press'];
				$prev['title_suffix'] = $present['title_suffix'];
				$prev['summary_display'] = $present['summary'];
				$prev['details_label'] = $present['details_label'];
				$prev['is_compact'] = $present['is_compact'] ? 1 : 0;

				$merged[$lastIndex] = $prev;
				continue;
			}
		}

		$b['_merge_title'] = $titleKey;
		$b['_merge_bucket'] = $bucket;
		$b['events'] = activity_feed_group_events($b['events'] ?? []);
		$merged[] = $b;
	}

	$lastDateKey = null;
	$lastTimeLabel = null;
	$lastDomainLabel = null;
	$groupIndex = 0;
	foreach ($merged as &$b) {
		$created = (int) ($b['created_at'] ?? 0);
		$dateKey = date('Y-m-d', $created);
		$dateLabel = $isEng
			? date('j F', $created)
			: (date('d ', $created) . ($GLOBALS['months'][date('m', $created)] ?? ''));
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
		unset($b['_merge_title'], $b['_merge_bucket']);
	}
	unset($b);

	return $merged;
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
			if (!$showAll && !(int) ($e['is_public'] ?? 0)) {
				continue;
			}
			$e['object_label'] = activity_object_label((string) $e['object_type'], $isEng);
			$titleEn = trim((string) ($e['title_en'] ?? ''));
			$rawTitle = ($isEng && $titleEn !== '') ? $titleEn : (string) ($e['title_ru'] ?? '');
			$e['title_display'] = title_plain($rawTitle);
			if ((string) ($e['object_type'] ?? '') === 'custom_update') {
				$e['title_html'] = activity_custom_update_markdown_html($rawTitle);
				$e['is_custom_update'] = 1;
			}
			$urlEn = trim((string) ($e['url_en'] ?? ''));
			$urlRu = trim((string) ($e['url_ru'] ?? ''));
			$e['url_display'] = ($isEng && $urlEn !== '') ? $urlEn : $urlRu;
			$events[] = $e;
		}
		$b['events'] = $events;

		$titleEn = trim((string) ($b['title_en'] ?? ''));
		$b['title_display'] = title_plain(
			($isEng && $titleEn !== '') ? $titleEn : (string) ($b['title_ru'] ?? '')
		);
		$sumEn = trim((string) ($b['summary_en'] ?? ''));
		$b['summary_display'] = title_plain(
			($isEng && $sumEn !== '') ? $sumEn : (string) ($b['summary_ru'] ?? '')
		);
		$urlEn = trim((string) ($b['url_en'] ?? ''));
		$urlRu = trim((string) ($b['url_ru'] ?? ''));
		$b['url_display'] = ($isEng && $urlEn !== '') ? $urlEn : $urlRu;

		$batches[] = $b;
	}

	$issueUrlIds = [];
	foreach ($batches as $b) {
		foreach (($b['events'] ?? []) as $e) {
			if ((string) ($e['object_type'] ?? '') === 'issue' && (int) ($e['object_id'] ?? 0) > 0) {
				$issueUrlIds[] = (int) $e['object_id'];
			}
			if ((string) ($e['parent_type'] ?? '') === 'issue' && (int) ($e['parent_id'] ?? 0) > 0) {
				$issueUrlIds[] = (int) $e['parent_id'];
			}
		}
	}
	$issueUrls = activity_feed_resolve_issue_urls($db, $issueUrlIds, $isEng);

	$rootRefs = [];
	foreach ($batches as $b) {
		$rootRefs[] = activity_feed_batch_root_ref($b);
	}
	$roots = activity_feed_resolve_roots($db, $rootRefs, $isEng);

	foreach ($batches as &$b) {
		foreach ($b['events'] as &$e) {
			$eventIssueId = 0;
			if ((string) ($e['object_type'] ?? '') === 'issue') {
				$eventIssueId = (int) ($e['object_id'] ?? 0);
			} elseif ((string) ($e['parent_type'] ?? '') === 'issue') {
				$eventIssueId = (int) ($e['parent_id'] ?? 0);
			}
			if ($eventIssueId > 0 && !empty($issueUrls[$eventIssueId])) {
				$e['url_display'] = $issueUrls[$eventIssueId];
			}
		}
		unset($e);

		[$rootType, $rootId] = activity_feed_batch_root_ref($b);
		$root = $roots[$rootType . ':' . $rootId] ?? [];

		$present = activity_feed_present_batch($b, $b['events'], $isEng, $root);
		$b['title_display'] = $present['title'];
		$b['title_press'] = $present['title_press'];
		$b['title_suffix'] = $present['title_suffix'];
		$b['summary_display'] = $present['summary'];
		$b['details_label'] = $present['details_label'];
		$b['is_compact'] = $present['is_compact'] ? 1 : 0;
		if ($present['url_press'] !== '') {
			$b['url_display'] = $present['url_press'];
		}
	}
	unset($b);

	$batches = activity_feed_merge_batches($batches, $isEng);
	foreach ($batches as &$b) {
		activity_feed_apply_custom_update_batch($b, $isEng);
	}
	unset($b);
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
