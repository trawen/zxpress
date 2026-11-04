<?php

require_once __DIR__ . '/ezine_slugs.php';

function books_ui_is_new(): bool
{
	return true;
}

function books_url_catalog(bool $isEng): string
{
	return ezn_path_prefix($isEng) . '/books';
}

function books_url_book(int $id, bool $isEng): string
{
	if ($id <= 0) {
		return books_url_catalog($isEng);
	}
	return ezn_path_prefix($isEng) . '/books/' . $id;
}

function books_url_chapter(int $chapterId, bool $isEng): string
{
	if ($chapterId <= 0) {
		return books_url_catalog($isEng);
	}
	return ezn_path_prefix($isEng) . '/books/chapter/' . $chapterId;
}

/** Alternate public catalog powered by books_v2 (parallel to /books). */
function books_v2_url_catalog(bool $isEng): string
{
	return ezn_path_prefix($isEng) . '/books-new';
}

function books_v2_url_book(int $id, bool $isEng): string
{
	if ($id <= 0) {
		return books_v2_url_catalog($isEng);
	}
	return ezn_path_prefix($isEng) . '/books-new/' . $id;
}

function books_v2_url_chapter(int $chapterId, bool $isEng): string
{
	if ($chapterId <= 0) {
		return books_v2_url_catalog($isEng);
	}
	return ezn_path_prefix($isEng) . '/books-new/chapter/' . $chapterId;
}

function books_file_type_label(int $type, bool $isEng): string
{
	$map = [
		1 => 'PDF',
		2 => 'DJVU',
		3 => 'HTML',
		4 => 'TXT',
		5 => 'JPG',
		6 => $isEng ? 'WORD' : 'WORD',
	];
	return $map[$type] ?? ($isEng ? 'File' : 'Файл');
}

/**
 * Chapter HTML stores site paths without a leading slash
 * (chapters_images/..., pictures/...). That works on /book_articles.php
 * but breaks under /{lang}/books-new/chapter/{id}.
 */
/**
 * @return array{html:string,mode:string}
 */
function books_render_chapter_body(string $body, string $format): array
{
	if ($format === 'markdown') {
		require_once __DIR__ . '/Parsedown.php';
		$pd = new Parsedown();
		$pd->setSafeMode(false);
		$pd->setBreaksEnabled(true);

		return [
			'html' => books_chapter_root_urls($pd->text($body)),
			'mode' => 'md',
		];
	}
	if ($format === 'txt') {
		return [
			'html' => htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			'mode' => 'txt',
		];
	}

	return [
		'html' => books_chapter_root_urls($body),
		'mode' => 'html',
	];
}

function books_public_lang_code(string $lng): string
{
	if ($lng === 'eng' || $lng === 'en') {
		return 'en';
	}
	if ($lng === 'es' || $lng === 'spa' || $lng === 'esp') {
		return 'es';
	}

	return 'ru';
}

/**
 * Prefer the site language, then Russian.
 *
 * @return array{lang:string,title:string,body:string,format:string}|null
 */
function books_public_chapter_translation(mysqli $db, int $legacyChId, string $lang): ?array
{
	if ($legacyChId <= 0) {
		return null;
	}
	$want = in_array($lang, ['ru', 'en', 'es'], true) ? $lang : 'ru';
	$z = db_select(
		$db,
		"SELECT i.lang, i.title, b.body, b.format
		 FROM book_chapters c
		 INNER JOIN book_chapter_i18n i ON i.chapter_id=c.id AND i.lang IN ('ru','en','es')
		 LEFT JOIN book_chapter_bodies b ON b.chapter_id=c.id AND b.lang=i.lang
		 WHERE c.legacy_ch_id=?",
		'i',
		$legacyChId
	);
	$rows = [];
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$rows[(string) $row['lang']] = $row;
	}
	$withBody = static function (array $row): bool {
		return trim((string) ($row['body'] ?? '')) !== '';
	};
	if (isset($rows[$want]) && $withBody($rows[$want])) {
		return $rows[$want];
	}
	if ($want !== 'ru' && isset($rows['ru']) && $withBody($rows['ru'])) {
		return $rows['ru'];
	}
	if (isset($rows[$want])) {
		return $rows[$want];
	}
	if (isset($rows['ru'])) {
		return $rows['ru'];
	}

	return null;
}

