<?php
require 'init.inc';

function books2_description_html(?string $s): string
{
	$s = (string) $s;
	if ($s === '') {
		return '';
	}

	return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

$isEng = (($_GET['lng'] ?? '') === 'eng');

if ($isEng) {
	$smarty->assign('title', 'Book rubrics — test page');
} else {
	$smarty->assign('title', 'Рубрики книг — тестовая страница');
}

$rubrics = [];
$z = db_select(
	$db,
	'SELECT id, name_ru, name_en, description_ru, description_en FROM book_rubrics WHERE is_active=1 ORDER BY sort_order ASC, name_ru ASC'
);
while ($z && ($t = mysqli_fetch_array($z))) {
	if ($isEng) {
		$t['title'] = trim((string) ($t['name_en'] ?? '')) !== ''
			? $t['name_en']
			: (string) ($t['name_ru'] ?? '');
		$desc = trim((string) ($t['description_en'] ?? '')) !== ''
			? $t['description_en']
			: (string) ($t['description_ru'] ?? '');
	} else {
		$t['title'] = trim((string) ($t['name_ru'] ?? '')) !== ''
			? $t['name_ru']
			: (string) ($t['name_en'] ?? '');
		$desc = trim((string) ($t['description_ru'] ?? '')) !== ''
			? $t['description_ru']
			: (string) ($t['description_en'] ?? '');
	}

	$t['description_html'] = books2_description_html($desc);
	$rubrics[] = $t;
}

$smarty->assign('rubrics', $rubrics);

include 'right.php';

$smarty->display('books2.tpl');
