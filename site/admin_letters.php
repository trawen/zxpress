<?php
require 'init.inc';
require_once __DIR__ . '/includes/letters_publish.php';
require_once __DIR__ . '/includes/letters_slugs.php';
require_once __DIR__ . '/includes/letters_images.php';
require_once __DIR__ . '/includes/letters_ocr.php';
require_once __DIR__ . '/includes/authors_slugs.php';
require_once __DIR__ . '/includes/letters_admin.php';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Drain queue here too — don't rely only on rare public page hits.
$lettersAutoPublishReason = null;
$lettersAutoPublishedId = letters_maybe_publish_next($db, $lettersAutoPublishReason);
if ($lettersAutoPublishedId) {
    $smarty->assign(
        'flash_ok',
        'Автопубликация из очереди: письмо #' . $lettersAutoPublishedId
    );
}

/**
 * Resolve author id from select and/or "new nick" input.
 * Non-empty nick wins: find by nickname (case-insensitive) or create.
 */
// AJAX: OCR cropped scans → fill letter form fields
if (($_POST['action'] ?? '') === 'ocr') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => letters_ocr_from_request($db)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        error_log('[letters_ocr] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}


$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ENTITY_TYPE_LETTER = LETTERS_ADMIN_ENTITY_TYPE;

if (($_POST['save'] ?? '') === 'Сохранить') {
    csrf_verify();
    $saved = letters_admin_save($db, $id, '/admin_letters.php');
    $id = (int) $saved['id'];
    if ($saved['error'] !== '') {
        $smarty->assign('error', $saved['error']);
    }
    if ($saved['redirect'] !== '') {
        header('Location: ' . $saved['redirect'], true, 303);
        exit;
    }
}

// Authors for selects
$authors = [];
$z = db_select($db, "SELECT id, nickname, name_ru, name_en, is_active FROM authors ORDER BY nickname ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $authors[] = $t;
}
$smarty->assign('authors', $authors);

// Letters list (with nicknames + publish labels)
$statusFilter = letters_publish_status_from_input((string) ($_GET['status'] ?? ''));
// Empty GET status means "all" — detect by raw presence.
$filterRaw = trim((string) ($_GET['status'] ?? ''));
$filterAll = ($filterRaw === '' || $filterRaw === 'all');
$authorFilterId = (int) ($_GET['author_id'] ?? 0);
if ($authorFilterId < 0) {
    $authorFilterId = 0;
}
$authorFilterNick = '';
if ($authorFilterId > 0) {
    foreach ($authors as $aRow) {
        if ((int) ($aRow['id'] ?? 0) === $authorFilterId) {
            $authorFilterNick = plain_text_normalize_for_storage((string) ($aRow['nickname'] ?? ''));
            break;
        }
    }
    if ($authorFilterNick === '') {
        $authorFilterId = 0;
    }
}

$letters_list = [];
$listSql = "SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick
     FROM letters l
     LEFT JOIN authors af ON af.id=l.author_from
     LEFT JOIN authors at ON at.id=l.author_to";
$where = [];
if (!$filterAll) {
    $where[] = 'l.publish_status=' . (int) $statusFilter;
}
if ($authorFilterId > 0) {
    $where[] = '(l.author_from=' . $authorFilterId . ' OR l.author_to=' . $authorFilterId . ')';
}
if ($where !== []) {
    $listSql .= ' WHERE ' . implode(' AND ', $where);
}
$listSql .= ' ORDER BY
     COALESCE(af.nickname, \'\') ASC,
     af.id ASC,
     CASE l.publish_status
       WHEN ' . LETTER_STATUS_QUEUED . ' THEN 0
       WHEN ' . LETTER_STATUS_DRAFT . ' THEN 1
       WHEN ' . LETTER_STATUS_PUBLISHED . ' THEN 2
       ELSE 3
     END ASC,
     l.queued_at ASC,
     l.id DESC';
$z = db_select($db, $listSql);

$queuePos = 0;
$queuedRows = db_select(
    $db,
    'SELECT id FROM letters WHERE publish_status=? ORDER BY queued_at ASC, id ASC',
    'i',
    LETTER_STATUS_QUEUED
);
$queueIndexById = [];
while ($queuedRows && ($qr = mysqli_fetch_assoc($queuedRows))) {
    $queuePos++;
    $queueIndexById[(int) $qr['id']] = $queuePos;
}

$letters_groups = [];
$groupIndexByKey = [];
while ($z && ($t = mysqli_fetch_array($z))) {
    $st = (int) ($t['publish_status'] ?? LETTER_STATUS_DRAFT);
    $t['publish_status'] = $st;
    $t['publish_label'] = letters_publish_status_label($st);
    $lid = (int) ($t['id'] ?? 0);
    if ($st === LETTER_STATUS_QUEUED && isset($queueIndexById[$lid])) {
        $t['queue_pos'] = $queueIndexById[$lid];
        $t['publish_label'] = 'очередь #' . $queueIndexById[$lid];
    }

    $fromId = (int) ($t['author_from'] ?? 0);
    $toId = (int) ($t['author_to'] ?? 0);
    $fromNick = trim((string) ($t['from_nick'] ?? ''));
    $toNick = trim((string) ($t['to_nick'] ?? ''));

    $pubRaw = trim((string) ($t['published_at'] ?? ''));
    $pubLabel = '—';
    if ($pubRaw !== '' && $pubRaw !== '0000-00-00 00:00:00') {
        $ts = strtotime($pubRaw);
        if ($ts !== false) {
            $pubLabel = date('d.m.Y', $ts);
        }
    }
    $titleRu = trim((string) ($t['title_ru'] ?? ''));

    // With author filter: group by the other party. Otherwise by sender.
    if ($authorFilterId > 0) {
        if ($fromId === $authorFilterId) {
            $peerId = $toId;
            $groupNick = $toNick !== '' ? $toNick : ('#' . $toId);
            $t['list_direction'] = 'to';
        } else {
            $peerId = $fromId;
            $groupNick = $fromNick !== '' ? $fromNick : ('#' . $fromId);
            $t['list_direction'] = 'from';
        }
        $groupKey = 'peer:' . $peerId;
        $t['list_peer'] = $groupNick;
        $t['list_text'] = $titleRu;
    } else {
        $groupKey = 'from:' . $fromId;
        $groupNick = $fromNick !== '' ? $fromNick : '—';
        $t['list_direction'] = 'from';
        $t['list_peer'] = $groupNick;
        $peer = $toNick !== '' ? $toNick : '—';
        $t['list_text'] = $peer . ': ' . $titleRu;
    }
    $t['list_published'] = $pubLabel;
    $t['list_label'] = $pubLabel . '  ' . (string) ($t['list_text'] ?? '');

    if (!isset($groupIndexByKey[$groupKey])) {
        $groupIndexByKey[$groupKey] = count($letters_groups);
        $letters_groups[] = [
            'key' => $groupKey,
            'nick' => $groupNick,
            'letters' => [],
        ];
    }
    $letters_groups[$groupIndexByKey[$groupKey]]['letters'][] = $t;
    $letters_list[] = $t;
}

// When filtered by author, sort groups by counterpart nick.
if ($authorFilterId > 0 && $letters_groups !== []) {
    usort($letters_groups, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['nick'] ?? ''), (string) ($b['nick'] ?? ''));
    });
}

