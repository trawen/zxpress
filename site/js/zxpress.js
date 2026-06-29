(function () {
	'use strict';

	function byId(id) {
		return document.getElementById(id);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function outerWidthWithMargin(el) {
		var rect = el.getBoundingClientRect();
		var style = window.getComputedStyle(el);
		return rect.width + parseFloat(style.marginLeft || 0) + parseFloat(style.marginRight || 0);
	}

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	onReady(function () {
		var colRight = byId('col-right');
		var colRightFake = byId('col-right-fake');
		if (colRight && colRightFake) {
			colRightFake.style.width = outerWidthWithMargin(colRight) + 'px';
		}

		var button = byId('button');
		if (button) {
			button.addEventListener('click', function () {
				centerPopup();
				loadPopup();
			});
		}

		var popupClose = byId('popupContactClose');
		if (popupClose) {
			popupClose.addEventListener('click', disablePopup);
		}

		var backgroundPopup = byId('backgroundPopup');
		if (backgroundPopup) {
			backgroundPopup.addEventListener('click', disablePopup);
		}

		document.addEventListener('keydown', function (e) {
			if (e.keyCode === 27 && popupStatus === 1) {
				disablePopup();
			}
		});

		var littleTIPdelay = 1;
		var littleTIPfade = 1;
		var littleTIPx = 15;
		var littleTIPy = 15;

		qsa('[title]').forEach(function (el) {
			el.addEventListener('mouseover', function () {
				var elemlT = el.getAttribute('title');
				var src = el.getAttribute('src');
				el.tip = el.title;
				el.title = '';

				var tip;
				if (src && src.indexOf('creens') > 0) {
					tip = document.createElement('div');
					tip.id = 'littleTIP';
					tip.innerHTML = '<div id="littleTIPtext" style="border: 8px solid #242321"><img src="' + src + '" width=256></div>';
				} else {
					tip = document.createElement('div');
					tip.id = 'littleTIP';
					var textEl = document.createElement('div');
					textEl.id = 'littleTIPtext';
					textEl.textContent = '"' + elemlT + '"';
					tip.appendChild(textEl);
				}
				document.body.appendChild(tip);

				setTimeout(function () {
					var little = byId('littleTIP');
					if (!little) return;
					little.style.zIndex = '10000000';
					little.style.position = 'absolute';
					little.style.display = 'block';
					little.style.width = 'auto';
					little.style.height = 'auto';
					little.style.opacity = '0';
					little.style.transition = 'opacity ' + littleTIPfade + 'ms';
					little.offsetHeight;
					little.style.opacity = '1';
				}, littleTIPdelay);
			});

			el.addEventListener('mousemove', function (e) {
				var little = byId('littleTIP');
				if (!little) return;
				var BwiWi = window.innerWidth;
				var BwiHe = window.innerHeight;
				var BwHs = window.pageXOffset;
				var BwVs = window.pageYOffset;
				var lTwi = little.offsetWidth;
				var lThe = little.offsetHeight;

				if (BwiWi + BwHs < e.pageX + lTwi + littleTIPx) {
					little.style.left = (e.pageX - lTwi - littleTIPx) + 'px';
				} else {
					little.style.left = (e.pageX + littleTIPx) + 'px';
				}
				if (BwiHe + BwVs < e.pageY + lThe + littleTIPy) {
					little.style.top = (e.pageY - lThe - littleTIPy) + 'px';
				} else {
					little.style.top = (e.pageY + littleTIPy) + 'px';
				}
			});

			el.addEventListener('mouseout', function () {
				var little = byId('littleTIP');
				if (little) little.remove();
				if (el.tip !== undefined) {
					el.title = el.tip;
				}
			});
		});

		qsa('.top-menu-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var top = btn.closest('.top');
				if (!top) return;
				var open = !top.classList.contains('is-menu-open');
				top.classList.toggle('is-menu-open', open);
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		});
	});
})();

function addStyleSheet() {
	var style = document.createElement('style');
	style.type = 'text/css';
	document.getElementsByTagName('head')[0].appendChild(style);
	return document.styleSheets[document.styleSheets.length - 1];
}

