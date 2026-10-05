{include file="admin_top.tpl"}
{if $login eq 1 and $username}

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<div style="font: bold 14px Verdana">
<br>

<div style="padding: 10px; border: 1px solid #C8C5AC; background-color: var(--smn-paper)">

<div style="margin-bottom:10px">
<a href="admin_letters.php?id=0" style="font-weight:bold">+ Новое письмо</a>
</div>

{if $error}
<div style="color:#A41E00;margin-bottom:10px">{$error}</div>
{/if}

<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td valign="top" width="360" style="border-right:1px solid #C8C5AC">
<div style="font: bold 12px Verdana; margin-bottom:6px">Письма</div>
<form method="get" action="admin_letters.php" style="margin-bottom:8px">
<label for="admin-letter-status-filter" class="u-sr-only">Фильтр статуса</label>
<select id="admin-letter-status-filter" name="status" style="width:340px;height:22px;margin-bottom:6px" onchange="this.form.submit()">
<option value="all" {if $status_filter eq 'all'}selected{/if}>все статусы</option>
<option value="{$letter_status_draft}" {if $status_filter eq $letter_status_draft}selected{/if}>черновики</option>
<option value="{$letter_status_queued}" {if $status_filter eq $letter_status_queued}selected{/if}>в очереди</option>
<option value="{$letter_status_published}" {if $status_filter eq $letter_status_published}selected{/if}>опубликованные</option>
<option value="{$letter_status_deleted}" {if $status_filter eq $letter_status_deleted}selected{/if}>удалённые</option>
</select>
{if $letter && $letter.id}<input type="hidden" name="id" value="{$letter.id}">{/if}
</form>
<form method="get" action="admin_letters.php">
{if $status_filter neq 'all'}<input type="hidden" name="status" value="{$status_filter}">{/if}
<label for="admin-letter-list" class="u-sr-only">Выбрать письмо</label>
<select id="admin-letter-list" name="id" style="width:340px;height:22px" onchange="this.form.submit()">
<option value="0" {if !$letter || !$letter.id}selected{/if}>— новое письмо —</option>
{section name=n loop=$letters_list}
<option value="{$letters_list[n].id}" {if $letter && $letters_list[n].id eq $letter.id}selected{/if}>
#{$letters_list[n].id} [{$letters_list[n].publish_label}] {$letters_list[n].from_nick} → {$letters_list[n].to_nick}: {$letters_list[n].title_ru}
</option>
{/section}
</select>
</form>
</td>

<td valign="top">
<div style="font: bold 12px Verdana; margin-bottom:6px">
{if $letter && $letter.id}Редактирование письма #{$letter.id}{else}Новое письмо{/if}
</div>

<form class="admin-letter-form" method="post" enctype="multipart/form-data" action="admin_letters.php?id={if $letter && $letter.id}{$letter.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table class="admin-letter-fields" style="font: 12px Verdana" cellpadding="4">
<tr>
<td><label for="admin-letter-from">От кого *</label></td>
<td>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
<select id="admin-letter-from" name="author_from" style="width:auto;min-width:180px;max-width:60%">
<option value="0">---</option>
{section name=n loop=$authors}
<option value="{$authors[n].id}" {if $letter && $authors[n].id eq $letter.author_from}selected{/if}>
{$authors[n].nickname}
</option>
{/section}
</select>
<input type="text" id="admin-letter-from-new" name="author_from_new" class="admin-letter-narrow" value="" placeholder="новый ник" maxlength="100" title="Если автора нет в списке — впиши ник, он создастся при сохранении">
</div>
</td>
</tr>

<tr>
<td><label for="admin-letter-to">Кому *</label></td>
<td>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
<select id="admin-letter-to" name="author_to" style="width:auto;min-width:180px;max-width:60%">
<option value="0">---</option>
{section name=n loop=$authors}
<option value="{$authors[n].id}" {if $letter && $authors[n].id eq $letter.author_to}selected{/if}>
{$authors[n].nickname}
</option>
{/section}
</select>
<input type="text" id="admin-letter-to-new" name="author_to_new" class="admin-letter-narrow" value="" placeholder="новый ник" maxlength="100" title="Если автора нет в списке — впиши ник, он создастся при сохранении">
</div>
</td>
</tr>

