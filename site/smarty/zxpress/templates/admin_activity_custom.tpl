{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<link rel="stylesheet" href="/js/admin_activity_custom_milkdown.bundle.css">
{literal}
<style>
.admin-ai {
	--ai-bg: var(--smn-paper);
	--ai-card: var(--smn-surface);
	--ai-line: var(--smn-line);
	--ai-text: var(--smn-ink);
	--ai-muted: var(--smn-muted);
	--ai-accent: var(--smn-accent);
	font: 13px/1.45 "IBM Plex Sans", Verdana, sans-serif;
	color: var(--ai-text);
}
.admin-ai * { box-sizing: border-box; }
.admin-ai a { color: var(--smn-lead); }
.admin-ai a:hover { color: var(--ai-accent); }
.admin-ai-shell {
	display: grid;
	grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
	min-height: calc(100vh - 80px);
	border: 1px solid var(--ai-line);
	background: var(--ai-bg);
}
.admin-ai-sidebar {
	position: sticky;
	top: 0;
	align-self: start;
	height: calc(100vh - 20px);
	padding: 18px 14px;
	border-right: 1px solid var(--ai-line);
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.admin-ai-sidebar-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}
.admin-ai-sidebar-title {
	margin: 0;
	font-size: 15px;
}
.admin-ai-new {
	font-size: 12px;
	font-weight: 700;
	text-decoration: none;
	white-space: nowrap;
}
.admin-ai-press-list {
	flex: 1 1 auto;
	min-height: 0;
	overflow: auto;
	margin: 0;
	padding: 0 4px 0 0;
	list-style: none;
}
.admin-ai-press-list li { margin: 0 0 3px; }
.admin-ai-press-link {
	display: block;
	padding: 5px 7px;
	color: var(--smn-ink);
	text-decoration: none;
	font-family: var(--smn-sans);
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.02em;
	line-height: 1.35;
}
.admin-ai-press-label {
	display: block;
	position: relative;
	padding: 2px 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--smn-ink);
}
.admin-ai-press-label::after {
	content: "";
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	height: 2px;
	background: var(--smn-accent);
	transform: scaleX(0);
	transform-origin: left;
	transition: transform 0.25s ease;
}
.admin-ai-press-link:hover .admin-ai-press-label,
.admin-ai-press-link.is-active .admin-ai-press-label {
	color: var(--smn-accent);
}
.admin-ai-press-link:hover .admin-ai-press-label::after,
.admin-ai-press-link.is-active .admin-ai-press-label::after {
	transform: scaleX(1);
}
.admin-ai-press-meta {
	display: block;
	margin-top: 2px;
	color: var(--ai-muted);
	font-size: 10px;
	font-weight: 400;
	letter-spacing: normal;
}
.admin-ai-main {
	min-width: 0;
	padding: 20px;
}
.admin-ai-page-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin: 0 0 16px;
}
.admin-ai-page-head h1 {
	margin: 0;
	font-size: 22px;
	line-height: 1.2;
}
.admin-ai-subtitle {
	margin-top: 4px;
	color: var(--ai-muted);
	font-size: 12px;
}
.admin-ai-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}
.admin-ai-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 34px;
	padding: 7px 14px;
	border: 1px solid #81786e;
	border-radius: 4px;
	background: #fff;
	color: var(--ai-text);
	font: 600 13px/1.2 inherit;
	text-decoration: none;
	cursor: pointer;
}
.admin-ai-button:hover { border-color: var(--ai-accent); color: var(--ai-accent); }
.admin-ai-button--primary {
	border-color: var(--ai-accent);
	background: var(--ai-accent);
	color: #fff;
}
.admin-ai-button--primary:hover { color: #fff; filter: brightness(.92); }
.admin-ai-panel {
	margin: 0 0 16px;
	border: 1px solid var(--ai-line);
	border-radius: 6px;
	background: var(--ai-card);
	overflow: hidden;
}
.admin-ai-panel-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 11px 14px;
	border-bottom: 1px solid var(--ai-line);
	background: color-mix(in srgb, var(--smn-paper) 72%, transparent);
}
.admin-ai-panel-head h2 {
	margin: 0;
	font-size: 15px;
}
.admin-ai-panel-body { padding: 14px; }
.admin-ai-alert {
	margin: 0 0 14px;
	padding: 10px 12px;
	border: 1px solid #d4a574;
	background: #fff4e8;
	color: var(--ai-accent);
}
.admin-ai-alert--ok {
	border-color: #93c193;
	background: #e8f7e8;
	color: #2a6a2a;
}
.admin-ai-alert--error {
	border-color: #d88f8f;
	background: #fde8e8;
	color: #A41E00;
}
.admin-ai-alert--warn {
	border-color: #dcc96d;
	background: #fff8d6;
	color: #665500;
}
.admin-ai-field {
	margin: 0 0 14px;
}
.admin-ai-field:last-child { margin-bottom: 0; }
.admin-ai-label {
	display: block;
	margin: 0 0 6px;
	color: var(--ai-muted);
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .04em;
}
.admin-ai input[type="file"] {
	width: 100%;
	min-height: 34px;
	padding: 6px 8px;
	border: 1px solid var(--ai-line);
	border-radius: 4px;
	background: #fff;
	font: inherit;
}
.admin-ai-footer {
	position: sticky;
	bottom: 0;
	display: flex;
	justify-content: flex-end;
	padding: 10px 0 0;
	background: linear-gradient(to bottom, transparent, var(--ai-bg) 35%);
}
.admin-ai-empty {
	padding: 22px;
	color: var(--ai-muted);
	text-align: center;
	font-size: 12px;
}
.custom-activity-editor-wrap {
	box-sizing: border-box;
	width: 100%;
	border: 1px solid #d8d1c4;
	background: #fff;
}
.custom-activity-editor-wrap:focus-within {
	border-color: #a41e00;
	box-shadow: 0 0 0 1px #a41e00;
}
.custom-activity-toolbar {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	padding: 6px 8px;
	border-bottom: 1px solid #e8e2d6;
	background: #f7f3ea;
}
.custom-activity-toolbar-btn {
	appearance: none;
	border: 1px solid #d8d1c4;
	background: #fff;
	color: #333;
	font: bold 12px/1 Verdana, sans-serif;
	min-width: 28px;
	height: 26px;
	padding: 0 8px;
	cursor: pointer;
	border-radius: 3px;
}
.custom-activity-toolbar-btn:hover {
	border-color: #a41e00;
	color: #a41e00;
}
.custom-activity-editor {
	box-sizing: border-box;
	width: 100%;
	min-height: 220px;
	padding: 10px 12px;
	background: #fff;
}
.custom-activity-editor .ProseMirror {
	min-height: 200px;
	outline: none;
	font: 13px/1.6 Verdana, sans-serif;
	color: #333;
	cursor: text;
}
.custom-activity-editor .ProseMirror p { margin: 0 0 8px; }
.custom-activity-editor-hint {
	margin-top: 6px;
	font: 11px Verdana, sans-serif;
	color: #888;
}
.custom-activity-translate-row {
	margin: 0 0 14px;
}
.custom-activity-translate-btn {
	font: 12px Verdana;
	padding: 4px 10px;
	cursor: pointer;
}
.custom-activity-translate-status {
	font: normal 11px Verdana;
	color: #666;
	margin-left: 8px;
}
.custom-activity-translate-status.is-error { color: #A41E00; }
.custom-activity-translate-status.is-ok { color: #2a6a2a; }
.custom-activity-current-image {
	margin: 0 0 10px;
}
.custom-activity-current-image img {
	display: block;
	max-width: 320px;
	height: auto;
	border: 1px solid var(--ai-line);
	background: #1a1814;
}
@media (max-width: 900px) {
	.admin-ai-shell { grid-template-columns: 1fr; }
	.admin-ai-sidebar {
		position: static;
		height: auto;
		max-height: 260px;
		border-right: 0;
		border-bottom: 1px solid var(--ai-line);
	}
	.admin-ai-press-list { max-height: 170px; }
	.admin-ai-main { padding: 14px; }
}
@media (max-width: 600px) {
	.admin-ai-page-head { flex-direction: column; }
	.admin-ai-main { padding: 10px; }
}
</style>
{/literal}

<div class="admin-ai">
<div class="admin-ai-shell">
<aside class="admin-ai-sidebar">
<div class="admin-ai-sidebar-head">
<h2 class="admin-ai-sidebar-title">Записи</h2>
<a class="admin-ai-new" href="admin_activity_custom.php">+ Новая</a>
</div>
<ul class="admin-ai-press-list">
{foreach from=$custom_activity_items item=item}
<li>
<a class="admin-ai-press-link{if $custom_activity_id eq $item.id} is-active{/if}" href="admin_activity_custom.php?id={$item.id}">
<span class="admin-ai-press-label">{$item.title_preview|escape:'html'}</span>
<span class="admin-ai-press-meta">{$item.created_at_display|escape:'html'}</span>
</a>
</li>
{foreachelse}
<li class="admin-ai-empty">Пока пусто</li>
{/foreach}
</ul>
</aside>

<main class="admin-ai-main">
{if !$custom_activity_table_ready}
<div class="admin-ai-alert admin-ai-alert--warn">
Сначала примени миграцию `db/migrate/20260819162000_custom_activity_updates.sql`.
</div>
{/if}

{if $custom_activity_show_created}
<div class="admin-ai-alert admin-ai-alert--ok">
Запись создана и добавлена в `/ru/updates-activity`.
</div>
{/if}

{if $custom_activity_show_saved}
<div class="admin-ai-alert admin-ai-alert--ok">
Изменения сохранены.
</div>
{/if}

{if $custom_activity_errors}
<div class="admin-ai-alert admin-ai-alert--error">
{foreach from=$custom_activity_errors item=err}
<div>{$err}</div>
{/foreach}
</div>
{/if}

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<input type="hidden" name="custom_activity_id" value="{$custom_activity_id|escape:'html'}">

<header class="admin-ai-page-head">
<div>
<h1>Апдейты</h1>
<div class="admin-ai-subtitle">{if $custom_activity_id}Редактирование записи #{$custom_activity_id}{else}Ручная запись в ленту обновлений{/if}</div>
</div>
<div class="admin-ai-actions">
<button class="admin-ai-button admin-ai-button--primary" type="submit" name="save_custom_activity" value="1">Сохранить</button>
</div>
</header>

<section class="admin-ai-panel">
<div class="admin-ai-panel-head"><h2>{if $custom_activity_id}Запись{else}Новая запись{/if}</h2></div>
<div class="admin-ai-panel-body">

<div class="admin-ai-field">
<span class="admin-ai-label">Комментарий RU *</span>
<input type="hidden" name="title_ru" id="custom-activity-title-ru" value="{$custom_activity_form.title_ru|escape:'html'}">
<div id="custom-activity-editor-ru" class="custom-activity-editor"></div>
<div class="custom-activity-editor-hint">Markdown: **жирный**, *курсив*, [ссылка](url)</div>
</div>

<div class="custom-activity-translate-row">
<button type="button" class="custom-activity-translate-btn" id="custom-activity-translate-en">Перевести RU → EN</button>
<span class="custom-activity-translate-status" id="custom-activity-translate-status" aria-live="polite"></span>
</div>

<div class="admin-ai-field">
<span class="admin-ai-label">Комментарий EN *</span>
<input type="hidden" name="title_en" id="custom-activity-title-en" value="{$custom_activity_form.title_en|escape:'html'}">
<div id="custom-activity-editor-en" class="custom-activity-editor"></div>
<div class="custom-activity-editor-hint">Markdown: **bold**, *italic*, [link](url)</div>
</div>

<div class="admin-ai-field">
<span class="admin-ai-label">Картинка{if $custom_activity_id} (необязательно){else} *{/if}</span>
{if $custom_activity_record && $custom_activity_record.image_url}
<div class="custom-activity-current-image">
<img src="{$custom_activity_record.image_url|escape:'html'}" alt="" width="{$custom_activity_record.image_width|escape:'html'}" height="{$custom_activity_record.image_height|escape:'html'}">
</div>
{/if}
<input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
</div>

</div>
</section>

<div class="admin-ai-footer">
<button class="admin-ai-button admin-ai-button--primary" type="submit" name="save_custom_activity" value="1">Сохранить</button>
</div>
</form>
<script src="/js/admin_activity_custom_milkdown.bundle.js"></script>
</main>
</div>
</div>

{/if}
{include file="footer.tpl"}
