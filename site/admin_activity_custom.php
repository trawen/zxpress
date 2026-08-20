<?php
require 'init.inc';
require_once __DIR__ . '/includes/storage_paths.php';
require_once __DIR__ . '/includes/admin_translate.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
	header('HTTP/1.1 403 Forbidden');
	header('Content-Type: text/html; charset=utf-8');
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title></head><body>';
	echo '<p>Нужна авторизация. Откройте <a href="/hyperjump.php">/hyperjump.php</a> и войдите.</p>';
	echo '</body></html>';
	exit;
}

function admin_custom_activity_table_ready(mysqli $db): bool
{
	static $ready = null;
	if ($ready !== null) {
		return $ready;
	}
	$r = @$db->query("SHOW TABLES LIKE 'custom_activity_updates'");
	$ready = ($r && $r->num_rows > 0);
	return $ready;
}

function admin_custom_activity_image_ext(string $tmpPath): ?string
{
	$info = @getimagesize($tmpPath);
	if (!$info || empty($info[2])) {
		return null;
	}
	return match ((int) $info[2]) {
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_WEBP => 'webp',
		default => null,
	};
}

function admin_custom_activity_fetch(mysqli $db, int $id): ?array
{
	if ($id <= 0 || !admin_custom_activity_table_ready($db)) {
		return null;
	}
	$z = db_select($db, 'SELECT * FROM custom_activity_updates WHERE id=? LIMIT 1', 'i', $id);
	if (!$z || !($row = $z->fetch_assoc())) {
		return null;
	}
	return $row;
}

/** Plain one-line sidebar label: markdown → text, links stripped to label only. */
function admin_custom_activity_list_preview(string $raw, int $maxLen = 80): string
{
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}

	$html = activity_custom_update_markdown_html($raw);
	$html = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $html) ?? $html;
	$plain = strip_tags($html);
	$plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	$plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
	$plain = trim($plain);

	if ($maxLen > 0 && mb_strlen($plain, 'UTF-8') > $maxLen) {
		$plain = rtrim(mb_substr($plain, 0, $maxLen - 1, 'UTF-8')) . '…';
	}

	return $plain;
}

function admin_custom_activity_sync_activity(
	mysqli $db,
	int $activityId,
	string $titleRu,
	string $titleEn,
	string $imageUrl,
	int $width,
	int $height
): void {
	if ($activityId <= 0) {
		return;
	}
	$metaJson = activity_json([
		'image_url' => $imageUrl,
		'image_width' => $width,
		'image_height' => $height,
	]);
	db_exec(
		$db,
		'UPDATE activity SET title_ru=?, title_en=?, thumb_url=?, meta_json=? WHERE id=? LIMIT 1',
		'ssssi',
		$titleRu,
		$titleEn,
		$imageUrl,
		$metaJson,
		$activityId
	);
}

$form = [
	'title_ru' => '',
	'title_en' => '',
];
$errors = [];
$editId = (int) ($_GET['id'] ?? 0);

