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

	define('_SAPE_USER', '7211e5c73c03f9032e1b2e571f97785d2665a2f4fab9d6c52f899cacb81cd55b');

	$sapeDir = zx_storage_dir('sape');
	require_once $sapeDir . '/sape.php';
	$sape = new SAPE_client();

	// $sp1 = iconv("Windows-1251", "UTF-8", $sape->return_links(1));
	// $sp2 = iconv("Windows-1251", "UTF-8", $sape->return_links(2));
	// if ($url[0] != "/article.php") {
	// 	// Legacy SAPE-only cleanup (not unified with html_legacy_normalize — third-party markup).
	// 	$sp1 = str_replace('class="RGB16"', "", $sp1);
	// 	$sp2 = str_replace('class="RGB16"', "", $sp2);
	// }

	$smarty->assign('sape1',  $sape->return_links(1));
	$smarty->assign('sape2',  $sape->return_links(2));

	// echo $sape->return_links();

	// if (!defined('_SAPE_USER')){
	// 	define('_SAPE_USER', '7211e5c73c03f9032e1b2e571f97785d2665a2f4fab9d6c52f899cacb81cd55b');
	// }
	// require_once($_SERVER['DOCUMENT_ROOT'].'/'._SAPE_USER.'/sape.php');
	// $sape = new SAPE_client();
}
