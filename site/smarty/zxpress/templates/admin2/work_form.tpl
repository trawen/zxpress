<div class="admin-panel" data-lang-tabs>
<form method="post" enctype="multipart/form-data" action="/admin2/work.php?id={if $work}{$work.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<div class="admin-fields">
<label class="admin-field admin-field--sm">
<select name="original_language_id">
{foreach from=$languages item=lang}
<option value="{$lang.id}" {if ($work && $work.original_language_id eq $lang.id) || (!$work && $lang.code eq 'ru')}selected{/if}>{$lang.name}</option>
{/foreach}
</select>
<span>Язык оригинала</span>
</label>
<label class="admin-field admin-field--xs"><input type="number" name="publish_year" placeholder=" " value="{if $edition}{$edition.publish_year}{/if}"><span>Год</span></label>
<label class="admin-field admin-field--date"><input type="date" name="signed_for_print" value="{if $edition}{$edition.signed_for_print}{/if}"><span>Подписано в печать</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="pages" placeholder=" " value="{if $edition}{$edition.pages}{/if}"><span>Страниц</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="circulation" placeholder=" " value="{if $edition}{$edition.circulation}{/if}"><span>Тираж</span></label>
<label class="admin-field admin-field--isbn"><input type="text" name="isbn_raw[]" maxlength="32" placeholder=" " value="{if $isbns.0}{$isbns.0}{/if}"><span>ISBN</span></label>
<label class="admin-field admin-field--grow">
<select name="series_id" id="admin-series-select">
<option value="0"></option>
<option value="__new__">+ новая</option>
{foreach from=$series item=s}
<option value="{$s.series_id}" {if $series_link.series_id eq $s.series_id}selected{/if}>{$s.title}</option>
{/foreach}
</select>
<span>Серия</span>
</label>
<label class="admin-field admin-field--xs"><input type="text" name="number_in_series" maxlength="16" placeholder=" " value="{$series_link.number_in_series}"><span>Номер</span></label>
</div>
<div class="admin-lang-tabs">
{foreach from=$languages item=lang name=langs}
<button type="button" data-lang-tab="{$lang.code}" class="{if $smarty.foreach.langs.first}is-active{/if}{if $work_i18n[$lang.code].title} has-data{/if}">{$lang.code|upper}</button>
{/foreach}
</div>
{foreach from=$languages item=lang name=langs}
<div class="admin-lang-panel{if $smarty.foreach.langs.first} is-active{/if}" data-lang-panel="{$lang.code}">
<div class="admin-fields">
<label class="admin-field admin-field--fill"><input type="text" name="i18n[{$lang.code}][title]" placeholder=" " value="{$work_i18n[$lang.code].title}"><span>Название {$lang.code|upper}</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="i18n[{$lang.code}][subtitle]" placeholder=" " value="{$work_i18n[$lang.code].subtitle}"><span>Подзаголовок {$lang.code|upper}</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="i18n[{$lang.code}][slug]" placeholder=" " maxlength="191" pattern="[a-z0-9-]*" value="{$work_i18n[$lang.code].slug}"><span>Slug {$lang.code|upper}</span></label>
</div>
<label class="admin-field admin-field--wide"><textarea name="i18n[{$lang.code}][annotation]" placeholder=" ">{$work_i18n[$lang.code].annotation}</textarea><span>Аннотация {$lang.code|upper}</span></label>
<label class="admin-field admin-field--wide"><input type="text" name="i18n[{$lang.code}][meta_description]" placeholder=" " maxlength="255" value="{$work_i18n[$lang.code].meta_description}"><span>Meta {$lang.code|upper}</span></label>
</div>
{/foreach}

<label class="admin-field admin-field--lg admin-cover-upload"><input type="file" id="admin-cover-upload" name="upload_covers[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"><span>Обложки</span></label>
<link rel="stylesheet" href="/js/cropper.min.css">
<script src="/js/cropper.min.js"></script>
{if $covers}
<div class="admin-field admin-letter-box">
<div class="admin-letter-pages" data-remove-confirm="удалить обложку?">
{foreach from=$covers item=img}
<div class="admin-letter-page" draggable="true" data-image-id="{$img.id}">
<img src="{$img.preview_url}" data-full="{$img.original_url}" alt="" draggable="false">
<button type="button" class="admin-letter-page__remove" aria-label="Удалить обложку">×</button>
<input type="hidden" name="sort_order_{$img.id}" value="{$img.sort_order}">
</div>
{/foreach}
</div>
<span>Обложки</span>
</div>
{/if}

