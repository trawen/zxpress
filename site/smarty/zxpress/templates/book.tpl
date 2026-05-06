{include file="top.tpl"}



<table cellpadding=0 cellspacing=0 style="padding: 8px" border=0>
<tr>
{if $screens[0].id}
<td width=280 valign="top">



{section name=n loop=$screens}
<table cellpadding="0" cellspacing="0">
<tr>
<td style="width: 262px; height: 3px; background-image: url(img/border_top.png); background-repeat: repeat-x" colspan="3">
</td>
</tr>
<tr>
<td style="width: 3px; height: 100%; background-image: url(img/border_left.png); background-repeat: repeat-y; padding-right: 2px">
</td>
<td style="padding-top: 1px; margin: 0px"><img width="256" src="pictures/{$screens[n].id}.jpg" title="{$press.title1|strip_tags|escape:'html'}{if $press.title2} — {$press.title2|strip_tags|escape:'html'}{/if}"></td>
<td style="width: 3px; height: 100%; background-image: url(img/border_left.png); background-repeat: repeat-y; padding-left: 1px">
</td>
</tr>
<tr>
<td style="width: 262px; height: 3px; background-image: url(img/border_top.png); background-repeat: repeat-x; padding-bottom: 1px" colspan="3">
</td>
</tr>
</table>
<br>
{/section}

</td>
{/if}
<td align="left" valign="top">

<!-- <span style="display: inline; font: 13pt/10pt; ">
{if $press.type eq 0}Книга{elseif $press.type eq 1}Журнал{else}Газета{/if}</span> -->

<H1 class="h1"> {$press.title1 nofilter}</h1>
<DIV class="gln"></DIV>

<!-- <DIV class="gln2"></DIV> -->
<div style="padding-top: 8px; font: 13pt/10pt">{$press.title2 nofilter}</div>


<br>
<div style="padding-left: 16px">

{if $press.series}
<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">Серия: </span>{$press.series nofilter}</div>
{/if}

{if $press.date neq 1970}
<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">Дата: </span>{$press.date} год</div>
{/if}

{if $press.publisher neq "«»" and $press.publisher}
<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">Издательство: </span>{$press.publisher nofilter}</div>
{/if}

{if $press.authors}
<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">Авторы: </span>{$press.authors nofilter}</div>
{/if}

<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">Объём: </span>{$press.pages} страниц</div>

{if $press.circulation}
<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">Тираж: </span>{$press.circulation} экземпляров</div>
{/if}

{if $press.isbn}
<div style="padding-top: 6px">
<span style="font: bold 10pt Verdana;">ISBN: </span>{$press.isbn}</div>
{/if}

{if $press.annotation}
<div style="padding-top: 6px"><br>
<span style="font: bold 10pt Verdana;">Аннотация издательства: </span>{$press.annotation nofilter}</div>
{/if}
</div>

<!-- <b>{$press.name} ({$press.country_name})</b>
 -->

<br>
<br>



{section name=n loop=$files}
<a href="books_files/{$files[n].file_name}" title="Скачать книгу - {$press.title1|strip_tags|escape:'html'}{if $press.title2} - {$press.title2|strip_tags|escape:'html'}{/if}">
<div style="float: left; padding: 5px 5px 4px 6px;" align="center">
<span style="font: bold 10pt verdana">{$files[n].file_name}</span><br>
<span style="font: bold 8pt verdana; color: #999">
{if $files[n].file_type eq 1}
PDF
{elseif $files[n].file_type eq 2}
DJVU
{elseif $files[n].file_type eq 3}
HTML
{elseif $files[n].file_type eq 4}
TXT
{elseif $files[n].file_type eq 5}
JPG
{elseif $files[n].file_type eq 6}
WORD
{/if} / {$files[n].file_size}Kb
</span>
</div>
</a>
{/section}







<div style="clear: both"></div>




{if $other_articles}

<br><br><div style="font: bold 12pt Georgia">СОДЕРЖАНИЕ:</div>


<ol>
{section name=n loop=$other_articles}
<li style="padding-top: 4px">
<div style="font: 10pt/12pt Verdana; padding-left: 16px">
<a href="book_articles.php?id={$other_articles[n].ch_id}"> {$other_articles[n].ch_title nofilter}</a>
</div>
</li>
{/section}
</ol>
{/if}




</td>
</tr>
</table>



{include file="right.tpl"}
{include file="footer.tpl"}