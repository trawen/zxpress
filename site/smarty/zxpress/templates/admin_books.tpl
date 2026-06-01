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




<form method='POST' enctype='multipart/form-data' action='admin_books.php?id={if $book.id}{$book.id}{else}0{/if}'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<div style="font: bold 14px Verdana">
<br>





<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7">

Информация:<br><br> 


<table><tr><td valign="top">



<table style="font: bold 12px Verdana" cellpadding="4px">

<tr>
<td>Название</td><td><input type="text" style="width: 500px" name="title1" value="{$book.title1}" onchange="change('book_change');"></td>
</tr>

<tr>
<td>Характеристика </td><td><input type="text" style="width: 500px" name="title2" value="{$book.title2}" onchange="change('book_change');"></td>
</tr>



<tr>
<td>Серия</td><td><input type="text" style="width: 500px" name="series" value="{$book.series}" onchange="change('book_change');"></td>
</tr>



<tr>
<td>Авторы</td><td><textarea rows='3' style="width: 500px" name="authors" onchange="change('book_change');">{$book.authors nofilter}</textarea></td>
</tr>

<tr>
<td>Аннотация</td><td><textarea rows='9' style="width: 500px" name="annotation" onchange="change('book_change');">{$book.annotation nofilter}</textarea></td>
</tr>

</table>

</td>






<td valign="top">

<table style="font: bold 12px Verdana" cellpadding="4px">



<tr>
<td><label for="admin-book-type">Формат</label></td><td> 
<select id="admin-book-type" name='type' onchange="change('book_change');" style="width: 150px">
<option value='1' {if $book.type eq 1}selected{/if}>Книга</option>
<option value='2' {if $book.type eq 2}selected{/if}>Журнал</option>
<option value='3' {if $book.type eq 3}selected{/if}>Газета</option>
<option value='4' {if $book.type eq 4}selected{/if}>Брошура</option>
</select>
</td>
</tr>





<tr>
<td><label for="admin-book-publishers">Из справочника</label></td><td>
<select id="admin-book-publishers" name="publisher_ids[]" multiple size="8" style="width:240px" onchange="change('book_change');">
{section name=n loop=$publishers_list}
<option value="{$publishers_list[n].id}" {if $publishers_list[n].selected}selected{/if}>{$publishers_list[n].label}</option>
{/section}
</select>
<div style="font:normal 10px Verdana;color:#666;margin-top:2px">Ctrl+клик — несколько издательств</div>
</td>
</tr>


<tr>
<td>Издательство</td><td><input type="text" style="width: 240px" name="publisher" value="{if $book.publisher}{$book.publisher}{else}«»{/if}" onchange="change('book_change');"></td>
</tr>


<tr>
<td><label for="admin-book-rubrics">Рубрики</label></td><td>
<select id="admin-book-rubrics" name="rubric_ids[]" multiple size="6" style="width:400px" onchange="change('book_change');">
{section name=n loop=$rubrics_list}
<option value="{$rubrics_list[n].id}" {if $rubrics_list[n].selected}selected{/if}>{$rubrics_list[n].label}</option>
{/section}
</select>
<div style="font:normal 10px Verdana;color:#666;margin-top:2px">Ctrl+клик — несколько рубрик</div>
</td>
</tr>


<tr>
<td><label for="admin-book-city">Город</label></td><td>  
<select id="admin-book-city" name="city" onchange="change('book_change');" style="width: 150px">
<option value="0">---</option>
{section name=n loop=$cities}
{cycle values=""}
<option value="{$cities[n].id}" {if $cities[n].id eq $book.city_id}selected{/if}>{$cities[n].name}</otpion>
{/section}
</select>
</td>
</tr>



<tr>
<td><label for="admin-book-language">Язык</label></td><td> 
<select id="admin-book-language" name="language" onchange="change('book_change');" style="width: 150px">
{section name=n loop=$languages}
<option value="{$languages[n].id}" {if $languages[n].id eq $book.language}selected{/if}>{$languages[n].name}</otpion>
{/section}
</select>
</td>
</tr>


<tr>
<td>
ISBN</td><td><input type="text" style="width: 100px" name="isbn" value="{$book.isbn}" onchange="change('book_change');"></td>
</tr>

<tr>
<td>
Кол-во страниц</td><td><input type="text" style="width: 100px" name="pages" value="{$book.pages}" onchange="change('book_change');"></td>
</tr>


<tr>
<td>
Тираж</td><td><input type="text" style="width: 100px" name="circulation" value="{$book.circulation}" onchange="change('book_change');"></td>
</tr>


