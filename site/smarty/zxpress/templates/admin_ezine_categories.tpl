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
<a href="admin_ezine_categories.php?id=0" style="font-weight:bold">+ Новая категория</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="320" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Категории</div>

{literal}
<style>
.admin-ezine-categories-tree-wrap ul.tree,
.admin-ezine-categories-tree-wrap ul.tree ul {
	list-style-type: none;
	background: url(http://odyniec.net/articles/turning-lists-into-trees/vline.png) repeat-y;
	margin: 0;
	padding: 0;
}
.admin-ezine-categories-tree-wrap ul.tree ul { margin-left: 10px; }
.admin-ezine-categories-tree-wrap ul.tree li {
	margin: 0;
	padding: 0 12px;
	line-height: 22px;
	background: url(http://odyniec.net/articles/turning-lists-into-trees/node.png) no-repeat;
	font: normal 12px Verdana;
}
.admin-ezine-categories-tree-wrap ul.tree li.last {
	background: #EBE8D7 url(http://odyniec.net/articles/turning-lists-into-trees/lastnode.png) no-repeat;
	margin-bottom: 6px;
}
.admin-ezine-categories-tree-wrap ul.tree li.first {
	background: #EBE8D7;
	font-weight: bold;
	padding: 0;
}
.admin-ezine-categories-tree-wrap .tree-item-count {
	font: normal 11px Arial;
	color: #999;
	margin-left: 4px;
}
.admin-ezine-categories-tree-wrap a.nav-active { font-weight: bold; color: #A41E00; }
.admin-ezine-categories-tree-wrap .admin-ec-public-link {
	display: inline-block;
	margin-left: 6px;
	color: #666;
	text-decoration: none;
	font-size: 13px;
	line-height: 1;
	vertical-align: middle;
}
.admin-ezine-categories-tree-wrap .admin-ec-public-link:hover { color: #A41E00; }
</style>
{/literal}

{function name=admin_ec_cat_tree level=0}
<ul{if $level eq 0} class="tree"{/if}>
{foreach from=$data item=c}
<li class="{if $c.last}last{/if}{if $level eq 0} first{/if}">
<a href="admin_ezine_categories.php?id={$c.id}"{if $category && $category.id eq $c.id} class="nav-active"{/if}>{$c.name_ru}</a>
{if $c.articles_count gt 0}<span class="tree-item-count">{$c.articles_count}</span>{/if}
<a href="{$host}ezine-categories.php?id={$c.id}" target="_blank" rel="noopener" class="admin-ec-public-link" title="Публичная страница со статьями" aria-label="Публичная страница">→</a>
{if $c.tree}{admin_ec_cat_tree data=$c.tree level=$level+1}{/if}
</li>
{/foreach}
</ul>
{/function}

<div class="admin-ezine-categories-tree-wrap">
{if $category_tree && $category_tree|@count gt 0}
{admin_ec_cat_tree data=$category_tree}
{else}
<p style="font:12px Verdana;color:#666;margin:0">Категорий пока нет</p>
{/if}
</div>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $category && $category.id}Редактирование категории #{$category.id}{else}Новая категория{/if}
{if $category && $category.id}
<span style="font-weight:normal;color:#666"> — статей: {$category.articles_count}</span>
{/if}
</div>

<form method="post" action="admin_ezine_categories.php?id={if $category && $category.id}{$category.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Название (RU) *</td>
<td><input type="text" name="name_ru" style="width:420px" maxlength="150" value="{if $category}{$category.name_ru}{/if}"></td>
</tr>
<tr>
<td>Название (EN) *</td>
<td><input type="text" name="name_en" style="width:420px" maxlength="150" value="{if $category}{$category.name_en}{/if}"></td>
</tr>
<tr>
<td>Заголовок (RU)</td>
<td><input type="text" name="title_ru" style="width:420px" maxlength="255" value="{if $category}{$category.title_ru}{/if}"></td>
</tr>
<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" style="width:420px" maxlength="255" value="{if $category}{$category.title_en}{/if}"></td>
</tr>
<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="description_ru" style="width:420px;height:80px">{if $category}{$category.description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="description_en" style="width:420px;height:80px">{if $category}{$category.description_en}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta description (RU)</td>
<td><textarea name="meta_description_ru" style="width:420px;height:48px" maxlength="500">{if $category}{$category.meta_description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta description (EN)</td>
<td><textarea name="meta_description_en" style="width:420px;height:48px" maxlength="500">{if $category}{$category.meta_description_en}{/if}</textarea></td>
</tr>
<tr>
<td><label for="admin-ezine-category-parent">Родительская категория</label></td>
<td>
<select id="admin-ezine-category-parent" name="parent_id" style="width:420px">
<option value="0">— нет —</option>
{section name=n loop=$parent_categories}
{if !$category || $parent_categories[n].id ne $category.id}
<option value="{$parent_categories[n].id}" {if $category && $parent_categories[n].id eq $category.parent_id}selected{/if}>
{$parent_categories[n].list_label}
</option>
{/if}
{/section}
</select>
</td>
</tr>
<tr>
<td>Порядок сортировки</td>
<td><input type="number" name="sort_order" style="width:80px" min="0" max="255" value="{if $category}{$category.sort_order}{else}0{/if}"></td>
</tr>
{if $category && $category.created_at}
<tr>
<td>Создана</td>
<td>{$category.created_at}{if $category.updated_at} · обновлена {$category.updated_at}{/if}</td>
</tr>
{/if}
{if $category && $category.id}
<tr>
<td valign="top">Удалить</td>
<td><label><input type="checkbox" name="delete" value="1"> удалить категорию</label></td>
</tr>
{/if}
</table>

<div style="margin-top:10px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>

</form>

{if $category && $category.id && $linked_articles|@count gt 0}
<div style="margin-top:16px;font:12px Verdana">
<div style="font-weight:bold;margin-bottom:6px">Привязанные статьи</div>
<div style="max-height:200px;overflow:auto;border:1px solid #C8C5AC;padding:6px;background:#fff;width:520px">
{section name=n loop=$linked_articles}
<div style="margin-bottom:4px">
<a href="admin_articles.php?id={$linked_articles[n].id}">#{$linked_articles[n].id}</a>
{if $linked_articles[n].number} · №{$linked_articles[n].number}{/if}
· {$linked_articles[n].title|strip_tags|escape:'html'}
</div>
{/section}
</div>
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
