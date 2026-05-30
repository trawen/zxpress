{include file="top.tpl"}



<H1>Бумажные книги и журналы для ZX Spectrum</H1>

<br>


<table class="books-table">
{section name=n loop=$books}
<tr class="books-row">
<td class="books-cover-cell">

{if $books[n].image_id}
<img src="{$host}pictures/thumbs/{$books[n].image_id}.jpg" width="80" border=0 class="search_img">
{else}
<img src="{$host}img/nobook.jpg" width="80" border=0>
{/if}
</td>

<td valign="top" class="books-cell">


<a class="books-title-link" href="{$host}book.php?id={$books[n].id}" class="catalog-book-title">{$books[n].title1}</a>


{if $books[n].title2}<div>{$books[n].title2} {$books[n].series}</div>{/if}

<br><br>

<div>

{if $books[n].online}<a class="books-action-link" href="{$host}book.php?id={$books[n].id}" class="catalog-book-title"><img src="{$host}img/read.png" border=0 title="Читать — {$books[n].title1}"></a> &nbsp;{/if}

{if $books[n].file_id}<img src="{$host}img/download.png" border=0 title="Скачать — {$books[n].title1}"> &nbsp;{/if}

{if $books[n].date neq 1970 and $books[n].publisher and $books[n].publisher neq "«»"}
© {$books[n].date} {$books[n].publisher}
{elseif $books[n].publisher and $books[n].publisher neq "«»"}
© {$books[n].publisher}
{/if}



</div>


</td>
</tr>

{/section}

</table>





{include file="right.tpl"}
{include file="footer.tpl"}