<tr>
<td>
Дата выхода</td><td><input type="text" style="width: 100px" name="date" value="{$book.date}" onchange="change('book_change');"></td>
</tr>


<tr>
<td>
</td><td>

<br><br>

<a href="admin_books.php?id=0" style="color: blue">Добавить новое издание +</a>
 
 <input type="hidden" value="" name="book_change" id="book_change">
</td>
</tr>

</table>

</td>
</tr>
</table>
 
</div>
<br>





<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; ">

Картинки обложки:<br><br>

{section name=n loop=$pictures}

<div style="display: inline; float: left; padding: 4px; font: bold 11px; border: 1px dashed #BBB; margin: 4px">

<div style="padding: 4px;">
<img src="pictures/{$pictures[n].id}.jpg" width="200">
</div>

<div style="padding: 4px;">

<label for="picture-type-{$pictures[n].id}" class="u-sr-only">Тип обложки</label>
<select id="picture-type-{$pictures[n].id}" name='picture_type_{$pictures[n].id}' onchange="change('change_picture_type_{$pictures[n].id}');" >
<option value='1' {if $pictures[n].type eq 1}selected{/if}>Лицевая сторона</option>
<option value='2' {if $pictures[n].type eq 2}selected{/if}>Задняя сторона</option>
</select>
  
<span style="font: bold 11px; color: red">удалить</span> 
<input type='checkbox' value='1' name="picture_delete_{$pictures[n].id}">


</div>


</div>
<input type="hidden" value="" name="change_picture_type_{$pictures[n].id}" id="change_picture_type_{$pictures[n].id}">
{/section}

{if $pictures[0].id  EQ ""} 
 —
{/if}

<div style="clear: both"></div><br>

<div style="font: bold 12px Verdana">
Загрузить картинки: <input type='file' name='upload_picture1'> &nbsp; <input type='file' name='upload_picture2'> 
</div>

</div>






<br>








<div style="padding: 10px; border: 1px solid #C8C5AC;  background-color: #EBE8D7">

Файлы:<br><br>


{section name=n loop=$files}

<div style="display: inline; float: left; padding: 8px; font: bold 11px/14px; border: 1px dashed #BBB; margin: 4px">


<a href="books_files/{$files[n].file_name}">{$files[n].file_name}</a> [{$files[n].file_size}Kb] <span style="font: 8pt verdana;">{$files[n].date}</span> &nbsp; 

автор <input type="text" size="24" value="{$files[n].author}" name="file_author_{$files[n][0]}" onchange="change('files_change_{$files[n][0]}');">  &nbsp; 


комментарий <input type="text" size="16" value="{$files[n].comment}" name="file_comment_{$files[n][0]}" onchange="change('files_change_{$files[n][0]}');">  &nbsp; 

<label for="book-file-type-{$files[n][0]}">формат</label>
<select id="book-file-type-{$files[n][0]}" name="file_type_{$files[n][0]}" style="font: 8pt verdana;" onchange="change('files_change_{$files[n][0]}');">
<option value="1" {if $files[n].file_type EQ 1}selected{/if} 
>PDF</option>
<option value="2" {if $files[n].file_type EQ 2}selected{/if} 
>DJVU</option>
<option value="3" {if $files[n].file_type EQ 3}selected{/if} 
>HTML</option>
<option value="4" {if $files[n].file_type EQ 4}selected{/if} 
>TXT</option>
<option value="5" {if $files[n].file_type EQ 5}selected{/if} 
>JPG</option>
<option value="6" {if $files[n].file_type EQ 6}selected{/if} 
>Word</option>
</select>

 &nbsp; 


<span style="font: bold 11px; color: red">удалить</span> <input name="file_delete_{$files[n][0]}" type="checkbox">  &nbsp; 



<input type="hidden" value="" name="files_change_{$files[n][0]}" id="files_change_{$files[n][0]}">


</div>
{/section}

{if $files[0].id EQ ""} 
 —
{else}
 <div style="clear: both"></div><br>
{/if}







<div style="font: bold 12px Verdana">
Загрузить файл: <input type='file' name='upload_file'> &nbsp; 

автор <input type="text" size="24" value="" name="upload_file_author">  &nbsp; 


комментарий <input type="text" size="16" value="" name="upload_file_comment">  &nbsp; 

