<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en">
{else}
<html lang="ru">
{/if}
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>{$title|strip_tags} — zxpress.ru</title>
	{if $description}<meta name="description" content="{$description|strip_tags|escape:'html'}">{/if}
	{if $og_title}
	<meta property="og:title" content="{$og_title|escape:'html'}">
	<meta property="og:description" content="{$og_description|escape:'html'}">
	<meta property="og:type" content="{$og_type|escape:'html'}">
	<meta property="og:url" content="{$og_url|escape:'html'}">
	{if $og_image}<meta property="og:image" content="{$og_image|escape:'html'}">{/if}
	{/if}
	<link rel="stylesheet" href="{$host}img/snailmail-new.css?{$smarty.now}">
</head>
<body class="smn">
	<div class="smn-frame">
		<header class="smn-header">
			<div class="smn-header-bar">
				<a class="smn-brand" href="{$letters_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/ezines-new">{if $lng eq 'eng'}Ezines{else}Эл. журналы{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/periodicals">{if $lng eq 'eng'}Periodicals{else}Периодика{/if}</a>
						<a class="smn-nav-item" href="{$host}books.php{if $lng eq 'eng'}?lng=eng{/if}">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item is-active" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
						<a class="smn-nav-item" href="{$host}zxnet{if $lng eq 'eng'}?lng=eng{/if}">ZXNet</a>
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
			<form class="smn-search" method="GET" action="{$host}search.php">
				{if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}
				<div class="smn-search-wrap">
					<label class="smn-search-label" for="input_query_smn">{if $lng eq 'eng'}Search{else}Поиск{/if}</label>
					<input class="smn-search-input" id="input_query_smn" name="q" type="search" placeholder="{if $lng eq 'eng'}Search...{else}Поиск...{/if}" value="{$q|default:''|escape:'html'}" autocomplete="off">
					<div id="suggest-smn" class="smn-search-suggest"></div>
				</div>
			</form>
		</header>

		<main class="smn-main">
{if $author_not_found}

			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Author not found{else}Автор не найден{/if}</h1>
				<p><a href="{$authors_catalog_url}">{if $lng eq 'eng'}All authors{else}Все авторы{/if}</a></p>
			</section>

{elseif $authors_catalog}

			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Authors of articles, letters and publications{else}Авторы статей, писем и публикаций{/if}</h1>
			</section>

			{if $authors_rows && $authors_rows|@count gt 0}
			<ul class="smn-author-index">
			{foreach from=$authors_rows item=auth}
				<li class="smn-author-index-item">
					<span class="smn-author-index-row">
						<span class="smn-author-index-main">
							<a class="smn-author-index-name" href="{$auth.author_url}">{$auth.author_handle}</a>
							{if $auth.author_geo}<span class="smn-author-index-geo">{$auth.author_geo}</span>{/if}
						</span>
						{if $auth.letter_count_label}<span class="smn-author-index-count">{$auth.letter_count_label}</span>{/if}
					</span>
				</li>
			{/foreach}
			</ul>
			{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No authors here yet.{else}Здесь пока нет авторов.{/if}</p>
			{/if}

{else}

			<section class="smn-hero smn-hero--compact">
				<h1>{$author_handle}{if $author_person} <span class="smn-hero-person">({$author_person})</span>{/if}</h1>
				{if $author_geo}
				<p class="smn-hero-geo">{$author_geo}</p>
				{/if}
				<p class="smn-hero-sub">
				{if $lng eq 'eng'}
					<a href="{$letters_catalog_url}">Paper letters</a> as sender or recipient{if $letters_total} · {$letters_total}{/if}
				{else}
					<a href="{$letters_catalog_url}">Бумажные письма</a> — от автора или к автору{if $letters_total} · {$letters_total}{/if}
				{/if}
				</p>
			</section>

			{if $letters_rows && $letters_rows|@count gt 0}
			<ul class="smn-list">
			{foreach from=$letters_rows item=row}
				<li class="smn-list-item">
					<a class="smn-list-card" href="{$row.public_url}">
						<h2 class="smn-list-title">{$row.title_display}</h2>
						{if $row.summary_html || $row.cover}
						<span class="smn-list-summary">{if $row.cover}<span class="smn-list-cover"><img src="{$row.cover.thumb_src}" alt="" width="128"></span>{/if}{if $row.summary_html}<span class="smn-list-summary-text">{$row.summary_html nofilter}</span>{/if}</span>
						{/if}
						<span class="smn-list-meta">
							<span class="smn-list-meta-main">
								{$row.from_author_name}{if $row.from_author_geo} <span class="smn-list-meta-geo">{$row.from_author_geo}</span>{/if}
								→
								{$row.to_author_name}{if $row.to_author_geo} <span class="smn-list-meta-geo">{$row.to_author_geo}</span>{/if}
							</span>
							{if $row.published_display}
							<span class="smn-list-meta-date">{if $lng eq 'eng'}published{else}опубликовано{/if} {$row.published_display}</span>
							{/if}
						</span>
					</a>
				</li>
			{/foreach}
			</ul>
			{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No letters here yet.{else}Здесь пока нет писем.{/if}</p>
			{/if}

			{if $letters_total_pages gt 1}
			<nav class="smn-pages" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
				{section name=pg loop=$letters_total_pages}
					{assign var=pnum value=$smarty.section.pg.iteration}
					{if $pnum == $letters_page}
						<b>{$pnum}</b>
					{else}
						<a href="{if $pnum gt 1}{$author_canonical_url}?p={$pnum}{else}{$author_canonical_url}{/if}">{$pnum}</a>
					{/if}
				{/section}
			</nav>
			{/if}

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
