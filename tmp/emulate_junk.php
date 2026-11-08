<?php
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require '/home/zxpress/web/zxpress.ru/public_html/init.inc';

$q = $db->query(
    "SELECT a.id, a.id_issue, a.number, a.temp,
            CHAR_LENGTH(COALESCE(a.text_ru,'')) AS ru_len,
            TRIM(a.title) AS title, a.slug_ru
     FROM articles a
     JOIN issue i ON i.id=a.id_issue
     WHERE i.id_press=66
     ORDER BY a.id_issue, a.number"
);
$junk = [];
$ok = 0;
while ($r = $q->fetch_assoc()) {
    $title = trim(strip_tags((string) $r['title']));
    if ($title === '') {
        $junk[] = $r;
        echo "JUNK\t".$r['id']."\tissue=".$r['id_issue']."\t#".$r['number']."\tru=".$r['ru_len']."\t".$r['slug_ru']."\n";
    } else {
        $ok++;
    }
}
echo "OK_COUNT=$ok JUNK_COUNT=".count($junk)."\n";
