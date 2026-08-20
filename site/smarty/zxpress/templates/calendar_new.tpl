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
	<style>
		.smn-calendar-today {
			margin: 0 0 28px;
			padding: 14px 16px;
			border: 1px solid var(--smn-line);
			background: var(--smn-surface);
		}
		.smn-calendar-today-body {
			margin: 0;
			font-family: var(--smn-sans);
			font-size: 15px;
			font-weight: 400;
			line-height: 1.5;
			color: var(--smn-ink);
		}
		.smn-calendar-today-date {
			font-weight: 700;
		}
		.smn-calendar-today-list {
			display: inline;
		}
		.smn-calendar-today-list .smn-updates-press {
			position: relative;
			display: inline-block;
			padding-bottom: 2px;
			text-decoration: none;
		}
		.smn-calendar-today-list .smn-updates-press::after {
			content: "";
			position: absolute;
			left: 0;
			right: 0;
			bottom: 0;
			height: 2px;
			background: var(--smn-accent);
			transform: scaleX(0);
			transform-origin: left;
			transition: transform 0.25s ease;
		}
		.smn-calendar-today-list .smn-updates-press:hover::after {
			transform: scaleX(1);
		}
		.smn-calendar-today-empty {
			color: var(--smn-muted);
		}
		.smn-calendar-years {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 0;
			margin: 0 0 20px;
			padding: 0 8px;
		}
		.smn-calendar-year-sep {
			display: inline-flex;
			align-items: center;
			align-self: center;
			height: 1em;
			padding-bottom: 4px;
			margin: 0 4px;
			color: var(--smn-line);
			font-size: 14px;
			font-weight: 900;
			line-height: 1;
			user-select: none;
		}
		.smn-calendar-year-link {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 0;
			font-family: var(--smn-sans);
			font-size: 14px;
			font-weight: 700;
			text-decoration: none;
			color: var(--smn-ink);
		}
		.smn-calendar-year-link sup {
			margin-left: 1px;
		}
		.smn-calendar-year-link:hover {
			color: var(--smn-accent);
		}
		.smn-calendar-year-label {
			position: relative;
			display: inline-block;
			padding-bottom: 4px;
		}
		.smn-calendar-year-label::after {
			content: "";
			position: absolute;
			left: 0;
			right: 0;
			bottom: 0;
			height: 2px;
			background: var(--smn-accent);
			transform: scaleX(0);
			transform-origin: left;
			transition: transform 0.25s ease;
		}
		.smn-calendar-year-link:hover .smn-calendar-year-label::after {
			transform: scaleX(1);
		}
		.smn-calendar-year {
			margin: 0 0 24px;
			scroll-margin-top: 84px;
		}
		.smn-calendar-year-head {
			display: flex;
			flex-wrap: wrap;
			align-items: baseline;
			gap: 8px 14px;
			margin: 0 0 16px;
		}
		.smn-calendar-year-title {
			margin: 0;
			font-family: var(--smn-sans);
			font-size: 30px;
			font-weight: 800;
			letter-spacing: -0.03em;
		}
		.smn-calendar-year-title sup {
			margin-left: 1px;
			font-size: 0.45em;
			position: relative;
			top: -0.6em;
		}
		.smn-calendar-year-total {
			font-family: var(--smn-sans);
			font-size: 14px;
			font-weight: 700;
			color: var(--smn-muted);
		}
		.smn-calendar-grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 10px;
		}
		.smn-calendar-month {
			position: relative;
			padding: 8px 10px 10px;
			border: 1px solid var(--smn-line);
			border-radius: 5px;
			background: var(--smn-paper);
		}
		.smn-calendar-month-head {
			display: flex;
			align-items: baseline;
			justify-content: space-between;
			gap: 6px;
			margin: 0 0 4px;
		}
		.smn-calendar-month-title {
			margin: 0;
			font-family: var(--smn-sans);
			font-size: 13px;
			font-weight: 700;
		}
		.smn-calendar-month-total {
			font-family: var(--smn-sans);
			font-size: 11px;
			font-weight: 700;
			color: var(--smn-muted);
		}
		.smn-calendar-weekdays,
		.smn-calendar-days {
			display: grid;
			grid-template-columns: repeat(7, minmax(0, 1fr));
			gap: 3px;
		}
		.smn-calendar-weekdays {
			margin-bottom: 3px;
		}
		.smn-calendar-weekday {
			text-align: center;
			font-family: var(--smn-sans);
			font-size: 10px;
			font-weight: 700;
			letter-spacing: 0.02em;
			text-transform: uppercase;
			color: #c4b9a8;
		}
		.smn-calendar-day,
		.smn-calendar-day-empty {
			min-height: 30px;
		}
		.smn-calendar-day-empty {
			border-radius: 6px;
		}
		.smn-calendar-day {
			display: flex;
		}
		.smn-calendar-day-btn,
		.smn-calendar-day-plain {
			position: relative;
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			justify-content: space-between;
			width: 100%;
			min-height: 30px;
			padding: 4px 5px 3px;
			font-family: var(--smn-sans);
			font-size: 11px;
			line-height: 1;
		}
		.smn-calendar-day-plain {
			border-radius: 6px;
			border: 1px solid transparent;
			color: var(--smn-muted);
			background: transparent;
		}
		.smn-calendar-day-btn {
			border-radius: 3px;
			cursor: pointer;
			border: 1px solid var(--smn-line);
			background: #fff;
			color: var(--smn-ink);
			text-align: left;
		}
		.smn-calendar-day-btn:hover,
		.smn-calendar-day-btn.is-open {
			border-color: var(--smn-accent);
			background: #fff;
		}
		.smn-calendar-day-num {
			font-weight: 700;
		}
		.smn-calendar-day-count {
			align-self: flex-end;
			font-size: 9px;
			font-weight: 800;
			color: var(--smn-accent);
		}
		.smn-calendar-popover[hidden] {
			display: none;
		}
		.smn-calendar-popover {
			position: fixed;
			z-index: 1200;
			width: min(360px, calc(100vw - 24px));
			padding: 14px;
			border: 1px solid var(--smn-line);
			border-radius: 0;
			background: var(--smn-surface);
			box-shadow: 0 18px 40px rgba(0, 0, 0, 0.16);
		}
		.smn-calendar-popover-title {
			margin: 0 0 12px;
			font-family: var(--smn-sans);
			font-size: 16px;
			font-weight: 800;
		}
		.smn-calendar-popover-list {
			margin: 0;
			padding: 0;
			list-style: none;
			display: grid;
			gap: 10px;
		}
		.smn-calendar-popover-item {
			font-family: var(--smn-sans);
			font-size: 14px;
			line-height: 1.4;
		}
		.smn-calendar-popover-item a {
			font-weight: 700;
			text-decoration: none;
		}
		.smn-calendar-popover-item a:hover {
			color: var(--smn-accent);
		}
		.smn-calendar-popover-loc {
			font-size: 14px;
			font-weight: 400;
			color: var(--smn-muted);
			white-space: nowrap;
		}
		.smn-calendar-empty {
			padding: 24px 0;
			font-family: var(--smn-sans);
			font-size: 16px;
			color: var(--smn-muted);
		}
		@media (max-width: 580px) {
			.smn-calendar-grid {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}
		@media (max-width: 380px) {
			.smn-calendar-grid {
				grid-template-columns: 1fr;
			}
			.smn-calendar-year-title {
				font-size: 20px;
			}
		}
	</style>
</head>
<body class="smn">
	<div class="smn-frame">
		<header class="smn-header">
			<div class="smn-header-bar">
				<a class="smn-brand" href="{$ezines_catalog_url}">
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
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Publication release calendar{else}Календарь выпуска изданий{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Emags release calendar for the ZX Spectrum{else}Календарь выхода прессы для ZX Spectrum{/if}</h1>
				<p class="smn-lead">
					{if $lng eq 'eng'}
						It's hard to imagine, but in the mid-1990s, Russian-language electronic newspapers and magazines were published almost daily. Unfortunately, most of them became publicly known only years later—after they were discovered in personal floppy disk collections, copied, and published on websites like Virtual TR-DOS and ZXPress. And some publications are only now being rediscovered.
					{else}
			Сложно представить, но в середине 90-х рускоязычныеэлектронные газеты и журналы выходили едва ли не каждый день. К сожалению, о большинстве из них широкая публика узнала лишь спустя годы — после того как были обнаружены в личных коллекциях дискет, скопированы и опубликованы на сайтах  Virtual TR-DOS и ZXPress. А некоторые издания открываются заново только сейчас.
					{/if}
				</p>
			</section>

			<section class="smn-calendar-today" aria-labelledby="smn-calendar-today-title">
{if $calendar_today_items && $calendar_today_items|@count gt 0}
				<p class="smn-calendar-today-body" id="smn-calendar-today-title">
					{$calendar_today_prefix|escape:'html'} <strong class="smn-calendar-today-date">{$calendar_today_date|escape:'html'}</strong><br>
					{if $lng eq 'eng'}The following issues were released:{else}Вышли следующие издания:{/if}
					<span class="smn-calendar-today-list">
{foreach from=$calendar_today_items item=item name=todayDay}
						<a class="smn-updates-press" href="{$item.url|escape:'html'}">{$item.label|escape:'html'}</a>{if $item.year gt 0} ({$item.year}){/if}{if !$smarty.foreach.todayDay.last}, {/if}
{/foreach}
					</span>
				</p>
{else}
				<p class="smn-calendar-today-body smn-calendar-today-empty" id="smn-calendar-today-title">
					{$calendar_today_prefix|escape:'html'} <strong class="smn-calendar-today-date">{$calendar_today_date|escape:'html'}</strong> {if $lng eq 'eng'}No issues on this day in the archive.{else}В этот день в архиве пока нет выпусков.{/if}
				</p>
{/if}
			</section>

{if $calendar_years && $calendar_years|@count gt 0}
			<nav class="smn-calendar-years" aria-label="{if $lng eq 'eng'}Jump to year{else}Перейти к году{/if}">
{foreach from=$calendar_years item=yearBlock name=yearNav}
				<a class="smn-calendar-year-link" href="#calendar-year-{$yearBlock.year}">
					<span class="smn-calendar-year-label">
						{$yearBlock.year}<sup class="smn-filter-count">{$yearBlock.total}</sup>
					</span>
				</a>{if !$smarty.foreach.yearNav.last}<span class="smn-calendar-year-sep" aria-hidden="true">·</span>{/if}
{/foreach}
			</nav>

{foreach from=$calendar_years item=yearBlock}
			<section class="smn-calendar-year" id="calendar-year-{$yearBlock.year}" aria-labelledby="calendar-year-title-{$yearBlock.year}">
				<div class="smn-calendar-year-head">
					<h2 class="smn-calendar-year-title" id="calendar-year-title-{$yearBlock.year}">{$yearBlock.year}<sup class="smn-filter-count">{$yearBlock.total}</sup></h2>
				</div>

				<div class="smn-calendar-grid">
{foreach from=$yearBlock.months item=monthBlock}
{if $monthBlock.total gt 0}
					<section class="smn-calendar-month" aria-labelledby="calendar-month-{$yearBlock.year}-{$monthBlock.month}">
						<div class="smn-calendar-month-head">
							<h3 class="smn-calendar-month-title" id="calendar-month-{$yearBlock.year}-{$monthBlock.month}">{$monthBlock.name}</h3>
							<span class="smn-calendar-month-total">{$monthBlock.total}</span>
						</div>
						<div class="smn-calendar-weekdays" aria-hidden="true">
{foreach from=$calendar_weekdays item=weekday}
							<span class="smn-calendar-weekday">{$weekday}</span>
{/foreach}
						</div>
						<div class="smn-calendar-days">
{foreach from=$monthBlock.weeks item=week}
{foreach from=$week item=cell}
{if $cell}
							<div class="smn-calendar-day">
{if $cell.has_items}
								<button
									type="button"
									class="smn-calendar-day-btn"
									data-calendar-day="{$cell.key}"
									aria-haspopup="dialog"
									aria-expanded="false"
								>
									<span class="smn-calendar-day-num">{$cell.day}</span>
									<span class="smn-calendar-day-count">{$cell.count}</span>
								</button>
{else}
								<span class="smn-calendar-day-plain">
									<span class="smn-calendar-day-num">{$cell.day}</span>
								</span>
{/if}
							</div>
{else}
							<div class="smn-calendar-day-empty" aria-hidden="true"></div>
{/if}
{/foreach}
{/foreach}
						</div>
					</section>
{/if}
{/foreach}
				</div>
			</section>
{/foreach}
{else}
			<p class="smn-calendar-empty">{if $lng eq 'eng'}No dated issues yet.{else}Пока нет выпусков с датой.{/if}</p>
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
	<div class="smn-calendar-popover" id="smn-calendar-popover" hidden></div>
{include file="snailmail_new_scripts.tpl"}
	<script>
	(function () {
		var data = {$calendar_days_json nofilter};
		var popover = document.getElementById('smn-calendar-popover');
		if (!popover) return;

		var activeButton = null;

		function closePopover() {
			if (activeButton) {
				activeButton.classList.remove('is-open');
				activeButton.setAttribute('aria-expanded', 'false');
			}
			popover.hidden = true;
			popover.innerHTML = '';
			activeButton = null;
		}

		function escapeHtml(value) {
			return String(value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		function renderItems(day) {
			var html = '<h3 class="smn-calendar-popover-title">' + escapeHtml(day.label || '') + '</h3>';
			html += '<ul class="smn-calendar-popover-list">';
			for (var i = 0; i < (day.items || []).length; i++) {
				var item = day.items[i] || {};
				html += '<li class="smn-calendar-popover-item">';
				html += '<a href="' + escapeHtml(item.url || '#') + '">' + escapeHtml((item.title || '') + ' #' + (item.issue_title || '')) + '</a>';
				if (item.city) {
					html += ' <span class="smn-calendar-popover-loc">(' + escapeHtml(item.city) + ')</span>';
				}
				html += '</li>';
			}
			html += '</ul>';
			return html;
		}

		function placePopover(button) {
			var rect = button.getBoundingClientRect();
			var gap = 10;
			var width = popover.offsetWidth;
			var left = rect.left + (rect.width / 2) - (width / 2);
			left = Math.max(12, Math.min(left, window.innerWidth - width - 12));
			var top = rect.bottom + gap;
			var maxTop = window.innerHeight - popover.offsetHeight - 12;
			if (top > maxTop) {
				top = Math.max(12, rect.top - popover.offsetHeight - gap);
			}
			popover.style.left = left + 'px';
			popover.style.top = top + 'px';
		}

		document.addEventListener('click', function (event) {
			var button = event.target.closest('[data-calendar-day]');
			if (!button) {
				if (!popover.contains(event.target)) {
					closePopover();
				}
				return;
			}

			var key = button.getAttribute('data-calendar-day') || '';
			var day = data[key];
			if (!day) {
				closePopover();
				return;
			}

			if (activeButton === button && !popover.hidden) {
				closePopover();
				return;
			}

			if (activeButton) {
				activeButton.classList.remove('is-open');
				activeButton.setAttribute('aria-expanded', 'false');
			}

			activeButton = button;
			activeButton.classList.add('is-open');
			activeButton.setAttribute('aria-expanded', 'true');
			popover.innerHTML = renderItems(day);
			popover.hidden = false;
			placePopover(button);
		});

		window.addEventListener('resize', function () {
			if (activeButton && !popover.hidden) {
				placePopover(activeButton);
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closePopover();
			}
		});
	})();
	</script>
</body>
</html>
