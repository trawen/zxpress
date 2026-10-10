<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/letters_admin.php';

$status = trim((string) ($_GET['status'] ?? 'all'));
$authorId = (int) ($_GET['author_id'] ?? 0);
$list = letters_admin_list($db, $status, $authorId);

$smarty->assign('letters_groups', $list['groups']);
$smarty->assign('status_filter', $list['status']);
$smarty->assign('author_filter_id', $list['author_id']);
$smarty->assign('letter_counts', $list['counts']);
$smarty->assign('letter_status_draft', LETTER_STATUS_DRAFT);
$smarty->assign('letter_status_queued', LETTER_STATUS_QUEUED);
$smarty->assign('letter_status_published', LETTER_STATUS_PUBLISHED);

admin2_render('admin2/letters_list.tpl', 'Письма', 'letters');