<label for="book-upload-file-type">формат</label>
<select id="book-upload-file-type" name="upload_file_type" style="font: 8pt verdana;">
<option value="1" {if $files[n].file_type EQ 1}selected{/if} 
>PDF</option>
<option value="2" {if $files[n].file_type EQ 2}selected{/if} 
>DJVU</option>
<option value="3" {if $files[n].file_type EQ 3}selected{/if} 
>HTML</option>
<option value="4" {if $files[n].file_type EQ 4}selected{/if} 
>TXT</option>
<option value="5" {if $files[n].file_type EQ 5}selected{/if} 
>JPG</option>
<option value="6" {if $files[n].file_type EQ 6}selected{/if} 
>Word</option>
</select>

</div>


</div>








<br>









<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7; font: bold 14px Verdana">

Главы или статьи: <br><br>

<div style="font: bold 12px Verdana">
Загрузить текст <input type='file' name='upload_text'> &nbsp; &nbsp; 

Переходить к форме добавления новой статьи <input type='checkbox' name='jump' {if $jump}checked{/if}>  &nbsp; &nbsp;  

<!-- / Автонумерация статей</b> <input type='checkbox' name='autonumber' {if $au}checked{/if}> -->
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
<label for="new-ch-article-tag-{$articles[n].ch_id}" class="u-sr-only">Добавить тег</label>
<select id="new-ch-article-tag-{$articles[n].ch_id}" style="display: inline; floating: left;" name="new_article_add_tag_{$articles[n].ch_id}">
<option value='0' selected>---</option>
{section name=i loop=$tags}
<option value='{$tags[i].id}'>{$tags[i].tag_name}</option>
{/section}
</select>
<br><br>




<b>Создать и добавить тэг</b><br>
<input type="text" name="article_new_tag_{$articles[n].ch_id}" size="23">
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

<textarea rows='2' cols='90' name='article_title_{$articles[n].ch_id}' onchange="change('title_change_{$articles[n].ch_id}');">{$articles[n].ch_title nofilter}</textarea>
<input id="title_change_{$articles[n].ch_id}" type="hidden" value="" name="title_change_{$articles[n].ch_id}"/>
</td>








<td style="font: bold 11px Verdana; padding-left: 8px;" ROWSPAN=2>
<a href="chapter.php?id={$articles[n].ch_id}" target="_blank" style="color: blue">Предпросмотр статьи »</a> <br><br><br>

{if $articles[n].by}
Добавил: 
<span style="color: #A41E00;">
{section name=x loop=$articles[n].by}
<b>{$articles[n].by[x].username}</b> &nbsp; 
{/section}
</span>
<br><br>
{/if}



<!--/ <b>Порядковый номер</b>
<input type="text" name="article_number_{$articles[n].id}" size="2" value="{$articles[n].number}" onchange="change('number_change_{$articles[n].id}');">
<input id="number_change_{$articles[n].id}" type="hidden" value="" name="number_change_{$articles[n].id}"/>
<br><br>
-->




<b>Добавить тэг</b><br>
<label for="ch-article-tag-{$articles[n].ch_id}" class="u-sr-only">Добавить тег</label>
<select id="ch-article-tag-{$articles[n].ch_id}" style="display: inline; floating: left;" name="article_add_tag_{$articles[n].ch_id}">
<option value='0' selected>---</option>
{section name=i loop=$tags}
<option value='{$tags[i].id}'>{$tags[i].tag_name}</option>
{/section}
</select>
<br><br>




<b>Создать и добавить тэг</b><br>
<input type="text" name="article_new_tag_{$articles[n].ch_id}" size="25">
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

<b>Не публиковать</b> <input name="hidden_article_{$articles[n].ch_id}" type='checkbox' value="1" {if $articles[n].temp}checked{/if} onchange="change('hidden_change_{$articles[n].ch_id}');">
<input id="hidden_change_{$articles[n].ch_id}" type="hidden" name="hidden_change_{$articles[n].ch_id}">

<br>
<b style="color: red">Удалить статью</b> <input name="delete_article_{$articles[n].ch_id}" type='checkbox' value='1'>



</td>
</tr>




<tr><td style="font: bold 12px Vedrana">
 &nbsp;Текст статьи &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;<input id="article_{$articles[n].ch_id}" type="checkbox" value="1" name="article_change_{$articles[n].ch_id}"/><br>

<textarea style="background-color: #FFFFF" rows='15' cols='90' name='article_text_{$articles[n].ch_id}' onchange="$('#article_{$articles[n].ch_id}').attr('checked','true');">{$articles[n].text nofilter}</textarea>

</td>
</tr></table>
</div>

<br>
{/section}
{/if}












</div>


<br><br>
<center><input type='submit' name='save' value='Сохранить'></center>
</form>


<BR>
</TD></TR></TABLE>
{/if}
{include file="footer.tpl"}