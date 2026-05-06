<?php
require 'init.inc';

//error_reporting(E_ALL);

include __DIR__ . '/includes/FeedWriter/Item.php';
include __DIR__ . '/includes/FeedWriter/Feed.php';
include __DIR__ . '/includes/FeedWriter/ATOM.php';
include __DIR__ . '/includes/FeedWriter/InvalidOperationException.php';

date_default_timezone_set('UTC');

use \FeedWriter\ATOM;

if (PHP_SAPI === 'cli') {
	error_log('[FIX] rss: canonical fallback (cli)');
}

$origin = zxpress_canonical_origin();
$base = rtrim($origin, '/') . '/';

//error_reporting(E_ALL);
$Feed = new ATOM();

$Feed->setTitle('ZXPRESS — источник актуальных новостей
о спектруме.');
$Feed->setLink($base . 'news');
//$Feed->setDescription('Этот тест создает RSS 2.0 канал, при помощи скрипта Universal Feed Writer');



$z = db_select($db, "SELECT *, UNIX_TIMESTAMP(date) as date FROM news ORDER BY date DESC");
while ($z && ($t = mysqli_fetch_array($z))) {

    $newItem = $Feed->createNewItem();
    $newItem->setLink($base . 'news/' . (int) ($t['id'] ?? 0) . '-' . niceurl($t['title'] ?? ''));
    $newItem->setTitle(title_plain($t['title'] ?? ''));

	$pos = mb_strpos($t['text'], "<cut>");
	$t['cut'] = $pos;
	if ($pos) {

		$t['text'] = mb_substr($t['text'], 0, $pos-1);

	}
	$t['text'] = nl2p($t['text']);

    $newItem->setDescription($t['text']);
 	$newItem->setDate($t['date']);
    $Feed->addItem($newItem);

}
echo $Feed->generateFeed();



// nl2p() and rusdate() moved to includes/functions.php

?>
