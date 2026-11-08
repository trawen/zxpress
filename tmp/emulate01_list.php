<?php
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require '/home/zxpress/web/zxpress.ru/public_html/init.inc';

$issueId = (int) ($argv[1] ?? 456);
$q = $db->query(
    'SELECT a.id, a.number, a.temp, a.text_type,
            CHAR_LENGTH(COALESCE(a.text_ru, \'\')) AS ru_len,
            CHAR_LENGTH(COALESCE(a.text_en, \'\')) AS en_len,
            a.title, a.slug_ru, a.slug_en
     FROM articles a
     WHERE a.id_issue=' . $issueId . '
     ORDER BY a.number, a.id'
);
while ($r = $q->fetch_assoc()) {
    $title = preg_replace('/\s+/', ' ', strip_tags((string) $r['title']));
    echo $r['id'] . "\t#" . $r['number'] . "\ttemp=" . $r['temp']
        . "\tru=" . $r['ru_len'] . "\ten=" . $r['en_len']
        . "\t" . mb_substr($title, 0, 50) . "\t" . $r['slug_ru'] . "\n";
}
