<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$admin2_title} | Админка — ZXPress</title>
<link rel="stylesheet" href="/css/admin-v2.css?v=20261009z">
</head>
<body class="admin">
{include file="admin2/icon_sprite.tpl"}
<div class="admin-shell">
<aside class="admin-sidebar" aria-label="Админка">
<a class="admin-sidebar__brand" href="/admin2/letters.php">
<span class="admin-sidebar__brand-row">
{include file="admin2/icon.tpl" name="book-open"}
<span class="admin-sidebar__brand-title">ZXPRESS</span>
</span>
</a>
<nav class="admin-sidebar__nav" aria-label="Разделы">
<a class="admin-sidebar__link{if $admin2_section eq 'letters'} is-active{/if}" href="/admin2/letters.php">{include file="admin2/icon.tpl" name="mail"}<span>Письма</span></a>
<a class="admin-sidebar__link{if $admin2_section eq 'books'} is-active{/if}" href="/admin2/books.php">{include file="admin2/icon.tpl" name="book-open"}<span>Книги</span></a>
</nav>
<div class="admin-sidebar__foot">
{if $username}<div class="admin-sidebar__meta">{include file="admin2/icon.tpl" name="user-round"}<span>{$username}</span></div>{/if}
<a class="admin-sidebar__link" href="/admin_letters.php">{include file="admin2/icon.tpl" name="arrow-left-to-line"}<span>Старая админка</span></a>
<a class="admin-sidebar__link" href="/">{include file="admin2/icon.tpl" name="external-link"}<span>На сайт</span></a>
<form method="post" action="/logout.php" class="admin-sidebar__logout">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<button type="submit" class="admin-sidebar__link">{include file="admin2/icon.tpl" name="log-out"}<span>Выход</span></button>
</form>
</div>
</aside>
<div class="admin-main">
<header class="admin-topbar">
<h1 class="admin-topbar__title">{$admin2_title}</h1>
<div class="admin-topbar__actions">
<div class="admin-topbar__search">
<input type="search" id="admin-rewind" class="admin-topbar__search-input" placeholder="Быстрый поиск…" aria-label="Быстрый поиск" autocomplete="off">
</div>
</div>
</header>
<main class="admin-content">
{if isset($admin2_flash) && $admin2_flash}
<div class="admin-flash {if $admin2_flash.kind eq 'ok'}admin-flash--ok{else}admin-flash--err{/if}">{$admin2_flash.message}</div>
{/if}
{if isset($error) && $error}
<div class="admin-flash admin-flash--err">{$error}</div>
{/if}
{include file=$admin2_content}
</main>
</div>
</div>
<script src="/js/admin-v2.js?v=20261009z"></script>
</body>
</html>
