<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/ezines_v2_admin.php';

if (!ezines_v2_ready($db)) {
    $smarty->assign('error', 'Схема ezines_v2 не применена');
    admin2_render('admin2/ezines_list.tpl', 'Эзины v2', 'ezines');
    exit;
}

$letter = trim((string) ($_GET['letter'] ?? ''));
if (mb_strlen($letter, 'UTF-8') > 1) {
    $letter = mb_substr($letter, 0, 1, 'UTF-8');
}

$rows = ezines_v2_admin_list($db, $letter);
$all = ezines_v2_admin_list($db, '');
$alpha = [];
foreach ($all as $row) {
    $ch = mb_strtoupper(mb_substr((string) ($row['title'] ?? ''), 0, 1, 'UTF-8'), 'UTF-8');
    if ($ch !== '' && preg_match('/\p{L}/u', $ch)) {
        $alpha[$ch] = true;
    }
}
$alpha = array_keys($alpha);
sort($alpha, SORT_STRING);

$smarty->assign('ezines', $rows);
$smarty->assign('alpha', $alpha);
$smarty->assign('current_letter', $letter);
admin2_render('admin2/ezines_list.tpl', 'Эзины v2', 'ezines');
