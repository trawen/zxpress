#!/usr/bin/env php
<?php
/**
 * Create Subliminal Extacy #04–#06 issues (if missing) and import EN+RU markdown articles.
 *
 * Defaults: press_id=213 (Subliminal Extacy).
 *
 * Usage:
 *   php site/cli/import-subxtc04-06-articles.php --dry-run
 *   php site/cli/import-subxtc04-06-articles.php --apply
 *   php site/cli/import-subxtc04-06-articles.php --apply --force
 *   php site/cli/import-subxtc04-06-articles.php --apply --with-tags
 *   php site/cli/import-subxtc04-06-articles.php --apply --only=4,5
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const SE456_PRESS_ID = 213;
const SE456_SOURCE = 'migration:subxtc04_06_articles';
const SE456_TEXT_TYPE = 3; // markdown

/** @var array<int, array{title:string, sort_order:int, date:int, slug:string}> */
const SE456_ISSUES = [
    // Spectrum Computing / Demozoo release dates (UTC midnight).
    4 => ['title' => '04', 'sort_order' => 50, 'date' => 1615680000, 'slug' => '04'], // 2021-03-14
    5 => ['title' => '05', 'sort_order' => 60, 'date' => 1701475200, 'slug' => '05'], // 2023-12-02
    6 => ['title' => '06', 'sort_order' => 70, 'date' => 1710547200, 'slug' => '06'], // 2024-03-16
];

$dryRun = true;
$force = false;
$withTags = false;
$pressId = SE456_PRESS_ID;
$only = [4, 5, 6];

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
    } elseif (str_starts_with($arg, '--only=')) {
        $only = [];
        foreach (explode(',', substr($arg, 7)) as $part) {
            $n = (int) trim($part);
            if (isset(SE456_ISSUES[$n])) {
                $only[] = $n;
            }
        }
        $only = array_values(array_unique($only));
        if ($only === []) {
            fwrite(STDERR, "--only must list 4, 5 and/or 6\n");
            exit(1);
        }
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(
            STDOUT,
            "Usage: {$argv[0]} [--dry-run|--apply] [--force] [--with-tags] [--press-id=213] [--only=4,5,6]\n"
        );
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/import-subxtc04-06-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-subxtc04-06';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/article_text_render.php';
require_once $siteRoot . '/includes/admin_helpers.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

/**
 * @return string absolute dir or ''
 */
function se456_resolve_source(int $issueNum, string $siteRoot): string
{
    $nn = sprintf('%02d', $issueNum);
    $candidates = [
        $siteRoot . '/cli/imports/subxtc' . $nn . '_i18n',
        dirname($siteRoot) . '/db/migration/subxtc04_06_articles/i18n/' . $nn,
        dirname($siteRoot) . '/local/work/SUBXTC' . $nn . '_texts/i18n',
    ];
    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            return $cand;
        }
    }

    return '';
}

/**
 * TOC number from stem: 01_foo → 1, 14_reader → 14.
 */
function se456_toc_number(string $stem): int
{
    if (preg_match('/^(\d+)_/', $stem, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * @return list<array{stem:string,number:int,path:string,data:array<string,mixed>}>
 */
function se456_load_articles(string $dir): array
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
        $number = se456_toc_number($stem);
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

function se456_queue_manticore_reindex(): void
{
    $dir = zx_data_root() . '/manticore-reindex';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function se456_tag_name_db(string $tagName): string
{
    $tagName = trim($tagName);
    if ($tagName === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($tagName, 0, 24, 'UTF-8');
    }

    return substr($tagName, 0, 24);
}

function se456_find_or_create_tag(mysqli $db, string $tagName, bool $dryRun): int
{
    $tagName = se456_tag_name_db($tagName);
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
            'after' => ['tag_name' => $tagName, 'tag_alias' => $alias, 'source' => SE456_SOURCE],
        ]);
    }

    return $id;
}

function se456_link_tag(mysqli $db, int $articleId, int $tagId, bool $dryRun): bool
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
        'after' => ['tag_id' => $tagId, 'source' => SE456_SOURCE],
    ]);

    return true;
}

function se456_sync_v2(mysqli $db, int $pressId, int $issueId, array $articleRow): void
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

/**
 * Find existing issue by press + title (00/04/07 style).
 *
 * @return array<string,mixed>|null
 */
function se456_find_issue(mysqli $db, int $pressId, string $title): ?array
{
    $z = db_select(
        $db,
        'SELECT * FROM issue WHERE id_press=? AND title=? LIMIT 1',
        'is',
        $pressId,
        $title
    );
    $row = $z ? $z->fetch_assoc() : null;

    return is_array($row) ? $row : null;
}

/**
 * Ensure SE#07 sits after #06 in sort_order.
 */
