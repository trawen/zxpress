<?php
/**
 * Universal activity / updates log.
 *
 * Tables: activity_batch, activity
 * Public feed: is_public=1 AND event_scope='content' (usually via batches).
 */

require_once __DIR__ . '/screen_images.php';

const ACTIVITY_SCOPE_CONTENT = 'content';
const ACTIVITY_SCOPE_METADATA = 'metadata';
const ACTIVITY_SCOPE_SYSTEM = 'system';

/** @var array{batch_id:int,domain:string,root_type:string,root_id:int,source:string}|null */
$GLOBALS['__activity_request'] = $GLOBALS['__activity_request'] ?? null;

function activity_tables_ready(mysqli $db): bool
{
	static $ready = null;
	if ($ready !== null) {
		return $ready;
	}
	$r = @$db->query("SHOW TABLES LIKE 'activity'");
	$ready = ($r && $r->num_rows > 0);
	return $ready;
}

function activity_json($data): ?string
{
	if ($data === null) {
		return null;
	}
	if (is_string($data)) {
		return $data;
	}
	$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	return $json === false ? null : $json;
}

function activity_actor_user_id(): int
{
	return (int) ($_SESSION['id_username'] ?? 0);
}

function activity_detect_domain(): string
{
	$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
	$map = [
		'admin_periodical_articles.php' => 'periodical',
		'admin_periodicals.php' => 'periodical',
		'admin_publications.php' => 'publication',
		'admin_pub_articles.php' => 'publication',
		'admin_books.php' => 'book',
		'admin_books_light.php' => 'book',
		'admin_book_rubrics.php' => 'book',
		'admin_letters.php' => 'letter',
		'admin_news.php' => 'news',
		'admin_news_upload.php' => 'news',
		'admin_activity_custom.php' => 'custom',
		'admin_authors.php' => 'author',
		'admin_publishers.php' => 'publisher',
		'admin_ezine_categories.php' => 'ezine',
		'admin_screens.php' => 'ezine',
		'admin_issue_emulator.php' => 'ezine',
		'admin_articles.php' => 'ezine',
		'admin_articles_new.php' => 'ezine',
		'admin_issue.php' => 'ezine',
		'gallery_admin.php' => 'gallery',
	];
	return $map[$script] ?? 'ezine';
}

/**
 * @return array{0:string,1:int}
 */
function activity_detect_root(): array
{
	$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
	$req = array_merge($_GET ?? [], $_POST ?? []);

	if (str_contains($script, 'periodical_articles')) {
		return ['periodical_issue', (int) ($req['issue_id'] ?? 0)];
	}
	if (str_contains($script, 'periodical')) {
		return ['periodical', (int) ($req['id'] ?? 0)];
	}
	if ($script === 'admin_pub_articles.php' || str_contains($script, 'publication')) {
		return ['publication', (int) ($req['publication_id'] ?? $req['id'] ?? 0)];
	}
	if (str_contains($script, 'book')) {
		return ['book', (int) ($req['id'] ?? 0)];
	}
	if (str_contains($script, 'letter')) {
		return ['letter', (int) ($req['id'] ?? 0)];
	}
	if (str_contains($script, 'news')) {
		return ['news', (int) ($req['id'] ?? 0)];
	}
	$issue = (int) ($req['issue'] ?? $req['issue_id'] ?? 0);
	if ($issue > 0) {
		return ['issue', $issue];
	}
	$press = (int) ($req['id'] ?? $req['press_id'] ?? 0);
	return $press > 0 ? ['press', $press] : ['', 0];
}

function activity_current_batch_id(): int
{
	$ctx = $GLOBALS['__activity_request'] ?? null;
	return is_array($ctx) ? (int) ($ctx['batch_id'] ?? 0) : 0;
}

function activity_batch_begin(
	mysqli $db,
	string $domain = '',
	string $rootType = '',
	int $rootId = 0,
	string $source = '',
	int $actorUserId = 0
): int {
	if (!activity_tables_ready($db)) {
		return 0;
	}
	if ($domain === '') {
		$domain = activity_detect_domain();
	}
	if ($rootType === '' && $rootId <= 0) {
		[$rootType, $rootId] = activity_detect_root();
	}
	if ($actorUserId <= 0) {
		$actorUserId = activity_actor_user_id();
	}
	if ($source === '') {
		$source = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
	}

	$now = time();
	$stmt = $db->prepare(
		'INSERT INTO activity_batch (created_at, actor_user_id, domain, root_type, root_id, is_public, source) '
		. 'VALUES (?, ?, ?, ?, ?, 1, ?)'
	);
	if (!$stmt) {
		return 0;
	}
	$stmt->bind_param('iissis', $now, $actorUserId, $domain, $rootType, $rootId, $source);
	if (!$stmt->execute()) {
		$stmt->close();
		return 0;
	}
	$batchId = (int) $db->insert_id;
	$stmt->close();

	$GLOBALS['__activity_request'] = [
		'batch_id' => $batchId,
		'domain' => $domain,
		'root_type' => $rootType,
		'root_id' => $rootId,
		'source' => $source,
	];
	// Used by trg_log_ai_activity to attach legacy log rows to this batch.
	$db->query('SET @activity_batch_id := ' . (int) $batchId);
	return $batchId;
}

/**
 * Attach activity rows created without batch_id (e.g. trigger missed @activity_batch_id).
 */
function activity_attach_orphan_events_to_batch(
	mysqli $db,
	int $batchId,
	int $sinceTs,
	int $actorUserId
): void {
	if ($batchId <= 0 || $sinceTs <= 0 || $actorUserId <= 0) {
		return;
	}

	db_exec(
		$db,
		'UPDATE activity a '
		. 'INNER JOIN log l ON l.id = a.legacy_log_id '
		. 'SET a.batch_id = ? '
		. 'WHERE a.batch_id IS NULL AND l.id_user = ? AND l.date >= ?',
		'iii',
		$batchId,
		$actorUserId,
		$sinceTs
	);
}

/**
 * Mirror any legacy `log` rows from this request that the SQL trigger missed.
 */
