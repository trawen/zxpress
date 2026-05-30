{include file="top.tpl"}

<h1 class="title">Карта электронных газет и журналов для ZX Spectrum</h1><br>

<div id="map" class="map" class="map-canvas"></div>




<br><br>


{include file="right.tpl"}

<script src="js/leaflet.js"></script>
<script src="js/leaflet.markercluster.js"></script>
<!-- <script src="js/d3.min.js" charset="utf-8"></script> -->
<script src="js/sample-geojson.js" type="text/javascript"></script>
<link rel="stylesheet" type="text/css" href="js/leaflet.css"/>
<link rel="stylesheet" type="text/css" href="js/MarkerCluster.css"/>
<link rel="stylesheet" type="text/css" href="http://leaflet.github.io/Leaflet.markercluster/dist/MarkerCluster.Default.css"/>
<script type="text/javascript">

 var addressPoints = {$map nofilter};

 {literal}


	var tiles = L.tileLayer('https://{s}.tiles.mapbox.com/v3/{id}/{z}/{x}/{y}.png', {
				maxZoom: 10,
				id: 'examples.map-20v6611k',
				attribution: ''
			}),
			latlng = L.latLng(55.752,37.616);

		var map = L.map('map', {center: latlng, zoom: 4, layers: [tiles]});

		var markers = L.markerClusterGroup({ disableClusteringAtZoom: 10 });
		
		for (var i = 0; i < addressPoints.length; i++) {
			var a = addressPoints[i];
			var title = a[2];
			var marker = L.marker(L.latLng(a[0], a[1]), { title: title });
			marker.bindPopup(title);
			markers.addLayer(marker);
		}

	 	map.addLayer(markers);

	 	function onEachFeature(feature, layer) {
			var popupContent = "<p>I started out as a GeoJSON " +
					feature.geometry.type + ", but now I'm a Leaflet vector!</p>";

			if (feature.properties && feature.properties.popupContent) {
				popupContent += feature.properties.popupContent;
			}

			layer.bindPopup(popupContent);
		}

		L.geoJson([uzbekistan,serbia,belarus,ukraine,uk,poland,czech,austria,latvia,russia,russia2], {

			style: function (feature) {
				return feature.properties && feature.properties.style;
			},

			onEachFeature: onEachFeature,

			pointToLayer: function (feature, latlng) {
				return L.circleMarker(latlng, {
					radius: 8,
					fillColor: "#ff7800",
					color: "#000",
					weight: 1,
					opacity: 1,
					fillOpacity: 0.8
				});
			}
		}).addTo(map);

	// 	markers.on('click', function () {
 //    		$("#video1").data("overlay").load();
	// 	});
   
	// mapBounds = L.latLngBounds(55.752,37.616);
	// var map = new L.Map("map",
	// 	{
	// 		center: [20, 0],
	// 		zoom: 3,
	// 		attributionControl: false
	// 	}).addLayer(
	// 		new L.TileLayer("http://{s}.tile2.opencyclemap.org/transport/{z}/{x}/{y}.png")
	// 	).setMaxBounds(mapBounds);

	// var map = L.map('map').setView([51.505, -0.09], 13);

	// // add an OpenStreetMap tile layer
	// L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
	//     attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
	// }).addTo(map);

	// // add a marker in the given location, attach some popup content to it and open the popup
	// L.marker([51.5, -0.09]).addTo(map)
 //    .bindPopup('A pretty CSS3 popup. <br> Easily customizable.')
 //    .openPopup();




	// d3.json(Globals.resourceWithPath("xxx.json"), function (json){
	// 	function style(feature) {
	// 		return {
	// 			fillColor: "#E3E3E3",
	// 			weight: 1,
	// 			opacity: 0.4,
	// 			color: 'white',
	// 			fillOpacity: 0.3
	// 		};
	// 	}
	// 	C.geojson = L.geoJson(json, {
	// 		onEachFeature: onEachFeature,
	// 		style : style
	// 	}).addTo(map);

	// });
	 
	// 	function onEachFeature(feature, layer){
	// 		layer.on({
	// 			click : onCountryClick,
	// 			mouseover : onCountryHighLight,
	// 			mouseout : onCountryMouseOut
	// 		});
	// 	}


	// 	function onCountryMouseOut(e){
	// 		C.geojson.resetStyle(e.target);
	// 	//	$("#countryHighlighted").text("No selection");
		 
	// 		var countryName = e.target.feature.properties.name;
	// 		var countryCode = e.target.feature.properties.iso_a2;
	// 	//callback when mouse exits a country polygon goes here, for additional actions
	// 	}
		 
	// 	// *
	// 	//  * Callback for when a country is clicked. Will take care of the ui aspects, and it will call
	// 	//  * other callbacks when done
	// 	//  * @param e
		 
	// 	function onCountryClick(e){
	// 	//callback for clicking inside a polygon
	// 	}
		 
	// 	/**
	// 	 * Callback for when a country is highlighted. Will take care of the ui aspects, and it will call
	// 	 * other callbacks after done.
	// 	 * @param e
	// 	 */
	// 	function onCountryHighLight(e){
	// 		var layer = e.target;
		 
	// 		layer.setStyle({
	// 			weight: 2,
	// 			color: '#666',
	// 			dashArray: '',
	// 			fillOpacity: 0.7
	// 		});
		 
	// 		if (!L.Browser.ie && !L.Browser.opera) {
	// 			layer.bringToFront();
	// 		}
		 
	// 		var countryName = e.target.feature.properties.name;
	// 		var countryCode = e.target.feature.properties.iso_a2;
	// 	//callback when mouse enters a country polygon goes here, for additional actions
	// 	}


     </script>
{/literal}

{include file="footer.tpl"}