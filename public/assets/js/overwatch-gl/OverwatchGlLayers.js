/* Overwatch Beta 3D — prismes bâtiments, forêts polygonales, contacts BFT (deck.gl). */
window.OverwatchGlLayers = (function () {
  'use strict';

  var overlay = null;
  var glMap = null;
  var proj = null;
  var buildings = [];
  var forests = [];
  var obstacles = [];
  var dem = null;
  var fetchTimer = 0;
  var unitTimer = 0;
  var lastSelected = '';
  var buildingsOn = true;
  var attached = false;
  var lastLod = '';
  var lastPickAt = 0;
  var focusId = '';
  var hoverId = '';
  var qualityOn = false;
  var sun = { night: false, intensity: 0.7 };
  var opts = { occlusion: 'always', ghost: false, heat: false, focus: false, cinematic: false };
  var lastCam = { x: 0, y: 0 };
  var meshMem = {};
  var loadHintTimer = 0;
  var SYMBOL_LIFT_M = 8;
  function sceneLoadHint(on) {
    window.clearTimeout(loadHintTimer);
    if (!on) {
      try { window.dispatchEvent(new CustomEvent('overwatch:scene-load', { detail: { on: false } })); } catch (e0) {}
      return;
    }
    loadHintTimer = window.setTimeout(function () {
      try { window.dispatchEvent(new CustomEvent('overwatch:scene-load', { detail: { on: true, text: 'Chargement du relevé…' } })); } catch (e1) {}
    }, 280);
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

  function sceneEnabled() {
    var toggle = document.getElementById('atak-scene-buildings');
    return !toggle || toggle.checked;
  }

  function unpackDem(data) {
    if (!data || data.encoding !== 'int16le_b64' || !data.heights) return null;
    var bin;
    try {
      var raw = atob(data.heights);
      var buf = new ArrayBuffer(raw.length);
      var view = new Uint8Array(buf);
      var i;
      for (i = 0; i < raw.length; i++) view[i] = raw.charCodeAt(i);
      bin = new DataView(buf);
    } catch (e) {
      return null;
    }
    return {
      cols: Number(data.cols) || 0,
      rows: Number(data.rows) || 0,
      cell: Number(data.cell_m) || 50,
      originX: Number(data.origin_x) || 0,
      originY: Number(data.origin_y) || 0,
      view: bin
    };
  }

  function cellZ(grid, c, r) {
    if (!grid || c < 0 || r < 0 || c >= grid.cols || r >= grid.rows) return null;
    var off = (r * grid.cols + c) * 2;
    if (off + 1 >= grid.view.byteLength) return null;
    var v = grid.view.getInt16(off, true);
    if (v === -32768) return null;
    return v;
  }

  function heightAt(x, y) {
    if (!dem || dem.cols < 2 || dem.rows < 2) return 0;
    var fx = (Number(x) - dem.originX) / dem.cell;
    var fy = (Number(y) - dem.originY) / dem.cell;
    if (fx < 0 || fy < 0 || fx > dem.cols - 1 || fy > dem.rows - 1) return 0;
    var x0 = Math.floor(fx);
    var y0 = Math.floor(fy);
    var x1 = Math.min(dem.cols - 1, x0 + 1);
    var y1 = Math.min(dem.rows - 1, y0 + 1);
    var tx = fx - x0;
    var ty = fy - y0;
    var z00 = cellZ(dem, x0, y0);
    var z10 = cellZ(dem, x1, y0);
    var z01 = cellZ(dem, x0, y1);
    var z11 = cellZ(dem, x1, y1);
    var z = z00;
    if (z00 == null) z = z10 != null ? z10 : (z01 != null ? z01 : z11);
    if (z == null) return 0;
    if (z10 == null || z01 == null || z11 == null) return z;
    return (1 - tx) * (1 - ty) * z00 + tx * (1 - ty) * z10 + (1 - tx) * ty * z01 + tx * ty * z11;
  }

  function loadDem() {
    return fetch(apiBase() + '/api/atak/terrain?include=heights&mapId=' + encodeURIComponent(mapId()), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        dem = unpackDem(data);
        return dem;
      })
      .catch(function () { return null; });
  }

  function footprint(item, scale) {
    scale = scale == null ? 1 : scale;
    var angle = Number(item.bearing || 0) * Math.PI / 180;
    var c = Math.cos(angle);
    var s = Math.sin(angle);
    var dims = buildingDims(item);
    var hw = (dims.w * scale) / 2;
    var hd = (dims.d * scale) / 2;
    /* AGL : sol = point le plus bas sous l’emprise (DEM ou base_z précalculé). */
    var baseZ = Number(item.base_z);
    if (!isFinite(baseZ)) baseZ = baseUnderFootprint(item, c, s, dims.w / 2, dims.d / 2);
    /* Léger enfoncement pour coller le volume au sol (ombre de contact). */
    baseZ -= 0.15;
    return [[-hw, -hd], [hw, -hd], [hw, hd], [-hw, hd]].map(function (v) {
      var x = Number(item.x) + v[0] * c - v[1] * s;
      var y = Number(item.y) + v[0] * s + v[1] * c;
      var ll = proj.worldToLngLat(x, y);
      return [ll[0], ll[1], baseZ];
    });
  }

  function shapeOf(item) {
    var known = String((item && item.shape) || '');
    if (known === 'pole' || known === 'ribbon' || known === 'panel' || known === 'building') return known;
    var w = Math.max(0.2, Number(item && item.width) || 4);
    var d = Math.max(0.2, Number(item && item.depth) || 4);
    var h = Math.max(0.5, Number(item && item.height) || 6);
    var shortEdge = Math.min(w, d);
    var longEdge = Math.max(w, d);
    var area = w * d;
    if (shortEdge < 1.15 && h >= 3.2 && h >= longEdge * 1.15) return 'pole';
    if (shortEdge < 1.0 && h > shortEdge * 3.5) return 'pole';
    if (shortEdge < 1.55 && longEdge / Math.max(0.01, shortEdge) >= 4) return 'ribbon';
    if (area < 9 && h > 2.8 && shortEdge < 2.2) return 'panel';
    return 'building';
  }

  function buildingDims(item) {
    var shape = shapeOf(item);
    var w = Math.max(0.2, Number(item.width) || 4);
    var d = Math.max(0.2, Number(item.depth) || 4);
    var h = Math.max(0.5, Number(item.height) || 6);
    if (shape === 'pole') {
      return { w: Math.min(1.1, Math.max(0.35, Math.min(w, d))), d: Math.min(1.1, Math.max(0.35, Math.min(w, d))), h: Math.min(16, Math.max(2, h)), shape: shape };
    }
    if (shape === 'panel') {
      return { w: Math.min(2.4, Math.max(0.4, w)), d: Math.min(1.0, Math.max(0.25, d)), h: Math.min(6, Math.max(1.2, h)), shape: shape };
    }
    if (shape === 'ribbon') {
      var longEdge = Math.max(w, d);
      var shortEdge = Math.min(w, d);
      return { w: Math.max(2, Math.min(220, longEdge)), d: Math.max(0.35, Math.min(1.1, shortEdge)), h: Math.min(2.6, Math.max(0.8, h)), shape: shape };
    }
    w = Math.max(2, w);
    d = Math.max(2, d);
    h = Math.max(2, h);
    var shortB = Math.min(w, d);
    var longB = Math.max(w, d);
    if (shortB > 0.01 && longB / shortB >= 5 && shortB < 4.5 && h > 3) {
      h = Math.min(h, 2.5);
      return { w: w, d: d, h: h, shape: 'ribbon', slab: true };
    }
    return { w: w, d: d, h: h, shape: 'building', slab: false };
  }

  function metersPerPixel() {
    if (!glMap || !proj) return 1;
    var b = glMap.getBounds();
    var sw = proj.lngLatToWorld(b.getWest(), b.getSouth());
    var ne = proj.lngLatToWorld(b.getEast(), b.getNorth());
    var span = Math.max(1, Math.hypot(ne.x - sw.x, ne.y - sw.y));
    var canvas = glMap.getCanvas ? glMap.getCanvas() : null;
    var px = canvas && canvas.clientWidth ? canvas.clientWidth : 900;
    return span / Math.max(1, px);
  }

  function visibleOnScreen(item, minPx) {
    minPx = minPx == null ? 3.5 : minPx;
    var dims = buildingDims(item);
    var mpp = metersPerPixel();
    return Math.max(dims.w, dims.d) / mpp >= minPx;
  }

  function partitionBuildings(rows) {
    var real = [];
    var poles = [];
    var ribbons = [];
    (rows || []).forEach(function (item) {
      if (!item) return;
      var dims = buildingDims(item);
      var shape = dims.shape || shapeOf(item);
      if (shape === 'pole' || shape === 'panel') poles.push(item);
      else if (shape === 'ribbon' || dims.slab) ribbons.push(item);
      else if (visibleOnScreen(item, 3.2)) real.push(item);
    });
    return { buildings: real, poles: poles, ribbons: ribbons };
  }

  function shortUnitLabel(raw, zoom) {
    var text = String(raw || '').trim();
    if (!text) return '';
    var main = text.split(/\s*\/\s*/)[0].trim();
    if (zoom < 14.2) {
      var token = main.split(/\s+/)[0] || main;
      return token.length > 10 ? token.slice(0, 9) + '…' : token;
    }
    return text.length > 22 ? text.slice(0, 21) + '…' : text;
  }

  function screenOfUnit(u) {
    if (!glMap || !u || !u.position) return null;
    try {
      return glMap.project([u.position[0], u.position[1]]);
    } catch (e0) {
      return null;
    }
  }

  function screenDistToCamera(u) {
    if (!glMap || !u || !u.position) return 0;
    try {
      var c = glMap.getCenter();
      var a = glMap.project([c.lng, c.lat]);
      var b = glMap.project([u.position[0], u.position[1]]);
      return Math.hypot(a.x - b.x, a.y - b.y);
    } catch (e1) {
      return 0;
    }
  }

  function declutterUnitLabels(units, zoom) {
    var bins = {};
    var out = [];
    var selected = selectedUnitId();
    /* Regroupement selon la distance projetée en pixels (pas les mètres). */
    var cell = zoom >= 15.2 ? 44 : (zoom >= 14.2 ? 60 : (zoom >= 13.2 ? 78 : 110));
    var ranked = (units || []).slice().sort(function (a, b) {
      var ap = a.selected || String(a.id || '') === hoverId ? 0 : 1;
      var bp = b.selected || String(b.id || '') === hoverId ? 0 : 1;
      if (ap !== bp) return ap - bp;
      return (a.screenDist || 0) - (b.screenDist || 0);
    });
    ranked.forEach(function (u) {
      var id = String(u.id || '');
      var full = !!u.selected || id === hoverId || id === selected;
      if (!full && zoom < 13.2) return;
      var label = full ? String(u.label || '') : shortUnitLabel(u.label, zoom);
      if (!label) return;
      var pt = screenOfUnit(u);
      if (!pt) return;
      var gx = Math.floor(pt.x / cell);
      var gy = Math.floor(pt.y / cell);
      var key = gx + ':' + gy;
      if (bins[key] && !full) return;
      bins[key] = true;
      out.push(Object.assign({}, u, { shortLabel: label, fullLabel: full }));
    });
    return out;
  }

  function unitInsideBuilding(x, y, symbolZ) {
    if (!buildings || !buildings.length) return false;
    var i;
    for (i = 0; i < buildings.length; i++) {
      var item = buildings[i];
      if (!item) continue;
      var dims = buildingDims(item);
      if (dims.shape === 'pole' || dims.shape === 'ribbon' || dims.slab) continue;
      var dx = Number(x) - Number(item.x);
      var dy = Number(y) - Number(item.y);
      if (!isFinite(dx) || !isFinite(dy)) continue;
      var angle = Number(item.bearing || 0) * Math.PI / 180;
      var c = Math.cos(-angle);
      var s = Math.sin(-angle);
      var lx = dx * c - dy * s;
      var ly = dx * s + dy * c;
      var hw = dims.w / 2 + 1.2;
      var hd = dims.d / 2 + 1.2;
      if (Math.abs(lx) > hw || Math.abs(ly) > hd) continue;
      var base = Number(item.base_z);
      if (!isFinite(base)) base = heightAt(item.x, item.y);
      var roof = base + Math.max(2, Number(item.height) || 6);
      if (roof > symbolZ - 1.5) return true;
    }
    return false;
  }

  function partitionObstacles(rows) {
    var poles = [];
    var ribbons = [];
    var longPaths = [];
    (rows || []).forEach(function (item) {
      if (!item) return;
      var kind = String(item.kind || 'wall');
      var dims = buildingDims(item);
      var shape = dims.shape || shapeOf(item);
      if (kind === 'pylon' || kind === 'power' || shape === 'pole' || shape === 'panel') {
        poles.push(item);
        return;
      }
      if (Math.max(dims.w, dims.d) > 55 && Math.min(dims.w, dims.d) < 3) {
        longPaths.push(item);
        return;
      }
      ribbons.push(item);
    });
    return { poles: poles, ribbons: ribbons, longPaths: longPaths };
  }

  function baseUnderFootprint(item, c, s, hw, hd) {
    var samples = [[-hw, -hd], [hw, -hd], [hw, hd], [-hw, hd], [0, 0]];
    var minZ = Infinity;
    var i;
    for (i = 0; i < samples.length; i++) {
      var v = samples[i];
      var x = Number(item.x) + v[0] * c - v[1] * s;
      var y = Number(item.y) + v[0] * s + v[1] * c;
      var z = heightAt(x, y);
      if (isFinite(z) && z < minZ) minZ = z;
    }
    return isFinite(minZ) ? minZ : heightAt(item.x, item.y);
  }

  function obstaclePath(item) {
    var angle = Number(item.bearing || 0) * Math.PI / 180;
    var c = Math.cos(angle);
    var s = Math.sin(angle);
    var dims = buildingDims(item);
    var half = Math.max(dims.w, dims.d) / 2;
    var z = Number(item.base_z);
    if (!isFinite(z)) z = heightAt(item.x, item.y);
    var a = proj.worldToLngLat(Number(item.x) - half * c, Number(item.y) - half * s);
    var b = proj.worldToLngLat(Number(item.x) + half * c, Number(item.y) + half * s);
    return [[a[0], a[1], z + 0.4], [b[0], b[1], z + 0.4]];
  }

  function polePosition(item) {
    var ll = proj.worldToLngLat(item.x, item.y);
    var z = Number(item.base_z);
    if (!isFinite(z)) z = heightAt(item.x, item.y);
    return [ll[0], ll[1], z];
  }

  function worldBbox() {
    if (!glMap || !proj) return null;
    var b = glMap.getBounds();
    var sw = proj.lngLatToWorld(b.getWest(), b.getSouth());
    var ne = proj.lngLatToWorld(b.getEast(), b.getNorth());
    var minX = Math.min(sw.x, ne.x);
    var minY = Math.min(sw.y, ne.y);
    var maxX = Math.max(sw.x, ne.x);
    var maxY = Math.max(sw.y, ne.y);
    var cx = (minX + maxX) / 2;
    var cy = (minY + maxY) / 2;
    var padW = 80;
    var padE = 80;
    var padS = 80;
    var padN = 80;
    var dx = cx - lastCam.x;
    var dy = cy - lastCam.y;
    if (Math.hypot(dx, dy) > 40) {
      if (dx > 0) padE = 420; else padW = 420;
      if (dy > 0) padN = 420; else padS = 420;
    }
    lastCam = { x: cx, y: cy };
    return [minX - padW, minY - padS, maxX + padE, maxY + padN];
  }

  function clusterForests(items, cell) {
    cell = cell || 80;
    var bins = {};
    items.forEach(function (it) {
      var gx = Math.floor(Number(it.x) / cell);
      var gy = Math.floor(Number(it.y) / cell);
      var k = gx + ':' + gy;
      if (!bins[k]) bins[k] = { gx: gx, gy: gy, n: 0, h: 0, d: 0 };
      bins[k].n += 1;
      bins[k].h += Number(it.height) || 8;
      bins[k].d += Number(it.density) || 1;
    });
    return Object.keys(bins).map(function (k) {
      var b = bins[k];
      var cx = b.gx * cell + cell / 2;
      var cy = b.gy * cell + cell / 2;
      var z = heightAt(cx, cy);
      var cover = Math.min(0.9, 0.28 + b.n * 0.07);
      var radius = (cell / 2) * cover;
      var poly = [];
      var steps = 12;
      var s;
      for (s = 0; s < steps; s++) {
        var t = (s / steps) * Math.PI * 2;
        var ll = proj.worldToLngLat(cx + Math.cos(t) * radius, cy + Math.sin(t) * radius);
        poly.push([ll[0], ll[1], z]);
      }
      return {
        polygon: poly,
        height: Math.max(4, Math.min(12, b.h / b.n)),
        density: b.d / b.n
      };
    });
  }

  function lodForZoom() {
    var zoom = glMap ? glMap.getZoom() : 12;
    if (zoom < 11.8) return '0';
    if (zoom < 13.4) return '1';
    if (zoom < 15.1) return '2';
    return '3';
  }

  function fetchKind(kind, bbox) {
    var q = bbox.map(function (n) { return Number(n).toFixed(1); }).join(',');
    return fetch(
      apiBase() + '/api/atak/scene?mapId=' + encodeURIComponent(mapId())
        + '&bbox=' + encodeURIComponent(q)
        + '&kind=' + encodeURIComponent(kind)
        + '&limit=8000',
      { credentials: 'same-origin' }
    ).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) { return data && Array.isArray(data.objects) ? data.objects : []; })
      .catch(function () { return []; });
  }

  function meshCacheKey(bbox, lod) {
    var q = bbox.map(function (n) { return String(Math.round(Number(n) / 200) * 200); }).join(',');
    return mapId() + ':' + lod + ':' + q;
  }

  function idbGet(key) {
    return new Promise(function (resolve) {
      try {
        var req = indexedDB.open('ow-scene-cache', 1);
        req.onupgradeneeded = function () {
          if (!req.result.objectStoreNames.contains('chunks')) req.result.createObjectStore('chunks');
        };
        req.onsuccess = function () {
          var tx = req.result.transaction('chunks', 'readonly');
          var g = tx.objectStore('chunks').get(key);
          g.onsuccess = function () { resolve(g.result || null); };
          g.onerror = function () { resolve(null); };
        };
        req.onerror = function () { resolve(null); };
      } catch (e) { resolve(null); }
    });
  }

  function idbSet(key, value) {
    try {
      var req = indexedDB.open('ow-scene-cache', 1);
      req.onupgradeneeded = function () {
        if (!req.result.objectStoreNames.contains('chunks')) req.result.createObjectStore('chunks');
      };
      req.onsuccess = function () {
        req.result.transaction('chunks', 'readwrite').objectStore('chunks').put(value, key);
      };
    } catch (e) {}
  }

  function fetchBuildings(bbox) {
    var q = bbox.map(function (n) { return Number(n).toFixed(1); }).join(',');
    var lod = lodForZoom();
    lastLod = lod;
    var key = meshCacheKey(bbox, lod);
    if (meshMem[key] && Date.now() - meshMem[key].t < 45000) {
      obstacles = meshMem[key].obstacles || [];
      return Promise.resolve(meshMem[key].objects || []);
    }
    return idbGet(key).then(function (cached) {
      if (cached && cached.objects) {
        meshMem[key] = { t: Date.now(), objects: cached.objects, obstacles: cached.obstacles || [], stamp: cached.stamp };
        obstacles = cached.obstacles || [];
      }
      return fetch(
        apiBase() + '/api/atak/scene/mesh?mapId=' + encodeURIComponent(mapId())
          + '&bbox=' + encodeURIComponent(q)
          + '&lod=' + encodeURIComponent(lod),
        { credentials: 'same-origin' }
      ).then(function (r) { return r.ok ? r.json() : null; });
    }).then(function (data) {
      if (!data) return fetchKind('building', bbox);
      obstacles = Array.isArray(data.obstacles) ? data.obstacles : [];
      var objects = Array.isArray(data.objects) && data.objects.length ? data.objects : null;
      var stamp = String(data.stamp || data.schema || '');
      meshMem[key] = { t: Date.now(), objects: objects || [], obstacles: obstacles, stamp: stamp };
      idbSet(key, { objects: objects || [], obstacles: obstacles, stamp: stamp });
      if (objects) return objects;
      return fetchKind('building', bbox);
    }).catch(function () { return fetchKind('building', bbox); });
  }

  function loadVisible() {
    if (!glMap || !proj || !buildingsOn) {
      buildings = [];
      forests = [];
      obstacles = [];
      sceneLoadHint(false);
      redraw();
      return;
    }
    var bbox = worldBbox();
    if (!bbox) return;
    var lod = lodForZoom();
    var forestP = (lod === '0' || lod === '1') ? Promise.resolve([]) : fetchKind('forest', bbox);
    sceneLoadHint(true);
    Promise.all([fetchBuildings(bbox), forestP]).then(function (rows) {
      buildings = rows[0] || [];
      forests = rows[1] || [];
      if (!obstacles.length && lastLod !== '0' && lastLod !== '1') {
        Promise.all([
          fetchKind('wall', bbox),
          fetchKind('fence', bbox),
          fetchKind('power', bbox),
          fetchKind('bridge', bbox),
          fetchKind('rock', bbox),
          fetchKind('pylon', bbox)
        ]).then(function (sets) {
          obstacles = [].concat.apply([], sets);
          redraw();
        });
      }
      redraw();
    }).then(function () { sceneLoadHint(false); }, function () { sceneLoadHint(false); });
  }

  function queueLoad() {
    window.clearTimeout(fetchTimer);
    fetchTimer = window.setTimeout(loadVisible, 200);
  }

  function unitSide(unit) {
    if (window.OverwatchGlSymbols && window.OverwatchGlSymbols.affiliationOf) {
      var aff = window.OverwatchGlSymbols.affiliationOf(unit);
      if (aff === 'hostile' || aff === 'enemy') return 'hostile';
      if (aff === 'unknown' || aff === 'suspect' || aff === 'neutral') return aff === 'neutral' ? 'unknown' : 'unknown';
      return 'friendly';
    }
    var raw = String((unit && (unit.side || unit.faction || unit.iff || unit.type)) || '').toLowerCase();
    if (/hostile|opfor|east|enemy/.test(raw)) return 'hostile';
    if (/unknown|civ|neutral/.test(raw)) return 'unknown';
    return 'friendly';
  }

  function unitColor(unit) {
    var s = unitSide(unit);
    if (s === 'hostile') return [220, 70, 60, 230];
    if (s === 'unknown') return [230, 190, 70, 230];
    return [80, 200, 140, 230];
  }

  function selectedUnitId() {
    try {
      var ow = window.OverwatchBeta;
      var sel = ow && ow.getSelected ? ow.getSelected() : null;
      return sel && ow.unitId ? String(ow.unitId(sel)) : '';
    } catch (e) {
      return '';
    }
  }

  function unitPoints() {
    var ow = window.OverwatchBeta;
    if (!ow || typeof ow.getUnits !== 'function' || !proj) return [];
    var Sym = window.OverwatchGlSymbols;
    var clock = replayClock();
    var samples = clock != null && ow.getTrackSamples ? ow.getTrackSamples() : null;
    var selected = selectedUnitId();
    var zoom = glMap && glMap.getZoom ? glMap.getZoom() : 14;
    return (ow.getUnits() || []).map(function (unit) {
      var x = Number(unit.pos_x != null ? unit.pos_x : unit.x);
      var y = Number(unit.pos_y != null ? unit.pos_y : unit.y);
      if (clock != null && samples && ow.unitId) {
        var rows = samples[ow.unitId(unit)] || [];
        if (rows.length) {
          var chosen = rows[0];
          rows.forEach(function (row) { if (row.t <= clock) chosen = row; });
          if (chosen && chosen.ll && ow.latLngToWorld) {
            var w = ow.latLngToWorld(chosen.ll);
            if (w) { x = w.x; y = w.y; }
          }
        }
      }
      if (!isFinite(x) || !isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return null;
      var ll = proj.worldToLngLat(x, y);
      var groundZ = heightAt(x, y);
      var air = Sym && Sym.isAircraft ? Sym.isAircraft(unit) : false;
      var asl = Sym && Sym.altitudeAslOf ? Sym.altitudeAslOf(unit) : null;
      var symbolZ = groundZ + SYMBOL_LIFT_M;
      if (air && asl != null && asl > groundZ + 4) symbolZ = asl;
      var foot = [ll[0], ll[1], groundZ + 0.35];
      var position = [ll[0], ll[1], symbolZ];
      var leader = [foot, position];
      var label = ow.callsign ? ow.callsign(unit) : (unit.call_sign || unit.callsign || 'Contact');
      var parent = ow.group ? ow.group(unit) : (unit.fire_team_label || unit.group_name || '');
      var heading = Sym && Sym.headingOf ? Sym.headingOf(unit) : null;
      var speedMs = Sym && Sym.speedMsOf ? Sym.speedMsOf(unit) : 0;
      var fresh = Sym && Sym.freshnessOf ? Sym.freshnessOf(unit) : 1;
      var icon = Sym && Sym.iconForUnit ? Sym.iconForUnit(unit, label) : null;
      var vel = Sym && Sym.velocityPath
        ? Sym.velocityPath(ll[0], ll[1], symbolZ, heading, speedMs)
        : null;
      var id = ow.unitId ? ow.unitId(unit) : label;
      var occluded = opts.occlusion !== 'never' && unitInsideBuilding(x, y, symbolZ);
      var sector = null;
      if (heading != null && Sym && Sym.sectorFan && (String(id) === selected || String(id) === hoverId)) {
        sector = Sym.sectorFan(ll[0], ll[1], groundZ + 0.6, heading, Math.max(35, Math.min(120, (speedMs || 0) * 8 + 45)), 26, 12);
      }
      var altLabel = '';
      if (air && asl != null) {
        altLabel = Math.round(asl) + ' m';
      }
      var point = {
        position: position,
        foot: foot,
        groundZ: groundZ,
        symbolZ: symbolZ,
        leader: leader,
        color: unitColor(unit),
        label: label,
        parent: parent && parent !== 'Sans groupe' ? parent : '',
        id: id,
        x: x,
        y: y,
        heading: heading,
        speedMs: speedMs,
        freshness: fresh,
        medical: Sym && Sym.isMedicalAlert ? Sym.isMedicalAlert(unit) : false,
        critical: Sym && Sym.isCritical ? Sym.isCritical(unit) : false,
        selected: String(id) === selected,
        hovered: String(id) === hoverId,
        icon: icon,
        velocity: vel,
        affiliation: Sym && Sym.affiliationOf ? Sym.affiliationOf(unit) : unitSide(unit),
        air: air,
        occluded: occluded,
        sector: sector,
        altLabel: altLabel
      };
      point.screenDist = screenDistToCamera(point);
      /* Taille / opacité selon distance écran, plancher de lisibilité. */
      var far = point.screenDist;
      var sizePx = 34;
      if (far > 220) sizePx = 28;
      if (far > 360) sizePx = 24;
      if (zoom < 13.5) sizePx = Math.min(sizePx, 26);
      point.sizePx = Math.max(20, sizePx);
      var alpha = Math.max(0.28, fresh);
      if (occluded) alpha *= 0.6;
      if (far > 300) alpha *= 0.82;
      if (far > 450) alpha *= 0.75;
      point.alpha = Math.max(0.22, Math.min(1, alpha));
      return point;
    }).filter(Boolean);
  }

  function unitMoveTrails() {
    var ow = window.OverwatchBeta;
    if (!ow || !ow.getTrackSamples || !proj) return [];
    var samples = ow.getTrackSamples() || {};
    var out = [];
    Object.keys(samples).forEach(function (id) {
      var rows = (samples[id] || []).filter(function (row) { return row.ll; }).slice(-16);
      if (rows.length < 2) return;
      var path = [];
      rows.forEach(function (row, i) {
        var w = ow.latLngToWorld ? ow.latLngToWorld(row.ll) : null;
        if (!w) return;
        var ll = proj.worldToLngLat(w.x, w.y);
        path.push([ll[0], ll[1], heightAt(w.x, w.y) + 2.2]);
      });
      if (path.length < 2) return;
      out.push({
        path: path,
        id: id,
        color: [160, 200, 180, 120]
      });
    });
    return out.slice(0, 80);
  }

  function replayPaths() {
    var ow = window.OverwatchBeta;
    var clock = replayClock();
    if (clock == null || !ow || !ow.getTrackSamples || !proj) return [];
    var samples = ow.getTrackSamples() || {};
    return Object.keys(samples).map(function (id) {
      var rows = (samples[id] || []).filter(function (row) { return row.t <= clock && row.ll; });
      if (rows.length < 2) return null;
      return {
        path: rows.slice(-48).map(function (row) {
          var w = ow.latLngToWorld ? ow.latLngToWorld(row.ll) : null;
          if (!w) return null;
          var ll = proj.worldToLngLat(w.x, w.y);
          return [ll[0], ll[1], heightAt(w.x, w.y) + 2];
        }).filter(Boolean)
      };
    }).filter(Boolean);
  }

  function missionNear(x, y) {
    var apiOw = window.OverwatchBeta;
    if (!apiOw) return true;
    var sel = apiOw.getSelected && apiOw.getSelected();
    if (sel && apiOw.point && apiOw.latLngToWorld) {
      var ll = apiOw.point(sel);
      var w = ll ? apiOw.latLngToWorld(ll) : null;
      if (w && Math.hypot(w.x - x, w.y - y) < 220) return true;
    }
    var markers = apiOw.getMarkers ? apiOw.getMarkers() : [];
    for (var i = 0; i < markers.length; i++) {
      var m = markers[i];
      var mx = Number(m.pos_x != null ? m.pos_x : m.x);
      var my = Number(m.pos_y != null ? m.pos_y : m.y);
      if (isFinite(mx) && Math.hypot(mx - x, my - y) < 180) return true;
    }
    return false;
  }

  function clusteredUnits(units) {
    var bins = {};
    units.forEach(function (u) {
      var k = Math.round(Number(u.x) / 14) + ':' + Math.round(Number(u.y) / 14);
      if (!bins[k]) bins[k] = [];
      bins[k].push(u);
    });
    var singles = [];
    var clusters = [];
    Object.keys(bins).forEach(function (k) {
      var bag = bins[k];
      if (bag.length < 3) {
        singles = singles.concat(bag);
        return;
      }
      var first = bag[0];
      clusters.push({
        position: first.position,
        count: bag.length,
        items: bag,
        label: String(bag.length),
        x: first.x,
        y: first.y,
        kind: 'stack'
      });
    });
    return { singles: singles, clusters: clusters };
  }

  function ghostTrails() {
    if (!opts.ghost || !proj) return [];
    var apiOw = window.OverwatchBeta;
    if (!apiOw || !apiOw.getTrackSamples) return [];
    var samples = apiOw.getTrackSamples() || {};
    var out = [];
    Object.keys(samples).forEach(function (id) {
      var rows = (samples[id] || []).filter(function (row) { return row.ll; }).slice(-12);
      rows.forEach(function (row, i) {
        var w = apiOw.latLngToWorld ? apiOw.latLngToWorld(row.ll) : null;
        if (!w) return;
        var ll = proj.worldToLngLat(w.x, w.y);
        out.push({
          position: [ll[0], ll[1], heightAt(w.x, w.y) + 3],
          color: [180, 210, 200, Math.round(30 + (i / Math.max(1, rows.length - 1)) * 140)]
        });
      });
    });
    return out;
  }

  function heatPoints() {
    if (!opts.heat || !proj) return [];
    var apiOw = window.OverwatchBeta;
    if (!apiOw || !apiOw.getTrackSamples) return [];
    var samples = apiOw.getTrackSamples() || {};
    var out = [];
    Object.keys(samples).forEach(function (id) {
      (samples[id] || []).forEach(function (row) {
        if (!row.ll) return;
        var w = apiOw.latLngToWorld ? apiOw.latLngToWorld(row.ll) : null;
        if (!w) return;
        var ll = proj.worldToLngLat(w.x, w.y);
        out.push({ position: [ll[0], ll[1], heightAt(w.x, w.y)] });
      });
    });
    return out.slice(0, 4000);
  }

  function volumeShapes() {
    var apiOw = window.OverwatchBeta;
    if (!apiOw || !apiOw.getShapes || !proj) return [];
    return (apiOw.getShapes() || []).map(function (shape) {
      var meta = shape.meta;
      if (typeof meta === 'string') {
        try { meta = JSON.parse(meta); } catch (e) { meta = {}; }
      }
      meta = meta && typeof meta === 'object' ? meta : {};
      if (!meta.volume && meta.z_min == null && meta.z_max == null) return null;
      var geo = shape.geometry;
      if (typeof geo === 'string') {
        try { geo = JSON.parse(geo); } catch (e2) { geo = null; }
      }
      var ring = geo && geo.coordinates ? (geo.coordinates[0] && Array.isArray(geo.coordinates[0][0]) ? geo.coordinates[0] : geo.coordinates) : null;
      if (!ring || ring.length < 3) return null;
      var zMin = Number(meta.z_min);
      var zMax = Number(meta.z_max);
      if (!isFinite(zMin)) zMin = 0;
      if (!isFinite(zMax)) zMax = zMin + 8;
      return {
        polygon: ring.map(function (p) {
          var ll = proj.worldToLngLat(Number(p[0]), Number(p[1]));
          return [ll[0], ll[1], zMin];
        }),
        height: Math.max(1, zMax - zMin)
      };
    }).filter(Boolean);
  }

  function unitDepthTest() {
    return opts.occlusion !== 'always';
  }

  function followSelected() {
    var ow = window.OverwatchBeta;
    if (!ow || typeof ow.getSelected !== 'function') return;
    var sel = ow.getSelected();
    var id = sel && ow.unitId ? ow.unitId(sel) : '';
    var x = Number(sel && (sel.pos_x != null ? sel.pos_x : sel.x));
    var y = Number(sel && (sel.pos_y != null ? sel.pos_y : sel.y));
    if (!isFinite(x) || !isFinite(y)) return;
    var cam = window.OverwatchGlMap && window.OverwatchGlMap.cameraMode ? window.OverwatchGlMap.cameraMode() : '';
    if (cam === 'follow' && id && window.OverwatchGlMap.followWorld) {
      lastSelected = id;
      window.OverwatchGlMap.followWorld(x, y);
      return;
    }
    if (!id || id === lastSelected) return;
    lastSelected = id;
    if (window.OverwatchGlMap && window.OverwatchGlMap.isActive()) {
      window.OverwatchGlMap.flyToWorld(x, y);
    }
  }

  function leafletFromLngLat(lng, lat) {
    if (!proj) return null;
    var leaf = proj.lngLatToLeaflet(lng, lat);
    if (!leaf) return null;
    if (window.L && typeof window.L.latLng === 'function') return window.L.latLng(leaf.lat, leaf.lng);
    return leaf;
  }

  function pickWorld(ll, originalEvent, layer) {
    lastPickAt = Date.now();
    var ow = window.OverwatchBeta;
    if (!ow || !ll) return;
    if (typeof ow.handleWorldClick === 'function') {
      ow.handleWorldClick(ll, originalEvent, layer);
      return;
    }
    if (typeof ow.inspectWorld === 'function') ow.inspectWorld(ll, originalEvent, layer);
    else if (typeof ow.openContextAt === 'function') ow.openContextAt(ll, originalEvent, layer);
  }

  function pickContext(ll, originalEvent, layer) {
    lastPickAt = Date.now();
    var ow = window.OverwatchBeta;
    if (!ow || !ll) return;
    if (typeof ow.handleWorldContext === 'function') ow.handleWorldContext(ll, originalEvent, layer);
    else if (typeof ow.openContextAt === 'function') ow.openContextAt(ll, originalEvent, layer);
  }

  function hash01(seed) {
    var s = String(seed || '');
    var h = 2166136261;
    var i;
    for (i = 0; i < s.length; i++) {
      h ^= s.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return ((h >>> 0) % 10000) / 10000;
  }

  function facadePalette(facade) {
    var f = String(facade || 'concrete');
    if (f === 'house') return { wall: [198, 186, 168], roof: [112, 96, 82] };
    if (f === 'hangar') return { wall: [168, 174, 180], roof: [86, 92, 98] };
    if (f === 'church') return { wall: [188, 184, 176], roof: [92, 78, 68] };
    if (f === 'tower') return { wall: [156, 160, 166], roof: [78, 82, 88] };
    if (f === 'stone') return { wall: [172, 164, 148], roof: [98, 90, 78] };
    return { wall: [176, 184, 192], roof: [96, 104, 112] };
  }

  function buildingElevation(d) {
    return buildingDims(d).h;
  }

  function buildingAlpha(d) {
    var zoom = glMap ? glMap.getZoom() : 12;
    var alpha = 195;
    /* Zoom élevé : laisser lire la photo des toits (moins de « double village »). */
    if (zoom >= 15.4) alpha = 88;
    else if (zoom >= 14.6) alpha = 125;
    else if (zoom >= 13.8) alpha = 160;
    else if (zoom < 12.2) alpha = 70;
    if (focusId && d && String(d.id || '') !== focusId) alpha = Math.min(alpha, 48);
    if (focusId && d && String(d.id || '') === focusId) alpha = Math.max(alpha, 220);
    if (opts.focus && d && !missionNear(Number(d.x), Number(d.y))) alpha = Math.min(alpha, 40);
    else if (!opts.focus && d && !missionNear(Number(d.x), Number(d.y)) && zoom < 14.2) {
      alpha = Math.round(alpha * 0.72);
    }
    return alpha;
  }

  function buildingFill(d) {
    var n = sun && sun.night ? 0.62 : Math.max(0.72, Math.min(1, Number(sun && sun.intensity) || 0.85) + 0.12);
    var alpha = buildingAlpha(d);
    if (qualityOn) {
      var q = String((d && d.quality) || '');
      if (q === 'complete') return [70, 170, 90, alpha];
      if (q === 'approx') return [220, 150, 50, alpha];
      if (q === 'position') return [140, 145, 150, alpha];
      if (q === 'suspect') return [200, 70, 60, alpha];
    }
    var pal = facadePalette(d && d.facade);
    var tint = hash01((d && d.id) || (String(d && d.x) + ':' + String(d && d.y)));
    var wobble = (tint - 0.5) * 22;
    var ao = d && d.cluster ? 0.88 : 0.94;
    var r = Math.round(Math.max(40, Math.min(230, (pal.wall[0] + wobble) * n * ao)));
    var g = Math.round(Math.max(40, Math.min(230, (pal.wall[1] + wobble * 0.7) * n * ao)));
    var b = Math.round(Math.max(40, Math.min(230, (pal.wall[2] - wobble * 0.4) * n * ao)));
    /* Occlusion ambiante fake : légèrement plus sombre pour ancrer le volume. */
    r = Math.round(r * 0.92);
    g = Math.round(g * 0.93);
    b = Math.round(b * 0.95);
    return [r, g, b, alpha];
  }

  function roofFill(d) {
    var n = sun && sun.night ? 0.55 : Math.max(0.65, Math.min(0.95, Number(sun && sun.intensity) || 0.8));
    var alpha = Math.min(200, buildingAlpha(d) + 20);
    var pal = facadePalette(d && d.facade);
    var tint = hash01('roof:' + ((d && d.id) || ''));
    var wobble = (tint - 0.5) * 14;
    return [
      Math.round(Math.max(30, Math.min(180, (pal.roof[0] + wobble) * n))),
      Math.round(Math.max(30, Math.min(180, (pal.roof[1] + wobble * 0.6) * n))),
      Math.round(Math.max(30, Math.min(180, (pal.roof[2] - wobble * 0.3) * n))),
      alpha
    ];
  }

  function buildingMaterial() {
    return {
      ambient: 0.38,
      diffuse: 0.72,
      shininess: 12,
      specularColor: [55, 58, 62]
    };
  }

  function lightingEffects() {
    if (!window.deck || !window.deck.LightingEffect) return [];
    var intensity = sun && sun.night ? 0.28 : Math.max(0.45, Math.min(1, Number(sun && sun.intensity) || 0.75));
    var az = (Number(sun && sun.azimuth) || 210) * Math.PI / 180;
    var elev = Math.max(8, 90 - (Number(sun && sun.polar) || 45)) * Math.PI / 180;
    var dir = [
      -Math.cos(elev) * Math.sin(az),
      -Math.sin(elev),
      -Math.cos(elev) * Math.cos(az)
    ];
    var ambient = window.deck.AmbientLight
      ? new window.deck.AmbientLight({ color: sun && sun.night ? [140, 156, 190] : [220, 228, 236], intensity: sun && sun.night ? 0.42 : 0.58 })
      : null;
    var directional = window.deck.DirectionalLight
      ? new window.deck.DirectionalLight({
        color: sun && sun.night ? [160, 178, 210] : [255, 236, 210],
        intensity: intensity,
        direction: dir
      })
      : null;
    var optsLight = {};
    if (ambient) optsLight.ambientLight = ambient;
    if (directional) optsLight.directionalLights = [directional];
    try {
      return [new window.deck.LightingEffect(optsLight)];
    } catch (e) {
      return [];
    }
  }

  function obstacleFill(d) {
    var kind = String((d && d.kind) || 'wall');
    var alpha = focusId ? 80 : 210;
    if (kind === 'fence') return [198, 184, 92, alpha];
    if (kind === 'power') return [92, 98, 128, alpha];
    if (kind === 'bridge') return [128, 138, 152, alpha];
    if (kind === 'rock') return [118, 108, 96, alpha];
    if (kind === 'pylon') return [88, 102, 78, alpha];
    return [168, 158, 148, alpha];
  }

  function obstacleLabel(d) {
    var kind = String((d && d.kind) || 'wall');
    if (kind === 'fence') return 'Clôture';
    if (kind === 'power') return 'Ligne électrique';
    if (kind === 'bridge') return 'Pont';
    if (kind === 'rock') return 'Rocher';
    if (kind === 'pylon') return 'Pylône';
    return 'Mur';
  }

  function replayClock() {
    var bar = document.getElementById('ow-timeline');
    var scrub = document.getElementById('ow-replay-scrub');
    if (!bar || bar.hidden || !scrub || Number(scrub.value) >= 99) return null;
    var ow = window.OverwatchBeta;
    var samples = ow && ow.getTrackSamples ? ow.getTrackSamples() : null;
    if (!samples) return null;
    var minT = Infinity;
    var maxT = 0;
    Object.keys(samples).forEach(function (id) {
      (samples[id] || []).forEach(function (row) {
        if (row.t < minT) minT = row.t;
        if (row.t > maxT) maxT = row.t;
      });
    });
    if (!isFinite(minT) || maxT <= minT) return null;
    return minT + ((maxT - minT) * Number(scrub.value) / 100);
  }

  function deckLayers() {
    if (!window.deck) return [];
    var layers = [];
    var zoom = glMap ? glMap.getZoom() : 12;
    var mat = buildingMaterial();
    var parted = partitionBuildings(buildings);
    var obs = partitionObstacles(obstacles);
    var drawBuildings = parted.buildings;
    var allPoles = parted.poles.concat(obs.poles);
    var allRibbons = parted.ribbons.concat(obs.ribbons);

    if (buildingsOn && drawBuildings.length) {
      /* Ombre de contact douce — ancre le volume au sol. */
      layers.push(new window.deck.PolygonLayer({
        id: 'ow-gl-shadows',
        data: drawBuildings,
        extruded: true,
        filled: true,
        stroked: false,
        getPolygon: function (d) { return footprint(d, 1.08); },
        getElevation: 0.35,
        getFillColor: [18, 22, 20, 55],
        material: false,
        pickable: false
      }));
      layers.push(new window.deck.PolygonLayer({
        id: 'ow-gl-buildings',
        data: drawBuildings,
        extruded: true,
        filled: true,
        stroked: true,
        wireframe: false,
        getPolygon: function (d) { return footprint(d); },
        getElevation: function (d) { return buildingElevation(d); },
        getFillColor: function (d) { return buildingFill(d); },
        getLineColor: [32, 38, 44, zoom >= 14.8 ? 110 : 160],
        lineWidthMinPixels: 1,
        material: mat,
        pickable: true,
        autoHighlight: true,
        highlightColor: [255, 210, 90, 90]
      }));
      /* Toit avec léger débord + bordure plus claire. */
      if (zoom >= 13.0) {
        layers.push(new window.deck.PolygonLayer({
          id: 'ow-gl-roofs',
          data: drawBuildings,
          extruded: true,
          filled: true,
          stroked: true,
          getPolygon: function (d) {
            var poly = footprint(d, 1.06);
            var h = buildingElevation(d);
            return poly.map(function (p) { return [p[0], p[1], p[2] + h]; });
          },
          getElevation: 0.5,
          getFillColor: function (d) { return roofFill(d); },
          getLineColor: [210, 214, 218, 140],
          lineWidthMinPixels: 1,
          material: { ambient: 0.48, diffuse: 0.52, shininess: 10, specularColor: [50, 52, 56] },
          pickable: false
        }));
      }
    }

    if (buildingsOn && allRibbons.length && lastLod !== '0' && lastLod !== '1') {
      layers.push(new window.deck.PolygonLayer({
        id: 'ow-gl-obstacles',
        data: allRibbons.filter(function (d) { return visibleOnScreen(d, 2.5); }),
        extruded: true,
        filled: true,
        stroked: true,
        getPolygon: function (d) { return footprint(d); },
        getElevation: function (d) { return Math.min(2.4, Math.max(0.7, buildingDims(d).h)); },
        getFillColor: function (d) {
          var fill = obstacleFill(d);
          return [fill[0], fill[1], fill[2], Math.min(150, fill[3] || 140)];
        },
        getLineColor: [40, 36, 32, 110],
        lineWidthMinPixels: 1,
        material: mat,
        pickable: true,
        autoHighlight: true
      }));
    }

    if (buildingsOn && obs.longPaths.length && window.deck.PathLayer) {
      /* Longs obstacles (glissières / murs) : trait au sol, pas une dalle qui traverse le cadre. */
      layers.push(new window.deck.PathLayer({
        id: 'ow-gl-long-paths',
        data: obs.longPaths,
        getPath: function (d) { return obstaclePath(d); },
        getColor: [48, 52, 56, 120],
        getWidth: 1.4,
        widthUnits: 'meters',
        widthMinPixels: 1,
        widthMaxPixels: 4,
        pickable: false
      }));
    }

    if (buildingsOn && allPoles.length && zoom >= 13.6) {
      if (window.deck.ColumnLayer) {
        layers.push(new window.deck.ColumnLayer({
          id: 'ow-gl-poles',
          data: allPoles.filter(function (d) { return visibleOnScreen(d, 1.2); }).slice(0, 2500),
          diskResolution: 6,
          radius: 0.45,
          extruded: true,
          getPosition: function (d) { return polePosition(d); },
          getElevation: function (d) { return Math.min(14, Math.max(2.2, buildingDims(d).h)); },
          getFillColor: [70, 74, 78, 170],
          material: mat,
          pickable: false
        }));
      } else {
        layers.push(new window.deck.PolygonLayer({
          id: 'ow-gl-poles',
          data: allPoles.slice(0, 1500),
          extruded: true,
          filled: true,
          getPolygon: function (d) {
            var item = Object.assign({}, d, { width: 0.7, depth: 0.7, shape: 'pole' });
            return footprint(item);
          },
          getElevation: function (d) { return Math.min(12, Math.max(2, Number(d.height) || 5)); },
          getFillColor: [70, 74, 78, 160],
          material: mat,
          pickable: false
        }));
      }
    }
    if (buildingsOn && forests.length && zoom >= 13.4) {
      if (zoom >= 15.4) {
        layers.push(new window.deck.IconLayer({
          id: 'ow-gl-forest-icons',
          data: forests.slice(0, 400),
          getPosition: function (d) {
            var ll = proj.worldToLngLat(d.x, d.y);
            return [ll[0], ll[1], heightAt(d.x, d.y) + 2];
          },
          getIcon: function () {
            return {
              url: 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32"><polygon points="16,2 28,28 4,28" fill="#2f7a45"/></svg>'),
              width: 32,
              height: 32,
              anchorY: 32
            };
          },
          getSize: 18,
          sizeUnits: 'pixels',
          pickable: false
        }));
      } else {
        layers.push(new window.deck.PolygonLayer({
          id: 'ow-gl-forests',
          data: clusterForests(forests, zoom >= 14 ? 70 : 110),
          extruded: true,
          filled: true,
          getPolygon: function (d) { return d.polygon; },
          getElevation: function (d) { return Math.max(2.2, Math.min(7, Number(d.height) || 5)); },
          getFillColor: function (d) {
            var a = Math.max(55, Math.min(120, (Number(d.density) || 1) * 80));
            return [24, 78, 40, a];
          },
          material: false,
          pickable: false
        }));
      }
    }
    var packed = clusteredUnits(unitPoints());
    var units = packed.singles;
    var stacks = packed.clusters;
    var depth = unitDepthTest();
    var labeled = declutterUnitLabels(units, zoom);
    var velocities = units.filter(function (d) { return d.velocity && d.velocity.length >= 2; });
    var medical = units.filter(function (d) { return d.medical; });
    var selectedUnits = units.filter(function (d) { return d.selected; });
    var hoveredUnits = units.filter(function (d) { return d.hovered && !d.selected; });
    var airUnits = units.filter(function (d) { return d.air && d.altLabel; });
    var sectors = units.filter(function (d) { return d.sector && d.sector.length >= 3; });
    var pulse = 0.55 + 0.45 * Math.sin(Date.now() / 420);
    if (units.length) {
      /* Pied au sol : anneau / point — position réelle. */
      layers.push(new window.deck.ScatterplotLayer({
        id: 'ow-gl-units-foot',
        data: units,
        getPosition: function (d) { return d.foot; },
        getFillColor: function (d) {
          return [12, 16, 14, Math.round(170 * (d.freshness || 1))];
        },
        getLineColor: function (d) {
          var c = d.color || [200, 210, 200, 200];
          return [c[0], c[1], c[2], Math.round(200 * (d.freshness || 1))];
        },
        lineWidthMinPixels: 1.5,
        stroked: true,
        filled: true,
        getRadius: 4.5,
        radiusUnits: 'pixels',
        pickable: false,
        parameters: { depthTest: true }
      }));
      /* Tige verticale sol → symbole (convention C2 3D). */
      if (window.deck.PathLayer) {
        layers.push(new window.deck.PathLayer({
          id: 'ow-gl-unit-leaders',
          data: units,
          getPath: function (d) { return d.leader; },
          getColor: function (d) {
            return [18, 24, 20, Math.round(190 * (d.alpha || 1))];
          },
          getWidth: 1.4,
          widthUnits: 'pixels',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      /* Zone de sélection invisible plus large que le sprite. */
      layers.push(new window.deck.ScatterplotLayer({
        id: 'ow-gl-units-pick',
        data: units,
        getPosition: function (d) { return d.position; },
        getFillColor: [0, 0, 0, 1],
        getRadius: 32,
        radiusUnits: 'pixels',
        pickable: true,
        autoHighlight: false,
        parameters: { depthTest: false }
      }));
      /* Halo sombre pour lisibilité sur photo claire. */
      layers.push(new window.deck.ScatterplotLayer({
        id: 'ow-gl-units-halo',
        data: units,
        getPosition: function (d) { return d.position; },
        getFillColor: function (d) {
          return [8, 12, 10, Math.round(95 * (d.alpha || 1))];
        },
        getRadius: function (d) { return (d.sizePx || 34) * 0.72; },
        radiusUnits: 'pixels',
        pickable: false,
        parameters: { depthTest: false }
      }));
      if (window.deck.IconLayer) {
        layers.push(new window.deck.IconLayer({
          id: 'ow-gl-units',
          data: units.filter(function (d) { return d.icon && d.icon.url; }),
          getPosition: function (d) { return d.position; },
          getIcon: function (d) {
            return {
              url: d.icon.url,
              width: d.icon.width || 34,
              height: d.icon.height || 34,
              anchorX: d.icon.anchorX != null ? d.icon.anchorX : (d.icon.width || 34) / 2,
              anchorY: d.icon.anchorY != null ? d.icon.anchorY : (d.icon.height || 34) / 2
            };
          },
          getSize: function (d) { return d.sizePx || 34; },
          sizeUnits: 'pixels',
          sizeMinPixels: 20,
          sizeMaxPixels: 44,
          getColor: function (d) {
            var a = Math.round(255 * (d.alpha || 1));
            return [255, 255, 255, a];
          },
          billboard: true,
          alphaCutoff: 0.05,
          pickable: false,
          parameters: { depthTest: false, depthMask: false }
        }));
      } else {
        layers.push(new window.deck.ScatterplotLayer({
          id: 'ow-gl-units',
          data: units,
          getPosition: function (d) { return d.position; },
          getFillColor: function (d) {
            var c = d.color || [80, 200, 140, 230];
            return [c[0], c[1], c[2], Math.round((c[3] || 230) * (d.alpha || 1))];
          },
          getLineColor: [12, 16, 14, 230],
          lineWidthMinPixels: 1.5,
          stroked: true,
          getRadius: 11,
          radiusUnits: 'pixels',
          pickable: true,
          parameters: { depthTest: depth }
        }));
      }
      if (sectors.length && window.deck.SolidPolygonLayer) {
        layers.push(new window.deck.SolidPolygonLayer({
          id: 'ow-gl-unit-sectors',
          data: sectors,
          getPolygon: function (d) { return d.sector; },
          getFillColor: function (d) {
            var c = d.color || [120, 180, 140, 80];
            return [c[0], c[1], c[2], 55];
          },
          extruded: false,
          pickable: false,
          parameters: { depthTest: true }
        }));
      } else if (sectors.length && window.deck.PolygonLayer) {
        layers.push(new window.deck.PolygonLayer({
          id: 'ow-gl-unit-sectors',
          data: sectors,
          getPolygon: function (d) { return d.sector; },
          getFillColor: function (d) {
            var c = d.color || [120, 180, 140, 80];
            return [c[0], c[1], c[2], 55];
          },
          stroked: false,
          filled: true,
          extruded: false,
          pickable: false,
          parameters: { depthTest: true }
        }));
      }
      if (velocities.length && window.deck.PathLayer) {
        layers.push(new window.deck.PathLayer({
          id: 'ow-gl-unit-velocity',
          data: velocities,
          getPath: function (d) { return d.velocity; },
          getColor: function (d) {
            var c = d.color || [180, 210, 190, 200];
            return [c[0], c[1], c[2], Math.round(180 * (d.alpha || 1))];
          },
          getWidth: 2,
          widthUnits: 'pixels',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      /* Colonne d’alerte médicale pulsante, visible de loin. */
      if (medical.length && window.deck.ColumnLayer) {
        layers.push(new window.deck.ColumnLayer({
          id: 'ow-gl-units-medical-col',
          data: medical,
          diskResolution: 10,
          radius: 1.8,
          extruded: true,
          getPosition: function (d) { return d.foot; },
          getElevation: function (d) {
            return 28 + pulse * (d.critical ? 18 : 12);
          },
          getFillColor: function (d) {
            var a = Math.round((d.critical ? 140 : 100) * pulse);
            return d.critical ? [240, 60, 60, a] : [240, 170, 50, a];
          },
          material: false,
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      if (medical.length) {
        layers.push(new window.deck.ScatterplotLayer({
          id: 'ow-gl-units-medical',
          data: medical,
          getPosition: function (d) { return d.position; },
          getFillColor: [0, 0, 0, 0],
          getLineColor: function (d) {
            var a = Math.round((d.critical ? 230 : 200) * (0.65 + 0.35 * pulse));
            return d.critical ? [240, 70, 70, a] : [240, 180, 60, a];
          },
          lineWidthMinPixels: 2.5,
          stroked: true,
          filled: false,
          getRadius: function (d) { return (d.sizePx || 34) * 0.85; },
          radiusUnits: 'pixels',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      if (selectedUnits.length) {
        layers.push(new window.deck.ScatterplotLayer({
          id: 'ow-gl-units-selected',
          data: selectedUnits,
          getPosition: function (d) { return d.position; },
          getFillColor: [0, 0, 0, 0],
          getLineColor: [212, 175, 55, 240],
          lineWidthMinPixels: 2.2,
          stroked: true,
          filled: false,
          getRadius: function (d) { return (d.sizePx || 34) * 0.95; },
          radiusUnits: 'pixels',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      if (hoveredUnits.length) {
        layers.push(new window.deck.ScatterplotLayer({
          id: 'ow-gl-units-hover',
          data: hoveredUnits,
          getPosition: function (d) { return d.position; },
          getFillColor: [0, 0, 0, 0],
          getLineColor: [230, 236, 230, 210],
          lineWidthMinPixels: 1.8,
          stroked: true,
          filled: false,
          getRadius: function (d) { return (d.sizePx || 34) * 0.9; },
          radiusUnits: 'pixels',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      if (airUnits.length && window.deck.TextLayer) {
        layers.push(new window.deck.TextLayer({
          id: 'ow-gl-unit-alt',
          data: airUnits,
          getPosition: function (d) { return d.position; },
          getText: function (d) { return d.altLabel; },
          getSize: 10,
          getColor: [220, 230, 220, 230],
          getPixelOffset: [0, 18],
          fontFamily: 'IBM Plex Mono, ui-monospace, monospace',
          background: true,
          getBackgroundColor: [10, 14, 12, 200],
          backgroundPadding: [3, 1],
          billboard: true,
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      if (labeled.length) {
        layers.push(new window.deck.TextLayer({
          id: 'ow-gl-unit-labels',
          data: labeled,
          getPosition: function (d) { return d.position; },
          getText: function (d) { return d.shortLabel || d.label; },
          getSize: function (d) { return d.fullLabel ? 12 : 11; },
          getColor: [236, 242, 236, 255],
          getPixelOffset: [0, -22],
          fontFamily: 'IBM Plex Mono, ui-monospace, monospace',
          background: true,
          getBackgroundColor: [14, 18, 16, 220],
          backgroundPadding: [4, 2],
          billboard: true,
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
    }
    var moveTrails = unitMoveTrails();
    if (moveTrails.length && window.deck.PathLayer && (opts.ghost || zoom >= 14.5)) {
      layers.push(new window.deck.PathLayer({
        id: 'ow-gl-unit-trails',
        data: moveTrails,
        getPath: function (d) { return d.path; },
        getColor: function () {
          return [150, 190, 170, 90];
        },
        getWidth: 1.6,
        widthUnits: 'pixels',
        rounded: true,
        pickable: false,
        parameters: { depthTest: false }
      }));
    }
    if (stacks.length) {
      if (window.deck.IconLayer && window.OverwatchGlSymbols && window.OverwatchGlSymbols.clusterIcon) {
        layers.push(new window.deck.IconLayer({
          id: 'ow-gl-stacks',
          data: stacks.map(function (s) {
            return Object.assign({}, s, { icon: window.OverwatchGlSymbols.clusterIcon(s.count) });
          }),
          getPosition: function (d) { return d.position; },
          getIcon: function (d) {
            return {
              url: d.icon.url,
              width: d.icon.width,
              height: d.icon.height,
              anchorX: d.icon.anchorX,
              anchorY: d.icon.anchorY
            };
          },
          getSize: 36,
          sizeUnits: 'pixels',
          billboard: true,
          pickable: true,
          parameters: { depthTest: false }
        }));
      } else {
        layers.push(new window.deck.ScatterplotLayer({
          id: 'ow-gl-stacks',
          data: stacks,
          getPosition: function (d) { return d.position; },
          getFillColor: [20, 28, 24, 230],
          getLineColor: [230, 210, 90, 240],
          lineWidthMinPixels: 2,
          stroked: true,
          getRadius: 18,
          radiusUnits: 'pixels',
          pickable: true,
          parameters: { depthTest: false }
        }));
        layers.push(new window.deck.TextLayer({
          id: 'ow-gl-stack-labels',
          data: stacks,
          getPosition: function (d) { return d.position; },
          getText: function (d) { return d.label; },
          getSize: 13,
          getColor: [250, 248, 230, 255],
          fontFamily: 'IBM Plex Mono, ui-monospace, monospace',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
    }
    var ghosts = ghostTrails();
    if (ghosts.length) {
      layers.push(new window.deck.ScatterplotLayer({
        id: 'ow-gl-ghosts',
        data: ghosts,
        getPosition: function (d) { return d.position; },
        getFillColor: function (d) { return d.color; },
        getRadius: 7,
        radiusUnits: 'pixels',
        pickable: false,
        parameters: { depthTest: false }
      }));
    }
    var heat = heatPoints();
    if (heat.length && window.deck.HeatmapLayer) {
      layers.push(new window.deck.HeatmapLayer({
        id: 'ow-gl-heat',
        data: heat,
        getPosition: function (d) { return d.position; },
        radiusPixels: 36,
        intensity: 1,
        threshold: 0.05
      }));
    }
    var vols = volumeShapes();
    if (vols.length) {
      layers.push(new window.deck.PolygonLayer({
        id: 'ow-gl-volumes',
        data: vols,
        extruded: true,
        filled: true,
        getPolygon: function (d) { return d.polygon; },
        getElevation: function (d) { return d.height; },
        getFillColor: [90, 160, 210, 90],
        getLineColor: [140, 200, 230, 180],
        material: false,
        pickable: false
      }));
    }
    var trails = replayPaths();
    if (trails.length && window.deck.PathLayer) {
      layers.push(new window.deck.PathLayer({
        id: 'ow-gl-replay-paths',
        data: trails,
        getPath: function (d) { return d.path; },
        getColor: [180, 210, 190, 160],
        getWidth: 2,
        widthUnits: 'pixels',
        pickable: false
      }));
    }
    return layers;
  }

  function buildingFeature(item) {
    var ring = footprint(item).map(function (p) { return [p[0], p[1]]; });
    if (ring.length) ring.push(ring[0]);
    var fill = buildingFill(item);
    var hex = '#' + [fill[0], fill[1], fill[2]].map(function (n) {
      var h = Math.max(0, Math.min(255, n | 0)).toString(16);
      return h.length === 1 ? '0' + h : h;
    }).join('');
    return {
      type: 'Feature',
      properties: {
        height: buildingElevation(item),
        id: String(item.id || ''),
        x: Number(item.x) || 0,
        y: Number(item.y) || 0,
        cluster: !!item.cluster,
        color: hex,
        opacity: Math.max(0.25, Math.min(0.92, (fill[3] || 180) / 255))
      },
      geometry: { type: 'Polygon', coordinates: [ring] }
    };
  }

  function forestCollection() {
    var zoom = glMap ? glMap.getZoom() : 12;
    if (zoom < 13.4) return { type: 'FeatureCollection', features: [] };
    var clustered = clusterForests(forests, zoom >= 14 ? 70 : 110);
    return {
      type: 'FeatureCollection',
      features: clustered.map(function (d) {
        var ring = (d.polygon || []).map(function (p) { return [p[0], p[1]]; });
        if (ring.length) ring.push(ring[0]);
        return {
          type: 'Feature',
          properties: { height: Math.max(3, Math.min(10, Number(d.height) || 6)) },
          geometry: { type: 'Polygon', coordinates: [ring] }
        };
      })
    };
  }

  function ensureMapLibreVolumes() {
    if (!glMap || typeof glMap.getSource !== 'function') return;
    if (!glMap.getSource('ow-buildings')) {
      glMap.addSource('ow-buildings', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    }
    if (!glMap.getLayer || !glMap.getLayer('ow-buildings-fill')) {
      /* fill-extrusion-opacity : constante uniquement (pas d’expression data). */
      glMap.addLayer({
        id: 'ow-buildings-fill',
        type: 'fill-extrusion',
        source: 'ow-buildings',
        paint: {
          'fill-extrusion-color': ['coalesce', ['get', 'color'], '#bac4ce'],
          'fill-extrusion-height': ['get', 'height'],
          'fill-extrusion-base': 0,
          'fill-extrusion-opacity': 0.72
        }
      });
    } else {
      try { glMap.setPaintProperty('ow-buildings-fill', 'fill-extrusion-opacity', 0.72); } catch (e0) {}
    }
    if (!glMap.getSource('ow-forests')) {
      glMap.addSource('ow-forests', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    }
    if (!glMap.getLayer || !glMap.getLayer('ow-forests-fill')) {
      glMap.addLayer({
        id: 'ow-forests-fill',
        type: 'fill-extrusion',
        source: 'ow-forests',
        paint: {
          'fill-extrusion-color': '#1c5c30',
          'fill-extrusion-height': ['get', 'height'],
          'fill-extrusion-base': 0,
          'fill-extrusion-opacity': 0.55
        }
      });
    }
  }

  function paintMapLibreVolumes() {
    if (!glMap || typeof glMap.getSource !== 'function') return;
    try { ensureMapLibreVolumes(); } catch (e) { return; }
    var bSrc = glMap.getSource('ow-buildings');
    var fSrc = glMap.getSource('ow-forests');
    if (bSrc && typeof bSrc.setData === 'function') {
      bSrc.setData({
        type: 'FeatureCollection',
        features: buildingsOn ? buildings.map(buildingFeature) : []
      });
    }
    if (fSrc && typeof fSrc.setData === 'function') {
      fSrc.setData(buildingsOn ? forestCollection() : { type: 'FeatureCollection', features: [] });
    }
  }

  function redraw() {
    if (overlay) {
      overlay.setProps({ layers: deckLayers(), effects: lightingEffects() });
      if (glMap && glMap.getSource && glMap.getSource('ow-buildings') && glMap.getSource('ow-forests')) {
        glMap.getSource('ow-buildings').setData({ type: 'FeatureCollection', features: [] });
        glMap.getSource('ow-forests').setData({ type: 'FeatureCollection', features: [] });
      }
      return;
    }
    paintMapLibreVolumes();
  }

  function onMove() {
    queueLoad();
    redraw();
  }

  function onGlClick(event) {
    if (!event || !event.lngLat) return;
    if (Date.now() - lastPickAt < 80) return;
    pickWorld(leafletFromLngLat(event.lngLat.lng, event.lngLat.lat), event.originalEvent, null);
  }

  function onGlContext(event) {
    if (event && typeof event.preventDefault === 'function') event.preventDefault();
    if (!event || !event.lngLat) return;
    pickContext(leafletFromLngLat(event.lngLat.lng, event.lngLat.lat), event.originalEvent, null);
  }

  function setSun(next) {
    sun = next && typeof next === 'object' ? next : { night: false, intensity: 0.7 };
    redraw();
  }

  function attach(map, projection) {
    detach();
    glMap = map;
    proj = projection;
    buildingsOn = sceneEnabled();
    attached = true;
    var toggle = document.getElementById('atak-scene-buildings');
    if (toggle && !toggle._owGlBound) {
      toggle._owGlBound = true;
      toggle.addEventListener('change', function () {
        buildingsOn = sceneEnabled();
        if (buildingsOn) loadVisible();
        else {
          buildings = [];
          forests = [];
          obstacles = [];
          redraw();
        }
      });
    }
    var qualityToggle = document.getElementById('atak-scene-quality');
    if (qualityToggle && !qualityToggle._owGlBound) {
      qualityToggle._owGlBound = true;
      qualityOn = !!qualityToggle.checked;
      qualityToggle.addEventListener('change', function () {
        qualityOn = !!qualityToggle.checked;
        redraw();
      });
    } else if (qualityToggle) {
      qualityOn = !!qualityToggle.checked;
    }
    window.addEventListener('overwatch:replay', redraw);
    loadDem().then(function () {
      if (!attached) return;
      if (window.deck && window.deck.MapboxOverlay) {
        try {
          overlay = new window.deck.MapboxOverlay({
            interleaved: true,
            layers: [],
            effects: lightingEffects(),
            getTooltip: function (info) {
              if (!info || !info.object) return null;
              if (info.object.count) return { text: info.object.count + ' éléments' };
              if (info.object.label) return { text: info.object.label };
              if (info.object.cluster) return { text: 'Groupe de constructions' };
              if (info.layer && info.layer.id === 'ow-gl-obstacles') return { text: obstacleLabel(info.object) };
              if (info.layer && info.layer.id === 'ow-gl-buildings') return { text: 'Bâtiment' };
              return null;
            },
            onHover: function (info) {
              var next = '';
              var lid = info && info.layer ? String(info.layer.id) : '';
              if (info && info.object && info.object.id && (lid === 'ow-gl-units' || lid === 'ow-gl-units-pick')) {
                next = String(info.object.id);
              }
              if (next !== hoverId) {
                hoverId = next;
                redraw();
              }
            },
            onClick: function (info) {
              if (!info) return;
              var ow = window.OverwatchBeta;
              var src = info.srcEvent || info.originalEvent;
              if (info.object && info.object.items && info.layer && String(info.layer.id) === 'ow-gl-stacks') {
                try {
                  window.dispatchEvent(new CustomEvent('overwatch:cluster-stack', { detail: { items: info.object.items } }));
                } catch (e0) {}
                lastPickAt = Date.now();
                return;
              }
              var layerIdHit = info.layer ? String(info.layer.id) : '';
              if (info.object && info.object.id && (layerIdHit === 'ow-gl-units' || layerIdHit === 'ow-gl-units-pick')) {
                if (ow && typeof ow.selectUnit === 'function' && typeof ow.getUnits === 'function') {
                  var hit = (ow.getUnits() || []).filter(function (u) {
                    return ow.unitId && ow.unitId(u) === info.object.id;
                  })[0];
                  if (hit) ow.selectUnit(hit);
                }
                lastPickAt = Date.now();
                return;
              }
              var lng = null;
              var lat = null;
              if (info.coordinate && info.coordinate.length >= 2) {
                lng = info.coordinate[0];
                lat = info.coordinate[1];
              } else if (info.lngLat) {
                lng = info.lngLat[0] != null ? info.lngLat[0] : info.lngLat.lng;
                lat = info.lngLat[1] != null ? info.lngLat[1] : info.lngLat.lat;
              }
              var ll = lng != null && lat != null ? leafletFromLngLat(lng, lat) : null;
              var layerId = info.layer ? String(info.layer.id) : '';
              if (info.object && info.object.id && !info.object.cluster && (layerId === 'ow-gl-buildings' || layerId === 'ow-gl-obstacles')) {
                focusId = String(info.object.id);
                if (ow && typeof ow.inspectSceneObject === 'function') {
                  ow.inspectSceneObject(info.object.id, ll, info.object);
                  lastPickAt = Date.now();
                  redraw();
                  return;
                }
              }
              if (!ll) return;
              pickWorld(ll, src, null);
            }
          });
          glMap.addControl(overlay);
        } catch (err) {
          overlay = null;
        }
      }
      try { ensureMapLibreVolumes(); } catch (e2) {}
      glMap.on('moveend', onMove);
      glMap.on('zoomend', onMove);
      glMap.on('click', onGlClick);
      glMap.on('contextmenu', onGlContext);
      loadVisible();
      unitTimer = window.setInterval(function () {
        if (!attached) return;
        redraw();
        followSelected();
      }, 480);
    });
  }

  function detach() {
    attached = false;
    window.clearTimeout(fetchTimer);
    window.clearInterval(unitTimer);
    window.removeEventListener('overwatch:replay', redraw);
    if (glMap) {
      try { glMap.off('moveend', onMove); } catch (e0) {}
      try { glMap.off('zoomend', onMove); } catch (e1) {}
      try { glMap.off('click', onGlClick); } catch (e2) {}
      try { glMap.off('contextmenu', onGlContext); } catch (e3) {}
    }
    if (glMap && overlay) {
      try { glMap.removeControl(overlay); } catch (e) {}
    }
    overlay = null;
    glMap = null;
    buildings = [];
    forests = [];
    obstacles = [];
    focusId = '';
    hoverId = '';
  }

  function setFocus(id) {
    focusId = id ? String(id) : '';
    redraw();
  }

  function setQuality(on) {
    qualityOn = !!on;
    redraw();
  }

  function setOpts(next) {
    opts = Object.assign(opts, next || {});
    redraw();
  }

  return {
    attach: attach,
    detach: detach,
    reload: loadVisible,
    setSun: setSun,
    setFocus: setFocus,
    setQuality: setQuality,
    setOpts: setOpts,
    redraw: redraw
  };
})();
