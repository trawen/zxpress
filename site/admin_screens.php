<?php
/**
 * Modern admin for ezine/journal issue screenshots.
 * Old screenshot UI remains in admin_articles.php.
 */
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}

function ascr_post_int(string $key): int
{
	return (int) ($_POST[$key] ?? 0);
}

function ascr_normalize_type(int $raw): int
{
	return in_array($raw, [0, 1, 2], true) ? $raw : 0;
}

function ascr_redirect(int $pressId, int $issueId, string $extra = ''): void
{
	$url = '/admin_screens.php?id=' . $pressId;
	if ($issueId > 0) {
		$url .= '&issue=' . $issueId;
	}
	if ($extra !== '') {
		$url .= $extra;
	}
	header('Location: ' . $url, true, 303);
	exit;
}

function ascr_screen_path(int $screenId, string $format): string
{
	$format = strtolower(preg_replace('/[^a-z0-9]/', '', $format) ?? '');
	if ($format === '') {
		$format = 'png';
	}
	return zx_storage_path('screens', '1/' . $screenId . '.' . $format);
}

function ascr_screen_public_url(int $screenId, string $format): string
{
	$format = strtolower(preg_replace('/[^a-z0-9]/', '', $format) ?? '');
	if ($format === '') {
		$format = 'png';
	}
	return '/screens/1/' . $screenId . '.' . $format;
}

$pressId = (int) ($_GET['id'] ?? 0);
$issueId = (int) ($_GET['issue'] ?? 0);
$error = null;
$notice = null;
$id_username = (int) ($_SESSION['id_username'] ?? 0);

if (isset($_GET['saved'])) {
	$notice = 'Сохранено.';
}
if (isset($_GET['uploaded'])) {
	$notice = 'Скриншот загружен.';
}
if (isset($_GET['deleted'])) {
	$notice = 'Скриншот удалён.';
}

// --- POST: save existing screens (type / issue / delete) ---
if (($_POST['save'] ?? '') === 'Сохранить') {
	csrf_verify();
	$pressId = ascr_post_int('press_id');
	$issueId = ascr_post_int('issue_id');

	$z = db_select($db, 'SELECT id, format FROM screens WHERE id_press=? AND id_issue=?', 'ii', $pressId, $issueId);
	while ($z && ($row = mysqli_fetch_array($z))) {
		$sid = (int) $row['id'];
		$fmt = (string) ($row['format'] ?? 'png');

		if (!empty($_POST['delete_screen'][$sid])) {
			db_exec($db, 'DELETE FROM screens WHERE id=? AND id_press=? LIMIT 1', 'ii', $sid, $pressId);
			$path = ascr_screen_path($sid, $fmt);
			if (is_file($path)) {
				@unlink($path);
			}
			continue;
		}

		$type = ascr_normalize_type((int) ($_POST['screen_type'][$sid] ?? 0));
		$newIssue = (int) ($_POST['screen_issue'][$sid] ?? $issueId);
		if ($newIssue <= 0) {
			$newIssue = $issueId;
		}
		// Only allow move within same press
		$okIssue = db_select($db, 'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1', 'ii', $newIssue, $pressId);
		if (!($okIssue && mysqli_fetch_array($okIssue))) {
			$newIssue = $issueId;
		}
		db_exec($db, 'UPDATE screens SET type=?, id_issue=? WHERE id=? AND id_press=? LIMIT 1', 'iiii', $type, $newIssue, $sid, $pressId);
	}

	ascr_redirect($pressId, $issueId, '&saved=1');
}

