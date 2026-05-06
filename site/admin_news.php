<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title></head><body>';
    echo '<p>Нужна авторизация. Откройте <a href="/hyperjump.php">/hyperjump.php</a> и войдите.</p>';
    echo '</body></html>';
    exit;
}


//mysqli_query($db, "SET time_zone = 'MSK'");
//error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);

if (isset($_POST['title'])) {
    csrf_verify();

    $rawTitle = trim((string) ($_POST['title'] ?? ''));
    $title = plain_text_normalize_for_storage($rawTitle);
    log_content_plain_normalized('news.title id=' . ($id ?: 'new'), $rawTitle, $title);
    $text = $_POST['text'] ?? '';
    $rawSource = trim((string) ($_POST['source'] ?? ''));
    $source = plain_text_normalize_for_storage($rawSource);
    log_content_plain_normalized('news.source id=' . ($id ?: 'new'), $rawSource, $source);
    $date = $_POST['date'] ?? '';

    if ($id) {
        $stmt = $db->prepare("UPDATE news SET title=?, text=?, source=?, date=? WHERE id=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ssssi", $title, $text, $source, $date, $id);
            $stmt->execute();
        }
    } else {
        $views = 0;
        $stmt = $db->prepare("INSERT INTO news (`title`,`text`,`source`,`date`,`views`) VALUES (?,?,?,?,?)");
        if ($stmt) {
            $stmt->bind_param("ssssi", $title, $text, $source, $date, $views);
            $stmt->execute();
            $id = mysqli_insert_id($db);
        }
    }

    header("Location: /admin_news.php?id=$id");
    exit;
}

$news_full_defaults = [
    'title' => '',
    'text' => '',
    'date' => date('Y-m-d H:i:s'),
    'source' => '',
];

if ($id) {

    $stmt = $db->prepare("SELECT * FROM news WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $z = $stmt->get_result();
        $t = mysqli_fetch_array($z);
        if ($t) {

            //	$t['date'] = date("Y-m-d h:i", $t['date']);

            $smarty->assign('news_full', $t);
            $stmt_v = $db->prepare("UPDATE news SET views=views+1 WHERE id=?");
            if ($stmt_v) {
                $stmt_v->bind_param("i", $id);
                $stmt_v->execute();
            }
        } else {
            // ?id= несуществующей новости — шаблон всё равно читает news_full.*
            $smarty->assign('news_full', $news_full_defaults);
        }
    }
    $smarty->assign('id', $id);

} else {
    // Новая новость без ?id=
    $smarty->assign('news_full', $news_full_defaults);
}

$smarty->assign('title', 'Новости — админка');




$news = [];
$z = db_select($db, "SELECT *, UNIX_TIMESTAMP(date) as date FROM news n ORDER BY date DESC");
while ($z && ($t = mysqli_fetch_array($z))) {

    $t['date'] = date("d.m.Y", $t['date']);
     $news[] = $t;

}
$smarty->assign('news_list', $news);



include "right.php";

$smarty->display('admin_news.tpl');

// nl2p() and rusdate() moved to includes/functions.php

?>