// --- AJAX: translate RU → EN comment ---
if (($_GET['action'] ?? '') === 'translate_en') {
	@set_time_limit(180);
	header('Content-Type: application/json; charset=utf-8');
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['ok' => false, 'error' => 'POST required'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	csrf_verify();

	$titleRu = trim((string) ($_POST['title_ru'] ?? ''));
	if ($titleRu === '') {
		echo json_encode(['ok' => false, 'error' => 'Нечего переводить: заполните текст RU'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	try {
		$titleEn = admin_translate_markdown($titleRu);
		echo json_encode([
			'ok' => true,
			'title_en' => $titleEn,
		], JSON_UNESCAPED_UNICODE);
	} catch (Throwable $e) {
		http_response_code(502);
		echo json_encode([
			'ok' => false,
			'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Ошибка перевода',
		], JSON_UNESCAPED_UNICODE);
	}
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity_batch'])) {
	csrf_verify();
	$batchId = (int) ($_POST['batch_id'] ?? 0);
	if ($batchId > 0 && activity_tables_ready($db) && activity_batch_delete($db, $batchId)) {
		$redirectId = (int) ($_POST['custom_activity_id'] ?? 0);
		// If we deleted the currently edited custom entry, go to blank form.
		if ($redirectId > 0 && admin_custom_activity_fetch($db, $redirectId) === null) {
			$redirectId = 0;
		}
		$url = '/admin_activity_custom.php?deleted=1';
		if ($redirectId > 0) {
			$url .= '&id=' . $redirectId;
		}
		header('Location: ' . $url);
		exit;
	}
	$errors[] = 'Не удалось удалить запись ленты.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_custom_activity'])) {
	csrf_verify();
	$editId = (int) ($_POST['custom_activity_id'] ?? 0);

	if (!admin_custom_activity_table_ready($db)) {
		$errors[] = 'Сначала примени миграцию `custom_activity_updates`.';
	} else {
		$rawRu = trim((string) ($_POST['title_ru'] ?? ''));
		$rawEn = trim((string) ($_POST['title_en'] ?? ''));
		$form['title_ru'] = plain_text_normalize_for_storage($rawRu);
		$form['title_en'] = plain_text_normalize_for_storage($rawEn);
		log_content_plain_normalized('custom_activity.title_ru', $rawRu, $form['title_ru']);
		log_content_plain_normalized('custom_activity.title_en', $rawEn, $form['title_en']);

		if ($form['title_ru'] === '') {
			$errors[] = 'Нужен текст на русском.';
		}
		if ($form['title_en'] === '') {
			$errors[] = 'Нужен текст на английском.';
		}

		$record = $editId > 0 ? admin_custom_activity_fetch($db, $editId) : null;
		if ($editId > 0 && $record === null) {
			$errors[] = 'Запись не найдена.';
		}

		$file = $_FILES['image'] ?? null;
		$hasUpload = $file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
		$imageUrl = (string) ($record['image_url'] ?? '');
		$width = (int) ($record['image_width'] ?? 0);
		$height = (int) ($record['image_height'] ?? 0);

		if ($editId <= 0 && !$hasUpload) {
			$errors[] = 'Нужно приложить картинку.';
		}

		if ($hasUpload) {
			$tmpPath = (string) ($file['tmp_name'] ?? '');
			$ext = admin_custom_activity_image_ext($tmpPath);
			$imageInfo = @getimagesize($tmpPath);
			if ($ext === null || !$imageInfo) {
				$errors[] = 'Поддерживаются только JPG, PNG, GIF или WebP.';
			} else {
				$width = (int) ($imageInfo[0] ?? 0);
				$height = (int) ($imageInfo[1] ?? 0);
			}
		}

		if ($errors === [] && $editId > 0) {
			if ($hasUpload) {
				$tmpPath = (string) ($file['tmp_name'] ?? '');
				$ext = (string) admin_custom_activity_image_ext($tmpPath);
				$leaf = 'custom-update-' . $editId . '.' . $ext;
				$imageUrl = '/activity-images/' . rawurlencode($leaf);

				if (!zx_storage_copy_uploaded_file('activity_images', $leaf, $tmpPath)) {
					$errors[] = 'Не удалось сохранить картинку.';
				}
			}

			if ($errors === []) {
				db_exec(
					$db,
					'UPDATE custom_activity_updates SET title_ru=?, title_en=?, image_url=?, image_width=?, image_height=? WHERE id=? LIMIT 1',
					'sssiii',
					$form['title_ru'],
					$form['title_en'],
					$imageUrl,
					$width,
					$height,
					$editId
				);

				$activityId = (int) ($record['activity_id'] ?? 0);
				admin_custom_activity_sync_activity(
					$db,
					$activityId,
					$form['title_ru'],
					$form['title_en'],
					$imageUrl,
					$width,
					$height
				);

				header('Location: /admin_activity_custom.php?id=' . $editId . '&saved=1');
				exit;
			}
		} elseif ($errors === []) {
			$createdAt = time();
			$createdBy = (int) ($_SESSION['id_username'] ?? 0);

			$stmt = $db->prepare(
				'INSERT INTO custom_activity_updates (created_at, created_by, title_ru, title_en, image_url, image_width, image_height, activity_id) '
				. 'VALUES (?, ?, ?, ?, \'\', 0, 0, NULL)'
			);
			if (!$stmt) {
				$errors[] = 'Не удалось создать запись.';
			} else {
				$stmt->bind_param('iiss', $createdAt, $createdBy, $form['title_ru'], $form['title_en']);
				$stmt->execute();
				$customId = (int) $db->insert_id;
				$stmt->close();

				$tmpPath = (string) ($_FILES['image']['tmp_name'] ?? '');
				$ext = (string) admin_custom_activity_image_ext($tmpPath);
				$imageInfo = @getimagesize($tmpPath);
				$width = (int) ($imageInfo[0] ?? 0);
				$height = (int) ($imageInfo[1] ?? 0);
				$leaf = 'custom-update-' . $customId . '.' . $ext;
				$imageUrl = '/activity-images/' . rawurlencode($leaf);

				if (!zx_storage_copy_uploaded_file('activity_images', $leaf, $tmpPath)) {
					db_exec($db, 'DELETE FROM custom_activity_updates WHERE id=? LIMIT 1', 'i', $customId);
					$errors[] = 'Не удалось сохранить картинку.';
				} else {
					db_exec(
						$db,
						'UPDATE custom_activity_updates SET image_url=?, image_width=?, image_height=? WHERE id=? LIMIT 1',
						'siii',
						$imageUrl,
						$width,
						$height,
						$customId
					);

					$activityId = activity_log($db, [
						'verb' => 'created',
						'object_type' => 'custom_update',
						'object_id' => $customId,
						'action' => 'custom_update.created',
						'event_scope' => ACTIVITY_SCOPE_CONTENT,
						'is_public' => 1,
						'title_ru' => $form['title_ru'],
						'title_en' => $form['title_en'],
						'thumb_url' => $imageUrl,
						'meta' => [
							'image_url' => $imageUrl,
							'image_width' => $width,
							'image_height' => $height,
						],
					]);

					db_exec($db, 'UPDATE custom_activity_updates SET activity_id=? WHERE id=? LIMIT 1', 'ii', $activityId, $customId);
					header('Location: /admin_activity_custom.php?id=' . $customId . '&created=1');
					exit;
				}
			}
		}
	}
}

$record = admin_custom_activity_fetch($db, $editId);
if ($record === null) {
	$editId = 0;
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$form['title_ru'] = (string) $record['title_ru'];
	$form['title_en'] = (string) $record['title_en'];
}

$items = [];
if (admin_custom_activity_table_ready($db)) {
	$z = db_select($db, 'SELECT * FROM custom_activity_updates ORDER BY created_at DESC, id DESC');
	while ($z && ($row = $z->fetch_assoc())) {
		$row['created_at_display'] = date('d.m.Y H:i', (int) ($row['created_at'] ?? 0));
		$row['title_preview'] = admin_custom_activity_list_preview((string) ($row['title_ru'] ?? ''));
		$items[] = $row;
	}
}

$feedBatches = [];
if (activity_tables_ready($db)) {
	$customByActivity = [];
	if (admin_custom_activity_table_ready($db)) {
		$zc = db_select($db, 'SELECT id, activity_id FROM custom_activity_updates WHERE activity_id IS NOT NULL');
		while ($zc && ($crow = $zc->fetch_assoc())) {
			$aid = (int) ($crow['activity_id'] ?? 0);
			if ($aid > 0) {
				$customByActivity[$aid] = (int) $crow['id'];
			}
		}
	}

	$z = db_select(
		$db,
		'SELECT b.*, '
		. '(SELECT a.id FROM activity a WHERE a.batch_id=b.id AND a.object_type=\'custom_update\' ORDER BY a.id ASC LIMIT 1) AS custom_activity_row_id, '
		. '(SELECT a.object_id FROM activity a WHERE a.batch_id=b.id AND a.object_type=\'custom_update\' ORDER BY a.id ASC LIMIT 1) AS custom_object_id '
		. 'FROM activity_batch b ORDER BY b.created_at DESC, b.id DESC LIMIT 500'
	);
	while ($z && ($row = $z->fetch_assoc())) {
		$batchId = (int) ($row['id'] ?? 0);
		$title = trim((string) ($row['title_ru'] ?? ''));
		$summary = trim((string) ($row['summary_ru'] ?? ''));
		$label = $title !== '' ? $title : ($summary !== '' ? $summary : ('Батч #' . $batchId));
		$row['title_preview'] = admin_custom_activity_list_preview($label, 90);
		if ($row['title_preview'] === '') {
			$row['title_preview'] = 'Батч #' . $batchId;
		}
		$row['created_at_display'] = date('d.m.Y H:i', (int) ($row['created_at'] ?? 0));
		$row['domain_label'] = activity_domain_label((string) ($row['domain'] ?? ''), false);
		$row['is_public'] = (int) ($row['is_public'] ?? 0);
		$row['public_items_count'] = (int) ($row['public_items_count'] ?? 0);
		$row['items_count'] = (int) ($row['items_count'] ?? 0);

		$customId = (int) ($row['custom_object_id'] ?? 0);
		if ($customId <= 0) {
			$customActId = (int) ($row['custom_activity_row_id'] ?? 0);
			if ($customActId > 0 && isset($customByActivity[$customActId])) {
				$customId = $customByActivity[$customActId];
			}
		}
		$row['custom_edit_id'] = $customId;
		$feedBatches[] = $row;
	}
}

$smarty->assign('title', 'Апдейты — админка');
$smarty->assign('custom_activity_form', $form);
$smarty->assign('custom_activity_errors', $errors);
$smarty->assign('custom_activity_items', $items);
$smarty->assign('activity_feed_batches', $feedBatches);
$smarty->assign('custom_activity_id', $editId);
$smarty->assign('custom_activity_record', $record);
$smarty->assign('custom_activity_show_created', (int) ($_GET['created'] ?? 0) === 1);
$smarty->assign('custom_activity_show_saved', (int) ($_GET['saved'] ?? 0) === 1);
$smarty->assign('custom_activity_show_deleted', (int) ($_GET['deleted'] ?? 0) === 1);
$smarty->assign('custom_activity_table_ready', admin_custom_activity_table_ready($db));

$smarty->display('admin_activity_custom.tpl');
