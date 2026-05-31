{include file="top.tpl"}


<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<table cellpadding=0 cellspacing=0 class="page-pad-8" border=0>
<tr>
<td>







{if $subjs}

<h1 class="title">ZXNet эхоконференция «<span class="big">{$echo.title}</span>»</h1>

<p>{$echo.description nofilter}</p><br>


<table>
{section name=n loop=$subjs}
<tr>
<td><b><a href="{$host}zxnet/{$echo.title|escape:'url'}/{$subjs[n].id}">{$subjs[n].title|default:'###'}</a></b></td>
<td width=40>{$subjs[n].nm}</td>
<td width=100>{$subjs[n].date_from}</td>
</tr>
<tr><td colspan=3><hr class="small"></td></tr>
{/section}
</table>

{elseif $id}

<h1 class="title">ZXNet эхоконференция «<a class="big" href="{$host}zxnet/{$echo.title|escape:'url'}">{$echo.title}</a>»</h1><br>

<h2><big><b>тема:</b> {$subj_title}</big></h2><br>
<hr>


{section name=n loop=$topic}

<b>от:</b> {$topic[n].name_from}<br>
<b>кому: </b>{$topic[n].name_to}<br>
<b>дата:</b> {$topic[n].date}

<hr>

<div class="zxnet-topic-body">{$topic[n].text nofilter}</div>
<hr>

{/section}

{else}

<h1 class="title">Архив эхоконференций сети ZXNet</h1>

<p>ZXNet — некоммерческая сеть, предназначенная для общения поклонников компьютера ZX Spectrum. Узлы ZXNet созданы в десятках городов ex-USSR.</p><p>
<p>Сеть была построена в Москве весной 1995 года в виде сети станций (BBS) на C-DOS модемах и программе C-DOS. Станции позволяли скачивать и закачивать файлы и чатиться с оператором. Переписка осуществлялась обменом специальными файлами.</p><p>Была также освоена автоматическая пересылка сообщений из ZXNet в Fido и обратно. Со временем ZXNet была превращена в FTN-сеть (Fidonet-type network).</p>
<p>Эхоконфере́нция — форма общения в сети Fido, разновидность телеконференций. У каждой эхи есть своё уникальное имя. Название большинства русскоязычных эх состоит из префикса области распространения (города или страны; например, SPB, MO, KIEV, RU, SU) и одного или нескольких слов, отражающих тематику эхи.</p>

<br>

<table width=100%>

<tr>
<td width=300><b>Название</b></td>
<td width=200><b>Количество сообщений</b></td>
<td width=150 align=right><b>Дата</b></td>
</tr>
<tr><td colspan=3><hr class="small"></td></tr>

{section name=n loop=$echos}
<tr>
<td width=300><b><a href="{$host}zxnet/{$echos[n].title|escape:'url'}">{$echos[n].title}</a></b></td>
<td width=200 align=center>{$echos[n].nm}</td>
<td width=150 align=right>{$echos[n].date_from}</td>
</tr>
<tr><td colspan=3><hr class="small"></td></tr>

{/section}
</table>


{/if}







</td>
</tr>
</table>





<br>

<BR>
</TD></TR></TABLE>

{include file="right.tpl"}
{include file="footer.tpl"}