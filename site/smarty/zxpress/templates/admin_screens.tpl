{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<div style="font: bold 14px Verdana">
<br>

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7">

<div style="margin-bottom:10px">
{if $press && $press.id}
<a href="admin_articles_new.php?id={$press.id}&amp;issue={$issue_id}">← Статьи (new)</a>
&nbsp;&nbsp;
<a href="admin_articles.php?id={$press.id}&amp;issue={$issue_id}">Старая админка</a>
{else}
<a href="admin_articles_new.php">← Статьи (new)</a>
{/if}
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}
{if $notice}
<div style="color:#2a6a2a;margin-bottom:10px">{$notice}</div>
{/if}

{literal}
<style>
.admin-ascr-sidebar {
	position: sticky;
	top: 10px;
	max-height: calc(100vh - 20px);
	display: flex;
	flex-direction: column;
	background: #EBE8D7;
	z-index: 2;
}
.admin-ascr-list-wrap {
	font: normal 12px Verdana;
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
}
.admin-ascr-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.admin-ascr-list-wrap li {
	margin: 0 0 5px;
	line-height: 1.35;
}
.admin-ascr-list-wrap a {
	color: #493C2F;
	text-decoration: none;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.admin-ascr-list-wrap a:hover { color: #A41E00; }
.admin-ascr-list-wrap a.nav-active {
	font-weight: bold;
	color: #A41E00;
}
.admin-ascr-muted { font: normal 11px Verdana; color: #666; }
.admin-ascr-card {
	display: inline-block;
	vertical-align: top;
	width: 280px;
	margin: 0 10px 12px 0;
	padding: 8px;
	border: 1px dashed #BBB;
	background: #f5f3e6;
	font: normal 11px Verdana;
	text-align: center;
}
.admin-ascr-card img {
	max-width: 256px;
	height: auto;
	display: block;
	margin: 0 auto 6px;
	background: #111;
}
.admin-ascr-missing {
	width: 256px;
	height: 192px;
	margin: 0 auto 6px;
	background: rgba(0,0,0,0.35);
	color: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	font: bold 12px Verdana;
}
</style>
{/literal}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="320" style="border-right:1px solid #C8C5AC">
<div class="admin-ascr-sidebar">

{if !$press}
<div style="font: bold 12px Verdana; margin-bottom:6px">Издания</div>
<div class="admin-ascr-list-wrap">
{if $press_list && $press_list|@count gt 0}
<ul>
{foreach from=$press_list item=p}
<li>
<a href="admin_screens.php?id={$p.id}">{$p.title|escape:'html'}{if $p.screens_count} <span class="admin-ascr-muted">({$p.screens_count})</span>{/if}</a>
</li>
{/foreach}
</ul>
{else}
<p style="color:#666;margin:0">Изданий нет</p>
{/if}
</div>
{else}
<div style="font: bold 12px Verdana; margin-bottom:6px">
<a href="admin_screens.php" style="font-weight:normal;color:#666">← издания</a><br>
{$press.title|escape:'html'}
</div>

<div style="font: bold 12px Verdana; margin-bottom:6px">Выпуски</div>
<div class="admin-ascr-list-wrap">
{if $issues && $issues|@count gt 0}
<ul>
{section name=n loop=$issues}
<li>
<a href="admin_screens.php?id={$press.id}&amp;issue={$issues[n].id}"{if $issues[n].id eq $issue_id} class="nav-active"{/if}>№ {$issues[n].title|escape:'html'}{if $issues[n].screens_count} <span class="admin-ascr-muted">({$issues[n].screens_count})</span>{/if}</a>
</li>
{/section}
</ul>
{else}
<p style="color:#666;margin:0">Выпусков нет</p>
{/if}
</div>
{/if}

</div>
</td>

<td valign="top">
{if !$press}
<div style="font: 12px Verdana; color:#666">Выберите издание слева.</div>
{elseif !$issue}
<div style="font: 12px Verdana; color:#666">У издания нет выпусков. <a href="admin_issue.php?id={$press.id}">Создать выпуск</a></div>
{else}

<div style="font: bold 12px Verdana; margin-bottom:10px">
Скриншоты выпуска № {$issue.title|escape:'html'}
<span class="admin-ascr-muted">({$screens|@count})</span>
</div>

<form method="post" action="admin_screens.php?id={$press.id}&amp;issue={$issue_id}" enctype="multipart/form-data" style="margin-bottom:16px;padding:10px;border:1px solid #C8C5AC;background:#f5f3e6">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="press_id" value="{$press.id}">
<input type="hidden" name="issue_id" value="{$issue_id}">
<input type="hidden" name="upload" value="1">
<div style="font: bold 12px Verdana; margin-bottom:6px">Загрузить скриншот</div>
<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Файл</td>
<td><input type="file" name="upload_screen" accept=".png,.jpg,.jpeg,image/png,image/jpeg"></td>
</tr>
<tr>
<td>Тип</td>
<td>
<select name="upload_type" style="width:160px">
<option value="0">заставка</option>
<option value="1">меню</option>
<option value="2">текст</option>
</select>
</td>
</tr>
<tr>
<td></td>
<td><button type="submit" style="font: bold 13px Verdana; padding: 3px 12px">Загрузить</button></td>
</tr>
</table>
</form>

<form method="post" action="admin_screens.php?id={$press.id}&amp;issue={$issue_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="press_id" value="{$press.id}">
<input type="hidden" name="issue_id" value="{$issue_id}">

{if $screens && $screens|@count gt 0}
{section name=n loop=$screens}
<div class="admin-ascr-card">
{if $screens[n].file_exists}
<img src="{$screens[n].public_url|escape:'html'}?v={$screens[n].date}" alt="screen #{$screens[n].id}" width="256">
{else}
<div class="admin-ascr-missing">нет файла<br>#{$screens[n].id}</div>
{/if}
<div style="margin-bottom:4px;font-weight:bold">#{$screens[n].id} · {$screens[n].format|escape:'html'}</div>
<label for="screen-type-{$screens[n].id}">Тип</label>
<select id="screen-type-{$screens[n].id}" name="screen_type[{$screens[n].id}]" style="width:100px;margin:0 4px 6px 0">
<option value="0"{if $screens[n].type eq 0} selected{/if}>заставка</option>
<option value="1"{if $screens[n].type eq 1} selected{/if}>меню</option>
<option value="2"{if $screens[n].type eq 2} selected{/if}>текст</option>
</select>
<br>
<label for="screen-issue-{$screens[n].id}">Выпуск</label>
<select id="screen-issue-{$screens[n].id}" name="screen_issue[{$screens[n].id}]" style="width:80px;margin-bottom:6px">
{section name=i loop=$issues}
<option value="{$issues[i].id}"{if $issues[i].id eq $screens[n].id_issue} selected{/if}>{$issues[i].title|escape:'html'}</option>
{/section}
</select>
<br>
<label style="color:#A41E00"><input type="checkbox" name="delete_screen[{$screens[n].id}]" value="1"> Удалить</label>
</div>
{/section}
<div style="clear:both"></div>
<p style="margin-top:8px">
<input type="submit" name="save" value="Сохранить" style="font: bold 14px Verdana; padding: 4px 14px">
</p>
{else}
<p style="font: 12px Verdana; color:#666;margin:0 0 8px">Скриншотов пока нет — загрузите первый выше.</p>
{/if}
</form>

{/if}
</td>
</tr>
</table>

</div>
</div>

</TD>
</TR>
</TBODY>
</TABLE>

{/if}
