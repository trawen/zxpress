<?php
require 'init.inc';
require 'html_fix.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

/// log
/// 1 - добавление статьи
/// 2 - добавления скриншота
/// 3 - добавление иллюстрации
/// 4 - добавление главы

/// 256 - удаление статьи
///
/// 100 - добавление нового печатного издания
/// 101 - добавления картинки
/// 102 - добавление файла
/// 128 - удаление файла
/// 160 - удаление картинки
/// 111 - обновление инфы

// title() moved to includes/functions.php






$id_username = intval($_SESSION['id_username']);




//error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);

//$smarty->debugging = true;



$tm = time();

// INPUT FORM
if ($_POST['save'] == "Сохранить" and $id != "") {
csrf_verify();





// CREATE NEW ISSUE
if ($id == "0" and $_POST['title1'] !="") {

	// MySQL strict mode: NOT NULL columns need values — placeholder row, then UPDATE below applies form data.
	if (!db_exec(
		$db,
		"INSERT INTO books (title1, title2, series, publisher, authors, annotation, isbn, pages, city_id, date, circulation, type, language, online, image_id, file_id) "
		. "VALUES ('','','','','','','',0,0,0,0,0,0,0,0,0)"
	)) {
		error_log('[FIX] admin_books_light: INSERT books placeholder failed: ' . mysqli_error($db));
	}
	$id = mysqli_insert_id($db);
	$log_t = 100;
	$stmt_l100 = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, 0, 0, ?, ?, ?)'
	);
	if ($stmt_l100) {
		$stmt_l100->bind_param('iiii', $id, $id_username, $tm, $log_t);
		$stmt_l100->execute();
	}

}





// UPLOAD PICTURES
$up1 = (isset($_FILES['upload_picture1']) && is_array($_FILES['upload_picture1'])) ? $_FILES['upload_picture1'] : [];
$up1name = (string) ($up1['name'] ?? '');
$ext = $up1name !== '' ? strtolower(substr($up1name, -3)) : '';
$up1tmp = (string) ($up1['tmp_name'] ?? '');

if ($up1tmp !== '' && ($ext == "png" or $ext == "jpg")) {

	$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $up1tmp);
	$allowed_pic_mime = ['image/png', 'image/jpeg'];
	if (!in_array($mime, $allowed_pic_mime, true)) {
		error_log("admin_books_light: rejected upload_picture1, MIME=$mime");
	} else {

	$stmt_pic = $db->prepare('INSERT INTO pictures (`id`, `book_id`, `type`) VALUES (NULL, ?, 0)');
	if ($stmt_pic) {
		$stmt_pic->bind_param('i', $id);
		$stmt_pic->execute();
	}
	$id_picture = mysqli_insert_id($db);

	copy($up1tmp, zx_storage_path('pictures', $id_picture . '.jpg'));
	$log_t101 = 101;
	$stmt_l101 = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, ?, 0, ?, ?, ?)'
	);
	if ($stmt_l101) {
		$stmt_l101->bind_param('iiiii', $id, $id_picture, $id_username, $tm, $log_t101);
		$stmt_l101->execute();
	}

	} // end MIME check
}

$up2 = (isset($_FILES['upload_picture2']) && is_array($_FILES['upload_picture2'])) ? $_FILES['upload_picture2'] : [];
$up2name = (string) ($up2['name'] ?? '');
$ext = $up2name !== '' ? strtolower(substr($up2name, -3)) : '';
$up2tmp = (string) ($up2['tmp_name'] ?? '');

if ($up2tmp !== '' && ($ext == "png" or $ext == "jpg")) {

	$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $up2tmp);
	$allowed_pic_mime = ['image/png', 'image/jpeg'];
	if (!in_array($mime, $allowed_pic_mime, true)) {
		error_log("admin_books_light: rejected upload_picture2, MIME=$mime");
	} else {

	$stmt_pic2 = $db->prepare('INSERT INTO pictures (`id`, `book_id`, `type`) VALUES (NULL, ?, 0)');
	if ($stmt_pic2) {
		$stmt_pic2->bind_param('i', $id);
		$stmt_pic2->execute();
	}
	$id_picture = mysqli_insert_id($db);

	copy($up2tmp, zx_storage_path('pictures', $id_picture . '.jpg'));

	$stmt_l101b = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, ?, 0, ?, ?, ?)'
	);
	if ($stmt_l101b) {
		$log_t101b = 101;
		$stmt_l101b->bind_param('iiiii', $id, $id_picture, $id_username, $tm, $log_t101b);
		$stmt_l101b->execute();
	}

	} // end MIME check
}







