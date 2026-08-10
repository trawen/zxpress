<?php

/** Absolute filesystem path to the new-design stylesheet. */
function smn_stylesheet_path(): string
{
	return dirname(__DIR__) . '/img/snailmail-new.css';
}

/** Cache-busted public URL for snailmail-new.css (fallback / debugging). */
function smn_stylesheet_href(): string
{
	$path = smn_stylesheet_path();
	$ver = is_readable($path) ? (string) filemtime($path) : '0';

	return '/img/snailmail-new.css?' . rawurlencode($ver);
}

/**
 * Read snailmail-new.css and rewrite relative asset URLs for inline <style>.
 * Paths like url("../fonts/…") and url("../img/…") must become root-absolute.
 */
function smn_stylesheet_inline_css(): string
{
	static $css = null;
	if ($css !== null) {
		return $css;
	}

	$path = smn_stylesheet_path();
	if (!is_readable($path)) {
		$css = '';
		return $css;
	}

	$raw = (string) file_get_contents($path);
	// Root-absolute assets so inline CSS works on /ru/…/nested/paths.
	$raw = preg_replace('#url\(\s*(["\']?)\.\./fonts/#', 'url($1/fonts/', $raw) ?? $raw;
	$raw = preg_replace('#url\(\s*(["\']?)\.\./img/#', 'url($1/img/', $raw) ?? $raw;
	// Guard against premature </style> breakout.
	$raw = str_replace('</style', '<\/style', $raw);

	$css = $raw;
	return $css;
}

/**
 * Smarty {smn_styles} — inline full stylesheet to avoid FOUC / render-blocking fetch.
 *
 * @param array<string, mixed> $params
 * @param Smarty_Internal_Template $template
 */
function smarty_function_smn_styles($params, $template): string
{
	$boot = '<script>(function(){try{var t=localStorage.getItem("smn-theme");if(t==="dark"||t==="light"){document.documentElement.setAttribute("data-theme",t);}}catch(e){}})();</script>';
	$css = smn_stylesheet_inline_css();
	if ($css === '') {
		$href = htmlspecialchars(smn_stylesheet_href(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return $boot . '<link rel="stylesheet" href="' . $href . '">';
	}

	return $boot . '<style id="smn-css">' . $css . '</style>';
}
