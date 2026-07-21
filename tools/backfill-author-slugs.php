#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/site/init.inc';
require_once $root . '/site/includes/authors_slugs.php';

$rs = $db->query(
    "SELECT id, nickname, name_ru, name_en, slug_ru, slug_en FROM authors "
    . "WHERE slug_ru IS NULL OR slug_ru='' OR slug_en IS NULL OR slug_en='' ORDER BY id ASC"
);
if (!$rs) {
    throw new RuntimeException('Authors query failed: ' . $db->error);
}

$updated = 0;
while ($row = $rs->fetch_assoc()) {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $slugs = authors_resolve_slugs(
        $db,
        (string) ($row['slug_ru'] ?? ''),
        (string) ($row['slug_en'] ?? ''),
        (string) ($row['nickname'] ?? ''),
        (string) ($row['name_ru'] ?? ''),
        (string) ($row['name_en'] ?? ''),
        $id
    );

    $stmt = $db->prepare('UPDATE authors SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Authors update prepare failed: ' . $db->error);
    }
    $stmt->bind_param('ssi', $slugs['slug_ru'], $slugs['slug_en'], $id);
    if (!$stmt->execute()) {
        throw new RuntimeException('Authors update failed for #' . $id . ': ' . $stmt->error);
    }
    $stmt->close();
    $updated++;
    fwrite(STDOUT, "#{$id}: {$slugs['slug_ru']} | {$slugs['slug_en']}\n");
}
$rs->free();

fwrite(STDOUT, "Updated {$updated} author(s).\n");
