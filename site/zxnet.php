<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/zxnet_slugs.php';

function zxnet_hidden_echo_titles(): array
{
	return ['e2e.talk'];
}

function zxnet_is_hidden_echo_title(string $title): bool
{
	return in_array(mb_strtolower(trim($title), 'UTF-8'), zxnet_hidden_echo_titles(), true);
}

function zxnet_ui_template(): string
{
	return zxnet_ui_is_new() ? 'zxnet_new.tpl' : 'zxnet.tpl';
}

/** Build a single-line meta description (≈160 chars). */
function zxnet_meta_description(string $text, int $max = 160): string
{
	$text = title_plain(strip_tags($text));
	$text = preg_replace('/\s+/u', ' ', $text) ?? $text;
	$text = trim($text);
	if ($text === '') {
		return '';
	}
	if (mb_strlen($text, 'UTF-8') <= $max) {
		return $text;
	}
	$cut = mb_substr($text, 0, $max - 1, 'UTF-8');
	$space = mb_strrpos($cut, ' ', 0, 'UTF-8');
	if ($space !== false && $space > (int) ($max * 0.55)) {
		$cut = mb_substr($cut, 0, $space, 'UTF-8');
	}

	return rtrim($cut, " \t.,;:!-–—") . '…';
}

/**
 * Format from/to dates for ZXNet list meta.
 * @return array{date_from:string,date_to:string,date_range:string}
 */
function zxnet_date_range(string $from, string $to): array
{
	$from = trim($from);
	$to = trim($to);
	$range = $from;
	if ($from !== '' && $to !== '' && $from !== $to) {
		$range = $from . ' – ' . $to;
	} elseif ($from === '' && $to !== '') {
		$range = $to;
	}

	return [
		'date_from' => $from,
		'date_to' => $to,
		'date_range' => $range,
	];
}

function zxnet_ui_render($smarty): void
{
	if (!zxnet_ui_is_new()) {
		global $db;
		include __DIR__ . '/right.php';
	}
	$smarty->display(zxnet_ui_template());
}

//error_reporting(E_ALL);

$e = mb_substr(trim((string) ($_GET['e'] ?? '')), 0, 32);
$topicKey = trim((string) ($_GET['id'] ?? ''));
$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');
$lngQs = $isEng ? '?lng=eng' : '';
$smarty->assign('zxnet_lng_qs', $lngQs);

$catalogUrl = zxnet_url_catalog($isEng);
$smarty->assign('zxnet_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', letters_url_catalog($isEng));
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', zxnet_ui_is_new());

$stmt_echo = $db->prepare("SELECT * FROM echos_titles2 WHERE title=? LIMIT 1");
$stmt_echo->bind_param("s", $e);
$stmt_echo->execute();
$z = $stmt_echo->get_result();
$t = mysqli_fetch_array($z);
if (is_array($t)) {
	$t['title'] = plain_text_decode_entities((string) ($t['title'] ?? ''));
	$descRu = plain_text_decode_entities((string) ($t['description'] ?? ''));
	$descEn = plain_text_decode_entities((string) ($t['description_en'] ?? ''));
	$t['description_en'] = $descEn;
	$t['description'] = ($isEng && $descEn !== '') ? $descEn : $descRu;
	if (zxnet_is_hidden_echo_title((string) ($t['title'] ?? ''))) {
		$t = null;
	}
}
$smarty->assign('echo', $t);
$id = $t ? (int) $t['id'] : 0;
$smarty->assign('id', $id);
$echo_title = is_array($t) ? (string) ($t['title'] ?? '') : '';
$echo_description = is_array($t) ? (string) ($t['description'] ?? '') : '';
$echoUrl = $echo_title !== '' ? zxnet_url_echo($echo_title, $isEng) : $catalogUrl;
$smarty->assign('zxnet_echo_url', $echoUrl);

$zxnet_view = 'catalog';

