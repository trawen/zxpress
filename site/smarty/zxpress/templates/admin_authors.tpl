{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<div style="font: bold 14px Verdana">
<br>

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7">

<div style="margin-bottom:10px">
<a href="admin_authors.php?id=0" style="font-weight:bold">+ Новый автор</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="260" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Авторы</div>
<form method="get" action="admin_authors.php">
<label for="admin-author-list" class="u-sr-only">Выбрать автора</label>
<select id="admin-author-list" name="id" style="width:240px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$author || !$author.id}selected{/if}>— выбрать —</option>
{section name=n loop=$authors_list}
<option value="{$authors_list[n].id}" {if $author && $authors_list[n].id eq $author.id}selected{/if}>
{$authors_list[n].nickname}{if $authors_list[n].name_ru} ({$authors_list[n].name_ru}){elseif $authors_list[n].name_en} ({$authors_list[n].name_en}){/if}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
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
<td><input type="text" name="name_en" style="width:420px" value="{if $author}{$author.name_en}{/if}"></td>
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
<option value="0">---</option>
{section name=n loop=$cities}
<option value="{$cities[n].id}" {if $author && $cities[n].id eq $author.city_id}selected{/if}>{$cities[n].name}</option>
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

</td>
</tr>
</table>

</div>

</div>

</TD>
</TR>
</TBODY>
</TABLE>

{/if}

