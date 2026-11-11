(function (global) {
	'use strict';

	function parseJsonResponse(response) {
		return response.text().then(function (text) {
			var json = null;
			if (text) {
				try {
					json = JSON.parse(text);
				} catch (e) {
					throw new Error(
						'Сервер вернул не JSON (HTTP ' + response.status + '): '
							+ text.slice(0, 120)
					);
				}
			}
			if (!json) {
				throw new Error('Пустой ответ сервера (HTTP ' + response.status + ')');
			}
			return { response: response, json: json };
		});
	}

	function isAuthError(error, json) {
		if (json && json.code === 'auth') return true;
		var msg = (error && error.message) ? error.message : String(error || '');
		return /сессия|csrf|войдите|устарела/i.test(msg);
	}

	function AuthError(message, json) {
		var err = new Error(message || 'Требуется вход');
		err.name = 'AiemAuthError';
		err.json = json || null;
		err.code = 'auth';
		return err;
	}

	function ensureModal() {
		return document.getElementById('aiem-reauth');
	}

	/**
	 * @param {object} cfg emulator config (mutated: csrfToken, username)
	 * @returns {Promise<object>} reauth JSON
	 */
	function prompt(cfg) {
		var dialog = ensureModal();
		if (!dialog || typeof dialog.showModal !== 'function') {
			return Promise.reject(new Error('Нет диалога входа'));
		}
		var form = document.getElementById('aiem-reauth-form');
		var userInput = document.getElementById('aiem-reauth-user');
		var passInput = document.getElementById('aiem-reauth-pass');
		var errNode = document.getElementById('aiem-reauth-err');
		var cancelBtn = document.getElementById('aiem-reauth-cancel');
		var submitBtn = document.getElementById('aiem-reauth-submit');
		var reauthUrl = (cfg && cfg.reauthUrl) || '/admin_issue_emulator.php?action=reauth';

		userInput.value = (cfg && cfg.username) || userInput.value || '';
		passInput.value = '';
		errNode.textContent = '';
		submitBtn.disabled = false;

		return new Promise(function (resolve, reject) {
			var settled = false;

			function cleanup() {
				form.removeEventListener('submit', onSubmit);
				cancelBtn.removeEventListener('click', onCancel);
				dialog.removeEventListener('cancel', onCancel);
			}

			function finish(ok, value) {
				if (settled) return;
				settled = true;
				cleanup();
				if (dialog.open) dialog.close();
				if (ok) resolve(value);
				else reject(value);
			}

			function onCancel(ev) {
				if (ev) ev.preventDefault();
				finish(false, new Error('Вход отменён'));
			}

			function onSubmit(ev) {
				ev.preventDefault();
				var username = (userInput.value || '').trim();
				var password = passInput.value || '';
				if (!username || !password) {
					errNode.textContent = 'Введите логин и пароль';
					return;
				}
				submitBtn.disabled = true;
				errNode.textContent = 'Проверяю…';
				var body = new FormData();
				body.set('username', username);
				body.set('password', password);
				fetch(reauthUrl, {
					method: 'POST',
					body: body,
					credentials: 'same-origin',
					cache: 'no-store'
				})
					.then(parseJsonResponse)
					.then(function (pack) {
						var json = pack.json;
						if (!pack.response.ok || !json.ok) {
							throw new Error(json.error || ('HTTP ' + pack.response.status));
						}
						if (cfg) {
							cfg.csrfToken = json.csrf_token || cfg.csrfToken;
							cfg.username = json.username || username;
						}
						finish(true, json);
					})
					.catch(function (error) {
						submitBtn.disabled = false;
						errNode.textContent = error && error.message ? error.message : String(error);
						passInput.focus();
						passInput.select();
					});
			}

			form.addEventListener('submit', onSubmit);
			cancelBtn.addEventListener('click', onCancel);
			dialog.addEventListener('cancel', onCancel);
			dialog.showModal();
			setTimeout(function () {
				if (userInput.value) {
					passInput.focus();
				} else {
					userInput.focus();
				}
			}, 0);
		});
	}

	global.AiemReauth = {
		parseJsonResponse: parseJsonResponse,
		isAuthError: isAuthError,
		AuthError: AuthError,
		prompt: prompt
	};
})(window);
