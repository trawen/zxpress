<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

//error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);

//$smarty->debugging = true;

$tm = time();

$nextIssueSortOrder = static function (mysqli $db, int $pressId): int {
    if ($pressId <= 0) {
        return 10;
    }
    $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM issue WHERE id_press=?');
    if (!$stmt) {
        return 10;
    }
    $stmt->bind_param('i', $pressId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return max(10, ((int) ($row['max_sort'] ?? 0)) + 10);
};

function admin_issue_format_date(int $ts): string
{
    return $ts > 0 ? date('d.m.Y', $ts) : '';
}

function admin_issue_parse_date(string $raw): int
{
    $raw = trim(str_replace('/', '.', $raw));
    if ($raw === '') {
        return 0;
    }
    $parts = explode('.', $raw);
    if (count($parts) !== 3) {
        return 0;
    }
    $day = (int) $parts[0];
    $month = (int) $parts[1];
    $year = (int) $parts[2];
    if ($year > 0 && $year < 100) {
        $year += ($year >= 70) ? 1900 : 2000;
    }
    if ($day <= 0 || $month <= 0 || $year <= 0) {
        return 0;
    }
    $ts = mktime(0, 0, 0, $month, $day, $year);
    return $ts !== false ? (int) $ts : 0;
}

/** @return list<string> */
function admin_issue_upload_extensions(): array
{
    return ['zip', 'rar', 'trd', 'scl', 'udi', 'fdi', 'tap', 'tzx', 'td0', 'pdf', 'txt', 'html', 'htm', 'djvu'];
}

function admin_issue_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'файл слишком большой (лимит загрузки)',
        UPLOAD_ERR_PARTIAL => 'файл загружен частично',
        UPLOAD_ERR_NO_TMP_DIR => 'нет временной директории на сервере',
        UPLOAD_ERR_CANT_WRITE => 'не удалось записать файл во временную директорию',
        UPLOAD_ERR_EXTENSION => 'загрузка остановлена расширением PHP',
        default => 'ошибка загрузки #' . $code,
    };
}

function admin_issue_normalize_file_name(string $uploadName): string
{
    $base = basename($uploadName);
    if (strlen($base) <= 32) {
        return $base;
    }
    $ext = pathinfo($base, PATHINFO_EXTENSION);
    if ($ext !== '') {
        $maxStem = max(1, 32 - strlen($ext) - 1);
        $stem = substr(pathinfo($base, PATHINFO_FILENAME), 0, $maxStem);

        return $stem . '.' . $ext;
    }

    return substr($base, 0, 32);
}

function admin_issue_upload_mime_allowed(string $ext, string $mime): bool
{
    $binary = ['trd', 'scl', 'udi', 'fdi', 'tap', 'tzx', 'td0'];
    if (in_array($ext, $binary, true)) {
        return true;
    }

    $allowed = [
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar' => ['application/x-rar-compressed', 'application/x-rar', 'application/vnd.rar', 'application/octet-stream'],
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'html' => ['text/html', 'application/octet-stream'],
        'htm' => ['text/html', 'application/octet-stream'],
        'djvu' => ['image/vnd.djvu', 'application/octet-stream'],
    ];
    if (!isset($allowed[$ext])) {
        return false;
    }
    if ($mime === '') {
        return true;
    }

    return in_array($mime, $allowed[$ext], true);
}

function admin_issue_issue_belongs_to_press(mysqli $db, int $issueId, int $pressId): bool
{
    if ($issueId <= 0 || $pressId <= 0) {
        return false;
    }
    $z = db_select($db, 'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1', 'ii', $issueId, $pressId);

    return (bool) ($z && $z->fetch_assoc());
}

function admin_issue_list_files(mysqli $db, int $pressId): mysqli_result|false
{
    $stmt = $db->prepare(
        'SELECT * FROM files WHERE id_press=? ORDER BY (id_issue = 0) DESC, id ASC'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $pressId);
    $stmt->execute();

    return $stmt->get_result();
}

