(function () {
	'use strict';

	var CANVAS_W = 640;
	var CANVAS_H = 480;

	var configNode = document.getElementById('aiem-config');
	if (!configNode) return;

	var cfg = JSON.parse(configNode.textContent || '{}');
	var canvas = document.getElementById('canvas');
	var diskSelect = document.getElementById('aiem-disk');
	var shotButton = document.getElementById('aiem-shot');
	var uploadButton = document.getElementById('aiem-upload');
	var clearButton = document.getElementById('aiem-clear');
	var focusButton = document.getElementById('aiem-focus');
	var statusNode = document.getElementById('aiem-status');
	var countNode = document.getElementById('aiem-count');
	var shotsNode = document.getElementById('aiem-shots');
	var bootNode = document.getElementById('aiem-boot');
	var ready = false;
	var busy = false;
	var savedCount = 0;
	var shots = [];

	var mirror = {
		canvas: null,
		context: null,
		gl: null,
		ok: false
	};

	var canvasWidthDesc = Object.getOwnPropertyDescriptor(HTMLCanvasElement.prototype, 'width');
	var canvasHeightDesc = Object.getOwnPropertyDescriptor(HTMLCanvasElement.prototype, 'height');

	// USP/emscripten keeps resetting backing store to cssSize×devicePixelRatio (→1280×960).
	function hijackCanvasSize(el) {
		if (!el || el.__aiemSizeLocked || !canvasWidthDesc || !canvasHeightDesc) return;
		el.__aiemSizeLocked = true;
		Object.defineProperty(el, 'width', {
			get: function () { return canvasWidthDesc.get.call(el); },
			set: function () { canvasWidthDesc.set.call(el, CANVAS_W); },
			configurable: true,
			enumerable: true
		});
		Object.defineProperty(el, 'height', {
			get: function () { return canvasHeightDesc.get.call(el); },
			set: function () { canvasHeightDesc.set.call(el, CANVAS_H); },
			configurable: true,
			enumerable: true
		});
		canvasWidthDesc.set.call(el, CANVAS_W);
		canvasHeightDesc.set.call(el, CANVAS_H);
	}

	hijackCanvasSize(canvas);

	function status(text, className) {
		statusNode.textContent = text;
		statusNode.className = className || '';
	}

	function ensureMirror(width, height) {
		if (!mirror.canvas) {
			mirror.canvas = document.createElement('canvas');
			mirror.context = mirror.canvas.getContext('2d', { willReadFrequently: true });
		}
		if (mirror.canvas.width !== width || mirror.canvas.height !== height) {
			mirror.canvas.width = width;
			mirror.canvas.height = height;
		}
	}

	function snapshotGl(gl) {
		try {
			if (gl.getParameter(gl.FRAMEBUFFER_BINDING)) return;
			var width = gl.canvas.width | 0;
			var height = gl.canvas.height | 0;
			if (width < 8 || height < 8) return;

			ensureMirror(width, height);
			var pixels = new Uint8Array(width * height * 4);
			gl.readPixels(0, 0, width, height, gl.RGBA, gl.UNSIGNED_BYTE, pixels);

			var lit = 0;
			for (var i = 0; i < pixels.length; i += 64) {
				if (pixels[i] + pixels[i + 1] + pixels[i + 2] > 20 && ++lit > 4) break;
			}
			if (lit <= 4) return;

			var image = mirror.context.createImageData(width, height);
			var rowSize = width * 4;
			for (var y = 0; y < height; y++) {
				image.data.set(
					pixels.subarray((height - 1 - y) * rowSize, (height - y) * rowSize),
					y * rowSize
				);
			}
			mirror.context.putImageData(image, 0, 0);
			mirror.ok = true;
		} catch (_) {
			// The framebuffer is transient while USP changes video mode.
		}
	}

	function wrapGl(gl) {
		if (!gl || gl.__zxpressScreenshotWrapped) return gl;
		gl.__zxpressScreenshotWrapped = true;
		mirror.gl = gl;
		['drawArrays', 'drawElements', 'drawArraysInstanced', 'drawElementsInstanced', 'blitFramebuffer']
			.forEach(function (name) {
				if (typeof gl[name] !== 'function') return;
				var original = gl[name].bind(gl);
				gl[name] = function () {
					var result = original.apply(gl, arguments);
					snapshotGl(gl);
					return result;
				};
			});
		if (typeof gl.flush === 'function') {
			var flush = gl.flush.bind(gl);
			gl.flush = function () {
				var result = flush();
				snapshotGl(gl);
				return result;
			};
		}
		return gl;
	}

	(function patchWebGlBeforeUspLoads() {
		var original = HTMLCanvasElement.prototype.getContext;
		HTMLCanvasElement.prototype.getContext = function (type, attrs) {
			var normalized = String(type || '').toLowerCase();
			if (normalized.indexOf('webgl') !== -1) {
				attrs = Object.assign({}, attrs || {}, {
					preserveDrawingBuffer: true,
					alpha: true,
					antialias: false
				});
				return wrapGl(original.call(this, type, attrs));
			}
			return original.call(this, type, attrs);
		};
	})();

	function waitFrames(count) {
		return new Promise(function (resolve) {
			function next() {
				count -= 1;
				if (count <= 0) resolve();
				else requestAnimationFrame(next);
			}
			requestAnimationFrame(next);
		});
	}

	function lockCanvasSize() {
		if (!canvas) return;
		canvas.style.width = '';
		canvas.style.height = '';
		if (canvas.width !== CANVAS_W) canvas.width = CANVAS_W;
		if (canvas.height !== CANVAS_H) canvas.height = CANVAS_H;
	}

	function startCanvasWatchdog() {
		(function tick() {
			lockCanvasSize();
			requestAnimationFrame(tick);
		})();
	}

	function applyVideoMode() {
		if (!window.Module || typeof window.Module.ccall !== 'function') return;
		['filtering=off', 'full screen=off', 'zoom=none'].forEach(function (command) {
			try {
				window.Module.ccall('OnCommand', null, ['string'], [command]);
			} catch (_) {}
		});
		lockCanvasSize();
	}

	// /us/index.html runs the public player with zoom=fill screen, and USP keeps
	// its options in IDBFS per origin, so admin inherits them and restores the
	// stored zoom shortly after start. Re-assert zoom=none while that happens.
	function holdVideoMode(durationMs) {
		var until = Date.now() + durationMs;
		(function tick() {
			applyVideoMode();
			if (Date.now() < until) setTimeout(tick, 150);
		})();
	}

	function openDisk() {
		if (!ready || !cfg.disks[diskSelect.selectedIndex]) return;
		var disk = cfg.disks[diskSelect.selectedIndex];
		status('Открываю ' + disk.name + '…', 'busy');
		mirror.ok = false;
		try {
			applyVideoMode();
			window.Module.ccall('OpenFile', null, ['string'], [disk.url]);
			holdVideoMode(2500);
			status('Готово: ' + disk.name, 'ok');
			canvas.focus();
		} catch (error) {
			status(String(error), 'err');
		}
	}

	function cropScreenTo256(source, srcCtx, cropX, cropY, cropW, cropH) {
		var output = document.createElement('canvas');
		output.width = 256;
		output.height = 192;
		var ctx = output.getContext('2d', { willReadFrequently: true });

		// 1:1 — без масштабирования (буфер 320×240).
		if (cropW === 256 && cropH === 192) {
			ctx.drawImage(source, cropX, cropY, 256, 192, 0, 0, 256, 192);
			return output;
		}

		// Nearest-neighbour: один исходный пиксель на выходной, без drawImage blur.
		var srcData = srcCtx.getImageData(cropX, cropY, cropW, cropH);
		var dstData = ctx.createImageData(256, 192);
		for (var y = 0; y < 192; y++) {
			var sy = ((y + 0.5) * cropH / 192 - 0.5) | 0;
			if (sy < 0) sy = 0;
			if (sy >= cropH) sy = cropH - 1;
			for (var x = 0; x < 256; x++) {
				var sx = ((x + 0.5) * cropW / 256 - 0.5) | 0;
				if (sx < 0) sx = 0;
				if (sx >= cropW) sx = cropW - 1;
				var si = (sy * cropW + sx) * 4;
				var di = (y * 256 + x) * 4;
				dstData.data[di] = srcData.data[si];
				dstData.data[di + 1] = srcData.data[si + 1];
				dstData.data[di + 2] = srcData.data[si + 2];
				dstData.data[di + 3] = srcData.data[si + 3];
			}
		}
		ctx.putImageData(dstData, 0, 0);
		return output;
	}

	function framePng() {
		return waitFrames(3).then(function () {
			if (mirror.gl) snapshotGl(mirror.gl);
			if (!mirror.ok || !mirror.canvas || !mirror.context) {
				throw new Error('Кадр WebGL ещё не готов — подождите секунду');
			}

			// TV border scales with framebuffer (32/320, 24/240 → 256×192 screen).
			var sourceWidth = mirror.canvas.width;
			var sourceHeight = mirror.canvas.height;
			var cropX = Math.round(sourceWidth * 32 / 320);
			var cropY = Math.round(sourceHeight * 24 / 240);
			var cropWidth = Math.round(sourceWidth * 256 / 320);
			var cropHeight = Math.round(sourceHeight * 192 / 240);

			var output = cropScreenTo256(
				mirror.canvas,
				mirror.context,
				cropX,
				cropY,
				cropWidth,
				cropHeight
			);

			return new Promise(function (resolve, reject) {
				output.toBlob(function (blob) {
					if (blob) resolve(blob);
					else reject(new Error('Не удалось создать PNG'));
				}, 'image/png');
			});
		});
	}

	function refreshQueueUi() {
		uploadButton.disabled = busy || shots.length === 0;
		clearButton.disabled = busy || shots.length === 0;
		countNode.textContent = shots.length
			? 'В очереди: ' + shots.length + ' · загружено: ' + savedCount
			: 'Очередь пуста · загружено: ' + savedCount;
	}

	function dropShot(shot) {
		var at = shots.indexOf(shot);
		if (at === -1) return;
		shots.splice(at, 1);
		URL.revokeObjectURL(shot.previewUrl);
		shot.node.remove();
		refreshQueueUi();
	}

	function addShot(blob) {
		var shot = {
			blob: blob,
			previewUrl: URL.createObjectURL(blob),
			node: document.createElement('figure')
		};

		shot.node.className = 'aiem-shot';
		var image = document.createElement('img');
		image.src = shot.previewUrl;
		image.width = 128;
		image.height = 96;
		image.alt = 'Кадр';

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'aiem-shot-del';
		remove.textContent = 'Удалить';
		remove.addEventListener('click', function () {
			if (!busy) dropShot(shot);
		});

		shot.node.appendChild(image);
		shot.node.appendChild(remove);
		shotsNode.appendChild(shot.node);
		shots.push(shot);
		refreshQueueUi();
		shot.node.scrollIntoView({ block: 'nearest' });

		return shot;
	}

	function takeScreenshot() {
		if (!ready || busy) return;
		busy = true;
		shotButton.disabled = true;
		status('Снимаю кадр…', 'busy');

		framePng()
			.then(function (blob) {
				addShot(blob);
				status('Кадр добавлен в очередь', 'ok');
			})
			.catch(function (error) {
				status(error && error.message ? error.message : String(error), 'err');
			})
			.finally(function () {
				busy = false;
				shotButton.disabled = !ready;
				refreshQueueUi();
			});
	}

	function uploadShot(shot) {
		var body = new FormData();
		body.set('csrf_token', cfg.csrfToken);
		body.set('id', String(cfg.pressId));
		body.set('issue', String(cfg.issueId));
		body.set('screenshot', shot.blob, 'screen.png');

		return fetch(cfg.uploadUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			cache: 'no-store'
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || !json.ok) {
					throw new Error(json.error || ('HTTP ' + response.status));
				}
				return json;
			});
		});
	}

	function uploadQueue() {
		if (busy || shots.length === 0) return;
		busy = true;
		shotButton.disabled = true;
		refreshQueueUi();

		var total = shots.length;
		var sent = 0;

		(function next() {
			if (shots.length === 0) {
				busy = false;
				shotButton.disabled = !ready;
				status('Загружено кадров: ' + sent + ' из ' + total, sent === total ? 'ok' : 'err');
				refreshQueueUi();
				return;
			}

			var shot = shots[0];
			status('Отправляю ' + (sent + 1) + ' из ' + total + '…', 'busy');
			uploadShot(shot)
				.then(function (result) {
					sent += 1;
					savedCount += 1;
					dropShot(shot);
					window.parent.postMessage({
						type: 'zxpress:issue-screenshot-saved',
						id: result.id,
						url: result.url
					}, window.location.origin);
					next();
				})
				.catch(function (error) {
					busy = false;
					shotButton.disabled = !ready;
					status(
						'Ошибка на кадре ' + (sent + 1) + ': '
							+ (error && error.message ? error.message : String(error)),
						'err'
					);
					refreshQueueUi();
				});
		})();
	}

	function clearQueue() {
		if (busy) return;
		while (shots.length) dropShot(shots[0]);
		status('Очередь очищена', '');
	}

	cfg.disks.forEach(function (disk) {
		var option = document.createElement('option');
		option.value = String(disk.id);
		option.textContent = disk.name;
		diskSelect.appendChild(option);
	});
	diskSelect.addEventListener('change', openDisk);
	shotButton.addEventListener('click', takeScreenshot);
	uploadButton.addEventListener('click', uploadQueue);
	clearButton.addEventListener('click', clearQueue);
	focusButton.addEventListener('click', function () { canvas.focus(); });
	window.addEventListener('beforeunload', function (event) {
		if (shots.length === 0) return;
		event.preventDefault();
		event.returnValue = '';
	});
	refreshQueueUi();

	window.Module = {
		preRun: [],
		postRun: [],
		canvas: canvas,
		contextAttributes: {
			preserveDrawingBuffer: true,
			alpha: true,
			antialias: false
		},
		locateFile: function (file) {
			return '/us/' + file;
		},
		onInit: applyVideoMode,
		onReady: function () {
			ready = true;
			bootNode.style.display = 'none';
			shotButton.disabled = false;
			hijackCanvasSize(canvas);
			startCanvasWatchdog();
			holdVideoMode(3000);
			openDisk();
		}
	};

	var script = document.createElement('script');
	script.src = '/us/unreal_speccy_portable.js';
	script.onerror = function () {
		status('Не удалось загрузить USP', 'err');
	};
	document.body.appendChild(script);
})();
