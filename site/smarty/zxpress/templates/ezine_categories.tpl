{include file="top.tpl"}

<br>
<center>
<table class="pub-layout ezine-categories-layout" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="left">

{if $category_not_found}

<h1>Категория не найдена</h1>
<p><a href="{$host}ezine-categories.php{if $lng eq 'eng'}?lng=eng{/if}">К каталогу категорий</a></p>

{else}

{function name=ec_cat_tree level=0}
<ul{if $level eq 0} class="tree ezine-categories-tree"{/if}>
{foreach from=$data item=c}
<li class="{if $c.last}last{/if}{if $level eq 0} first{/if}">
<a href="{$host}ezine-categories.php?id={$c.id}{if $lng eq 'eng'}&amp;lng=eng{/if}{if $title_only}&amp;title=1{/if}"{if $category && $category.id eq $c.id} class="nav-active"{/if}>
{if $lng eq 'eng' && $c.name_en}{$c.name_en}{else}{$c.name_ru}{/if}
</a>
{if $c.articles_count gt 0}<span class="tree-item-count">{$c.articles_count}</span>{/if}
{if $c.tree}{ec_cat_tree data=$c.tree level=$level+1}{/if}
</li>
{/foreach}
</ul>
{/function}

{if !$category}
<h1>{if $lng eq 'eng'}Ezine article categories{else}Категории статей журналов{/if}</h1>
{else}
<p class="pub-breadcrumbs">
<a href="{$host}ezine-categories.php{if $lng eq 'eng'}?lng=eng{/if}">{if $lng eq 'eng'}Categories{else}Категории{/if}</a>
{foreach from=$category_breadcrumbs item=bc name=bc}
 &rarr; <a href="{$host}ezine-categories.php?id={$bc.id}{if $lng eq 'eng'}&amp;lng=eng{/if}{if $title_only}&amp;title=1{/if}">{if $lng eq 'eng' && $bc.name_en}{$bc.name_en}{else}{$bc.name_ru}{/if}</a>
{/foreach}
</p>
<h1>{$category_title}</h1>
{if $category_image_url}
<p class="ezine-category-image-wrap"><img src="{$category_image_url}" class="ezine-category-image" alt="{$category_title|escape:'html'}" loading="lazy"></p>
{/if}
{if $category_description_html}
<p class="ezine-category-desc">{$category_description_html nofilter}</p>
{/if}
{/if}

{if !$category && $category_tree && $category_tree|@count gt 0}
<div class="ezine-categories-tree-wrap">
{ec_cat_tree data=$category_tree}
</div>
{/if}

{if $category}
<a name="articles"></a>
{if $category_articles && $category_articles|@count gt 0}
<h2 class="ezine-category-articles-heading">{if $lng eq 'eng'}Articles{else}Статьи{/if} ({$category_articles|@count})</h2>
<table cellpadding="0" cellspacing="0" border="0" width="100%" class="ezine-category-articles{if $title_only} ezine-category-articles--titles-only{/if}">
{foreach from=$category_articles item=a}
<tr>
{if !$title_only && $a.show}
<td colspan="2"><hr class="line"></td></tr><tr>
<td class="ezine-category-articles-issue" colspan="2">
<a href="{$host}issue.php?id={$a.id_press}{if $lng eq 'eng'}{$dl}{/if}#{$a.issue_title|escape:'url'}">{$a.press_name_plain} #{$a.issue_title_plain}</a>
{if $a.date && $a.date neq "01 января 1970" && $a.date neq "01 January 1970"}<span class="ezine-category-articles-date">{$a.date}</span>{/if}
</td></tr><tr>
{/if}
<td class="ezine-category-articles-link"{if $title_only} colspan="2"{/if}>
{if $lng eq 'eng'}
<a href="{$host}article.php?id={$a.id_article}{$dl}">{$a.title_eng_list nofilter}</a>
{else}
<a href="{$host}article.php?id={$a.id_article}">{$a.title_list nofilter}</a>
{/if}
</td>
</tr>
{/foreach}
</table>
{else}
<p>{if $lng eq 'eng'}No articles in this category yet.{else}В этой категории пока нет статей.{/if}</p>
{/if}
{/if}

{/if}

</td>
</tr>
</table>
</center>

{include file="right.tpl"}
{include file="footer.tpl"}