// --- POST: upload ---
if (($_POST['upload'] ?? '') === '1') {
	csrf_verify();
	$pressId = ascr_post_int('press_id');
	$issueId = ascr_post_int('issue_id');
	$type = ascr_normalize_type(ascr_post_int('upload_type'));

	$issueOk = db_select($db, 'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1', 'ii', $issueId, $pressId);
	if (!($issueOk && mysqli_fetch_array($issueOk))) {
		$error = 'Выпуск не найден';
	} elseif (empty($_FILES['upload_screen']['tmp_name'])) {
		$error = 'Выберите файл PNG или JPG';
	} else {
		$origName = (string) ($_FILES['upload_screen']['name'] ?? '');
		$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
		if ($ext === 'jpeg') {
			$ext = 'jpg';
		}
		if ($ext !== 'png' && $ext !== 'jpg') {
			$error = 'Допустимы только PNG и JPG';
		} else {
			$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), (string) $_FILES['upload_screen']['tmp_name']);
			$allowed = ['image/png', 'image/jpeg'];
			if (!in_array($mime, $allowed, true)) {
				$error = 'Неверный MIME тип файла';
				error_log('[FIX] admin_screens: rejected upload MIME=' . $mime);
			} else {
				$tm = time();
				$saved = db_exec(
					$db,
					'INSERT INTO screens (id_press, id_issue, type, date, format) VALUES (?,?,?,?,?)',
					'iiiis',
					$pressId,
					$issueId,
					$type,
					$tm,
					$ext
				);
				if ($saved) {
					$screenId = (int) mysqli_insert_id($db);
					$okCopy = zx_storage_copy_uploaded_file(
						'screens',
						'1/' . $screenId . '.' . $ext,
						(string) $_FILES['upload_screen']['tmp_name']
					);
					if (!$okCopy) {
						db_exec($db, 'DELETE FROM screens WHERE id=? LIMIT 1', 'i', $screenId);
						$error = 'Не удалось сохранить файл на диск';
					} else {
						db_exec(
							$db,
							'INSERT INTO log (id_press, id_article, id_issue, id_user, date, type, id_screen, id_cover) '
							. 'VALUES (?,0,?,?,?,2,?,0)',
							'iiiii',
							$pressId,
							$issueId,
							$id_username,
							$tm,
							$screenId
						);
						ascr_redirect($pressId, $issueId, '&uploaded=1');
					}
				} else {
					$error = 'Не удалось создать запись скриншота';
				}
			}
		}
	}
}

// --- Load press list ---
$press_list = [];
$z = db_select(
	$db,
	'SELECT p.id, p.title, '
	. '(SELECT COUNT(*) FROM screens s WHERE s.id_press=p.id) AS screens_count '
	. 'FROM press p ORDER BY p.title ASC'
);
while ($z && ($t = mysqli_fetch_array($z))) {
	$press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$press = null;
$issues = [];
$screens = [];

if ($pressId > 0) {
	$z = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
	$press = $z ? mysqli_fetch_array($z) : null;
	if (!$press) {
		$pressId = 0;
		$error = $error ?: 'Издание не найдено';
	}
}

if ($pressId > 0) {
	$z = db_select(
		$db,
		'SELECT i.id, i.title, i.date, '
		. '(SELECT COUNT(*) FROM screens s WHERE s.id_issue=i.id) AS screens_count '
		. 'FROM issue i WHERE i.id_press=? ORDER BY LENGTH(i.title) ASC, i.title ASC',
		'i',
		$pressId
	);
	while ($z && ($t = mysqli_fetch_array($z))) {
		$issues[] = $t;
	}
	if ($issueId <= 0 && count($issues) > 0) {
		$issueId = (int) $issues[0]['id'];
	}
	$issueValid = false;
	foreach ($issues as $iss) {
		if ((int) $iss['id'] === $issueId) {
			$issueValid = true;
			break;
		}
	}
	if (!$issueValid) {
		$issueId = count($issues) > 0 ? (int) $issues[0]['id'] : 0;
	}
}

$issue = null;
if ($issueId > 0) {
	foreach ($issues as $iss) {
		if ((int) $iss['id'] === $issueId) {
			$issue = $iss;
			break;
		}
	}
}

if ($issueId > 0) {
	$z = db_select(
		$db,
		'SELECT * FROM screens WHERE id_issue=? ORDER BY type ASC, id ASC',
		'i',
		$issueId
	);
	while ($z && ($t = mysqli_fetch_array($z))) {
		$sid = (int) $t['id'];
		$fmt = (string) ($t['format'] ?? 'png');
		$t['public_url'] = ascr_screen_public_url($sid, $fmt);
		$t['file_exists'] = is_file(ascr_screen_path($sid, $fmt));
		$screens[] = $t;
	}
}

$smarty->assign('press', $press);
$smarty->assign('issues', $issues);
$smarty->assign('issue', $issue);
$smarty->assign('screens', $screens);
$smarty->assign('id', $pressId);
$smarty->assign('issue_id', $issueId);
$smarty->assign('error', $error);
$smarty->assign('notice', $notice);
$smarty->assign('title', 'Админка: Скриншоты журналов');
$smarty->display('admin_screens.tpl');
