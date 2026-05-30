{include file="top.tpl"}

<table cellpadding=0 cellspacing=0 class="pad-4 issue-header-table" border=0>
<tr class="issue-header-row">
{if $screens[0].id}
<td width=170 valign="top" class="issue-cover-cell">

<div class="issue-covers-wrap">
{section name=n loop=$screens}
<img width="128" height="96" class="issue-cover" src="{$host}screens/1/{$screens[n].id}.{$screens[n].format}" title="{$press.title_plain}" alt="{$press.title_plain}">
{/section}
</div>
<div class="issue-sape">{$sape1 nofilter}</div>
</td>
{/if}
<td align="left" valign="top" class="issue-content-cell">

<span class="issue-type-label">
{if $lng eq 'eng'}
{if $press.type eq 0}Newspaper{else}Magazine{/if}
{else}
{if $press.type eq 0}Газета{else}Журнал{/if}
{/if}
</span><H1 class="h1"> {$press.title_plain}</h1>

<DIV class="gln"></DIV>

<br>
<div class="issue-meta">
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

<img src="{$host}img/diske.png" width=21 class="issue-download-icon" valign=top> 
{if $lng eq 'eng'}
<a href="{$host}d.php?id={$press.id}">Download the newspaper archive to run in the emulator</a>
{else}
<a href="{$host}d.php?id={$press.id}">Скачать архив {if $press.type eq 0}газеты{else}журнала{/if} для запуска в эмуляторе</a>
{/if}

<!-- <table width=100% cellpadding=0 cellspacing=0><tr><td valign=top><img src="img/diske.png" width=21 class="issue-download-icon"></td>
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

<div class="book-meta-row">

{if $articles[n].print_title}
<a name="{$articles[n].issue}"></a>
<br><hr><br>
<div class="issue-section-header"><b>
<a href="issue.php?id=189#{$articles[n].issue}">{$press.title_plain} #{$articles[n].issue}</a>
</b>
<br>
{if $articles[n].date neq "01 января 1970"}
<div class="issue-article-date">{$articles[n].date}</div>
{/if}
</div><br>
{/if}

<div class="issue-article-link">
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