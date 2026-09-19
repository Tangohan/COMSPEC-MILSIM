/* Overwatch Beta — hôte MapLibre (relief réel). Leaflet reste monté mais caché en vue 3D. */
window.OverwatchGlMap = (function () {
  'use strict';

  var KEY = 'athena:ow-gl-mode';
  var PROTOCOL = 'owtile';
  var protocolBound = false;
  var proj = null;
  var glMap = null;
  var host = null;
  var leafletEl = null;
  var stage = null;
  var modeSelect = null;
  var pitchInput = null;
  var exaggerationInput = null;
  var active = false;
  var starting = false;
  var wrapped = false;
  var origSetView = null;
  var origPanTo = null;
  var origFlyTo = null;
  var origFitBounds = null;
  var currentFond = 'plan';
  var currentSpec = null;
  var paintFilter = 'none';
  var lastWeather = null;
  var camMode = 'terrain';
  var KEY_CAM = 'athena:ow-gl-cam';
  var splitOn = false;
  var syncLock = false;
  var resizeTimer = 0;
  var hostObs = null;

  function sunFromWeather(w) {
    w = w || {};
    var hour = Number(w.daytime);
    if (!isFinite(hour)) {
      var cond = String(w.condition || '').toLowerCase();
      if (cond.indexOf('brouillard') >= 0) hour = 7;
      else if (cond.indexOf('pluie') >= 0) hour = 10;
      else if (cond.indexOf('couvert') >= 0) hour = 11;
      else hour = 12;
    }
    hour = ((hour % 24) + 24) % 24;
    var azimuth = 90 + (hour - 6) * 15;
    var elev = 0;
    if (hour >= 6 && hour <= 18) elev = Math.sin((hour - 6) / 12 * Math.PI) * 58;
    else elev = -14;
    var polar = Math.max(8, Math.min(92, 90 - elev));
    var cloud = Math.max(0, Math.min(100, Number(w.cloud_pct) || 0)) / 100;
    var fog = Math.max(0, Math.min(100, Number(w.fog_pct) || 0)) / 100;
    var rain = Math.max(0, Math.min(100, Number(w.rain_pct) || 0)) / 100;
    if (!cloud && /couvert|nuageux|pluie/i.test(String(w.condition || ''))) cloud = /couvert|pluie/i.test(String(w.condition || '')) ? 0.75 : 0.4;
    var night = elev < 0;
    var intensity = night ? 0.2 : (0.74 - cloud * 0.28 - fog * 0.22 - rain * 0.12);
    intensity = Math.max(0.12, Math.min(0.85, intensity));
    var color = night ? '#8aa0c8' : (rain > 0.4 ? '#c8d0d8' : (cloud > 0.6 ? '#d8dde4' : '#fff4e0'));
    return {
      night: night,
      intensity: intensity,
      color: color,
      azimuth: azimuth,
      polar: polar,
      light: {
        anchor: 'map',
        color: color,
        intensity: intensity,
        position: [1.15, azimuth, polar]
      }
    };
  }

  function applyWeatherLight(detail) {
    if (detail) lastWeather = detail;
    var sun = sunFromWeather(lastWeather || {});
    if (glMap && typeof glMap.setLight === 'function') {
      try { glMap.setLight(sun.light); } catch (e) {}
    }
    if (window.OverwatchGlLayers && typeof window.OverwatchGlLayers.setSun === 'function') {
      window.OverwatchGlLayers.setSun(sun);
    }
  }

  function apiBase() {
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
    return String(base || '').replace(/\/$/, '');
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function leafletMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : (window.OverwatchBeta && window.OverwatchBeta.map) || null;
  }

  function clamp(v, a, b) {
    var n = Number(v);
    if (!isFinite(n)) return a;
    return Math.max(a, Math.min(b, n));
  }

  function storedMode() {
    try {
      var v = localStorage.getItem(KEY);
      if (v === 'volume' || v === 'flat' || v === 'tactical') return v;
    } catch (e) {}
    return 'flat';
  }

  function persistMode(mode) {
    try { localStorage.setItem(KEY, mode); } catch (e) {}
  }

  function exaggeration() {
    return clamp(exaggerationInput && exaggerationInput.value, 1, 4) || 2.5;
  }

  function pitch() {
    return clamp(pitchInput && pitchInput.value, 25, 65) || 48;
  }

  function fondState() {
    var layers = window.ATAKAerial && window.ATAKAerial.resolveLayers
      ? window.ATAKAerial.resolveLayers(window.ATAK_MAP_CONFIG)
      : [];
    var id = window.ATAKAerial && window.ATAKAerial.storedMode ? window.ATAKAerial.storedMode() : 'plan';
    var spec = null;
    var i;
    for (i = 0; i < layers.length; i++) {
      if (layers[i].id === id && layers[i].spec) spec = layers[i].spec;
    }
    return { id: id, spec: spec };
  }

  function ensureProtocol() {
    if (protocolBound || !window.maplibregl || typeof window.maplibregl.addProtocol !== 'function') return;
    protocolBound = true;
    window.maplibregl.addProtocol(PROTOCOL, function (params, abort) {
      return paintArmaTile(params, abort);
    });
  }

  function loadImage(url, abort) {
    return new Promise(function (resolve, reject) {
      if (!url) {
        resolve(null);
        return;
      }
      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = function () { resolve(img); };
      img.onerror = function () { resolve(null); };
      if (abort && abort.signal) {
        abort.signal.addEventListener('abort', function () {
          img.src = '';
          reject(new Error('abort'));
        });
      }
      img.src = url;
    });
  }

  function canvasToArrayBuffer(canvas) {
    return new Promise(function (resolve) {
      canvas.toBlob(function (blob) {
        if (!blob) {
          resolve(new ArrayBuffer(0));
          return;
        }
        blob.arrayBuffer().then(resolve).catch(function () { resolve(new ArrayBuffer(0)); });
      }, 'image/png');
    });
  }

  function paintArmaTile(params, abort) {
    if (!proj) return Promise.resolve({ data: new ArrayBuffer(0) });
    var parts = String(params.url || '').replace(PROTOCOL + '://', '').split('/');
    var z = parseInt(parts[0], 10) || 0;
    var x = parseInt(parts[1], 10) || 0;
    var y = parseInt(parts[2], 10) || 0;
    var world = proj.mercatorTileWorld(z, x, y);
    var canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#1a221c';
    ctx.fillRect(0, 0, 256, 256);
    if (world.maxX < 0 || world.maxY < 0 || world.minX > proj.worldSize || world.minY > proj.worldSize) {
      return canvasToArrayBuffer(canvas).then(function (data) { return { data: data }; });
    }
    var mpp = (world.maxX - world.minX) / 256;
    var spec = currentSpec;
    var pattern = spec && spec.tilePattern ? spec.tilePattern : proj.tilePattern;
    var armaZ = proj.pickArmaZoom(mpp, spec || { tileSize: proj.tileSize, factorX: proj.factorx, maxZoom: proj.maxZoom, minZoom: proj.minZoom });
    var tiles = proj.armaTilesForWorld(world, armaZ, spec);
    var jobs = tiles.slice(0, 24).filter(function (tile) {
      return tile && tile.x >= 0 && tile.y >= 0 && tile.z >= 0;
    }).map(function (tile) {
      return loadImage(proj.tileUrl(pattern, tile.z, tile.x, tile.y), abort).then(function (img) {
        if (!img) return;
        var dx = (tile.minX - world.minX) / (world.maxX - world.minX) * 256;
        var dw = (tile.maxX - tile.minX) / (world.maxX - world.minX) * 256;
        var dy = (world.maxY - tile.maxY) / (world.maxY - world.minY) * 256;
        var dh = (tile.maxY - tile.minY) / (world.maxY - world.minY) * 256;
        try { ctx.drawImage(img, dx, dy, dw, dh); } catch (e) {}
      });
    });
    return Promise.all(jobs).then(function () {
      if (paintFilter === 'grayscale(1) contrast(1.18) brightness(1.02)') {
        var id = ctx.getImageData(0, 0, 256, 256);
        var d = id.data;
        var i, g;
        for (i = 0; i < d.length; i += 4) {
          g = d[i] * 0.3 + d[i + 1] * 0.59 + d[i + 2] * 0.11;
          g = Math.max(0, Math.min(255, (g - 128) * 1.18 + 128 * 1.02));
          d[i] = d[i + 1] = d[i + 2] = g;
        }
        ctx.putImageData(id, 0, 0);
      }
      return canvasToArrayBuffer(canvas).then(function (data) { return { data: data }; });
    });
  }

  function rgbUrl() {
    return apiBase() + '/api/atak/terrain/rgb/{z}/{x}/{y}?mapId=' + encodeURIComponent(mapId())
      + '&ox=' + encodeURIComponent(proj.offsetX) + '&oy=' + encodeURIComponent(proj.offsetY);
  }

  function styleSpec() {
    return {
      version: 8,
      sources: {
        arma: {
          type: 'raster',
          tiles: [PROTOCOL + '://{z}/{x}/{y}'],
          tileSize: 256,
          minzoom: 8,
          maxzoom: 16,
          attribution: '&copy; Bohemia Interactive'
        },
        terrain: {
          type: 'raster-dem',
          tiles: [rgbUrl()],
          tileSize: 256,
          minzoom: 8,
          maxzoom: 14,
          encoding: 'mapbox'
        }
      },
      layers: [
        { id: 'bg', type: 'background', paint: { 'background-color': '#121816' } },
        { id: 'arma', type: 'raster', source: 'arma', paint: { 'raster-opacity': 1 } }
      ],
      terrain: { source: 'terrain', exaggeration: exaggeration() }
    };
  }

  function syncFromLeaflet() {
    if (syncLock || !glMap || !active) return;
    var lm = leafletMap();
    if (!lm) return;
    var c = lm.getCenter();
    var ll = proj.leafletToLngLat(c);
    if (!ll) return;
    var z = proj.leafletZoomToMaplibre(lm.getZoom());
    syncLock = true;
    glMap.jumpTo({
      center: [ll.lng, ll.lat],
      zoom: z,
      pitch: splitOn ? glMap.getPitch() : pitch(),
      bearing: splitOn ? glMap.getBearing() : 0
    });
    syncLock = false;
  }

  function syncToLeaflet() {
    if (syncLock || !glMap) return;
    var lm = leafletMap();
    if (!lm) return;
    var c = glMap.getCenter();
    var leaf = proj.lngLatToLeaflet(c.lng, c.lat);
    var z = proj.maplibreZoomToLeaflet(glMap.getZoom());
    syncLock = true;
    if (origSetView) origSetView.call(lm, [leaf.lat, leaf.lng], z, { animate: false });
    else lm.setView([leaf.lat, leaf.lng], z, { animate: false });
    syncLock = false;
  }

  function wrapCamera() {
    var lm = leafletMap();
    if (!lm || wrapped) return;
    origSetView = lm.setView;
    origPanTo = lm.panTo;
    origFlyTo = lm.flyTo;
    origFitBounds = lm.fitBounds;
    lm.setView = function (center, zoom, options) {
      var ret = origSetView.call(lm, center, zoom, options);
      if (active) syncFromLeaflet();
      return ret;
    };
    lm.panTo = function (center, options) {
      var ret = origPanTo.call(lm, center, options);
      if (active) syncFromLeaflet();
      return ret;
    };
    if (typeof origFlyTo === 'function') {
      lm.flyTo = function (center, zoom, options) {
        var ret = origFlyTo.call(lm, center, zoom, options);
        if (active) syncFromLeaflet();
        return ret;
      };
    }
    if (typeof origFitBounds === 'function') {
      lm.fitBounds = function (bounds, options) {
        var ret = origFitBounds.call(lm, bounds, options);
        if (active) syncFromLeaflet();
        return ret;
      };
    }
    wrapped = true;
  }

  function applyLookFilter() {
    var look = stage && stage.dataset ? stage.dataset.look : 'color';
    paintFilter = look === 'bw' ? 'grayscale(1) contrast(1.18) brightness(1.02)' : 'none';
    if (glMap && glMap.style) {
      try { glMap.style.sourceCaches.arma && glMap.style.sourceCaches.arma.clearTiles(); } catch (e) {}
      try { glMap.triggerRepaint(); } catch (e2) {}
    }
  }

  function applyFond() {
    var fond = fondState();
    currentFond = fond.id;
    currentSpec = fond.spec;
    if (glMap) {
      try {
        var src = glMap.getSource('arma');
        if (src && typeof src.setTiles === 'function') src.setTiles([PROTOCOL + '://{z}/{x}/{y}']);
        glMap.triggerRepaint();
      } catch (e) {}
    }
  }

  function cancelScheduledResize() {
    if (resizeTimer) {
      window.clearTimeout(resizeTimer);
      resizeTimer = 0;
    }
  }

  function applyResize() {
    if (!glMap) return;
    try {
      glMap.resize();
    } catch (err) {
      if (String(err && err.message || err).indexOf('already running') >= 0) {
        window.setTimeout(function () {
          if (!glMap) return;
          try { glMap.resize(); } catch (e2) {}
        }, 48);
      }
    }
  }

  function scheduleResize() {
    if (!glMap) return;
    cancelScheduledResize();
    resizeTimer = window.setTimeout(function () {
      resizeTimer = 0;
      applyResize();
    }, 32);
  }

  function bindHostResize() {
    if (hostObs || !host || typeof ResizeObserver === 'undefined') return;
    hostObs = new ResizeObserver(function () {
      scheduleResize();
    });
    try { hostObs.observe(host); } catch (e) { hostObs = null; }
  }

  function unbindHostResize() {
    cancelScheduledResize();
    if (hostObs) {
      try { hostObs.disconnect(); } catch (e) {}
      hostObs = null;
    }
  }

  function setHostVisible(on) {
    if (host) host.hidden = !(on || splitOn);
    if (leafletEl) {
      var hideLeaf = on && !splitOn;
      leafletEl.classList.toggle('ow-map--gl-hidden', hideLeaf);
      leafletEl.setAttribute('aria-hidden', hideLeaf ? 'true' : 'false');
    }
    if (stage) {
      stage.classList.toggle('ow-map-stage--gl', on && !splitOn);
      stage.classList.toggle('is-split', splitOn);
    }
  }

  function setSplit(on) {
    splitOn = !!on;
    if (splitOn && !active) {
      camMode = 'tactical';
      setEnabled(true);
    }
    setHostVisible(active || splitOn);
    window.setTimeout(function () {
      scheduleResize();
      var lm = leafletMap();
      if (lm && typeof lm.invalidateSize === 'function') {
        try { lm.invalidateSize({ animate: false }); } catch (e) {}
      }
    }, 60);
  }

  function getCamera() {
    if (!glMap) return null;
    var c = glMap.getCenter();
    return {
      lng: c.lng,
      lat: c.lat,
      zoom: glMap.getZoom(),
      pitch: glMap.getPitch(),
      bearing: glMap.getBearing(),
      mode: camMode
    };
  }

  function setCamera(cam) {
    if (!glMap || !cam) return;
    try {
      glMap.jumpTo({
        center: [cam.lng, cam.lat],
        zoom: cam.zoom,
        pitch: cam.pitch != null ? cam.pitch : pitch(),
        bearing: cam.bearing != null ? cam.bearing : 0
      });
    } catch (e) {}
  }

  function applyTerrain() {
    if (!glMap) return;
    try {
      glMap.setTerrain({ source: 'terrain', exaggeration: exaggeration() });
    } catch (e) {}
    try {
      if (camMode === 'tactical') glMap.setPitch(58);
      else if (camMode === 'ground' || camMode === 'follow') glMap.setPitch(camMode === 'ground' ? 82 : 56);
      else glMap.setPitch(pitch());
    } catch (e2) {}
    if (exaggerationInput && document.getElementById('atak-terrain-exaggeration-val')) {
      document.getElementById('atak-terrain-exaggeration-val').textContent = exaggeration().toFixed(1) + '×';
    }
    if (pitchInput && document.getElementById('atak-terrain-pitch-val')) {
      document.getElementById('atak-terrain-pitch-val').textContent = Math.round(pitch()) + '°';
    }
  }

  function destroyGl() {
    if (window.OverwatchGlLayers && typeof window.OverwatchGlLayers.detach === 'function') {
      window.OverwatchGlLayers.detach();
    }
    unbindHostResize();
    if (glMap) {
      try { glMap.remove(); } catch (e) {}
      glMap = null;
    }
  }

  function startGl() {
    if (glMap || starting) {
      if (glMap) {
        setHostVisible(true);
        scheduleResize();
        syncFromLeaflet();
        applyTerrain();
      }
      return;
    }
    if (!window.maplibregl || !host || !proj) return;
    starting = true;
    setHostVisible(true);
    ensureProtocol();
    applyFond();
    applyLookFilter();
    try {
      glMap = new window.maplibregl.Map({
        container: host,
        style: styleSpec(),
        renderWorldCopies: false,
        attributionControl: false,
        maxBounds: proj.maxBounds(),
        minZoom: 8,
        maxZoom: 16.5,
        dragRotate: true,
        pitchWithRotate: true,
        cooperativeGestures: false,
        trackResize: false
      });
    } catch (err) {
      starting = false;
      return;
    }
    bindHostResize();
    scheduleResize();
    glMap.on('load', function () {
      starting = false;
      scheduleResize();
      try { glMap.doubleClickZoom.disable(); } catch (e0) {}
      glMap.on('dblclick', onGlDblClick);
      applyTerrain();
      applyWeatherLight(lastWeather);
      syncFromLeaflet();
      applyCameraMode(camMode);
      if (window.OverwatchGlLayers && typeof window.OverwatchGlLayers.attach === 'function') {
        window.OverwatchGlLayers.attach(glMap, proj);
      }
      try {
        window.dispatchEvent(new CustomEvent('atak:overwatch-gl-ready', { detail: { map: glMap } }));
      } catch (e) {}
    });
    glMap.on('moveend', function () {
      if (active) syncToLeaflet();
    });
    glMap.on('mousemove', function (event) {
      if (!active || !event || !event.lngLat) return;
      var ow = window.OverwatchBeta;
      if (!ow || typeof ow.gridLabel !== 'function' || !proj) return;
      var leaf = proj.lngLatToLeaflet(event.lngLat.lng, event.lngLat.lat);
      var el = document.getElementById('ow-coordinate');
      if (el && leaf) el.textContent = ow.gridLabel({ lat: leaf.lat, lng: leaf.lng }) + ' · Direct';
    });
    setHostVisible(true);
  }

  function setEnabled(on) {
    active = !!on;
    persistMode(active ? (camMode === 'tactical' ? 'tactical' : 'volume') : 'flat');
    if (modeSelect) {
      if (!active) modeSelect.value = 'flat';
      else if (camMode === 'tactical') modeSelect.value = 'tactical';
      else modeSelect.value = 'volume';
    }
    var camBar = document.getElementById('ow-cam-bar');
    if (camBar) camBar.hidden = !active;
    if (active) {
      wrapCamera();
      startGl();
      setHostVisible(true);
      if (glMap) {
        scheduleResize();
        syncFromLeaflet();
        applyTerrain();
      }
    } else {
      if (glMap) syncToLeaflet();
      setHostVisible(false);
      var lm = leafletMap();
      if (lm && typeof lm.invalidateSize === 'function') {
        try { lm.invalidateSize({ animate: false }); } catch (e) {}
      }
    }
    try {
      window.dispatchEvent(new CustomEvent('atak:overwatch-gl-change', { detail: { enabled: active } }));
    } catch (e2) {}
  }

  function lookAtLngLat(lng, lat) {
    if (!glMap || !active) return;
    glMap.easeTo({
      center: [lng, lat],
      pitch: camMode === 'ground' ? 82 : (camMode === 'tactical' ? 58 : pitch()),
      zoom: Math.max(glMap.getZoom(), 14.2),
      duration: 520
    });
  }

  function applyCameraMode(mode) {
    camMode = mode || camMode;
    try { localStorage.setItem(KEY_CAM, camMode); } catch (e) {}
    if (!glMap || !active) return;
    if (mode === 'north' || mode === 'map') {
      camMode = mode === 'north' ? 'terrain' : camMode;
      glMap.easeTo({ pitch: 0, bearing: 0, duration: 420 });
      return;
    }
    if (mode === 'ground') {
      glMap.easeTo({ pitch: 82, zoom: Math.max(glMap.getZoom(), 15.8), duration: 520 });
      return;
    }
    if (mode === 'follow') {
      glMap.easeTo({ pitch: 56, duration: 360 });
      return;
    }
    if (mode === 'tactical') {
      glMap.easeTo({ pitch: 58, duration: 400 });
      return;
    }
    glMap.easeTo({ pitch: pitch(), duration: 400 });
  }

  function followWorld(x, y) {
    if (!glMap || !active || !proj) return;
    var ll = proj.worldToLngLat(x, y);
    glMap.easeTo({
      center: [ll[0], ll[1]],
      pitch: camMode === 'ground' ? 82 : 56,
      duration: 280
    });
  }

  function flyToWorld(x, y, leafletZoom) {
    if (!glMap || !active || !proj) return;
    var ll = proj.worldToLngLat(x, y);
    glMap.easeTo({
      center: [ll[0], ll[1]],
      zoom: leafletZoom != null ? proj.leafletZoomToMaplibre(leafletZoom) : glMap.getZoom(),
      duration: 450
    });
  }

  function onGlDblClick(event) {
    if (!event || !event.lngLat) return;
    if (event.originalEvent && typeof event.originalEvent.preventDefault === 'function') {
      event.originalEvent.preventDefault();
    }
    lookAtLngLat(event.lngLat.lng, event.lngLat.lat);
  }

  function init() {
    if (!window.ATAK_OVERWATCH_BETA) return;
    stage = document.getElementById('ow-map-stage');
    leafletEl = document.getElementById('ow-map');
    host = document.getElementById('ow-gl-map');
    modeSelect = document.getElementById('atak-terrain-3d-mode');
    pitchInput = document.getElementById('atak-terrain-pitch');
    exaggerationInput = document.getElementById('atak-terrain-exaggeration');
    if (!stage || !host || !window.OverwatchTheaterProjection) return;
    proj = window.OverwatchTheaterProjection.create(window.ATAK_MAP_CONFIG || {});
    wrapCamera();
    if (modeSelect) {
      modeSelect.addEventListener('change', function () {
        var v = modeSelect.value;
        if (v === 'flat') {
          setEnabled(false);
          return;
        }
        camMode = v === 'tactical' ? 'tactical' : 'terrain';
        setEnabled(true);
        applyCameraMode(camMode);
      });
    }
    document.querySelectorAll('[data-ow-cam]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var mode = btn.getAttribute('data-ow-cam');
        if (!active) {
          camMode = mode === 'follow' || mode === 'ground' ? 'tactical' : 'terrain';
          setEnabled(true);
        }
        applyCameraMode(mode);
        if (mode === 'follow') {
          var box = document.getElementById('ow-follow');
          if (box && !box.checked) {
            box.checked = true;
            box.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
    });
    if (pitchInput) pitchInput.addEventListener('input', applyTerrain);
    if (exaggerationInput) exaggerationInput.addEventListener('input', applyTerrain);
    window.addEventListener('atak:fond-changed', applyFond);
    window.addEventListener('atak:display-prefs-changed', applyLookFilter);
    window.addEventListener('overwatch:weather', function (event) {
      applyWeatherLight(event && event.detail);
    });
    if (stage) {
      var lookObs = new MutationObserver(applyLookFilter);
      lookObs.observe(stage, { attributes: true, attributeFilter: ['data-look'] });
    }
    window.addEventListener('resize', function () {
      if (active && glMap) scheduleResize();
    });
    if (storedMode() === 'volume' || storedMode() === 'tactical' || (modeSelect && (modeSelect.value === 'volume' || modeSelect.value === 'tactical'))) {
      camMode = storedMode() === 'tactical' || (modeSelect && modeSelect.value === 'tactical') ? 'tactical' : 'terrain';
      setEnabled(true);
    } else if (modeSelect) {
      modeSelect.value = 'flat';
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  window.addEventListener('atak:mapready', function () {
    wrapCamera();
    if (storedMode() === 'volume' || storedMode() === 'tactical') {
      camMode = storedMode() === 'tactical' ? 'tactical' : 'terrain';
      setEnabled(true);
    }
  });

  return {
    isActive: function () { return active; },
    getMap: function () { return glMap; },
    getProjection: function () { return proj; },
    setEnabled: setEnabled,
    flyToWorld: flyToWorld,
    followWorld: followWorld,
    lookAtLngLat: lookAtLngLat,
    applyCameraMode: applyCameraMode,
    cameraMode: function () { return active ? camMode : 'map'; },
    syncFromLeaflet: syncFromLeaflet,
    setSplit: setSplit,
    getCamera: getCamera,
    setCamera: setCamera
  };
})();
