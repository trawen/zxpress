<?php
require 'init.inc';
require_once __DIR__ . '/includes/books_v2.php';
require_once __DIR__ . '/includes/authors_slugs.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function bv2_admin_post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function bv2_admin_post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

/**
 * @return array<string, array{title:string,subtitle:string,slug:string,annotation:string,meta_description:string}>
 */
function bv2_admin_post_work_i18n(array $langCodes): array
{
    $raw = $_POST['i18n'] ?? [];
    if (!is_array($raw)) {
        $raw = [];
    }
    $out = [];
    foreach ($langCodes as $code) {
        $row = is_array($raw[$code] ?? null) ? $raw[$code] : [];
        $out[$code] = [
            'title' => plain_text_normalize_for_storage(trim((string) ($row['title'] ?? ''))),
            'subtitle' => plain_text_normalize_for_storage(trim((string) ($row['subtitle'] ?? ''))),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'annotation' => (string) ($row['annotation'] ?? ''),
            'meta_description' => plain_text_normalize_for_storage(trim((string) ($row['meta_description'] ?? ''))),
        ];
    }

    return $out;
}

/**
 * @return array<string, array{slug:string,title_override:string,edition_statement:string,note:string,meta_description:string}>
 */
function bv2_admin_post_edition_i18n(array $langCodes): array
{
    $raw = $_POST['edition_i18n'] ?? [];
    if (!is_array($raw)) {
        $raw = [];
    }
    $out = [];
    foreach ($langCodes as $code) {
        $row = is_array($raw[$code] ?? null) ? $raw[$code] : [];
        $out[$code] = [
            'slug' => trim((string) ($row['slug'] ?? '')),
            'title_override' => plain_text_normalize_for_storage(trim((string) ($row['title_override'] ?? ''))),
            'edition_statement' => plain_text_normalize_for_storage(trim((string) ($row['edition_statement'] ?? ''))),
            'note' => (string) ($row['note'] ?? ''),
            'meta_description' => plain_text_normalize_for_storage(trim((string) ($row['meta_description'] ?? ''))),
        ];
    }

    return $out;
}

function bv2_admin_unique_work_slug(mysqli $db, string $lang, string $base, int $excludeWorkId): string
{
    $base = books_v2_slugify($base);
    if ($base === '') {
        $base = 'work-' . max(1, $excludeWorkId);
    }
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt = $db->prepare(
            'SELECT work_id FROM book_work_i18n WHERE lang=? AND slug=? LIMIT 1'
        );
        if (!$stmt) {
            return $slug;
        }
        $stmt->bind_param('ss', $lang, $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || (int) $row['work_id'] === $excludeWorkId) {
            return $slug;
        }
        $suffix = (string) $n;
        $room = BOOKS_V2_SLUG_MAX_LEN - strlen($suffix) - 1;
        $slug = rtrim(substr($base, 0, max(8, $room)), '-') . '-' . $suffix;
        $n++;
        if ($n > 500) {
            return rtrim(substr($base, 0, 180), '-') . '-' . $excludeWorkId;
        }
    }
}

