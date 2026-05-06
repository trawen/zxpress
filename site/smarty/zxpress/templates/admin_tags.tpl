{include file="admin_top.tpl"}
<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD style="font: 14px Arial;">


<b>


<form method='GET'>
перейти 
<select name='id' onChange="javascript:this.parentNode.submit();">
<option value="0" selected>---</option>
{section name=x loop=$list}
<option value="{$list[x].id}">{$list[x].tag_name}  ({$list[x].nm})</option>
{/section}
</select>
</form>
<br><br>

<form method='POST'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">


название тэга <input type="text" name="name" value="{$admin_tag.tag_name}" style="font: bold 14px Arial"> + 
<select name="add_tag">
<option value="0" selected>---</option>
{section name=x loop=$list}
<option value="{$list[x].id}">{$list[x].tag_name} ({$list[x].nm})</option>
{/section}
</select>

	{if $admin_tag.relations}

	{section name=t loop=$admin_tag.relations}
  < {$admin_tag.relations[t].tag_name}  <input name="delete_tag_{$admin_tag.relations[t].id}" type='checkbox' value='1' style="border: 1px solid red">
	{/section}
	{/if}

<br><br>
синоним тэга <input type="text" name="alias" value="{$admin_tag.tag_alias}" style="font: bold 14px Arial">
  &nbsp; 
удалить тэг <input name="delete_tag" type='checkbox' value='1' style="border: 1px solid red">
  &nbsp; 
нормализовать <input name="normalisation" type='checkbox' value='1' style="border: 1px solid red">

<br><br><br>
	
склеить 
<select name="glue_tag_1">
<option value="0" selected>---</option>
{section name=x loop=$list}
<option value="{$list[x].id}">{$list[x].id} - {$list[x].tag_name} ({$list[x].nm})</option>
{/section}
</select>

с

<select name="glue_tag_2">
<option value="0" selected>---</option>
{section name=x loop=$list}
<option value="{$list[x].id}">{$list[x].id} - {$list[x].tag_name} ({$list[x].nm})</option>
{/section}
</select>

<br><br>

<input type='submit' name='save' value='save'>
</form>
<br>

<BR>
</TD></TR></TABLE>

{include file="footer.tpl"}