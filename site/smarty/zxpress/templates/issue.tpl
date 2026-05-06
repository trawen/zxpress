{include file="top.tpl"}

<table cellpadding=0 cellspacing=0 style="padding: 4px" border=0>
<tr>
{if $screens[0].id}
<td width=170 valign="top">

{section name=n loop=$screens}
<img width="128" height="96" style="border: 8px solid #242321; -webkit-border-radius: 2px;-moz-border-radius: 2px;border-radius: 2px;" src="{$host}screens/1/{$screens[n].id}.{$screens[n].format}" title="{$press.title_plain}" alt="{$press.title_plain}"><br><br>
{/section}
<div style="padding: 4px; Font: normal 10px Verdana">{$sape1 nofilter}</div>
</td>
{/if}
<td align="left" valign="top">

<span style="display: inline; font-size: 15pt; ">
{if $lng eq 'eng'}
{if $press.type eq 0}Newspaper{else}Magazine{/if}
{else}
{if $press.type eq 0}Газета{else}Журнал{/if}
{/if}
</span><H1 class="h1"> {$press.title_plain}</h1>

<DIV class="gln"></DIV>

<br>
<div style="font-size: 13pt; line-height: 150%">
<b>{if $press.name}{$press.name} ({$press.country_name}){/if}</b>
{if $press.years_from}<br>{$press.years_from}{/if}
{if $press.years_to} – {$press.years_to}{/if}
<br>
<b>{$press.numbers}</b> 
{if $lng eq 'eng'}
issues
{else}
{$num}
{/if}
<br><br>

<img src="{$host}img/diske.png" width=21 style="padding-right: 4px" valign=top> 
{if $lng eq 'eng'}
<a href="{$host}d.php?id={$press.id}">Download the newspaper archive to run in the emulator</a>
{else}
<a href="{$host}d.php?id={$press.id}">Скачать архив {if $press.type eq 0}газеты{else}журнала{/if} для запуска в эмуляторе</a>
{/if}

<!-- <table width=100% cellpadding=0 cellspacing=0><tr><td valign=top><img src="img/diske.png" width=21 style="padding-right: 4px"></td>
<td> -->
<!-- {foreach from=$issues key=myId item=i name=issue}
<a href="files/{$i.name}">{$i.title}</a>
{if $smarty.foreach.issue.last eq false}, {/if}
{/foreach} -->
<!-- {section name=n loop=$issues}
,
{/section} -->
<!-- </td>
</tr>
</table> -->
</div>



{section name=n loop=$articles}
{if $articles[n].title}

<div style="padding-top: 6px">

{if $articles[n].print_title}
<a name="{$articles[n].issue}"></a>
<br><hr><br>
<div style="font: 12pt Georgia; color: #800; text-align: center"><b>
<a href="issue.php?id=189#{$articles[n].issue}">{$press.title_plain} #{$articles[n].issue}</a>
</b>
<br>
{if $articles[n].date neq "01 января 1970"}
<div style="font: 10pt; color: #312C12">{$articles[n].date}</div>
{/if}
</div><br>
{/if}

<div style="font: 13pt/14pt Times; text-align: left">
{if $lng eq 'eng'}
<a href="{$host}article.php?id={$articles[n].id}{$dl}">{$articles[n].title_eng_list nofilter}</a>
{else}
<a href="{$host}article.php?id={$articles[n].id}{$dl}">{$articles[n].title_list nofilter}</a>
{/if}
</div>
</div>
{/if}
{/section}
</td></tr></table>


{include file="right.tpl"}
{include file="footer.tpl"}