#!/usr/bin/env php
<?php
/**
 * Backfill slug_ru / slug_en for press, issue, and articles (ezines).
 *
 * Usage:
 *   php tools/backfill-ezine-slugs.php
 *   php tools/backfill-ezine-slugs.php --truncate-long
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/site/init.inc';
require_once $root . '/site/includes/ezine_slugs.php';

function ezn_backfill_lang_column(
    mysqli $db,
    string $table,
    string $column,
    callable $valueGenerator
): int {
    $allowed = ['press', 'issue', 'articles'];
    if (!in_array($table, $allowed, true) || !in_array($column, ['slug_ru', 'slug_en'], true)) {
        throw new InvalidArgumentException('Invalid table/column');
    }

    if ($table === 'press') {
        $sql = 'SELECT * FROM press WHERE ' . $column . ' IS NULL OR ' . $column . "='' ORDER BY id ASC";
    } else {
        $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $column . "='' ORDER BY id ASC";
    }

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
        fwrite(STDOUT, "[backfill] {$table} #{$id} {$column} -> {$slug}\n");
    }
    $rs->free();

    return $updated;
}

function ezn_truncate_existing_article_slugs(mysqli $db): int
{
    $updated = 0;

    foreach (['slug_ru', 'slug_en'] as $column) {
        $sql = 'SELECT * FROM articles WHERE CHAR_LENGTH(' . $column . ') > ' . EZN_ARTICLE_SLUG_MAX_LEN . ' ORDER BY id ASC';
        $rs = $db->query($sql);
        if (!$rs) {
            throw new RuntimeException('Query failed: ' . $db->error);
        }

        while ($row = $rs->fetch_assoc()) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $current = (string) ($row[$column] ?? '');
            $base = ezn_truncate_article_slug($current);
            if ($base === '' || $base === $current) {
                continue;
            }

            [$scopeColumn, $scopeId] = per_slug_scope_for_row('articles', $row);
            $slug = per_slug_make_unique_lang(
                $db,
                'articles',
                $column,
                $base,
                $id,
                $scopeColumn,
                $scopeId
            );

            $stmt = $db->prepare('UPDATE articles SET ' . $column . '=? WHERE id=? LIMIT 1');
            if (!$stmt) {
                throw new RuntimeException('Prepare failed: ' . $db->error);
            }
            $stmt->bind_param('si', $slug, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Update failed for articles #' . $id . ': ' . $db->error);
            }
            $stmt->close();
            $updated++;
            fwrite(STDOUT, "[truncate] articles #{$id} {$column} -> {$slug}\n");
        }
        $rs->free();
    }

    return $updated;
}

$total = 0;

$total += ezn_backfill_lang_column(
    $db,
    'press',
    'slug_ru',
    static fn (array $row): string => ezn_default_press_ru($row)
);
$total += ezn_backfill_lang_column(
    $db,
    'press',
    'slug_en',
    static fn (array $row): string => ezn_default_press_en($row)
);

$total += ezn_backfill_lang_column(
    $db,
    'issue',
    'slug_ru',
    static fn (array $row): string => ezn_default_issue_ru($row)
);
$total += ezn_backfill_lang_column(
    $db,
    'issue',
    'slug_en',
    static fn (array $row): string => ezn_default_issue_en($row)
);

$total += ezn_backfill_lang_column(
    $db,
    'articles',
    'slug_ru',
    static fn (array $row): string => ezn_default_article_ru($row)
);
$total += ezn_backfill_lang_column(
    $db,
    'articles',
    'slug_en',
    static fn (array $row): string => ezn_default_article_en($row)
);

$truncateLong = in_array('--truncate-long', $argv ?? [], true);
if ($truncateLong) {
    $total += ezn_truncate_existing_article_slugs($db);
}

fwrite(STDOUT, "[backfill] Done. Updated {$total} slug value(s).\n");
