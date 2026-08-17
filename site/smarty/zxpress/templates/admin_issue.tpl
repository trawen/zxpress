{include file="admin_top.tpl"}
{if $login eq 1 and $username}

{literal}
<style>
.admin-ai {
	--ai-bg: #ebe8d7;
	--ai-card: #f7f5ea;
	--ai-line: #c8c5ac;
	--ai-text: #342c24;
	--ai-muted: #746c62;
	--ai-accent: #a41e00;
	font: 13px/1.45 "IBM Plex Sans", Verdana, sans-serif;
	color: var(--ai-text);
}
.admin-ai * { box-sizing: border-box; }
.admin-ai a { color: #493c2f; }
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
	font-weight: 700;
	text-decoration: none;
	white-space: nowrap;
}
.admin-ai-filter {
	width: 100%;
	padding: 7px 9px;
	border: 1px solid var(--ai-line);
	border-radius: 4px;
	background: #fff;
	font: inherit;
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
.admin-ai-press-row {
	display: flex;
	align-items: center;
	gap: 6px;
	min-width: 0;
}
.admin-ai-press-link {
	display: block;
	flex: 1 1 auto;
	min-width: 0;
	padding: 5px 7px;
	border-radius: 4px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	text-decoration: none;
}
.admin-ai-press-link:hover { background: rgba(255,255,255,.55); }
.admin-ai-press-link.is-active {
	background: #fff;
	color: var(--ai-accent);
	font-weight: 700;
	box-shadow: inset 3px 0 0 var(--ai-accent);
}
.admin-ai-public {
	flex: 0 0 auto;
	color: var(--ai-muted);
	text-decoration: none;
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
	background: rgba(235,232,215,.72);
}
.admin-ai-panel-head h2 {
	margin: 0;
	font-size: 15px;
}
.admin-ai-count {
	color: var(--ai-muted);
	font-size: 12px;
	font-weight: 400;
}
.admin-ai-panel-body { padding: 14px; }
.admin-ai-grid {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: 12px 14px;
}
.admin-ai-field { grid-column: span 4; min-width: 0; }
.admin-ai-field--wide { grid-column: span 6; }
.admin-ai-field--full { grid-column: 1 / -1; }
.admin-ai-field--press-title { grid-column: span 5; }
.admin-ai-field--press-type { grid-column: span 2; }
.admin-ai-field--press-city { grid-column: span 3; }
.admin-ai-field--press-numbers { grid-column: span 2; }
.admin-ai-label {
	display: block;
	margin: 0 0 4px;
	color: var(--ai-muted);
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .04em;
}
.admin-ai input[type="text"],
.admin-ai input[type="number"],
.admin-ai input[type="file"],
.admin-ai select {
	width: 100%;
	min-height: 34px;
	padding: 6px 8px;
	border: 1px solid var(--ai-line);
	border-radius: 4px;
	background: #fff;
	color: var(--ai-text);
	font: inherit;
}
.admin-ai input:focus,
.admin-ai select:focus {
	outline: 2px solid rgba(164,30,0,.18);
	border-color: var(--ai-accent);
}
.admin-ai-help {
	margin-top: 3px;
	color: var(--ai-muted);
	font-size: 11px;
}
.admin-ai-table-wrap { overflow-x: auto; }
.admin-ai-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 12px;
}
.admin-ai-table th {
	padding: 7px 6px;
	border-bottom: 1px solid var(--ai-line);
	color: var(--ai-muted);
	font-size: 10px;
	text-align: left;
	text-transform: uppercase;
	letter-spacing: .04em;
	white-space: nowrap;
}
.admin-ai-table td {
	padding: 7px 6px;
	border-bottom: 1px solid rgba(200,197,172,.7);
	vertical-align: middle;
}
.admin-ai-table tr:last-child td { border-bottom: 0; }
.admin-ai-table input,
.admin-ai-table select { min-width: 80px; }
.admin-ai-order { width: 78px !important; min-width: 78px !important; }
.admin-ai-number { width: 86px !important; min-width: 86px !important; }
.admin-ai-date { width: 108px !important; min-width: 108px !important; }
.admin-ai-row-links {
	display: flex;
	align-items: center;
	gap: 7px;
	white-space: nowrap;
}
.admin-ai-row-links a { text-decoration: none; }
.admin-ai-danger {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	color: #9b2d18;
	font-size: 11px;
	white-space: nowrap;
}
.admin-ai-empty {
	padding: 22px;
	color: var(--ai-muted);
	text-align: center;
}
.admin-ai-add-row {
	display: grid;
	grid-template-columns: minmax(110px, 180px) 110px minmax(108px, 140px) auto;
	align-items: end;
	gap: 10px;
}
.admin-ai-file-name {
	display: block;
	max-width: 260px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 700;
}
.admin-ai-file-meta { color: var(--ai-muted); font-size: 11px; }
.admin-ai-upload-grid {
	display: grid;
	grid-template-columns: minmax(180px, 1.4fr) minmax(130px, 1fr) minmax(100px, .7fr);
	gap: 12px;
}
.admin-ai-alert {
	margin: 0 0 14px;
	padding: 10px 12px;
	border: 1px solid #d4a574;
	background: #fff4e8;
	color: var(--ai-accent);
}
.admin-ai-footer {
	position: sticky;
	bottom: 0;
	display: flex;
	justify-content: flex-end;
	padding: 10px 0 0;
	background: linear-gradient(to bottom, transparent, var(--ai-bg) 35%);
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
	.admin-ai-field,
	.admin-ai-field--wide,
	.admin-ai-field--press-title,
	.admin-ai-field--press-type,
	.admin-ai-field--press-city,
	.admin-ai-field--press-numbers { grid-column: span 6; }
}
@media (max-width: 600px) {
	.admin-ai-page-head { flex-direction: column; }
	.admin-ai-field,
	.admin-ai-field--wide,
	.admin-ai-field--press-title,
	.admin-ai-field--press-type,
	.admin-ai-field--press-city,
	.admin-ai-field--press-numbers { grid-column: 1 / -1; }
	.admin-ai-add-row,
	.admin-ai-upload-grid { grid-template-columns: 1fr; }
	.admin-ai-main { padding: 10px; }
}
</style>
{/literal}

