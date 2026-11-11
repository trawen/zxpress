#!/usr/bin/env php
<?php
/**
 * Import Restore diskmag issues/articles (cs + ru markdown) into ezines_v2.
 *
 * Resolves ezine by slug "restore" (creates if missing). Never uses hardcoded
 * press id 326 — that is Screamergy on prod.
 *
 * Usage:
 *   php site/cli/import-restore-ezines-v2.php --source=/path/to/RESTORE_texts --apply
 *   php site/cli/import-restore-ezines-v2.php --source=... --i18n=.../restore_i18n --files=... --apply
 *   php site/cli/import-restore-ezines-v2.php --source=/tmp/RESTORE_texts --dry-run
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$dryRun = true;
$source = '';
$filesDir = '';
$i18nDir = '';
$ezineId = 0; // 0 = resolve/create by slug

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--source=')) {
        $source = rtrim(substr($arg, 9), '/');
    } elseif (str_starts_with($arg, '--files=')) {
        $filesDir = rtrim(substr($arg, 8), '/');
    } elseif (str_starts_with($arg, '--i18n=')) {
        $i18nDir = rtrim(substr($arg, 7), '/');
    } elseif (str_starts_with($arg, '--ezine-id=')) {
        $ezineId = max(0, (int) substr($arg, 11));
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(
            STDOUT,
            "Usage: {$argv[0]} --source=DIR [--i18n=DIR] [--files=DIR] [--ezine-id=N] [--dry-run|--apply]\n"
        );
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

if ($source === '' || !is_dir($source)) {
    fwrite(STDERR, "Need existing --source=DIR (RESTORE_texts root)\n");
    exit(1);
}

$_SERVER['REQUEST_URI'] = '/cli/import-restore-ezines-v2.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-restore';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';
require_once $siteRoot . '/includes/ezines_v2_admin.php';
require_once $siteRoot . '/includes/periodicals_slugs.php';

if (!ezines_v2_ready($db)) {
    fwrite(STDERR, "ezines_v2 schema not ready\n");
    exit(1);
}

/** @var list<array{num:int,dir:string,date:int,title_cs:string,title_ru:string,title_en:string,desc_cs:string,desc_ru:string,desc_en:string,meta_cs:string,meta_ru:string,meta_en:string,file?:string}> */
$issuesMeta = [
    [
        'num' => 0,
        'dir' => 'RESTOR00',
        'date' => (int) mktime(0, 0, 0, 8, 29, 2020),
        'title_cs' => '0',
        'title_ru' => '0',
        'title_en' => '0',
        'desc_cs' => 'Ukázkové číslo 0 vyšlo 29. srpna 2020 při příležitosti KapliConu 2020.',
        'desc_ru' => 'Пробный номер 0 вышел 29 августа 2020 к KapliCon 2020.',
        'desc_en' => 'Demo issue 0 was released on 29 August 2020 for KapliCon 2020.',
        'meta_cs' => 'Restore #0 (29.8.2020, KapliCon): ukázkové číslo českého diskmagu pro ZX Spectrum.',
        'meta_ru' => 'Restore #0 (29.08.2020, KapliCon): пробный номер чешского дискмага для ZX Spectrum.',
        'meta_en' => 'Restore #0 (29 Aug 2020, KapliCon): demo issue of the Czech ZX Spectrum diskmag.',
        'file' => 'restore00.d80.zip',
    ],
    [
        'num' => 1,
        'dir' => 'RESTOR01',
        'date' => (int) mktime(0, 0, 0, 4, 23, 2021),
        'title_cs' => '1',
        'title_ru' => '1',
        'title_en' => '1',
        'desc_cs' => 'První ostré číslo vyšlo 23. dubna 2021 k 39. narozeninám ZX Spectra.',
        'desc_ru' => 'Первый полноценный номер вышел 23 апреля 2021 к 39-летию ZX Spectrum.',
        'desc_en' => 'The first full issue was released on 23 April 2021 for the ZX Spectrum\'s 39th birthday.',
        'meta_cs' => 'Restore #1 (23.4.2021): první ostré číslo k 39. narozeninám ZX Spectra.',
        'meta_ru' => 'Restore #1 (23.04.2021): первый полноценный номер к 39-летию ZX Spectrum.',
        'meta_en' => 'Restore #1 (23 Apr 2021): first full issue for the ZX Spectrum\'s 39th birthday.',
        'file' => 'restore01.d80.zip',
    ],
    [
        'num' => 2,
        'dir' => 'RESTOR02',
        'date' => (int) mktime(0, 0, 0, 10, 29, 2022),
        'title_cs' => '2',
        'title_ru' => '2',
        'title_en' => '2',
        'desc_cs' => 'Druhé číslo vyšlo 29. října 2022 při příležitosti ByteFestu 2022.',
        'desc_ru' => 'Второй номер вышел 29 октября 2022 к ByteFest 2022.',
        'desc_en' => 'Issue 2 was released on 29 October 2022 for ByteFest 2022.',
        'meta_cs' => 'Restore #2 (29.10.2022, ByteFest): podpora MDOS, TR-DOS, BS-DOS a +3DOS.',
        'meta_ru' => 'Restore #2 (29.10.2022, ByteFest): поддержка MDOS, TR-DOS, BS-DOS и +3DOS.',
        'meta_en' => 'Restore #2 (29 Oct 2022, ByteFest): MDOS, TR-DOS, BS-DOS and +3DOS support.',
        'file' => 'restore02.trd.zip',
    ],
    [
        'num' => 3,
        'dir' => 'RESTOR03',
        'date' => (int) mktime(0, 0, 0, 12, 3, 2023),
        'title_cs' => '3',
        'title_ru' => '3',
        'title_en' => '3',
        'desc_cs' => 'Třetí číslo vyšlo 3. prosince 2023 během JHConu 2023.',
        'desc_ru' => 'Третий номер вышел 3 декабря 2023 на JHCon 2023.',
        'desc_en' => 'Issue 3 was released on 3 December 2023 during JHCon 2023.',
        'meta_cs' => 'Restore #3 (3.12.2023, JHCon): speciál k 20 letům JHConu a další texty.',
        'meta_ru' => 'Restore #3 (03.12.2023, JHCon): спецвыпуск к 20-летию JHCon и другие материалы.',
        'meta_en' => 'Restore #3 (3 Dec 2023, JHCon): special on 20 years of JHCon and more.',
        'file' => 'restore03.trd.zip',
    ],
    [
        'num' => 4,
        'dir' => 'RESTOR04',
        'date' => (int) mktime(0, 0, 0, 12, 31, 2024),
        'title_cs' => '4',
        'title_ru' => '4',
        'title_en' => '4',
        'desc_cs' => 'Čtvrté číslo vyšlo 31. prosince 2024.',
        'desc_ru' => 'Четвёртый номер вышел 31 декабря 2024.',
        'desc_en' => 'Issue 4 was released on 31 December 2024.',
        'meta_cs' => 'Restore #4 (31.12.2024): Donkey Kong, Opus Discovery, ZX Touch, ZX VGA Joy a další.',
        'meta_ru' => 'Restore #4 (31.12.2024): Donkey Kong, Opus Discovery, ZX Touch, ZX VGA Joy и др.',
        'meta_en' => 'Restore #4 (31 Dec 2024): Donkey Kong, Opus Discovery, ZX Touch, ZX VGA Joy and more.',
        'file' => 'RESTOR04.zip',
    ],
    [
        'num' => 5,
        'dir' => 'RESTOR05',
        'date' => (int) mktime(0, 0, 0, 7, 17, 2026),
        'title_cs' => '5',
        'title_ru' => '5',
        'title_en' => '5',
        'desc_cs' => 'Páté číslo vyšlo 17. července 2026 během ZX Šumavy 2026.',
        'desc_ru' => 'Пятый номер вышел 17 июля 2026 на ZX Šumava 2026.',
        'desc_en' => 'Issue 5 was released on 17 July 2026 during ZX Šumava 2026.',
        'meta_cs' => 'Restore #5 (17.7.2026, ZX Šumava): NedoOS, Opus Discovery 2, ZX Kraken, dema a reportáže.',
        'meta_ru' => 'Restore #5 (17.07.2026, ZX Šumava): NedoOS, Opus Discovery 2, ZX Kraken, демо и репортажи.',
        'meta_en' => 'Restore #5 (17 Jul 2026, ZX Šumava): NedoOS, Opus Discovery 2, ZX Kraken, demos and reports.',
        'file' => 'RESTOR05.zip',
    ],
];