function bv2_admin_i18n_row_empty(array $row): bool
{
    foreach (['title', 'subtitle', 'slug', 'annotation', 'meta_description', 'title_override', 'edition_statement', 'note'] as $k) {
        if (trim((string) ($row[$k] ?? '')) !== '') {
            return false;
        }
    }

    return true;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';

$languages = [];
$z = db_select($db, 'SELECT id, code, name FROM languages ORDER BY sort_order ASC, id ASC');
while ($z && ($t = mysqli_fetch_assoc($z))) {
    $languages[] = $t;
}
$smarty->assign('languages', $languages);

$langById = [];
$langCodes = [];
foreach ($languages as $langRow) {
    $code = (string) $langRow['code'];
    $langById[(int) $langRow['id']] = $code;
    $langCodes[] = $code;
}

$cities = [];
$z = db_select($db, 'SELECT id, name FROM cities ORDER BY name ASC');
while ($z && ($t = mysqli_fetch_assoc($z))) {
    $cities[] = $t;
}
$smarty->assign('cities', $cities);

$editionKinds = ['original', 'reprint', 'revised', 'translation', 'facsimile', 'electronic'];
$smarty->assign('edition_kinds', $editionKinds);

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();

    $workI18nPost = bv2_admin_post_work_i18n($langCodes);
    $editionI18nPost = bv2_admin_post_edition_i18n($langCodes);

    $langId = bv2_admin_post_int('original_language_id');
    if ($langId <= 0) {
        $langId = 1;
    }
    $origLang = $langById[$langId] ?? 'ru';
    $firstYear = bv2_admin_post_int('first_year');
    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    $editionKind = bv2_admin_post_string('edition_kind');
    if (!in_array($editionKind, $editionKinds, true)) {
        $editionKind = 'original';
    }
    $publishYear = bv2_admin_post_int('publish_year');
    $pages = bv2_admin_post_int('pages');
    $circulation = bv2_admin_post_int('circulation');
    $cityId = bv2_admin_post_int('city_id');
    $isbnRaw = bv2_admin_post_string('isbn_raw');

    $origTitle = $workI18nPost[$origLang]['title'] ?? '';
    if ($origTitle === '') {
        // Fallback: any filled title, prefer ru then en.
        foreach (array_merge([$origLang, 'ru', 'en'], $langCodes) as $code) {
            if (($workI18nPost[$code]['title'] ?? '') !== '') {
                $origTitle = $workI18nPost[$code]['title'];
                break;
            }
        }
    }
    if ($origTitle === '') {
        $error = 'Название обязательно хотя бы на языке оригинала (' . $origLang . ')';
    }

    if ($error === '') {
        $wasCreate = ($id === 0);
        $yearSql = $firstYear > 0 ? (string) $firstYear : 'NULL';
        $editionId = 0;

        if ($id === 0) {
            $db->query(
                'INSERT INTO book_works (original_language_id, first_year, is_active)
                 VALUES (' . (int) $langId . ',' . $yearSql . ',' . (int) $isActive . ')'
            );
            $id = (int) $db->insert_id;

            $pubYearSql = $publishYear > 0 ? (string) $publishYear : $yearSql;
            $pagesSql = $pages > 0 ? (string) $pages : 'NULL';
            $circSql = $circulation > 0 ? (string) $circulation : 'NULL';
            $citySql = $cityId > 0 ? (string) $cityId : 'NULL';
            $db->query(
                'INSERT INTO book_editions
                 (work_id, edition_kind, language_id, publish_year, city_id, pages, circulation, is_active, sort_order)
                 VALUES ('
                . (int) $id . ','
                . "'" . $db->real_escape_string($editionKind) . "',"
                . (int) $langId . ','
                . $pubYearSql . ','
                . $citySql . ','
                . $pagesSql . ','
                . $circSql . ',1,0)'
            );
            $editionId = (int) $db->insert_id;
            $db->query(
                'UPDATE book_works SET primary_edition_id=' . (int) $editionId
                . ' WHERE id=' . (int) $id . ' LIMIT 1'
            );
        } else {
            $db->query(
                'UPDATE book_works SET original_language_id=' . (int) $langId
                . ', first_year=' . $yearSql
                . ', is_active=' . (int) $isActive
                . ' WHERE id=' . (int) $id . ' LIMIT 1'
            );

            $ws = $db->prepare('SELECT primary_edition_id FROM book_works WHERE id=? LIMIT 1');
            $ws->bind_param('i', $id);
            $ws->execute();
            $workRow = $ws->get_result()->fetch_assoc();
            $ws->close();
            $editionId = (int) ($workRow['primary_edition_id'] ?? 0);

            if ($editionId <= 0) {
                $es = $db->prepare('SELECT id FROM book_editions WHERE work_id=? ORDER BY sort_order, id LIMIT 1');
                $es->bind_param('i', $id);
                $es->execute();
                $erow = $es->get_result()->fetch_assoc();
                $es->close();
                $editionId = $erow ? (int) $erow['id'] : 0;
            }

            $pubYearSql = $publishYear > 0 ? (string) $publishYear : 'NULL';
            $pagesSql = $pages > 0 ? (string) $pages : 'NULL';
            $circSql = $circulation > 0 ? (string) $circulation : 'NULL';
            $citySql = $cityId > 0 ? (string) $cityId : 'NULL';

            if ($editionId > 0) {
                $db->query(
                    'UPDATE book_editions SET edition_kind=\'' . $db->real_escape_string($editionKind) . '\''
                    . ', language_id=' . (int) $langId
                    . ', publish_year=' . $pubYearSql
                    . ', city_id=' . $citySql
                    . ', pages=' . $pagesSql
                    . ', circulation=' . $circSql
                    . ' WHERE id=' . (int) $editionId . ' LIMIT 1'
                );
            } else {
                $db->query(
                    'INSERT INTO book_editions
                     (work_id, edition_kind, language_id, publish_year, city_id, pages, circulation, is_active, sort_order)
                     VALUES ('
                    . (int) $id . ','
                    . "'" . $db->real_escape_string($editionKind) . "',"
                    . (int) $langId . ','
                    . $pubYearSql . ','
                    . $citySql . ','
                    . $pagesSql . ','
                    . $circSql . ',1,0)'
                );
                $editionId = (int) $db->insert_id;
                $db->query(
                    'UPDATE book_works SET primary_edition_id=' . (int) $editionId
                    . ' WHERE id=' . (int) $id . ' LIMIT 1'
                );
            }
        }

        $primarySlug = '';
        foreach ($langCodes as $code) {
            $row = $workI18nPost[$code];
            $isOrig = ($code === $origLang);
            if (!$isOrig && bv2_admin_i18n_row_empty($row)) {
                $del = $db->prepare('DELETE FROM book_work_i18n WHERE work_id=? AND lang=? LIMIT 1');
                $del->bind_param('is', $id, $code);
                $del->execute();
                $del->close();
                continue;
            }
            if ($row['title'] === '' && $isOrig) {
                $row['title'] = $origTitle;
            }
            if ($row['title'] === '') {
                continue;
            }
            $slug = bv2_admin_unique_work_slug(
                $db,
                $code,
                $row['slug'] !== '' ? $row['slug'] : $row['title'],
                $id
            );
            if ($code === $origLang || $primarySlug === '') {
                $primarySlug = $slug;
            }
            $i18n = $db->prepare(
                'INSERT INTO book_work_i18n (work_id, lang, title, subtitle, slug, annotation, meta_description)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle),
                   slug=VALUES(slug), annotation=VALUES(annotation), meta_description=VALUES(meta_description)'
            );
            $i18n->bind_param(
                'issssss',
                $id,
                $code,
                $row['title'],
                $row['subtitle'],
                $slug,
                $row['annotation'],
                $row['meta_description']
            );
            $i18n->execute();
            $i18n->close();
        }

        if ($editionId > 0) {
            foreach ($langCodes as $code) {
                $row = $editionI18nPost[$code];
                $workRow = $workI18nPost[$code] ?? null;
                $hasWork = $workRow && ($workRow['title'] ?? '') !== '';
                if (!$hasWork && bv2_admin_i18n_row_empty($row)) {
                    $del = $db->prepare('DELETE FROM book_edition_i18n WHERE edition_id=? AND lang=? LIMIT 1');
                    $del->bind_param('is', $editionId, $code);
                    $del->execute();
                    $del->close();
                    continue;
                }
                $edSlugSeed = $row['slug'] !== ''
                    ? $row['slug']
                    : (($workRow['slug'] ?? '') !== '' ? $workRow['slug'] : ($workRow['title'] ?? $origTitle));
                $edSlug = books_v2_slugify($edSlugSeed);
                if ($edSlug === '') {
                    $edSlug = 'edition-' . $editionId . '-' . $code;
                }
                $note = $row['note'] !== '' ? $row['note'] : null;
                $ei = $db->prepare(
                    'INSERT INTO book_edition_i18n
                     (edition_id, lang, slug, title_override, edition_statement, note, meta_description)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE slug=VALUES(slug), title_override=VALUES(title_override),
                       edition_statement=VALUES(edition_statement), note=VALUES(note),
                       meta_description=VALUES(meta_description)'
                );
                $ei->bind_param(
                    'issssss',
                    $editionId,
                    $code,
                    $edSlug,
                    $row['title_override'],
                    $row['edition_statement'],
                    $note,
                    $row['meta_description']
                );
                $ei->execute();
                $ei->close();
            }

            if ($isbnRaw !== '') {
                $norm = books_v2_normalize_isbn($isbnRaw);
                $existing = $db->prepare(
                    'SELECT id FROM book_edition_isbns WHERE edition_id=? AND is_primary=1 ORDER BY id LIMIT 1'
                );
                $existing->bind_param('i', $editionId);
                $existing->execute();
                $exRow = $existing->get_result()->fetch_assoc();
                $existing->close();
                $inv = $norm['is_invalid'] ? 1 : 0;
                $isbn13 = $norm['isbn13'];
                if ($exRow) {
                    $isbnId = (int) $exRow['id'];
                    if ($isbn13 === null) {
                        $db->query(
                            'UPDATE book_edition_isbns SET isbn_raw=\''
                            . $db->real_escape_string($norm['isbn_raw'])
                            . '\', isbn13=NULL, is_invalid=' . $inv
                            . ' WHERE id=' . $isbnId . ' LIMIT 1'
                        );
                    } else {
                        $u = $db->prepare(
                            'UPDATE book_edition_isbns SET isbn_raw=?, isbn13=?, is_invalid=? WHERE id=? LIMIT 1'
                        );
                        $u->bind_param('ssii', $norm['isbn_raw'], $isbn13, $inv, $isbnId);
                        $u->execute();
                        $u->close();
                    }
                } elseif ($isbn13 === null) {
                    $db->query(
                        'INSERT INTO book_edition_isbns
                         (edition_id, isbn_raw, isbn13, is_primary, is_invalid, sort_order)
                         VALUES (' . (int) $editionId . ',\''
                        . $db->real_escape_string($norm['isbn_raw'])
                        . '\',NULL,1,' . $inv . ',0)'
                    );
                } else {
                    $ins = $db->prepare(
                        'INSERT INTO book_edition_isbns
                         (edition_id, isbn_raw, isbn13, is_primary, is_invalid, sort_order)
                         VALUES (?,?,?,1,?,0)'
                    );
                    $ins->bind_param('issi', $editionId, $norm['isbn_raw'], $isbn13, $inv);
                    $ins->execute();
                    $ins->close();
                }
            }
        }

        $titleRu = $workI18nPost['ru']['title'] ?? $origTitle;
        $titleEn = $workI18nPost['en']['title'] ?? $titleRu;
        activity_log($db, [
            'verb' => $wasCreate ? 'created' : 'updated',
            'object_type' => 'book',
            'object_id' => $id,
            'action' => $wasCreate ? 'book_work.created' : 'book_work.updated',
            'event_scope' => ACTIVITY_SCOPE_METADATA,
            'is_public' => 0,
            'title_ru' => $titleRu !== '' ? $titleRu : $origTitle,
            'title_en' => $titleEn !== '' ? $titleEn : $origTitle,
            'after' => ['is_active' => $isActive, 'slug' => $primarySlug],
        ]);

        header('Location: /admin_books_v2.php?id=' . $id, true, 303);
        exit;
    }
}

$works_list = [];
$z = db_select(
    $db,
    "SELECT w.id, w.is_active, w.first_year, w.legacy_book_id, w.primary_edition_id,
            COALESCE(i_ru.title, i_en.title, i_es.title, CONCAT('Work #', w.id)) AS title,
            COALESCE(i_ru.subtitle, i_en.subtitle, i_es.subtitle, '') AS subtitle,
            COALESCE(i_ru.slug, i_en.slug, i_es.slug, '') AS slug,
            (SELECT COUNT(*) FROM book_editions e WHERE e.work_id = w.id) AS editions_count,
            (SELECT COUNT(*) FROM book_chapters c
              INNER JOIN book_editions e2 ON e2.id = c.edition_id
              WHERE e2.work_id = w.id) AS chapters_count,
            (SELECT GROUP_CONCAT(i.lang ORDER BY i.lang SEPARATOR ',')
               FROM book_work_i18n i WHERE i.work_id = w.id) AS langs
     FROM book_works w
     LEFT JOIN book_work_i18n i_ru ON i_ru.work_id = w.id AND i_ru.lang = 'ru'
     LEFT JOIN book_work_i18n i_en ON i_en.work_id = w.id AND i_en.lang = 'en'
     LEFT JOIN book_work_i18n i_es ON i_es.work_id = w.id AND i_es.lang = 'es'
     ORDER BY title ASC, w.id ASC"
);
while ($z && ($t = mysqli_fetch_assoc($z))) {
    $t['title'] = plain_text_normalize_for_storage((string) ($t['title'] ?? ''));
    $t['subtitle'] = plain_text_normalize_for_storage((string) ($t['subtitle'] ?? ''));
    $t['editions_count'] = (int) ($t['editions_count'] ?? 0);
    $t['chapters_count'] = (int) ($t['chapters_count'] ?? 0);
    $t['langs'] = (string) ($t['langs'] ?? '');
    $works_list[] = $t;
}
$smarty->assign('works_list', $works_list);

if ($id === 0 && count($works_list) > 0 && !isset($_GET['id'])) {
    $id = (int) ($works_list[0]['id'] ?? 0);
}

$work = null;
$work_i18n_by_lang = [];
$edition = null;
$edition_i18n_by_lang = [];
$isbn = null;
$credits = [];
$editions = [];
$chapters = [];

foreach ($langCodes as $code) {
    $work_i18n_by_lang[$code] = [
        'title' => '',
        'subtitle' => '',
        'slug' => '',
        'annotation' => '',
        'meta_description' => '',
    ];
    $edition_i18n_by_lang[$code] = [
        'slug' => '',
        'title_override' => '',
        'edition_statement' => '',
        'note' => '',
        'meta_description' => '',
    ];
}

if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM book_works WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $work = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($work) {
        $langId = (int) ($work['original_language_id'] ?? 1);
        $langCode = $langById[$langId] ?? 'ru';

        $is = $db->prepare('SELECT * FROM book_work_i18n WHERE work_id=?');
        $is->bind_param('i', $id);
        $is->execute();
        $isRes = $is->get_result();
        while ($row = $isRes->fetch_assoc()) {
            $code = (string) $row['lang'];
            $work_i18n_by_lang[$code] = [
                'title' => (string) ($row['title'] ?? ''),
                'subtitle' => (string) ($row['subtitle'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'annotation' => (string) ($row['annotation'] ?? ''),
                'meta_description' => (string) ($row['meta_description'] ?? ''),
            ];
        }
        $is->close();

        $editionId = (int) ($work['primary_edition_id'] ?? 0);
        if ($editionId > 0) {
            $es = $db->prepare('SELECT * FROM book_editions WHERE id=? LIMIT 1');
            $es->bind_param('i', $editionId);
            $es->execute();
            $edition = $es->get_result()->fetch_assoc();
            $es->close();
        }
        if (!$edition) {
            $es = $db->prepare('SELECT * FROM book_editions WHERE work_id=? ORDER BY sort_order, id LIMIT 1');
            $es->bind_param('i', $id);
            $es->execute();
            $edition = $es->get_result()->fetch_assoc();
            $es->close();
            $editionId = $edition ? (int) $edition['id'] : 0;
        }

        if ($editionId > 0) {
            $ei = $db->prepare('SELECT * FROM book_edition_i18n WHERE edition_id=?');
            $ei->bind_param('i', $editionId);
            $ei->execute();
            $eiRes = $ei->get_result();
            while ($row = $eiRes->fetch_assoc()) {
                $code = (string) $row['lang'];
                $edition_i18n_by_lang[$code] = [
                    'slug' => (string) ($row['slug'] ?? ''),
                    'title_override' => (string) ($row['title_override'] ?? ''),
                    'edition_statement' => (string) ($row['edition_statement'] ?? ''),
                    'note' => (string) ($row['note'] ?? ''),
                    'meta_description' => (string) ($row['meta_description'] ?? ''),
                ];
            }
            $ei->close();

            $ii = $db->prepare(
                'SELECT * FROM book_edition_isbns WHERE edition_id=? AND is_primary=1 ORDER BY id LIMIT 1'
            );
            $ii->bind_param('i', $editionId);
            $ii->execute();
            $isbn = $ii->get_result()->fetch_assoc();
            $ii->close();
            if (!$isbn) {
                $ii2 = $db->prepare(
                    'SELECT * FROM book_edition_isbns WHERE edition_id=? ORDER BY sort_order, id LIMIT 1'
                );
                $ii2->bind_param('i', $editionId);
                $ii2->execute();
                $isbn = $ii2->get_result()->fetch_assoc();
                $ii2->close();
            }
        }

        $cs = $db->prepare(
            'SELECT c.id, c.role, c.sort_order, a.nickname, a.name_ru, a.name_en
             FROM book_credits c
             INNER JOIN authors a ON a.id = c.author_id
             WHERE c.work_id=? OR c.edition_id=?
             ORDER BY c.sort_order, c.id'
        );
        $cs->bind_param('ii', $id, $editionId);
        $cs->execute();
        $cr = $cs->get_result();
        while ($crow = $cr->fetch_assoc()) {
            $credits[] = $crow;
        }
        $cs->close();

        $eds = $db->prepare(
            'SELECT e.id, e.edition_kind, e.publish_year, e.pages, e.is_active, e.legacy_book_id,
                    COALESCE(ei.slug, \'\') AS slug,
                    (SELECT COUNT(*) FROM book_chapters ch WHERE ch.edition_id = e.id) AS chapters_count
             FROM book_editions e
             LEFT JOIN book_edition_i18n ei ON ei.edition_id = e.id AND ei.lang = ?
             WHERE e.work_id=?
             ORDER BY e.sort_order, e.id'
        );
        $eds->bind_param('si', $langCode, $id);
        $eds->execute();
        $edsRes = $eds->get_result();
        while ($erow = $edsRes->fetch_assoc()) {
            $editions[] = $erow;
        }
        $eds->close();

        if ($editionId > 0) {
            $chs = $db->prepare(
                'SELECT c.id, c.sort_order, c.views, c.legacy_ch_id, c.chapter_type,
                        COALESCE(ci.title, CONCAT(\'Chapter #\', c.id)) AS title,
                        COALESCE(ci.slug, \'\') AS slug
                 FROM book_chapters c
                 LEFT JOIN book_chapter_i18n ci ON ci.chapter_id = c.id AND ci.lang = ?
                 WHERE c.edition_id=?
                 ORDER BY c.sort_order, c.id
                 LIMIT 200'
            );
            $chs->bind_param('si', $langCode, $editionId);
            $chs->execute();
            $chsRes = $chs->get_result();
            while ($chrow = $chsRes->fetch_assoc()) {
                $chrow['title'] = books_v2_strip_html_title((string) ($chrow['title'] ?? ''));
                $chapters[] = $chrow;
            }
            $chs->close();
        }
    }
}

// Keep posted values on validation error.
if ($error !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $work_i18n_by_lang = bv2_admin_post_work_i18n($langCodes);
    $edition_i18n_by_lang = bv2_admin_post_edition_i18n($langCodes);
}

$smarty->assign('error', $error);
$smarty->assign('work', $work);
$smarty->assign('work_i18n_by_lang', $work_i18n_by_lang);
$smarty->assign('edition', $edition);
$smarty->assign('edition_i18n_by_lang', $edition_i18n_by_lang);
$smarty->assign('isbn', $isbn);
$smarty->assign('credits', $credits);
$smarty->assign('editions', $editions);
$smarty->assign('chapters', $chapters);
$smarty->assign('work_id', $id);

$press_list = [];
$z = db_select($db, 'SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC');
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . ' - ' . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$smarty->assign('title', 'Админка: Книги v2');
$smarty->display('admin_books_v2.tpl');
