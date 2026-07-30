<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/article_text_render.php';

//error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);
if (!$id) {$id = 1;}

$months = array(
		"01"=>"января",
		"02"=>"февраля",
		"03"=>"марта",
		"04"=>"апреля",
		"05"=>"мая",
		"06"=>"июня",
		"07"=>"июля",
		"08"=>"августа",
		"09"=>"сентября",
		"10"=>"октября",
		"11"=>"ноября",
		"12"=>"декабря");

//$smarty->debugging = true;

$stmt = mysqli_prepare($db, "SELECT * FROM articles, issue WHERE articles.id=? AND issue.id=articles.id_issue");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$issue = mysqli_fetch_array($z);
$issue['date'] = date("d ".$months[date("m", $issue['date'])]." Y", $issue['date'] );
$smarty->assign('issue', $issue);

$id_issue = intval($issue['id']);
$stmt = mysqli_prepare($db, "SELECT * FROM issue, press, cities WHERE issue.id=? AND press.id=issue.id_press AND press.city=cities.id");
mysqli_stmt_bind_param($stmt, "i", $id_issue);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$press = mysqli_fetch_array($z);
$smarty->assign('press', $press);


$stmt = mysqli_prepare($db, "SELECT * FROM articles WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$z = mysqli_stmt_get_result($stmt);
$article = mysqli_fetch_array($z);
if ($article['temp'] == 0) {
	$raw = (string) ($article['text_ru'] ?? '');
	if ($raw === '') {
		$articlePath = realpath(zx_storage_path('articles', (string) $article['id']));
		$allowedDir = realpath(zx_storage_dir('articles'));
		if ($articlePath && $allowedDir && strpos($articlePath, $allowedDir . DIRECTORY_SEPARATOR) === 0) {
			$raw = (string) file_get_contents($articlePath);
		}
	}
	$rendered = ezn_render_article_body($raw, (int) ($article['text_type'] ?? 0));
	$article['text'] = ezn_article_root_urls($rendered['html']);
	$smarty->assign('article', $article);
	$smarty->assign('article_text_mode', $rendered['mode']);
	$smarty->assign('article_text_use_pre', $rendered['use_pre'] ? 1 : 0);
	$smarty->assign('article_text_mono', $rendered['mono'] ? 1 : 0);
}
else {

	echo "Запрещенная информация! :(";
	exit;

}

$tit = strip_tags($article['title']);
$tit = str_replace ( "\r", " " , $tit);
$tit = str_replace ( "\n", " " , $tit);
$tit = str_replace ( "\t", " " , $tit);
$tit = str_replace ( "  ", " " , $tit);

$smarty->assign('title', $press['title']." ".$issue['title']." - ".$tit );




$smarty->assign('id_article', $id);
$smarty->assign('keywords', $keywords);
$smarty->display('print.tpl');

?>
