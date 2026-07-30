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
	{if $description}<meta name="description" content="{$description|strip_tags}">{/if}
	{smn_styles}
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
						<a class="smn-nav-item{if $smn_nav_ezines_active|default:false} is-active{/if}" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл.пресса{/if}</a>
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
			<nav class="smn-breadcrumbs" id="smn-sticky-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
{if $press && $view_mode eq 'issue' && $current_issue}
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$ezines_catalog_url}">{include file="snailmail_bc_ezines_label.tpl"}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$press_url}">{$press.title_plain}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">#{$current_issue.title}</span>
{elseif $press}
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$ezines_catalog_url}">{include file="snailmail_bc_ezines_label.tpl"}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$press.title_plain}</span>
{else}
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{include file="snailmail_bc_ezines_label.tpl"}</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
{if $press && $view_mode eq 'issue' && $current_issue}

			<article class="smn-press smn-press--issue">
				<h1 class="smn-press-title">{$press.title_plain} #{$current_issue.title}</h1>
				<p class="smn-press-type">{if $lng eq 'eng'}{if $press.type eq 0}Diskmag (electronic paper){elseif $press.type eq 2}Diskmag (electronic report){else}Diskmag (electronic magazine){/if}{else}{if $press.type eq 0}Электронная газета{elseif $press.type eq 2}Электронный отчёт{else}Электронный журнал{/if}{/if}</p>
				<p class="smn-press-issue-meta">
{if $current_issue.date_display}
					<span class="smn-press-issue-date smn-press-issue-date--hero">{$current_issue.date_display}</span>
{/if}
{if $current_issue.download_url || $press.id}
{if $current_issue.date_display}<span class="smn-press-issue-meta-sep" aria-hidden="true">·</span>{/if}
{if $current_issue.download_url}
					<a class="smn-press-issue-action" href="{$current_issue.download_url}">{if $lng eq 'eng'}Download{else}Скачать{/if}</a>
{else}
					<a class="smn-press-issue-action" href="{$host}d.php?id={$press.id}">{if $lng eq 'eng'}Download{else}Скачать{/if}</a>
{/if}
{/if}
{if $current_issue.emulator_url}
{if $current_issue.date_display || $current_issue.download_url || $press.id}<span class="smn-press-issue-meta-sep" aria-hidden="true">·</span>{/if}
					<button type="button" class="smn-press-issue-action" id="smn-emulator-run" data-url="{$current_issue.emulator_url|escape:'html'}">{if $lng eq 'eng'}Launch in ZX Spectrum online emulator{else}Запустить в online эмуляторе ZX Spectrum{/if}</button>
{/if}
				</p>

{if $current_issue.screens && $current_issue.screens|@count gt 0}
				<ul class="smn-press-issue-screens" aria-label="{if $lng eq 'eng'}Screenshots{else}Скриншоты{/if}">
{foreach from=$current_issue.screens item=shot}
					<li class="smn-press-issue-screen">
						<img
							class="smn-press-issue-screen-img"
							src="{$host}screens/1/{$shot.id}.{$shot.format}"
							alt="{$press.title_plain} #{$current_issue.title}"
							width="256"
							height="192"
							loading="eager"
							decoding="async"
						>
					</li>
{/foreach}
				</ul>
{/if}

{if $current_issue.description}
				<p class="smn-press-issue-desc" id="smn-press-issue-desc">{$current_issue.description}</p>
{/if}

{if $prev_issue_nav || $next_issue_nav}
				<nav class="smn-issue-nav" aria-label="{if $lng eq 'eng'}Issues{else}Выпуски{/if}">
{if $prev_issue_nav}
					<a class="smn-issue-nav-prev" href="{$prev_issue_nav.public_url}" rel="prev">← {if $lng eq 'eng'}issue{else}выпуск{/if} #{$prev_issue_nav.title}</a>
{else}
					<span class="smn-issue-nav-prev is-disabled" aria-hidden="true"></span>
{/if}
{if $next_issue_nav}
					<a class="smn-issue-nav-next" href="{$next_issue_nav.public_url}" rel="next">{if $lng eq 'eng'}issue{else}выпуск{/if} #{$next_issue_nav.title} →</a>
{else}
					<span class="smn-issue-nav-next is-disabled" aria-hidden="true"></span>
{/if}
				</nav>
{/if}

{if $articles && $articles|@count gt 0}
				<section class="smn-press-toc" aria-label="{if $lng eq 'eng'}Articles{else}Статьи{/if}">
					<h2 class="smn-press-toc-heading">{if $lng eq 'eng'}Issue contents:{else}Содержание выпуска:{/if}</h2>
					<ol class="smn-press-articles">
{section name=n loop=$articles}
{if $articles[n].title}
						<li class="smn-press-article">
{if $lng eq 'eng'}
							<a href="{$articles[n].public_url}">{$articles[n].title_eng_list nofilter}</a>
{else}
							<a href="{$articles[n].public_url}">{$articles[n].title_list nofilter}</a>
{/if}
						</li>
{/if}
{/section}
					</ol>
				</section>
{/if}
			</article>

