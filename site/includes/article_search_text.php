<?php
/**
 * Prepare article body text for Manticore fulltext indexing.
 *
 * Rules:
 * - Prefer markdown (DB text_type=markdown, or content-store/articles-md/{id}.md);
 *   strip markdown to plain text.
 * - Otherwise use plain/html body; undo hard hyphenation across line breaks.
 * - Always strip UTF-8 pseudographics (box drawing / block elements / etc.).
 */

declare(strict_types=1);

require_once __DIR__ . '/article_text_render.php';
require_once __DIR__ . '/storage_paths.php';

/**
 * UTF-8 box drawing, block elements, geometric shapes, braille, soft hyphen.
 */
function article_search_strip_pseudographics(string $s): string
{
	if ($s === '') {
		return '';
	}
	$out = preg_replace(
		'/[\x{00AD}\x{2500}-\x{259F}\x{25A0}-\x{25FF}\x{2800}-\x{28FF}]/u',
		'',
		$s
	);
	if (!is_string($out)) {
		$out = $s;
	}
	// Drop lines that are empty or only leftover ASCII art scaffolding.
	$lines = preg_split("/\r\n|\n|\r/", $out) ?: [];
	$kept = [];
	foreach ($lines as $line) {
		$trim = trim($line);
		if ($trim === '') {
			$kept[] = '';
			continue;
		}
		if (preg_match('/^[\p{Z}\p{P}\p{S}\/_\\\\|`~^=*\-+#]+$/u', $trim)) {
			continue;
		}
		$kept[] = $line;
	}
	$joined = implode("\n", $kept);
	$joined = preg_replace("/\n{3,}/", "\n\n", $joined) ?? $joined;

	return trim($joined);
}

/**
 * Join magazine-style hard wraps: "re-\nleased" → "released".
 */
function article_search_join_hard_hyphens(string $s): string
{
	if ($s === '') {
		return '';
	}
	$out = preg_replace(
		'/([0-9A-Za-zÀ-ÖØ-öø-ÿА-Яа-яЁё])-\r?\n[ \t]*/u',
		'$1',
		$s
	);

	return is_string($out) ? $out : $s;
}

/**
 * Markdown → plain text (keep link/code textual content).
 */
function article_search_strip_markdown(string $s): string
{
	if ($s === '') {
		return '';
	}
	$pd = new Parsedown();
	$pd->setSafeMode(false);
	$pd->setBreaksEnabled(true);
	$html = $pd->text($s);
	$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	$text = str_replace("\xc2\xa0", ' ', $text);

	return trim($text);
}

/**
 * Normalize plain/html body before indexing.
 */
function article_search_strip_markup_plain(string $s): string
{
	if ($s === '') {
		return '';
	}
	if (preg_match('/<[a-zA-Z][^>]*>/', $s)) {
		$s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}
	$s = article_search_join_hard_hyphens($s);

	return trim($s);
}

/**
 * Apply source-type prep + pseudographics cleanup.
 */
function article_search_prepare_source(string $raw, bool $isMarkdown): string
{
	$text = $isMarkdown
		? article_search_strip_markdown($raw)
		: article_search_strip_markup_plain($raw);
	$text = article_search_strip_pseudographics($text);
	$text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
	$text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;
	$text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

	return trim($text);
}

function article_search_read_body_from_disk(int $articleId, bool $isEng): string
{
	if ($articleId <= 0) {
		return '';
	}
	$baseDir = false;
	if ($isEng) {
		$engDir = realpath(zx_storage_dir('articles_eng'));
		if ($engDir !== false) {
			$engCandidate = $engDir . '/' . $articleId;
			$engResolved = realpath($engCandidate);
			if ($engResolved !== false && is_file($engResolved)
				&& strpos($engResolved, $engDir . DIRECTORY_SEPARATOR) === 0) {
				$baseDir = $engDir;
			}
		}
	}
	if ($baseDir === false) {
		$baseDir = realpath(zx_storage_dir('articles'));
	}
	if ($baseDir === false) {
		return '';
	}
	$candidate = $baseDir . '/' . $articleId;
	$resolved = realpath($candidate);
	if ($resolved !== false && is_file($resolved)
		&& strpos($resolved, $baseDir . DIRECTORY_SEPARATOR) === 0) {
		return (string) file_get_contents($resolved);
	}

	return '';
}

function article_search_read_markdown_variant(int $articleId): string
{
	if ($articleId <= 0) {
		return '';
	}
	$baseDir = realpath(zx_storage_dir('articles_md'));
	if ($baseDir === false) {
		return '';
	}
	$candidate = $baseDir . '/' . $articleId . '.md';
	$resolved = realpath($candidate);
	if ($resolved === false || !is_file($resolved)
		|| strpos($resolved, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
		return '';
	}

	return trim((string) file_get_contents($resolved));
}

/**
 * @param array<string,mixed> $article
 * @return array{0:string,1:bool} raw body, is_markdown
 */
function article_search_resolve_lang_body(array $article, bool $isEng): array
{
	$id = (int) ($article['id'] ?? 0);
	$dbText = $isEng
		? trim((string) ($article['text_en'] ?? ''))
		: trim((string) ($article['text_ru'] ?? ''));
	$type = ezn_article_text_type_for_lang($article, $isEng);

	if ($type === EZN_TEXT_TYPE_MARKDOWN && $dbText !== '') {
		return [$dbText, true];
	}

	$mdFile = article_search_read_markdown_variant($id);
	if ($mdFile !== '') {
		return [$mdFile, true];
	}

	if ($dbText !== '') {
		return [$dbText, $type === EZN_TEXT_TYPE_MARKDOWN];
	}

	$disk = trim(article_search_read_body_from_disk($id, $isEng));
	if ($disk === '' && $isEng) {
		$disk = trim(article_search_read_body_from_disk($id, false));
	}

	return [$disk, false];
}

/**
 * @param array<string,mixed> $article must include id, text_*, text_type*
 */
function article_search_build_text(array $article): string
{
	$parts = [];
	foreach ([false, true] as $isEng) {
		[$raw, $isMd] = article_search_resolve_lang_body($article, $isEng);
		if ($raw === '') {
			continue;
		}
		$prepared = article_search_prepare_source($raw, $isMd);
		if ($prepared !== '') {
			$parts[] = $prepared;
		}
	}

	return trim(implode("\n\n", array_unique($parts)));
}

function article_search_text_column_exists(mysqli $db): bool
{
	static $exists = null;
	if ($exists !== null) {
		return $exists;
	}
	$z = $db->query("SHOW COLUMNS FROM articles LIKE 'search_text'");
	$exists = $z instanceof mysqli_result && $z->num_rows > 0;

	return $exists;
}

/**
 * Recompute and store articles.search_text for one row.
 */
function article_search_text_refresh(mysqli $db, int $articleId): bool
{
	if ($articleId <= 0 || !article_search_text_column_exists($db)) {
		return false;
	}
	$stmt = $db->prepare(
		'SELECT id, text_ru, text_en, text_type, text_type_ru, text_type_en '
		. 'FROM articles WHERE id=? LIMIT 1'
	);
	if (!$stmt) {
		return false;
	}
	$stmt->bind_param('i', $articleId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!is_array($row)) {
		return false;
	}
	$text = article_search_build_text($row);
	$upd = $db->prepare('UPDATE articles SET search_text=? WHERE id=? LIMIT 1');
	if (!$upd) {
		return false;
	}
	$upd->bind_param('si', $text, $articleId);
	$ok = $upd->execute();
	$upd->close();

	return (bool) $ok;
}
