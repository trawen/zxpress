{include file="top.tpl"}

<h1 class="title">Хронология выхода электронных газет и журналов на ZX Spectrum</h1>


<div class="chron-chart-wrap"><img src="{$host}zxpress_dinamic.png?v={$chronology_png_ver}" width="{$chronology_png_w}" height="{$chronology_png_h}" alt="" class="chron-chart-img"></div><br>

<div class="right-on-this-day">На графике отражено количество изданий выпущенных за каждый год. Невероятно, но факт, в 1997-ом году <b>каждый день</b> выходило по одной газете или журналу. </div>
<br>

<hr>

<br>
<div class="chron-year-nav">
{foreach from=$years item=y name=year}
<a href="#{$y}">{$y}</a>{if !$smarty.foreach.year.last}{if $smarty.foreach.year.iteration mod 10 eq 0}<br>{else} · {/if}{/if}
{/foreach}
</div>

<br><br>


<table border=0 class="comments-wrap">

<tr><td colspan="2" class="chron-section-title">Детальная статистика</td></tr>

{section name=n loop=$chronology}

{if $chronology[n].y}
<tr>
<td align="left" colspan=2><hr>
<a name="{$chronology[n].year_display}"></a>
<div class="chron-year-heading">{$chronology[n].year_display} год</div><br>
</td>
</tr>
{/if}

{if $chronology[n].m AND $chronology[n].y eq 0}<tr><td colspan="2" align="center"></td></tr>{/if}

<tr>
<td align="left" width="110"><div class="chron-date">{$chronology[n].date}</div></td>

<td align="left" class="type-times-13-bold" nowrap>
<a href="{$host}issue.php?id={$chronology[n].press_id_cal}#{$chronology[n].number_cal}">
{$chronology[n].title_cal_plain} №{$chronology[n].number_cal}</a>
</td>
</tr>

{/section}

</table>


{include file="right.tpl"}
{include file="footer.tpl"}