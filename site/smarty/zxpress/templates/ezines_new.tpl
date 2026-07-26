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
				<a class="smn-brand" href="{$ezines_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item{if $smn_nav_ezines_active|default:false} is-active{/if}" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл. журналы{/if}</a>
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
			<nav class="smn-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{include file="snailmail_bc_ezines_label.tpl"}</span>
			</nav>
		</header>

		<main class="smn-main">

			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Diskmags for ZX Spectrum{else}Электронная пресса для ZX Spectrum{/if}</h1>
				<div class="smn-lead" id="smn-lead" data-collapsed-label="{if $lng eq 'eng'}Show more{else}Показать полностью{/if}" data-expanded-label="{if $lng eq 'eng'}Show less{else}Свернуть{/if}">				{if $lng eq 'eng'}
					<p>This section is devoted to electronic newspapers and magazines (scene ezines, or diskmags) published for the ZX Spectrum from the early 1990s onward. These titles became one of the brightest phenomena of the post-Soviet ZX Spectrum scene, bringing together news, technical knowledge, creativity and lively exchange among thousands of Spectrum users.</p>
					<p>Most publications were runnable programs with loading screens, original interfaces, music and illustrations. Reading them became a unique multimedia experience, typical of the early home-computer era.</p>
					<p>The first electronic magazines appeared on the Western computer scene in the late 1980s, mainly on the Commodore 64, Amiga and Atari ST. The format later spread widely on the ZX Spectrum scene as well, especially in the countries of the former USSR, where amateur newspapers and magazines became an important way to share information within the community.</p>
					<p>Magazines and newspapers were distributed on 5.25&quot; floppy disks, sent by post and through FidoNet/ZXNet networks long before the Internet became widespread. Their pages carried scene news, game and demo reviews, articles on programming and hardware, interviews, readers’ letters, humour, fiction and original writing.</p>
					<p>The split into magazines and newspapers is largely conventional. Many titles sat somewhere in between or changed format over time. Still, most of them shared the following traits:</p>
					<div class="smn-compare-wrap">
						<table class="smn-compare">
							<thead>
								<tr>
									<th>Trait</th>
									<th>Magazine</th>
									<th>Newspaper</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<th scope="row">Purpose</th>
									<td>Extensive thematic pieces, analysis, creative work</td>
									<td>Timely news and scene life</td>
								</tr>
								<tr>
									<th scope="row">Size</th>
									<td>Usually filled a whole floppy disk</td>
									<td>Compact issue</td>
								</tr>
								<tr>
									<th scope="row">Interface</th>
									<td>Complex, with graphics and several fonts</td>
									<td>Simple and functional</td>
								</tr>
								<tr>
									<th scope="row">Frequency</th>
									<td>Rare, irregular</td>
									<td>Frequent, regular</td>
								</tr>
								<tr>
									<th scope="row">Distribution</th>
									<td>Often paid</td>
									<td>Usually free</td>
								</tr>
								<tr>
									<th scope="row">Appendices</th>
									<td>Often included</td>
									<td>Almost never</td>
								</tr>
								<tr>
									<th scope="row">Average issues</th>
									<td>Usually 2–3</td>
									<td>Dozens, sometimes over a hundred</td>
								</tr>
							</tbody>
						</table>
					</div>
				{else}
					<p>Раздел посвящён электронным газетам и журналам (scene ezines, или diskmags), выпускавшимся на ZX Spectrum с начала 1990-х годов. Эти издания стали одним из самых ярких явлений постсоветской ZX Spectrum-сцены, объединив новости, технические знания, творчество и живое общение тысяч спектрумистов.</p>

					<p>Большинство изданий представляли собой запускаемые программы с загрузочными экранами, оригинальным интерфейсом, музыкальным сопровождением и иллюстрациями. Благодаря этому процесс чтения превращался в уникальный мультимедийный опыт, характерный для эпохи первых домашних компьютеров.</p>

						<p>Первые электронные журналы появились на западной компьютерной сцене в конце 1980-х годов, прежде всего на Commodore 64, Amiga, Atari ST. В дальнейшем этот формат получил широкое распространение и на ZX Spectrum-сцене, особенно в странах бывшего СССР, где любительские газеты и журналы стали важным способом обмена информацией внутри сообщества.</p>

					<p>Журналы и газеты распространялись на 5.25 дискетах, пересылались по почте и через сети FidoNet/ZXNet  задолго до широкого распространения Интернета. На их страницах публиковались новости сцены, обзоры игр и демо, статьи по программированию и аппаратному обеспечению, интервью, переписка читателей, юмор, художественные произведения и авторские материалы. </p>

					<p>В основном издания были универсальными и содержали материалы на всевозможные темы связанные с ZX Spectrum. Однако были и тематические издания, например: демосценерские, юмористические, эротические и т.д.</p>

					<p>Деление на журналы и газеты зачастую условно. Некоторые издания занимали промежуточное положение или со временем меняли формат. Тем не менее для большинства из них характерны следующие особенности:</p>

					<div class="smn-compare-wrap">
						<table class="smn-compare">
							<thead>
								<tr>
									<th></th>
									<th>Журнал</th>
									<th>Газета</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<th scope="row">Цель</th>
									<td>Прохождение игр, программирование, обзоры ПО, аналитика, обмен опытом</td>
									<td>Оперативные новости и жизнь сцены</td>
								</tr>
								<tr>
									<th scope="row">Объём</th>
									<td>Обычно занимал целую дискету (640 КБ)</td>
									<td>Десятки килобайт</td>
								</tr>
								<tr>
									<th scope="row">Интерфейс</th>
									<td>Сложный, с графикой и несколькими шрифтами</td>
									<td>Простой и функциональный</td>
								</tr>
								<tr>
									<th scope="row">Периодичность</th>
									<td>Редкая, нерегулярная, раз 5-6 месяцев</td>
									<td>Частая, регулярная, раз в 2-3 недели</td>
								</tr>
								<tr>
									<th scope="row">Распространение</th>
									<td>Нередко платное</td>
									<td>Бесплатное</td>
								</tr>
								<tr>
									<th scope="row">Приложения</th>
									<td>Часто присутствовали</td>
									<td>Практически не встречались</td>
								</tr>
								<tr>
									<th scope="row">Число выпусков</th>
									<td>Обычно 2–3</td>
									<td>Десятки</td>
								</tr>
							</tbody>
						</table>
					</div>
				{/if}</div>
			</section>

			{if $catalog && $catalog|@count gt 0}
			<nav class="smn-az" aria-label="{if $lng eq 'eng'}Jump to letter{else}Перейти к букве{/if}">
			{section name=n loop=$catalog}
				{if $catalog[n].letter}
				<a class="smn-az-item" href="#letter_{$catalog[n].letter}">{$catalog[n].letter}</a>
				{/if}
			{/section}
			</nav>

			<ul class="smn-ezine-index">
			{section name=n loop=$catalog}
			{if $catalog[n].off}
				{if $catalog[n].letter}
				<li class="smn-ezine-index-letter" id="letter_{$catalog[n].letter}">
					<span class="smn-ezine-index-letter-mark">{$catalog[n].letter}</span>
				</li>
				{/if}
				<li class="smn-ezine-index-item{if $catalog[n].online_articles eq 0} is-offline{/if}">
					<a class="smn-ezine-index-row" href="{$catalog[n].public_url}">
						<span class="smn-ezine-index-main">
							<span class="smn-ezine-index-name">{$catalog[n].title_plain}</span>
							{if $catalog[n].issues_count_label}<span class="smn-ezine-index-count">{$catalog[n].issues_count_label}</span>{/if}
						</span>
						<span class="smn-ezine-index-meta">
							{if $catalog[n].city_label}
							<span class="smn-ezine-index-city">
								{if $catalog[n].country_id}<img class="smn-ezine-flag" src="{$host}img/{$catalog[n].country_id}.png" width="16" height="10" alt="">{/if}
								{$catalog[n].city_label}
							</span>
							{/if}
							{if $catalog[n].type_label}<span class="smn-ezine-index-type">{$catalog[n].type_label}</span>{/if}
							{if $catalog[n].years_label}<span class="smn-ezine-index-years">{$catalog[n].years_label}</span>{/if}
						</span>
					</a>
				</li>
			{/if}
			{/section}
			</ul>
			{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No ezines here yet.{else}Здесь пока нет изданий.{/if}</p>
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
