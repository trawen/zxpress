<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<HTML><HEAD><TITLE>{$title}</TITLE>
<META http-equiv=content-type content="text/html; charset=utf-8">
{literal}
<style type="text/css" id="vbulletin_css">
:root {
	--smn-ink: rgba(10, 9, 8, 0.8);
	--smn-muted: rgb(60, 55, 50);
	--smn-paper: rgb(244, 238, 224);
	--smn-paper-deep: rgb(228, 217, 194);
	--smn-gutter: rgb(221, 210, 188);
	--smn-line: rgb(201, 184, 150);
	--smn-accent: rgb(164, 30, 0);
	--smn-accent-soft: rgb(196, 74, 46);
	--smn-lead: #622e1a;
	--smn-body-bg: #eeebcd;
	--smn-surface: #ffffff;
	--smn-brand: #000000;
	--smn-sans: "Proxima Nova", "Helvetica Neue", Helvetica, Arial, sans-serif;
}
a:link, a:visited, body_alink, body_avisited {
	color: var(--smn-accent);
	font-family: Verdana, sans-serif;
}
a:hover, a:active, body_ahover {
	color: var(--smn-accent-soft);
	font-family: Verdana, sans-serif;
}
body {
	font-family: Verdana, sans-serif;
	color: var(--smn-ink);
	background: var(--smn-body-bg);
}
.admin-top-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	padding: 8px 12px;
	margin: 0 0 12px;
	border: 1px solid var(--smn-line);
	background: var(--smn-paper);
	font: 13px Verdana, sans-serif;
	color: var(--smn-ink);
}
.admin-top-nav {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 14px;
	flex: 1 1 420px;
	min-width: 0;
	font-family: var(--smn-sans);
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.02em;
}
.admin-top-nav a:link,
.admin-top-nav a:visited {
	color: var(--smn-ink);
	text-decoration: none;
	font-weight: 600;
	position: relative;
	padding: 4px 0;
	white-space: nowrap;
}
.admin-top-nav a::after {
	content: "";
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	height: 2px;
	background: var(--smn-accent);
	transform: scaleX(0);
	transform-origin: left;
	transition: transform 0.25s ease;
}
.admin-top-nav a:hover::after,
.admin-top-nav a.is-active::after {
	transform: scaleX(1);
}
.admin-top-nav a:hover,
.admin-top-nav a.is-active {
	color: var(--smn-accent);
}
.admin-top-bar form { margin: 0; }
.admin-top-bar button {
	appearance: none;
	background: none;
	border: 0;
	padding: 4px 0;
	font-family: var(--smn-sans);
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.02em;
	color: var(--smn-ink);
	cursor: pointer;
	position: relative;
	white-space: nowrap;
}
.admin-top-bar button::after {
	content: "";
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	height: 2px;
	background: var(--smn-accent);
	transform: scaleX(0);
	transform-origin: left;
	transition: transform 0.25s ease;
}
.admin-top-bar button:hover {
	color: var(--smn-accent);
}
.admin-top-bar button:hover::after {
	transform: scaleX(1);
}
</style>
{/literal}

<META content="MSHTML 6.00.2900.2180" name=GENERATOR></HEAD>
<BODY style="padding-top: 0px; margin-top: 0px; overflow-x: hidden !important; background: var(--smn-body-bg); color: var(--smn-ink);">


<DIV style="padding-top: 16px"></DIV>

<TABLE cellSpacing=0 cellPadding=0 width="100%" border=0>
<TR>
<TD vAlign=top>

{if $login neq 1 or $username eq ""}

<br><br><br><br>

<b><center><h2>Доступ возможен только для авторезированных пользователей!</h2></center></b>

<br>

<b><center><a href="http://zxpress.ru/input.php">» вход «</a></center></b>

<br><br><br><br>
{else}

<div class="admin-top-bar">
<nav class="admin-top-nav">
<a href="admin_books.php">Книги</a>
<a href="admin_publishers.php">Издательства</a>
<a href="admin_periodicals.php">Периодика</a>
<a href="admin_issue.php">Выпуски журналов</a>
<a href="admin_articles.php">Статьи журналов</a>
<a href="admin_articles_new.php">Статьи журналов (new)</a>
<a href="admin_screens.php">Скриншоты журналов</a>
<a href="admin_ezine_categories.php">Категории журналов</a>
<a href="admin_authors.php">Авторы</a>
<a href="admin_book_rubrics.php">Рубрики книг</a>
<a href="admin_letters.php">Письма</a>
<a href="admin_publications.php">Публикации</a>
<a href="/ru/updates-activity">Activity</a>
</nav>
<form method="post" action="/logout.php">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<button type="submit">Выйти</button>
</form>
</div>
<script>
(function () {
	var path = (window.location.pathname || '').replace(/\/+$/, '');
	var file = path.split('/').pop();
	var links = document.querySelectorAll('.admin-top-nav a');
	for (var i = 0; i < links.length; i++) {
		var href = (links[i].getAttribute('href') || '').replace(/\/+$/, '');
		var hrefFile = href.split('/').pop();
		if (href === path || (file && hrefFile === file)) {
			links[i].className = (links[i].className ? links[i].className + ' ' : '') + 'is-active';
		}
	}
})();
</script>

{/if}
