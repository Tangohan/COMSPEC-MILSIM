/* Carte Leaflet du terrain — CRS Arma (mètres), tuiles satellite, grille vectorielle. */
(function () {
    "use strict";
    var map = null;
    var tileLayer = null;
    var gridLayer = null;
    var overlayLayer = null;
    var playerMarker = null;
    var contactMarkers = {};
    var contactAccuracy = {};
    var poiMarkers = {};
    var pingMarkers = [];
    var measurePts = [];
    var measureLine = null;
    var lastWorld = "";
    var lastSize = 0;
    var didCenter = false;
    var texture = "SAT";
    var currentTool = "NONE";
    var terrainMode = "flat";
    var terrainData = null;
    var terrainLayer = null;
    var buildingLayer = null;
    var rangeLayer = null;
    var mapSettings = { labels: true, rings: true, night: false };
    var layers = {
        friendly: true,
        hostile: true,
        intel: true,
        bft: true,
        grid: true
    };
    var lastPlayer = { x: 0, y: 0, heading: 0 };
    /* Tailles connues (arma3-leaflet-map + jetelain). worldSize du jeu prime. */
    var WORLDS = {
        altis: { size: 30720, tile: 212, fx: 0.006839, fy: 0.006836, maxZoom: 6, defaultZoom: 3 },
        stratis: { size: 8192, tile: 226, fx: 0.027475, fy: 0.027475, maxZoom: 4, defaultZoom: 2 },
        tanoa: { size: 15360, tile: 213, fx: 0.01385, fy: 0.01385, maxZoom: 5, defaultZoom: 2 },
        enoch: { size: 12800, tile: 356, fx: 0.02735, fy: 0.02735, maxZoom: 4, defaultZoom: 2 },
        livonia: { size: 12800, tile: 356, fx: 0.02735, fy: 0.02735, maxZoom: 4, defaultZoom: 2 },
        kunduz: { size: 5120, tile: 323, fx: 0.063, fy: 0.063, maxZoom: 3, defaultZoom: 2 },
        sze_kimmirut: { size: 20480, tile: 323, fx: 0.01575, fy: 0.01575, maxZoom: 6, defaultZoom: 2 },
        kimmirut: { size: 20480, tile: 323, fx: 0.01575, fy: 0.01575, maxZoom: 6, defaultZoom: 2 },
        malden: { size: 12800, tile: 256, fx: 0.02, fy: 0.02, maxZoom: 5, defaultZoom: 2 },
        vr: { size: 8192, tile: 256, fx: 0.03125, fy: 0.03125, maxZoom: 5, defaultZoom: 2 }
    };
    function slugFromWorld(name) {
        var raw = String(name || "").toLowerCase().replace(/\s+/g, "");
        if (raw === "livonia")
            return "enoch";
        if (WORLDS[raw])
            return raw;
        return raw || "altis";
    }
    function worldCfg(slug, sizeOverride) {
        var known = WORLDS[slug] || {};
        var size = Number(sizeOverride);
        if (!Number.isFinite(size) || size < 256)
            size = known.size || 30720;
        var tile = known.tile || 256;
        var fx = known.fx;
        var fy = known.fy;
        if (!fx)
            fx = tile / size;
        if (!fy)
            fy = tile / size;
        return { size: size, tile: tile, fx: fx, fy: fy, maxZoom: Number(known.maxZoom || 7), defaultZoom: Number(known.defaultZoom || 3) };
    }
    function makeCrs(cfg) {
        if (typeof window.L === "undefined")
            return null;
        return window.L.extend({}, window.L.CRS.Simple, {
            projection: window.L.Projection.LonLat,
            transformation: new window.L.Transformation(cfg.fx, 0, -cfg.fy, cfg.tile),
            scale: function (zoom) { return Math.pow(2, zoom); },
            zoom: function (scale) { return Math.log(scale) / Math.LN2; },
            distance: function (a, b) {
                var dx = b.lng - a.lng, dy = b.lat - a.lat;
                return Math.sqrt(dx * dx + dy * dy);
            },
            infinite: true
        });
    }
    function host() {
        return document.getElementById("nativeMapViewport");
    }
    function send(cmd) {
        if (typeof window.COMSPEC_ATAK_send === "function")
            window.COMSPEC_ATAK_send(cmd);
    }
    function setText(id, text) {
        var el = document.getElementById(id);
        if (el)
            el.textContent = text;
    }
    function fmtMeters(v) {
        v = Number(v);
        if (!Number.isFinite(v) || v < 0)
            return "—";
        if (v >= 1000)
            return (v / 1000).toFixed(v >= 10000 ? 0 : 1) + " km";
        return Math.round(v) + " m";
    }
    function applyTextureClass() {
        var el = host();
        if (!el)
            return;
        el.classList.toggle("topo-tiles", texture === "TOPO");
        if (map)
            refreshGrid();
    }
    function destroy() {
        if (map) {
            try {
                map.remove();
            }
            catch (e) { }
        }
        map = null;
        tileLayer = null;
        gridLayer = null;
        overlayLayer = null;
        playerMarker = null;
        contactMarkers = {};
        poiMarkers = {};
        pingMarkers = [];
        measurePts = [];
        measureLine = null;
        terrainLayer = null;
        buildingLayer = null;
        rangeLayer = null;
        lastWorld = "";
        lastSize = 0;
        didCenter = false;
    }
    function ringLatLngs(y, x, r, n) {
        n = n || 48;
        var pts = [];
        var i;
        for (i = 0; i <= n; i += 1) {
            var a = (i / n) * Math.PI * 2;
            pts.push([y + r * Math.cos(a), x + r * Math.sin(a)]);
        }
        return pts;
    }
    function pulsePing(y, x) {
        if (!map)
            return;
        var icon = window.L.divIcon({
            className: "atak-live-ping",
            html: '<span class="ping-ring r1"></span><span class="ping-ring r2"></span><span class="ping-core">!</span>',
            iconSize: [62, 62],
            iconAnchor: [31, 31]
        });
        var marker = window.L.marker([y, x], { icon: icon, interactive: false, zIndexOffset: 1800 }).addTo(map);
        var halo = window.L.circle([y, x], {
            radius: 45, color: "#ffd75e", weight: 2, fillColor: "#ffd75e", fillOpacity: .08,
            dashArray: "2 5", interactive: false
        }).addTo(map);
        pingMarkers.push(marker, halo);
        try {
            map.panInside([y, x], { padding: [55, 55] });
        }
        catch (e) { }
        setTimeout(function () {
            try {
                map.removeLayer(marker);
            }
            catch (e1) { }
            try {
                map.removeLayer(halo);
            }
            catch (e2) { }
        }, 3600);
    }
    function refreshGrid() {
        if (!map || !gridLayer)
            return;
        gridLayer.clearLayers();
        if (layers.grid === false)
            return;
        var z = map.getZoom();
        var step = z >= 5 ? 1000 : (z >= 3 ? 2000 : 5000);
        var b = map.getBounds();
        var pad = step * 2;
        var x0 = Math.floor((b.getWest() - pad) / step) * step;
        var x1 = Math.ceil((b.getEast() + pad) / step) * step;
        var y0 = Math.floor((b.getSouth() - pad) / step) * step;
        var y1 = Math.ceil((b.getNorth() + pad) / step) * step;
        var major = step * 5;
        var color = texture === "TOPO" ? "rgba(28,62,44,.55)" : "rgba(186,224,208,.16)";
        var majorColor = texture === "TOPO" ? "rgba(18,48,34,.75)" : "rgba(210,240,226,.28)";
        var x, y, isMajor, line;
        var maxLines = 80;
        var count = 0;
        for (x = x0; x <= x1 && count < maxLines; x += step) {
            isMajor = Math.abs(x % major) < 1;
            line = window.L.polyline([[y0, x], [y1, x]], {
                color: isMajor ? majorColor : color,
                weight: isMajor ? 1.15 : 0.55,
                opacity: 1,
                interactive: false
            });
            gridLayer.addLayer(line);
            count += 1;
        }
        for (y = y0; y <= y1 && count < maxLines * 2; y += step) {
            isMajor = Math.abs(y % major) < 1;
            line = window.L.polyline([[y, x0], [y, x1]], {
                color: isMajor ? majorColor : color,
                weight: isMajor ? 1.15 : 0.55,
                opacity: 1,
                interactive: false
            });
            gridLayer.addLayer(line);
            count += 1;
        }
    }
    function nearestPoiId(x, y, maxDist) {
        var best = "", bd = Number(maxDist) || 180;
        Object.keys(poiMarkers).forEach(function (id) {
            var mk = poiMarkers[id];
            if (!mk || !mk.getLatLng)
                return;
            var p = mk.getLatLng();
            var dx = Number(p.lng) - x, dy = Number(p.lat) - y, d = Math.sqrt(dx * dx + dy * dy);
            if (d < bd) {
                bd = d;
                best = id;
            }
        });
        return best;
    }
    function nearestDrawId(x, y, maxDist) {
        if (!window.COMSPEC_MapEngine || !window.COMSPEC_MapEngine.nearest)
            return "";
        var n = window.COMSPEC_MapEngine.nearest(x, y, Number(maxDist) || 180);
        return n && n.id ? String(n.id) : "";
    }
    function bindMapEvents(m) {
        m.on("contextmenu", function (ev) {
            try {
                if (ev.originalEvent) {
                    ev.originalEvent.preventDefault();
                    ev.originalEvent.stopPropagation();
                }
                if (window.COMSPEC_MapEngine && window.COMSPEC_MapEngine.getTool &&
                    window.COMSPEC_MapEngine.getTool() === "freehand") {
                    if (window.COMSPEC_MapEngine.finishFreehandMode)
                        window.COMSPEC_MapEngine.finishFreehandMode();
                    if (window.COMSPEC_ATAK_toast)
                        window.COMSPEC_ATAK_toast("Mode dessin terminé.", "OK");
                    try {
                        m.dragging.enable();
                    }
                    catch (e) { }
                    freehandActive = false;
                    return;
                }
                var x = Number(ev.latlng && ev.latlng.lng), y = Number(ev.latlng && ev.latlng.lat);
                if (!Number.isFinite(x) || !Number.isFinite(y))
                    return;
                var oe = ev.originalEvent || {};
                var markerId = nearestPoiId(x, y, 220);
                var drawId = nearestDrawId(x, y, 220);
                if (window.COMSPEC_ATAK_openMapContext)
                    window.COMSPEC_ATAK_openMapContext(oe.clientX || 0, oe.clientY || 0, x, y, markerId, drawId);
            }
            catch (e) { }
        });
        var freehandActive = false;
        m.on("mousedown", function (ev) {
            if (!window.COMSPEC_MapEngine || window.COMSPEC_MapEngine.getTool() !== "freehand")
                return;
            if (ev.originalEvent && ev.originalEvent.button !== 0)
                return;
            freehandActive = true;
            try {
                m.dragging.disable();
            }
            catch (e) { }
            window.COMSPEC_MapEngine.beginFreehand(Number(ev.latlng.lng), Number(ev.latlng.lat));
            if (ev.originalEvent)
                ev.originalEvent.preventDefault();
        });
        m.on("mousemove", function (ev) {
            if (!freehandActive || !window.COMSPEC_MapEngine)
                return;
            window.COMSPEC_MapEngine.appendFreehand(Number(ev.latlng.lng), Number(ev.latlng.lat));
        });
        m.on("mouseup", function () {
            if (!freehandActive)
                return;
            freehandActive = false;
            if (window.COMSPEC_MapEngine)
                window.COMSPEC_MapEngine.endFreehand();
            try {
                m.dragging.enable();
            }
            catch (e) { }
        });
        m.on("mouseout", function () {
            if (!freehandActive)
                return;
            freehandActive = false;
            if (window.COMSPEC_MapEngine)
                window.COMSPEC_MapEngine.endFreehand();
            try {
                m.dragging.enable();
            }
            catch (e) { }
        });
        m.on("click", function (ev) {
            var ll = ev.latlng || {};
            var x = Number(ll.lng);
            var y = Number(ll.lat);
            if (!Number.isFinite(x) || !Number.isFinite(y))
                return;
            if (window.COMSPEC_ATAK_placePendingMarkerAt && window.COMSPEC_ATAK_placePendingMarkerAt(x, y))
                return;
            if (window.COMSPEC_MapEngine && window.COMSPEC_MapEngine.getTool() !== "none") {
                if (window.COMSPEC_MapEngine.clickAt(x, y))
                    return;
            }
            send("map:worldclick|" + x.toFixed(2) + "|" + y.toFixed(2));
            if (currentTool === "PING")
                pulsePing(y, x);
            if (currentTool === "MEASURE")
                updateMeasure(y, x);
        });
        m.on("moveend zoomend", function () {
            reportScale();
            refreshGrid();
        });
    }
    function updateMeasure(y, x) {
        if (!map)
            return;
        measurePts.push([y, x]);
        if (measurePts.length === 1) {
            setText("mapStatus", "Mesure · point A");
            return;
        }
        var a = measurePts[measurePts.length - 2];
        var b = measurePts[measurePts.length - 1];
        if (measureLine) {
            try {
                map.removeLayer(measureLine);
            }
            catch (e) { }
        }
        measureLine = window.L.polyline(measurePts, {
            color: "#f0b43d",
            weight: 2,
            dashArray: "6 4"
        }).addTo(map);
        var dist = map.distance(window.L.latLng(a[0], a[1]), window.L.latLng(b[0], b[1]));
        var dx = b[1] - a[1];
        var dy = b[0] - a[0];
        var az = ((Math.atan2(dx, dy) * 180 / Math.PI) + 360) % 360;
        setText("scaleValue", fmtMeters(dist));
        var status = document.getElementById("mapStatus");
        if (status) {
            var label = status.querySelector("span") || status;
            label.textContent = "Mesure " + fmtMeters(dist) + " · " + String(Math.round(az)).padStart(3, "0") + "°";
        }
        if (measurePts.length >= 2)
            measurePts = [measurePts[measurePts.length - 1]];
    }
    function reportScale() {
        if (!map)
            return;
        var b = map.getBounds();
        var west = window.L.latLng(b.getSouth(), b.getWest());
        var east = window.L.latLng(b.getSouth(), b.getEast());
        var width = map.distance(west, east);
        var scaleMeters = width * 0.2;
        setText("scaleValue", fmtMeters(scaleMeters));
        setText("zoomValue", String(map.getZoom()));
        var center = map.getCenter();
        var dx = Number(center.lng) - lastPlayer.x;
        var dy = Number(center.lat) - lastPlayer.y;
        var dist = Math.sqrt(dx * dx + dy * dy);
        var az = ((Math.atan2(dx, dy) * 180 / Math.PI) + 360) % 360;
        setText("telemetryCenter", "DIST " + fmtMeters(dist) + " · AZ " + String(Math.round(az)).padStart(3, "0") + "°");
    }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function terrainColor(mode, h, minH, maxH, slope, shade) {
        var t = (h - minH) / Math.max(1, maxH - minH);
        t = clamp(t, 0, 1);
        slope = clamp(slope, 0, 1);
        shade = clamp(shade, 0, 1);
        var r, g, b;
        if (mode === "slope") {
            r = 35 + 205 * slope;
            g = 115 + 100 * (1 - slope);
            b = 70 - 35 * slope;
        }
        else if (mode === "heatmap") {
            r = 35 + 200 * t;
            g = 95 + 125 * (1 - Math.abs(t - .5) * 1.6);
            b = 135 - 95 * t;
        }
        else if (mode === "relief" || mode === "tactical") {
            r = 28 + 62 * t;
            g = 58 + 82 * t;
            b = 48 + 52 * t;
            var k = .48 + shade * .72;
            r *= k;
            g *= k;
            b *= k;
        }
        else {
            var band = Math.floor(t * 12) / 12;
            r = 30 + 52 * band;
            g = 66 + 78 * band;
            b = 54 + 46 * band;
        }
        return [clamp(r, 0, 255) | 0, clamp(g, 0, 255) | 0, clamp(b, 0, 255) | 0, 255];
    }
    function renderTerrain() {
        if (!map || !terrainData || !terrainData.heights)
            return;
        var d = terrainData, n = Number(d.n) || 0;
        if (n < 3 || d.heights.length < n * n)
            return;
        if (terrainLayer) {
            try {
                map.removeLayer(terrainLayer);
            }
            catch (e) { }
            terrainLayer = null;
        }
        if (buildingLayer) {
            try {
                map.removeLayer(buildingLayer);
            }
            catch (e2) { }
            buildingLayer = null;
        }
        var canvas = document.createElement("canvas");
        canvas.width = n;
        canvas.height = n;
        var ctx = canvas.getContext("2d");
        var img = ctx.createImageData(n, n);
        var hs = d.heights.map(Number);
        var minH = Math.min.apply(Math, hs), maxH = Math.max.apply(Math, hs);
        var step = Number(d.step) || 1;
        function H(x, y) { x = clamp(x, 0, n - 1) | 0; y = clamp(y, 0, n - 1) | 0; return hs[y * n + x] || 0; }
        for (var y = 0; y < n; y++) {
            for (var x = 0; x < n; x++) {
                var dx = (H(x + 1, y) - H(x - 1, y)) / (2 * step);
                var dy = (H(x, y + 1) - H(x, y - 1)) / (2 * step);
                var slope = Math.atan(Math.sqrt(dx * dx + dy * dy)) / (Math.PI / 2);
                var nx = -dx, ny = -dy, nz = 1;
                var inv = 1 / Math.max(.001, Math.sqrt(nx * nx + ny * ny + nz * nz));
                nx *= inv;
                ny *= inv;
                nz *= inv;
                var lx = -.55, ly = .55, lz = .63;
                var shade = clamp((nx * lx + ny * ly + nz * lz + 1) / 2, 0, 1);
                var c = terrainColor(terrainMode, H(x, y), minH, maxH, slope, shade);
                var iy = (n - 1 - y), off = (iy * n + x) * 4;
                img.data[off] = c[0];
                img.data[off + 1] = c[1];
                img.data[off + 2] = c[2];
                img.data[off + 3] = c[3];
            }
        }
        ctx.putImageData(img, 0, 0);
        var x0 = Number(d.x0) || 0, y0 = Number(d.y0) || 0;
        var x1 = x0 + step * (n - 1), y1 = y0 + step * (n - 1);
        terrainLayer = window.L.imageOverlay(canvas.toDataURL("image/png"), [[y0, x0], [y1, x1]], {
            opacity: terrainMode === "flat" ? .82 : .96, interactive: false, pane: "terrainLocal"
        }).addTo(map);
        buildingLayer = window.L.layerGroup().addTo(map);
        (d.buildings || []).forEach(function (b) {
            var bx = Number(b[0]), by = Number(b[1]), bw = Math.max(3, Number(b[2]) || 5), bh = Math.max(3, Number(b[3]) || 5), a = (Number(b[4]) || 0) * Math.PI / 180;
            var ca = Math.cos(a), sa = Math.sin(a), pts = [];
            [[-bw / 2, -bh / 2], [bw / 2, -bh / 2], [bw / 2, bh / 2], [-bw / 2, bh / 2]].forEach(function (q) {
                pts.push([by + q[0] * sa + q[1] * ca, bx + q[0] * ca - q[1] * sa]);
            });
            window.L.polygon(pts, {
                color: terrainMode === "tactical" ? "rgba(188,220,208,.72)" : "rgba(160,190,178,.52)",
                weight: terrainMode === "tactical" ? 1.3 : .7,
                fillColor: "#b8c7c0",
                fillOpacity: terrainMode === "tactical" ? .32 : .16,
                interactive: false,
                pane: "buildingsLocal"
            }).addTo(buildingLayer);
        });
    }
    function updateRangeRings() {
        if (!map)
            return;
        if (rangeLayer) {
            try {
                map.removeLayer(rangeLayer);
            }
            catch (e) { }
        }
        rangeLayer = window.L.layerGroup().addTo(map);
        if (!mapSettings.rings)
            return;
        [500, 1000, 2000].forEach(function (r) {
            window.L.circle([lastPlayer.y, lastPlayer.x], {
                radius: r, color: "rgba(91,232,190,.20)", weight: 1, fill: false, interactive: false
            }).addTo(rangeLayer);
        });
    }
    function markLiveTiles() {
        var el = host();
        var wrap = document.getElementById("atakMap");
        if (el)
            el.classList.add("leaflet-ready", "tiles-live");
        if (wrap)
            wrap.classList.add("live-tiles");
    }
    function attachTiles(slug, cfg) {
        if (!map)
            return;
        if (tileLayer) {
            try {
                map.removeLayer(tileLayer);
            }
            catch (e) { }
        }
        tileLayer = null;
        if (window.COMSPEC_MapTiles && typeof window.COMSPEC_MapTiles.layer === "function") {
            tileLayer = window.COMSPEC_MapTiles.layer(slug, cfg);
        }
        if (tileLayer) {
            tileLayer.on("tileload", function (ev) {
                var src = ev && ev.tile ? String(ev.tile.src || "") : "";
                if (!src || src.indexOf("data:image/gif") === 0)
                    return;
                markLiveTiles();
            });
            tileLayer.on("tileerror", function () {
                var wrap = document.getElementById("atakMap");
                if (wrap && !host().classList.contains("tiles-live"))
                    wrap.classList.remove("live-tiles");
            });
            tileLayer.addTo(map);
        }
    }
    function ensure(worldName, sizeOverride) {
        if (typeof window.L === "undefined")
            return null;
        var el = host();
        if (!el)
            return null;
        var slug = slugFromWorld(worldName);
        var cfg = worldCfg(slug, sizeOverride);
        if (map && lastWorld === slug && lastSize === cfg.size) {
            try {
                map.invalidateSize({ animate: false });
            }
            catch (e0) { }
            return map;
        }
        destroy();
        lastWorld = slug;
        lastSize = cfg.size;
        var crs = makeCrs(cfg);
        el.classList.add("leaflet-ready");
        var wrap = document.getElementById("atakMap");
        if (wrap)
            wrap.classList.remove("live-tiles");
        map = window.L.map(el, {
            crs: crs,
            minZoom: 0,
            maxZoom: cfg.maxZoom,
            zoomControl: false,
            attributionControl: false,
            fadeAnimation: false,
            zoomAnimation: false,
            markerZoomAnimation: false
        });
        var groundPane = map.createPane("ground");
        groundPane.style.zIndex = 150;
        var terrainPane = map.createPane("terrainLocal");
        terrainPane.style.zIndex = 165;
        var buildingsPane = map.createPane("buildingsLocal");
        buildingsPane.style.zIndex = 235;
        window.L.rectangle([window.L.latLng(0, 0), window.L.latLng(cfg.size, cfg.size)], {
            pane: "ground",
            color: "#1a2c24",
            weight: 0,
            fillColor: "#15241e",
            fillOpacity: 1,
            interactive: false
        }).addTo(map);
        attachTiles(slug, cfg);
        if (terrainData)
            renderTerrain();
        gridLayer = window.L.layerGroup().addTo(map);
        overlayLayer = window.L.layerGroup().addTo(map);
        map.setView(window.L.latLng(cfg.size / 2, cfg.size / 2), cfg.defaultZoom);
        bindMapEvents(map);
        applyTextureClass();
        refreshGrid();
        if (window.COMSPEC_MapEngine) {
            window.COMSPEC_MapEngine.setWorld(slug);
            window.COMSPEC_MapEngine.attach(map, { world: slug });
        }
        setTimeout(function () {
            try {
                map.invalidateSize({ animate: false });
            }
            catch (e2) { }
            reportScale();
            refreshGrid();
        }, 80);
        return map;
    }
    function playerIcon(heading) {
        var rot = Number(heading);
        if (!Number.isFinite(rot))
            rot = 0;
        return window.L.divIcon({
            className: "atak-self-marker",
            html: '<span class="atak-self-chevron" style="transform:rotate(' + rot + 'deg)"></span>',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });
    }
    function contactIcon(kind, heading, label) {
        var rot = Number(heading);
        if (!Number.isFinite(rot))
            rot = 0;
        var safe = String(label || "").replace(/[<>&]/g, "");
        return window.L.divIcon({
            className: "atak-contact-marker atak-contact-" + (kind === "g" ? "group" : "bft") + (mapSettings.labels ? "" : " no-label"),
            html: '<span class="atak-contact-dot" style="transform:rotate(' + rot + 'deg)"></span><em>' + safe + "</em>",
            iconSize: [18, 18],
            iconAnchor: [9, 9]
        });
    }
    var MARKER_WEB_ROOTS = ["https://athena.ttrd.fr/assets/markers/arma/", "https://athena.ttrd.fr/public/assets/markers/arma/"];
    var MARKER_WEB_MAP = {
        "COMSPEC_CTAB_B_INF": "ctab/cTab/img/b_inf_rifle.png",
        "COMSPEC_CTAB_B_MECH": "ctab/cTab/img/b_mech_inf_wheeled.png",
        "COMSPEC_CTAB_B_AA": "ctab/cTab/img/b_armor_aa.png",
        "COMSPEC_CTAB_O_INF": "ctab/cTab/img/o_inf_rifle.png",
        "COMSPEC_CTAB_O_AT": "ctab/cTab/img/o_inf_at.png",
        "COMSPEC_CTAB_O_AA": "ctab/cTab/img/o_inf_aa.png",
        "COMSPEC_CTAB_O_MG": "ctab/cTab/img/o_inf_mg.png",
        "COMSPEC_CTAB_O_WHEELED": "ctab/cTab/img/o_armor_wheeled.png",
        "COMSPEC_MRH_TARGET": "markersplus/data/img/aapoint.png",
        "COMSPEC_MRH_MEDICAL": "markersplus/data/img/ccpoint.png",
        "COMSPEC_MRH_HELI": "markersplus/data/img/checkpoint.png",
        "COMSPEC_MRH_UAV": "markersplus/data/img/ambush.png",
        "COMSPEC_MRH_SPECOPS": "markersplus/data/img/attackbyfire.png",
        "COMSPEC_MRH_RESUPPLY": "markersplus/data/img/ammopoint.png",
        "COMSPEC_MRH_RADIO": "markersplus/data/img/civpoint.png",
        "OBJECTIVE": "markersplus/data/img/checkpoint.png",
        "TARGET": "markersplus/data/img/aapoint.png",
        "CONTACT": "markersplus/data/img/ambush.png",
        "MEDICAL": "markersplus/data/img/ccpoint.png",
        "HAZARD": "markersplus/data/img/block.png",
        "LZ": "markersplus/data/img/checkpoint.png"
    };
    function markerImageCandidates(symbol) {
        var s = String(symbol || "POINT").toUpperCase();
        var mapped = MARKER_WEB_MAP[s];
        var low = s.toLowerCase();
        var stripped = low.replace(/^comspec_/, "").replace(/^ctab_/, "").replace(/^mrh_/, "");
        var out = [];
        MARKER_WEB_ROOTS.forEach(function (root) {
            if (mapped)
                out.push(root + mapped);
            out.push(root + "markersplus/data/img/" + stripped + ".png");
            out.push(root + "ctab/cTab/img/" + stripped + ".png");
            out.push(root + "a3/ui_f/data/map/groupicons/" + stripped + ".png");
        });
        return out;
    }
    window.COMSPEC_ATAK_markerImgFallback = function (img) {
        if (!img)
            return;
        var arr = String(img.getAttribute("data-candidates") || "").split(";");
        var idx = Number(img.getAttribute("data-index") || 0) + 1;
        if (idx < arr.length) {
            img.setAttribute("data-index", String(idx));
            img.src = arr[idx];
        }
        else {
            img.style.display = "none";
            var n = img.nextElementSibling;
            if (n)
                n.style.display = "grid";
        }
    };
    function markerStaleness(marker) {
        var ttl = window.COMSPEC_MapSymbols && window.COMSPEC_MapSymbols.ttlFor
            ? Number(window.COMSPEC_MapSymbols.ttlFor(marker)) : Number(marker.ttlMin || 0);
        var age = Number(marker.ageSec || 0);
        if (!(ttl > 0))
            return 0;
        var ratio = age / (ttl * 60);
        if (ratio >= 1)
            return 2;
        if (ratio >= 0.72)
            return 1;
        return 0;
    }
    function poiIcon(marker) {
        var label = String(marker.label || marker.category || "").replace(/[<>&]/g, "");
        var stale = markerStaleness(marker);
        var symbol = window.COMSPEC_MapSymbols && window.COMSPEC_MapSymbols.render
            ? window.COMSPEC_MapSymbols.render({
                affiliation: marker.affiliation || marker.type,
                symbol: marker.symbol || marker.category,
                category: marker.category,
                stale: stale
            })
            : '<span class="atak-poi-fallback">•</span>';
        var nativeBadge = marker.native ? '<span class="native-marker-badge">ARMA</span>' : '';
        var staleBadge = stale === 2 ? '<span class="stale-marker-badge">NON CONFIRMÉ</span>' : (stale === 1 ? '<span class="stale-marker-badge">ANCIEN</span>' : '');
        return window.L.divIcon({
            className: "atak-poi-marker tactical-symbol-marker",
            html: '<span class="symbol-stack">' + symbol + nativeBadge + staleBadge + '</span><em>' + (mapSettings.labels ? label : "") + '</em>',
            iconSize: [42, 42],
            iconAnchor: [21, 21]
        });
    }
    function markerVisible(marker) {
        var type = String(marker.type || "").toUpperCase();
        var aff = String(marker.affiliation || "").toUpperCase();
        if (type === "FRIENDLY" || aff === "FRIENDLY")
            return layers.friendly !== false;
        if (type === "HOSTILE" || aff === "HOSTILE")
            return layers.hostile !== false;
        if (type === "SSE")
            return layers.intel !== false;
        return true;
    }
    function parseContactAge(c) {
        if (Number.isFinite(Number(c.ageSec)))
            return Math.max(0, Number(c.ageSec));
        var raw = String(c.updated || "").trim();
        if (raw) {
            var t = Date.parse(raw);
            if (Number.isFinite(t))
                return Math.max(0, (Date.now() - t) / 1000);
        }
        return 0;
    }
    function accuracyRadius(c) {
        var age = parseContactAge(c);
        var src = String(c.src || "").toUpperCase();
        var base = src.indexOf("ATHENA") >= 0 ? 8 : (c.ai ? 3 : 5);
        return Math.min(350, base + age * 1.8);
    }
    function displayNoise(id, quality) {
        quality = String(quality || "NOMINAL").toUpperCase();
        var amp = quality === "DEGRADED" ? 5 : quality === "CRITICAL" ? 12 : quality === "INDOOR" ? 9 : 0;
        if (!amp)
            return [0, 0];
        var h = 0;
        id = String(id || "");
        for (var i = 0; i < id.length; i++)
            h = (h * 31 + id.charCodeAt(i)) >>> 0;
        var t = Date.now() / 2200;
        return [Math.sin(t + (h % 17)) * amp, Math.cos(t * .83 + (h % 29)) * amp];
    }
    function syncContacts(list) {
        if (!map)
            return;
        var seen = {};
        (list || []).forEach(function (c) {
            if (!c)
                return;
            var id = String(c.id || c.cs || "");
            if (!id)
                return;
            if (c.k === "b" && layers.bft === false)
                return;
            var x = Number(c.x), y = Number(c.y);
            if (!Number.isFinite(x) || !Number.isFinite(y))
                return;
            if (window.COMSPEC_MapEngine && !window.COMSPEC_MapEngine.inAo(x, y))
                return;
            var q = (window.COMSPEC_ATAK_STATE && window.COMSPEC_ATAK_STATE.sensorQuality) || "NOMINAL";
            var noise = displayNoise(id, q);
            x += noise[0];
            y += noise[1];
            seen[id] = true;
            var ll = window.L.latLng(y, x);
            var icon = contactIcon(c.k, c.h, c.cs);
            if (contactMarkers[id]) {
                contactMarkers[id].setLatLng(ll);
                contactMarkers[id].setIcon(icon);
            }
            else {
                contactMarkers[id] = window.L.marker(ll, { icon: icon, zIndexOffset: 400 }).addTo(map);
            }
            var radius = accuracyRadius(c);
            if (radius > 10) {
                if (contactAccuracy[id]) {
                    contactAccuracy[id].setLatLng(ll).setRadius(radius);
                }
                else {
                    contactAccuracy[id] = window.L.circle(ll, {
                        radius: radius, color: "#70d7b9", weight: 1, dashArray: "3 5",
                        fillColor: "#70d7b9", fillOpacity: .045, interactive: false
                    }).addTo(map);
                }
            }
            else if (contactAccuracy[id]) {
                try {
                    map.removeLayer(contactAccuracy[id]);
                }
                catch (e) { }
                delete contactAccuracy[id];
            }
        });
        Object.keys(contactMarkers).forEach(function (id) {
            if (!seen[id]) {
                try {
                    map.removeLayer(contactMarkers[id]);
                }
                catch (e) { }
                if (contactAccuracy[id]) {
                    try {
                        map.removeLayer(contactAccuracy[id]);
                    }
                    catch (e) { }
                    delete contactAccuracy[id];
                }
                delete contactMarkers[id];
            }
        });
        var tc = document.getElementById("trackerCount");
        if (tc)
            tc.textContent = String(Object.keys(seen).length);
        var tl = document.getElementById("trackerList");
        if (tl) {
            tl.innerHTML = "";
            var ids = Object.keys(seen);
            var athenaN = 0, aiN = 0;
            (list || []).forEach(function (c) { var id = String(c && (c.id || c.cs) || ""); if (!seen[id])
                return; if (c.ai)
                aiN++; if (String(c.src || "").toUpperCase().indexOf("ATHENA") >= 0)
                athenaN++; });
            var ac = document.getElementById("bftAthenaCount"), aic = document.getElementById("bftAiCount");
            if (ac)
                ac.textContent = String(athenaN);
            if (aic)
                aic.textContent = String(aiN);
            if (!ids.length) {
                tl.innerHTML = '<div class="panel-empty bft-empty"><span class="bft-empty-reticle">＋</span><b>Aucune piste exploitable</b><small>Le terminal n’a reçu ni unité Arma locale ni piste Athena/P2P.</small></div>';
            }
            else {
                (list || []).forEach(function (c) {
                    var id = String(c && (c.id || c.cs) || "");
                    if (!seen[id])
                        return;
                    var a = document.createElement("button");
                    a.className = "tracker-card" + (c.ai ? " ai" : "");
                    a.type = "button";
                    var name = String(c.cs || id).replace(/[<>&]/g, "");
                    var src = String(c.src || (c.k === "b" ? "ATHENA" : "ARMA")).replace(/[<>&]/g, "");
                    var grp = String(c.grp || "").replace(/[<>&]/g, "");
                    var role = String(c.role || "").replace(/[<>&]/g, "");
                    var dist = Number(c.dist);
                    var health = Number(c.health);
                    var htxt = Number.isFinite(health) && health >= 0 ? (health <= 1 ? Math.round(health * 100) : Math.round(health)) + "%" : "—";
                    var dtxt = Number.isFinite(dist) ? (dist < 1000 ? Math.round(dist) + " m" : (dist / 1000).toFixed(1) + " km") : "—";
                    a.dataset.bftAi = c.ai ? "1" : "0";
                    a.dataset.bftSource = src.toUpperCase();
                    a.dataset.bftGroup = grp;
                    a.innerHTML =
                        '<span class="bft-unit-symbol">' + (c.ai ? 'AI' : 'OP') + '</span>' +
                            '<div class="bft-card-body"><div class="tracker-card-main"><b>' + name + '</b><span class="tracker-source">' + src + '</span></div>' +
                            '<div class="tracker-card-meta">' + (grp ? '<span>' + grp + '</span>' : '') + (role ? '<span>' + role + '</span>' : '') + '<span>SANTÉ ' + htxt + '</span></div></div>' +
                            '<div class="bft-range">' + dtxt + '<small>' + Math.round(Number(c.h || 0)) + '°</small></div>';
                    a.addEventListener("click", function () { try {
                        map.setView([Number(c.y), Number(c.x)], Math.max(map.getZoom(), 5));
                    }
                    catch (e) { } });
                    tl.appendChild(a);
                });
            }
        }
    }
    function syncPois(list) {
        if (!map)
            return;
        var seen = {};
        (list || []).forEach(function (marker) {
            if (!marker || !markerVisible(marker))
                return;
            var ttl = window.COMSPEC_MapSymbols && window.COMSPEC_MapSymbols.ttlFor ? Number(window.COMSPEC_MapSymbols.ttlFor(marker)) : Number(marker.ttlMin || 0);
            if (ttl > 0 && Number(marker.ageSec || 0) > ttl * 60 * 1.25)
                return;
            var id = String(marker.id || "");
            if (!id)
                return;
            var x = Number(marker.x), y = Number(marker.y);
            if (!Number.isFinite(x) || !Number.isFinite(y))
                return;
            seen[id] = true;
            var ll = window.L.latLng(y, x);
            var icon = poiIcon(marker);
            if (poiMarkers[id]) {
                poiMarkers[id].setLatLng(ll);
                poiMarkers[id].setIcon(icon);
            }
            else {
                poiMarkers[id] = window.L.marker(ll, { icon: icon, zIndexOffset: 300 }).addTo(map);
            }
        });
        Object.keys(poiMarkers).forEach(function (id) {
            if (!seen[id]) {
                try {
                    map.removeLayer(poiMarkers[id]);
                }
                catch (e) { }
                delete poiMarkers[id];
            }
        });
    }
    function updateFromTelemetry(t) {
        t = t || {};
        var x = Number(t.coordX);
        var y = Number(t.coordY);
        if (!Number.isFinite(x) || !Number.isFinite(y))
            return;
        var world = t.world || (window.COMSPEC_ATAK_STATE && window.COMSPEC_ATAK_STATE.world) || "altis";
        var m = ensure(world, t.worldSize);
        if (!m)
            return;
        lastPlayer.x = x;
        lastPlayer.y = y;
        lastPlayer.heading = Number(t.heading) || 0;
        if (window.COMSPEC_MapBus) {
            window.COMSPEC_MapBus.state("position.update", {
                x: x,
                y: y,
                heading: lastPlayer.heading,
                world: world
            });
            if (Array.isArray(t.contacts)) {
                window.COMSPEC_MapBus.state("bft.snapshot", { count: t.contacts.length });
            }
        }
        var ll = window.L.latLng(y, x);
        if (!playerMarker) {
            playerMarker = window.L.marker(ll, { icon: playerIcon(lastPlayer.heading), zIndexOffset: 800 }).addTo(m);
        }
        else {
            playerMarker.setLatLng(ll);
            playerMarker.setIcon(playerIcon(lastPlayer.heading));
        }
        if (!didCenter) {
            m.setView(ll, Math.max(m.getZoom(), 4), { animate: false });
            didCenter = true;
        }
        if (Array.isArray(t.contacts))
            syncContacts(t.contacts);
        var st = window.COMSPEC_ATAK_STATE || {};
        document.body.classList.toggle("data-delayed", String(st.linkQuality || "").toUpperCase() === "DEGRADED" || Number(st.runtimeHeartbeatAge) > 12);
        updateRangeRings();
        reportScale();
    }
    function applyState(st) {
        st = st || {};
        if (st.tool)
            currentTool = String(st.tool).toUpperCase();
        if (st.world || st.worldSize)
            ensure(st.world, st.worldSize);
        if (st.texture) {
            texture = String(st.texture).toUpperCase() === "TOPO" ? "TOPO" : "SAT";
            applyTextureClass();
        }
        if (typeof st.bft === "boolean")
            layers.bft = st.bft;
        if (typeof st.layerFriendly === "boolean")
            layers.friendly = st.layerFriendly;
        if (typeof st.layerHostile === "boolean")
            layers.hostile = st.layerHostile;
        if (typeof st.layerIntel === "boolean")
            layers.intel = st.layerIntel;
        if (Array.isArray(st.markers))
            syncPois(st.markers);
        if (st.callsign && window.COMSPEC_MapEngine)
            window.COMSPEC_MapEngine.setAuthor(st.callsign);
        if (st.world && window.COMSPEC_MapEngine)
            window.COMSPEC_MapEngine.setWorld(st.world);
        if (currentTool !== "MEASURE") {
            measurePts = [];
            if (measureLine && map) {
                try {
                    map.removeLayer(measureLine);
                }
                catch (e) { }
                measureLine = null;
            }
        }
    }
    window.COMSPEC_ATAK_liveMapTerrain = function (d) {
        terrainData = d || null;
        try {
            renderTerrain();
        }
        catch (e) { }
    };
    window.COMSPEC_ATAK_liveMapMode = function (next) {
        terrainMode = String(next || "flat").toLowerCase();
        if (["flat", "relief", "slope", "heatmap", "tactical"].indexOf(terrainMode) < 0)
            terrainMode = "flat";
        var root = document.getElementById("overwatchApp");
        if (root) {
            root.classList.toggle("scene-tactical", terrainMode === "tactical");
            root.classList.toggle("scene-relief", terrainMode === "relief");
            root.classList.toggle("scene-slope", terrainMode === "slope");
            root.classList.toggle("scene-heatmap", terrainMode === "heatmap");
        }
        try {
            renderTerrain();
        }
        catch (e) { }
    };
    window.COMSPEC_ATAK_liveMapSettings = function (next) {
        next = next || {};
        if (typeof next.labels === "boolean")
            mapSettings.labels = next.labels;
        if (typeof next.rings === "boolean")
            mapSettings.rings = next.rings;
        if (typeof next.night === "boolean")
            mapSettings.night = next.night;
        updateRangeRings();
        if (terrainData)
            renderTerrain();
    };
    window.COMSPEC_ATAK_liveMapShow = function (worldName, worldSize) {
        if (typeof window.L === "undefined" && window.leaflet)
            window.L = window.leaflet;
        var m = ensure(worldName, worldSize);
        var el = host();
        if (el)
            el.setAttribute("data-live-map", "1");
        if (m) {
            setTimeout(function () {
                try {
                    m.invalidateSize({ animate: false });
                }
                catch (e) { }
                reportScale();
                refreshGrid();
            }, 60);
            setTimeout(function () {
                try {
                    m.invalidateSize({ animate: false });
                }
                catch (e2) { }
            }, 400);
        }
        return !!m;
    };
    window.COMSPEC_ATAK_liveMapHide = function () {
        var el = host();
        if (el)
            el.removeAttribute("data-live-map");
    };
    window.COMSPEC_ATAK_liveMapCenter = function () {
        if (!map)
            return;
        if (!Number.isFinite(lastPlayer.x) || !Number.isFinite(lastPlayer.y))
            return;
        map.setView(window.L.latLng(lastPlayer.y, lastPlayer.x), Math.max(map.getZoom(), 4), { animate: false });
        didCenter = true;
        reportScale();
    };
    window.COMSPEC_ATAK_liveMapZoom = function (dir) {
        if (!map)
            return;
        var next = map.getZoom() + (String(dir) === "out" ? -1 : 1);
        map.setZoom(Math.max(0, Math.min(7, next)));
        reportScale();
    };
    window.COMSPEC_ATAK_liveMapTexture = function (tex) {
        texture = String(tex || "SAT").toUpperCase() === "TOPO" ? "TOPO" : "SAT";
        applyTextureClass();
    };
    window.COMSPEC_ATAK_liveMapLayer = function (layer, on) {
        layer = String(layer || "");
        if (layer === "friendly")
            layers.friendly = on !== false;
        else if (layer === "hostile")
            layers.hostile = on !== false;
        else if (layer === "intel")
            layers.intel = on !== false;
        else if (layer === "bft")
            layers.bft = on !== false;
        else if (layer === "grid") {
            layers.grid = on !== false;
            refreshGrid();
        }
    };
    window.COMSPEC_ATAK_liveMapGoto = function (x, y, zoom) {
        var m = map || ensure((window.COMSPEC_ATAK_STATE && window.COMSPEC_ATAK_STATE.world) || "altis");
        if (!m)
            return;
        var z = Number(zoom);
        if (!Number.isFinite(z) || z <= 1)
            z = 4;
        z = Math.max(0, Math.min(7, Math.round(z)));
        m.setView(window.L.latLng(Number(y) || 0, Number(x) || 0), z, { animate: false });
        didCenter = true;
        reportScale();
    };
    window.COMSPEC_ATAK_liveMapSaveView = function (name) {
        name = String(name || "Vue").replace(/\|/g, " ");
        var x = lastPlayer.x;
        var y = lastPlayer.y;
        var z = 4;
        if (map) {
            var c = map.getCenter();
            x = c.lng;
            y = c.lat;
            z = map.getZoom();
        }
        send("view:commit|" + name + "|" + Number(x).toFixed(2) + "|" + Number(y).toFixed(2) + "|" + z + "|" + texture);
    };
    window.COMSPEC_ATAK_liveMapPulse = function (x, y) { pulsePing(Number(y) || 0, Number(x) || 0); };
    window.COMSPEC_ATAK_liveMapUpdate = updateFromTelemetry;
    window.COMSPEC_ATAK_liveMapApply = applyState;
    window.COMSPEC_ATAK_getMap = function () { return map; };
    var prevTel = window.COMSPEC_ATAK_applyTelemetry;
    window.COMSPEC_ATAK_applyTelemetry = function (t) {
        if (typeof prevTel === "function")
            prevTel(t);
        try {
            updateFromTelemetry(t);
        }
        catch (e) { }
    };
    var prevApply = window.COMSPEC_ATAK_apply;
    window.COMSPEC_ATAK_apply = function (next) {
        var r = typeof prevApply === "function" ? prevApply(next) : undefined;
        try {
            applyState(window.COMSPEC_ATAK_STATE || next || {});
        }
        catch (e) { }
        return r;
    };
    function bootPreview() {
        var preview = /(?:\?|&)preview=map(?:&|$)/.test(String(location.search || ""));
        if (preview) {
            document.body.classList.remove("boot-lock-active");
            var gate = document.getElementById("bootGate");
            if (gate)
                gate.hidden = true;
            var desktop = document.getElementById("phoneDesktop");
            if (desktop)
                desktop.classList.remove("active");
            var pair = document.getElementById("pairingPanel");
            if (pair)
                pair.hidden = true;
            if (window.COMSPEC_ATAK_UI && typeof window.COMSPEC_ATAK_UI.setPhoneApp === "function") {
                window.COMSPEC_ATAK_UI.setPhoneApp("overwatch", false);
            }
        }
        var world = (window.COMSPEC_ATAK_STATE && window.COMSPEC_ATAK_STATE.world) || "altis";
        var shown = window.COMSPEC_ATAK_liveMapShow(world, 30720);
        if (!shown)
            return;
        if (preview) {
            updateFromTelemetry({
                coordX: 14600,
                coordY: 16800,
                heading: 42,
                world: "altis",
                worldSize: 30720,
                contacts: [
                    { id: "g1", cs: "ALPHA-1", x: 14820, y: 16940, h: 90, k: "g" },
                    { id: "b1", cs: "BRAVO-2", x: 14110, y: 16480, h: 210, k: "b" }
                ]
            });
            if (window.COMSPEC_MapEngine && !window.COMSPEC_MapEngine.get("draw_preview_zone_rouge")) {
                window.COMSPEC_MapEngine.commit("danger", [
                    [14440, 16640],
                    [14780, 16580],
                    [14860, 16920],
                    [14510, 17010]
                ], { id: "draw_preview_zone_rouge", name: "ZONE ROUGE", silent: true, style: { color: "#e24a46", fillOpacity: 0.22 } });
            }
        }
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bootPreview);
    }
    else {
        setTimeout(bootPreview, 30);
    }
})();