function activity_mirror_pending_logs(mysqli $db, int $sinceTs = 0, int $actorUserId = 0): void
{
	if (!activity_tables_ready($db)) {
		return;
	}
	if ($actorUserId <= 0) {
		$actorUserId = activity_actor_user_id();
	}
	if ($sinceTs <= 0) {
		$sinceTs = time() - 3600;
	}
	if ($actorUserId <= 0) {
		return;
	}

	$z = db_select(
		$db,
		'SELECT l.* FROM log l '
		. 'LEFT JOIN activity a ON a.legacy_log_id=l.id '
		. 'WHERE l.id_user=? AND l.date>=? AND a.id IS NULL '
		. 'ORDER BY l.id ASC LIMIT 200',
		'ii',
		$actorUserId,
		$sinceTs
	);
	while ($z && ($row = $z->fetch_assoc())) {
		activity_log_from_legacy(
			$db,
			(int) ($row['type'] ?? 0),
			(int) ($row['id_press'] ?? 0),
			(int) ($row['id_article'] ?? 0),
			(int) ($row['id_issue'] ?? 0),
			(int) ($row['id_user'] ?? 0),
			(int) ($row['date'] ?? time()),
			(int) ($row['id_screen'] ?? 0),
			(int) ($row['id_cover'] ?? 0),
			(int) ($row['id'] ?? 0)
		);
	}
}

/**
 * Resolve human title / url / thumb for an activity object.
 *
 * @return array{title_ru:string,title_en:string,url_ru:string,url_en:string,thumb_url:?string}
 */
function activity_resolve_object_display(
	mysqli $db,
	string $objectType,
	int $objectId,
	?string $parentType = null,
	?int $parentId = null,
	?array $meta = null
): array {
	$out = [
		'title_ru' => '',
		'title_en' => '',
		'url_ru' => '',
		'url_en' => '',
		'thumb_url' => null,
	];
	$meta = is_array($meta) ? $meta : [];

	if ($objectType === 'screen' || $objectType === 'illustration') {
		if ($objectId <= 0) {
			return $out;
		}
		$z = db_select(
			$db,
			'SELECT s.id, s.format, s.id_press, s.id_issue, p.title AS press_title, i.title AS issue_title '
			. 'FROM screens s '
			. 'LEFT JOIN press p ON p.id=s.id_press '
			. 'LEFT JOIN issue i ON i.id=s.id_issue '
			. 'WHERE s.id=? LIMIT 1',
			'i',
			$objectId
		);
		$row = $z ? $z->fetch_assoc() : null;
		if (!$row && !empty($meta['id_press'])) {
			$pressId = (int) $meta['id_press'];
			$issueId = (int) ($meta['id_issue'] ?? 0);
			$z = db_select($db, 'SELECT title FROM press WHERE id=? LIMIT 1', 'i', $pressId);
			$pressTitle = ($z && ($p = $z->fetch_assoc())) ? title_plain((string) ($p['title'] ?? '')) : '';
			$issueTitle = '';
			if ($issueId > 0) {
				$z = db_select($db, 'SELECT title FROM issue WHERE id=? LIMIT 1', 'i', $issueId);
				$issueTitle = ($z && ($i = $z->fetch_assoc())) ? title_plain((string) ($i['title'] ?? '')) : '';
			}
			$label = $pressTitle !== '' ? $pressTitle : ('Скриншот #' . $objectId);
			if ($issueTitle !== '') {
				$label .= ' · #' . $issueTitle;
			}
			$out['title_ru'] = $label;
			$out['title_en'] = $label;
			$out['url_ru'] = $issueId > 0 ? ('/issue.php?id=' . $issueId) : ('/press.php?id=' . $pressId);
			$out['url_en'] = $out['url_ru'];
			$out['thumb_url'] = screen_public_url($objectId);
			return $out;
		}
		if ($row) {
			$pressTitle = title_plain((string) ($row['press_title'] ?? ''));
			$issueTitle = title_plain((string) ($row['issue_title'] ?? ''));
			$label = $pressTitle !== '' ? $pressTitle : (($objectType === 'illustration' ? 'Иллюстрация #' : 'Скриншот #') . $objectId);
			if ($issueTitle !== '') {
				$label .= ' · #' . $issueTitle;
			}
			$issueId = (int) ($row['id_issue'] ?? 0);
			$pressId = (int) ($row['id_press'] ?? 0);
			$out['title_ru'] = $label;
			$out['title_en'] = $label;
			$out['url_ru'] = $issueId > 0 ? ('/issue.php?id=' . $issueId) : ($pressId > 0 ? ('/press.php?id=' . $pressId) : '');
			$out['url_en'] = $out['url_ru'];
			$out['thumb_url'] = screen_public_url($objectId);
		}
		return $out;
	}

	if ($objectType === 'article' && $objectId > 0) {
		$z = db_select($db, 'SELECT title, title_eng, id_issue FROM articles WHERE id=? LIMIT 1', 'i', $objectId);
		if ($z && ($a = $z->fetch_assoc())) {
			$out['title_ru'] = title_plain((string) ($a['title'] ?? ''));
			$out['title_en'] = title_plain((string) (($a['title_eng'] ?? '') !== '' ? $a['title_eng'] : $a['title']));
			$out['url_ru'] = '/article.php?id=' . $objectId;
			$out['url_en'] = $out['url_ru'];
		}
		return $out;
	}

	if ($objectType === 'issue' && $objectId > 0) {
		$z = db_select(
			$db,
			'SELECT i.title AS issue_title, p.title AS press_title, i.id_press '
			. 'FROM issue i LEFT JOIN press p ON p.id=i.id_press WHERE i.id=? LIMIT 1',
			'i',
			$objectId
		);
		if ($z && ($row = $z->fetch_assoc())) {
			$pressTitle = title_plain((string) ($row['press_title'] ?? ''));
			$issueTitle = title_plain((string) ($row['issue_title'] ?? ''));
			$label = $pressTitle !== '' ? $pressTitle : ('Выпуск #' . $objectId);
			if ($issueTitle !== '') {
				$label .= ' · #' . $issueTitle;
			}
			$out['title_ru'] = $label;
			$out['title_en'] = $label;
			$out['url_ru'] = '/issue.php?id=' . $objectId;
			$out['url_en'] = $out['url_ru'];
		}
		return $out;
	}

	if ($objectType === 'press' && $objectId > 0) {
		$z = db_select($db, 'SELECT title FROM press WHERE id=? LIMIT 1', 'i', $objectId);
		if ($z && ($p = $z->fetch_assoc())) {
			$out['title_ru'] = title_plain((string) ($p['title'] ?? ''));
			$out['title_en'] = $out['title_ru'];
			$out['url_ru'] = '/press.php?id=' . $objectId;
			$out['url_en'] = $out['url_ru'];
		}
		return $out;
	}

	if ($objectType === 'book' && $objectId > 0) {
		$z = db_select($db, 'SELECT title1 FROM books WHERE id=? LIMIT 1', 'i', $objectId);
		if ($z && ($b = $z->fetch_assoc())) {
			$out['title_ru'] = title_plain((string) ($b['title1'] ?? ''));
			$out['title_en'] = $out['title_ru'];
			$out['url_ru'] = '/book.php?id=' . $objectId;
			$out['url_en'] = $out['url_ru'];
		}
		return $out;
	}

	if ($parentType === 'issue' && (int) $parentId > 0 && $out['title_ru'] === '') {
		return activity_resolve_object_display($db, 'issue', (int) $parentId);
	}
	if ($parentType === 'press' && (int) $parentId > 0 && $out['title_ru'] === '') {
		return activity_resolve_object_display($db, 'press', (int) $parentId);
	}

	return $out;
}