$smarty->assign('letters_list', $letters_list);
$smarty->assign('letters_groups', $letters_groups);
$smarty->assign('status_filter', $filterAll ? 'all' : (string) $statusFilter);
$smarty->assign('author_filter_id', $authorFilterId);
$smarty->assign('author_filter_nick', $authorFilterNick);
$smarty->assign('letter_status_draft', LETTER_STATUS_DRAFT);
$smarty->assign('letter_status_queued', LETTER_STATUS_QUEUED);
$smarty->assign('letter_status_published', LETTER_STATUS_PUBLISHED);
$smarty->assign('letter_status_deleted', LETTER_STATUS_DELETED);

$letter = null;
if ($id > 0) {
    $stmt = $db->prepare(
        "SELECT l.*, af.nickname AS from_nick, at.nickname AS to_nick
         FROM letters l
         LEFT JOIN authors af ON af.id=l.author_from
         LEFT JOIN authors at ON at.id=l.author_to
         WHERE l.id=? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $letter = $stmt->get_result()->fetch_assoc();
    }
}
if ($letter && !empty($letter['date'])) {
    $letter['date'] = date('d.m.Y', strtotime((string) $letter['date']));
}
if ($letter) {
    $letter['publish_status'] = (int) ($letter['publish_status'] ?? LETTER_STATUS_DRAFT);
}
$smarty->assign('letter', $letter);

// Letter images list (admin)
$images = [];
if ($id > 0) {
    $zImg = db_select($db, "SELECT * FROM images WHERE entity_type=? AND entity_id=? ORDER BY sort_order ASC, id ASC", "ii", $ENTITY_TYPE_LETTER, $id);
    while ($zImg && ($img = mysqli_fetch_array($zImg))) {
        $imgId = (int) ($img['id'] ?? 0);
        $fmt = (int) ($img['format'] ?? 1);
        $ext = 'jpg';
        if ($fmt === 2) {
            $ext = 'png';
        } elseif ($fmt === 3) {
            $ext = 'webp';
        } elseif ($fmt === 4) {
            $ext = 'gif';
        }
        $img['original_path'] = zx_storage_path('letters', $imgId . '.' . $ext);
        $img['preview_path'] = zx_storage_path('letters_preview', $imgId . '.jpg');
        $img['original_url'] = '/letters/' . $imgId . '.' . $ext;
        $img['preview_url'] = '/letters/preview/' . $imgId . '.jpg?v=' . (is_file($img['preview_path']) ? (string) filemtime($img['preview_path']) : (string) time());
        $images[] = $img;
    }
}
$smarty->assign('images', $images);

// Admin top expects press_list
$press_list = [];
$z = db_select($db, "SELECT id, title1, title2, online AS online_articles FROM books ORDER BY title1 ASC");
while ($z && ($t = mysqli_fetch_array($z))) {
    $t['title'] = $t['title1'];
    if (!empty($t['title2'])) {
        $t['title'] = $t['title'] . " - " . $t['title2'];
    }
    $press_list[] = $t;
}
$smarty->assign('press_list', $press_list);

$letterDetectJs = __DIR__ . '/js/admin_letter_sheet_detect.js';
$smarty->assign(
    'letter_sheet_detect_js_v',
    is_file($letterDetectJs) ? (string) filemtime($letterDetectJs) : '1'
);

$smarty->assign('title', 'Админка: Письма');
$smarty->display('admin_letters.tpl');