// UPLOAD FILE
$uploadBf = (isset($_FILES['upload_file']) && is_array($_FILES['upload_file'])) ? $_FILES['upload_file'] : [];
$uploadBfName = (string) ($uploadBf['name'] ?? '');
$ext = $uploadBfName !== '' ? strtolower(substr($uploadBfName, -3)) : '';
$tmpBf = (string) ($uploadBf['tmp_name'] ?? '');

if ($tmpBf !== '' && ($ext == "rar" or $ext == "zip")) {

	$mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpBf);
	$allowed_file_mime = ['application/zip', 'application/x-rar-compressed', 'application/x-rar', 'application/octet-stream'];
	if (!in_array($mime, $allowed_file_mime, true)) {
		error_log("admin_books_light: rejected upload_file, MIME=$mime");
	} else {

	$rawAuthor = trim((string) ($_POST['upload_file_author'] ?? ''));
	$author = plain_text_normalize_for_storage($rawAuthor);
	log_content_plain_normalized('books_files.author book=' . $id, $rawAuthor, $author);
	$rawComment = trim((string) ($_POST['upload_file_comment'] ?? ''));
	$comment = plain_text_normalize_for_storage($rawComment);
	log_content_plain_normalized('books_files.comment book=' . $id, $rawComment, $comment);
	$type = intval($_POST['upload_file_type'] ?? 0);
	$name = pathinfo($uploadBfName);
	$size = (int)ceil(((int) ($uploadBf['size'] ?? 0)) / 1000);
	
	$name['filename'] = substr($name['basename'], 0, -1 * strlen($name['extension']) - 1); 

	
	$n = 1;
	$prf = "";
	while (file_exists(zx_storage_path('books_files', $name['filename'].$prf.".".$name['extension']))) {
		$prf = "_".$n;
		$n ++;
	}
	
	$filename = $name['filename'].$prf.".".$name['extension'];

	$stmt_bf = $db->prepare("INSERT INTO books_files (`id`, `book_id`, `file_name`, `file_size`, `file_type`, `author`, `comment`, `upload_date`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)");
	if ($stmt_bf) {
		$stmt_bf->bind_param("isiissi", $id, $filename, $size, $type, $author, $comment, $tm);
		$stmt_bf->execute();
	}
	$id_file = mysqli_insert_id($db);
	
	copy($tmpBf, zx_storage_path('books_files', $filename));
	$log_t102 = 102;
	$stmt_l102 = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, ?, 0, ?, ?, ?)'
	);
	if ($stmt_l102) {
		$stmt_l102->bind_param('iiiii', $id, $id_file, $id_username, $tm, $log_t102);
		$stmt_l102->execute();
	}

	} // end MIME check
}






// DELETE FILE
$stmt_bfl = $db->prepare("SELECT * FROM books_files WHERE book_id=?");
if ($stmt_bfl) {
	$stmt_bfl->bind_param("i", $id);
	$stmt_bfl->execute();
	$z = $stmt_bfl->get_result();
} else {
	$z = false;
}

while ($z && ($t = mysqli_fetch_array($z))) {

if ($_POST['file_delete_'.$t['id']]) {

	$id_file = (int)$t['id'];

	$stmt_bfd = $db->prepare("DELETE FROM books_files WHERE id=? LIMIT 1");
	if ($stmt_bfd) {
		$stmt_bfd->bind_param("i", $id_file);
		$stmt_bfd->execute();
	}
	@unlink(zx_storage_path('books_files', (string) $t['file_name']));
	$log_t128 = 128;
	$stmt_l128 = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, ?, 0, ?, ?, ?)'
	);
	if ($stmt_l128) {
		$stmt_l128->bind_param('iiiii', $id, $id_file, $id_username, $tm, $log_t128);
		$stmt_l128->execute();
	}
	
}
}



// UPDATE & DELETE PICTURES
$stmt_picq = $db->prepare("SELECT * FROM pictures WHERE book_id=?");
if ($stmt_picq) {
	$stmt_picq->bind_param("i", $id);
	$stmt_picq->execute();
	$z = $stmt_picq->get_result();
} else {
	$z = false;
}

