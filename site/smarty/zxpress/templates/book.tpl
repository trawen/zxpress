{include file="top.tpl"}



<table cellpadding=0 cellspacing=0 class="page-pad-8 book-page-table" border=0>
<tr class="book-page-row">
{if $screens[0].id}
<td width=280 valign="top" class="book-covers-cell">



{section name=n loop=$screens}
<table cellpadding="0" cellspacing="0">
<tr>
<td class="book-frame-top" colspan="3">
</td>
</tr>
<tr>
<td class="book-frame-side book-frame-side--left">
</td>
<td class="book-frame-cell"><img width="256" src="pictures/{$screens[n].id}.jpg" title="{$press.title1|strip_tags|escape:'html'}{if $press.title2} — {$press.title2|strip_tags|escape:'html'}{/if}"></td>
<td class="book-frame-side book-frame-side--right">
</td>
</tr>
<tr>
<td class="book-frame-bottom" colspan="3">
</td>
</tr>
</table>
<br>
{/section}

</td>
{/if}
<td align="left" valign="top" class="book-content-cell">

<!-- <span style="display: inline; font: 13pt/10pt; ">
{if $press.type eq 0}Книга{elseif $press.type eq 1}Журнал{else}Газета{/if}</span> -->

<div class="book-header">
<H1 class="h1"> {$press.title1 nofilter}</h1>
<DIV class="gln"></DIV>

<!-- <DIV class="gln2"></DIV> -->
<div class="book-subtitle">{$press.title2 nofilter}</div>
</div>

<div class="book-details">
<br>
<div class="book-meta">

{if $press.series}
<div class="book-meta-row">
<span class="book-meta-label">Серия: </span>{$press.series nofilter}</div>
{/if}

{if $press.date neq 1970}
<div class="book-meta-row">
<span class="book-meta-label">Дата: </span>{$press.date} год</div>
{/if}

{if $press.publisher neq "«»" and $press.publisher}
<div class="book-meta-row">
<span class="book-meta-label">Издательство: </span>{$press.publisher nofilter}</div>
{/if}

{if $press.authors}
<div class="book-meta-row">
<span class="book-meta-label">Авторы: </span>{$press.authors nofilter}</div>
{/if}

<div class="book-meta-row">
<span class="book-meta-label">Объём: </span>{$press.pages} страниц</div>

{if $press.circulation}
<div class="book-meta-row">
<span class="book-meta-label">Тираж: </span>{$press.circulation} экземпляров</div>
{/if}

{if $press.isbn}
<div class="book-meta-row">
<span class="book-meta-label">ISBN: </span>{$press.isbn}</div>
{/if}

{if $press.annotation}
<div class="book-meta-row"><br>
<span class="book-meta-label">Аннотация издательства: </span>{$press.annotation nofilter}</div>
{/if}
</div>

<!-- <b>{$press.name} ({$press.country_name})</b>
 -->

<br>
<br>



{section name=n loop=$files}
<a href="books_files/{$files[n].file_name}" title="Скачать книгу - {$press.title1|strip_tags|escape:'html'}{if $press.title2} - {$press.title2|strip_tags|escape:'html'}{/if}">
<div class="book-file-tile" align="center">
<span class="book-file-name">{$files[n].file_name}</span><br>
<span class="book-file-meta">
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







<div class="u-clearfix"></div>




{if $other_articles}

<br><br><div class="book-toc-heading">СОДЕРЖАНИЕ:</div>


<ol>
{section name=n loop=$other_articles}
<li class="book-toc-item">
<div class="book-toc-link">
<a href="book_articles.php?id={$other_articles[n].ch_id}"> {$other_articles[n].ch_title nofilter}</a>
</div>
</li>
{/section}
</ol>
{/if}




</div>

</td>
</tr>
</table>



{include file="right.tpl"}
{include file="footer.tpl"}