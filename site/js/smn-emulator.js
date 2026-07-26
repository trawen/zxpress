(function () {
	function initEmulatorModal() {
		var modal = document.getElementById('smn-emulator-modal');
		var runBtn = document.getElementById('smn-emulator-run');
		var iframe = document.getElementById('smn-emulator-iframe');
		var loading = document.getElementById('smn-emulator-loading');

		if (!modal || !runBtn || !iframe) {
			return;
		}

		var filePath = runBtn.getAttribute('data-url') || '';

		function openModal() {
			if (!filePath || modal.classList.contains('is-open')) return;
			
			runBtn.blur(); // Убираем фокус с кнопки, чтобы клавиши Enter/Space не нажимали её повторно
			
			modal.classList.add('is-open');
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('smn-modal-open');
			
			if (loading) {
				loading.hidden = false;
				loading.style.setProperty('display', 'flex', 'important');
			}

			// Загружаем эмулятор во фрейм
			iframe.src = '/us/index.html?file=' + encodeURIComponent(filePath);
			iframe.style.display = 'block';
		}

		function closeModal() {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('smn-modal-open');
			
			// ПОЛНОСТЬЮ УБИВАЕМ ЭМУЛЯТОР: очищаем src
			iframe.src = 'about:blank';
			iframe.style.display = 'none';
		}

		iframe.onload = function() {
			// Когда фрейм загрузился (или переключился на about:blank), скрываем лоадер
			if (iframe.src.indexOf('about:blank') === -1 && loading) {
				loading.hidden = true;
				loading.style.setProperty('display', 'none', 'important');
			}
		};

		runBtn.addEventListener('click', function (e) {
			e.preventDefault();
			openModal();
		});

		modal.addEventListener('click', function (e) {
			if (e.target.closest('[data-smn-emulator-close]')) {
				closeModal();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.classList.contains('is-open')) {
				closeModal();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initEmulatorModal);
	} else {
		initEmulatorModal();
	}
})();
