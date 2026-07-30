<?php
/**
 * Render ezine article body by text_type.
 * 0=legacy (html pre), 1=text pre, 2=html pre, 3=markdown
 */

require_once __DIR__ . '/Parsedown.php';

const EZN_TEXT_TYPE_LEGACY = 0;
const EZN_TEXT_TYPE_TEXT_PRE = 1;
const EZN_TEXT_TYPE_HTML_PRE = 2;
const EZN_TEXT_TYPE_MARKDOWN = 3;

function ezn_normalize_article_text_type(int $raw): int
{
	if (in_array($raw, [EZN_TEXT_TYPE_TEXT_PRE, EZN_TEXT_TYPE_HTML_PRE, EZN_TEXT_TYPE_MARKDOWN], true)) {
		return $raw;
	}
	// Legacy 0 and unknown → html pre
	return EZN_TEXT_TYPE_HTML_PRE;
}

/**
 * Page already has one h1 (article title). Demote markdown headings by one level:
 * # → h2, ## → h3, …; h6 stays h6.
 */
function ezn_markdown_demote_headings(string $html): string
{
	for ($level = 5; $level >= 1; $level--) {
		$next = $level + 1;
		$html = (string) preg_replace(
			'#<(/?)h' . $level . '\b([^>]*)>#i',
			'<$1h' . $next . '$2>',
			$html
		);
	}
	return $html;
}

/**
 * @return array{html:string,mode:string,use_pre:bool,mono:bool}
 *   mode: text_pre|html_pre|markdown
 */
function ezn_render_article_body(string $raw, int $textType): array
{
	$type = ezn_normalize_article_text_type($textType);

	if ($type === EZN_TEXT_TYPE_MARKDOWN) {
		$pd = new Parsedown();
		$pd->setSafeMode(false);
		$pd->setBreaksEnabled(true);
		$html = ezn_markdown_demote_headings($pd->text($raw));
		return [
			'html' => $html,
			'mode' => 'markdown',
			'use_pre' => false,
			'mono' => false,
		];
	}

	if ($type === EZN_TEXT_TYPE_TEXT_PRE) {
		return [
			'html' => htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			'mode' => 'text_pre',
			'use_pre' => true,
			'mono' => true,
		];
	}

	// html pre (default / legacy): raw HTML inside <pre>, monospace
	return [
		'html' => $raw,
		'mode' => 'html_pre',
		'use_pre' => true,
		'mono' => true,
	];
}
