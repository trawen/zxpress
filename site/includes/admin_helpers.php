<?php
/**
 * Shared helpers for admin CRUD (tags, log).
 */

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
	return (int)$db->insert_id;
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
}

/**
 * Append a row to the admin log (prepared statement).
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
	$stmt = $db->prepare(
		'INSERT INTO log (`id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `format`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
	);
	if ($stmt === false) {
		return;
	}
	$stmt->bind_param('iiiiiiis', $id_press, $id_article, $id_issue, $id_username, $tm, $type, $id_screen, $format);
	$stmt->execute();
	$stmt->close();
}
