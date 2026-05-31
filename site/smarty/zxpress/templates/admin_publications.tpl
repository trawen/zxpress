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
<a href="admin_publications.php?id=0" style="font-weight:bold">+ Новая публикация</a>
{if $pub && $pub.id}
&nbsp;&nbsp;|&nbsp;&nbsp;
<a href="admin_pub_articles.php?pub_id={$pub.id}" style="font-weight:bold">Статьи публикации #{$pub.id} ({$articles_count}) →</a>
{/if}
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="360" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Публикации</div>
<form method="get" action="admin_publications.php">
<label for="admin-pub-list" class="u-sr-only">Выбрать публикацию</label>
<select id="admin-pub-list" name="id" style="width:340px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$pub || !$pub.id}selected{/if}>— выбрать —</option>
{section name=n loop=$pub_list}
<option value="{$pub_list[n].id}" {if $pub && $pub_list[n].id eq $pub.id}selected{/if}>
#{$pub_list[n].id} [{$pub_types[$pub_list[n].type]}] {$pub_list[n].title_ru}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $pub && $pub.id}Редактирование публикации #{$pub.id}{else}Новая публикация{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_publications.php?id={if $pub && $pub.id}{$pub.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td><label for="admin-pub-type">Тип *</label></td>
<td>
<select id="admin-pub-type" name="type" style="width:340px">
<option value="0">---</option>
{foreach from=$pub_types key=k item=v}
<option value="{$k}" {if $pub && $pub.type == $k}selected{/if}>{$v}</option>
{/foreach}
</select>
</td>
</tr>

<tr>
<td>Название (RU) *</td>
<td><input type="text" name="title_ru" style="width:520px" value="{if $pub}{$pub.title_ru}{/if}"></td>
</tr>

<tr>
<td>Название (EN)</td>
<td><input type="text" name="title_en" style="width:520px" value="{if $pub}{$pub.title_en}{/if}"></td>
</tr>

<tr>
<td>Дата публикации</td>
<td><input type="text" name="published_at" style="width:140px" value="{if $pub}{$pub.published_at}{/if}" placeholder="дд.мм.гггг"></td>
</tr>

<tr>
<td><label for="admin-pub-country">Страна</label></td>
<td>
<select id="admin-pub-country" name="country_id" style="width:340px">
<option value="0">---</option>
{section name=n loop=$countries}
<option value="{$countries[n].id}" {if $pub && $countries[n].id eq $pub.country_id}selected{/if}>
{$countries[n].country_name}
</option>
{/section}
</select>
</td>
</tr>

<tr>
<td><label for="admin-pub-city">Город</label></td>
<td>
<select id="admin-pub-city" name="city_id" style="width:340px">
<option value="0">---</option>
{section name=n loop=$cities}
<option value="{$cities[n].id}" {if $pub && $cities[n].id eq $pub.city_id}selected{/if}>
{$cities[n].name}
</option>
{/section}
</select>
</td>
</tr>

<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="summary_ru" rows="4" style="width:520px">{if $pub}{$pub.summary_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="summary_en" rows="4" style="width:520px">{if $pub}{$pub.summary_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td>Картинки</td>
<td>
<input type="file" name="upload_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font-size:11px;font-weight:normal;margin-top:4px">
Оригинал сохраняется как есть; превью — jpeg 1280px по ширине (качество 85%). Первая картинка — обложка.
</div>
{if $images && $images|@count gt 0}
<div style="font-size:11px;font-weight:normal;margin-top:8px">
<b>Загруженные картинки:</b><br>
{section name=n loop=$images}
<div style="margin-top:4px">
<input type="checkbox" name="delete_image_{$images[n].id}" value="1"> удалить
 — id={$images[n].id}
 sort=<input type="text" name="sort_order_{$images[n].id}" value="{$images[n].sort_order}" style="width:50px">
 format={$images[n].format}
<div style="margin-left:18px">
<a href="{$images[n].original_url}" target="_blank">оригинал</a> —
<a href="{$images[n].preview_url}" target="_blank">превью</a><br>
<img src="{$images[n].preview_url}" style="max-width:420px; height:auto; border:1px solid #C8C5AC; margin-top:4px">
</div>
</div>
{/section}
</div>
{/if}
</td>
</tr>

<tr>
<td>Активно</td>
<td><input type="checkbox" name="is_active" value="1" {if !$pub || $pub.is_active}checked{/if}></td>
</tr>
</table>

<div style="margin-top:10px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>

</form>

{if $pub && $pub.id}
<div style="margin-top:16px; padding-top:12px; border-top:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Статьи ({$articles_count})</div>
<a href="admin_pub_articles.php?pub_id={$pub.id}">Управление статьями этой публикации →</a>
</div>
{/if}

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
