{include file="top.tpl"}
<h1 class="title">Список последних поступлений в библиотеку zxpress</h1>


<p>Обновления сайта носят не регулярный характер. Приоритетными явлются издания не развлекательной направленности. На данный момент обработаны и залиты на сайт более 90% статей таких изданий.</p>

<hr>

<div class="pag-num">
{section name=n loop=$pages}
{if $pages[n] eq $tk_page}
	«{$pages[n]}»
{else}
	&nbsp; <a href="{$host}updates.php?page={$pages[n]}">{$pages[n]}</a> 
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
<div class="updates-press-title"><a href="{$host}issue.php?id={$updates[n].press_id}#{$updates[n].issue_title}">{$updates[n].press_title} #{$updates[n].issue_title}</a>
<br>
</div>
<br>
{/if}
<div class="updates-article-title">
<a href="{$host}article.php?id={$updates[n].id}">{$updates[n].title_list nofilter}</a>
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
	&nbsp; <a href="{$host}updates.php?page={$pages[n]}">{$pages[n]}</a> 
{/if}
{/section}
</div>

<hr>

{include file="right.tpl"}
{include file="footer.tpl"}