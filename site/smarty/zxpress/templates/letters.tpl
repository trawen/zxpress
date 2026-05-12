{include file="top.tpl"}

<br>
<center>
<table cellpadding="0" cellspacing="0" border="0" width="640">
<tr>
<td align="left">

{if $letter}

<h1>{$letter.title_ru}</h1>

<p style="font-size: 14px; margin: 8px 0 4px 0;">
	<b>От:</b> {$letter.from_author_display}
</p>
<p style="font-size: 14px; margin: 0 0 8px 0;">
	<b>Кому:</b> {$letter.to_author_display}
	{if $letter.date_display}
		&nbsp;·&nbsp; <span>{$letter.date_display}</span>
	{/if}
</p>

{if $letter.summary_html}
<div style="margin: 12px 0; font-size: 16px;">{$letter.summary_html nofilter}</div>
{/if}

{if $letter_images && $letter_images|@count gt 0}
<div style="margin-top: 20px;">
{foreach from=$letter_images item=img}
<div style="margin-bottom: 16px;">
	<a href="{$img.original_url}" target="_blank" rel="noopener">
		<img src="{$img.display_src}" alt="" style="width: 100%; max-width: 100%; height: auto; border: 1px solid #ccc;">
	</a>
</div>
{/foreach}
</div>
{/if}

{if $letter.body_html}
<div style="margin-top: 20px; font-size: 16px; line-height: 1.5;">{$letter.body_html nofilter}</div>
{/if}

{elseif $letter_not_found}

<h1>Письмо не найдено</h1>
<p><a href="{$host}snailmail.php{if $lng eq 'eng'}?lng=eng{/if}">К каталогу писем</a></p>

{else}

<h1>Бумажные письма середины 90-х годов от участников ZX Spectrum сцены</h1>
<div style="margin-bottom: 12px;"><img src="{$host}img/snailmail.png" alt="Snailmail" style="max-width: 100%; height: auto;"></div>
<h2><p><i>Swapping и Snailmail — культура обмена дискетами, кассетами, журналами по обычной (улиточной) почте, без которой демосцена СССР и Восточной Европы 80–90-х, возможно, не существовала бы.</p> <p>До интернета именно письма связывали активных пользователей из разных городов и стран. Обменом как правило занимался специальный человек - своппер.
Он поддерживал контакты с десятками, иногда сотнями людей. От него зависело, насколько быстро новые релизы, софт, игры и демо разойдутся по сцене. Не редко свопперы занимались так же продажей софта в своем городе.</p><p>

Здесь собраны сканы бумажных писем участников отечественной ZX Spectrum сцены. В письмах вы найдете — новости, планы, обсуждение софта, игр и демосцены.</p></i></h2>

{if $letter_author_filters && $letter_author_filters|@count gt 0}
<p style="font-size: 13px; line-height: 1.8; margin-bottom: 16px;">
	<b>Фильтровать письма по автору:</b>
	{foreach from=$letter_author_filters item=auth name=af}
		{if !$smarty.foreach.af.first}, {/if}
		{if $filter_from && $auth.id == $filter_from}
			<b>{$auth.author_display}</b>
		{else}
			<a href="{$host}snailmail.php?from={$auth.id}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$auth.author_display}</a>
		{/if}
	{/foreach}
	{if $filter_from}
		 , <a href="{$host}snailmail.php{if $lng eq 'eng'}?lng=eng{/if}">все</a>
	{/if}
</p>
{/if}

{if $letters_rows && $letters_rows|@count gt 0}
{foreach from=$letters_rows item=row}
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 18px; padding-bottom: 12px;">
<tr valign="top">
<td width="140" style="padding-right: 12px;">
{if $row.cover}
	<a href="{$row.cover.original_url}" target="_blank" rel="noopener">
		<img src="{$row.cover.thumb_src}" alt="" width="128" style="width: 128px; max-width: 128px; height: auto; border: 1px solid #ccc;">
	</a>
{else}
	<div style="width: 128px; height: 96px; background: #eee; border: 1px solid #ccc;"></div>
{/if}
{if $row.published_display}
<div style="margin-top: 4px; font-size: 11px; color: #666;">опубликовано: {$row.published_display}</div>
{/if}
</td>
<td>
	<a href="{$host}snailmail.php?id={$row.id}{if $lng eq 'eng'}&amp;lng=eng{/if}" style="font-size: 16px; font-weight: bold;">{$row.title_ru}</a>
	{if $row.summary_html}
	<div style="margin-top: 6px; font-size: 16px;">{$row.summary_html nofilter}</div>
	{/if}
	<div style="margin-top: 6px; font-size: 14px; color: #555;">
		От <b><a href="{$host}snailmail.php?from={$row.author_from}{if $lng eq 'eng'}&amp;lng=eng{/if}" style="color: inherit;">{$row.from_author_display}</a></b>
		 к <b><a href="{$host}snailmail.php?from={$row.author_to}{if $lng eq 'eng'}&amp;lng=eng{/if}" style="color: inherit;">{$row.to_author_display}</a></b>
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
<p>Здесь пока нет писем.</p>
{/if}

{if $letters_total_pages gt 1}
<div style="margin-top: 20px; font-size: 13px;">
	Страницы:
	{section name=pg loop=$letters_total_pages}
		{assign var=pnum value=$smarty.section.pg.iteration}
		{if $pnum == $letters_page}
			<b>{$pnum}</b>
		{else}
			<a href="{$host}snailmail.php?p={$pnum}{if $filter_from}&amp;from={$filter_from}{/if}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$pnum}</a>
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
