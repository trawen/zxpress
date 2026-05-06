{include file="top.tpl"}
<TABLE cellSpacing=0 cellPadding=0 align="center">
<TBODY>
<TR>
<TD>

<a name="magazines"></a>







{if $lng}











<div style="padding-left: 56px"><H1>Library of electronic newspapers and magazines for ZX Sepectrum</H1></div>

<br><br>

<table cellpadding=0 cellspacing=0>
<tr>
<td></td>
<td class="catalog8" style="font: bold 14px Times">TITLE</td>
<td class="catalog8" style="font: bold 14px Times">TYPE</td>
<td class="catalog8" style="font: bold 14px Times" nowrap>YEAR</td>
<td class="catalog8" style="font: bold 14px Times" nowrap>CITY</td>
<td class="catalog8" style="font: bold 14px Times">ISSUE</td>
<td class="catalog8" style="font: bold 14px Times">ARTICLES</td>
</tr>




{section name=n loop=$catalog}


{if $catalog[n].off}
<tr>

{if $catalog[n].letter}
<td class="catalog2" valign="bottom">
<div style="COLOR: #493C2F; font: bold 32px Times; position: relative; top: 10px">{$catalog[n].letter}</div></td>
<td class="catalog2" valign="bottom"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0}style="color: #594C3F"{/if}><b>{$catalog[n].title_plain}</b></a></td>
<td class="catalog2" valign="bottom">{if $catalog[n].type eq 1}magazine{elseif $catalog[n].type eq 0}newspaper{/if}</td>


<td class="catalog2" valign="bottom" align="center" nowrap>
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>
<td class="catalog2" valign="bottom" nowrap><img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {$catalog[n].name_eng}</td>
<td class="catalog2" valign="bottom" align="center">{$catalog[n].numbers}</td>
<td class="catalog2" valign="bottom" align="center"><b>{if $catalog[n].online_articles}{$catalog[n].online_articles}{else}—{/if}</b></td>
{else}
<td></td>
<td class="catalog"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0}style="color: #594C3F"{/if}><b>{$catalog[n].title_plain}</b></a></td>
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
<tr><td colspan="8" style="height: 4px"></tr>
{/section}	
</table>












{else}






<div style="padding-left: 56px"><H1>Библиотека электронных газет и журналов для ZX Spectrum</H1></div>

<br><br>

<table cellpadding=0 cellspacing=0>
<tr>
<td></td>
<td class="catalog8" style="font: bold 14px Times">НАЗВАНИЕ</td>
<td class="catalog8" style="font: bold 14px Times">ФОРМА</td>
<td class="catalog8" style="font: bold 14px Times" nowrap>ГОДА ИЗДАНИЯ</td>
<td class="catalog8" style="font: bold 14px Times" nowrap>ГОРОД</td>
<td class="catalog8" style="font: bold 14px Times">ВЫПУСКИ</td>
<td class="catalog8" style="font: bold 14px Times">СТАТЬИ</td>
</tr>




{section name=n loop=$catalog}


{if $catalog[n].off}
<tr>

{if $catalog[n].letter}
<td class="catalog2" valign="bottom">
<div style="COLOR: #493C2F; font: bold 32px Times; position: relative; top: 10px">{$catalog[n].letter}</div></td>
<td class="catalog2" valign="bottom"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0}style="color: #594C3F"{/if}><b>{$catalog[n].title_plain}</b></a></td>
<td class="catalog2" valign="bottom">{if $catalog[n].type eq 1}журнал{elseif $catalog[n].type eq 0}газета{/if}</td>


<td class="catalog2" valign="bottom" align="center">
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>
<td class="catalog2" valign="bottom" nowrap><img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {$catalog[n].name}</td>
<td class="catalog2" valign="bottom" align="center">{$catalog[n].numbers}</td>
<td class="catalog2" valign="bottom" align="center"><b>{if $catalog[n].online_articles}{$catalog[n].online_articles}{else}—{/if}</b></td>
{else}
<td></td>
<td class="catalog"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0}style="color: #594C3F"{/if}><b>{$catalog[n].title_plain}</b></a></td>
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
<tr><td colspan="8" style="height: 2px"></tr>
{/section}	
</table>







<br><br><br><br>













<a name="books"></a>

<div style="padding-left: 8px">
<H1>Библиотека ZX Spectrum: <a href="#magazines">электронные газеты и журналы</a> / <span style="color: #777">«книги»</span></H1>
</div>

<br><br>

<table cellpadding=0 cellspacing=0>
<tr>
<td class="catalog8" style="font: bold 13px Verdana">НАЗВАНИЕ</td>
<td class="catalog8" style="font: bold 13px Verdana">ИЗДАТЕЛЬСТВО</td>
<td class="catalog8" style="font: bold 13px Verdana">ГОД</td>
<td class="catalog8" style="font: bold 13px Verdana">СКАН</td>
</tr>

{section name=n loop=$books}
<tr>
<td class="catalog"><a href="{$host}book.php?id={$books[n].id}" style="color: black; font: bold 14px Times">{$books[n].title1_plain}
{if $books[n].title2}<br>{$books[n].title2_plain}{/if}</a>
</td>
<td class="catalog" style="font: normal 13px Times">{if $books[n].publisher}{$books[n].publisher_plain}{else}&nbsp;{/if}</td>
<td class="catalog">{if $books[n].date eq 1970}&nbsp;{else}{$books[n].date}{/if}</td>
<td class="catalog" align="center">+</td>
</tr>

<tr><td colspan="4" style="height: 6px"></tr>

{/section}
</table>




{/if}













<BR>
</TD></TR></TBODY></TABLE>
{include file="right.tpl"}
{include file="footer.tpl"}