{include file="top.tpl"}
<TABLE cellSpacing=0 cellPadding=0 align="center">
<TBODY>
<TR>
<TD>

<a name="magazines"></a>







{if $lng}











<div class="catalog-page-title-wrap"><H1>Library of electronic newspapers and magazines for ZX Sepectrum</H1></div>

<br><br>

<table cellpadding=0 cellspacing=0>
<tr>
<td></td>
<td class="catalog8" class="catalog-th-times">TITLE</td>
<td class="catalog8" class="catalog-th-times">TYPE</td>
<td class="catalog8" class="catalog-th-times" nowrap>YEAR</td>
<td class="catalog8" class="catalog-th-times" nowrap>CITY</td>
<td class="catalog8" class="catalog-th-times">ISSUE</td>
<td class="catalog8" class="catalog-th-times">ARTICLES</td>
</tr>




{section name=n loop=$catalog}


{if $catalog[n].off}
<tr>

{if $catalog[n].letter}
<td class="catalog2" valign="bottom">
<div class="catalog-letter">{$catalog[n].letter}</div></td>
<td class="catalog2" valign="bottom"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[n].title_plain}</b></a></td>
<td class="catalog2" valign="bottom">{if $catalog[n].type eq 1}magazine{elseif $catalog[n].type eq 0}newspaper{/if}</td>


<td class="catalog2" valign="bottom" align="center" nowrap>
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>
<td class="catalog2" valign="bottom" nowrap><img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {$catalog[n].name_eng}</td>
<td class="catalog2" valign="bottom" align="center">{$catalog[n].numbers}</td>
<td class="catalog2" valign="bottom" align="center"><b>{if $catalog[n].online_articles}{$catalog[n].online_articles}{else}—{/if}</b></td>
{else}
<td></td>
<td class="catalog"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[n].title_plain}</b></a></td>
<td class="catalog">{if $catalog[n].type eq 1}magazine{elseif $catalog[n].type eq 0}newspaper{/if}</td>
<td class="catalog" align="center" nowrap>
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>
<td class="catalog"><img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {$catalog[n].name_eng}</td>
<td class="catalog" align="center">{$catalog[n].numbers}</td>
<td class="catalog" align="center"><b>{if $catalog[n].online_articles}{$catalog[n].online_articles}{else}—{/if}</b></td>
{/if}


</tr>
{else}
<tr>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td></td>
<td></td>
<td></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>


{/if}
<tr><td colspan="8" class="catalog-spacer-4"></tr>
{/section}	
</table>












{else}






<div class="catalog-page-title-wrap"><H1>Библиотека электронных газет и журналов для ZX Spectrum</H1></div>

<br><br>

<table cellpadding=0 cellspacing=0>
<tr>
<td></td>
<td class="catalog8" class="catalog-th-times">НАЗВАНИЕ</td>
<td class="catalog8" class="catalog-th-times">ФОРМА</td>
<td class="catalog8" class="catalog-th-times" nowrap>ГОДА ИЗДАНИЯ</td>
<td class="catalog8" class="catalog-th-times" nowrap>ГОРОД</td>
<td class="catalog8" class="catalog-th-times">ВЫПУСКИ</td>
<td class="catalog8" class="catalog-th-times">СТАТЬИ</td>
</tr>




{section name=n loop=$catalog}


{if $catalog[n].off}
<tr>

{if $catalog[n].letter}
<td class="catalog2" valign="bottom">
<div class="catalog-letter">{$catalog[n].letter}</div></td>
<td class="catalog2" valign="bottom"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[n].title_plain}</b></a></td>
<td class="catalog2" valign="bottom">{if $catalog[n].type eq 1}журнал{elseif $catalog[n].type eq 0}газета{/if}</td>


<td class="catalog2" valign="bottom" align="center">
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>
<td class="catalog2" valign="bottom" nowrap><img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {$catalog[n].name}</td>
<td class="catalog2" valign="bottom" align="center">{$catalog[n].numbers}</td>
<td class="catalog2" valign="bottom" align="center"><b>{if $catalog[n].online_articles}{$catalog[n].online_articles}{else}—{/if}</b></td>
{else}
<td></td>
<td class="catalog"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[n].title_plain}</b></a></td>
<td class="catalog">{if $catalog[n].type eq 1}журнал{elseif $catalog[n].type eq 0}газета{/if}</td>
<td class="catalog" align="center">
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>
<td class="catalog"><img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {$catalog[n].name}</td>
<td class="catalog" align="center">{$catalog[n].numbers}</td>
<td class="catalog" align="center"><b>{if $catalog[n].online_articles}{$catalog[n].online_articles}{else}—{/if}</b></td>
{/if}


</tr>
{else}
<tr>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td></td>
<td></td>
<td></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>


{/if}
<tr><td colspan="8" class="catalog-spacer-2"></tr>
{/section}	
</table>







<br><br><br><br>













<a name="books"></a>

<div class="catalog-books-title-wrap">
<H1>Библиотека ZX Spectrum: <a href="#magazines">электронные газеты и журналы</a> / <span class="catalog-books-muted">«книги»</span></H1>
</div>

<br><br>

<table cellpadding=0 cellspacing=0>
<tr>
<td class="catalog8" class="catalog-th-verdana">НАЗВАНИЕ</td>
<td class="catalog8" class="catalog-th-verdana">ИЗДАТЕЛЬСТВО</td>
<td class="catalog8" class="catalog-th-verdana">ГОД</td>
<td class="catalog8" class="catalog-th-verdana">СКАН</td>
</tr>

{section name=n loop=$books}
<tr>
<td class="catalog"><a href="{$host}book.php?id={$books[n].id}" class="catalog-book-title">{$books[n].title1_plain}
{if $books[n].title2}<br>{$books[n].title2_plain}{/if}</a>
</td>
<td class="catalog" class="catalog-cell-times">{if $books[n].publisher}{$books[n].publisher_plain}{else}&nbsp;{/if}</td>
<td class="catalog">{if $books[n].date eq 1970}&nbsp;{else}{$books[n].date}{/if}</td>
<td class="catalog" align="center">+</td>
</tr>

<tr><td colspan="4" class="catalog-spacer-6"></tr>

{/section}
</table>




{/if}













<BR>
</TD></TR></TBODY></TABLE>
{include file="right.tpl"}
{include file="footer.tpl"}