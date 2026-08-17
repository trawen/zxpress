{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<div style="font: bold 14px Verdana">
<br>

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: var(--smn-paper)">

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
	height: calc(100vh - 20px);
	max-height: calc(100vh - 20px);
	display: flex;
	flex-direction: column;
	background: var(--smn-paper);
	z-index: 2;
	box-sizing: border-box;
}
.admin-ascr-sidebar-head {
	flex: 0 0 auto;
}
.admin-ascr-list-wrap {
	font: normal 12px Verdana;
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
	overscroll-behavior: contain;
	-webkit-overflow-scrolling: touch;
	padding-right: 4px;
	border-top: 1px solid #C8C5AC;
	margin-top: 4px;
	padding-top: 6px;
}
.admin-ascr-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 4px 6px;
	align-content: flex-start;
}
.admin-ascr-list-wrap li {
	margin: 0;
	line-height: 1.25;
}
.admin-ascr-list-wrap a {
	color: #493C2F;
	text-decoration: none;
	display: inline-block;
	padding: 2px 5px;
	border: 1px solid #C8C5AC;
	background: var(--smn-surface);
	white-space: nowrap;
	line-height: 1.25;
}
.admin-ascr-list-wrap a sup {
	font: normal 9px Verdana;
	color: #666;
	line-height: 0;
	vertical-align: baseline;
	position: relative;
	top: -0.45em;
}
.admin-ascr-list-wrap a:hover { color: #A41E00; border-color: #A41E00; }
.admin-ascr-list-wrap a.nav-active {
	font-weight: bold;
	color: #A41E00;
	border-color: #A41E00;
	background: #fff;
}
.admin-ascr-muted { font: normal 11px Verdana; color: #666; }
.admin-ascr-card {
	display: inline-block;
	vertical-align: top;
	width: 280px;
	margin: 0 10px 12px 0;
	padding: 8px;
	border: 1px dashed #BBB;
	background: var(--smn-surface);
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
.admin-ascr-emulator {
	margin-bottom: 16px;
	border: 1px solid var(--smn-line);
	background: var(--smn-paper);
}
.admin-ascr-emulator summary {
	cursor: pointer;
	padding: 10px;
	font: bold 12px Verdana;
}
.admin-ascr-emulator-frame {
	display: block;
	width: 100%;
	height: 470px;
	border: 0;
	border-top: 1px solid #8f8a71;
	background: #15181c;
}
.admin-ascr-emulator-note {
	padding: 7px 10px;
	font: normal 11px Verdana;
	color: #2a6a2a;
}
</style>
{/literal}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top">
{if !$press}
<div style="font: 12px Verdana; color:#666">Выберите издание справа.</div>
{elseif !$issue}
<div style="font: 12px Verdana; color:#666">У издания нет выпусков. <a href="admin_issue.php?id={$press.id}">Создать выпуск</a></div>
{else}

<div style="font: bold 12px Verdana; margin-bottom:10px">
Скриншоты выпуска № {$issue.title|escape:'html'}
<span class="admin-ascr-muted">({$screens|@count})</span>
</div>

<details class="admin-ascr-emulator" id="admin-ascr-emulator">
<summary>Запустить эмулятор и сделать скриншоты</summary>
<iframe
	class="admin-ascr-emulator-frame"
	id="admin-ascr-emulator-frame"
	title="Эмулятор выпуска"
	data-src="/admin_issue_emulator.php?id={$press.id}&amp;issue={$issue_id}"
></iframe>
<div class="admin-ascr-emulator-note" id="admin-ascr-emulator-note"></div>
</details>

<form method="post" action="admin_screens.php?id={$press.id}&amp;issue={$issue_id}" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="press_id" value="{$press.id}">
<input type="hidden" name="issue_id" value="{$issue_id}">

<div style="margin-bottom:16px;padding:10px;border:1px solid #C8C5AC;background:var(--smn-surface)">
<div style="font: bold 12px Verdana; margin-bottom:6px">Загрузить скриншоты</div>
<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Файлы</td>
<td><input type="file" name="upload_screen[]" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" multiple></td>
</tr>
<tr>
<td>Тип</td>
<td>
<select name="upload_type" style="width:160px">
<option value="0">заставка</option>
<option value="1">меню</option>
<option value="2">текст</option>
</select>
<span class="admin-ascr-muted"> — для всех выбранных</span>
</td>
</tr>
<tr>
<td></td>
<td>
<button type="submit" name="upload" value="1" style="font: bold 13px Verdana; padding: 3px 12px">Загрузить</button>
<span class="admin-ascr-muted"> (можно несколько файлов сразу)</span>
</td>
</tr>
</table>
</div>

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
<span class="admin-ascr-muted"> — типы/выпуски/удаление; новые файлы тоже подхватятся</span>
</p>
{else}
<p style="font: 12px Verdana; color:#666;margin:0 0 8px">Скриншотов пока нет — выберите файлы выше и нажмите «Загрузить».</p>
{/if}
</form>

{/if}
</td>

<td valign="top" width="240" style="border-left:1px solid #C8C5AC">
<div class="admin-ascr-sidebar">

{if !$press}
<div class="admin-ascr-sidebar-head" style="font: bold 12px Verdana; margin-bottom:6px">Издания</div>
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
<div class="admin-ascr-sidebar-head" style="font: bold 12px Verdana; margin-bottom:6px">
<a href="admin_screens.php" style="font-weight:normal;color:#666">← издания</a><br>
{$press.title|escape:'html'}
<span class="admin-ascr-muted"> · {$issues|@count} вып.</span>
</div>

<div class="admin-ascr-sidebar-head" style="font: bold 12px Verdana; margin-bottom:2px">Выпуски</div>
<div class="admin-ascr-list-wrap">
{if $issues && $issues|@count gt 0}
<ul>
{section name=n loop=$issues}
<li>
<a href="admin_screens.php?id={$press.id}&amp;issue={$issues[n].id}"{if $issues[n].id eq $issue_id} class="nav-active"{/if}>{$issues[n].title|escape:'html'}{if $issues[n].screens_count}<sup>{$issues[n].screens_count}</sup>{/if}</a>
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
</tr>
</table>

{literal}
<script>
(function () {
	var details = document.getElementById('admin-ascr-emulator');
	var frame = document.getElementById('admin-ascr-emulator-frame');
	var note = document.getElementById('admin-ascr-emulator-note');
	if (!details || !frame) return;

	function loadEmulator() {
		if (!frame.getAttribute('src')) {
			frame.setAttribute('src', frame.getAttribute('data-src'));
		}
	}

	details.addEventListener('toggle', function () {
		if (details.open) {
			loadEmulator();
		}
	});
	if (window.location.hash === '#emulator') {
		details.open = true;
		loadEmulator();
	}
	var saved = 0;
	window.addEventListener('message', function (event) {
		if (event.origin !== window.location.origin || event.source !== frame.contentWindow) return;
		if (!event.data || event.data.type !== 'zxpress:issue-screenshot-saved') return;
		saved += 1;
		note.innerHTML = 'Загружено скриншотов: ' + saved + ' (последний #' + String(event.data.id)
			+ '). <a href="' + window.location.href.replace(/#.*$/, '') + '">Обновить список скриншотов</a>';
	});
})();
</script>
{/literal}

</div>
</div>

</TD>
</TR>
</TBODY>
</TABLE>

{/if}
