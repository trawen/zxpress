#!/usr/bin/env php
<?php
/**
 * Import Scene+ articles (DE source → RU/EN markdown i18n) into existing issues.
 *
 * Defaults: press_id=315 (Scene+). Issues 01–36 already exist.
 *
 *   php site/cli/import-scene-articles.php --dry-run
 *   php site/cli/import-scene-articles.php --apply
 *   php site/cli/import-scene-articles.php --apply --force --only=01,02
 *   php site/cli/import-scene-articles.php --apply --with-tags --with-authors
 *
 * i18n: site/cli/imports/scene_i18n/*.json (issue + NN from filename).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

const SCENE_PRESS_ID = 315;
const SCENE_SOURCE = 'migration:scene_articles';
const SCENE_TEXT_TYPE = 3; // markdown

$dryRun = true;
$force = false;
$withTags = true;
$withAuthors = true;
$pressId = SCENE_PRESS_ID;
$source = '';
$only = [];

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--force') {
		$force = true;
	} elseif ($arg === '--with-tags') {
		$withTags = true;
	} elseif ($arg === '--no-tags') {
		$withTags = false;
	} elseif ($arg === '--with-authors') {
		$withAuthors = true;
	} elseif ($arg === '--no-authors') {
		$withAuthors = false;
	} elseif (str_starts_with($arg, '--press-id=')) {
		$pressId = max(0, (int) substr($arg, 11));
	} elseif (str_starts_with($arg, '--source=')) {
		$source = rtrim(substr($arg, 9), '/');
	} elseif (str_starts_with($arg, '--only=')) {
		foreach (explode(',', substr($arg, 7)) as $part) {
			$p = trim($part);
			if ($p === '') {
				continue;
			}
			if (preg_match('/^\d+$/', $p)) {
				$p = str_pad($p, 2, '0', STR_PAD_LEFT);
			}
			$only[] = $p;
		}
		$only = array_values(array_unique($only));
		if ($only === []) {
			fwrite(STDERR, "--only must list issue keys like 01,02\n");
			exit(1);
		}
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(
			STDOUT,
			"Usage: {$argv[0]} [--dry-run|--apply] [--force] [--with-tags|--no-tags] "
			. "[--with-authors|--no-authors] [--press-id=315] [--source=DIR] [--only=01,02]\n"
		);
		exit(0);
	} else {
		fwrite(STDERR, "Unknown arg: {$arg}\n");
		exit(1);
	}
}

$_SERVER['REQUEST_URI'] = '/cli/import-scene-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-scene';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/admin_helpers.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';
require_once $siteRoot . '/includes/article_search_text.php';
require_once $siteRoot . '/includes/authors_slugs.php';

if ($source === '') {
	$candidates = [
		$siteRoot . '/cli/imports/scene_i18n',
		dirname($siteRoot) . '/db/migration/scene_articles/i18n',
		dirname($siteRoot) . '/local/work/SCENE_texts/articles',
	];
	foreach ($candidates as $cand) {
		if (is_dir($cand)) {
			$source = $cand;
			break;
		}
	}
}
if ($source === '' || !is_dir($source)) {
	fwrite(STDERR, "Need --source=DIR with scene_i18n *.json\n");
	exit(1);
}

function scene_queue_manticore_reindex(): void
{
	$dir = zx_data_root() . '/manticore-reindex';
	@mkdir($dir, 0775, true);
	@file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function scene_clip_meta(string $s): string
{
	$s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
	if (function_exists('mb_substr')) {
		return mb_substr($s, 0, 255, 'UTF-8');
	}

	return substr($s, 0, 255);
}

/**
 * Filename: 01_02_INHALT_1.json → issue=01, number=2
 *
 * @return array{issue:string,number:int}|null
 */
function scene_parse_stem(string $stem): ?array
{
	if (!preg_match('/^(\d{2})_(\d{2})_/', $stem, $m)) {
		return null;
	}

	return ['issue' => $m[1], 'number' => (int) $m[2]];
}

