{include file="top.tpl"}

<h1 class="title">Статистика сайта</h1><br>

<b>{$articles}</b> статей, &nbsp;<b>{$screens}</b> скриншотов; суммарный обьем статей <b>180Mb</b>.<br>
 
<br>
 
<b>Самые читаемые статьи:</b><br><br>


{foreach from=$top_articles item=t name=top}
<div><a href="{$host}article.php?id={$t.id}&skip=1" title="Просмотры: {$t.views}">{$t.title_list nofilter}</a></div>
<!-- {if $smarty.foreach.top.index eq 4}
<div onclick="$('#top').show(); $(this).hide();" class="simulink">показать еще</div><div id="top" class="u-hidden">
{/if} -->
<!-- {if $smarty.foreach.top.last eq true}</div>{/if} -->
{/foreach}

 
<hr>

<h1 class="title">Хроностатистика по Спектруму</h1><br>


<div>
Количество электронных журналов <b>{$papers}</b>, газет <b>{$magazines}</b>; архив прессы занимает <b>270Mb</b> (эквивалент 400 дискет).<br>
</div>

<br>

<div>
Самые живучие журналы: 
{foreach from=$mag_issues item=mg name=mg}
<b><a href="{$host}issue.php?id={$mg.id}">{$mg.title}</a></b> ({$mg.numbers}){if $smarty.foreach.mg.last eq false}, {/if}
{/foreach}.</div>

<br>

<div>Самые живучие газеты:
{foreach from=$pap_issues item=pp name=pp}
<b><a href="{$host}issue.php?id={$pp.id}">{$pp.title}</a></b> ({$pp.numbers}){if $smarty.foreach.pp.last eq false}, {/if}
{/foreach}.
</div>

<br>

<div>Самые активные города:
{foreach from=$bycity item=city name=city}
<b>{$city.name}</b> ({$city.kl}){if $smarty.foreach.city.last eq false}, {/if}
{/foreach}.
</div>



{include file="right.tpl"}
{include file="footer.tpl"}