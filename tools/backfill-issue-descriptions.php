#!/usr/bin/env php
<?php
/**
 * Backfill issue.description_* / meta_description_* by summarizing article titles.
 *
 * Usage:
 *   php tools/backfill-issue-descriptions.php           # dry-run
 *   php tools/backfill-issue-descriptions.php --apply
 *   php tools/backfill-issue-descriptions.php --apply --force
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

const ISSUE_META_MAX_LEN = 320;
const ISSUE_DESC_MAX_TOPICS = 10;
const ISSUE_META_MAX_TOPICS = 6;

$dryRun = true;
$force = false;

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$dryRun = false;
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--force') {
		$force = true;
	} elseif ($arg === '--help' || $arg === '-h') {
		fwrite(STDOUT, "Usage: php tools/backfill-issue-descriptions.php [--dry-run|--apply] [--force]\n");
		exit(0);
	}
}

$root = dirname(__DIR__);
require_once $root . '/site/init.inc';

if (isset($db) && $db instanceof mysqli) {
	mysqli_set_charset($db, 'utf8mb4');
}

function issue_desc_truncate(string $text, int $maxLen): string
{
	$text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
	if ($text === '' || mb_strlen($text, 'UTF-8') <= $maxLen) {
		return $text;
	}

	$ellipsis = '...';
	$budget = max(1, $maxLen - strlen($ellipsis));
	$cut = mb_substr($text, 0, $budget, 'UTF-8');
	$breakAt = -1;
	foreach (['; ', ', ', ' — ', ' - ', ' '] as $sep) {
		$pos = mb_strrpos($cut, $sep, 0, 'UTF-8');
		if ($pos !== false && $pos > $breakAt) {
			$breakAt = $pos;
		}
	}
	if ($breakAt > (int) ($budget * 0.55)) {
		$cut = mb_substr($cut, 0, $breakAt, 'UTF-8');
	}

	$cut = preg_replace('/[\s.,;:\-]+$/u', '', $cut) ?? $cut;
	$result = $cut . $ellipsis;
	if (mb_strlen($result, 'UTF-8') > $maxLen) {
		$result = mb_substr($result, 0, $maxLen, 'UTF-8');
	}

	return $result;
}

function issue_desc_plain(string $title): string
{
	$plain = title_plain($title);
	return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
}

function issue_desc_is_noise(string $rubric, string $body): bool
{
	$r = mb_strtolower(trim($rubric, " \t."), 'UTF-8');
	$b = mb_strtolower(trim($body, " \t."), 'UTF-8');
	$t = $b !== '' ? $b : $r;

	if ($t === '') {
		return true;
	}
	if (preg_match('/^(реклам|advert|ads?\b)/u', $r)) {
		return true;
	}
	if (preg_match('/^(реклама и об|объявлени|advert)/u', $t)) {
		return true;
	}
	if (preg_match('/^(содержание|оглавление|contents|index)\.?$/u', $t)) {
		return true;
	}
	if (preg_match('/^(от редакции|от авторов|авторы журнала|editorial|authors)\.?$/u', $t)) {
		return true;
	}
	if (preg_match('/^(в приложении к номеру|приложение)\.?$/u', $t)) {
		return true;
	}

	return false;
}

/**
 * Pull a meaningful topic phrase from an article title.
 */
function issue_desc_extract_topic(string $title): ?string
{
	$plain = issue_desc_plain($title);
	if ($plain === '' || $plain === '.') {
		return null;
	}

	$rubric = '';
	$body = $plain;
	if (preg_match('/^(.+?)\s+[—\-–]\s+(.+)$/us', $plain, $m)) {
		$rubric = trim($m[1], " \t.");
		$body = trim($m[2], " \t.");
	}

	if (issue_desc_is_noise($rubric, $body)) {
		return null;
	}

	$body = trim($body, " \t.");

	$topic = '';

	if (mb_strlen($body, 'UTF-8') >= 8) {
		$topic = $body;
		// Keep interview/review cue when body does not already state it.
		if (
			preg_match('/^(интервью|interview|обзор|review|рецензия)/iu', $rubric)
			&& !preg_match('/^(интервью|interview|обзор|review|рецензия)/iu', $body)
		) {
			$topic = $rubric . ': ' . $body;
		}
	} elseif ($rubric !== '') {
		$topic = $rubric;
	} else {
		$topic = $body;
	}

	$topic = trim($topic, " \t.;");
	$topic = preg_replace('/\s+/u', ' ', $topic) ?? $topic;
	if (mb_strlen($topic, 'UTF-8') < 3) {
		return null;
	}

	return $topic;
}

