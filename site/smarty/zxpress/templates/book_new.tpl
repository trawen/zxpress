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
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл. журналы{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/periodicals">{if $lng eq 'eng'}Periodicals{else}Периодика{/if}</a>
						<a class="smn-nav-item is-active" href="{$books_catalog_url}">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
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
				<a href="{$books_catalog_url}">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
{if $press}
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$press.title1|strip_tags}</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
{if $book_not_found || !$press}

			<section class="smn-empty">
				<h1>{if $lng eq 'eng'}Book not found{else}Книга не найдена{/if}</h1>
				<p><a href="{$books_catalog_url}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>
			</section>

{else}

			<article class="smn-book">
				<div class="smn-book-layout">
{if $screens && $screens|@count gt 0}
					<div class="smn-book-covers">
					{foreach from=$screens item=shot}
						<figure class="smn-book-cover-full">
							<img src="{$shot.img_src}" alt="{$press.title1|strip_tags|escape:'html'}" width="256" height="360" loading="lazy" decoding="async">
						</figure>
					{/foreach}
					</div>
{/if}
					<div class="smn-book-content">
						<h1 class="smn-book-title">{$press.title1}</h1>
						{if $press.title2}<p class="smn-book-subtitle-lg">{$press.title2}</p>{/if}

						<dl class="smn-book-facts">
						{if $press.series}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}Series{else}Серия{/if}</dt>
								<dd>{$press.series}</dd>
							</div>
						{/if}
						{if $press.year_display}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}Year{else}Год{/if}</dt>
								<dd>{$press.year_display}</dd>
							</div>
						{/if}
						{if $press.publisher_display}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}Publisher{else}Издательство{/if}</dt>
								<dd>{$press.publisher_display}</dd>
							</div>
						{/if}
						{if $press.city_display}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}City{else}Город{/if}</dt>
								<dd>{$press.city_display}</dd>
							</div>
						{/if}
						{if $press.authors}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}Authors{else}Авторы{/if}</dt>
								<dd>{$press.authors}</dd>
							</div>
						{/if}
						{if $press.pages}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}Pages{else}Объём{/if}</dt>
								<dd>{if $lng eq 'eng'}{$press.pages} pages{else}{$press.pages} страниц{/if}</dd>
							</div>
						{/if}
						{if $press.circulation}
							<div class="smn-book-fact">
								<dt>{if $lng eq 'eng'}Print run{else}Тираж{/if}</dt>
								<dd>{if $lng eq 'eng'}{$press.circulation} copies{else}{$press.circulation} экземпляров{/if}</dd>
							</div>
						{/if}
						{if $press.isbn}
							<div class="smn-book-fact">
								<dt>ISBN</dt>
								<dd>{$press.isbn}</dd>
							</div>
						{/if}
						</dl>

						{if $press.annotation}
						<div class="smn-book-annotation">
							<h2 class="smn-book-section-title">{if $lng eq 'eng'}Publisher’s summary{else}Аннотация издательства{/if}</h2>
							<div class="smn-book-annotation-body">{$press.annotation nofilter}</div>
						</div>
						{/if}

						{if $files && $files|@count gt 0}
						<div class="smn-book-files">
							<h2 class="smn-book-section-title">{if $lng eq 'eng'}Downloads{else}Скачать{/if}</h2>
							<ul class="smn-book-file-list">
							{foreach from=$files item=file}
								<li>
									<a class="smn-book-file" href="{$file.public_url}">
										<span class="smn-book-file-name">{$file.file_name}</span>
										<span class="smn-book-file-meta">{$file.file_type_label} · {$file.file_size} Kb</span>
									</a>
								</li>
							{/foreach}
							</ul>
						</div>
						{/if}

						{if $other_articles && $other_articles|@count gt 0}
						<section class="smn-press-toc" aria-label="{if $lng eq 'eng'}Contents{else}Содержание{/if}">
							<h2 class="smn-press-toc-heading">{if $lng eq 'eng'}Contents:{else}Содержание:{/if}</h2>
							<ol class="smn-press-articles">
							{foreach from=$other_articles item=ch}
								<li class="smn-press-article">
									<a href="{$ch.public_url}">{$ch.ch_title nofilter}</a>
								</li>
							{/foreach}
							</ol>
						</section>
						{/if}
					</div>
				</div>
			</article>

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
