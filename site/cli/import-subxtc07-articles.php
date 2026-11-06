#!/usr/bin/env php
<?php
/**
 * Import Subliminal Extacy #07 articles (EN+RU markdown i18n) into legacy articles
 * for an already-created issue, dual-write to ezines_v2, attach tags.
 *
 * Defaults match production admin URL:
 *   admin_articles_new.php?id=213&issue=2665
 *
 * Usage:
 *   php site/cli/import-subxtc07-articles.php --dry-run
 *   php site/cli/import-subxtc07-articles.php --apply
 *   php site/cli/import-subxtc07-articles.php --apply --force
 *   php site/cli/import-subxtc07-articles.php --apply --press-id=213 --issue-id=2665
 *   php site/cli/import-subxtc07-articles.php --apply --source=/path/to/i18n
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const SE07_PRESS_ID = 213;
const SE07_ISSUE_ID = 2665;
const SE07_SOURCE = 'migration:subxtc07_articles';
const SE07_TEXT_TYPE = 3; // markdown

$dryRun = true;
$force = false;
$withTags = false; // AI tags_ru are noisy; opt-in only
$pressId = SE07_PRESS_ID;
$issueId = SE07_ISSUE_ID;
$source = '';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--force') {
        $force = true;
    } elseif ($arg === '--with-tags') {
        $withTags = true;
    } elseif (str_starts_with($arg, '--press-id=')) {
        $pressId = max(0, (int) substr($arg, 11));
    } elseif (str_starts_with($arg, '--issue-id=')) {
        $issueId = max(0, (int) substr($arg, 11));
    } elseif (str_starts_with($arg, '--source=')) {
        $source = rtrim(substr($arg, 9), '/');
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: {$argv[0]} [--dry-run|--apply] [--force] [--with-tags] [--press-id=213] [--issue-id=2665] [--source=DIR]\n");
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/import-subxtc07-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-subxtc07';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/article_text_render.php';
require_once $siteRoot . '/includes/admin_helpers.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

if ($source === '') {
    $candidates = [
        $siteRoot . '/cli/imports/subxtc07_i18n',
        dirname($siteRoot) . '/db/migration/subxtc07_articles/i18n',
        dirname($siteRoot) . '/local/work/SUBXTC07_texts/i18n',
        // legacy path (gitignored as data/) — local-only fallback
        $siteRoot . '/cli/data/subxtc07_i18n',
    ];
    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            $source = $cand;
            break;
        }
    }
}
if ($source === '' || !is_dir($source)) {
    fwrite(STDERR, "Need --source=DIR with *.json i18n files\n");
    exit(1);
}

/**
 * TOC number from stem: 01_01_foo → 1, 10_11_foo → 11, 15_big_belly → 16.
 */