/**
 * Fill empty titles/thumbs/urls on activity rows of a batch (e.g. trigger-mirrored).
 */
function activity_enrich_batch_events(mysqli $db, int $batchId): void
{
	if ($batchId <= 0 || !activity_tables_ready($db)) {
		return;
	}
	$z = db_select(
		$db,
		'SELECT id, object_type, object_id, parent_type, parent_id, title_ru, title_en, url_ru, url_en, thumb_url, meta_json '
		. 'FROM activity WHERE batch_id=? ORDER BY id ASC',
		'i',
		$batchId
	);
	while ($z && ($row = $z->fetch_assoc())) {
		$needTitle = trim((string) ($row['title_ru'] ?? '')) === '';
		$needThumb = empty($row['thumb_url']);
		$needUrl = trim((string) ($row['url_ru'] ?? '')) === '';
		if (!$needTitle && !$needThumb && !$needUrl) {
			continue;
		}
		$meta = null;
		if (!empty($row['meta_json'])) {
			$decoded = json_decode((string) $row['meta_json'], true);
			$meta = is_array($decoded) ? $decoded : null;
		}
		$disp = activity_resolve_object_display(
			$db,
			(string) $row['object_type'],
			(int) $row['object_id'],
			isset($row['parent_type']) ? (string) $row['parent_type'] : null,
			isset($row['parent_id']) ? (int) $row['parent_id'] : null,
			$meta
		);
		$titleRu = $needTitle ? $disp['title_ru'] : (string) $row['title_ru'];
		$titleEn = $needTitle
			? ($disp['title_en'] !== '' ? $disp['title_en'] : $disp['title_ru'])
			: (string) (($row['title_en'] ?? '') !== '' ? $row['title_en'] : $row['title_ru']);
		$urlRu = $needUrl ? $disp['url_ru'] : (string) $row['url_ru'];
		$urlEn = $needUrl ? ($disp['url_en'] !== '' ? $disp['url_en'] : $disp['url_ru']) : (string) ($row['url_en'] ?? $row['url_ru']);
		$thumb = $needThumb ? $disp['thumb_url'] : (string) ($row['thumb_url'] ?? '');
		if ($titleRu === '' && ($thumb === null || $thumb === '') && $urlRu === '') {
			continue;
		}
		db_exec(
			$db,
			'UPDATE activity SET title_ru=?, title_en=?, url_ru=?, url_en=?, thumb_url=? WHERE id=? LIMIT 1',
			'sssssi',
			$titleRu,
			$titleEn,
			$urlRu,
			$urlEn,
			$thumb !== null ? $thumb : '',
			(int) $row['id']
		);
		if ($thumb === null || $thumb === '') {
			db_exec($db, 'UPDATE activity SET thumb_url=NULL WHERE id=? AND thumb_url=\'\' LIMIT 1', 'i', (int) $row['id']);
		}
	}
}

/**
 * Build summary + titles for a batch from its events.
 * Also mirrors any pending legacy `log` rows into this batch first.
 */
