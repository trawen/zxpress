{include file="top.tpl"}

<h1 class="title">Электронные газеты и журналы для ZX Spectrum</h1>



<center>
{section name=n loop=$catalog}
{if $catalog[n].letter}
<a href="#letter_{$catalog[n].letter}">{$catalog[n].letter}</a> &nbsp; 
{/if}
{/section}
</center>

<br><br>

<div class="ezines-mobile">
{section name=m loop=$catalog}
{if $catalog[m].off}
{if $catalog[m].letter}
<div class="ezines-mobile-letter"><a name="letter_{$catalog[m].letter}">{$catalog[m].letter}</a></div>
{/if}
<div class="ezines-mobile-item{if $catalog[m].letter} ezines-mobile-item-letter{/if}">
<a href="{$host}issue.php?id={$catalog[m].id}" {if $catalog[m].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[m].title_plain}</b></a><span class="number">{$catalog[m].numbers}</span>{if $catalog[m].name}, {$catalog[m].name}{/if}{if $catalog[m].years_from neq 1970 or $catalog[m].years_to neq 1970}, <span class="ezines-mobile-years">{if $catalog[m].years_from neq 1970 AND $catalog[m].years_to neq 1970 AND $catalog[m].years_from neq $catalog[m].years_to}{$catalog[m].years_from}-{$catalog[m].years_to}{elseif $catalog[m].years_from neq 1970}{$catalog[m].years_from}{else}{$catalog[m].years_to}{/if}</span>{/if}{if $a}, {if $catalog[m].finish eq 100}<img src="{$host}img/ok.png" width="18" alt="100%">{elseif $catalog[m].finish eq 0}—{else}{$catalog[m].finish}%{/if}{/if}
</div>
{/if}
{/section}
</div>

<table class="ezines-desktop" cellpadding=0 cellspacing=0 width="100%">
<tr>
<td></td>
<td class="catalog8"><b>Название</b></td>
<td class="catalog8" nowrap><b>Город</b></td>
<td class="catalog8"><b>Форма</b></td>
<td class="catalog8" nowrap align="right"><b>Года издания</b></td>
{if $a}<td class="catalog8"><b>Статьи</b></td>{/if}
</tr>




{section name=n loop=$catalog}


{if $catalog[n].off}



<tr><td colspan="8" class="catalog-spacer-4"></tr>
<tr>

{if $catalog[n].letter}
<td></td><td colspan=7><hr></td></tr><tr>
<td class="catalog2" valign="bottom">
<a name="letter_{$catalog[n].letter}">
<div class="catalog-letter">{$catalog[n].letter}</div></a></td>
<td class="catalog2" valign="bottom"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[n].title_plain}</b></a>
<span class="number">{$catalog[n].numbers}</span>
</td>

<td class="catalog2" valign="bottom" nowrap>{if $catalog[n].country_id}
<img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {/if}{$catalog[n].name}</td>

<td class="catalog2" valign="bottom">
{if $catalog[n].type eq 1}журнал{elseif $catalog[n].type eq 0}газета{elseif $catalog[n].type eq 2}репортаж{/if}</td>


<td class="catalog2" valign="bottom" align="right">
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>


{if $a}
<td class="catalog2" valign="bottom" align="center"><b>
{if $catalog[n].finish eq 100}
<img src="{$host}img/ok.png" width=18>
{elseif $catalog[n].finish eq 0}
—
{else}
{$catalog[n].finish}%
{/if}</b></td>
{/if}




{else}



<td></td>
<td class="catalog"><a href="{$host}issue.php?id={$catalog[n].id}" {if $catalog[n].online_articles eq 0} class="catalog-link-offline"{/if}><b>{$catalog[n].title_plain}</b></a>
<span class="number">{$catalog[n].numbers}</span>
</td>


<td class="catalog" nowrap>{if $catalog[n].country_id}
<img width='16' height='10' class='flag' src="{$host}img/{$catalog[n].country_id}.png"> {/if}{$catalog[n].name}
</td>

<td class="catalog">
{if $catalog[n].type eq 1}журнал{elseif $catalog[n].type eq 0}газета{elseif $catalog[n].type eq 2}репортаж{/if}
</td>


<td class="catalog" align="right">
{if $catalog[n].years_from neq 1970 AND $catalog[n].years_to neq 1970 AND $catalog[n].years_from neq $catalog[n].years_to}{$catalog[n].years_from}-{$catalog[n].years_to}{elseif $catalog[n].years_from neq 1970}{$catalog[n].years_from}{elseif $catalog[n].years_to neq 1970}{$catalog[n].years_to}{else}&nbsp;{/if}
</td>


{if $a}
<td class="catalog" align="center"><b>
{if $catalog[n].finish eq 100}
<img src="{$host}img/ok.png" width=18>
{elseif $catalog[n].finish eq 0}
—
{else}
{$catalog[n].finish}%
{/if}
</b></td>
{/if}
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
{if $a}<td>&nbsp;</td>{/if}
</tr>


{/if}
<tr><td colspan="8" class="catalog-spacer-3"></td></tr>
{/section}	
</table>

<br>

<center>
{section name=n loop=$catalog}
{if $catalog[n].letter}
<a href="#letter_{$catalog[n].letter}">{$catalog[n].letter}</a> &nbsp; 
{/if}
{/section}
</center>

<br><br>


{include file="right.tpl"}
{include file="footer.tpl"}