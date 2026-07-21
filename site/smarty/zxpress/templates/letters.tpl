{include file="top.tpl"}

<br>
<center>
<table class="pub-layout snailmail-layout" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="left">

{if $letter}

<p class="pub-breadcrumbs">
	<a href="{$letters_catalog_url}">{if $lng eq 'eng'}← All letters{else}← Все бумажные письма{/if}</a>
</p>

<h1>{$letter.title_display}</h1>

<p class="letter-meta-from">
	<b>{if $lng eq 'eng'}From:{else}От:{/if}</b>
	{if $letter.author_from}<a href="{$letter.from_author_url}" class="u-link-inherit">{$letter.from_author_display}</a>{else}{$letter.from_author_display}{/if}
</p>
<p class="letter-meta-to">
	<b>{if $lng eq 'eng'}To:{else}Кому:{/if}</b>
	{if $letter.author_to}<a href="{$letter.to_author_url}" class="u-link-inherit">{$letter.to_author_display}</a>{else}{$letter.to_author_display}{/if}
	{if $letter.date_display}
		&nbsp;·&nbsp; <span>{$letter.date_display}</span>
	{/if}
</p>

{if $letter.summary_html}
<p class="letter-summary-block"><b>{if $lng eq 'eng'}Letter summary:{else}Краткое содержимое бумажного письма:{/if}</b> <span class="letter-summary">{$letter.summary_html nofilter}</span></p>
{/if}

{if $letter_images && $letter_images|@count gt 0}
<div class="pub-images">
{foreach from=$letter_images item=img}
<div class="pub-image-item">
	<a href="{$img.original_url}" target="_blank" rel="noopener">
		<img src="{$img.display_src}" alt="" class="pub-image">
	</a>
</div>
{/foreach}
</div>
{/if}

{if $letter.body_html}
<div class="pub-body">{$letter.body_html nofilter}</div>
{/if}

{elseif $letter_not_found}

<h1>{if $lng eq 'eng'}Letter not found{else}Бумажное письмо не найдено{/if}</h1>
<p><a href="{$letters_catalog_url}">{if $lng eq 'eng'}Back to the letter catalog{else}К каталогу бумажных писем{/if}</a></p>

{else}

{if $filter_author && $filter_author_display}
<h1 class="letter-filter-h1">{if $lng eq 'eng'}Letters with {$filter_author_display}{else}Письма с участием {$filter_author_display}{/if}</h1>
<p class="pub-breadcrumbs">
	<a href="{$letters_catalog_url}">{if $lng eq 'eng'}← All letters{else}← Все бумажные письма{/if}</a>
</p>
{else}
<h1>{if $lng eq 'eng'}Paper letters from mid-1990s members of the ZX Spectrum scene{else}Бумажные письма середины 90-х годов от участников ZX Spectrum сцены{/if}</h1>
<div class="letter-banner"><img src="{$host}img/snailmail.png" alt="Snailmail" class="letter-banner-img"></div>
<h2><p><i>{if $lng eq 'eng'}
Swapping and snailmail — the culture of exchanging floppy disks, cassettes, and magazines via regular (snail) mail, without which the demoscene of the USSR and Eastern Europe in the 80s–90s might not have existed.</p> <p>Before the internet, paper letters connected active users from different cities and countries. Exchange was usually handled by a dedicated person — a swapper.
They maintained contacts with dozens, sometimes hundreds of people. How quickly new releases, software, games and demos spread across the scene depended on them. Swappers often also sold software in their city.</p><p>

Here are scans of paper letters from the domestic ZX Spectrum scene. In these letters you will find news, plans, and discussions of software, games and the demoscene.</p>{else}
Swapping и Snailmail — культура обмена дискетами, кассетами, журналами по обычной (улиточной) почте, без которой демосцена СССР и Восточной Европы 80–90-х, возможно, не существовала бы.</p> <p>До интернета именно бумажные письма связывали активных пользователей из разных городов и стран. Обменом как правило занимался специальный человек - своппер.
Он поддерживал контакты с десятками, иногда сотнями людей. От него зависело, насколько быстро новые релизы, софт, игры и демо разойдутся по сцене. Не редко свопперы занимались так же продажей софта в своем городе.</p><p>

Здесь собраны сканы бумажных писем участников отечественной ZX Spectrum сцены. В этих бумажных письмах вы найдете — новости, планы, обсуждение софта, игр и демосцены.</p>{/if}</i></h2>
{/if}

{if $letter_author_filters && $letter_author_filters|@count gt 0}
<p class="pub-filters">
	<b>{if $lng eq 'eng'}Filter letters by correspondent:{else}Фильтровать бумажные письма по корреспонденту:{/if}</b>
	{foreach from=$letter_author_filters item=auth name=af}
		{if !$smarty.foreach.af.first}, {/if}
		{if $filter_author && $auth.id == $filter_author}
			<b>{$auth.author_display}<sup class="pub-filter-count">{$auth.letter_count}</sup></b>
		{else}
			<a href="{$auth.author_url}">{$auth.author_display}<sup class="pub-filter-count">{$auth.letter_count}</sup></a>
		{/if}
	{/foreach}
	{if $filter_author}
		 , <a href="{$letters_catalog_url}">{if $lng eq 'eng'}all{else}все{/if}</a>
	{/if}
</p>
{/if}

{if $letters_rows && $letters_rows|@count gt 0}
{foreach from=$letters_rows item=row}
<table cellpadding="0" cellspacing="0" border="0" width="100%" class="pub-list-item">
<tr valign="top">
<td width="140" class="pub-list-cover-cell">
{if $row.cover}
	<a href="{$row.public_url}">
		<img src="{$row.cover.thumb_src}" alt="" width="128" class="pub-list-thumb">
	</a>
{else}
	<div class="letter-list-placeholder"></div>
{/if}
{if $row.published_display}
<div class="pub-list-date">{if $lng eq 'eng'}published:{else}опубликовано:{/if} {$row.published_display}</div>
{/if}
</td>
<td>
	<a href="{$row.public_url}" class="pub-list-title">{$row.title_display}</a>
	{if $row.summary_html}
	<div class="pub-list-summary pub-list-summary--lg">{$row.summary_html nofilter}</div>
	{/if}
	<div class="letter-list-correspondents">
		{if $lng eq 'eng'}From{else}От{/if} <b>{if $row.from_author_url}<a href="{$row.from_author_url}" class="u-link-inherit">{$row.from_author_display}</a>{else}{$row.from_author_display}{/if}</b>
		 {if $lng eq 'eng'}to{else}к{/if} <b>{if $row.to_author_url}<a href="{$row.to_author_url}" class="u-link-inherit">{$row.to_author_display}</a>{else}{$row.to_author_display}{/if}</b>
		{if $row.date_display}
			&nbsp;·&nbsp; {$row.date_display}
		{/if}
	</div>
</td>
</tr>
</table>
<hr>
<br>
{/foreach}
{else}
<p>{if $lng eq 'eng'}No letters here yet.{else}Здесь пока нет бумажных писем.{/if}</p>
{/if}

{if $letters_total_pages gt 1}
<div class="pub-pagination">
	{if $lng eq 'eng'}Pages:{else}Страницы:{/if}
	{section name=pg loop=$letters_total_pages}
		{assign var=pnum value=$smarty.section.pg.iteration}
		{if $pnum == $letters_page}
			<b>{$pnum}</b>
		{else}
			<a href="{$letters_catalog_url}?p={$pnum}">{$pnum}</a>
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
