#!/usr/bin/env php
<?php
/**
 * Update Emulate (press #66) articles with RU titles + plain-text bodies (+ EN), dual-write ezines_v2.
 *
 *   php site/cli/import-emulate-articles.php --dry-run
 *   php site/cli/import-emulate-articles.php --apply
 *   php site/cli/import-emulate-articles.php --apply --only=01
 *
 * i18n: site/cli/imports/emulate_i18n/*.json (issue_key + number).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const EMULATE_PRESS_ID = 66;
const EMULATE_SOURCE = 'migration:emulate_articles';
const EMULATE_TEXT_TYPE = 3; // markdown (plain originals kept as body_*_plain in JSON)

$dryRun = true;
$pressId = EMULATE_PRESS_ID;
$source = '';
$onlyIssues = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--press-id=')) {
        $pressId = max(0, (int) substr($arg, 11));
    } elseif (str_starts_with($arg, '--source=')) {
        $source = rtrim(substr($arg, 9), '/');
    } elseif (str_starts_with($arg, '--only=')) {
        foreach (explode(',', substr($arg, 7)) as $part) {
            $p = trim($part);
            if ($p !== '') {
                $onlyIssues[] = $p;
            }
        }
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--press-id=66] [--source=DIR] [--only=01,02,special-edition]\n");
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/import-emulate-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-emulate';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/article_text_render.php';
require_once $siteRoot . '/includes/admin_helpers.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';
require_once $siteRoot . '/includes/article_search_text.php';

if ($source === '') {
    $candidates = [
        $siteRoot . '/cli/imports/emulate_i18n',
        dirname($siteRoot) . '/db/migration/emulate_articles/i18n',
    ];
    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            $source = $cand;
            break;
        }
    }
}
if ($source === '' || !is_dir($source)) {
    fwrite(STDERR, "Need --source=DIR with *.json i18n\n");
    exit(1);
}

/**
 * @return list<array{stem:string,number:int,issue_key:string,path:string,data:array<string,mixed>}>
 */
function emulate_load_articles(string $dir): array
{
    $files = glob($dir . '/*.json') ?: [];
    sort($files, SORT_STRING);
    $out = [];
    foreach ($files as $path) {
        $stem = basename($path, '.json');
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Cannot read {$path}");
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON {$path}");
        }
        $issueKey = trim((string) ($data['issue_key'] ?? ''));
        $number = (int) ($data['number'] ?? 0);
        if ($issueKey === '' || $number <= 0) {
            continue;
        }
        if (!isset($data['body_ru']) || trim((string) $data['body_ru']) === '') {
            continue;
        }
        $out[] = [
            'stem' => $stem,
            'number' => $number,
            'issue_key' => $issueKey,
            'path' => $path,
            'data' => $data,
        ];
    }
    usort(
        $out,
        static fn (array $a, array $b): int => [$a['issue_key'], $a['number']] <=> [$b['issue_key'], $b['number']]
    );

    return $out;
}

