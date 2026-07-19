#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/site/init.inc';
require_once $root . '/site/includes/letters_slugs.php';

$rs = $db->query(
    "SELECT id, title_ru, title_en, slug_ru, slug_en FROM letters "
    . "WHERE slug_ru IS NULL OR slug_ru='' OR slug_en IS NULL OR slug_en='' ORDER BY id ASC"
);
if (!$rs) {
    throw new RuntimeException('Letters query failed: ' . $db->error);
}

$updated = 0;
while ($row = $rs->fetch_assoc()) {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $slugs = letters_resolve_slugs(
        $db,
        (string) ($row['slug_ru'] ?? ''),
        (string) ($row['slug_en'] ?? ''),
        (string) ($row['title_ru'] ?? ''),
        (string) ($row['title_en'] ?? ''),
        $id
    );

    $stmt = $db->prepare('UPDATE letters SET slug_ru=?, slug_en=? WHERE id=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Letters update prepare failed: ' . $db->error);
    }
    $stmt->bind_param('ssi', $slugs['slug_ru'], $slugs['slug_en'], $id);
    if (!$stmt->execute()) {
        throw new RuntimeException('Letters update failed for #' . $id . ': ' . $stmt->error);
    }
    $stmt->close();
    $updated++;
    fwrite(STDOUT, "#{$id}: {$slugs['slug_ru']} | {$slugs['slug_en']}\n");
}
$rs->free();

fwrite(STDOUT, "Updated {$updated} letter(s).\n");
