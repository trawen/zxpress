<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
{else}
<html lang="ru" xml:lang="ru" xmlns="http://www.w3.org/1999/xhtml">
{/if}

<head>
	<title>{$title|strip_tags} {$keywords}</title>

	<meta name="viewport" content="width=device-width, initial-scale=1">

	{if $smarty.server.SCRIPT_NAME eq '/article.php'}

		<link rel="alternate" hreflang="ru" href="http://zxpress.ru/article.php?id={$id}" />
		<link rel="alternate" hreflang="en" href="http://zxpress.ru{$url_eng}" />

		{literal}

			<script type="text/javascript">
				(function(w, d, n, s, t) {
					w[n] = w[n] || [];
					w[n].push(function() {
						Ya.Context.AdvManager.render({
							blockId: "R-A-575149-1",
							renderTo: "yandex_rtb_R-A-575149-1",
							async: true
						});
					});
					t = d.getElementsByTagName("script")[0];
					s = d.createElement("script");
					s.type = "text/javascript";
					s.src = "//an.yandex.ru/system/context.js";
					s.async = true;
					t.parentNode.insertBefore(s, t);
				})(this, this.document, "yandexContextAsyncCallbacks");
			</script>
			<script async src="https://yastatic.net/pcode-native/loaders/loader.js"></script>
		{/literal}

	{/if}


	<meta http-equiv=content-type content="text/html; charset=utf-8">
	<meta name="keywords" content="ZX Spectrum Spektrum Спектрум Spеccy Спекки Z80 {$keywords}" />
	<meta name="description" content="{$description|default:$title|strip_tags}" />

	{if $og_title}
	<meta property="og:title" content="{$og_title}" />
	<meta property="og:description" content="{$og_description}" />
	<meta property="og:site_name" content="ZXPRESS" />
	<meta property="og:type" content="{$og_type}" />
	<meta property="og:url" content="{$og_url}" />
	{if $og_image}
	<meta property="og:image" content="{$og_image}" />
	{/if}
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="{$og_title}">
	<meta name="twitter:description" content="{$og_description}">
	<meta name="twitter:site" content="@zxpressru">
	{if $og_image}
	<meta name="twitter:image" content="{$og_image}">
	<meta name="twitter:image:alt" content="{$og_title}">
	{/if}
	{/if}

	<link rel="preload" href="{$host}img/style.css?&{$smarty.now}" as="style">
	<link href="{$host}img/style.css?&{$smarty.now}" rel="stylesheet">
	<link rel="preload" href="{$host}img/mobile-logo.webp" as="image" fetchpriority="high" media="(max-width: 960px)">
</head>

