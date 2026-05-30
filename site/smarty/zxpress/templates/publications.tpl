{include file="top.tpl"}

<br>
<center>
<table cellpadding="0" cellspacing="0" border="0" width="640">
<tr>
<td align="left">

{if $pub_article_detail}

<p style="font-size: 13px; margin-bottom: 8px;">
	<a href="{$host}publications.php">Публикации</a> &rarr;
	<a href="{$host}publications.php?id={$pub.id}">{$pub.title_ru}</a>
</p>

<h1>{$pub_article_detail.title_ru}</h1>

{if $pub_article_detail.pages_display}
<p style="font-size: 13px; color: #888; margin: 4px 0 8px 0;">{$pub_article_detail.pages_display}</p>
{/if}

{if $pub_article_detail.body_html}
<div style="margin-top: 20px; font-size: 16px; line-height: 1.5;">{$pub_article_detail.body_html nofilter}</div>
{/if}

{if $pub_article_images && $pub_article_images|@count gt 0}
<div style="margin-top: 20px;">
{foreach from=$pub_article_images item=img}
<div style="margin-bottom: 16px;">
	<a href="{$img.original_url}" target="_blank" rel="noopener">
		<img src="{$img.display_src}" alt="" style="width: 100%; max-width: 100%; height: auto; border: 1px solid #ccc;">
	</a>
</div>
{/foreach}
</div>
{/if}

{if $pub_article_detail.files && $pub_article_detail.files|@count gt 0}
<div style="margin-top: 20px; padding: 12px; background: #f5f5f0; border: 1px solid #ddd;">
<b>Файлы:</b>
{foreach from=$pub_article_detail.files item=f}
<div style="margin-top: 6px; font-size: 14px;">
	<a href="{$f.file_url}" target="_blank" rel="noopener">{$f.format_label}</a>
	{if $f.size_display}<span style="color: #888;">({$f.size_display})</span>{/if}
</div>
{/foreach}
</div>
{/if}

{elseif $pub}

<p style="font-size: 13px; margin-bottom: 8px;">
	<a href="{$host}publications.php">← Все публикации</a>
</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%">
<tr valign="top">
{if $pub_cover}
<td width="200" style="padding-right: 16px;">
	<a href="{$pub_cover.original_url}" target="_blank" rel="noopener">
		<img src="{$pub_cover.thumb_src}" alt="" style="width: 180px; max-width: 180px; height: auto; border: 1px solid #ccc;">
	</a>
</td>
{/if}
<td>
<h1 style="margin-top: 0;">{$pub.title_ru}</h1>

<div style="font-size: 14px; color: #555; margin-bottom: 8px;">
{if $pub.type_label}<span>{$pub.type_label}</span>{/if}
{if $pub.published_display} &nbsp;·&nbsp; {$pub.published_display}{/if}
{if $pub.geo_display} &nbsp;·&nbsp; {$pub.geo_display}{/if}
</div>
</td>
</tr>
</table>

{if $pub_articles && $pub_articles|@count gt 0}
<div style="margin-top: 24px;">
<h2 style="font-size: 16px; margin-bottom: 12px;">Содержание ({$pub_articles|@count})</h2>

{foreach from=$pub_articles item=a}
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
<tr valign="top">
{if $a.cover}
<td width="100" style="padding-right: 10px;">
	<a href="{$host}publications.php?id={$pub.id}&amp;article={$a.id}">
		<img src="{$a.cover.thumb_src}" alt="" width="90" style="width: 90px; max-width: 90px; height: auto; border: 1px solid #ccc;">
	</a>
</td>
{/if}
<td>
	<a href="{$host}publications.php?id={$pub.id}&amp;article={$a.id}" style="font-size: 15px; font-weight: bold;">{$a.title_ru}</a>
	{if $a.pages_display}
	<span style="font-size: 12px; color: #888; margin-left: 8px;">{$a.pages_display}</span>
	{/if}
	{if $a.summary_html}
	<div style="margin-top: 4px; font-size: 14px; color: #444;">{$a.summary_html nofilter}</div>
	{/if}
	{if $a.files && $a.files|@count gt 0}
	<div style="margin-top: 4px; font-size: 13px;">
		{foreach from=$a.files item=f name=fl}
		<a href="{$f.file_url}" target="_blank" rel="noopener">{$f.format_label}</a>{if $f.size_display} <span style="color:#888;">({$f.size_display})</span>{/if}{if !$smarty.foreach.fl.last}, {/if}
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
<div style="margin-top: 20px;">
{foreach from=$pub_images item=img}
<div style="margin-bottom: 16px;">
	<a href="{$img.original_url}" target="_blank" rel="noopener">
		<img src="{$img.display_src}" alt="" style="width: 100%; max-width: 100%; height: auto; border: 1px solid #ccc;">
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
<p style="font-size: 13px; line-height: 1.8; margin-bottom: 16px;">
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
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 18px; padding-bottom: 12px;">
<tr valign="top">
<td width="140" style="padding-right: 12px;">
{if $row.cover}
	<a href="{$host}publications.php?id={$row.id}">
		<img src="{$row.cover.thumb_src}" alt="" width="128" style="width: 128px; max-width: 128px; height: auto; border: 1px solid #ccc;">
	</a>
{else}
	<div style="width: 128px; height: 170px; background: #eee; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #aaa;">нет обложки</div>
{/if}
{if $row.created_display}
<div style="margin-top: 4px; font-size: 11px; color: #666;">опубликовано: {$row.created_display}</div>
{/if}
</td>
<td>
	<a href="{$host}publications.php?id={$row.id}" style="font-size: 16px; font-weight: bold;">{$row.title_ru}</a>
	<div style="margin-top: 4px; font-size: 13px; color: #888;">
		{$row.type_label}
		{if $row.published_display} &nbsp;·&nbsp; {$row.published_display}{/if}
		{if $row.geo_display} &nbsp;·&nbsp; {$row.geo_display}{/if}
		{if $row.articles_count gt 0} &nbsp;·&nbsp; {$row.articles_count} статей{/if}
	</div>
	{if $row.summary_html}
	<div style="margin-top: 6px; font-size: 14px;">{$row.summary_html nofilter}</div>
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
<div style="margin-top: 20px; font-size: 13px;">
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
