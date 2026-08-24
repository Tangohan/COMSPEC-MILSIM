/* Relief ATAK — DEM serveur : hillshade PNG + courbes GeoJSON. Jamais la grille brute. */
window.ATAKTerrain = (function () {
  'use strict';

  var meta = null;
  var hillshade = null;
  var slopeLayer = null;
  var contourLayer = null;
  var panesReady = false;
  var lastPaintKey = '';
  var opacity = 0.32;
  var flags = {
    hillshade: true,
    contours10: true,
    contours50: false,
    altitudes: false,
    slope: false
  };
  var statusEl = null;
  var solEl = null;
  var sampleTimer = null;
  var lastSol = null;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function leafletMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function isReady() {
    return !!(meta && meta.ready);
  }

  function heightAt(x, y) {
    if (lastSol && lastSol.z != null) {
      var dx = Math.abs((lastSol.x || 0) - x);
      var dy = Math.abs((lastSol.y || 0) - y);
      if (dx < 40 && dy < 40) return lastSol.z;
    }
    return null;
  }

  function boundsLatLng() {
    if (!meta || !window.ATAKMap || !window.ATAKMap.latLngFromWorld) return null;
    var cell = meta.cell_m || 50;
    var x0 = meta.origin_x || 0;
    var y0 = meta.origin_y || 0;
    var x1 = x0 + Math.max(1, (meta.cols || 1) - 1) * cell;
    var y1 = y0 + Math.max(1, (meta.rows || 1) - 1) * cell;
    return [window.ATAKMap.latLngFromWorld(x0, y0), window.ATAKMap.latLngFromWorld(x1, y1)];
  }

  function ensurePanes(map) {
    if (panesReady || !map) return;
    if (!map.getPane('atakHillshadePane')) {
      map.createPane('atakHillshadePane');
      map.getPane('atakHillshadePane').style.zIndex = 350;
      map.getPane('atakHillshadePane').style.pointerEvents = 'none';
    }
    if (!map.getPane('atakSlopePane')) {
      map.createPane('atakSlopePane');
      map.getPane('atakSlopePane').style.zIndex = 345;
      map.getPane('atakSlopePane').style.pointerEvents = 'none';
    }
    if (!map.getPane('atakContourPane')) {
      map.createPane('atakContourPane');
      map.getPane('atakContourPane').style.zIndex = 360;
      map.getPane('atakContourPane').style.pointerEvents = 'none';
    }
    panesReady = true;
  }

  function overlayUrl(kind, stamp) {
    return apiBase() + '/api/atak/terrain/' + kind + '?mapId=' + encodeURIComponent(mapId()) + '&t=' + encodeURIComponent(stamp || '');
  }

  function removeLayer(layer) {
    var map = leafletMap();
    if (map && layer) {
      try { map.removeLayer(layer); } catch (e) {}
    }
    return null;
  }

  function setStatus(text) {
    if (!statusEl) statusEl = document.getElementById('atak-terrain-status');
    if (statusEl) statusEl.textContent = text || '';
  }

  function coverageLabel() {
    if (!meta) return 'Données terrain — aucune couverture';
    var pct = meta.coverage_pct != null ? meta.coverage_pct : Math.round((meta.progress || 0) * 100);
    var world = meta.world_name ? (' · ' + meta.world_name) : '';
    return 'Données terrain — couverture ' + pct + ' %' + world;
  }

  function paintOverlays() {
    var map = leafletMap();
    if (!map || !window.L || !isReady()) {
      hillshade = removeLayer(hillshade);
      slopeLayer = removeLayer(slopeLayer);
      contourLayer = removeLayer(contourLayer);
      lastPaintKey = '';
      return;
    }
    ensurePanes(map);
    var b = boundsLatLng();
    if (!b) return;
    var stamp = meta.sampled_at || String(meta.filled_cells || 0);
    var key = [stamp, flags.hillshade, flags.slope, flags.contours10, flags.contours50, flags.altitudes, opacity].join('|');
    if (lastPaintKey === key) {
      if (hillshade && hillshade.setOpacity) hillshade.setOpacity(opacity);
      if (slopeLayer && slopeLayer.setOpacity) slopeLayer.setOpacity(Math.min(0.55, opacity + 0.12));
      return;
    }
    lastPaintKey = key;

    if (flags.hillshade) {
      if (hillshade) hillshade = removeLayer(hillshade);
      hillshade = window.L.imageOverlay(overlayUrl('hillshade', stamp), b, {
        opacity: opacity,
        pane: 'atakHillshadePane',
        interactive: false
      });
      hillshade.addTo(map);
    } else {
      hillshade = removeLayer(hillshade);
    }

    if (flags.slope) {
      if (slopeLayer) slopeLayer = removeLayer(slopeLayer);
      slopeLayer = window.L.imageOverlay(overlayUrl('slope', stamp), b, {
        opacity: Math.min(0.55, opacity + 0.12),
        pane: 'atakSlopePane',
        interactive: false
      });
      slopeLayer.addTo(map);
    } else {
      slopeLayer = removeLayer(slopeLayer);
    }

    loadContours(map, stamp);
  }

  function loadContours(map, stamp) {
    contourLayer = removeLayer(contourLayer);
    if (!flags.contours10 && !flags.contours50) return;
    fetch(apiBase() + '/api/atak/terrain/contours?mapId=' + encodeURIComponent(mapId()) + '&t=' + encodeURIComponent(stamp || ''), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (geo) {
      if (!geo || !geo.features || !window.L) return;
      var show10 = flags.contours10;
      var show50 = flags.contours50;
      var labels = flags.altitudes;
      contourLayer = window.L.geoJSON(geo, {
        pane: 'atakContourPane',
        interactive: false,
        coordsToLatLng: function (c) {
          return window.ATAKMap.latLngFromWorld(c[0], c[1]);
        },
        filter: function (feat) {
          var major = !!(feat.properties && feat.properties.major);
          if (major) return show50 || show10;
          return show10;
        },
        style: function (feat) {
          var major = !!(feat.properties && feat.properties.major);
          return {
            color: major ? '#1c1917' : '#44403c',
            weight: major ? 1.6 : 0.8,
            opacity: major ? 0.85 : 0.55,
            fill: false
          };
        },
        onEachFeature: function (feat, layer) {
          if (!labels || !feat.properties || !feat.properties.major) return;
          var el = feat.properties.elevation;
          if (el == null) return;
          try {
            layer.bindTooltip(String(el) + ' m', {
              permanent: true,
              direction: 'center',
              className: 'atak-contour-label',
              pane: 'atakContourPane'
            });
          } catch (e) {}
        }
      });
      contourLayer.addTo(map);
    }).catch(function () {});
  }

  function loadMeta() {
    if (!apiBase()) return Promise.resolve(false);
    return fetch(apiBase() + '/api/atak/terrain?mapId=' + encodeURIComponent(mapId()), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) {
      return r.text().then(function (raw) {
        try { return raw ? JSON.parse(raw) : null; } catch (e) { return null; }
      });
    }).then(function (j) {
      if (!j || !j.ok) {
        meta = null;
        setStatus('Relief du théâtre non encore relevé.');
        paintOverlays();
        return false;
      }
      var prevStamp = meta && meta.sampled_at;
      meta = j;
      if (prevStamp !== j.sampled_at) lastPaintKey = '';
      setStatus(coverageLabel());
      paintOverlays();
      try { window.dispatchEvent(new CustomEvent('atak:terrain-ready', { detail: meta })); } catch (e) {}
      return !!j.ready;
    }).catch(function () {
      setStatus('Impossible de charger le relief.');
      return false;
    });
  }

  function sampleSol(x, y) {
    if (!flags.altitudes || !apiBase() || !isReady()) {
      if (solEl) solEl.textContent = '—';
      return;
    }
    fetch(apiBase() + '/api/atak/terrain/sample?mapId=' + encodeURIComponent(mapId())
      + '&x=' + encodeURIComponent(String(x)) + '&y=' + encodeURIComponent(String(y)), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.ok || j.z == null) {
        lastSol = null;
        if (solEl) solEl.textContent = '—';
        return;
      }
      lastSol = { x: x, y: y, z: j.z };
      if (solEl) solEl.textContent = 'SOL ' + Math.round(Number(j.z)) + ' m';
    }).catch(function () {});
  }

  function bindHud() {
    var map = leafletMap();
    if (!map || map._atakTerrainHud) return;
    map._atakTerrainHud = true;
    var hud = document.querySelector('.atak-map-hud');
    if (hud && !hud.querySelector('[data-hud-sol]')) {
      var row = document.createElement('div');
      row.className = 'atak-map-hud__row';
      row.innerHTML = '<span class="atak-map-hud__k">Sol</span> <span class="atak-map-hud__v" data-hud-sol>—</span>';
      hud.appendChild(row);
    }
    solEl = document.querySelector('[data-hud-sol]');
    map.on('mousemove', function (e) {
      if (!flags.altitudes) return;
      var w = window.ATAKMap && window.ATAKMap.worldFromLatLng ? window.ATAKMap.worldFromLatLng(e.latlng) : null;
      if (!w) return;
      clearTimeout(sampleTimer);
      sampleTimer = setTimeout(function () { sampleSol(w.x, w.y); }, 180);
    });
  }

  function prefsFrom(p) {
    p = p || {};
    if (p.terrainHillshade != null || p.terrainContours10 != null) {
      flags.hillshade = p.terrainHillshade !== false;
      flags.contours10 = p.terrainContours10 !== false;
      flags.contours50 = !!p.terrainContours50;
      flags.altitudes = !!p.terrainAltitudes;
      flags.slope = !!p.terrainSlope;
    } else if (p.terrainLayer) {
      flags.hillshade = p.terrainLayer === 'hillshade' || p.terrainLayer === 'hypsometry';
      flags.contours10 = p.terrainLayer === 'contours';
      flags.contours50 = false;
      flags.altitudes = false;
      flags.slope = p.terrainLayer === 'slope';
    }
    opacity = p.terrainOpacity != null ? Number(p.terrainOpacity) : 0.32;
  }

  function applyPrefs(p) {
    prefsFrom(p || (window.ATAKMap && window.ATAKMap.getDisplayPrefs ? window.ATAKMap.getDisplayPrefs() : {}));
    paintOverlays();
    if (solEl && !flags.altitudes) solEl.textContent = '—';
  }

  function syncUi(p) {
    p = p || (window.ATAKMap && window.ATAKMap.getDisplayPrefs ? window.ATAKMap.getDisplayPrefs() : {});
    prefsFrom(p);
    function setChk(id, on) {
      var el = document.getElementById(id);
      if (el) el.checked = !!on;
    }
    setChk('atak-terrain-hillshade', flags.hillshade);
    setChk('atak-terrain-contours10', flags.contours10);
    setChk('atak-terrain-contours50', flags.contours50);
    setChk('atak-terrain-altitudes', flags.altitudes);
    setChk('atak-terrain-slope', flags.slope);
    var op = document.getElementById('atak-terrain-opacity');
    var opv = document.getElementById('atak-terrain-opacity-val');
    if (op) op.value = String(Math.round(opacity * 100));
    if (opv) opv.textContent = Math.round(opacity * 100) + ' %';
  }

  function bindUi() {
    function patch(part) {
      if (window.ATAKMap && window.ATAKMap.patchDisplayPrefs) window.ATAKMap.patchDisplayPrefs(part);
    }
    function chk(id, key) {
      var el = document.getElementById(id);
      if (!el || el._bound) return;
      el._bound = true;
      el.addEventListener('change', function () {
        var o = {};
        o[key] = !!el.checked;
        patch(o);
      });
    }
    chk('atak-terrain-hillshade', 'terrainHillshade');
    chk('atak-terrain-contours10', 'terrainContours10');
    chk('atak-terrain-contours50', 'terrainContours50');
    chk('atak-terrain-altitudes', 'terrainAltitudes');
    chk('atak-terrain-slope', 'terrainSlope');
    var op = document.getElementById('atak-terrain-opacity');
    if (op && !op._bound) {
      op._bound = true;
      op.addEventListener('input', function () {
        var v = parseInt(op.value, 10) / 100;
        var lab = document.getElementById('atak-terrain-opacity-val');
        if (lab) lab.textContent = Math.round(v * 100) + ' %';
        patch({ terrainOpacity: v });
      });
    }
  }

  window.addEventListener('atak:mapready', function () {
    bindUi();
    bindHud();
    applyPrefs();
    syncUi();
    loadMeta();
    setInterval(function () { loadMeta(); }, 45000);
  });
  window.addEventListener('atak:display-prefs-changed', function (ev) {
    applyPrefs(ev.detail || {});
    syncUi(ev.detail || {});
  });

  return {
    load: loadMeta,
    heightAt: heightAt,
    isReady: isReady,
    getGrid: function () { return meta; },
    paint: paintOverlays,
    applyPrefs: applyPrefs
  };
})();
