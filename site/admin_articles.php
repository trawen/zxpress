<?php
require 'init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
require 'html_fix.php';
require_once __DIR__ . '/includes/ezine_slugs.php';
//error_reporting(E_ALL);

function admin_articles_read_text_from_disk(int $articleId): string
{
	$path = zx_storage_path('articles', (string) $articleId);
	$text = @file_get_contents($path);
	if ($text === false) {
		error_log('[FIX] admin_articles: article read failed id=' . $articleId . ' path=' . $path);
		return '';
	}
	return $text;
}

function admin_articles_read_text(mysqli $db, int $articleId): string
{
	$stmt = $db->prepare('SELECT text_ru FROM articles WHERE id=? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('i', $articleId);
		$stmt->execute();
		$res = $stmt->get_result();
		$row = $res ? mysqli_fetch_array($res) : false;
		$textRu = is_array($row) ? (string) ($row['text_ru'] ?? '') : '';
		if ($textRu !== '') {
			return $textRu;
		}
	}
	return admin_articles_read_text_from_disk($articleId);
}

function admin_articles_write_text(mysqli $db, int $articleId, string $text): bool
{
	$stmt = $db->prepare('UPDATE articles SET text_ru=? WHERE id=? LIMIT 1');
	if (!$stmt) {
		error_log('[FIX] admin_articles: prepare text_ru update failed id=' . $articleId . ' err=' . mysqli_error($db));
		return false;
	}
	$stmt->bind_param('si', $text, $articleId);
	$ok = $stmt->execute();
	if (!$ok) {
		error_log('[FIX] admin_articles: text_ru update failed id=' . $articleId . ' err=' . $stmt->error);
	}
	return $ok;
}

function admin_articles_copy_upload(string $key, string $leaf, string $tmpPath, string $context): bool
{
	$ok = zx_storage_copy_uploaded_file($key, $leaf, $tmpPath);
	if (!$ok) {
		error_log('[FIX] admin_articles: upload copy failed ctx=' . $context . ' key=' . $key . ' leaf=' . $leaf);
	}
	return $ok;
}

// title() moved to includes/functions.php

$html = $_REQUEST['html'];


$id_username = intval($_SESSION['id_username']);



$id = intval($_GET['id'] ?? 0);
if (!$id) {$_GET['go']="go"; $id=1;}

if (!empty($_GET['issue'])) {

	$issue = intval($_GET['issue']);
	
}
else {

	$stmt_issue0 = $db->prepare("SELECT id FROM issue WHERE id_press=? LIMIT 1");
	if ($stmt_issue0) {
		$stmt_issue0->bind_param("i", $id);
		$stmt_issue0->execute();
		$z = $stmt_issue0->get_result();
		$n = $z ? mysqli_fetch_array($z) : false;
		$issue = $n ? (int)$n[0] : 0;
	} else {
		$issue = 0;
	}

}

$smarty->assign('id_issue', $issue);





if (($_GET['go'] ?? '') == "go") {
$stmt_go = $db->prepare("SELECT * FROM issue WHERE id_press=? ORDER BY LENGTH(title) ASC, title ASC LIMIT 1");
if ($stmt_go) {
	$stmt_go->bind_param("i", $id);
	$stmt_go->execute();
	$z = $stmt_go->get_result();
	$a = $z ? mysqli_fetch_array($z) : false;
} else {
	$a = false;
}
$issue = $a ? (int)$a['id'] : 0;
header("Location: /admin_articles.php?id=$id&issue=$issue");
exit;	
}

$tm = time();

