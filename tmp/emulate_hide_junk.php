<?php
/**
 * Soft-delete Emulate junk stubs (empty title) on press 66.
 * Usage: php cli/_emulate_hide_junk.php [--apply]
 */
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'cli';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__) . '/init.inc';

$apply = in_array('--apply', $argv, true);

$q = $db->query(
    "SELECT a.id, a.id_issue, a.number, a.slug_ru
     FROM articles a
     JOIN issue i ON i.id=a.id_issue
     WHERE i.id_press=66 AND a.temp=0 AND TRIM(COALESCE(a.title,''))=''
     ORDER BY a.id"
);
$ids = [];
while ($r = $q->fetch_assoc()) {
    $ids[] = (int) $r['id'];
    echo "junk id={$r['id']} issue={$r['id_issue']} #{$r['number']} {$r['slug_ru']}\n";
}
echo 'count=' . count($ids) . ' mode=' . ($apply ? 'APPLY' : 'dry-run') . "\n";
if (!$apply || $ids === []) {
    exit(0);
}

$db->begin_transaction();
try {
    foreach ($ids as $id) {
        $st = $db->prepare('UPDATE articles SET temp=1 WHERE id=? AND temp=0 LIMIT 1');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();

        // Deactivate matching ezines_v2 rows if any
        $st = $db->prepare(
            'UPDATE ezine_articles SET is_active=0 WHERE legacy_article_id=? AND is_active=1'
        );
        if ($st) {
            $st->bind_param('i', $id);
            $st->execute();
            $st->close();
        }
    }
    $db->commit();
    echo "DONE soft-deleted=" . count($ids) . "\n";
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
