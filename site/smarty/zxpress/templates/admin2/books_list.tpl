<div class="admin-actions" style="margin-top:0">
<div class="admin-actions__start">
<a class="admin-btn admin-btn--primary" href="/admin2/work.php">{include file="admin2/icon.tpl" name="plus"}<span>Новая книга</span></a>
</div>
</div>
<nav class="admin-alpha" aria-label="Алфавит">
<a class="admin-alpha__link admin-alpha__link--all{if $current_letter eq ''} is-current{/if}" href="/admin2/books.php">Все</a>
{foreach from=$alpha item=ch}
{if $current_letter eq $ch}
<span class="admin-alpha__link is-current">{$ch}</span>
{else}
<a class="admin-alpha__link" href="/admin2/books.php?letter={$ch|escape:'url'}">{$ch}</a>
{/if}
{/foreach}
</nav>
<div class="admin-list admin-list--books">
<div class="admin-list__row admin-list__row--head">
<div class="admin-list__num">№</div>
<div class="admin-list__title">Название</div>
<div class="admin-list__meta">Авторы</div>
<div class="admin-list__year">Год</div>
<div class="admin-list__badge">Языки</div>
<div class="admin-list__badge">Издания</div>
<div class="admin-list__count">Главы</div>
<div class="admin-list__link"></div>
</div>
{foreach from=$works item=row}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/work.php?id={$row.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$row.id}</div>
<div class="admin-list__title"><a href="/admin2/work.php?id={$row.id}">{$row.title}</a></div>
<div class="admin-list__meta">{$row.authors}</div>
<div class="admin-list__year">{$row.first_year}</div>
<div class="admin-list__badge">{$row.langs}</div>
<div class="admin-list__badge">{$row.editions_count}</div>
<div class="admin-list__count">{$row.chapters_count}</div>
<div class="admin-list__link">{if $row.legacy_book_id}<a href="/book.php?id={$row.legacy_book_id}" title="Старая страница">{include file="admin2/icon.tpl" name="external-link" class="admin-icon admin-icon--action"}</a>{/if}</div>
</div>
{/foreach}
</div>
