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
<a href="admin_letters.php?id=0" style="font-weight:bold">+ Новое письмо</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="360" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Письма</div>
<form method="get" action="admin_letters.php">
<select name="id" style="width:340px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$letter || !$letter.id}selected{/if}>— выбрать —</option>
{section name=n loop=$letters_list}
<option value="{$letters_list[n].id}" {if $letter && $letters_list[n].id eq $letter.id}selected{/if}>
#{$letters_list[n].id} {$letters_list[n].from_nick} → {$letters_list[n].to_nick}: {$letters_list[n].title_ru}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $letter && $letter.id}Редактирование письма #{$letter.id}{else}Новое письмо{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_letters.php?id={if $letter && $letter.id}{$letter.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>От кого *</td>
<td>
<select name="author_from" style="width:340px">
<option value="0">---</option>
{section name=n loop=$authors}
<option value="{$authors[n].id}" {if $letter && $authors[n].id eq $letter.author_from}selected{/if}>
{$authors[n].nickname}
</option>
{/section}
</select>
</td>
</tr>

<tr>
<td>Кому *</td>
<td>
<select name="author_to" style="width:340px">
<option value="0">---</option>
{section name=n loop=$authors}
<option value="{$authors[n].id}" {if $letter && $authors[n].id eq $letter.author_to}selected{/if}>
{$authors[n].nickname}
</option>
{/section}
</select>
</td>
</tr>

<tr>
<td>Заголовок (RU) *</td>
<td><input type="text" name="title_ru" style="width:520px" value="{if $letter}{$letter.title_ru}{/if}"></td>
</tr>

<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" style="width:520px" value="{if $letter}{$letter.title_en}{/if}"></td>
</tr>

<tr>
<td>Дата</td>
<td><input type="text" name="date" style="width:140px" value="{if $letter}{$letter.date}{/if}" placeholder="дд.мм.гггг"></td>
</tr>

<tr>
<td valign="top">Кратко (RU)</td>
<td><textarea name="summary_ru" rows="4" style="width:520px">{if $letter}{$letter.summary_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Кратко (EN)</td>
<td><textarea name="summary_en" rows="4" style="width:520px">{if $letter}{$letter.summary_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (RU)</td>
<td><textarea name="body_ru" rows="10" style="width:520px">{if $letter}{$letter.body_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (EN)</td>
<td><textarea name="body_en" rows="10" style="width:520px">{if $letter}{$letter.body_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td>Файлы (сканы)</td>
<td>
<input type="file" name="upload_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font-size:11px;font-weight:normal;margin-top:4px">
Оригинал сохраняется как есть; превью — jpeg 1280px по ширине (качество 85%).
</div>
{if $images && $images|@count gt 0}
<div style="font-size:11px;font-weight:normal;margin-top:8px">
<b>Загруженные страницы:</b><br>
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
<td><input type="checkbox" name="is_active" value="1" {if !$letter || $letter.is_active}checked{/if}></td>
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