function activity_batch_finalize(mysqli $db, int $batchId = 0, bool $keepEmpty = false): void
{
	if (!activity_tables_ready($db)) {
		return;
	}
	if ($batchId <= 0) {
		$batchId = activity_current_batch_id();
	}
	if ($batchId <= 0) {
		return;
	}

	$batchCreated = 0;
	$batchActor = 0;
	$zb = db_select($db, 'SELECT created_at, actor_user_id FROM activity_batch WHERE id=? LIMIT 1', 'i', $batchId);
	if ($zb && ($brow = $zb->fetch_assoc())) {
		$batchCreated = (int) ($brow['created_at'] ?? 0);
		$batchActor = (int) ($brow['actor_user_id'] ?? 0);
		$sinceTs = max(0, $batchCreated - 10);
		activity_attach_orphan_events_to_batch($db, $batchId, $sinceTs, $batchActor);
		activity_mirror_pending_logs($db, $sinceTs, $batchActor);
	}

	activity_enrich_batch_events($db, $batchId);

	$z = db_select(
		$db,
		'SELECT object_type, verb, is_public, event_scope, title_ru, title_en, url_ru, url_en, thumb_url, parent_type, parent_id, action '
		. 'FROM activity WHERE batch_id=? ORDER BY id ASC',
		'i',
		$batchId
	);
	$rows = [];
	while ($z && ($row = $z->fetch_assoc())) {
		$rows[] = $row;
	}

	if ($rows === [] && !$keepEmpty) {
		db_exec($db, 'DELETE FROM activity_batch WHERE id=? AND items_count=0 LIMIT 1', 'i', $batchId);
		if (($GLOBALS['__activity_request']['batch_id'] ?? 0) == $batchId) {
			$GLOBALS['__activity_request'] = null;
		}
		return;
	}

	$counts = [];
	$publicCount = 0;
	$titleRu = '';
	$titleEn = '';
	$urlRu = '';
	$urlEn = '';
	$thumb = null;
	$hasPublicContent = false;

	foreach ($rows as $r) {
		$key = (string) $r['object_type'] . ':' . (string) $r['verb'];
		$counts[$key] = ($counts[$key] ?? 0) + 1;
		if ((int) $r['is_public'] === 1 && (string) $r['event_scope'] === ACTIVITY_SCOPE_CONTENT) {
			$publicCount++;
			$hasPublicContent = true;
			if ($titleRu === '' && trim((string) $r['title_ru']) !== '') {
				$titleRu = (string) $r['title_ru'];
				$titleEn = (string) ($r['title_en'] ?: $r['title_ru']);
				$urlRu = (string) ($r['url_ru'] ?? '');
				$urlEn = (string) ($r['url_en'] ?? $urlRu);
			}
			if ($thumb === null && !empty($r['thumb_url'])) {
				$thumb = (string) $r['thumb_url'];
			}
		}
	}

	$partsRu = [];
	$partsEn = [];
	foreach ($counts as $key => $n) {
		[$type, $verb] = array_pad(explode(':', $key, 2), 2, '');
		$partsRu[] = activity_count_phrase($type, $verb, $n, false);
		$partsEn[] = activity_count_phrase($type, $verb, $n, true);
	}

	$summaryRu = $partsRu !== [] ? implode(', ', $partsRu) : ('Событий: ' . count($rows));
	$summaryEn = $partsEn !== [] ? implode(', ', $partsEn) : ('Events: ' . count($rows));
	if ($titleRu === '') {
		$titleRu = 'Обновление';
		$titleEn = 'Update';
	}

	$now = time();
	$isPublic = $hasPublicContent ? 1 : 0;
	$items = count($rows);
	$thumbVal = $thumb !== null ? $thumb : '';

	db_exec(
		$db,
		'UPDATE activity_batch SET closed_at=?, title_ru=?, title_en=?, url_ru=?, url_en=?, '
		. 'summary_ru=?, summary_en=?, thumb_url=?, items_count=?, public_items_count=?, is_public=? '
		. 'WHERE id=? LIMIT 1',
		'isssssssiiii',
		$now,
		$titleRu,
		$titleEn,
		$urlRu,
		$urlEn,
		$summaryRu,
		$summaryEn,
		$thumbVal,
		$items,
		$publicCount,
		$isPublic,
		$batchId
	);
	if ($thumbVal === '') {
		db_exec($db, 'UPDATE activity_batch SET thumb_url=NULL WHERE id=? LIMIT 1', 'i', $batchId);
	}

	if (($GLOBALS['__activity_request']['batch_id'] ?? 0) == $batchId) {
		$GLOBALS['__activity_request'] = null;
	}
	$db->query('SET @activity_batch_id := NULL');
}

/**
 * Delete a batch and its events. Also removes linked custom_activity_updates rows
 * (and their image files) when present.
 */
function activity_batch_delete(mysqli $db, int $batchId): bool
{
	if ($batchId <= 0 || !activity_tables_ready($db)) {
		return false;
	}

	$customIds = [];
	$z = db_select(
		$db,
		'SELECT object_id FROM activity WHERE batch_id=? AND object_type=\'custom_update\' AND object_id>0',
		'i',
		$batchId
	);
	while ($z && ($row = $z->fetch_assoc())) {
		$cid = (int) ($row['object_id'] ?? 0);
		if ($cid > 0) {
			$customIds[$cid] = $cid;
		}
	}

	$r = @$db->query("SHOW TABLES LIKE 'custom_activity_updates'");
	if ($r && $r->num_rows > 0 && $customIds !== []) {
		$idList = implode(',', array_map('intval', $customIds));
		$zc = $db->query('SELECT id, image_url FROM custom_activity_updates WHERE id IN (' . $idList . ')');
		while ($zc && ($crow = $zc->fetch_assoc())) {
			$imageUrl = (string) ($crow['image_url'] ?? '');
			if ($imageUrl !== '' && preg_match('#^/activity-images/([^/]+)$#', $imageUrl, $m)) {
				$leaf = rawurldecode($m[1]);
				$path = zx_storage_path('activity_images', $leaf);
				if (is_file($path)) {
					@unlink($path);
				}
			}
		}
		$db->query('DELETE FROM custom_activity_updates WHERE id IN (' . $idList . ')');
	}

	db_exec($db, 'DELETE FROM activity WHERE batch_id=?', 'i', $batchId);
	db_exec($db, 'DELETE FROM activity_batch WHERE id=? LIMIT 1', 'i', $batchId);

	return true;
}

/**
 * Log one activity event. Joins current request batch if any.
 *
 * @param array<string,mixed> $opts
 */
