#!/usr/bin/env php
<?php
/**
 * Import Sinclair Classic articles (EN+RU markdown i18n) into existing issues.
 *
 * Defaults: press_id=313 (Sinclair Classic). Issues 01–10 + Summer Special already exist.
 *
 * Usage:
 *   php site/cli/import-sclass-articles.php --dry-run
 *   php site/cli/import-sclass-articles.php --apply
 *   php site/cli/import-sclass-articles.php --apply --force
 *   php site/cli/import-sclass-articles.php --apply --with-tags
 *   php site/cli/import-sclass-articles.php --apply --only=01,02,summer
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const SCLASS_PRESS_ID = 313;
const SCLASS_SOURCE = 'migration:sclass_articles';
const SCLASS_TEXT_TYPE = 3; // markdown

/**
 * Ordered issue keys (must stay strings — PHP casts bare '10' array keys to int).
 *
 * @var list<string>
 */
const SCLASS_ISSUE_KEYS = [
    '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', 'summer',
];

/**
 * Issue keys → DB title (issue.title).
 *
 * @var array<string, string>
 */
const SCLASS_ISSUE_TITLES = [
    '01' => '01',
    '02' => '02',
    '03' => '03',
    '04' => '04',
    '05' => '05',
    '06' => '06',
    '07' => '07',
    '08' => '08',
    '09' => '09',
    '10' => '10',
    'summer' => 'Summer Special',
];

$dryRun = true;
$force = false;
$withTags = false;
$pressId = SCLASS_PRESS_ID;
$only = SCLASS_ISSUE_KEYS;

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
            $k = trim($part);
            if ($k === 'Summer Special' || strtolower($k) === 'summer') {
                $k = 'summer';
            } elseif (preg_match('/^\d+$/', $k)) {
                $k = str_pad($k, 2, '0', STR_PAD_LEFT);
            }
            if (isset(SCLASS_ISSUE_TITLES[$k])) {
                $only[] = $k;
            }
        }
        $only = array_values(array_unique($only));
        if ($only === []) {
            fwrite(STDERR, "--only must list 01..10 and/or summer\n");
            exit(1);
        }
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(
            STDOUT,
            "Usage: {$argv[0]} [--dry-run|--apply] [--force] [--with-tags] [--press-id=313] [--only=01,02,summer]\n"
        );
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/import-sclass-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-sclass';

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
function sclass_resolve_source(string $issueKey, string $siteRoot): string
{
    $candidates = [
        $siteRoot . '/cli/imports/sclass' . $issueKey . '_i18n',
        dirname($siteRoot) . '/db/migration/sclass_articles/i18n/' . $issueKey,
        dirname($siteRoot) . '/local/work/sinclair_classic/texts/' . $issueKey . '/i18n',
    ];
    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            return $cand;
        }
    }

    return '';
}

function sclass_toc_number(string $stem): int
{
    if (preg_match('/^(\d+)_/', $stem, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * @return list<array{stem:string,number:int,path:string,data:array<string,mixed>}>
 */
function sclass_load_articles(string $dir): array
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
        $number = sclass_toc_number($stem);
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

function sclass_queue_manticore_reindex(): void
{
    $dir = zx_data_root() . '/manticore-reindex';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function sclass_tag_name_db(string $tagName): string
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

function sclass_find_or_create_tag(mysqli $db, string $tagName, bool $dryRun): int
{
    $tagName = sclass_tag_name_db($tagName);
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
            'after' => ['tag_name' => $tagName, 'tag_alias' => $alias, 'source' => SCLASS_SOURCE],
        ]);
    }

    return $id;
}

function sclass_link_tag(mysqli $db, int $articleId, int $tagId, bool $dryRun): bool
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
        'after' => ['tag_id' => $tagId, 'source' => SCLASS_SOURCE],
    ]);

    return true;
}

function sclass_sync_v2(mysqli $db, int $pressId, int $issueId, array $articleRow): void
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
 * @return array<string,mixed>|null
 */
function sclass_find_issue(mysqli $db, int $pressId, string $title): ?array
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
 * @param list<array{stem:string,number:int,path:string,data:array<string,mixed>}> $articles
 * @return array{created:int,updated:int,skipped:int,tag_links:int}
 */