while ($z && ($t = mysqli_fetch_array($z))) {

$id_pic = (int)$t['id'];

if ($_POST['change_picture_type_'.$t['id']]) {

		$ptype = intval($_POST['picture_type_'.$t['id']] ?? 0);
		$stmt_pt = $db->prepare("UPDATE pictures SET type=? WHERE id=? LIMIT 1");
		if ($stmt_pt) {
			$stmt_pt->bind_param("ii", $ptype, $id_pic);
			$stmt_pt->execute();
		}

}

if ($_POST['picture_delete_'.$t['id']]) {

	$stmt_pdel = $db->prepare("DELETE FROM pictures WHERE id=? LIMIT 1");
	if ($stmt_pdel) {
		$stmt_pdel->bind_param("i", $id_pic);
		$stmt_pdel->execute();
	}
	$log_t160 = 160;
	$stmt_l160 = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, ?, 0, ?, ?, ?)'
	);
	if ($stmt_l160) {
		$stmt_l160->bind_param('iiiii', $id, $id_pic, $id_username, $tm, $log_t160);
		$stmt_l160->execute();
	}
	
}
}



// UPDATE ISSUE INFO
if ($_POST['book_change']) {

	$title1 = plain_text_normalize_for_storage(trim((string) ($_POST['title1'] ?? '')));
	$title2 = plain_text_normalize_for_storage(trim((string) ($_POST['title2'] ?? '')));
	$series = plain_text_normalize_for_storage(trim((string) ($_POST['series'] ?? '')));
	$authors = plain_text_normalize_for_storage(trim((string) ($_POST['authors'] ?? '')));
	$annotation = trim((string) ($_POST['annotation'] ?? ''));
	$publisher = plain_text_normalize_for_storage(trim((string) ($_POST['publisher'] ?? '')));
	
	if ($publisher == "« »") {$publisher = "";}
	
	$language = intval($_POST['language'] ?? 0);
	$isbn = plain_text_normalize_for_storage(trim((string) ($_POST['isbn'] ?? '')));
	$pages = intval($_POST['pages'] ?? 0);
	$circulation = intval($_POST['circulation'] ?? 0);
	$city = intval($_POST['city'] ?? 0);
	$type = intval($_POST['type'] ?? 0);
	
    $d = explode(".", $_POST['date'] ?? '1.1.1970');
	$date = mktime(0, 0, 0, (int)($d[1] ?? 1), (int)($d[0] ?? 1), (int)($d[2] ?? 1970));
	
	$stmt_bup = $db->prepare("UPDATE books SET type=?, city_id=?, title1=?, title2=?, authors=?, annotation=?, publisher=?, language=?, isbn=?, pages=?, circulation=?, date=?, series=? WHERE id=? LIMIT 1");
	if ($stmt_bup) {
		$stmt_bup->bind_param(
			"iisssssisiiisi",
			$type, $city, $title1, $title2, $authors, $annotation, $publisher, $language, $isbn, $pages, $circulation, $date, $series, $id
		);
		$stmt_bup->execute();
	}
	$log_t111 = 111;
	$stmt_l111 = $db->prepare(
		'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
		. 'VALUES (NULL, 0, 0, ?, 0, 0, ?, ?, ?)'
	);
	if ($stmt_l111) {
		$stmt_l111->bind_param('iiii', $id, $id_username, $tm, $log_t111);
		$stmt_l111->execute();
	}

}	
	
////COUNT NUMBERS ONLINE
// $zapros7 = mysqli_query($db,"SELECT DISTINCT muzx_authors.country_en, COUNT(*) AS kl FROM muzx_authors WHERE country_en!='' GROUP BY country_en  ORDER BY kl DESC LIMIT 20");
// if (!$zapros7) {error_log(mysqli_error($db));}
// else {$kl_country = mysqli_num_rows($zapros7);


//

//FILES TYPE UPDATE
// $z = mysqli_query($db,"SELECT * FROM files, issue WHERE issue.id_press=$id AND files.id_issue=issue.id" );

// error_log(mysqli_error($db));

// $n = 0;
// while ($t = mysqli_fetch_array($z)) {