function se07_toc_number(string $stem): int
{
    if (preg_match('/^(\d+)_(\d+)_/', $stem, $m)) {
        return (int) $m[2];
    }
    if (preg_match('/^\d+_big_belly/', $stem)) {
        return 16;
    }
    if (preg_match('/^(\d+)_/', $stem, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * @return list<array{stem:string,number:int,path:string,data:array<string,mixed>}>
 */
function se07_load_articles(string $dir): array
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
        $number = se07_toc_number($stem);
        if ($number <= 0) {
            throw new RuntimeException("Cannot parse TOC number from {$stem}");
        }
        $out[] = [
            'stem' => $stem,
            'number' => $number,
            'path' => $path,
            'data' => $data,
        ];
    }
    usort($out, static fn (array $a, array $b): int => $a['number'] <=> $b['number']);

    return $out;
}

function se07_queue_manticore_reindex(): void
{
    $dir = zx_data_root() . '/manticore-reindex';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function se07_tag_name_db(string $tagName): string
{
    $tagName = trim($tagName);
    if ($tagName === '') {
        return '';
    }
    // Live schema: tags.tag_name varchar(24).
    if (function_exists('mb_substr')) {
        return mb_substr($tagName, 0, 24, 'UTF-8');
    }

    return substr($tagName, 0, 24);
}

function se07_find_or_create_tag(mysqli $db, string $tagName, bool $dryRun): int
{
    $tagName = se07_tag_name_db($tagName);
    if ($tagName === '') {
        return 0;
    }
    $z = db_select($db, 'SELECT id FROM tags WHERE tag_name=? LIMIT 1', 's', $tagName);
    $row = $z ? $z->fetch_assoc() : null;
    if ($row) {
        return (int) $row['id'];
    }
    if ($dryRun) {
        return -1;
    }
    $alias = per_slugify($tagName);
    if ($alias === '') {
        $alias = $tagName;
    }
    if (function_exists('mb_substr')) {
        $alias = mb_substr($alias, 0, 32, 'UTF-8');
    } else {
        $alias = substr($alias, 0, 32);
    }
    $show = 0;
    db_exec(
        $db,
        'INSERT INTO tags (tag_name, tag_alias, `show`) VALUES (?,?,?)',
        'ssi',
        $tagName,
        $alias,
        $show
    );
    $id = (int) mysqli_insert_id($db);
    if ($id > 0) {
        activity_log($db, [
            'verb' => 'created',
            'object_type' => 'tag',
            'object_id' => $id,
            'action' => 'tag.created',
            'event_scope' => ACTIVITY_SCOPE_METADATA,
            'is_public' => 0,
            'title_ru' => $tagName,
            'title_en' => $tagName,
            'after' => ['tag_name' => $tagName, 'tag_alias' => $alias, 'source' => SE07_SOURCE],
        ]);
    }

    return $id;
}

function se07_link_tag(mysqli $db, int $articleId, int $tagId, bool $dryRun): bool
{
    if ($articleId <= 0 || $tagId <= 0) {
        return false;
    }
    $z = db_select(
        $db,
        'SELECT id FROM tags_articles WHERE id_article=? AND id_tag=? LIMIT 1',
        'ii',
        $articleId,
        $tagId
    );
    if ($z && $z->fetch_assoc()) {
        return false;
    }
    if ($dryRun) {
        return true;
    }
    db_exec(
        $db,
        'INSERT INTO tags_articles (id_tag, id_article, tag_size, tag_type) VALUES (?,?,0,0)',
        'ii',
        $tagId,
        $articleId
    );
    activity_log($db, [
        'verb' => 'updated',
        'object_type' => 'article',
        'object_id' => $articleId,
        'action' => 'article.tag.added',
        'event_scope' => ACTIVITY_SCOPE_METADATA,
        'is_public' => 0,
        'after' => ['tag_id' => $tagId, 'source' => SE07_SOURCE],
    ]);

    return true;
}

function se07_sync_v2(mysqli $db, int $pressId, int $issueId, array $articleRow, string $title, string $titleEng): void
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
    unset($title, $titleEng);
}

$zPress = db_select($db, 'SELECT id, title, slug_ru FROM press WHERE id=? LIMIT 1', 'i', $pressId);
$press = $zPress ? $zPress->fetch_assoc() : null;
if (!$press) {
    fwrite(STDERR, "Press id={$pressId} not found\n");
    exit(1);
}

$zIssue = db_select(
    $db,
    'SELECT id, id_press, title, slug_ru, slug_en, date FROM issue WHERE id=? LIMIT 1',
    'i',
    $issueId
);
$issue = $zIssue ? $zIssue->fetch_assoc() : null;
if (!$issue) {
    fwrite(STDERR, "Issue id={$issueId} not found\n");
    exit(1);
}
if ((int) $issue['id_press'] !== $pressId) {
    fwrite(
        STDERR,
        "Issue {$issueId} belongs to press " . (int) $issue['id_press']
        . ", expected press {$pressId} ({$press['title']}). Abort.\n"
    );
    exit(1);
}

$articles = se07_load_articles($source);
fwrite(
    STDOUT,
    sprintf(
        "SE#07 import: press=%d (%s) issue=%d (title=%s) articles=%d source=%s mode=%s%s\n",
        $pressId,
        (string) $press['title'],
        $issueId,
        (string) $issue['title'],
        count($articles),
        $source,
        $dryRun ? 'dry-run' : 'APPLY',
        $force ? ' force' : ''
    )
);

$created = 0;
$updated = 0;
$skipped = 0;
$tagLinks = 0;
$now = time();
$issueDate = (int) ($issue['date'] ?? 0);
$articleDate = $issueDate > 0 ? $issueDate : $now;

foreach ($articles as $item) {
    $data = $item['data'];
    $number = $item['number'];
    $titleRu = trim((string) ($data['title_ru'] ?? ''));
    $titleEn = trim((string) ($data['title_en'] ?? ''));
    $metaRu = trim((string) ($data['meta_description_ru'] ?? ''));
    $metaEn = trim((string) ($data['meta_description_en'] ?? ''));
    $bodyRu = (string) ($data['body_ru'] ?? '');
    $bodyEn = (string) ($data['body_en'] ?? '');
    /** @var list<string> $tags */
    $tags = [];
    if ($withTags && !empty($data['tags_ru']) && is_array($data['tags_ru'])) {
        foreach ($data['tags_ru'] as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $tags[] = $t;
            }
        }
    }

    if ($titleRu === '' || trim($bodyRu) === '') {
        fwrite(STDERR, "  SKIP #{$number} {$item['stem']}: empty title_ru/body_ru\n");
        $skipped++;
        continue;
    }

    $existingId = 0;
    $zEx = db_select(
        $db,
        'SELECT id, title, slug_ru FROM articles WHERE id_issue=? AND number=? LIMIT 1',
        'ii',
        $issueId,
        $number
    );
    $ex = $zEx ? $zEx->fetch_assoc() : null;
    if ($ex) {
        $existingId = (int) $ex['id'];
    }

    $seed = [
        'id' => $existingId,
        'id_issue' => $issueId,
        'title' => $titleRu,
        'title_eng' => $titleEn,
        'meta_description_ru' => $metaRu,
        'meta_description_en' => $metaEn,
    ];
    $slugs = ezn_admin_resolve_article_slugs(
        $db,
        $titleRu,
        $titleEn !== '' ? $titleEn : $titleRu,
        static fn (): string => ezn_default_article_ru($seed),
        static fn (): string => ezn_default_article_en($seed),
        $existingId,
        $issueId
    );

    $action = 'create';
    if ($existingId > 0) {
        if (!$force) {
            fwrite(STDOUT, "  skip #{$number} id={$existingId} (exists) {$titleRu}\n");
            $skipped++;
            continue;
        }
        $action = 'update';
    }

    fwrite(
        STDOUT,
        sprintf(
            "  %s #%d %s | ru=%d en=%d tags=%d slugs=%s/%s\n",
            $action,
            $number,
            $titleRu,
            strlen($bodyRu),
            strlen($bodyEn),
            count($tags),
            $slugs['slug_ru'],
            $slugs['slug_en']
        )
    );

    if ($dryRun) {
        foreach ($tags as $tagName) {
            se07_find_or_create_tag($db, $tagName, true);
            $tagLinks++;
        }
        if ($action === 'create') {
            $created++;
        } else {
            $updated++;
        }
        continue;
    }

    $textType = SE07_TEXT_TYPE;
    $temp = 0;
    $aid = $existingId;

    if ($action === 'create') {
        $ok = db_exec(
            $db,
            'INSERT INTO articles '
            . '(id_issue, title, temp, date, views, number, title_eng, file, name, dt, id_press, text_type, text_type_ru, text_type_en, text_ru, text_en, '
            . 'meta_description_ru, meta_description_en, slug_ru, slug_en) '
            . 'VALUES (?,?,?,?,0,?,?,?,?,0,?,?,?,?,?,?,?,?,?,?)',
            'isiiisssiiiissssss',
            $issueId,
            $titleRu,
            $temp,
            $articleDate,
            $number,
            $titleEn,
            '',
            '',
            $pressId,
            $textType,
            $textType,
            $textType,
            $bodyRu,
            $bodyEn,
            $metaRu,
            $metaEn,
            $slugs['slug_ru'],
            $slugs['slug_en']
        );
        if (!$ok) {
            fwrite(STDERR, "  FAIL insert #{$number}\n");
            continue;
        }
        $aid = (int) mysqli_insert_id($db);
        $created++;
        activity_log($db, [
            'verb' => 'created',
            'object_type' => 'article',
            'object_id' => $aid,
            'parent_type' => 'issue',
            'parent_id' => $issueId,
            'action' => 'article.created',
            'event_scope' => ACTIVITY_SCOPE_CONTENT,
            'is_public' => 1,
            'title_ru' => $titleRu,
            'title_en' => $titleEn !== '' ? $titleEn : $titleRu,
            'url_ru' => '/article.php?id=' . $aid,
            'meta' => [
                'press_id' => $pressId,
                'source' => SE07_SOURCE,
                'stem' => $item['stem'],
                'text_type_ru' => $textType,
                'text_type_en' => $textType,
            ],
        ]);
    } else {
        $ok = db_exec(
            $db,
            'UPDATE articles SET title=?, title_eng=?, number=?, temp=?, text_type=?, text_type_ru=?, text_type_en=?, text_ru=?, text_en=?, '
            . 'meta_description_ru=?, meta_description_en=?, slug_ru=?, slug_en=?, id_press=? '
            . 'WHERE id=? AND id_issue=? LIMIT 1',
            'ssiiiiissssssiii',
            $titleRu,
            $titleEn,
            $number,
            $temp,
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
            'meta' => ['source' => SE07_SOURCE, 'stem' => $item['stem']],
        ]);
    }

    $articleRow = [
        'id' => $aid,
        'title' => $titleRu,
        'title_eng' => $titleEn,
        'number' => $number,
        'temp' => $temp,
        'views' => 0,
        'file' => '',
        'name' => '',
        'date' => $articleDate,
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
    se07_sync_v2($db, $pressId, $issueId, $articleRow, $titleRu, $titleEn);

    foreach ($tags as $tagName) {
        $tagId = se07_find_or_create_tag($db, $tagName, false);
        if (se07_link_tag($db, $aid, $tagId, false)) {
            $tagLinks++;
        }
    }
}

if (!$dryRun && ($created > 0 || $updated > 0)) {
    se07_queue_manticore_reindex();
}

fwrite(
    STDOUT,
    sprintf(
        "DONE created=%d updated=%d skipped=%d tag_links=%d%s\n",
        $created,
        $updated,
        $skipped,
        $tagLinks,
        $dryRun ? ' (dry-run)' : ''
    )
);
exit(0);
