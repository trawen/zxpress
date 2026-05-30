{include file="top.tpl"}

<h1 class="title">Галерея электронных газет и журналов для ZX Spectrum</h1>



<form method='get' class="gallery" action='{$host}gallery.php'><b>Отобразить</b>
<select name='id' onChange="javascript:this.parentNode.submit();">
{if $id}

<option value="0">Все издания</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}' {if $id eq $press_list[n].id} selected{/if} >{$press_list[n].title}</option>
{/section}

{else}

<option value="0" selected>Все издания</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}'>{$press_list[n].title}</option>
{/section}

{/if}

</select>

&nbsp;по

<select class="gallery" name='num' onChange="javascript:this.parentNode.submit();">

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
	&nbsp; <a href="{$host}gallery.php?page={$pages[n]}{if $id}&id={$id}{/if}{if $num}&num={$num}{/if}">{$pages[n]}</a> 
{/if}
{/section}
</div>
<hr>
{/if}






<br>





<center>
{section name=n loop=$screens}

<div class="gallery-block">
	<img width="256" height="192" src="{$host}screens/1/{$screens[n].gallery_screen_id}.png" alt="{$screens[n].gallery_label_plain} — {if $screens[n].gallery_screen_type eq 0}Газета{else}Журнал{/if} для ZX Spectrum" title="{$screens[n].gallery_label_plain} — {if $screens[n].gallery_screen_type eq 0}Газета{else}Журнал{/if} для ZX Spectrum" class="gallery-img">
	<div class="gallery-title"><a href="issue.php?id={$screens[n].gallery_press_id}#{$screens[n].gallery_issue_title}">{$screens[n].gallery_label_plain}</a></div>
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
	&nbsp; <a href="gallery.php?page={$pages[n]}{if $id}&id={$id}{/if}{if $num}&num={$num}{/if}">{$pages[n]}</a> 
{/if}
{/section}
</div>
{/if}

<hr>

<form method='get' action='gallery.php' class="gallery">Отобразить
<select class="gallery" name='id' onChange="javascript:this.parentNode.submit();">
{if $id}

<option value="0">Все издания</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}' {if $id eq $press_list[n].id} selected{/if} >{$press_list[n].title}</option>
{/section}

{else}

<option value="0" selected>Все издания</option>
{section name=n loop=$press_list}
<option value='{$press_list[n].id}'>{$press_list[n].title}</option>
{/section}

{/if}

</select>

&nbsp; по

<select class="gallery" name='num' onChange="javascript:this.parentNode.submit();">

<option value="50"  {if $num eq 50}selected{/if} >50</option>
<option value="150" {if $num eq 150}selected{/if} >150</option>
<option value="500" {if $num eq 500}selected{/if} >500</option>

</select>
</form>
<br>


{include file="right.tpl"}
{include file="footer.tpl"}