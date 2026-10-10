<div class="admin-panel">
<form method="post" action="/admin2/edition.php?id={if $edition}{$edition.id}{else}0{/if}&amp;work_id={$work_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<p class="admin-hint"><a href="/admin2/work.php?id={$work_id}">← к книге #{$work_id}</a></p>
<div class="admin-fields">
<label class="admin-field admin-field--md">
<select name="edition_kind">
{foreach from=$edition_kinds item=kind}
<option value="{$kind}" {if ($edition && $edition.edition_kind eq $kind) || (!$edition && $kind eq 'original')}selected{/if}>{$kind}</option>
{/foreach}
</select>
<span>Тип</span>
</label>
<label class="admin-field admin-field--md">
<select name="language_id">
{foreach from=$languages item=lang}
<option value="{$lang.id}" {if $edition && $edition.language_id eq $lang.id}selected{/if}>{$lang.code}</option>
{/foreach}
</select>
<span>Язык издания</span>
</label>
<label class="admin-field admin-field--xs"><input type="number" name="publish_year" placeholder=" " value="{if $edition}{$edition.publish_year}{/if}"><span>Год</span></label>
<label class="admin-field admin-field--date"><input type="date" name="signed_for_print" value="{if $edition}{$edition.signed_for_print}{/if}"><span>Подписано в печать</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="pages" placeholder=" " value="{if $edition}{$edition.pages}{/if}"><span>Страниц</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="circulation" placeholder=" " value="{if $edition}{$edition.circulation}{/if}"><span>Тираж</span></label>
<label class="admin-field admin-field--lg">
<select name="city_id"><option value="0"></option>
{foreach from=$cities item=c}
<option value="{$c.id}" {if $edition && $edition.city_id eq $c.id}selected{/if}>{$c.name}</option>
{/foreach}
</select>
<span>Город</span>
</label>
<label class="admin-check"><input type="checkbox" name="is_active" value="1" {if !$edition || $edition.is_active}checked{/if}> активно</label>
</div>
<div class="admin-fields">
<label class="admin-field admin-field--lg">
<select name="based_on_edition_id"><option value="0"></option>
{foreach from=$siblings item=s}
{if !$edition || $s.id neq $edition.id}<option value="{$s.id}" {if $edition && $edition.based_on_edition_id eq $s.id}selected{/if}>#{$s.id} {$s.edition_kind} {$s.publish_year}</option>{/if}
{/foreach}
</select>
<span>Сделано из</span>
</label>
<label class="admin-field admin-field--lg">
<select name="content_edition_id"><option value="0">свои главы</option>
{foreach from=$siblings item=s}
{if (!$edition || $s.id neq $edition.id) && !$s.content_edition_id}<option value="{$s.id}" {if $edition && $edition.content_edition_id eq $s.id}selected{/if}>главы из #{$s.id}</option>{/if}
{/foreach}
</select>
<span>Откуда главы</span>
</label>
<label class="admin-field admin-field--lg"><input type="text" name="edition_statement" placeholder=" " value="{$edition_i18n.edition_statement}"><span>Пометка издания</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="edition_slug" placeholder=" " value="{$edition_i18n.slug}"><span>Slug издания</span></label>
<label class="admin-field admin-field--fill"><textarea name="note" placeholder=" ">{$edition_i18n.note}</textarea><span>Заметка</span></label>
</div>
<div class="admin-section">
<h2 class="admin-section__title">ISBN</h2>
{foreach from=$isbns item=isbn}
<label class="admin-field admin-field--md"><input type="text" name="isbn_raw[]" placeholder=" " value="{$isbn}"><span>ISBN</span></label>
{/foreach}
<label class="admin-field admin-field--md"><input type="text" name="isbn_raw[]" placeholder=" " value=""><span>+ ISBN</span></label>
</div>
<div class="admin-section">
<h2 class="admin-section__title">Издательства</h2>
{foreach from=$publishers_linked item=pl}
<div class="admin-repeat__row">
<select name="publisher_id[]"><option value="0"></option>{foreach from=$publishers item=p}<option value="{$p.id}" {if $p.id eq $pl.publisher_id}selected{/if}>{$p.name_ru}</option>{/foreach}</select>
<select name="publisher_role[]">{foreach from=$publisher_roles item=role}<option value="{$role}" {if $pl.role eq $role}selected{/if}>{$role}</option>{/foreach}</select>
</div>
{/foreach}
<div class="admin-repeat__row">
<select name="publisher_id[]"><option value="0">+ издательство</option>{foreach from=$publishers item=p}<option value="{$p.id}">{$p.name_ru}</option>{/foreach}</select>
<select name="publisher_role[]">{foreach from=$publisher_roles item=role}<option value="{$role}">{$role}</option>{/foreach}</select>
</div>
</div>
<div class="admin-fields">
<label class="admin-field admin-field--lg">
<select name="series_id"><option value="0"></option>
{foreach from=$series item=s}
<option value="{$s.series_id}" {if $series_link.series_id eq $s.series_id}selected{/if}>{$s.title}</option>
{/foreach}
</select>
<span>Серия</span>
</label>
<label class="admin-field admin-field--xs"><input type="text" name="number_in_series" placeholder=" " value="{$series_link.number_in_series}"><span>Номер</span></label>
</div>
<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="Сохранить" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
{if $edition}<a class="admin-btn admin-btn--primary" href="/admin2/chapter.php?edition_id={$edition.id}">{include file="admin2/icon.tpl" name="plus"}<span>Новая глава</span></a>{/if}
</div>
{if $edition}
<div class="admin-actions__end">
<button type="submit" name="delete_edition" value="1" class="admin-btn admin-btn--danger" onclick="return confirm('Удалить издание?')">{include file="admin2/icon.tpl" name="trash-2"}<span>Удалить</span></button>
</div>
{/if}
</div>
</form>
</div>
{if $chapters}
<div class="admin-list admin-list--chapters">
<div class="admin-list__row admin-list__row--head"><div class="admin-list__num">№</div><div class="admin-list__title">Глава</div><div class="admin-list__badge">Тип</div><div class="admin-list__count">Порядок</div><div></div></div>
{foreach from=$chapters item=ch}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/chapter.php?id={$ch.id}&amp;edition_id={$edition.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$ch.id}</div>
<div class="admin-list__title"><a href="/admin2/chapter.php?id={$ch.id}&amp;edition_id={$edition.id}">{$ch.title}</a></div>
<div class="admin-list__badge">{$chapter_types[$ch.chapter_type]}</div>
<div class="admin-list__count">{$ch.sort_order}</div>
<div></div>
</div>
{/foreach}
</div>
{/if}
