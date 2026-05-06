<?php

/**
 * Pick $limit random rows from a table using COUNT + OFFSET
 * (avoids ORDER BY RAND() full table scan).
 * $query_count and $query_select can be:
 *   - a string (plain SQL, no params)
 *   - an array [$sql, $types, ...$params] for prepared statements
 */
function random_rows($db, $query_count, $query_select, $limit = 5) {
	if (is_array($query_count)) {
		$stmt = $db->prepare($query_count[0]);
		if (count($query_count) > 2) {
			$params = array_slice($query_count, 2);
			$stmt->bind_param($query_count[1], ...$params);
		}
		$stmt->execute();
		$z = $stmt->get_result();
	} else {
		$stmt = $db->prepare($query_count);
		if (!$stmt) {
			return [];
		}
		$stmt->execute();
		$z = $stmt->get_result();
	}
	$row = mysqli_fetch_array($z);
	$total = intval($row[0]);
	if ($total === 0) return [];

	$results = [];
	$offsets_used = [];
	$attempts = 0;
	while (count($results) < $limit && $attempts < $limit * 3 && $total > 0) {
		$offset = mt_rand(0, max(0, $total - 1));
		if (isset($offsets_used[$offset])) { $attempts++; continue; }
		$offsets_used[$offset] = true;

		if (is_array($query_select)) {
			$sql = $query_select[0] . " LIMIT 1 OFFSET " . intval($offset);
			$stmt = $db->prepare($sql);
			if (count($query_select) > 2) {
				$params = array_slice($query_select, 2);
				$stmt->bind_param($query_select[1], ...$params);
			}
			$stmt->execute();
			$z = $stmt->get_result();
		} else {
			$sql = $query_select . " LIMIT 1 OFFSET " . intval($offset);
			$stmt = $db->prepare($sql);
			if (!$stmt) {
				$attempts++;
				continue;
			}
			$stmt->execute();
			$z = $stmt->get_result();
		}
		$t = mysqli_fetch_array($z);
		if ($t) $results[] = $t;
		$attempts++;
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
	if (count($article_breadcrumbs)) {
		$id_menu = intval($article_breadcrumbs[0]['id']);

		$rnd = random_rows($db,
			["SELECT COUNT(*) FROM menu_articles AS ma, articles AS a WHERE ma.id_menu=? AND a.id=ma.id_article", "i", $id_menu],
			["SELECT * FROM menu_articles AS ma, articles AS a WHERE ma.id_menu=? AND a.id=ma.id_article", "i", $id_menu]
		);
		$smarty->assign('random_articles', sidebar_article_link_titles($rnd));
	}

	$pl = [];
	$z = db_select($db, "SELECT * FROM press ORDER BY title ASC");
	while ($z && ($t = mysqli_fetch_array($z))) { $pl[] = $t; }
	$smarty->assign('press_list', $pl);

	if (!$smarty->getTemplateVars("random_articles")) {
		$rnd = random_rows($db,
			"SELECT COUNT(*) FROM articles",
			"SELECT * FROM articles"
		);
		$smarty->assign('random_articles', sidebar_article_link_titles($rnd));
	}
}

setup_sidebar($db, $smarty, $article_breadcrumbs);

?>
