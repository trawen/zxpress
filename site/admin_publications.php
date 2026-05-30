<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}

function pub_post_string(string $key): string
{
	return trim((string) ($_POST[$key] ?? ''));
}

function pub_post_int(string $key): int
{
	return (int) ($_POST[$key] ?? 0);
}

function pub_parse_date(?string $raw): ?string
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

function pub_make_jpeg_preview(string $tmpFile, string $dstPath, int $maxWidth = 600, int $quality = 85): bool
{
	if (!function_exists('imagejpeg')) {
		return false;
	}
	$info = @getimagesize($tmpFile);
	if (!$info) {
		return false;
	}
	[$w, $h] = $info;
	if ($w <= 0 || $h <= 0) {
		return false;
	}

	$mime = $info['mime'] ?? '';
	if ($mime === 'image/jpeg') {
		$src = @imagecreatefromjpeg($tmpFile);
	} elseif ($mime === 'image/png') {
		$src = @imagecreatefrompng($tmpFile);
	} else {
		return false;
	}
	if (!$src) {
		return false;
	}

	$outW = $w;
	$outH = $h;
	if ($w > $maxWidth) {
		$outW = $maxWidth;
		$outH = (int) round(($h * $outW) / $w);
	}

	$dst = imagecreatetruecolor($outW, $outH);
	if (!$dst) {
		imagedestroy($src);
		return false;
	}

	imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);

	$dir = dirname($dstPath);
	if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
		imagedestroy($dst);
		imagedestroy($src);
		return false;
	}

	$ok = @imagejpeg($dst, $dstPath, $quality);
	imagedestroy($dst);
	imagedestroy($src);
	return (bool) $ok;
}

function pub_image_format_from_ext(string $ext): int
{
	$ext = strtolower($ext);
	if ($ext === 'jpg' || $ext === 'jpeg') return 1;
	if ($ext === 'png') return 2;
	if ($ext === 'webp') return 3;
	if ($ext === 'gif') return 4;
	return 1;
}

const PUB_ENTITY_TYPE = 2;
const PUB_TYPES = [1 => 'Журнал', 2 => 'Книга', 3 => 'Газета'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (($_POST['save'] ?? '') === 'Сохранить') {
	csrf_verify();

	$title_ru = plain_text_normalize_for_storage(pub_post_string('title_ru'));
	$title_en = plain_text_normalize_for_storage(pub_post_string('title_en'));
	$summary_ru = trim((string) ($_POST['summary_ru'] ?? ''));
	$summary_en = trim((string) ($_POST['summary_en'] ?? ''));
	$type = pub_post_int('type');
	$country_id = pub_post_int('country_id');
	$city_id = pub_post_int('city_id');
	$published_at = pub_parse_date(pub_post_string('published_at'));
	$is_active = !empty($_POST['is_active']) ? 1 : 0;

	if ($title_ru === '' || $type <= 0) {
		$smarty->assign('error', 'Заполни: Название (RU) и Тип');
	} else {
		if ($id === 0) {
			db_exec(
				$db,
				"INSERT INTO publications (title_ru, title_en, summary_ru, summary_en, type, country_id, city_id, published_at, is_active) VALUES (?,?,?,?,?,?,?,?,?)",
				"ssssiiisi",
				$title_ru,
				($title_en !== '' ? $title_en : null),
				($summary_ru !== '' ? $summary_ru : null),
				($summary_en !== '' ? $summary_en : null),
				$type,
				($country_id > 0 ? $country_id : null),
				($city_id > 0 ? $city_id : null),
				$published_at,
				$is_active
			);
			$id = (int) mysqli_insert_id($db);
		} else {
			db_exec(
				$db,
				"UPDATE publications SET title_ru=?, title_en=?, summary_ru=?, summary_en=?, type=?, country_id=?, city_id=?, published_at=?, is_active=? WHERE id=? LIMIT 1",
				"ssssiiisii",
				$title_ru,
				($title_en !== '' ? $title_en : null),
				($summary_ru !== '' ? $summary_ru : null),
				($summary_en !== '' ? $summary_en : null),
				$type,
				($country_id > 0 ? $country_id : null),
				($city_id > 0 ? $city_id : null),
				$published_at,
				$is_active,
				$id
			);
		}

		// Update sort order for existing images
		if ($id > 0) {
			$zImg = db_select(
				$db,
				"SELECT id, sort_order FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC",
				"ii",
				PUB_ENTITY_TYPE,
				$id
			);
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
			$zImg = db_select($db, "SELECT id, format FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", PUB_ENTITY_TYPE, $id);
			while ($zImg && ($img = mysqli_fetch_array($zImg))) {
				$imgId = (int) ($img['id'] ?? 0);
				if ($imgId <= 0) continue;
				if (!empty($_POST['delete_image_' . $imgId])) {
					db_exec($db, "DELETE FROM images WHERE id=? LIMIT 1", "i", $imgId);
					foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
						@unlink(zx_storage_path('publications', $imgId . '.' . $ext));
					}
					@unlink(zx_storage_path('publications_preview', $imgId . '.jpg'));
				}
			}
		}

		// Upload multiple images (originals + jpeg previews)
		$upl = (isset($_FILES['upload_files']) && is_array($_FILES['upload_files'])) ? $_FILES['upload_files'] : [];
		$names = (isset($upl['name']) && is_array($upl['name'])) ? $upl['name'] : [];
		$tmps = (isset($upl['tmp_name']) && is_array($upl['tmp_name'])) ? $upl['tmp_name'] : [];

		$nextSort = 0;
		$rowMax = db_select($db, "SELECT COALESCE(MAX(sort_order), 0) AS mx FROM images WHERE entity_type=? AND entity_id=?", "ii", PUB_ENTITY_TYPE, $id);
		$maxRow = $rowMax ? mysqli_fetch_assoc($rowMax) : null;
		if ($maxRow && isset($maxRow['mx'])) {
			$nextSort = (int) $maxRow['mx'];
		}

		for ($i = 0; $i < count($names); $i++) {
			$tmp = (string) ($tmps[$i] ?? '');
			$origName = (string) ($names[$i] ?? '');
			if ($tmp === '' || !is_file($tmp) || $origName === '') continue;

			$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
			$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
			if (!isset($allowed[$mime])) continue;

			$ext = $allowed[$mime];
			$format = pub_image_format_from_ext($ext);
			$nextSort++;

			db_exec(
				$db,
				"INSERT INTO images (entity_type, entity_id, format, sort_order, is_active) VALUES (?,?,?,?,1)",
				"iiii",
				PUB_ENTITY_TYPE,
				$id,
				$format,
				$nextSort
			);
			$imgId = (int) mysqli_insert_id($db);
			if ($imgId <= 0) continue;

			zx_storage_copy_uploaded_file('publications', $imgId . '.' . $ext, $tmp);
			pub_make_jpeg_preview($tmp, zx_storage_path('publications_preview', $imgId . '.jpg'), 1280, 85);
		}

		header("Location: /admin_publications.php?id=" . $id, true, 303);
		exit;
	}
}

