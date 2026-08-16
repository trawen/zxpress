{literal}
<script>
(function () {
	function initSuggest(inputId, dropId) {
		var input = document.getElementById(inputId);
		var drop = document.getElementById(dropId);
		if (!input || !drop) return;
		var timer = null, sel = -1;

		function suggestLng() {
			var p = location.pathname || '';
			return (p === '/en' || p.indexOf('/en/') === 0 || p.indexOf('/eng/') === 0) ? 'eng' : 'ru';
		}

		function initGoButton() {
			var wrap = input.closest('.smn-search-wrap');
			if (!wrap || wrap.querySelector('.smn-search-go')) return;
			var btn = document.createElement('button');
			btn.type = 'submit';
			btn.className = 'smn-search-go';
			btn.textContent = suggestLng() === 'eng' ? 'detailed search' : 'подробный поиск';
			wrap.appendChild(btn);

			function syncGo() {
				var has = !!String(input.value || '').trim();
				btn.hidden = !has;
				wrap.classList.toggle('has-query', has);
			}

			input.addEventListener('input', syncGo);
			input.addEventListener('change', syncGo);
			input.addEventListener('keyup', syncGo);
			syncGo();
		}

		initGoButton();

		function sizeDrop() {
			var rect = drop.getBoundingClientRect();
			// When hidden, measure from the search wrap bottom.
			var top = rect.top;
			if (!top || drop.style.display === 'none') {
				var wrap = input.closest('.smn-search-wrap') || input;
				top = wrap.getBoundingClientRect().bottom;
			}
			var room = Math.floor(window.innerHeight - top - 12);
			if (room < 120) room = 120;
			drop.style.maxHeight = room + 'px';
		}

		function hideDrop(clear) {
			drop.style.display = 'none';
			if (clear) drop.innerHTML = '';
		}

		function showDrop() {
			sizeDrop();
			drop.style.display = 'block';
			sizeDrop();
		}

		function dropItems() {
			return drop.querySelectorAll('.smn-suggest-row');
		}

		function setActiveItem(items, index) {
			for (var i = 0; i < items.length; i++) {
				items[i].classList.toggle('active', i === index);
			}
		}

		function itemLabel(item) {
			if (typeof item === 'string') return item;
			return (item && item.label) ? String(item.label) : '';
		}

		function applyItem(row) {
			var url = row.getAttribute('data-url') || '';
			var label = row.getAttribute('data-label') || row.textContent || '';
			hideDrop(true);
			if (url) {
				location.href = url;
				return;
			}
			input.value = label;
			var form = input.closest('form');
			if (form) form.submit();
		}

		function renderItems(data) {
			drop.innerHTML = '';
			sel = -1;
			if (!data || !data.length) {
				var empty = document.createElement('div');
				empty.className = 'smn-suggest-empty';
				empty.textContent = suggestLng() === 'eng'
					? 'Nothing found, try detailed search'
					: 'Ничего не найдено, попробуйте подробный поиск';
				drop.appendChild(empty);
				showDrop();
				return;
			}
			for (var i = 0; i < data.length; i++) {
				var item = data[i];
				var label = itemLabel(item);
				if (!label) continue;
				var row = document.createElement('div');
				row.className = 'smn-suggest-row';
				row.setAttribute('data-label', label);
				if (item && item.url) row.setAttribute('data-url', String(item.url));
				if (item && item.type) row.setAttribute('data-type', String(item.type));

				var main = document.createElement('div');
				main.className = 'smn-suggest-label';
				if (item && item.label_html) {
					main.innerHTML = String(item.label_html);
				} else {
					main.textContent = label;
				}
				row.appendChild(main);

				if (item && item.meta) {
					var meta = document.createElement('div');
					meta.className = 'smn-suggest-meta';
					meta.textContent = String(item.meta);
					row.appendChild(meta);
				}
				drop.appendChild(row);
			}
			if (!dropItems().length) {
				var emptyFallback = document.createElement('div');
				emptyFallback.className = 'smn-suggest-empty';
				emptyFallback.textContent = suggestLng() === 'eng'
					? 'Nothing found, try detailed search'
					: 'Ничего не найдено, попробуйте подробный поиск';
				drop.appendChild(emptyFallback);
				showDrop();
				return;
			}
			showDrop();
		}

		function blockSuggestContextMenu(e) {
			e.preventDefault();
			e.stopPropagation();
		}

		// Only block context menu on suggestion rows — not on the input
		// (so Inspect / DevTools via right-click still work).
		drop.addEventListener('contextmenu', blockSuggestContextMenu, true);

		input.addEventListener('input', onInput);
		input.addEventListener('keyup', onInput);
		input.addEventListener('contextmenu', function (e) {
			e.preventDefault();
		});
		drop.addEventListener('contextmenu', function (e) {
			e.preventDefault();
		});

		function onInput(e) {
			if (e.keyCode === 38 || e.keyCode === 40 || e.keyCode === 13 || e.keyCode === 27) return;
			clearTimeout(timer);
			var q = input.value;
			if (q.length < 2) { hideDrop(true); return; }
			timer = setTimeout(function () {
				var url = '/suggest.php?q=' + encodeURIComponent(q)
					+ '&ui=new&lng=' + encodeURIComponent(suggestLng());
				fetch(url, { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(renderItems)
					.catch(function () { hideDrop(true); });
			}, 300);
		}

		input.addEventListener('keydown', function (e) {
			var items = dropItems();
			if (!items.length) return;
			if (e.keyCode === 40) {
				sel = Math.min(sel + 1, items.length - 1);
				setActiveItem(items, sel);
				e.preventDefault();
			} else if (e.keyCode === 38) {
				sel = Math.max(sel - 1, 0);
				setActiveItem(items, sel);
				e.preventDefault();
			} else if (e.keyCode === 13 && sel >= 0) {
				e.preventDefault();
				applyItem(items[sel]);
			} else if (e.keyCode === 27) {
				hideDrop(true);
				sel = -1;
			}
		});

		drop.addEventListener('mousedown', function (e) {
			// Only left click opens a suggestion; ignore right/middle.
			if (e.button !== 0) {
				e.preventDefault();
				return;
			}
			var row = e.target.closest('.smn-suggest-row');
			if (!row || !drop.contains(row)) return;
			e.preventDefault();
			applyItem(row);
		});

		input.addEventListener('blur', function () {
			setTimeout(function () { hideDrop(false); }, 200);
		});
		input.addEventListener('focus', function () {
			if (dropItems().length) showDrop();
		});
		window.addEventListener('resize', function () {
			if (drop.style.display === 'block') sizeDrop();
		});
	}

	function initCoverZoom() {
		if (
			document.body.classList.contains('smn-page-letters') ||
			document.body.classList.contains('smn-page-authors')
		) {
			return;
		}
		if (window.matchMedia && window.matchMedia('(max-width: 820px)').matches) {
			return;
		}
		var covers = document.querySelectorAll('.smn-list-cover');
		for (var i = 0; i < covers.length; i++) {
			(function (cover) {
				var img = cover.querySelector('img');
				var item = cover.closest('.smn-list-item');
				if (!img || !item) return;

				function resetZoom() {
					img.style.transform = '';
					cover.classList.remove('is-zoomed');
					item.classList.remove('is-zoomed');
				}

				function applyZoom() {
					var rect = img.getBoundingClientRect();
					var margin = 16;
					var cx = window.innerWidth / 2;
					var cy = window.innerHeight / 2;
					var imgCx = rect.left + rect.width / 2;
					var imgCy = rect.top + rect.height / 2;
					var maxW = window.innerWidth - margin * 2;
					var maxH = window.innerHeight - margin * 2;
					var scale = Math.min(maxW / rect.width, maxH / rect.height);
					var dx = cx - imgCx;
					var dy = cy - imgCy;
					img.style.transform = 'translate(' + dx + 'px, ' + dy + 'px) scale(' + scale + ')';
					cover.classList.add('is-zoomed');
					item.classList.add('is-zoomed');
				}

				cover.addEventListener('mouseenter', applyZoom);
				item.addEventListener('mouseleave', resetZoom);
			})(covers[i]);
		}
	}

	function initThemeToggle() {
		var lang = document.querySelector('.smn-lang');
		if (!lang || lang.querySelector('.smn-theme-toggle')) return;

		var isEng = (document.documentElement.lang || '').toLowerCase().indexOf('en') === 0;

		function currentTheme() {
			return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
		}

		function applyTheme(theme) {
			if (theme === 'dark') {
				document.documentElement.setAttribute('data-theme', 'dark');
			} else {
				document.documentElement.removeAttribute('data-theme');
			}
			try {
				localStorage.setItem('smn-theme', theme);
			} catch (e) {}
			syncButtons();
		}

		function syncButtons() {
			var dark = currentTheme() === 'dark';
			var buttons = document.querySelectorAll('.smn-theme-toggle');
			for (var i = 0; i < buttons.length; i++) {
				buttons[i].setAttribute(
					'aria-label',
					dark
						? (isEng ? 'Switch to light theme' : 'Включить светлую тему')
						: (isEng ? 'Switch to dark theme' : 'Включить тёмную тему')
				);
				buttons[i].setAttribute('aria-pressed', dark ? 'true' : 'false');
			}
		}

		function toggleTheme() {
			applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
		}

		function makeButton() {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'smn-theme-toggle';
			btn.innerHTML =
				'<svg class="smn-theme-icon smn-theme-icon--moon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>' +
				'<svg class="smn-theme-icon smn-theme-icon--sun" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				toggleTheme();
			});
			return btn;
		}

		// Keep .smn-lang as the header grid cell; put the toggle inside it.
		// Wrap rus/eng so flex gap doesn't stretch the slash between languages.
		if (!lang.querySelector('.smn-lang-switch')) {
			var switchWrap = document.createElement('span');
			switchWrap.className = 'smn-lang-switch';
			while (lang.firstChild) {
				switchWrap.appendChild(lang.firstChild);
			}
			lang.appendChild(switchWrap);
		}
		lang.insertBefore(makeButton(), lang.firstChild);
		lang.classList.add('smn-lang--with-theme');
		window.__smnMakeThemeButton = makeButton;
		window.__smnToggleTheme = toggleTheme;
		window.__smnSyncThemeButtons = syncButtons;
		syncButtons();
	}

	function initMobileMenu() {
		var header = document.querySelector('.smn-header');
		var bar = header && header.querySelector('.smn-header-bar');
		var brand = bar && bar.querySelector('.smn-brand');
		var nav = bar && bar.querySelector('.smn-nav');
		var lang = bar && bar.querySelector('.smn-lang');
		if (!header || !bar || !brand || !nav) return;

		var mq = window.matchMedia('(max-width: 820px)');
		var isEng = (document.documentElement.lang || '').toLowerCase().indexOf('en') === 0;

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'smn-menu-toggle';
		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-controls', 'smn-menu-drawer');
		btn.setAttribute('aria-label', isEng ? 'Menu' : 'Меню');
		btn.innerHTML = '<span class="smn-menu-toggle-box" aria-hidden="true"><span></span><span></span><span></span></span>';
		bar.insertBefore(btn, brand);

		var drawer = document.createElement('div');
		drawer.id = 'smn-menu-drawer';
		drawer.className = 'smn-menu-drawer';
		drawer.hidden = true;
		var drawerNav = document.createElement('nav');
		drawerNav.className = 'smn-menu-drawer-nav';
		drawerNav.setAttribute('aria-label', isEng ? 'Sections' : 'Разделы');
		drawer.appendChild(drawerNav);
		header.insertBefore(drawer, header.querySelector('.smn-breadcrumbs') || null);

		function menuFullLabel(el) {
			var href = (el.getAttribute('href') || '').toLowerCase();
			var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
			if (
				href.indexOf('/ezines') !== -1 ||
				text === 'Эл.пресса' ||
				text === 'Электронная пресса' ||
				text === 'Diskmags'
			) {
				return isEng
					? 'Electronic magazines and newspapers'
					: 'Электронные журналы и газеты';
			}
			if (
				href.indexOf('/books') !== -1 ||
				text === 'Книги' ||
				text === 'Books'
			) {
				return isEng ? 'Books and magazines' : 'Книги и журналы';
			}
			if (
				href.indexOf('/snailmail') !== -1 ||
				href.indexOf('/letters') !== -1 ||
				text === 'Письма' ||
				text === 'Letters'
			) {
				return isEng ? 'Paper letters from Spectrumists' : 'Бумажные письма спектрумистов';
			}
			if (href.indexOf('/zxnet') !== -1 || text === 'ZXNet') {
				return isEng
					? 'Fido, ZXNet and SPBZXNet echoes'
					: 'Эхоконференции Fido, ZXNet и SPBZXNet';
			}
			return null;
		}

		function fillDrawer() {
			drawerNav.innerHTML = '';
			var primary = nav.querySelectorAll('.smn-nav-primary .smn-nav-item');
			var galleryHref = null;
			for (var i = 0; i < primary.length; i++) {
				var clone = primary[i].cloneNode(true);
				var full = menuFullLabel(primary[i]);
				if (full) clone.textContent = full;
				drawerNav.appendChild(clone);
			}

			var secondaryLinks = nav.querySelectorAll('.smn-nav-secondary > a');
			for (var g = 0; g < secondaryLinks.length; g++) {
				var sh = (secondaryLinks[g].getAttribute('href') || '').toLowerCase();
				if (sh.indexOf('/gallery') !== -1) {
					galleryHref = secondaryLinks[g].getAttribute('href');
					break;
				}
			}
			if (!galleryHref) {
				galleryHref = isEng ? '/en/gallery' : '/ru/gallery';
			}
			var galleryLink = document.createElement('a');
			galleryLink.href = galleryHref;
			galleryLink.textContent = isEng
				? 'Gallery of electronic magazines and newspapers'
				: 'Галерея электронных журналов и газет';
			if (window.location.pathname.toLowerCase().indexOf('/gallery') !== -1) {
				galleryLink.className = 'is-active';
			}
			drawerNav.appendChild(galleryLink);

			var more = document.createElement('div');
			more.className = 'smn-menu-drawer-more';
			var secondary = nav.querySelectorAll('.smn-nav-secondary > a, .smn-nav-secondary > form');
			for (var j = 0; j < secondary.length; j++) {
				var href = ((secondary[j].getAttribute && secondary[j].getAttribute('href')) || '').toLowerCase();
				if (href.indexOf('/gallery') !== -1) continue;
				more.appendChild(secondary[j].cloneNode(true));
			}
			if (lang) {
				var langBox = document.createElement('div');
				langBox.className = 'smn-menu-drawer-lang';
				if (typeof window.__smnMakeThemeButton === 'function') {
					langBox.appendChild(window.__smnMakeThemeButton());
				}
				var langText = document.createElement('span');
				langText.className = 'smn-menu-drawer-lang-text';
				var switchSrc = lang.querySelector('.smn-lang-switch');
				langText.innerHTML = switchSrc ? switchSrc.innerHTML : lang.innerHTML;
				langBox.appendChild(langText);
				more.appendChild(langBox);
				if (typeof window.__smnSyncThemeButtons === 'function') {
					window.__smnSyncThemeButtons();
				}
			}
			if (more.childNodes.length) {
				drawerNav.appendChild(more);
			}
		}

		function syncDrawerPad() {
			var search = header.querySelector('.smn-search');
			var bottom = Math.max(
				btn.getBoundingClientRect().bottom,
				brand.getBoundingClientRect().bottom,
				search ? search.getBoundingClientRect().bottom : 0
			);
			header.style.setProperty('--smn-menu-pad-top', Math.ceil(bottom + 14) + 'px');
		}

		var closeTimer = null;

		function onGuardScroll(e) {
			if (!header.classList.contains('is-menu-open')) return;
			if (drawer.contains(e.target)) return;
			e.preventDefault();
		}

		function closeMenu() {
			if (!header.classList.contains('is-menu-open')) {
				drawer.hidden = true;
				return;
			}
			header.classList.remove('is-menu-open');
			document.body.classList.remove('smn-menu-open');
			btn.setAttribute('aria-expanded', 'false');
			if (closeTimer) {
				clearTimeout(closeTimer);
				closeTimer = null;
			}
			// Close instantly — no reverse slide.
			drawer.hidden = true;
		}

		function openMenu() {
			if (closeTimer) {
				clearTimeout(closeTimer);
				closeTimer = null;
			}
			fillDrawer();
			syncDrawerPad();
			drawer.hidden = false;
			drawer.scrollTop = 0;
			// Two frames: apply closed transform, then open — otherwise no slide.
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					header.classList.add('is-menu-open');
					document.body.classList.add('smn-menu-open');
				});
			});
			btn.setAttribute('aria-expanded', 'true');
		}

		function toggleMenu(e) {
			e.stopPropagation();
			if (header.classList.contains('is-menu-open')) {
				closeMenu();
			} else {
				openMenu();
			}
		}

		btn.addEventListener('click', toggleMenu);
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') closeMenu();
		});
		document.addEventListener('touchmove', onGuardScroll, { passive: false });
		document.addEventListener('wheel', onGuardScroll, { passive: false });
		window.addEventListener('resize', function () {
			if (header.classList.contains('is-menu-open')) syncDrawerPad();
		});
		function onViewportChange() {
			if (!mq.matches) closeMenu();
		}
		if (mq.addEventListener) {
			mq.addEventListener('change', onViewportChange);
		} else if (mq.addListener) {
			mq.addListener(onViewportChange);
		}
		fillDrawer();
	}

	function initNavMore() {
		var nav = document.querySelector('.smn-nav');
		if (!nav) return;

		var primary = nav.querySelector('.smn-nav-primary');
		var wrap = nav.querySelector('.smn-nav-more-wrap');
		var btn = nav.querySelector('.smn-nav-more-toggle');
		var panel = nav.querySelector('.smn-nav-more');
		var overflow = nav.querySelector('.smn-nav-overflow');
		if (!primary || !wrap || !btn || !panel || !overflow) return;

		var items = Array.prototype.slice.call(primary.querySelectorAll('.smn-nav-item'));
		var gap = 14;
		var frame = null;
		var mqMobile = window.matchMedia('(max-width: 820px)');
		var widthCache = null;
		var lastHideFrom = -1;
		var ignoreRo = false;

		function closeMenu() {
			wrap.classList.remove('is-open');
			btn.setAttribute('aria-expanded', 'false');
			panel.hidden = true;
		}

		function openMenu() {
			wrap.classList.add('is-open');
			btn.setAttribute('aria-expanded', 'true');
			panel.hidden = false;
		}

		function measureGap() {
			var styles = window.getComputedStyle(primary);
			var g = parseFloat(styles.columnGap || styles.gap || '14');
			return isNaN(g) ? 14 : g;
		}

		function resetOverflowDom() {
			for (var i = 0; i < items.length; i++) {
				items[i].classList.remove('is-overflow');
			}
			overflow.innerHTML = '';
			overflow.hidden = true;
		}

		function updateOverflow(remeasure) {
			// Mobile uses the hamburger drawer — skip overflow collapsing.
			if (mqMobile.matches) {
				if (lastHideFrom !== items.length) {
					resetOverflowDom();
					lastHideFrom = items.length;
					widthCache = null;
				}
				closeMenu();
				return;
			}

			if (remeasure || !widthCache || widthCache.length !== items.length) {
				resetOverflowDom();
				gap = measureGap();
				// One layout pass: read all widths together while items are visible.
				var widths = [];
				for (var m = 0; m < items.length; m++) {
					widths.push(items[m].offsetWidth);
				}
				widthCache = widths;
			}

			var available = nav.clientWidth - wrap.offsetWidth - gap;
			if (available < 0) available = 0;

			var used = 0;
			var hideFrom = items.length;
			for (var i = 0; i < widthCache.length; i++) {
				var next = used + (i > 0 ? gap : 0) + widthCache[i];
				if (next > available) {
					hideFrom = i;
					break;
				}
				used = next;
			}

			// Keep at least one primary item visible when possible.
			if (hideFrom === 0 && items.length > 0 && available > 40) {
				hideFrom = 1;
			}

			if (hideFrom === lastHideFrom && overflow.childNodes.length === items.length - hideFrom) {
				return;
			}
			lastHideFrom = hideFrom;

			ignoreRo = true;
			resetOverflowDom();
			for (var j = hideFrom; j < items.length; j++) {
				var src = items[j];
				src.classList.add('is-overflow');
				var clone = src.cloneNode(true);
				clone.classList.remove('is-overflow');
				overflow.appendChild(clone);
			}
			overflow.hidden = overflow.childNodes.length === 0;
			requestAnimationFrame(function () {
				ignoreRo = false;
			});
		}

		function scheduleUpdate(remeasure) {
			if (frame) cancelAnimationFrame(frame);
			frame = requestAnimationFrame(function () {
				frame = null;
				updateOverflow(!!remeasure);
			});
		}

		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			if (wrap.classList.contains('is-open')) {
				closeMenu();
			} else {
				openMenu();
			}
		});

		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) closeMenu();
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') closeMenu();
		});

		window.addEventListener('resize', function () {
			scheduleUpdate(true);
		});
		if (window.ResizeObserver) {
			var bar = nav.closest('.smn-header-bar');
			var ro = new ResizeObserver(function () {
				if (ignoreRo) return;
				scheduleUpdate(true);
			});
			// Observe the bar, not the nav — hiding items changes nav size and caused a loop.
			if (bar) ro.observe(bar);
			else ro.observe(nav);
		}

		scheduleUpdate(true);
	}

	function initTextCollapse(id, options) {
		var el = document.getElementById(id);
		if (!el) return;
		var always = !!(options && options.always);
		var mq = window.matchMedia('(max-width: 820px)');

		function clearCollapse() {
			el.classList.remove('is-expanded', 'is-collapsible');
			el.removeAttribute('role');
			el.removeAttribute('tabindex');
			el.removeAttribute('aria-expanded');
		}

		function applyCollapse(keepOpen) {
			el.classList.add('is-collapsible');
			el.setAttribute('role', 'button');
			el.setAttribute('tabindex', '0');
			if (keepOpen) {
				el.classList.add('is-expanded');
				el.setAttribute('aria-expanded', 'true');
			} else {
				el.classList.remove('is-expanded');
				el.setAttribute('aria-expanded', 'false');
			}
		}

		function syncCollapsed() {
			if (!always && !mq.matches) {
				clearCollapse();
				return;
			}

			var keepOpen = el.classList.contains('is-expanded');

			// Homepage lead is always long — skip expensive scrollHeight of the whole block.
			if (always && el.id === 'smn-lead') {
				applyCollapse(keepOpen);
				return;
			}

			// One layout read of natural height; compare to CSS clamp in em (no second measure).
			el.classList.remove('is-collapsible', 'is-expanded');
			var fullH = el.scrollHeight;
			var fontSize = parseFloat(window.getComputedStyle(el).fontSize) || 16;
			var clampEm = mq.matches ? 7.2 : 5.6;
			if (fullH <= fontSize * clampEm + 4) {
				clearCollapse();
				return;
			}
			applyCollapse(keepOpen);
		}

		function toggle(e) {
			if (e && e.target && e.target.closest && e.target.closest('a')) return;
			if (!el.classList.contains('is-collapsible')) return;
			if (!always && !mq.matches) return;
			var open = !el.classList.contains('is-expanded');
			el.classList.toggle('is-expanded', open);
			el.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		el.addEventListener('click', toggle);
		el.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				toggle();
			}
		});
		if (mq.addEventListener) {
			mq.addEventListener('change', syncCollapsed);
		} else if (mq.addListener) {
			mq.addListener(syncCollapsed);
		}
		syncCollapsed();
	}

	/* TEMP: pin category aside to sticky breadcrumbs level */
	function initCatAsidePin() {
		var aside = document.querySelector('.smn-article-cat-aside');
		var crumbs = document.getElementById('smn-sticky-breadcrumbs');
		if (!aside || !crumbs) return;

		var header = crumbs.closest('.smn-header');

		function measurePinTop() {
			if (!header) return 0;
			// Breadcrumbs level + a little lower, to clear the header fade.
			var pin = Math.round(crumbs.getBoundingClientRect().top - header.getBoundingClientRect().top) + 60;
			return pin < 0 ? 0 : pin;
		}

		function sync() {
			if (window.getComputedStyle(aside).position !== 'sticky') {
				aside.style.top = '';
				aside.style.maxHeight = '';
				return;
			}
			var pinTop = measurePinTop();
			aside.style.top = pinTop + 'px';
			aside.style.maxHeight = 'calc(100vh - ' + pinTop + 'px - 16px)';
		}

		sync();
		window.addEventListener('resize', sync);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initSuggest('input_query_smn', 'suggest-smn');
			initCoverZoom();
			initThemeToggle();
			initMobileMenu();
			initNavMore();
			initTextCollapse('smn-lead', { always: true });
			initTextCollapse('smn-letter-summary');
			initTextCollapse('smn-press-issue-desc');
			initCatAsidePin();
		});
	} else {
		initSuggest('input_query_smn', 'suggest-smn');
		initCoverZoom();
		initThemeToggle();
		initMobileMenu();
		initNavMore();
		initTextCollapse('smn-lead', { always: true });
		initTextCollapse('smn-letter-summary');
		initTextCollapse('smn-press-issue-desc');
		initCatAsidePin();
	}
})();
</script>
{/literal}
