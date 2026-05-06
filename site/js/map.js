(function(c, d, f) {
    c && (c.addEventListener ? c.addEventListener(d, f, !1) : c.attachEvent("on" + d, f))
})(window, "load", function() {
    if (document.getElementById("map")) {
        var c = document.head || document.getElementsByTagName("head")[0] || document.documentElement;
        if (c) {
            var d = function(c, a, b, d) {
                    var e = document.createElement(a ? "link" : "script");
                    a ? (e.rel = "stylesheet", e.href = b) : (e.type = "text/javascript", e.src = b);
                    e.__cb = d;
                    e.__cbd = 1;
                    e.onload = function() {
                        this.__cbd && (this.__cbd = 0, this.onload = null, this.__cb && this.__cb())
                    };
                    e.onreadystatechange = function() {
                        if (this.__cbd && ("loaded" == this.readyState || "complete" == this.readyState)) this.__cbd = 0, this.onreadystatechange = null, this.__cb && this.__cb()
                    };
                    c.appendChild(e)
                },
                f = function() {
                    console.log(map_array);
                    var c = new L.MarkerClusterGroup,
                        a = map_array,
                        b, d = null,
                        e, f, g;
                    for (b in a) null === d ? (d = a[b].p[0], f = a[b].p[1], e = a[b].p[0], g = a[b].p[1]) : (d > a[b].p[0] && (d = a[b].p[0]), e < a[b].p[0] && (e = a[b].p[0]), f > a[b].p[1] && (f = a[b].p[1]), g < a[b].p[1] && (g = a[b].p[1])), c.addLayer(L.marker(a[b].p, {
                        title: a[b].t
                    }).bindPopup(a[b].t));
                    a = L.map("map").fitBounds([
                        [d, f],
                        [e, g]
                    ]);
                    L.tileLayer("http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                        attribution: "",
                        maxZoom: 18
                    }).addTo(a);
                    a.addLayer(c)
                },
                h = f,
                j = document.createElement("div");
            j.innerHTML = "\x3c!--[if gt IE 8]><a /><![endif]--\x3e";
            j.getElementsByTagName("a").length && (h = function() {
                d(c, 1, "http://wifly.ru/leaflet.ie.css", function() {
                    d(c, 1, "http://wifly.ru/MarkerCluster.Default.ie.css", f)
                })
            });
            d(c, 0, "http://wifly.ru/leaflet.js", function() {
                d(c, 0, "http://wifly.ru/leaflet.markercluster.js", function() {
                    d(c, 1, "http://wifly.ru/leaflet.css", function() {
                        d(c, 1, "http://wifly.ru/MarkerCluster.Default.css", h)
                    })
                })
            })
        }
    }
});