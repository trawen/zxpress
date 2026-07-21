{literal}
<script>
(function () {
	function initSuggest(inputId, dropId) {
		var input = document.getElementById(inputId);
		var drop = document.getElementById(dropId);
		if (!input || !drop) return;
		var timer = null, sel = -1;

		function hideDrop(clear) {
			drop.style.display = 'none';
			if (clear) drop.innerHTML = '';
		}

		function showDrop() {
			drop.style.display = 'block';
		}

		function dropItems() {
			return drop.querySelectorAll('div');
		}

		function setActiveItem(items, index) {
			for (var i = 0; i < items.length; i++) {
				items[i].classList.toggle('active', i === index);
			}
		}

		input.addEventListener('input', onInput);
		input.addEventListener('keyup', onInput);

		function onInput(e) {
			if (e.keyCode === 38 || e.keyCode === 40 || e.keyCode === 13 || e.keyCode === 27) return;
			clearTimeout(timer);
			var q = input.value;
			if (q.length < 2) { hideDrop(true); return; }
			timer = setTimeout(function () {
				fetch('/suggest.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (data) {
						drop.innerHTML = '';
						sel = -1;
						if (!data || !data.length) { hideDrop(true); return; }
						for (var i = 0; i < data.length; i++) {
							var row = document.createElement('div');
							row.textContent = data[i];
							drop.appendChild(row);
						}
						showDrop();
					})
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
				input.value = items[sel].textContent;
				hideDrop(true);
			} else if (e.keyCode === 27) {
				hideDrop(true);
				sel = -1;
			}
		});

		drop.addEventListener('mousedown', function (e) {
			var row = e.target.closest('div');
			if (!row || !drop.contains(row)) return;
			input.value = row.textContent;
			hideDrop(true);
			var form = input.closest('form');
			if (form) form.submit();
		});

		input.addEventListener('blur', function () {
			setTimeout(function () { hideDrop(false); }, 200);
		});
		input.addEventListener('focus', function () {
			if (dropItems().length) showDrop();
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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initSuggest('input_query_smn', 'suggest-smn');
			initCoverZoom();
			initNavMore();
			initTextCollapse('smn-lead');
			initTextCollapse('smn-letter-summary');
		});
	} else {
		initSuggest('input_query_smn', 'suggest-smn');
		initCoverZoom();
		initNavMore();
		initTextCollapse('smn-lead');
		initTextCollapse('smn-letter-summary');
	}
})();
</script>
{/literal}
