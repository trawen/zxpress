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
						<a class="smn-nav-item is-active" href="{$books_catalog_url}">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
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
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$books_catalog_url}">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
{if $press}
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$press.public_url}">{$press.title1|strip_tags}</a>
{/if}
{if !$article_not_found}
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$article.ch_title_plain}</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
{if $article_not_found}
			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Chapter not found{else}Глава не найдена{/if}</h1>
				<p><a href="{$books_catalog_url}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>
			</section>
{else}
			<article class="smn-article smn-book-chapter">
				<header class="smn-article-hero">
					<div class="smn-article-header">
						<h1 class="smn-article-title">{$article.ch_title nofilter}</h1>
						<div class="smn-article-meta">
{if $press}
							<a class="smn-article-meta-press" href="{$press.public_url}">{$press.title1}</a>
{/if}
{if $press && $press.date_label}
							<span class="smn-article-meta-sep" aria-hidden="true">·</span>
							<span class="smn-article-meta-date">{$press.date_label}</span>
{/if}
						</div>
					</div>
{if $press && $press.cover_src}
					<div class="smn-article-cover smn-book-chapter-cover">
						<a href="{$press.public_url}">
							<img src="{$press.cover_src}" alt="{$press.title1|strip_tags|escape:'html'}" width="96" height="128">
						</a>
					</div>
{/if}
				</header>

{if $article_tags && $article_tags|@count gt 0}
				<nav class="smn-article-categories" aria-label="{if $lng eq 'eng'}Topics{else}Темы{/if}">
					<span class="smn-book-chapter-tags-label">{if $lng eq 'eng'}Topics{else}Темы{/if}</span>
{foreach from=$article_tags item=tag}
					<a href="{$host}articles_list.php?tag={$tag.id_tag}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$tag.tag_name}</a>
{/foreach}
				</nav>
{/if}

				<div class="smn-article-body">
					<pre class="smn-article-text" id="text">{$article.text nofilter}</pre>
				</div>

				<footer class="smn-article-footer">
{if $other_articles && $other_articles|@count gt 0}
					<section class="smn-article-related smn-press-toc" aria-label="{if $lng eq 'eng'}Contents{else}Содержание{/if}">
						<h2 class="smn-press-toc-heading">{if $lng eq 'eng'}Contents:{else}Содержание:{/if}</h2>
						<ol class="smn-press-articles">
{foreach from=$other_articles item=ch}
							<li class="smn-press-article">
{if $ch.current}
								<span class="is-active">{$ch.ch_title nofilter}</span>
{else}
								<a href="{$ch.public_url}">{$ch.ch_title nofilter}</a>
{/if}
							</li>
{/foreach}
						</ol>
					</section>
{/if}
				</footer>
			</article>
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
