{include file="top.tpl"}

<br>
<center>
<table class="pub-layout" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="left">

{if $per_article && $periodical && $per_issue}

<p class="pub-breadcrumbs">
	<a href="{$per_url_catalog|escape:'html'}">{if $lng eq 'eng'}Periodicals{else}Бумажные газеты и журналы{/if}</a> &rarr;
	<a href="{$per_url_periodical|escape:'html'}">{$periodical.title_display|escape:'html'}</a> &rarr;
	<a href="{$per_issue.url|escape:'html'}">{if $per_issue.preview_caption}{$per_issue.preview_caption|escape:'html'}{else}{$per_issue.label|escape:'html'}{/if}</a> &rarr;
	{$per_article.title_display|escape:'html'}
</p>

<h1>{$per_article.title_display|escape:'html'}</h1>

{if $per_article.pages_display}
<p class="pub-meta">{$per_article.pages_display}</p>
{/if}

{if $per_article.abstract_html}
<div class="pub-toc-summary">{$per_article.abstract_html nofilter}</div>
{/if}

{if $per_article.body_html}
<div class="pub-body">{$per_article.body_html nofilter}</div>
{/if}

{elseif $per_issue && $periodical}

<p class="pub-breadcrumbs">
	<a href="{$per_url_catalog|escape:'html'}">{if $lng eq 'eng'}Periodicals{else}Бумажные газеты и журналы{/if}</a> &rarr;
	<a href="{$per_url_periodical|escape:'html'}">{$periodical.title_display|escape:'html'}</a> &rarr;
	{if $per_issue.preview_caption}{$per_issue.preview_caption|escape:'html'}{else}{$per_issue.label|escape:'html'}{/if}
</p>

<table class="pub-header-table" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
{if $per_issue.cover}
<td class="pub-cover-cell">
	<a href="{$per_issue.cover.jpg_url}" target="_blank" rel="noopener">
		<img src="{$per_issue.cover.display_src}" alt="" class="pub-cover-img">
	</a>
</td>
{/if}
<td>
<h1 class="pub-title">{$per_issue.label|escape:'html'}</h1>
<div class="pub-info">
	<span>{$periodical.title_display|escape:'html'}</span>
	{if $per_issue.date_display} &nbsp;·&nbsp; {$per_issue.date_display}{/if}
	{if $per_issue.pages} &nbsp;·&nbsp; {if $lng eq 'eng'}{$per_issue.pages} pp.{else}{$per_issue.pages} стр.{/if}{/if}
	{if $per_issue.circulation} &nbsp;·&nbsp; {if $lng eq 'eng'}circ. {$per_issue.circulation}{else}тираж {$per_issue.circulation}{/if}{/if}
</div>
{if $per_issue.description_html}
<div class="pub-toc-summary">{$per_issue.description_html nofilter}</div>
{/if}
</td>
</tr>
</table>

{if $per_issue.files && $per_issue.files|@count gt 0}
<div class="pub-files">
<b>{if $lng eq 'eng'}Files:{else}Файлы:{/if}</b>
{foreach from=$per_issue.files item=f}
<div class="pub-file-item">
	<a href="{$f.file_url}" target="_blank" rel="noopener">{$f.name|escape:'html'}</a>
	<span class="pub-muted">({$f.format_label}{if $f.size_display}, {$f.size_display}{/if})</span>
</div>
{/foreach}
</div>
{/if}

{if $per_articles && $per_articles|@count gt 0}
<div class="pub-toc">
<h2 class="pub-toc-heading">{if $lng eq 'eng'}Contents ({$per_articles|@count}){else}Содержание ({$per_articles|@count}){/if}</h2>
{foreach from=$per_articles item=a}
<table class="pub-toc-item" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td>
	<a href="{$a.url|escape:'html'}" class="pub-toc-title">{$a.title_display|escape:'html'}</a>
	{if $a.pages_display}
	<span class="pub-toc-pages">{$a.pages_display}</span>
	{/if}
	{if $a.abstract_html}
	<div class="pub-toc-summary">{$a.abstract_html nofilter}</div>
	{/if}
</td>
</tr>
</table>
{/foreach}
</div>
{/if}

{elseif $periodical}

<p class="pub-breadcrumbs">
	<a href="{$per_url_catalog|escape:'html'}">{if $lng eq 'eng'}Periodicals{else}Бумажные газеты и журналы{/if}</a> &rarr;
	{$periodical.title_display|escape:'html'}
</p>

<h1 class="pub-title">{$periodical.title_display|escape:'html'}</h1>

<div class="pub-info">
	{if $periodical.issn}<span>ISSN {$periodical.issn|escape:'html'}</span>{/if}
	{if $periodical.years_display} &nbsp;·&nbsp; {$periodical.years_display}{/if}
	{if $periodical.geo_display} &nbsp;·&nbsp; {$periodical.geo_display|escape:'html'}{/if}
	{if $periodical.publishers_display} &nbsp;·&nbsp; {$periodical.publishers_display|escape:'html'}{/if}
</div>

{if $periodical.description_html}
<div class="pub-toc-summary">{$periodical.description_html nofilter}</div>
{/if}

{if $per_issues && $per_issues|@count gt 0}
<div class="pub-toc">
<h2 class="pub-toc-heading">{if $lng eq 'eng'}Issues{else}Выпуски{/if}</h2>
<div class="per-issues-grid">
{foreach from=$per_issues item=i}
<div class="per-issues-grid__item">
<div class="per-issues-grid__caption">{$i.preview_caption|escape:'html'}</div>
<a href="{$i.url|escape:'html'}" class="per-issues-grid__link">
{if $i.cover}
<img src="{$i.cover.thumb_src}" alt="{$i.preview_caption|escape:'html'}" class="per-issues-grid__thumb" width="160" height="auto">
{else}
<span class="per-issues-grid__placeholder" aria-hidden="true"></span>
{/if}
</a>
</div>
{/foreach}
</div>
</div>
{/if}

{elseif $per_not_found}

<h1>{if $lng eq 'eng'}Periodical not found{else}Издание не найдено{/if}</h1>
<p><a href="{$per_url_catalog|escape:'html'}">{if $lng eq 'eng'}Back to catalog{else}К каталогу{/if}</a></p>

{else}

<h1>{if $lng eq 'eng'}Periodicals{else}Бумажные газеты и журналы{/if}</h1>

{if $per_rows && $per_rows|@count gt 0}
{foreach from=$per_rows item=row}
<table class="pub-list-item" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr valign="top">
<td>
	<a href="{$row.url|escape:'html'}" class="pub-list-title">{$row.title_display|escape:'html'}</a>
	<div class="pub-list-summary">
		{if $row.years_display}{$row.years_display}{/if}
		{if $row.geo_display}{if $row.years_display} · {/if}{$row.geo_display|escape:'html'}{/if}
		{if $row.issues_count}{if $row.years_display || $row.geo_display} · {/if}{if $lng eq 'eng'}{$row.issues_count} issues{else}{$row.issues_count} выпусков{/if}{/if}
	</div>
	{if $row.description_html}
	<div class="pub-list-summary pub-list-summary--lg">{$row.description_html nofilter}</div>
	{/if}
</td>
</tr>
</table>
<hr>
{/foreach}
{else}
<p>{if $lng eq 'eng'}No periodicals yet.{else}Изданий пока нет.{/if}</p>
{/if}

{/if}

</td>
</tr>
</table>
</center>

{include file="right.tpl"}
{include file="footer.tpl"}