function scene_find_issue(mysqli $db, int $pressId, string $issueKey): ?array
{
	$titles = [$issueKey];
	$trimmed = ltrim($issueKey, '0');
	if ($trimmed !== '' && $trimmed !== $issueKey) {
		$titles[] = $trimmed;
	}
	foreach ($titles as $title) {
		$z = db_select(
			$db,
			'SELECT * FROM issue WHERE id_press=? AND title=? LIMIT 1',
			'is',
			$pressId,
			$title
		);
		$row = $z ? $z->fetch_assoc() : null;
		if (is_array($row)) {
			return $row;
		}
	}

	return null;
}

function scene_find_or_create_tag(mysqli $db, string $tagName, string $alias, bool $dryRun): int
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

function scene_link_tag(mysqli $db, int $articleId, int $tagId, bool $dryRun): void
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

function scene_link_categories(mysqli $db, int $articleId, array $categoryIds, bool $dryRun): int
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

function scene_find_or_create_author(mysqli $db, string $nick, bool $dryRun): int
{
	$nick = trim($nick);
	if ($nick === '') {
		return 0;
	}
	if (function_exists('mb_substr')) {
		$nick = mb_substr($nick, 0, 100, 'UTF-8');
	} else {
		$nick = substr($nick, 0, 100);
	}
	$z = db_select(
		$db,
		'SELECT id FROM authors WHERE nickname=? OR nickname=? LIMIT 1',
		'ss',
		$nick,
		mb_strtolower($nick)
	);
	// case-insensitive fallback
	if (!$z || !($row = $z->fetch_assoc())) {
		$z2 = db_select(
			$db,
			'SELECT id FROM authors WHERE LOWER(nickname)=LOWER(?) LIMIT 1',
			's',
			$nick
		);
		$row = $z2 ? $z2->fetch_assoc() : null;
	}
	if ($row) {
		return (int) $row['id'];
	}
	if ($dryRun) {
		return -1;
	}
	$slugs = authors_resolve_slugs($db, '', '', $nick, $nick, $nick, 0);
	db_exec(
		$db,
		'INSERT INTO authors (nickname, name_ru, name_en, group_name, slug_ru, slug_en, country_id, city_id, user_id, is_active) '
		. 'VALUES (?,?,?,?,?,?,NULL,NULL,NULL,1)',
		'ssssss',
		$nick,
		$nick,
		$nick,
		'',
		$slugs['slug_ru'],
		$slugs['slug_en']
	);

	return (int) mysqli_insert_id($db);
}

function scene_link_author(mysqli $db, int $articleId, int $authorId, bool $dryRun): void
{
	if ($articleId <= 0 || $authorId <= 0) {
		return;
	}
	$z = db_select(
		$db,
		'SELECT id FROM authors_articles WHERE id_article=? AND id_author=? LIMIT 1',
		'ii',
		$articleId,
		$authorId
	);
	if ($z && $z->fetch_assoc()) {
		return;
	}
	if ($dryRun) {
		return;
	}
	db_exec(
		$db,
		'INSERT INTO authors_articles (id_author, id_article) VALUES (?,?)',
		'ii',
		$authorId,
		$articleId
	);
}

