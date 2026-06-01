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
<a href="admin_book_rubrics.php?id=0" style="font-weight:bold">+ Новая рубрика</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="260" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Рубрики</div>
<form method="get" action="admin_book_rubrics.php">
<label for="admin-rubric-list" class="u-sr-only">Выбрать рубрику</label>
<select id="admin-rubric-list" name="id" style="width:240px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$rubric || !$rubric.id}selected{/if}>— выбрать —</option>
{section name=n loop=$rubrics_list}
<option value="{$rubrics_list[n].id}" {if $rubric && $rubrics_list[n].id eq $rubric.id}selected{/if}>
{if !$rubrics_list[n].is_active}[×] {/if}[{$rubrics_list[n].sort_order}] {$rubrics_list[n].name_ru}{if $rubrics_list[n].name_en} / {$rubrics_list[n].name_en}{/if}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $rubric && $rubric.id}Редактирование рубрики #{$rubric.id}{else}Новая рубрика{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_book_rubrics.php?id={if $rubric && $rubric.id}{$rubric.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Название (RU) *</td>
<td><input type="text" name="name_ru" style="width:420px" value="{if $rubric}{$rubric.name_ru}{/if}"></td>
</tr>
<tr>
<td>Название (EN)</td>
<td><input type="text" name="name_en" style="width:420px" value="{if $rubric}{$rubric.name_en}{/if}"></td>
</tr>
<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="description_ru" style="width:420px;height:80px">{if $rubric}{$rubric.description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="description_en" style="width:420px;height:80px">{if $rubric}{$rubric.description_en}{/if}</textarea></td>
</tr>
<tr>
<td>Порядок отображения</td>
<td><input type="number" name="sort_order" style="width:80px" value="{if $rubric}{$rubric.sort_order}{else}0{/if}"></td>
</tr>
{if $rubric && $rubric.updated_at}
<tr>
<td>Обновлена</td>
<td>{$rubric.updated_at}</td>
</tr>
{/if}
<tr>
<td>Активна</td>
<td><input type="checkbox" name="is_active" value="1" {if !$rubric || $rubric.is_active}checked{/if}></td>
</tr>
<tr>
<td valign="top">Картинка</td>
<td>
{if $rubric_image_url}
<div style="margin-bottom:6px">
<img src="{$rubric_image_url}" style="max-width:200px;height:auto;border:1px solid #C8C5AC">
<br>
<label><input type="checkbox" name="delete_image" value="1"> удалить картинку</label>
</div>
{/if}
<input type="file" name="upload_image" accept="image/jpeg,image/png,image/webp,image/gif">
<div style="color:#666;margin-top:4px">Файл сохраняется как data/book_rubrics/ID.расширение</div>
</td>
</tr>
<tr>
<td valign="top">Книги</td>
<td>
<div style="max-height:220px;overflow:auto;border:1px solid #C8C5AC;padding:6px;width:420px;background:#fff">
{section name=n loop=$books_list}
<label style="display:block;margin-bottom:2px">
<input type="checkbox" name="book_ids[]" value="{$books_list[n].id}" {if $books_list[n].linked}checked{/if}>
{$books_list[n].title}
</label>
{/section}
</div>
</td>
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
