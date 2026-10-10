<div class="admin-panel">
<form class="admin-letter-form" method="post" enctype="multipart/form-data" action="/admin2/letter.php?id={if $letter}{$letter.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<div class="admin-fields admin-fields--stack">
<div class="admin-field-row">
<label class="admin-field">
<select name="author_from">
<option value="0"></option>
{foreach from=$authors item=a}
<option value="{$a.id}" {if $letter && $a.id eq $letter.author_from}selected{/if}>{$a.nickname}</option>
{/foreach}
</select>
<span>От кого<span class="admin-req">*</span></span>
</label>
<button type="button" class="admin-btn admin-btn--ghost admin-author-add" data-author-add="author_from" title="Новый автор в «От кого»">+1</button>
<label class="admin-field">
<select name="author_to">
<option value="0"></option>
{foreach from=$authors item=a}
<option value="{$a.id}" {if $letter && $a.id eq $letter.author_to}selected{/if}>{$a.nickname}</option>
{/foreach}
</select>
<span>Кому<span class="admin-req">*</span></span>
</label>
<button type="button" class="admin-btn admin-btn--ghost admin-author-add" data-author-add="author_to" title="Новый автор в «Кому»">+1</button>
</div>
<div class="admin-field-row">
<label class="admin-field"><input type="text" name="title_ru" placeholder=" " value="{if $letter}{$letter.title_ru}{/if}"><span>Заголовок RU<span class="admin-req">*</span></span></label>
<label class="admin-field"><input type="text" name="title_en" placeholder=" " value="{if $letter}{$letter.title_en}{/if}"><span>Заголовок EN</span></label>
</div>
<div class="admin-field-row">
<label class="admin-field"><input type="text" name="slug_ru" placeholder=" " maxlength="191" pattern="[a-z0-9-]*" value="{if $letter}{$letter.slug_ru}{/if}"><span>Slug RU</span></label>
<label class="admin-field"><input type="text" name="slug_en" placeholder=" " maxlength="191" pattern="[a-z0-9-]*" value="{if $letter}{$letter.slug_en}{/if}"><span>Slug EN</span></label>
</div>
<label class="admin-field admin-field--date"><input type="text" name="date" placeholder=" " value="{if $letter}{$letter.date}{/if}"><span>Дата</span></label>
<div class="admin-field-row">
<label class="admin-field"><textarea name="summary_ru" placeholder=" " rows="4">{if $letter}{$letter.summary_ru}{/if}</textarea><span>Кратко RU</span></label>
<label class="admin-field"><textarea name="summary_en" placeholder=" " rows="4">{if $letter}{$letter.summary_en}{/if}</textarea><span>Кратко EN</span></label>
</div>
<div class="admin-field-row">
<label class="admin-field admin-field--meta"><textarea name="meta_description_ru" placeholder=" " rows="2">{if $letter}{$letter.meta_description_ru}{/if}</textarea><span>Meta RU</span></label>
<label class="admin-field admin-field--meta"><textarea name="meta_description_en" placeholder=" " rows="2">{if $letter}{$letter.meta_description_en}{/if}</textarea><span>Meta EN</span></label>
</div>
<div class="admin-field-row">
<label class="admin-field admin-field--body"><textarea name="body_ru" placeholder=" " rows="10">{if $letter}{$letter.body_ru}{/if}</textarea><span>Текст RU</span></label>
<label class="admin-field admin-field--body"><textarea name="body_en" placeholder=" " rows="10">{if $letter}{$letter.body_en}{/if}</textarea><span>Текст EN</span></label>
</div>
<div class="admin-ocr">
<label class="admin-field"><input type="file" id="admin-letter-upload" name="upload_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"><span>Сканы</span></label>
<button type="button" class="admin-btn admin-btn--info" id="admin-letter-ocr-btn" data-letter-id="{if $letter && $letter.id}{$letter.id}{else}0{/if}" data-saved-count="{if $images}{$images|@count}{else}0{/if}"{if !$images || $images|@count eq 0} disabled{/if}>Обработать (AI OCR)</button>
<p class="admin-ocr__status" id="admin-letter-ocr-status" hidden></p>
</div>
{if $images}
<div class="admin-field admin-letter-box">
<div class="admin-letter-pages">
{foreach from=$images item=img}
<div class="admin-letter-page" draggable="true" data-image-id="{$img.id}">
<img src="{$img.preview_url}" data-full="{$img.original_url}" alt="" draggable="false">
<button type="button" class="admin-letter-page__remove" aria-label="Удалить картинку">×</button>
<input type="hidden" name="sort_order_{$img.id}" value="{$img.sort_order}">
</div>
{/foreach}
</div>
<span>Картинки</span>
</div>
{/if}
<div class="admin-status" role="radiogroup" aria-label="Статус">
<label class="admin-status__btn admin-status__btn--draft">
<input type="radio" name="publish_status" value="{$letter_status_draft}" {if !$letter || $letter.publish_status eq $letter_status_draft}checked{/if}>
{include file="admin2/icon.tpl" name="pencil"}
<span>черновик</span>
</label>
<label class="admin-status__btn admin-status__btn--queued">
<input type="radio" name="publish_status" value="{$letter_status_queued}" {if $letter && $letter.publish_status eq $letter_status_queued}checked{/if}>
{include file="admin2/icon.tpl" name="clock"}
<span>в очереди</span>
</label>
<label class="admin-status__btn admin-status__btn--published">
<input type="radio" name="publish_status" value="{$letter_status_published}" {if $letter && $letter.publish_status eq $letter_status_published}checked{/if}>
{include file="admin2/icon.tpl" name="check"}
<span>опубликовано</span>
</label>
<label class="admin-status__btn admin-status__btn--deleted">
<input type="radio" name="publish_status" value="{$letter_status_deleted}" {if $letter && $letter.publish_status eq $letter_status_deleted}checked{/if}>
{include file="admin2/icon.tpl" name="trash-2"}
<span>удалено</span>
</label>
</div>
</div>
<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="Сохранить" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
<button type="submit" name="save" value="Сохранить и добавить еще" class="admin-btn admin-btn--info">{include file="admin2/icon.tpl" name="plus"}<span>Сохранить и добавить еще</span></button>
<a class="admin-btn admin-btn--primary" href="/admin2/letter.php">{include file="admin2/icon.tpl" name="plus"}<span>Новое письмо</span></a>
</div>
{if $letter && $letter.id}
<div class="admin-actions__end">
<button type="submit" name="delete_letter" value="1" class="admin-btn admin-btn--danger" onclick="return confirm('Пометить письмо удалённым?')">{include file="admin2/icon.tpl" name="trash-2"}<span>Удалить</span></button>
</div>
{/if}
</div>
</form>
</div>
<dialog class="admin-dialog" id="admin-author-modal">
<form class="admin-dialog__form" id="admin-author-form" method="post" action="/admin2/author.php">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<h2 class="admin-dialog__title">Новый автор</h2>
<p class="admin-dialog__error" id="admin-author-error" hidden></p>
<div class="admin-fields admin-fields--stack">
<label class="admin-field"><input type="text" name="nickname" placeholder=" " maxlength="100" required><span>Ник<span class="admin-req">*</span></span></label>
<div class="admin-field-row">
<label class="admin-field"><input type="text" name="name_ru" placeholder=" " maxlength="255"><span>Имя RU</span></label>
<label class="admin-field"><input type="text" name="name_en" placeholder=" " maxlength="255"><span>Имя EN</span></label>
</div>
<label class="admin-field"><input type="text" name="group_name" placeholder=" " maxlength="255"><span>Группа</span></label>
</div>
<div class="admin-dialog__actions">
<button type="submit" class="admin-btn admin-btn--success">Создать</button>
<button type="button" class="admin-btn admin-btn--ghost" data-author-cancel>Отмена</button>
</div>
</form>
</dialog>
<dialog class="admin-letter-view" id="admin-letter-view">
<button type="button" class="admin-letter-view__close" aria-label="Закрыть">×</button>
<img alt="">
</dialog>
