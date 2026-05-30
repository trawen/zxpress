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
<a href="admin_publications.php?id={$publication.id}">← Назад к публикации: {$publication.title_ru}</a>
&nbsp;&nbsp;
<a href="admin_pub_articles.php?pub_id={$publication.id}&id=0" style="font-weight:bold">+ Новая статья</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="360" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Статьи</div>
<form method="get" action="admin_pub_articles.php">
<input type="hidden" name="pub_id" value="{$publication.id}">
<select name="id" style="width:340px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$article || !$article.id}selected{/if}>— выбрать —</option>
{section name=n loop=$articles_list}
<option value="{$articles_list[n].id}" {if $article && $articles_list[n].id eq $article.id}selected{/if}>
#{$articles_list[n].id}
{if $articles_list[n].page_from} стр.{$articles_list[n].page_from}{if $articles_list[n].page_to}-{$articles_list[n].page_to}{/if}{/if}
{$articles_list[n].title_ru}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $article && $article.id}Редактирование статьи #{$article.id}{else}Новая статья{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_pub_articles.php?pub_id={$publication.id}&id={if $article && $article.id}{$article.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Заголовок (RU) *</td>
<td><input type="text" name="title_ru" style="width:520px" value="{if $article}{$article.title_ru}{/if}"></td>
</tr>

<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" style="width:520px" value="{if $article}{$article.title_en}{/if}"></td>
</tr>

<tr>
<td>Страница с</td>
<td>
<input type="text" name="page_from" style="width:60px" value="{if $article && $article.page_from}{$article.page_from}{/if}">
&nbsp; по &nbsp;
<input type="text" name="page_to" style="width:60px" value="{if $article && $article.page_to}{$article.page_to}{/if}">
</td>
</tr>

<tr>
<td valign="top">Кратко (RU)</td>
<td><textarea name="summary_ru" rows="4" style="width:520px">{if $article}{$article.summary_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Кратко (EN)</td>
<td><textarea name="summary_en" rows="4" style="width:520px">{if $article}{$article.summary_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (RU)</td>
<td><textarea name="body_ru" rows="10" style="width:520px">{if $article}{$article.body_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (EN)</td>
<td><textarea name="body_en" rows="10" style="width:520px">{if $article}{$article.body_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td>Картинки</td>
<td>
<input type="file" name="upload_images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font-size:11px;font-weight:normal;margin-top:4px">
Оригинал сохраняется как есть; превью — jpeg 1280px по ширине (качество 85%).
</div>
{if $art_images && $art_images|@count gt 0}
<div style="font-size:11px;font-weight:normal;margin-top:8px">
<b>Загруженные картинки:</b><br>
{section name=n loop=$art_images}
<div style="margin-top:4px">
<input type="checkbox" name="delete_image_{$art_images[n].id}" value="1"> удалить
 — id={$art_images[n].id}
 sort=<input type="text" name="sort_order_{$art_images[n].id}" value="{$art_images[n].sort_order}" style="width:50px">
 format={$art_images[n].format}
<div style="margin-left:18px">
<a href="{$art_images[n].original_url}" target="_blank">оригинал</a> —
<a href="{$art_images[n].preview_url}" target="_blank">превью</a><br>
<img src="{$art_images[n].preview_url}" style="max-width:420px; height:auto; border:1px solid #C8C5AC; margin-top:4px">
</div>
</div>
{/section}
</div>
{/if}
</td>
</tr>

<tr>
<td>Файлы</td>
<td>
<input type="file" name="upload_files[]" multiple accept=".pdf,.doc,.docx,.html,.htm,.txt">
<div style="font-size:11px;font-weight:normal;margin-top:4px">
Допустимые форматы: PDF, DOC/DOCX, HTML, TXT.
</div>
{if $files && $files|@count gt 0}
<div style="font-size:11px;font-weight:normal;margin-top:8px">
<b>Загруженные файлы:</b><br>
{section name=n loop=$files}
<div style="margin-top:4px">
<input type="checkbox" name="delete_file_{$files[n].id}" value="1"> удалить
 — id={$files[n].id}
 [{$files[n].format_label}]
{if $files[n].size_display} ({$files[n].size_display}){/if}
&nbsp; <a href="{$files[n].file_url}" target="_blank">скачать</a>
</div>
{/section}
</div>
{/if}
</td>
</tr>

<tr>
<td>Активно</td>
<td><input type="checkbox" name="is_active" value="1" {if !$article || $article.is_active}checked{/if}></td>
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
