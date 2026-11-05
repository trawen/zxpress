#!/usr/bin/env php
<?php
/**
 * Update Subliminal Extacy #00–#03 articles with AI RU/EN markdown i18n.
 *
 * Articles already exist (legacy plain text). This overwrites title/meta/bodies
 * and sets text_type*=3 (markdown). Match key: issue.title + articles.number.
 *
 * Usage:
 *   php site/cli/import-subxtc00-03-articles.php --dry-run
 *   php site/cli/import-subxtc00-03-articles.php --apply
 *   php site/cli/import-subxtc00-03-articles.php --apply --with-tags
 *   php site/cli/import-subxtc00-03-articles.php --apply --only=0,1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

const SE0003_PRESS_ID = 213;
const SE0003_SOURCE = 'migration:subxtc00_03_articles';
const SE0003_TEXT_TYPE = 3; // markdown

$dryRun = true;
$withTags = false;
$pressId = SE0003_PRESS_ID;
$only = [0, 1, 2, 3];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $dryRun = false;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--with-tags') {
        $withTags = true;
    } elseif (str_starts_with($arg, '--press-id=')) {
        $pressId = max(0, (int) substr($arg, 11));
    } elseif (str_starts_with($arg, '--only=')) {
        $only = [];
        foreach (explode(',', substr($arg, 7)) as $part) {
            $n = (int) trim($part);
            if ($n >= 0 && $n <= 3) {
                $only[] = $n;
            }
        }
        $only = array_values(array_unique($only));
        if ($only === []) {
            fwrite(STDERR, "--only must list 0,1,2 and/or 3\n");
            exit(1);
        }
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(
            STDOUT,
            "Usage: {$argv[0]} [--dry-run|--apply] [--with-tags] [--press-id=213] [--only=0,1,2,3]\n"
        );
        exit(0);
    } else {
        fwrite(STDERR, "Unknown arg: {$arg}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/cli/import-subxtc00-03-articles.php';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'zxpress-cli-import-subxtc00-03';

$siteRoot = dirname(__DIR__);
require $siteRoot . '/init.inc';
require_once $siteRoot . '/includes/ezine_slugs.php';
require_once $siteRoot . '/includes/article_text_render.php';
require_once $siteRoot . '/includes/admin_helpers.php';
require_once $siteRoot . '/includes/ezines_v2.php';
require_once $siteRoot . '/includes/ezines_v2_sync.php';

function se0003_resolve_source(int $issueNum, string $siteRoot): string
{
    $nn = sprintf('%02d', $issueNum);
    $candidates = [
        $siteRoot . '/cli/imports/subxtc' . $nn . '_i18n',
        dirname($siteRoot) . '/db/migration/subxtc00_03_articles/i18n/' . $nn,
        dirname($siteRoot) . '/local/work/SUBXTC' . $nn . '_texts/i18n',
    ];
    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            return $cand;
        }
    }

    return '';
}

function se0003_toc_number(string $stem): int
{
    if (preg_match('/^(\d+)_/', $stem, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * @return list<array{stem:string,number:int,path:string,data:array<string,mixed>}>
 */
function se0003_load_articles(string $dir): array
{
    $files = glob($dir . '/*.json') ?: [];
    sort($files, SORT_STRING);
    $out = [];
    foreach ($files as $path) {
        $stem = basename($path, '.json');
        if (str_ends_with($stem, '.src') || str_ends_with($path, '.src.json')) {
            continue;
        }
        // skip *.src.json named like foo.src.json
        if (str_contains($stem, '.src')) {
            continue;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Cannot read {$path}");
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON {$path}");
        }
        // require translated payload
        if (!isset($data['body_ru']) && !isset($data['title_ru'])) {
            continue;
        }
        $number = se0003_toc_number($stem);
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

function se0003_queue_manticore_reindex(): void
{
    $dir = zx_data_root() . '/manticore-reindex';
    @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/articles.pending', (string) time(), LOCK_EX);
}

function se0003_tag_name_db(string $tagName): string
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

function se0003_find_or_create_tag(mysqli $db, string $tagName, bool $dryRun): int
{
    $tagName = se0003_tag_name_db($tagName);
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

    return (int) mysqli_insert_id($db);
}

function se0003_link_tag(mysqli $db, int $articleId, int $tagId, bool $dryRun): bool
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

    return true;
}

function se0003_sync_v2(mysqli $db, int $pressId, int $issueId, array $articleRow): void
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

function se0003_find_issue(mysqli $db, int $pressId, string $title): ?array
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

$zPress = db_select($db, 'SELECT id, title, slug_ru FROM press WHERE id=? LIMIT 1', 'i', $pressId);
$press = $zPress ? $zPress->fetch_assoc() : null;
if (!$press) {
    fwrite(STDERR, "Press id={$pressId} not found\n");
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "SE#00–03 update: press=%d (%s) only=%s mode=%s%s\n",
        $pressId,
        (string) $press['title'],
        implode(',', $only),
        $dryRun ? 'dry-run' : 'APPLY',
        $withTags ? ' with-tags' : ''
    )
);

