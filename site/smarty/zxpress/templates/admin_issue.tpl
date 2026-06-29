{include file="admin_top.tpl"}
{if $login eq 1 and $username}

{literal}
<SCRIPT language=javascript src="img/jquery.js" type=text/javascript></SCRIPT>
<script type="text/javascript">
        function change(vl) {
                     $("#"+vl).attr("value", "1");
					
        };
</script>
{/literal}



<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>




<br>

<form method='POST' enctype='multipart/form-data' action='admin_issue.php?id={if $press.id}{$press.id}{else}0{/if}'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<div style="font: bold 13px Verdana">

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7;">

Информация:<br><br> 

<table style="font: bold 12px Verdana" width="100%">
<tr>
<td>
Название <input type="text" name="title" value="{$press.title}" onchange="change('press_change');">

 &nbsp; <label for="admin-issue-type">Формат</label>
<select id="admin-issue-type" name='type' onchange="change('press_change');">
{if $press.type eq 0}<option value='0' selected>Газета</option><option value='1'>Журнал</option>
<option value='2'>Отчёт</option>
{elseif $press.type eq 1}<option value='0'>Газета</option><option value='1' selected>Журнал</option>
<option value='2'>Отчёт</option>
{elseif $press.type eq 2}<option value='0'>Газета</option><option value='1'>Журнал</option>
<option value='2' selected>Отчёт</option>
{/if}
</select>

 &nbsp; <label for="admin-issue-city">Город</label>
<select id="admin-issue-city" name="city" onchange="change('press_change');">
<option value="{$cities[n].id}" {if $cities[n].id eq $press.city}selected{/if}>Неизвестен</otpion>
{section name=n loop=$cities}
<option value="{$cities[n].id}" {if $cities[n].id eq $press.city}selected{/if}>{$cities[n].name}</otpion>
{/section}
</select>

 &nbsp; Количество выпусков <input type="text" name="numbers" value="{$press.numbers}" onchange="change('press_change');">

<br><br>
slug (ru) <input type="text" name="slug_ru" value="{$press.slug_ru}" size="24" onchange="change('press_change');">
slug (en) <input type="text" name="slug_en" value="{$press.slug_en}" size="24" onchange="change('press_change');">

</td>
<td align="right">
<a href="admin_issue.php?id=0" style="color: blue">Добавить новое издание +</a>
 
 <input type="hidden" value="" name="press_change" id="press_change">
</td>
</table>
 </div>






<br>





<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; ">

Выпуски:<br><br>

{section name=n loop=$issues}

<div style="display: inline; float: left; padding: 4px; font: bold 11px; border: 1px dashed #BBB; margin: 4px">

<div style="padding: 4px;">
название <input type="text" size="3" value="{$issues[n].title}" name="issue_title_{$issues[n].id}">
</div>

<div style="padding: 4px;">
slug ru <input type="text" size="12" value="{$issues[n].slug_ru}" name="issue_slug_ru_{$issues[n].id}">
slug en <input type="text" size="12" value="{$issues[n].slug_en}" name="issue_slug_en_{$issues[n].id}">
</div>

<div style="padding: 4px;">
<span style="font: bold 11px; color: red">удалить</span> 
<input type='checkbox' value='1' name="issue_delete_{$issues[n].id}">
</div>


</div>

{/section}

{if $issues[0].id  EQ ""} 
 —
{/if}

<div style="clear: both"></div><br>

</div>



<br>




<div style="padding: 10px; border: 1px solid #C8C5AC;  background-color: #EBE8D7">
Создать выпуск: <br><br>
название <input type="text" size="8" value="" name="add_issue">
</div>




<br>




<div style="padding: 10px; border: 1px solid #C8C5AC;  background-color: #EBE8D7">

Файлы связанные с выпусками <span style="font-weight: normal"></span>:<br><br>


{section name=n loop=$files}

<div style="display: inline; float: left; padding: 8px; font: bold 11px/14px; border: 1px dashed #BBB; margin: 4px">