/**
 * @return list<array{title:string,file:string}>
 */
function restore_parse_index(string $indexPath): array
{
    if (!is_file($indexPath)) {
        return [];
    }
    $text = (string) file_get_contents($indexPath);
    $out = [];
    if (!preg_match_all('/\[([^\]]+)\]\(([^)]+\.md)\)/u', $text, $m, PREG_SET_ORDER)) {
        return [];
    }
    foreach ($m as $row) {
        $out[] = ['title' => trim($row[1]), 'file' => basename($row[2])];
    }

    return $out;
}

function restore_read_md(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $body = (string) file_get_contents($path);
    $body = str_replace("\r\n", "\n", $body);
    $body = preg_replace('/\A#\s+[^\n]*\n+/u', '', $body, 1) ?? $body;

    return trim($body) . "\n";
}

function restore_unique_slug(mysqli $db, int $issueId, string $lang, string $title, int $sortOrder): string
{
    $base = per_slug_from_title($title);
    if ($base === '') {
        $base = 'article-' . $sortOrder;
    }
    $slug = $base;
    $n = 2;
    while (true) {
        $st = $db->prepare(
            'SELECT article_id FROM ezine_article_i18n WHERE issue_id=? AND lang=? AND slug=? LIMIT 1'
        );
        if (!$st) {
            break;
        }
        $st->bind_param('iss', $issueId, $lang, $slug);
        $st->execute();
        $hit = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$hit) {
            break;
        }
        $slug = $base . '-' . $n;
        $n++;
        if ($n > 50) {
            $slug = $base . '-' . $sortOrder . '-' . bin2hex(random_bytes(2));
            break;
        }
    }

    return $slug;
}

