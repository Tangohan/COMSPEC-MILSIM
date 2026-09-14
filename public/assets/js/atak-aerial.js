/* Photo aérienne Atlas, posée en mètres Arma sur le CRS du plan (grilles différentes). */
window.ATAKAerial = (function () {
  'use strict';

  var STORAGE_KEY = 'athena:atak-fond';
  var PANE = 'atakAerialPane';
  var STYLE_ID = 'atak-aerial-style';
  var DEFAULTS = {
    altis: {
      tilePattern: 'https://atlas.plan-ops.fr/data/1/maps/3/295/{z}/{x}/{y}.webp',
      factorX: 0.012375,
      factorY: 0.012375,
      tileSize: 381,
      minZoom: 0,
      maxZoom: 7,
      attribution: '&copy; Bohemia Interactive'
    }
  };

  var states = [];

  function slugOf(raw) {
    return String((raw && (raw.slug || (raw.config && raw.config.title))) || '').toLowerCase();
  }

  function resolveSpec(raw) {
    var aerial = raw && raw.aerial;
    if (aerial && aerial.tilePattern) {
      return {
        tilePattern: String(aerial.tilePattern),
        factorX: Number(aerial.factorX) || 0.012375,
        factorY: Number(aerial.factorY) || Number(aerial.factorX) || 0.012375,
        tileSize: Number(aerial.tileSize) || 381,
        minZoom: aerial.minZoom != null ? Number(aerial.minZoom) : 0,
        maxZoom: aerial.maxZoom != null ? Number(aerial.maxZoom) : 7,
        attribution: aerial.attribution || '&copy; Bohemia Interactive'
      };
    }
    var slug = slugOf(raw);
    return DEFAULTS[slug] ? Object.assign({}, DEFAULTS[slug]) : null;
  }

  function worldMeters(spec) {
    var f = Number(spec.factorX) || 0.012375;
    var t = Number(spec.tileSize) || 381;
    return t / f;
  }

  function storedMode() {
    try {
      var v = localStorage.getItem(STORAGE_KEY);
      if (v === 'plan' || v === 'aerial') return v;
    } catch (e) {}
    return 'aerial';
  }

  function persistMode(mode) {
    try { localStorage.setItem(STORAGE_KEY, mode === 'plan' ? 'plan' : 'aerial'); } catch (e) {}
  }

  function offsetsOf(raw) {
    var ox = raw && raw.offsetX != null ? Number(raw.offsetX) : 0;
    var oy = raw && raw.offsetY != null ? Number(raw.offsetY) : 0;
    if ((!ox && !oy) && raw && raw.config) {
      if (raw.config.offset_x != null) ox = Number(raw.config.offset_x) || 0;
      if (raw.config.offset_y != null) oy = Number(raw.config.offset_y) || 0;
    }
    return { x: isFinite(ox) ? ox : 0, y: isFinite(oy) ? oy : 0 };
  }

  function findBaseTileLayer(map) {
    if (window.ATAKMap && typeof window.ATAKMap.getMap === 'function' && window.ATAKMap.getMap() === map
        && typeof window.ATAKMap.getBaseTileLayer === 'function') {
      var fromAtak = window.ATAKMap.getBaseTileLayer();
      if (fromAtak) return fromAtak;
    }
    var found = null;
    if (!map || typeof map.eachLayer !== 'function') return null;
    map.eachLayer(function (layer) {
      if (found) return;
      if (window.L && layer instanceof L.TileLayer) found = layer;
    });
    return found;
  }

  function setBaseVisible(map, visible) {
    var base = findBaseTileLayer(map);
    if (!base) return;
    try {
      if (typeof base.setOpacity === 'function') base.setOpacity(visible ? 1 : 0);
    } catch (e) {}
  }

  function ensureStyle() {
    if (document.getElementById(STYLE_ID)) return;
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = '.leaflet-image-layer.atak-aerial-tile{pointer-events:none;}';
    document.head.appendChild(style);
  }

  function ensurePane(map) {
    ensureStyle();
    if (!map.getPane(PANE)) {
      map.createPane(PANE);
      map.getPane(PANE).style.zIndex = 250;
      map.getPane(PANE).style.pointerEvents = 'none';
    }
  }

  function tileUrl(spec, z, x, y) {
    return String(spec.tilePattern)
      .replace('{z}', String(z))
      .replace('{x}', String(x))
      .replace('{y}', String(y));
  }

  function tileBounds(spec, z, x, y, off) {
    var W = worldMeters(spec);
    var n = Math.pow(2, z);
    var west = (x * W) / n;
    var east = ((x + 1) * W) / n;
    var north = W * (1 - y / n);
    var south = W * (1 - (y + 1) / n);
    return L.latLngBounds(
      L.latLng(south + off.y, west + off.x),
      L.latLng(north + off.y, east + off.x)
    );
  }

  function stateFor(map) {
    for (var i = 0; i < states.length; i++) {
      if (states[i].map === map) return states[i];
    }
    return null;
  }

  function clearOverlays(st) {
    if (!st || !st.overlays) return;
    Object.keys(st.overlays).forEach(function (key) {
      var layer = st.overlays[key];
      if (st.map && layer) {
        try { st.map.removeLayer(layer); } catch (e) {}
      }
    });
    st.overlays = {};
  }

  function pickZoom(st) {
    var spec = st.spec;
    var z = Math.round(st.map.getZoom());
    if (!isFinite(z)) z = spec.minZoom;
    z = Math.max(spec.minZoom, Math.min(spec.maxZoom, z));
    var b = st.map.getBounds();
    var W = worldMeters(spec);
    var n = Math.pow(2, z);
    var west = b.getWest() - st.offset.x;
    var east = b.getEast() - st.offset.x;
    var south = b.getSouth() - st.offset.y;
    var north = b.getNorth() - st.offset.y;
    function countAt(zoom) {
      var nn = Math.pow(2, zoom);
      var x0 = Math.floor(west / W * nn) - 1;
      var x1 = Math.floor((east - 1e-6) / W * nn) + 1;
      var y0 = Math.floor((1 - north / W) * nn) - 1;
      var y1 = Math.floor((1 - (south + 1e-6) / W) * nn) + 1;
      x0 = Math.max(0, x0);
      x1 = Math.min(nn - 1, x1);
      y0 = Math.max(0, y0);
      y1 = Math.min(nn - 1, y1);
      if (x1 < x0 || y1 < y0) return 0;
      return (x1 - x0 + 1) * (y1 - y0 + 1);
    }
    while (z > spec.minZoom && countAt(z) > 64) z -= 1;
    return z;
  }

  function refresh(st) {
    if (!st || !st.enabled || !st.map || !st.spec) return;
    var spec = st.spec;
    var z = pickZoom(st);
    var W = worldMeters(spec);
    var n = Math.pow(2, z);
    var b = st.map.getBounds();
    var west = b.getWest() - st.offset.x;
    var east = b.getEast() - st.offset.x;
    var south = b.getSouth() - st.offset.y;
    var north = b.getNorth() - st.offset.y;
    var x0 = Math.max(0, Math.floor(west / W * n) - 1);
    var x1 = Math.min(n - 1, Math.floor((east - 1e-6) / W * n) + 1);
    var y0 = Math.max(0, Math.floor((1 - north / W) * n) - 1);
    var y1 = Math.min(n - 1, Math.floor((1 - (south + 1e-6) / W) * n) + 1);
    var keep = {};
    if (x1 >= x0 && y1 >= y0) {
      for (var x = x0; x <= x1; x++) {
        for (var y = y0; y <= y1; y++) {
          var key = z + '/' + x + '/' + y;
          keep[key] = true;
          if (st.overlays[key]) continue;
          var overlay = L.imageOverlay(tileUrl(spec, z, x, y), tileBounds(spec, z, x, y, st.offset), {
            pane: PANE,
            opacity: 1,
            interactive: false,
            className: 'atak-aerial-tile'
          });
          overlay.addTo(st.map);
          st.overlays[key] = overlay;
        }
      }
    }
    Object.keys(st.overlays).forEach(function (key) {
      if (keep[key]) return;
      try { st.map.removeLayer(st.overlays[key]); } catch (e) {}
      delete st.overlays[key];
    });
  }

  function scheduleRefresh(st) {
    if (!st) return;
    if (st.timer) clearTimeout(st.timer);
    st.timer = setTimeout(function () {
      st.timer = null;
      refresh(st);
    }, 60);
  }

  function applyMode(st, mode) {
    var next = mode === 'plan' ? 'plan' : 'aerial';
    persistMode(next);
    st.enabled = next === 'aerial';
    if (!st.enabled) {
      clearOverlays(st);
      setBaseVisible(st.map, true);
    } else {
      ensurePane(st.map);
      setBaseVisible(st.map, false);
      refresh(st);
    }
    syncSelects();
  }

  function syncSelects() {
    var active = states[0] || null;
    var has = !!(active && active.spec);
    var mode = storedMode();
    document.querySelectorAll('[data-atak-aerial-fond]').forEach(function (el) {
      var wrap = el.closest('.atak-aerial-fond-wrap') || el.parentElement;
      if (wrap) wrap.hidden = !has;
      el.disabled = !has;
      if (has && (el.value === 'plan' || el.value === 'aerial')) {
        el.value = mode;
      }
    });
  }

  function bindSelects() {
    document.querySelectorAll('[data-atak-aerial-fond]').forEach(function (el) {
      if (el._atakAerialBound) return;
      el._atakAerialBound = true;
      el.addEventListener('change', function () {
        var mode = el.value === 'plan' ? 'plan' : 'aerial';
        persistMode(mode);
        states.forEach(function (st) { applyMode(st, mode); });
        syncSelects();
      });
    });
    syncSelects();
  }

  function attach(map, raw) {
    if (!map || !window.L) return null;
    detach(map);
    var spec = resolveSpec(raw);
    if (!spec) {
      syncSelects();
      return null;
    }
    ensurePane(map);
    var st = {
      map: map,
      spec: spec,
      offset: offsetsOf(raw || {}),
      overlays: {},
      enabled: storedMode() === 'aerial',
      timer: null,
      onMove: null
    };
    st.onMove = function () { scheduleRefresh(st); };
    map.on('moveend', st.onMove);
    map.on('zoomend', st.onMove);
    states.push(st);
    if (st.enabled) {
      setBaseVisible(map, false);
      refresh(st);
    } else {
      setBaseVisible(map, true);
    }
    bindSelects();
    syncSelects();
    return st;
  }

  function detach(map) {
    var next = [];
    states.forEach(function (st) {
      if (map && st.map !== map) {
        next.push(st);
        return;
      }
      if (st.timer) clearTimeout(st.timer);
      if (st.map && st.onMove) {
        try { st.map.off('moveend', st.onMove); } catch (e) {}
        try { st.map.off('zoomend', st.onMove); } catch (e) {}
      }
      clearOverlays(st);
      if (st.map) setBaseVisible(st.map, true);
    });
    states = next;
    syncSelects();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindSelects);
  } else {
    bindSelects();
  }

  return {
    attach: attach,
    detach: detach,
    resolveSpec: resolveSpec,
    storedMode: storedMode,
    setMode: function (mode) {
      persistMode(mode);
      states.forEach(function (st) { applyMode(st, mode); });
    },
    hasAerial: function (raw) { return !!resolveSpec(raw); }
  };
})();
