#!/usr/bin/env php
<?php
/**
 * Import ACNews #76–#80 enriched JSON (plain + markdown, tags, categories).
 *
 *   php site/cli/import-acnews-76-80-articles.php --dry-run
 *   php site/cli/import-acnews-76-80-articles.php --apply
 *   php site/cli/import-acnews-76-80-articles.php --apply --force --only=76,80
 *
 * Source: local/work/ACN7680_texts/articles/*.json (or --source=DIR)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

const ACN_PRESS_ID = 8;
const ACN_SOURCE = 'migration:acnews_76_80';
const ACN_TEXT_TYPE = 3; // markdown in articles.text_*; plain also stored in ezines_v2 bodies

/** @var array<string,int> issue title → unix date from menu */
const ACN_ISSUE_DATES = [
	'76' => 1701475200, // 2023-12-02
	'77' => 1735689600, // 2025-01-01
	'78' => 1746316800, // 2025-05-04
	'79' => 1766016000, // 2025-12-18
	'80' => 1793923200, // 2026-11-06 (menu: 6xi'26)
];

$dryRun = true;
$force = false;
$pressId = ACN_PRESS_ID;
$source = '';
$only = ['76', '77', '78', '79', '80'];

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--force') {
		$force = true;
	} elseif (str_starts_with($arg, '--press-id=')) {
		$pressId = max(0, (int) substr($arg, 11));
	} elseif (str_starts_with($arg, '--source=')) {
		$source = rtrim(substr($arg, 9), '/');
	} elseif (str_starts_with($arg, '--only=')) {
		$only = [];
		foreach (explode(',', substr($arg, 7)) as $part) {
			$p = trim($part);
			if ($p !== '' && isset(ACN_ISSUE_DATES[$p])) {
				$only[] = $p;
			}
		}
		$only = array_values(array_unique($only));
		if ($only === []) {
			fwrite(STDERR, "--only must list 76..80\n");
			exit(1);
		}
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--force] [--only=76,80] [--source=DIR]\n");
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/cli/import-acnews-76-80-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-acnews-76-80';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/admin_helpers.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';
require_once $siteRoot . '/includes/article_search_text.php';

if ($source === '') {
	$candidates = [
		dirname($siteRoot) . '/local/work/ACN7680_texts/articles',
		$siteRoot . '/cli/imports/acnews_76_80',
	];
	foreach ($candidates as $cand) {
		if (is_dir($cand)) {
			$source = $cand;
			break;
		}
	}
}
if ($source === '' || !is_dir($source)) {
	fwrite(STDERR, "Need --source=DIR with enriched *.json\n");
	exit(1);
}