// Countries & cities for selects
$countries = [];
$z = db_select($db, "SELECT * FROM countries ORDER BY country_name ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
	$countries[] = $t;
}
$smarty->assign('countries', $countries);

$cities = [];
$z = db_select($db, "SELECT * FROM cities ORDER BY name ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
	$cities[] = $t;
}
$smarty->assign('cities', $cities);

// Publications list
$pub_list = [];
$z = db_select($db, "SELECT * FROM publications ORDER BY id DESC");
while ($z && ($t = mysqli_fetch_array($z))) {
	$pub_list[] = $t;
}
$smarty->assign('pub_list', $pub_list);
$smarty->assign('pub_types', PUB_TYPES);

if ($id === 0 && count($pub_list) > 0 && !isset($_GET['id'])) {
	$id = (int) ($pub_list[0]['id'] ?? 0);
}

$pub = null;
if ($id > 0) {
	$stmt = $db->prepare("SELECT * FROM publications WHERE id=? LIMIT 1");
	if ($stmt) {
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$pub = $stmt->get_result()->fetch_assoc();
		$stmt->close();
	}
}
if ($pub && !empty($pub['published_at'])) {
	$pub['published_at'] = date('d.m.Y', strtotime((string) $pub['published_at']));
}
$smarty->assign('pub', $pub);

// Publication images
$images = [];
if ($id > 0) {
	$zImg = db_select($db, "SELECT * FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", PUB_ENTITY_TYPE, $id);
	while ($zImg && ($img = mysqli_fetch_assoc($zImg))) {
		$imgId = (int) ($img['id'] ?? 0);
		$fmt = (int) ($img['format'] ?? 1);
		$extMap = [1 => 'jpg', 2 => 'png', 3 => 'webp', 4 => 'gif'];
		$ext = $extMap[$fmt] ?? 'jpg';
		$img['original_url'] = '/publications/' . $imgId . '.' . $ext;
		$img['preview_url'] = '/publications/preview/' . $imgId . '.jpg';
		$images[] = $img;
	}
}
$smarty->assign('images', $images);

// Articles count for current publication
$articles_count = 0;
if ($id > 0) {
	$stmt = $db->prepare("SELECT COUNT(*) AS c FROM publication_articles WHERE publication_id = ?");
	if ($stmt) {
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$articles_count = (int) ($row['c'] ?? 0);
		$stmt->close();
	}
}
$smarty->assign('articles_count', $articles_count);

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

$smarty->assign('title', 'Админка: Публикации');
$smarty->display('admin_publications.tpl');