function se456_bump_issue07_sort(mysqli $db, int $pressId, bool $dryRun): void
{
    $issue07 = se456_find_issue($db, $pressId, '07');
    if (!$issue07) {
        return;
    }
    $cur = (int) ($issue07['sort_order'] ?? 0);
    if ($cur >= 80) {
        return;
    }
    fwrite(STDOUT, "  bump issue #07 id={$issue07['id']} sort_order {$cur} → 80\n");
    if ($dryRun) {
        return;
    }
    db_exec(
        $db,
        'UPDATE issue SET sort_order=80 WHERE id=? AND id_press=? LIMIT 1',
        'ii',
        (int) $issue07['id'],
        $pressId
    );
}

/**
 * Create or reuse issue; returns issue id (0 in dry-run create).
 *
 * @param array{title:string, sort_order:int, date:int, slug:string} $spec
 */
function se456_ensure_issue(mysqli $db, int $pressId, int $issueNum, array $spec, bool $dryRun): int
{
    $existing = se456_find_issue($db, $pressId, $spec['title']);
    if ($existing) {
        $id = (int) $existing['id'];
        fwrite(
            STDOUT,
            sprintf(
                "  issue #%s exists id=%d sort=%s date=%s\n",
                $spec['title'],
                $id,
                (string) ($existing['sort_order'] ?? ''),
                (string) ($existing['date'] ?? '')
            )
        );
        // Keep title/slug; refresh date/sort if empty/wrong and not dry-run.
        if (!$dryRun) {
            $needDate = (int) ($existing['date'] ?? 0) <= 0 || (int) $existing['date'] !== $spec['date'];
            $needSort = (int) ($existing['sort_order'] ?? 0) !== $spec['sort_order'];
            if ($needDate || $needSort) {
                db_exec(
                    $db,
                    'UPDATE issue SET date=?, sort_order=? WHERE id=? LIMIT 1',
                    'iii',
                    $spec['date'],
                    $spec['sort_order'],
                    $id
                );
                fwrite(STDOUT, "    refreshed date/sort_order for id={$id}\n");
            }
        }

        return $id;
    }

    fwrite(
        STDOUT,
        sprintf(
            "  create issue #%s sort=%d date=%d (%s)%s\n",
            $spec['title'],
            $spec['sort_order'],
            $spec['date'],
            gmdate('Y-m-d', $spec['date']),
            $dryRun ? ' [dry-run]' : ''
        )
    );
    if ($dryRun) {
        return 0;
    }

    $missing = 0;
    $ok = db_exec(
        $db,
        'INSERT INTO issue (`id_press`, `title`, `date`, `sort_order`, `views`, `missing`) VALUES (?,?,?,?,0,?)',
        'isiii',
        $pressId,
        $spec['title'],
        $spec['date'],
        $spec['sort_order'],
        $missing
    );
    if (!$ok) {
        fwrite(STDERR, "  FAIL insert issue #{$spec['title']}\n");

        return 0;
    }
    $issueId = (int) mysqli_insert_id($db);
    $issueRow = [
        'id' => $issueId,
        'id_press' => $pressId,
        'title' => $spec['title'],
        'missing' => $missing,
        'date' => $spec['date'],
        'sort_order' => $spec['sort_order'],
    ];
    $slugs = per_admin_resolve_slugs(
        $db,
        'issue',
        $spec['slug'],
        $spec['slug'],
        static fn (): string => ezn_default_issue_ru($issueRow),
        static fn (): string => ezn_default_issue_en($issueRow),
        $issueId,
        'id_press',
        $pressId
    );
    db_exec(
        $db,
        'UPDATE issue SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1',
        'ssi',
        $slugs['slug_ru'],
        $slugs['slug_en'],
        $issueId
    );
    $issueRow['slug_ru'] = $slugs['slug_ru'];
    $issueRow['slug_en'] = $slugs['slug_en'];

    activity_log($db, [
        'verb' => 'created',
        'object_type' => 'issue',
        'object_id' => $issueId,
        'parent_type' => 'press',
        'parent_id' => $pressId,
        'action' => 'issue.created',
        'event_scope' => ACTIVITY_SCOPE_CONTENT,
        'is_public' => 1,
        'title_ru' => 'Subliminal Extacy #' . $spec['title'],
        'title_en' => 'Subliminal Extacy #' . $spec['title'],
        'url_ru' => '/issue.php?id=' . $issueId,
        'meta' => ['source' => SE456_SOURCE, 'issue_num' => $issueNum],
    ]);

    if (ezines_v2_ready($db)) {
        $zPress = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
        $pressRow = ($zPress && ($pr = mysqli_fetch_assoc($zPress))) ? $pr : null;
        if (is_array($pressRow)) {
            $ezineId = ezines_v2_sync_press($db, $pressRow, 'en');
            if ($ezineId > 0) {
                ezines_v2_sync_issue($db, $issueRow, $ezineId);
            }
        }
    }

    return $issueId;
}

/**
 * @param list<array{stem:string,number:int,path:string,data:array<string,mixed>}> $articles
 * @return array{created:int,updated:int,skipped:int,tag_links:int}
 */