function activity_log(mysqli $db, array $opts): int
{
	if (!activity_tables_ready($db)) {
		return 0;
	}

	$verb = (string) ($opts['verb'] ?? 'updated');
	$objectType = (string) ($opts['object_type'] ?? '');
	$objectId = (int) ($opts['object_id'] ?? 0);
	if ($objectType === '') {
		return 0;
	}

	$parentType = isset($opts['parent_type']) ? (string) $opts['parent_type'] : null;
	$parentId = isset($opts['parent_id']) ? (int) $opts['parent_id'] : null;
	$action = (string) ($opts['action'] ?? ($objectType . '.' . $verb));
	$scope = (string) ($opts['event_scope'] ?? ACTIVITY_SCOPE_CONTENT);
	if (!in_array($scope, [ACTIVITY_SCOPE_CONTENT, ACTIVITY_SCOPE_METADATA, ACTIVITY_SCOPE_SYSTEM], true)) {
		$scope = ACTIVITY_SCOPE_CONTENT;
	}
	$isPublic = array_key_exists('is_public', $opts)
		? ((int) ((bool) $opts['is_public']))
		: ($scope === ACTIVITY_SCOPE_CONTENT ? 1 : 0);

	$titleRu = (string) ($opts['title_ru'] ?? '');
	$titleEn = (string) ($opts['title_en'] ?? $titleRu);
	$urlRu = (string) ($opts['url_ru'] ?? '');
	$urlEn = (string) ($opts['url_en'] ?? $urlRu);
	$thumbUrl = isset($opts['thumb_url']) ? (string) $opts['thumb_url'] : null;
	$beforeJson = activity_json($opts['before'] ?? $opts['before_json'] ?? null);
	$afterJson = activity_json($opts['after'] ?? $opts['after_json'] ?? null);
	$metaJson = activity_json($opts['meta'] ?? $opts['meta_json'] ?? null);
	$legacyLogId = isset($opts['legacy_log_id']) ? (int) $opts['legacy_log_id'] : null;
	$legacyLogType = isset($opts['legacy_log_type']) ? (int) $opts['legacy_log_type'] : null;

	$batchId = (int) ($opts['batch_id'] ?? 0);
	if ($batchId <= 0) {
		$batchId = activity_current_batch_id();
	}
	$batchIdParam = $batchId > 0 ? $batchId : null;

	$actor = (int) ($opts['actor_user_id'] ?? 0);
	if ($actor <= 0) {
		$actor = activity_actor_user_id();
	}
	$createdAt = (int) ($opts['created_at'] ?? time());

	return activity_log_insert_raw($db, [
		'batch_id' => $batchIdParam,
		'created_at' => $createdAt,
		'actor_user_id' => $actor,
		'verb' => $verb,
		'object_type' => $objectType,
		'object_id' => $objectId,
		'parent_type' => $parentType,
		'parent_id' => $parentId,
		'action' => $action,
		'event_scope' => $scope,
		'is_public' => $isPublic,
		'title_ru' => $titleRu,
		'title_en' => $titleEn,
		'url_ru' => $urlRu,
		'url_en' => $urlEn,
		'thumb_url' => $thumbUrl,
		'before_json' => $beforeJson,
		'after_json' => $afterJson,
		'meta_json' => $metaJson,
		'legacy_log_id' => $legacyLogId,
		'legacy_log_type' => $legacyLogType,
	]);
}

/**
 * @param array<string,mixed> $row
 */
function activity_log_insert_raw(mysqli $db, array $row): int
{
	$cols = [];
	$placeholders = [];
	$types = '';
	$values = [];

	$map = [
		'batch_id' => 'i',
		'created_at' => 'i',
		'actor_user_id' => 'i',
		'verb' => 's',
		'object_type' => 's',
		'object_id' => 'i',
		'parent_type' => 's',
		'parent_id' => 'i',
		'action' => 's',
		'event_scope' => 's',
		'is_public' => 'i',
		'title_ru' => 's',
		'title_en' => 's',
		'url_ru' => 's',
		'url_en' => 's',
		'thumb_url' => 's',
		'before_json' => 's',
		'after_json' => 's',
		'meta_json' => 's',
		'legacy_log_id' => 'i',
		'legacy_log_type' => 'i',
	];

	foreach ($map as $col => $t) {
		if (!array_key_exists($col, $row)) {
			continue;
		}
		$val = $row[$col];
		if ($val === null) {
			$cols[] = '`' . $col . '`';
			$placeholders[] = 'NULL';
			continue;
		}
		$cols[] = '`' . $col . '`';
		$placeholders[] = '?';
		$types .= $t;
		$values[] = $val;
	}

	$sql = 'INSERT INTO activity (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		error_log('[activity] insert prepare failed: ' . $db->error);
		return 0;
	}
	if ($types !== '') {
		$stmt->bind_param($types, ...$values);
	}
	if (!$stmt->execute()) {
		error_log('[activity] insert failed: ' . $stmt->error);
		$stmt->close();
		return 0;
	}
	$id = (int) $db->insert_id;
	$stmt->close();
	return $id;
}

/**
 * Map legacy `log.type` into activity and write a row.
 */