/**
 * Load restore_i18n JSON keyed by "{issueNum}/{source_file}".
 *
 * @return array<string, array<string, mixed>>
 */
function restore_load_i18n_map(string $dir): array
{
    $map = [];
    if ($dir === '' || !is_dir($dir)) {
        return $map;
    }
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw)) {
            continue;
        }
        $issue = (string) ($raw['issue'] ?? '');
        $file = (string) ($raw['source_file'] ?? '');
        $sort = (int) ($raw['sort_order'] ?? 0);
        if ($issue !== '' && $file !== '') {
            $map[$issue . '/' . $file] = $raw;
        }
        if ($issue !== '' && $sort > 0) {
            $map[$issue . '/#' . $sort] = $raw;
        }
    }

    return $map;
}

function restore_find_ezine_by_slug(mysqli $db, string $slug): int
{
    $st = $db->prepare('SELECT ezine_id FROM ezine_i18n WHERE slug=? LIMIT 1');
    if (!$st) {
        return 0;
    }
    $st->bind_param('s', $slug);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return (int) ($row['ezine_id'] ?? 0);
}

function restore_create_ezine(mysqli $db): int
{
    $orig = 'cs';
    $type = 1;
    // years_from/to are unix timestamps of first/last issue, not calendar years.
    $yearsFrom = (int) gmmktime(0, 0, 0, 8, 29, 2020); // Restore #0
    $yearsTo = (int) gmmktime(0, 0, 0, 7, 17, 2026);   // Restore #5
    $numbers = 6;
    $isActive = 1;
    $st = $db->prepare(
        'INSERT INTO ezines (original_language, type, city_id, years_from, years_to, numbers, ex, is_active)
         VALUES (?, ?, NULL, ?, ?, ?, \'\', ?)'
    );
    if (!$st || !$st->bind_param('siiiii', $orig, $type, $yearsFrom, $yearsTo, $numbers, $isActive) || !$st->execute()) {
        return 0;
    }
    $id = (int) $st->insert_id;
    $st->close();

    return $id;
}

if ($i18nDir === '') {
    foreach ([
        $siteRoot . '/cli/imports/restore_i18n',
        dirname($siteRoot) . '/site/cli/imports/restore_i18n',
        '/data/tmp/restore_import/restore_i18n',
    ] as $cand) {
        if (is_dir($cand)) {
            $i18nDir = $cand;
            break;
        }
    }
}
$i18nMap = restore_load_i18n_map($i18nDir);
fwrite(STDOUT, 'i18n metas loaded: ' . count($i18nMap) . " from {$i18nDir}\n");

if ($ezineId <= 0) {
    $ezineId = restore_find_ezine_by_slug($db, 'restore');
}
if ($ezineId <= 0) {
    if ($dryRun) {
        fwrite(STDOUT, "[dry-run] would create ezine slug=restore\n");
        $ezineId = 0;
    } else {
        $ezineId = restore_create_ezine($db);
        if ($ezineId <= 0) {
            fwrite(STDERR, "Failed to create Restore ezine\n");
            exit(1);
        }
        fwrite(STDOUT, "created ezine_id={$ezineId}\n");
    }
}

$ezine = null;
if ($ezineId > 0) {
    $st = $db->prepare('SELECT id, legacy_press_id FROM ezines WHERE id=? LIMIT 1');
    $st->bind_param('i', $ezineId);
    $st->execute();
    $ezine = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$ezine) {
        fwrite(STDERR, "ezine id={$ezineId} not found\n");
        exit(1);
    }
}

fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '[apply] ') . "Restore ezine_id={$ezineId} source={$source}\n");

if (!$dryRun && $ezineId > 0) {
    $yearsFrom = (int) gmmktime(0, 0, 0, 8, 29, 2020);
    $yearsTo = (int) gmmktime(0, 0, 0, 7, 17, 2026);
    $upd = $db->prepare(
        "UPDATE ezines SET original_language='cs', type=1, years_from=?, years_to=?, numbers=6, is_active=1 WHERE id=? LIMIT 1"
    );
    if ($upd) {
        $upd->bind_param('iii', $yearsFrom, $yearsTo, $ezineId);
        $upd->execute();
        $upd->close();
    }
    ezines_v2_upsert_ezine_i18n($db, $ezineId, 'cs', [
        'title' => 'Restore',
        'slug' => 'restore',
        'description' => 'Český diskmag pro počítače ZX Spectrum a kompatibilní. Autor: Logout. https://restore.zxm.cz/',
        'meta_description' => 'Restore — český diskmag pro ZX Spectrum (2020–2026), Logout.',
    ]);
    ezines_v2_upsert_ezine_i18n($db, $ezineId, 'ru', [
        'title' => 'Restore',
        'slug' => 'restore',
        'description' => 'Чешский дискмаг для ZX Spectrum и совместимых. Автор: Logout. https://restore.zxm.cz/',
        'meta_description' => 'Restore — чешский дискмаг для ZX Spectrum (2020–2026), Logout.',
    ]);
    ezines_v2_upsert_ezine_i18n($db, $ezineId, 'en', [
        'title' => 'Restore',
        'slug' => 'restore',
        'description' => 'Czech disk magazine for ZX Spectrum and compatibles. Author: Logout. https://restore.zxm.cz/',
        'meta_description' => 'Restore — Czech ZX Spectrum diskmag (2020–2026) by Logout.',
    ]);
    ezines_v2_admin_ensure_legacy_press($db, $ezineId);
}

$issuesCreated = 0;
$articlesUpserted = 0;
$filesAttached = 0;