<div class="admin-field admin-group">
<div class="admin-pairs" id="credit-rows">
{foreach from=$credits item=c}
<div class="admin-pair">
<select class="admin-pair__main" name="credit_author[]">
<option value="0"></option>
{foreach from=$authors item=a}
<option value="{$a.id}" {if $a.id eq $c.author_id}selected{/if}>{$a.nickname}</option>
{/foreach}
</select>
<select class="admin-pair__side" name="credit_role[]">
{foreach from=$credit_roles key=code item=label}
<option value="{$code}" {if $c.role eq $code}selected{/if}>{$label}</option>
{/foreach}
</select>
</div>
{foreachelse}
<div class="admin-pair">
<select class="admin-pair__main" name="credit_author[]">
<option value="0"></option>
{foreach from=$authors item=a}
<option value="{$a.id}">{$a.nickname}</option>
{/foreach}
</select>
<select class="admin-pair__side" name="credit_role[]">
{foreach from=$credit_roles key=code item=label}
<option value="{$code}">{$label}</option>
{/foreach}
</select>
</div>
{/foreach}
<button type="button" class="admin-btn admin-btn--ghost admin-pair-add" data-repeat="credit-rows" data-repeat-tpl="credit-row-tpl">+1</button>
</div>
<template id="credit-row-tpl">
<div class="admin-pair">
<select class="admin-pair__main" name="credit_author[]">
<option value="0"></option>
{foreach from=$authors item=a}
<option value="{$a.id}">{$a.nickname}</option>
{/foreach}
</select>
<select class="admin-pair__side" name="credit_role[]">
{foreach from=$credit_roles key=code item=label}
<option value="{$code}">{$label}</option>
{/foreach}
</select>
</div>
</template>
<span>Авторы</span>
</div>

<div class="admin-field admin-group">
<div class="admin-pairs" id="publisher-rows">
{foreach from=$publishers_linked item=pl}
<div class="admin-pair">
<select class="admin-pair__main" name="publisher_id[]">
<option value="0"></option>
{foreach from=$publishers item=p}
<option value="{$p.id}" {if $p.id eq $pl.publisher_id}selected{/if}>{$p.name_ru}</option>
{/foreach}
</select>
<select class="admin-pair__side" name="publisher_role[]">
{foreach from=$publisher_roles key=code item=label}
<option value="{$code}" {if $pl.role eq $code}selected{/if}>{$label}</option>
{/foreach}
</select>
</div>
{foreachelse}
<div class="admin-pair">
<select class="admin-pair__main" name="publisher_id[]">
<option value="0"></option>
{foreach from=$publishers item=p}
<option value="{$p.id}">{$p.name_ru}</option>
{/foreach}
</select>
<select class="admin-pair__side" name="publisher_role[]">
{foreach from=$publisher_roles key=code item=label}
<option value="{$code}">{$label}</option>
{/foreach}
</select>
</div>
{/foreach}
<button type="button" class="admin-btn admin-btn--ghost admin-pair-add" data-repeat="publisher-rows" data-repeat-tpl="publisher-row-tpl">+1</button>
</div>
<template id="publisher-row-tpl">
<div class="admin-pair">
<select class="admin-pair__main" name="publisher_id[]">
<option value="0"></option>
{foreach from=$publishers item=p}
<option value="{$p.id}">{$p.name_ru}</option>
{/foreach}
</select>
<select class="admin-pair__side" name="publisher_role[]">
{foreach from=$publisher_roles key=code item=label}
<option value="{$code}">{$label}</option>
{/foreach}
</select>
</div>
</template>
<span>Издательства</span>
</div>

<div class="admin-section">
<h2 class="admin-section__title">Рубрики</h2>
<div class="admin-fields">
{foreach from=$rubrics item=r}
<label class="admin-check"><input type="checkbox" name="rubric_id[]" value="{$r.id}" {if $r.on}checked{/if}> {$r.name_ru}</label>
{/foreach}
</div>
</div>
<div class="admin-actions">
<div class="admin-actions__start">
<label class="admin-check"><input type="checkbox" name="is_active" value="1" {if !$work || $work.is_active}checked{/if}> активно</label>
<button type="submit" name="save" value="Сохранить" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
<button type="submit" name="save" value="Сохранить и добавить еще" class="admin-btn admin-btn--info">{include file="admin2/icon.tpl" name="plus"}<span>Сохранить и добавить еще</span></button>
<a class="admin-btn admin-btn--primary" href="/admin2/work.php">{include file="admin2/icon.tpl" name="plus"}<span>Новая книга</span></a>
{if $main_edition_id}<a class="admin-btn admin-btn--ghost" href="/admin2/chapter.php?edition_id={$main_edition_id}">{include file="admin2/icon.tpl" name="plus"}<span>Новая глава</span></a>{/if}
{if $work}<a class="admin-btn admin-btn--ghost" href="/admin2/edition.php?work_id={$work.id}">{include file="admin2/icon.tpl" name="plus"}<span>Ещё издание</span></a>{/if}
</div>
{if $work}
<div class="admin-actions__end">
<button type="submit" name="delete_work" value="1" class="admin-btn admin-btn--danger" onclick="return confirm('Удалить книгу со всеми изданиями и главами?')">{include file="admin2/icon.tpl" name="trash-2"}<span>Удалить</span></button>
</div>
{/if}
</div>
</form>
</div>

