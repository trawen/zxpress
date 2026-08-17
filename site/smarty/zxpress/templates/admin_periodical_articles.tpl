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
<a href="admin_periodicals.php?id={$periodical.id}&issue_id={$issue_id}#admin-periodical-issue-form">← Назад к выпуску: {$periodical.title_ru|escape:'html'} — {$issue_label|escape:'html'}</a>
&nbsp;&nbsp;
<a href="admin_periodical_articles.php?issue_id={$issue_id}&id=0" style="font-weight:bold">+ Новая статья</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="360" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Статьи выпуска</div>

{literal}
<style>
.admin-periodical-articles-list-wrap {
	font: normal 12px Verdana;
	max-height: 70vh;
	overflow-y: auto;
	overflow-x: hidden;
}
.admin-periodical-articles-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.admin-periodical-articles-list-wrap li {
	margin: 0 0 5px;
	line-height: 1.35;
}
.admin-periodical-articles-list-wrap a {
	color: #493C2F;
	text-decoration: none;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
}
.admin-periodical-articles-list-wrap a:hover { color: #A41E00; }
.admin-periodical-articles-list-wrap a.nav-active {
	font-weight: bold;
	color: #A41E00;
}
</style>
{/literal}

<div class="admin-periodical-articles-list-wrap">
{if $articles_list && $articles_list|@count gt 0}
<ul>
{section name=n loop=$articles_list}
<li>
<a href="admin_periodical_articles.php?issue_id={$issue_id}&id={$articles_list[n].id}"{if $article && $articles_list[n].id eq $article.id} class="nav-active"{/if}>{if !$articles_list[n].is_active}[×] {/if}#{$articles_list[n].id}{if $articles_list[n].page_start} стр.{$articles_list[n].page_start}{if $articles_list[n].page_end}-{$articles_list[n].page_end}{/if}{/if} {$articles_list[n].title_ru|escape:'html'}</a>
</li>
{/section}
</ul>
{else}
<p style="color:#666;margin:0">Статей пока нет</p>
{/if}
</div>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $article && $article.id}Редактирование статьи #{$article.id}{else}Новая статья{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_periodical_articles.php?issue_id={$issue_id}&id={if $article && $article.id}{$article.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Заголовок (RU) *</td>
<td><input type="text" name="title_ru" style="width:520px" maxlength="255" value="{if $article}{$article.title_ru}{/if}"></td>
</tr>
<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" style="width:520px" maxlength="255" value="{if $article}{$article.title_en}{/if}"></td>
</tr>
<tr>
<td>Slug RU (URL)</td>
<td>
<input type="text" name="slug_ru" style="width:520px" maxlength="255" value="{if $article}{$article.slug_ru}{/if}">
<div style="font:normal 10px Verdana;color:#666;margin-top:2px">/ru/periodicals/…/…/<i>slug</i> — пусто = из meta (RU), иначе заголовок</div>
</td>
</tr>
<tr>
<td>Slug EN (URL)</td>
<td>
<input type="text" name="slug_en" style="width:520px" maxlength="255" value="{if $article}{$article.slug_en}{/if}">
</td>
</tr>
<tr>
<td>Язык *</td>
<td>
<select name="language_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$languages}
<option value="{$languages[n].id}" {if $article && $languages[n].id eq $article.language_id}selected{elseif !$article && $languages[n].id eq 1}selected{/if}>{$languages[n].name}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td>Оригинальный язык</td>
<td>
<select name="original_language_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$languages}
<option value="{$languages[n].id}" {if $article && $article.original_language_id && $languages[n].id eq $article.original_language_id}selected{/if}>{$languages[n].name}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td>Страница с</td>
<td>
<input type="number" name="page_start" style="width:80px" min="0" value="{if $article && $article.page_start}{$article.page_start}{/if}">
&nbsp; по &nbsp;
<input type="number" name="page_end" style="width:80px" min="0" value="{if $article && $article.page_end}{$article.page_end}{/if}">
</td>
</tr>
<tr>
<td>Порядок</td>
<td><input type="number" name="sort_order" style="width:80px" min="0" value="{if $article}{$article.sort_order}{else}0{/if}"></td>
</tr>
<tr>
<td valign="top">Аннотация (RU)</td>
<td><textarea name="abstract_ru" rows="4" style="width:520px">{if $article}{$article.abstract_ru nofilter}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Аннотация (EN)</td>
<td><textarea name="abstract_en" rows="4" style="width:520px">{if $article}{$article.abstract_en nofilter}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Текст (RU)</td>
<td><textarea name="text_ru" rows="10" style="width:520px">{if $article}{$article.text_ru nofilter}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Текст (EN)</td>
<td><textarea name="text_en" rows="10" style="width:520px">{if $article}{$article.text_en nofilter}{/if}</textarea></td>
</tr>
<tr>
<td>Meta (RU)</td>
<td><textarea name="meta_description_ru" rows="5" style="width:520px" maxlength="255">{if $article}{$article.meta_description_ru}{/if}</textarea></td>
</tr>
<tr>
<td>Meta (EN)</td>
<td><textarea name="meta_description_en" rows="5" style="width:520px" maxlength="255">{if $article}{$article.meta_description_en}{/if}</textarea></td>
</tr>
<tr>
<td>Активна</td>
<td><input type="checkbox" name="is_active" value="1" {if !$article || $article.is_active}checked{/if}></td>
</tr>
{if $article && $article.id}
<tr>
<td valign="top">Картинки</td>
<td>
<input type="file" name="upload_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font:normal 10px Verdana;color:#666;margin-top:4px">оригинал сохраняется как есть; превью 640 и 1280 px в WebP (70%)</div>
{if $article_images && $article_images|@count gt 0}
<div style="margin-top:10px">
{section name=img loop=$article_images}
<div style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #C8C5AC">
<label><input type="checkbox" name="delete_image_{$article_images[img].id}" value="1"> удалить</label>
 — id={$article_images[img].id}
 sort=<input type="text" name="sort_order_{$article_images[img].id}" value="{$article_images[img].sort_order}" style="width:50px">
 {if $article_images[img].width && $article_images[img].height}{$article_images[img].width}×{$article_images[img].height}{/if}
<br>
<a href="{$article_images[img].original_url}" target="_blank" rel="noopener">оригинал</a> —
<a href="{$article_images[img].preview_url}" target="_blank" rel="noopener">640</a> —
<a href="{$article_images[img].preview_url_hd}" target="_blank" rel="noopener">1280</a><br>
<img src="{$article_images[img].preview_url}" alt="" style="max-width:420px;height:auto;border:1px solid #C8C5AC;margin-top:4px">
</div>
{/section}
</div>
{else}
<p style="color:#666;margin:8px 0 0">Картинок пока нет</p>
{/if}
</td>
</tr>
{/if}
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
