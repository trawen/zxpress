<?php

declare(strict_types=1);

require dirname(__DIR__) . '/init.inc';

if (!isset($_SESSION['login']) || !$_SESSION['login']) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
}

function admin2_redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

function admin2_flash_set(string $kind, string $message): void
{
    $_SESSION['admin2_flash'] = ['kind' => $kind, 'message' => $message];
}

function admin2_render(string $contentTpl, string $title, string $section): void
{
    global $smarty;
    $flash = $_SESSION['admin2_flash'] ?? null;
    unset($_SESSION['admin2_flash']);
    $smarty->assign('admin2_flash', is_array($flash) ? $flash : null);
    $smarty->assign('error', $smarty->getTemplateVars('error') ?: '');
    $smarty->assign('admin2_title', $title);
    $smarty->assign('admin2_section', $section);
    $smarty->assign('admin2_content', $contentTpl);
    $smarty->assign('csrf_token', csrf_token());
    $smarty->display('admin2/layout.tpl');
}