<body>


	<div class="page">

		<header class="top">
			<a href="{$host}" class="top-logo-mobile" aria-label="ZXPRESS">
				<span class="top-logo-mobile-img" aria-hidden="true"></span>
			</a>
			<div class="top-menu">
				{if $lng eq 'eng'}
					<div class="top-menu-primary">
					<div class="top-menu-line"><a href="/">Library</a></div>
					<div class="top-menu-line">
						<a href="{$host}books.php{$sl}">books</a> ▪
						<a href="{$host}ezines.php{$sl}">magazines</a> ▪
						<a href="{$host}zxnet{$sl}">zxnet</a>
					</div>
					</div>
					<div class="top-menu-extra-wrap">
					<button type="button" class="top-menu-toggle" aria-expanded="false" aria-controls="top-menu-extra" aria-label="Menu">
						<span class="top-menu-toggle-icon" aria-hidden="true"></span>
					</button>
					<div id="top-menu-extra" class="top-menu-extra">
					{if $login eq 1}
					<div class="top-menu-line">
						<form method="post" action="{$host}logout.php" class="form-inline">
							<input type="hidden" name="csrf_token" value="{$csrf_token}">
							<button type="submit" class="btn-link">Log out ({$username})</button>
						</form>
					</div>
					{/if}
					<div class="top-menu-line"><a href="{$host}news{$sl}">News</a></div>
					<div class="top-menu-line"><a href="{$host}updates.php{$sl}">Updates</a></div>
					<div class="top-menu-line"><a href="{$host}gallery.php{$sl}">Gallery</a></div>
					<div class="top-menu-line"><a href="{$host}chronology.php{$sl}">Hronology</a></div>
					<div class="top-menu-line"><a href="{$host}stats.php{$sl}">Stats</a></div>
					<div class="top-menu-line"><a href="{$host}guestbook.php{$sl}">Guestbook</a></div>
					<div class="top-menu-line"><a href="{$host}whois.php{$sl}">?</a></div>
					</div>
					</div>
				{else}
					<div class="top-menu-primary">
					<div class="top-menu-line top-menu-line--primary">
						<a href="{$host}ezines.php{$sl}" class="top-menu-item"><span class="top-menu-item-icon top-menu-item-icon--ezines" aria-hidden="true"></span><span class="top-menu-label-desktop">Пресса</span><span class="top-menu-label-mobile">Электронные журналы и газеты</span></a><span class="top-menu-sep"> ▪</span>
						<a href="{$host}books.php{$sl}" class="top-menu-item"><span class="top-menu-item-icon top-menu-item-icon--books" aria-hidden="true"></span><span class="top-menu-label-desktop">Книги</span><span class="top-menu-label-mobile">Книги и бумажные журналы</span></a><span class="top-menu-sep"> ▪</span>
						<a href="{$host}snailmail.php{$sl}" class="top-menu-item"><span class="top-menu-item-icon top-menu-item-icon--letters" aria-hidden="true"></span><span class="top-menu-label-desktop">Бумажные письма</span><span class="top-menu-label-mobile">Бумажные письма</span></a><span class="top-menu-sep"> ▪</span>
						<a href="{$host}zxnet{$sl}" class="top-menu-item"><span class="top-menu-item-icon top-menu-item-icon--zxnet" aria-hidden="true"></span><span class="top-menu-label-desktop">ZXNet</span><span class="top-menu-label-mobile">ZXNet эхоконференции</span></a>
					</div>
					</div>
					<div class="top-menu-extra-wrap">
					<button type="button" class="top-menu-toggle" aria-expanded="false" aria-controls="top-menu-extra" aria-label="Меню">
						<span class="top-menu-toggle-icon" aria-hidden="true"></span>
					</button>
					<div id="top-menu-extra" class="top-menu-extra">
					{if $login eq 1}
					<div class="top-menu-line">
						<form method="post" action="{$host}logout.php" class="form-inline">
							<input type="hidden" name="csrf_token" value="{$csrf_token}">
							<button type="submit" class="btn-link">Выйти ({$username})</button>
						</form>
					</div>
					{/if}
					<div class="top-menu-line u-faded"><a href="https://t.me/zxpress" target="_blank">Мы в
							телеграме</a></div>
					{* <div class="top-menu-line"><a href="{$host}news">Новости</a></div> *}
					<div class="top-menu-line"><a href="{$host}updates.php">Обновления</a></div>
					<div class="top-menu-line"><a href="{$host}gallery.php">Галерея</a></div>
					<div class="top-menu-line"><a href="{$host}chronology.php">Хронология</a></div>
					<div class="top-menu-line"><a href="{$host}stats.php">Статистика</a></div>
					<div class="top-menu-line"><a href="{$host}guestbook.php">Гостевая</a></div>
					<div class="top-menu-line"><a href="{$host}whois.php">?</a></div>
					</div>
					</div>
				{/if}
			</div>
		</header>



		<br>

		<div class="content">
			<main class="col-left" id="main">

				{literal}
					<div class="u-text-left">
						<!-- <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<ins class="adsbygoogle"
     class="u-hidden"
     data-ad-client="ca-pub-4566303154078986"
     data-ad-slot="3254804951"></ins>
<script>
(adsbygoogle = window.adsbygoogle || []).push({});
</script>
 -->
					</div>
{/literal}