function books_chapter_root_urls(string $html): string
{
	return (string) preg_replace(
		'/\b(src|href)=(["\'])(?!\/|https?:|\/\/|mailto:|data:|#|javascript:)((?:chapters_images|pictures|books_files|img)\/[^"\']*)\2/i',
		'$1=$2/$3$2',
		$html
	);
}

/**
 * @return array{preview_url:string,original_url:string}
 */
function books_public_cover_urls(string $path): array
{
	$path = str_replace('\\', '/', $path);
	if (preg_match('#^pictures/(\d+)\.(jpe?g|png|webp)$#i', $path, $m)) {
		$ext = strtolower($m[2] === 'jpeg' ? 'jpg' : $m[2]);

		return [
			'preview_url' => '/pictures/thumbs/' . $m[1] . '.jpg',
			'original_url' => '/pictures/' . $m[1] . '.' . $ext,
		];
	}
	if (preg_match('#^pictures/book-(\d+)\.webp$#', $path, $m)) {
		$leaf = 'book-' . $m[1];
		$preview = zx_storage_path('pictures', 'thumbs/' . $leaf . '.jpg');
		$v = is_file($preview) ? (string) filemtime($preview) : '1';

		return [
			'preview_url' => '/pictures/thumbs/' . $leaf . '.jpg?v=' . $v,
			'original_url' => '/pictures/' . $leaf . '.webp',
		];
	}
	$url = '/' . ltrim($path, '/');

	return ['preview_url' => $url, 'original_url' => $url];
}

function books_public_i18n_pick(array $byLang, string $lang, string $field): string
{
	$want = in_array($lang, ['ru', 'en', 'es'], true) ? $lang : 'ru';
	foreach ([$want, 'ru', 'en', 'es'] as $code) {
		$value = trim((string) ($byLang[$code][$field] ?? ''));
		if ($value !== '') {
			return $value;
		}
	}

	return '';
}

