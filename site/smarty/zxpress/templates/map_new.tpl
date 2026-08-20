<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en">
{else}
<html lang="ru">
{/if}
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{$title|strip_tags} — ZXPRESS</title>
	{if $description}<meta name="description" content="{$description|strip_tags|escape:'html'}">{/if}
	{smn_styles}
	<link rel="stylesheet" href="{$host}js/leaflet.css">
</head>
<body class="smn smn-has-map">
	<div class="smn-frame">
		<header class="smn-header">
			<div class="smn-header-bar">
				<a class="smn-brand" href="{$ezines_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Электронная пресса{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/books">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/zxnet">ZXNet</a>
					</div>
					<div class="smn-nav-more-wrap">
						<button type="button" class="smn-nav-more-toggle" aria-expanded="false" aria-controls="smn-nav-more" aria-haspopup="true">
							{if $lng eq 'eng'}More...{else}Ещё...{/if}
						</button>
						<div id="smn-nav-more" class="smn-nav-more" hidden>
							<div class="smn-nav-overflow" hidden></div>
							{include file="snailmail_new_nav_more.tpl"}
						</div>
					</div>
				</nav>
				<div class="smn-lang">
					{if $lng eq 'eng'}
						<a href="{$url_rus}">rus</a><span aria-hidden="true">/</span><b>eng</b>
					{else}
						<b>rus</b><span aria-hidden="true">/</span><a href="{$url_eng}">eng</a>
					{/if}
				</div>
			</div>
			<form class="smn-search" method="GET" action="{if $lng eq 'eng'}/en{else}/ru{/if}/search">
				{if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}
				<div class="smn-search-wrap">
					<label class="smn-search-label" for="input_query_smn">{if $lng eq 'eng'}Search{else}Поиск{/if}</label>
					<input class="smn-search-input" id="input_query_smn" name="q" type="search" placeholder="{if $lng eq 'eng'}Search...{else}Поиск...{/if}" value="{$q|default:''|escape:'html'}" autocomplete="off">
					<div id="suggest-smn" class="smn-search-suggest"></div>
				</div>
			</form>
			<nav class="smn-breadcrumbs" id="smn-sticky-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
{if $map_filter}
				<a href="{$map_catalog_url}">{if $lng eq 'eng'}Map of ZX publications{else}Карта ZX-прессы{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{$map_filter_label|escape:'html'}</span>
{else}
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Map of ZX publications{else}Карта ZX-прессы{/if}</span>
{/if}
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{$map_heading|escape:'html'}</h1>
				<p class="smn-hero-sub smn-map-summary">
				{if $lng eq 'eng'}
					<b>{$map_publications}</b> {$map_publications_label} in <b>{$map_cities}</b> {$map_cities_label}. Circle size and color intensity reflect how many titles were published in each city.
				{else}
					<b>{$map_publications}</b> {$map_publications_label} в <b>{$map_cities}</b> {$map_cities_label}. Размер и насыщенность кружка — число изданий в городе.
				{/if}
				</p>
			</section>

			<nav class="smn-map-tabs" aria-label="{if $lng eq 'eng'}Filter by publication type{else}Фильтр по типу изданий{/if}">
{foreach from=$map_filters item=flt}
{if $flt.active}
				<span class="smn-map-tab is-active"><span class="smn-map-tab-label">{$flt.label|escape:'html'}</span><sup class="smn-filter-count">{$flt.count}</sup></span>
{else}
				<a class="smn-map-tab" href="{$flt.url|escape:'html'}"><span class="smn-map-tab-label">{$flt.label|escape:'html'}</span><sup class="smn-filter-count">{$flt.count}</sup></a>
{/if}
{/foreach}
			</nav>

			<div id="smn-press-map" class="smn-press-map" role="img" aria-label="{$map_heading|escape:'html'}"></div>

{if $top_cities && $top_cities|@count gt 0}
			<section class="smn-map-top" aria-labelledby="smn-map-top-heading">
				<h2 class="smn-map-top-heading" id="smn-map-top-heading">{if $lng eq 'eng'}TOP 50 cities{else}TOP 50 городов{/if}</h2>
				<div class="smn-map-top-list">
{foreach from=$top_cities item=city name=top}
					<div class="smn-map-top-row">
						<div class="smn-map-top-city">
							<span class="smn-map-top-rank">{$smarty.foreach.top.iteration}.</span>
{if $city.country_id}
							<img class="smn-map-top-flag" src="{$host}img/{$city.country_id}.png" width="16" height="10" alt="">
{/if}
							<span class="smn-map-top-city-text">
								<span class="smn-map-top-city-name">{if $lng eq 'eng'}{$city.city_en|default:$city.city_ru|escape:'html'}{else}{$city.city_ru|default:$city.city_en|escape:'html'}{/if}</span>{if ($lng eq 'eng' && $city.country_en) || ($lng neq 'eng' && $city.country_ru)}<span class="smn-map-top-country">, {if $lng eq 'eng'}{$city.country_en|escape:'html'}{else}{$city.country_ru|escape:'html'}{/if}</span>{/if}<span class="smn-map-top-count"> — {$city.count} {$city.count_label}</span>
							</span>
						</div>
						<div class="smn-map-top-pubs">
{foreach from=$city.publications item=pub name=pub}
							<a class="smn-map-top-pub" style="font-size: {$pub.font_size}px" href="{$pub.public_url}">{$pub.title|escape:'html'}</a>{if !$smarty.foreach.pub.last}<span class="smn-map-top-sep">, </span>{/if}
{/foreach}
						</div>
					</div>
{/foreach}
				</div>
			</section>
{/if}
		</main>

		<footer class="smn-footer">
			<div class="smn-copyright">
				{if $lng eq 'eng'}
				<p><b>ZXPRESS</b> — Magazines, newspapers and books for ZX Spectrum &nbsp;© 2009–{$smarty.now|date_format:"%Y"}</p>
				<p class="smn-disclaimer">You may use site materials only with a backlink to the source</p>
				{else}
				<p><b>ZXPRESS</b> — Журналы, газеты и книги для ZX Spectrum &nbsp;© 2009–{$smarty.now|date_format:"%Y"}</p>
				<p class="smn-disclaimer">Использование материалов сайта разрешено только при указании обратной ссылки</p>
				{/if}
			</div>
		</footer>
	</div>

	<script type="application/json" id="smn-press-map-data">{$map_points_json nofilter}</script>
	<script src="{$host}js/leaflet.js"></script>
	<script type="text/javascript">
{literal}
(function () {
	function initPressMap() {
		var dataEl = document.getElementById('smn-press-map-data');
		var points = [];
		if (dataEl) {
			try {
				points = JSON.parse(dataEl.textContent);
			} catch (e) {
				console.error('[zxpress map] bad JSON', e);
				points = [];
			}
		}
		var lang = '{/literal}{if $lng eq 'eng'}eng{else}rus{/if}{literal}';
		var mapFilter = '{/literal}{$map_filter|escape:'javascript'}{literal}';
		var mapEl = document.getElementById('smn-press-map');
		if (!mapEl) {
			console.error('[zxpress map] #smn-press-map missing');
			return;
		}
		if (!window.L) {
			console.error('[zxpress map] Leaflet failed to load');
			return;
		}
		if (!points.length) {
			console.error('[zxpress map] no points');
			return;
		}
		if (mapEl.getAttribute('data-map-ready') === '1') {
			return;
		}
		mapEl.setAttribute('data-map-ready', '1');

		var map = L.map(mapEl, {
			worldCopyJump: true,
			scrollWheelZoom: true
		});

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 18,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
		}).addTo(map);

		var maxCount = 1;
		for (var i = 0; i < points.length; i++) {
			if (points[i].count > maxCount) {
				maxCount = points[i].count;
			}
		}

		function radiusForCount(count) {
			var radius = 4 + Math.sqrt(count / maxCount) * 22;
			if (count === 1) {
				radius *= 0.5;
			}
			return radius;
		}

		function intensityForCount(count) {
			if (count === 1) {
				return 0;
			}
			return Math.sqrt(count / maxCount);
		}

		function hexToRgb(hex) {
			var n = parseInt(hex.slice(1), 16);
			return [n >> 16, (n >> 8) & 255, n & 255];
		}

		function rgbToHex(r, g, b) {
			return '#' + [r, g, b].map(function (channel) {
				var hex = Math.round(channel).toString(16);
				return hex.length === 1 ? '0' + hex : hex;
			}).join('');
		}

		function mixColor(from, to, t) {
			var a = hexToRgb(from);
			var b = hexToRgb(to);
			return rgbToHex(
				a[0] + (b[0] - a[0]) * t,
				a[1] + (b[1] - a[1]) * t,
				a[2] + (b[2] - a[2]) * t
			);
		}

		function colorForCount(count) {
			return mixColor('#c44a2e', '#a41e00', intensityForCount(count));
		}

		function strokeForCount(count) {
			return mixColor('#a0412a', '#7a1600', intensityForCount(count));
		}

		function fillOpacityForCount(count) {
			return 0.72 + intensityForCount(count) * 0.23;
		}

		function cityLabel(p) {
			if (lang === 'rus') {
				return p.city_ru || p.city_en;
			}
			return p.city_en || p.city_ru;
		}

		function countryLabel(p) {
			if (lang === 'rus') {
				return p.country_ru || p.country_en;
			}
			return p.country_en || p.country_ru;
		}

		function pubsWord(n) {
			if (lang !== 'rus') {
				if (mapFilter === 'papers') {
					return n === 1 ? 'newspaper' : 'newspapers';
				}
				if (mapFilter === 'magazines') {
					return n === 1 ? 'magazine' : 'magazines';
				}
				if (mapFilter === 'books') {
					return n === 1 ? 'book' : 'books';
				}
				return n === 1 ? 'publication' : 'publications';
			}
			var forms;
			if (mapFilter === 'papers') {
				forms = ['газета', 'газеты', 'газет'];
			} else if (mapFilter === 'magazines') {
				forms = ['журнал', 'журналы', 'журналов'];
			} else if (mapFilter === 'books') {
				forms = ['книга', 'книги', 'книг'];
			} else {
				forms = ['издание', 'издания', 'изданий'];
			}
			var mod10 = n % 10;
			var mod100 = n % 100;
			if (mod10 === 1 && mod100 !== 11) {
				return forms[0];
			}
			if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
				return forms[1];
			}
			return forms[2];
		}

		function escHtml(s) {
			return String(s == null ? '' : s)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function buildPopup(point) {
			var city = cityLabel(point);
			var country = countryLabel(point);
			var pubs = point.publications || [];
			var html = '<div class="smn-map-popup">'
				+ '<strong>' + escHtml(city) + '</strong>'
				+ (country ? '<br><span class="smn-map-popup-country">' + escHtml(country) + '</span>' : '')
				+ '<br><span class="smn-map-popup-count">' + point.count + ' ' + pubsWord(point.count) + '</span>';
			if (pubs.length) {
				html += '<ul class="smn-map-popup-list">';
				for (var k = 0; k < pubs.length; k++) {
					html += '<li><a href="' + escHtml(pubs[k].url) + '">' + escHtml(pubs[k].title) + '</a></li>';
				}
				html += '</ul>';
			}
			html += '</div>';
			return html;
		}

		var bounds = [];

		for (var j = 0; j < points.length; j++) {
			var point = points[j];
			var latLng = [point.lat, point.lng];
			bounds.push(latLng);

			L.circleMarker(latLng, {
				radius: radiusForCount(point.count),
				fillColor: colorForCount(point.count),
				color: strokeForCount(point.count),
				weight: 1.2,
				opacity: 0.95,
				fillOpacity: fillOpacityForCount(point.count)
			}).bindPopup(buildPopup(point), {
				maxWidth: 280,
				minWidth: 160,
				autoPanPadding: [24, 24]
			}).addTo(map);
		}

		function setMapView() {
			if (bounds.length) {
				map.setView(L.latLngBounds(bounds).getCenter(), 4);
			} else {
				map.setView([55.75, 37.62], 3);
			}
		}

		setMapView();
		map.invalidateSize();

		window.addEventListener('resize', function () {
			map.invalidateSize();
		});
		[50, 250, 800].forEach(function (ms) {
			setTimeout(function () {
				map.invalidateSize();
			}, ms);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPressMap);
	} else {
		initPressMap();
	}
})();
{/literal}
	</script>
{include file="snailmail_new_scripts.tpl"}
</body>
</html>
