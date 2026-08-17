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

<form method="post" enctype="multipart/form-data" action="admin_letters.php?id={if $letter && $letter.id}{$letter.id}{else}0{/if}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<table style="font: 12px Verdana" cellpadding="4">
<tr>
<td><label for="admin-letter-from">От кого *</label></td>
<td>
<select id="admin-letter-from" name="author_from" style="width:340px">
<option value="0">---</option>
{section name=n loop=$authors}
<option value="{$authors[n].id}" {if $letter && $authors[n].id eq $letter.author_from}selected{/if}>
{$authors[n].nickname}
</option>
{/section}
</select>
</td>
</tr>

<tr>
<td><label for="admin-letter-to">Кому *</label></td>
<td>
<select id="admin-letter-to" name="author_to" style="width:340px">
<option value="0">---</option>
{section name=n loop=$authors}
<option value="{$authors[n].id}" {if $letter && $authors[n].id eq $letter.author_to}selected{/if}>
{$authors[n].nickname}
</option>
{/section}
</select>
</td>
</tr>

<tr>
<td>Заголовок (RU) *</td>
<td><input type="text" name="title_ru" style="width:520px" value="{if $letter}{$letter.title_ru}{/if}"></td>
</tr>

<tr>
<td>Заголовок (EN)</td>
<td><input type="text" name="title_en" style="width:520px" value="{if $letter}{$letter.title_en}{/if}"></td>
</tr>

<tr>
<td>Slug (RU)</td>
<td>
<input type="text" name="slug_ru" style="width:520px" maxlength="191" pattern="[a-z0-9-]*" value="{if $letter}{$letter.slug_ru}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле генерируется из заголовка RU. Разрешены только a-z, 0-9 и дефис.</div>
</td>
</tr>

<tr>
<td>Slug (EN)</td>
<td>
<input type="text" name="slug_en" style="width:520px" maxlength="191" pattern="[a-z0-9-]*" value="{if $letter}{$letter.slug_en}{/if}">
<div style="font-size:11px;font-weight:normal;color:#555">Пустое поле генерируется из заголовка EN (или RU, если EN пуст).</div>
</td>
</tr>

<tr>
<td>Дата</td>
<td><input type="text" name="date" style="width:140px" value="{if $letter}{$letter.date}{/if}" placeholder="дд.мм.гггг"></td>
</tr>

