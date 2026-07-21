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
	<title>{$title|strip_tags} — ZXPRESS</title>
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
{if $letter}

			<p class="smn-back"><a href="{$letters_catalog_url}">{if $lng eq 'eng'}← All letters{else}← Все письма{/if}</a></p>
			<article class="smn-letter">
				<h1 class="smn-letter-title">{$letter.title_display}</h1>
				<p class="smn-letter-meta">
					<span class="smn-letter-meta-row">
						<span class="smn-letter-meta-label">{if $lng eq 'eng'}From{else}От{/if}</span>
						{if $letter.author_from}
						<a class="smn-letter-meta-author" href="{$letter.from_author_url}">{$letter.from_author_name}</a>{else}<span class="smn-letter-meta-author">{$letter.from_author_name}</span>{/if}{if $letter.from_author_geo}<span class="smn-letter-meta-geo"> {$letter.from_author_geo}</span>{/if}
					</span>
					<span class="smn-letter-meta-sep" aria-hidden="true">→</span>
					<span class="smn-letter-meta-row">
						<span class="smn-letter-meta-label">{if $lng eq 'eng'}To{else}Кому{/if}</span>
						{if $letter.author_to}
						<a class="smn-letter-meta-author" href="{$letter.to_author_url}">{$letter.to_author_name}</a>{else}<span class="smn-letter-meta-author">{$letter.to_author_name}</span>{/if}{if $letter.to_author_geo}<span class="smn-letter-meta-geo"> {$letter.to_author_geo}</span>{/if}
					</span>
					{if $letter.date_display}<span class="smn-letter-meta-date">{$letter.date_display}</span>{/if}
				</p>
				{if $letter.summary_html}
				<div class="smn-letter-summary" id="smn-letter-summary">{$letter.summary_html nofilter}</div>
				{/if}
				{if $letter_images && $letter_images|@count gt 0}
				<div class="smn-letter-scans">
				{foreach from=$letter_images item=img}
					<a href="{$img.original_url}" target="_blank" rel="noopener" class="smn-scan">
						<img src="{$img.display_src}" alt="">
					</a>
				{/foreach}
				</div>
				{/if}
				{if $letter.body_html}
				<div class="smn-letter-body">{$letter.body_html nofilter}</div>
				{/if}
			</article>

{elseif $letter_not_found}

			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Letter not found{else}Письмо не найдено{/if}</h1>
				<p><a href="{$letters_catalog_url}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>
			</section>

{else}

			{if $filter_author && $filter_author_display}
			<section class="smn-hero smn-hero--compact">
				<p class="smn-back"><a href="{$letters_catalog_url}">{if $lng eq 'eng'}← All letters{else}← Все письма{/if}</a></p>
				<h1>{if $lng eq 'eng'}Letters with {$filter_author_display}{else}Письма с участием {$filter_author_display}{/if}</h1>
			</section>
			{else}
			<section class="smn-hero">
				<h1>{if $lng eq 'eng'}Paper letters from mid-1990s members of the ZX Spectrum scene{else}Бумажная переписка участников ZX Spectrum-сцены{/if}</h1>
				<div class="smn-lead" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">{if $lng eq 'eng'}
					<p>Swapping and snailmail — the culture of exchanging floppy disks, cassettes, and magazines via regular (snail) mail, without which the demoscene of the USSR and Eastern Europe in the 80s–90s might not have existed.</p>
					<p>Before the internet, paper letters connected active users from different cities and countries. Exchange was usually handled by a dedicated person — a swapper. They maintained contacts with dozens, sometimes hundreds of people. How quickly new releases, software, games and demos spread across the scene depended on them. Swappers often also sold software in their city.</p>
					<p>Here are scans of paper letters from the domestic ZX Spectrum scene. In these letters you will find news, plans, and discussions of software, games and the demoscene.</p>
				{else}
					<p>Раздел посвящен культуре <b>swapping</b> и <b>snailmail</b> - обмену дискетами, кассетами через обычную (улиточную) почту. Без этой системы обмена демосцена СССР и Восточной Европы 1980–1990-х годов, вероятно, не смогла бы развиваться в том виде, в котором мы её знаем сегодня.</p>
					<p>До широкого распространения Интернета именно бумажные письма связывали активных пользователей из разных городов и стран. Обменом как правило занимался специальный человек - <b>своппер</b>. Он поддерживал контакты с десятками, иногда сотнями людей. От него зависело, насколько быстро новые релизы (софт, игры и демо) разойдутся по сцене. Не редко свопперы занимались так же продажей софта, на местном рынке и по почте.</p>
					<p>В этом разделе собраны сканы бумажных писем участников постсоветской ZX Spectrum-сцены 1990-х годов. В них можно найти новости, планы, обсуждения программ, игр, демо и демосцены того времени.</p>
				{/if}</div>
				<div class="smn-hero-visual">
					<img src="{$host}img/snailmail.png" alt="" width="420" height="280">
				</div>
			</section>
			{/if}

			{if $letter_author_filters && $letter_author_filters|@count gt 0}
			<nav class="smn-filters" aria-label="{if $lng eq 'eng'}Filter by correspondent{else}Фильтр по корреспонденту{/if}">
				<span class="smn-filters-label">{if $lng eq 'eng'}Correspondents{else}Корреспонденты{/if}</span>
				<div class="smn-filters-list">
				{foreach from=$letter_author_filters item=auth}
					{if $filter_author && $auth.id == $filter_author}
						<span class="smn-filter is-active">{$auth.author_display}<sup class="smn-filter-count">{$auth.letter_count}</sup></span>
					{else}
						<a class="smn-filter" href="{$auth.author_url}">{$auth.author_display}<sup class="smn-filter-count">{$auth.letter_count}</sup></a>
					{/if}
				{/foreach}
				{if $filter_author}
					<a class="smn-filter smn-filter--all" href="{$letters_catalog_url}">{if $lng eq 'eng'}all{else}все{/if}</a>
				{/if}
				</div>
			</nav>
			{/if}

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
						<a href="{$letters_catalog_url}?p={$pnum}">{$pnum}</a>
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