<div class="admin-ai">
	<div class="admin-ai-shell">
		<aside class="admin-ai-sidebar">
			<div class="admin-ai-sidebar-head">
				<h2 class="admin-ai-sidebar-title">Издания</h2>
				<a class="admin-ai-new" href="admin_issue.php?id=0">+ Новое</a>
			</div>
			<input class="admin-ai-filter" id="admin-ai-filter" type="search" placeholder="Найти издание…" autocomplete="off">
			<ul class="admin-ai-press-list" id="admin-ai-press-list">
			{section name=n loop=$press_list}
				<li data-title="{$press_list[n].title}">
					<span class="admin-ai-press-row">
						<a class="admin-ai-press-link{if $press.id eq $press_list[n].id} is-active{/if}" href="admin_issue.php?id={$press_list[n].id}">{$press_list[n].title}</a>
						{if $press_list[n].slug_ru}
						<a class="admin-ai-public" href="/ru/ezines/{$press_list[n].slug_ru|escape:'url'}" target="_blank" rel="noopener" title="Открыть на сайте">↗</a>
						{/if}
					</span>
				</li>
			{/section}
			</ul>
		</aside>

		<main class="admin-ai-main">
			{if $error}
			<div class="admin-ai-alert">{$error}</div>
			{/if}
			<form method="post" enctype="multipart/form-data" action="admin_issue.php?id={if $press.id}{$press.id}{else}0{/if}">
				<input type="hidden" name="csrf_token" value="{$csrf_token}">
				<input type="hidden" name="press_change" id="press_change" value="">

				<header class="admin-ai-page-head">
					<div>
						<h1>{if $press.id}{$press.title}{else}Новое издание{/if}</h1>
						<div class="admin-ai-subtitle">
							{if $press.id}ID {$press.id} · {$issues|@count} выпусков · {$files|@count} файлов{else}Создание электронного издания{/if}
						</div>
					</div>
					<div class="admin-ai-actions">
						{if $press.id && $press.slug_ru}
						<a class="admin-ai-button" href="/ru/ezines/{$press.slug_ru|escape:'url'}" target="_blank" rel="noopener">Открыть ↗</a>
						{/if}
						<button class="admin-ai-button admin-ai-button--primary" type="submit" name="save" value="save">Сохранить</button>
					</div>
				</header>

				<section class="admin-ai-panel">
					<div class="admin-ai-panel-head"><h2>Издание</h2></div>
					<div class="admin-ai-panel-body">
						<div class="admin-ai-grid">
							<label class="admin-ai-field admin-ai-field--press-title">
								<span class="admin-ai-label">Название</span>
								<input type="text" name="title" value="{$press.title}" data-press-change required>
							</label>
							<label class="admin-ai-field admin-ai-field--press-type">
								<span class="admin-ai-label">Тип</span>
								<select name="type" data-press-change>
									<option value="0"{if $press.type eq 0} selected{/if}>Газета</option>
									<option value="1"{if $press.type eq 1} selected{/if}>Журнал</option>
									<option value="2"{if $press.type eq 2} selected{/if}>Отчёт</option>
								</select>
							</label>
							<label class="admin-ai-field admin-ai-field--press-city">
								<span class="admin-ai-label">Город</span>
								<select name="city" data-press-change>
									<option value="0"{if !$press.city} selected{/if}>Неизвестен</option>
									{section name=n loop=$cities}
									<option value="{$cities[n].id}"{if $cities[n].id eq $press.city} selected{/if}>{$cities[n].name}</option>
									{/section}
								</select>
							</label>
							<label class="admin-ai-field admin-ai-field--press-numbers">
								<span class="admin-ai-label">Кол-во выпусков</span>
								<input type="number" min="0" name="numbers" value="{$press.numbers}" data-press-change title="0 — пересчитать по базе при сохранении">
							</label>
							<label class="admin-ai-field admin-ai-field--wide">
								<span class="admin-ai-label">Slug RU</span>
								<input type="text" name="slug_ru" value="{$press.slug_ru}" data-press-change>
							</label>
							<label class="admin-ai-field admin-ai-field--wide">
								<span class="admin-ai-label">Slug EN</span>
								<input type="text" name="slug_en" value="{$press.slug_en}" data-press-change>
							</label>
						</div>
					</div>
				</section>

				{if $press.id}
				<section class="admin-ai-panel">
					<div class="admin-ai-panel-head">
						<h2>Выпуски <span class="admin-ai-count">{$issues|@count}</span></h2>
					</div>
					{if $issues && $issues|@count gt 0}
					<div class="admin-ai-table-wrap">
						<table class="admin-ai-table">
							<thead>
								<tr>
									<th>Номер</th>
									<th>Порядок</th>
									<th>Дата</th>
									<th>Slug RU</th>
									<th>Slug EN</th>
									<th>Ссылки</th>
									<th>Удалить</th>
								</tr>
							</thead>
							<tbody>
							{section name=n loop=$issues}
								<tr>
									<td><input class="admin-ai-number" type="text" name="issue_title_{$issues[n].id}" value="{$issues[n].title}"></td>
									<td><input class="admin-ai-order" type="number" min="0" name="issue_sort_order_{$issues[n].id}" value="{$issues[n].sort_order}"></td>
									<td><input class="admin-ai-date" type="text" name="issue_date_{$issues[n].id}" value="{$issues[n].date_fmt}" placeholder="дд.мм.гггг"></td>
									<td><input type="text" name="issue_slug_ru_{$issues[n].id}" value="{$issues[n].slug_ru}"></td>
									<td><input type="text" name="issue_slug_en_{$issues[n].id}" value="{$issues[n].slug_en}"></td>
									<td>
										<span class="admin-ai-row-links">
											<a href="admin_articles_new.php?id={$press.id}&amp;issue={$issues[n].id}" title="Статьи">статьи</a>
											<a href="admin_screens.php?id={$press.id}&amp;issue={$issues[n].id}#emulator" title="Скриншоты и эмулятор">экраны</a>
											{if $press.slug_ru && $issues[n].slug_ru}<a href="/ru/ezines/{$press.slug_ru|escape:'url'}/{$issues[n].slug_ru|escape:'url'}" target="_blank" rel="noopener" title="Публичная страница">↗</a>{/if}
										</span>
									</td>
									<td>
										<label class="admin-ai-danger">
											<input type="checkbox" value="1" name="issue_delete_{$issues[n].id}">
											удалить
										</label>
									</td>
								</tr>
							{/section}
							</tbody>
						</table>
					</div>
					{else}
					<div class="admin-ai-empty">У издания пока нет выпусков</div>
					{/if}
					<div class="admin-ai-panel-body" style="border-top:1px solid var(--ai-line)">
						<div class="admin-ai-add-row">
							<label>
								<span class="admin-ai-label">Номер</span>
								<input type="text" name="add_issue" placeholder="например, 08">
							</label>
							<label>
								<span class="admin-ai-label">Порядок</span>
								<input type="number" min="0" name="add_issue_sort_order" placeholder="авто">
							</label>
							<label>
								<span class="admin-ai-label">Дата</span>
								<input class="admin-ai-date" type="text" name="add_issue_date" placeholder="дд.мм.гггг">
							</label>
							<div class="admin-ai-help">Пустой порядок = последний + 10</div>
						</div>
					</div>
				</section>

				<section class="admin-ai-panel">
					<div class="admin-ai-panel-head">
						<h2>Файлы <span class="admin-ai-count">{$files|@count}</span></h2>
					</div>
					{if $files && $files|@count gt 0}
					<div class="admin-ai-table-wrap">
						<table class="admin-ai-table">
							<thead>
								<tr>
									<th>Файл</th>
									<th>Комментарий</th>
									<th>Привязка</th>
									<th>Формат</th>
									<th>Удалить</th>
								</tr>
							</thead>
							<tbody>
							{section name=n loop=$files}
								<tr>
									<td>
										<a class="admin-ai-file-name" href="files/{$files[n].name|escape:'url'}">{$files[n].name}</a>
										<span class="admin-ai-file-meta">{$files[n].size} КБ · {$files[n].date}</span>
									</td>
									<td><input type="text" name="file_title_{$files[n][0]}" value="{$files[n].file_title}" data-file-id="{$files[n][0]}"></td>
									<td>
										<select name="issue_file_{$files[n][0]}" data-file-id="{$files[n][0]}">
											<option value="0"{if $files[n].id_issue eq 0} selected{/if}>все выпуски</option>
										{section name=a loop=$issues}
											<option value="{$issues[a].id}"{if $files[n].id_issue eq $issues[a].id} selected{/if}>#{$issues[a].title}</option>
										{/section}
										</select>
									</td>
									<td>
										<select name="file_type_{$files[n][0]}" data-file-id="{$files[n][0]}">
											<option value="0"{if $files[n].type eq 0} selected{/if}>SCL/TRD</option>
											<option value="2"{if $files[n].type eq 2} selected{/if}>FDI</option>
											<option value="3"{if $files[n].type eq 3} selected{/if}>UDI</option>
											<option value="4"{if $files[n].type eq 4} selected{/if}>TD0</option>
											<option value="5"{if $files[n].type eq 5} selected{/if}>TAP</option>
											<option value="6"{if $files[n].type eq 6} selected{/if}>TZX</option>
											<option value="7"{if $files[n].type eq 7} selected{/if}>PDF</option>
											<option value="8"{if $files[n].type eq 8} selected{/if}>TXT</option>
											<option value="9"{if $files[n].type eq 9} selected{/if}>HTML</option>
											<option value="10"{if $files[n].type eq 10} selected{/if}>DEJAVU</option>
										</select>
									</td>
									<td>
										<label class="admin-ai-danger">
											<input type="checkbox" name="file_delete_{$files[n][0]}" data-file-id="{$files[n][0]}">
											удалить
										</label>
										<input type="hidden" name="issue_files_change_{$files[n][0]}" id="issue_files_change_{$files[n][0]}" value="">
									</td>
								</tr>
							{/section}
							</tbody>
						</table>
					</div>
					{else}
					<div class="admin-ai-empty">Файлов пока нет</div>
					{/if}
				</section>

				<section class="admin-ai-panel">
					<div class="admin-ai-panel-head"><h2>Загрузить файл</h2></div>
					<div class="admin-ai-panel-body">
						<div class="admin-ai-upload-grid">
							<label>
								<span class="admin-ai-label">Файл</span>
								<input type="file" name="upload_file" accept=".zip,.rar,.trd,.scl,.udi,.fdi,.tap,.tzx,.td0,.pdf,.txt,.html,.htm,.djvu">
							</label>
							<label>
								<span class="admin-ai-label">Привязать</span>
								<select name="upload_file_issue">
									<option value="0">все выпуски (общее для издания)</option>
								{section name=n loop=$issues}
									<option value="{$issues[n].id}">#{$issues[n].title}</option>
								{/section}
								</select>
							</label>
							<label>
								<span class="admin-ai-label">Формат</span>
								<select name="upload_file_type">
									<option value="0">SCL/TRD</option>
									<option value="2">FDI</option>
									<option value="3">UDI</option>
									<option value="4">TD0</option>
									<option value="5">TAP</option>
									<option value="6">TZX</option>
									<option value="7">PDF</option>
									<option value="8">TXT</option>
									<option value="9">HTML</option>
									<option value="10">DEJAVU</option>
								</select>
							</label>
							<label>
								<span class="admin-ai-label">Комментарий</span>
								<input type="text" name="upload_file_title">
							</label>
							<label>
								<span class="admin-ai-label">Или создать выпуск</span>
								<input type="text" name="upload_file_new_issue" placeholder="номер">
							</label>
							<label>
								<span class="admin-ai-label">Дата выпуска</span>
								<input class="admin-ai-date" type="text" name="upload_file_new_issue_date" placeholder="дд.мм.гггг">
							</label>
							<label>
								<span class="admin-ai-label">Порядок нового выпуска</span>
								<input type="number" min="0" name="upload_file_new_issue_sort_order" placeholder="авто">
							</label>
						</div>
					</div>
				</section>
				{/if}

				<div class="admin-ai-footer">
					<button class="admin-ai-button admin-ai-button--primary" type="submit" name="save" value="save">Сохранить изменения</button>
				</div>
			</form>
		</main>
	</div>
</div>

{literal}
<script>
(function () {
	var filter = document.getElementById('admin-ai-filter');
	var list = document.getElementById('admin-ai-press-list');
	if (filter && list) {
		filter.addEventListener('input', function () {
			var query = this.value.trim().toLowerCase();
			var rows = list.querySelectorAll('li');
			for (var i = 0; i < rows.length; i++) {
				var title = (rows[i].getAttribute('data-title') || '').toLowerCase();
				rows[i].hidden = query !== '' && title.indexOf(query) === -1;
			}
		});
	}

	var pressChange = document.getElementById('press_change');
	var pressFields = document.querySelectorAll('[data-press-change]');
	for (var p = 0; p < pressFields.length; p++) {
		pressFields[p].addEventListener('change', function () {
			if (pressChange) pressChange.value = '1';
		});
	}

	var fileFields = document.querySelectorAll('[data-file-id]');
	for (var f = 0; f < fileFields.length; f++) {
		fileFields[f].addEventListener('change', function () {
			var id = this.getAttribute('data-file-id');
			var marker = document.getElementById('issue_files_change_' + id);
			if (marker) marker.value = '1';
		});
	}
})();
</script>
{/literal}

{/if}
{include file="footer.tpl"}
