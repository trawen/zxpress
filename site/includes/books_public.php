<?php

require_once __DIR__ . '/ezine_slugs.php';

function books_ui_is_new(): bool
{
	if (defined('BOOKS_UI_VARIANT') || defined('BOOK_UI_VARIANT') || defined('BOOK_ARTICLE_UI_VARIANT')) {
		return (defined('BOOKS_UI_VARIANT') && BOOKS_UI_VARIANT === 'new')
			|| (defined('BOOK_UI_VARIANT') && BOOK_UI_VARIANT === 'new')
			|| (defined('BOOK_ARTICLE_UI_VARIANT') && BOOK_ARTICLE_UI_VARIANT === 'new');
	}

	return true;
}

function books_url_catalog(bool $isEng): string
{
	if (books_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/books';
	}
	return ezn_path_prefix($isEng) . '/books-old';
}

function books_url_book(int $id, bool $isEng): string
{
	if ($id <= 0) {
		return books_url_catalog($isEng);
	}
	if (books_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/books/' . $id;
	}
	return ezn_path_prefix($isEng) . '/books-old/' . $id;
}

function books_url_chapter(int $chapterId, bool $isEng): string
{
	if ($chapterId <= 0) {
		return books_url_catalog($isEng);
	}
	if (books_ui_is_new()) {
		return ezn_path_prefix($isEng) . '/books/chapter/' . $chapterId;
	}
	return ezn_path_prefix($isEng) . '/books-old/chapter/' . $chapterId;
}

function books_file_type_label(int $type, bool $isEng): string
{
	$map = [
		1 => 'PDF',
		2 => 'DJVU',
		3 => 'HTML',
		4 => 'TXT',
		5 => 'JPG',
		6 => $isEng ? 'WORD' : 'WORD',
	];
	return $map[$type] ?? ($isEng ? 'File' : 'Файл');
}

/**
 * Chapter HTML stores site paths without a leading slash
 * (chapters_images/..., pictures/...). That works on /book_articles.php
 * but breaks under /{lang}/books-new/chapter/{id}.
 */
function books_chapter_root_urls(string $html): string
{
	return (string) preg_replace(
		'/\b(src|href)=(["\'])(?!\/|https?:|\/\/|mailto:|data:|#|javascript:)((?:chapters_images|pictures|books_files|img)\/[^"\']*)\2/i',
		'$1=$2/$3$2',
		$html
	);
}
