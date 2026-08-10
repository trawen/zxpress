<?php
require 'init.inc';

$id = (int) ($_GET['id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$num = 100;
$isEng = !empty($_GET['lng']) || (($smarty->getTemplateVars('lng') ?? '') === 'eng');
$wantRubrics = !empty($_GET['rubrics']);

$z = db_select($db, 'SELECT tag_name FROM tags WHERE id=? LIMIT 1', 'i', $id);
$tag_row = $z ? mysqli_fetch_array($z) : false;
$tag_name = $tag_row ? (string) $tag_row[0] : '';
$smarty->assign('tag', $tag_name);
$smarty->assign('tag_id', $id);

$total = 0;
$z = false;

if ($id > 0) {
	$z_cnt = db_select(
		$db,
		'SELECT COUNT(*) FROM tags_articles'
			. ' INNER JOIN articles ON articles.id = tags_articles.id_article'
			. ' INNER JOIN issue ON issue.id = articles.id_issue'
			. ' INNER JOIN press ON press.id = issue.id_press'
			. ' WHERE tags_articles.id_tag = ?',
		'i',
		$id
	);
	$cnt_row = $z_cnt ? mysqli_fetch_array($z_cnt) : false;
	$total = (int) ($cnt_row[0] ?? 0);

	$nm_pages = max(1, (int) ceil($total / $num));
	if ($page > $nm_pages) {
		$page = $nm_pages;
	}
	$from = ($page - 1) * $num;

	$pages = [];
	if ($total > 0) {
		for ($n = 1; $n <= $nm_pages; $n++) {
			$pages[] = $n;
		}
	}
	$smarty->assign('pages', $pages);
	$smarty->assign('tk_page', $page);
	$smarty->assign('tag_total_pages', $nm_pages);

	// Keep issue/press headers correct when a page starts mid-group.
	$last = null;
	if ($from > 0) {
		$z_prev = db_select(
			$db,
			'SELECT articles.id_issue FROM tags_articles'
				. ' INNER JOIN articles ON articles.id = tags_articles.id_article'
				. ' INNER JOIN issue ON issue.id = articles.id_issue'
				. ' INNER JOIN press ON press.id = issue.id_press'
				. ' WHERE tags_articles.id_tag = ?'
				. ' ORDER BY articles.date ASC, articles.id ASC'
				. ' LIMIT ?, 1',
			'ii',
			$id,
			$from - 1
		);
		$prev = $z_prev ? mysqli_fetch_array($z_prev) : false;
		if ($prev) {
			$last = $prev['id_issue'];
		}
	}

	$z = db_select(
		$db,
		'SELECT tags_articles.id_article AS id_article,'
			. ' articles.id_issue AS id_issue,'
			. ' articles.id_press AS id_press,'
			. ' articles.title AS title,'
			. ' articles.title_eng AS title_eng,'
			. ' issue.date AS date,'
			. ' press.title AS press_name'
			. ' FROM tags_articles'
			. ' INNER JOIN articles ON articles.id = tags_articles.id_article'
			. ' INNER JOIN issue ON issue.id = articles.id_issue'
			. ' INNER JOIN press ON press.id = issue.id_press'
			. ' WHERE tags_articles.id_tag = ?'
			. ' ORDER BY articles.date ASC, articles.id ASC'
			. ' LIMIT ?, ?',
		'iii',
		$id,
		$from,
		$num
	);
} else {
	// Untagged articles listing (admin/debug): hard cap to avoid OOM.
	$z = db_select(
		$db,
		'SELECT id AS id_article, id_issue, id_press, title, title_eng, date, title AS press_name'
			. ' FROM articles'
			. ' WHERE NOT EXISTS (SELECT 1 FROM tags_articles AS ta WHERE ta.id_article = articles.id)'
			. ' ORDER BY id ASC LIMIT 200'
	);
	$total = 0;
	$smarty->assign('pages', []);
	$smarty->assign('tk_page', 1);
	$smarty->assign('tag_total_pages', 1);
	$last = null;
}

$rubrics_by_article = [];
if ($wantRubrics) {
	$z_rub = db_select($db, 'SELECT id_article, id_menu FROM menu_articles');
	while ($z_rub && ($rub = mysqli_fetch_array($z_rub))) {
		$rubrics_by_article[(int) $rub['id_article']][] = $rub['id_menu'];
	}
}

$articles = [];
while ($z && ($t = mysqli_fetch_array($z))) {
	$ts = (int) ($t['date'] ?? 0);
	if ($isEng) {
		$t['date'] = $ts ? date('d F Y', $ts) : '';
	} else {
		$t['date'] = $ts ? date(($mnt[date('m', $ts)] ?? '') . ' Y', $ts) : '';
	}

	if ($last != $t['id_issue']) {
		$t['show'] = 1;
		$last = $t['id_issue'];
	} else {
		$t['show'] = 0;
	}

	if ($wantRubrics) {
		$t['rubrics'] = $rubrics_by_article[(int) $t['id_article']] ?? [];
	}

	$t['title_list'] = article_title_list_html($t['title'] ?? '');
	$t['title_eng_list'] = article_title_list_html($t['title_eng'] ?? '');
	$t['press_name_plain'] = title_plain($t['press_name'] ?? '');

	$articles[] = $t;
}

$smarty->assign('articles', $articles);
$smarty->assign('count', $total > 0 ? $total : count($articles));
$smarty->assign('count_page', count($articles));

if ($isEng) {
	$smarty->assign('title', $tag_name !== '' ? ('Tag: ' . $tag_name) : 'Tag');
} else {
	$smarty->assign('title', $tag_name !== '' ? ('Тег: ' . $tag_name) : 'Тег');
}

$r = [];
$z_menu = db_select($db, 'SELECT * FROM menu');
while ($z_menu && ($t = mysqli_fetch_array($z_menu))) {
	$t['name_plain'] = title_plain($t['name'] ?? '');
	$r[] = $t;
}
$smarty->assign('rubrics', $r);

include 'right.php';

$smarty->display('tag.tpl');