$updated = 0;
$missing = 0;
$skipped = 0;
$tagLinks = 0;

foreach ($only as $issueNum) {
    $issueTitle = sprintf('%02d', $issueNum);
    $source = se0003_resolve_source($issueNum, $siteRoot);
    if ($source === '') {
        fwrite(STDERR, "SE#{$issueTitle}: no i18n source dir\n");
        exit(1);
    }
    $issue = se0003_find_issue($db, $pressId, $issueTitle);
    if (!$issue) {
        fwrite(STDERR, "SE#{$issueTitle}: issue not found for press {$pressId}\n");
        exit(1);
    }
    $issueId = (int) $issue['id'];
    $articles = se0003_load_articles($source);
    fwrite(
        STDOUT,
        sprintf(
            "\n== SE#%s issue_id=%d articles=%d source=%s ==\n",
            $issueTitle,
            $issueId,
            count($articles),
            $source
        )
    );

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

        $zEx = db_select(
            $db,
            'SELECT id, title, slug_ru, slug_en FROM articles WHERE id_issue=? AND number=? LIMIT 1',
            'ii',
            $issueId,
            $number
        );
        $ex = $zEx ? $zEx->fetch_assoc() : null;
        if (!$ex) {
            fwrite(STDERR, "  MISSING #{$number} {$item['stem']} (no article in DB)\n");
            $missing++;
            continue;
        }
        $aid = (int) $ex['id'];

        $seed = [
            'id' => $aid,
            'id_issue' => $issueId,
            'title' => $titleRu,
            'title_eng' => $titleEn,
            'meta_description_ru' => $metaRu,
            'meta_description_en' => $metaEn,
            'slug_ru' => (string) ($ex['slug_ru'] ?? ''),
            'slug_en' => (string) ($ex['slug_en'] ?? ''),
        ];
        $slugs = ezn_admin_resolve_article_slugs(
            $db,
            $titleRu,
            $titleEn !== '' ? $titleEn : $titleRu,
            static fn (): string => ezn_default_article_ru($seed),
            static fn (): string => ezn_default_article_en($seed),
            $aid,
            $issueId
        );

        fwrite(
            STDOUT,
            sprintf(
                "  update #%d id=%d %s | ru=%d en=%d tags=%d\n",
                $number,
                $aid,
                $titleRu,
                strlen($bodyRu),
                strlen($bodyEn),
                count($tags)
            )
        );

        if ($dryRun) {
            foreach ($tags as $tagName) {
                se0003_find_or_create_tag($db, $tagName, true);
                $tagLinks++;
            }
            $updated++;
            continue;
        }

        $textType = SE0003_TEXT_TYPE;
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
            'meta' => ['source' => SE0003_SOURCE, 'stem' => $item['stem'], 'issue_num' => $issueNum],
        ]);

        $articleRow = [
            'id' => $aid,
            'title' => $titleRu,
            'title_eng' => $titleEn,
            'number' => $number,
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
        se0003_sync_v2($db, $pressId, $issueId, $articleRow);

        foreach ($tags as $tagName) {
            $tagId = se0003_find_or_create_tag($db, $tagName, false);
            if (se0003_link_tag($db, $aid, $tagId, false)) {
                $tagLinks++;
            }
        }
    }
}

if (!$dryRun && $updated > 0) {
    se0003_queue_manticore_reindex();
}

fwrite(
    STDOUT,
    sprintf(
        "\nDONE updated=%d missing=%d skipped=%d tag_links=%d%s\n",
        $updated,
        $missing,
        $skipped,
        $tagLinks,
        $dryRun ? ' (dry-run)' : ''
    )
);
exit($missing > 0 ? 2 : 0);
