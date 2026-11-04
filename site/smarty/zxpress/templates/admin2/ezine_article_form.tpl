<div class="admin-panel" data-lang-tabs>
<form method="post" action="/admin2/ezine_article.php?id={if $article}{$article.id}{else}0{/if}&amp;issue_id={$issue_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="issue_id" value="{$issue_id}">
<div class="admin-fields">
<label class="admin-field admin-field--sm">
<select name="original_language">
{foreach from=$languages item=lang}
<option value="{$lang.code}" {if ($article && $article.original_language eq $lang.code) || (!$article && $lang.code eq 'ru')}selected{/if}>{$lang.name}</option>
{/foreach}
</select>
<span>Язык оригинала</span>
</label>
<label class="admin-field admin-field--xs"><input type="number" name="sort_order" placeholder=" " value="{if $article}{$article.sort_order}{else}1{/if}"><span>Порядок</span></label>
<label class="admin-field admin-field--sm">
<select name="is_active">
<option value="1" {if !$article || $article.is_active}selected{/if}>Активна</option>
<option value="0" {if $article && !$article.is_active}selected{/if}>Скрыта</option>
</select>
<span>Статус</span>
</label>
</div>

<div class="admin-lang-tabs">
{foreach from=$languages item=lang name=langs}
<button type="button" data-lang-tab="{$lang.code}" class="{if $smarty.foreach.langs.first}is-active{/if}{if $article_i18n[$lang.code].title || $article_i18n[$lang.code].body} has-data{/if}">{$lang.code|upper}</button>
{/foreach}
</div>
{foreach from=$languages item=lang name=langs}
<div class="admin-lang-panel{if $smarty.foreach.langs.first} is-active{/if}" data-lang-panel="{$lang.code}">
<div class="admin-fields">
<label class="admin-field admin-field--fill"><input type="text" name="i18n[{$lang.code}][title]" placeholder=" " value="{$article_i18n[$lang.code].title}"><span>Название {$lang.code|upper}</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="i18n[{$lang.code}][slug]" placeholder=" " maxlength="255" pattern="[a-z0-9-]*" value="{$article_i18n[$lang.code].slug}"><span>Slug {$lang.code|upper}</span></label>
<label class="admin-field admin-field--sm">
<select name="i18n[{$lang.code}][format]">
{foreach from=$body_formats key=code item=label}
<option value="{$code}" {if $article_i18n[$lang.code].format eq $code}selected{/if}>{$label}</option>
{/foreach}
</select>
<span>Формат {$lang.code|upper}</span>
</label>
</div>
<label class="admin-field admin-field--wide"><input type="text" name="i18n[{$lang.code}][meta_description]" placeholder=" " maxlength="512" value="{$article_i18n[$lang.code].meta_description}"><span>Meta {$lang.code|upper}</span></label>
<label class="admin-field admin-field--wide"><textarea name="i18n[{$lang.code}][body]" placeholder=" " rows="18">{$article_i18n[$lang.code].body}</textarea><span>Текст {$lang.code|upper}</span></label>
</div>
{/foreach}

<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="Сохранить" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
<button type="submit" name="save" value="Сохранить и добавить еще" class="admin-btn admin-btn--info">{include file="admin2/icon.tpl" name="plus"}<span>Сохранить и добавить еще</span></button>
{if $public_url}<a class="admin-btn admin-btn--ghost" href="{$public_url}" target="_blank" rel="noopener">{include file="admin2/icon.tpl" name="external-link"}<span>На сайт v2</span></a>{/if}
</div>
</div>
</form>
</div>
