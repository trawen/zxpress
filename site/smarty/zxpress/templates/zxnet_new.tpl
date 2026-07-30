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
				<a class="smn-brand" href="{$zxnet_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл.пресса{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/books">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
						<a class="smn-nav-item is-active" href="{$zxnet_catalog_url}">ZXNet</a>
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
{if $zxnet_view eq 'topic'}
				<a href="{$zxnet_catalog_url}">ZXNet</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<a href="{$zxnet_echo_url}">{$echo.title}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$subj_title|default:'###'}</span>
{elseif $zxnet_view eq 'echo'}
				<a href="{$zxnet_catalog_url}">ZXNet</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$echo.title}</span>
{else}
				<span class="smn-breadcrumb-current">ZXNet</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
{if $zxnet_view eq 'topic'}

			<article class="smn-zxnet-topic">
				<h1 class="smn-zxnet-topic-title">{$subj_title|default:'###'}</h1>
				<p class="smn-zxnet-topic-echo">
					{if $lng eq 'eng'}ZXNet echo conference{else}ZXNet эхоконференция{/if}
					«<a href="{$zxnet_echo_url}">{$echo.title}</a>»
				</p>

				{if $topic && $topic|@count gt 0}
				<div class="smn-zxnet-messages">
				{foreach from=$topic item=msg}
					<section class="smn-zxnet-msg">
						<p class="smn-zxnet-msg-meta">
							<span class="smn-zxnet-msg-row">
								<span class="smn-zxnet-msg-label">{if $lng eq 'eng'}From{else}От{/if}</span>
								<span class="smn-zxnet-msg-name">{$msg.name_from}</span>
							</span>
							<span class="smn-zxnet-msg-sep" aria-hidden="true">→</span>
							<span class="smn-zxnet-msg-row">
								<span class="smn-zxnet-msg-label">{if $lng eq 'eng'}To{else}Кому{/if}</span>
								<span class="smn-zxnet-msg-name">{$msg.name_to}</span>
							</span>
							<span class="smn-zxnet-msg-date">{$msg.date}</span>
						</p>
						<div class="smn-zxnet-msg-body">{$msg.text nofilter}</div>
					</section>
				{/foreach}
				</div>
				{else}
				<p class="smn-empty-note">{if $lng eq 'eng'}No messages in this topic.{else}В этой теме пока нет сообщений.{/if}</p>
				{/if}
			</article>

