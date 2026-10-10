<div class="admin-panel">
<form method="post" action="/admin2/chapter.php?id={if $chapter}{$chapter.id}{else}0{/if}&amp;edition_id={$edition_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<p class="admin-hint"><a href="{$back_url}">{$back_label}</a></p>
<div class="admin-fields">
<label class="admin-field admin-field--md">
<select name="chapter_type">
{foreach from=$chapter_types key=code item=label}
<option value="{$code}" {if ($chapter && $chapter.chapter_type eq $code) || (!$chapter && $code eq 'chapter')}selected{/if}>{$label}</option>
{/foreach}
</select>
<span>Тип</span>
</label>
<label class="admin-field admin-field--xs"><input type="number" name="page_start" min="1" placeholder=" " value="{if $chapter && $chapter.page_start}{$chapter.page_start}{/if}"><span>Стр. от</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="page_end" min="1" placeholder=" " value="{if $chapter && $chapter.page_end}{$chapter.page_end}{/if}"><span>Стр. до</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="sort_order" placeholder=" " value="{if $chapter}{$chapter.sort_order}{else}0{/if}"><span>Порядок</span></label>
<label class="admin-check"><input type="checkbox" name="is_active" value="1" {if !$chapter || $chapter.is_active}checked{/if}> активна</label>
<label class="admin-field admin-field--fill"><input type="text" name="title" placeholder=" " value="{$chapter_i18n.title}"><span>Название<span class="admin-req">*</span></span></label>
<label class="admin-field admin-field--lg"><input type="text" name="slug" placeholder=" " value="{$chapter_i18n.slug}"><span>Slug</span></label>
<label class="admin-field admin-field--fill"><textarea name="body" placeholder=" ">{$body}</textarea><span>Текст</span></label>
</div>
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
