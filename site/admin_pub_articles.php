<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}

function pa_post_string(string $key): string
{
	return trim((string) ($_POST[$key] ?? ''));
}

function pa_post_int(string $key): int
{
	return (int) ($_POST[$key] ?? 0);
}

const PA_FILES_ENTITY_TYPE = 2;
const PA_IMAGES_ENTITY_TYPE = 3;
const PA_FILES_FORMATS = [1 => 'pdf', 2 => 'doc', 3 => 'html', 4 => 'txt'];
function pa_image_format_from_ext(string $ext): int
{
	$ext = strtolower($ext);
	if ($ext === 'jpg' || $ext === 'jpeg') return 1;
	if ($ext === 'png') return 2;
	if ($ext === 'webp') return 3;
	if ($ext === 'gif') return 4;
	return 1;
}

function pa_make_jpeg_preview(string $tmpFile, string $dstPath, int $maxWidth = 1280, int $quality = 85): bool
{
	if (!function_exists('imagejpeg')) return false;
	$info = @getimagesize($tmpFile);
	if (!$info) return false;
	[$w, $h] = $info;
	if ($w <= 0 || $h <= 0) return false;

	$mime = $info['mime'] ?? '';
	if ($mime === 'image/jpeg') {
		$src = @imagecreatefromjpeg($tmpFile);
	} elseif ($mime === 'image/png') {
		$src = @imagecreatefrompng($tmpFile);
	} else {
		return false;
	}
	if (!$src) return false;

	$outW = $w;
	$outH = $h;
	if ($w > $maxWidth) {
		$outW = $maxWidth;
		$outH = (int) round(($h * $outW) / $w);
	}
	$dst = imagecreatetruecolor($outW, $outH);
	if (!$dst) { imagedestroy($src); return false; }
	imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);

	$dir = dirname($dstPath);
	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
		imagedestroy($dst); imagedestroy($src); return false;
	}
	$ok = @imagejpeg($dst, $dstPath, $quality);
	imagedestroy($dst);
	imagedestroy($src);
	return (bool) $ok;
}

const PA_FILES_MIME_MAP = [
	'application/pdf' => ['ext' => 'pdf', 'format' => 1],
	'application/msword' => ['ext' => 'doc', 'format' => 2],
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['ext' => 'docx', 'format' => 2],
	'text/html' => ['ext' => 'html', 'format' => 3],
	'text/plain' => ['ext' => 'txt', 'format' => 4],
];

