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
			if (!data || !data.length) { hideDrop(true); return; }
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
			if (!dropItems().length) { hideDrop(true); return; }
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

		function updateOverflow() {
			items.forEach(function (item) {
				item.classList.remove('is-overflow');
			});
			overflow.innerHTML = '';
			overflow.hidden = true;
			gap = measureGap();

			var available = nav.clientWidth - wrap.offsetWidth - gap;
			if (available < 0) available = 0;

			var used = 0;
			var hideFrom = items.length;

			for (var i = 0; i < items.length; i++) {
				var w = items[i].offsetWidth;
				var next = used + (i > 0 ? gap : 0) + w;
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

			for (var j = hideFrom; j < items.length; j++) {
				var src = items[j];
				src.classList.add('is-overflow');
				var clone = src.cloneNode(true);
				clone.classList.remove('is-overflow');
				overflow.appendChild(clone);
			}

			overflow.hidden = overflow.childNodes.length === 0;
		}

		function scheduleUpdate() {
			if (frame) cancelAnimationFrame(frame);
			frame = requestAnimationFrame(function () {
				frame = null;
				updateOverflow();
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

		window.addEventListener('resize', scheduleUpdate);
		if (window.ResizeObserver) {
			var ro = new ResizeObserver(scheduleUpdate);
			ro.observe(nav);
			var bar = nav.closest('.smn-header-bar');
			if (bar) ro.observe(bar);
		}

		scheduleUpdate();
	}

	function initTextCollapse(id) {
		var el = document.getElementById(id);
		if (!el) return;
		var mq = window.matchMedia('(max-width: 820px)');

		function syncCollapsed() {
			if (!mq.matches) {
				el.classList.remove('is-expanded', 'is-collapsible');
				el.removeAttribute('role');
				el.removeAttribute('tabindex');
				el.removeAttribute('aria-expanded');
				return;
			}
			el.classList.add('is-collapsible');
			el.setAttribute('role', 'button');
			el.setAttribute('tabindex', '0');
			if (!el.classList.contains('is-expanded')) {
				el.setAttribute('aria-expanded', 'false');
			}
		}

		function toggle() {
			if (!mq.matches) return;
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

	/* Mobile: drop sticky header top padding on scroll */
	function initBrandCompact() {
		var header = document.querySelector('.smn-header');
		if (!header) return;
		var mq = window.matchMedia('(max-width: 820px)');
		var ticking = false;

		function sync() {
			if (!mq.matches) {
				header.classList.remove('is-compact');
				return;
			}
			header.classList.toggle('is-compact', window.scrollY > 8);
		}

		function onScroll() {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(function () {
				ticking = false;
				sync();
			});
		}

		sync();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', sync);
		if (mq.addEventListener) {
			mq.addEventListener('change', sync);
		} else if (mq.addListener) {
			mq.addListener(sync);
		}
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
			initNavMore();
			initTextCollapse('smn-lead');
			initTextCollapse('smn-letter-summary');
			initTextCollapse('smn-press-issue-desc');
			initCatAsidePin();
			initBrandCompact();
		});
	} else {
		initSuggest('input_query_smn', 'suggest-smn');
		initCoverZoom();
		initNavMore();
		initTextCollapse('smn-lead');
		initTextCollapse('smn-letter-summary');
		initTextCollapse('smn-press-issue-desc');
		initCatAsidePin();
		initBrandCompact();
	}
})();
</script>
{/literal}
