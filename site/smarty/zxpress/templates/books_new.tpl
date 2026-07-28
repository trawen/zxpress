<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en">
{else}
<html lang="ru">
{/if}
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{$title|strip_tags} — ZXPRESS</title>
	{if $description}<meta name="description" content="{$description|strip_tags|escape:'html'}">{/if}
	{smn_styles}
</head>
<body class="smn">
	<div class="smn-frame">
		<header class="smn-header">
			<div class="smn-header-bar">
				<a class="smn-brand" href="{$books_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл.пресса{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/periodicals">{if $lng eq 'eng'}Periodicals{else}Периодика{/if}</a>
						<a class="smn-nav-item is-active" href="{$books_catalog_url}">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/zxnet-new">ZXNet</a>
					</div>
					<div class="smn-nav-more-wrap">
						<button type="button" class="smn-nav-more-toggle" aria-expanded="false" aria-controls="smn-nav-more" aria-haspopup="true">
							{if $lng eq 'eng'}More...{else}Ещё...{/if}
						</button>
						<div id="smn-nav-more" class="smn-nav-more" hidden>
							<div class="smn-nav-overflow" hidden></div>
							{include file="snailmail_new_nav_more.tpl"}
						</div>
					</div>
				</nav>
				<div class="smn-lang">
					{if $lng eq 'eng'}
						<a href="{$url_rus}">rus</a><span aria-hidden="true">/</span><b>eng</b>
					{else}
						<b>rus</b><span aria-hidden="true">/</span><a href="{$url_eng}">eng</a>
					{/if}
				</div>
			</div>
			<form class="smn-search" method="GET" action="{if $lng eq 'eng'}/en{else}/ru{/if}/search-new">
				{if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}
				<div class="smn-search-wrap">
					<label class="smn-search-label" for="input_query_smn">{if $lng eq 'eng'}Search{else}Поиск{/if}</label>
					<input class="smn-search-input" id="input_query_smn" name="q" type="search" placeholder="{if $lng eq 'eng'}Search...{else}Поиск...{/if}" value="{$q|default:''|escape:'html'}" autocomplete="off">
					<div id="suggest-smn" class="smn-search-suggest"></div>
				</div>
			</form>
			<nav class="smn-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Books{else}Книги{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Paper books and magazines for ZX Spectrum{else}Бумажные книги и журналы для ZX Spectrum{/if}</h1>
				<p class="smn-hero-sub">
					{if $lng eq 'eng'}
						{$books_total} titles in the library
					{else}
						{$books_total} изданий в библиотеке
					{/if}
				</p>
				<div class="smn-lead is-collapsible" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">
				{if $lng eq 'eng'}
					<p>This section collects paper books and magazines about the ZX Spectrum and related home computers — programming manuals, game guides, repair handbooks and popular titles published from the late 1980s onward.</p>
					<p>Many editions can be read online or downloaded as scans. Covers and bibliographic details help you find the right book in the ZXPRESS library.</p>
				{else}
					<p>В этом разделе собраны бумажные книги и журналы о ZX Spectrum и родственных домашних компьютерах — учебники по программированию, справочники по играм, руководства по ремонту и популярные издания конца 1980-х и позже.</p>
					<p>Многие книги можно читать онлайн или скачать в виде сканов. Обложки и библиографические данные помогают найти нужное издание в библиотеке ZXPRESS.</p>
				{/if}
				</div>
			</section>

			{if $books && $books|@count gt 0}
			<ul class="smn-list smn-books">
			{foreach from=$books item=row}
				<li class="smn-list-item">
					<a class="smn-list-card smn-book-card" href="{$row.public_url}">
						<span class="smn-list-cover smn-book-cover">
							<img src="{$row.cover_src}" alt="" width="96" height="128" loading="lazy" decoding="async">
						</span>
						<h2 class="smn-list-title">{$row.title1}</h2>
						{if $row.title2 || $row.series}
						<span class="smn-book-subtitle">{$row.title2}{if $row.title2 && $row.series} {/if}{$row.series}</span>
						{/if}
						{if $row.year_display || $row.publisher_display}
						<span class="smn-list-meta-main smn-book-meta">
							{if $row.year_display}{$row.year_display}{/if}{if $row.year_display && $row.publisher_display} {/if}{$row.publisher_display}
						</span>
						{/if}
						{if $row.can_read || $row.can_download}
						<span class="smn-book-flags">
							{if $row.can_read}<span class="smn-book-flag">{if $lng eq 'eng'}read{else}читать{/if}</span>{/if}
							{if $row.can_download}<span class="smn-book-flag">{if $lng eq 'eng'}download{else}скачать{/if}</span>{/if}
						</span>
						{/if}
					</a>
				</li>
			{/foreach}
			</ul>
			{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No books here yet.{else}Здесь пока нет книг.{/if}</p>
			{/if}
		</main>

		<footer class="smn-footer">
			<div class="smn-copyright">
				{if $lng eq 'eng'}
				<p><b>ZXPRESS</b> — Magazines, newspapers and books for ZX Spectrum &nbsp;© 2009–{$smarty.now|date_format:"%Y"}</p>
				<p class="smn-disclaimer">You may use site materials only with a backlink to the source</p>
				{else}
				<p><b>ZXPRESS</b> — Журналы, газеты и книги для ZX Spectrum &nbsp;© 2009–{$smarty.now|date_format:"%Y"}</p>
				<p class="smn-disclaimer">Использование материалов сайта разрешено только при указании обратной ссылки</p>
				{/if}
			</div>
		</footer>
	</div>
{include file="snailmail_new_scripts.tpl"}

</body>
</html>
