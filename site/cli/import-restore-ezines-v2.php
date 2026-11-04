#!/usr/bin/env php
<?php
/**
 * Import Restore diskmag issues/articles (cs + ru markdown) into ezines_v2.
 *
 * Usage:
 *   php site/cli/import-restore-ezines-v2.php --source=/path/to/RESTORE_texts --apply
 *   php site/cli/import-restore-ezines-v2.php --source=/tmp/RESTORE_texts --dry-run
 *   php site/cli/import-restore-ezines-v2.php --source=... --files=/path/to/restore_dl --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$dryRun = true;
$source = '';
$filesDir = '';
$ezineId = 326;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--source=')) {
        $source = rtrim(substr($arg, 9), '/');
    } elseif (str_starts_with($arg, '--files=')) {
        $filesDir = rtrim(substr($arg, 8), '/');
    } elseif (str_starts_with($arg, '--ezine-id=')) {
        $ezineId = max(0, (int) substr($arg, 11));
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: {$argv[0]} --source=DIR [--files=DIR] [--ezine-id=326] [--dry-run|--apply]\n");
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

/** @var list<array{num:int,dir:string,date:int,title_cs:string,title_ru:string,desc_cs:string,desc_ru:string,file?:string}> */
$issuesMeta = [
    [
        'num' => 0,
        'dir' => 'RESTOR00',
        'date' => (int) mktime(0, 0, 0, 8, 29, 2020),
        'title_cs' => '0',
        'title_ru' => '0',
        'desc_cs' => 'Ukázkové číslo 0 vyšlo 29. srpna 2020 při příležitosti KapliConu 2020.',
        'desc_ru' => 'Пробный номер 0 вышел 29 августа 2020 к KapliCon 2020.',
        'file' => 'restore00.d80.zip',
    ],
    [
        'num' => 1,
        'dir' => 'RESTOR01',
        'date' => (int) mktime(0, 0, 0, 4, 23, 2021),
        'title_cs' => '1',
        'title_ru' => '1',
        'desc_cs' => 'První ostré číslo vyšlo 23. dubna 2021 k 39. narozeninám ZX Spectra.',
        'desc_ru' => 'Первый полноценный номер вышел 23 апреля 2021 к 39-летию ZX Spectrum.',
        'file' => 'restore01.d80.zip',
    ],
    [
        'num' => 2,
        'dir' => 'RESTOR02',
        'date' => (int) mktime(0, 0, 0, 10, 29, 2022),
        'title_cs' => '2',
        'title_ru' => '2',
        'desc_cs' => 'Druhé číslo vyšlo 29. října 2022 při příležitosti ByteFestu 2022.',
        'desc_ru' => 'Второй номер вышел 29 октября 2022 к ByteFest 2022.',
        'file' => 'restore02.trd.zip',
    ],
    [
        'num' => 3,
        'dir' => 'RESTOR03',
        'date' => (int) mktime(0, 0, 0, 12, 3, 2023),
        'title_cs' => '3',
        'title_ru' => '3',
        'desc_cs' => 'Třetí číslo vyšlo 3. prosince 2023 během JHConu 2023.',
        'desc_ru' => 'Третий номер вышел 3 декабря 2023 на JHCon 2023.',
        'file' => 'restore03.trd.zip',
    ],
    [
        'num' => 4,
        'dir' => 'RESTOR04',
        'date' => (int) mktime(0, 0, 0, 12, 31, 2024),
        'title_cs' => '4',
        'title_ru' => '4',
        'desc_cs' => 'Čtvrté číslo vyšlo 31. prosince 2024.',
        'desc_ru' => 'Четвёртый номер вышел 31 декабря 2024.',
        'file' => 'RESTOR04.zip',
    ],
    [
        'num' => 5,
        'dir' => 'RESTOR05',
        'date' => (int) mktime(0, 0, 0, 7, 17, 2026),
        'title_cs' => '5',
        'title_ru' => '5',
        'desc_cs' => 'Páté číslo vyšlo 17. července 2026 během ZX Šumavy 2026.',
        'desc_ru' => 'Пятый номер вышел 17 июля 2026 на ZX Šumava 2026.',
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

$st = $db->prepare('SELECT id, legacy_press_id FROM ezines WHERE id=? LIMIT 1');
$st->bind_param('i', $ezineId);
$st->execute();
$ezine = $st->get_result()->fetch_assoc();
$st->close();
if (!$ezine) {
    fwrite(STDERR, "ezine id={$ezineId} not found\n");
    exit(1);
}

fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '[apply] ') . "Restore ezine_id={$ezineId} source={$source}\n");

if (!$dryRun) {
    $upd = $db->prepare(
        "UPDATE ezines SET original_language='cs', type=1, years_from=2020, years_to=2026, numbers=6 WHERE id=? LIMIT 1"
    );
    if ($upd) {
        $upd->bind_param('i', $ezineId);
        $upd->execute();
        $upd->close();
    }
    ezines_v2_upsert_ezine_i18n($db, $ezineId, 'cs', [
        'title' => 'Restore',
        'slug' => 'restore',
        'description' => 'Český diskmag pro počítače ZX Spectrum a kompatibilní. Autor: Logout.',
        'meta_description' => 'Restore — český diskmag pro ZX Spectrum',
    ]);
    ezines_v2_upsert_ezine_i18n($db, $ezineId, 'ru', [
        'title' => 'Restore',
        'slug' => 'restore',
        'description' => 'Чешский дискмаг для ZX Spectrum и совместимых. Автор: Logout.',
        'meta_description' => 'Restore — чешский дискмаг для ZX Spectrum',
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
    ezines_v2_upsert_issue_i18n($db, $issueId, $ezineId, 'cs', [
        'title' => $meta['title_cs'],
        'slug' => $slugCs,
        'description' => $meta['desc_cs'],
        'meta_description' => 'Restore #' . $num,
    ]);
    ezines_v2_upsert_issue_i18n($db, $issueId, $ezineId, 'ru', [
        'title' => $meta['title_ru'],
        'slug' => $slugRu,
        'description' => $meta['desc_ru'],
        'meta_description' => 'Restore #' . $num,
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
            'meta_description' => '',
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
                'meta_description' => '',
            ]);
            ezines_v2_upsert_article_body($db, $articleId, 'ru', 'markdown', $bodyRu, true);
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
