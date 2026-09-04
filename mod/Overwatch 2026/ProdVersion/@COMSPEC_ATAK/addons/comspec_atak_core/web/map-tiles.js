/* Résolveur de tuiles : l’écran ne voit que /map-data/{monde}/{z}/{x}/{y}. */
(function () {
  "use strict";

  var LOGICAL = "/map-data/{world}/{z}/{x}/{y}.webp";
  /* Sources distantes : jamais exposées à l’UI. Premier affichage IMMÉDIAT. */
  var REMOTE = [
    "https://jetelain.github.io/Arma3Map/maps/{world}/{z}/{x}/{y}.png",
    "https://cdn.jsdelivr.net/gh/jetelain/Arma3Map@gh-pages/maps/{world}/{z}/{x}/{y}.png"
  ];
  var inflight = {};
  var kept = {};

  function key(world, z, x, y) {
    return String(world || "altis").toLowerCase() + "/" + z + "/" + x + "/" + y;
  }

  function logicalUrl(world, z, x, y) {
    return LOGICAL
      .replace("{world}", String(world || "altis").toLowerCase())
      .replace("{z}", String(z))
      .replace("{x}", String(x))
      .replace("{y}", String(y));
  }

  function fill(tpl, world, z, x, y) {
    return tpl
      .replace("{world}", String(world || "altis").toLowerCase())
      .replace("{z}", String(z))
      .replace("{x}", String(x))
      .replace("{y}", String(y));
  }

  function remotes(world, z, x, y) {
    return REMOTE.map(function (tpl) { return fill(tpl, world, z, x, y); });
  }

  function blobUrl(blob) {
    try { return URL.createObjectURL(blob); } catch (e) { return ""; }
  }

  function fromCache(id) {
    var store = window.COMSPEC_MapStore;
    if (!store) return Promise.resolve(null);
    return store.get("tiles", id).then(function (row) {
      if (!row) return null;
      if (row.blob) return blobUrl(row.blob);
      if (row.url) return row.url;
      return null;
    }).then(function (v) { return v; }, function () { return null; });
  }

  function toCache(id, blob) {
    var store = window.COMSPEC_MapStore;
    if (!store || !blob) return;
    store.put("tiles", id, { blob: blob, at: Date.now() });
  }

  function fetchOne(url) {
    if (typeof fetch !== "function") return Promise.reject(new Error("fetch"));
    return fetch(url, { mode: "cors" }).then(function (res) {
      if (!res || !res.ok) throw new Error("tile");
      return res.blob();
    });
  }

  function cacheLater(id, urls) {
    if (inflight[id]) return;
    inflight[id] = true;
    var chain = Promise.reject();
    urls.forEach(function (url) {
      chain = chain.then(function (v) { return v; }, function () { return fetchOne(url); });
    });
    chain.then(function (blob) {
      toCache(id, blob);
    }).then(function () {}, function () {});
    setTimeout(function () { delete inflight[id]; }, 8000);
  }

  function keepOnDisk(world, z, x, y) {
    var id = key(world, z, x, y);
    if (kept[id]) return;
    kept[id] = true;
    if (typeof window.COMSPEC_ATAK_send === "function") {
      window.COMSPEC_ATAK_send("map:pack:keep|" + world + "|" + z + "|" + x + "|" + y);
    }
  }

  function load(world, z, x, y) {
    world = String(world || "altis").toLowerCase();
    var id = key(world, z, x, y);
    return fromCache(id).then(function (cached) {
      if (cached) return cached;
      var urls = remotes(world, z, x, y);
      cacheLater(id, urls);
      return urls[0] || logicalUrl(world, z, x, y);
    });
  }

  function paintTile(img, world, z, x, y, done) {
    var id = key(world, z, x, y);
    var urls = remotes(world, z, x, y);
    var idx = 0;
    var finished = false;

    function finish() {
      if (finished) return;
      finished = true;
      if (typeof done === "function") done(null, img);
    }

    img.onload = function () {
      finish();
      if (img.src && img.src.indexOf("blob:") !== 0) {
        cacheLater(id, [img.src].concat(urls));
        keepOnDisk(world, z, x, y);
      }
    };
    img.onerror = function () {
      idx += 1;
      if (idx < urls.length && urls[idx]) {
        img.src = urls[idx];
        return;
      }
      finish();
    };

    /* Leçon 1.8.18 : ne jamais attendre le cache, un pack disque ou /map-data. */
    img.src = urls[0] || "";
    fromCache(id).then(function (cached) {
      if (cached && !finished) img.src = cached;
    });
  }

  function layer(world, cfg) {
    cfg = cfg || {};
    if (!window.L || typeof window.L.GridLayer !== "function") return null;
    var slug = String(world || "altis").toLowerCase();
    var tile = cfg.tile || 256;
    var size = cfg.size || 30720;
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
      maxZoom: 7,
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