function acn_queue_manticore_reindex(): void
{
	$dir = zx_data_root() . '/manticore-reindex';
	@mkdir($dir, 0775, true);
	@file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function acn_clip_meta(string $s): string
{
	$s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
	if (function_exists('mb_substr')) {
		return mb_substr($s, 0, 255, 'UTF-8');
	}

	return substr($s, 0, 255);
}

function acn_find_or_create_issue(mysqli $db, int $pressId, string $title, int $date, bool $dryRun): array
{
	$z = db_select(
		$db,
		'SELECT id, title, date, slug_ru, slug_en FROM issue WHERE id_press=? AND title=? LIMIT 1',
		'is',
		$pressId,
		$title
	);
	$row = $z ? $z->fetch_assoc() : null;
	if ($row) {
		return $row;
	}
	if ($dryRun) {
		return ['id' => 0, 'title' => $title, 'date' => $date, 'slug_ru' => '', 'slug_en' => ''];
	}
	// ACNews uses title*10 (…740, 750) so new issues sort above older ones.
	$sort = ((int) $title) * 10;
	$missing = 0;
	db_exec(
		$db,
		'INSERT INTO issue (id_press, title, date, sort_order, views, missing) VALUES (?,?,?,?,0,?)',
		'isiii',
		$pressId,
		$title,
		$date,
		$sort,
		$missing
	);
	$id = (int) mysqli_insert_id($db);
	$slug = 'acnews-' . $title;
	db_exec($db, 'UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1', 'ssi', $slug, $slug, $id);
	activity_log($db, [
		'verb' => 'created',
		'object_type' => 'issue',
		'object_id' => $id,
		'parent_type' => 'press',
		'parent_id' => $pressId,
		'action' => 'issue.created',
		'event_scope' => ACTIVITY_SCOPE_CONTENT,
		'is_public' => 1,
		'title_ru' => 'ACNews #' . $title,
		'title_en' => 'ACNews #' . $title,
		'url_ru' => '/issue.php?id=' . $id,
		'meta' => ['source' => ACN_SOURCE],
	]);
	$zPress = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
	$pressRow = $zPress ? $zPress->fetch_assoc() : null;
	if (is_array($pressRow) && ezines_v2_ready($db)) {
		$ezineId = ezines_v2_sync_press($db, $pressRow, 'ru');
		$zIssue = db_select($db, 'SELECT * FROM issue WHERE id=? LIMIT 1', 'i', $id);
		$issueRow = $zIssue ? $zIssue->fetch_assoc() : null;
		if ($ezineId > 0 && is_array($issueRow)) {
			ezines_v2_sync_issue($db, $issueRow, $ezineId);
		}
	}

	return [
		'id' => $id,
		'title' => $title,
		'date' => $date,
		'slug_ru' => $slug,
		'slug_en' => $slug,
	];
}

function acn_find_or_create_tag(mysqli $db, string $tagName, string $alias, bool $dryRun): int
{
	$tagName = trim($tagName);
	if ($tagName === '') {
		return 0;
	}
	if (function_exists('mb_substr')) {
		$tagName = mb_substr($tagName, 0, 24, 'UTF-8');
	} else {
		$tagName = substr($tagName, 0, 24);
	}
	$z = db_select($db, 'SELECT id FROM tags WHERE tag_name=? LIMIT 1', 's', $tagName);
	$row = $z ? $z->fetch_assoc() : null;
	if ($row) {
		return (int) $row['id'];
	}
	if ($dryRun) {
		return -1;
	}
	$alias = trim($alias);
	if ($alias === '') {
		$alias = per_slugify($tagName);
	}
	if (function_exists('mb_substr')) {
		$alias = mb_substr($alias, 0, 32, 'UTF-8');
	} else {
		$alias = substr($alias, 0, 32);
	}
	db_exec($db, 'INSERT INTO tags (tag_name, tag_alias, `show`) VALUES (?,?,0)', 'ss', $tagName, $alias);

	return (int) mysqli_insert_id($db);
}

function acn_link_tag(mysqli $db, int $articleId, int $tagId, bool $dryRun): void
{
	if ($articleId <= 0 || $tagId <= 0) {
		return;
	}
	$z = db_select(
		$db,
		'SELECT id FROM tags_articles WHERE id_article=? AND id_tag=? LIMIT 1',
		'ii',
		$articleId,
		$tagId
	);
	if ($z && $z->fetch_assoc()) {
		return;
	}
	if ($dryRun) {
		return;
	}
	db_exec(
		$db,
		'INSERT INTO tags_articles (id_tag, id_article, tag_size, tag_type) VALUES (?,?,0,0)',
		'ii',
		$tagId,
		$articleId
	);
}

function acn_link_categories(mysqli $db, int $articleId, array $categoryIds, bool $dryRun): int
{
	$n = 0;
	$sort = 0;
	foreach ($categoryIds as $cid) {
		$cid = (int) $cid;
		if ($cid <= 0) {
			continue;
		}
		$z = db_select(
			$db,
			'SELECT category_id FROM ezine_article_categories WHERE article_id=? AND category_id=? LIMIT 1',
			'ii',
			$articleId,
			$cid
		);
		if ($z && $z->fetch_assoc()) {
			continue;
		}
		if (!$dryRun) {
			db_exec(
				$db,
				'INSERT IGNORE INTO ezine_article_categories (category_id, article_id, sort_order) VALUES (?,?,?)',
				'iii',
				$cid,
				$articleId,
				$sort
			);
			db_exec(
				$db,
				'UPDATE ezine_categories SET articles_count=(SELECT COUNT(*) FROM ezine_article_categories WHERE category_id=?) WHERE id=? LIMIT 1',
				'ii',
				$cid,
				$cid
			);
		}
		$sort++;
		$n++;
	}

	return $n;
}

function acn_sync_v2_with_plain(mysqli $db, int $pressId, int $issueId, array $articleRow, string $plainRu, string $plainEn): void
{
	if (!ezines_v2_ready($db)) {
		return;
	}
	$v2Issue = ezines_v2_find_by_legacy_issue($db, $issueId);
	$v2IssueId = $v2Issue ? (int) $v2Issue['id'] : 0;
	if ($v2IssueId <= 0) {
		$zPress = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
		$pressRow = ($zPress && ($pr = mysqli_fetch_assoc($zPress))) ? $pr : null;
		if (is_array($pressRow)) {
			$ezineId = ezines_v2_sync_press($db, $pressRow, 'ru');
			$zIssue = db_select($db, 'SELECT * FROM issue WHERE id=? LIMIT 1', 'i', $issueId);
			$issueRow = ($zIssue && ($ir = mysqli_fetch_assoc($zIssue))) ? $ir : null;
			if ($ezineId > 0 && is_array($issueRow)) {
				$v2IssueId = ezines_v2_sync_issue($db, $issueRow, $ezineId);
			}
		}
	}
	if ($v2IssueId <= 0) {
		return;
	}
	$v2ArticleId = ezines_v2_sync_article($db, $articleRow, $v2IssueId, 'ru');
	if ($v2ArticleId <= 0) {
		return;
	}
	// markdown already primary via sync; add plain as text_pre (non-primary)
	if (trim($plainRu) !== '') {
		ezines_v2_upsert_article_body($db, $v2ArticleId, 'ru', 'text_pre', $plainRu, false);
	}
	if (trim($plainEn) !== '') {
		ezines_v2_upsert_article_body($db, $v2ArticleId, 'en', 'text_pre', $plainEn, false);
	}
}

$zPress = db_select($db, 'SELECT id, title FROM press WHERE id=? LIMIT 1', 'i', $pressId);
$press = $zPress ? $zPress->fetch_assoc() : null;
if (!$press) {
	fwrite(STDERR, "Press id={$pressId} not found\n");
	exit(1);
}

$files = glob($source . '/*.json') ?: [];
sort($files, SORT_STRING);
$byIssue = [];
foreach ($files as $path) {
	$data = json_decode((string) file_get_contents($path), true);
	if (!is_array($data)) {
		continue;
	}
	$issue = (string) ($data['issue'] ?? '');
	if ($issue === '' || !in_array($issue, $only, true)) {
		continue;
	}
	$byIssue[$issue][] = ['path' => $path, 'data' => $data];
}

fwrite(
	STDOUT,
	sprintf(
		"ACNews import: press=%d source=%s mode=%s issues=%d\n",
		$pressId,
		$source,
		$dryRun ? 'dry-run' : 'APPLY',
		count($byIssue)
	)
);

$created = $updated = $skipped = $tagLinks = $catLinks = 0;

foreach ($only as $issueKey) {
	$articles = $byIssue[$issueKey] ?? [];
	if ($articles === []) {
		fwrite(STDERR, "Issue {$issueKey}: no JSON\n");
		continue;
	}
	usort($articles, static function ($a, $b) {
		return strcmp((string) ($a['data']['article_id'] ?? ''), (string) ($b['data']['article_id'] ?? ''));
	});
	$date = ACN_ISSUE_DATES[$issueKey] ?? time();
	$issue = acn_find_or_create_issue($db, $pressId, $issueKey, $date, $dryRun);
	$issueId = (int) ($issue['id'] ?? 0);
	fwrite(STDOUT, "\n== #{$issueKey} issue_id={$issueId} articles=" . count($articles) . " ==\n");

	$toc = 0;
	foreach ($articles as $item) {
		$toc++;
		$data = $item['data'];
		$number = 1 + ($toc - 1) * 10;
		$titleRu = trim((string) ($data['title_ru'] ?? ''));
		$titleEn = trim((string) ($data['title_en'] ?? ''));
		$bodyRu = (string) ($data['body_ru'] ?? '');
		$bodyEn = (string) ($data['body_en'] ?? '');
		$plainRu = (string) ($data['body_ru_plain'] ?? '');
		$plainEn = (string) ($data['body_en_plain'] ?? '');
		$metaRu = acn_clip_meta((string) ($data['meta_description_ru'] ?? ''));
		$metaEn = acn_clip_meta((string) ($data['meta_description_en'] ?? ''));
		$tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];
		$categoryIds = is_array($data['category_ids'] ?? null) ? $data['category_ids'] : [];

		if ($titleRu === '' || trim($bodyRu) === '') {
			fwrite(STDERR, "  SKIP empty title/body {$item['path']}\n");
			$skipped++;
			continue;
		}

		$existingId = 0;
		if ($issueId > 0) {
			$zEx = db_select(
				$db,
				'SELECT id FROM articles WHERE id_issue=? AND number=? LIMIT 1',
				'ii',
				$issueId,
				$number
			);
			$ex = $zEx ? $zEx->fetch_assoc() : null;
			$existingId = $ex ? (int) $ex['id'] : 0;
		}

		$action = 'create';
		if ($existingId > 0) {
			if (!$force) {
				fwrite(STDOUT, "  skip #{$number} id={$existingId} {$titleRu}\n");
				$skipped++;
				continue;
			}
			$action = 'update';
		}

		$seed = [
			'id' => $existingId,
			'id_issue' => $issueId,
			'title' => $titleRu,
			'title_eng' => $titleEn,
			'meta_description_ru' => $metaRu,
			'meta_description_en' => $metaEn,
		];
		$slugs = ezn_admin_resolve_article_slugs(
			$db,
			$titleRu,
			$titleEn !== '' ? $titleEn : $titleRu,
			static fn (): string => ezn_default_article_ru($seed),
			static fn (): string => ezn_default_article_en($seed),
			$existingId,
			max(0, $issueId)
		);

		fwrite(
			STDOUT,
			sprintf(
				"  %s #%d %s | md_ru=%d md_en=%d plain_ru=%d plain_en=%d tags=%d cats=%d\n",
				$action,
				$number,
				$titleRu,
				strlen($bodyRu),
				strlen($bodyEn),
				strlen($plainRu),
				strlen($plainEn),
				count($tags),
				count($categoryIds)
			)
		);

		if ($dryRun) {
			if ($action === 'create') {
				$created++;
			} else {
				$updated++;
			}
			$tagLinks += count($tags);
			$catLinks += count($categoryIds);
			continue;
		}

		$aid = $existingId;
		$textType = ACN_TEXT_TYPE;
		if ($action === 'create') {
			$ok = db_exec(
				$db,
				'INSERT INTO articles '
				. '(id_issue, title, temp, date, views, number, title_eng, file, name, dt, id_press, '
				. 'text_type, text_type_ru, text_type_en, text_ru, text_en, '
				. 'meta_description_ru, meta_description_en, slug_ru, slug_en) '
				. 'VALUES (?,?,0,?,0,?,?,?,?,0,?,?,?,?,?,?,?,?,?,?)',
				'isiisssiiiissssss',
				$issueId,
				$titleRu,
				$date,
				$number,
				$titleEn,
				'',
				'',
				$pressId,
				$textType,
				$textType,
				$textType,
				$bodyRu,
				$bodyEn,
				$metaRu,
				$metaEn,
				$slugs['slug_ru'],
				$slugs['slug_en']
			);
			if (!$ok) {
				fwrite(STDERR, "  FAIL insert #{$number}\n");
				continue;
			}
			$aid = (int) mysqli_insert_id($db);
			$created++;
			activity_log($db, [
				'verb' => 'created',
				'object_type' => 'article',
				'object_id' => $aid,
				'parent_type' => 'issue',
				'parent_id' => $issueId,
				'action' => 'article.created',
				'event_scope' => ACTIVITY_SCOPE_CONTENT,
				'is_public' => 1,
				'title_ru' => $titleRu,
				'title_en' => $titleEn !== '' ? $titleEn : $titleRu,
				'url_ru' => '/article.php?id=' . $aid,
				'meta' => ['source' => ACN_SOURCE, 'issue' => $issueKey, 'article_id' => $data['article_id'] ?? ''],
			]);
		} else {
			$ok = db_exec(
				$db,
				'UPDATE articles SET title=?, title_eng=?, number=?, text_type=?, text_type_ru=?, text_type_en=?, '
				. 'text_ru=?, text_en=?, meta_description_ru=?, meta_description_en=?, slug_ru=?, slug_en=?, id_press=? '
				. 'WHERE id=? AND id_issue=? LIMIT 1',
				'ssiiiissssssiii',
				$titleRu,
				$titleEn,
				$number,
				$textType,
				$textType,
				$textType,
				$bodyRu,
				$bodyEn,
				$metaRu,
				$metaEn,
				$slugs['slug_ru'],
				$slugs['slug_en'],
				$pressId,
				$aid,
				$issueId
			);
			if (!$ok) {
				fwrite(STDERR, "  FAIL update #{$number} id={$aid}\n");
				continue;
			}
			$updated++;
		}

		$articleRow = [
			'id' => $aid,
			'title' => $titleRu,
			'title_eng' => $titleEn,
			'number' => $number,
			'temp' => 0,
			'views' => 0,
			'file' => '',
			'name' => '',
			'date' => $date,
			'text_ru' => $bodyRu,
			'text_en' => $bodyEn,
			'text_type' => $textType,
			'text_type_ru' => $textType,
			'text_type_en' => $textType,
			'meta_description_ru' => $metaRu,
			'meta_description_en' => $metaEn,
			'slug_ru' => $slugs['slug_ru'],
			'slug_en' => $slugs['slug_en'],
		];
		acn_sync_v2_with_plain($db, $pressId, $issueId, $articleRow, $plainRu, $plainEn);

		foreach ($tags as $tag) {
			$name = is_array($tag) ? (string) ($tag['tag_name'] ?? '') : (string) $tag;
			$alias = is_array($tag) ? (string) ($tag['tag_alias'] ?? '') : '';
			$tagId = acn_find_or_create_tag($db, $name, $alias, false);
			if ($tagId > 0) {
				acn_link_tag($db, $aid, $tagId, false);
				$tagLinks++;
			}
		}
		$catLinks += acn_link_categories($db, $aid, $categoryIds, false);
		article_search_text_refresh($db, $aid);
	}
}