foreach ($issuesMeta as $meta) {
    $num = (int) $meta['num'];
    $dir = (string) $meta['dir'];
    $csDir = $source . '/' . $dir;
    $ruDir = $source . '/ru/' . $dir;
    $csIndex = restore_parse_index($csDir . '/INDEX.md');
    $ruIndex = restore_parse_index($ruDir . '/INDEX.md');
    if ($csIndex === []) {
        fwrite(STDERR, "WARN: no CS INDEX for {$dir}\n");
        continue;
    }
    $ruByFile = [];
    foreach ($ruIndex as $row) {
        $ruByFile[$row['file']] = $row;
    }

    fwrite(STDOUT, "issue {$num} ({$dir}): " . count($csIndex) . " articles\n");

    $issueId = 0;
    $find = $db->prepare('SELECT id FROM ezine_issues WHERE ezine_id=? AND sort_order=? LIMIT 1');
    if ($find) {
        $find->bind_param('ii', $ezineId, $num);
        $find->execute();
        $issueId = (int) (($find->get_result()->fetch_assoc()['id'] ?? 0));
        $find->close();
    }

    if ($dryRun) {
        if ($issueId <= 0) {
            $issuesCreated++;
        }
        foreach ($csIndex as $i => $csArt) {
            $articlesUpserted++;
            $ruTitle = $ruByFile[$csArt['file']]['title'] ?? '';
            fwrite(STDOUT, "  - {$csArt['file']}: cs={$csArt['title']}" . ($ruTitle !== '' ? " / ru={$ruTitle}" : " (no ru)") . "\n");
        }
        continue;
    }

    $issueDate = (int) $meta['date'];
    $isMissing = 0;
    $isActive = 1;
    if ($issueId <= 0) {
        $ins = $db->prepare(
            'INSERT INTO ezine_issues (ezine_id, issue_date, sort_order, is_missing, is_active) VALUES (?,?,?,?,?)'
        );
        if (!$ins || !$ins->bind_param('iiiii', $ezineId, $issueDate, $num, $isMissing, $isActive) || !$ins->execute()) {
            fwrite(STDERR, "FAIL create issue {$num}\n");
            continue;
        }
        $issueId = (int) $ins->insert_id;
        $ins->close();
        $issuesCreated++;
    } else {
        $upd = $db->prepare(
            'UPDATE ezine_issues SET issue_date=?, is_missing=0, is_active=1 WHERE id=? AND ezine_id=? LIMIT 1'
        );
        if ($upd) {
            $upd->bind_param('iii', $issueDate, $issueId, $ezineId);
            $upd->execute();
            $upd->close();
        }
    }

    $slugCs = (string) $num;
    $slugRu = (string) $num;
    $slugEn = (string) $num;
    ezines_v2_upsert_issue_i18n($db, $issueId, $ezineId, 'cs', [
        'title' => $meta['title_cs'],
        'slug' => $slugCs,
        'description' => $meta['desc_cs'],
        'meta_description' => $meta['meta_cs'],
    ]);
    ezines_v2_upsert_issue_i18n($db, $issueId, $ezineId, 'ru', [
        'title' => $meta['title_ru'],
        'slug' => $slugRu,
        'description' => $meta['desc_ru'],
        'meta_description' => $meta['meta_ru'],
    ]);
    ezines_v2_upsert_issue_i18n($db, $issueId, $ezineId, 'en', [
        'title' => $meta['title_en'],
        'slug' => $slugEn,
        'description' => $meta['desc_en'],
        'meta_description' => $meta['meta_en'],
    ]);
    ezines_v2_admin_ensure_legacy_issue($db, $issueId);

    foreach ($csIndex as $i => $csArt) {
        $sortOrder = $i + 1;
        $fileName = $csArt['file'];
        $titleCs = $csArt['title'];
        $titleRu = (string) ($ruByFile[$fileName]['title'] ?? '');
        $bodyCs = restore_read_md($csDir . '/' . $fileName);
        $bodyRu = restore_read_md($ruDir . '/' . $fileName);
        if ($bodyCs === "\n" || $bodyCs === '') {
            fwrite(STDERR, "WARN: empty cs body {$dir}/{$fileName}\n");
            continue;
        }

        $articleId = 0;
        $af = $db->prepare(
            'SELECT id FROM ezine_articles WHERE issue_id=? AND sort_order=? LIMIT 1'
        );
        if ($af) {
            $af->bind_param('ii', $issueId, $sortOrder);
            $af->execute();
            $articleId = (int) (($af->get_result()->fetch_assoc()['id'] ?? 0));
            $af->close();
        }

        $orig = 'cs';
        $pubAt = $issueDate;
        if ($articleId <= 0) {
            $insA = $db->prepare(
                'INSERT INTO ezine_articles (issue_id, original_language, sort_order, views, is_active, published_at)
                 VALUES (?,?,?,0,1,?)'
            );
            if (!$insA || !$insA->bind_param('isii', $issueId, $orig, $sortOrder, $pubAt) || !$insA->execute()) {
                fwrite(STDERR, "FAIL article {$dir}/{$fileName}\n");
                continue;
            }
            $articleId = (int) $insA->insert_id;
            $insA->close();
        } else {
            $updA = $db->prepare(
                'UPDATE ezine_articles SET original_language=?, published_at=?, is_active=1 WHERE id=? AND issue_id=? LIMIT 1'
            );
            if ($updA) {
                $updA->bind_param('siii', $orig, $pubAt, $articleId, $issueId);
                $updA->execute();
                $updA->close();
            }
        }

        $issueKey = sprintf('%02d', $num);
        $pack = $i18nMap[$issueKey . '/' . $fileName]
            ?? $i18nMap[$issueKey . '/#' . $sortOrder]
            ?? [];
        $metaCs = trim((string) ($pack['meta_description_cs'] ?? ''));
        $metaRu = trim((string) ($pack['meta_description_ru'] ?? ''));
        $metaEn = trim((string) ($pack['meta_description_en'] ?? ''));
        $titleEn = trim((string) ($pack['title_en'] ?? ''));
        $bodyEn = (string) ($pack['body_en'] ?? '');

        $slugCsArt = restore_unique_slug($db, $issueId, 'cs', $titleCs, $sortOrder);
        // If updating existing, keep existing slug when present.
        $ex = $db->prepare('SELECT slug FROM ezine_article_i18n WHERE article_id=? AND lang=\'cs\' LIMIT 1');
        if ($ex) {
            $ex->bind_param('i', $articleId);
            $ex->execute();
            $old = (string) (($ex->get_result()->fetch_assoc()['slug'] ?? ''));
            $ex->close();
            if ($old !== '') {
                $slugCsArt = $old;
            }
        }
        ezines_v2_upsert_article_i18n($db, $articleId, $issueId, 'cs', [
            'title' => $titleCs,
            'slug' => $slugCsArt,
            'meta_description' => $metaCs,
        ]);
        ezines_v2_upsert_article_body($db, $articleId, 'cs', 'markdown', $bodyCs, true);

        if ($titleRu !== '' && $bodyRu !== '' && $bodyRu !== "\n") {
            $slugRuArt = restore_unique_slug($db, $issueId, 'ru', $titleRu, $sortOrder);
            $ex = $db->prepare('SELECT slug FROM ezine_article_i18n WHERE article_id=? AND lang=\'ru\' LIMIT 1');
            if ($ex) {
                $ex->bind_param('i', $articleId);
                $ex->execute();
                $old = (string) (($ex->get_result()->fetch_assoc()['slug'] ?? ''));
                $ex->close();
                if ($old !== '') {
                    $slugRuArt = $old;
                }
            }
            ezines_v2_upsert_article_i18n($db, $articleId, $issueId, 'ru', [
                'title' => $titleRu,
                'slug' => $slugRuArt,
                'meta_description' => $metaRu,
            ]);
            ezines_v2_upsert_article_body($db, $articleId, 'ru', 'markdown', $bodyRu, true);
        }

        if ($metaEn !== '' || $titleEn !== '' || trim($bodyEn) !== '') {
            $enTitle = $titleEn !== '' ? $titleEn : $titleCs;
            $slugEnArt = restore_unique_slug($db, $issueId, 'en', $enTitle, $sortOrder);
            $ex = $db->prepare('SELECT slug FROM ezine_article_i18n WHERE article_id=? AND lang=\'en\' LIMIT 1');
            if ($ex) {
                $ex->bind_param('i', $articleId);
                $ex->execute();
                $old = (string) (($ex->get_result()->fetch_assoc()['slug'] ?? ''));
                $ex->close();
                if ($old !== '') {
                    $slugEnArt = $old;
                }
            }
            ezines_v2_upsert_article_i18n($db, $articleId, $issueId, 'en', [
                'title' => $enTitle,
                'slug' => $slugEnArt,
                'meta_description' => $metaEn,
            ]);
            if (trim($bodyEn) !== '') {
                ezines_v2_upsert_article_body($db, $articleId, 'en', 'markdown', $bodyEn, true);
            }
        }

        $articlesUpserted++;
    }

    // Optional download file for the issue.
    if ($filesDir !== '' && !empty($meta['file'])) {
        $srcFile = $filesDir . '/' . $meta['file'];
        if (!is_file($srcFile)) {
            // try alternate names
            foreach (glob($filesDir . '/restore0' . $num . '.*') ?: [] as $cand) {
                $srcFile = $cand;
                break;
            }
        }
        if (is_file($srcFile)) {
            $existing = ezines_v2_admin_issue_files($db, $issueId);
            $wantName = basename($srcFile);
            $already = false;
            foreach ($existing as $f) {
                if (strcasecmp((string) $f['name'], $wantName) === 0
                    || str_contains(strtolower((string) $f['name']), 'restore0' . $num)
                    || str_contains(strtolower((string) $f['name']), 'restor0' . $num)
                ) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $legacyIssueId = ezines_v2_admin_ensure_legacy_issue($db, $issueId);
                $pressId = 0;
                $pst = $db->prepare(
                    'SELECT e.legacy_press_id FROM ezine_issues i INNER JOIN ezines e ON e.id=i.ezine_id WHERE i.id=? LIMIT 1'
                );
                if ($pst) {
                    $pst->bind_param('i', $issueId);
                    $pst->execute();
                    $pressId = (int) (($pst->get_result()->fetch_assoc()['legacy_press_id'] ?? 0));
                    $pst->close();
                }
                if ($legacyIssueId > 0 && $pressId > 0) {
                    $name = ezines_v2_admin_unique_file_name($db, $wantName);
                    $sizeKb = (int) ceil(filesize($srcFile) / 1000);
                    $type = ezines_v2_admin_file_type_from_ext(strtolower(pathinfo($name, PATHINFO_EXTENSION)));
                    $tm = time();
                    $title = 'Restore #' . $num;
                    $title = mb_substr($title, 0, 32, 'UTF-8');
                    $insF = $db->prepare(
                        'INSERT INTO files (`id`, `id_issue`, `id_press`, `date`, `name`, `type`, `file_title`, `size`, `downloads`, `delete`, `file_comment`)
                         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, 0, \'\')'
                    );
                    if ($insF) {
                        $insF->bind_param('iiisisi', $legacyIssueId, $pressId, $tm, $name, $type, $title, $sizeKb);
                        if ($insF->execute()) {
                            $fileId = (int) $insF->insert_id;
                            $dest = zx_storage_path('files', $name);
                            $dirName = dirname($dest);
                            if (!is_dir($dirName)) {
                                @mkdir($dirName, 0775, true);
                            }
                            if (@copy($srcFile, $dest)) {
                                $filesAttached++;
                                fwrite(STDOUT, "  + file {$name}\n");
                            } else {
                                $db->query('DELETE FROM files WHERE id=' . (int) $fileId . ' LIMIT 1');
                                fwrite(STDERR, "WARN: copy failed for {$srcFile}\n");
                            }
                        }
                        $insF->close();
                    }
                }
            }
        }
    }
}

fwrite(STDOUT, "done issues_new={$issuesCreated} articles_upserted={$articlesUpserted} files={$filesAttached}\n");
exit(0);