function scene_sync_v2(mysqli $db, int $pressId, int $issueId, array $articleRow, string $plainDe): void
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
			$ezineId = ezines_v2_sync_press($db, $pressRow, 'en');
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
	$aid = (int) ($articleRow['id'] ?? 0);
	$v2ArticleId = ezines_v2_sync_article($db, $articleRow, $v2IssueId, 'en');
	if ($v2ArticleId > 0) {
		ezines_v2_sync_article_authors($db, $aid, $v2ArticleId);
	}
	unset($plainDe);
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
	$stem = basename($path, '.json');
	$parsed = scene_parse_stem($stem);
	$raw = file_get_contents($path);
	if ($raw === false) {
		continue;
	}
	$data = json_decode($raw, true);
	if (!is_array($data)) {
		continue;
	}
	$issue = (string) ($data['issue'] ?? ($parsed['issue'] ?? ''));
	if ($issue !== '' && preg_match('/^\d+$/', $issue)) {
		$issue = str_pad($issue, 2, '0', STR_PAD_LEFT);
	}
	$number = (int) ($parsed['number'] ?? 0);
	if ($issue === '' || $number <= 0) {
		fwrite(STDERR, "SKIP bad stem: {$stem}\n");
		continue;
	}
	if ($only !== [] && !in_array($issue, $only, true)) {
		continue;
	}
	if (!isset($data['body_ru']) || trim((string) $data['body_ru']) === '') {
		continue;
	}
	$byIssue[$issue][] = [
		'stem' => $stem,
		'number' => $number,
		'path' => $path,
		'data' => $data,
	];
}
ksort($byIssue, SORT_STRING);

fwrite(
	STDOUT,
	sprintf(
		"Scene+ import: press=%d (%s) source=%s mode=%s issues=%d articles=%d tags=%s authors=%s\n",
		$pressId,
		(string) $press['title'],
		$source,
		$dryRun ? 'dry-run' : 'APPLY',
		count($byIssue),
		array_sum(array_map('count', $byIssue)),
		$withTags ? 'yes' : 'no',
		$withAuthors ? 'yes' : 'no'
	)
);

$created = $updated = $skipped = $tagLinks = $catLinks = $authorLinks = 0;