/**
 * @param list<string> $topics
 * @return list<string>
 */
function issue_desc_dedupe(array $topics): array
{
	$out = [];
	$keys = [];
	foreach ($topics as $topic) {
		$key = mb_strtolower(preg_replace('/\s+/u', ' ', $topic) ?? $topic, 'UTF-8');
		$key = trim($key, " \t.;");
		$short = mb_substr($key, 0, 56, 'UTF-8');
		if ($short === '' || isset($keys[$short])) {
			continue;
		}
		foreach ($keys as $existing) {
			if (
				mb_strpos($existing, $short, 0, 'UTF-8') !== false
				|| mb_strpos($short, $existing, 0, 'UTF-8') !== false
			) {
				continue 2;
			}
		}
		$keys[$short] = $short;
		$out[] = $topic;
	}

	return $out;
}

/**
 * @param list<string> $parts
 */
function issue_desc_join_natural(array $parts, bool $eng): string
{
	$n = count($parts);
	if ($n === 0) {
		return '';
	}
	if ($n === 1) {
		return $parts[0];
	}
	if ($n === 2) {
		return $parts[0] . ($eng ? ' and ' : ' и ') . $parts[1];
	}
	$last = array_pop($parts);

	return implode(', ', $parts) . ($eng ? ', and ' : ' и ') . $last;
}

/**
 * @param list<string> $titles
 */
function issue_desc_summarize(array $titles, bool $eng, int $maxTopics): string
{
	$topics = [];
	foreach ($titles as $title) {
		$topic = issue_desc_extract_topic((string) $title);
		if ($topic !== null) {
			$topics[] = $topic;
		}
	}
	$topics = issue_desc_dedupe($topics);
	if ($topics === []) {
		return '';
	}
	if (count($topics) > $maxTopics) {
		$topics = array_slice($topics, 0, $maxTopics);
	}

	$list = issue_desc_join_natural($topics, $eng);
	if ($list === '') {
		return '';
	}

	if ($eng) {
		return 'This issue covers ' . $list . '.';
	}

	return 'В выпуске — ' . $list . '.';
}

/**
 * @param list<string> $titles
 */
function issue_desc_meta(
	string $pressPlain,
	string $issueNo,
	array $titles,
	bool $eng
): string {
	$topics = [];
	foreach ($titles as $title) {
		$topic = issue_desc_extract_topic((string) $title);
		if ($topic !== null) {
			$topics[] = $topic;
		}
	}
	$topics = issue_desc_dedupe($topics);
	if ($topics === []) {
		return '';
	}
	$topics = array_slice($topics, 0, ISSUE_META_MAX_TOPICS);
	$short = [];
	foreach ($topics as $topic) {
		if (mb_strlen($topic, 'UTF-8') > 72) {
			$topic = issue_desc_truncate($topic, 72);
		}
		$short[] = $topic;
	}
	$list = issue_desc_join_natural($short, $eng);
	if ($list === '') {
		return '';
	}

	if ($eng) {
		$prefix = ($pressPlain !== '' && $issueNo !== '') ? ($pressPlain . ' #' . $issueNo . ': ') : '';
	} else {
		$prefix = ($pressPlain !== '' && $issueNo !== '') ? ('«' . $pressPlain . '» #' . $issueNo . ': ') : '';
	}

	return issue_desc_truncate($prefix . $list, ISSUE_META_MAX_LEN);
}

$cols = $db->query("SHOW COLUMNS FROM `issue` LIKE 'description_ru'");
if (!$cols || $cols->num_rows === 0) {
	fwrite(STDERR, "[backfill-issue-desc] ERROR columns missing — run db/migration/issue_descriptions.sql\n");
	exit(1);
}

$where = $force
	? '1=1'
	: "(i.description_ru IS NULL OR i.description_ru='' OR i.meta_description_ru='')";

