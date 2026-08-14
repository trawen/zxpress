<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<HTML><HEAD><TITLE>{$title}</TITLE>
<META http-equiv=content-type content="text/html; charset=utf-8">
{literal}
<style type="text/css" id="vbulletin_css">

a:link, body_alink
{
	color: black;
	font-family: Verdana, sans-serif;
}
a:visited, body_avisited
{
	color: black;
	font-family: Verdana, sans-serif;
}
a:hover, a:active, body_ahover
{
	color: black;
	font-family: Verdana, sans-serif;
}
body {
	font-family: Verdana, sans-serif;
}
</style>
{/literal}

<META content="MSHTML 6.00.2900.2180" name=GENERATOR></HEAD>
<BODY style="padding-top: 0px; margin-top: 0px; overflow-x: hidden !important; background-image: url(img/zxpress-2026-2-bg.webp); background-color: #EEEBCD;">


<DIV style="padding-top: 16px"></DIV>

<TABLE cellSpacing=0 cellPadding=0 width="100%" border=0>
<TR>
<TD vAlign=top>

<div align="center" style="letter-spacing: 1px; float: left; font: bold 13px Verdana">
<strong><a href="news.php">Новости сайта</a></strong> -
<strong><a href="catalog.php">Библиотека</a></strong> -
<strong><a href="gallery.php">Галерея</a></strong> -
<strong><a href="updates.php">Обновления</a></strong> -
<strong><a href="stats.php">Статистика</a></strong> -
<strong>О проекте</strong> -
<strong><a href="guestbook.php">Гостевая</a></strong> -
<strong><a href="input.php">Вход</a></strong>
</div>





<div align="right">

<form method='GET' id="goto">
<label for="admin-goto-press" class="u-sr-only">Перейти к изданию</label>
<select id="admin-goto-press" style="height: 18px; width: 220px; background-color: white; color: black; border: 1px solid #D6D0AB" name='id' onChange="javascript:this.parentNode.submit();"><option selected>Перейти к изданию</option>
{section name=n loop=$press_list}
{if $press_list[n].online_articles}<option value='{$press_list[n].id}' style="color: #A41E00; text-decoration:  underline">{$press_list[n].title} ({$press_list[n].online_articles} статей){else}<option value='{$press_list[n].id}' >{$press_list[n].title}{/if}</option>
{/section}
</select>
</form>


</div>


<hr>




{if $login neq 1 or $username eq ""}

<br><br><br><br>

<b><center><h2>Доступ возможен только для авторезированных пользователей!</h2></center></b>

<br>

<b><center><a href="http://zxpress.ru/input.php">» вход «</a></center></b>

<br><br><br><br>
{else}



{* <a href="admin_news.php?id={$id}">Новости</a> / *}
<div style="font: bold 13px Verdana">
<a href="admin_books.php">Книги</a> /
<a href="admin_publishers.php">Издательства</a> /
<a href="admin_periodicals.php">Периодика</a> /
<a href="admin_issue.php">Выпуски журналов</a> /
<a href="admin_articles.php">Статьи журналов</a> /
<a href="admin_articles_new.php">Статьи журналов (new)</a> /
<a href="admin_screens.php">Скриншоты журналов</a> /
<a href="admin_ezine_categories.php">Категории журналов</a> /
<a href="admin_authors.php">Авторы</a> /
<a href="admin_book_rubrics.php">Рубрики книг</a> /
<a href="admin_letters.php">Письма</a> /
<a href="admin_publications.php">Публикации</a> /
<a href="/ru/updates-activity">Activity</a>
 —
<form method="post" action="/logout.php" style="display:inline">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<button type="submit" style="background:transparent;border:none;padding:0;font:inherit;cursor:pointer;text-decoration:underline;color:inherit">Выйти</button>
</form>
</div>

{/if}
