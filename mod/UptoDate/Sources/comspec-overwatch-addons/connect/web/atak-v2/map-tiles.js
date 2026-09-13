/* RÃ©solveur de tuiles : lâ€™Ã©cran ne voit que /map-data/{monde}/{z}/{x}/{y}. */
(function () {
    "use strict";
    var LOGICAL = "/map-data/{world}/{z}/{x}/{y}.webp";
    /* Sources distantes : jamais exposÃ©es Ã  lâ€™UI. Premier affichage IMMÃ‰DIAT. */
    var REMOTE = [
        "https://jetelain.github.io/Arma3Map/maps/{world}/{z}/{x}/{y}.png",
        "https://cdn.jsdelivr.net/gh/jetelain/Arma3Map@gh-pages/maps/{world}/{z}/{x}/{y}.png",
        "https://mapsdata.plan-ops.fr/maps/{world}/{z}/{x}/{y}.png",
        "https://athena.ttrd.fr/assets/maps/{world}/{z}/{x}/{y}.png",
        "https://athena.ttrd.fr/public/assets/maps/{world}/{z}/{x}/{y}.png",
        "http://127.0.0.1:28745/maps/{world}/{z}/{x}/{y}.png"
    ];
    var TRANSPARENT = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
    var inflight = {};
    var kept = {};
    var failCount = 0;
    function worldCfg(world) {
        if (window.COMSPEC_MapTheatres && window.COMSPEC_MapTheatres.resolve) {
            return window.COMSPEC_MapTheatres.resolve(world);
        }
        return { slug: String(world || "altis").toLowerCase(), tileSize: 256, worldSize: 30720, maxZoom: 7 };
    }
    function worldSlug(world) {
        return String(worldCfg(world).slug || world || "altis").toLowerCase();
    }
    function key(world, z, x, y) {
        return worldSlug(world) + "/" + z + "/" + x + "/" + y;
    }
    function logicalUrl(world, z, x, y) {
        return LOGICAL
            .replace("{world}", worldSlug(world))
            .replace("{z}", String(z))
            .replace("{x}", String(x))
            .replace("{y}", String(y));
    }
    function fill(tpl, world, z, x, y) {
        return tpl
            .replace("{world}", worldSlug(world))
            .replace("{z}", String(z))
            .replace("{x}", String(x))
            .replace("{y}", String(y));
    }
    function remotes(world, z, x, y) {
        return REMOTE.map(function (tpl) { return fill(tpl, world, z, x, y); });
    }
    function blobUrl(blob) {
        try {
            return URL.createObjectURL(blob);
        }
        catch (e) {
            return "";
        }
    }
    /* Seuls blob: et data: peuvent remplacer un src dÃ©jÃ  posÃ©. Jamais /map-data ni file://. */
    function isCachePaintUrl(url) {
        var u = String(url || "");
        return u.indexOf("blob:") === 0 || u.indexOf("data:image/") === 0;
    }
    function isRemotePaintUrl(url) {
        var u = String(url || "");
        return u.indexOf("http://127.0.0.1:28745/") === 0
            || u.indexOf("https://athena.ttrd.fr/") === 0
            || u.indexOf("https://mapsdata.plan-ops.fr/") === 0
            || u.indexOf("https://jetelain.github.io/") === 0
            || u.indexOf("https://cdn.jsdelivr.net/") === 0;
    }
    function fromCache(id) {
        var store = window.COMSPEC_MapStore;
        if (!store)
            return Promise.resolve(null);
        return store.get("tiles", id).then(function (row) {
            if (!row)
                return null;
            if (row.blob)
                return blobUrl(row.blob);
            if (row.url && isCachePaintUrl(row.url))
                return row.url;
            return null;
        }).then(function (v) { return v; }, function () { return null; });
    }
    function toCache(id, blob) {
        var store = window.COMSPEC_MapStore;
        if (!store || !blob)
            return;
        store.put("tiles", id, { blob: blob, at: Date.now() });
    }
    function fetchOne(url) {
        if (typeof fetch !== "function")
            return Promise.reject(new Error("fetch"));
        var ctrl = typeof AbortController !== "undefined" ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { try {
            ctrl.abort();
        }
        catch (e) { } }, 2200) : 0;
        return fetch(url, { mode: "cors", cache: "force-cache", signal: ctrl ? ctrl.signal : undefined }).then(function (res) {
            if (timer)
                clearTimeout(timer);
            if (!res || !res.ok)
                throw new Error("tile");
            return res.blob();
        }, function (err) {
            if (timer)
                clearTimeout(timer);
            throw err;
        });
    }
    function cacheLater(id, urls) {
        if (inflight[id])
            return;
        inflight[id] = true;
        var chain = Promise.reject();
        urls.forEach(function (url) {
            chain = chain.then(function (v) { return v; }, function () { return fetchOne(url); });
        });
        chain.then(function (blob) {
            toCache(id, blob);
        }).then(function () { }, function () { });
        setTimeout(function () { delete inflight[id]; }, 8000);
    }
    function keepOnDisk(world, z, x, y) {
        var id = key(world, z, x, y);
        if (kept[id])
            return;
        kept[id] = true;
        if (typeof window.COMSPEC_ATAK_send === "function") {
            window.COMSPEC_ATAK_send("map:pack:keep|" + world + "|" + z + "|" + x + "|" + y);
        }
    }
    function load(world, z, x, y) {
        world = worldSlug(world);
        var urls = remotes(world, z, x, y);
        cacheLater(key(world, z, x, y), urls);
        return Promise.resolve(urls[0] || TRANSPARENT);
    }
    function paintTile(img, world, z, x, y, done) {
        var id = key(world, z, x, y);
        var urls = remotes(world, z, x, y);
        var idx = 0;
        var finished = false;
        var paintedOk = false;
        function finish() {
            if (finished)
                return;
            finished = true;
            if (!paintedOk) {
                img.src = TRANSPARENT;
            }
            if (typeof done === "function")
                done(null, img);
        }
        img.onload = function () {
            var src = String(img.src || "");
            if (src === TRANSPARENT || src.indexOf("data:image/gif") === 0) {
                finish();
                return;
            }
            paintedOk = true;
            try {
                var source = src.indexOf("127.0.0.1:28745") >= 0 ? "LOCAL PROXY"
                    : (src.indexOf("athena.ttrd.fr") >= 0 ? "ATHENA VPS"
                        : (src.indexOf("mapsdata.plan-ops.fr") >= 0 ? "PLAN-OPS"
                            : (src.indexOf("jetelain.github.io") >= 0 || src.indexOf("cdn.jsdelivr.net") >= 0 ? "PUBLIC" : "CACHE")));
                window.COMSPEC_ATAK_TILE_SOURCE = source;
                document.dispatchEvent(new CustomEvent("comspec:tile-source", { detail: { source: source, url: src } }));
            }
            catch (e) { }
            finish();
            if (isRemotePaintUrl(src)) {
                cacheLater(id, [src].concat(urls));
            }
        };
        img.onerror = function () {
            if (paintedOk)
                return;
            idx += 1;
            if (idx < urls.length && isRemotePaintUrl(urls[idx])) {
                try {
                    document.dispatchEvent(new CustomEvent("comspec:tile-attempt", { detail: { world: world, z: z, x: x, y: y, index: idx, url: urls[idx] || "" } }));
                }
                catch (e) { }
                img.src = urls[idx];
                return;
            }
            failCount += 1;
            try {
                document.dispatchEvent(new CustomEvent("comspec:tile-failure", { detail: { world: world, z: z, x: x, y: y, count: failCount } }));
            }
            catch (e) { }
            img.src = TRANSPARENT;
            finish();
        };
        /* LeÃ§on 1.8.18 / 1.8.21 : jetelain dâ€™abord, jamais /map-data ni file://. */
        try {
            document.dispatchEvent(new CustomEvent("comspec:tile-attempt", { detail: { world: world, z: z, x: x, y: y, index: 0, url: urls[0] || "" } }));
        }
        catch (e) { }
        img.src = urls[0];
        var failoverTimer = setInterval(function () {
            if (paintedOk || finished) {
                clearInterval(failoverTimer);
                return;
            }
            idx += 1;
            if (idx < urls.length) {
                try {
                    document.dispatchEvent(new CustomEvent("comspec:tile-attempt", { detail: { world: world, z: z, x: x, y: y, index: idx, url: urls[idx] || "" } }));
                }
                catch (e) { }
                img.src = urls[idx];
            }
            else {
                clearInterval(failoverTimer);
            }
        }, 3000);
        fromCache(id).then(function (cached) {
            if (cached && isCachePaintUrl(cached) && !paintedOk) {
                img.src = cached;
            }
        });
    }
    function layer(world, cfg) {
        cfg = cfg || {};
        if (!window.L || typeof window.L.GridLayer !== "function")
            return null;
        var meta = worldCfg(world);
        var slug = String(meta.slug || world || "altis").toLowerCase();
        var tile = Number(cfg.tile || meta.tileSize || 256);
        var size = Number(cfg.size || meta.worldSize || 30720);
        var maxZoom = Number(cfg.maxZoom != null ? cfg.maxZoom : (meta.maxZoom != null ? meta.maxZoom : 7));
        var bounds = window.L.latLngBounds(window.L.latLng(0, 0), window.L.latLng(size, size));
        var Grid = window.L.GridLayer.extend({
            createTile: function (coords, done) {
                var img = document.createElement("img");
                img.alt = "";
                img.setAttribute("role", "presentation");
                img.setAttribute("data-map-path", logicalUrl(slug, coords.z, coords.x, coords.y));
                paintTile(img, slug, coords.z, coords.x, coords.y, done);
                return img;
            }
        });
        return new Grid({
            tileSize: tile,
            minZoom: 0,
            maxZoom: maxZoom,
            noWrap: true,
            bounds: bounds,
            keepBuffer: 2
        });
    }
    window.COMSPEC_MapTiles = {
        url: logicalUrl,
        load: load,
        layer: layer,
        sources: function () { return ["/map-data"]; }
    };
})();
