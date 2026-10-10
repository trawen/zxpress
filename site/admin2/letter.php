<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/letters_admin.php';
require_once dirname(__DIR__) . '/includes/letters_ocr.php';

$id = (int) ($_GET['id'] ?? 0);
$error = '';

if (($_POST['action'] ?? '') === 'delete_image') {
    header('Content-Type: application/json; charset=utf-8');
    $imageId = (int) ($_POST['image_id'] ?? 0);
    if (!letters_admin_delete_image($db, $id, $imageId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Картинка не найдена'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_POST['action'] ?? '') === 'ocr') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode(['ok' => true, 'data' => letters_ocr_from_request($db)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        error_log('[letters_ocr] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['delete_letter']) && $id > 0) {
        $_POST['publish_status'] = (string) LETTER_STATUS_DELETED;
    }
    $saved = letters_admin_save($db, $id, '/admin2/letter.php');
    $id = (int) $saved['id'];
    if ($saved['error'] !== '') {
        $error = $saved['error'];
    }
    if ($saved['redirect'] !== '') {
        if (($_POST['save'] ?? '') === 'Сохранить и добавить еще') {
            admin2_flash_set('ok', 'Письмо #' . $id . ' сохранено');
            admin2_redirect('/admin2/letter.php');
        }
        admin2_flash_set('ok', 'Сохранено');
        admin2_redirect($saved['redirect']);
    }
}

$letter = letters_admin_load($db, $id);

$smarty->assign('error', $error);
$smarty->assign('letter', $letter);
$smarty->assign('authors', letters_admin_authors($db));
$smarty->assign('images', letters_admin_images($db, $id));
$smarty->assign('letter_status_draft', LETTER_STATUS_DRAFT);
$smarty->assign('letter_status_queued', LETTER_STATUS_QUEUED);
$smarty->assign('letter_status_published', LETTER_STATUS_PUBLISHED);
$smarty->assign('letter_status_deleted', LETTER_STATUS_DELETED);

$title = $letter ? ('Письмо: ' . (string) ($letter['title_ru'] ?? '')) : 'Новое письмо';
admin2_render('admin2/letter_form.tpl', $title, 'letters');
