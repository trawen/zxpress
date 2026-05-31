{include file="top.tpl"}
<TABLE cellSpacing=0 cellPadding=0 align="center">
<TBODY>
<TR>
<TD>

{if $view_pages[0]}
<div align="center">
<strong>страницы:</strong> 
{section name=n loop=$view_pages}
{cycle values=""}
{if $view_pages[n] eq $view_tk_page}
	{$view_pages[n]}
{else}
	<a href="gallery_admin.php?page={$view_pages[n]}">{$view_pages[n]}</a>
{/if} 
{/section}
</div>
{/if}






<br>
<br>
<form method='POST'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<table>
{section name=n loop=$screens}
{cycle values=""}


{if $screens[n].nm eq 0}
<tr>
<td align="center" style="font-size: 12px">
<img class="flag" src="screens/1/{$screens[n].id}.png" width="256" height="192"><br>

<label for="gallery-admin-press-{$screens[n].id}" class="u-sr-only">Издание для скриншота</label>
<select id="gallery-admin-press-{$screens[n].id}" name="press_{$screens[n].id}">
{if $screens[n].id_press}<option name="press_{$screens[n].id}" value="{$screens[n].id_press}" checked>{$screens[n].press_title}</option>{/if}
{$screens_select} {$screens[n].issue}

<br>
<b>
<input type='radio' name='type_{$screens[n].id}' value='0' {if $screens[n].type eq 0}checked{/if}>Интро 
<input type='radio' name='type_{$screens[n].id}' value='1' {if $screens[n].type eq 1}checked{/if}>Меню 
<input type='radio' name='type_{$screens[n].id}' value='2' {if $screens[n].type eq 2}checked{/if}>Текст
</b>
<br><br>
</td>
{else}
<td align="center" style="font-size: 11px">
<img class="flag" src="screens/1/{$screens[n].id}.png" width="256" height="192"><br>

<label for="gallery-admin-press-{$screens[n].id}" class="u-sr-only">Издание для скриншота</label>
<select id="gallery-admin-press-{$screens[n].id}" name="press_{$screens[n].id}">
{if $screens[n].id_press}<option name="press_{$screens[n].id}" value="{$screens[n].id_press}" checked>{$screens[n].press_title}</option>{/if}
{$screens_select} {$screens[n].issue}

<br>
<b>
<input type='radio' name='type_{$screens[n].id}' value='0' {if $screens[n].type eq 0}checked{/if}>Интро 
<input type='radio' name='type_{$screens[n].id}' value='1' {if $screens[n].type eq 1}checked{/if}>Меню 
<input type='radio' name='type_{$screens[n].id}' value='2' {if $screens[n].type eq 2}checked{/if}>Текст
</b>
<br><br>
</td>
</tr>
{/if}
{/section}	
</table>

<br>
<input type="hidden" name='id_pack' value='{$id_pack}'>
<center><input type='submit' name='save' value='save'></center>
</form>
<br>




{if $view_pages[0]}
<div align="center">
<strong>страницы:</strong> 
{section name=n loop=$view_pages}
{cycle values=""}
{if $view_pages[n] eq $view_tk_page}
	{$view_pages[n]}
{else}
	<a href="gallery_admin.php?page={$view_pages[n]}">{$view_pages[n]}</a>
{/if} 
{/section}
</div>
{/if}

<BR>
</TD></TR></TBODY></TABLE>
{include file="right.tpl"}
{include file="footer.tpl"}