function sclass_import_articles(
    mysqli $db,
    int $pressId,
    int $issueId,
    string $issueKey,
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

    if ($issueId <= 0) {
        fwrite(STDERR, "  SCLASS#{$issueKey}: no issue id, skip articles\n");

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
            fwrite(STDERR, "  SKIP SCLASS#{$issueKey} #{$number} {$item['stem']}: empty title_ru/body_ru\n");
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
            max(0, $issueId)
        );

        $action = 'create';
        if ($existingId > 0) {
            if (!$force) {
                fwrite(STDOUT, "  skip SCLASS#{$issueKey} #{$number} id={$existingId} (exists) {$titleRu}\n");
                $skipped++;
                continue;
            }
            $action = 'update';
        }

        fwrite(
            STDOUT,
            sprintf(
                "  %s SCLASS#%s #%d %s | ru=%d en=%d tags=%d slugs=%s/%s\n",
                $action,
                $issueKey,
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
                sclass_find_or_create_tag($db, $tagName, true);
                $tagLinks++;
            }
            if ($action === 'create') {
                $created++;
            } else {
                $updated++;
            }
            continue;
        }

        $textType = SCLASS_TEXT_TYPE;
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
                fwrite(STDERR, "  FAIL insert SCLASS#{$issueKey} #{$number}\n");
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
                    'source' => SCLASS_SOURCE,
                    'stem' => $item['stem'],
                    'issue_key' => $issueKey,
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
                fwrite(STDERR, "  FAIL update SCLASS#{$issueKey} #{$number} id={$aid}\n");
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
                'meta' => ['source' => SCLASS_SOURCE, 'stem' => $item['stem'], 'issue_key' => $issueKey],
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
        sclass_sync_v2($db, $pressId, $issueId, $articleRow);

        foreach ($tags as $tagName) {
            $tagId = sclass_find_or_create_tag($db, $tagName, false);
            if (sclass_link_tag($db, $aid, $tagId, false)) {
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
        "Sinclair Classic import: press=%d (%s) only=%s mode=%s%s%s\n",
        $pressId,
        (string) $press['title'],
        implode(',', $only),
        $dryRun ? 'dry-run' : 'APPLY',
        $force ? ' force' : '',
        $withTags ? ' with-tags' : ''
    )
);

$totalCreated = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$totalTags = 0;
$issueIds = [];

foreach ($only as $issueKey) {
    $issueKey = (string) $issueKey;
    if ($issueKey === '10' || ctype_digit($issueKey)) {
        $issueKey = str_pad($issueKey, 2, '0', STR_PAD_LEFT);
    }
    $title = SCLASS_ISSUE_TITLES[$issueKey] ?? null;
    if ($title === null) {
        fwrite(STDERR, "Unknown issue key={$issueKey}\n");
        exit(1);
    }
    $source = sclass_resolve_source($issueKey, $siteRoot);
    if ($source === '') {
        fwrite(STDERR, "SCLASS#{$issueKey}: no i18n source dir\n");
        exit(1);
    }
    $articles = sclass_load_articles($source);
    $issue = sclass_find_issue($db, $pressId, $title);
    if (!$issue) {
        fwrite(STDERR, "SCLASS#{$issueKey}: issue title={$title} not found\n");
        exit(1);
    }
    $issueId = (int) $issue['id'];
    $issueIds[$issueKey] = $issueId;
    $articleDate = (int) ($issue['date'] ?? 0);

    fwrite(
        STDOUT,
        sprintf(
            "\n== SCLASS#%s id=%d articles=%d source=%s ==\n",
            $issueKey,
            $issueId,
            count($articles),
            $source
        )
    );

    $stats = sclass_import_articles(
        $db,
        $pressId,
        $issueId,
        $issueKey,
        $articles,
        $articleDate,
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
    $zCnt = db_select($db, 'SELECT COUNT(*) AS c FROM issue WHERE id_press=?', 'i', $pressId);
    $cntRow = $zCnt ? $zCnt->fetch_assoc() : null;
    $cnt = (int) ($cntRow['c'] ?? 0);

    $zRange = db_select(
        $db,
        'SELECT MIN(date) AS y_from, MAX(date) AS y_to FROM issue WHERE id_press=? AND date > 0',
        'i',
        $pressId
    );
    $rangeRow = $zRange ? $zRange->fetch_assoc() : null;
    $yearsFrom = (int) ($rangeRow['y_from'] ?? 0);
    $yearsTo = (int) ($rangeRow['y_to'] ?? 0);

    if ($cnt > 0 || ($yearsFrom > 0 && $yearsTo > 0)) {
        if ($cnt > 0 && $yearsFrom > 0 && $yearsTo > 0) {
            db_exec(
                $db,
                'UPDATE press SET numbers=?, years_from=?, years_to=? WHERE id=? LIMIT 1',
                'iiii',
                $cnt,
                $yearsFrom,
                $yearsTo,
                $pressId
            );
        } elseif ($cnt > 0) {
            db_exec($db, 'UPDATE press SET numbers=? WHERE id=? LIMIT 1', 'ii', $cnt, $pressId);
        }
        fwrite(
            STDOUT,
            sprintf(
                "\npress.meta → numbers=%d years_from=%s years_to=%s\n",
                $cnt,
                $yearsFrom > 0 ? gmdate('Y-m-d', $yearsFrom) : '—',
                $yearsTo > 0 ? gmdate('Y-m-d', $yearsTo) : '—'
            )
        );

        if (ezines_v2_ready($db)) {
            $zPressFresh = db_select($db, 'SELECT * FROM press WHERE id=? LIMIT 1', 'i', $pressId);
            $pressFresh = ($zPressFresh && ($pr = mysqli_fetch_assoc($zPressFresh))) ? $pr : null;
            if (is_array($pressFresh)) {
                ezines_v2_sync_press($db, $pressFresh, 'en');
            }
        }
    }
    if ($totalCreated > 0 || $totalUpdated > 0) {
        sclass_queue_manticore_reindex();
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