{elseif $press}

			<article class="smn-press">
				<h1 class="smn-press-title">{$press.title_plain}</h1>
				<p class="smn-press-type">{if $lng eq 'eng'}{if $press.type eq 0}Diskmag (electronic paper){elseif $press.type eq 2}Diskmag (electronic report){else}Diskmag (electronic magazine){/if}{else}{if $press.type eq 0}Электронная газета{elseif $press.type eq 2}Электронный отчёт{else}Электронный журнал{/if}{/if}</p>

				<p class="smn-press-meta">
{if $press.name}
					<span class="smn-press-meta-place">
{if $press.country_id}<img class="smn-ezine-flag" src="{$host}img/{$press.country_id}.png" width="16" height="10" alt="">{/if}
						{$press.name}{if $press.country_name} ({$press.country_name}){/if}
					</span>
{/if}
{if $press.years_from}
					{if $press.name}<span class="smn-press-meta-sep" aria-hidden="true">·</span>{/if}
					<span class="smn-press-meta-years">{$press.years_from}{if $press.years_to} – {$press.years_to}{/if}</span>
{/if}
					{if $press.name || $press.years_from}<span class="smn-press-meta-sep" aria-hidden="true">·</span>{/if}
					<span class="smn-press-meta-count">
						<b>{$press.numbers}</b>
{if $lng eq 'eng'}
						issues
{else}
						{$num}
{/if}
					</span>
					<span class="smn-press-meta-sep" aria-hidden="true">·</span>
					<a class="smn-press-meta-dl" href="{$host}d.php?id={$press.id}">{if $lng eq 'eng'}Download archive{else}Скачать архив{/if}</a>
				</p>

				{if $timeline}
				<div class="smn-timeline" aria-hidden="true">
					<div class="smn-timeline-dates">
						<span class="smn-timeline-date">{$timeline.start_date}</span>
						<span class="smn-timeline-date">{$timeline.end_date}</span>
					</div>
					<div class="smn-timeline-rail">
						{foreach from=$timeline.years item=y}
						<div class="smn-timeline-year" style="left: {$y.pos}%">
							<span class="smn-timeline-year-label">{$y.year}</span>
						</div>
						{/foreach}
						{foreach from=$timeline.dots item=dot}
						<div class="smn-timeline-dot{if $dot.is_edge} is-edge{/if}" style="left: {$dot.pos}%"></div>
						{/foreach}
					</div>
				</div>
				{/if}

{if $issues_list && $issues_list|@count gt 0}
				<ul class="smn-gallery smn-press-issues" aria-label="{if $lng eq 'eng'}Issues{else}Выпуски{/if}">
{foreach from=$issues_list item=iss}
					<li class="smn-gallery-item">
						<a class="smn-gallery-card smn-press-issue-card" href="{$iss.public_url}">
							<span class="smn-press-issue-num">#{$iss.title}</span>
{if $iss.cover}
							<img
								class="smn-gallery-img"
								src="{$host}screens/1/{$iss.cover.id}.{$iss.cover.format}"
								alt="{$press.title_plain} #{$iss.title}"
								width="256"
								height="192"
								loading="lazy"
								decoding="async"
							>
{else}
							<span class="smn-gallery-img smn-gallery-img--empty" aria-label="{$press.title_plain|escape:'html'} #{$iss.title|escape:'html'}">
								<span class="smn-gallery-img-empty-text">{$press.title_plain|escape:'html'}<br>#{$iss.title|escape:'html'}</span>
							</span>
{/if}
{if $iss.date_display}
							<span class="smn-press-issue-caption-date">{$iss.date_display}</span>
{/if}
						</a>
					</li>
{/foreach}
				</ul>
{/if}

{if $press.description}
				<section class="smn-press-history" aria-labelledby="smn-press-history-heading">
					<h2 class="smn-press-history-heading" id="smn-press-history-heading">{if $lng eq 'eng'}Magazine history{else}История журнала{/if}</h2>
					<div class="smn-lead is-collapsible" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">
						<p>{$press.description|escape:'html'|nl2br nofilter}</p>
					</div>
				</section>
{/if}
			</article>

{elseif $issue_not_found}

			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Issue not found{else}Выпуск не найден{/if}</h1>
				<p><a href="{$ezines_catalog_url}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>
			</section>

{elseif $press_not_found}

			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Publication not found{else}Издание не найдено{/if}</h1>
				<p><a href="{$ezines_catalog_url}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>
			</section>

{/if}
{if $comments_enabled|default:false}
			{include file="comments_new.tpl"}
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
{if $current_issue.emulator_url|default:''}
<div id="smn-emulator-modal" class="smn-emulator-modal" aria-hidden="true" data-error-msg="{if $lng eq 'eng'}Failed to load the emulator.{else}Не удалось загрузить эмулятор.{/if}">
	<div class="smn-emulator-modal-backdrop" data-smn-emulator-close="1"></div>
	<div class="smn-emulator-modal-panel" role="dialog" aria-modal="true" aria-labelledby="smn-emulator-title">
		<div class="smn-emulator-modal-head">
			<h2 id="smn-emulator-title" class="smn-emulator-modal-title">{if $lng eq 'eng'}Online emulator{else}Online эмулятор{/if}</h2>
			<button type="button" class="smn-emulator-modal-close" data-smn-emulator-close="1" aria-label="{if $lng eq 'eng'}Close{else}Закрыть{/if}">×</button>
		</div>
		<div class="smn-emulator-modal-body">
			<p id="smn-emulator-loading" class="smn-emulator-loading">{if $lng eq 'eng'}Loading emulator…{else}Загрузка эмулятора…{/if}</p>
			<p id="smn-emulator-error" class="smn-emulator-error" hidden></p>
			<iframe id="smn-emulator-iframe" class="smn-emulator-canvas" frameborder="0" scrolling="no" allow="autoplay" style="display:none"></iframe>
		</div>
	</div>
</div>
<script src="{$host}js/smn-emulator.js?{$smarty.now}"></script>
{/if}

</body>
</html>
