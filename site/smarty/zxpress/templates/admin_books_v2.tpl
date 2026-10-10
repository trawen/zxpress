{include file="admin_top.tpl"}
{if $login eq 1 and $username}

{literal}
<style>
.admin-books-v2-page-shell {
	box-sizing: border-box;
	height: calc(100vh - 88px);
	max-height: calc(100vh - 88px);
	min-height: 420px;
	padding: 0 10px 10px;
	display: flex;
	flex-direction: column;
}
.admin-books-v2-panel {
	flex: 1 1 auto;
	min-height: 0;
	padding: 10px;
	border: 1px solid #C8C5AC;
	background-color: var(--smn-paper);
	display: flex;
	flex-direction: column;
	font: bold 14px Verdana;
}
.admin-books-v2-toolbar {
	flex: 0 0 auto;
	margin-bottom: 10px;
	display: flex;
	gap: 16px;
	align-items: baseline;
}
.admin-books-v2-layout {
	flex: 1 1 auto;
	min-height: 0;
	width: 100%;
	display: flex;
	gap: 0;
}
.admin-books-v2-sidebar {
	flex: 0 0 420px;
	width: 420px;
	border-right: 1px solid #C8C5AC;
	padding-right: 10px;
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	min-height: 0;
}
.admin-books-v2-sidebar-head {
	flex: 0 0 auto;
	font: bold 12px Verdana;
	margin-bottom: 6px;
}
.admin-books-v2-list-wrap {
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
	overscroll-behavior: contain;
	-webkit-overflow-scrolling: touch;
	font: normal 12px Verdana;
	padding-right: 4px;
	border: 1px solid #C8C5AC;
	background: var(--smn-surface);
}
.admin-books-v2-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 4px 0;
}
.admin-books-v2-list-wrap li {
	margin: 0;
	line-height: 1.35;
	display: flex;
	align-items: baseline;
	gap: 6px;
}
.admin-books-v2-list-wrap a.admin-books-v2-list-name {
	color: #493C2F;
	text-decoration: none;
	display: block;
	flex: 1 1 auto;
	min-width: 0;
	padding: 5px 8px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.admin-books-v2-list-wrap a.admin-books-v2-list-name:hover { color: #A41E00; background: rgba(164,30,0,0.06); }
.admin-books-v2-list-wrap a.admin-books-v2-list-name.nav-active {
	font-weight: bold;
	color: #A41E00;
	background: rgba(164,30,0,0.1);
}
.admin-books-v2-list-wrap a.admin-books-v2-list-name.is-inactive { opacity: 0.55; }
.admin-books-v2-list-meta {
	flex: 0 0 auto;
	font: normal 11px Verdana;
	padding: 5px 8px 5px 0;
	white-space: nowrap;
	color: #555;
}
.admin-books-v2-main {
	flex: 1 1 auto;
	min-width: 0;
	min-height: 0;
	padding-left: 12px;
	overflow: auto;
}
.admin-books-v2-main-head {
	font: bold 12px Verdana;
	margin-bottom: 6px;
}
.admin-books-v2-section {
	margin-top: 16px;
	padding-top: 10px;
	border-top: 1px solid #C8C5AC;
}
.admin-books-v2-section h3 {
	margin: 0 0 8px;
	font: bold 12px Verdana;
}
.admin-books-v2-hint {
	font-size: 11px;
	font-weight: normal;
	color: #555;
}
.admin-books-v2-chapters {
	font: normal 12px Verdana;
	max-height: 280px;
	overflow: auto;
	border: 1px solid #C8C5AC;
	background: var(--smn-surface);
	padding: 4px 0;
}
.admin-books-v2-chapters li {
	padding: 3px 8px;
	display: flex;
	gap: 8px;
}
.admin-books-v2-chapters .ch-ord { color: #888; min-width: 28px; }
.admin-books-v2-chapters .ch-views { color: #888; margin-left: auto; }
.admin-books-v2-lang-tabs {
	display: flex;
	gap: 4px;
	margin: 10px 0 8px;
	flex-wrap: wrap;
}
.admin-books-v2-lang-tabs button {
	font: bold 11px Verdana;
	padding: 4px 10px;
	border: 1px solid #C8C5AC;
	background: var(--smn-surface);
	color: #493C2F;
	cursor: pointer;
}
.admin-books-v2-lang-tabs button.is-active {
	background: rgba(164,30,0,0.1);
	color: #A41E00;
	border-color: #A41E00;
}
.admin-books-v2-lang-tabs button.has-data:not(.is-active) {
	border-bottom: 2px solid #7a8f4a;
}
.admin-books-v2-lang-panel { display: none; }
.admin-books-v2-lang-panel.is-active { display: block; }
</style>
{/literal}

<script type="text/javascript">
(function () {
	var active = document.querySelector('.admin-books-v2-list-wrap a.nav-active');
	if (active && typeof active.scrollIntoView === 'function') {
		active.scrollIntoView({ block: 'nearest' });
	}
})();
</script>

<div class="admin-books-v2-page-shell">
<div class="admin-books-v2-panel">

<div class="admin-books-v2-toolbar">
<a href="admin_books_v2.php?id=0" style="font-weight:bold">+ Новое произведение</a>
<a href="admin_books.php" class="admin-books-v2-hint">Старая админка книг</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px;flex:0 0 auto">{$error}</div>
{/if}

<div class="admin-books-v2-layout">
<aside class="admin-books-v2-sidebar">
<div class="admin-books-v2-sidebar-head">Произведения ({$works_list|@count})</div>
<div class="admin-books-v2-list-wrap" id="admin-books-v2-list" role="navigation" aria-label="Список произведений">
{if $works_list && $works_list|@count gt 0}
<ul>
{section name=n loop=$works_list}
<li>
<a class="admin-books-v2-list-name{if $work && $works_list[n].id eq $work.id} nav-active{/if}{if $works_list[n].is_active eq 0} is-inactive{/if}" href="admin_books_v2.php?id={$works_list[n].id}">{if $works_list[n].is_active eq 0}[×] {/if}{$works_list[n].title}{if $works_list[n].subtitle} — {$works_list[n].subtitle}{/if}{if $works_list[n].first_year} ({$works_list[n].first_year}){/if}</a>
<span class="admin-books-v2-list-meta">{if $works_list[n].langs}{$works_list[n].langs} · {/if}{$works_list[n].chapters_count} гл.</span>
</li>
{/section}
</ul>
{else}
<p style="color:#666;margin:8px">Произведений пока нет</p>
{/if}
</div>
</aside>

<div class="admin-books-v2-main">
<div class="admin-books-v2-main-head">
{if $work && $work.id}Произведение #{$work.id}{if $work.legacy_book_id} <span class="admin-books-v2-hint">(legacy books.id={$work.legacy_book_id})</span>{/if}{else}Новое произведение{/if}
</div>

<form method="post" action="admin_books_v2.php?id={if $work && $work.id}{$work.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Язык оригинала</td>
<td>
<select name="original_language_id" style="width:220px">
{section name=n loop=$languages}
<option value="{$languages[n].id}" {if ($work && $languages[n].id eq $work.original_language_id) || (!$work && $languages[n].code eq 'ru')}selected{/if}>{$languages[n].code} — {$languages[n].name}</option>
{/section}
</select>
<div class="admin-books-v2-hint">На этом языке название обязательно. Остальные вкладки — переводы метаданных.</div>
</td>
</tr>
<tr>
<td>Год (first_year)</td>
<td><input type="number" name="first_year" style="width:100px" value="{if $work && $work.first_year}{$work.first_year}{/if}"></td>
</tr>
<tr>
<td>Активно</td>
<td><input type="checkbox" name="is_active" value="1" {if !$work || $work.is_active}checked{/if}></td>
</tr>
</table>

<div class="admin-books-v2-lang-tabs" role="tablist" aria-label="Языки перевода">
{section name=n loop=$languages}
{assign var=lc value=$languages[n].code}
{assign var=has value=0}
{if $work_i18n_by_lang[$lc].title || $work_i18n_by_lang[$lc].subtitle || $work_i18n_by_lang[$lc].annotation}{assign var=has value=1}{/if}
<button type="button" class="admin-books-v2-lang-tab{if $smarty.section.n.first} is-active{/if}{if $has} has-data{/if}" data-lang="{$lc}" role="tab">{$lc|upper}</button>
{/section}
</div>

{section name=n loop=$languages}
{assign var=lc value=$languages[n].code}
<div class="admin-books-v2-lang-panel{if $smarty.section.n.first} is-active{/if}" data-lang="{$lc}" role="tabpanel">
<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Название{if $smarty.section.n.first} *{/if}</td>
<td><input type="text" name="i18n[{$lc}][title]" style="width:480px" value="{$work_i18n_by_lang[$lc].title}"></td>
</tr>
<tr>
<td>Подзаголовок</td>
<td><input type="text" name="i18n[{$lc}][subtitle]" style="width:480px" value="{$work_i18n_by_lang[$lc].subtitle}"></td>
</tr>
<tr>
<td>Slug ({$lc})</td>
<td>
<input type="text" name="i18n[{$lc}][slug]" style="width:480px" maxlength="191" pattern="[a-z0-9-]*" value="{$work_i18n_by_lang[$lc].slug}">
<div class="admin-books-v2-hint">Пустое поле генерируется из названия. UNIQUE в пределах языка.</div>
</td>
</tr>
<tr>
<td>Аннотация</td>
<td><textarea name="i18n[{$lc}][annotation]" style="width:480px;height:90px">{$work_i18n_by_lang[$lc].annotation}</textarea></td>
</tr>
<tr>
<td>Meta description</td>
<td><input type="text" name="i18n[{$lc}][meta_description]" style="width:480px" maxlength="255" value="{$work_i18n_by_lang[$lc].meta_description}"></td>
</tr>
<tr>
<td>Заголовок на обложке</td>
<td><input type="text" name="edition_i18n[{$lc}][title_override]" style="width:480px" value="{$edition_i18n_by_lang[$lc].title_override}" placeholder="если отличается от названия произведения"></td>
</tr>
<tr>
<td>Пометка издания</td>
<td><input type="text" name="edition_i18n[{$lc}][edition_statement]" style="width:480px" value="{$edition_i18n_by_lang[$lc].edition_statement}" placeholder="2-е изд., испр."></td>
</tr>
<tr>
<td>Заметка издания</td>
<td><textarea name="edition_i18n[{$lc}][note]" style="width:480px;height:60px">{$edition_i18n_by_lang[$lc].note}</textarea></td>
</tr>
<tr>
<td>Slug издания</td>
<td><input type="text" name="edition_i18n[{$lc}][slug]" style="width:480px" maxlength="191" pattern="[a-z0-9-]*" value="{$edition_i18n_by_lang[$lc].slug}"></td>
</tr>
<tr>
<td>Meta издания</td>
<td><input type="text" name="edition_i18n[{$lc}][meta_description]" style="width:480px" maxlength="255" value="{$edition_i18n_by_lang[$lc].meta_description}"></td>
</tr>
</table>
</div>
{/section}

<div class="admin-books-v2-section">
<h3>Основное издание (общие поля)</h3>
<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Тип издания</td>
<td>
<select name="edition_kind" style="width:200px">
{section name=n loop=$edition_kinds}
<option value="{$edition_kinds[n]}" {if ($edition && $edition.edition_kind eq $edition_kinds[n]) || (!$edition && $edition_kinds[n] eq 'original')}selected{/if}>{$edition_kinds[n]}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td>Год издания</td>
<td><input type="number" name="publish_year" style="width:100px" value="{if $edition && $edition.publish_year}{$edition.publish_year}{elseif $work && $work.first_year}{$work.first_year}{/if}"></td>
</tr>
<tr>
<td>Страниц</td>
<td><input type="number" name="pages" style="width:100px" value="{if $edition && $edition.pages}{$edition.pages}{/if}"></td>
</tr>
<tr>
<td>Тираж</td>
<td><input type="number" name="circulation" style="width:120px" value="{if $edition && $edition.circulation}{$edition.circulation}{/if}"></td>
</tr>
<tr>
<td>Город</td>
<td>
<select name="city_id" style="width:240px">
<option value="0">---</option>
{section name=n loop=$cities}
<option value="{$cities[n].id}" {if $edition && $cities[n].id eq $edition.city_id}selected{/if}>{$cities[n].name}</option>
{/section}
</select>
</td>
</tr>
<tr>
<td>ISBN</td>
<td>
<input type="text" name="isbn_raw" style="width:240px" value="{if $isbn}{$isbn.isbn_raw}{/if}">
{if $isbn && $isbn.isbn13}<span class="admin-books-v2-hint"> → {$isbn.isbn13}</span>{/if}
{if $isbn && $isbn.is_invalid}<span style="color:#A41E00;font-weight:normal;font-size:11px"> неверная контрольная сумма</span>{/if}
</td>
</tr>
</table>
</div>

{literal}
<script type="text/javascript">
(function () {
	var tabs = document.querySelectorAll('.admin-books-v2-lang-tab');
	var panels = document.querySelectorAll('.admin-books-v2-lang-panel');
	if (!tabs.length) return;
	function activate(lang) {
		tabs.forEach(function (btn) {
			btn.classList.toggle('is-active', btn.getAttribute('data-lang') === lang);
		});
		panels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-lang') === lang);
		});
	}
	tabs.forEach(function (btn) {
		btn.addEventListener('click', function () {
			activate(btn.getAttribute('data-lang'));
		});
	});
})();
</script>
{/literal}

<div style="margin-top:12px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>
</form>

{if $credits && $credits|@count gt 0}
<div class="admin-books-v2-section">
<h3>Авторы / credits</h3>
<ul style="font:normal 12px Verdana;margin:0;padding-left:18px">
{section name=n loop=$credits}
<li>{$credits[n].role}: {if $credits[n].name_ru}{$credits[n].name_ru}{elseif $credits[n].nickname}{$credits[n].nickname}{else}{$credits[n].name_en}{/if}</li>
{/section}
</ul>
</div>
{/if}

{if $editions && $editions|@count gt 0}
<div class="admin-books-v2-section">
<h3>Издания ({$editions|@count})</h3>
<ul style="font:normal 12px Verdana;margin:0;padding-left:18px">
{section name=n loop=$editions}
<li>#{$editions[n].id} {$editions[n].edition_kind}{if $editions[n].publish_year} {$editions[n].publish_year}{/if} — {$editions[n].chapters_count} гл.{if $edition && $editions[n].id eq $edition.id} (основное){/if}{if $editions[n].legacy_book_id} <span class="admin-books-v2-hint">legacy={$editions[n].legacy_book_id}</span>{/if}</li>
{/section}
</ul>
</div>
{/if}

{if $chapters && $chapters|@count gt 0}
<div class="admin-books-v2-section">
<h3>Главы основного издания ({$chapters|@count}{if $chapters|@count eq 200}+{/if})</h3>
<ul class="admin-books-v2-chapters">
{section name=n loop=$chapters}
<li>
<span class="ch-ord">{$chapters[n].sort_order}</span>
<span>{$chapters[n].title}</span>
<span class="ch-views">{$chapters[n].views}</span>
</li>
{/section}
</ul>
</div>
{/if}

</div>
</div>

</div>
</div>

{/if}