// echo "issue_files_change_".$t[0]." - ".$_POST['issue_files_change_'.$t[0]]."<br>";


	// if ($_POST['issue_files_change_'.$t[0]] == 1)  {
	
		// $id_file = $t[0];
		// $type = $_POST['file_type_'.$t[0]];
		// $file_title = $_POST['file_title_'.$t[0]];
		// $issue_file = $_POST['issue_file_'.$t[0]];
		
		// mysqli_query($db,"UPDATE files SET type='$type', file_title='$file_title', id_issue='$issue_file' WHERE id='$id_file' LIMIT 1"); 
		// error_log(mysqli_error($db));
	// }

// }






//UPDATE ARTICLES (UPDATE TITLE, DELETE ARTICLE, CHANGE ISSUE)

$stmt_chapters_loop = $db->prepare("SELECT * FROM chapters WHERE ch_id_book=?");
if ($stmt_chapters_loop) {
	$stmt_chapters_loop->bind_param("i", $id);
	$stmt_chapters_loop->execute();
	$z = $stmt_chapters_loop->get_result();
} else {
	error_log("admin_books_light: prepare chapters loop failed: " . mysqli_error($db));
	$z = false;
}
$stmt_ch_del = $db->prepare("DELETE FROM chapters WHERE ch_id=? LIMIT 1");
$stmt_log256 = $db->prepare(
	'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
	. 'VALUES (NULL, ?, ?, ?, 0, 0, ?, ?, ?)'
);
$stmt_ta_ins = $db->prepare("INSERT INTO tags_articles (`id`, `id_tag`, `id_article`) VALUES (NULL, ?, ?)");
$stmt_ta_sel = $db->prepare("SELECT * FROM tags_articles WHERE id_article=?");
$stmt_ta_del = $db->prepare("DELETE FROM tags_articles WHERE id=? LIMIT 1");
$stmt_tag_ins = $db->prepare("INSERT INTO tags (`id`, `tag_name`) VALUES (NULL, ?)");