if ($topicKey !== '') {

	$zxnet_view = 'topic';

	$subj = zxnet_find_subj($db, $id, $topicKey, $isEng);
	$subj_id = $subj ? (int) ($subj['id'] ?? 0) : 0;

	if ($subj) {
		$titleRu = plain_text_decode_entities((string) ($subj['title'] ?? ''));
		$titleEn = plain_text_decode_entities((string) ($subj['title_en'] ?? ''));
		$subj_title = ($isEng && $titleEn !== '') ? $titleEn : $titleRu;
		$slugCurrent = zxnet_row_slug($subj, $isEng);
		// Legacy /…/1248 → /…/vicomm-modem
		if (
			$slugCurrent !== ''
			&& ctype_digit($topicKey)
			&& $slugCurrent !== $topicKey
			&& $echo_title !== ''
		) {
			header('Location: ' . zxnet_url_topic($echo_title, $slugCurrent, $isEng, $subj_id), true, 301);
			exit;
		}
	} else {
		$subj_title = '';
	}
	$smarty->assign('subj_title', $subj_title);
	$smarty->assign('subj_id', $subj_id);

	$topic = [];
	$zxnet_entity_fix_logs = 0;
	if ($subj_id > 0) {
	$stmt_topic = $db->prepare("SELECT * FROM echos_zxnet WHERE echo_id=? AND subj_id=? ORDER BY date");
	$stmt_topic->bind_param("ii", $id, $subj_id);
	$stmt_topic->execute();
	$z = $stmt_topic->get_result();
	while ($z && ($t = mysqli_fetch_array($z))) {

		$rawText = (string) ($t['text'] ?? '');
		$decoded = plain_text_decode_entities($rawText);
		if ($decoded !== $rawText && preg_match('/&(quot|amp|lt|gt|apos);|&#\d+;/i', $rawText)) {
			if ($zxnet_entity_fix_logs < 20) {
				error_log('[FIX] zxnet decoded HTML entities in topic body id=' . (int) ($t['id'] ?? 0));
				$zxnet_entity_fix_logs++;
			}
		}
		// Echo dumps store odd LF+CR (`\n\r`); in CSS pre-wrap that is two breaks.
		$decoded = str_replace(["\r\n", "\n\r"], "\n", $decoded);
		$decoded = str_replace("\r", "\n", $decoded);

		$rawTextEn = (string) ($t['text_en'] ?? '');
		$decodedEn = plain_text_decode_entities($rawTextEn);
		$decodedEn = str_replace(["\r\n", "\n\r"], "\n", $decodedEn);
		$decodedEn = str_replace("\r", "\n", $decodedEn);

		$t['text_en'] = $decodedEn;
		$t['text'] = ($isEng && $decodedEn !== '') ? $decodedEn : $decoded;

		$nameFromRu = plain_text_decode_entities((string) ($t['name_from'] ?? ''));
		$nameFromEn = plain_text_decode_entities((string) ($t['name_from_en'] ?? ''));
		$nameToRu = plain_text_decode_entities((string) ($t['name_to'] ?? ''));
		$nameToEn = plain_text_decode_entities((string) ($t['name_to_en'] ?? ''));
		$t['name_from_en'] = $nameFromEn;
		$t['name_to_en'] = $nameToEn;
		$t['name_from'] = ($isEng && $nameFromEn !== '') ? $nameFromEn : $nameFromRu;
		$t['name_to'] = ($isEng && $nameToEn !== '') ? $nameToEn : $nameToRu;

		$t['date'] = $isEng ? date('j F Y', (int) $t['date']) : date('d.m.Y', (int) $t['date']);
		$topic[] = $t;

	}
	}
	$smarty->assign('topic', $topic);

	if ($subj_title !== '') {
		$page_title = $echo_title !== ''
			? ($subj_title . ' — ZXNet «' . $echo_title . '»')
			: $subj_title;
	} else {
		$page_title = $echo_title !== ''
			? ('ZXNet «' . $echo_title . '»')
			: 'ZXNet';
	}
	$smarty->assign('title', $page_title);

	$msgCount = count($topic);
	if ($isEng) {
		$descPlain = ($subj_title !== '' ? 'Topic «' . $subj_title . '»' : 'ZXNet topic')
			. ($echo_title !== '' ? ' in ZXNet echo «' . $echo_title . '»' : '')
			. ($msgCount > 0 ? ' — ' . $msgCount . ' message' . ($msgCount === 1 ? '' : 's') : '')
			. '. Archive on ZXPRESS.';
	} else {
		$descPlain = ($subj_title !== '' ? 'Тема «' . $subj_title . '»' : 'Тема ZXNet')
			. ($echo_title !== '' ? ' в эхоконференции ZXNet «' . $echo_title . '»' : '')
			. ($msgCount > 0 ? ' — ' . $msgCount . ' сообщ.' : '')
			. '. Архив на ZXPRESS.';
	}
	$smarty->assign('description', zxnet_meta_description($descPlain, 160));

	$slugRu = $subj ? zxnet_row_slug($subj, false) : '';
	$slugEn = $subj ? zxnet_row_slug($subj, true) : '';
	$smarty->assign('url_rus', htmlspecialchars(
		$echo_title !== '' ? zxnet_url_topic($echo_title, $slugRu, false, $subj_id) : zxnet_url_catalog(false),
		ENT_QUOTES,
		'UTF-8'
	));
	$smarty->assign('url_eng', htmlspecialchars(
		$echo_title !== '' ? zxnet_url_topic($echo_title, $slugEn, true, $subj_id) : zxnet_url_catalog(true),
		ENT_QUOTES,
		'UTF-8'
	));

} elseif ($id) {

	$zxnet_view = 'echo';

	$subjs = [];
	$subjsByYear = [];
	$z = db_select($db, "SELECT * FROM echos_subjs2 WHERE echo_id=? ORDER BY date_from ASC, id ASC", "i", $id);
	while ($z && ($t = mysqli_fetch_array($z))) {

		$titleRu = plain_text_decode_entities((string) ($t['title'] ?? ''));
		$titleEn = plain_text_decode_entities((string) ($t['title_en'] ?? ''));
		$t['title_en'] = $titleEn;
		$t['title'] = ($isEng && $titleEn !== '') ? $titleEn : $titleRu;
		$dateFromTs = (int) ($t['date_from'] ?? 0);
		$dateToTs = (int) ($t['date_to'] ?? 0);
		$year = $dateFromTs > 0 ? (int) date('Y', $dateFromTs) : 0;
		$t['year'] = $year;
		if ($isEng) {
			$dates = zxnet_date_range(
				$dateFromTs > 0 ? date('j F Y', $dateFromTs) : '',
				$dateToTs > 0 ? date('j F Y', $dateToTs) : ''
			);
		} else {
			$dates = zxnet_date_range(
				$dateFromTs > 0 ? date('d.m.Y', $dateFromTs) : '',
				$dateToTs > 0 ? date('d.m.y', $dateToTs) : ''
			);
		}
		$t['date_from'] = $dates['date_from'];
		$t['date_to'] = $dates['date_to'];
		$t['date_range'] = $dates['date_range'];
		$topicSlug = zxnet_row_slug($t, $isEng);
		$t['public_url'] = zxnet_url_topic($echo_title, $topicSlug, $isEng, (int) $t['id']);

		$subjs[] = $t;
		$yearKey = $year > 0 ? (string) $year : '0';
		if (!isset($subjsByYear[$yearKey])) {
			$subjsByYear[$yearKey] = [
				'year' => $year,
				'year_label' => $year > 0
					? (string) $year
					: ($isEng ? 'Unknown year' : 'Без даты'),
				'items' => [],
				'topic_count' => 0,
			];
		}
		$subjsByYear[$yearKey]['items'][] = $t;
		$subjsByYear[$yearKey]['topic_count']++;

	}

	// Empty echos (e.g. stale nm) — hide from new UI.
	if ($subjs === [] && zxnet_ui_is_new()) {
		header('Location: ' . $catalogUrl, true, 302);
		exit;
	}

	$smarty->assign('subjs', $subjs);
	$smarty->assign('subjs_by_year', array_values($subjsByYear));

	$smarty->assign('title', $echo_title !== ''
		? ($isEng
			? 'ZXNet echo conference «' . $echo_title . '»'
			: 'ZXNet эхоконференция «' . $echo_title . '»')
		: ($isEng ? 'ZXNet echo conference' : 'ZXNet эхоконференция'));

	$topicCount = count($subjs);
	$echoDesc = $echo_description;
	if ($echoDesc === '') {
		if ($isEng) {
			$echoDesc = ($echo_title !== '' ? 'ZXNet echo conference «' . $echo_title . '»' : 'ZXNet echo conference')
				. ($topicCount > 0 ? ' — ' . $topicCount . ' topic' . ($topicCount === 1 ? '' : 's') : '')
				. '. Message archive on ZXPRESS.';
		} else {
			$echoDesc = ($echo_title !== '' ? 'Эхоконференция ZXNet «' . $echo_title . '»' : 'Эхоконференция ZXNet')
				. ($topicCount > 0 ? ' — ' . $topicCount . ' тем' : '')
				. '. Архив сообщений на ZXPRESS.';
		}
	}
	$smarty->assign('description', zxnet_meta_description($echoDesc, 160));

	$smarty->assign('url_rus', htmlspecialchars(zxnet_url_echo($echo_title, false), ENT_QUOTES, 'UTF-8'));
	$smarty->assign('url_eng', htmlspecialchars(zxnet_url_echo($echo_title, true), ENT_QUOTES, 'UTF-8'));

} else {

	$echos = [];
	// Only echos that actually have topics (nm alone can be stale, e.g. spbzxnet.amy).
	$z = db_select(
		$db,
		"SELECT t.* FROM echos_titles2 t
		WHERE EXISTS (SELECT 1 FROM echos_subjs2 s WHERE s.echo_id = t.id)
		ORDER BY t.title"
	);
	while ($z && ($t = mysqli_fetch_array($z))) {

		$t['title'] = plain_text_decode_entities((string) ($t['title'] ?? ''));
		if (zxnet_is_hidden_echo_title((string) ($t['title'] ?? ''))) {
			continue;
		}
		$dateFromTs = (int) ($t['date_from'] ?? 0);
		$dateToTs = (int) ($t['date_to'] ?? 0);
		if ($isEng) {
			$dates = zxnet_date_range(
				$dateFromTs > 0 ? date('F Y', $dateFromTs) : '',
				$dateToTs > 0 ? date('F Y', $dateToTs) : ''
			);
		} else {
			$from = $dateFromTs > 0
				? $mnt[date('m', $dateFromTs)] . date(' Y', $dateFromTs)
				: '';
			$to = $dateToTs > 0
				? $mnt[date('m', $dateToTs)] . date(' Y', $dateToTs)
				: '';
			$dates = zxnet_date_range($from, $to);
			// RU catalog keeps start date only in the list meta.
			$dates['date_range'] = $from !== '' ? $from : $dates['date_range'];
		}
		$t['date_from'] = $dates['date_from'];
		$t['date_to'] = $dates['date_to'];
		$t['date_range'] = $dates['date_range'];
		$t['public_url'] = zxnet_url_echo((string) $t['title'], $isEng);

		$echos[] = $t;

	}
	$smarty->assign('echos', $echos);

	$smarty->assign('title', $isEng
		? 'Archive of ZXNet echo conferences'
		: 'Архив эхоконференций сети ZXNet');

	$smarty->assign('description', $isEng
		? 'Archive of ZXNet echomail conferences, the largest ZX Spectrum community network in the former USSR. History, FTN, Fidonet integration and preserved discussions from the 1990s.'
		: 'Архив эхоконференций ZXNet — крупнейшей сети пользователей ZX Spectrum в странах бывшего СССР. История сети, FTN, Fidonet и тысячи сообщений 1990-х годов.');

	$smarty->assign('url_rus', htmlspecialchars(zxnet_url_catalog(false), ENT_QUOTES, 'UTF-8'));
	$smarty->assign('url_eng', htmlspecialchars(zxnet_url_catalog(true), ENT_QUOTES, 'UTF-8'));

}

$smarty->assign('zxnet_view', $zxnet_view);

zxnet_ui_render($smarty);
