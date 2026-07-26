<?php
require 'init.inc';
require_once __DIR__ . '/includes/ezine_slugs.php';
require_once __DIR__ . '/includes/authors_slugs.php';

function guestbook_ui_is_new(): bool
{
	return defined('GUESTBOOK_UI_VARIANT') && GUESTBOOK_UI_VARIANT === 'new';
}

function guestbook_ui_template(): string
{
	return guestbook_ui_is_new() ? 'guestbook_new.tpl' : 'guestbook.tpl';
}

function guestbook_url(bool $isEng): string
{
	if (guestbook_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/guestbook-new';
	}
	return '/guestbook.php' . ($isEng ? '?lng=eng' : '');
}

$_REQUEST['id'] = 0;

$lng = $smarty->getTemplateVars('lng');
$isEng = ($lng === 'eng');

$catalogUrl = guestbook_url($isEng);
$smarty->assign('guestbook_catalog_url', $catalogUrl);
$smarty->assign('ezines_catalog_url', guestbook_ui_is_new() ? ezn_url_catalog_new($isEng) : ezn_url_catalog($isEng));
$smarty->assign('letters_catalog_url', ezn_path_prefix($isEng) . '/snailmail-new');
$smarty->assign('authors_catalog_url', authors_url_catalog($isEng));
$smarty->assign('smn_nav_authors_active', false);
$smarty->assign('smn_nav_ezines_active', false);
$smarty->assign('smn_nav_gallery_active', false);
$smarty->assign('smn_nav_zxnet_active', false);
$smarty->assign('smn_nav_guestbook_active', guestbook_ui_is_new());

$smarty->assign('id_article', 0);
$smarty->assign('url_rus', htmlspecialchars(guestbook_url(false), ENT_QUOTES, 'UTF-8'));
$smarty->assign('url_eng', htmlspecialchars(guestbook_url(true), ENT_QUOTES, 'UTF-8'));

require __DIR__ . '/comments.php';

$smarty->assign('title', $isEng ? 'Guestbook' : 'Гостевая книга');
$smarty->assign(
	'description',
	$isEng
		? 'Your opinion about the project, suggestions, or just say hello'
		: 'Ваше мнение о проекте, пожелания или просто привет'
);

if (!guestbook_ui_is_new()) {
	include __DIR__ . '/right.php';
}

$smarty->display(guestbook_ui_template());
