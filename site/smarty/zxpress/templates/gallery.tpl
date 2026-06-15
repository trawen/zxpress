{include file="top.tpl"}

<h1 class="title">{if $lng eq 'eng'}Gallery of electronic newspapers and magazines for ZX Spectrum{else}Галерея электронных газет и журналов для ZX Spectrum{/if}</h1>



<form method='get' class="gallery" action='{$host}gallery.php'>{if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}<label for="gallery-press-top"><b>{if $lng eq 'eng'}Show{else}Отобразить{/if}</b></label>
<select id="gallery-press-top" name='id' onChange="javascript:this.parentNode.submit();">
{if $id}

<option value="0">{if $lng eq 'eng'}All publications{else}Все издания{/if}</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}' {if $id eq $press_list[n].id} selected{/if} >{$press_list[n].title}</option>
{/section}

{else}

<option value="0" selected>{if $lng eq 'eng'}All publications{else}Все издания{/if}</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}'>{$press_list[n].title}</option>
{/section}

{/if}

</select>

&nbsp;{if $lng eq 'eng'}per page{else}по{/if}

<label for="gallery-count-top" class="u-sr-only">{if $lng eq 'eng'}Items per page{else}Количество на странице{/if}</label>
<select class="gallery" id="gallery-count-top" name='num' onChange="javascript:this.parentNode.submit();">

<option value="50"  {if $num eq 50}selected{/if} >50</option>
<option value="150" {if $num eq 150}selected{/if} >150</option>
<option value="500" {if $num eq 500}selected{/if} >500</option>

</select>
</form>
<br>

<hr>


{if $id eq ""}
<div class="pag-num">
{section name=n loop=$pages}
{if $pages[n] eq $tk_page}
	«{$pages[n]}»
{else}
	&nbsp; <a href="{$host}gallery.php?page={$pages[n]}{if $id}&amp;id={$id}{/if}{if $num}&amp;num={$num}{/if}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$pages[n]}</a> 
{/if}
{/section}
</div>
<hr>
{/if}






<br>





<center>
{section name=n loop=$screens}

<div class="gallery-block">
	<img width="256" height="192" src="{$host}screens/1/{$screens[n].gallery_screen_id}.png" alt="{$screens[n].gallery_label_plain} — {if $screens[n].gallery_screen_type eq 0}{if $lng eq 'eng'}Newspaper{else}Газета{/if}{else}{if $lng eq 'eng'}Magazine{else}Журнал{/if}{/if} {if $lng eq 'eng'}for ZX Spectrum{else}для ZX Spectrum{/if}" title="{$screens[n].gallery_label_plain} — {if $screens[n].gallery_screen_type eq 0}{if $lng eq 'eng'}Newspaper{else}Газета{/if}{else}{if $lng eq 'eng'}Magazine{else}Журнал{/if}{/if} {if $lng eq 'eng'}for ZX Spectrum{else}для ZX Spectrum{/if}" class="gallery-img">
	<div class="gallery-title"><a href="{$host}issue.php?id={$screens[n].gallery_press_id}{if $lng eq 'eng'}{$dl}{/if}#{$screens[n].gallery_issue_title}">{$screens[n].gallery_label_plain}</a></div>
</div>

{/section}
</center>

<div class="u-clearfix"></div>






{if $id eq ""}
<hr>
<div class="pag-num">
{section name=n loop=$pages}
{if $pages[n] eq $tk_page}
	&nbsp;«{$pages[n]}»
{else}
	&nbsp; <a href="{$host}gallery.php?page={$pages[n]}{if $id}&amp;id={$id}{/if}{if $num}&amp;num={$num}{/if}{if $lng eq 'eng'}&amp;lng=eng{/if}">{$pages[n]}</a> 
{/if}
{/section}
</div>
{/if}

<hr>

<form method='get' action='{$host}gallery.php' class="gallery">{if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}<label for="gallery-press-bottom">{if $lng eq 'eng'}Show{else}Отобразить{/if}</label>
<select class="gallery" id="gallery-press-bottom" name='id' onChange="javascript:this.parentNode.submit();">
{if $id}

<option value="0">{if $lng eq 'eng'}All publications{else}Все издания{/if}</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}' {if $id eq $press_list[n].id} selected{/if} >{$press_list[n].title}</option>
{/section}

{else}

<option value="0" selected>{if $lng eq 'eng'}All publications{else}Все издания{/if}</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}'>{$press_list[n].title}</option>
{/section}

{/if}

</select>

&nbsp; {if $lng eq 'eng'}per page{else}по{/if}

<label for="gallery-count-bottom" class="u-sr-only">{if $lng eq 'eng'}Items per page{else}Количество на странице{/if}</label>
<select class="gallery" id="gallery-count-bottom" name='num' onChange="javascript:this.parentNode.submit();">

<option value="50"  {if $num eq 50}selected{/if} >50</option>
<option value="150" {if $num eq 150}selected{/if} >150</option>
<option value="500" {if $num eq 500}selected{/if} >500</option>

</select>
</form>
<br>


{include file="right.tpl"}
{include file="footer.tpl"}