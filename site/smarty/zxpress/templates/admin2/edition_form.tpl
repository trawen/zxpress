<div class="admin-panel">
<form method="post" enctype="multipart/form-data" action="/admin2/edition.php?id={if $edition}{$edition.id}{else}0{/if}&amp;work_id={$work_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<div class="admin-fields">
<label class="admin-field admin-field--md">
<select name="edition_kind">
{foreach from=$edition_kinds key=code item=label}
<option value="{$code}" {if ($edition && $edition.edition_kind eq $code) || (!$edition && $code eq 'reprint')}selected{/if}>{$label}</option>
{/foreach}
</select>
<span>Тип</span>
</label>
<label class="admin-field admin-field--sm">
<select name="language_id">
{foreach from=$languages item=lang}
<option value="{$lang.id}" {if ($edition && $edition.language_id eq $lang.id) || (!$edition && $lang.code eq 'ru')}selected{/if}>{$lang.name}</option>
{/foreach}
</select>
<span>Язык издания</span>
</label>
<label class="admin-field admin-field--xs"><input type="number" name="publish_year" placeholder=" " value="{if $edition}{$edition.publish_year}{/if}"><span>Год</span></label>
<label class="admin-field admin-field--date"><input type="date" name="signed_for_print" value="{if $edition}{$edition.signed_for_print}{/if}"><span>Подписано в печать</span></label>
<label class="admin-field admin-field--pages"><input type="number" name="pages" placeholder=" " value="{if $edition}{$edition.pages}{/if}"><span>Страниц</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="circulation" placeholder=" " value="{if $edition}{$edition.circulation}{/if}"><span>Тираж</span></label>
<label class="admin-field admin-field--isbn"><input type="text" name="isbn_raw[]" maxlength="32" placeholder=" " value="{if $isbns.0}{$isbns.0}{/if}"><span>ISBN</span></label>
</div>
<div class="admin-fields">
<label class="admin-field admin-field--grow">
<select name="city_id"><option value="0"></option>
{foreach from=$cities item=c}
<option value="{$c.id}" {if $edition && $edition.city_id eq $c.id}selected{/if}>{$c.name}</option>
{/foreach}
</select>
<span>Город</span>
</label>
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
<label class="admin-field admin-field--series-num"><input type="text" name="number_in_series" maxlength="16" placeholder=" " value="{$series_link.number_in_series}"><span>Номер</span></label>
</div>
<div class="admin-fields">
<label class="admin-field admin-field--grow">
<select name="based_on_edition_id"><option value="0"></option>
{foreach from=$siblings item=s}
{if !$edition || $s.id neq $edition.id}<option value="{$s.id}" {if $edition && $edition.based_on_edition_id eq $s.id}selected{/if}>#{$s.id} {$edition_kinds[$s.edition_kind]|default:$s.edition_kind} {$s.publish_year}</option>{/if}
{/foreach}
</select>
<span>Сделано из</span>
</label>
<label class="admin-field admin-field--grow">
<select name="content_edition_id"><option value="0">свои главы</option>
{foreach from=$siblings item=s}
{if (!$edition || $s.id neq $edition.id) && !$s.content_edition_id}<option value="{$s.id}" {if $edition && $edition.content_edition_id eq $s.id}selected{/if}>главы из #{$s.id}</option>{/if}
{/foreach}
</select>
<span>Откуда главы</span>
</label>
<label class="admin-field admin-field--lg"><input type="text" name="edition_statement" placeholder=" " value="{$edition_i18n.edition_statement}"><span>Пометка издания</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="edition_slug" placeholder=" " maxlength="191" pattern="[a-z0-9-]*" value="{$edition_i18n.slug}"><span>Slug издания</span></label>
</div>
<label class="admin-field admin-field--wide admin-field--autosize"><textarea name="note" placeholder=" " data-autosize>{$edition_i18n.note}</textarea><span>Заметка</span></label>

<link rel="stylesheet" href="/js/cropper.min.css">
<script src="/js/cropper.min.js"></script>
<div class="admin-media-row">
<div class="admin-field admin-letter-box admin-media">
<div class="admin-letter-pages" data-remove-confirm="удалить обложку?">
{foreach from=$covers item=img}
<div class="admin-letter-page" draggable="true" data-image-id="{$img.id}">
<img src="{$img.preview_url}" data-full="{$img.original_url}" alt="" draggable="false">
<button type="button" class="admin-letter-page__remove" aria-label="Удалить обложку">×</button>
<input type="hidden" name="sort_order_{$img.id}" value="{$img.sort_order}">
</div>
{/foreach}
<label class="admin-btn admin-btn--ghost admin-pair-add admin-media__add">
<input type="file" id="admin-cover-upload" name="upload_covers[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<span>+1</span>
</label>
</div>
<span>Обложки</span>
</div>
<div class="admin-field admin-letter-box admin-media admin-media--files">
<div class="admin-media__docs">
{if $book_files}
<ul class="admin-file-list">
{foreach from=$book_files item=file}
<li class="admin-file-chip">
<a class="admin-file-chip__name" href="{$file.public_url}">{$file.file_name}</a>
<span class="admin-file-chip__meta">{$file.type_label} · {$file.file_size} КБ</span>
<button type="button" class="admin-file-chip__remove admin-file-delete" data-file-id="{$file.id}" aria-label="Удалить файл">×</button>
</li>
{/foreach}
</ul>
{/if}
<label class="admin-btn admin-btn--ghost admin-pair-add admin-media__add admin-media__add--doc">
<input type="file" name="upload_files[]" multiple accept=".pdf,.doc,.docx,.djvu,.html,.htm,.txt,.rtf,.odt,.zip,.rar,.epub,.fb2">
<span>+1</span>
</label>
</div>
<span>Файлы</span>
</div>
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

<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="Сохранить" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
{if $edition}<a class="admin-btn admin-btn--primary" href="/admin2/chapter.php?edition_id={$edition.id}">{include file="admin2/icon.tpl" name="plus"}<span>Новая глава</span></a>{/if}
<a class="admin-btn admin-btn--ghost" href="/admin2/edition.php?work_id={$work_id}">{include file="admin2/icon.tpl" name="plus"}<span>Ещё издание</span></a>
</div>
{if $edition}
<div class="admin-actions__end">
<button type="submit" name="delete_edition" value="1" class="admin-btn admin-btn--danger" onclick="return confirm('Удалить издание?')">{include file="admin2/icon.tpl" name="trash-2"}<span>Удалить</span></button>
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
{foreach from=$chapters item=ch name=chapters}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/chapter.php?id={$ch.id}&amp;edition_id={$edition.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$smarty.foreach.chapters.iteration}</div>
<div class="admin-list__title"><a href="/admin2/chapter.php?id={$ch.id}&amp;edition_id={$edition.id}">{$ch.title}</a></div>
<div class="admin-list__badge">{$chapter_types[$ch.chapter_type]}</div>
<div class="admin-list__count">{$ch.sort_order}</div>
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
