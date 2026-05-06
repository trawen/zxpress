{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<br>

<!-- {if $login eq 1 and $username}<div align="right" style="font: bold 11px Verdana">Вы <span style="color: red">{$username}</span></div>{/if} -->



{literal}
<SCRIPT language=javascript src="img/jquery.js" type=text/javascript></SCRIPT>
<script type="text/javascript">
        function change(vl) {
                     $("#"+vl).attr("value", "1");
        };
</script>
{/literal}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%" style="font: bold 12px Verdana">
<TBODY>
<TR>
<TD>







<!-- {if $username eq "newart"}
<a href="admin_issue.php?id={$press.id}">главное</a> 
{/if} -->










<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; font: bold 14px Verdana">




<table width="100%"><tr><td>
<div style="font: normal 13pt/10pt Verdana">
{if $press.type eq 0}Газета{else}Журнал{/if} <b style="COLOR: #A41E00; font: normal 18pt Times" title="{$id_issue}">{$press.title}</b></div></td>

<td style="font: bold 12px Vedrana">
выпуск №

<form method='GET' action='admin_articles.php' style="display: inline">
<select name="issue" onChange="javascript:this.parentNode.submit();">
{section name=n loop=$issues}
<option value='{$issues[n].id}' {if $issues[n].title eq $issue_title} selected{/if}>{$issues[n].title}</option>
{/section}
</select>
<input type="hidden" value="{$id}" name="id">
</form>


</td>
<td style="font: bold 12px Vedrana">
<form method='POST' enctype='multipart/form-data' action='admin_articles.php?id={$press.id}&issue={$issue.id}'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">


<input type="hidden" value="{$issue_title}" name="issue_title"/>
<input type="hidden" value="{$press.title}" name="press_title"/>



дата выхода

<input size='10' type='text' name='issue_date' value='{$issue.date}' onchange="change('issue_date_change');">
<input id="issue_date_change" type="hidden" value="" name="issue_date_change">
</td>

<td align="right" width="40%">
<input style="font: bold 14px Verdana" type='submit' name='save' value='Сохранить'>
</td>
</tr>
</table>
</div>







<br>









<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; font: bold 14px Verdana">

{if $screens}

Скриншоты:<br><br>

{section name=n loop=$screens}

<div style="display: inline; float: left; padding: 4px; font: bold 11px Verdana; border: 1px dashed #BBB; margin: 4px" align="center">
<img width="256" src="screens/1/{$screens[n].id}.{$screens[n].format}" style="padding-bottom: 4px"><br>
<b>Тип 
<select name='screen_type_{$screens[n].id}' style="width: 60px">
{if $screens[n].type eq 0}
<option value='0' selected>заставка</option><option value='1'>меню</option><option value='2'>текст</option>
{elseif $screens[n].type eq 1}
<option value='0'>заставка</option><option value='1' selected>меню</option><option value='2'>текст</option>
{elseif $screens[n].type eq 2}
<option value='0' >заставка</option><option value='1'>меню</option><option value='2' selected>текст</option>
{/if}
</select>

Выпуск 
<select style="width: 40px" name="screen_issue_{$screens[n].id}">
{section name=i loop=$issues}
{if $issues[i].id eq $issue.id}<option value='{$issues[i].id}' selected>{$issues[i].title}
{else}<option value='{$issues[i].id}'>{$issues[i].title}
{/if}
</option>
{/section}
</select>

 <span style="color: red">Удалить</span> <input name="delete_screen_{$screens[n].id}" type='checkbox' value='1'>
</b>
</div> 

{/section}
<br>

{/if}
<div style="clear: both"></div><br>

<div style="font: bold 12px Verdana">
Загрузить скриншот <input type='file' name='upload_screen'>
</div>

</div>






<br>








<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; font: bold 14px Verdana">

Иллюстрации к статьям: <br><br>

{if $illustrations}

{section name=n loop=$illustrations}

<div style="display: inline; float: left; padding: 4px; font: bold 11px; border: 1px dashed #BBB; margin: 4px" align="center">
<img width="256" src="illustrations/1/{$illustrations[n].id_il}.png" style="padding-bottom: 4px"><br>


 
<input style="font: normal 11px Arial; width: 80px" type="text" onfocus="this.select()" value='<img src="illustrations/1/{$illustrations[n].id_il}.png">' /> 

Выпуск 
<select  style="width: 50px" name="illustration_issue_{$illustrations[n].id_il}">
{section name=i loop=$issues}
{if $issues[i].id eq $issue.id}<option value='{$issues[i].id}' selected>{$issues[i].title}
{else}<option value='{$issues[i].id}'>{$issues[i].title}
{/if}
</option>
{/section}
</select> 

<span style="color: red">Удалить</span> <input name="delete_illustration_{$illustrations[n].id_il}" type='checkbox' value='1'> 

 
</div> 
{/section}

{else}
  —
<br>
{/if}
<div style="clear: both"></div>

<div style="font: bold 12px Verdana">
Загрузить иллюстрацию <input type='file' name='upload_illustration'>
</div>

</div>






<br>







<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; font: bold 14px Verdana">

Статьи: <br><br>

<div style="font: bold 12px Verdana">
Загрузить статью <input type='file' name='upload_text'> &nbsp; &nbsp; 

Переходить к форме добавления статьи <input type='checkbox' name='jump' {if $jump}checked{/if}>  &nbsp; &nbsp;  

Автонумерация статей</b> <input type='checkbox' name='autonumber' {if $au}checked{/if}>

&nbsp;  

HTML</b> <input type='checkbox' name='html' {if $html}checked{/if}>
</div>
<br><br>




<a name="jump">
Добавить новую:<br><br>
</a>







<div style="padding: 4px; border: 1px dashed #F88; margin: 4px">

<table valign=top><tr><td style="font: bold 12px Vedrana">

 &nbsp;Заголовок статьи или краткое содержание<br>

<textarea rows='3' cols='90' name='new_article_title' onchange="change('article_create');"></textarea><br><br>
<input id="article_create" type="hidden" value="" name="article_create"/>
</td>








<td style="font: bold 11px Verdana; padding-left: 8px;" ROWSPAN=2>
<center>
<br>
<br><br><br>


<b>Добавить тэг</b><br>
<select  style="display: inline; floating: left;" name="new_article_add_tag_{$articles[n].id}">
<option value='0' selected>---</option>
{section name=i loop=$tags}
<option value='{$tags[i].id}'>{$tags[i].tag_name}</option>
{/section}
</select>
<br><br>




<b>Создать и добавить тэг</b><br>
<input type="text" name="article_new_tag_{$articles[n].id}" size="23">
<br><br><br><br><br><br><br>


<center><input style="font: bold 14px Verdana" type='submit' name='save' value='Сохранить'></center>

</center>




</td>
</tr>




<tr><td style="font: bold 12px Vedrana">
 &nbsp;Текст статьи<br>

<textarea rows='15' cols='90' name='new_article_text' onchange="change('article_create');"></textarea>
</td>
</tr></table>

</div>

<br><br>













<br>































{if $articles}
{section name=n loop=$articles}

<div style="padding: 4px; border: 1px dashed #BBB; margin: 4px">

<table valign=top><tr><td style="font: bold 12px Vedrana">

 &nbsp;Заголовок статьи или краткое содержание<br>

<textarea rows='2' cols='90' name='article_title_{$articles[n].id}' onchange="change('title_change_{$articles[n].id}');">{$articles[n].title nofilter}</textarea>
<input id="title_change_{$articles[n].id}" type="hidden" value="" name="title_change_{$articles[n].id}"/>
</td>








<td style="font: bold 11px Verdana; padding-left: 8px;" ROWSPAN=2>
<a href="article.php?id={$articles[n].id}" target="_blank" style="color: blue">Предпросмотр статьи »</a> <br><br><br>

{if $articles[n].by}
Добавил: 
<span style="color: #A41E00;">
{section name=x loop=$articles[n].by}
<b>{$articles[n].by[x].username}</b> &nbsp; 
{/section}
</span>
<br><br>
{/if}



<b>Порядковый номер</b>
<input type="text" name="article_number_{$articles[n].id}" size="2" value="{$articles[n].number}" onchange="change('number_change_{$articles[n].id}');">
<input id="number_change_{$articles[n].id}" type="hidden" value="" name="number_change_{$articles[n].id}"/>
<br><br>


<b>Привязать к выпуску</b>
<select name="article_issue_{$articles[n].id}" onchange="change('issue_change_{$articles[n].id}');">
{section name=i loop=$issues}
{if $issues[i].id eq $issue.id}<option value='{$issues[i].id}' selected>{$issues[i].title}
{else}<option value='{$issues[i].id}'>{$issues[i].title}
{/if}
</option>
{/section}
</select>
<input id="issue_change_{$articles[n].id}" type="hidden" value="" name="issue_change_{$articles[n].id}"/>
<br><br>


<b>Добавить тэг</b><br>
<select  style="display: inline; floating: left;" name="article_add_tag_{$articles[n].id}">
<option value='0' selected>---</option>
{section name=i loop=$tags}
<option value='{$tags[i].id}'>{$tags[i].tag_name}</option>
{/section}
</select>
<br><br>




<b>Создать и добавить тэг</b><br>
<input type="text" name="article_new_tag_{$articles[n].id}" size="25">
<br><br>


{if $articles[n].tags}
<b style="color: red">Удалить тэги: </b>
<span style="color: #00A">
{section name=x loop=$articles[n].tags}
<b>{$articles[n].tags[x].tag_name}</b> <input name="delete_article_tag_{$articles[n].tags[x].id}" type='checkbox' value='1'> &nbsp; 
{/section}
</span>
<br>
{/if}

<br>

<b>Не публиковать</b> <input name="hidden_article_{$articles[n].id}" type='checkbox' value="1" {if $articles[n].temp}checked{/if} onchange="change('hidden_change_{$articles[n].id}');">
<input id="hidden_change_{$articles[n].id}" type="hidden" name="hidden_change_{$articles[n].id}">

<br>
<b style="color: red">Удалить статью</b> <input name="delete_article_{$articles[n].id}" type='checkbox' value='1'>



</td>
</tr>




<tr><td style="font: bold 12px Vedrana">
 &nbsp;Текст статьи &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<input id="article_{$articles[n].id}" type="checkbox" value="1" name="article_change_{$articles[n].id}"/><br>

<textarea style="background-color: #FFFFF" rows='15' cols='90' name='article_text_{$articles[n].id}' onchange="$('#article_{$articles[n].id}').attr('checked','true');">{$articles[n].text nofilter}</textarea>

</td>
</tr></table>
</div>

<br>
{/section}
{/if}











<br>





</form>
</TD></TR></TABLE>

{/if}
{include file="footer.tpl"}