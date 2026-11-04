<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/ezines_v2_admin.php';
require_once dirname(__DIR__) . '/includes/ezines2_public.php';

$id = (int) ($_GET['id'] ?? 0);
$error = '';
$languages = ezines_v2_admin_languages($db);

if (!ezines_v2_ready($db)) {
    $smarty->assign('error', 'Схема ezines_v2 не применена');
    admin2_render('admin2/ezine_form.tpl', 'Эзин', 'ezines');
    exit;
}

if (isset($_POST['save'])) {
    $saved = ezines_v2_admin_save_ezine($db, $id, $languages);
    if (!$saved['ok']) {
        $error = $saved['error'];
        $id = $saved['id'] > 0 ? $saved['id'] : $id;
    } else {
        admin2_flash_set('ok', 'Сохранено');
        admin2_redirect('/admin2/ezine.php?id=' . (int) $saved['id']);
    }
}

$ezine = null;
$i18n = ezines_v2_admin_empty_ezine_i18n($languages);
$issues = [];
if ($id > 0) {
    $st = $db->prepare('SELECT * FROM ezines WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $ezine = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
    if (!$ezine) {
        $smarty->assign('error', 'Издание не найдено');
        admin2_render('admin2/ezine_form.tpl', 'Эзин', 'ezines');
        exit;
    }
    $i18n = ezines_v2_admin_load_ezine_i18n($db, $id, $languages);
    $issues = ezines_v2_admin_issues($db, $id);
} elseif ($error !== '') {
    $i18n = ezines_v2_admin_post_i18n_meta($languages);
}

$cities = ezines_v2_admin_cities($db);
$title = $ezine
    ? ('Эзин #' . $id . ' · ' . ($i18n['ru']['title'] !== '' ? $i18n['ru']['title'] : ($i18n[(string) $ezine['original_language']]['title'] ?? '')))
    : 'Новый эзин';

$publicUrl = '';
if ($ezine) {
    $slugRow = [
        'slug_ru' => $i18n['ru']['slug'] ?? '',
        'slug_en' => $i18n['en']['slug'] ?? '',
    ];
    $publicUrl = ezines2_url_ezine($slugRow, false);
}

$smarty->assign('error', $error);
$smarty->assign('ezine', $ezine);
$smarty->assign('ezine_i18n', $i18n);
$smarty->assign('languages', $languages);
$smarty->assign('cities', $cities);
$smarty->assign('issues', $issues);
$smarty->assign('public_url', $publicUrl);
if ($ezine) {
    $smarty->assign('admin2_kicker', 'Эзины v2');
    $smarty->assign('admin2_kicker_url', '/admin2/ezines.php');
    $smarty->assign('admin2_status', !empty($ezine['is_active']) ? 'on' : 'off');
}
admin2_render('admin2/ezine_form.tpl', $title, 'ezines');
