{include file="top.tpl"}


<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<table cellpadding=0 cellspacing=0 class="page-pad-8" border=0>
<tr>
<td>







{if $subjs}

<h1 class="title">{if $lng eq 'eng'}ZXNet echo conference «{else}ZXNet эхоконференция «{/if}<span class="big">{$echo.title}</span>»</h1>

<p>{$echo.description nofilter}</p><br>


<table>
{section name=n loop=$subjs}
<tr>
<td><b><a href="{$host}zxnet/{$echo.title|escape:'url'}/{$subjs[n].id}{$zxnet_lng_qs}">{$subjs[n].title|default:'###'}</a></b></td>
<td width=40>{$subjs[n].nm}</td>
<td width=100>{$subjs[n].date_from}</td>
</tr>
<tr><td colspan=3><hr class="small"></td></tr>
{/section}
</table>

{elseif $id}

<h1 class="title">{$subj_title|default:'###'}</h1>
<p>{if $lng eq 'eng'}ZXNet echo conference «{else}ZXNet эхоконференция «{/if}<a class="big" href="{$host}zxnet/{$echo.title|escape:'url'}{$zxnet_lng_qs}">{$echo.title}</a>»</p><br>
<hr>


{section name=n loop=$topic}

<b>{if $lng eq 'eng'}from:{else}от:{/if}</b> {$topic[n].name_from}<br>
<b>{if $lng eq 'eng'}to:{else}кому:{/if} </b>{$topic[n].name_to}<br>
<b>{if $lng eq 'eng'}date:{else}дата:{/if}</b> {$topic[n].date}

<hr>

<div class="zxnet-topic-body">{$topic[n].text nofilter}</div>
<hr>

{/section}

{else}

<h1 class="title">{if $lng eq 'eng'}Archive of ZXNet echo conferences{else}Архив эхоконференций сети ZXNet{/if}</h1>

{if $lng eq 'eng'}
<p>ZXNet is a non-commercial network for ZX Spectrum enthusiasts. ZXNet nodes were set up in dozens of cities across the former USSR.</p><p>
<p>The network was built in Moscow in spring 1995 as a BBS network on C-DOS modems and the C-DOS software. Stations allowed users to download and upload files and chat with the operator. Correspondence was carried out by exchanging special message files.</p><p>Automatic forwarding of messages from ZXNet to Fido and back was also implemented. Over time, ZXNet was converted into an FTN network (Fidonet-type network).</p>
<p>An echo conference is a form of communication in the Fido network, a type of teleconference. Each echo has its own unique name. Most Russian-language echo names consist of a distribution area prefix (city or country; for example, SPB, MO, KIEV, RU, SU) and one or more words reflecting the echo's topic.</p>
{else}
<p>ZXNet — некоммерческая сеть, предназначенная для общения поклонников компьютера ZX Spectrum. Узлы ZXNet созданы в десятках городов ex-USSR.</p><p>
<p>Сеть была построена в Москве весной 1995 года в виде сети станций (BBS) на C-DOS модемах и программе C-DOS. Станции позволяли скачивать и закачивать файлы и чатиться с оператором. Переписка осуществлялась обменом специальными файлами.</p><p>Была также освоена автоматическая пересылка сообщений из ZXNet в Fido и обратно. Со временем ZXNet была превращена в FTN-сеть (Fidonet-type network).</p>
<p>Эхоконфере́нция — форма общения в сети Fido, разновидность телеконференций. У каждой эхи есть своё уникальное имя. Название большинства русскоязычных эх состоит из префикса области распространения (города или страны; например, SPB, MO, KIEV, RU, SU) и одного или нескольких слов, отражающих тематику эхи.</p>
{/if}

<br>

<table width=100%>

<tr>
<td width=300><b>{if $lng eq 'eng'}Name{else}Название{/if}</b></td>
<td width=200><b>{if $lng eq 'eng'}Messages{else}Количество сообщений{/if}</b></td>
<td width=150 align=right><b>{if $lng eq 'eng'}Date{else}Дата{/if}</b></td>
</tr>
<tr><td colspan=3><hr class="small"></td></tr>

{section name=n loop=$echos}
<tr>
<td width=300><b><a href="{$host}zxnet/{$echos[n].title|escape:'url'}{$zxnet_lng_qs}">{$echos[n].title}</a></b></td>
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
