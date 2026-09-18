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
  var qualityOn = false;
  var sun = { night: false, intensity: 0.7 };
  var opts = { occlusion: 'always', ghost: false, heat: false, focus: false, cinematic: false };
  var lastCam = { x: 0, y: 0 };
  var meshMem = {};

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

  function footprint(item) {
    var angle = Number(item.bearing || 0) * Math.PI / 180;
    var c = Math.cos(angle);
    var s = Math.sin(angle);
    var hw = Math.max(2, Number(item.width) || 4) / 2;
    var hd = Math.max(2, Number(item.depth) || 4) / 2;
    /* AGL : le sol = DEM (ou base_z du précalcul), height du relevé = extrusion au-dessus du sol. */
    var baseZ = Number(item.base_z);
    if (!isFinite(baseZ)) baseZ = heightAt(item.x, item.y);
    return [[-hw, -hd], [hw, -hd], [hw, hd], [-hw, hd]].map(function (v) {
      var x = Number(item.x) + v[0] * c - v[1] * s;
      var y = Number(item.y) + v[0] * s + v[1] * c;
      var ll = proj.worldToLngLat(x, y);
      return [ll[0], ll[1], baseZ];
    });
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
      var x0 = b.gx * cell;
      var y0 = b.gy * cell;
      var z = heightAt(x0 + cell / 2, y0 + cell / 2);
      var poly = [
        [x0, y0], [x0 + cell, y0], [x0 + cell, y0 + cell], [x0, y0 + cell]
      ].map(function (p) {
        var ll = proj.worldToLngLat(p[0], p[1]);
        return [ll[0], ll[1], z];
      });
      return {
        polygon: poly,
        height: Math.max(4, Math.min(14, b.h / b.n)),
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
      redraw();
      return;
    }
    var bbox = worldBbox();
    if (!bbox) return;
    Promise.all([fetchBuildings(bbox), fetchKind('forest', bbox)]).then(function (rows) {
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
    });
  }

  function queueLoad() {
    window.clearTimeout(fetchTimer);
    fetchTimer = window.setTimeout(loadVisible, 200);
  }

  function unitSide(unit) {
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

  function unitPoints() {
    var ow = window.OverwatchBeta;
    if (!ow || typeof ow.getUnits !== 'function' || !proj) return [];
    var clock = replayClock();
    var samples = clock != null && ow.getTrackSamples ? ow.getTrackSamples() : null;
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
      var z = heightAt(x, y) + 4;
      var label = ow.callsign ? ow.callsign(unit) : (unit.call_sign || unit.callsign || 'Contact');
      return {
        position: [ll[0], ll[1], z],
        color: unitColor(unit),
        label: label,
        id: ow.unitId ? ow.unitId(unit) : label,
        x: x,
        y: y
      };
    }).filter(Boolean);
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

  function buildingFill(d) {
    var n = sun && sun.night ? 0.72 : Math.max(0.78, Math.min(1, Number(sun && sun.intensity) || 0.85) + 0.2);
    var alpha = 220;
    if (focusId && d && String(d.id || '') !== focusId) alpha = 70;
    if (focusId && d && String(d.id || '') === focusId) alpha = 245;
    if (opts.focus && d && !missionNear(Number(d.x), Number(d.y))) alpha = Math.min(alpha, 55);
    var base;
    if (d && d.cluster) base = [Math.round(148 * n), Math.round(158 * n), Math.round(168 * n), alpha];
    else base = [Math.round(186 * n), Math.round(196 * n), Math.round(206 * n), alpha];
    if (!qualityOn) return base;
    var q = String((d && d.quality) || '');
    if (q === 'complete') return [70, 170, 90, alpha];
    if (q === 'approx') return [220, 150, 50, alpha];
    if (q === 'position') return [140, 145, 150, alpha];
    if (q === 'suspect') return [200, 70, 60, alpha];
    return base;
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
    if (buildingsOn && buildings.length) {
      layers.push(new window.deck.PolygonLayer({
        id: 'ow-gl-buildings',
        data: buildings,
        extruded: true,
        filled: true,
        wireframe: false,
        getPolygon: function (d) { return footprint(d); },
        getElevation: function (d) { return Math.max(3, Number(d.height) || 6); },
        getFillColor: function (d) { return buildingFill(d); },
        getLineColor: [40, 48, 56, 180],
        lineWidthMinPixels: 1,
        material: true,
        pickable: true,
        autoHighlight: true,
        highlightColor: [255, 210, 90, 90]
      }));
    }
    if (buildingsOn && obstacles.length && lastLod !== '0' && lastLod !== '1') {
      layers.push(new window.deck.PolygonLayer({
        id: 'ow-gl-obstacles',
        data: obstacles,
        extruded: true,
        filled: true,
        getPolygon: function (d) { return footprint(d); },
        getElevation: function (d) { return Math.max(0.8, Number(d.height) || 1.4); },
        getFillColor: function (d) { return obstacleFill(d); },
        getLineColor: [40, 36, 32, 160],
        lineWidthMinPixels: 1,
        material: true,
        pickable: true,
        autoHighlight: true
      }));
    }
    if (buildingsOn && forests.length) {
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
          data: clusterForests(forests, zoom >= 14 ? 50 : 90),
          extruded: true,
          filled: true,
          getPolygon: function (d) { return d.polygon; },
          getElevation: function (d) { return Math.max(3, Math.min(10, Number(d.height) || 6)); },
          getFillColor: function (d) {
            var a = Math.max(70, Math.min(160, (Number(d.density) || 1) * 110));
            return [28, 92, 48, a];
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
    if (units.length) {
      layers.push(new window.deck.ScatterplotLayer({
        id: 'ow-gl-units',
        data: units,
        getPosition: function (d) { return d.position; },
        getFillColor: function (d) { return d.color; },
        getLineColor: [8, 12, 10, 220],
        lineWidthMinPixels: 1,
        stroked: true,
        getRadius: 14,
        radiusUnits: 'pixels',
        pickable: true,
        parameters: { depthTest: depth }
      }));
      if (opts.occlusion === 'silhouette') {
        layers.push(new window.deck.ScatterplotLayer({
          id: 'ow-gl-units-sil',
          data: units,
          getPosition: function (d) { return d.position; },
          getFillColor: [0, 0, 0, 0],
          getLineColor: [240, 244, 238, 220],
          lineWidthMinPixels: 2,
          stroked: true,
          filled: false,
          getRadius: 16,
          radiusUnits: 'pixels',
          pickable: false,
          parameters: { depthTest: false }
        }));
      }
      layers.push(new window.deck.TextLayer({
        id: 'ow-gl-unit-labels',
        data: units,
        getPosition: function (d) { return d.position; },
        getText: function (d) { return d.label; },
        getSize: 12,
        getColor: [230, 236, 232, 240],
        getPixelOffset: [0, -16],
        fontFamily: 'IBM Plex Sans, sans-serif',
        pickable: false,
        parameters: { depthTest: false }
      }));
    }
    if (stacks.length) {
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
        fontFamily: 'IBM Plex Sans, sans-serif',
        pickable: false,
        parameters: { depthTest: false }
      }));
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
    return {
      type: 'Feature',
      properties: {
        height: Math.max(3, Number(item.height) || 6),
        id: String(item.id || ''),
        x: Number(item.x) || 0,
        y: Number(item.y) || 0,
        cluster: !!item.cluster
      },
      geometry: { type: 'Polygon', coordinates: [ring] }
    };
  }

  function forestCollection() {
    var zoom = glMap ? glMap.getZoom() : 12;
    var clustered = clusterForests(forests, zoom >= 14 ? 50 : 90);
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
      glMap.addLayer({
        id: 'ow-buildings-fill',
        type: 'fill-extrusion',
        source: 'ow-buildings',
        paint: {
          'fill-extrusion-color': '#bac4ce',
          'fill-extrusion-height': ['get', 'height'],
          'fill-extrusion-base': 0,
          'fill-extrusion-opacity': 0.88
        }
      });
    }
    if (!glMap.getSource('ow-forests')) {
      glMap.addSource('ow-forests', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
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
      overlay.setProps({ layers: deckLayers() });
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
            getTooltip: function (info) {
              if (!info || !info.object) return null;
              if (info.object.count) return { text: info.object.count + ' éléments' };
              if (info.object.label) return { text: info.object.label };
              if (info.object.cluster) return { text: 'Groupe de constructions' };
              if (info.layer && info.layer.id === 'ow-gl-obstacles') return { text: obstacleLabel(info.object) };
              if (info.layer && info.layer.id === 'ow-gl-buildings') return { text: 'Bâtiment' };
              return null;
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
              if (info.object && info.object.id && info.layer && String(info.layer.id) === 'ow-gl-units') {
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
      }, 900);
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