$sql = 'SELECT i.id, i.title AS issue_title, i.description_ru, i.meta_description_ru,'
	. ' p.title AS press_title'
	. ' FROM issue i'
	. ' INNER JOIN press p ON p.id=i.id_press'
	. ' WHERE ' . $where
	. ' ORDER BY i.id ASC';

$rs = $db->query($sql);
if (!$rs) {
	fwrite(STDERR, '[backfill-issue-desc] ERROR query failed: ' . $db->error . "\n");
	exit(1);
}

$stmtArts = $db->prepare(
	'SELECT title, title_eng FROM articles WHERE id_issue=? AND temp=0 ORDER BY number ASC, title ASC'
);
if (!$stmtArts) {
	fwrite(STDERR, '[backfill-issue-desc] ERROR prepare articles: ' . $db->error . "\n");
	exit(1);
}

$stmtUp = $db->prepare(
	'UPDATE issue SET description_ru=?, description_en=?, meta_description_ru=?, meta_description_en=? WHERE id=? LIMIT 1'
);
if (!$stmtUp) {
	fwrite(STDERR, '[backfill-issue-desc] ERROR prepare update: ' . $db->error . "\n");
	exit(1);
}

$updated = 0;
$skipped = 0;
$mode = $dryRun ? 'dry-run' : 'apply';
fwrite(STDOUT, "[backfill-issue-desc] mode={$mode}" . ($force ? ' force=1' : '') . "\n");

while ($issue = $rs->fetch_assoc()) {
	$issueId = (int) ($issue['id'] ?? 0);
	if ($issueId <= 0) {
		continue;
	}

	$stmtArts->bind_param('i', $issueId);
	$stmtArts->execute();
	$arts = $stmtArts->get_result();
	$titlesRu = [];
	$titlesEn = [];
	while ($art = $arts->fetch_assoc()) {
		$titlesRu[] = (string) ($art['title'] ?? '');
		$en = trim((string) ($art['title_eng'] ?? ''));
		$titlesEn[] = $en !== '' ? $en : (string) ($art['title'] ?? '');
	}

	$descriptionRu = issue_desc_summarize($titlesRu, false, ISSUE_DESC_MAX_TOPICS);
	$descriptionEn = issue_desc_summarize($titlesEn, true, ISSUE_DESC_MAX_TOPICS);
	if ($descriptionEn === '' && $descriptionRu !== '') {
		$descriptionEn = issue_desc_summarize($titlesRu, true, ISSUE_DESC_MAX_TOPICS);
	}
	if ($descriptionRu === '' && $descriptionEn === '') {
		$skipped++;
		continue;
	}

	$pressPlain = issue_desc_plain((string) ($issue['press_title'] ?? ''));
	$issueNo = trim((string) ($issue['issue_title'] ?? ''));
	$metaRu = issue_desc_meta($pressPlain, $issueNo, $titlesRu, false);
	$metaEn = issue_desc_meta($pressPlain, $issueNo, $titlesEn, true);
	if ($metaEn === '' && $metaRu !== '') {
		$metaEn = issue_desc_meta($pressPlain, $issueNo, $titlesRu, true);
	}

	fwrite(
		STDOUT,
		sprintf(
			"[backfill-issue-desc] #%d %s#%s\n  desc: %s\n  meta: %s\n",
			$issueId,
			$pressPlain,
			$issueNo,
			mb_substr($descriptionRu !== '' ? $descriptionRu : $descriptionEn, 0, 160, 'UTF-8'),
			mb_substr($metaRu !== '' ? $metaRu : $metaEn, 0, 140, 'UTF-8')
		)
	);

	if ($dryRun) {
		$updated++;
		continue;
	}

	$stmtUp->bind_param('ssssi', $descriptionRu, $descriptionEn, $metaRu, $metaEn, $issueId);
	if (!$stmtUp->execute()) {
		fwrite(STDERR, "[backfill-issue-desc] ERROR update #{$issueId}: " . $stmtUp->error . "\n");
		exit(1);
	}
	$updated++;
}

$stmtArts->close();
$stmtUp->close();
$rs->free();

fwrite(STDOUT, "[backfill-issue-desc] done updated={$updated} skipped_empty={$skipped}\n");
