/* Moteur de scène cartographique — objets structurés, journal, zones, modes. */
(function () {
    "use strict";
    var STORE_KEY = "comspec_atak_scene_v1";
    var MAX_EVENTS = 800;
    var map = null;
    var drawLayer = null;
    var maskLayer = null;
    var leafletById = {};
    var draftPts = [];
    var draftLayer = null;
    var tool = "none";
    var mode = "flat";
    var replayAt = null;
    var author = "LOCAL";
    var world = "altis";
    var pendingTemplate = null;
    var listeners = [];
    var state = {
        objects: {},
        events: [],
        ao: null,
        seq: 1
    };
    var TOOL_NEEDS = {
        point: 1, text: 1, rally: 1,
        line: 2, arrow: 2, measure: 2, rectangle: 2, circle: 2, ellipse: 2, corridor: 2, axis: 2,
        polyline: 0, polygon: 0, freehand: 0, danger: 0, exclusion: 0, sector: 0, ao: 0, area: 0
    };
    function now() { return Date.now(); }
    function emit() {
        listeners.forEach(function (fn) { try {
            fn(snapshot());
        }
        catch (e) { } });
    }
    function snapshot() {
        return {
            mode: mode,
            tool: tool,
            replayAt: replayAt,
            ao: state.ao,
            count: Object.keys(state.objects).length,
            events: state.events.length,
            objects: visibleObjects()
        };
    }
    function save() {
        var snap = {
            objects: state.objects,
            events: state.events.slice(-MAX_EVENTS),
            ao: state.ao,
            seq: state.seq
        };
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify(snap));
        }
        catch (e) { }
        if (window.COMSPEC_MapStore) {
            window.COMSPEC_MapStore.put("kv", "scene", snap);
        }
    }
    function load() {
        function apply(parsed) {
            if (!parsed || typeof parsed !== "object")
                return;
            state.objects = parsed.objects || {};
            state.events = Array.isArray(parsed.events) ? parsed.events : [];
            state.ao = parsed.ao || null;
            state.seq = Number(parsed.seq) || 1;
        }
        try {
            var raw = localStorage.getItem(STORE_KEY);
            if (raw)
                apply(JSON.parse(raw));
        }
        catch (e) { }
        if (window.COMSPEC_MapStore) {
            window.COMSPEC_MapStore.get("kv", "scene").then(function (row) {
                if (row && row.objects) {
                    apply(row);
                    emit();
                    if (map) {
                        try {
                            repaintAll();
                        }
                        catch (e2) { }
                    }
                }
            });
        }
    }
    function nextId(prefix) {
        state.seq += 1;
        return prefix + "_" + state.seq.toString(16);
    }
    function eventTypeFor(kind, obj) {
        if (kind === "delete") {
            if (obj && (obj.type === "point" || obj.type === "rally" || obj.type === "text"))
                return "marker.deleted";
            return "map.object.deleted";
        }
        if (kind === "mode")
            return "map.view.changed";
        var t = obj && obj.type;
        if (t === "ao" || t === "danger" || t === "exclusion" || t === "area")
            return "zone.created";
        if (t === "point" || t === "rally" || t === "text")
            return "marker.created";
        if (t === "corridor" || t === "axis")
            return "route.created";
        if (kind === "create")
            return "drawing.created";
        return "map.object.updated";
    }
    function record(kind, id, extra) {
        var obj = state.objects[id];
        var type = eventTypeFor(kind, obj);
        var payload = { object: obj || { id: id, type: kind, name: extra } };
        var full = window.COMSPEC_MapBus
            ? window.COMSPEC_MapBus.event(type, payload)
            : { t: now(), type: type, timestamp: new Date().toISOString() };
        var ev = {
            t: full.t || now(),
            kind: type,
            id: id,
            extra: extra || ""
        };
        state.events.push(ev);
        if (state.events.length > MAX_EVENTS)
            state.events.splice(0, state.events.length - MAX_EVENTS);
        save();
        emit();
        return ev;
    }
    function ll(pt) {
        return window.L.latLng(pt[1], pt[0]);
    }
    function styleOf(obj) {
        var s = obj.style || {};
        var type = obj.type;
        var color = s.color;
        if (!color) {
            if (type === "danger" || type === "exclusion")
                color = "#e24a46";
            else if (type === "ao")
                color = "#6ae9c4";
            else if (type === "axis" || type === "arrow")
                color = "#f0b43d";
            else if (type === "corridor")
                color = "#4aa8ea";
            else
                color = "#7ee0c4";
        }
        return {
            color: color,
            weight: Number(s.strokeWidth) || 2,
            fillColor: color,
            fillOpacity: s.fillOpacity != null ? Number(s.fillOpacity) : (type === "polygon" || type === "danger" || type === "ao" || type === "exclusion" || type === "area" || type === "circle" || type === "ellipse" || type === "sector" ? 0.18 : 0),
            dashArray: s.dash ? "7 5" : null
        };
    }
    function ring(center, r, n) {
        n = n || 48;
        var pts = [];
        var i;
        for (i = 0; i <= n; i += 1) {
            var a = (i / n) * Math.PI * 2;
            pts.push([center[0] + r * Math.sin(a), center[1] + r * Math.cos(a)]);
        }
        return pts;
    }
    function ellipsePts(a, b) {
        var cx = (a[0] + b[0]) / 2;
        var cy = (a[1] + b[1]) / 2;
        var rx = Math.abs(b[0] - a[0]) / 2;
        var ry = Math.abs(b[1] - a[1]) / 2;
        var pts = [];
        var i;
        for (i = 0; i <= 48; i += 1) {
            var t = (i / 48) * Math.PI * 2;
            pts.push([cx + rx * Math.sin(t), cy + ry * Math.cos(t)]);
        }
        return pts;
    }
    function rectPts(a, b) {
        return [[a[0], a[1]], [b[0], a[1]], [b[0], b[1]], [a[0], b[1]]];
    }
    function dist(a, b) {
        var dx = b[0] - a[0], dy = b[1] - a[1];
        return Math.sqrt(dx * dx + dy * dy);
    }
    function polygonArea(pts) {
        var n = pts.length;
        if (n < 3)
            return 0;
        var s = 0;
        var i;
        for (i = 0; i < n; i += 1) {
            var p = pts[i];
            var q = pts[(i + 1) % n];
            s += p[0] * q[1] - q[0] * p[1];
        }
        return Math.abs(s) / 2;
    }
    function pointInPoly(x, y, pts) {
        var inside = false;
        var i, j;
        for (i = 0, j = pts.length - 1; i < pts.length; j = i++) {
            var xi = pts[i][0], yi = pts[i][1];
            var xj = pts[j][0], yj = pts[j][1];
            if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-9) + xi))
                inside = !inside;
        }
        return inside;
    }
    function geomPts(obj) {
        var t = obj.type;
        var p = obj.points || [];
        if (t === "circle" && p.length >= 2)
            return ring(p[0], dist(p[0], p[1]));
        if (t === "ellipse" && p.length >= 2)
            return ellipsePts(p[0], p[1]);
        if (t === "rectangle" && p.length >= 2)
            return rectPts(p[0], p[1]);
        if (t === "sector" && p.length >= 3) {
            var c = p[0];
            var r = dist(c, p[1]);
            var a0 = Math.atan2(p[1][0] - c[0], p[1][1] - c[1]);
            var a1 = Math.atan2(p[2][0] - c[0], p[2][1] - c[1]);
            var out = [c];
            var steps = 16;
            var i;
            for (i = 0; i <= steps; i += 1) {
                var a = a0 + (a1 - a0) * (i / steps);
                out.push([c[0] + r * Math.sin(a), c[1] + r * Math.cos(a)]);
            }
            return out;
        }
        return p;
    }
    function objectVisible(obj) {
        if (!obj)
            return false;
        if (replayAt != null) {
            if (obj.createdAt > replayAt)
                return false;
            var gone = state.events.some(function (ev) {
                var k = String(ev.kind || "");
                return (k === "delete" || k.indexOf("deleted") !== -1) && ev.id === obj.id && ev.t <= replayAt && ev.t >= obj.createdAt;
            });
            if (gone)
                return false;
        }
        else if (obj.deleted) {
            return false;
        }
        if (state.ao && state.ao.points && obj.points && obj.points[0] && obj.type !== "ao") {
            if (!pointInPoly(obj.points[0][0], obj.points[0][1], state.ao.points))
                return false;
        }
        return true;
    }
    function visibleObjects() {
        var out = [];
        Object.keys(state.objects).forEach(function (id) {
            var obj = state.objects[id];
            if (objectVisible(obj))
                out.push(obj);
        });
        return out;
    }
    function removeLeaflet(id) {
        if (leafletById[id] && map) {
            try {
                map.removeLayer(leafletById[id]);
            }
            catch (e) { }
        }
        delete leafletById[id];
    }
    function paintObject(obj) {
        if (!map || typeof window.L === "undefined")
            return;
        removeLeaflet(obj.id);
        if (!objectVisible(obj))
            return;
        var pts = geomPts(obj);
        if (!pts.length)
            return;
        var st = styleOf(obj);
        var layer;
        if (pts.length === 1 || obj.type === "point" || obj.type === "text" || obj.type === "rally") {
            layer = window.L.marker(ll(pts[0]), {
                icon: window.L.divIcon({
                    className: "scene-symbol scene-" + obj.type,
                    html: "<span></span><em>" + String(obj.name || "").replace(/[<>&]/g, "") + "</em>",
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                }),
                zIndexOffset: 350
            });
        }
        else if (obj.type === "arrow" || obj.type === "axis") {
            var line = window.L.polyline(pts.map(ll), st);
            var tip = pts[pts.length - 1];
            var prev = pts[pts.length - 2] || pts[0];
            var ang = Math.atan2(tip[0] - prev[0], tip[1] - prev[1]);
            var ah = 32;
            var left = [tip[0] - ah * Math.sin(ang - 0.42), tip[1] - ah * Math.cos(ang - 0.42)];
            var right = [tip[0] - ah * Math.sin(ang + 0.42), tip[1] - ah * Math.cos(ang + 0.42)];
            var head = window.L.polygon([ll(left), ll(tip), ll(right)], Object.assign({}, st, { fillOpacity: 0.92 }));
            layer = window.L.featureGroup([line, head]);
        }
        else if (obj.type === "line" || obj.type === "polyline" || obj.type === "measure" || obj.type === "corridor" || obj.type === "freehand") {
            layer = window.L.polyline(pts.map(ll), st);
        }
        else {
            layer = window.L.polygon(pts.map(ll), st);
        }
        layer.addTo(drawLayer);
        leafletById[obj.id] = layer;
        layer.on("click", function (ev) {
            if (window.L)
                window.L.DomEvent.stopPropagation(ev);
            openSheet(obj);
        });
    }
    function repaintAll() {
        if (!drawLayer)
            return;
        drawLayer.clearLayers();
        leafletById = {};
        visibleObjects().forEach(paintObject);
        paintAoMask();
    }
    function paintAoMask() {
        if (!map || !maskLayer)
            return;
        maskLayer.clearLayers();
        if (!state.ao || !state.ao.points || state.ao.points.length < 3)
            return;
        var worldSize = 40000;
        var outer = [[0, 0], [worldSize, 0], [worldSize, worldSize], [0, worldSize]];
        window.L.polygon([outer.map(ll), state.ao.points.map(ll)], {
            color: "#6ae9c4",
            weight: 2,
            fillColor: "#050807",
            fillOpacity: 0.72,
            interactive: false
        }).addTo(maskLayer);
    }
    function setHint(text) {
        var el = document.getElementById("sceneHint");
        if (el)
            el.textContent = text || "";
        var wrap = document.getElementById("sceneHintBar");
        if (wrap)
            wrap.hidden = !text;
    }
    function openSheet(obj) {
        var sheet = document.getElementById("sceneObjectSheet");
        if (!sheet)
            return;
        sheet.hidden = false;
        var title = sheet.querySelector("[data-scene-title]");
        var meta = sheet.querySelector("[data-scene-meta]");
        var fields = sheet.querySelector("[data-scene-fields]");
        if (title)
            title.textContent = obj.name || "Objet";
        if (meta) {
            var scopeLabel = {
                local: "Moi seulement",
                session: "Cette session",
                group: "Mon groupe",
                mission: "Cette mission",
                tenant: "La communauté",
                server: "Ce serveur",
                public: "Public"
            }[obj.scope] || "Cette session";
            meta.textContent = (obj.persistent ? "Conservé · " : "Temporaire · ") + scopeLabel + " · " + (obj.author || author);
        }
        if (fields) {
            var rows = obj.fields || {};
            var keys = Object.keys(rows);
            fields.innerHTML = keys.length
                ? keys.map(function (k) {
                    return "<div><span>" + String(k).replace(/[<>&]/g, "") + "</span><b>" + String(rows[k]).replace(/[<>&]/g, "") + "</b></div>";
                }).join("")
                : "<p>Aucun renseignement complémentaire.</p>";
        }
        sheet.dataset.objectId = obj.id;
    }
    function commit(type, points, extras) {
        extras = extras || {};
        var id = extras.id || nextId(type === "ao" ? "ao" : "draw");
        var obj = {
            id: id,
            type: type,
            name: extras.name || defaultName(type),
            points: points,
            style: extras.style || {},
            metadata: {
                author: extras.author || author,
                createdAt: extras.createdAt || now(),
                persistent: extras.persistent !== false,
                world: world
            },
            author: extras.author || author,
            createdAt: extras.createdAt || now(),
            persistent: extras.persistent !== false,
            scope: extras.scope || "session",
            fields: extras.fields || {},
            z: extras.z || 0,
            deleted: false
        };
        state.objects[id] = obj;
        if (type === "ao")
            state.ao = obj;
        record("create", id, type + " " + obj.name);
        paintObject(obj);
        if (type === "ao")
            paintAoMask();
        emit();
        if (!extras.silent)
            openSheet(obj);
        return obj;
    }
    function defaultName(type) {
        return ({
            polygon: "Zone",
            danger: "Zone de danger",
            exclusion: "Zone d’exclusion",
            ao: "Zone d’intérêt",
            area: "Emprise",
            circle: "Cercle",
            ellipse: "Ellipse",
            rectangle: "Rectangle",
            polyline: "Tracé",
            line: "Ligne",
            arrow: "Flèche",
            axis: "Axe d’effort",
            corridor: "Couloir",
            sector: "Secteur d’observation",
            freehand: "Croquis",
            measure: "Mesure",
            rally: "Point de regroupement",
            text: "Annotation",
            point: "Point"
        })[type] || "Dessin";
    }
    function finishDraft(force) {
        var need = TOOL_NEEDS[tool];
        if (need === 0) {
            if (!force && draftPts.length < 3 && (tool === "polygon" || tool === "danger" || tool === "exclusion" || tool === "ao" || tool === "area" || tool === "sector")) {
                setHint("Encore un point, ou validez la forme.");
                return;
            }
            if (draftPts.length < 2)
                return;
        }
        else if (draftPts.length < need) {
            return;
        }
        var type = tool;
        var extras = {};
        if (pendingTemplate) {
            extras.name = pendingTemplate.name;
            extras.fields = pendingTemplate.fields || {};
            extras.style = { color: pendingTemplate.color || "#e24a46" };
            extras.scope = pendingTemplate.scope || "session";
            type = pendingTemplate.drawType || "point";
        }
        if (!extras.scope) {
            extras.scope = (document.getElementById("sceneScope") || {}).value || "session";
        }
        if (type === "measure" && draftPts.length >= 2) {
            extras.name = Math.round(dist(draftPts[0], draftPts[draftPts.length - 1])) + " m";
        }
        commit(type, draftPts.slice(), extras);
        draftPts = [];
        pendingTemplate = null;
        if (draftLayer && map)
            try {
                map.removeLayer(draftLayer);
            }
            catch (e) { }
        draftLayer = null;
        setTool("none");
    }
    function previewDraft() {
        if (!map || typeof window.L === "undefined")
            return;
        if (draftLayer)
            try {
                map.removeLayer(draftLayer);
            }
            catch (e) { }
        if (draftPts.length < 1)
            return;
        var lls = draftPts.map(ll);
        if (draftPts.length === 1) {
            draftLayer = window.L.circleMarker(lls[0], { radius: 5, color: "#f0b43d" }).addTo(map);
        }
        else {
            draftLayer = window.L.polyline(lls, { color: "#f0b43d", dashArray: "5 4", weight: 2 }).addTo(map);
        }
    }
    function clickAt(x, y) {
        if (replayAt != null)
            return false;
        if (tool === "none")
            return false;
        draftPts.push([x, y]);
        previewDraft();
        var need = TOOL_NEEDS[tool];
        if (need === 1) {
            finishDraft(true);
            return true;
        }
        if (need === 2 && draftPts.length >= 2) {
            finishDraft(true);
            return true;
        }
        if (need === 0) {
            setHint("Cliquez pour ajouter un point · double-clic ou Valider pour terminer (" + draftPts.length + ")");
        }
        return true;
    }
    function applyMode() {
        var root = document.getElementById("overwatchApp");
        if (!root)
            return;
        root.classList.remove("scene-flat", "scene-relief", "scene-slope", "scene-heatmap", "scene-tactical");
        root.classList.add("scene-" + mode);
        if (typeof window.COMSPEC_ATAK_liveMapMode === "function")
            window.COMSPEC_ATAK_liveMapMode(mode);
        if (map)
            setTimeout(function () { try {
                map.invalidateSize({ animate: false });
            }
            catch (e) { } }, 80);
        emit();
    }
    function setTool(next) {
        tool = String(next || "none").toLowerCase();
        draftPts = [];
        if (draftLayer && map)
            try {
                map.removeLayer(draftLayer);
            }
            catch (e) { }
        draftLayer = null;
        var labels = {
            none: "",
            polygon: "Polygone · cliquez les sommets, puis Valider",
            polyline: "Ligne brisée · cliquez les points, puis Valider",
            line: "Ligne · deux clics",
            arrow: "Flèche · origine puis pointe",
            rectangle: "Rectangle · deux coins",
            circle: "Cercle · centre puis rayon",
            ellipse: "Ellipse · deux coins",
            freehand: "Croquis live · tracez au clic gauche · clic droit pour terminer",
            measure: "Mesure · deux points",
            axis: "Axe d’effort · origine puis direction",
            danger: "Zone de danger · cliquez le contour, puis Valider",
            exclusion: "Zone d’exclusion · contour, puis Valider",
            sector: "Secteur · centre, ouverture, puis Valider",
            corridor: "Couloir · deux points d’axe",
            rally: "Point de regroupement · un clic",
            ao: "Zone d’intérêt · contour, puis Valider",
            area: "Emprise · contour, puis Valider",
            text: "Texte · un clic",
            point: "Point · un clic"
        };
        setHint(labels[tool] || "");
        var finish = document.getElementById("sceneFinish");
        if (finish)
            finish.hidden = TOOL_NEEDS[tool] !== 0;
        emit();
    }
    function setMode(next) {
        next = String(next || "flat").toLowerCase();
        if (["flat", "relief", "slope", "heatmap", "tactical"].indexOf(next) < 0)
            next = "flat";
        mode = next;
        applyMode();
        record("mode", "scene", mode);
    }
    function setReplay(ts) {
        replayAt = ts == null || ts === "" ? null : Number(ts);
        if (replayAt != null && !Number.isFinite(replayAt))
            replayAt = null;
        repaintAll();
        emit();
    }
    function attach(leafletMap, opts) {
        opts = opts || {};
        map = leafletMap;
        if (opts.author)
            author = opts.author;
        if (opts.world)
            world = opts.world;
        if (!map)
            return;
        if (drawLayer)
            try {
                map.removeLayer(drawLayer);
            }
            catch (e) { }
        if (maskLayer)
            try {
                map.removeLayer(maskLayer);
            }
            catch (e) { }
        drawLayer = window.L.layerGroup().addTo(map);
        maskLayer = window.L.layerGroup().addTo(map);
        map.on("dblclick", function () {
            if (TOOL_NEEDS[tool] === 0)
                finishDraft(true);
        });
        applyMode();
        repaintAll();
    }
    function removeObject(id) {
        var obj = state.objects[id];
        if (!obj)
            return false;
        obj.deleted = true;
        record("delete", id, obj.type);
        removeLeaflet(id);
        if (state.ao && state.ao.id === id) {
            state.ao = null;
            paintAoMask();
        }
        save();
        emit();
    }
    function pointSegDist(px, py, a, b) {
        var ax = Number(a[0]), ay = Number(a[1]), bx = Number(b[0]), by = Number(b[1]);
        var vx = bx - ax, vy = by - ay, wx = px - ax, wy = py - ay;
        var c1 = vx * wx + vy * wy;
        if (c1 <= 0)
            return Math.sqrt((px - ax) * (px - ax) + (py - ay) * (py - ay));
        var c2 = vx * vx + vy * vy;
        if (c2 <= c1)
            return Math.sqrt((px - bx) * (px - bx) + (py - by) * (py - by));
        var t = c1 / c2, qx = ax + t * vx, qy = ay + t * vy;
        return Math.sqrt((px - qx) * (px - qx) + (py - qy) * (py - qy));
    }
    function containsPoint(pts, x, y) {
        if (!Array.isArray(pts) || pts.length < 3)
            return false;
        var inside = false;
        for (var i = 0, j = pts.length - 1; i < pts.length; j = i++) {
            var xi = Number(pts[i][0]), yi = Number(pts[i][1]), xj = Number(pts[j][0]), yj = Number(pts[j][1]);
            var hit = ((yi > y) != (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-9) + xi);
            if (hit)
                inside = !inside;
        }
        return inside;
    }
    function nearestObject(x, y, maxDist) {
        var best = null, bd = Number(maxDist) || 260;
        visibleObjects().forEach(function (obj) {
            var pts = Array.isArray(obj.points) ? obj.points : [];
            if (!pts.length)
                return;
            var d = Infinity;
            if (["polygon", "danger", "exclusion", "ao", "area", "rectangle", "ellipse", "sector"].indexOf(String(obj.type || "")) >= 0 && containsPoint(pts, x, y)) {
                d = 0;
            }
            else {
                for (var i = 0; i < pts.length; i++) {
                    var p = pts[i];
                    if (!p || p.length < 2)
                        continue;
                    var vd = Math.sqrt((Number(p[0]) - x) * (Number(p[0]) - x) + (Number(p[1]) - y) * (Number(p[1]) - y));
                    if (vd < d)
                        d = vd;
                    if (i > 0) {
                        var sd = pointSegDist(x, y, pts[i - 1], p);
                        if (sd < d)
                            d = sd;
                    }
                }
            }
            if (d < bd) {
                bd = d;
                best = obj;
            }
        });
        return best;
    }
    function beginFreehand(x, y) {
        tool = "freehand";
        draftPts = [[Number(x) || 0, Number(y) || 0]];
        previewDraft();
        setHint("Dessin live · maintenez le clic gauche");
    }
    function appendFreehand(x, y) {
        if (tool !== "freehand" || !draftPts.length)
            return;
        var last = draftPts[draftPts.length - 1], dx = (Number(x) || 0) - last[0], dy = (Number(y) || 0) - last[1];
        if (Math.sqrt(dx * dx + dy * dy) < 5)
            return;
        draftPts.push([Number(x) || 0, Number(y) || 0]);
        if (draftPts.length > 600)
            draftPts.splice(1, 1);
        previewDraft();
    }
    function endFreehand() {
        if (tool !== "freehand")
            return false;
        if (draftPts.length >= 2) {
            commit("freehand", draftPts.slice(), { name: "Croquis" });
        }
        draftPts = [];
        if (draftLayer && map)
            try {
                map.removeLayer(draftLayer);
            }
            catch (e) { }
        draftLayer = null;
        tool = "freehand";
        setHint("Dessin live · clic gauche pour tracer · clic droit pour terminer");
        emit();
        return true;
    }
    function finishFreehandMode() {
        if (tool !== "freehand")
            return false;
        if (draftPts.length >= 2) {
            commit("freehand", draftPts.slice(), { name: "Croquis" });
        }
        draftPts = [];
        if (draftLayer && map)
            try {
                map.removeLayer(draftLayer);
            }
            catch (e) { }
        draftLayer = null;
        tool = "none";
        setHint("");
        emit();
        return true;
    }
    window.COMSPEC_MapEngine = {
        attach: attach,
        clickAt: clickAt,
        setTool: setTool,
        getTool: function () { return tool; },
        setMode: setMode,
        getMode: function () { return mode; },
        setReplay: setReplay,
        finish: function () { finishDraft(true); },
        cancel: function () { setTool("none"); setHint(""); },
        commit: commit,
        get: function (id) { return state.objects[id] || null; },
        remove: removeObject,
        nearest: nearestObject,
        beginFreehand: beginFreehand,
        appendFreehand: appendFreehand,
        endFreehand: endFreehand,
        finishFreehandMode: finishFreehandMode,
        snapshot: snapshot,
        events: function () { return state.events.slice(); },
        objects: function () { return visibleObjects(); },
        subscribe: function (fn) { listeners.push(fn); },
        setAuthor: function (name) { author = name || author; },
        setWorld: function (name) { world = name || world; },
        armTemplate: function (tpl) {
            pendingTemplate = tpl;
            setTool(tpl && tpl.drawType ? tpl.drawType : "point");
        },
        clearAo: function () {
            if (state.ao)
                removeObject(state.ao.id);
        },
        inAo: function (x, y) {
            if (!state.ao || !state.ao.points)
                return true;
            return pointInPoly(x, y, state.ao.points);
        }
    };
    load();
    document.addEventListener("click", function (ev) {
        var modeBtn = ev.target.closest("[data-scene-mode]");
        if (modeBtn) {
            setMode(modeBtn.getAttribute("data-scene-mode"));
            return;
        }
        var toolBtn = ev.target.closest("[data-scene-tool]");
        if (toolBtn) {
            setTool(toolBtn.getAttribute("data-scene-tool"));
            if (window.COMSPEC_ATAK_UI && typeof window.COMSPEC_ATAK_UI.closePanel === "function") {
                window.COMSPEC_ATAK_UI.closePanel(false);
            }
            return;
        }
        if (ev.target.closest("#sceneFinish")) {
            finishDraft(true);
            return;
        }
        if (ev.target.closest("#sceneCancel")) {
            setTool("none");
            setHint("");
            return;
        }
        if (ev.target.closest("#sceneSheetClose")) {
            var sheet = document.getElementById("sceneObjectSheet");
            if (sheet)
                sheet.hidden = true;
            return;
        }
        if (ev.target.closest("#sceneSheetDelete")) {
            var sheet2 = document.getElementById("sceneObjectSheet");
            if (sheet2 && sheet2.dataset.objectId) {
                removeObject(sheet2.dataset.objectId);
                sheet2.hidden = true;
            }
            return;
        }
        if (ev.target.closest("#sceneReplayLive")) {
            setReplay(null);
            var slider = document.getElementById("sceneReplaySlider");
            if (slider)
                slider.value = slider.max || 0;
        }
    });
    document.addEventListener("input", function (ev) {
        if (ev.target && ev.target.id === "sceneReplaySlider") {
            var events = state.events;
            if (!events.length)
                return;
            var idx = Number(ev.target.value) || 0;
            var evn = events[Math.max(0, Math.min(events.length - 1, idx))];
            setReplay(evn ? evn.t : null);
        }
    });
    listeners.push(function () {
        var slider = document.getElementById("sceneReplaySlider");
        if (slider) {
            slider.max = Math.max(0, state.events.length - 1);
            if (replayAt == null)
                slider.value = slider.max;
        }
    });
})();