function addStyle(ss, sel, rule) {
	if (ss.addRule) {
		ss.addRule(sel, rule);
	} else if (ss.insertRule) {
		ss.insertRule(sel + ' {' + rule + '}', ss.cssRules.length);
	}
}

var colors = ['#00B400', '#0000B4', '#B40000', '#B400B4', '#00B400', '#00B4B4', '#B4B400', '#B4B4B4', '#00B400', '#0000FE', '#FE0000', '#FE00FE', '#00FE00', '#00FEFE', '#FEFE00', '#FEFEFE', '#808080'];

var popupStatus = 0;
var text = '';

function ToggleColors() {
	var textEl = document.getElementById('text');
	var contactArea = document.getElementById('contactArea');
	if (!textEl || !contactArea) return;

	if (text === '') {
		text = textEl.innerHTML;
		document.querySelectorAll('span').forEach(function (span) {
			var re = /\bRGB\S+\b/g;
			span.className = span.className.replace(re, ' ').replace(/\s+/g, ' ').trim();
		});
		contactArea.innerHTML = text;
		var s = addStyleSheet();
		for (var a = 0; a < 17; a++) {
			addStyle(s, 'span.RGB' + a, 'color:' + colors[a] + ';');
		}
	}

	centerPopup();
	loadPopup();
}

function loadPopup() {
	if (popupStatus !== 0) return;
	var bg = document.getElementById('backgroundPopup');
	var popup = document.getElementById('popupContact');
	if (!bg || !popup) return;
	bg.style.opacity = '0.85';
	fadeIn(bg, 600);
	fadeIn(popup, 600);
	popupStatus = 1;
}

function disablePopup() {
	if (popupStatus !== 1) return;
	fadeOut(document.getElementById('backgroundPopup'), 600);
	fadeOut(document.getElementById('popupContact'), 600);
	popupStatus = 0;
}

function fadeIn(el, ms) {
	if (!el) return;
	ms = ms || 600;
	var targetOpacity = el.id === 'backgroundPopup' ? '0.85' : '1';
	el.style.display = 'block';
	el.style.opacity = '0';
	el.style.transition = 'opacity ' + ms + 'ms';
	el.offsetHeight;
	el.style.opacity = targetOpacity;
}

function fadeOut(el, ms) {
	if (!el) return;
	ms = ms || 600;
	el.style.transition = 'opacity ' + ms + 'ms';
	el.style.opacity = '0';
	setTimeout(function () {
		el.style.display = 'none';
	}, ms);
}

function centerPopup() {
	var popup = document.getElementById('popupContact');
	var bg = document.getElementById('backgroundPopup');
	if (!popup || !bg) return;

	var windowWidth = document.documentElement.clientWidth;
	var windowHeight = document.documentElement.clientHeight;
	var wasHidden = popup.style.display === 'none';
	if (wasHidden) popup.style.display = 'block';
	var popupHeight = popup.offsetHeight;
	var popupWidth = popup.offsetWidth;
	if (wasHidden) popup.style.display = 'none';

	var t = window.pageYOffset || document.documentElement.scrollTop;
	popup.style.position = 'absolute';
	popup.style.top = t + 'px';
	popup.style.left = (windowWidth / 2 - popupWidth / 2) + 'px';
	bg.style.height = windowHeight + 'px';
}

function fuck() {
	var sortEl = document.getElementById('select_sort');
	var queryEl = document.getElementById('input_query');
	var pageEl = document.getElementById('input_page');
	var fromEl = document.getElementById('input_from');
	if (!sortEl || !queryEl) return;
	var sort = sortEl.value;
	var query = queryEl.value;
	var page = pageEl ? pageEl.value : '';
	var from = fromEl && fromEl.checked ? 1 : 0;
	window.location.href = '/search.php?q=' + encodeURIComponent(query) + '&s=' + encodeURIComponent(sort) + '&f=' + from + (page ? '&p=' + encodeURIComponent(page) : '');
}
