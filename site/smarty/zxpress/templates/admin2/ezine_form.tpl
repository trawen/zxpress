<div class="admin-panel" data-lang-tabs>
<form method="post" action="/admin2/ezine.php?id={if $ezine}{$ezine.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<div class="admin-fields">
<label class="admin-field admin-field--sm">
<select name="original_language">
{foreach from=$languages item=lang}
<option value="{$lang.code}" {if ($ezine && $ezine.original_language eq $lang.code) || (!$ezine && $lang.code eq 'ru')}selected{/if}>{$lang.name}</option>
{/foreach}
</select>
<span>Язык оригинала</span>
</label>
<label class="admin-field admin-field--type">
<select name="type">
<option value="0" {if !$ezine || $ezine.type eq 0}selected{/if}>Газета</option>
<option value="1" {if $ezine && $ezine.type eq 1}selected{/if}>Журнал</option>
<option value="2" {if $ezine && $ezine.type eq 2}selected{/if}>Отчёт</option>
</select>
<span>Тип</span>
</label>
<label class="admin-field admin-field--md">
<select name="city_id">
<option value="0">Город не указан</option>
{foreach from=$cities item=c}
<option value="{$c.id}" {if $ezine && $ezine.city_id eq $c.id}selected{/if}>{$c.name|escape}</option>
{/foreach}
</select>
<span>Город</span>
</label>
<label class="admin-field admin-field--year"><input type="number" name="years_from" placeholder=" " value="{if $ezine}{$ezine.years_from}{/if}"><span>Год с</span></label>
<label class="admin-field admin-field--year"><input type="number" name="years_to" placeholder=" " value="{if $ezine}{$ezine.years_to}{/if}"><span>Год по</span></label>
<label class="admin-field admin-field--numbers"><input type="number" name="numbers" placeholder=" " value="{if $ezine}{$ezine.numbers}{/if}"><span>Номеров</span></label>
<label class="admin-field admin-field--sm">
<select name="is_active">
<option value="1" {if !$ezine || $ezine.is_active}selected{/if}>Активен</option>
<option value="0" {if $ezine && !$ezine.is_active}selected{/if}>Скрыт</option>
</select>
<span>Статус</span>
</label>
</div>

<div class="admin-lang-tabs">
{foreach from=$languages item=lang name=langs}
<button type="button" data-lang-tab="{$lang.code}" class="{if $smarty.foreach.langs.first}is-active{/if}{if $ezine_i18n[$lang.code].title} has-data{/if}">{$lang.code|upper}</button>
{/foreach}
</div>
{foreach from=$languages item=lang name=langs}
<div class="admin-lang-panel{if $smarty.foreach.langs.first} is-active{/if}" data-lang-panel="{$lang.code}">
<div class="admin-fields">
<label class="admin-field admin-field--fill"><input type="text" name="i18n[{$lang.code}][title]" placeholder=" " value="{$ezine_i18n[$lang.code].title}"><span>Название {$lang.code|upper}</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="i18n[{$lang.code}][slug]" placeholder=" " maxlength="191" pattern="[a-z0-9-]*" value="{$ezine_i18n[$lang.code].slug}"><span>Slug {$lang.code|upper}</span></label>
<label class="admin-field admin-field--fill"><input type="text" name="i18n[{$lang.code}][meta_description]" placeholder=" " maxlength="256" value="{$ezine_i18n[$lang.code].meta_description}"><span>Meta {$lang.code|upper}</span></label>
</div>
<label class="admin-field admin-field--wide admin-field--autosize"><textarea name="i18n[{$lang.code}][description]" placeholder=" " data-autosize>{$ezine_i18n[$lang.code].description}</textarea><span>Описание {$lang.code|upper}</span></label>
</div>
{/foreach}

<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="1" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
{if $public_url}<a class="admin-btn admin-btn--ghost" href="{$public_url}" target="_blank" rel="noopener">{include file="admin2/icon.tpl" name="external-link"}<span>На сайт v2</span></a>{/if}
{if $ezine && $ezine.legacy_press_id}<a class="admin-btn admin-btn--ghost" href="/admin_issue.php?id={$ezine.legacy_press_id}">{include file="admin2/icon.tpl" name="arrow-left-to-line"}<span>Старая админка</span></a>{/if}
</div>
</div>
</form>
</div>

{if $ezine}
<div class="admin-panel" style="margin-top:1.25rem">
<div class="admin-actions" style="margin-top:0">
<div class="admin-actions__start">
<a class="admin-btn admin-btn--primary" href="/admin2/ezine_issue.php?id=0&amp;ezine_id={$ezine.id}">{include file="admin2/icon.tpl" name="plus"}<span>Новый выпуск</span></a>
</div>
</div>
<div class="admin-list admin-list--books">
<div class="admin-list__row admin-list__row--head">
<div class="admin-list__num">№</div>
<div class="admin-list__title">Выпуск</div>
<div class="admin-list__year">Дата</div>
<div class="admin-list__count">Статьи</div>
<div class="admin-list__link"></div>
</div>
{foreach from=$issues item=iss}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/ezine_issue.php?id={$iss.id}&amp;ezine_id={$ezine.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$iss.id}</div>
<div class="admin-list__title"><a href="/admin2/ezine_issue.php?id={$iss.id}&amp;ezine_id={$ezine.id}">{$iss.title|escape}</a>{if $iss.is_missing} · утерян{/if}</div>
<div class="admin-list__year">{if $iss.issue_date}{$iss.issue_date|date_format:"%d.%m.%Y"}{/if}</div>
<div class="admin-list__count">{$iss.articles_count}</div>
<div class="admin-list__link"></div>
</div>
{foreachelse}
<div class="admin-list__row"><div class="admin-list__title">Выпусков пока нет</div></div>
{/foreach}
</div>
</div>
{/if}