$pub_id = (int) ($_GET['pub_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);

// Load parent publication
$publication = null;
if ($pub_id > 0) {
	$stmt = $db->prepare("SELECT * FROM publications WHERE id=? LIMIT 1");
	if ($stmt) {
		$stmt->bind_param("i", $pub_id);
		$stmt->execute();
		$publication = $stmt->get_result()->fetch_assoc();
		$stmt->close();
	}
}
if (!$publication && $id > 0) {
	$stmt = $db->prepare("SELECT pa.publication_id FROM publication_articles pa WHERE pa.id=? LIMIT 1");
	if ($stmt) {
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($row) {
			$pub_id = (int) $row['publication_id'];
			$stmt2 = $db->prepare("SELECT * FROM publications WHERE id=? LIMIT 1");
			if ($stmt2) {
				$stmt2->bind_param("i", $pub_id);
				$stmt2->execute();
				$publication = $stmt2->get_result()->fetch_assoc();
				$stmt2->close();
			}
		}
	}
}

if (!$publication) {
	http_response_code(404);
	die('Публикация не найдена. <a href="admin_publications.php">Назад</a>');
}

$smarty->assign('publication', $publication);

if (($_POST['save'] ?? '') === 'Сохранить') {
	csrf_verify();

	$title_ru = plain_text_normalize_for_storage(pa_post_string('title_ru'));
	$title_en = plain_text_normalize_for_storage(pa_post_string('title_en'));
	$summary_ru = trim((string) ($_POST['summary_ru'] ?? ''));
	$summary_en = trim((string) ($_POST['summary_en'] ?? ''));
	$body_ru = trim((string) ($_POST['body_ru'] ?? ''));
	$body_en = trim((string) ($_POST['body_en'] ?? ''));
	$page_from = pa_post_int('page_from');
	$page_to = pa_post_int('page_to');
	$is_active = !empty($_POST['is_active']) ? 1 : 0;

	if ($title_ru === '') {
		$smarty->assign('error', 'Заполни: Заголовок (RU)');
	} else {
		if ($id === 0) {
			db_exec(
				$db,
				"INSERT INTO publication_articles (publication_id, title_ru, title_en, summary_ru, summary_en, body_ru, body_en, page_from, page_to, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)",
				"issssssiii",
				$pub_id,
				$title_ru,
				($title_en !== '' ? $title_en : null),
				($summary_ru !== '' ? $summary_ru : null),
				($summary_en !== '' ? $summary_en : null),
				($body_ru !== '' ? $body_ru : null),
				($body_en !== '' ? $body_en : null),
				($page_from > 0 ? $page_from : null),
				($page_to > 0 ? $page_to : null),
				$is_active
			);
			$id = (int) mysqli_insert_id($db);
		} else {
			db_exec(
				$db,
				"UPDATE publication_articles SET title_ru=?, title_en=?, summary_ru=?, summary_en=?, body_ru=?, body_en=?, page_from=?, page_to=?, is_active=? WHERE id=? AND publication_id=? LIMIT 1",
				"ssssssiiiii",
				$title_ru,
				($title_en !== '' ? $title_en : null),
				($summary_ru !== '' ? $summary_ru : null),
				($summary_en !== '' ? $summary_en : null),
				($body_ru !== '' ? $body_ru : null),
				($body_en !== '' ? $body_en : null),
				($page_from > 0 ? $page_from : null),
				($page_to > 0 ? $page_to : null),
				$is_active,
				$id,
				$pub_id
			);
		}

		// Delete selected files
		if ($id > 0) {
			$zFiles = db_select($db, "SELECT id, format FROM files_ WHERE entity_type=? AND entity_id=? ORDER BY id ASC", "ii", PA_FILES_ENTITY_TYPE, $id);
			while ($zFiles && ($f = mysqli_fetch_assoc($zFiles))) {
				$fId = (int) ($f['id'] ?? 0);
				if ($fId <= 0) continue;
				if (!empty($_POST['delete_file_' . $fId])) {
					db_exec($db, "DELETE FROM files_ WHERE id=? LIMIT 1", "i", $fId);
					foreach (['pdf', 'doc', 'docx', 'html', 'txt'] as $e) {
						@unlink(zx_storage_path('content_files', $fId . '.' . $e));
					}
				}
			}
		}

		// Upload new files
		$upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
		$names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
		$tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];
		$sizes = (isset($upl['size']) && is_array($upl['size'])) ? $upl['size'] : [];

		for ($i = 0; $i < count($names); $i++) {
			$tmp = (string) ($tmps[$i] ?? '');
			$origName = (string) ($names[$i] ?? '');
			$fileSize = (int) ($sizes[$i] ?? 0);
			if ($tmp === '' || !is_file($tmp) || $origName === '') {
				continue;
			}

			$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
			if (!isset(PA_FILES_MIME_MAP[$mime])) {
				error_log('[FIX] admin_pub_articles: rejected upload mime=' . $mime . ' name=' . $origName);
				continue;
			}

			$fileInfo = PA_FILES_MIME_MAP[$mime];
			$ext = pathinfo($origName, PATHINFO_EXTENSION);
			if ($ext === '') {
				$ext = $fileInfo['ext'];
			}
			$ext = strtolower($ext);
			$format = $fileInfo['format'];

			db_exec(
				$db,
				"INSERT INTO files_ (entity_type, entity_id, format, size, is_active) VALUES (?,?,?,?,1)",
				"iiii",
				PA_FILES_ENTITY_TYPE,
				$id,
				$format,
				$fileSize
			);
			$fileId = (int) mysqli_insert_id($db);
			if ($fileId <= 0) {
				continue;
			}

			zx_storage_copy_uploaded_file('content_files', $fileId . '.' . $ext, $tmp);
		}

		// Update sort order for existing images
		if ($id > 0) {
			$zImg = db_select($db, "SELECT id, sort_order FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", PA_IMAGES_ENTITY_TYPE, $id);
			while ($zImg && ($img = mysqli_fetch_array($zImg))) {
				$imgId = (int) ($img['id'] ?? 0);
				if ($imgId <= 0) continue;
				$key = 'sort_order_' . $imgId;
				if (!array_key_exists($key, $_POST)) continue;
				$newSort = (int) ($_POST[$key] ?? 0);
				$oldSort = (int) ($img['sort_order'] ?? 0);
				if ($newSort !== $oldSort) {
					db_exec($db, "UPDATE images SET sort_order=? WHERE id=? LIMIT 1", "ii", $newSort, $imgId);
				}
			}
		}

		// Delete selected images
		if ($id > 0) {
			$zImg = db_select($db, "SELECT id, format FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", PA_IMAGES_ENTITY_TYPE, $id);
			while ($zImg && ($img = mysqli_fetch_array($zImg))) {
				$imgId = (int) ($img['id'] ?? 0);
				if ($imgId <= 0) continue;
				if (!empty($_POST['delete_image_' . $imgId])) {
					db_exec($db, "DELETE FROM images WHERE id=? LIMIT 1", "i", $imgId);
					foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $e) {
						@unlink(zx_storage_path('publications', $imgId . '.' . $e));
					}
					@unlink(zx_storage_path('publications_preview', $imgId . '.jpg'));
				}
			}
		}

		// Upload article images
		$uplImg = (isset($_FILES['upload_images']) && is_array($_FILES['upload_images'])) ? $_FILES['upload_images'] : [];
		$imgNames = (isset($uplImg['name']) && is_array($uplImg['name'])) ? $uplImg['name'] : [];
		$imgTmps = (isset($uplImg['tmp_name']) && is_array($uplImg['tmp_name'])) ? $uplImg['tmp_name'] : [];

		$nextSort = 0;
		$rowMax = db_select($db, "SELECT COALESCE(MAX(sort_order), 0) AS mx FROM images WHERE entity_type=? AND entity_id=?", "ii", PA_IMAGES_ENTITY_TYPE, $id);
		$maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
		if ($maxRow && isset($maxRow['mx'])) {
			$nextSort = (int) $maxRow['mx'];
		}

		for ($i = 0; $i < count($imgNames); $i++) {
			$tmp = (string) ($imgTmps[$i] ?? '');
			$origName = (string) ($imgNames[$i] ?? '');
			if ($tmp === '' || !is_file($tmp) || $origName === '') continue;

			$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
			$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
			if (!isset($allowed[$mime])) continue;

			$ext = $allowed[$mime];
			$format = pa_image_format_from_ext($ext);
			$nextSort++;

			db_exec(
				$db,
				"INSERT INTO images (entity_type, entity_id, format, sort_order, is_active) VALUES (?,?,?,?,1)",
				"iiii",
				PA_IMAGES_ENTITY_TYPE,
				$id,
				$format,
				$nextSort
			);
			$imgId = (int) mysqli_insert_id($db);
			if ($imgId <= 0) continue;

			zx_storage_copy_uploaded_file('publications', $imgId . '.' . $ext, $tmp);
			pa_make_jpeg_preview($tmp, zx_storage_path('publications_preview', $imgId . '.jpg'), 1280, 85);
		}

		header("Location: /admin_pub_articles.php?pub_id=" . $pub_id . "&id=" . $id, true, 303);
		exit;
	}
}

