<?php
/**
 * Modern admin for ezine/journal issue screenshots.
 * Old screenshot UI remains in admin_articles.php.
 */
require 'init.inc';
require_once __DIR__ . '/includes/admin_helpers.php';
require_once __DIR__ . '/includes/screen_images.php';

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
	return screen_storage_path($screenId, $format);
}

function ascr_screen_public_url(int $screenId, string $format): string
{
	return screen_public_url($screenId, $format);
}

/**
 * @return array{0:int,1:list<string>} [uploadedCount, errors]
 */
function ascr_process_uploads(mysqli $db, int $pressId, int $issueId, int $type, int $idUsername): array
{
	$files = $_FILES['upload_screen'] ?? null;
	$items = [];
	if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
		$count = count($files['name']);
		for ($i = 0; $i < $count; $i++) {
			$items[] = [
				'name' => (string) ($files['name'][$i] ?? ''),
				'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
				'error' => (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
			];
		}
	} elseif (is_array($files) && !empty($files['tmp_name']) && is_string($files['tmp_name'])) {
		$items[] = [
			'name' => (string) ($files['name'] ?? ''),
			'tmp_name' => (string) $files['tmp_name'],
			'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
		];
	}

	$uploaded = 0;
	$errors = [];
	if ($items === []) {
		return [0, $errors];
	}

	$allowedMime = ['image/png', 'image/jpeg'];
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$tm = time();

	foreach ($items as $item) {
		$origName = $item['name'];
		$tmp = $item['tmp_name'];
		$errCode = $item['error'];
		if ($errCode === UPLOAD_ERR_NO_FILE || $tmp === '') {
			continue;
		}
		if ($errCode !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
			$errors[] = ($origName !== '' ? $origName : 'файл') . ': ошибка загрузки';
			continue;
		}
		$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
		if ($ext === 'jpeg') {
			$ext = 'jpg';
		}
		if ($ext !== 'png' && $ext !== 'jpg') {
			$errors[] = $origName . ': только PNG/JPG';
			continue;
		}
		$mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
		if (!in_array($mime, $allowedMime, true)) {
			$errors[] = $origName . ': неверный MIME';
			error_log('[FIX] admin_screens: rejected upload MIME=' . $mime . ' name=' . $origName);
			continue;
		}
		$saved = db_exec(
			$db,
			'INSERT INTO screens (id_press, id_issue, type, date, format) VALUES (?,?,?,?,?)',
			'iiiis',
			$pressId,
			$issueId,
			$type,
			$tm,
			'webp'
		);
		if (!$saved) {
			$errors[] = $origName . ': не удалось создать запись';
			continue;
		}
		$screenId = (int) mysqli_insert_id($db);
		$converted = screen_save_upload_as_webp($tmp, $screenId);
		if (empty($converted['ok'])) {
			db_exec($db, 'DELETE FROM screens WHERE id=? LIMIT 1', 'i', $screenId);
			screen_delete_files($screenId);
			$errors[] = $origName . ': ' . (string) ($converted['error'] ?? 'не удалось сохранить WebP');
			continue;
		}
		admin_log($db, $pressId, $idUsername, $tm, 2, 0, $issueId, $screenId);
		$uploaded++;
	}
	if ($finfo) {
		finfo_close($finfo);
	}

	return [$uploaded, $errors];
}

function ascr_has_upload_files(): bool
{
	$files = $_FILES['upload_screen'] ?? null;
	if (!is_array($files)) {
		return false;
	}
	if (isset($files['error']) && is_array($files['error'])) {
		foreach ($files['error'] as $err) {
			if ((int) $err !== UPLOAD_ERR_NO_FILE) {
				return true;
			}
		}
		return false;
	}
	return isset($files['tmp_name']) && (string) $files['tmp_name'] !== '';
}

$pressId = (int) ($_GET['id'] ?? 0);
$issueId = (int) ($_GET['issue'] ?? 0);
$error = null;
$notice = null;
$id_username = (int) ($_SESSION['id_username'] ?? 0);

if (isset($_GET['saved']) && isset($_GET['uploaded'])) {
	$n = max(1, (int) $_GET['uploaded']);
	$notice = 'Сохранено. Загружено скриншотов: ' . $n . '.';
} elseif (isset($_GET['saved'])) {
	$notice = 'Сохранено.';
} elseif (isset($_GET['uploaded'])) {
	$n = max(0, (int) ($_GET['uploaded'] ?? 0));
	if ($n <= 0) {
		$n = 1;
	}
	$notice = $n === 1 ? 'Скриншот загружен.' : ('Загружено скриншотов: ' . $n);
}
if (isset($_GET['deleted'])) {
	$notice = 'Скриншот удалён.';
}

$doSave = (($_POST['save'] ?? '') === 'Сохранить');
$doUpload = (($_POST['upload'] ?? '') === '1') || ($doSave && ascr_has_upload_files());

if ($doSave || $doUpload) {
	csrf_verify();
	$pressId = ascr_post_int('press_id');
	$issueId = ascr_post_int('issue_id');
	$uploadType = ascr_normalize_type(ascr_post_int('upload_type'));
	$uploaded = 0;
	$uploadErrors = [];

	$issueOk = db_select($db, 'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1', 'ii', $issueId, $pressId);
	if (!($issueOk && mysqli_fetch_array($issueOk))) {
		$error = 'Выпуск не найден';
	} else {
		if ($doUpload) {
			[$uploaded, $uploadErrors] = ascr_process_uploads($db, $pressId, $issueId, $uploadType, $id_username);
		}

		if ($doSave) {
			$z = db_select($db, 'SELECT id, format FROM screens WHERE id_press=? AND id_issue=?', 'ii', $pressId, $issueId);
			while ($z && ($row = mysqli_fetch_array($z))) {
				$sid = (int) $row['id'];
				$fmt = (string) ($row['format'] ?? 'png');

				if (!empty($_POST['delete_screen'][$sid])) {
					db_exec($db, 'DELETE FROM screens WHERE id=? AND id_press=? LIMIT 1', 'ii', $sid, $pressId);
					screen_delete_files($sid, $fmt);
					continue;
				}

				$type = ascr_normalize_type((int) ($_POST['screen_type'][$sid] ?? 0));
				$newIssue = (int) ($_POST['screen_issue'][$sid] ?? $issueId);
				if ($newIssue <= 0) {
					$newIssue = $issueId;
				}
				$okIssue = db_select($db, 'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1', 'ii', $newIssue, $pressId);
				if (!($okIssue && mysqli_fetch_array($okIssue))) {
					$newIssue = $issueId;
				}
				db_exec($db, 'UPDATE screens SET type=?, id_issue=? WHERE id=? AND id_press=? LIMIT 1', 'iiii', $type, $newIssue, $sid, $pressId);
			}
		}

		if ($uploadErrors !== [] && $uploaded === 0 && !$doSave) {
			$error = implode('; ', $uploadErrors);
		} elseif ($uploadErrors !== [] && $uploaded === 0 && $doSave) {
			// Save without successful new files — still redirect, but keep errors visible via notice path.
			$notice = 'Сохранено, но файлы не загружены: ' . implode('; ', $uploadErrors);
		} elseif ($doSave || $uploaded > 0) {
			activity_batch_finalize($db);
			$extra = '';
			if ($doSave) {
				$extra .= '&saved=1';
			}
			if ($uploaded > 0) {
				$extra .= '&uploaded=' . $uploaded;
			}
			ascr_redirect($pressId, $issueId, $extra);
		} elseif ($doUpload && $uploaded === 0) {
			$error = 'Выберите один или несколько файлов PNG/JPG';
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