<dialog class="admin-dialog" id="admin-series-modal">
<form class="admin-dialog__form" id="admin-series-form" method="post" action="/admin2/series.php">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<h2 class="admin-dialog__title">Новая серия</h2>
<p class="admin-dialog__error" id="admin-series-error" hidden></p>
<div class="admin-fields admin-fields--stack">
<label class="admin-field"><input type="text" name="title" placeholder=" " maxlength="255" required><span>Название<span class="admin-req">*</span></span></label>
</div>
<div class="admin-dialog__actions">
<button type="submit" class="admin-btn admin-btn--success">Создать</button>
<button type="button" class="admin-btn admin-btn--ghost" data-series-cancel>Отмена</button>
</div>
</form>
</dialog>

{if $chapters}
<div class="admin-list admin-list--chapters">
<div class="admin-list__row admin-list__row--head"><div class="admin-list__num">№</div><div class="admin-list__title">Глава</div><div class="admin-list__badge">Тип</div><div class="admin-list__count">Порядок</div><div></div></div>
{foreach from=$chapters item=ch}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/chapter.php?id={$ch.id}&amp;edition_id={$main_edition_id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$ch.id}</div>
<div class="admin-list__title"><a href="/admin2/chapter.php?id={$ch.id}&amp;edition_id={$main_edition_id}">{$ch.title}</a></div>
<div class="admin-list__badge">{$chapter_types[$ch.chapter_type]}</div>
<div class="admin-list__count">{$ch.sort_order}</div>
<div></div>
</div>
{/foreach}
</div>
{/if}

{if $editions|@count > 1}
<div class="admin-list admin-list--editions">
<div class="admin-list__row admin-list__row--head"><div class="admin-list__num">№</div><div class="admin-list__title">Издания</div><div class="admin-list__year">Год</div><div class="admin-list__badge">Главы</div><div></div></div>
{foreach from=$editions item=e}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/edition.php?id={$e.id}&amp;work_id={$work.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$e.id}</div>
<div class="admin-list__title"><a href="/admin2/edition.php?id={$e.id}&amp;work_id={$work.id}">{$e.edition_kind}</a>{if $e.is_main} <span class="admin-hint">основное, на этой странице</span>{/if}</div>
<div class="admin-list__year">{$e.publish_year}</div>
<div class="admin-list__badge">{$e.chapters_count}</div>
<div></div>
</div>
{/foreach}
</div>
{/if}
<dialog class="admin-cover-edit" id="admin-cover-edit">
<form method="dialog" class="admin-cover-edit__form">
<div class="admin-cover-edit__title">
<span id="admin-cover-edit-title">Обложка</span>
<span id="admin-cover-edit-count"></span>
</div>
<div class="admin-cover-edit__stage"><img id="admin-cover-edit-img" alt=""></div>
<div class="admin-cover-edit__tools">
<button type="button" class="admin-btn admin-btn--ghost" data-cover-rotate="-90">↺ 90°</button>
<button type="button" class="admin-btn admin-btn--ghost" data-cover-rotate="90">↻ 90°</button>
<button type="button" class="admin-btn admin-btn--ghost" data-cover-flip>Отразить</button>
<label class="admin-cover-edit__skew">Наклон X <input type="range" id="admin-cover-edit-skew-x" min="-25" max="25" value="0" step="1"></label>
<label class="admin-cover-edit__skew">Наклон Y <input type="range" id="admin-cover-edit-skew-y" min="-25" max="25" value="0" step="1"></label>
</div>
<div class="admin-cover-edit__actions">
<button type="button" class="admin-btn admin-btn--success" data-cover-apply>Обрезать</button>
<button type="button" class="admin-btn admin-btn--ghost" data-cover-skip>Без обрезки</button>
<button type="button" class="admin-btn admin-btn--ghost" data-cover-cancel>Отмена</button>
</div>
</form>
</dialog>
<dialog class="admin-letter-view" id="admin-letter-view">
<button type="button" class="admin-letter-view__close" aria-label="Закрыть">×</button>
<img alt="">
</dialog>
