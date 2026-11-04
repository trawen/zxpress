<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/search_suggest.php';
require_once dirname(__DIR__) . '/includes/books_v2_admin.php';
require_once dirname(__DIR__) . '/includes/letters_images.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$q = search_suggest_normalize_q((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$esc = search_suggest_like_escape($q);
$contains = '%' . $esc . '%';
$prefix = $esc . '%';
$limit = 8;
$items = [];

$bookSql = "SELECT w.id,
            COALESCE(
              (SELECT i.title FROM book_work_i18n i WHERE i.work_id=w.id AND i.lang='ru' AND i.title<>'' LIMIT 1),
              (SELECT i.title FROM book_work_i18n i WHERE i.work_id=w.id AND i.title<>'' ORDER BY i.lang LIMIT 1),
              CONCAT('Книга #', w.id)
            ) AS title,
            (SELECT img.path
               FROM book_edition_images img
              INNER JOIN book_editions e ON e.id=img.edition_id AND e.work_id=w.id
              ORDER BY (e.id = w.primary_edition_id) DESC, img.sort_order ASC, img.id ASC
              LIMIT 1) AS cover_path
     FROM book_works w
    WHERE w.id = ?
       OR EXISTS (
            SELECT 1 FROM book_work_i18n i
             WHERE i.work_id=w.id
               AND (i.title LIKE ? OR i.subtitle LIKE ? OR i.imprint LIKE ? OR i.slug LIKE ?)
          )
    ORDER BY (w.id = ?) DESC,
             (EXISTS (SELECT 1 FROM book_work_i18n i WHERE i.work_id=w.id AND i.title LIKE ?)) DESC,
             title ASC
    LIMIT ?";
$bookId = ctype_digit($q) ? (int) $q : 0;
$stmt = $db->prepare($bookSql);
if ($stmt) {
    $stmt->bind_param('issssisi', $bookId, $contains, $contains, $contains, $contains, $bookId, $prefix, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $label = search_suggest_plain_label((string) ($row['title'] ?? ''));
        if ($label === '') {
            $label = 'Книга #' . $id;
        }
        $thumb = '';
        $coverPath = trim((string) ($row['cover_path'] ?? ''));
        if ($coverPath !== '') {
            $thumb = books_v2_admin_cover_urls(['path' => $coverPath])['preview_url'];
        }
        $items[] = search_suggest_with_highlight([
            'type' => 'book',
            'label' => $label,
            'meta' => 'Книга #' . $id,
            'url' => '/admin2/work.php?id=' . $id,
            'thumb' => $thumb,
        ], $q);
    }
    $stmt->close();
}

$letterSql = 'SELECT l.id, l.title_ru, l.title_en,
            (SELECT i.id FROM images i
              WHERE i.entity_type=1 AND i.entity_id=l.id AND i.is_active=1
              ORDER BY i.sort_order ASC, i.id ASC LIMIT 1) AS cover_id
     FROM letters l
    WHERE l.id = ?
       OR l.title_ru LIKE ?
       OR l.title_en LIKE ?
    ORDER BY (l.id = ?) DESC,
             (l.title_ru LIKE ?) DESC,
             (l.title_en LIKE ?) DESC,
             l.title_ru ASC
    LIMIT ?';
$letterId = ctype_digit($q) ? (int) $q : 0;
$stmt = $db->prepare($letterSql);
if ($stmt) {
    $stmt->bind_param('issiisi', $letterId, $contains, $contains, $letterId, $prefix, $prefix, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $titleRu = search_suggest_plain_label((string) ($row['title_ru'] ?? ''));
        $titleEn = search_suggest_plain_label((string) ($row['title_en'] ?? ''));
        $label = $titleRu !== '' ? $titleRu : $titleEn;
        if ($label === '') {
            $label = 'Письмо #' . $id;
        }
        if (mb_strlen($label) > 120) {
            $label = rtrim(mb_substr($label, 0, 117)) . '…';
        }
        $thumb = '';
        $coverId = (int) ($row['cover_id'] ?? 0);
        if ($coverId > 0) {
            if (letters_preview_256_exists($coverId)) {
                $thumb = letters_preview_256_url($coverId);
            } else {
                $jpg = zx_storage_path('letters_preview', $coverId . '.jpg');
                if (is_file($jpg)) {
                    $thumb = '/letters/preview/' . $coverId . '.jpg';
                }
            }
        }
        $items[] = search_suggest_with_highlight([
            'type' => 'letter',
            'label' => $label,
            'meta' => 'Письмо #' . $id,
            'url' => '/admin2/letter.php?id=' . $id,
            'thumb' => $thumb,
        ], $q);
    }
    $stmt->close();
}

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