function activity_log_from_legacy(
	mysqli $db,
	int $legacyType,
	int $idPress,
	int $idArticle,
	int $idIssue,
	int $idUser,
	int $date,
	int $idScreen = 0,
	int $idCover = 0,
	int $legacyLogId = 0
): int {
	$map = [
		1 => ['verb' => 'created', 'object_type' => 'article', 'object_id_from' => 'article', 'parent_type' => 'issue', 'parent_from' => 'issue', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'article.created'],
		2 => ['verb' => 'uploaded', 'object_type' => 'screen', 'object_id_from' => 'screen', 'parent_type' => 'issue', 'parent_from' => 'issue', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'screen.uploaded'],
		3 => ['verb' => 'uploaded', 'object_type' => 'illustration', 'object_id_from' => 'screen', 'parent_type' => 'issue', 'parent_from' => 'issue', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'illustration.uploaded'],
		4 => ['verb' => 'created', 'object_type' => 'chapter', 'object_id_from' => 'article', 'parent_type' => 'book', 'parent_from' => 'press', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'chapter.created'],
		100 => ['verb' => 'created', 'object_type' => 'book', 'object_id_from' => 'press', 'parent_type' => null, 'parent_from' => null, 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'book.created'],
		101 => ['verb' => 'uploaded', 'object_type' => 'book_file', 'object_id_from' => 'screen', 'parent_type' => 'book', 'parent_from' => 'press', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'book_file.uploaded'],
		102 => ['verb' => 'uploaded', 'object_type' => 'book_image', 'object_id_from' => 'screen', 'parent_type' => 'book', 'parent_from' => 'press', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 1, 'action' => 'book_image.uploaded'],
		111 => ['verb' => 'updated', 'object_type' => 'book', 'object_id_from' => 'press', 'parent_type' => null, 'parent_from' => null, 'scope' => ACTIVITY_SCOPE_METADATA, 'public' => 0, 'action' => 'book.updated'],
		128 => ['verb' => 'updated', 'object_type' => 'book', 'object_id_from' => 'press', 'parent_type' => null, 'parent_from' => null, 'scope' => ACTIVITY_SCOPE_METADATA, 'public' => 0, 'action' => 'book.meta.updated'],
		160 => ['verb' => 'updated', 'object_type' => 'book', 'object_id_from' => 'press', 'parent_type' => null, 'parent_from' => null, 'scope' => ACTIVITY_SCOPE_METADATA, 'public' => 0, 'action' => 'book.linked'],
		256 => ['verb' => 'deleted', 'object_type' => 'article', 'object_id_from' => 'article', 'parent_type' => 'issue', 'parent_from' => 'issue', 'scope' => ACTIVITY_SCOPE_CONTENT, 'public' => 0, 'action' => 'article.deleted'],
	];

	$m = $map[$legacyType] ?? [
		'verb' => 'updated',
		'object_type' => 'legacy',
		'object_id_from' => 'article',
		'parent_type' => 'issue',
		'parent_from' => 'issue',
		'scope' => ACTIVITY_SCOPE_SYSTEM,
		'public' => 0,
		'action' => 'legacy.type.' . $legacyType,
	];

	$objectId = match ($m['object_id_from']) {
		'screen' => $idScreen > 0 ? $idScreen : ($idCover > 0 ? $idCover : $idArticle),
		'press' => $idPress,
		'issue' => $idIssue,
		default => $idArticle > 0 ? $idArticle : ($idPress > 0 ? $idPress : $idIssue),
	};
	$parentId = null;
	$parentType = $m['parent_type'];
	if ($parentType !== null) {
		$parentId = match ($m['parent_from']) {
			'press' => $idPress,
			'issue' => $idIssue,
			default => 0,
		};
	}

	$titleRu = '';
	$titleEn = '';
	$urlRu = '';
	$thumb = null;
	$meta = [
		'legacy_type' => $legacyType,
		'id_press' => $idPress,
		'id_issue' => $idIssue,
		'id_article' => $idArticle,
		'id_screen' => $idScreen,
		'id_cover' => $idCover,
	];

	$disp = activity_resolve_object_display(
		$db,
		(string) $m['object_type'],
		$objectId,
		$parentType,
		$parentId,
		$meta
	);
	$titleRu = $disp['title_ru'];
	$titleEn = $disp['title_en'];
	$urlRu = $disp['url_ru'];
	$thumb = $disp['thumb_url'];

	if ($m['object_type'] === 'chapter' && $idArticle > 0 && $parentId <= 0) {
		$z = db_select($db, 'SELECT ch_id_book FROM chapters WHERE ch_id=? LIMIT 1', 'i', $idArticle);
		if ($z && ($c = $z->fetch_assoc())) {
			$parentId = (int) ($c['ch_id_book'] ?? $parentId);
		}
	}

	return activity_log($db, [
		'verb' => $m['verb'],
		'object_type' => $m['object_type'],
		'object_id' => $objectId,
		'parent_type' => $parentType,
		'parent_id' => $parentId,
		'action' => $m['action'],
		'event_scope' => $m['scope'],
		'is_public' => $m['public'],
		'title_ru' => $titleRu,
		'title_en' => $titleEn,
		'url_ru' => $urlRu,
		'url_en' => $urlRu,
		'thumb_url' => $thumb,
		'meta' => $meta,
		'actor_user_id' => $idUser,
		'created_at' => $date > 0 ? $date : time(),
		'legacy_log_id' => $legacyLogId > 0 ? $legacyLogId : null,
		'legacy_log_type' => $legacyType,
	]);
}

/**
 * Boot per-request batch for admin POST mutations.
 */
function activity_admin_request_boot(mysqli $db): void
{
	if (!activity_tables_ready($db)) {
		return;
	}
	if (($GLOBALS['__activity_request']['batch_id'] ?? 0) > 0) {
		return;
	}
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		return;
	}
	if (empty($_SESSION['login'])) {
		return;
	}
	$script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
	if (!(str_starts_with($script, 'admin_') || $script === 'gallery_admin.php')) {
		return;
	}

	activity_batch_begin($db);
	register_shutdown_function(static function () use ($db): void {
		try {
			activity_batch_finalize($db);
		} catch (Throwable $e) {
			error_log('[activity] finalize failed: ' . $e->getMessage());
		}
	});
}

/**
 * Human labels for object types (test page / feed).
 */
function activity_object_label(string $type, bool $eng = false): string
{
	$ru = [
		'article' => 'Статья',
		'screen' => 'Скриншот',
		'illustration' => 'Иллюстрация',
		'file' => 'Файл',
		'issue' => 'Выпуск',
		'press' => 'Издание',
		'book' => 'Книга',
		'chapter' => 'Глава',
		'book_file' => 'Файл книги',
		'book_image' => 'Обложка/картинка книги',
		'periodical' => 'Периодика',
		'periodical_issue' => 'Номер периодики',
		'periodical_article' => 'Статья периодики',
		'periodical_issue_image' => 'Скан номера',
		'periodical_issue_file' => 'Файл номера',
		'publication' => 'Публикация',
		'publication_article' => 'Материал публикации',
		'letter' => 'Письмо',
		'news' => 'Новость',
		'news_file' => 'Файл новости',
		'custom_update' => 'Обновление',
		'author' => 'Автор',
		'publisher' => 'Издательство',
		'book_rubric' => 'Рубрика книг',
		'category' => 'Категория',
		'tag' => 'Тег',
		'legacy' => 'Служебное',
	];
	$en = [
		'article' => 'Article',
		'screen' => 'Screenshot',
		'illustration' => 'Illustration',
		'file' => 'File',
		'issue' => 'Issue',
		'press' => 'Magazine',
		'book' => 'Book',
		'chapter' => 'Chapter',
		'book_file' => 'Book file',
		'book_image' => 'Book image',
		'periodical' => 'Periodical',
		'periodical_issue' => 'Periodical issue',
		'periodical_article' => 'Periodical article',
		'periodical_issue_image' => 'Issue scan',
		'periodical_issue_file' => 'Issue file',
		'publication' => 'Publication',
		'publication_article' => 'Publication article',
		'letter' => 'Letter',
		'news' => 'News',
		'news_file' => 'News file',
		'custom_update' => 'Update',
		'author' => 'Author',
		'publisher' => 'Publisher',
		'book_rubric' => 'Book rubric',
		'category' => 'Category',
		'tag' => 'Tag',
		'legacy' => 'System',
	];
	return $eng ? ($en[$type] ?? $type) : ($ru[$type] ?? $type);
}