// Articles list for this publication
$articles_list = [];
$z = db_select(
	$db,
	"SELECT * FROM publication_articles WHERE publication_id=? ORDER BY COALESCE(page_from, 99999) ASC, id ASC",
	"i",
	$pub_id
);
while ($z && ($t = mysqli_fetch_array($z))) {
	$articles_list[] = $t;
}
$smarty->assign('articles_list', $articles_list);

if ($id === 0 && count($articles_list) > 0 && !isset($_GET['id'])) {
	$id = (int) ($articles_list[0]['id'] ?? 0);
}

$article = null;
if ($id > 0) {
	$stmt = $db->prepare("SELECT * FROM publication_articles WHERE id=? AND publication_id=? LIMIT 1");
	if ($stmt) {
		$stmt->bind_param("ii", $id, $pub_id);
		$stmt->execute();
		$article = $stmt->get_result()->fetch_assoc();
		$stmt->close();
	}
}
$smarty->assign('article', $article);

// Files for current article
$files = [];
if ($id > 0) {
	$zFiles = db_select($db, "SELECT * FROM files_ WHERE entity_type=? AND entity_id=? ORDER BY id ASC", "ii", PA_FILES_ENTITY_TYPE, $id);
	while ($zFiles && ($f = mysqli_fetch_assoc($zFiles))) {
		$fId = (int) ($f['id'] ?? 0);
		$fmt = (int) ($f['format'] ?? 1);
		$extMap = [1 => 'pdf', 2 => 'doc', 3 => 'html', 4 => 'txt'];
		$ext = $extMap[$fmt] ?? 'pdf';

		// Try to find actual file on disk (may have docx extension)
		$actualExt = $ext;
		$basePath = zx_storage_dir('content_files');
		foreach ([$ext, 'docx', 'pdf', 'doc', 'html', 'txt'] as $tryExt) {
			if (is_file($basePath . '/' . $fId . '.' . $tryExt)) {
				$actualExt = $tryExt;
				break;
			}
		}

		$f['file_url'] = '/content-files/' . $fId . '.' . $actualExt;
		$f['format_label'] = strtoupper($actualExt);
		$f['size_display'] = '';
		$sz = (int) ($f['size'] ?? 0);
		if ($sz > 0) {
			if ($sz >= 1048576) {
				$f['size_display'] = round($sz / 1048576, 1) . ' МБ';
			} elseif ($sz >= 1024) {
				$f['size_display'] = round($sz / 1024, 1) . ' КБ';
			} else {
				$f['size_display'] = $sz . ' Б';
			}
		}
		$files[] = $f;
	}
}
$smarty->assign('files', $files);

// Article images
$art_images = [];
if ($id > 0) {
	$zImg = db_select($db, "SELECT * FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", PA_IMAGES_ENTITY_TYPE, $id);
	while ($zImg && ($img = mysqli_fetch_assoc($zImg))) {
		$imgId = (int) ($img['id'] ?? 0);
		$fmt = (int) ($img['format'] ?? 1);
		$extMap = [1 => 'jpg', 2 => 'png', 3 => 'webp', 4 => 'gif'];
		$ext = $extMap[$fmt] ?? 'jpg';
		$img['original_url'] = '/publications/' . $imgId . '.' . $ext;
		$img['preview_url'] = '/publications/preview/' . $imgId . '.jpg';
		$art_images[] = $img;
	}
}
$smarty->assign('art_images', $art_images);

// Admin top expects press_list
$press_list = [];
$z = db_select($db, "SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
	$t['title'] = $t['title1'];
	if (!empty($t['title2'])) {
		$t['title'] = $t['title'] . " - " . $t['title2'];
	}
	$press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Статьи — ' . ($publication['title_ru'] ?? ''));
$smarty->display('admin_pub_articles.tpl');