function emulate_queue_manticore_reindex(): void
{
    $dir = zx_data_root() . '/manticore-reindex';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

/**
 * @return array<string, mixed>|null
 */
function emulate_find_issue(mysqli $db, int $pressId, string $issueKey): ?array
{
    $issueKey = trim($issueKey);
    $titles = match ($issueKey) {
        'special-edition' => ['Special Edition', 'special-edition', 'Special edition', '#Special Edition'],
        default => [$issueKey, ltrim($issueKey, '0') !== '' ? ltrim($issueKey, '0') : '0', sprintf('%02d', (int) $issueKey)],
    };
    foreach ($titles as $title) {
        $z = db_select(
            $db,
            'SELECT * FROM issue WHERE id_press=? AND title=? LIMIT 1',
            'is',
            $pressId,
            $title
        );
        $row = $z ? $z->fetch_assoc() : null;
        if (is_array($row)) {
            return $row;
        }
    }
    $slugCandidates = match ($issueKey) {
        'special-edition' => ['special-edition', 'special-edition'],
        default => [$issueKey, sprintf('%02d', (int) $issueKey)],
    };
    foreach ($slugCandidates as $slug) {
        $z = db_select(
            $db,
            'SELECT * FROM issue WHERE id_press=? AND (slug_ru=? OR slug_en=?) LIMIT 1',
            'iss',
            $pressId,
            $slug,
            $slug
        );
        $row = $z ? $z->fetch_assoc() : null;
        if (is_array($row)) {
            return $row;
        }
    }

    return null;
}

function emulate_sync_v2(mysqli $db, int $pressId, int $issueId, array $articleRow): void
{
    if (!ezines_v2_ready($db)) {
        return;
    }
    $v2Issue = ezines_v2_find_by_legacy_issue($db, $issueId);
    $v2IssueId = $v2Issue ? (int) $v2Issue['id'] : 0;
    if ($v2IssueId <= 0) {
        $zPress = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
        $pressRow = ($zPress && ($pr = mysqli_fetch_assoc($zPress))) ? $pr : null;
        if (is_array($pressRow)) {
            $ezineId = ezines_v2_sync_press($db, $pressRow, 'en');
            $zIssue = db_select($db, 'SELECT * FROM issue WHERE id=? LIMIT 1', 'i', $issueId);
            $issueRow = ($zIssue && ($ir = mysqli_fetch_assoc($zIssue))) ? $ir : null;
            if ($ezineId > 0 && is_array($issueRow)) {
                $v2IssueId = ezines_v2_sync_issue($db, $issueRow, $ezineId);
            }
        }
    }
    if ($v2IssueId <= 0) {
        return;
    }
    $aid = (int) ($articleRow['id'] ?? 0);
    $v2ArticleId = ezines_v2_sync_article($db, $articleRow, $v2IssueId, 'en');
    if ($v2ArticleId > 0) {
        ezines_v2_sync_article_authors($db, $aid, $v2ArticleId);
    }
}

$zPress = db_select($db, 'SELECT id, title FROM press WHERE id=? LIMIT 1', 'i', $pressId);
$press = $zPress ? $zPress->fetch_assoc() : null;
if (!$press) {
    fwrite(STDERR, "Press id={$pressId} not found\n");
    exit(1);
}

$all = emulate_load_articles($source);
$byIssue = [];
foreach ($all as $item) {
    if ($onlyIssues !== [] && !in_array($item['issue_key'], $onlyIssues, true)) {
        continue;
    }
    $byIssue[$item['issue_key']][] = $item;
}

fwrite(
    STDOUT,
    sprintf(
        "Emulate import: press=%d (%s) issues=%d articles=%d source=%s mode=%s\n",
        $pressId,
        (string) $press['title'],
        count($byIssue),
        array_sum(array_map('count', $byIssue)),
        $source,
        $dryRun ? 'dry-run' : 'APPLY'
    )
);

$updated = 0;
$missing = 0;
$skipped = 0;

foreach ($byIssue as $issueKey => $articles) {
    $issue = emulate_find_issue($db, $pressId, $issueKey);
    if ($issue === null) {
        fwrite(STDERR, "Issue key={$issueKey}: not found for press {$pressId}\n");
        $missing += count($articles);
        continue;
    }
    $issueId = (int) $issue['id'];
    fwrite(STDOUT, "\n== {$issueKey} issue_id={$issueId} articles=" . count($articles) . " ==\n");

    foreach ($articles as $item) {
        $data = $item['data'];
        $number = $item['number'];
        $titleRu = trim((string) ($data['title_ru'] ?? ''));
        $titleEn = trim((string) ($data['title_en'] ?? ''));
        // Drop TOC "Часть N —" / "Part N -" prefixes; capitalize; no trailing period.
        $normalizeTitle = static function (string $title): string {
            $t = trim($title);
            $t = preg_replace('/^(?:Часть|Part)\s+\d+\s*[—–\-:—:]\s*/iu', '', $t, 1) ?? $t;
            $t = trim($t);
            while ($t !== '' && str_ends_with($t, '.') && !str_ends_with($t, '...')) {
                $t = rtrim(substr($t, 0, -1));
            }
            if ($t === '') {
                return trim($title);
            }
            if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
                return mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($t, 1, null, 'UTF-8');
            }

            return strtoupper($t[0]) . substr($t, 1);
        };
        $titleRu = $normalizeTitle($titleRu);
        $titleEn = $normalizeTitle($titleEn);
        $metaRu = trim((string) ($data['meta_description_ru'] ?? ''));
        $metaEn = trim((string) ($data['meta_description_en'] ?? ''));
        // articles.meta_description_* is varchar(255)
        if (function_exists('mb_substr')) {
            $metaRu = mb_substr($metaRu, 0, 255, 'UTF-8');
            $metaEn = mb_substr($metaEn, 0, 255, 'UTF-8');
        } else {
            $metaRu = substr($metaRu, 0, 255);
            $metaEn = substr($metaEn, 0, 255);
        }
        $bodyRu = (string) ($data['body_ru'] ?? '');
        $bodyEn = (string) ($data['body_en'] ?? '');
        $slugRuPreset = trim((string) ($data['slug_ru'] ?? ''));

        if ($titleRu === '' || trim($bodyRu) === '') {
            fwrite(STDERR, "  SKIP #{$number} {$item['stem']}: empty title_ru/body_ru\n");
            $skipped++;
            continue;
        }

        // Emulate legacy numbers are spaced by 10: TOC #1→1, #2→11, #3→21, …
        $dbNumber = 1 + ($number - 1) * 10;
        $zEx = db_select(
            $db,
            'SELECT id, title, slug_ru, slug_en, text_type FROM articles WHERE id_issue=? AND number=? LIMIT 1',
            'ii',
            $issueId,
            $dbNumber
        );
        $ex = $zEx ? $zEx->fetch_assoc() : null;
        if (!$ex) {
            fwrite(STDERR, "  MISSING TOC#{$number} (db number={$dbNumber}) {$item['stem']}\n");
            $missing++;
            continue;
        }
        $aid = (int) $ex['id'];
        $textType = EMULATE_TEXT_TYPE;

        $seed = [
            'id' => $aid,
            'id_issue' => $issueId,
            'title' => $titleRu,
            'title_eng' => $titleEn,
            'meta_description_ru' => $metaRu,
            'meta_description_en' => $metaEn,
            'slug_ru' => $slugRuPreset !== '' ? $slugRuPreset : (string) ($ex['slug_ru'] ?? ''),
            'slug_en' => (string) ($ex['slug_en'] ?? ''),
        ];
        $slugs = ezn_admin_resolve_article_slugs(
            $db,
            $titleRu,
            $titleEn !== '' ? $titleEn : $titleRu,
            static fn (): string => $slugRuPreset !== '' ? $slugRuPreset : ezn_default_article_ru($seed),
            static fn (): string => ezn_default_article_en($seed),
            $aid,
            $issueId
        );
        if ($slugRuPreset !== '') {
            $slugs['slug_ru'] = $slugRuPreset;
        }

        fwrite(
            STDOUT,
            sprintf(
                "  update TOC#%d db#=%d id=%d %s | ru=%d en=%d\n",
                $number,
                $dbNumber,
                $aid,
                $titleRu,
                strlen($bodyRu),
                strlen($bodyEn)
            )
        );

        if ($dryRun) {
            $updated++;
            continue;
        }

        $ok = db_exec(
            $db,
            'UPDATE articles SET title=?, title_eng=?, text_type=?, text_type_ru=?, text_type_en=?, text_ru=?, text_en=?, '
            . 'meta_description_ru=?, meta_description_en=?, slug_ru=?, slug_en=?, id_press=? '
            . 'WHERE id=? AND id_issue=? LIMIT 1',
            'ssiiissssssiii',
            $titleRu,
            $titleEn,
            $textType,
            $textType,
            $textType,
            $bodyRu,
            $bodyEn,
            $metaRu,
            $metaEn,
            $slugs['slug_ru'],
            $slugs['slug_en'],
            $pressId,
            $aid,
            $issueId
        );
        if (!$ok) {
            fwrite(STDERR, "  FAIL update #{$number} id={$aid}\n");
            continue;
        }
        $updated++;

        activity_log($db, [
            'verb' => 'updated',
            'object_type' => 'article',
            'object_id' => $aid,
            'parent_type' => 'issue',
            'parent_id' => $issueId,
            'action' => 'article.updated',
            'event_scope' => ACTIVITY_SCOPE_CONTENT,
            'is_public' => 1,
            'title_ru' => $titleRu,
            'title_en' => $titleEn !== '' ? $titleEn : $titleRu,
            'url_ru' => '/article.php?id=' . $aid,
            'meta' => ['source' => EMULATE_SOURCE, 'stem' => $item['stem'], 'issue_key' => $issueKey],
        ]);

        $articleRow = [
            'id' => $aid,
            'title' => $titleRu,
            'title_eng' => $titleEn,
            'number' => $dbNumber,
            'temp' => 0,
            'views' => 0,
            'file' => '',
            'name' => '',
            'date' => (int) ($issue['date'] ?? 0),
            'text_ru' => $bodyRu,
            'text_en' => $bodyEn,
            'text_type' => $textType,
            'text_type_ru' => $textType,
            'text_type_en' => $textType,
            'meta_description_ru' => $metaRu,
            'meta_description_en' => $metaEn,
            'slug_ru' => $slugs['slug_ru'],
            'slug_en' => $slugs['slug_en'],
        ];
        emulate_sync_v2($db, $pressId, $issueId, $articleRow);
        article_search_text_refresh($db, $aid);
    }
}

if (!$dryRun && $updated > 0) {
    emulate_queue_manticore_reindex();
}

fwrite(
    STDOUT,
    sprintf("\nDONE updated=%d missing=%d skipped=%d%s\n", $updated, $missing, $skipped, $dryRun ? ' (dry-run)' : '')
);
exit($missing > 0 ? 2 : 0);