/**
 * Human labels for activity_batch.domain (feed filters / row meta).
 */
function activity_domain_label(string $domain, bool $eng = false): string
{
	$ru = [
		'ezine' => 'Электронная пресса',
		'periodical' => 'Периодика',
		'publication' => 'Публикации',
		'book' => 'Книги',
		'letter' => 'Письма',
		'news' => 'Новости',
		'custom' => 'Ручные апдейты',
		'author' => 'Авторы',
		'publisher' => 'Издательства',
		'gallery' => 'Галерея',
	];
	$en = [
		'ezine' => 'Diskmags',
		'periodical' => 'Periodicals',
		'publication' => 'Publications',
		'book' => 'Books',
		'letter' => 'Letters',
		'news' => 'News',
		'custom' => 'Manual updates',
		'author' => 'Authors',
		'publisher' => 'Publishers',
		'gallery' => 'Gallery',
	];
	$key = strtolower(trim($domain));
	return $eng ? ($en[$key] ?? $domain) : ($ru[$key] ?? $domain);
}

function activity_plural_ru(int $n, string $one, string $few, string $many): string
{
	$n = abs($n);
	$mod10 = $n % 10;
	$mod100 = $n % 100;
	if ($mod10 === 1 && $mod100 !== 11) {
		return $one;
	}
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
		return $few;
	}
	return $many;
}

/**
 * Countable words for object types: [one, few, many].
 *
 * @return array{0:string,1:string,2:string}
 */
function activity_object_count_words(string $type, bool $eng = false): array
{
	$ru = [
		'article' => ['статья', 'статьи', 'статей'],
		'screen' => ['скриншот', 'скриншота', 'скриншотов'],
		'illustration' => ['иллюстрация', 'иллюстрации', 'иллюстраций'],
		'file' => ['файл', 'файла', 'файлов'],
		'issue' => ['выпуск', 'выпуска', 'выпусков'],
		'press' => ['издание', 'издания', 'изданий'],
		'book' => ['книга', 'книги', 'книг'],
		'chapter' => ['глава', 'главы', 'глав'],
		'book_file' => ['файл книги', 'файла книги', 'файлов книги'],
		'book_image' => ['обложка', 'обложки', 'обложек'],
		'periodical' => ['издание', 'издания', 'изданий'],
		'periodical_issue' => ['номер', 'номера', 'номеров'],
		'periodical_article' => ['статья', 'статьи', 'статей'],
		'periodical_issue_image' => ['скан', 'скана', 'сканов'],
		'periodical_issue_file' => ['файл номера', 'файла номера', 'файлов номера'],
		'publication' => ['публикация', 'публикации', 'публикаций'],
		'publication_article' => ['материал', 'материала', 'материалов'],
		'letter' => ['письмо', 'письма', 'писем'],
		'news' => ['новость', 'новости', 'новостей'],
		'news_file' => ['файл новости', 'файла новости', 'файлов новости'],
		'custom_update' => ['обновление', 'обновления', 'обновлений'],
		'author' => ['автор', 'автора', 'авторов'],
		'publisher' => ['издательство', 'издательства', 'издательств'],
		'book_rubric' => ['рубрика', 'рубрики', 'рубрик'],
		'category' => ['категория', 'категории', 'категорий'],
		'tag' => ['тег', 'тега', 'тегов'],
	];
	$en = [
		'article' => ['article', 'articles', 'articles'],
		'screen' => ['screenshot', 'screenshots', 'screenshots'],
		'illustration' => ['illustration', 'illustrations', 'illustrations'],
		'file' => ['file', 'files', 'files'],
		'issue' => ['issue', 'issues', 'issues'],
		'press' => ['magazine', 'magazines', 'magazines'],
		'book' => ['book', 'books', 'books'],
		'chapter' => ['chapter', 'chapters', 'chapters'],
		'book_file' => ['book file', 'book files', 'book files'],
		'book_image' => ['cover', 'covers', 'covers'],
		'periodical' => ['periodical', 'periodicals', 'periodicals'],
		'periodical_issue' => ['issue', 'issues', 'issues'],
		'periodical_article' => ['article', 'articles', 'articles'],
		'periodical_issue_image' => ['scan', 'scans', 'scans'],
		'periodical_issue_file' => ['issue file', 'issue files', 'issue files'],
		'publication' => ['publication', 'publications', 'publications'],
		'publication_article' => ['item', 'items', 'items'],
		'letter' => ['letter', 'letters', 'letters'],
		'news' => ['news item', 'news items', 'news items'],
		'news_file' => ['news file', 'news files', 'news files'],
		'custom_update' => ['update', 'updates', 'updates'],
		'author' => ['author', 'authors', 'authors'],
		'publisher' => ['publisher', 'publishers', 'publishers'],
		'book_rubric' => ['rubric', 'rubrics', 'rubrics'],
		'category' => ['category', 'categories', 'categories'],
		'tag' => ['tag', 'tags', 'tags'],
	];
	$map = $eng ? $en : $ru;
	if (isset($map[$type])) {
		return $map[$type];
	}
	$fallback = mb_strtolower(activity_object_label($type, $eng));
	return [$fallback, $fallback, $fallback];
}

