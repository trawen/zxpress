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
				<a class="smn-brand" href="{if $lng eq 'eng'}/en{else}/ru{/if}">
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
			<form class="smn-search" method="GET" action="{$search_catalog_url}">
				<div class="smn-search-wrap">
					<label class="smn-search-label" for="input_query_smn">{if $lng eq 'eng'}Search{else}Поиск{/if}</label>
					<input class="smn-search-input" id="input_query_smn" name="q" type="search" placeholder="{if $lng eq 'eng'}Search...{else}Поиск...{/if}" value="{$query|default:''}" autocomplete="off">
					<div id="suggest-smn" class="smn-search-suggest"></div>
				</div>
			</form>
			<nav class="smn-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Search{else}Поиск{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Search{else}Поиск{/if}</h1>
{if $query}
				<p class="smn-hero-sub">{if $lng eq 'eng'}Results for{else}Результаты по запросу{/if} «{$query}»</p>
{/if}
			</section>

			<form class="smn-search-toolbar" method="GET" action="{$search_catalog_url}">
				<input type="hidden" name="q" value="{$query}">
				<div class="smn-search-toolbar-row">
					<label class="smn-search-scope">
						<input type="radio" name="f" value="0" {if $from neq 1}checked{/if} onchange="this.form.submit()">
						{if $lng eq 'eng'}Books &amp; ezines{else}Книги и эл. журналы{/if}
					</label>
					<label class="smn-search-scope">
						<input type="radio" name="f" value="1" {if $from eq 1}checked{/if} onchange="this.form.submit()">
						{if $lng eq 'eng'}ZXNet echoes{else}Эхоконференции ZXNet{/if}
					</label>
					<label class="smn-search-sort" for="smn-search-sort">
						<span class="u-sr-only">{if $lng eq 'eng'}Sort{else}Сортировка{/if}</span>
						<select id="smn-search-sort" name="s" onchange="this.form.submit()">
							<option value="rw" {if $sort eq "rw"}selected{/if}>{if $lng eq 'eng'}By relevance{else}По релевантности{/if}</option>
							<option value="up" {if $sort eq "up"}selected{/if}>{if $lng eq 'eng'}Date ↓{else}По дате ↓{/if}</option>
							<option value="dw" {if $sort eq "dw"}selected{/if}>{if $lng eq 'eng'}Date ↑{else}По дате ↑{/if}</option>
						</select>
					</label>
				</div>
			</form>

{if $found}
			<p class="smn-search-stats">{if $lng eq 'eng'}Results: {$found} ({$time} s){else}Результатов: {$found} ({$time} сек.){/if}</p>

			<section class="smn-search-results" aria-label="{if $lng eq 'eng'}Search results{else}Результаты поиска{/if}">
{section name=n loop=$search}
				<article class="smn-search-item">
{if $from}
					<h2 class="smn-search-item-title">
						<a href="{$host}zxnet/{$search[n].title|escape:'url'}/{$search[n].subj_id}">{$search[n].title}</a>
					</h2>
					<p class="smn-search-item-meta">
						<a href="{$host}zxnet/{$search[n].title|escape:'url'}">{$search[n].name}</a>
{if $search[n].date} · {$search[n].date}{/if}
					</p>
					<div class="smn-search-item-snippet">{$search[n].text nofilter}</div>
{else}
					<div class="smn-search-item-row">
						<div class="smn-search-item-thumb">
{if $search[n].type eq 1}
{if $search[n].img}
							<img src="{$host}screens/1/{$search[n].img}.{$search[n].img_format|default:'png'}" width="64" height="48" alt="">
{else}
							<img src="{$host}img/empty_img.png" width="64" height="48" alt="">
{/if}
{else}
{if $search[n].img}
							<img src="{$host}pictures/thumbs/{$search[n].img}.jpg" width="64" height="48" alt="">
{else}
							<img src="{$host}img/empty_img.png" width="64" height="48" alt="">
{/if}
{/if}
						</div>
						<div class="smn-search-item-body">
							<h2 class="smn-search-item-title">
{if $search[n].type eq 1}
								<a href="{$search[n].article_url}">{$search[n].title}</a>
{else}
								<a href="{$search[n].chapter_url}">{$search[n].title}</a>
{/if}
							</h2>
							<p class="smn-search-item-meta">
{if $search[n].type eq 1}
								<a href="{$search[n].issue_url}">{$search[n].name}</a>
{else}
								<a href="{$search[n].book_url}">{$search[n].name}</a>
{/if}
{if $search[n].date} · {$search[n].date}{/if}
							</p>
							<div class="smn-search-item-snippet">{$search[n].text nofilter}</div>
						</div>
					</div>
{/if}
				</article>
{/section}
			</section>

{if $pages}
			<nav class="smn-pages" aria-label="{if $lng eq 'eng'}Pages{else}Страницы{/if}">
{section name=n loop=$pages}
{if $pages[n].num eq $page}
				<b>{$pages[n].show}</b>
{else}
				<a href="{$search_catalog_url}?q={$query|escape:'url'}&amp;p={$pages[n].num}&amp;s={$sort|escape:'url'}&amp;f={$from|escape:'url'}">{$pages[n].show}</a>
{/if}
{/section}
			</nav>
{/if}

{else}
			<p class="smn-empty-note">
{if $query}
				{if $lng eq 'eng'}Nothing found. Check that all words are spelled correctly.{else}Ничего не найдено. Убедитесь, что все слова написаны без ошибок.{/if}
{else}
				{if $lng eq 'eng'}Enter a search query.{else}Введите поисковый запрос.{/if}
{/if}
			</p>
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
