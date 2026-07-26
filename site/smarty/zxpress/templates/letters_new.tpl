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
	{if $og_title}
	<meta property="og:title" content="{$og_title|escape:'html'}">
	<meta property="og:description" content="{$og_description|escape:'html'}">
	<meta property="og:type" content="{$og_type|escape:'html'}">
	<meta property="og:url" content="{$og_url|escape:'html'}">
	{if $og_image}<meta property="og:image" content="{$og_image|escape:'html'}">{/if}
	{/if}
	{smn_styles}
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
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/ezines-new">{if $lng eq 'eng'}Diskmags{else}Эл. журналы{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/periodicals">{if $lng eq 'eng'}Periodicals{else}Периодика{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/books-new">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item is-active" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
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
{if $letter}
				<a href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$letter.title_display}</span>
{elseif $filter_author && $filter_author_display}
				<a href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$filter_author_display}</span>
{else}
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Letters{else}Письма{/if}</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
{if $letter}

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
						<img src="{$img.display_src}" alt="" loading="lazy" decoding="async">
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
				<h1>{if $lng eq 'eng'}Letters with {$filter_author_display}{else}Письма с участием {$filter_author_display}{/if}</h1>
			</section>
			{else}
			<section class="smn-hero">
				<h1>{if $lng eq 'eng'}Paper letters from mid-1990s members of the ZX Spectrum scene{else}Бумажная переписка участников ZX Spectrum-сцены{/if}</h1>
				<div class="smn-lead" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">				{if $lng eq 'eng'}
					<p>This section is about the culture of <b>swapping</b> and <b>snailmail</b> — exchanging floppy disks and cassettes by ordinary (snail) mail. Without that network of exchange, the demoscene of the USSR and Eastern Europe in the 1980s–1990s might not have developed into the form we know today.</p>
					<p>Before the Internet became widespread, paper letters were what linked active users across cities and countries. Exchange was usually handled by a dedicated person — a <b>swapper</b>. They kept in touch with dozens, sometimes hundreds of people. How quickly new releases (software, games and demos) spread across the scene depended on them. Swappers often also sold software at local markets and by mail.</p>
					<p>This section collects scans of paper letters from members of the post-Soviet ZX Spectrum scene of the 1990s. In them you will find news, plans, and discussions of programs, games, demos and the demoscene of that time.</p>
				{else}
					<p>Раздел посвящен культуре <b>swapping</b> и <b>snailmail</b> - обмену дискетами, кассетами через обычную (улиточную) почту. Без этой системы обмена демосцена СССР и Восточной Европы 1980–1990-х годов, вероятно, не смогла бы развиваться в том виде, в котором мы её знаем сегодня.</p>
					<p>До широкого распространения Интернета именно бумажные письма связывали активных пользователей из разных городов и стран. Обменом как правило занимался специальный человек - <b>своппер</b>. Он поддерживал контакты с десятками, иногда сотнями людей. От него зависело, насколько быстро новые релизы (софт, игры и демо) разойдутся по сцене. Не редко свопперы занимались так же продажей софта, на местном рынке и по почте.</p>
					<p>В этом разделе собраны сканы бумажных писем участников постсоветской ZX Spectrum-сцены 1990-х годов. В них можно найти новости, планы, обсуждения программ, игр, демо и демосцены того времени.</p>
				{/if}</div>
				<div class="smn-hero-visual">
					<img src="{$host}img/snailmail.png" alt="" width="180" height="120">
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
						<span class="smn-list-summary">{if $row.cover}<span class="smn-list-cover"><img src="{$row.cover.thumb_src}" alt="" width="256" loading="lazy" decoding="async"></span>{/if}{if $row.summary_html}<span class="smn-list-summary-text">{$row.summary_html nofilter}</span>{/if}</span>
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
				{if $letters_prev_page}
					<a class="smn-pages-prev" href="{if $letters_prev_page gt 1}{$letters_catalog_url}?p={$letters_prev_page}{else}{$letters_catalog_url}{/if}" rel="prev" aria-label="{if $lng eq 'eng'}Previous page{else}Предыдущая страница{/if}">←</a>
				{else}
					<span class="smn-pages-prev is-disabled" aria-hidden="true">←</span>
				{/if}
				{section name=pg loop=$letters_total_pages}
					{assign var=pnum value=$smarty.section.pg.iteration}
					{if $pnum == $letters_page}
						<b aria-current="page">{$pnum}</b>
					{else}
						<a href="{if $pnum gt 1}{$letters_catalog_url}?p={$pnum}{else}{$letters_catalog_url}{/if}">{$pnum}</a>
					{/if}
				{/section}
				{if $letters_next_page}
					<a class="smn-pages-next" href="{$letters_catalog_url}?p={$letters_next_page}" rel="next" aria-label="{if $lng eq 'eng'}Next page{else}Следующая страница{/if}">→</a>
				{else}
					<span class="smn-pages-next is-disabled" aria-hidden="true">→</span>
				{/if}
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
