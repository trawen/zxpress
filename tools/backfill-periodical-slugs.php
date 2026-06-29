#!/usr/bin/env php
<?php
/**
 * Backfill empty slug_ru / slug_en for periodicals, issues, and articles.
 * Copies legacy `slug` column into slug_ru when present.
 *
 * Usage:
 *   php tools/backfill-periodical-slugs.php
 *   php tools/backfill-periodical-slugs.php --regenerate-issues
 *   php tools/backfill-periodical-slugs.php --regenerate-articles
 *   php tools/backfill-periodical-slugs.php --regenerate-publishers
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/site/init.inc';
require_once $root . '/site/includes/periodicals_slugs.php';

function backfill_lang_column(
    mysqli $db,
    string $table,
    string $column,
    callable $valueGenerator
): int {
    $allowed = ['periodicals', 'periodical_issues', 'periodical_articles', 'publishers'];
    if (!in_array($table, $allowed, true) || !in_array($column, ['slug_ru', 'slug_en'], true)) {
        throw new InvalidArgumentException('Invalid table/column');
    }

    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $column . ' IS NULL OR ' . $column . "='' ORDER BY id ASC";
    $rs = $db->query($sql);
    if (!$rs) {
        throw new RuntimeException('Query failed: ' . $db->error);
    }

    $updated = 0;
    while ($row = $rs->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $base = (string) $valueGenerator($row);
        if ($base === '' && !empty($row['slug'])) {
            $base = (string) $row['slug'];
        }
        if ($base === '') {
            $base = 'item-' . $id;
        }

        [$scopeColumn, $scopeId] = per_slug_scope_for_row($table, $row);

        $slug = per_slug_make_unique_lang(
            $db,
            $table,
            $column,
            $base,
            $id,
            $scopeColumn,
            $scopeId
        );

        $stmt = $db->prepare('UPDATE ' . $table . ' SET ' . $column . '=? WHERE id=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $db->error);
        }
        $stmt->bind_param('si', $slug, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Update failed for ' . $table . ' #' . $id . ': ' . $db->error);
        }
        $stmt->close();
        $updated++;
        fwrite(STDOUT, "[backfill] {$table} #{$id} {$column} -> {$slug}\n");
    }
    $rs->free();

    return $updated;
}

function regenerate_lang_column(
    mysqli $db,
    string $table,
    string $column,
    callable $valueGenerator
): int {
    $allowed = ['periodicals', 'periodical_issues', 'periodical_articles', 'publishers'];
    if (!in_array($table, $allowed, true) || !in_array($column, ['slug_ru', 'slug_en'], true)) {
        throw new InvalidArgumentException('Invalid table/column');
    }

    $sql = 'SELECT * FROM ' . $table . ' ORDER BY id ASC';
    $rs = $db->query($sql);
    if (!$rs) {
        throw new RuntimeException('Query failed: ' . $db->error);
    }

    $updated = 0;
    while ($row = $rs->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $base = (string) $valueGenerator($row);
        if ($base === '') {
            $base = 'item-' . $id;
        }

        [$scopeColumn, $scopeId] = per_slug_scope_for_row($table, $row);

        $slug = per_slug_make_unique_lang(
            $db,
            $table,
            $column,
            $base,
            $id,
            $scopeColumn,
            $scopeId
        );

        $stmt = $db->prepare('UPDATE ' . $table . ' SET ' . $column . '=? WHERE id=? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $db->error);
        }
        $stmt->bind_param('si', $slug, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Update failed for ' . $table . ' #' . $id . ': ' . $db->error);
        }
        $stmt->close();
        $updated++;
        fwrite(STDOUT, "[regenerate] {$table} #{$id} {$column} -> {$slug}\n");
    }
    $rs->free();

    return $updated;
}

$regenerateIssues = in_array('--regenerate-issues', $argv ?? [], true);
$regenerateArticles = in_array('--regenerate-articles', $argv ?? [], true);
$regeneratePublishers = in_array('--regenerate-publishers', $argv ?? [], true);
$total = 0;

if ($regeneratePublishers) {
    $total += regenerate_lang_column(
        $db,
        'publishers',
        'slug_ru',
        static fn (array $row): string => per_slug_default_publisher_ru($row)
    );
    $total += regenerate_lang_column(
        $db,
        'publishers',
        'slug_en',
        static fn (array $row): string => per_slug_default_publisher_en($row)
    );
    fwrite(STDOUT, "[regenerate] Done. Updated {$total} publisher slug value(s).\n");
    exit(0);
}

if ($regenerateArticles) {
    $total += regenerate_lang_column(
        $db,
        'periodical_articles',
        'slug_ru',
        static fn (array $row): string => per_slug_default_article_ru($row)
    );
    $total += regenerate_lang_column(
        $db,
        'periodical_articles',
        'slug_en',
        static fn (array $row): string => per_slug_default_article_en($row)
    );
    fwrite(STDOUT, "[regenerate] Done. Updated {$total} article slug value(s).\n");
    exit(0);
}

if ($regenerateIssues) {
    $total += regenerate_lang_column(
        $db,
        'periodical_issues',
        'slug_ru',
        static fn (array $row): string => per_slug_default_issue_ru($row)
    );
    $total += regenerate_lang_column(
        $db,
        'periodical_issues',
        'slug_en',
        static fn (array $row): string => per_slug_default_issue_en($row)
    );
    fwrite(STDOUT, "[regenerate] Done. Updated {$total} issue slug value(s).\n");
    exit(0);
}

$total = 0;

$total += backfill_lang_column(
    $db,
    'periodicals',
    'slug_ru',
    static fn (array $row): string => per_slug_from_title((string) ($row['title_ru'] ?? ''))
);
$total += backfill_lang_column(
    $db,
    'periodicals',
    'slug_en',
    static fn (array $row): string => per_slug_from_title((string) ($row['title_en'] ?? ''))
);

$total += backfill_lang_column(
    $db,
    'periodical_issues',
    'slug_ru',
    static fn (array $row): string => per_slug_default_issue_ru($row)
);
$total += backfill_lang_column(
    $db,
    'periodical_issues',
    'slug_en',
    static fn (array $row): string => per_slug_default_issue_en($row)
);

$total += backfill_lang_column(
    $db,
    'periodical_articles',
    'slug_ru',
    static fn (array $row): string => per_slug_default_article_ru($row)
);
$total += backfill_lang_column(
    $db,
    'periodical_articles',
    'slug_en',
    static fn (array $row): string => per_slug_default_article_en($row)
);

$total += backfill_lang_column(
    $db,
    'publishers',
    'slug_ru',
    static fn (array $row): string => per_slug_default_publisher_ru($row)
);
$total += backfill_lang_column(
    $db,
    'publishers',
    'slug_en',
    static fn (array $row): string => per_slug_default_publisher_en($row)
);

fwrite(STDOUT, "[backfill] Done. Updated {$total} slug value(s).\n");
