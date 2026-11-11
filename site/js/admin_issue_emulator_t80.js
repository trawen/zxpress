(function () {
	'use strict';

	var configNode = document.getElementById('aiem-config');
	if (!configNode) return;

	var cfg = JSON.parse(configNode.textContent || '{}');
	var mount = document.getElementById('t80-mount');
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
	var emu = null;
	var t80Base = cfg.t80Base || '/t80/';

	// PlusD system tape is fetched as relative api/fetch.php — point it at /t80/api/.
	(function patchFetchForPlusd() {
		var original = window.fetch.bind(window);
		window.fetch = function (input, init) {
			var url = typeof input === 'string' ? input : (input && input.url) || '';
			if (
				url === 'api/fetch.php'
				|| url.indexOf('api/fetch.php?') === 0
				|| url === 'api/local.php'
				|| url.indexOf('api/local.php?') === 0
			) {
				url = t80Base.replace(/\/?$/, '/') + url;
				input = typeof input === 'string' ? url : new Request(url, input);
			}
			return original(input, init);
		};
	})();

	function status(text, className) {
		statusNode.textContent = text;
		statusNode.className = className || '';
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

	function cropTo256(blob) {
		return new Promise(function (resolve, reject) {
			var url = URL.createObjectURL(blob);
			var img = new Image();
			img.onload = function () {
				URL.revokeObjectURL(url);
				var sw = img.naturalWidth || img.width;
				var sh = img.naturalHeight || img.height;
				var out = document.createElement('canvas');
				out.width = 256;
				out.height = 192;
				var ctx = out.getContext('2d');
				ctx.imageSmoothingEnabled = false;

				// Turbo80 screenshotBlob defaults to recScale=2 → 640×480 (bezel)
				// or 512×384 (screen-only). Always downsample to 256×192.
				var sx = 0;
				var sy = 0;
				var cw = sw;
				var ch = sh;
				if (sw > 0 && sh > 0 && sw * 240 === sh * 320) {
					var bezel = sw / 320;
					sx = Math.round(32 * bezel);
					sy = Math.round(24 * bezel);
					cw = Math.round(256 * bezel);
					ch = Math.round(192 * bezel);
				} else if (!(sw > 0 && sh > 0 && sw * 192 === sh * 256)) {
					var scaleX = sw / 320;
					var scaleY = sh / 240;
					if (scaleX >= 1 && Math.abs(scaleX - scaleY) < 0.05) {
						sx = Math.round(32 * scaleX);
						sy = Math.round(24 * scaleY);
						cw = Math.round(256 * scaleX);
						ch = Math.round(192 * scaleY);
					}
				}

				ctx.drawImage(img, sx, sy, cw, ch, 0, 0, 256, 192);
				out.toBlob(function (png) {
					if (png) resolve(png);
					else reject(new Error('Не удалось обрезать кадр'));
				}, 'image/png');
			};
			img.onerror = function () {
				URL.revokeObjectURL(url);
				reject(new Error('Не удалось прочитать PNG Turbo80'));
			};
			img.src = url;
		});
	}

	function openDisk() {
		if (!ready || !emu || !cfg.disks[diskSelect.selectedIndex]) return;
		var disk = cfg.disks[diskSelect.selectedIndex];
		status('Открываю ' + disk.name + '…', 'busy');
		emu.load(disk.url, disk.fileName || disk.name)
			.then(function () {
				status('Готово: ' + disk.name, 'ok');
				if (typeof emu.focus === 'function') emu.focus();
			})
			.catch(function (error) {
				status(error && error.message ? error.message : String(error), 'err');
			});
	}

	function takeScreenshot() {
		if (!ready || !emu || busy) return;
		busy = true;
		shotButton.disabled = true;
		status('Снимаю кадр…', 'busy');
		emu.screenshot()
			.then(function (png) { return cropTo256(png); })
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
	focusButton.addEventListener('click', function () {
		if (emu && typeof emu.focus === 'function') emu.focus();
	});
	window.addEventListener('beforeunload', function (event) {
		if (shots.length === 0) return;
		event.preventDefault();
		event.returnValue = '';
	});
	refreshQueueUi();

	function onReady() {
		ready = true;
		bootNode.style.display = 'none';
		shotButton.disabled = false;
		status('Turbo80 готов', 'ok');
		openDisk();
	}

	function diskNeedsTrdos(disk) {
		var ext = String((disk && disk.ext) || '').toLowerCase();
		return ext === 'trd' || ext === 'scl' || ext === 'fdi' || ext === 'udi';
	}

	function needsTrdos() {
		return (cfg.disks || []).some(diskNeedsTrdos);
	}

	/**
	 * Turbo80 does not bundle TR-DOS ROM (licence). It only auto-fetches
	 * Roms/trdos.rom on localhost AND only if the machine is already Pentagon.
	 * Our admin embed starts as 48k for +D, so seed localStorage ourselves
	 * (same key Turbo80 uses) before boot — works on prod hostnames too.
	 */
	function ensureTrdosRom() {
		if (!needsTrdos()) return Promise.resolve();
		try {
			if (localStorage.getItem('zx-trdos')) return Promise.resolve();
		} catch (e) { /* ignore */ }
		var url = t80Base.replace(/\/?$/, '/') + 'Roms/trdos.rom';
		return fetch(url, { cache: 'force-cache' }).then(function (response) {
			if (!response.ok) throw new Error('HTTP ' + response.status);
			return response.arrayBuffer();
		}).then(function (buf) {
			var bytes = new Uint8Array(buf);
			if (bytes.length !== 16384) {
				throw new Error('trdos.rom must be 16384 bytes, got ' + bytes.length);
			}
			var bin = '';
			for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
			try {
				localStorage.setItem('zx-trdos', btoa(bin));
			} catch (e) {
				throw new Error('localStorage: ' + (e && e.message ? e.message : e));
			}
		}).catch(function (error) {
			status(
				'Нет TR-DOS ROM (' + (error && error.message ? error.message : error)
					+ '). Положи site/t80/Roms/trdos.rom или загрузи вручную в Settings → Peripherals.',
				'err'
			);
		});
	}

	function bootTurbo80() {
		if (typeof window.Turbo80 !== 'function') {
			status('Turbo80 не загрузился', 'err');
			return;
		}
		emu = window.Turbo80(mount, {
			base: t80Base,
			ui: 'min',
			tools: 'none',
			crt: 'off',
			sound: 'off',
			speed: 1,
			turbo: false,
			model: '48k',
			border: '0',
			width: 320,
			height: 240,
			lang: 'en',
			relay: false
		});
		emu.on('ready', onReady);
		emu.on('error', function (data) {
			var msg = (data && data.message) ? data.message : 'Turbo80 error';
			status(msg, 'err');
		});
		emu.on('loaded', function () {
			status('Диск загружен', 'ok');
		});
	}

	var script = document.createElement('script');
	script.src = t80Base.replace(/\/?$/, '/') + 'turbo80.js';
	script.async = true;
	script.onload = function () {
		ensureTrdosRom().then(bootTurbo80);
	};
	script.onerror = function () {
		status('Не удалось загрузить /t80/turbo80.js', 'err');
	};
	document.body.appendChild(script);
})();
