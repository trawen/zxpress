<div class="admin-actions" style="margin-top:0">
<div class="admin-actions__start">
<a class="admin-btn admin-btn--primary" href="/admin2/letter.php">{include file="admin2/icon.tpl" name="plus"}<span>Новое письмо</span></a>
<nav class="admin-filter-icons" aria-label="Статус">
<a class="{if $status_filter eq 'all'}is-active{/if}" href="/admin2/letters.php?status=all{if $author_filter_id}&amp;author_id={$author_filter_id}{/if}" title="Все">{include file="admin2/icon.tpl" name="layout-grid"}<sup>{$letter_counts.all}</sup></a>
<a class="{if $status_filter eq $letter_status_draft}is-active{/if}" href="/admin2/letters.php?status={$letter_status_draft}{if $author_filter_id}&amp;author_id={$author_filter_id}{/if}" title="Черновики">{include file="admin2/icon.tpl" name="pencil"}<sup>{$letter_counts.draft}</sup></a>
<a class="{if $status_filter eq $letter_status_queued}is-active{/if}" href="/admin2/letters.php?status={$letter_status_queued}{if $author_filter_id}&amp;author_id={$author_filter_id}{/if}" title="В очереди">{include file="admin2/icon.tpl" name="clock"}<sup>{$letter_counts.queued}</sup></a>
<a class="{if $status_filter eq $letter_status_published}is-active{/if}" href="/admin2/letters.php?status={$letter_status_published}{if $author_filter_id}&amp;author_id={$author_filter_id}{/if}" title="Опубликованные">{include file="admin2/icon.tpl" name="check"}<sup>{$letter_counts.published}</sup></a>
</nav>
</div>
</div>
<div class="admin-list admin-list--letters">
<div class="admin-list__row admin-list__row--head">
<div class="admin-list__num">№</div>
<div class="admin-list__date">Дата</div>
<div class="admin-list__meta">От</div>
<div class="admin-list__meta">Кому</div>
<div class="admin-list__title">Заголовок</div>
<div class="admin-list__status"></div>
<div class="admin-list__shot"></div>
</div>
{foreach from=$letters_groups item=group}
{foreach from=$group.letters item=row}
<div class="admin-list__row">
<div class="admin-list__num"><a href="/admin2/letter.php?id={$row.id}">{include file="admin2/icon.tpl" name="square-pen" class="admin-icon admin-icon--action"}</a>{$row.id}</div>
<div class="admin-list__date">{$row.list_published}</div>
<div class="admin-list__meta"><a href="/admin2/letters.php?status={$status_filter}&amp;author_id={$row.author_from}">{$row.from_nick}</a></div>
<div class="admin-list__meta"><a href="/admin2/letters.php?status={$status_filter}&amp;author_id={$row.author_to}">{$row.to_nick}</a></div>
<div class="admin-list__title"><a href="/admin2/letter.php?id={$row.id}">{$row.title_ru}</a></div>
<div class="admin-list__status admin-list__status--{$row.publish_tone}" title="{$row.publish_label}">{include file="admin2/icon.tpl" name=$row.publish_icon}<span class="admin-sr">{$row.publish_label}</span></div>
<div class="admin-list__shot">{if $row.thumb_url}<a href="/admin2/letter.php?id={$row.id}"><img src="{$row.thumb_url}" alt=""></a>{/if}</div>
</div>
{/foreach}
{/foreach}
</div>
