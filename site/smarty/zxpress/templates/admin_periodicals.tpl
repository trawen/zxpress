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
<td valign="top" width="260" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Издания</div>
<form method="get" action="admin_periodicals.php">
<label for="admin-periodical-list" class="u-sr-only">Выбрать издание</label>
<select id="admin-periodical-list" name="id" style="width:240px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$periodical || !$periodical.id}selected{/if}>— выбрать —</option>
{section name=n loop=$periodicals_list}
<option value="{$periodicals_list[n].id}" {if $periodical && $periodicals_list[n].id eq $periodical.id}selected{/if}>
{if !$periodicals_list[n].is_active}[×] {/if}{$periodicals_list[n].title_ru}{if $periodicals_list[n].title_en} / {$periodicals_list[n].title_en}{/if}
</option>
{/section}
</select>
</form>
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
<th align="left"></th>
</tr>
{section name=g loop=$issues_by_year}
<tr style="background:#E8E4D0">
<td colspan="6" style="font:bold 12px Verdana;padding:6px 4px">{$issues_by_year[g].year_label}</td>
</tr>
{section name=n loop=$issues_by_year[g].issues}
<tr style="border-top:1px solid #C8C5AC">
<td>{$issues_by_year[g].issues[n].issue_no}</td>
<td>{if $issues_by_year[g].issues[n].issue_volume}{$issues_by_year[g].issues[n].issue_volume}{else}—{/if}</td>
<td>{if $issues_by_year[g].issues[n].issue_date_fmt}{$issues_by_year[g].issues[n].issue_date_fmt}{else}—{/if}</td>
<td>{$issues_by_year[g].issues[n].title_ru}{if !$issues_by_year[g].issues[n].is_active} [×]{/if}{if $issues_by_year[g].issues[n].is_bound} [перепл.]{/if}</td>
<td>{$issues_by_year[g].issues[n].pages}</td>
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
</div>

<form method="post" action="admin_periodicals.php?id={$periodical.id}&issue_id={if $issue.id}{$issue.id}{else}0{/if}">
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
</table>

<div style="margin-top:8px">
<input type="submit" name="save_issue" value="Сохранить выпуск" style="height:26px">
</div>
</form>
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
