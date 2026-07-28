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
				<a class="smn-brand" href="{$updates_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл.пресса{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/periodicals">{if $lng eq 'eng'}Periodicals{else}Периодика{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/books-new">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
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
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Updates{else}Обновления{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Latest additions to the zxpress library{else}Список последних поступлений в библиотеку zxpress{/if}</h1>
				<p class="smn-lead" id="smn-lead">
					{if $lng eq 'eng'}
						Site updates are irregular. Priority is given to non-entertainment publications. At the moment, more than 90% of articles from such publications have been processed and uploaded to the site.
					{else}
						Обновления сайта носят нерегулярный характер. Приоритетными являются издания не развлекательной направленности. На данный момент обработаны и залиты на сайт более 90% статей таких изданий.
					{/if}
				</p>
			</section>

{if $pages && $pages|@count gt 1}
			<nav class="smn-pages smn-pages--top" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
{foreach from=$pages item=pnum}
{if $pnum eq $tk_page}
				<b>{$pnum}</b>
{else}
				<a href="{if $pnum gt 1}{$updates_catalog_url}?page={$pnum}{else}{$updates_catalog_url}{/if}">{$pnum}</a>
{/if}
{/foreach}
			</nav>
{/if}

{if $updates && $updates|@count gt 0}
			<section class="smn-updates" aria-label="{if $lng eq 'eng'}Updates{else}Обновления{/if}">
{foreach from=$updates item=row}
{if $row.show_rule}
				<div class="smn-updates-rule" aria-hidden="true"></div>
{/if}
				<article class="smn-updates-row">
					<div class="smn-updates-date">{if $row.date}{$row.date}{/if}</div>
					<div class="smn-updates-body">
{if $row.print}
						<a class="smn-updates-press" href="{$row.issue_public_url}">{$row.press_title|escape:'html'} #{$row.issue_title|escape:'html'}</a>
{/if}
						<a class="smn-updates-article" href="{$row.article_public_url}">{$row.title_list nofilter}</a>
					</div>
				</article>
{/foreach}
			</section>
{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No updates yet.{else}Пока нет обновлений.{/if}</p>
{/if}

{if $pages && $pages|@count gt 1}
			<nav class="smn-pages" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
{foreach from=$pages item=pnum}
{if $pnum eq $tk_page}
				<b>{$pnum}</b>
{else}
				<a href="{if $pnum gt 1}{$updates_catalog_url}?page={$pnum}{else}{$updates_catalog_url}{/if}">{$pnum}</a>
{/if}
{/foreach}
			</nav>
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