while ($z && ($t = mysqli_fetch_array($z))) {
    
	$id_article = intval($t['ch_id']);
	
	
	// UPDATE NUMBER
//	if ($_POST['number_change_'.$id_article] == 1) {
		
//		$anm =  $_POST['article_number_'.$id_article];
//		mysqli_query($db,"UPDATE articles SET number=$anm WHERE id=$id_article LIMIT 1");

//	}
	
	// UPDATE HIDDEN
//	if ($_POST['hidden_change_'.$id_article] == 1) {
				
//		if ($_POST['hidden_article_'.$id_article]) {$h = 1;} else {$h = 0;};
		
//		mysqli_query($db,"UPDATE articles SET temp=$h WHERE id=$id_article LIMIT 1");

	//}
	
	
	// UPDATE TITLE
	if ($_POST['title_change_'.$id_article] == 1) {
		
		$rawCh = trim((string) ($_POST['article_title_' . $id_article] ?? ''));
		$title = plain_text_normalize_for_storage($rawCh);
		log_content_plain_normalized('chapters.ch_title id=' . $id_article, $rawCh, $title);
		$stmt_upd = $db->prepare("UPDATE chapters SET ch_title=? WHERE ch_id=? LIMIT 1");
		$stmt_upd->bind_param("si", $title, $id_article);
		$stmt_upd->execute();
		
	}
	
	// UPDATE ARTICLE
	if ($_POST['article_change_'.$id_article] == 1) {
		
		$text = html_legacy_normalize(stripslashes($_POST['article_text_' . $id_article] ?? ''), HTML_LEGACY_FIX_MODE_RGB, 0);
		@unlink(zx_storage_path('chapters', (string) $id_article));
		$file = fopen(zx_storage_path('chapters', (string) $id_article), 'wb');
		fwrite($file, $text);
		fclose($file);
	}
	
	
	// DELETE ARTILCE
	if ($_POST['delete_article_'.$id_article] == 1) {
				
		if ($stmt_ch_del) {
			$stmt_ch_del->bind_param("i", $id_article);
			$stmt_ch_del->execute();
		}
		//unlink("articles/$id_article");
		$log_issue_zero = 0;
		$log_type256 = 256;
		if ($stmt_log256) {
			$stmt_log256->bind_param('iiiiii', $log_issue_zero, $id_article, $id, $id_username, $tm, $log_type256);
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









$stmt_log4 = $db->prepare(
	'INSERT INTO log (`id`, `id_issue`, `id_article`, `id_press`, `id_screen`, `id_cover`, `id_user`, `date`, `type`) '
	. 'VALUES (NULL, 0, ?, ?, 0, 0, ?, ?, ?)'
);
$log_type4 = 4;

//NEW CHAPTER
if ($_POST['article_create'] == 1) {

	$num = 1;
//	if ( $_POST['autonumber'] ) {
	
//$z5 = mysqli_query($db,"SELECT articles.number FROM issue, articles WHERE issue.id_press=$id AND issue.id=$issue AND  articles.id_issue=issue.id ORDER BY articles.number DESC LIMIT 1" );
//	$nu = mysqli_fetch_array($z5);
//	if ($nu['0']) {$num = $nu['0'] +10;} 
		
//	}


	$rawNewCh = trim((string) ($_POST['new_article_title'] ?? ''));
	$title = plain_text_normalize_for_storage($rawNewCh);
	log_content_plain_normalized('chapters.ch_title new book=' . $id, $rawNewCh, $title);

	$stmt_ch = $db->prepare("INSERT INTO chapters (`ch_id`, `ch_id_book`, `ch_title`, `ch_date`) VALUES (NULL, ?, ?, ?)");
	$stmt_ch->bind_param("isi", $id, $title, $tm);
	$stmt_ch->execute();

	$id_text = mysqli_insert_id($db);
	$text = html_legacy_normalize(stripslashes($_POST['new_article_text'] ?? ''), HTML_LEGACY_FIX_MODE_RGB, 0);
	$file = fopen(zx_storage_path('chapters', (string) $id_text), 'wb');
	fwrite($file, $text);
	fclose($file);
	
	if ($stmt_log4) {
		$stmt_log4->bind_param('iiiii', $id_text, $id, $id_username, $tm, $log_type4);
		$stmt_log4->execute();
	}

	
}


//UPLOAD TEXT
$ext = strtolower(substr($_FILES['upload_text']['name'], -3));

if ($_FILES['upload_text']['tmp_name'] and ($ext == "tml" or $ext == "htm" or $ext == "txt")) {

	$upload_title = plain_text_normalize_for_storage(pathinfo($_FILES['upload_text']['name'], PATHINFO_FILENAME));
	$stmt_ut = $db->prepare("INSERT INTO chapters (`ch_id`, `ch_id_book`, `ch_title`, `ch_date`) VALUES (NULL, ?, ?, ?)");
	$stmt_ut->bind_param("isi", $id, $upload_title, $tm);
	$stmt_ut->execute();

	$id_text = mysqli_insert_id($db);
	copy($_FILES['upload_text']['tmp_name'], zx_storage_path('chapters', (string) $id_text));
	$rawUp = (string) file_get_contents(zx_storage_path('chapters', (string) $id_text));
	$normUp = html_legacy_normalize($rawUp, HTML_LEGACY_FIX_MODE_RGB, 0);
	if ($rawUp !== $normUp) {
		file_put_contents(zx_storage_path('chapters', (string) $id_text), $normUp);
	}
	
	if ($stmt_log4) {
		$stmt_log4->bind_param('iiiii', $id_text, $id, $id_username, $tm, $log_type4);
		$stmt_log4->execute();
	}
}


//REDIRECT
header("Location: /admin_books_light.php?id=$id");
exit;

}








if ($id == "") {$id = 1;}


//GET INFO
$stmt_book_get = $db->prepare("SELECT * FROM books WHERE books.id=?");
if ($stmt_book_get) {
	$stmt_book_get->bind_param("i", $id);
	$stmt_book_get->execute();
	$z = $stmt_book_get->get_result();
} else {
	error_log("admin_books_light: prepare book get failed: " . mysqli_error($db));
	$z = false;
}
$t = $z ? mysqli_fetch_array($z) : false;
if ($t) {
	$t['date'] = date("d.m.Y", $t['date']);
}
$smarty->assign('book', $t);



// $z = mysqli_query($db,"SELECT * FROM issue WHERE id_press=$id ORDER BY title ASC" ); error_log(mysqli_error($db));
// $n = 0;
// while ($t = mysqli_fetch_array($z)) {$iss[$n] = $t; $n++;}
// $smarty->assign('issues', $iss);

$z = db_select($db, "SELECT * FROM cities ORDER BY name ASC"); error_log(mysqli_error($db));
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {$ct[$n] = $t; $n++;}
$smarty->assign('cities', $ct);


$z = db_select($db, "SELECT * FROM languages ORDER BY name ASC"); error_log(mysqli_error($db));
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {$ln[$n] = $t; $n++;}
$smarty->assign('languages', $ln);


$z = db_select($db, "SELECT * FROM books ORDER BY title1 ASC"); error_log(mysqli_error($db));
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {$pl[$n] = $t; $n++;}
$smarty->assign('books_list', $pl);


$stmt_pics_get = $db->prepare("SELECT * FROM pictures WHERE book_id=? ORDER BY type ASC");
if ($stmt_pics_get) {
	$stmt_pics_get->bind_param("i", $id);
	$stmt_pics_get->execute();
	$z = $stmt_pics_get->get_result();
} else {
	error_log("admin_books_light: prepare pictures get failed: " . mysqli_error($db));
	$z = false;
}
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {$pic[$n] = $t; $n++;}
$smarty->assign('pictures', $pic);


// FILES
$stmt_bf_get = $db->prepare("SELECT * FROM books_files WHERE book_id=?");
if ($stmt_bf_get) {
	$stmt_bf_get->bind_param("i", $id);
	$stmt_bf_get->execute();
	$z = $stmt_bf_get->get_result();
} else {
	error_log("admin_books_light: prepare books_files get failed: " . mysqli_error($db));
	$z = false;
}
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {
	
	$t['date']=date("d.m.Y", $t['upload_date']);
	$fl[$n] = $t;
	$n++;
}
$smarty->assign('files', $fl);


// BOOKS LIST
$z = db_select($db, "SELECT * FROM books ORDER BY title1 ASC"); error_log(mysqli_error($db));
$n = 0;
unset($t);
while ($z && ($t = mysqli_fetch_array($z))) {

	$t['title'] = $t['title1'];
	if ($t['title2']) {$t['title'] = $t['title']." - ". $t['title2'];}
	$pl[$n] = $t;
	$n++;

}
$smarty->assign('press_list', $pl);





//TEXT & TOPIC

$stmt_ch_list = $db->prepare("SELECT * FROM chapters WHERE ch_id_book=? ORDER BY ch_date DESC");
$stmt_tags_j = $db->prepare("SELECT * FROM tags, tags_articles WHERE tags_articles.id_article=? AND tags.id=id_tag");
$stmt_log_j = $db->prepare("SELECT * FROM log, users WHERE log.id_article=? AND log.type=4 AND users.id=log.id_user");
if ($stmt_ch_list) {
	$stmt_ch_list->bind_param("i", $id);
	$stmt_ch_list->execute();
	$z = $stmt_ch_list->get_result();
} else {
	error_log("admin_books_light: prepare chapters list failed: " . mysqli_error($db));
	$z = false;
}
$art = [];
$n = 0;
while ($z && ($t = mysqli_fetch_array($z))) {

	$id_art = (int)$t['ch_id'];
	if ($stmt_tags_j) {
		$stmt_tags_j->bind_param("i", $id_art);
		$stmt_tags_j->execute();
		$zx = $stmt_tags_j->get_result();
	} else {
		$zx = false;
	}
	$x = 0; 
	unset($tags);
	while ($zx && ($tg = mysqli_fetch_array($zx))) {$tags[$x] = $tg; $x++;}
	
	
	
	if ($stmt_log_j) {
		$stmt_log_j->bind_param("i", $id_art);
		$stmt_log_j->execute();
		$zx = $stmt_log_j->get_result();
	} else {
		$zx = false;
	}

	$x = 0; 
	unset($by);
	while ($zx && ($byus = mysqli_fetch_array($zx))) {$by[$x] = $byus; $x++;}
	
$t['by'] = $by;
$t['tags'] = $tags;
$t['text'] = "OK!";//file_get_contents("chapters/".$t['ch_id']);



$art[$n] = $t;
$n++;
}
$smarty->assign('articles', $art);






//TAGS
$z = db_select($db, "SELECT * FROM tags ORDER BY tag_name");
$n = 0;
while ($t = mysqli_fetch_array($z)) {$tgs[$n] = $t; $n++;}
$smarty->assign('tags', $tgs);


$smarty->display('admin_books_light.tpl');

?>