if (!$dryRun) {
	acn_queue_manticore_reindex();
	activity_batch_finalize($db);
	$maxNum = 0;
	$maxDate = 0;
	$zMeta = db_select(
		$db,
		'SELECT MAX(CAST(title AS UNSIGNED)) AS mx, MAX(date) AS md FROM issue'
		. ' WHERE id_press=? AND title REGEXP ? AND date>0',
		'is',
		$pressId,
		'^[0-9]+$'
	);
	if ($zMeta && ($meta = $zMeta->fetch_assoc())) {
		$maxNum = (int) ($meta['mx'] ?? 0);
		$maxDate = (int) ($meta['md'] ?? 0);
	}
	// bump press counters + declared numbers / years_to
	db_exec(
		$db,
		'UPDATE press SET online_issues=(SELECT COUNT(*) FROM issue WHERE id_press=?),'
		. ' online_articles=(SELECT COUNT(*) FROM articles a JOIN issue i ON i.id=a.id_issue WHERE i.id_press=? AND a.temp=0),'
		. ' numbers=IF(? > numbers, ?, numbers),'
		. ' years_to=IF(? > years_to, ?, years_to)'
		. ' WHERE id=? LIMIT 1',
		'iiiiiii',
		$pressId,
		$pressId,
		$maxNum,
		$maxNum,
		$maxDate,
		$maxDate,
		$pressId
	);
	if (ezines_v2_ready($db)) {
		$zPressFresh = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
		$pressFresh = $zPressFresh ? $zPressFresh->fetch_assoc() : null;
		if (is_array($pressFresh)) {
			ezines_v2_sync_press($db, $pressFresh, 'ru');
		}
	}
}

fwrite(
	STDOUT,
	sprintf(
		"\nDone created=%d updated=%d skipped=%d tag_links=%d cat_links=%d\n",
		$created,
		$updated,
		$skipped,
		$tagLinks,
		$catLinks
	)
);
exit(0);