{elseif $zxnet_view eq 'echo'}

			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}ZXNet echo conference «{$echo.title}»{else}ZXNet эхоконференция «{$echo.title}»{/if}</h1>
				{if $echo.description}
				<div class="smn-lead is-collapsible" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">
					<p>{$echo.description nofilter}</p>
				</div>
				{/if}
			</section>

			{if $subjs_by_year && $subjs_by_year|@count gt 0}
			<nav class="smn-year-nav" aria-label="{if $lng eq 'eng'}Years{else}Годы{/if}">
				<span class="smn-year-nav-label">{if $lng eq 'eng'}Years{else}Годы{/if}</span>
				<div class="smn-year-nav-list">
{foreach from=$subjs_by_year item=group}
					<a class="smn-year-nav-link" href="#zxnet-year-{$group.year}">{$group.year_label}<sup class="smn-filter-count">{$group.topic_count}</sup></a>
{/foreach}
				</div>
			</nav>
			<div class="smn-zxnet-years">
{foreach from=$subjs_by_year item=group}
				<section class="smn-zxnet-year" aria-labelledby="zxnet-year-{$group.year}">
					<h2 class="smn-year-heading" id="zxnet-year-{$group.year}">{$group.year_label}<sup class="smn-filter-count">{$group.topic_count}</sup></h2>
					<ul class="smn-list">
{foreach from=$group.items item=row}
						<li class="smn-list-item">
							<a class="smn-list-card" href="{$row.public_url}">
								<h3 class="smn-list-title">{$row.title|default:'###'}</h3>
								<span class="smn-list-meta">
									<span class="smn-list-meta-main">
										{if $lng eq 'eng'}{$row.nm} messages{else}{$row.nm} сообщ.{/if}
									</span>
									<span class="smn-list-meta-date">{if $lng eq 'eng'}{$row.date_range}{else}{$row.date_from}{/if}</span>
								</span>
							</a>
						</li>
{/foreach}
					</ul>
				</section>
{/foreach}
			</div>
			{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No topics in this echo yet.{else}В этой эхе пока нет тем.{/if}</p>
			{/if}

{else}

			<section class="smn-hero">
				<h1>{if $lng eq 'eng'}Archive of ZXNet echo conferences{else}Архив эхоконференций сети ZXNet{/if}</h1>
				<div class="smn-lead is-collapsible" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">
				{if $lng eq 'eng'}
				<p>This section is dedicated to the echomail conferences of ZXNet, the largest non-commercial network uniting ZX Spectrum users across the former Soviet Union. It contains archives of conferences where users discussed programming, hardware, games, the demoscene, and everyday life.</p>

<p>ZXNet was founded in Moscow in the spring of 1995 on the basis of several independent BBS systems. The idea of объединing them into a single network with common standards was proposed by Alexey Ivanov (Alex Research), while the On-Line electronic newspaper, published by Dmitry Grigoriev, became the network's coordinating center. Automatic mail exchange with Fidonet was soon established, and ZXNet evolved into a full-fledged FTN network with its own address zone — 500 (for example, 500:95/462.53).</p>

<p>Over time, ZXNet expanded to dozens of cities across the former USSR, including Moscow, Saint Petersburg, Minsk, Lviv, Kharkiv, Tashkent, and many others. At its peak, the network connected around 2,000 modem users.</p>

<p>Regional segments often developed independently and implemented their own technical solutions. The best-known example was SPbZXNet, created in Saint Petersburg by Omega Group using Vicomm modems and its own software. Despite these differences, regional networks gradually became part of the overall ZXNet infrastructure.</p>

<p>ZXNet was built around a star topology with a central hub and regional nodes. In later years, the network expanded beyond dial-up connections by supporting Internet access and e-mail-based point systems.</p>
				{else}
			<p>Раздел посвящён эхоконференциям сети ZXNet — некоммерческой сети, объединявшей пользователей компьютеров ZX Spectrum в странах бывшего СССР. Здесь собраны архивы эх, в которых обсуждались программирование, аппаратное обеспечение, игры, демосцена и повседневная жизнь спектрумистов.</p>

<p>ZXNet возникла весной 1995 года в Москве на базе разрозненных BBS-станций. Инициатором объединения их в единую сеть с общими правилами стал Алексей Иванов (Alex Research), а координацию взяла на себя электронная газета «On-Line» Дмитрия Григорьева. Вскоре была организована автоматическая пересылка почты между ZXNet и Fidonet, после чего сеть превратилась в полноценную FTN-сеть с собственной зоной адресации — 500 (например, 500:95/462.53).</p>

<p>Со временем узлы ZXNet появились в десятках городов бывшего СССР — от Москвы, Санкт-Петербурга, Минска и Ташкента до Львова, Владивостока, Екатеринбурга, Самары, Горно-Алтайска и небольших региональных центров. В период наибольшего развития сеть объединяла около двух тысяч пользователей модемной связи.</p>

<p>Отдельные региональные сегменты развивались независимо и нередко использовали собственные технические решения. Наиболее известным примером стала SPbZXNet, созданная в Санкт-Петербурге группой Omega на основе модемов Vicomm и собственного программного обеспечения. Несмотря на различия в реализации, все региональные сети постепенно интегрировались в общую структуру ZXNet.</p>

<p>В основе сети лежала звездообразная архитектура с центральным хабом и региональными узлами. Позднее, помимо модемной связи, появились поинты с доставкой почты по электронной почте и доступ к сети через Интернет.</p>
				{/if}
				</div>
			</section>

			{if $echos && $echos|@count gt 0}
			<ul class="smn-list">
			{foreach from=$echos item=row}
				<li class="smn-list-item">
					<a class="smn-list-card" href="{$row.public_url}">
						<h2 class="smn-list-title">{$row.title}</h2>
						<span class="smn-list-meta">
							<span class="smn-list-meta-main">
								{if $lng eq 'eng'}{$row.nm} messages{else}{$row.nm} сообщений{/if}
							</span>
							<span class="smn-list-meta-date">{if $lng eq 'eng'}{$row.date_range}{else}{$row.date_from}{/if}</span>
						</span>
					</a>
				</li>
			{/foreach}
			</ul>
			{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No echo conferences here yet.{else}Здесь пока нет эхоконференций.{/if}</p>
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