<tr>
<td valign="top">Кратко (RU)</td>
<td><textarea name="summary_ru" rows="4" style="width:520px">{if $letter}{$letter.summary_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Кратко (EN)</td>
<td><textarea name="summary_en" rows="4" style="width:520px">{if $letter}{$letter.summary_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Meta description (RU)</td>
<td><textarea name="meta_description_ru" rows="2" style="width:520px" maxlength="512">{if $letter}{$letter.meta_description_ru}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Meta description (EN)</td>
<td><textarea name="meta_description_en" rows="2" style="width:520px" maxlength="512">{if $letter}{$letter.meta_description_en}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (RU)</td>
<td><textarea name="body_ru" rows="10" style="width:520px">{if $letter}{$letter.body_ru nofilter}{/if}</textarea></td>
</tr>

<tr>
<td valign="top">Текст (EN)</td>
<td><textarea name="body_en" rows="10" style="width:520px">{if $letter}{$letter.body_en nofilter}{/if}</textarea></td>
</tr>

<tr>
<td>Файлы (сканы)</td>
<td>
<input type="file" id="admin-letter-upload" name="upload_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
<div style="font-size:11px;font-weight:normal;margin-top:4px">
После выбора файла откроется окно обрезки. «Применить обрезку» — в форму попадёт уже обрезанный файл;
«Без обрезки» — загрузится целиком. На сервере оригинал сохраняется как WebP 85%, превью — JPEG до 1280px.
</div>
<div id="admin-letter-upload-queue" style="font-size:11px;font-weight:normal;margin-top:8px"></div>
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
<img src="{$images[n].preview_url}" style="max-width:420px; height:auto; border:1px solid #C8C5AC; margin-top:4px">
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
<select name="publish_status" style="width:340px">
<option value="{$letter_status_draft}" {if $cur_status eq $letter_status_draft}selected{/if}>Черновик</option>
<option value="{$letter_status_queued}" {if $cur_status eq $letter_status_queued}selected{/if}>В очереди (автопубликация ≤1/сутки)</option>
<option value="{$letter_status_published}" {if $cur_status eq $letter_status_published}selected{/if}>Опубликовано сейчас</option>
<option value="{$letter_status_deleted}" {if $cur_status eq $letter_status_deleted}selected{/if}>Удалено (корзина)</option>
</select>
<div style="font-size:11px;font-weight:normal;margin-top:4px;color:#555">
Очередь публикуется при заходе на snailmail: не больше одного письма в сутки (таймзона Europe/Moscow).
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
.admin-letter-crop-modal {
	display: none;
	position: fixed;
	z-index: 10000;
	left: 0; top: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.55);
}
.admin-letter-crop-modal.is-open { display: block; }
.admin-letter-crop-dialog {
	position: absolute;
	left: 50%; top: 50%;
	transform: translate(-50%, -50%);
	width: min(920px, 94vw);
	max-height: 92vh;
	background: var(--smn-surface);
	border: 1px solid #C8C5AC;
	padding: 12px;
	box-sizing: border-box;
}
.admin-letter-crop-stage {
	width: 100%;
	height: min(62vh, 520px);
	background: #222;
	overflow: hidden;
}
.admin-letter-crop-stage img {
	display: block;
	max-width: 100%;
}
.admin-letter-crop-actions {
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
</style>
{/literal}

<div id="admin-letter-crop-modal" class="admin-letter-crop-modal" aria-hidden="true">
	<div class="admin-letter-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-letter-crop-title">
		<div id="admin-letter-crop-title" style="font:bold 12px Verdana;margin-bottom:8px">Обрезка скана</div>
		<div class="admin-letter-crop-stage"><img id="admin-letter-crop-img" alt=""></div>
		<div class="admin-letter-crop-actions">
			<button type="button" id="admin-letter-crop-apply">Применить обрезку</button>
			<button type="button" id="admin-letter-crop-skip">Без обрезки</button>
			<button type="button" id="admin-letter-crop-cancel">Отмена</button>
			<span id="admin-letter-crop-hint" style="font:11px Verdana;color:#555"></span>
		</div>
	</div>
</div>

<script src="/js/cropper.min.js"></script>
{literal}
<script type="text/javascript">
(function () {
	var input = document.getElementById('admin-letter-upload');
	var queueEl = document.getElementById('admin-letter-upload-queue');
	var modal = document.getElementById('admin-letter-crop-modal');
	var imgEl = document.getElementById('admin-letter-crop-img');
	var titleEl = document.getElementById('admin-letter-crop-title');
	var hintEl = document.getElementById('admin-letter-crop-hint');
	var applyBtn = document.getElementById('admin-letter-crop-apply');
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

	function clearCropper() {
		if (cropper) {
			cropper.destroy();
			cropper = null;
		}
		imgEl.removeAttribute('src');
	}

	function closeModal() {
		modal.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
		clearCropper();
		activeIndex = -1;
		applyBtn.disabled = false;
		applyBtn.textContent = 'Применить обрезку';
	}

	function syncInputFromUploadFiles() {
		var dt = new DataTransfer();
		for (var i = 0; i < uploadFiles.length; i++) {
			dt.items.add(uploadFiles[i]);
		}
		input.files = dt.files;
	}

	function renderQueue() {
		if (!uploadFiles.length) {
			queueEl.innerHTML = '';
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
	}

	function openCrop(index) {
		if (!sourceFiles[index]) {
			return;
		}
		activeIndex = index;
		clearCropper();
		titleEl.textContent = 'Обрезка: ' + sourceFiles[index].name;
		hintEl.textContent = 'Выделите область и нажмите «Применить обрезку».';
		var reader = new FileReader();
		reader.onload = function () {
			imgEl.onload = function () {
				cropper = new Cropper(imgEl, {
					viewMode: 1,
					autoCropArea: 0.85,
					responsive: true,
					background: false,
					checkOrientation: false,
					guides: true,
					movable: true,
					zoomable: true,
					rotatable: false,
					scalable: false
				});
			};
			imgEl.src = String(reader.result || '');
		};
		reader.readAsDataURL(sourceFiles[index]);
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
	}

	input.addEventListener('change', function () {
		sourceFiles = Array.prototype.slice.call(input.files || [], 0);
		uploadFiles = sourceFiles.slice();
		croppedFlags = [];
		for (var i = 0; i < sourceFiles.length; i++) {
			croppedFlags[i] = false;
		}
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
			var base = (sourceFiles[done] && sourceFiles[done].name) ? sourceFiles[done].name : ('scan-' + done);
			base = base.replace(/\.[^.]+$/, '');
			uploadFiles[done] = new File([blob], base + '-crop.jpg', { type: 'image/jpeg', lastModified: Date.now() });
			croppedFlags[done] = true;
			syncInputFromUploadFiles();
			closeModal();
			renderQueue();
			if (done + 1 < sourceFiles.length && !croppedFlags[done + 1]) {
				openCrop(done + 1);
			}
		}, 'image/jpeg', 0.85);
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
})();
</script>
{/literal}

{/if}

