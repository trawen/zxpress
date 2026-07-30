<?php
/**
 * Modern admin for ezine/journal articles (press → issue → article).
 * Old admin_articles.php stays available.
 */
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/article_text_render.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}

function aan_post_string(string $key): string
{
	return trim((string) ($_POST[$key] ?? ''));
}

function aan_post_int(string $key): int
{
	return (int) ($_POST[$key] ?? 0);
}

function aan_normalize_text_type(int $raw): int
{
	return ezn_normalize_article_text_type($raw);
}

function aan_queue_manticore_reindex(): void
{
	$dir = zx_data_root() . '/manticore-reindex';
	@mkdir($dir, 0775, true);
	@file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function aan_read_text_fallback(int $articleId, string $textRu): string
{
	if ($textRu !== '') {
		return $textRu;
	}
	$path = zx_storage_path('articles', (string) $articleId);
	$text = @file_get_contents($path);
	return $text === false ? '' : $text;
}

/** Plain label for sidebar: strip markup/tags from legacy titles. */
function aan_title_plain(string $title): string
{
	// Prefer shared helper (decode entities + strip tags).
	$plain = title_plain($title);
	// Guard against leftover / double-encoded entities in old rows.
	$prev = null;
	while ($plain !== $prev && (strpos($plain, '&') !== false)) {
		$prev = $plain;
		$plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}
	$plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
	return trim($plain);
}

/** Decode entities for edit fields; keep legacy tags like <b> for now. */
function aan_title_for_edit(string $title): string
{
	$prev = null;
	while ($title !== $prev && (strpos($title, '&') !== false)) {
		$prev = $title;
		$title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}
	return $title;
}

function aan_redirect(int $pressId, int $issueId, int $aid = 0, string $extra = ''): void
{
	$url = '/admin_articles_new.php?id=' . $pressId . '&issue=' . $issueId;
	if ($aid > 0 || isset($_GET['aid'])) {
		$url .= '&aid=' . $aid;
	}
	if ($extra !== '') {
		$url .= $extra;
	}
	header('Location: ' . $url, true, 303);
	exit;
}

$pressId = (int) ($_GET['id'] ?? 0);
$issueId = (int) ($_GET['issue'] ?? 0);
$aid = isset($_GET['aid']) ? (int) $_GET['aid'] : -1;
$error = null;
$notice = null;

if (isset($_GET['saved'])) {
	$notice = 'Сохранено.';
}
if (isset($_GET['deleted'])) {
	$notice = 'Статья удалена.';
}
if (isset($_GET['moved'])) {
	$notice = 'Порядок обновлён.';
}

/**
 * Reorder article within issue by swapping with neighbour, then normalize numbers 10,20,30...
 *
 * @param 'up'|'down' $dir
 */
function aan_move_article(mysqli $db, int $issueId, int $articleId, string $dir): bool
{
	if ($articleId <= 0 || $issueId <= 0 || ($dir !== 'up' && $dir !== 'down')) {
		return false;
	}
	$ids = [];
	$z = db_select($db, 'SELECT id FROM articles WHERE id_issue=? ORDER BY number ASC, id ASC', 'i', $issueId);
	while ($z && ($row = mysqli_fetch_array($z))) {
		$ids[] = (int) $row['id'];
	}
	$idx = array_search($articleId, $ids, true);
	if ($idx === false) {
		return false;
	}
	$swapWith = ($dir === 'up') ? $idx - 1 : $idx + 1;
	if ($swapWith < 0 || $swapWith >= count($ids)) {
		return false;
	}
	$tmp = $ids[$idx];
	$ids[$idx] = $ids[$swapWith];
	$ids[$swapWith] = $tmp;

	foreach ($ids as $i => $id) {
		$num = ($i + 1) * 10;
		db_exec($db, 'UPDATE articles SET number=? WHERE id=? AND id_issue=? LIMIT 1', 'iii', $num, $id, $issueId);
	}
	return true;
}

// --- POST: move article up/down ---
if (($_POST['move'] ?? '') === 'up' || ($_POST['move'] ?? '') === 'down') {
	csrf_verify();
	$pressId = aan_post_int('press_id');
	$issueId = aan_post_int('issue_id');
	$moveId = aan_post_int('article_id');
	$dir = (string) $_POST['move'];
	if (aan_move_article($db, $issueId, $moveId, $dir)) {
		aan_redirect($pressId, $issueId, $moveId, '&moved=1');
	}
	$error = 'Не удалось изменить порядок';
	$aid = $moveId;
}

// --- POST: delete ---
if (($_POST['delete'] ?? '') === '1' && aan_post_int('article_id') > 0) {
	csrf_verify();
	$delId = aan_post_int('article_id');
	$delIssue = aan_post_int('issue_id');
	$delPress = aan_post_int('press_id');
	$ok = db_exec($db, 'DELETE FROM articles WHERE id=? AND id_issue=? LIMIT 1', 'ii', $delId, $delIssue);
	if ($ok) {
		db_exec($db, 'DELETE FROM tags_articles WHERE id_article=?', 'i', $delId);
		aan_queue_manticore_reindex();
		aan_redirect($delPress, $delIssue, 0, '&deleted=1');
	}
	$error = 'Не удалось удалить статью';
	$pressId = $delPress;
	$issueId = $delIssue;
	$aid = $delId;
}

// --- POST: save ---
if (($_POST['save'] ?? '') === 'Сохранить') {
	csrf_verify();

	$pressId = aan_post_int('press_id');
	$issueId = aan_post_int('issue_id');
	$aid = aan_post_int('article_id');

	$title = plain_text_normalize_for_storage(aan_post_string('title'));
	$titleEng = plain_text_normalize_for_storage(aan_post_string('title_eng'));
	$number = max(1, aan_post_int('number'));
	$temp = !empty($_POST['temp']) ? 1 : 0;
	$textType = aan_normalize_text_type(aan_post_int('text_type'));
	$textRu = (string) ($_POST['text_ru'] ?? '');
	$textEn = (string) ($_POST['text_en'] ?? '');
	$metaRu = plain_text_normalize_for_storage(aan_post_string('meta_description_ru'));
	$metaEn = plain_text_normalize_for_storage(aan_post_string('meta_description_en'));
	$slugInputRu = aan_post_string('slug_ru');
	$slugInputEn = aan_post_string('slug_en');

	if ($pressId <= 0 || $issueId <= 0) {
		$error = 'Выберите издание и выпуск';
	} elseif ($title === '') {
		$error = 'Заголовок (RU) обязателен';
	} else {
		$issueOk = false;
		$zIss = db_select($db, 'SELECT id FROM issue WHERE id=? AND id_press=? LIMIT 1', 'ii', $issueId, $pressId);
		if ($zIss && mysqli_fetch_array($zIss)) {
			$issueOk = true;
		}
		if (!$issueOk) {
			$error = 'Выпуск не принадлежит выбранному изданию';
		} else {
			$articleSeed = [
				'id' => $aid > 0 ? $aid : 0,
				'id_issue' => $issueId,
				'title' => $title,
				'title_eng' => $titleEng,
				'meta_description_ru' => $metaRu,
				'meta_description_en' => $metaEn,
			];
			$slugs = ezn_admin_resolve_article_slugs(
				$db,
				$slugInputRu,
				$slugInputEn,
				static fn (): string => ezn_default_article_ru($articleSeed),
				static fn (): string => ezn_default_article_en($articleSeed),
				max(0, $aid),
				$issueId
			);

			$now = time();
			if ($aid <= 0) {
				$saved = db_exec(
					$db,
					'INSERT INTO articles '
					. '(id_issue, title, temp, date, views, number, title_eng, file, name, dt, id_press, text_type, text_ru, text_en, '
					. 'meta_description_ru, meta_description_en, slug_ru, slug_en) '
					. 'VALUES (?,?,?,?,0,?,?,?,?,0,?,?,?,?,?,?,?,?)',
					'isiiisssiissssss',
					$issueId,
					$title,
					$temp,
					$now,
					$number,
					$titleEng,
					'',
					'',
					$pressId,
					$textType,
					$textRu,
					$textEn,
					$metaRu,
					$metaEn,
					$slugs['slug_ru'],
					$slugs['slug_en']
				);
				if ($saved) {
					$aid = (int) mysqli_insert_id($db);
				}
			} else {
				$saved = db_exec(
					$db,
					'UPDATE articles SET title=?, title_eng=?, number=?, temp=?, text_type=?, text_ru=?, text_en=?, '
					. 'meta_description_ru=?, meta_description_en=?, slug_ru=?, slug_en=?, id_press=? '
					. 'WHERE id=? AND id_issue=? LIMIT 1',
					'ssiiissssssiii',
					$title,
					$titleEng,
					$number,
					$temp,
					$textType,
					$textRu,
					$textEn,
					$metaRu,
					$metaEn,
					$slugs['slug_ru'],
					$slugs['slug_en'],
					$pressId,
					$aid,
					$issueId
				);
			}

			if (!empty($saved) && $aid > 0) {
				// Tags: add existing
				$addTag = aan_post_int('add_tag_id');
				if ($addTag > 0) {
					$exists = db_select(
						$db,
						'SELECT id FROM tags_articles WHERE id_article=? AND id_tag=? LIMIT 1',
						'ii',
						$aid,
						$addTag
					);
					if (!($exists && mysqli_fetch_array($exists))) {
						db_exec($db, 'INSERT INTO tags_articles (id_tag, id_article) VALUES (?,?)', 'ii', $addTag, $aid);
					}
				}
				$newTagName = plain_text_normalize_for_storage(strip_tags(aan_post_string('new_tag')));
				if ($newTagName !== '') {
					db_exec($db, 'INSERT INTO tags (tag_name) VALUES (?)', 's', $newTagName);
					$newTagId = (int) mysqli_insert_id($db);
					if ($newTagId > 0) {
						db_exec($db, 'INSERT INTO tags_articles (id_tag, id_article) VALUES (?,?)', 'ii', $newTagId, $aid);
					}
				}
				if (!empty($_POST['delete_tag']) && is_array($_POST['delete_tag'])) {
					foreach ($_POST['delete_tag'] as $taId) {
						$taId = (int) $taId;
						if ($taId > 0) {
							db_exec($db, 'DELETE FROM tags_articles WHERE id=? AND id_article=? LIMIT 1', 'ii', $taId, $aid);
						}
					}
				}

				aan_queue_manticore_reindex();
				aan_redirect($pressId, $issueId, $aid, '&saved=1');
			}

			$error = 'Не удалось сохранить статью';
		}
	}
}

// --- Load press list ---
$press_list = [];
$z = db_select(
	$db,
	'SELECT p.id, p.title, p.type, '
	. '(SELECT COUNT(*) FROM articles a INNER JOIN issue i ON i.id=a.id_issue WHERE i.id_press=p.id AND a.temp=0) AS online_articles '
	. 'FROM press p ORDER BY p.title ASC'
);
while ($z && ($t = mysqli_fetch_array($z))) {
	$press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$press = null;
$issues = [];
$articles_list = [];
$article = null;
$article_tags = [];
$all_tags = [];

if ($pressId > 0) {
	$z = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
	$press = $z ? mysqli_fetch_array($z) : null;
	if (!$press) {
		$pressId = 0;
		$error = $error ?: 'Издание не найдено';
	}
}

if ($pressId > 0) {
	$z = db_select($db, 'SELECT id, title, date, slug_ru, slug_en FROM issue WHERE id_press=? ORDER BY LENGTH(title) ASC, title ASC', 'i', $pressId);
	while ($z && ($t = mysqli_fetch_array($z))) {
		$issues[] = $t;
	}
	if ($issueId <= 0 && count($issues) > 0) {
		$issueId = (int) $issues[0]['id'];
	}
	// Validate issue belongs to press
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
		'SELECT id, title, title_eng, number, temp, text_type FROM articles WHERE id_issue=? ORDER BY number ASC, id ASC',
		'i',
		$issueId
	);
	while ($z && ($t = mysqli_fetch_array($z))) {
		$t['title_plain'] = aan_title_plain((string) ($t['title'] ?? ''));
		$articles_list[] = $t;
	}

	if ($aid < 0) {
		// First open of issue: pick first article if any, else new form
		$aid = count($articles_list) > 0 ? (int) $articles_list[0]['id'] : 0;
	}

	if ($aid > 0) {
		$z = db_select($db, 'SELECT * FROM articles WHERE id=? AND id_issue=? LIMIT 1', 'ii', $aid, $issueId);
		$article = $z ? mysqli_fetch_array($z) : null;
		if ($article) {
			$article['title'] = aan_title_for_edit((string) ($article['title'] ?? ''));
			$article['title_eng'] = aan_title_for_edit((string) ($article['title_eng'] ?? ''));
			$article['text_ru'] = aan_read_text_fallback((int) $article['id'], (string) ($article['text_ru'] ?? ''));
			$article['text_en'] = (string) ($article['text_en'] ?? '');
			$uiType = (int) ($article['text_type'] ?? 0);
			if ($uiType === EZN_TEXT_TYPE_LEGACY) {
				$article['text_type_ui'] = EZN_TEXT_TYPE_HTML_PRE;
			} else {
				$article['text_type_ui'] = aan_normalize_text_type($uiType);
			}

			$zTags = db_select(
				$db,
				'SELECT ta.id AS ta_id, t.id AS tag_id, t.tag_name '
				. 'FROM tags_articles ta INNER JOIN tags t ON t.id=ta.id_tag '
				. 'WHERE ta.id_article=? ORDER BY t.tag_name ASC',
				'i',
				$aid
			);
			while ($zTags && ($tg = mysqli_fetch_array($zTags))) {
				$article_tags[] = $tg;
			}
		} else {
			$aid = 0;
		}
	}
}

$z = db_select($db, 'SELECT id, tag_name FROM tags ORDER BY tag_name ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
	$all_tags[] = $t;
}

// Next number suggestion for new article
$next_number = 1;
if ($issueId > 0 && $aid === 0) {
	$z = db_select($db, 'SELECT number FROM articles WHERE id_issue=? ORDER BY number DESC LIMIT 1', 'i', $issueId);
	$row = $z ? mysqli_fetch_array($z) : false;
	if ($row && isset($row['number'])) {
		$next_number = (int) $row['number'] + 10;
	}
}

$smarty->assign('press', $press);
$smarty->assign('issues', $issues);
$smarty->assign('issue', $issue);
$smarty->assign('articles_list', $articles_list);
$smarty->assign('article', $article);
$smarty->assign('article_tags', $article_tags);
$smarty->assign('all_tags', $all_tags);
$smarty->assign('id', $pressId);
$smarty->assign('issue_id', $issueId);
$smarty->assign('aid', max(0, $aid));
$smarty->assign('next_number', $next_number);
$smarty->assign('error', $error);
$smarty->assign('notice', $notice);
$smarty->assign('text_type_text_pre', EZN_TEXT_TYPE_TEXT_PRE);
$smarty->assign('text_type_html_pre', EZN_TEXT_TYPE_HTML_PRE);
$smarty->assign('text_type_markdown', EZN_TEXT_TYPE_MARKDOWN);
$smarty->assign('title', 'Админка: Статьи журналов (новая)');
$smarty->display('admin_articles_new.tpl');
