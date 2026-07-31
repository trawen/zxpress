<?php
/**
 * Shared helpers for admin CRUD (tags, log).
 */

require_once __DIR__ . '/activity.php';

/**
 * Insert a new tag; returns new tag id.
 */
function admin_insert_tag(mysqli $db, string $tag_name): int {
	$tag_name = trim($tag_name);
	$stmt = $db->prepare('INSERT INTO tags (`tag_name`) VALUES (?)');
	if ($stmt === false) {
		return 0;
	}
	$stmt->bind_param('s', $tag_name);
	$stmt->execute();
	$stmt->close();
	$id = (int) $db->insert_id;
	if ($id > 0) {
		activity_log($db, [
			'verb' => 'created',
			'object_type' => 'tag',
			'object_id' => $id,
			'action' => 'tag.created',
			'event_scope' => ACTIVITY_SCOPE_METADATA,
			'is_public' => 0,
			'title_ru' => $tag_name,
			'title_en' => $tag_name,
			'after' => ['tag_name' => $tag_name],
		]);
	}
	return $id;
}

/**
 * Link a tag to an article (optional tag_type, e.g. 1 for book chapters).
 */
function admin_link_tag(mysqli $db, int $tag_id, int $article_id, int $tag_type = 0): void {
	$stmt = $db->prepare('INSERT INTO tags_articles (`id_tag`, `id_article`, `tag_type`) VALUES (?, ?, ?)');
	if ($stmt === false) {
		return;
	}
	$stmt->bind_param('iii', $tag_id, $article_id, $tag_type);
	$stmt->execute();
	$stmt->close();
	activity_log($db, [
		'verb' => 'updated',
		'object_type' => 'article',
		'object_id' => $article_id,
		'action' => 'article.tag.added',
		'event_scope' => ACTIVITY_SCOPE_METADATA,
		'is_public' => 0,
		'title_ru' => 'Тег #' . $tag_id,
		'after' => ['tag_id' => $tag_id, 'tag_type' => $tag_type],
		'meta' => ['article_id' => $article_id],
	]);
}

/**
 * Append a row to the admin log.
 * Mirrored into activity via trg_log_ai_activity.
 */
function admin_log(
	mysqli $db,
	int $id_press,
	int $id_username,
	int $tm,
	int $type,
	int $id_article = 0,
	int $id_issue = 0,
	int $id_screen = 0,
	string $format = ''
): void {
	// `format` kept in signature for callers; column is not on `log`.
	unset($format);
	$id_cover = 0;
	$stmt = $db->prepare(
		'INSERT INTO log (`id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `id_cover`) '
		. 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
	);
	if ($stmt === false) {
		return;
	}
	$stmt->bind_param(
		'iiiiiiii',
		$id_press,
		$id_article,
		$id_issue,
		$id_username,
		$tm,
		$type,
		$id_screen,
		$id_cover
	);
	$stmt->execute();
	$legacyId = (int) $db->insert_id;
	$stmt->close();
	if ($legacyId > 0) {
		// Prefer SQL trigger; if absent, PHP mirror fills the gap (deduped by legacy_log_id).
		$exists = db_select($db, 'SELECT id FROM activity WHERE legacy_log_id=? LIMIT 1', 'i', $legacyId);
		if (!$exists || !$exists->fetch_row()) {
			activity_log_from_legacy(
				$db,
				$type,
				$id_press,
				$id_article,
				$id_issue,
				$id_username,
				$tm,
				$id_screen,
				$id_cover,
				$legacyId
			);
		}
	}
}
