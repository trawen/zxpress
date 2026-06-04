<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function per_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function per_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function per_nullable_text(string $value): ?string
{
    return $value !== '' ? $value : null;
}

function per_nullable_int(?string $raw): ?int
{
    $raw = trim((string) $raw);
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }

    return (int) $raw;
}

function per_parse_date(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('d.m.Y', $raw) ?: DateTime::createFromFormat('Y-m-d', $raw);
    if (!$dt) {
        return null;
    }

    return $dt->format('Y-m-d');
}

function per_format_date(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }

    return date('d.m.Y', $ts);
}

function per_publisher_label(array $row): string
{
    $name = trim((string) ($row['name_ru'] ?? ''));
    $alias = trim((string) ($row['alias_ru'] ?? ''));
    if ($alias !== '' && $alias !== $name) {
        return $name . ' (' . $alias . ')';
    }

    return $name !== '' ? $name : ('#' . (int) ($row['id'] ?? 0));
}

function per_publisher_ids_from_post(): array
{
    $ids = [];
    if (!isset($_POST['publisher_ids']) || !is_array($_POST['publisher_ids'])) {
        return $ids;
    }

    foreach ($_POST['publisher_ids'] as $publisherId) {
        $publisherId = (int) $publisherId;
        if ($publisherId > 0) {
            $ids[$publisherId] = $publisherId;
        }
    }

    return array_values($ids);
}

function per_sync_publishers(mysqli $db, int $periodicalId, array $publisherIds): void
{
    db_exec($db, 'DELETE FROM periodical_publishers WHERE periodical_id=?', 'i', $periodicalId);

    foreach ($publisherIds as $publisherId) {
        $publisherId = (int) $publisherId;
        if ($publisherId <= 0) {
            continue;
        }
        db_exec(
            $db,
            'INSERT IGNORE INTO periodical_publishers (periodical_id, publisher_id) VALUES (?, ?)',
            'ii',
            $periodicalId,
            $publisherId
        );
    }
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$issue_id = isset($_GET['issue_id']) ? (int) $_GET['issue_id'] : 0;
$error = null;

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $title_ru = plain_text_normalize_for_storage(per_post_string('title_ru'));
    $title_en = plain_text_normalize_for_storage(per_post_string('title_en'));
    $issn = plain_text_normalize_for_storage(per_post_string('issn'));
    $description_ru = plain_text_normalize_for_storage(per_post_string('description_ru'));
    $description_en = plain_text_normalize_for_storage(per_post_string('description_en'));
    $city_id = per_post_int('city_id');
    $year_start = per_nullable_int(per_post_string('year_start'));
    $year_end = per_nullable_int(per_post_string('year_end'));
    $is_active = !empty($_POST['is_active']) ? 1 : 0;
    $publisherIds = per_publisher_ids_from_post();

    if ($title_ru === '') {
        $error = 'Название (RU) обязательно';
    } else {
        $saved = false;
        if ($id === 0) {
            $saved = db_exec(
                $db,
                'INSERT INTO periodicals (title_ru, title_en, issn, city_id, description_ru, description_en, is_active, year_start, year_end) '
                . 'VALUES (?,?,?,?,?,?,?,?,?)',
                'sssissiii',
                $title_ru,
                $title_en,
                $issn,
                ($city_id > 0 ? $city_id : 1),
                per_nullable_text($description_ru),
                per_nullable_text($description_en),
                $is_active,
                $year_start,
                $year_end
            );
            if ($saved) {
                $id = (int) mysqli_insert_id($db);
            }
        } else {
            $saved = db_exec(
                $db,
                'UPDATE periodicals SET title_ru=?, title_en=?, issn=?, city_id=?, description_ru=?, description_en=?, is_active=?, year_start=?, year_end=? WHERE id=? LIMIT 1',
                'sssissiiii',
                $title_ru,
                $title_en,
                $issn,
                ($city_id > 0 ? $city_id : 1),
                per_nullable_text($description_ru),
                per_nullable_text($description_en),
                $is_active,
                $year_start,
                $year_end,
                $id
            );
        }

        if ($saved) {
            per_sync_publishers($db, $id, $publisherIds);
            header('Location: /admin_periodicals.php?id=' . $id . ($issue_id > 0 ? '&issue_id=' . $issue_id : ''), true, 303);
            exit;
        }

        $error = 'Не удалось сохранить издание';
    }
}

