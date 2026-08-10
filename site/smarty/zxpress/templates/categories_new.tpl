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
				<a class="smn-brand" href="{$categories_catalog_url}">
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
{if $category && !$category_not_found}
				<a href="{$categories_catalog_url}">{if $lng eq 'eng'}Categories{else}Категории{/if}</a>
{foreach from=$category_breadcrumbs item=bc name=bc}
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
{if $smarty.foreach.bc.last}
				<span class="smn-breadcrumb-current">{$bc.display_name|escape:'html'}</span>
{else}
				<a href="{$bc.public_url}">{$bc.display_name|escape:'html'}</a>
{/if}
{/foreach}
{else}
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Categories{else}Категории{/if}</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
{if $category_not_found}

			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Category not found{else}Категория не найдена{/if}</h1>
				<p><a href="{$categories_catalog_url}">{if $lng eq 'eng'}All categories{else}К каталогу категорий{/if}</a></p>
			</section>

{elseif !$category}

			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Ezine article categories{else}Категории статей журналов{/if}</h1>
				<p class="smn-lead" id="smn-lead">
					{if $lng eq 'eng'}
						Browse articles from ZX Spectrum magazines and newspapers by topic.
					{else}
						Статьи из журналов и газет для ZX Spectrum, сгруппированные по темам.
					{/if}
				</p>
			</section>

{if $category_tree && $category_tree|@count gt 0}
{function name=smn_ec_tree level=0}
			<ul class="smn-cat-tree">
{foreach from=$data item=c}
				<li class="smn-cat-tree-item">
					<a class="smn-cat-tree-link smn-cat-tree-link--l{$level}" href="{$c.public_url}">{$c.display_name|escape:'html'}{if $c.articles_count gt 0}<sup class="smn-filter-count">{$c.articles_count}</sup>{/if}</a>
{if $c.tree}{smn_ec_tree data=$c.tree level=$level+1}{/if}
				</li>
{/foreach}
			</ul>
{/function}
			<nav class="smn-cat-tree-wrap" aria-label="{if $lng eq 'eng'}Category tree{else}Дерево категорий{/if}">
				<ul class="smn-cat-tree smn-cat-tree--fake-root">
					<li class="smn-cat-tree-item">
						<span class="smn-cat-tree-label">{if $lng eq 'eng'}Categories{else}Категории{/if}</span>
{smn_ec_tree data=$category_tree}
					</li>
				</ul>
			</nav>
{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No categories yet.{else}Категорий пока нет.{/if}</p>
{/if}

{else}

			<section class="smn-hero smn-hero--compact">
				<h1>{$category_title|escape:'html'}</h1>
{if $category_description_html}
				<div class="smn-lead" id="smn-lead">{$category_description_html nofilter}</div>
{/if}
			</section>

{if $category_image_url}
			<figure class="smn-cat-image">
				<img src="{$category_image_url}" alt="{$category_title|escape:'html'}" loading="lazy">
			</figure>
{/if}

{if $category_articles && $category_articles|@count gt 0}
			<section class="smn-cat-articles" aria-label="{if $lng eq 'eng'}Articles{else}Статьи{/if}">
				<h2 class="smn-cat-articles-heading">{if $lng eq 'eng'}Articles{else}Статьи{/if} ({$category_articles|@count})</h2>
{foreach from=$category_articles item=a}
{if !$title_only && $a.show_rule}
				<div class="smn-updates-rule" aria-hidden="true"></div>
{/if}
{if !$title_only && $a.show}
				<div class="smn-cat-issue">
					<a class="smn-cat-issue-link" href="{$a.issue_public_url}">{$a.press_name_plain|escape:'html'} #{$a.issue_title_plain|escape:'html'}</a>
{if $a.date && $a.date neq "01 января 1970" && $a.date neq "01 January 1970"}
					<span class="smn-cat-issue-date">{$a.date}</span>
{/if}
				</div>
{/if}
				<div class="smn-cat-article{if $title_only} smn-cat-article--titles-only{/if}">
{if $lng eq 'eng'}
					<a href="{$a.article_public_url}">{$a.title_eng_list nofilter}</a>
{else}
					<a href="{$a.article_public_url}">{$a.title_list nofilter}</a>
{/if}
				</div>
{/foreach}
			</section>
{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No articles in this category yet.{else}В этой категории пока нет статей.{/if}</p>
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
