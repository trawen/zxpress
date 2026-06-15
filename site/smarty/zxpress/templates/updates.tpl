{include file="top.tpl"}
<h1 class="title">{if $lng eq 'eng'}Latest additions to the zxpress library{else}Список последних поступлений в библиотеку zxpress{/if}</h1>


<p>{if $lng eq 'eng'}Site updates are irregular. Priority is given to non-entertainment publications. At the moment, more than 90% of articles from such publications have been processed and uploaded to the site.{else}Обновления сайта носят не регулярный характер. Приоритетными явлются издания не развлекательной направленности. На данный момент обработаны и залиты на сайт более 90% статей таких изданий.{/if}</p>

<hr>

<div class="pag-num">
{section name=n loop=$pages}
{if $pages[n] eq $tk_page}
	«{$pages[n]}»
{else}
	&nbsp; <a href="{$host}updates.php?page={$pages[n]}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$pages[n]}</a> 
{/if}
{/section}
</div>

<hr>



{section name=n loop=$updates}
{if $updates[n].print}
<div class="updates-rule-row">
<div class="updates-rule-spacer"></div>
<div class="updates-rule-line"><hr></div>
</div>
{/if}

<div class="updates-block">
<div class="updates-date">
{if $updates[n].date}{$updates[n].date}{else}&nbsp;{/if}
</div>

<div>
{if $updates[n].print}
<div class="updates-press-title"><a href="{$host}issue.php?id={$updates[n].press_id}{if $lng eq 'eng'}{$dl}{/if}#{$updates[n].issue_title}">{$updates[n].press_title} #{$updates[n].issue_title}</a>
<br>
</div>
<br>
{/if}
<div class="updates-article-title">
<a href="{$host}article.php?id={$updates[n].id}{if $lng eq 'eng'}{$dl}{/if}">{$updates[n].title_list nofilter}</a>
</div>
</div>
</div>



{/section}



<hr>

<div class="pag-num">
{section name=n loop=$pages}
{if $pages[n] eq $tk_page}
	«{$pages[n]}»
{else}
	&nbsp; <a href="{$host}updates.php?page={$pages[n]}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$pages[n]}</a> 
{/if}
{/section}
</div>

<hr>

{include file="right.tpl"}
{include file="footer.tpl"}