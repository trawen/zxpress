<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en">
{else}
<html lang="ru">
{/if}
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
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
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Электронная пресса{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/books">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/zxnet">ZXNet</a>
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
			<form class="smn-search" method="GET" action="{if $lng eq 'eng'}/en{else}/ru{/if}/search">
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
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}What's new{else}Что нового{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Activity feed{else}Лента активности{/if}</h1>
				<p class="smn-lead">
					{if $lng eq 'eng'}
						Live stream of uploads and edits across the archive — screenshots, articles, and other site changes.
					{else}
						Живая лента загрузок и правок по архиву — скриншоты, статьи и другие изменения на сайте.
					{/if}
				</p>
			</section>

{if !$activity_ready}
			<p class="smn-empty-note">{if $lng eq 'eng'}Activity tables are not ready yet.{else}Таблицы activity ещё не созданы.{/if}</p>
{else}
			{assign var=updates_pages_top value=true}
			{include file="updates_activity_pages.tpl"}

{if $activity_batches && $activity_batches|@count gt 0}
			<section class="smn-updates smn-activity" aria-label="{if $lng eq 'eng'}Activity{else}Активность{/if}">
{foreach from=$activity_batches item=b}
{if $b.show_rule}
				<div class="smn-updates-rule" aria-hidden="true"></div>
{/if}
				<article class="smn-updates-row smn-activity-row">
					<div class="smn-updates-date">{if $b.date}<span class="smn-activity-date">{$b.date}</span>{/if}</div>
					<div class="smn-activity-when">{if $b.time_label}<span class="smn-activity-time">{$b.time_label|escape:'html'}</span>{/if}</div>
					<div class="smn-updates-body">
						{if !$b.is_public}
						<span class="smn-activity-meta">
							<span class="smn-activity-flag">{if $lng eq 'eng'}hidden{else}скрыто{/if}</span>
						</span>
						{/if}
{if $b.is_compact && $b.events && $b.events|@count gt 0}
						<span class="smn-activity-title">
{if $b.url_display}
							<a class="smn-updates-press" href="{$b.url_display|escape:'html'}">{$b.title_press|default:$b.title_display|escape:'html'}</a>
{else}
							<span class="smn-updates-press">{$b.title_press|default:$b.title_display|escape:'html'}</span>
{/if}
							<span class="smn-activity-title-suffix"> — </span>
							<details class="smn-activity-details smn-activity-details--inline">
								<summary>{$b.details_label|escape:'html'}</summary>
								<ul class="smn-activity-events">
{foreach from=$b.events item=e}
									<li class="smn-activity-event">
{if $e.thumb_url}
										<a class="smn-activity-thumb" href="{if $e.url_display}{$e.url_display|escape:'html'}{elseif $b.url_display}{$b.url_display|escape:'html'}{else}{$e.thumb_url|escape:'html'}{/if}">
											<img src="{$e.thumb_url|escape:'html'}" alt="" loading="lazy" width="128" height="96">
										</a>
{/if}
										<div class="smn-activity-event-body">
{if $e.url_display}
											<a class="smn-activity-event-title" href="{$e.url_display|escape:'html'}">{$e.title_display|escape:'html'}</a>
{else}
											<span class="smn-activity-event-title">{$e.title_display|escape:'html'}</span>
{/if}
											<span class="smn-activity-event-meta">{$e.object_label|escape:'html'}</span>
										</div>
									</li>
{/foreach}
								</ul>
							</details>
						</span>
{else}
{if $b.url_display}
						<span class="smn-activity-title"><a class="smn-updates-press" href="{$b.url_display|escape:'html'}">{$b.title_press|default:$b.title_display|escape:'html'}</a>{if $b.title_suffix}<span class="smn-activity-title-suffix">{$b.title_suffix|escape:'html'}</span>{/if}</span>
{else}
						<span class="smn-activity-title"><span class="smn-updates-press">{$b.title_press|default:$b.title_display|escape:'html'}</span>{if $b.title_suffix}<span class="smn-activity-title-suffix">{$b.title_suffix|escape:'html'}</span>{/if}</span>
{/if}
{if $b.summary_display}
						<p class="smn-activity-summary">{$b.summary_display|escape:'html'}</p>
{/if}
{if $b.events && $b.events|@count gt 0}
						<details class="smn-activity-details">
							<summary>
								{if $b.details_label}
									{$b.details_label|escape:'html'}
								{elseif $lng eq 'eng'}
									{$b.events|@count} {if $b.events|@count == 1}event{else}events{/if}
								{else}
									{$b.events|@count} {if $b.events|@count == 1}событие{elseif $b.events|@count < 5}события{else}событий{/if}
								{/if}
							</summary>
							<ul class="smn-activity-events">
{foreach from=$b.events item=e}
								<li class="smn-activity-event">
{if $e.thumb_url}
									<a class="smn-activity-thumb" href="{if $e.url_display}{$e.url_display|escape:'html'}{elseif $b.url_display}{$b.url_display|escape:'html'}{else}{$e.thumb_url|escape:'html'}{/if}">
										<img src="{$e.thumb_url|escape:'html'}" alt="" loading="lazy" width="128" height="96">
									</a>
{/if}
									<div class="smn-activity-event-body">
{if $e.url_display}
										<a class="smn-activity-event-title" href="{$e.url_display|escape:'html'}">{$e.title_display|escape:'html'}</a>
{else}
										<span class="smn-activity-event-title">{$e.title_display|escape:'html'}</span>
{/if}
										<span class="smn-activity-event-meta">{$e.object_label|escape:'html'}</span>
									</div>
								</li>
{/foreach}
							</ul>
						</details>
{/if}
{/if}
					</div>
				</article>
{/foreach}
			</section>
{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No activity yet.{else}Пока нет записей.{/if}</p>
{/if}

			{assign var=updates_pages_top value=false}
			{include file="updates_activity_pages.tpl"}
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
<script>
(function () {
	function pageHref(baseUrl, page) {
		if (page <= 1) return baseUrl;
		return baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + 'page=' + page;
	}

	function buildPages(total, current, maxVisible) {
		if (total <= maxVisible) {
			var full = [];
			for (var p = 1; p <= total; p++) full.push(p);
			return full;
		}
		var interiorSlots = Math.max(1, maxVisible - 2);
		var start = current - Math.floor((interiorSlots - 1) / 2);
		var end = start + interiorSlots - 1;
		if (start < 2) {
			start = 2;
			end = start + interiorSlots - 1;
		}
		if (end > total - 1) {
			end = total - 1;
			start = end - interiorSlots + 1;
		}
		var out = [1];
		if (start > 2) out.push('gap');
		for (var n = start; n <= end; n++) out.push(n);
		if (end < total - 1) out.push('gap');
		out.push(total);
		return out;
	}

	function renderNav(nav) {
		var total = parseInt(nav.getAttribute('data-total-pages') || '0', 10);
		var current = parseInt(nav.getAttribute('data-current-page') || '1', 10);
		var baseUrl = nav.getAttribute('data-base-url') || '';
		var list = nav.querySelector('.smn-pages-list');
		if (!list || total <= 1 || !baseUrl) return;

		var prev = nav.querySelector('.smn-pages-prev, .smn-pages-prev.is-disabled');
		var next = nav.querySelector('.smn-pages-next, .smn-pages-next.is-disabled');
		var navWidth = nav.clientWidth || 0;
		var sideWidth = (prev ? prev.offsetWidth : 0) + (next ? next.offsetWidth : 0) + 48;
		var free = Math.max(120, navWidth - sideWidth);
		var maxVisible = Math.max(5, Math.floor(free / 42));
		var model = buildPages(total, current, maxVisible);

		var html = '';
		for (var i = 0; i < model.length; i++) {
			var item = model[i];
			if (item === 'gap') {
				html += '<span class="smn-pages-gap" aria-hidden="true">…</span>';
			} else if (item === current) {
				html += '<b aria-current="page">' + item + '</b>';
			} else {
				html += '<a href="' + pageHref(baseUrl, item) + '">' + item + '</a>';
			}
		}
		list.innerHTML = html;
	}

	function renderAll() {
		var navs = document.querySelectorAll('[data-adaptive-pages="activity"]');
		for (var i = 0; i < navs.length; i++) renderNav(navs[i]);
	}

	var resizeTimer = 0;
	window.addEventListener('resize', function () {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(renderAll, 120);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', renderAll);
	} else {
		renderAll();
	}
})();
</script>

</body>
</html>
