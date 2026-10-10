<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/books_v2_admin.php';

$letter = trim((string) ($_GET['letter'] ?? ''));
$works = books_v2_admin_works($db, $letter);
$letters = [];
$all = books_v2_admin_works($db, '');
foreach ($all as $row) {
    $letters[(string) $row['letter']] = true;
}
ksort($letters);

$smarty->assign('works', $works);
$smarty->assign('alpha', array_keys($letters));
$smarty->assign('current_letter', $letter);
admin2_render('admin2/books_list.tpl', 'Книги', 'books');
