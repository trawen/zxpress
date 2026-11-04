<div class="admin-actions" style="margin-top:0">
<div class="admin-actions__start">
<a class="admin-btn admin-btn--primary" href="/admin2/ezine.php">{include file="admin2/icon.tpl" name="plus"}<span>Новый эзин</span></a>
<a class="admin-btn admin-btn--ghost" href="/ru/ezines2" target="_blank" rel="noopener">{include file="admin2/icon.tpl" name="external-link"}<span>Публичка v2</span></a>
</div>
</div>
<nav class="admin-alpha" aria-label="Алфавит">
<a class="admin-alpha__link admin-alpha__link--all{if $current_letter eq ''} is-current{/if}" href="/admin2/ezines.php">Все</a>
{foreach from=$alpha item=ch}
{if $current_letter eq $ch}
<span class="admin-alpha__link is-current">{$ch}</span>
{else}
<a class="admin-alpha__link" href="/admin2/ezines.php?letter={$ch|escape:'url'}">{$ch}</a>
{/if}
{/foreach}
</nav>
<div class="admin-list admin-list--books">
<div class="admin-list__row admin-list__row--head">
<div class="admin-list__num">№</div>
<div class="admin-list__title">Название</div>
<div class="admin-list__badge">Языки</div>
<div class="admin-list__badge">Номера</div>
<div class="admin-list__count">Статьи</div>
<div class="admin-list__link"></div>
</div>
{foreach from=$ezines item=row}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/ezine.php?id={$row.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$row.id}</div>
<div class="admin-list__title"><a href="/admin2/ezine.php?id={$row.id}">{$row.title|escape}</a></div>
<div class="admin-list__badge">{$row.langs}</div>
<div class="admin-list__badge">{$row.issues_count}</div>
<div class="admin-list__count">{$row.articles_count}</div>
<div class="admin-list__link">{if $row.legacy_press_id}<a href="/admin_issue.php?id={$row.legacy_press_id}" title="Старая админка">{include file="admin2/icon.tpl" name="external-link" class="admin-icon admin-icon--action"}</a>{/if}</div>
</div>
{foreachelse}
<div class="admin-list__row"><div class="admin-list__title">Пока нет изданий в v2</div></div>
{/foreach}
</div>
