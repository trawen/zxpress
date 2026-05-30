{include file="top.tpl"}

<br>
<center>
<table class="pub-layout" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="left">

{if $pub_article_detail}

<p class="pub-breadcrumbs">
	<a href="{$host}publications.php">Публикации</a> &rarr;
	<a href="{$host}publications.php?id={$pub.id}">{$pub.title_ru}</a>
</p>

<h1>{$pub_article_detail.title_ru}</h1>

{if $pub_article_detail.pages_display}
<p class="pub-meta">{$pub_article_detail.pages_display}</p>
{/if}

{if $pub_article_detail.body_html}
<div class="pub-body">{$pub_article_detail.body_html nofilter}</div>
{/if}

{if $pub_article_images && $pub_article_images|@count gt 0}
<div class="pub-images">
{foreach from=$pub_article_images item=img}
<div class="pub-image-item">
	<a href="{$img.original_url}" target="_blank" rel="noopener">
		<img src="{$img.display_src}" alt="" class="pub-image">
	</a>
</div>
{/foreach}
</div>
{/if}

{if $pub_article_detail.files && $pub_article_detail.files|@count gt 0}
<div class="pub-files">
<b>Файлы:</b>
{foreach from=$pub_article_detail.files item=f}
<div class="pub-file-item">
	<a href="{$f.file_url}" target="_blank" rel="noopener">{$f.format_label}</a>
	{if $f.size_display}<span class="pub-muted">({$f.size_display})</span>{/if}
</div>
{/foreach}
</div>
{/if}

{elseif $pub}

<p class="pub-breadcrumbs">
	<a href="{$host}publications.php">← Все публикации</a>
</p>

<table class="pub-header-table" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
{if $pub_cover}
<td class="pub-cover-cell">
	<a href="{$pub_cover.original_url}" target="_blank" rel="noopener">
		<img src="{$pub_cover.thumb_src}" alt="" class="pub-cover-img">
	</a>
</td>
{/if}
<td>
<h1 class="pub-title">{$pub.title_ru}</h1>

<div class="pub-info">
{if $pub.type_label}<span>{$pub.type_label}</span>{/if}
{if $pub.published_display} &nbsp;·&nbsp; {$pub.published_display}{/if}
{if $pub.geo_display} &nbsp;·&nbsp; {$pub.geo_display}{/if}
</div>
</td>
</tr>
</table>

{if $pub_articles && $pub_articles|@count gt 0}
<div class="pub-toc">
<h2 class="pub-toc-heading">Содержание ({$pub_articles|@count})</h2>

{foreach from=$pub_articles item=a}
<table class="pub-toc-item" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
{if $a.cover}
<td class="pub-toc-cover-cell">
	<a href="{$host}publications.php?id={$pub.id}&amp;article={$a.id}">
		<img src="{$a.cover.thumb_src}" alt="" width="90" class="pub-toc-thumb">
	</a>
</td>
{/if}
<td>
	<a href="{$host}publications.php?id={$pub.id}&amp;article={$a.id}" class="pub-toc-title">{$a.title_ru}</a>
	{if $a.pages_display}
	<span class="pub-toc-pages">{$a.pages_display}</span>
	{/if}
	{if $a.summary_html}
	<div class="pub-toc-summary">{$a.summary_html nofilter}</div>
	{/if}
	{if $a.files && $a.files|@count gt 0}
	<div class="pub-toc-files">
		{foreach from=$a.files item=f name=fl}
		<a href="{$f.file_url}" target="_blank" rel="noopener">{$f.format_label}</a>{if $f.size_display} <span class="pub-muted">({$f.size_display})</span>{/if}{if !$smarty.foreach.fl.last}, {/if}
		{/foreach}
	</div>
	{/if}
</td>
</tr>
</table>
{/foreach}
</div>
{/if}

{if $pub_images && $pub_images|@count gt 0}
<div class="pub-images">
{foreach from=$pub_images item=img}
<div class="pub-image-item">
	<a href="{$img.original_url}" target="_blank" rel="noopener">
		<img src="{$img.display_src}" alt="" class="pub-image">
	</a>
</div>
{/foreach}
</div>
{/if}

{elseif $pub_not_found}

<h1>Публикация не найдена</h1>
<p><a href="{$host}publications.php">К каталогу публикаций</a></p>

{else}

<h1>Публикации</h1>

{if $pub_type_filters && $pub_type_filters|@count gt 0}
<p class="pub-filters">
	<b>Тип:</b>
	{if $filter_type}
		<a href="{$host}publications.php">все</a>,
	{else}
		<b>все</b>,
	{/if}
	{foreach from=$pub_type_filters item=tf name=tfl}
		{if $filter_type == $tf.type}
			<b>{$tf.label}</b>
		{else}
			<a href="{$host}publications.php?type={$tf.type}">{$tf.label}</a>
		{/if}
		{if !$smarty.foreach.tfl.last}, {/if}
	{/foreach}
</p>
{/if}

{if $pub_rows && $pub_rows|@count gt 0}
{foreach from=$pub_rows item=row}
<table class="pub-list-item" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td class="pub-list-cover-cell">
{if $row.cover}
	<a href="{$host}publications.php?id={$row.id}">
		<img src="{$row.cover.thumb_src}" alt="" width="128" class="pub-list-thumb">
	</a>
{else}
	<div class="pub-list-placeholder">нет обложки</div>
{/if}
{if $row.created_display}
<div class="pub-list-date">опубликовано: {$row.created_display}</div>
{/if}
</td>
<td>
	<a href="{$host}publications.php?id={$row.id}" class="pub-list-title">{$row.title_ru}</a>
	<div class="pub-list-meta">
		{$row.type_label}
		{if $row.published_display} &nbsp;·&nbsp; {$row.published_display}{/if}
		{if $row.geo_display} &nbsp;·&nbsp; {$row.geo_display}{/if}
		{if $row.articles_count gt 0} &nbsp;·&nbsp; {$row.articles_count} статей{/if}
	</div>
	{if $row.summary_html}
	<div class="pub-list-summary">{$row.summary_html nofilter}</div>
	{/if}
</td>
</tr>
</table>
<hr>
<br>
{/foreach}
{else}
<p>Здесь пока нет публикаций.</p>
{/if}

{if $pub_total_pages gt 1}
<div class="pub-pagination">
	Страницы:
	{section name=pg loop=$pub_total_pages}
		{assign var=pnum value=$smarty.section.pg.iteration}
		{if $pnum == $pub_page}
			<b>{$pnum}</b>
		{else}
			<a href="{$host}publications.php?p={$pnum}{if $filter_type}&amp;type={$filter_type}{/if}">{$pnum}</a>
		{/if}
		{if !$smarty.section.pg.last} {/if}
	{/section}
</div>
{/if}

{/if}

</td>
</tr>
</table>
</center>

{include file="right.tpl"}
