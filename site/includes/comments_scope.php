<?php

/**
 * Namespaced ids for comments.id_article.
 * Guestbook keeps 0; book chapters keep raw ch_id (legacy).
 * Ezine entities use high offsets to avoid collisions.
 */
function comments_id_guestbook(): int
{
	return 0;
}

function comments_id_book_chapter(int $chapterId): int
{
	return max(0, $chapterId);
}

function comments_id_ezine_article(int $articleId): int
{
	return 3_000_000 + max(0, $articleId);
}

function comments_id_ezine_issue(int $issueId): int
{
	return 1_000_000 + max(0, $issueId);
}

function comments_id_ezine_press(int $pressId): int
{
	return 2_000_000 + max(0, $pressId);
}

/**
 * Ids to read for an ezine article: namespaced + legacy raw article id
 * when it does not collide with a book chapter.
 *
 * @return list<int>
 */
function comments_ezine_article_read_ids(mysqli $db, int $articleId): array
{
	$articleId = max(0, $articleId);
	$ids = [comments_id_ezine_article($articleId)];
	if ($articleId <= 0) {
		return $ids;
	}

	$stmt = mysqli_prepare($db, 'SELECT 1 FROM chapters WHERE ch_id=? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('i', $articleId);
		$stmt->execute();
		$hit = $stmt->get_result()->fetch_row();
		$stmt->close();
		if (!$hit) {
			$ids[] = $articleId;
		}
	}

	return array_values(array_unique($ids));
}