if (($_POST['save_issue'] ?? '') === 'Сохранить выпуск') {
    csrf_verify();

    if ($id <= 0) {
        $error = 'Сначала сохраните издание';
    } else {
        $issue_no = plain_text_normalize_for_storage(per_post_string('issue_no'));
        $title_ru = plain_text_normalize_for_storage(per_post_string('issue_title_ru'));
        $title_en = plain_text_normalize_for_storage(per_post_string('issue_title_en'));
        $description_ru = plain_text_normalize_for_storage(per_post_string('issue_description_ru'));
        $description_en = plain_text_normalize_for_storage(per_post_string('issue_description_en'));
        $issue_volume = per_nullable_int(per_post_string('issue_volume'));
        $issue_year = per_nullable_int(per_post_string('issue_year'));
        $circulation = per_nullable_int(per_post_string('circulation'));
        $pages = max(0, per_post_int('pages'));
        $issue_date = per_parse_date(per_post_string('issue_date'));
        $is_active = !empty($_POST['issue_is_active']) ? 1 : 0;
        $is_bound = !empty($_POST['issue_is_bound']) ? 1 : 0;
        $issue_id = per_post_int('issue_id');

        if ($issue_no === '') {
            $error = 'Номер выпуска обязателен';
        } else {
            $saved = false;
            if ($issue_id === 0) {
                $saved = db_exec(
                    $db,
                    'INSERT INTO periodical_issues (periodical_id, issue_volume, issue_no, issue_date, issue_year, title_ru, title_en, description_ru, description_en, is_active, is_bound, circulation, pages) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    'iississssiiii',
                    $id,
                    $issue_volume,
                    $issue_no,
                    $issue_date,
                    $issue_year,
                    $title_ru,
                    $title_en,
                    per_nullable_text($description_ru),
                    per_nullable_text($description_en),
                    $is_active,
                    $is_bound,
                    $circulation,
                    $pages
                );
                if ($saved) {
                    $issue_id = (int) mysqli_insert_id($db);
                }
            } else {
                $saved = db_exec(
                    $db,
                    'UPDATE periodical_issues SET issue_volume=?, issue_no=?, issue_date=?, issue_year=?, title_ru=?, title_en=?, description_ru=?, description_en=?, is_active=?, is_bound=?, circulation=?, pages=? '
                    . 'WHERE id=? AND periodical_id=? LIMIT 1',
                    'ississssiiiiii',
                    $issue_volume,
                    $issue_no,
                    $issue_date,
                    $issue_year,
                    $title_ru,
                    $title_en,
                    per_nullable_text($description_ru),
                    per_nullable_text($description_en),
                    $is_active,
                    $is_bound,
                    $circulation,
                    $pages,
                    $issue_id,
                    $id
                );
            }

            if ($saved) {
                header('Location: /admin_periodicals.php?id=' . $id . '&issue_id=0&scroll_issue=1', true, 303);
                exit;
            }

            if ((int) mysqli_errno($db) === 1062) {
                $error = 'Выпуск с таким годом и номером уже существует';
            } else {
                $error = 'Не удалось сохранить выпуск';
            }
        }
    }
}

$smarty->assign('error', $error);

$periodicals_list = [];
$z = db_select($db, 'SELECT id, title_ru, title_en, issn, is_active, year_start, year_end FROM periodicals ORDER BY title_ru ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $periodicals_list[] = $t;
}
$smarty->assign('periodicals_list', $periodicals_list);

