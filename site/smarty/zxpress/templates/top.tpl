{if $lng eq 'eng'}
	<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
{else}
	<html lang="ru" xml:lang="ru" xmlns="http://www.w3.org/1999/xhtml">
{/if}

<head>
	<title>{$title|strip_tags} {$keywords}</title>

	{* <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"> *}

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

	<link href="{$host}img/style.css?&{$smarty.now}" type=text/css rel=stylesheet>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.5.2/jquery.min.js"></script>
	<script language=javascript src="{$host}img/zxpress.js?{$smarty.now}" type="text/javascript"></script>
</head>

<body>


	<div class="page">

		<div class="top">
			<div class="top-menu">
				{if $lng eq 'eng'}
					<div class="top-menu-line"><a href="/">Library</a></div>
					<div class="top-menu-line">
						<a href="{$host}books.php{$sl}">books</a> ▪
						<a href="{$host}ezines.php{$sl}">magazines</a> ▪
						<a href="{$host}zxnet{$sl}">zxnet</a>
					</div>
					{if $login eq 1}
					<div class="top-menu-line">
						<form method="post" action="{$host}logout.php" style="display:inline;margin:0">
							<input type="hidden" name="csrf_token" value="{$csrf_token}">
							<button type="submit" style="background:none;border:none;padding:0;font:inherit;cursor:pointer;color:inherit;text-decoration:underline">Log out ({$username})</button>
						</form>
					</div>
					{/if}
				{else}
					<div class="top-menu-line">
						<a href="{$host}ezines.php{$sl}">Пресса</a> ▪
						<a href="{$host}books.php{$sl}">Книги</a> ▪
						<a href="{$host}snailmail.php{$sl}">Письма</a> ▪
						<a href="{$host}zxnet{$sl}">ZXNet</a>
					</div>
				{/if}


				<div class="top-menu-line"></div>
				{if $lng eq 'eng'}
					<div class="top-menu-line"><a href="{$host}news{$sl}">News</a></div>
					<div class="top-menu-line"><a href="{$host}updates.php{$sl}">Updates</a></div>
					<div class="top-menu-line"><a href="{$host}gallery.php{$sl}">Gallery</a></div>
					<div class="top-menu-line"><a href="{$host}chronology.php{$sl}">Hronology</a></div>
					<div class="top-menu-line"><a href="{$host}stats.php{$sl}">Stats</a></div>
					<div class="top-menu-line"><a href="{$host}guestbook.php{$sl}">Guestbook</a></div>
					<div class="top-menu-line"><a href="{$host}whois.php{$sl}">?</a></div>
				{else}
					{if $login eq 1}
					<div class="top-menu-line">
						<form method="post" action="{$host}logout.php" style="display:inline;margin:0">
							<input type="hidden" name="csrf_token" value="{$csrf_token}">
							<button type="submit" style="background:none;border:none;padding:0;font:inherit;cursor:pointer;color:inherit;text-decoration:underline">Выйти ({$username})</button>
						</form>
					</div>
					{/if}
					<div class="top-menu-line" style="opacity: 0.5"><a href="https://t.me/zxpress" target="_blank">Мы в
							телеграме</a></div>
					{* style="padding: 0 8px; border-left: 8px solid black; border-right: 8px solid black;" *}
					{* <div class="top-menu-line"><a href="{$host}news">Новости</a></div> *}
					<div class="top-menu-line"><a href="{$host}updates.php">Обновления</a></div>
					<div class="top-menu-line"><a href="{$host}gallery.php">Галерея</a></div>
					<div class="top-menu-line"><a href="{$host}chronology.php">Хронология</a></div>
					<div class="top-menu-line"><a href="{$host}stats.php">Статистика</a></div>
					<div class="top-menu-line"><a href="{$host}guestbook.php">Гостевая</a></div>
					<div class="top-menu-line"><a href="{$host}whois.php">?</a></div>
				{/if}
			</div>
		</div>



		<br>

		<div class="content">
			<div class='col-left'>

				{literal}
					<div style="text-align: left">
						<!-- <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<ins class="adsbygoogle"
     style="display:inline-block;width:640px;height:90px"
     data-ad-client="ca-pub-4566303154078986"
     data-ad-slot="3254804951"></ins>
<script>
(adsbygoogle = window.adsbygoogle || []).push({});
</script>
 -->
					</div>
{/literal}
