/* Overwatch Beta — masque, horizon, coupe, split, occlusion, AAR, cache. */
window.OverwatchGlTactics = (function () {
  'use strict';

  var KEY_OCC = 'athena:ow-occ';
  var KEY_GHOST = 'athena:ow-ghost';
  var KEY_HEAT = 'athena:ow-heat';
  var KEY_FOCUS = 'athena:ow-focus-mission';
  var KEY_CINE = 'athena:ow-cine';
  var KEY_BOOK = 'athena:ow-cam-bookmarks';
  var viewshedLayer = null;
  var coverageLayer = null;
  var splitOn = false;
  var cinematicOn = false;
  var lastReplayClock = null;

  function ow() { return window.OverwatchBeta || null; }
  function api() { return ow() && ow().api ? ow().api.bind(ow()) : null; }
  function toast(msg) { if (ow() && ow().toast) ow().toast(msg); }
  function layers() { return window.OverwatchGlLayers || null; }
  function glHost() { return window.OverwatchGlMap || null; }

  function pref(key, fallback) {
    try {
      var v = localStorage.getItem(key);
      if (v === null || v === undefined) return fallback;
      if (v === '1' || v === 'true') return true;
      if (v === '0' || v === 'false') return false;
      return v;
    } catch (e) { return fallback; }
  }
  function setPref(key, value) {
    try { localStorage.setItem(key, value === true ? '1' : (value === false ? '0' : String(value))); } catch (e) {}
  }

  function worldOf(ll) {
    var apiOw = ow();
    if (!apiOw || !apiOw.latLngToWorld) return null;
    return apiOw.latLngToWorld(ll);
  }

  function drawViewshed(payload) {
    var apiOw = ow();
    if (!apiOw || !apiOw.map || !payload || !payload.ready) return;
    if (viewshedLayer) {
      try { apiOw.map.removeLayer(viewshedLayer); } catch (e) {}
      viewshedLayer = null;
    }
    var sectors = payload.sectors || [];
    if (!sectors.length) return;
    var ring = [];
    sectors.forEach(function (s) {
      var az = Number(s.az) * Math.PI / 180;
      var r = Number(s.range_m) || 0;
      var x = Number(payload.x) + Math.sin(az) * r;
      var y = Number(payload.y) + Math.cos(az) * r;
      ring.push(apiOw.worldToLatLng(x, y));
    });
    if (ring.length) ring.push(ring[0]);
    viewshedLayer = L.polygon(ring, {
      color: '#7ad0ff',
      weight: 1,
      fillColor: '#3aa0d8',
      fillOpacity: 0.22,
      className: 'ow-viewshed'
    }).addTo(apiOw.map);
    if (apiOw.registerScratch) apiOw.registerScratch(viewshedLayer, 'shape', 'viewshed-' + Date.now(), 'Masque de visibilité');
  }

  function requestViewshed(ll) {
    var apiOw = ow();
    var call = api();
    if (!apiOw || !call) return;
    var w = worldOf(ll);
    if (!w) return;
    var observer = { x: w.x, y: w.y };
    var sel = apiOw.getSelected && apiOw.getSelected();
    if (sel && apiOw.point) {
      var sl = apiOw.point(sel);
      if (sl && apiOw.map && apiOw.map.distance(sl, ll) < 25) {
        var alt = sel.pos_z != null ? Number(sel.pos_z) : (sel.alt != null ? Number(sel.alt) : null);
        if (alt != null && isFinite(alt)) observer.z = alt;
      }
    }
    apiOw.openDrawer('Visibilité', 'Masque', '<p class="ow-help">Calcul du masque de visibilité…</p>');
    call('/api/atak/terrain/viewshed', { method: 'POST', body: { mapId: apiOw.mapId, observer: observer, radius_m: 500 } }).then(function (payload) {
      if (!payload || payload.ready === false) {
        apiOw.openDrawer('Visibilité', 'Masque', '<p class="ow-help">' + (payload && (payload.gap_message || payload.message) || 'Relief non relevé.') + '</p>');
        return;
      }
      drawViewshed(payload);
      apiOw.openDrawer('Visibilité', 'Masque',
        '<p class="ow-help">Portions de terrain théoriquement visibles depuis ce point, d’après le relief et les constructions relevés.</p>' +
        '<div class="ow-event"><span>Visible</span><strong>' + (payload.visible_pct || 0) + ' %</strong></div>' +
        '<div class="ow-event"><span>Portée</span><strong>' + Math.round(payload.radius_m || 0) + ' m</strong></div>');
      toast('Masque de visibilité : ' + (payload.visible_pct || 0) + ' % dégagé.');
    }).catch(function () {
      apiOw.openDrawer('Visibilité', 'Masque', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function horizonSpark(samples) {
    if (!samples || !samples.length) return '';
    var vals = samples.map(function (s) { return Number(s.angle_deg) || 0; });
    var min = Math.min.apply(null, vals.concat([-2]));
    var max = Math.max.apply(null, vals.concat([8]));
    var span = Math.max(4, max - min);
    var w = 280;
    var h = 72;
    var pts = vals.map(function (v, i) {
      var x = (i / Math.max(1, vals.length - 1)) * w;
      var y = h - ((v - min) / span) * (h - 8) - 4;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    return '<svg class="ow-spark" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true"><polyline fill="none" stroke="#e7b14d" stroke-width="2" points="' + pts + '"/></svg>';
  }

  function requestHorizon(ll) {
    var apiOw = ow();
    var call = api();
    if (!apiOw || !call) return;
    var w = worldOf(ll);
    if (!w) return;
    apiOw.openDrawer('Horizon', 'Silhouette', '<p class="ow-help">Lecture de l’horizon…</p>');
    call('/api/atak/terrain/horizon', { method: 'POST', body: { mapId: apiOw.mapId, observer: { x: w.x, y: w.y } } }).then(function (payload) {
      if (!payload || payload.ready === false) {
        apiOw.openDrawer('Horizon', 'Silhouette', '<p class="ow-help">' + (payload && (payload.gap_message || payload.message) || 'Relief non relevé.') + '</p>');
        return;
      }
      var peak = payload.peak || {};
      apiOw.openDrawer('Horizon', 'Silhouette',
        '<p class="ow-help">Silhouette du relief autour de ce point. Le pic indique l’obstacle dominant.</p>' +
        horizonSpark(payload.samples) +
        '<div class="ow-event"><span>Obstacle dominant</span><strong>' + (peak.kind_label || 'Horizon') + '</strong></div>' +
        '<div class="ow-event"><span>Azimut</span><strong>' + Math.round(peak.az || 0) + '°</strong></div>' +
        '<div class="ow-event"><span>Élévation</span><strong>' + (Number(peak.angle_deg) || 0).toFixed(1).replace('.', ',') + '°</strong></div>');
    }).catch(function () {
      apiOw.openDrawer('Horizon', 'Silhouette', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function sliceSpark(payload) {
    var samples = payload.samples || [];
    var zs = samples.map(function (s) { return Number(s.z != null ? s.z : s.elevation); }).filter(function (z) { return !isNaN(z); });
    var vols = payload.volumes || [];
    var min = zs.length ? Math.min.apply(null, zs) : 0;
    var max = zs.length ? Math.max.apply(null, zs) : 10;
    vols.forEach(function (v) {
      min = Math.min(min, Number(v.z_base) || min);
      max = Math.max(max, Number(v.z_top) || max);
    });
    var span = Math.max(4, max - min);
    var dist = Math.max(1, Number(payload.distance_m) || 1);
    var w = 280;
    var h = 88;
    var pts = zs.map(function (z, i) {
      var x = (i / Math.max(1, zs.length - 1)) * w;
      var y = h - ((z - min) / span) * (h - 10) - 5;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    var boxes = vols.map(function (v) {
      var x = (Number(v.d) / dist) * w;
      var y1 = h - (((Number(v.z_top) || min) - min) / span) * (h - 10) - 5;
      var y0 = h - (((Number(v.z_base) || min) - min) / span) * (h - 10) - 5;
      var hh = Math.max(3, y0 - y1);
      return '<rect x="' + (x - 4).toFixed(1) + '" y="' + y1.toFixed(1) + '" width="8" height="' + hh.toFixed(1) + '" fill="#8a9aaa" opacity="0.7"/>';
    }).join('');
    return '<svg class="ow-spark" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true">' + boxes +
      '<polyline fill="none" stroke="#00d69a" stroke-width="2" points="' + pts + '"/></svg>';
  }

  function requestSlice(points) {
    var apiOw = ow();
    var call = api();
    if (!apiOw || !call || !points || points.length < 2) return;
    var world = points.map(function (ll) { return worldOf(ll); }).filter(Boolean);
    apiOw.openDrawer('Coupe', 'Tranche verticale', '<p class="ow-help">Calcul de la coupe…</p>');
    call('/api/atak/terrain/slice', { method: 'POST', body: { mapId: apiOw.mapId, points: world } }).then(function (payload) {
      if (!payload || payload.ready === false) {
        apiOw.openDrawer('Coupe', 'Tranche verticale', '<p class="ow-help">' + (payload && (payload.gap_message || payload.message) || 'Relief non relevé.') + '</p>');
        return;
      }
      var n = (payload.volumes || []).length;
      apiOw.openDrawer('Coupe', 'Tranche verticale',
        '<p class="ow-help">Sol et constructions croisés par le tracé.</p>' + sliceSpark(payload) +
        '<div class="ow-event"><span>Distance</span><strong>' + Math.round(payload.distance_m || 0) + ' m</strong></div>' +
        '<div class="ow-event"><span>Volumes croisés</span><strong>' + n + '</strong></div>');
    }).catch(function () {
      apiOw.openDrawer('Coupe', 'Tranche verticale', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function requestMeasure3d(points) {
    var apiOw = ow();
    var call = api();
    if (!apiOw || !call || !points || points.length < 2) return;
    var a = worldOf(points[0]);
    var b = worldOf(points[points.length - 1]);
    if (!a || !b) return;
    call('/api/atak/terrain/measure', { method: 'POST', body: { mapId: apiOw.mapId, from: a, to: b } }).then(function (payload) {
      if (!payload) return;
      var html = '<p class="ow-help">Mesure sur le relief du théâtre.</p>' +
        '<div class="ow-event"><span>Distance au sol</span><strong>' + Math.round(payload.distance_m || 0) + ' m</strong></div>' +
        '<div class="ow-event"><span>Distance spatiale</span><strong>' + Math.round(payload.spatial_m || 0) + ' m</strong></div>' +
        (payload.delta_m != null ? '<div class="ow-event"><span>Dénivelé</span><strong>' + (payload.delta_m > 0 ? '+' : '') + Math.round(payload.delta_m) + ' m</strong></div>' : '') +
        '<div class="ow-event"><span>Cap</span><strong>' + Math.round(payload.azimuth_deg || 0) + '°</strong></div>' +
        (payload.slope_pct != null ? '<div class="ow-event"><span>Pente</span><strong>' + String(payload.slope_pct).replace('.', ',') + ' %</strong></div>' : '');
      apiOw.openDrawer('Mesure', '3D', html);
      toast('Mesure 3D : ' + Math.round(payload.spatial_m || 0) + ' m spatiaux.');
    }).catch(function () {
      apiOw.openDrawer('Mesure', '3D', '<p class="ow-help">Relief non relevé.</p>');
    });
  }

  function saveVolume(points) {
    var apiOw = ow();
    if (!apiOw || !points || points.length < 3) return;
    var zMin = window.prompt('Altitude basse du volume (m)', '0');
    if (zMin === null) return;
    var zMax = window.prompt('Altitude haute du volume (m)', '12');
    if (zMax === null) return;
    var a = parseFloat(String(zMin).replace(',', '.'));
    var b = parseFloat(String(zMax).replace(',', '.'));
    if (!isFinite(a) || !isFinite(b)) { toast('Altitudes invalides.'); return; }
    apiOw.saveShape('AOI', points, 'Volume', {
      confirmed: true,
      meta: { volume: true, z_min: Math.min(a, b), z_max: Math.max(a, b) }
    });
    toast('Volume enregistré.');
  }

  function setSplit(on) {
    splitOn = !!on;
    var host = glHost();
    if (host && typeof host.setSplit === 'function') host.setSplit(splitOn);
    var stage = document.getElementById('ow-map-stage');
    if (stage) stage.classList.toggle('is-split', splitOn);
    var btn = document.querySelector('[data-tool="compare"]');
    if (btn) btn.classList.toggle('is-active', splitOn);
    toast(splitOn ? 'Comparaison 2D / 3D : même centre et même zoom.' : 'Comparaison 2D / 3D désactivée.');
  }

  function applyLayerPrefs() {
    var l = layers();
    if (!l || typeof l.setOpts !== 'function') return;
    l.setOpts({
      occlusion: pref(KEY_OCC, 'always'),
      ghost: pref(KEY_GHOST, false) === true || pref(KEY_GHOST, '0') === '1',
      heat: pref(KEY_HEAT, false) === true || pref(KEY_HEAT, '0') === '1',
      focus: pref(KEY_FOCUS, false) === true || pref(KEY_FOCUS, '0') === '1',
      cinematic: cinematicOn
    });
  }

  function bookmarks() {
    try {
      var raw = localStorage.getItem(KEY_BOOK);
      var rows = raw ? JSON.parse(raw) : [];
      return Array.isArray(rows) ? rows : [];
    } catch (e) { return []; }
  }
  function saveBookmarks(rows) {
    try { localStorage.setItem(KEY_BOOK, JSON.stringify(rows.slice(0, 24))); } catch (e) {}
  }

  function captureBookmark() {
    var host = glHost();
    var name = window.prompt('Nom de la vue', 'Vue QG');
    if (!name || !name.trim()) return;
    var cam = host && typeof host.getCamera === 'function' ? host.getCamera() : null;
    var lm = ow() && ow().map;
    var row = {
      name: name.trim(),
      lng: cam ? cam.lng : null,
      lat: cam ? cam.lat : null,
      zoom: cam ? cam.zoom : (lm ? lm.getZoom() : 12),
      pitch: cam ? cam.pitch : 0,
      bearing: cam ? cam.bearing : 0,
      mode: cam ? cam.mode : 'map',
      layers: {
        buildings: !!(document.getElementById('atak-scene-buildings') || {}).checked,
        quality: !!(document.getElementById('atak-scene-quality') || {}).checked
      }
    };
    if (!row.lng && lm) {
      var c = lm.getCenter();
      row.lat = c.lat;
      row.lng = c.lng;
    }
    var rows = bookmarks();
    rows.unshift(row);
    saveBookmarks(rows);
    toast('Vue « ' + row.name + ' » enregistrée.');
    renderBookmarkList();
  }

  function applyBookmark(row) {
    if (!row) return;
    var host = glHost();
    if (host && typeof host.setCamera === 'function' && row.lng != null) {
      if (!host.isActive || !host.isActive()) {
        if (typeof host.setEnabled === 'function') host.setEnabled(true);
      }
      window.setTimeout(function () { host.setCamera(row); }, 80);
    } else if (ow() && ow().map && row.lat != null) {
      ow().map.setView([row.lat, row.lng], row.zoom || ow().map.getZoom());
    }
    toast('Vue « ' + row.name + ' ».');
  }

  function renderBookmarkList() {
    var host = document.getElementById('ow-bookmark-list');
    if (!host) return;
    var rows = bookmarks();
    if (!rows.length) {
      host.innerHTML = '<p class="ow-help">Aucune vue enregistrée.</p>';
      return;
    }
    host.innerHTML = rows.map(function (row, i) {
      return '<button type="button" class="ow-secondary" data-ow-bookmark="' + i + '">' + String(row.name || 'Vue').replace(/[<>]/g, '') + '</button>';
    }).join(' ');
  }

  function loadCoverageGaps() {
    var apiOw = ow();
    var call = api();
    if (!apiOw || !call || !apiOw.map) return;
    if (coverageLayer) {
      try { apiOw.map.removeLayer(coverageLayer); } catch (e) {}
      coverageLayer = null;
    }
    var box = document.getElementById('atak-coverage-diag');
    if (!box || !box.checked) return;
    call('/api/atak/theater/coverage?mapId=' + encodeURIComponent(apiOw.mapId) + '&include=gaps').then(function (payload) {
      var gaps = payload && payload.terrain_gaps ? payload.terrain_gaps : [];
      coverageLayer = L.layerGroup();
      gaps.forEach(function (g) {
        var half = (Number(g.size) || 400) / 2;
        var sw = apiOw.worldToLatLng(Number(g.x) - half, Number(g.y) - half);
        var ne = apiOw.worldToLatLng(Number(g.x) + half, Number(g.y) + half);
        L.rectangle([sw, ne], { color: '#e05b63', weight: 1, fillOpacity: 0.12, className: 'ow-coverage-gap' }).addTo(coverageLayer);
      });
      coverageLayer.addTo(apiOw.map);
      toast(gaps.length ? (gaps.length + ' zones de relief manquantes.') : 'Couverture relief complète sur l’échantillon.');
    }).catch(function () {});
  }

  function openClusterStack(items) {
    var apiOw = ow();
    if (!apiOw) return;
    var cats = { personnes: 0, photos: 0, sse: 0, task: 0, notes: 0, evenements: 0 };
    (items || []).forEach(function (it) {
      var k = String(it.kind || it.cat || 'personnes').toLowerCase();
      if (k.indexOf('photo') >= 0) cats.photos += 1;
      else if (k.indexOf('sse') >= 0) cats.sse += 1;
      else if (k.indexOf('task') >= 0) cats.task += 1;
      else if (k.indexOf('note') >= 0) cats.notes += 1;
      else if (k.indexOf('event') >= 0 || k.indexOf('intel') >= 0) cats.evenements += 1;
      else cats.personnes += 1;
    });
    var html = '<p class="ow-help">' + (items || []).length + ' éléments au même endroit.</p>' +
      '<div class="ow-event"><span>Personnes</span><strong>' + cats.personnes + '</strong></div>' +
      '<div class="ow-event"><span>Photos</span><strong>' + cats.photos + '</strong></div>' +
      '<div class="ow-event"><span>SSE</span><strong>' + cats.sse + '</strong></div>' +
      '<div class="ow-event"><span>TASK</span><strong>' + cats.task + '</strong></div>' +
      '<div class="ow-event"><span>Notes</span><strong>' + cats.notes + '</strong></div>' +
      '<div class="ow-event"><span>Événements</span><strong>' + cats.evenements + '</strong></div>' +
      '<p class="ow-kicker">Liste</p>' +
      (items || []).slice(0, 16).map(function (it) {
        return '<div class="ow-event"><span>' + String(it.label || 'Contact').replace(/[<>]/g, '') + '</span><strong>' + String(it.kind || 'personne').replace(/[<>]/g, '') + '</strong></div>';
      }).join('');
    apiOw.openDrawer('Pile', (items || []).length + ' éléments', html);
  }

  function onReplay(event) {
    var clock = event && event.detail ? event.detail.clock : null;
    lastReplayClock = clock;
    if (!cinematicOn || clock == null) return;
    var host = glHost();
    var apiOw = ow();
    if (!host || !apiOw || typeof host.followWorld !== 'function') return;
    var samples = apiOw.getTrackSamples ? apiOw.getTrackSamples() : {};
    var best = null;
    var bestD = 0;
    Object.keys(samples).forEach(function (id) {
      var rows = samples[id] || [];
      var prev = null;
      var cur = null;
      rows.forEach(function (row) {
        if (row.t <= clock) { prev = cur; cur = row; }
      });
      if (cur && prev && cur.ll && prev.ll && apiOw.map) {
        var d = apiOw.map.distance(prev.ll, cur.ll);
        if (d > bestD) { bestD = d; best = cur; }
      } else if (!best && cur && cur.ll) best = cur;
    });
    if (best && best.ll && apiOw.latLngToWorld) {
      var w = apiOw.latLngToWorld(best.ll);
      if (w) host.followWorld(w.x, w.y);
    }
  }

  function bindUi() {
    var occ = document.getElementById('atak-symbol-occlusion');
    if (occ && !occ._owBound) {
      occ._owBound = true;
      occ.value = pref(KEY_OCC, 'always');
      occ.addEventListener('change', function () {
        setPref(KEY_OCC, occ.value);
        applyLayerPrefs();
      });
    }
    [['atak-ghost-trails', KEY_GHOST], ['atak-time-heat', KEY_HEAT], ['atak-focus-mission', KEY_FOCUS], ['atak-cinematic-aar', KEY_CINE]].forEach(function (pair) {
      var el = document.getElementById(pair[0]);
      if (!el || el._owBound) return;
      el._owBound = true;
      el.checked = pref(pair[1], false) === true || pref(pair[1], '0') === '1';
      if (pair[0] === 'atak-cinematic-aar') cinematicOn = el.checked;
      el.addEventListener('change', function () {
        setPref(pair[1], el.checked);
        if (pair[0] === 'atak-cinematic-aar') cinematicOn = el.checked;
        applyLayerPrefs();
        toast(el.checked ? el.parentNode.textContent.trim() : 'Option retirée.');
      });
    });
    var cov = document.getElementById('atak-coverage-diag');
    if (cov && !cov._owBound) {
      cov._owBound = true;
      cov.addEventListener('change', loadCoverageGaps);
    }
    var saveBk = document.getElementById('ow-bookmark-save');
    if (saveBk && !saveBk._owBound) {
      saveBk._owBound = true;
      saveBk.addEventListener('click', captureBookmark);
    }
    var list = document.getElementById('ow-bookmark-list');
    if (list && !list._owBound) {
      list._owBound = true;
      list.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-ow-bookmark]');
        if (!btn) return;
        applyBookmark(bookmarks()[Number(btn.getAttribute('data-ow-bookmark'))]);
      });
    }
    renderBookmarkList();
    applyLayerPrefs();
    window.addEventListener('overwatch:replay', onReplay);
    window.addEventListener('overwatch:cluster-stack', function (event) {
      openClusterStack(event.detail && event.detail.items);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindUi);
  else bindUi();

  return {
    requestViewshed: requestViewshed,
    requestHorizon: requestHorizon,
    requestSlice: requestSlice,
    requestMeasure3d: requestMeasure3d,
    saveVolume: saveVolume,
    setSplit: setSplit,
    isSplit: function () { return splitOn; },
    captureBookmark: captureBookmark,
    applyBookmark: applyBookmark,
    openClusterStack: openClusterStack,
    loadCoverageGaps: loadCoverageGaps
  };
})();