<div style="padding: 4px;">
<a href="files/{$files[n].name}">{$files[n].name}</a> [{$files[n].size}Kb]<br>
</div>

<div style="padding: 4px;">
комментарий <input type="text" size="8" value="{$files[n].file_title}" name="file_title_{$files[n][0]}" onchange="change('issue_files_change_{$files[n][0]}');"><br>
</div>

<div style="padding: 4px;">
<label for="issue-file-issue-{$files[n][0]}">выпуск</label>
<select id="issue-file-issue-{$files[n][0]}" name="issue_file_{$files[n][0]}" onchange="change('issue_files_change_{$files[n][0]}');">
{section name=a loop=$issues}
<option value='{$issues[a].id}' {if $files[n].id_issue EQ $issues[a].id}selected{/if} >{$issues[a].title}</option>
{/section}
</select>
</div>


<div style="padding: 4px;">
<label for="issue-file-type-{$files[n][0]}">формат</label>
<select id="issue-file-type-{$files[n][0]}" name="file_type_{$files[n][0]}" style="font: 8pt verdana;" onchange="change('issue_files_change_{$files[n][0]}');">
<option value="0" {if $files[n].type EQ 0}selected{/if} 
>SCL/TRD</option>
<option value="2" {if $files[n].type EQ 2}selected{/if} 
>FDI</option>
<option value="3" {if $files[n].type EQ 3}selected{/if} 
>UDI</option>
<option value="4" {if $files[n].type EQ 4}selected{/if} 
>TD0</option>
<option value="5" {if $files[n].type EQ 5}selected{/if} 
>TAP</option>
<option value="6" {if $files[n].type EQ 6}selected{/if} 
>TZX</option>
<option value="7" {if $files[n].type EQ 7}selected{/if} 
>PDF</option>
<option value="8" {if $files[n].type EQ 8}selected{/if} 
>TXT</option>
<option value="9" {if $files[n].type EQ 9}selected{/if} 
>HTML</option>
<option value="10" {if $files[n].type EQ 10}selected{/if} 
>DEJAVU</option>
</select><br>
</div>

<div style="padding: 4px;">
<span style="font: bold 11px; color: red">удалить</span> <input name="file_delete_{$files[n][0]}" type="checkbox"  onchange="change('issue_files_change_{$files[n][0]}');">
</div>

<div style="padding: 4px;">
<span style="font: 8pt verdana;">{$files[n].date}</span>
</div>

<input type="hidden" value="" name="issue_files_change_{$files[n][0]}" id="issue_files_change_{$files[n][0]}">


</div>

{/section}

{if $files[0].id EQ ""} 
 —
{/if}

<div style="clear: both"></div><br>
</div>




<br>




<div style="padding: 10px; border: 1px solid #C8C5AC;  background-color: #EBE8D7">

Загрузить файл:<br><br>

<input type='file' name='upload_file' style="width: 100px"> 
 
 &nbsp; 
<label for="admin-issue-upload-issue">привязать к выпуску</label> <select id="admin-issue-upload-issue" name="upload_file_issue">
 
{section name=n loop=$issues}
<option value='{$issues[n].id}'>{$issues[n].title}</option>
{/section}
</select>



или создать новый <input type="text" size="3" value="" name="upload_file_new_issue"> 


 &nbsp; &nbsp;
<label for="admin-issue-upload-type">формат</label>

<select id="admin-issue-upload-type" name="upload_file_type" style="font: 8pt verdana;">
<option value="0">SCL/TRD</option>
<option value="2">FDI</option>
<option value="3">UDI</option>
<option value="4">TD0</option>
<option value="5">TAP</option>
<option value="6">TZX</option>
<option value="7">PDF</option>
<option value="8">TXT</option>
<option value="9">HTML</option>
<option value="10">DEJV</option>
</select>

 &nbsp; 
комментарий <input type="text" size="15" value="" name="upload_file_title}">

</div>





</div>


<br><br>
<center><input type='submit' name='save' value='save'></center>
</form>


<BR>
</TD></TR></TABLE>
{/if}
{include file="footer.tpl"}