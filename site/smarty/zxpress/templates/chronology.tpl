{include file="top.tpl"}

<h1 class="title">Хронология выхода электронных газет и журналов на ZX Spectrum</h1>


<div style="width: 630px; overflow: auto"><img src="{$host}zxpress_dinamic.png?v={$chronology_png_ver}" width="{$chronology_png_w}" height="{$chronology_png_h}" alt="" style="max-width:100%;height:auto;padding-bottom:8px;border:1px solid #D6D0AB;background:#faf8f5"></div><br>

<div style="font: normal 13pt Times;">На графике отражено количество изданий выпущенных за каждый год. Невероятно, но факт, в 1997-ом году <b>каждый день</b> выходило по одной газете или журналу. </div>
<br>

<hr>

<br>
<div style="font: bold 13pt Times; text-align: center">
{foreach from=$years item=y name=year}
<a href="#{$y}">{$y}</a>{if !$smarty.foreach.year.last}{if $smarty.foreach.year.iteration mod 10 eq 0}<br>{else} · {/if}{/if}
{/foreach}
</div>

<br><br>


<table border=0 style="padding-left: 32px">

<tr><td colspan="2" style="font: bold 15pt Times">Детальная статистика</td></tr>

{section name=n loop=$chronology}

{if $chronology[n].y}
<tr>
<td align="left" colspan=2><hr>
<a name="{$chronology[n].year_display}"></a>
<div style="font: normal 18px Times; padding-right: 20px">{$chronology[n].year_display} год</div><br>
</td>
</tr>
{/if}

{if $chronology[n].m AND $chronology[n].y eq 0}<tr><td colspan="2" align="center"></td></tr>{/if}

<tr>
<td align="left" width="110"><div style="font: normal 13pt Times; padding-right: 20px">{$chronology[n].date}</div></td>

<td align="left" style="font: bold 13pt Times;" nowrap>
<a href="{$host}issue.php?id={$chronology[n].press_id_cal}#{$chronology[n].number_cal}">
{$chronology[n].title_cal_plain} №{$chronology[n].number_cal}</a>
</td>
</tr>

{/section}

</table>


{include file="right.tpl"}
{include file="footer.tpl"}