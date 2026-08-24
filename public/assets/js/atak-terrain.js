/* Relief de théâtre — grille métrique Arma, hillshade / hypsométrie / pente / courbes. */
window.ATAKTerrain = (function () {
  'use strict';

  var MISSING = -32768;
  var grid = null;
  var heights = null;
  var overlay = null;
  var paneName = 'atakTerrainPane';
  var lastKey = '';
  var sunAzimuth = 315;
  var opacity = 0.55;
  var layer = 'hillshade';
  var statusEl = null;

  var LAYERS = [
    { id: 'off', label: 'Aucun' },
    { id: 'hillshade', label: 'Ombrage' },
    { id: 'hypsometry', label: 'Hypsométrie' },
    { id: 'slope', label: 'Pente' },
    { id: 'contours', label: 'Courbes de niveau' },
    { id: 'ridges', label: 'Crêtes' },
    { id: 'depressions', label: 'Dépressions' }
  ];

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function i16(u) {
    return u >= 32768 ? u - 65536 : u;
  }

  function cellZ(c, r) {
    if (!heights || !grid) return null;
    if (c < 0 || r < 0 || c >= grid.cols || r >= grid.rows) return null;
    var z = heights[r * grid.cols + c];
    if (z == null || z === MISSING) return null;
    return z;
  }

  function heightAt(x, y) {
    if (!heights || !grid) return null;
    var cell = grid.cell_m || 50;
    var fx = (x - (grid.origin_x || 0)) / cell;
    var fy = (y - (grid.origin_y || 0)) / cell;
    if (fx < 0 || fy < 0 || fx > grid.cols - 1 || fy > grid.rows - 1) return null;
    var x0 = Math.floor(fx);
    var y0 = Math.floor(fy);
    var x1 = Math.min(grid.cols - 1, x0 + 1);
    var y1 = Math.min(grid.rows - 1, y0 + 1);
    var z00 = cellZ(x0, y0);
    var z10 = cellZ(x1, y0);
    var z01 = cellZ(x0, y1);
    var z11 = cellZ(x1, y1);
    if (z00 == null || z10 == null || z01 == null || z11 == null) return z00 != null ? z00 : (z10 != null ? z10 : (z01 != null ? z01 : z11));
    var tx = fx - x0;
    var ty = fy - y0;
    return (1 - tx) * (1 - ty) * z00 + tx * (1 - ty) * z10 + (1 - tx) * ty * z01 + tx * ty * z11;
  }

  function isReady() {
    return !!(grid && heights && grid.ready);
  }

  function decode(payload) {
    if (!payload || !payload.ready || !payload.heights) {
      grid = payload || null;
      heights = null;
      return false;
    }
    var bin;
    try {
      bin = atob(payload.heights);
    } catch (e) {
      return false;
    }
    var n = (payload.cols || 0) * (payload.rows || 0);
    if (n < 4) return false;
    var view = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) view[i] = bin.charCodeAt(i);
    var out = new Int16Array(n);
    var dv = new DataView(view.buffer, view.byteOffset, view.byteLength);
    var count = Math.min(n, Math.floor(view.byteLength / 2));
    for (var k = 0; k < count; k++) {
      out[k] = i16(dv.getUint16(k * 2, true));
    }
    grid = {
      ready: true,
      world_size: payload.world_size || 0,
      origin_x: payload.origin_x || 0,
      origin_y: payload.origin_y || 0,
      cell_m: payload.cell_m || 50,
      cols: payload.cols,
      rows: payload.rows,
      min_z: payload.min_z,
      max_z: payload.max_z,
      world_name: payload.world_name || ''
    };
    heights = out;
    return true;
  }

  function hypsoColor(t) {
    var stops = [
      [0.00, 30, 80, 40],
      [0.18, 56, 118, 52],
      [0.38, 140, 150, 70],
      [0.58, 168, 132, 72],
      [0.78, 150, 110, 78],
      [1.00, 236, 236, 230]
    ];
    t = Math.max(0, Math.min(1, t));
    for (var i = 1; i < stops.length; i++) {
      if (t <= stops[i][0]) {
        var a = stops[i - 1];
        var b = stops[i];
        var u = (t - a[0]) / (b[0] - a[0] || 1);
        return [
          a[1] + (b[1] - a[1]) * u,
          a[2] + (b[2] - a[2]) * u,
          a[3] + (b[3] - a[3]) * u
        ];
      }
    }
    return [236, 236, 230];
  }

  function renderCanvas(kind, azimuthDeg) {
    if (!isReady()) return null;
    var cols = grid.cols;
    var rows = grid.rows;
    var canvas = document.createElement('canvas');
    canvas.width = cols;
    canvas.height = rows;
    var ctx = canvas.getContext('2d');
    var img = ctx.createImageData(cols, rows);
    var data = img.data;
    var minZ = grid.min_z != null ? grid.min_z : 0;
    var maxZ = grid.max_z != null ? grid.max_z : 400;
    var span = Math.max(1, maxZ - minZ);
    var cell = grid.cell_m || 50;
    var az = ((azimuthDeg != null ? azimuthDeg : sunAzimuth) - 90) * Math.PI / 180;
    var zenith = 45 * Math.PI / 180;
    var sinZ = Math.sin(zenith);
    var cosZ = Math.cos(zenith);
    var contourStep = span > 250 ? 50 : (span > 80 ? 25 : 10);

    function slopeAspect(c, r) {
      var z2 = cellZ(c + 1, r + 1);
      var z3 = cellZ(c + 1, r);
      var z4 = cellZ(c + 1, r - 1);
      var z1 = cellZ(c, r + 1);
      var z5 = cellZ(c, r - 1);
      var z0 = cellZ(c - 1, r + 1);
      var z7 = cellZ(c - 1, r);
      var z6 = cellZ(c - 1, r - 1);
      if (z0 == null || z1 == null || z2 == null || z3 == null || z4 == null || z5 == null || z6 == null || z7 == null) {
        return null;
      }
      var dzdx = ((z2 + 2 * z3 + z4) - (z0 + 2 * z7 + z6)) / (8 * cell);
      var dzdy = ((z6 + 2 * z5 + z4) - (z0 + 2 * z1 + z2)) / (8 * cell);
      var slope = Math.atan(Math.sqrt(dzdx * dzdx + dzdy * dzdy));
      var aspect = Math.atan2(dzdy, -dzdx);
      return { slope: slope, aspect: aspect, dzdx: dzdx, dzdy: dzdy };
    }

    for (var r = 0; r < rows; r++) {
      var pr = rows - 1 - r;
      for (var c = 0; c < cols; c++) {
        var i = (pr * cols + c) * 4;
        var z = cellZ(c, r);
        if (z == null) {
          data[i + 3] = 0;
          continue;
        }
        var rgb = [40, 40, 40];
        var a = 220;
        if (kind === 'hypsometry') {
          rgb = hypsoColor((z - minZ) / span);
        } else if (kind === 'slope') {
          var sa = slopeAspect(c, r);
          var pct = sa ? Math.min(1, (Math.tan(sa.slope) * 100) / 45) : 0;
          rgb = [40 + pct * 200, 40 + (1 - pct) * 90, 30];
        } else if (kind === 'contours') {
          var n = cellZ(c + 1, r);
          var w = cellZ(c, r + 1);
          var band = Math.floor(z / contourStep);
          var edge = (n != null && Math.floor(n / contourStep) !== band) || (w != null && Math.floor(w / contourStep) !== band);
          if (edge) {
            rgb = [28, 22, 12];
            a = 230;
          } else {
            a = 0;
          }
        } else if (kind === 'ridges') {
          var up = cellZ(c, r + 1);
          var dn = cellZ(c, r - 1);
          var lf = cellZ(c - 1, r);
          var rt = cellZ(c + 1, r);
          var ridge = up != null && dn != null && lf != null && rt != null && z >= up && z >= dn && z >= lf && z >= rt;
          if (ridge) {
            rgb = [210, 190, 140];
            a = 230;
          } else a = 0;
        } else if (kind === 'depressions') {
          var u2 = cellZ(c, r + 1);
          var d2 = cellZ(c, r - 1);
          var l2 = cellZ(c - 1, r);
          var r2 = cellZ(c + 1, r);
          var bowl = u2 != null && d2 != null && l2 != null && r2 != null && z <= u2 && z <= d2 && z <= l2 && z <= r2;
          if (bowl) {
            rgb = [40, 90, 140];
            a = 210;
          } else a = 0;
        } else {
          var hs = slopeAspect(c, r);
          var shade = 0.35;
          if (hs) {
            shade = cosZ * Math.cos(hs.slope) + sinZ * Math.sin(hs.slope) * Math.cos(az - hs.aspect);
            shade = Math.max(0, Math.min(1, shade));
          }
          var v = 18 + shade * 210;
          rgb = [v, v, v];
        }
        data[i] = rgb[0];
        data[i + 1] = rgb[1];
        data[i + 2] = rgb[2];
        data[i + 3] = a;
      }
    }
    ctx.putImageData(img, 0, 0);
    return canvas;
  }

  function boundsLatLng() {
    if (!grid || !window.ATAKMap || !window.ATAKMap.latLngFromWorld) return null;
    var cell = grid.cell_m || 50;
    var x0 = grid.origin_x || 0;
    var y0 = grid.origin_y || 0;
    var x1 = x0 + (grid.cols - 1) * cell;
    var y1 = y0 + (grid.rows - 1) * cell;
    var sw = window.ATAKMap.latLngFromWorld(x0, y0);
    var ne = window.ATAKMap.latLngFromWorld(x1, y1);
    return [sw, ne];
  }

  function paint() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || !window.L) return;
    if (layer === 'off' || !isReady()) {
      if (overlay) {
        map.removeLayer(overlay);
        overlay = null;
      }
      lastKey = '';
      return;
    }
    var key = [layer, sunAzimuth, grid.cols, grid.rows, grid.min_z, grid.max_z].join('|');
    if (overlay && lastKey === key) {
      overlay.setOpacity(opacity);
      return;
    }
    var canvas = renderCanvas(layer, sunAzimuth);
    var b = boundsLatLng();
    if (!canvas || !b) return;
    if (!map.getPane(paneName)) {
      map.createPane(paneName);
      map.getPane(paneName).style.zIndex = 350;
      map.getPane(paneName).style.pointerEvents = 'none';
    }
    var url = canvas.toDataURL('image/png');
    if (overlay) {
      map.removeLayer(overlay);
      overlay = null;
    }
    overlay = window.L.imageOverlay(url, b, { opacity: opacity, pane: paneName, interactive: false });
    overlay.addTo(map);
    lastKey = key;
  }

  function setStatus(text) {
    if (!statusEl) statusEl = document.getElementById('atak-terrain-status');
    if (statusEl) statusEl.textContent = text || '';
  }

  function load() {
    if (!apiBase()) return Promise.resolve(false);
    return fetch(apiBase() + '/api/atak/terrain?mapId=' + encodeURIComponent(mapId()), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) {
      return r.text().then(function (raw) {
        var j = null;
        try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
        if (!r.ok || !j) {
          setStatus('Relief du théâtre non encore relevé.');
          return false;
        }
        return j;
      });
    }).then(function (j) {
      if (!j || j === false) return false;
      if (!j.ok) {
        setStatus('Relief indisponible.');
        return false;
      }
      if (!j.ready) {
        var pct = j.progress != null ? Math.round(j.progress * 100) : 0;
        setStatus(pct > 0 ? ('Relevé du relief en cours — ' + pct + ' %') : 'Relief du théâtre non encore relevé.');
        decode(j);
        paint();
        return false;
      }
      decode(j);
      setStatus('Relief chargé' + (j.world_name ? ' (' + j.world_name + ')' : '') + '.');
      paint();
      try { window.dispatchEvent(new CustomEvent('atak:terrain-ready', { detail: grid })); } catch (e) {}
      return true;
    }).catch(function () {
      setStatus('Impossible de charger le relief.');
      return false;
    });
  }

  function applyPrefs(p) {
    p = p || (window.ATAKMap && window.ATAKMap.getDisplayPrefs ? window.ATAKMap.getDisplayPrefs() : {});
    layer = p.terrainLayer || 'off';
    opacity = p.terrainOpacity != null ? Number(p.terrainOpacity) : 0.55;
    sunAzimuth = p.terrainSunAzimuth != null ? Number(p.terrainSunAzimuth) : 315;
    paint();
  }

  function syncUi(p) {
    p = p || (window.ATAKMap && window.ATAKMap.getDisplayPrefs ? window.ATAKMap.getDisplayPrefs() : {});
    var sel = document.getElementById('atak-terrain-layer');
    if (sel && p.terrainLayer) sel.value = p.terrainLayer;
    var op = document.getElementById('atak-terrain-opacity');
    var opv = document.getElementById('atak-terrain-opacity-val');
    if (op && p.terrainOpacity != null) op.value = String(Math.round(p.terrainOpacity * 100));
    if (opv && p.terrainOpacity != null) opv.textContent = Math.round(p.terrainOpacity * 100) + ' %';
    var sun = document.getElementById('atak-terrain-sun');
    var sunv = document.getElementById('atak-terrain-sun-val');
    if (sun && p.terrainSunAzimuth != null) sun.value = String(p.terrainSunAzimuth);
    if (sunv && p.terrainSunAzimuth != null) sunv.textContent = Math.round(p.terrainSunAzimuth) + '°';
  }

  function bindUi() {
    function patch(part) {
      if (window.ATAKMap && window.ATAKMap.patchDisplayPrefs) window.ATAKMap.patchDisplayPrefs(part);
    }
    var sel = document.getElementById('atak-terrain-layer');
    if (sel && !sel._bound) {
      sel._bound = true;
      sel.addEventListener('change', function () { patch({ terrainLayer: sel.value }); });
    }
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
    var sun = document.getElementById('atak-terrain-sun');
    if (sun && !sun._bound) {
      sun._bound = true;
      sun.addEventListener('input', function () {
        var v = parseInt(sun.value, 10);
        var lab = document.getElementById('atak-terrain-sun-val');
        if (lab) lab.textContent = v + '°';
        patch({ terrainSunAzimuth: v });
      });
    }
  }

  window.addEventListener('atak:mapready', function () {
    bindUi();
    applyPrefs();
    syncUi();
    load();
  });
  window.addEventListener('atak:display-prefs-changed', function (ev) {
    applyPrefs(ev.detail || {});
    syncUi(ev.detail || {});
  });

  return {
    LAYERS: LAYERS,
    load: load,
    heightAt: heightAt,
    isReady: isReady,
    getGrid: function () { return grid; },
    paint: paint,
    applyPrefs: applyPrefs
  };
})();
