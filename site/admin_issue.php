<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

//error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);

//$smarty->debugging = true;

$tm = time();

// INPUT FORM
if ($_POST['save'] == "save") {
    csrf_verify();

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
        }
    }

    // UPLOAD FILE (optional — e2e and normal saves may omit $_FILES['upload_file'])
    $upload = (isset($_FILES['upload_file']) && is_array($_FILES['upload_file'])) ? $_FILES['upload_file'] : [];
    $uploadName = (string) ($upload['name'] ?? '');
    $ext = $uploadName !== '' ? strtolower(substr($uploadName, -3)) : '';
    $tmpName = (string) ($upload['tmp_name'] ?? '');
    $mime = ($tmpName !== '' && is_uploaded_file($tmpName))
        ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpName)
        : '';
    $allowed_mime = ['application/zip', 'application/x-rar-compressed', 'application/x-rar', 'application/octet-stream'];

    if ($tmpName !== '' && ($ext == "zip" or $ext == "rar" or $ext == "trd" or $ext == "scl" or $ext == "udi" or $ext == "fdi") and in_array($mime, $allowed_mime, true)) {

        if ($_POST['upload_file_new_issue']) {

            $title = $_POST['upload_file_new_issue'] ?? '';
            $stmt_is = $db->prepare(
                'INSERT INTO issue (`id`, `id_press`, `title`, `date`, `views`) VALUES (NULL, ?, ?, 0, 0)'
            );
            if ($stmt_is) {
                $stmt_is->bind_param('is', $id, $title);
                $stmt_is->execute();
            }
            $id_issue = mysqli_insert_id($db);

        } else {

            $id_issue = intval($_POST['upload_file_issue']);

        }

        $name = $uploadName;
        $type = intval($_POST['upload_file_type'] ?? 0);
        $file_title = $_POST['upload_file_title'] ?? '';
        $size = (int)ceil(((int) ($upload['size'] ?? 0)) / 1000);

        $stmt_f = $db->prepare("INSERT INTO files (`id`, `id_issue`, `date`, `name`, `type`, `file_title`, `size`) VALUES (NULL, ?, ?, ?, ?, ?, ?)");
        if ($stmt_f) {
            $stmt_f->bind_param("iisisi", $id_issue, $tm, $name, $type, $file_title, $size);
            $stmt_f->execute();
        }

        $id_screen = mysqli_insert_id($db);
        $safe_name = basename($uploadName);
        copy($tmpName, zx_storage_path('files', $safe_name));

    }

    $add_issue = $_POST['add_issue'] ?? '';

    // ADD ISSUE NUMBER
    if ($add_issue !== "") {
        $stmt_ai = $db->prepare(
            'INSERT INTO issue (`id`, `id_press`, `title`, `date`, `views`) VALUES (NULL, ?, ?, 0, 0)'
        );
        if ($stmt_ai) {
            $stmt_ai->bind_param('is', $id, $add_issue);
            $stmt_ai->execute();
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
            $stmt_up = $db->prepare("UPDATE issue SET title=? WHERE id=? LIMIT 1");
            if ($stmt_up) {
                $stmt_up->bind_param("si", $title, $id_issue);
                $stmt_up->execute();
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
    if ($_POST['press_change']) {

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

    }

    //FILES TYPE UPDATE
    $stmt_fl = $db->prepare("SELECT * FROM files, issue WHERE issue.id_press=? AND files.id_issue=issue.id");
    if ($stmt_fl) {
        $stmt_fl->bind_param("i", $id);
        $stmt_fl->execute();
        $z = $stmt_fl->get_result();
    } else {
        $z = false;
    }

    $n = 0;
    while ($z && ($t = mysqli_fetch_array($z))) {

        if (($_POST['issue_files_change_' . $t[0]] ?? null) == 1) {

            $id_file = intval($t[0]);
            $type = intval($_POST['file_type_' . $t[0]] ?? 0);
            $file_title = $_POST['file_title_' . $t[0]] ?? '';
            $issue_file = intval($_POST['issue_file_' . $t[0]] ?? 0);

            $stmt_fu = $db->prepare("UPDATE files SET type=?, file_title=?, id_issue=? WHERE id=? LIMIT 1");
            if ($stmt_fu) {
                $stmt_fu->bind_param("isii", $type, $file_title, $issue_file, $id_file);
                $stmt_fu->execute();
            }

            if (isset($_POST['file_delete_' . $t[0]])) {

                $stmt_fd = $db->prepare("DELETE FROM files WHERE id=? LIMIT 1");
                if ($stmt_fd) {
                    $stmt_fd->bind_param("i", $id_file);
                    $stmt_fd->execute();
                }

            }

        }

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

    $stmt_gi = $db->prepare("SELECT * FROM issue WHERE id_press=? ORDER BY title ASC");
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
        $iss[$n] = $t;
        $n++;
    }
    $smarty->assign('issues', $iss);

    //FILES
    $stmt_gf = $db->prepare("SELECT * FROM files, issue WHERE issue.id_press=? AND files.id_issue=issue.id");
    if ($stmt_gf) {
        $stmt_gf->bind_param("i", $id);
        $stmt_gf->execute();
        $z = $stmt_gf->get_result();
    } else {
        $z = false;
    }

    $n = 0;
    $fl = [];
    while ($z && ($t = mysqli_fetch_array($z))) {
        $t['date'] = date("d.m.y", $t[6]);
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

$smarty->display('admin_issue.tpl');

?>
