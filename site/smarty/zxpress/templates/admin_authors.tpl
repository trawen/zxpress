{include file="admin_top.tpl"}
{if $login eq 1 and $username}

{literal}
<style>
.admin-authors-page-shell {
	box-sizing: border-box;
	height: calc(100vh - 88px);
	max-height: calc(100vh - 88px);
	min-height: 420px;
	padding: 0 10px 10px;
	display: flex;
	flex-direction: column;
}
.admin-authors-panel {
	flex: 1 1 auto;
	min-height: 0;
	padding: 10px;
	border: 1px solid #C8C5AC;
	background-color: var(--smn-paper);
	display: flex;
	flex-direction: column;
	font: bold 14px Verdana;
}
.admin-authors-toolbar {
	flex: 0 0 auto;
	margin-bottom: 10px;
}
.admin-authors-layout {
	flex: 1 1 auto;
	min-height: 0;
	width: 100%;
	display: flex;
	gap: 0;
}
.admin-authors-sidebar {
	flex: 0 0 420px;
	width: 420px;
	border-right: 1px solid #C8C5AC;
	padding-right: 10px;
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	min-height: 0;
}
.admin-authors-sidebar-head {
	flex: 0 0 auto;
	font: bold 12px Verdana;
	margin-bottom: 6px;
}
.admin-authors-list-wrap {
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
	overscroll-behavior: contain;
	-webkit-overflow-scrolling: touch;
	font: normal 12px Verdana;
	padding-right: 4px;
	border: 1px solid #C8C5AC;
	background: var(--smn-surface);
}
.admin-authors-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 4px 0;
}
.admin-authors-list-wrap li {
	margin: 0;
	line-height: 1.35;
	display: flex;
	align-items: baseline;
	gap: 6px;
}
.admin-authors-list-wrap a.admin-authors-list-name {
	color: #493C2F;
	text-decoration: none;
	display: block;
	flex: 1 1 auto;
	min-width: 0;
	padding: 5px 8px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.admin-authors-list-wrap a.admin-authors-list-name:hover { color: #A41E00; background: rgba(164,30,0,0.06); }
.admin-authors-list-wrap a.admin-authors-list-name.nav-active {
	font-weight: bold;
	color: #A41E00;
	background: rgba(164,30,0,0.1);
}
.admin-authors-list-wrap a.admin-authors-list-name.is-inactive { opacity: 0.55; }
.admin-authors-list-letters {
	flex: 0 0 auto;
	font: normal 11px Verdana;
	padding: 5px 8px 5px 0;
	white-space: nowrap;
	color: #555;
}
.admin-authors-list-letters a {
	color: #A41E00;
	text-decoration: none;
}
.admin-authors-list-letters a:hover { text-decoration: underline; }
.admin-authors-list-letters .is-zero { color: #999; }
.admin-authors-main {
	flex: 1 1 auto;
	min-width: 0;
	min-height: 0;
	padding-left: 12px;
	overflow: auto;
}
.admin-authors-main-head {
	font: bold 12px Verdana;
	margin-bottom: 6px;
}
</style>
{/literal}

<script type="text/javascript">
(function () {
	var active = document.querySelector('.admin-authors-list-wrap a.nav-active');
	if (active && typeof active.scrollIntoView === 'function') {
		active.scrollIntoView({ block: 'nearest' });
	}
})();
</script>

<div class="admin-authors-page-shell">
<div class="admin-authors-panel">

<div class="admin-authors-toolbar">
<a href="admin_authors.php?id=0" style="font-weight:bold">+ Новый автор</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px;flex:0 0 auto">{$error}</div>
{/if}

<div class="admin-authors-layout">
<aside class="admin-authors-sidebar">
<div class="admin-authors-sidebar-head">Авторы</div>
<div class="admin-authors-list-wrap" id="admin-author-list" role="navigation" aria-label="Список авторов">
{if $authors_list && $authors_list|@count gt 0}
<ul>
{section name=n loop=$authors_list}
<li>
<a class="admin-authors-list-name{if $author && $authors_list[n].id eq $author.id} nav-active{/if}{if $authors_list[n].is_active eq 0} is-inactive{/if}" href="admin_authors.php?id={$authors_list[n].id}">{if $authors_list[n].is_active eq 0}[×] {/if}{$authors_list[n].nickname}{if $authors_list[n].name_ru} ({$authors_list[n].name_ru}){elseif $authors_list[n].name_en} ({$authors_list[n].name_en}){/if}{if $authors_list[n].city_name} — {$authors_list[n].city_name}{/if}</a>
<span class="admin-authors-list-letters">{if $authors_list[n].letters_from_count gt 0}<a href="admin_letters.php?status=all&amp;author_id={$authors_list[n].id}" title="Письма с участием этого автора">{$authors_list[n].letters_from_count}</a>{else}<span class="is-zero">0</span>{/if}</span>
</li>
{/section}
</ul>
{else}
<p style="color:#666;margin:8px">Авторов пока нет</p>
{/if}
</div>
</aside>

<div class="admin-authors-main">
<div class="admin-authors-main-head">
{if $author && $author.id}Редактирование автора #{$author.id}{else}Новый автор{/if}
</div>

<form method="post" action="admin_authors.php?id={if $author && $author.id}{$author.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Ник *</td>
<td><input type="text" name="nickname" style="width:420px" value="{if $author}{$author.nickname}{/if}"></td>
</tr>
<tr>
<td>Имя (RU)</td>
<td><input type="text" name="name_ru" style="width:420px" value="{if $author}{$author.name_ru}{/if}"></td>
</tr>
<tr>
<td>Имя (EN)</td>
<td>
<input type="text" name="name_en" style="width:420px" value="{if $author}{$author.name_en}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле при сохранении переводится из Имя (RU) через Google.</div>
</td>
</tr>
<tr>
<td>Группа</td>
<td><input type="text" name="group_name" style="width:420px" value="{if $author}{$author.group_name}{/if}"></td>
</tr>
<tr>
<td>Slug (RU)</td>
<td>
<input type="text" name="slug_ru" style="width:420px" maxlength="191" pattern="[a-z0-9-]*" value="{if $author}{$author.slug_ru}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле генерируется из ника (или имени RU). Разрешены только a-z, 0-9 и дефис.</div>
</td>
</tr>
<tr>
<td>Slug (EN)</td>
<td>
<input type="text" name="slug_en" style="width:420px" maxlength="191" pattern="[a-z0-9-]*" value="{if $author}{$author.slug_en}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле генерируется из имени EN (или ника).</div>
</td>
</tr>
<tr>
<td><label for="admin-author-country">Страна</label></td>
<td>
<select id="admin-author-country" name="country_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$countries}
<option value="{$countries[n].id}" {if $author && $countries[n].id eq $author.country_id}selected{/if}>
{if $countries[n].country_name}{$countries[n].country_name}{else}{$countries[n].id}{/if}
</option>
{/section}
</select>
</td>
</tr>
<tr>
<td><label for="admin-author-city">Город</label></td>
<td>
<select id="admin-author-city" name="city_id" style="width:240px">
<option value="0" data-country-id="0">---</option>
{section name=n loop=$cities}
<option value="{$cities[n].id}" data-country-id="{$cities[n].country_id}" {if $author && $cities[n].id eq $author.city_id}selected{/if}>{$cities[n].name}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td><label for="admin-author-user">Пользователь</label></td>
<td>
<select id="admin-author-user" name="user_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$users}
<option value="{$users[n].id}" {if $author && $users[n].id eq $author.user_id}selected{/if}>{$users[n].username}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td>Активен</td>
<td><input type="checkbox" name="is_active" value="1" {if !$author || $author.is_active}checked{/if}></td>
</tr>
</table>

<div style="margin-top:10px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>

</form>

{literal}
<script type="text/javascript">
(function () {
	var citySel = document.getElementById('admin-author-city');
	var countrySel = document.getElementById('admin-author-country');
	if (!citySel || !countrySel) return;

	function syncCountryFromCity() {
		var opt = citySel.options[citySel.selectedIndex];
		if (!opt) return;
		var countryId = opt.getAttribute('data-country-id') || '0';
		if (countryId === '0') return;
		countrySel.value = String(countryId);
	}

	citySel.addEventListener('change', syncCountryFromCity);
	if (citySel.value && citySel.value !== '0') {
		syncCountryFromCity();
	}
})();
</script>
{/literal}

</div>
</div>

</div>
</div>

{/if}
