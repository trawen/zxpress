{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<div style="font: bold 14px Verdana">
<br>

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: #EBE8D7">

<div style="margin-bottom:10px">
<a href="admin_ezine_categories.php?id=0" style="font-weight:bold">+ Новая категория</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}
{if $merge_message}
<div style="color:#2E6B2E;margin-bottom:10px">{$merge_message}</div>
{/if}
{if $delete_message}
<div style="color:#2E6B2E;margin-bottom:10px">{$delete_message}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="320" style="border-right:1px solid #C8C5AC">
<form method="post" action="admin_ezine_categories.php{if $category && $category.id}?id={$category.id}{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<div style="font: bold 12px Verdana; margin-bottom:6px">Категории</div>

{literal}
<style>
.admin-ezine-categories-tree-wrap ul.tree,
.admin-ezine-categories-tree-wrap ul.tree ul {
	list-style-type: none;
	background: url(http://odyniec.net/articles/turning-lists-into-trees/vline.png) repeat-y;
	margin: 0;
	padding: 0;
}
.admin-ezine-categories-tree-wrap ul.tree ul { margin-left: 10px; }
.admin-ezine-categories-tree-wrap ul.tree li {
	margin: 0;
	padding: 0 12px;
	line-height: 22px;
	background: url(http://odyniec.net/articles/turning-lists-into-trees/node.png) no-repeat;
	font: normal 12px Verdana;
}
.admin-ezine-categories-tree-wrap {
	overflow-x: auto;
}
.admin-ezine-categories-tree-wrap .admin-ec-tree-item {
	display: inline-flex;
	flex-wrap: nowrap;
	align-items: center;
	white-space: nowrap;
	max-width: 100%;
}
.admin-ezine-categories-tree-wrap .admin-ec-tree-item > a:first-child {
	flex: 0 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
}
.admin-ezine-categories-tree-wrap ul.tree li.last {
	background: #EBE8D7 url(http://odyniec.net/articles/turning-lists-into-trees/lastnode.png) no-repeat;
	margin-bottom: 6px;
}
.admin-ezine-categories-tree-wrap ul.tree li.first {
	background: #EBE8D7;
	font-weight: bold;
	padding: 0;
}
.admin-ezine-categories-tree-wrap .tree-item-count {
	font: normal 11px Arial;
	color: #999;
	margin-left: 4px;
	flex: 0 0 auto;
}
.admin-ezine-categories-tree-wrap a.nav-active { font-weight: bold; color: #A41E00; }
.admin-ezine-categories-tree-wrap .admin-ec-public-link {
	display: inline-block;
	margin-left: 6px;
	color: #666;
	text-decoration: none;
	font-size: 13px;
	line-height: 1;
	vertical-align: middle;
	flex: 0 0 auto;
}
.admin-ezine-categories-tree-wrap .admin-ec-public-link:hover { color: #A41E00; }
.admin-ezine-categories-tree-wrap .admin-ec-merge-check {
	margin: 0 0 0 6px;
	vertical-align: middle;
	flex: 0 0 auto;
}
.admin-ezine-categories-tree-wrap .admin-ec-delete-btn {
	border: none;
	background: none;
	cursor: pointer;
	padding: 0 2px;
	margin: 0 0 0 4px;
	color: #A41E00;
	font: bold 14px/1 Verdana, sans-serif;
	vertical-align: middle;
	flex: 0 0 auto;
}
.admin-ezine-categories-tree-wrap .admin-ec-delete-btn:hover { color: #7a1600; }
.admin-ezine-categories-tree-wrap .admin-ec-move-btn {
	border: none;
	background: none;
	cursor: pointer;
	padding: 0 2px;
	margin: 0 0 0 2px;
	color: #666;
	font: 12px/1 Verdana, sans-serif;
	vertical-align: middle;
	flex: 0 0 auto;
}
.admin-ezine-categories-tree-wrap .admin-ec-move-btn:hover:not(:disabled) { color: #A41E00; }
.admin-ezine-categories-tree-wrap .admin-ec-move-btn:disabled {
	color: #ccc;
	cursor: default;
}
.admin-ezine-categories-tree-wrap .admin-ec-merge-actions {
	margin-top: 10px;
	padding-top: 8px;
	border-top: 1px solid #C8C5AC;
}
</style>
{/literal}

{function name=admin_ec_cat_tree level=0}
<ul{if $level eq 0} class="tree"{/if}>
{foreach from=$data item=c}
<li class="{if $c.last}last{/if}{if $level eq 0} first{/if}">
<span class="admin-ec-tree-item">
<a href="admin_ezine_categories.php?id={$c.id}"{if $category && $category.id eq $c.id} class="nav-active"{/if}>{$c.name_ru}</a>
{if $c.articles_count gt 0}<span class="tree-item-count">{$c.articles_count}</span>{/if}
<a href="/ru/categories/{$c.id}" target="_blank" rel="noopener" class="admin-ec-public-link" title="Публичная страница со статьями" aria-label="Публичная страница">→</a>
<label class="admin-ec-merge-check"><input type="checkbox" name="merge_category_ids[]" value="{$c.id}"></label>
<button type="submit" name="delete_category" value="{$c.id}" class="admin-ec-delete-btn" title="Удалить категорию" aria-label="Удалить категорию «{$c.name_ru|escape:'html'}»" onclick="return confirm('Удалить категорию «{$c.name_ru|escape:'javascript'}» и все привязки статей?');">×</button>
<button type="submit" name="move_category" value="{$c.id}:up" class="admin-ec-move-btn" title="Выше"{if !$c.can_move_up} disabled{/if} aria-label="Переместить выше">↑</button>
<button type="submit" name="move_category" value="{$c.id}:down" class="admin-ec-move-btn" title="Ниже"{if !$c.can_move_down} disabled{/if} aria-label="Переместить ниже">↓</button>
</span>
{if $c.tree}{admin_ec_cat_tree data=$c.tree level=$level+1}{/if}
</li>
{/foreach}
</ul>
{/function}

<div class="admin-ezine-categories-tree-wrap">
{if $category_tree && $category_tree|@count gt 0}
{admin_ec_cat_tree data=$category_tree}
{else}
<p style="font:12px Verdana;color:#666;margin:0">Категорий пока нет</p>
{/if}
</div>
<div class="admin-ec-merge-actions">
<input type="submit" name="merge" value="Склеить" style="height:26px">
<div style="color:#666;font:11px Verdana;margin-top:4px">Статьи выбранных категорий будут привязаны ко всем выбранным категориям</div>
</div>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $category && $category.id}Редактирование категории #{$category.id}{else}Новая категория{/if}
{if $category && $category.id}
<span style="font-weight:normal;color:#666"> — статей: {$category.articles_count}</span>
{/if}
</div>

<form method="post" enctype="multipart/form-data" action="admin_ezine_categories.php?id={if $category && $category.id}{$category.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

{literal}
<style>
.admin-ec-edit-form input[type="text"],
.admin-ec-edit-form input[type="number"],
.admin-ec-edit-form input[type="file"],
.admin-ec-edit-form select,
.admin-ec-edit-form textarea {
	width: 620px;
	max-width: 100%;
	box-sizing: border-box;
}
.admin-ec-edit-form textarea.admin-ec-field-desc {
	height: 160px;
}
.admin-ec-edit-form textarea.admin-ec-field-meta {
	height: 72px;
}
.admin-ec-edit-form input[name="sort_order"] {
	width: 80px;
}
</style>
{/literal}

<table class="admin-ec-edit-form" style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Название (RU) *</td>
<td><input type="text" name="name_ru" maxlength="150" value="{if $category}{$category.name_ru}{/if}"></td>
</tr>
<tr>
<td>Название (EN) *</td>
<td><input type="text" name="name_en" maxlength="150" value="{if $category}{$category.name_en}{/if}"></td>
</tr>
<tr>
<td>Заголовок (RU)</td>
<td><input type="text" name="title_ru" maxlength="255" value="{if $category}{$category.title_ru}{/if}"></td>
</tr>
<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" maxlength="255" value="{if $category}{$category.title_en}{/if}"></td>
</tr>
<tr>
<td valign="top">Описание (RU)</td>
<td><textarea name="description_ru" class="admin-ec-field-desc">{if $category}{$category.description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Описание (EN)</td>
<td><textarea name="description_en" class="admin-ec-field-desc">{if $category}{$category.description_en}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta description (RU)</td>
<td><textarea name="meta_description_ru" class="admin-ec-field-meta" maxlength="500">{if $category}{$category.meta_description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta description (EN)</td>
<td><textarea name="meta_description_en" class="admin-ec-field-meta" maxlength="500">{if $category}{$category.meta_description_en}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Иллюстрация</td>
<td>
{if $category_image_url}
<div style="margin-bottom:6px">
<img src="{$category_image_url}" style="max-width:200px;height:auto;border:1px solid #C8C5AC">
<br>
<label><input type="checkbox" name="delete_image" value="1"> удалить иллюстрацию</label>
</div>
{/if}
<input type="file" name="upload_image" accept="image/jpeg,image/png,image/webp,image/gif">
<div style="color:#666;margin-top:4px">Оригинал: data/content-store/ezine-categories/original/ · публикация: WebP 80%</div>
</td>
</tr>
<tr>
<td><label for="admin-ezine-category-parent">Родительская категория</label></td>
<td>
<select id="admin-ezine-category-parent" name="parent_id">
<option value="0">— нет —</option>
{section name=n loop=$parent_categories}
{if !$category || $parent_categories[n].id ne $category.id}
<option value="{$parent_categories[n].id}" {if $category && $parent_categories[n].id eq $category.parent_id}selected{/if}>
{$parent_categories[n].list_label}
</option>
{/if}
{/section}
</select>
</td>
</tr>
<tr>
<td>Порядок сортировки</td>
<td><input type="number" name="sort_order" min="0" max="255" value="{if $category}{$category.sort_order}{else}0{/if}"></td>
</tr>
{if $category && $category.created_at}
<tr>
<td>Создана</td>
<td>{$category.created_at}{if $category.updated_at} · обновлена {$category.updated_at}{/if}</td>
</tr>
{/if}
{if $category && $category.id}
<tr>
<td valign="top">Удалить</td>
<td><label><input type="checkbox" name="delete" value="1"> удалить категорию</label></td>
</tr>
{/if}
</table>

<div style="margin-top:10px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>

</form>

{if $category && $category.id}
<a name="articles"></a>
<div style="margin-top:16px;font:12px Verdana;max-width:640px">
<div style="font-weight:bold;margin-bottom:6px">Статьи ({$linked_articles|@count})</div>

{literal}
<style>
.admin-ec-articles-list { font: 13pt/16pt Times; }
.admin-ec-articles-list hr.line { border: none; border-top: 1px solid #C8C5AC; margin: 0.4em 0; }
.admin-ec-articles-list .tag-press-title { font: normal 15pt Times; margin-bottom: 0.2em; }
.admin-ec-articles-list .type-article-link { font: 13pt/15pt Times; margin-bottom: 0.35em; }
.admin-ec-articles-list .admin-ec-public-link {
	display: inline-block;
	margin-left: 6px;
	color: #666;
	text-decoration: none;
	font-size: 13px;
	line-height: 1;
	vertical-align: middle;
}
.admin-ec-articles-list .admin-ec-public-link:hover { color: #A41E00; }
.admin-ec-articles-list .tag-date-pad { font: 12px Verdana; color: #666; margin-bottom: 0.25em; }
.admin-ec-article-cats { margin: 0.2em 0 0.6em; font: 11px Verdana; line-height: 1.6; }
.admin-ec-article-cat {
	display: inline-block;
	margin: 0 8px 4px 0;
	padding: 1px 4px 1px 6px;
	background: #fff;
	border: 1px solid #C8C5AC;
	white-space: nowrap;
}
.admin-ec-article-cat a { text-decoration: none; color: #493C2F; }
.admin-ec-article-cat a:hover { color: #A41E00; }
.admin-ec-cat-unlink {
	border: none;
	background: none;
	cursor: pointer;
	padding: 0 2px 0 4px;
	margin: 0;
	color: #A41E00;
	font: bold 13px/1 Verdana, sans-serif;
	vertical-align: baseline;
}
.admin-ec-cat-unlink:hover { color: #7a1600; }
.admin-ec-article-preview { cursor: pointer; }
.admin-ec-modal { display: none; position: fixed; inset: 0; z-index: 2000; }
.admin-ec-modal.is-open { display: flex; align-items: center; justify-content: center; }
.admin-ec-modal-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.45); }
.admin-ec-modal-panel {
	position: relative;
	z-index: 1;
	width: min(920px, 92vw);
	max-height: 88vh;
	background: #EBE8D7;
	border: 1px solid #C8C5AC;
	display: flex;
	flex-direction: column;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
}
.admin-ec-modal-head {
	padding: 8px 12px;
	border-bottom: 1px solid #C8C5AC;
	font: bold 13px Verdana;
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}
.admin-ec-modal-title { flex: 1; font: bold 15pt Times; }
.admin-ec-modal-close {
	border: none;
	background: none;
	cursor: pointer;
	color: #A41E00;
	font: bold 18px/1 Verdana, sans-serif;
	padding: 0 4px;
}
.admin-ec-modal-close:hover { color: #7a1600; }
.admin-ec-modal-body {
	padding: 12px 16px;
	overflow: auto;
	background: #fff;
	flex: 1;
	min-height: 120px;
}
.admin-ec-modal-body pre,
.admin-ec-modal-body pre#text {
	margin: 0;
	font: normal 13pt/17pt Menlo, "MenloRegular", monospace;
	white-space: pre-wrap;
	word-wrap: break-word;
}
.admin-ec-modal-loading { color: #666; font: 12px Verdana; padding: 1em 0; }
</style>
{/literal}

<div id="admin-ec-article-modal" class="admin-ec-modal" aria-hidden="true">
<div class="admin-ec-modal-backdrop" data-admin-ec-modal-close="1"></div>
<div class="admin-ec-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-ec-modal-title">
<div class="admin-ec-modal-head">
<div id="admin-ec-modal-title" class="admin-ec-modal-title"></div>
<button type="button" class="admin-ec-modal-close" data-admin-ec-modal-close="1" title="Закрыть" aria-label="Закрыть">×</button>
</div>
<div id="admin-ec-modal-body" class="admin-ec-modal-body"><div class="admin-ec-modal-loading">Загрузка…</div></div>
</div>
</div>

{if $linked_articles && $linked_articles|@count gt 0}
<form method="post" action="admin_ezine_categories.php?id={$category.id}#articles">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<table cellpadding="0" cellspacing="0" border="0" width="100%" class="admin-ec-articles-list">
{foreach from=$linked_articles item=a}
{if $a.show}
<tr><td colspan="2"><hr class="line"></td></tr>
{/if}
<tr>
<td valign="top" colspan="2">
{if $a.show}
<div class="tag-press-title">
<a href="{$host}issue.php?id={$a.id_press}#{$a.issue_title|escape:'url'}">{$a.press_name_plain}</a>
</div>
{if $a.date && $a.date neq "01 января 1970"}<div class="tag-date-pad">{$a.date}</div>{/if}
{/if}
<div class="type-article-link">
<a href="#" class="admin-ec-article-preview" data-article-id="{$a.id_article}">{$a.title_list nofilter}</a><a href="{$host}article.php?id={$a.id_article}" target="_blank" rel="noopener" class="admin-ec-public-link" title="Публичная страница статьи" aria-label="Публичная страница статьи">→</a>
</div>
{if $a.article_categories && $a.article_categories|@count gt 0}
<div class="admin-ec-article-cats">
{foreach from=$a.article_categories item=acat}
<span class="admin-ec-article-cat">
<a href="admin_ezine_categories.php?id={$acat.id}">{$acat.name_ru|escape:'html'}</a><button type="submit" name="unlink" value="{$acat.id}:{$a.id_article}" class="admin-ec-cat-unlink" title="Отвязать от «{$acat.name_ru|escape:'html'}»" aria-label="Отвязать от «{$acat.name_ru|escape:'html'}»">×</button>
</span>
{/foreach}
</div>
{/if}
</td>
</tr>
{/foreach}
</table>
</form>
{else}
<p style="color:#666;margin:0">В этой категории пока нет статей</p>
{/if}
</div>
{/if}

</td>
</tr>
</table>

</div>

</div>

<script type="text/javascript">
(function () {
	var modal = document.getElementById('admin-ec-article-modal');
	var modalTitle = document.getElementById('admin-ec-modal-title');
	var modalBody = document.getElementById('admin-ec-modal-body');
	if (!modal || !modalTitle || !modalBody) {
		return;
	}

	var loadSeq = 0;

	function closeModal() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		modalBody.innerHTML = '<div class="admin-ec-modal-loading">Загрузка…</div>';
		modalTitle.textContent = '';
		document.body.style.overflow = '';
	}

	function openModal(articleId) {
		var seq = ++loadSeq;
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		modalTitle.textContent = 'Загрузка…';
		modalBody.innerHTML = '<div class="admin-ec-modal-loading">Загрузка…</div>';
		document.body.style.overflow = 'hidden';

		fetch('/admin_ezine_categories.php?article_text=' + encodeURIComponent(String(articleId)), {
			credentials: 'same-origin'
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (seq !== loadSeq) {
					return;
				}
				if (!data || !data.ok) {
					modalTitle.textContent = 'Ошибка';
					modalBody.innerHTML = '<div class="admin-ec-modal-loading">Не удалось загрузить текст статьи</div>';
					return;
				}
				modalTitle.innerHTML = data.title || ('Статья #' + data.id);
				modalBody.innerHTML = '';
				var pre = document.createElement('pre');
				pre.id = 'text';
				if (data.text) {
					pre.innerHTML = data.text;
				} else {
					pre.innerHTML = '<i>Текст пуст</i>';
				}
				modalBody.appendChild(pre);
			})
			.catch(function () {
				if (seq !== loadSeq) {
					return;
				}
				modalTitle.textContent = 'Ошибка';
				modalBody.innerHTML = '<div class="admin-ec-modal-loading">Не удалось загрузить текст статьи</div>';
			});
	}

	document.addEventListener('click', function (e) {
		var preview = e.target.closest('.admin-ec-article-preview');
		if (preview) {
			e.preventDefault();
			var articleId = preview.getAttribute('data-article-id');
			if (articleId) {
				openModal(articleId);
			}
			return;
		}
		if (e.target.closest('[data-admin-ec-modal-close]')) {
			closeModal();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && modal.classList.contains('is-open')) {
			closeModal();
		}
	});
})();
</script>

</TD>
</TR>
</TBODY>
</TABLE>

{/if}