// INPUT FORM
if ($_POST['save'] == "Сохранить" and $id and $issue) {
csrf_verify();

// UPDATE DATE
if ($_POST['issue_date_change'] == 1) {

	$d = str_replace("/", ".", $_POST['issue_date']);
	$d = explode(".", $d);
	$date = mktime(0, 0, 0, $d[1], $d[0], $d[2]);

	if ($date > 0) {
		$stmt_idate = $db->prepare("UPDATE issue SET date=? WHERE id=? LIMIT 1");
		if ($stmt_idate) {
			$stmt_idate->bind_param("ii", $date, $issue);
			$stmt_idate->execute();
		}
	
	
		$monday = date('m', $date).date('d', $date);
		$year = date('Y', $date);
		$press_id = $id;
		$rawPressTitle = trim((string) ($_POST['press_title'] ?? ''));
		$title = plain_text_normalize_for_storage($rawPressTitle);
		log_content_plain_normalized('calendar.title_cal press=' . $press_id, $rawPressTitle, $title);
		$number = intval($_POST['issue_title']); // номер выпуска
	
		$stmt_scdel = $db->prepare("DELETE FROM calendar WHERE press_id_cal = ? AND number_cal = ? LIMIT 1");
		if ($stmt_scdel) {
			$stmt_scdel->bind_param("ii", $press_id, $number);
			$stmt_scdel->execute();
			if ($stmt_scdel->error) {
				error_log('[FIX] admin_articles: calendar cleanup failed press=' . $press_id . ' number=' . $number . ' err=' . $stmt_scdel->error);
			}
		} else {
			error_log('[FIX] admin_articles: prepare calendar cleanup failed press=' . $press_id . ' number=' . $number . ' err=' . mysqli_error($db));
		}
	
		$stmt_cal = $db->prepare("INSERT INTO calendar (`id_cal`, `press_id_cal`, `monday_cal`, `year_cal`, `title_cal`, `number_cal`, `date_cal`) VALUES (NULL, ?, ?, ?, ?, ?, ?)");
		$stmt_cal->bind_param("isssii", $press_id, $monday, $year, $title, $number, $date);
		$stmt_cal->execute();
	
	}

}



//UPDATE SCREENSHOTS

$stmt_scr_sel = $db->prepare("SELECT * FROM screens WHERE id_issue=?");
if ($stmt_scr_sel) {
	$stmt_scr_sel->bind_param("i", $issue);
	$stmt_scr_sel->execute();
	$z = $stmt_scr_sel->get_result();
} else {
	$z = false;
}
while ($z && ($t = mysqli_fetch_array($z))) {

	if (isset($_POST['screen_type_'.$t['id']])) {

		$type = intval($_POST['screen_type_'.$t['id']]);
		$iss = intval($_POST['screen_issue_'.$t['id']]);
		$sid = (int)$t['id'];
		$stmt_scr_up = $db->prepare("UPDATE screens SET type=?, id_issue=? WHERE id=? LIMIT 1");
		if ($stmt_scr_up) {
			$stmt_scr_up->bind_param("iii", $type, $iss, $sid);
			$stmt_scr_up->execute();
		}
	}

	if ($_POST['delete_screen_'.$t['id']] == 1) {
		
		$ids = (int)$t['id'];
		$stmt_scr_del = $db->prepare("DELETE FROM screens WHERE id=? LIMIT 1");
		if ($stmt_scr_del) {
			$stmt_scr_del->bind_param("i", $ids);
			$stmt_scr_del->execute();
		}
		//unlink("screens/1/".$t['id'].".png");=============================================================
	}
}



//UPDATE ILLUSTRATIONS

$stmt_il_sel = $db->prepare("SELECT * FROM illustrations WHERE id_il_issue=?");
if ($stmt_il_sel) {
	$stmt_il_sel->bind_param("i", $issue);
	$stmt_il_sel->execute();
	$z = $stmt_il_sel->get_result();
} else {
	$z = false;
}
while ($z && ($t = mysqli_fetch_array($z))) {

$il = (int)$t['id_il'];

	if (isset($_POST['illustration_issue_'.$il])) {

		$il_is = intval($_POST['illustration_issue_'.$il]);
		
		$stmt_il_up = $db->prepare("UPDATE illustrations SET id_il_issue=? WHERE id_il=? LIMIT 1");
		if ($stmt_il_up) {
			$stmt_il_up->bind_param("ii", $il_is, $il);
			$stmt_il_up->execute();
		}
		
	}

	if ($_POST['delete_illustration_'.$il] == 1) {
		
		$stmt_il_del = $db->prepare("DELETE FROM illustrations WHERE id_il=? LIMIT 1");
		if ($stmt_il_del) {
			$stmt_il_del->bind_param("i", $il);
			$stmt_il_del->execute();
		}
		
		//unlink("screens/1/".$t['id'].".png");=============================================================
	}

}


//UPDATE ARTICLES (UPDATE TITLE, DELETE ARTICLE, CHANGE ISSUE)

$stmt_art_loop = $db->prepare("SELECT * FROM articles WHERE id_issue=?");
if ($stmt_art_loop) {
	$stmt_art_loop->bind_param("i", $issue);
	$stmt_art_loop->execute();
	$z = $stmt_art_loop->get_result();
} else {
	$z = false;
}
$stmt_art_del = $db->prepare("DELETE FROM articles WHERE id=? LIMIT 1");
$stmt_log256 = $db->prepare("INSERT INTO log (`id`, `id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `id_cover`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt_ta_ins = $db->prepare("INSERT INTO tags_articles (`id`, `id_tag`, `id_article`) VALUES (NULL, ?, ?)");
$stmt_ta_sel = $db->prepare("SELECT * FROM tags_articles WHERE id_article=?");
$stmt_ta_del = $db->prepare("DELETE FROM tags_articles WHERE id=? LIMIT 1");
$stmt_tag_ins = $db->prepare("INSERT INTO tags (`id`, `tag_name`) VALUES (NULL, ?)");

while ($z && ($t = mysqli_fetch_array($z))) {
    
	$id_article = $t['id'];
	
	// UPDATE NUMBER
	if ($_POST['number_change_'.$id_article] == 1) {
		
		$anm = intval($_POST['article_number_'.$id_article]);
		$stmt_num = $db->prepare("UPDATE articles SET number=? WHERE id=? LIMIT 1");
		if ($stmt_num) {
			$stmt_num->bind_param("ii", $anm, $id_article);
			$stmt_num->execute();
		}

	}
	
	// UPDATE HIDDEN
	if ($_POST['hidden_change_'.$id_article] == 1) {
				
		if ($_POST['hidden_article_'.$id_article]) {$h = 1;} else {$h = 0;};
		
		$stmt_hid = $db->prepare("UPDATE articles SET temp=? WHERE id=? LIMIT 1");
		if ($stmt_hid) {
			$stmt_hid->bind_param("ii", $h, $id_article);
			$stmt_hid->execute();
		}

	}
	
	// REMOVE ARTICLE
	if ($_POST['issue_change_'.$id_article] == 1) {
		
		$iss = intval($_POST['article_issue_'.$id_article]);
		$stmt_iss = $db->prepare("UPDATE articles SET id_issue=? WHERE id=? LIMIT 1");
		if ($stmt_iss) {
			$stmt_iss->bind_param("ii", $iss, $id_article);
			$stmt_iss->execute();
		}

	}
	
	// UPDATE TITLE
	if ($_POST['title_change_'.$id_article] == 1) {
		
		$rawAt = trim((string) ($_POST['article_title_' . $id_article] ?? ''));
		$title = plain_text_normalize_for_storage($rawAt);
		log_content_plain_normalized('articles.title id=' . $id_article, $rawAt, $title);
		$stmt_title = $db->prepare("UPDATE articles SET title=? WHERE id=? LIMIT 1");
		$stmt_title->bind_param("si", $title, $id_article);
		$stmt_title->execute();

	}

	// UPDATE META + SLUGS
	if (($_POST['slug_change_'.$id_article] ?? null) == 1) {
		$metaRu = plain_text_normalize_for_storage(trim((string) ($_POST['meta_description_ru_' . $id_article] ?? '')));
		$metaEn = plain_text_normalize_for_storage(trim((string) ($_POST['meta_description_en_' . $id_article] ?? '')));
		$slugInputRu = trim((string) ($_POST['slug_ru_' . $id_article] ?? ''));
		$slugInputEn = trim((string) ($_POST['slug_en_' . $id_article] ?? ''));

		$articleRow = array_merge($t, [
			'meta_description_ru' => $metaRu,
			'meta_description_en' => $metaEn,
		]);
		$slugs = ezn_admin_resolve_article_slugs(
			$db,
			$slugInputRu,
			$slugInputEn,
			static fn (): string => ezn_default_article_ru($articleRow),
			static fn (): string => ezn_default_article_en($articleRow),
			(int) $id_article,
			(int) $issue
		);

		$stmt_meta_slug = $db->prepare(
			'UPDATE articles SET meta_description_ru=?, meta_description_en=?, slug_ru=?, slug_en=? WHERE id=? LIMIT 1'
		);
		if ($stmt_meta_slug) {
			$stmt_meta_slug->bind_param('ssssi', $metaRu, $metaEn, $slugs['slug_ru'], $slugs['slug_en'], $id_article);
			$stmt_meta_slug->execute();
			$stmt_meta_slug->close();
		}
	}
	
	// UPDATE ARTICLE
	if ($_POST['article_change_'.$id_article] == 1) {
		
		$text = html_legacy_normalize(stripslashes($_POST['article_text_'.$id_article]), (int) $html, $issue);
		admin_articles_write_text($db, (int) $id_article, $text);
	}
	
	
	// DELETE ARTILCE
	if ($_POST['delete_article_'.$id_article] == 1) {
				
		if ($stmt_art_del) {
			$stmt_art_del->bind_param("i", $id_article);
			$stmt_art_del->execute();
		}
		//unlink("articles/$id_article");
		$log_type256 = 256;
		if ($stmt_log256) {
			$log_screen_zero = 0;
			$log_cover_zero = 0;
			$stmt_log256->bind_param("iiiiiiii", $id, $id_article, $issue, $id_username, $tm, $log_type256, $log_screen_zero, $log_cover_zero);
			$stmt_log256->execute();
		}
		
	}
	
	// ADD TAG
	if ($_POST['article_add_tag_'.$id_article] > 0) {
	
		$id_tag = intval($_POST['article_add_tag_'.$id_article]);
		if ($stmt_ta_ins) {
			$stmt_ta_ins->bind_param("ii", $id_tag, $id_article);
			$stmt_ta_ins->execute();
		}
		
	}
	
	// DELETE TAGS
	if ($stmt_ta_sel) {
		$stmt_ta_sel->bind_param("i", $id_article);
		$stmt_ta_sel->execute();
		$z3 = $stmt_ta_sel->get_result();
	} else {
		$z3 = false;
	}
	while ($z3 && ($t2 = mysqli_fetch_array($z3))) {
		$id_tag = $t2['id'];
		if ($_POST['delete_article_tag_'.$id_tag] == 1) {
			if ($stmt_ta_del) {
				$stmt_ta_del->bind_param("i", $id_tag);
				$stmt_ta_del->execute();
			}
		}
	}
	
	// NEW TAG
	if ($_POST['article_new_tag_'.$id_article] !="") {
	
		$tag_name = plain_text_normalize_for_storage(strip_tags(trim($_POST['article_new_tag_' . $id_article] ?? '')));
		if ($stmt_tag_ins) {
			$stmt_tag_ins->bind_param("s", $tag_name);
			$stmt_tag_ins->execute();
		}
		
		$id_tag = mysqli_insert_id($db);
		if ($stmt_ta_ins && $id_tag) {
			$stmt_ta_ins->bind_param("ii", $id_tag, $id_article);
			$stmt_ta_ins->execute();
		}
		
		
	}
	
	
	

}


//UPLOAD SCREENSHOT
$ext = strtolower(substr($_FILES['upload_screen']['name'], -3));
$ext = mysqli_real_escape_string($db, $ext);

if ($_FILES['upload_screen']['tmp_name'] and ($ext=="png" or $ext=="jpg")) {

	$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['upload_screen']['tmp_name']);
	$allowed_mime = ['image/png', 'image/jpeg'];
	if (!in_array($mime, $allowed_mime, true)) {
		error_log("admin_articles: rejected upload_screen, MIME=$mime");
	} else {

	$screen_type_default = 0;
	$stmt_scr_ins = $db->prepare("INSERT INTO screens (`id`, `id_press`, `id_issue`, `type`, `date`, `format`) VALUES (NULL, ?, ?, ?, ?, ?)");
	if ($stmt_scr_ins) {
		$stmt_scr_ins->bind_param("iiiis", $id, $issue, $screen_type_default, $tm, $ext);
		$stmt_scr_ins->execute();
		if ($stmt_scr_ins->error) {
			error_log('[FIX] admin_articles: insert screen failed issue=' . $issue . ' press=' . $id . ' err=' . $stmt_scr_ins->error);
		}
	} else {
		error_log('[FIX] admin_articles: prepare insert screen failed issue=' . $issue . ' press=' . $id . ' err=' . mysqli_error($db));
	}

	//

	$id_screen = mysqli_insert_id($db);
	$safe_name = basename("$id_screen.$ext");
	admin_articles_copy_upload('screens', '1/' . $safe_name, (string) ($_FILES['upload_screen']['tmp_name'] ?? ''), 'upload_screen');
	
	$log_type2 = 2;
	$stmt_log2 = $db->prepare("INSERT INTO log (`id`, `id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `id_cover`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
	if ($stmt_log2) {
		$log_article_zero = 0;
		$log_cover_zero = 0;
		$stmt_log2->bind_param("iiiiiiii", $id, $log_article_zero, $issue, $id_username, $tm, $log_type2, $id_screen, $log_cover_zero);
		$stmt_log2->execute();
	}

	} // end MIME check
}


//UPLOAD ILLUSTRATION
$ext = strtolower(substr($_FILES['upload_illustration']['name'], -3));
$ext = mysqli_real_escape_string($db, $ext);

if ($_FILES['upload_illustration']['tmp_name'] and ($ext=="png" or $ext=="gif")) {

	$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['upload_illustration']['tmp_name']);
	$allowed_mime = ['image/png', 'image/gif'];
	if (!in_array($mime, $allowed_mime, true)) {
		error_log("admin_articles: rejected upload_illustration, MIME=$mime");
	} else {

	$il_type_zero = 0;
	$stmt_il_ins = $db->prepare("INSERT INTO illustrations (`id_il`, `id_il_issue`, `il_type`, `il_date`) VALUES (NULL, ?, ?, ?)");
	if ($stmt_il_ins) {
		$stmt_il_ins->bind_param("iii", $issue, $il_type_zero, $tm);
		$stmt_il_ins->execute();
	}

	//

	$id_illustration = mysqli_insert_id($db);
	$safe_name = basename($id_illustration . ".png");
	admin_articles_copy_upload('illustrations', '1/' . $safe_name, (string) ($_FILES['upload_illustration']['tmp_name'] ?? ''), 'upload_illustration');
	
	$log_type3 = 3;
	$stmt_log3 = $db->prepare("INSERT INTO log (`id`, `id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `id_cover`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
	if ($stmt_log3) {
		$log_article_zero = 0;
		$log_cover_zero = 0;
		$stmt_log3->bind_param("iiiiiiii", $id, $log_article_zero, $issue, $id_username, $tm, $log_type3, $id_illustration, $log_cover_zero);
		$stmt_log3->execute();
	}

	} // end MIME check
}


//UPLOAD TEXT
$ext = strtolower(substr($_FILES['upload_text']['name'], -3));

if ($_FILES['upload_text']['tmp_name'] and $ext=="txt") {

	$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['upload_text']['tmp_name']);
	if (strpos($mime, 'text/') !== 0) {
		error_log("admin_articles: rejected upload_text, MIME=$mime");
	} else {

	$temp_default = 0;
	$views_default = 0;
	$title_eng_default = '';
	$file_default = '';
	$name_default = '';
	$dt_default = 0;
	$id_press_default = 0;
	$text_type_default = 0;
	$stmt_txt_ins = $db->prepare("INSERT INTO articles (`id`, `id_issue`, `title`, `temp`, `date`, `views`, `number`, `title_eng`, `file`, `name`, `dt`, `id_press`, `text_type`) VALUES (NULL, ?, '', ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)");
	if ($stmt_txt_ins) {
		$stmt_txt_ins->bind_param("iiiisssiii", $issue, $temp_default, $tm, $views_default, $title_eng_default, $file_default, $name_default, $dt_default, $id_press_default, $text_type_default);
		$stmt_txt_ins->execute();
		if ($stmt_txt_ins->error) {
			error_log('[FIX] admin_articles: insert text article failed issue=' . $issue . ' press=' . $id . ' err=' . $stmt_txt_ins->error);
		}
	} else {
		error_log('[FIX] admin_articles: prepare insert text article failed issue=' . $issue . ' press=' . $id . ' err=' . mysqli_error($db));
	}

	$id_text = mysqli_insert_id($db);
	$safe_name = basename($id_text);
	admin_articles_copy_upload('articles', $safe_name, (string) ($_FILES['upload_text']['tmp_name'] ?? ''), 'upload_text');
	if ($id_text > 0) {
		$uploadedText = @file_get_contents((string) ($_FILES['upload_text']['tmp_name'] ?? ''));
		if ($uploadedText !== false) {
			admin_articles_write_text($db, (int) $id_text, (string) $uploadedText);
		}
	}

	$log_type1 = 1;
	$stmt_log1a = $db->prepare("INSERT INTO log (`id`, `id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `id_cover`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
	if ($stmt_log1a) {
		$log_screen_zero = 0;
		$log_cover_zero = 0;
		$stmt_log1a->bind_param("iiiiiiii", $id, $id_text, $issue, $id_username, $tm, $log_type1, $log_screen_zero, $log_cover_zero);
		$stmt_log1a->execute();
	}
	} // end MIME check
}



//UPDATE DATE
$stmt_yf = $db->prepare("SELECT * FROM issue WHERE id_press=? AND date!=0 ORDER BY date ASC LIMIT 1");
if ($stmt_yf) {
	$stmt_yf->bind_param("i", $id);
	$stmt_yf->execute();
	$z = $stmt_yf->get_result();
} else {
	$z = false;
}

if ($z && ($t = mysqli_fetch_array($z))) {
	$date=$t['date']; 
	$stmt_pyf = $db->prepare("UPDATE press SET years_from=? WHERE id=? LIMIT 1");
	if ($stmt_pyf) {
		$stmt_pyf->bind_param("ii", $date, $id);
		$stmt_pyf->execute();
	}
}
$stmt_yt = $db->prepare("SELECT * FROM issue WHERE id_press=? AND date!=0 ORDER BY date DESC LIMIT 1");
if ($stmt_yt) {
	$stmt_yt->bind_param("i", $id);
	$stmt_yt->execute();
	$z = $stmt_yt->get_result();
} else {
	$z = false;
}

if ($z && ($t = mysqli_fetch_array($z))) {
	$date=$t['date']; 
	$stmt_pyt = $db->prepare("UPDATE press SET years_to=? WHERE id=? LIMIT 1");
	if ($stmt_pyt) {
		$stmt_pyt->bind_param("ii", $date, $id);
		$stmt_pyt->execute();
	}
}


//NEW ARTILCE
if ($_POST['article_create'] == 1) {

	$num = 1;
	if ( $_POST['autonumber'] ) {
	
$stmt_autonum = $db->prepare("SELECT articles.number FROM issue, articles WHERE issue.id_press=? AND issue.id=? AND articles.id_issue=issue.id ORDER BY articles.number DESC LIMIT 1");
	if ($stmt_autonum) {
		$stmt_autonum->bind_param("ii", $id, $issue);
		$stmt_autonum->execute();
		$z5 = $stmt_autonum->get_result();
		$nu = $z5 ? mysqli_fetch_array($z5) : false;
		$num = ($nu && isset($nu[0]) && (int)$nu[0]) ? (int)$nu[0] + 10 : 1;
	}
		
	}


	$rawNewTitle = trim((string) ($_POST['new_article_title'] ?? ''));
	$title = plain_text_normalize_for_storage($rawNewTitle);
	log_content_plain_normalized('articles.title new', $rawNewTitle, $title);

	$temp_default = 0;
	$views_default = 0;
	$title_eng_default = '';
	$file_default = '';
	$name_default = '';
	$dt_default = 0;
	$id_press_default = 0;
	$text_type_default = 0;
	$stmt_ins = $db->prepare("INSERT INTO articles (`id`, `id_issue`, `title`, `temp`, `date`, `views`, `number`, `title_eng`, `file`, `name`, `dt`, `id_press`, `text_type`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	if (!$stmt_ins) {
		error_log('[FIX] admin_articles: prepare create article failed issue=' . $issue . ' press=' . $id . ' err=' . mysqli_error($db));
	} else {
		$stmt_ins->bind_param("isiiiisssiii", $issue, $title, $temp_default, $tm, $views_default, $num, $title_eng_default, $file_default, $name_default, $dt_default, $id_press_default, $text_type_default);
		$stmt_ins->execute();
		if ($stmt_ins->error) {
			error_log('[FIX] admin_articles: insert create article failed issue=' . $issue . ' press=' . $id . ' err=' . $stmt_ins->error);
		}
	}
	
	$id_text = mysqli_insert_id($db);
	if ($id_text > 0) {
		$articleRow = [
			'id' => $id_text,
			'id_issue' => $issue,
			'title' => $title,
			'title_eng' => '',
			'meta_description_ru' => '',
			'meta_description_en' => '',
		];
		$slugs = ezn_admin_resolve_article_slugs(
			$db,
			'',
			'',
			static fn (): string => ezn_default_article_ru($articleRow),
			static fn (): string => ezn_default_article_en($articleRow),
			(int) $id_text,
			(int) $issue
		);
		$stmt_new_slug = $db->prepare('UPDATE articles SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
		if ($stmt_new_slug) {
			$stmt_new_slug->bind_param('ssi', $slugs['slug_ru'], $slugs['slug_en'], $id_text);
			$stmt_new_slug->execute();
			$stmt_new_slug->close();
		}
	}
	$text = html_legacy_normalize(stripslashes($_POST['new_article_text']), (int) $html, $issue);
	if ($id_text > 0) {
		admin_articles_write_text($db, (int) $id_text, $text);
	} else {
		error_log('[FIX] admin_articles: create article id missing issue=' . $issue . ' press=' . $id);
	}
	
	$log_type1b = 1;
	$stmt_log1b = $db->prepare("INSERT INTO log (`id`, `id_press`, `id_article`, `id_issue`, `id_user`, `date`, `type`, `id_screen`, `id_cover`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
	if ($stmt_log1b) {
		$log_screen_zero = 0;
		$log_cover_zero = 0;
		$stmt_log1b->bind_param("iiiiiiii", $id, $id_text, $issue, $id_username, $tm, $log_type1b, $log_screen_zero, $log_cover_zero);
		$stmt_log1b->execute();
	}

	
	// UPDATE FINISHED ISSUE PROCENT 	
	$nm = 0;
	$stmt_iss_list = $db->prepare("SELECT * FROM issue WHERE id_press=?");
	if ($stmt_iss_list) {
		$stmt_iss_list->bind_param("i", $id);
		$stmt_iss_list->execute();
		$x = $stmt_iss_list->get_result();
	} else {
		$x = false;
	}
	$stmt_cnt_art = $db->prepare("SELECT COUNT(*) AS c FROM articles WHERE id_issue=?");
	while ($x && ($u = mysqli_fetch_array($x))) {
	
		$idi = (int)$u['id'];
		
		if ($stmt_cnt_art) {
			$stmt_cnt_art->bind_param("i", $idi);
			$stmt_cnt_art->execute();
			$v = $stmt_cnt_art->get_result();
			$a = $v ? mysqli_fetch_array($v) : false;
		} else {
			$a = false;
		}
				
		if ($a && $a[0]) {$nm ++;}
		
	}
	
	$stmt_p_online = $db->prepare("UPDATE press SET online_issues=? WHERE id=?");
	if ($stmt_p_online) {
		$stmt_p_online->bind_param("ii", $nm, $id);
		$stmt_p_online->execute();
	}
	
}



// UPDATE ARTICLES NUMBERS 
$stmt_oa = $db->prepare("SELECT COUNT(*) AS c FROM issue, articles WHERE issue.id_press=? AND articles.id_issue=issue.id");
if ($stmt_oa) {
	$stmt_oa->bind_param("i", $id);
	$stmt_oa->execute();
	$z = $stmt_oa->get_result();
	$nm = $z ? mysqli_fetch_array($z) : false;
} else {
	$nm = false;
}

if ($nm && $nm[0] > 0) {
	$stmt_poa = $db->prepare("UPDATE press SET online_articles=? WHERE id=? LIMIT 1");
	if ($stmt_poa) {
		$oa = (int)$nm[0];
		$stmt_poa->bind_param("ii", $oa, $id);
		$stmt_poa->execute();
	}
}



// UPDATE YEARS
$stmt_yfa = $db->prepare("SELECT * FROM issue, press WHERE issue.id_press=? AND issue.id_press=press.id AND issue.date!=0 ORDER BY issue.date ASC LIMIT 1");
if ($stmt_yfa) {
	$stmt_yfa->bind_param("i", $id);
	$stmt_yfa->execute();
	$z = $stmt_yfa->get_result();
} else {
	$z = false;
}

$t = $z ? mysqli_fetch_array($z) : false;
$years_from = $t ? $t['date'] : 0;
//echo $years_from."<br>";

$stmt_yta = $db->prepare("SELECT * FROM issue, press WHERE issue.id_press=? AND issue.id_press=press.id AND issue.date!=0 ORDER BY issue.date DESC LIMIT 1");
if ($stmt_yta) {
	$stmt_yta->bind_param("i", $id);
	$stmt_yta->execute();
	$z = $stmt_yta->get_result();
} else {
	$z = false;
}

$t = $z ? mysqli_fetch_array($z) : false;
$years_to = $t ? $t['date'] : 0;
//echo $years_to;

if ($years_from AND $years_to) {
	$stmt_pyears = $db->prepare("UPDATE press SET years_from=?, years_to=? WHERE id=? LIMIT 1");
	if ($stmt_pyears) {
		$stmt_pyears->bind_param("iii", $years_from, $years_to, $id);
		$stmt_pyears->execute();
	}
	
}


if ($_POST['jump']) {$jump = "&jump=1#jump";}
if ($_POST['autonumber']) {$au = "&au=1";}
if ($_POST['html']) {$html = "&html=1";}

// Manticore fulltext index rebuild is not instant:
// queue a background reindex for articles after admin "save".
// Worker debounces multiple saves into a single index run.
$manticoreQueueDir = zx_data_root() . '/manticore-reindex';
@mkdir($manticoreQueueDir, 0775, true);
@file_put_contents(
	$manticoreQueueDir . '/articles.pending',
	(string) time(),
	LOCK_EX
);

//REDIRECT
header("Location: /admin_articles.php?id=$id&issue=$issue$au$html$jump");
exit;

}








//GET INFO
$stmt_iss_list_get = $db->prepare("SELECT * FROM issue WHERE id_press=? ORDER BY LENGTH(title) ASC, title ASC");
if ($stmt_iss_list_get) {
	$stmt_iss_list_get->bind_param("i", $id);
	$stmt_iss_list_get->execute();
	$z = $stmt_iss_list_get->get_result();
} else {
	error_log("admin_articles: prepare issues list failed: " . mysqli_error($db));
	$z = false;
}
$n = 0;
unset($t);
while ($z && ($t = mysqli_fetch_array($z))) {
if ($issue == $t['id']) {$t['date']=date("d.m.Y", $t['date']); $smarty->assign('issue', $t); $smarty->assign('issue_title', $t['title']);}
$iss[$n] = $t; $n++;

}
$smarty->assign('issues', $iss);


$z = db_select($db, "SELECT * FROM press ORDER BY title ASC"); 
$n = 0;
unset($t);
while ($z && ($t = mysqli_fetch_array($z))) {$pl[$n] = $t; $n++;}
$smarty->assign('press_list', $pl);



//$z = mysqli_query($db,"SELECT * FROM cities, press WHERE press.id='$id' AND city=cities.id " );
$stmt_press_get = $db->prepare("SELECT *, press.id AS id FROM press LEFT OUTER JOIN cities ON press.city=cities.id WHERE press.id=?");
if ($stmt_press_get) {
	$stmt_press_get->bind_param("i", $id);
	$stmt_press_get->execute();
	$z = $stmt_press_get->get_result();
	$smarty->assign('press', $z ? mysqli_fetch_array($z) : false);
} else {
	error_log("admin_articles: prepare press get failed: " . mysqli_error($db));
	$smarty->assign('press', false);
}



$stmt_scr_get = $db->prepare("SELECT * FROM screens WHERE id_issue=?");
if ($stmt_scr_get) {
	$stmt_scr_get->bind_param("i", $issue);
	$stmt_scr_get->execute();
	$z = $stmt_scr_get->get_result();
} else {
	$z = false;
}
$n = 0;
unset($t);
while ($z && ($t = mysqli_fetch_array($z))) {$scr[$n] = $t; $n++;}
$smarty->assign('screens', $scr);


$stmt_il_get = $db->prepare("SELECT * FROM illustrations WHERE id_il_issue=?");
if ($stmt_il_get) {
	$stmt_il_get->bind_param("i", $issue);
	$stmt_il_get->execute();
	$z = $stmt_il_get->get_result();
} else {
	$z = false;
}
$n = 0;
unset($t);
while ($z && ($t = mysqli_fetch_array($z))) {$ils[$n] = $t; $n++;}
$smarty->assign('illustrations', $ils);



//TEXT & TOPIC

// Prefetch tags for all articles in this issue (avoid N+1)
$stmt_tags_pref = $db->prepare("SELECT tags.*, tags_articles.id_article FROM tags, tags_articles, articles WHERE articles.id_issue=? AND tags_articles.id_article=articles.id AND tags.id=tags_articles.id_tag");
$tags_by_article = [];
if ($stmt_tags_pref) {
	$stmt_tags_pref->bind_param("i", $issue);
	$stmt_tags_pref->execute();
	$z_tags = $stmt_tags_pref->get_result();
	while ($z_tags && ($tg = mysqli_fetch_array($z_tags))) {
		$tags_by_article[$tg['id_article']][] = $tg;
	}
}

// Prefetch log entries with users for all articles in this issue (avoid N+1)
$stmt_log_pref = $db->prepare("SELECT log.*, users.* FROM log, users, articles WHERE articles.id_issue=? AND log.id_article=articles.id AND log.type=1 AND users.id=log.id_user");
$log_by_article = [];
if ($stmt_log_pref) {
	$stmt_log_pref->bind_param("i", $issue);
	$stmt_log_pref->execute();
	$z_log = $stmt_log_pref->get_result();
	while ($z_log && ($byus = mysqli_fetch_array($z_log))) {
		$log_by_article[$byus['id_article']][] = $byus;
	}
}

$stmt_art_get = $db->prepare("SELECT * FROM articles WHERE id_issue=? ORDER BY date DESC");
if ($stmt_art_get) {
	$stmt_art_get->bind_param("i", $issue);
	$stmt_art_get->execute();
	$z = $stmt_art_get->get_result();
} else {
	$z = false;
}
$art = [];
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {

	$id_art = $t['id'];
$t['by'] = $log_by_article[$id_art] ?? [];
$t['tags'] = $tags_by_article[$id_art] ?? [];
	$t['text'] = (string) ($t['text_ru'] ?? '');
	if ($t['text'] === '') {
		$t['text'] = admin_articles_read_text_from_disk((int) $t['id']);
	}
$art[$n] = $t;
$n++;
}
$smarty->assign('articles', $art);



//TAGS
$z = db_select($db, "SELECT * FROM tags ORDER BY tag_name"); 
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {$tgs[$n] = $t; $n++;}
$smarty->assign('tags', $tgs);



//FILES
$stmt_files_get = $db->prepare("SELECT * FROM files, issue WHERE files.id_issue=? AND issue.id=?");
if ($stmt_files_get) {
	$stmt_files_get->bind_param("ii", $issue, $issue);
	$stmt_files_get->execute();
	$z = $stmt_files_get->get_result();
} else {
	$z = false;
}
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {$fl[$n] = $t; $n++;}
$smarty->assign('files', $fl);

$smarty->assign('au', htmlspecialchars($_GET['au'] ?? '', ENT_QUOTES, 'UTF-8'));
$smarty->assign('jump', htmlspecialchars($_GET['jump'] ?? '', ENT_QUOTES, 'UTF-8'));
$smarty->assign('id', $id);
$smarty->assign('html', $html);

//$smarty->debugging = true;
$smarty->display('admin_articles.tpl');


?>