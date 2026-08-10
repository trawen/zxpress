<?php

/**
 * Brief shared cache for sidebar random articles (blunts crawler storms).
 * Prefers APCu; falls back to a file under zx_tmp_root().
 */
function random_articles_cache_get(string $key): ?array {
	if (function_exists('apcu_fetch')) {
		$ok = false;
		$val = apcu_fetch($key, $ok);
		if ($ok && is_array($val)) {
			return $val;
		}
	}

	if (!function_exists('zx_tmp_root')) {
		return null;
	}
	$path = zx_tmp_root() . '/cache_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key) . '.json';
	if (!is_file($path)) {
		return null;
	}
	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '') {
		return null;
	}
	$data = json_decode($raw, true);
	if (!is_array($data) || !isset($data['exp'], $data['rows']) || !is_array($data['rows'])) {
		return null;
	}
	if ((int) $data['exp'] < time()) {
		@unlink($path);
		return null;
	}
	return $data['rows'];
}

function random_articles_cache_set(string $key, array $rows, int $ttl = 60): void {
	if (function_exists('apcu_store')) {
		apcu_store($key, $rows, $ttl);
	}

	if (!function_exists('zx_tmp_root')) {
		return;
	}
	$dir = zx_tmp_root();
	if (!is_dir($dir) || !is_writable($dir)) {
		return;
	}
	$path = $dir . '/cache_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key) . '.json';
	$payload = json_encode([
		'exp' => time() + $ttl,
		'rows' => $rows,
	], JSON_UNESCAPED_UNICODE);
	if ($payload === false) {
		return;
	}
	@file_put_contents($path, $payload, LOCK_EX);
}

/**
 * Pick $limit random articles via primary-key id range (no deep OFFSET / ORDER BY RAND).
 * Optional $id_menu limits to articles linked in menu_articles.
 */
function random_rows($db, $limit = 5, $id_menu = null) {
	$limit = max(1, (int) $limit);
	$id_menu = ($id_menu === null || $id_menu === '') ? null : (int) $id_menu;
	$cache_key = 'zx_rnd_articles_v1_' . ($id_menu ?? 0) . '_' . $limit;

	$cached = random_articles_cache_get($cache_key);
	if (is_array($cached) && count($cached) > 0) {
		return $cached;
	}

	if ($id_menu !== null) {
		$stmt = $db->prepare(
			'SELECT MIN(a.id), MAX(a.id) FROM menu_articles AS ma'
			. ' INNER JOIN articles AS a ON a.id = ma.id_article'
			. ' WHERE ma.id_menu = ?'
		);
		if (!$stmt) {
			return [];
		}
		$stmt->bind_param('i', $id_menu);
		$stmt->execute();
		$z = $stmt->get_result();
	} else {
		$z = $db->query('SELECT MIN(id), MAX(id) FROM articles');
		if (!$z) {
			return [];
		}
	}

	$row = $z ? mysqli_fetch_row($z) : null;
	if (!$row || $row[0] === null || $row[1] === null) {
		return [];
	}
	$min_id = (int) $row[0];
	$max_id = (int) $row[1];
	if ($max_id < $min_id) {
		return [];
	}

	$results = [];
	$used_ids = [];
	$attempts = 0;
	$max_attempts = $limit * 8;

	while (count($results) < $limit && $attempts < $max_attempts) {
		$attempts++;
		$candidate = mt_rand($min_id, $max_id);

		if ($id_menu !== null) {
			$stmt = $db->prepare(
				'SELECT a.* FROM menu_articles AS ma'
				. ' INNER JOIN articles AS a ON a.id = ma.id_article'
				. ' WHERE ma.id_menu = ? AND a.id >= ?'
				. ' ORDER BY a.id ASC LIMIT 1'
			);
			if (!$stmt) {
				continue;
			}
			$stmt->bind_param('ii', $id_menu, $candidate);
			$stmt->execute();
			$z = $stmt->get_result();
		} else {
			$stmt = $db->prepare('SELECT * FROM articles WHERE id >= ? ORDER BY id ASC LIMIT 1');
			if (!$stmt) {
				continue;
			}
			$stmt->bind_param('i', $candidate);
			$stmt->execute();
			$z = $stmt->get_result();
		}

		$t = $z ? mysqli_fetch_assoc($z) : null;
		if (!$t || !isset($t['id'])) {
			continue;
		}
		$aid = (int) $t['id'];
		if (isset($used_ids[$aid])) {
			continue;
		}
		$used_ids[$aid] = true;
		$results[] = $t;
	}

	if (count($results) > 0) {
		random_articles_cache_set($cache_key, $results, 60);
	}
	return $results;
}

/**
 * Sidebar “similar articles”: BBCode → safe HTML + title() emphasis (link body uses |nofilter in tpl).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function sidebar_article_link_titles(array $rows): array {
	foreach ($rows as &$r) {
		if (isset($r['title'])) {
			$r['title'] = article_title_list_html($r['title']);
		}
		if (isset($r['title_eng'])) {
			$r['title_eng'] = article_title_list_html($r['title_eng']);
		}
	}
	unset($r);
	return $rows;
}

/**
 * Populate Smarty variables for the right sidebar.
 * Accepts $db, $smarty, and $article_breadcrumbs explicitly to avoid
 * tight coupling with init.inc globals.
 */
function setup_sidebar($db, $smarty, $article_breadcrumbs) {
	if (is_array($article_breadcrumbs) && count($article_breadcrumbs)) {
		$id_menu = intval($article_breadcrumbs[0]['id']);

		$rnd = random_rows($db, 5, $id_menu);
		if (count($rnd) > 0) {
			$smarty->assign('random_articles', sidebar_article_link_titles($rnd));
		}
	}

	$pl = [];
	$z = db_select($db, "SELECT * FROM press ORDER BY title ASC");
	while ($z && ($t = mysqli_fetch_array($z))) { $pl[] = $t; }
	$smarty->assign('press_list', $pl);

	if (!$smarty->getTemplateVars("random_articles")) {
		$rnd = random_rows($db, 5);
		$smarty->assign('random_articles', sidebar_article_link_titles($rnd));
	}
}

setup_sidebar($db, $smarty, $article_breadcrumbs);

?>