foreach ($byIssue as $issueKey => $articles) {
	// PHP casts numeric string keys ("10") to int — keep zero-padded strings.
	$issueKey = is_int($issueKey) ? sprintf('%02d', $issueKey) : (string) $issueKey;
	if (preg_match('/^\d+$/', $issueKey)) {
		$issueKey = str_pad($issueKey, 2, '0', STR_PAD_LEFT);
	}
	usort($articles, static fn ($a, $b) => $a['number'] <=> $b['number']);
	$issue = scene_find_issue($db, $pressId, $issueKey);
	if ($issue === null) {
		fwrite(STDERR, "Issue key={$issueKey}: not found for press {$pressId}\n");
		$skipped += count($articles);
		continue;
	}
	$issueId = (int) $issue['id'];
	$articleDate = (int) ($issue['date'] ?? 0);
	if ($articleDate <= 0) {
		$articleDate = time();
	}
	fwrite(STDOUT, "\n== #{$issueKey} issue_id={$issueId} articles=" . count($articles) . " ==\n");

	foreach ($articles as $item) {
		$data = $item['data'];
		$number = $item['number'];
		$titleRu = trim((string) ($data['title_ru'] ?? ''));
		$titleEn = trim((string) ($data['title_en'] ?? ''));
		$bodyRu = (string) ($data['body_ru'] ?? '');
		$bodyEn = (string) ($data['body_en'] ?? '');
		$plainDe = (string) ($data['body_de_plain'] ?? '');
		$metaRu = scene_clip_meta((string) ($data['meta_description_ru'] ?? ''));
		$metaEn = scene_clip_meta((string) ($data['meta_description_en'] ?? ''));
		$tags = $withTags && is_array($data['tags'] ?? null) ? $data['tags'] : [];
		$categoryIds = is_array($data['category_ids'] ?? null) ? $data['category_ids'] : [];
		$authors = $withAuthors && is_array($data['authors'] ?? null) ? $data['authors'] : [];

		if ($titleRu === '' || trim($bodyRu) === '') {
			fwrite(STDERR, "  SKIP #{$number} {$item['stem']}: empty title_ru/body_ru\n");
			$skipped++;
			continue;
		}

		$zEx = db_select(
			$db,
			'SELECT id FROM articles WHERE id_issue=? AND number=? LIMIT 1',
			'ii',
			$issueId,
			$number
		);
		$ex = $zEx ? $zEx->fetch_assoc() : null;
		$existingId = $ex ? (int) $ex['id'] : 0;

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
			$max = max(0, $issueId)
		);

		fwrite(
			STDOUT,
			sprintf(
				"  %s #%d %s | ru=%d en=%d tags=%d cats=%d authors=%d\n",
				$action,
				$number,
				$titleRu,
				strlen($bodyRu),
				strlen($bodyEn),
				count($tags),
				count($categoryIds),
				count($authors)
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
			$authorLinks += count($authors);
			continue;
		}

		$aid = $existingId;
		$textType = SCENE_TEXT_TYPE;
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
				$articleDate,
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
				'meta' => [
					'source' => SCENE_SOURCE,
					'issue' => $issueKey,
					'article_id' => $data['article_id'] ?? $item['stem'],
				],
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
			activity_log($db, [
				'verb' => 'updated',
				'object_type' => 'article',
				'object_id' => $aid,
				'parent_type' => 'issue',
				'parent_id' => $issueId,
				'action' => 'article.updated',
				'event_scope' => ACTIVITY_SCOPE_CONTENT,
				'is_public' => 1,
				'title_ru' => $titleRu,
				'title_en' => $titleEn !== '' ? $titleEn : $titleRu,
				'url_ru' => '/article.php?id=' . $aid,
				'meta' => ['source' => SCENE_SOURCE, 'issue' => $issueKey, 'stem' => $item['stem']],
			]);
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
			'date' => $articleDate,
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

		foreach ($tags as $tag) {
			$name = is_array($tag) ? (string) ($tag['tag_name'] ?? '') : (string) $tag;
			$alias = is_array($tag) ? (string) ($tag['tag_alias'] ?? '') : '';
			$tagId = scene_find_or_create_tag($db, $name, $alias, false);
			if ($tagId > 0) {
				scene_link_tag($db, $aid, $tagId, false);
				$tagLinks++;
			}
		}
		$catLinks += scene_link_categories($db, $aid, $categoryIds, false);

		foreach ($authors as $author) {
			$nick = is_array($author) ? (string) ($author['nickname'] ?? $author['name'] ?? '') : (string) $author;
			$authorId = scene_find_or_create_author($db, $nick, false);
			if ($authorId > 0) {
				scene_link_author($db, $aid, $authorId, false);
				$authorLinks++;
			}
		}

		scene_sync_v2($db, $pressId, $issueId, $articleRow, $plainDe);
		article_search_text_refresh($db, $aid);
	}
}

if (!$dryRun) {
	scene_queue_manticore_reindex();
	if (function_exists('activity_batch_finalize')) {
		activity_batch_finalize($db);
	}
	db_exec(
		$db,
		'UPDATE press SET online_issues=(SELECT COUNT(*) FROM issue WHERE id_press=?),'
		. ' online_articles=(SELECT COUNT(*) FROM articles a JOIN issue i ON i.id=a.id_issue WHERE i.id_press=? AND a.temp=0)'
		. ' WHERE id=? LIMIT 1',
		'iii',
		$pressId,
		$pressId,
		$pressId
	);
	if (ezines_v2_ready($db)) {
		$zPressFresh = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
		$pressFresh = $zPressFresh ? $zPressFresh->fetch_assoc() : null;
		if (is_array($pressFresh)) {
			ezines_v2_sync_press($db, $pressFresh, 'en');
		}
	}
}

fwrite(
	STDOUT,
	sprintf(
		"\nDone created=%d updated=%d skipped=%d tag_links=%d cat_links=%d author_links=%d%s\n",
		$created,
		$updated,
		$skipped,
		$tagLinks,
		$catLinks,
		$authorLinks,
		$dryRun ? ' (dry-run)' : ''
	)
);
exit(0);
