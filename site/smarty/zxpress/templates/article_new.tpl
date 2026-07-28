<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en">
{else}
<html lang="ru">
{/if}
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{$title|strip_tags} — {$press.title_plain} #{$issue.title} — ZXPRESS</title>
	{if $description}<meta name="description" content="{$description|strip_tags}">{/if}
	{smn_styles}

	<meta property="og:description" content="{$description|escape:'html'}" />
	<meta property="og:title" content="{$article.title_plain_meta}" />
	<meta property="og:site_name" content="ZXPRESS" />
	<meta property="og:type" content="article" />
	<meta property="og:url" content="{$canonical_url}" />
	{if $screens.id}<meta property="og:image" content="{$host}screens/1/{$screens.id}.{$screens.format}" />{/if}

	<meta name="twitter:description" content="{$description|escape:'html'}">
	<meta name="twitter:title" content="{$article.title_plain_meta}">
	<meta name="twitter:site" content="@zxpressru">
	<meta name="twitter:creator" content="@zxpressru">
	{if $screens.id}<meta name="twitter:image" content="{$host}screens/1/{$screens.id}.{$screens.format}">{/if}
	<meta name="twitter:image:alt" content="{$article.title_plain_meta}">
	<meta name="twitter:card" content="summary_large_image">

	<link rel="canonical" href="{$canonical_url}">
	<link rel="alternate" hreflang="ru" href="{$hreflang_ru}">
	<link rel="alternate" hreflang="en" href="{$hreflang_en}">
</head>
<body class="smn{if $related_category_articles && $related_category_articles|@count gt 0} smn-has-cat-aside{/if}">
	<div class="smn-frame">
		<header class="smn-header">
			<div class="smn-header-bar">
				<a class="smn-brand" href="{$ezines_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item{if $smn_nav_ezines_active|default:false} is-active{/if}" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл.пресса{/if}</a>
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
			<nav class="smn-breadcrumbs" id="smn-sticky-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$ezines_catalog_url}">{include file="snailmail_bc_ezines_label.tpl"}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$press_public_url}">{$press.title_plain}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$issue_public_url}">#{$issue.title}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{if $lng eq 'eng' && $article.title_eng_plain_meta}{$article.title_eng_plain_meta}{else}{$article.title_plain_meta}{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
{if $article_not_found}
			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Article not found{else}Статья не найдена{/if}</h1>
				<p><a href="{$ezines_catalog_url}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>
			</section>
{else}
			<div class="smn-article-layout">
			<article class="smn-article">
				<header class="smn-article-hero">
					<div class="smn-article-header">
						<h1 class="smn-article-title">{if $lng eq 'eng'}{$article.title_eng_html nofilter}{else}{$article.title_html nofilter}{/if}</h1>
						{if $issue.date && $issue.date neq "01 января 1970" && $issue.date neq "01 January 1970"}
							<div class="smn-article-meta">
								<span class="smn-article-meta-date">{$issue.date}</span>
							</div>
						{/if}
					</div>
					{if $screens.id}
						<div class="smn-article-cover">
							<img
								class="smn-gallery-img"
								src="{$host}screens/1/{$screens.id}.{$screens.format}"
								alt="{$press.title_plain} #{$issue.title}"
								width="256"
								height="192"
								loading="lazy"
								decoding="async"
							>
						</div>
					{/if}
				</header>

				{if $ezine_category_branch}
					<nav class="smn-article-categories" aria-label="{if $lng eq 'eng'}Categories{else}Категории{/if}">
						<a href="{if $lng eq 'eng'}/en{else}/ru{/if}/categories">{if $lng eq 'eng'}Categories{else}Категории{/if}</a>
						{foreach from=$ezine_category_branch item=bc name=ecb}
							<span class="smn-article-meta-sep" aria-hidden="true">→</span>
							<a href="{$bc.public_url}">{$bc.name|escape:'html'}</a>
						{/foreach}
					</nav>
				{/if}

				<div class="smn-article-body">
					{if $article.temp and $roskom neq "fuck"}
						<div class="smn-article-blocked">
							{if $lng eq 'eng'}
								This content is blocked in accordance with local laws.
							{else}
								Искомый адрес внесен в реестр на основаниях, предусмотренных статьей
								15.1 Федерального закона от 27 июля 2006 года No 149-ФЗ
							{/if}
						</div>
					{else}
						<pre class="smn-article-text" id="text">{$article.text nofilter}</pre>
					{/if}
				</div>

				<footer class="smn-article-footer">
					<div class="ya-share2" data-services="vkontakte,facebook,odnoklassniki" data-counter=""
						{if $screens.id}data-image="{$host}screens/1/{$screens.id}.{$screens.format}"{/if}></div>

					{if $other_articles}
						<section class="smn-article-related">
							<h2 class="smn-press-toc-heading">{if $lng eq 'eng'}Issue contents:{else}Содержание номера:{/if}</h2>
							<ul class="smn-press-articles">
								{foreach from=$other_articles item=art}
									<li class="smn-press-article">
										{if $art.current}
											<span class="is-active">{if $lng eq 'eng'}{$art.title_eng_html nofilter}{else}{$art.title_html nofilter}{/if}</span>
										{else}
											<a href="{$art.public_url}">{if $lng eq 'eng'}{$art.title_eng_html nofilter}{else}{$art.title_html nofilter}{/if}</a>
										{/if}
									</li>
								{/foreach}
							</ul>
						</section>
					{/if}
				</footer>
			</article>

{if $related_category_articles && $related_category_articles|@count gt 0}
			{* TEMP: related articles from the same category *}
			<aside class="smn-article-cat-aside" aria-label="{if $lng eq 'eng'}More from this category{else}Ещё из этой категории{/if}">
				<h2 class="smn-article-cat-aside-title">
{if $related_category}
					<a href="{$related_category.public_url}">{$related_category.name|escape:'html'}</a>
{else}
					{if $lng eq 'eng'}Same category{else}Та же категория{/if}
{/if}
				</h2>
				<ul class="smn-article-cat-aside-list">
{foreach from=$related_category_articles item=rel}
					<li>
						<a href="{$rel.public_url}">
							<span class="smn-article-cat-aside-item-title">{if $lng eq 'eng'}{$rel.title_eng_html nofilter}{else}{$rel.title_html nofilter}{/if}</span>
							<span class="smn-article-cat-aside-item-meta">{$rel.press_name_plain|escape:'html'} #{$rel.issue_title|escape:'html'}</span>
						</a>
					</li>
{/foreach}
				</ul>
			</aside>
{/if}
			</div>
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
</body>
</html>