function books_public_annotation_html(string $annotation): string
{
	$annotation = trim($annotation);
	if ($annotation === '') {
		return '';
	}
	if (preg_match('/<\s*p[\s>]/i', $annotation)) {
		return $annotation;
	}

	return '<p>' . nl2br(htmlspecialchars($annotation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>';
}

function books_public_imprint_html(string $imprint): string
{
	$imprint = trim($imprint);
	if ($imprint === '') {
		return '';
	}
	require_once __DIR__ . '/Parsedown.php';
	$pd = new Parsedown();
	$pd->setSafeMode(false);
	$pd->setBreaksEnabled(true);

	return books_chapter_root_urls($pd->text($imprint));
}

/**
 * Catalog cards for active works (primary edition).
 *
 * @return list<array<string,mixed>>
 */
function books_public_catalog(mysqli $db, bool $isEng): array
{
	$lang = $isEng ? 'en' : 'ru';
	$sql = "SELECT w.id, w.legacy_book_id, w.first_year,
            COALESCE(i_want.title, i_ru.title, i_any.title, CONCAT('Book #', w.id)) AS title,
            COALESCE(i_want.subtitle, i_ru.subtitle, '') AS subtitle,
            e.publish_year, e.id AS edition_id,
            (SELECT COALESCE(ser_want.title, ser_ru.title, '')
              FROM book_edition_series es
              LEFT JOIN book_series_i18n ser_want ON ser_want.series_id=es.series_id AND ser_want.lang=?
              LEFT JOIN book_series_i18n ser_ru ON ser_ru.series_id=es.series_id AND ser_ru.lang='ru'
              WHERE es.edition_id=e.id LIMIT 1) AS series_title,
            (SELECT p.name_ru FROM book_edition_publishers ep
              INNER JOIN publishers p ON p.id=ep.publisher_id
              WHERE ep.edition_id=e.id AND ep.role='publisher'
              ORDER BY ep.sort_order, ep.publisher_id LIMIT 1) AS publisher_name,
            (SELECT img.path FROM book_edition_images img
              WHERE img.edition_id=e.id ORDER BY img.sort_order, img.id LIMIT 1) AS cover_path,
            (SELECT COUNT(*) FROM book_chapters c
              WHERE c.edition_id=COALESCE(e.content_edition_id, e.id) AND c.is_active=1) AS chapters_count,
            (SELECT COUNT(*) FROM book_edition_files f WHERE f.edition_id=e.id) AS files_count
     FROM book_works w
     INNER JOIN book_editions e ON e.id=w.primary_edition_id AND e.work_id=w.id
     LEFT JOIN book_work_i18n i_want ON i_want.work_id=w.id AND i_want.lang=?
     LEFT JOIN book_work_i18n i_ru ON i_ru.work_id=w.id AND i_ru.lang='ru'
     LEFT JOIN book_work_i18n i_any ON i_any.work_id=w.id
       AND i_any.lang=(SELECT i2.lang FROM book_work_i18n i2 WHERE i2.work_id=w.id ORDER BY i2.lang LIMIT 1)
     WHERE w.is_active=1 AND e.is_active=1
     ORDER BY title ASC, w.id ASC";
	$z = db_select($db, $sql, 'ss', $lang, $lang);
	$out = [];
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$workId = (int) $row['id'];
		$legacyId = (int) ($row['legacy_book_id'] ?? 0);
		$publicId = $legacyId > 0 ? $legacyId : $workId;
		$title = plain_text_decode_entities((string) ($row['title'] ?? ''));
		$subtitle = plain_text_decode_entities((string) ($row['subtitle'] ?? ''));
		$series = plain_text_decode_entities((string) ($row['series_title'] ?? ''));
		$year = (int) ($row['publish_year'] ?? 0);
		if ($year <= 0) {
			$year = (int) ($row['first_year'] ?? 0);
		}
		$publisher = trim(plain_text_decode_entities((string) ($row['publisher_name'] ?? '')));
		$coverPath = trim((string) ($row['cover_path'] ?? ''));
		$coverSrc = '/img/nobook.jpg';
		if ($coverPath !== '') {
			$coverSrc = books_public_cover_urls($coverPath)['preview_url'];
		}
		$out[] = [
			'id' => $publicId,
			'work_id' => $workId,
			'title1' => $title,
			'title2' => $subtitle,
			'series' => $series,
			'date' => $year,
			'year_display' => $year > 1970 ? (string) $year : '',
			'publisher' => $publisher,
			'publisher_display' => ($publisher !== '' && $publisher !== '«»') ? $publisher : '',
			'public_url' => books_v2_url_book($publicId, $isEng),
			'cover_src' => $coverSrc,
			'can_read' => ((int) ($row['chapters_count'] ?? 0)) > 0,
			'can_download' => ((int) ($row['files_count'] ?? 0)) > 0,
		];
	}

	return $out;
}

/**
 * Resolve work by legacy book id or work id.
 *
 * @return array{work:array<string,mixed>,edition:array<string,mixed>}|null
 */
function books_public_resolve_work(mysqli $db, int $id): ?array
{
	if ($id <= 0) {
		return null;
	}
	$z = db_select($db, 'SELECT * FROM book_works WHERE legacy_book_id=? AND is_active=1 LIMIT 1', 'i', $id);
	$work = $z ? mysqli_fetch_assoc($z) : null;
	if (!$work) {
		$z = db_select($db, 'SELECT * FROM book_works WHERE id=? AND is_active=1 LIMIT 1', 'i', $id);
		$work = $z ? mysqli_fetch_assoc($z) : null;
	}
	if (!$work) {
		$z = db_select(
			$db,
			'SELECT w.* FROM book_works w
			 INNER JOIN book_editions e ON e.work_id=w.id
			 WHERE e.legacy_book_id=? AND w.is_active=1
			 ORDER BY e.id ASC LIMIT 1',
			'i',
			$id
		);
		$work = $z ? mysqli_fetch_assoc($z) : null;
	}
	if (!$work) {
		return null;
	}
	$workId = (int) $work['id'];
	$editionId = (int) ($work['primary_edition_id'] ?? 0);
	$edition = null;
	if ($editionId > 0) {
		$z = db_select($db, 'SELECT * FROM book_editions WHERE id=? AND work_id=? LIMIT 1', 'ii', $editionId, $workId);
		$edition = $z ? mysqli_fetch_assoc($z) : null;
	}
	if (!$edition) {
		$z = db_select(
			$db,
			'SELECT * FROM book_editions WHERE work_id=? AND is_active=1 ORDER BY sort_order, id LIMIT 1',
			'i',
			$workId
		);
		$edition = $z ? mysqli_fetch_assoc($z) : null;
	}
	if (!$edition || !(int) ($edition['is_active'] ?? 0)) {
		return null;
	}

	return ['work' => $work, 'edition' => $edition];
}

/**
 * Book detail payload shaped for book_new.tpl.
 *
 * @return array{press:array<string,mixed>,screens:list<array<string,mixed>>,files:list<array<string,mixed>>,chapters:list<array<string,mixed>>,printings:list<array<string,mixed>}|null
 */
function books_public_book_detail(mysqli $db, int $id, bool $isEng): ?array
{
	$resolved = books_public_resolve_work($db, $id);
	if ($resolved === null) {
		return null;
	}
	$work = $resolved['work'];
	$edition = $resolved['edition'];
	$workId = (int) $work['id'];
	$editionId = (int) $edition['id'];
	$lang = $isEng ? 'en' : 'ru';
	$legacyId = (int) ($work['legacy_book_id'] ?? 0);
	$publicId = $legacyId > 0 ? $legacyId : $workId;

	$i18n = [];
	$z = db_select($db, 'SELECT * FROM book_work_i18n WHERE work_id=?', 'i', $workId);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$i18n[(string) $row['lang']] = $row;
	}
	$title = plain_text_decode_entities(books_public_i18n_pick($i18n, $lang, 'title'));
	if ($title === '') {
		$title = 'Book #' . $publicId;
	}
	$subtitle = plain_text_decode_entities(books_public_i18n_pick($i18n, $lang, 'subtitle'));
	$annotation = books_public_i18n_pick($i18n, $lang, 'annotation');
	$imprint = books_public_i18n_pick($i18n, $lang, 'imprint');
	$meta = books_public_i18n_pick($i18n, $lang, 'meta_description');

	$series = '';
	$z = db_select(
		$db,
		"SELECT COALESCE(i_want.title, i_ru.title, '') AS title
		 FROM book_edition_series es
		 LEFT JOIN book_series_i18n i_want ON i_want.series_id=es.series_id AND i_want.lang=?
		 LEFT JOIN book_series_i18n i_ru ON i_ru.series_id=es.series_id AND i_ru.lang='ru'
		 WHERE es.edition_id=? LIMIT 1",
		'si',
		$lang,
		$editionId
	);
	if ($z && ($row = mysqli_fetch_assoc($z))) {
		$series = plain_text_decode_entities((string) ($row['title'] ?? ''));
	}

	$publishers = [];
	$z = db_select(
		$db,
		'SELECT p.name_ru, ep.role FROM book_edition_publishers ep
		 INNER JOIN publishers p ON p.id=ep.publisher_id
		 WHERE ep.edition_id=? ORDER BY ep.sort_order, ep.publisher_id',
		'i',
		$editionId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$name = trim(plain_text_decode_entities((string) ($row['name_ru'] ?? '')));
		if ($name !== '') {
			$publishers[] = $name;
		}
	}
	$publisher = implode(', ', $publishers);

	$authors = [];
	$z = db_select(
		$db,
		"SELECT a.nickname, a.name_ru, a.name_en, c.role
		 FROM book_credits c
		 INNER JOIN authors a ON a.id=c.author_id
		 WHERE c.work_id=? ORDER BY c.sort_order, c.id",
		'i',
		$workId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$nick = trim((string) ($row['nickname'] ?? ''));
		$name = $isEng
			? trim((string) ($row['name_en'] ?? ''))
			: trim((string) ($row['name_ru'] ?? ''));
		$label = $nick !== '' ? $nick : $name;
		if ($label !== '') {
			$authors[] = $label;
		}
	}

	$cityName = '';
	$cityId = (int) ($edition['city_id'] ?? 0);
	if ($cityId > 0) {
		$z = db_select($db, 'SELECT name, name_eng FROM cities WHERE id=? LIMIT 1', 'i', $cityId);
		if ($z && ($row = mysqli_fetch_assoc($z))) {
			$cityName = $isEng
				? trim((string) ($row['name_eng'] ?? ''))
				: trim((string) ($row['name'] ?? ''));
			if ($cityName === '') {
				$cityName = trim((string) ($row['name'] ?? ''));
			}
		}
	}

	$isbn = '';
	$z = db_select(
		$db,
		'SELECT isbn_raw FROM book_edition_isbns WHERE edition_id=? ORDER BY is_primary DESC, sort_order, id LIMIT 1',
		'i',
		$editionId
	);
	if ($z && ($row = mysqli_fetch_assoc($z))) {
		$isbn = trim((string) ($row['isbn_raw'] ?? ''));
	}

	$year = (int) ($edition['publish_year'] ?? 0);
	if ($year <= 0) {
		$year = (int) ($work['first_year'] ?? 0);
	}

	$press = [
		'id' => $publicId,
		'work_id' => $workId,
		'edition_id' => $editionId,
		'title1' => $title,
		'title2' => $subtitle,
		'series' => $series,
		'publisher' => $publisher,
		'publisher_display' => ($publisher !== '' && $publisher !== '«»') ? $publisher : '',
		'authors' => implode(', ', $authors),
		'annotation' => books_public_annotation_html($annotation),
		'imprint_html' => books_public_imprint_html($imprint),
		'isbn' => $isbn,
		'pages' => (int) ($edition['pages'] ?? 0),
		'circulation' => (int) ($edition['circulation'] ?? 0),
		'date' => $year,
		'year_display' => $year > 1970 ? (string) $year : '',
		'city_display' => $cityName,
		'city_id' => $cityId,
		'meta_description' => $meta,
	];

	$screens = [];
	$z = db_select(
		$db,
		'SELECT id, path FROM book_edition_images WHERE edition_id=? ORDER BY sort_order, id',
		'i',
		$editionId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$urls = books_public_cover_urls((string) ($row['path'] ?? ''));
		$screens[] = [
			'id' => (int) $row['id'],
			'img_src' => $urls['original_url'],
			'preview_url' => $urls['preview_url'],
		];
	}

	$files = [];
	$z = db_select(
		$db,
		'SELECT id, file_name, file_type, file_size FROM book_edition_files WHERE edition_id=? ORDER BY sort_order, id',
		'i',
		$editionId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$name = (string) ($row['file_name'] ?? '');
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$type = (int) ($row['file_type'] ?? 0);
		$label = books_file_type_label($type, $isEng);
		if ($label === ($isEng ? 'File' : 'Файл') && $ext !== '') {
			$label = strtoupper($ext);
		}
		$files[] = [
			'id' => (int) $row['id'],
			'file_name' => $name,
			'file_size' => (int) ($row['file_size'] ?? 0),
			'file_type_label' => $label,
			'public_url' => '/books_files/' . rawurlencode($name),
		];
	}

	$contentEditionId = (int) ($edition['content_edition_id'] ?? 0);
	if ($contentEditionId <= 0) {
		$contentEditionId = $editionId;
	}
	$chapters = [];
	$z = db_select(
		$db,
		"SELECT c.id, c.legacy_ch_id, c.sort_order,
		        COALESCE(
		          (SELECT ci.title FROM book_chapter_i18n ci WHERE ci.chapter_id=c.id AND ci.lang=? LIMIT 1),
		          (SELECT ci.title FROM book_chapter_i18n ci WHERE ci.chapter_id=c.id AND ci.lang='ru' LIMIT 1),
		          (SELECT ci.title FROM book_chapter_i18n ci WHERE ci.chapter_id=c.id ORDER BY ci.lang LIMIT 1),
		          CONCAT('#', c.id)
		        ) AS title
		 FROM book_chapters c
		 WHERE c.edition_id=? AND c.is_active=1
		 ORDER BY c.sort_order, c.id",
		'si',
		$lang,
		$contentEditionId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$legacyCh = (int) ($row['legacy_ch_id'] ?? 0);
		$chId = $legacyCh > 0 ? $legacyCh : (int) $row['id'];
		$chapters[] = [
			'ch_id' => $chId,
			'ch_title' => plain_text_decode_entities((string) ($row['title'] ?? '')),
			'public_url' => books_v2_url_chapter($chId, $isEng),
		];
	}

	$printings = [];
	$z = db_select(
		$db,
		'SELECT printing_no, year, circulation FROM book_edition_printings
		 WHERE edition_id=? ORDER BY printing_no, id',
		'i',
		$editionId
	);
	while ($z && ($row = mysqli_fetch_assoc($z))) {
		$printings[] = [
			'printing_no' => (int) ($row['printing_no'] ?? 0),
			'year' => isset($row['year']) && $row['year'] !== null ? (int) $row['year'] : null,
			'circulation' => isset($row['circulation']) && $row['circulation'] !== null ? (int) $row['circulation'] : null,
		];
	}

	return [
		'press' => $press,
		'screens' => $screens,
		'files' => $files,
		'chapters' => $chapters,
		'printings' => $printings,
	];
}
