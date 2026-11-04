<div class="admin-panel" data-lang-tabs>
<form method="post" enctype="multipart/form-data" action="/admin2/ezine_issue.php?id={if $issue}{$issue.id}{else}0{/if}&amp;ezine_id={$ezine_id}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="ezine_id" value="{$ezine_id}">
<div class="admin-fields">
<label class="admin-field admin-field--date"><input type="text" name="issue_date_fmt" placeholder="дд.мм.гггг" value="{$issue_date_fmt|escape}"><span>Дата</span></label>
<label class="admin-field admin-field--xs"><input type="number" name="sort_order" placeholder=" " value="{if $issue}{$issue.sort_order}{else}0{/if}"><span>Порядок</span></label>
<label class="admin-field admin-field--sm">
<select name="is_missing">
<option value="0" {if !$issue || !$issue.is_missing}selected{/if}>В наличии</option>
<option value="1" {if $issue && $issue.is_missing}selected{/if}>Утерян</option>
</select>
<span>Статус файла</span>
</label>
<label class="admin-field admin-field--sm">
<select name="is_active">
<option value="1" {if !$issue || $issue.is_active}selected{/if}>Активен</option>
<option value="0" {if $issue && !$issue.is_active}selected{/if}>Скрыт</option>
</select>
<span>Публикация</span>
</label>
</div>

<div class="admin-lang-tabs">
{foreach from=$languages item=lang name=langs}
<button type="button" data-lang-tab="{$lang.code}" class="{if $smarty.foreach.langs.first}is-active{/if}{if $issue_i18n[$lang.code].title || $issue_i18n[$lang.code].slug} has-data{/if}">{$lang.code|upper}</button>
{/foreach}
</div>
{foreach from=$languages item=lang name=langs}
<div class="admin-lang-panel{if $smarty.foreach.langs.first} is-active{/if}" data-lang-panel="{$lang.code}">
<div class="admin-fields">
<label class="admin-field admin-field--sm"><input type="text" name="i18n[{$lang.code}][title]" placeholder=" " value="{$issue_i18n[$lang.code].title}"><span>Номер {$lang.code|upper}</span></label>
<label class="admin-field admin-field--lg"><input type="text" name="i18n[{$lang.code}][slug]" placeholder=" " maxlength="255" pattern="[a-z0-9-]*" value="{$issue_i18n[$lang.code].slug}"><span>Slug {$lang.code|upper}</span></label>
<label class="admin-field admin-field--fill"><input type="text" name="i18n[{$lang.code}][meta_description]" placeholder=" " maxlength="256" value="{$issue_i18n[$lang.code].meta_description}"><span>Meta {$lang.code|upper}</span></label>
</div>
<label class="admin-field admin-field--wide admin-field--autosize"><textarea name="i18n[{$lang.code}][description]" placeholder=" " data-autosize>{$issue_i18n[$lang.code].description}</textarea><span>Описание {$lang.code|upper}</span></label>
</div>
{/foreach}

<div class="admin-field admin-letter-box admin-media admin-media--files" style="margin-top:1rem">
<div class="admin-media__docs">
{if $issue_files}
<ul class="admin-file-list">
{foreach from=$issue_files item=file}
<li class="admin-file-chip">
<a class="admin-file-chip__name" href="{$file.public_url}" target="_blank" rel="noopener">{$file.name|escape}</a>
<span class="admin-file-chip__meta">{$file.type_label|escape} · {$file.size} КБ</span>
<label class="admin-field admin-field--sm" style="margin:0.35rem 0 0;width:100%">
<input type="text" name="file_title_{$file.id}" maxlength="32" placeholder=" " value="{$file.file_title|escape}">
<span>Комментарий</span>
</label>
<label class="admin-field admin-field--sm" style="margin:0.35rem 0 0;width:100%">
<select name="file_type_{$file.id}">
{foreach from=$file_type_labels key=code item=label}
<option value="{$code}" {if $file.type eq $code}selected{/if}>{$label}</option>
{/foreach}
</select>
<span>Формат</span>
</label>
<button type="button" class="admin-file-chip__remove admin-file-delete" data-file-id="{$file.id}" aria-label="Удалить файл">×</button>
</li>
{/foreach}
</ul>
{/if}
<label class="admin-btn admin-btn--ghost admin-pair-add admin-media__add admin-media__add--doc">
<input type="file" name="upload_files[]" multiple accept=".zip,.rar,.trd,.scl,.udi,.fdi,.tap,.tzx,.td0,.pdf,.txt,.html,.htm,.djvu">
<span>+ файл</span>
</label>
</div>
<span>Файлы выпуска</span>
</div>
<div class="admin-fields" style="margin-top:0.75rem">
<label class="admin-field admin-field--sm">
<select name="upload_file_type">
<option value="-1">Авто по расширению</option>
{foreach from=$file_type_labels key=code item=label}
<option value="{$code}">{$label}</option>
{/foreach}
</select>
<span>Формат новых</span>
</label>
<label class="admin-field admin-field--fill"><input type="text" name="upload_file_title" maxlength="32" placeholder=" " value=""><span>Комментарий новых</span></label>
</div>

<div class="admin-actions">
<div class="admin-actions__start">
<button type="submit" name="save" value="1" class="admin-btn admin-btn--success">{include file="admin2/icon.tpl" name="save"}<span>Сохранить</span></button>
</div>
</div>
</form>
</div>

{if $issue}
<div class="admin-panel" style="margin-top:1.25rem">
<div class="admin-actions" style="margin-top:0">
<div class="admin-actions__start">
<a class="admin-btn admin-btn--primary" href="/admin2/ezine_article.php?id=0&amp;issue_id={$issue.id}">{include file="admin2/icon.tpl" name="plus"}<span>Новая статья</span></a>
</div>
</div>
<div class="admin-list admin-list--books">
<div class="admin-list__row admin-list__row--head">
<div class="admin-list__num">№</div>
<div class="admin-list__title">Статья</div>
<div class="admin-list__badge">Языки</div>
<div class="admin-list__year">Порядок</div>
<div class="admin-list__link"></div>
</div>
{foreach from=$articles item=a}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/ezine_article.php?id={$a.id}&amp;issue_id={$issue.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$a.id}</div>
<div class="admin-list__title"><a href="/admin2/ezine_article.php?id={$a.id}&amp;issue_id={$issue.id}">{$a.title|strip_tags|escape}</a></div>
<div class="admin-list__badge">{$a.langs}</div>
<div class="admin-list__year">{$a.sort_order}</div>
<div class="admin-list__link">{if $a.legacy_article_id}<a href="/admin_articles_new.php?id={$a.legacy_article_id}" title="Старая админка">{include file="admin2/icon.tpl" name="external-link" class="admin-icon admin-icon--action"}</a>{/if}</div>
</div>
{foreachelse}
<div class="admin-list__row"><div class="admin-list__title">Статей пока нет</div></div>
{/foreach}
</div>
</div>
{/if}