<tr>
<td>Заголовок (RU) *</td>
<td><input type="text" name="title_ru" value="{if $letter}{$letter.title_ru}{/if}"></td>
</tr>

<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" value="{if $letter}{$letter.title_en}{/if}"></td>
</tr>

<tr>
<td>Slug (RU)</td>
<td>
<input type="text" name="slug_ru" maxlength="191" pattern="[a-z0-9-]*" value="{if $letter}{$letter.slug_ru}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле генерируется из заголовка RU. Разрешены только a-z, 0-9 и дефис.</div>
</td>
</tr>

<tr>
<td>Slug (EN)</td>
<td>
<input type="text" name="slug_en" maxlength="191" pattern="[a-z0-9-]*" value="{if $letter}{$letter.slug_en}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле генерируется из заголовка EN (или RU, если EN пуст).</div>
</td>
</tr>

<tr>
<td>Дата</td>
<td><input class="admin-letter-narrow" type="text" name="date" value="{if $letter}{$letter.date}{/if}" placeholder="дд.мм.гггг"></td>
</tr>

<tr>
<td valign="top">Кратко (RU)</td>
<td><textarea name="summary_ru" rows="4">{if $letter}{$letter.summary_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Кратко (EN)</td>
<td><textarea name="summary_en" rows="4">{if $letter}{$letter.summary_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Meta description (RU)</td>
<td><textarea name="meta_description_ru" rows="2" maxlength="512">{if $letter}{$letter.meta_description_ru}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Meta description (EN)</td>
<td><textarea name="meta_description_en" rows="2" maxlength="512">{if $letter}{$letter.meta_description_en}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (RU)</td>
<td><textarea name="body_ru" rows="10">{if $letter}{$letter.body_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (EN)</td>
<td><textarea name="body_en" rows="10">{if $letter}{$letter.body_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td>Файлы (сканы)</td>
<td>
<input type="file" id="admin-letter-upload" name="upload_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font-size:11px;font-weight:normal;margin-top:4px">
После выбора файла откроется окно обрезки. «Применить обрезку» — в форму попадёт уже обрезанный файл;
«Без обрезки» — загрузится целиком. Затем «Обработать (AI OCR)» отправит кропы в AI и заполнит поля формы.
При сохранении письма оригинал на сервере — WebP 85%, превью — JPEG до 1280px.
</div>
<div id="admin-letter-upload-queue" style="font-size:11px;font-weight:normal;margin-top:8px"></div>
<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
<button type="button" id="admin-letter-ocr-btn" disabled style="height:26px;cursor:pointer">Обработать (AI OCR)</button>
<span id="admin-letter-ocr-status" style="font-size:11px;color:#555"></span>
</div>
{if $images && $images|@count gt 0}
<div style="font-size:11px;font-weight:normal;margin-top:8px">
<b>Загруженные страницы:</b><br>
{section name=n loop=$images}
<div style="margin-top:4px">
<input type="checkbox" name="delete_image_{$images[n].id}" value="1"> удалить
 — id={$images[n].id}
 sort=<input type="text" name="sort_order_{$images[n].id}" value="{$images[n].sort_order}" style="width:50px">
 format={$images[n].format}
<div style="margin-left:18px">
<a href="{$images[n].original_url}" target="_blank">оригинал</a> —
<a href="{$images[n].preview_url}" target="_blank">превью</a><br>
<img src="{$images[n].preview_url}" style="max-width:100%; height:auto; border:1px solid #C8C5AC; margin-top:4px">
</div>
</div>
{/section}
</div>
{/if}
</td>
</tr>

<tr>
<td>Статус публикации</td>
<td>
{assign var=cur_status value=$letter_status_draft}
{if $letter}{assign var=cur_status value=$letter.publish_status}{/if}
<select name="publish_status">
<option value="{$letter_status_draft}" {if $cur_status eq $letter_status_draft}selected{/if}>Черновик</option>
<option value="{$letter_status_queued}" {if $cur_status eq $letter_status_queued}selected{/if}>В очереди (автопубликация ≤1/сутки)</option>
<option value="{$letter_status_published}" {if $cur_status eq $letter_status_published}selected{/if}>Опубликовано сейчас</option>
<option value="{$letter_status_deleted}" {if $cur_status eq $letter_status_deleted}selected{/if}>Удалено (корзина)</option>
</select>
<div style="font-size:11px;font-weight:normal;margin-top:4px;color:#555">
Очередь публикуется при заходе на snailmail/authors: не больше одного письма за календарные сутки (Europe/Moscow). Ручная «Опубликовано сейчас» тоже засчитывается в этот день.
{if $letter && $letter.queued_at}<br>В очереди с: {$letter.queued_at}{/if}
{if $letter && $letter.published_at}<br>Опубликовано: {$letter.published_at}{/if}
{if $letter && $letter.deleted_at}<br>Удалено: {$letter.deleted_at}{/if}
</div>
</td>
</tr>
</table>

<div style="margin-top:10px">
<input type="submit" name="save" value="Сохранить" style="height:26px">
</div>

</form>

</td>
</tr>
</table>

</div>

</div>

</TD>
</TR>
</TBODY>
</TABLE>

<link rel="stylesheet" href="/js/cropper.min.css">
{literal}
<style type="text/css">
.admin-letter-form {
	width: 100%;
}
.admin-letter-fields {
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
}
.admin-letter-fields td:first-child {
	width: 160px;
	vertical-align: top;
	padding-top: 8px;
	white-space: nowrap;
}
.admin-letter-fields td:last-child {
	width: auto;
}
.admin-letter-form input[type="text"],
.admin-letter-form textarea,
.admin-letter-form select {
	width: 100%;
	max-width: none;
	box-sizing: border-box;
}
.admin-letter-form input.admin-letter-narrow {
	width: 140px;
	max-width: 100%;
}
.admin-letter-form input[type="file"] {
	width: 100%;
	max-width: none;
	box-sizing: border-box;
}
.admin-letter-crop-modal {
	display: none;
	position: fixed;
	z-index: 10000;
	inset: 0;
	background: rgba(0,0,0,0.72);
}
.admin-letter-crop-modal.is-open { display: block; }
body.admin-letter-crop-open { overflow: hidden; }
.admin-letter-crop-dialog {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	max-width: none;
	max-height: none;
	display: flex;
	flex-direction: column;
	background: var(--smn-surface);
	border: 0;
	padding: 10px 12px;
	box-sizing: border-box;
}
.admin-letter-crop-title {
	flex: 0 0 auto;
	font: bold 12px Verdana;
	margin-bottom: 8px;
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}
.admin-letter-crop-sheets {
	display: none;
	align-items: center;
	gap: 6px;
	font: normal 11px Verdana;
	color: #555;
}
.admin-letter-crop-sheets.is-visible { display: inline-flex; }
.admin-letter-crop-sheets button {
	height: 22px;
	min-width: 28px;
	cursor: pointer;
}
.admin-letter-crop-stage {
	flex: 1 1 auto;
	min-height: 0;
	width: 100%;
	height: auto;
	background: #222;
	overflow: hidden;
}
.admin-letter-crop-stage img {
	display: block;
	max-width: 100%;
}
.admin-letter-crop-actions {
	flex: 0 0 auto;
	margin-top: 10px;
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	align-items: center;
}
.admin-letter-crop-actions button {
	height: 26px;
	cursor: pointer;
}
#admin-letter-crop-apply-all { display: none; }
#admin-letter-crop-apply-all.is-visible { display: inline-block; }
.admin-letter-upload-item {
	margin-top: 6px;
	padding: 6px 8px;
	border: 1px solid #C8C5AC;
	background: var(--smn-surface);
}
.admin-letter-upload-item button {
	height: 22px;
	margin-left: 6px;
	cursor: pointer;
}
.admin-letter-upload-status { color: #555; }
.admin-letter-upload-status.is-cropped { color: #2a5a1a; font-weight: bold; }
#admin-letter-ocr-btn:disabled { opacity: 0.55; cursor: default; }
#admin-letter-ocr-status.is-error { color: #A41E00; }
#admin-letter-ocr-status.is-ok { color: #2a5a1a; }
</style>
{/literal}

<div id="admin-letter-crop-modal" class="admin-letter-crop-modal" aria-hidden="true">
	<div class="admin-letter-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-letter-crop-title">
		<div id="admin-letter-crop-title" class="admin-letter-crop-title">
			<span id="admin-letter-crop-title-text">Обрезка скана</span>
			<span id="admin-letter-crop-sheets" class="admin-letter-crop-sheets" aria-live="polite">
				<button type="button" id="admin-letter-crop-sheet-prev" title="Предыдущий лист">◀</button>
				<span id="admin-letter-crop-sheet-label">Лист 1/1</span>
				<button type="button" id="admin-letter-crop-sheet-next" title="Следующий лист">▶</button>
			</span>
		</div>
		<div class="admin-letter-crop-stage"><img id="admin-letter-crop-img" alt=""></div>
		<div class="admin-letter-crop-actions">
			<button type="button" id="admin-letter-crop-apply">Применить обрезку</button>
			<button type="button" id="admin-letter-crop-apply-all">Применить все листы</button>
			<button type="button" id="admin-letter-crop-skip">Без обрезки</button>
			<button type="button" id="admin-letter-crop-cancel">Отмена</button>
			<span id="admin-letter-crop-hint" style="font:11px Verdana;color:#555"></span>
		</div>
	</div>
</div>

<script src="/js/cropper.min.js"></script>
<script src="/js/admin_letter_sheet_detect.js"></script>
{literal}
<script type="text/javascript">
(function () {
	var input = document.getElementById('admin-letter-upload');
	var queueEl = document.getElementById('admin-letter-upload-queue');
	var modal = document.getElementById('admin-letter-crop-modal');
	var imgEl = document.getElementById('admin-letter-crop-img');
	var titleTextEl = document.getElementById('admin-letter-crop-title-text') || document.getElementById('admin-letter-crop-title');
	var hintEl = document.getElementById('admin-letter-crop-hint');
	var applyBtn = document.getElementById('admin-letter-crop-apply');
	var applyAllBtn = document.getElementById('admin-letter-crop-apply-all');
	var sheetBar = document.getElementById('admin-letter-crop-sheets');
	var sheetLabel = document.getElementById('admin-letter-crop-sheet-label');
	var sheetPrevBtn = document.getElementById('admin-letter-crop-sheet-prev');
	var sheetNextBtn = document.getElementById('admin-letter-crop-sheet-next');
	var ocrBtn = document.getElementById('admin-letter-ocr-btn');
	var ocrStatus = document.getElementById('admin-letter-ocr-status');
	var formEl = document.querySelector('.admin-letter-form');
	if (!input || !queueEl || !modal || !imgEl || !applyBtn) {
		return;
	}
	if (typeof Cropper === 'undefined') {
		queueEl.innerHTML = '<div style="color:#A41E00">Cropper.js не загрузился — обрезка недоступна.</div>';
		return;
	}

	// sourceFiles = originals from disk picker; uploadFiles = what the form actually sends
	var sourceFiles = [];
	var uploadFiles = [];
	var croppedFlags = []; // bool per index
	var cropper = null;
	var activeIndex = -1;
	var ocrBusy = false;
	var detectedSheets = []; // [{x,y,width,height}]
	var activeSheet = 0;
	var detectBusy = false;
	var rawCropImage = null; // HTMLImageElement for canvas crops (unwrapped)

	function csrfToken() {
		var el = formEl && formEl.querySelector('[name="csrf_token"]');
		return el ? String(el.value || '') : '';
	}

	function setOcrStatus(msg, kind) {
		if (!ocrStatus) return;
		ocrStatus.textContent = msg || '';
		ocrStatus.classList.remove('is-error', 'is-ok');
		if (kind) ocrStatus.classList.add(kind);
	}

	function syncOcrButton() {
		if (!ocrBtn) return;
		ocrBtn.disabled = ocrBusy || uploadFiles.length === 0;
	}

	function clearCropper() {
		if (cropper) {
			cropper.destroy();
			cropper = null;
		}
		imgEl.removeAttribute('src');
		rawCropImage = null;
		detectedSheets = [];
		activeSheet = 0;
		updateSheetUi();
	}

	function closeModal() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('admin-letter-crop-open');
		clearCropper();
		activeIndex = -1;
		applyBtn.disabled = false;
		applyBtn.textContent = 'Применить обрезку';
		if (applyAllBtn) {
			applyAllBtn.disabled = false;
			applyAllBtn.textContent = 'Применить все листы';
		}
	}

	function updateSheetUi() {
		var n = detectedSheets.length;
		if (sheetBar) {
			if (n > 1) {
				sheetBar.classList.add('is-visible');
			} else {
				sheetBar.classList.remove('is-visible');
			}
		}
		if (sheetLabel) {
			sheetLabel.textContent = n ? ('Лист ' + (activeSheet + 1) + '/' + n) : 'Лист —';
		}
		if (sheetPrevBtn) sheetPrevBtn.disabled = n < 2 || activeSheet <= 0 || detectBusy;
		if (sheetNextBtn) sheetNextBtn.disabled = n < 2 || activeSheet >= n - 1 || detectBusy;
		if (applyAllBtn) {
			if (n > 1) {
				applyAllBtn.classList.add('is-visible');
			} else {
				applyAllBtn.classList.remove('is-visible');
			}
		}
	}

	function selectSheet(index) {
		if (!cropper || !detectedSheets.length) return;
		if (index < 0 || index >= detectedSheets.length) return;
		activeSheet = index;
		var box = detectedSheets[activeSheet];
		cropper.setData({
			x: box.x,
			y: box.y,
			width: box.width,
			height: box.height,
			rotate: 0,
			scaleX: 1,
			scaleY: 1
		});
		updateSheetUi();
		if (hintEl) {
			hintEl.textContent = detectedSheets.length > 1
				? ('Авто-область листа ' + (activeSheet + 1) + '/' + detectedSheets.length + '. Можно подправить вручную.')
				: 'Авто-область письма. Можно подправить вручную.';
		}
	}

	function runSheetDetect(img) {
		detectBusy = true;
		updateSheetUi();
		if (hintEl) hintEl.textContent = 'Ищу листы на чёрном фоне…';
		var detectFn = typeof detectLetterSheetsFromImage === 'function'
			? detectLetterSheetsFromImage
			: null;
		if (!detectFn) {
			detectBusy = false;
			detectedSheets = [];
			updateSheetUi();
			if (hintEl) hintEl.textContent = 'Выделите область и нажмите «Применить обрезку».';
			return;
		}
		detectFn(img).then(function (boxes) {
			detectBusy = false;
			detectedSheets = Array.isArray(boxes) ? boxes : [];
			updateSheetUi();
			if (detectedSheets.length) {
				selectSheet(0);
			} else if (hintEl) {
				hintEl.textContent = 'Листы не найдены — выделите область вручную.';
			}
		}).catch(function () {
			detectBusy = false;
			detectedSheets = [];
			updateSheetUi();
			if (hintEl) hintEl.textContent = 'Автодетект не удался — выделите область вручную.';
		});
	}

	function syncInputFromUploadFiles() {
		var dt = new DataTransfer();
		for (var i = 0; i < uploadFiles.length; i++) {
			dt.items.add(uploadFiles[i]);
		}
		input.files = dt.files;
		syncOcrButton();
	}

	function renderQueue() {
		if (!uploadFiles.length) {
			queueEl.innerHTML = '';
			syncOcrButton();
			return;
		}
		var html = '<b>К загрузке (' + uploadFiles.length + '):</b>';
		for (var i = 0; i < uploadFiles.length; i++) {
			var f = uploadFiles[i];
			var status = croppedFlags[i]
				? ('обрезано, ' + Math.round(f.size / 1024) + ' КБ')
				: ('без обрезки, ' + Math.round(f.size / 1024) + ' КБ');
			var statusClass = croppedFlags[i] ? ' is-cropped' : '';
			html += '<div class="admin-letter-upload-item">'
				+ (i + 1) + '. ' + f.name
				+ ' <span class="admin-letter-upload-status' + statusClass + '">(' + status + ')</span>'
				+ '<button type="button" data-crop-index="' + i + '">Обрезать</button>'
				+ '<button type="button" data-clear-index="' + i + '">Сбросить кроп</button>'
				+ '</div>';
		}
		queueEl.innerHTML = html;
		syncOcrButton();
	}

	function openCrop(index) {
		if (!sourceFiles[index]) {
			return;
		}
		activeIndex = index;
		clearCropper();
		if (titleTextEl) titleTextEl.textContent = 'Обрезка: ' + sourceFiles[index].name;
		if (hintEl) hintEl.textContent = 'Загрузка…';
		var reader = new FileReader();
		reader.onload = function () {
			var url = String(reader.result || '');
			var probe = new Image();
			probe.onload = function () {
				rawCropImage = probe;
				imgEl.onload = function () {
					cropper = new Cropper(imgEl, {
						viewMode: 1,
						autoCropArea: 1,
						responsive: true,
						background: false,
						checkOrientation: false,
						guides: true,
						movable: true,
						zoomable: true,
						rotatable: false,
						scalable: false,
						ready: function () {
							runSheetDetect(probe);
						}
					});
				};
				imgEl.src = url;
			};
			probe.onerror = function () {
				if (hintEl) hintEl.textContent = 'Не удалось загрузить изображение.';
			};
			probe.src = url;
		};
		reader.readAsDataURL(sourceFiles[index]);
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('admin-letter-crop-open');
	}

	function fileBaseName(idx) {
		var base = (sourceFiles[idx] && sourceFiles[idx].name) ? sourceFiles[idx].name : ('scan-' + idx);
		return base.replace(/\.[^.]+$/, '');
	}

	function applyCurrentCrop() {
		if (!cropper || activeIndex < 0) {
			closeModal();
			return;
		}
		var done = activeIndex;
		var data = cropper.getData(true);
		var maxSide = 3500;
		var canvasOpts = {
			imageSmoothingEnabled: true,
			imageSmoothingQuality: 'high'
		};
		var srcW = Math.max(1, Math.round(data.width || 0));
		var srcH = Math.max(1, Math.round(data.height || 0));
		if (srcW >= srcH && srcW > maxSide) {
			canvasOpts.width = maxSide;
		} else if (srcH > maxSide) {
			canvasOpts.height = maxSide;
		}
		var canvas = cropper.getCroppedCanvas(canvasOpts);
		if (!canvas) {
			hintEl.textContent = 'Не удалось получить область обрезки.';
			return;
		}
		applyBtn.disabled = true;
		applyBtn.textContent = 'Обрезаю…';
		canvas.toBlob(function (blob) {
			if (!blob) {
				applyBtn.disabled = false;
				applyBtn.textContent = 'Применить обрезку';
				hintEl.textContent = 'Ошибка создания файла обрезки.';
				return;
			}
			var base = fileBaseName(done);
			uploadFiles[done] = new File([blob], base + '-crop.jpg', { type: 'image/jpeg', lastModified: Date.now() });
			croppedFlags[done] = true;
			syncInputFromUploadFiles();
			closeModal();
			renderQueue();
			if (done + 1 < sourceFiles.length && !croppedFlags[done + 1]) {
				openCrop(done + 1);
			}
		}, 'image/jpeg', 0.85);
	}

	function applyAllSheets() {
		if (!detectedSheets.length || activeIndex < 0) return;
		var cropFn = typeof cropImageRegionToBlob === 'function' ? cropImageRegionToBlob : null;
		var img = rawCropImage || imgEl;
		if (!cropFn || !img) {
			hintEl.textContent = 'Нельзя применить все листы — нет helper/изображения.';
			return;
		}
		var done = activeIndex;
		var sheets = detectedSheets.slice();
		var original = sourceFiles[done];
		applyBtn.disabled = true;
		if (applyAllBtn) {
			applyAllBtn.disabled = true;
			applyAllBtn.textContent = 'Обрезаю…';
		}
		if (hintEl) hintEl.textContent = 'Обрезаю ' + sheets.length + ' лист(а)…';

		var chain = Promise.resolve([]);
		sheets.forEach(function (region, i) {
			chain = chain.then(function (acc) {
				return cropFn(img, region, 3500, 0.85).then(function (blob) {
					var base = fileBaseName(done);
					var suffix = sheets.length > 1 ? ('-p' + (i + 1)) : '';
					acc.push(new File([blob], base + suffix + '-crop.jpg', {
						type: 'image/jpeg',
						lastModified: Date.now()
					}));
					return acc;
				});
			});
		});

		chain.then(function (files) {
			uploadFiles.splice(done, 1);
			croppedFlags.splice(done, 1);
			sourceFiles.splice(done, 1);
			for (var i = 0; i < files.length; i++) {
				uploadFiles.splice(done + i, 0, files[i]);
				croppedFlags.splice(done + i, 0, true);
				sourceFiles.splice(done + i, 0, original);
			}
			syncInputFromUploadFiles();
			closeModal();
			renderQueue();
			var next = done + files.length;
			if (next < sourceFiles.length && !croppedFlags[next]) {
				openCrop(next);
			}
		}).catch(function (err) {
			applyBtn.disabled = false;
			applyBtn.textContent = 'Применить обрезку';
			if (applyAllBtn) {
				applyAllBtn.disabled = false;
				applyAllBtn.textContent = 'Применить все листы';
			}
			if (hintEl) hintEl.textContent = 'Ошибка: ' + (err && err.message ? err.message : err);
		});
	}

	function setField(name, value) {
		if (value === undefined || value === null) return;
		var el = document.querySelector('.admin-letter-form [name="' + name + '"]');
		if (!el) return;
		el.value = String(value);
	}

	function applyOcrResult(data) {
		if (!data || typeof data !== 'object') return;
		setField('title_ru', data.title_ru || '');
		setField('title_en', data.title_en || '');
		setField('summary_ru', data.summary_ru || '');
		setField('summary_en', data.summary_en || '');
		setField('meta_description_ru', data.meta_description_ru || '');
		setField('meta_description_en', data.meta_description_en || '');
		setField('body_ru', data.body_ru || '');
		setField('body_en', data.body_en || '');
		if (data.date) setField('date', data.date);
		var fromSel = document.getElementById('admin-letter-from');
		var toSel = document.getElementById('admin-letter-to');
		var fromNew = document.getElementById('admin-letter-from-new');
		var toNew = document.getElementById('admin-letter-to-new');
		if (data.author_from && fromSel) {
			fromSel.value = String(data.author_from);
			if (fromNew) fromNew.value = '';
		} else if (fromNew && data.from_nick) {
			fromNew.value = String(data.from_nick);
			if (fromSel) fromSel.value = '0';
		}
		if (data.author_to && toSel) {
			toSel.value = String(data.author_to);
			if (toNew) toNew.value = '';
		} else if (toNew && data.to_nick) {
			toNew.value = String(data.to_nick);
			if (toSel) toSel.value = '0';
		}
	}

	function runOcr() {
		if (ocrBusy || !uploadFiles.length) return;
		var token = csrfToken();
		if (!token) {
			setOcrStatus('Нет CSRF-токена — обнови страницу', 'is-error');
			return;
		}
		ocrBusy = true;
		syncOcrButton();
		setOcrStatus('Отправляю ' + uploadFiles.length + ' стр. в AI…', '');
		if (ocrBtn) ocrBtn.textContent = 'Обрабатываю…';

		var fd = new FormData();
		fd.append('action', 'ocr');
		fd.append('csrf_token', token);
		for (var i = 0; i < uploadFiles.length; i++) {
			fd.append('ocr_files[]', uploadFiles[i], uploadFiles[i].name || ('page-' + (i + 1) + '.jpg'));
		}

		var ocrUrl = (formEl && formEl.getAttribute('action')) || 'admin_letters.php';
		fetch(ocrUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin'
		})
			.then(function (r) {
				return r.text().then(function (t) {
					var j = null;
					try { j = JSON.parse(t); } catch (e) { /* plain error */ }
					if (!r.ok || !j || !j.ok) {
						var msg = (j && j.error) ? j.error : '';
						if (!msg) {
							if (r.status === 504 || /upstream timed out/i.test(t)) {
								msg = 'Таймаут OCR (сервер ждал ответа AI слишком долго)';
							} else if (/<!DOCTYPE|<html/i.test(t)) {
								msg = 'HTTP ' + r.status + ' — ответ сервера не JSON (часто таймаут/ошибка nginx)';
							} else {
								msg = (t || ('HTTP ' + r.status)).slice(0, 400);
							}
						}
						throw new Error(msg);
					}
					return j.data;
				});
			})
			.then(function (data) {
				applyOcrResult(data);
				var note = data && data.note ? (' ' + data.note) : '';
				setOcrStatus('Готово — поля формы заполнены.' + note, 'is-ok');
			})
			.catch(function (err) {
				setOcrStatus(String(err && err.message ? err.message : err), 'is-error');
			})
			.finally(function () {
				ocrBusy = false;
				if (ocrBtn) ocrBtn.textContent = 'Обработать (AI OCR)';
				syncOcrButton();
			});
	}

	if (ocrBtn) {
		ocrBtn.addEventListener('click', function (e) {
			e.preventDefault();
			runOcr();
		});
	}

	input.addEventListener('change', function () {
		sourceFiles = Array.prototype.slice.call(input.files || [], 0);
		uploadFiles = sourceFiles.slice();
		croppedFlags = [];
		for (var i = 0; i < sourceFiles.length; i++) {
			croppedFlags[i] = false;
		}
		setOcrStatus('', '');
		renderQueue();
		if (sourceFiles.length) {
			openCrop(0);
		}
	});

	queueEl.addEventListener('click', function (e) {
		var t = e.target;
		if (!(t instanceof HTMLElement)) {
			return;
		}
		if (t.hasAttribute('data-crop-index')) {
			openCrop(parseInt(t.getAttribute('data-crop-index'), 10));
		} else if (t.hasAttribute('data-clear-index')) {
			var idx = parseInt(t.getAttribute('data-clear-index'), 10);
			if (!isNaN(idx) && sourceFiles[idx]) {
				uploadFiles[idx] = sourceFiles[idx];
				croppedFlags[idx] = false;
				syncInputFromUploadFiles();
				renderQueue();
			}
		}
	});

	applyBtn.addEventListener('click', function () {
		applyCurrentCrop();
	});

	if (applyAllBtn) {
		applyAllBtn.addEventListener('click', function () {
			applyAllSheets();
		});
	}

	if (sheetPrevBtn) {
		sheetPrevBtn.addEventListener('click', function () {
			selectSheet(activeSheet - 1);
		});
	}
	if (sheetNextBtn) {
		sheetNextBtn.addEventListener('click', function () {
			selectSheet(activeSheet + 1);
		});
	}

	document.addEventListener('keydown', function (e) {
		if (!modal.classList.contains('is-open')) return;
		if (e.key === 'ArrowLeft') {
			e.preventDefault();
			selectSheet(activeSheet - 1);
		} else if (e.key === 'ArrowRight') {
			e.preventDefault();
			selectSheet(activeSheet + 1);
		}
	});

	document.getElementById('admin-letter-crop-skip').addEventListener('click', function () {
		var next = activeIndex >= 0 ? activeIndex + 1 : -1;
		if (activeIndex >= 0 && sourceFiles[activeIndex]) {
			uploadFiles[activeIndex] = sourceFiles[activeIndex];
			croppedFlags[activeIndex] = false;
			syncInputFromUploadFiles();
		}
		closeModal();
		renderQueue();
		if (next >= 0 && next < sourceFiles.length) {
			openCrop(next);
		}
	});

	document.getElementById('admin-letter-crop-cancel').addEventListener('click', function () {
		closeModal();
	});

	modal.addEventListener('click', function (e) {
		if (e.target === modal) {
			closeModal();
		}
	});

	syncOcrButton();
})();
</script>
{/literal}

{/if}