if ($id === 0 && count($periodicals_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($periodicals_list[0]['id'] ?? 0);
}

$periodical = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM periodicals WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $periodical = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$smarty->assign('periodical', $periodical);

$linkedPublisherIds = [];
if ($id > 0) {
    $z = db_select($db, 'SELECT publisher_id FROM periodical_publishers WHERE periodical_id=?', 'i', $id);
    while ($z && ($t = mysqli_fetch_array($z))) {
        $linkedPublisherIds[(int) $t['publisher_id']] = true;
    }
}

$publishers_list = [];
$z = db_select($db, 'SELECT id, name_ru, alias_ru, active FROM publishers WHERE active=1 ORDER BY name_ru ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $pid = (int) $t['id'];
    $t['label'] = per_publisher_label($t);
    $t['selected'] = !empty($linkedPublisherIds[$pid]);
    $publishers_list[] = $t;
    unset($linkedPublisherIds[$pid]);
}

if ($linkedPublisherIds !== []) {
    foreach (array_keys($linkedPublisherIds) as $pid) {
        $stmt = $db->prepare('SELECT id, name_ru, alias_ru, active FROM publishers WHERE id=? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$t) {
            continue;
        }
        $t['label'] = per_publisher_label($t);
        $t['selected'] = true;
        if ((int) ($t['active'] ?? 1) !== 1) {
            $t['label'] .= ' [неакт.]';
        }
        $publishers_list[] = $t;
    }
    usort($publishers_list, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['name_ru'] ?? ''), (string) ($b['name_ru'] ?? ''));
    });
}
$smarty->assign('publishers_list', $publishers_list);

$issues_list = [];
$issues_by_year = [];
if ($id > 0) {
    $z = db_select(
        $db,
        'SELECT id, issue_no, issue_year, issue_volume, issue_date, title_ru, is_active, is_bound, pages '
        . 'FROM periodical_issues WHERE periodical_id=? ORDER BY issue_year DESC, issue_volume IS NULL, issue_volume ASC, issue_no ASC',
        'i',
        $id
    );
    while ($z && ($t = mysqli_fetch_array($z))) {
        $t['issue_date_fmt'] = per_format_date($t['issue_date'] ?? '');
        $issues_list[] = $t;

        $yearSort = ($t['issue_year'] !== null && $t['issue_year'] !== '') ? (int) $t['issue_year'] : -1;
        if (!isset($issues_by_year[$yearSort])) {
            $issues_by_year[$yearSort] = [
                'year_label' => $yearSort >= 0 ? (string) $yearSort : 'Без года',
                'issues' => [],
            ];
        }
        $issues_by_year[$yearSort]['issues'][] = $t;
    }
    krsort($issues_by_year, SORT_NUMERIC);
    $issues_by_year = array_values($issues_by_year);
}
$smarty->assign('issues_list', $issues_list);
$smarty->assign('issues_by_year', $issues_by_year);

$issue = null;
if ($id > 0 && $issue_id > 0) {
    $stmt = $db->prepare('SELECT * FROM periodical_issues WHERE id=? AND periodical_id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ii', $issue_id, $id);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($issue) {
            $issue['issue_date_fmt'] = per_format_date($issue['issue_date'] ?? '');
        }
    }
} elseif ($id > 0 && isset($_GET['issue_id']) && (int) $_GET['issue_id'] === 0) {
    $issue = [
        'id' => 0,
        'issue_no' => '',
        'issue_volume' => '',
        'issue_year' => '',
        'issue_date_fmt' => '',
        'title_ru' => '',
        'title_en' => '',
        'description_ru' => '',
        'description_en' => '',
        'circulation' => '',
        'pages' => 0,
        'is_active' => 1,
        'is_bound' => 0,
    ];
}
$smarty->assign('issue', $issue);
$smarty->assign('issue_id', $issue_id);
$smarty->assign('scroll_issue', !empty($_GET['scroll_issue']));

$cities = [];
$z = db_select($db, 'SELECT id, name FROM cities ORDER BY name ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $cities[] = $t;
}
$smarty->assign('cities', $cities);

$press_list = [];
$z = db_select($db, 'SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . ' - ' . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Периодические издания');
$smarty->display('admin_periodicals.tpl');
