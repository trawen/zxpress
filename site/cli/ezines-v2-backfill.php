#!/usr/bin/env php
<?php
/**
 * Idempotent backfill: legacy press/issue/articles → ezines_v2 tables.
 * Legacy tables are not modified.
 *
 * Usage:
 *   php site/cli/ezines-v2-backfill.php --dry-run
 *   php site/cli/ezines-v2-backfill.php --apply
 *   php site/cli/ezines-v2-backfill.php --apply --limit=20
 *   php site/cli/ezines-v2-backfill.php --apply --press-id=12
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$dryRun = true;
$limit = 0;
$onlyPressId = 0;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    } elseif (str_starts_with($arg, '--press-id=')) {
        $onlyPressId = max(0, (int) substr($arg, 11));
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--limit=N] [--press-id=N]\n");
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/ezines-v2-backfill.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-ezines-v2-backfill';

// Prefer the tree that contains this script (e.g. /src-site in docker), so local
 // edits are used even when the legacy public_html volume path also exists.
$siteRoot = dirname(__DIR__);
$legacyRoot = '/home/zxpress/web/zxpress.ru/public_html';
if (!is_file($siteRoot . '/includes/ezines_v2.php') && is_file($legacyRoot . '/includes/ezines_v2.php')) {
    $siteRoot = $legacyRoot;
}

require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/storage_paths.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

/** @var mysqli $db */
if (!isset($db) || !($db instanceof mysqli)) {
    fwrite(STDERR, "No mysqli \$db\n");
    exit(1);
}

if (!ezines_v2_ready($db)) {
    fwrite(STDERR, "ERROR: ezines missing — run db/migrate/20261101012530_ezines_v2.sql\n");
    exit(1);
}

$stats = [
    'press_seen' => 0,
    'ezines_upserted' => 0,
    'issues_upserted' => 0,
    'articles_upserted' => 0,
    'bodies_upserted' => 0,
    'md_bodies' => 0,
    'authors' => 0,
];

function ezn_v2_log(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

function ezn_v2_read_disk_body(int $articleId, bool $eng): string
{
    $kind = $eng ? 'articles_eng' : 'articles';
    if (!function_exists('zx_storage_path')) {
        return '';
    }
    $path = zx_storage_path($kind, (string) $articleId);
    if (!is_string($path) || $path === '' || !is_file($path)) {
        return '';
    }
    $raw = file_get_contents($path);

    return is_string($raw) ? $raw : '';
}

function ezn_v2_read_md(int $articleId): string
{
    if (!function_exists('zx_storage_path')) {
        return '';
    }
    try {
        $path = zx_storage_path('articles_md', $articleId . '.md');
        if (is_string($path) && $path !== '' && is_file($path)) {
            $raw = file_get_contents($path);

            return is_string($raw) ? $raw : '';
        }
    } catch (Throwable $e) {
        // Unknown storage key on older deploys — fall through to path candidates.
    }
    $candidates = [];
    if (defined('ZXPRESS_SITE_ROOT')) {
        $candidates[] = ZXPRESS_SITE_ROOT . '/../data/content-store/articles-md/' . $articleId . '.md';
    }
    $candidates[] = dirname(__DIR__, 2) . '/data/content-store/articles-md/' . $articleId . '.md';
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $raw = file_get_contents($path);

            return is_string($raw) ? $raw : '';
        }
    }

    return '';
}

ezn_v2_log(($dryRun ? '[dry-run] ' : '[apply] ') . 'ezines-v2 backfill start');

$sql = 'SELECT * FROM press';
$types = '';
$params = [];
if ($onlyPressId > 0) {
    $sql .= ' WHERE id=?';
    $types = 'i';
    $params[] = $onlyPressId;
}
$sql .= ' ORDER BY id ASC';
if ($limit > 0) {
    $sql .= ' LIMIT ' . (int) $limit;
}

$stmt = $db->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "prepare press failed: {$db->error}\n");
    exit(1);
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pressRes = $stmt->get_result();

while ($pressRes && ($press = $pressRes->fetch_assoc())) {
    $stats['press_seen']++;
    $legacyPressId = (int) $press['id'];
    ezn_v2_log("press #{$legacyPressId} " . (string) ($press['title'] ?? ''));

    if ($dryRun) {
        $zIss = db_select($db, 'SELECT COUNT(*) AS c FROM issue WHERE id_press=?', 'i', $legacyPressId);
        $issCnt = (int) (($zIss && ($r = mysqli_fetch_assoc($zIss))) ? ($r['c'] ?? 0) : 0);
        $zArt = db_select($db, 'SELECT COUNT(*) AS c FROM articles WHERE id_press=?', 'i', $legacyPressId);
        $artCnt = (int) (($zArt && ($r = mysqli_fetch_assoc($zArt))) ? ($r['c'] ?? 0) : 0);
        ezn_v2_log("  would sync issues={$issCnt} articles={$artCnt}");
        continue;
    }

    $ezineId = ezines_v2_sync_press($db, $press, 'ru');
    if ($ezineId <= 0) {
        ezn_v2_log("  ERROR sync press failed");
        continue;
    }
    $stats['ezines_upserted']++;

    $issStmt = $db->prepare('SELECT * FROM issue WHERE id_press=? ORDER BY sort_order ASC, id ASC');
    if (!$issStmt) {
        continue;
    }
    $issStmt->bind_param('i', $legacyPressId);
    $issStmt->execute();
    $issRes = $issStmt->get_result();
    while ($issRes && ($issue = $issRes->fetch_assoc())) {
        $issueId = ezines_v2_sync_issue($db, $issue, $ezineId);
        if ($issueId <= 0) {
            ezn_v2_log('  ERROR sync issue #' . (int) $issue['id']);
            continue;
        }
        $stats['issues_upserted']++;
        $legacyIssueId = (int) $issue['id'];

        $artStmt = $db->prepare('SELECT * FROM articles WHERE id_issue=? ORDER BY number ASC, id ASC');
        if (!$artStmt) {
            continue;
        }
        $artStmt->bind_param('i', $legacyIssueId);
        $artStmt->execute();
        $artRes = $artStmt->get_result();
        while ($artRes && ($article = $artRes->fetch_assoc())) {
            $legacyArticleId = (int) $article['id'];
            $textRu = (string) ($article['text_ru'] ?? '');
            $textEn = (string) ($article['text_en'] ?? '');
            if ($textRu === '') {
                $textRu = ezn_v2_read_disk_body($legacyArticleId, false);
                if ($textRu !== '') {
                    $article['text_ru'] = $textRu;
                }
            }
            if ($textEn === '') {
                $textEn = ezn_v2_read_disk_body($legacyArticleId, true);
                if ($textEn !== '') {
                    $article['text_en'] = $textEn;
                }
            }

            $articleId = ezines_v2_sync_article($db, $article, $issueId, 'ru');
            if ($articleId <= 0) {
                ezn_v2_log("  ERROR sync article #{$legacyArticleId}");
                continue;
            }
            $stats['articles_upserted']++;
            if ($textRu !== '' || $textEn !== '') {
                $stats['bodies_upserted']++;
            }

            $md = ezn_v2_read_md($legacyArticleId);
            if ($md !== '') {
                ezines_v2_upsert_article_body($db, $articleId, 'ru', 'markdown', $md, false);
                $stats['md_bodies']++;
            }

            ezines_v2_sync_article_authors($db, $legacyArticleId, $articleId);
            $stats['authors']++;
        }
        $artStmt->close();
    }
    $issStmt->close();
}
$stmt->close();

ezn_v2_log('done ' . json_encode($stats, JSON_UNESCAPED_UNICODE));
exit(0);
