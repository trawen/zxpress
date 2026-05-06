<?php
/**
 * Sape.ru link exchange client. Included from init.inc only.
 *
 * @param Smarty $smarty
 * @param array<int, string> $url Result of explode("?", $_SERVER['REQUEST_URI'])
 */
function setup_sape($smarty, array $url): void
{
	if (defined('_SAPE_USER')) {
		return;
	}

	define('_SAPE_USER', getenv('SAPE_USER_HASH') ?: 'df148749a7a956a4334286aea4e556e8');

	$sapeDir = zx_storage_dir('sape');
	require_once $sapeDir . '/sape.php';
	$sape = new SAPE_client();

	$sp1 = iconv("Windows-1251", "UTF-8", $sape->return_links(1));
	$sp2 = iconv("Windows-1251", "UTF-8", $sape->return_links(2));
	if ($url[0] != "/article.php") {
		// Legacy SAPE-only cleanup (not unified with html_legacy_normalize — third-party markup).
		$sp1 = str_replace('class="RGB16"', "", $sp1);
		$sp2 = str_replace('class="RGB16"', "", $sp2);
	}

	$smarty->assign('sape1', $sp1);
	$smarty->assign('sape2', $sp2);
}
