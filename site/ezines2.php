<?php

declare(strict_types=1);

require 'init.inc';
require_once __DIR__ . '/includes/ezines2_public.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

if (!ezines_v2_ready($db)) {
    http_response_code(503);
    echo 'ezines2 schema not ready';
    exit;
}

$lng = $smarty->getTemplateVars('lng');
$uiLang = ezines2_request_ui_lang(is_string($lng) ? $lng : null);
$isEng = $uiLang === 'en';

$rawFilter = (string) ($_GET['filter'] ?? '');
if ($rawFilter === '') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($path) && preg_match('#^/(?:ru|en|eng|cs|sk)/ezines2/(papers|magazines|reports)/?$#', $path, $m)) {
        $rawFilter = (string) ($m[1] ?? '');
    }
}
$filter = '';
if ($rawFilter === 'papers' || $rawFilter === 'magazines' || $rawFilter === 'reports') {
    $filter = $rawFilter;
} elseif ($rawFilter !== '') {
    header('Location: ' . ezines2_url_catalog($uiLang), true, 301);
    exit;
}

$typeFilter = null;
if ($filter === 'papers') {
    $typeFilter = 0;
} elseif ($filter === 'magazines') {
    $typeFilter = 1;
} elseif ($filter === 'reports') {
    $typeFilter = 2;
}

$sql = 'SELECT e.*, '
    . 'COALESCE(i_want.title, i_ru.title, i_any.title, CONCAT(\'Ezine #\', e.id)) AS title, '
    . 'i_ru.slug AS slug_ru, i_en.slug AS slug_en, '
    . 'cities.name AS city_name, cities.name_eng AS city_name_eng '
    . 'FROM ezines e '
    . 'LEFT JOIN ezine_i18n i_want ON i_want.ezine_id=e.id AND i_want.lang=? '
    . 'LEFT JOIN ezine_i18n i_ru ON i_ru.ezine_id=e.id AND i_ru.lang=\'ru\' '
    . 'LEFT JOIN ezine_i18n i_en ON i_en.ezine_id=e.id AND i_en.lang=\'en\' '
    . 'LEFT JOIN ezine_i18n i_any ON i_any.ezine_id=e.id '
    . '  AND i_any.lang=(SELECT i2.lang FROM ezine_i18n i2 WHERE i2.ezine_id=e.id ORDER BY i2.lang LIMIT 1) '
    . 'LEFT JOIN cities ON cities.id=e.city_id '
    . 'WHERE e.is_active=1';
if ($typeFilter !== null) {
    $sql .= ' AND e.type=?';
}
$sql .= ' ORDER BY title ASC';

$stmt = $db->prepare($sql);
if ($typeFilter !== null) {
    $stmt->bind_param('si', $uiLang, $typeFilter);
} else {
    $stmt->bind_param('s', $uiLang);
}
$stmt->execute();
$res = $stmt->get_result();

$press = [];
while ($res && ($row = $res->fetch_assoc())) {
    $row['id'] = (int) $row['id'];
    $row['title_plain'] = title_plain((string) ($row['title'] ?? ''));
    $row['public_url'] = ezines2_url_ezine($row, $uiLang);
    $row['type_label'] = match ((int) ($row['type'] ?? -1)) {
        1 => $isEng ? 'magazine' : ($uiLang === 'cs' ? 'časopis' : 'журнал'),
        2 => $isEng ? 'report' : ($uiLang === 'cs' ? 'reportáž' : 'отчёт'),
        default => $isEng ? 'newspaper' : ($uiLang === 'cs' ? 'noviny' : 'газета'),
    };
    // Template expects press.* shape
    $row['numbers'] = (int) ($row['numbers'] ?? 0);
    $press[] = $row;
}
$stmt->close();

$smarty->assign('press', $press);
$smarty->assign('ezines_filter', $filter);
$smarty->assign('ezines_catalog_url', ezines2_url_catalog($uiLang));
$smarty->assign('ezines_classic_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', true);
$smarty->assign('ui_lang', $uiLang);
$smarty->assign('url_rus', htmlspecialchars(ezines2_url_catalog('ru', $filter), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(ezines2_url_catalog('en', $filter), ENT_QUOTES, 'UTF-8'));
$smarty->assign('page_title', $isEng ? 'Electronic press (v2)' : ($uiLang === 'cs' ? 'Elektronický tisk (v2)' : 'Электронная пресса (v2)'));
$smarty->assign('canonical', ezines2_url_catalog($uiLang, $filter));

$smarty->display('ezines_new.tpl');
