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
<a href="admin_periodicals.php?id=0" style="font-weight:bold">+ Новое издание</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="320" style="border-right:1px solid #C8C5AC">
<div class="admin-periodicals-sidebar">
<div style="font: bold 12px Verdana; margin-bottom:6px">Издания</div>

{literal}
<style>
.admin-periodicals-sidebar {
	position: sticky;
	top: 10px;
	max-height: calc(100vh - 20px);
	display: flex;
	flex-direction: column;
	background: #EBE8D7;
	z-index: 2;
}
.admin-periodicals-list-wrap {
	font: normal 12px Verdana;
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
}
.admin-periodicals-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.admin-periodicals-list-wrap li {
	margin: 0 0 5px;
	line-height: 1.35;
}
.admin-periodicals-list-wrap .admin-periodicals-list-item {
	display: inline-flex;
	flex-wrap: nowrap;
	align-items: center;
	max-width: 100%;
}
.admin-periodicals-list-wrap .admin-periodicals-list-item > a:first-child {
	flex: 0 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
}
.admin-periodicals-list-wrap a {
	color: #493C2F;
	text-decoration: none;
}
.admin-periodicals-list-wrap a:hover { color: #A41E00; }
.admin-periodicals-list-wrap a.nav-active {
	font-weight: bold;
	color: #A41E00;
}
.admin-periodicals-list-wrap .admin-periodicals-public-link {
	display: inline-block;
	margin-left: 6px;
	color: #666;
	text-decoration: none;
	font-size: 13px;
	line-height: 1;
	vertical-align: middle;
	flex: 0 0 auto;
}
.admin-periodicals-list-wrap .admin-periodicals-public-link:hover { color: #A41E00; }
</style>
{/literal}

<div class="admin-periodicals-list-wrap">
{if $periodicals_list && $periodicals_list|@count gt 0}
<ul>
{foreach from=$periodicals_list item=p}
<li>
<span class="admin-periodicals-list-item">
<a href="admin_periodicals.php?id={$p.id}"{if $periodical && $periodical.id eq $p.id} class="nav-active"{/if}>{if !$p.is_active}[×] {/if}{$p.title_ru|escape:'html'}{if $p.title_en} / {$p.title_en|escape:'html'}{/if}</a><a href="{$host}periodicals.php?id={$p.id}" target="_blank" rel="noopener" class="admin-periodicals-public-link" title="Публичная страница издания" aria-label="Публичная страница издания «{$p.title_ru|escape:'html'}»">→</a>
</span>
</li>
{/foreach}
</ul>
{else}
<p style="color:#666;margin:0">Изданий пока нет</p>
{/if}
</div>
</div>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $periodical && $periodical.id}Редактирование издания #{$periodical.id}{else}Новое издание{/if}
</div>

<form method="post" action="admin_periodicals.php?id={if $periodical && $periodical.id}{$periodical.id}{else}0{/if}{if $issue_id}&issue_id={$issue_id}{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Название (RU) *</td>
<td><input type="text" name="title_ru" style="width:420px" maxlength="255" value="{if $periodical}{$periodical.title_ru}{/if}"></td>
</tr>
<tr>
<td>Название (EN)</td>
<td><input type="text" name="title_en" style="width:420px" maxlength="255" value="{if $periodical}{$periodical.title_en}{/if}"></td>
</tr>
<tr>
<td>ISSN</td>
<td><input type="text" name="issn" style="width:160px" maxlength="16" value="{if $periodical}{$periodical.issn}{/if}"></td>
</tr>
<tr>
<td><label for="admin-periodical-city">Город</label></td>
<td>
<select id="admin-periodical-city" name="city_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$cities}
<option value="{$cities[n].id}" {if $periodical && $cities[n].id eq $periodical.city_id}selected{/if}>{$cities[n].name}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td>Годы</td>
<td>
с <input type="number" name="year_start" style="width:80px" min="1800" max="2100" value="{if $periodical && $periodical.year_start}{$periodical.year_start}{/if}">
по <input type="number" name="year_end" style="width:80px" min="1800" max="2100" value="{if $periodical && $periodical.year_end}{$periodical.year_end}{/if}">
</td>
</tr>
<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="description_ru" style="width:420px;height:80px">{if $periodical}{$periodical.description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="description_en" style="width:420px;height:80px">{if $periodical}{$periodical.description_en}{/if}</textarea></td>
</tr>
<tr>
<td><label for="admin-periodical-publishers">Издательства</label></td>
<td>
<select id="admin-periodical-publishers" name="publisher_ids[]" multiple size="6" style="width:420px">
{section name=n loop=$publishers_list}
<option value="{$publishers_list[n].id}" {if $publishers_list[n].selected}selected{/if}>{$publishers_list[n].label}</option>
{/section}
</select>
<div style="font:normal 10px Verdana;color:#666;margin-top:2px">Ctrl+клик — несколько издательств</div>
</td>
</tr>
{if $periodical && $periodical.updated_at}
<tr>
<td>Обновлено</td>
<td>{$periodical.updated_at}</td>
</tr>
{/if}
<tr>
<td>Активно</td>
<td><input type="checkbox" name="is_active" value="1" {if !$periodical || $periodical.is_active}checked{/if}></td>
</tr>
</table>

<div style="margin-top:10px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>

</form>

{if $periodical && $periodical.id}
<br><br>
<div style="font:bold 12px Verdana;margin-bottom:8px;border-top:1px solid #C8C5AC;padding-top:12px">
Выпуски
<a href="admin_periodicals.php?id={$periodical.id}&issue_id=0&scroll_issue=1" style="font-weight:normal;margin-left:12px">+ новый выпуск</a>
</div>

{if $issues_by_year && $issues_by_year|@count gt 0}
<table style="font:11px Verdana;border-collapse:collapse;width:100%;margin-bottom:12px" cellpadding="4" cellspacing="0">
<tr style="background:#E0DCC8">
<th align="left">№</th>
<th align="left">Том</th>
<th align="left">Дата</th>
<th align="left">Название</th>
<th align="left">Стр.</th>
<th align="left">Статьи</th>
<th align="left"></th>
</tr>
{section name=g loop=$issues_by_year}
<tr style="background:#E8E4D0">
<td colspan="7" style="font:bold 12px Verdana;padding:6px 4px">{$issues_by_year[g].year_label}</td>
</tr>
{section name=n loop=$issues_by_year[g].issues}
<tr style="border-top:1px solid #C8C5AC">
<td>{$issues_by_year[g].issues[n].issue_no}</td>
<td>{if $issues_by_year[g].issues[n].issue_volume}{$issues_by_year[g].issues[n].issue_volume}{else}—{/if}</td>
<td>{if $issues_by_year[g].issues[n].issue_date_fmt}{$issues_by_year[g].issues[n].issue_date_fmt}{else}—{/if}</td>
<td>{$issues_by_year[g].issues[n].title_ru}{if !$issues_by_year[g].issues[n].is_active} [×]{/if}{if $issues_by_year[g].issues[n].is_bound} [перепл.]{/if}</td>
<td>{$issues_by_year[g].issues[n].pages}</td>
<td>{if $issues_by_year[g].issues[n].articles_count}{$issues_by_year[g].issues[n].articles_count}{else}0{/if} <a href="admin_periodical_articles.php?issue_id={$issues_by_year[g].issues[n].id}">→</a></td>
<td><a href="admin_periodicals.php?id={$periodical.id}&issue_id={$issues_by_year[g].issues[n].id}">редактировать</a></td>
</tr>
{/section}
{/section}
</table>
{else}
<div style="color:#777;margin-bottom:12px">Выпусков пока нет.</div>
{/if}

{if $issue}
<div id="admin-periodical-issue-form" style="border:1px solid #C8C5AC;padding:10px;background:#F5F2E8">
<div style="font:bold 12px Verdana;margin-bottom:8px">
{if $issue.id}Редактирование выпуска #{$issue.id}{else}Новый выпуск{/if}
{if $issue.id}<a href="admin_periodical_articles.php?issue_id={$issue.id}" style="font-weight:normal;margin-left:12px">Статьи ({$issue_articles_count}) →</a>{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_periodicals.php?id={$periodical.id}&issue_id={if $issue.id}{$issue.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="issue_id" value="{if $issue.id}{$issue.id}{else}0{/if}">

<table style="font:12px Verdana" cellpadding="4">
<tr>
<td>Номер *</td>
<td><input type="text" name="issue_no" style="width:120px" maxlength="32" value="{$issue.issue_no}"></td>
</tr>
<tr>
<td>Год</td>
<td><input type="number" name="issue_year" style="width:80px" min="1800" max="2100" value="{if $issue.issue_year}{$issue.issue_year}{/if}"></td>
</tr>
<tr>
<td>Том</td>
<td><input type="number" name="issue_volume" style="width:80px" min="0" value="{if $issue.issue_volume}{$issue.issue_volume}{/if}"></td>
</tr>
<tr>
<td>Дата выпуска</td>
<td><input type="text" name="issue_date" style="width:100px" placeholder="дд.мм.гггг" value="{$issue.issue_date_fmt}"></td>
</tr>
<tr>
<td>Название (RU)</td>
<td><input type="text" name="issue_title_ru" style="width:420px" maxlength="255" value="{$issue.title_ru}"></td>
</tr>
<tr>
<td>Название (EN)</td>
<td><input type="text" name="issue_title_en" style="width:420px" maxlength="255" value="{$issue.title_en}"></td>
</tr>
<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="issue_description_ru" style="width:420px;height:60px">{$issue.description_ru}</textarea></td>
</tr>
<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="issue_description_en" style="width:420px;height:60px">{$issue.description_en}</textarea></td>
</tr>
<tr>
<td>Тираж</td>
<td><input type="number" name="circulation" style="width:100px" min="0" value="{if $issue.circulation}{$issue.circulation}{/if}"></td>
</tr>
<tr>
<td>Страниц</td>
<td><input type="number" name="pages" style="width:100px" min="0" value="{if $issue.pages}{$issue.pages}{else}0{/if}"></td>
</tr>
<tr>
<td>Активен</td>
<td><input type="checkbox" name="issue_is_active" value="1" {if !$issue.id || $issue.is_active}checked{/if}></td>
</tr>
<tr>
<td>Переплёт</td>
<td><input type="checkbox" name="issue_is_bound" value="1" {if $issue.is_bound}checked{/if}></td>
</tr>
<tr>
<td valign="top">Обложка</td>
<td>
{if $issue_cover_url}
<div style="margin-bottom:8px">
<div style="font:normal 10px Verdana;color:#666;margin-bottom:2px">Оригинал (JPG)</div>
<img src="{$issue_cover_url}" alt="" style="max-width:200px;height:auto;border:1px solid #C8C5AC;margin-bottom:6px;display:block">
</div>
{if $issue_cover_webp_640_url}
<div style="margin-bottom:8px">
<div style="font:normal 10px Verdana;color:#666;margin-bottom:2px">WebP 640px, 70% — <a href="{$issue_cover_webp_640_url}" target="_blank" rel="noopener">{$issue_cover_webp_640_url}</a></div>
<img src="{$issue_cover_webp_640_url}" alt="" style="max-width:200px;height:auto;border:1px solid #C8C5AC;display:block">
</div>
{/if}
{if $issue_cover_webp_1280_url}
<div style="margin-bottom:8px">
<div style="font:normal 10px Verdana;color:#666;margin-bottom:2px">WebP 1280px, 70% — <a href="{$issue_cover_webp_1280_url}" target="_blank" rel="noopener">{$issue_cover_webp_1280_url}</a></div>
<img src="{$issue_cover_webp_1280_url}" alt="" style="max-width:320px;height:auto;border:1px solid #C8C5AC;display:block">
</div>
{/if}
<label><input type="checkbox" name="delete_issue_cover" value="1"> удалить обложку</label><br>
{/if}
<input type="file" name="upload_issue_cover" accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font:normal 10px Verdana;color:#666;margin-top:2px">Оригинал → JPG; для сайта — WebP 640 и 1280 px (макс. ширина), сжатие 70%</div>
</td>
</tr>
{if $issue.id}
<tr>
<td valign="top">Файлы</td>
<td>
{literal}
<style>
.admin-periodical-issue-files { margin-bottom: 8px; }
.admin-periodical-issue-file-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	margin: 0 0 6px;
	padding: 6px 8px;
	border: 1px solid #D6D0AB;
	background: #F8F6EE;
	font: normal 11px Verdana;
}
.admin-periodical-issue-file-row a { color: #493C2F; }
.admin-periodical-issue-file-row a:hover { color: #A41E00; }
.admin-periodical-issue-file-delete {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	padding: 0;
	border: 1px solid #C8A0A0;
	background: #F5E8E8;
	color: #A41E00;
	font: bold 14px/1 Verdana;
	cursor: pointer;
}
.admin-periodical-issue-file-delete:hover {
	background: #A41E00;
	color: #fff;
	border-color: #A41E00;
}
</style>
{/literal}
<div class="admin-periodical-issue-files">
{if $issue_files && $issue_files|@count gt 0}
{section name=n loop=$issue_files}
<div class="admin-periodical-issue-file-row">
<label>формат
<select name="issue_file_format_{$issue_files[n].id}" style="font:11px Verdana">
{foreach from=$issue_file_formats key=fmtId item=fmtLabel}
<option value="{$fmtId}" {if $issue_files[n].format eq $fmtId}selected{/if}>{$fmtLabel}</option>
{/foreach}
</select>
</label>
{if $issue_files[n].file_url}<a href="{$issue_files[n].file_url}" target="_blank" rel="noopener">{$issue_files[n].name|escape:'html'}</a>{else}{$issue_files[n].name|escape:'html'}{/if}
{if $issue_files[n].size_display}<span style="color:#666">({$issue_files[n].size_display})</span>{/if}
<button type="submit" form="per-issue-file-delete-{$issue_files[n].id}" name="delete_issue_file" value="{$issue_files[n].id}" class="admin-periodical-issue-file-delete" title="Удалить файл">×</button>
</div>
{/section}
{else}
<div style="color:#666;margin-bottom:6px">Файлов пока нет</div>
{/if}
</div>
<input type="file" name="upload_issue_files[]" multiple>
<div style="font:normal 10px Verdana;color:#666;margin-top:4px">
<label for="upload-issue-file-format">Формат для новых файлов (иконка):</label>
<select id="upload-issue-file-format" name="upload_issue_file_format" style="font:11px Verdana;margin-left:4px">
<option value="">— из расширения —</option>
{foreach from=$issue_file_formats key=fmtId item=fmtLabel}
<option value="{$fmtId}">{$fmtLabel}</option>
{/foreach}
</select>
<br>
Имя на диске: транслит названия издания + год + номер выпуска + исходное расширение
</div>
</td>
</tr>
{/if}
</table>

<div style="margin-top:8px">
<input type="submit" name="save_issue" value="Сохранить выпуск" style="height:26px">
</div>
</form>
{if $issue.id && $issue_files && $issue_files|@count gt 0}
{section name=n loop=$issue_files}
<form id="per-issue-file-delete-{$issue_files[n].id}" method="post" action="admin_periodicals.php?id={$periodical.id}&issue_id={$issue.id}#admin-periodical-issue-form">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="periodical_id" value="{$periodical.id}">
<input type="hidden" name="issue_id" value="{$issue.id}">
</form>
{/section}
{/if}
</div>
{/if}
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

{if $scroll_issue}
{literal}
<script type="text/javascript">
window.addEventListener('load', function () {
    var el = document.getElementById('admin-periodical-issue-form');
    if (el) {
        el.scrollIntoView({ behavior: 'instant', block: 'end' });
    }
    window.scrollTo(0, document.documentElement.scrollHeight);
});
</script>
{/literal}
{/if}

{/if}
