<div class="admin-panel" data-lang-tabs>
<form method="post" action="/admin2/chapter.php?id={if $chapter}{$chapter.id}{else}0{/if}&amp;edition_id={$edition_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<div class="admin-fields">
<label class="admin-field admin-field--md">
<select name="chapter_type">
{foreach from=$chapter_types key=code item=label}
<option value="{$code}" {if ($chapter && $chapter.chapter_type eq $code) || (!$chapter && $code eq 'chapter')}selected{/if}>{$label}</option>
{/foreach}
</select>
<span>Тип</span>
</label>
<label class="admin-field admin-field--page"><input type="number" name="page_start" min="1" placeholder=" " value="{if $chapter && $chapter.page_start}{$chapter.page_start}{/if}"><span>Стр. от</span></label>
<label class="admin-field admin-field--page"><input type="number" name="page_end" min="1" placeholder=" " value="{if $chapter && $chapter.page_end}{$chapter.page_end}{/if}"><span>Стр. до</span></label>
<label class="admin-field admin-field--page"><input type="number" name="sort_order" placeholder=" " value="{if $chapter}{$chapter.sort_order}{else}0{/if}"><span>Порядок</span></label>
</div>
<div class="admin-lang-tabs">
{foreach from=$languages item=lang name=langs}
<button type="button" data-lang-tab="{$lang.code}" class="{if $smarty.foreach.langs.first}is-active{/if}{if $chapter_i18n[$lang.code].title} has-data{/if}">{$lang.code|upper}</button>
{/foreach}
<button type="button" class="admin-btn admin-btn--ghost admin-lang-translate" id="admin-chapter-translate">Перевести</button>
<span class="admin-lang-translate-status" id="admin-chapter-translate-status" aria-live="polite"></span>
</div>
{foreach from=$languages item=lang name=langs}
<div class="admin-lang-panel{if $smarty.foreach.langs.first} is-active{/if}" data-lang-panel="{$lang.code}">
<div class="admin-fields">
<label class="admin-field admin-field--fill"><input type="text" name="i18n[{$lang.code}][title]" placeholder=" " value="{$chapter_i18n[$lang.code].title}"><span>Название {$lang.code|upper}{if $lang.code eq 'ru'}<span class="admin-req">*</span>{/if}</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="i18n[{$lang.code}][slug]" placeholder=" " maxlength="191" pattern="[a-z0-9-]*" value="{$chapter_i18n[$lang.code].slug}"><span>Slug {$lang.code|upper}</span></label>
<label class="admin-field admin-field--sm">
<select name="i18n[{$lang.code}][body_format]">
{foreach from=$body_formats key=code item=label}
<option value="{$code}" {if $chapter_i18n[$lang.code].format eq $code}selected{/if}>{$label}</option>
{/foreach}
</select>
<span>Формат {$lang.code|upper}</span>
</label>
</div>
<label class="admin-field admin-field--wide"><textarea name="i18n[{$lang.code}][body]" placeholder=" ">{$chapter_i18n[$lang.code].body}</textarea><span>Текст {$lang.code|upper}</span></label>
<div class="admin-field admin-field--wide admin-chapter-preview-field">
<div class="admin-chapter-preview" data-chapter-preview tabindex="0" aria-label="Превью {$lang.code|upper}"></div>
<span>Превью {$lang.code|upper}</span>
</div>
</div>
{/foreach}
<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="Сохранить" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
<button type="submit" name="save" value="Сохранить и добавить еще" class="admin-btn admin-btn--info">{include file="admin2/icon.tpl" name="plus"}<span>Сохранить и добавить еще</span></button>
</div>
{if $chapter}
<div class="admin-actions__end">
<button type="submit" name="delete_chapter" value="1" class="admin-btn admin-btn--danger" onclick="return confirm('Удалить главу?')">{include file="admin2/icon.tpl" name="trash-2"}<span>Удалить</span></button>
</div>
{/if}
</div>
</form>
</div>