// INPUT FORM
if (($_POST['save'] ?? '') === 'save') {
    csrf_verify();

    $pressCreated = false;
    $loggedIssueIds = [];
    $loggedFileIds = [];

    // CREATE NEW PRESS (MySQL strict: all NOT NULL columns must be set)
    if (!$id) {
        $title_ins = (string) ($_POST['title'] ?? '');
        $type_ins = (int) ($_POST['type'] ?? 0);
        $city_ins = (int) ($_POST['city'] ?? 0);
        $numbers_ins = (int) ($_POST['numbers'] ?? 0);
        $stmt_press = $db->prepare(
            'INSERT INTO press (title, type, city, years_from, years_to, numbers, online_issues, online_articles, language, ex) '
            . "VALUES (?, ?, ?, 0, 0, ?, 0, 0, 0, '')"
        );
        if ($stmt_press) {
            $stmt_press->bind_param('siii', $title_ins, $type_ins, $city_ins, $numbers_ins);
            if (!$stmt_press->execute()) {
                error_log('[FIX] admin_issue: INSERT press failed: ' . $stmt_press->error);
            }
        } else {
            error_log('[FIX] admin_issue: prepare INSERT press failed: ' . mysqli_error($db));
        }
        $id = (int) mysqli_insert_id($db);
        if ($id <= 0) {
            error_log('[FIX] admin_issue: mysqli_insert_id after INSERT press is zero');
        } elseif ($id > 0) {
            $pressCreated = true;
            $stmt_new_press = $db->prepare('SELECT * FROM press WHERE id=? LIMIT 1');
            if ($stmt_new_press) {
                $stmt_new_press->bind_param('i', $id);
                $stmt_new_press->execute();
                $newPressRow = $stmt_new_press->get_result()->fetch_assoc();
                $stmt_new_press->close();
                if (is_array($newPressRow)) {
                    $slugs = per_admin_resolve_slugs(
                        $db,
                        'press',
                        '',
                        '',
                        static fn (): string => ezn_default_press_ru($newPressRow),
                        static fn (): string => ezn_default_press_en($newPressRow),
                        $id
                    );
                    $stmt_slug = $db->prepare('UPDATE press SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
                    if ($stmt_slug) {
                        $stmt_slug->bind_param('ssi', $slugs['slug_ru'], $slugs['slug_en'], $id);
                        $stmt_slug->execute();
                        $stmt_slug->close();
                    }
                }
            }
        }
    }

    // UPLOAD FILE (optional — e2e and normal saves may omit $_FILES['upload_file'])
    $upload = (isset($_FILES['upload_file']) && is_array($_FILES['upload_file'])) ? $_FILES['upload_file'] : [];
    $uploadName = (string) ($upload['name'] ?? '');
    $uploadErrCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadName !== '' || $uploadErrCode !== UPLOAD_ERR_NO_FILE) {
        $uploadError = '';
        if ($uploadErrCode !== UPLOAD_ERR_OK) {
            $uploadError = admin_issue_upload_error_message($uploadErrCode);
        } else {
            $ext = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));
            $tmpName = (string) ($upload['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $uploadError = 'файл не получен сервером';
            } elseif ($ext === '' || !in_array($ext, admin_issue_upload_extensions(), true)) {
                $uploadError = 'недопустимое расширение' . ($ext !== '' ? ' .' . $ext : '');
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = ($finfo && $tmpName !== '') ? (string) (finfo_file($finfo, $tmpName) ?: '') : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
                if (!admin_issue_upload_mime_allowed($ext, $mime)) {
                    error_log('admin_issue: rejected upload_file ext=' . $ext . ' MIME=' . $mime);
                    $uploadError = 'недопустимый тип файла' . ($mime !== '' ? ' (' . $mime . ')' : '');
                } else {
                    $id_issue = 0;
                    if (trim((string) ($_POST['upload_file_new_issue'] ?? '')) !== '') {
                        $title = (string) $_POST['upload_file_new_issue'];
                        $newIssueSortOrder = (int) ($_POST['upload_file_new_issue_sort_order'] ?? 0);
                        if ($newIssueSortOrder <= 0) {
                            $newIssueSortOrder = $nextIssueSortOrder($db, $id);
                        }
                        $newIssueDate = admin_issue_parse_date((string) ($_POST['upload_file_new_issue_date'] ?? ''));
                        $stmt_is = $db->prepare(
                            'INSERT INTO issue (`id`, `id_press`, `title`, `date`, `sort_order`, `views`) VALUES (NULL, ?, ?, ?, ?, 0)'
                        );
                        if ($stmt_is) {
                            $stmt_is->bind_param('isii', $id, $title, $newIssueDate, $newIssueSortOrder);
                            $stmt_is->execute();
                        }
                        $id_issue = (int) mysqli_insert_id($db);
                        if ($id_issue > 0) {
                            $loggedIssueIds[$id_issue] = $title;
                            $issueRow = ['id' => $id_issue, 'id_press' => $id, 'title' => $title];
                            $issueSlugs = per_admin_resolve_slugs(
                                $db,
                                'issue',
                                '',
                                '',
                                static fn (): string => ezn_default_issue_ru($issueRow),
                                static fn (): string => ezn_default_issue_en($issueRow),
                                $id_issue,
                                'id_press',
                                $id
                            );
                            $stmt_is_slug = $db->prepare('UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
                            if ($stmt_is_slug) {
                                $stmt_is_slug->bind_param('ssi', $issueSlugs['slug_ru'], $issueSlugs['slug_en'], $id_issue);
                                $stmt_is_slug->execute();
                                $stmt_is_slug->close();
                            }
                        }
                    } else {
                        $id_issue = (int) ($_POST['upload_file_issue'] ?? 0);
                    }

                    if ($id_issue < 0) {
                        $uploadError = 'выберите выпуск, издание или укажите номер нового';
                    } elseif ($id_issue > 0 && !admin_issue_issue_belongs_to_press($db, $id_issue, $id)) {
                        $uploadError = 'выпуск не относится к этому изданию';
                    } else {
                        $name = admin_issue_normalize_file_name($uploadName);
                        $type = (int) ($_POST['upload_file_type'] ?? 0);
                        $file_title = (string) ($_POST['upload_file_title'] ?? '');
                        $size = (int) ceil(((int) ($upload['size'] ?? 0)) / 1000);

                        $stmt_f = $db->prepare(
                            'INSERT INTO files (`id`, `id_issue`, `id_press`, `date`, `name`, `type`, `file_title`, `size`, `downloads`, `delete`, `file_comment`) '
                            . "VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, 0, '')"
                        );
                        $saved = false;
                        if ($stmt_f) {
                            $stmt_f->bind_param('iiisisi', $id_issue, $id, $tm, $name, $type, $file_title, $size);
                            $saved = $stmt_f->execute();
                            $stmt_f->close();
                        }

                        $id_file = (int) mysqli_insert_id($db);
                        if (!$saved || $id_file <= 0) {
                            $uploadError = 'не удалось сохранить запись в базе';
                            error_log('[FIX] admin_issue: INSERT files failed for ' . $name);
                        } elseif (!zx_storage_copy_uploaded_file('files', $name, $tmpName)) {
                            $stmt_del = $db->prepare('DELETE FROM files WHERE id=? LIMIT 1');
                            if ($stmt_del) {
                                $stmt_del->bind_param('i', $id_file);
                                $stmt_del->execute();
                                $stmt_del->close();
                            }
                            $uploadError = 'не удалось сохранить файл на диск';
                        } else {
                            $loggedFileIds[$id_file] = [
                                'issue_id' => $id_issue,
                                'title' => $file_title !== '' ? $file_title : $name,
                            ];
                        }
                    }
                }
            }
        }

        if ($uploadError !== '') {
            $_SESSION['admin_issue_error'] = 'Файл не загружен: ' . $uploadError;
        }
    }

    $add_issue = $_POST['add_issue'] ?? '';

    // ADD ISSUE NUMBER
    if ($add_issue !== "") {
        $addIssueSortOrder = (int) ($_POST['add_issue_sort_order'] ?? 0);
        if ($addIssueSortOrder <= 0) {
            $addIssueSortOrder = $nextIssueSortOrder($db, $id);
        }
        $addIssueDate = admin_issue_parse_date((string) ($_POST['add_issue_date'] ?? ''));
        $stmt_ai = $db->prepare(
            'INSERT INTO issue (`id`, `id_press`, `title`, `date`, `sort_order`, `views`) VALUES (NULL, ?, ?, ?, ?, 0)'
        );
        if ($stmt_ai) {
            $stmt_ai->bind_param('isii', $id, $add_issue, $addIssueDate, $addIssueSortOrder);
            $stmt_ai->execute();
            $newIssueId = (int) mysqli_insert_id($db);
            if ($newIssueId > 0) {
                $loggedIssueIds[$newIssueId] = $add_issue;
                $issueRow = ['id' => $newIssueId, 'id_press' => $id, 'title' => $add_issue];
                $issueSlugs = per_admin_resolve_slugs(
                    $db,
                    'issue',
                    '',
                    '',
                    static fn (): string => ezn_default_issue_ru($issueRow),
                    static fn (): string => ezn_default_issue_en($issueRow),
                    $newIssueId,
                    'id_press',
                    $id
                );
                $stmt_ai_slug = $db->prepare('UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
                if ($stmt_ai_slug) {
                    $stmt_ai_slug->bind_param('ssi', $issueSlugs['slug_ru'], $issueSlugs['slug_en'], $newIssueId);
                    $stmt_ai_slug->execute();
                    $stmt_ai_slug->close();
                }
            }
        }

    }

    // UPDATE/DELETE ISSUE NUMBER
    $stmt_li = $db->prepare("SELECT * FROM issue WHERE id_press=?");
    if ($stmt_li) {
        $stmt_li->bind_param("i", $id);
        $stmt_li->execute();
        $z = $stmt_li->get_result();
    } else {
        $z = false;
    }

    while ($z && ($t = mysqli_fetch_array($z))) {

        if (isset($_POST['issue_title_' . $t['id']])) {

            $id_issue = (int)$t['id'];
            $title = $_POST['issue_title_' . $id_issue] ?? '';
            $sortOrder = max(0, (int) ($_POST['issue_sort_order_' . $id_issue] ?? $t['sort_order'] ?? 0));
            $issueDate = admin_issue_parse_date((string) ($_POST['issue_date_' . $id_issue] ?? ''));
            $stmt_up = $db->prepare('UPDATE issue SET title=?, sort_order=?, date=? WHERE id=? LIMIT 1');
            if ($stmt_up) {
                $stmt_up->bind_param('siii', $title, $sortOrder, $issueDate, $id_issue);
                $stmt_up->execute();
            }

            $slugInputRu = trim((string) ($_POST['issue_slug_ru_' . $id_issue] ?? ''));
            $slugInputEn = trim((string) ($_POST['issue_slug_en_' . $id_issue] ?? ''));
            $issueRow = array_merge($t, ['title' => $title]);
            $issueSlugs = per_admin_resolve_slugs(
                $db,
                'issue',
                $slugInputRu,
                $slugInputEn,
                static fn (): string => ezn_default_issue_ru($issueRow),
                static fn (): string => ezn_default_issue_en($issueRow),
                $id_issue,
                'id_press',
                $id
            );
            $stmt_issue_slug = $db->prepare('UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
            if ($stmt_issue_slug) {
                $stmt_issue_slug->bind_param('ssi', $issueSlugs['slug_ru'], $issueSlugs['slug_en'], $id_issue);
                $stmt_issue_slug->execute();
                $stmt_issue_slug->close();
            }

            if (($_POST['issue_delete_' . $id_issue] ?? null) == 1) {
                $stmt_del = $db->prepare("DELETE FROM issue WHERE id=? LIMIT 1");
                if ($stmt_del) {
                    $stmt_del->bind_param("i", $id_issue);
                    $stmt_del->execute();
                }
            }
        }
    }

    // UPDATE PRESS NUMBER
    if (!empty($_POST['press_change'])) {

        $stmt_cnt = $db->prepare("SELECT COUNT(*) AS c FROM issue WHERE id_press=?");
        if ($stmt_cnt) {
            $stmt_cnt->bind_param("i", $id);
            $stmt_cnt->execute();
            $z = $stmt_cnt->get_result();
            $cnt_row = $z ? mysqli_fetch_array($z) : null;
        } else {
            $cnt_row = null;
        }
        $numbers = intval($_POST['numbers'] ?? 0);

        $numbers = $numbers ? $numbers : (int)($cnt_row['c'] ?? 0);

        $title = $_POST['title'] ?? '';
        $city = intval($_POST['city'] ?? 0);
        $type = intval($_POST['type'] ?? 0);

        $stmt_pr = $db->prepare("UPDATE press SET type=?, city=?, title=?, numbers=? WHERE id=? LIMIT 1");
        if ($stmt_pr) {
            $stmt_pr->bind_param("iisii", $type, $city, $title, $numbers, $id);
            $stmt_pr->execute();
        }

        $stmt_press_row = $db->prepare('SELECT * FROM press WHERE id=? LIMIT 1');
        $pressRow = null;
        if ($stmt_press_row) {
            $stmt_press_row->bind_param('i', $id);
            $stmt_press_row->execute();
            $pressRow = $stmt_press_row->get_result()->fetch_assoc();
            $stmt_press_row->close();
        }
        if (is_array($pressRow)) {
            $pressRow['title'] = $title;
            $slugInputRu = trim((string) ($_POST['slug_ru'] ?? ''));
            $slugInputEn = trim((string) ($_POST['slug_en'] ?? ''));
            $pressSlugs = per_admin_resolve_slugs(
                $db,
                'press',
                $slugInputRu,
                $slugInputEn,
                static fn (): string => ezn_default_press_ru($pressRow),
                static fn (): string => ezn_default_press_en($pressRow),
                $id
            );
            $stmt_press_slug = $db->prepare('UPDATE press SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
            if ($stmt_press_slug) {
                $stmt_press_slug->bind_param('ssi', $pressSlugs['slug_ru'], $pressSlugs['slug_en'], $id);
                $stmt_press_slug->execute();
                $stmt_press_slug->close();
            }
        }

    }

    //FILES TYPE UPDATE
    $z = admin_issue_list_files($db, $id);

    while ($z && ($t = mysqli_fetch_array($z))) {

        if (($_POST['issue_files_change_' . $t[0]] ?? null) == 1) {

            $id_file = intval($t[0]);
            $type = intval($_POST['file_type_' . $t[0]] ?? 0);
            $file_title = $_POST['file_title_' . $t[0]] ?? '';
            $issue_file = intval($_POST['issue_file_' . $t[0]] ?? 0);
            if ($issue_file < 0
                || ($issue_file > 0 && !admin_issue_issue_belongs_to_press($db, $issue_file, $id))) {
                $issue_file = (int) ($t['id_issue'] ?? 0);
            }

            $stmt_fu = $db->prepare("UPDATE files SET type=?, file_title=?, id_issue=?, id_press=? WHERE id=? AND id_press=? LIMIT 1");
            if ($stmt_fu) {
                $stmt_fu->bind_param("isiiii", $type, $file_title, $issue_file, $id, $id_file, $id);
                $stmt_fu->execute();
            }

            if (isset($_POST['file_delete_' . $t[0]])) {

                $stmt_fd = $db->prepare("DELETE FROM files WHERE id=? AND id_press=? LIMIT 1");
                if ($stmt_fd) {
                    $stmt_fd->bind_param("ii", $id_file, $id);
                    $stmt_fd->execute();
                }

            }

        }

    }

    $pressTitle = (string) ($_POST['title'] ?? '');
    if ($pressCreated && $id > 0) {
        activity_log($db, [
            'verb' => 'created',
            'object_type' => 'press',
            'object_id' => $id,
            'action' => 'press.created',
            'event_scope' => ACTIVITY_SCOPE_CONTENT,
            'is_public' => 1,
            'title_ru' => $pressTitle,
            'title_en' => $pressTitle,
            'url_ru' => '/press.php?id=' . $id,
        ]);
    } elseif (!empty($_POST['press_change']) && $id > 0) {
        activity_log($db, [
            'verb' => 'updated',
            'object_type' => 'press',
            'object_id' => $id,
            'action' => 'press.updated',
            'event_scope' => ACTIVITY_SCOPE_METADATA,
            'is_public' => 0,
            'title_ru' => $pressTitle,
            'title_en' => $pressTitle,
            'url_ru' => '/press.php?id=' . $id,
        ]);
    }
    foreach ($loggedIssueIds as $issueId => $issueTitle) {
        activity_log($db, [
            'verb' => 'created',
            'object_type' => 'issue',
            'object_id' => (int) $issueId,
            'parent_type' => 'press',
            'parent_id' => $id,
            'action' => 'issue.created',
            'event_scope' => ACTIVITY_SCOPE_CONTENT,
            'is_public' => 1,
            'title_ru' => (string) $issueTitle,
            'title_en' => (string) $issueTitle,
            'url_ru' => '/issue.php?id=' . (int) $issueId,
        ]);
    }
    foreach ($loggedFileIds as $fileId => $fileMeta) {
        $fileIssueId = (int) ($fileMeta['issue_id'] ?? 0);
        activity_log($db, [
            'verb' => 'uploaded',
            'object_type' => 'file',
            'object_id' => (int) $fileId,
            'parent_type' => $fileIssueId > 0 ? 'issue' : 'press',
            'parent_id' => $fileIssueId > 0 ? $fileIssueId : $id,
            'action' => 'file.uploaded',
            'event_scope' => ACTIVITY_SCOPE_CONTENT,
            'is_public' => 1,
            'title_ru' => (string) ($fileMeta['title'] ?? ''),
            'title_en' => (string) ($fileMeta['title'] ?? ''),
        ]);
    }

    //REDIRECT
    header("Location: /admin_issue.php?id=$id");
    exit;

}

if ($id) {

    //GET INFO
    $stmt_gp = $db->prepare("SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id WHERE press.id=?");
    if ($stmt_gp) {
        $stmt_gp->bind_param("i", $id);
        $stmt_gp->execute();
        $z = $stmt_gp->get_result();
        $smarty->assign('press', mysqli_fetch_array($z));
    }

    $stmt_gi = $db->prepare("SELECT * FROM issue WHERE id_press=? ORDER BY sort_order ASC, id ASC");
    if ($stmt_gi) {
        $stmt_gi->bind_param("i", $id);
        $stmt_gi->execute();
        $z = $stmt_gi->get_result();
    } else {
        $z = false;
    }
    $n = 0;
    $iss = [];
    while ($z && ($t = mysqli_fetch_array($z))) {
        $t['date_fmt'] = admin_issue_format_date((int) ($t['date'] ?? 0));
        $iss[$n] = $t;
        $n++;
    }
    $smarty->assign('issues', $iss);

    //FILES
    $z = admin_issue_list_files($db, $id);

    $n = 0;
    $fl = [];
    while ($z && ($t = mysqli_fetch_array($z))) {
        $t['date'] = date("d.m.y", (int) ($t['date'] ?? 0));
        $fl[$n] = $t;
        $n++;
    }
    $smarty->assign('files', $fl);

}

// New press (?id=0): template expects `press.*` keys (avoid undefined in Smarty).
if (!$id) {
    $smarty->assign('press', [
        'title' => '',
        'type' => 0,
        'city' => 0,
        'numbers' => 0,
        'slug_ru' => '',
        'slug_en' => '',
    ]);
    $smarty->assign('issues', []);
    $smarty->assign('files', []);
}

$pl = [];
$z = db_select($db, "SELECT * FROM press ORDER BY title ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $pl[] = $t;
}
$smarty->assign('press_list', $pl);

$ct = [];
$z = db_select($db, "SELECT * FROM cities ORDER BY name ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $ct[] = $t;
}
$smarty->assign('cities', $ct);

$adminIssueError = (string) ($_SESSION['admin_issue_error'] ?? '');
unset($_SESSION['admin_issue_error']);
$smarty->assign('error', $adminIssueError);

$smarty->display('admin_issue.tpl');

?>