/**
 * Short counted phrase for a batch summary: "+3 файла", "правки: 2 статьи".
 */
function activity_count_phrase(string $type, string $verb, int $n, bool $eng): string
{
	$words = activity_object_count_words($type, $eng);
	$word = $eng ? ($n === 1 ? $words[0] : $words[2]) : getNumEnding($n, $words);
	$additive = ['created', 'uploaded', 'published', 'added', 'imported', 'restored'];
	if (in_array($verb, $additive, true)) {
		return '+' . $n . ' ' . $word;
	}
	if ($verb === 'deleted') {
		return ($eng ? 'removed' : 'удалено') . ': ' . $n . ' ' . $word;
	}
	$prefix = $eng
		? ($n === 1 ? 'edit' : 'edits')
		: getNumEnding($n, ['правка', 'правки', 'правок']);
	return $prefix . ': ' . $n . ' ' . $word;
}

/**
 * Pretty title / details label for feed batches: parent name + counted events.
 *
 * @param array<string,mixed> $batch
 * @param list<array<string,mixed>> $events
 * @param array{title?:string,url?:string} $root resolved parent (press / issue / book) of the batch
 * @return array{title:string,title_press:string,title_suffix:string,summary:string,details_label:string,url_press:string,is_compact:bool}
 */
function activity_feed_present_batch(array $batch, array $events, bool $eng, array $root = []): array
{
	$title = title_plain(
		$eng
			? (trim((string) ($batch['title_en'] ?? '')) !== ''
				? (string) $batch['title_en']
				: (string) ($batch['title_ru'] ?? ''))
			: (string) ($batch['title_ru'] ?? '')
	);
	$summary = title_plain(
		$eng
			? (trim((string) ($batch['summary_en'] ?? '')) !== ''
				? (string) $batch['summary_en']
				: (string) ($batch['summary_ru'] ?? ''))
			: (string) ($batch['summary_ru'] ?? '')
	);

	$empty = [
		'title' => $title,
		'title_press' => $title,
		'title_suffix' => '',
		'summary' => $summary,
		'details_label' => '',
		'url_press' => '',
		'is_compact' => false,
	];

	if ($events === []) {
		return $empty;
	}

	$counts = [];
	foreach ($events as $e) {
		$type = (string) ($e['object_type'] ?? '');
		if ($type === '') {
			continue;
		}
		$key = $type . ':' . (string) ($e['verb'] ?? '');
		$counts[$key] = ($counts[$key] ?? 0) + 1;
	}

	$parts = [];
	foreach ($counts as $key => $n) {
		[$type, $verb] = array_pad(explode(':', $key, 2), 2, '');
		$parts[] = activity_count_phrase($type, $verb, $n, $eng);
	}
	$details = $parts !== [] ? implode(', ', $parts) : trim($summary);
	if ($details === '') {
		$n = count($events);
		$details = $eng
			? $n . ' ' . activity_plural_en($n, 'event', 'events')
			: $n . ' ' . activity_plural_ru($n, 'событие', 'события', 'событий');
	}

	$head = trim((string) ($root['title'] ?? ''));
	if ($head === '') {
		$head = $title;
		if (preg_match('/^(.+?)\s*:\s*(скриншот|screenshot)/iu', $title, $m)) {
			$head = trim($m[1]);
		} elseif (preg_match('/^(.+?)\s+[—–-]\s+/u', $title, $m)) {
			$head = trim($m[1]);
		}
	}
	if ($head === '') {
		return $empty;
	}

	return [
		'title' => $head . ' — ' . $details,
		'title_press' => $head,
		'title_suffix' => ' — ',
		'summary' => '',
		'details_label' => $details,
		'url_press' => trim((string) ($root['url'] ?? '')),
		'is_compact' => true,
	];
}

/**
 * Pick RU/EN title field for a feed row (batch or event).
 *
 * @param array<string,mixed> $row
 */
function activity_feed_custom_update_raw_title(array $row, bool $isEng): string
{
	$titleEn = trim((string) ($row['title_en'] ?? ''));
	return ($isEng && $titleEn !== '') ? $titleEn : (string) ($row['title_ru'] ?? '');
}

/** Render admin-authored custom update markdown for the public feed. */
function activity_custom_update_markdown_html(string $raw): string
{
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}

	static $pd = null;
	if ($pd === null) {
		require_once __DIR__ . '/Parsedown.php';
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		$pd->setBreaksEnabled(true);
	}

	return $pd->text($raw);
}

/**
 * @param array<string,mixed> $batch
 */
function activity_feed_apply_custom_update_batch(array &$batch, bool $isEng): void
{
	$events = $batch['events'] ?? [];
	if ($events === []) {
		return;
	}

	$onlyCustom = count($events) === 1
		&& (string) ($events[0]['object_type'] ?? '') === 'custom_update';
	if (!$onlyCustom) {
		foreach ($events as &$e) {
			if ((string) ($e['object_type'] ?? '') !== 'custom_update') {
				continue;
			}
			$raw = activity_feed_custom_update_raw_title($e, $isEng);
			$e['title_html'] = activity_custom_update_markdown_html($raw);
			$e['is_custom_update'] = 1;
		}
		unset($e);
		return;
	}

	$raw = activity_feed_custom_update_raw_title($events[0], $isEng);
	if ($raw === '') {
		$raw = activity_feed_custom_update_raw_title($batch, $isEng);
	}
	$html = activity_custom_update_markdown_html($raw);

	$batch['title_html'] = $html;
	$batch['title_display'] = title_plain($raw);
	$batch['title_press'] = $batch['title_display'];
	$batch['title_suffix'] = '';
	$batch['summary_display'] = '';
	$batch['is_custom_update'] = 1;
	$batch['is_compact'] = 0;

	$events[0]['title_html'] = $html;
	$events[0]['is_custom_update'] = 1;
	$batch['events'] = $events;
}

function activity_plural_en(int $n, string $one, string $many): string
{
	return abs($n) === 1 ? $one : $many;
}
