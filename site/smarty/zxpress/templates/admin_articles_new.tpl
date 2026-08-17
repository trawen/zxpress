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
<a href="admin_articles.php{if $id}?id={$id}{if $issue_id}&amp;issue={$issue_id}{/if}{/if}">← Старая админка статей</a>
{if $press && $press.id}
&nbsp;&nbsp;
<a href="admin_screens.php?id={$press.id}&amp;issue={$issue_id}">Скриншоты</a>
&nbsp;&nbsp;
<a href="admin_articles_new.php?id={$press.id}&amp;issue={$issue_id}&amp;aid=0" style="font-weight:bold">+ Новая статья</a>
{/if}
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}
{if $notice}
<div style="color:#2a6a2a;margin-bottom:10px">{$notice}</div>
{/if}

{literal}
<style>
@font-face {
	font-family: "IBM Plex Sans";
	font-style: normal;
	font-weight: 400;
	src: url("/fonts/IBMPlexSans-Regular.woff2") format("woff2");
	font-display: swap;
}
@font-face {
	font-family: "IBM Plex Sans";
	font-style: italic;
	font-weight: 400;
	src: url("/fonts/IBMPlexSans-Italic.woff2") format("woff2");
	font-display: swap;
}
@font-face {
	font-family: "IBM Plex Sans";
	font-style: normal;
	font-weight: 600;
	src: url("/fonts/IBMPlexSans-SemiBold.woff2") format("woff2");
	font-display: swap;
}
@font-face {
	font-family: "IBM Plex Sans";
	font-style: normal;
	font-weight: 700;
	src: url("/fonts/IBMPlexSans-Bold.woff2") format("woff2");
	font-display: swap;
}
.admin-aan-layout {
	width: 100%;
	table-layout: fixed;
}
.admin-aan-sidebar-cell {
	width: 33.333%;
	max-width: 33.333%;
	vertical-align: top;
	border-left: 1px solid #C8C5AC;
}
.admin-aan-main-cell {
	width: 66.667%;
	vertical-align: top;
}
.admin-aan-sidebar {
	position: sticky;
	top: 10px;
	max-width: 100%;
	max-height: calc(100vh - 20px);
	display: flex;
	flex-direction: column;
	background: #EBE8D7;
	z-index: 2;
	overflow: hidden;
}
.admin-aan-list-wrap {
	font: normal 12px Verdana;
	flex: 1 1 auto;
	min-height: 0;
	max-width: 100%;
	overflow-y: auto;
	overflow-x: hidden;
}
.admin-aan-list-wrap ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.admin-aan-list-wrap li {
	margin: 0 0 5px;
	line-height: 1.35;
	max-width: 100%;
	overflow: hidden;
	display: flex;
	align-items: center;
	gap: 2px;
}
.admin-aan-list-wrap a.admin-aan-article-link {
	color: #493C2F;
	text-decoration: none;
	display: block;
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.admin-aan-list-wrap a.admin-aan-article-link:hover { color: #A41E00; }
.admin-aan-list-wrap a.admin-aan-article-link.nav-active {
	font-weight: bold;
	color: #A41E00;
}
.admin-aan-move {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	white-space: nowrap;
}
.admin-aan-move button {
	border: none;
	background: none;
	cursor: pointer;
	padding: 0 2px;
	margin: 0;
	color: #666;
	font: 12px/1 Verdana, sans-serif;
	vertical-align: middle;
}
.admin-aan-move button:hover:not(:disabled) { color: #A41E00; }
.admin-aan-move button:disabled {
	color: #ccc;
	cursor: default;
}
.admin-aan-muted { font: normal 11px Verdana; color: #666; }
.admin-aan-field-hint { font: normal 10px Verdana; color: #666; margin-top: 2px; }
.admin-aan-tag {
	display: inline-block;
	margin: 0 6px 6px 0;
	padding: 2px 6px;
	border: 1px solid #C8C5AC;
	background: #f5f3e6;
	font: normal 11px Verdana;
}
.admin-aan-title-input,
.admin-aan-text-input,
.admin-aan-form input[type="text"],
.admin-aan-form textarea,
.admin-aan-form select:not(.admin-aan-narrow) {
	width: 100%;
	max-width: none;
	box-sizing: border-box;
}
.admin-aan-form {
	width: 100%;
}
.admin-aan-form > table {
	width: 100%;
	table-layout: fixed;
}
.admin-aan-form > table td:first-child {
	width: 160px;
	vertical-align: top;
	padding-top: 6px;
}
.admin-aan-form textarea.admin-aan-mono {
	font: 12px/1.4 Consolas, Menlo, monospace;
}
.admin-aan-preview-wrap {
	margin-top: 8px;
}
.admin-aan-preview {
	box-sizing: border-box;
	width: 100%;
	margin: 0;
	padding: 8px;
	border: 1px solid #C8C5AC;
	background: #fff;
	color: #222;
	font: 12px/1.4 Verdana, sans-serif;
	white-space: pre-wrap;
	word-break: break-word;
	overflow: auto;
	max-height: calc(1.4em * 5 + 16px);
	min-height: calc(1.4em * 5 + 16px);
	transition: max-height 0.18s ease;
	outline: none;
	cursor: text;
}
.admin-aan-preview.is-html {
	white-space: normal;
}
.admin-aan-preview.is-html-pre {
	font-family: Consolas, Menlo, Monaco, "Courier New", monospace;
	white-space: pre-wrap;
}
.admin-aan-preview.is-text-pre {
	font-family: Consolas, Menlo, Monaco, "Courier New", monospace;
	white-space: pre-wrap;
}
.admin-aan-preview.is-markdown {
	font-family: "IBM Plex Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
	white-space: normal;
	line-height: 1.45;
}
.admin-aan-preview.is-markdown p { margin: 0 0 0.7em; }
.admin-aan-preview.is-markdown h2,
.admin-aan-preview.is-markdown h3,
.admin-aan-preview.is-markdown h4,
.admin-aan-preview.is-markdown h5,
.admin-aan-preview.is-markdown h6 {
	margin: 0.8em 0 0.35em;
	line-height: 1.25;
	text-align: left;
}
.admin-aan-preview.is-markdown h2 { font-size: 24px; }
.admin-aan-preview.is-markdown h3 { font-size: 20px; }
.admin-aan-preview.is-markdown h4 { font-size: 18px; }
.admin-aan-preview.is-markdown h5 { font-size: 17px; }
.admin-aan-preview.is-markdown h6 { font-size: 16px; }
.admin-aan-preview.is-markdown ul,
.admin-aan-preview.is-markdown ol {
	margin: 0 0 0.7em 1.3em;
	padding: 0;
}
.admin-aan-preview.is-markdown code,
.admin-aan-preview.is-markdown pre {
	font-family: Consolas, Menlo, Monaco, monospace;
}
.admin-aan-preview.is-markdown pre {
	white-space: pre-wrap;
}
.admin-aan-preview:focus {
	max-height: 70vh;
	border-color: #A41E00;
	box-shadow: 0 0 0 1px #A41E00;
}
.admin-aan-narrow {
	width: auto !important;
	max-width: none;
}
</style>
{/literal}

<table class="admin-aan-layout" cellpadding="6" cellspacing="0">
<tr>
<td class="admin-aan-main-cell">
{if !$press}
<div style="font: 12px Verdana; color:#666">Выберите издание справа.</div>
{elseif !$issue}
<div style="font: 12px Verdana; color:#666">У издания нет выпусков. <a href="admin_issue.php?id={$press.id}">Создать выпуск</a></div>
{else}

<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $article && $article.id}Редактирование статьи #{$article.id}{else}Новая статья{/if}
<span class="admin-aan-muted"> — выпуск № {$issue.title}</span>
</div>

<form class="admin-aan-form" method="post" action="admin_articles_new.php?id={$press.id}&amp;issue={$issue_id}&amp;aid={$aid}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="press_id" value="{$press.id}">
<input type="hidden" name="issue_id" value="{$issue_id}">
<input type="hidden" name="article_id" value="{$aid}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td>Заголовок (RU) *</td>
<td><textarea name="title" rows="3">{if $article}{$article.title}{/if}</textarea></td>
</tr>
<tr>
<td>Заголовок (EN)</td>
<td><textarea name="title_eng" rows="3">{if $article}{$article.title_eng}{/if}</textarea></td>
</tr>
<tr>
<td>Slug (RU)</td>
<td>
<input type="text" name="slug_ru" maxlength="255" pattern="[a-z0-9-]*" value="{if $article}{$article.slug_ru}{/if}">
<div class="admin-aan-field-hint">Пусто = из заголовка. Только a-z, 0-9 и дефис.</div>
</td>
</tr>
<tr>
<td>Slug (EN)</td>
<td>
<input type="text" name="slug_en" maxlength="255" pattern="[a-z0-9-]*" value="{if $article}{$article.slug_en}{/if}">
</td>
</tr>
<tr>
<td>Формат текста (RU)</td>
<td>
{assign var=tt_ru value=$text_type_html_pre}
{if $article}{assign var=tt_ru value=$article.text_type_ru_ui}{/if}
<select class="admin-aan-narrow" id="admin-aan-text-type-ru" name="text_type_ru" style="width:240px">
<option value="{$text_type_text_pre}"{if $tt_ru eq $text_type_text_pre} selected{/if}>text pre</option>
<option value="{$text_type_html_pre}"{if $tt_ru eq $text_type_html_pre} selected{/if}>html pre (по умолчанию)</option>
<option value="{$text_type_markdown}"{if $tt_ru eq $text_type_markdown} selected{/if}>markdown</option>
</select>
</td>
</tr>
<tr>
<td>Формат текста (EN)</td>
<td>
{assign var=tt_en value=$text_type_html_pre}
{if $article}{assign var=tt_en value=$article.text_type_en_ui}{/if}
<select class="admin-aan-narrow" id="admin-aan-text-type-en" name="text_type_en" style="width:240px">
<option value="{$text_type_text_pre}"{if $tt_en eq $text_type_text_pre} selected{/if}>text pre</option>
<option value="{$text_type_html_pre}"{if $tt_en eq $text_type_html_pre} selected{/if}>html pre (по умолчанию)</option>
<option value="{$text_type_markdown}"{if $tt_en eq $text_type_markdown} selected{/if}>markdown</option>
</select>
<div class="admin-aan-field-hint">RU и EN могут отличаться: text pre / html pre / markdown.</div>
</td>
</tr>
<tr>
<td valign="top">Meta description (RU)</td>
<td><textarea name="meta_description_ru" rows="3" maxlength="512">{if $article}{$article.meta_description_ru}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Meta description (EN)</td>
<td><textarea name="meta_description_en" rows="3" maxlength="512">{if $article}{$article.meta_description_en}{/if}</textarea></td>
</tr>
<tr>
<td valign="top">Текст (RU)</td>
<td>
<textarea class="admin-aan-mono" id="admin-aan-text-ru" name="text_ru" rows="16">{if $article}{$article.text_ru nofilter}{/if}</textarea>
<div class="admin-aan-preview-wrap">
<div class="admin-aan-field-hint">Превью (фокус — развернуть, без фокуса — 5 строк)</div>
<div id="admin-aan-preview-ru" class="admin-aan-preview" tabindex="0" aria-label="Превью текста RU"></div>
</div>
</td>
</tr>
<tr>
<td valign="top">Текст (EN)</td>
<td>
<textarea class="admin-aan-mono" id="admin-aan-text-en" name="text_en" rows="10">{if $article}{$article.text_en nofilter}{/if}</textarea>
<div class="admin-aan-preview-wrap">
<div class="admin-aan-field-hint">Превью (фокус — развернуть, без фокуса — 5 строк)</div>
<div id="admin-aan-preview-en" class="admin-aan-preview" tabindex="0" aria-label="Превью текста EN"></div>
</div>
</td>
</tr>

{if $article && $article.id}
<tr>
<td valign="top">Теги</td>
<td>
{if $article_tags && $article_tags|@count gt 0}
{section name=t loop=$article_tags}
<span class="admin-aan-tag">
{$article_tags[t].tag_name}
<label style="margin-left:4px;color:#A41E00;cursor:pointer"><input type="checkbox" name="delete_tag[]" value="{$article_tags[t].ta_id}"> ×</label>
</span>
{/section}
<br>
{/if}
<label for="admin-aan-add-tag">Добавить тег</label>
<select class="admin-aan-narrow" id="admin-aan-add-tag" name="add_tag_id" style="width:260px;height:22px;margin-right:8px">
<option value="0">---</option>
{section name=tg loop=$all_tags}
<option value="{$all_tags[tg].id}">{$all_tags[tg].tag_name}</option>
{/section}
</select>
<br>
<label for="admin-aan-new-tag">Или новый тег</label>
<input class="admin-aan-narrow" id="admin-aan-new-tag" type="text" name="new_tag" style="width:260px;margin-top:4px" maxlength="128">
</td>
</tr>
{/if}

<tr>
<td>Номер в выпуске</td>
<td><input class="admin-aan-narrow" type="number" name="number" style="width:80px" min="1" value="{if $article}{$article.number}{else}{$next_number}{/if}"></td>
</tr>
<tr>
<td>Скрыта</td>
<td><label><input type="checkbox" name="temp" value="1"{if $article && $article.temp} checked{/if}> не показывать в публичном каталоге</label></td>
</tr>

<tr>
<td></td>
<td style="padding-top:10px">
<input type="submit" name="save" value="Сохранить" style="font: bold 14px Verdana; padding: 4px 14px">
{if $article && $article.id}
&nbsp;&nbsp;
<button type="submit" name="delete" value="1" style="font:12px Verdana;color:#A41E00" onclick="return confirm('Удалить статью #{$article.id}?');">Удалить</button>
{/if}
</td>
</tr>
</table>
</form>

{literal}
<script src="/js/marked.min.js"></script>
<script>
(function () {
	var TYPE_TEXT = '1';
	var TYPE_HTML = '2';
	var TYPE_MD = '3';

	function getMarkedParse() {
		var m = window.marked;
		if (!m) return null;
		if (typeof m.parse === 'function') return m.parse.bind(m);
		if (m.marked && typeof m.marked.parse === 'function') return m.marked.parse.bind(m.marked);
		if (typeof m === 'function') return m;
		return null;
	}

	function demoteMarkdownHeadings(html) {
		var out = String(html || '');
		for (var level = 5; level >= 1; level--) {
			var next = level + 1;
			var reOpen = new RegExp('<h' + level + '\\b([^>]*)>', 'gi');
			var reClose = new RegExp('</h' + level + '>', 'gi');
			out = out.replace(reOpen, '<h' + next + '$1>').replace(reClose, '</h' + next + '>');
		}
		return out;
	}

	function renderMarkdown(src) {
		var parse = getMarkedParse();
		if (!parse) {
			return null;
		}
		try {
			if (typeof marked.setOptions === 'function') {
				marked.setOptions({ breaks: true, gfm: true });
			} else if (marked.marked && typeof marked.marked.setOptions === 'function') {
				marked.marked.setOptions({ breaks: true, gfm: true });
			}
			var out = parse(src);
			// marked async safety
			if (out && typeof out.then === 'function') {
				return null;
			}
			return demoteMarkdownHeadings(String(out));
		} catch (err) {
			if (window.console && console.warn) {
				console.warn('[admin_articles_new] marked failed', err);
			}
			return null;
		}
	}

	function renderPreview(text, type, el) {
		if (!el) return;
		var t = String(text || '');
		el.classList.remove('is-html', 'is-html-pre', 'is-text-pre', 'is-markdown');
		if (type === TYPE_MD) {
			el.classList.add('is-markdown');
			var html = renderMarkdown(t);
			if (html !== null) {
				el.innerHTML = html;
			} else {
				el.textContent = t;
			}
			return;
		}
		if (type === TYPE_TEXT) {
			el.classList.add('is-text-pre');
			el.textContent = t;
			return;
		}
		// html pre (default)
		el.classList.add('is-html-pre');
		el.innerHTML = t;
	}

	function bindPair(textId, previewId, typeSelId) {
		var ta = document.getElementById(textId);
		var prev = document.getElementById(previewId);
		var typeSel = document.getElementById(typeSelId);
		if (!ta || !prev) return;

		function update() {
			var type = typeSel ? String(typeSel.value) : TYPE_HTML;
			renderPreview(ta.value, type, prev);
		}

		function updateSoon() {
			// paste/cut apply value after the event in some browsers
			setTimeout(update, 0);
		}

		ta.addEventListener('input', update);
		ta.addEventListener('change', update);
		ta.addEventListener('keyup', update);
		ta.addEventListener('paste', updateSoon);
		ta.addEventListener('cut', updateSoon);
		if (typeSel) {
			typeSel.addEventListener('change', update);
			typeSel.addEventListener('input', update);
		}
		update();
	}

	function boot() {
		bindPair('admin-aan-text-ru', 'admin-aan-preview-ru', 'admin-aan-text-type-ru');
		bindPair('admin-aan-text-en', 'admin-aan-preview-en', 'admin-aan-text-type-en');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
</script>
{/literal}

{/if}
</td>

<td class="admin-aan-sidebar-cell">
<div class="admin-aan-sidebar">

{if !$press}
<div style="font: bold 12px Verdana; margin-bottom:6px">Издания</div>
<div class="admin-aan-list-wrap">
{if $press_list && $press_list|@count gt 0}
<ul>
{foreach from=$press_list item=p}
<li>
<a href="admin_articles_new.php?id={$p.id}">{if $p.online_articles}<span style="color:#A41E00">{$p.title}</span> <span class="admin-aan-muted">({$p.online_articles})</span>{else}{$p.title}{/if}</a>
</li>
{/foreach}
</ul>
{else}
<p style="color:#666;margin:0">Изданий нет</p>
{/if}
</div>
{else}
<div style="font: bold 12px Verdana; margin-bottom:6px">
<a href="admin_articles_new.php" style="font-weight:normal;color:#666">← издания</a><br>
{$press.title}
</div>

<form method="get" action="admin_articles_new.php" style="margin-bottom:10px">
<input type="hidden" name="id" value="{$press.id}">
<label for="admin-aan-issue" class="u-sr-only">Выпуск</label>
<select id="admin-aan-issue" name="issue" style="width:100%;max-width:300px;height:24px" onchange="this.form.submit()">
{section name=n loop=$issues}
<option value="{$issues[n].id}"{if $issues[n].id eq $issue_id} selected{/if}>№ {$issues[n].title}</option>
{/section}
</select>
</form>

<div style="font: bold 12px Verdana; margin-bottom:6px">Статьи выпуска</div>
<div class="admin-aan-list-wrap">
{if $articles_list && $articles_list|@count gt 0}
<ul>
{section name=n loop=$articles_list}
<li>
<a class="admin-aan-article-link{if $aid eq $articles_list[n].id} nav-active{/if}" href="admin_articles_new.php?id={$press.id}&amp;issue={$issue_id}&amp;aid={$articles_list[n].id}" title="{$articles_list[n].title_plain}">{if $articles_list[n].temp}[скрыта] {/if}{$smarty.section.n.iteration}. {$articles_list[n].title_plain}</a>
<span class="admin-aan-move">
<form method="post" action="admin_articles_new.php?id={$press.id}&amp;issue={$issue_id}&amp;aid={$articles_list[n].id}" style="display:inline">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="press_id" value="{$press.id}">
<input type="hidden" name="issue_id" value="{$issue_id}">
<input type="hidden" name="article_id" value="{$articles_list[n].id}">
<button type="submit" name="move" value="up" class="admin-aan-move-btn" title="Выше"{if $smarty.section.n.first} disabled{/if} aria-label="Переместить выше">↑</button>
<button type="submit" name="move" value="down" class="admin-aan-move-btn" title="Ниже"{if $smarty.section.n.last} disabled{/if} aria-label="Переместить ниже">↓</button>
</form>
</span>
</li>
{/section}
</ul>
{else}
<p style="color:#666;margin:0">Статей пока нет</p>
{/if}
</div>
{/if}

</div>
</td>

</tr>
</table>

</div>
</div>

</TD>
</TR>
</TBODY>
</TABLE>

{/if}
