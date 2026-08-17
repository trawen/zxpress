{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<div style="font: bold 14px Verdana">
<br>

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: var(--smn-paper)">

<div style="margin-bottom:10px">
<a href="admin_publishers.php?id=0" style="font-weight:bold">+ Новое издательство</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="260" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Издательства</div>
<form method="get" action="admin_publishers.php">
<label for="admin-publisher-list" class="u-sr-only">Выбрать издательство</label>
<select id="admin-publisher-list" name="id" style="width:240px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$publisher || !$publisher.id}selected{/if}>— выбрать —</option>
{section name=n loop=$publishers_list}
<option value="{$publishers_list[n].id}" {if $publisher && $publishers_list[n].id eq $publisher.id}selected{/if}>
{if !$publishers_list[n].active}[×] {/if}{$publishers_list[n].name_ru}{if $publishers_list[n].name_en} / {$publishers_list[n].name_en}{/if}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $publisher && $publisher.id}Редактирование издательства #{$publisher.id}{else}Новое издательство{/if}
{if $publisher && $publisher.id && $books_count gt 0}
<span style="font-weight:normal;color:#666"> — книг в справочнике: {$books_count}</span>
{/if}
</div>

<form method="post" action="admin_publishers.php?id={if $publisher && $publisher.id}{$publisher.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Название (RU) *</td>
<td><input type="text" name="name_ru" style="width:420px" maxlength="128" value="{if $publisher}{$publisher.name_ru}{/if}"></td>
</tr>
<tr>
<td>Название (EN)</td>
<td><input type="text" name="name_en" style="width:420px" maxlength="128" value="{if $publisher}{$publisher.name_en}{/if}"></td>
</tr>
<tr>
<td>Псевдоним (RU)</td>
<td><input type="text" name="alias_ru" style="width:420px" maxlength="128" value="{if $publisher}{$publisher.alias_ru}{/if}"></td>
</tr>
<tr>
<td>Псевдоним (EN)</td>
<td><input type="text" name="alias_en" style="width:420px" maxlength="128" value="{if $publisher}{$publisher.alias_en}{/if}"></td>
</tr>
<tr>
<td>Форма (RU)</td>
<td><input type="text" name="form_ru" style="width:420px" maxlength="128" value="{if $publisher}{$publisher.form_ru}{/if}"></td>
</tr>
<tr>
<td>Форма (EN)</td>
<td><input type="text" name="form_en" style="width:420px" maxlength="128" value="{if $publisher}{$publisher.form_en}{/if}"></td>
</tr>
<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="description_ru" style="width:420px;height:80px">{if $publisher}{$publisher.description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="description_en" style="width:420px;height:80px">{if $publisher}{$publisher.description_en}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta (RU)</td>
<td><textarea name="meta_description_ru" rows="5" style="width:420px" maxlength="255">{if $publisher}{$publisher.meta_description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta (EN)</td>
<td><textarea name="meta_description_en" rows="5" style="width:420px" maxlength="255">{if $publisher}{$publisher.meta_description_en}{/if}</textarea></td>
</tr>
<tr>
<td>Slug RU (URL)</td>
<td>
<input type="text" name="slug_ru" style="width:420px" maxlength="255" value="{if $publisher}{$publisher.slug_ru}{/if}">
<div style="font:normal 10px Verdana;color:#666;margin-top:2px">пусто = из meta (RU), иначе название</div>
</td>
</tr>
<tr>
<td>Slug EN (URL)</td>
<td>
<input type="text" name="slug_en" style="width:420px" maxlength="255" value="{if $publisher}{$publisher.slug_en}{/if}">
</td>
</tr>
<tr>
<td><label for="admin-publisher-city">Город</label></td>
<td>
<select id="admin-publisher-city" name="city_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$cities}
<option value="{$cities[n].id}" {if $publisher && $cities[n].id eq $publisher.city_id}selected{/if}>{$cities[n].name}</option>
{/section}
</select>
</td>
</tr>
{if $publisher && $publisher.updated_at}
<tr>
<td>Обновлено</td>
<td>{$publisher.updated_at}</td>
</tr>
{/if}
<tr>
<td>Активно</td>
<td><input type="checkbox" name="active" value="1" {if !$publisher || $publisher.active}checked{/if}></td>
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