function se456_import_articles(
    mysqli $db,
    int $pressId,
    int $issueId,
    int $issueNum,
    array $articles,
    int $articleDate,
    bool $dryRun,
    bool $force,
    bool $withTags
): array {
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $tagLinks = 0;

    if ($issueId <= 0 && !$dryRun) {
        fwrite(STDERR, "  SE#{$issueNum}: no issue id, skip articles\n");

        return compact('created', 'updated', 'skipped', 'tagLinks');
    }

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
            fwrite(STDERR, "  SKIP SE#{$issueNum} #{$number} {$item['stem']}: empty title_ru/body_ru\n");
            $skipped++;
            continue;
        }

        $existingId = 0;
        if ($issueId > 0) {
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
            max(0, $issueId)
        );

        $action = 'create';
        if ($existingId > 0) {
            if (!$force) {
                fwrite(STDOUT, "  skip SE#{$issueNum} #{$number} id={$existingId} (exists) {$titleRu}\n");
                $skipped++;
                continue;
            }
            $action = 'update';
        }

        fwrite(
            STDOUT,
            sprintf(
                "  %s SE#%d #%d %s | ru=%d en=%d tags=%d slugs=%s/%s\n",
                $action,
                $issueNum,
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
                se456_find_or_create_tag($db, $tagName, true);
                $tagLinks++;
            }
            if ($action === 'create') {
                $created++;
            } else {
                $updated++;
            }
            continue;
        }

        $textType = SE456_TEXT_TYPE;
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
                fwrite(STDERR, "  FAIL insert SE#{$issueNum} #{$number}\n");
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
                    'source' => SE456_SOURCE,
                    'stem' => $item['stem'],
                    'issue_num' => $issueNum,
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
                fwrite(STDERR, "  FAIL update SE#{$issueNum} #{$number} id={$aid}\n");
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
                'meta' => ['source' => SE456_SOURCE, 'stem' => $item['stem'], 'issue_num' => $issueNum],
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
        se456_sync_v2($db, $pressId, $issueId, $articleRow);

        foreach ($tags as $tagName) {
            $tagId = se456_find_or_create_tag($db, $tagName, false);
            if (se456_link_tag($db, $aid, $tagId, false)) {
                $tagLinks++;
            }
        }
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'tag_links' => $tagLinks,
    ];
}

$zPress = db_select($db, 'SELECT id, title, slug_ru, numbers FROM press WHERE id=? LIMIT 1', 'i', $pressId);
$press = $zPress ? $zPress->fetch_assoc() : null;
if (!$press) {
    fwrite(STDERR, "Press id={$pressId} not found\n");
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "SE#04–06 import: press=%d (%s) only=%s mode=%s%s%s\n",
        $pressId,
        (string) $press['title'],
        implode(',', $only),
        $dryRun ? 'dry-run' : 'APPLY',
        $force ? ' force' : '',
        $withTags ? ' with-tags' : ''
    )
);

se456_bump_issue07_sort($db, $pressId, $dryRun);

$totalCreated = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$totalTags = 0;
$issueIds = [];

foreach ($only as $issueNum) {
    $spec = SE456_ISSUES[$issueNum];
    $source = se456_resolve_source($issueNum, $siteRoot);
    if ($source === '') {
        fwrite(STDERR, "SE#{$issueNum}: no i18n source dir\n");
        exit(1);
    }
    $articles = se456_load_articles($source);
    fwrite(
        STDOUT,
        sprintf(
            "\n== SE#%02d articles=%d source=%s ==\n",
            $issueNum,
            count($articles),
            $source
        )
    );

    $issueId = se456_ensure_issue($db, $pressId, $issueNum, $spec, $dryRun);
    $issueIds[$issueNum] = $issueId;

    $stats = se456_import_articles(
        $db,
        $pressId,
        $issueId,
        $issueNum,
        $articles,
        $spec['date'],
        $dryRun,
        $force,
        $withTags
    );
    $totalCreated += $stats['created'];
    $totalUpdated += $stats['updated'];
    $totalSkipped += $stats['skipped'];
    $totalTags += $stats['tag_links'];
}

if (!$dryRun) {
    // Keep press.numbers in sync with actual issue count.
    $zCnt = db_select($db, 'SELECT COUNT(*) AS c FROM issue WHERE id_press=?', 'i', $pressId);
    $cntRow = $zCnt ? $zCnt->fetch_assoc() : null;
    $cnt = (int) ($cntRow['c'] ?? 0);
    if ($cnt > 0 && (int) ($press['numbers'] ?? 0) !== $cnt) {
        db_exec($db, 'UPDATE press SET numbers=? WHERE id=? LIMIT 1', 'ii', $cnt, $pressId);
        fwrite(STDOUT, "\npress.numbers → {$cnt}\n");
    }
    if ($totalCreated > 0 || $totalUpdated > 0) {
        se456_queue_manticore_reindex();
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nDONE created=%d updated=%d skipped=%d tag_links=%d issue_ids=%s%s\n",
        $totalCreated,
        $totalUpdated,
        $totalSkipped,
        $totalTags,
        json_encode($issueIds, JSON_UNESCAPED_UNICODE),
        $dryRun ? ' (dry-run)' : ''
    )
);
exit(0);
