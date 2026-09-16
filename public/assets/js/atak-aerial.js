/* Calques de fond Atlas, posés en mètres Arma sur le CRS du plan (grilles différentes). */
window.ATAKAerial = (function () {
  'use strict';

  var STORAGE_KEY = 'athena:atak-fond';
  var PANE = 'atakAerialPane';
  var STYLE_ID = 'atak-aerial-style';
  var DEFAULT_OVERLAY = {
    factorX: 0.012375,
    factorY: 0.012375,
    tileSize: 381,
    minZoom: 0,
    maxZoom: 7,
    attribution: '&copy; Bohemia Interactive'
  };

  var states = [];

  function slugOf(raw) {
    return String((raw && (raw.slug || (raw.config && raw.config.title))) || '').toLowerCase();
  }

  function overlaySpec(rawSpec) {
    if (!rawSpec || !rawSpec.tilePattern) return null;
    return {
      tilePattern: String(rawSpec.tilePattern),
      factorX: Number(rawSpec.factorX) || DEFAULT_OVERLAY.factorX,
      factorY: Number(rawSpec.factorY) || Number(rawSpec.factorX) || DEFAULT_OVERLAY.factorY,
      tileSize: Number(rawSpec.tileSize) || DEFAULT_OVERLAY.tileSize,
      minZoom: rawSpec.minZoom != null ? Number(rawSpec.minZoom) : DEFAULT_OVERLAY.minZoom,
      maxZoom: rawSpec.maxZoom != null ? Number(rawSpec.maxZoom) : DEFAULT_OVERLAY.maxZoom,
      attribution: rawSpec.attribution || DEFAULT_OVERLAY.attribution
    };
  }

  function defaultAltisLayers() {
    return [
      { id: 'plan', label: 'Plan', help: 'Carte topographique du poste', kind: 'base' },
      {
        id: 'topo',
        label: 'Carte du jeu',
        help: 'Plan du théâtre',
        kind: 'overlay',
        spec: overlaySpec({
          tilePattern: 'https://atlas.plan-ops.fr/data/1/maps/3/3/{z}/{x}/{y}.webp',
          factorX: 0.012375,
          factorY: 0.012375,
          tileSize: 381,
          minZoom: 0,
          maxZoom: 6
        })
      },
      {
        id: 'aerial',
        label: 'Photo aérienne',
        help: 'Vue photo du terrain',
        kind: 'overlay',
        spec: overlaySpec({
          tilePattern: 'https://atlas.plan-ops.fr/data/1/maps/3/295/{z}/{x}/{y}.webp',
          factorX: 0.012375,
          factorY: 0.012375,
          tileSize: 381,
          minZoom: 0,
          maxZoom: 7
        })
      }
    ];
  }

  function resolveLayers(raw) {
    var list = raw && Array.isArray(raw.fondLayers) ? raw.fondLayers : null;
    var layers = [];
    if (list && list.length) {
      list.forEach(function (item) {
        if (!item || !item.id) return;
        var kind = item.kind === 'overlay' ? 'overlay' : 'base';
        var spec = kind === 'overlay' ? overlaySpec(item.spec) : null;
        if (kind === 'overlay' && !spec) return;
        layers.push({
          id: String(item.id),
          label: String(item.label || item.id),
          help: String(item.help || ''),
          kind: kind,
          spec: spec
        });
      });
    }
    if (!layers.length) {
      var aerial = overlaySpec(raw && raw.aerial);
      if (aerial) {
        layers = defaultAltisLayers();
      } else if (slugOf(raw) === 'altis') {
        layers = defaultAltisLayers();
      } else {
        layers = [{ id: 'plan', label: 'Plan', help: 'Carte topographique du poste', kind: 'base' }];
      }
    }
    var ids = {};
    return layers.filter(function (layer) {
      if (ids[layer.id]) return false;
      ids[layer.id] = true;
      return true;
    });
  }

  function resolveSpec(raw) {
    var layers = resolveLayers(raw);
    for (var i = 0; i < layers.length; i++) {
      if (layers[i].id === 'aerial' && layers[i].spec) return layers[i].spec;
    }
    for (var j = 0; j < layers.length; j++) {
      if (layers[j].kind === 'overlay' && layers[j].spec) return layers[j].spec;
    }
    return null;
  }

  function findLayer(layers, id) {
    for (var i = 0; i < layers.length; i++) {
      if (layers[i].id === id) return layers[i];
    }
    return layers[0] || { id: 'plan', kind: 'base', label: 'Plan', help: '', spec: null };
  }

  function worldMeters(spec) {
    var f = Number(spec.factorX) || 0.012375;
    var t = Number(spec.tileSize) || 381;
    return t / f;
  }

  function storedMode(layers) {
    var allowed = {};
    (layers || []).forEach(function (layer) { allowed[layer.id] = true; });
    try {
      var v = localStorage.getItem(STORAGE_KEY);
      if (v && allowed[v]) return v;
      if (v === 'plan' || v === 'aerial' || v === 'topo') {
        if (allowed[v]) return v;
      }
    } catch (e) {}
    if (allowed.aerial) return 'aerial';
    if (layers && layers.length) return layers[0].id;
    return 'plan';
  }

  function persistMode(mode) {
    try { localStorage.setItem(STORAGE_KEY, String(mode || 'plan')); } catch (e) {}
  }

  function currentRaw() {
    if (states[0] && states[0].raw) return states[0].raw;
    return window.ATAK_MAP_CONFIG || null;
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
    if (!spec) return 0;
    var z = Math.round(st.map.getZoom());
    if (!isFinite(z)) z = spec.minZoom;
    z = Math.max(spec.minZoom, Math.min(spec.maxZoom, z));
    var b = st.map.getBounds();
    var W = worldMeters(spec);
    function countAt(zoom) {
      var nn = Math.pow(2, zoom);
      var west = b.getWest() - st.offset.x;
      var east = b.getEast() - st.offset.x;
      var south = b.getSouth() - st.offset.y;
      var north = b.getNorth() - st.offset.y;
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
    var layer = findLayer(st.layers, mode);
    persistMode(layer.id);
    st.currentId = layer.id;
    var overlay = layer.kind === 'overlay' && layer.spec;
    if (!overlay) {
      st.enabled = false;
      clearOverlays(st);
      setBaseVisible(st.map, true);
    } else {
      var specChanged = !st.spec || st.spec.tilePattern !== layer.spec.tilePattern;
      st.spec = layer.spec;
      st.enabled = true;
      if (specChanged) clearOverlays(st);
      ensurePane(st.map);
      setBaseVisible(st.map, false);
      refresh(st);
    }
    syncSelects();
  }

  function fillSelect(el, layers, mode) {
    el.innerHTML = '';
    layers.forEach(function (layer) {
      var opt = document.createElement('option');
      opt.value = layer.id;
      opt.textContent = layer.label;
      el.appendChild(opt);
    });
    var chosen = mode;
    if (!layers.some(function (layer) { return layer.id === chosen; })) {
      chosen = layers[0] ? layers[0].id : 'plan';
    }
    el.value = chosen;
  }

  function fillRadios(host, layers, mode) {
    host.innerHTML = '';
    layers.forEach(function (layer) {
      var lab = document.createElement('label');
      lab.className = 'atak-map-look__check atak-fond-calque';
      var input = document.createElement('input');
      input.type = 'radio';
      input.name = 'atak-fond-calque';
      input.value = layer.id;
      input.checked = layer.id === mode;
      input.setAttribute('data-atak-aerial-fond-radio', '1');
      var text = document.createElement('span');
      var strong = document.createElement('strong');
      strong.textContent = layer.label;
      text.appendChild(strong);
      if (layer.help) {
        var help = document.createElement('small');
        help.textContent = layer.help;
        text.appendChild(help);
      }
      lab.appendChild(input);
      lab.appendChild(text);
      host.appendChild(lab);
    });
  }

  function syncSelects() {
    var raw = currentRaw();
    var layers = resolveLayers(raw);
    var hasExtras = layers.some(function (layer) { return layer.kind === 'overlay'; });
    var mode = storedMode(layers);
    var fieldset = document.getElementById('atak-settings-fond');
    var alwaysShow = !!(document.querySelector('.ow-shell') || document.documentElement.classList.contains('ow-root'));
    if (fieldset) fieldset.hidden = !hasExtras && !alwaysShow;
    var radioHost = document.getElementById('atak-fond-calques-list');
    if (radioHost && (hasExtras || alwaysShow)) fillRadios(radioHost, layers, mode);
    document.querySelectorAll('[data-atak-aerial-fond]').forEach(function (el) {
      var wrap = el.closest('.atak-aerial-fond-wrap') || el.parentElement;
      if (wrap) wrap.hidden = !hasExtras;
      el.disabled = !hasExtras;
      if (hasExtras) fillSelect(el, layers, mode);
    });
  }

  function onFondChange(mode) {
    persistMode(mode);
    if (!states.length) {
      syncSelects();
      return;
    }
    states.forEach(function (st) { applyMode(st, mode); });
    syncSelects();
  }

  function bindSelects() {
    document.querySelectorAll('[data-atak-aerial-fond]').forEach(function (el) {
      if (el._atakAerialBound) return;
      el._atakAerialBound = true;
      el.addEventListener('change', function () {
        onFondChange(el.value);
      });
    });
    var radioHost = document.getElementById('atak-fond-calques-list');
    if (radioHost && !radioHost._atakAerialBound) {
      radioHost._atakAerialBound = true;
      radioHost.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || t.name !== 'atak-fond-calque') return;
        onFondChange(t.value);
      });
    }
    syncSelects();
  }

  function attach(map, raw) {
    if (!map || !window.L) return null;
    detach(map);
    var layers = resolveLayers(raw);
    var overlayLayers = layers.filter(function (layer) { return layer.kind === 'overlay' && layer.spec; });
    if (!overlayLayers.length) {
      syncSelects();
      return null;
    }
    var mode = storedMode(layers);
    var chosen = findLayer(layers, mode);
    var spec = chosen.kind === 'overlay' ? chosen.spec : overlayLayers[0].spec;
    ensurePane(map);
    var st = {
      map: map,
      raw: raw || {},
      layers: layers,
      spec: spec,
      currentId: chosen.id,
      offset: offsetsOf(raw || {}),
      overlays: {},
      enabled: chosen.kind === 'overlay',
      timer: null,
      onMove: null
    };
    st.onMove = function () { scheduleRefresh(st); };
    map.on('moveend', st.onMove);
    map.on('zoomend', st.onMove);
    states.push(st);
    applyMode(st, chosen.id);
    bindSelects();
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
    resolveLayers: resolveLayers,
    storedMode: function () { return storedMode(resolveLayers(currentRaw())); },
    setMode: function (mode) { onFondChange(mode); },
    hasAerial: function (raw) {
      return resolveLayers(raw).some(function (layer) { return layer.kind === 'overlay'; });
    }
  };
})();
