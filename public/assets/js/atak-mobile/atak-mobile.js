/* COMSPEC ATAK Mobile — shell téléphone dédié (QR détaché) */
(function () {
  'use strict';

  var CFG = window.ATAK_MOBILE || {};
  var API = String(CFG.apiBase || '').replace(/\/$/, '');
  var MAP_ID = Number(CFG.mapId) || 1;
  var MAP_CFG = CFG.mapConfig || {};
  var LABELS = {
    c2: 'Centre C2',
    sitac: 'SITAC',
    chat: 'Tchat',
    bft: 'BFT',
    status: 'État C2',
    pings: 'Pings',
    intel: 'Intel',
    jtac: 'JTAC',
    air: 'Air Assets',
    sigint: 'SIGINT',
    orders: 'Ordres',
    explosives: 'Explosifs'
  };
  var PRIMARY_NAV = { c2: 1, sitac: 1, chat: 1, bft: 1 };
  var DRAWER_MODULES = [
    { id: 'c2', title: 'Centre C2', sub: 'Vue opérationnelle' },
    { id: 'sitac', title: 'SITAC', sub: 'Carte tactique' },
    { id: 'chat', title: 'Tchat', sub: 'Canaux C2' },
    { id: 'bft', title: 'BFT', sub: 'Positions live' },
    { id: 'pings', title: 'Pings', sub: 'Alertes terrain' },
    { id: 'intel', title: 'Intel', sub: 'Photos & rapports' },
    { id: 'jtac', title: 'JTAC', sub: '9-Line CAS' },
    { id: 'air', title: 'Air Assets', sub: 'Flight manifest' },
    { id: 'sigint', title: 'SIGINT', sub: 'Spectre / EW' },
    { id: 'status', title: 'État C2', sub: 'Santé système' },
    { id: 'orders', title: 'Ordres', sub: 'Tasking' },
    { id: 'explosives', title: 'Explosifs', sub: 'Charges armées' }
  ];

  var state = {
    module: normalizeModule(CFG.module || 'c2'),
    units: [],
    unitsById: {},
    lastUnitsAt: 0,
    chat: [],
    chatChannel: 'C2',
    chatSignature: '',
    chatDraft: '',
    pings: [],
    markers: [],
    nineLines: [],
    air: [],
    sigint: [],
    activity: [],
    stats: null,
    medical: [],
    reports: [],
    laserCodes: [],
    orders: [],
    explosives: [],
    intelPhotos: [],
    filters: { bft: true, markers: true, intel: true, air: true, sigint: true, jtac: true },
    bftFilter: 'LIVE',
    followId: null,
    map: null,
    unitLayer: null,
    markerLayer: null,
    unitMarkers: {},
    lastOkAt: 0,
    lastFailAt: 0,
    pollTimers: {},
    sitacReady: false
  };

  function normalizeModule(m) {
    m = String(m || 'c2').toLowerCase();
    return LABELS[m] ? m : 'c2';
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function authorLabel() {
    if (CFG.user && (CFG.user.callsign || CFG.user.displayName)) {
      return CFG.user.callsign || CFG.user.displayName;
    }
    if (CFG.phoneSession && CFG.phoneSession.label) return CFG.phoneSession.label;
    return 'Opérateur mobile';
  }

  function api(path, opts) {
    var url = path.indexOf('http') === 0 ? path : (API + path);
    var o = opts || {};
    o.credentials = 'same-origin';
    o.cache = o.cache || 'no-store';
    return fetch(url, o).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      var ct = r.headers.get('content-type') || '';
      if (ct.indexOf('json') >= 0) return r.json();
      return r.text();
    }).then(function (data) {
      state.lastOkAt = Date.now();
      updateLiveBadge();
      return data;
    }).catch(function (err) {
      state.lastFailAt = Date.now();
      updateLiveBadge();
      throw err;
    });
  }

  function qMap(path) {
    var sep = path.indexOf('?') >= 0 ? '&' : '?';
    return path + sep + 'mapId=' + encodeURIComponent(MAP_ID);
  }

  function zuluNow() {
    var d = new Date();
    var hh = String(d.getUTCHours()).padStart(2, '0');
    var mm = String(d.getUTCMinutes()).padStart(2, '0');
    var ss = String(d.getUTCSeconds()).padStart(2, '0');
    return hh + ':' + mm + ':' + ss + 'Z';
  }

  function ageSec(ts) {
    if (!ts) return null;
    var t = typeof ts === 'number' ? ts : Date.parse(ts);
    if (isNaN(t)) return null;
    return Math.max(0, Math.round((Date.now() - t) / 1000));
  }

  function fmtAge(sec) {
    if (sec == null) return '—';
    if (sec < 60) return sec + 's';
    if (sec < 3600) return Math.floor(sec / 60) + 'm';
    return Math.floor(sec / 3600) + 'h';
  }

  function gridLabel(x, y) {
    if (x == null || y == null || isNaN(x) || isNaN(y)) return '—';
    var gx = Math.floor(Number(x) / 100);
    var gy = Math.floor(Number(y) / 100);
    return String(gx).padStart(3, '0') + ' ' + String(gy).padStart(3, '0');
  }

  function unitExtra(u) {
    var ex = u && u.extra;
    if (typeof ex === 'string') {
      try { ex = JSON.parse(ex); } catch (e) { ex = {}; }
    }
    return ex && typeof ex === 'object' ? ex : {};
  }

  function unitStatus(u) {
    var st = String((u && u.status) || '').toLowerCase();
    var ex = unitExtra(u);
    var health = String(ex.health || u.health || 'ok').toLowerCase();
    if (health === 'wounded' || health === 'injured' || health === 'unconscious' || health === 'dead' || health === 'kia') {
      return 'MEDICAL';
    }
    if (ex.in_vehicle || ex.inVehicle) return 'IN VEHICLE';
    if (ex.airborne || ex.is_air) return 'AIRBORNE';
    if (st === 'linked') return 'LIVE';
    if (st === 'delayed') return 'STALE';
    return 'OFFLINE';
  }

  function isHostileUnit(u) {
    var side = String((u && (u.side || u.faction || unitExtra(u).side)) || '').toLowerCase();
    return side.indexOf('east') >= 0 || side.indexOf('enemy') >= 0 || side.indexOf('hostile') >= 0 || side === 'opfor';
  }

  function updateLiveBadge() {
    var el = document.getElementById('am-live');
    if (!el) return;
    var ago = ageSec(state.lastOkAt || null);
    var failAgo = ageSec(state.lastFailAt || null);
    var hidden = document.hidden;
    var label = 'OFFLINE';
    var st = 'offline';
    if (state.lastOkAt && (!state.lastFailAt || state.lastOkAt >= state.lastFailAt)) {
      if (ago != null && ago <= 8) {
        st = 'live';
        label = 'LIVE · ' + ago + 's';
      } else if (ago != null && ago <= 45) {
        st = 'degraded';
        label = 'STALE · ' + ago + 's';
      } else {
        st = 'degraded';
        label = 'STALE · ' + (ago != null ? ago + 's' : '—');
      }
    } else if (failAgo != null && failAgo < 120 && state.lastOkAt) {
      st = 'degraded';
      label = 'DEGRADED';
    }
    if (hidden && st === 'live') label = 'PAUSE';
    el.dataset.state = st;
    el.textContent = label;
  }

  function setModule(mod, pushUrl) {
    mod = normalizeModule(mod);
    state.module = mod;
    document.body.dataset.module = mod;
    var label = document.getElementById('am-module-label');
    if (label) label.textContent = LABELS[mod] || mod.toUpperCase();

    document.querySelectorAll('.am-screen').forEach(function (s) {
      var on = s.dataset.screen === mod;
      s.hidden = !on;
      s.classList.toggle('is-active', on);
    });
    document.querySelectorAll('.am-bottom__btn[data-nav]').forEach(function (b) {
      var nav = b.getAttribute('data-nav');
      if (nav === 'plus') {
        b.classList.toggle('is-active', !PRIMARY_NAV[mod]);
      } else {
        b.classList.toggle('is-active', nav === mod);
      }
    });

    if (pushUrl !== false) {
      var path = API + '/atak/mobile/' + encodeURIComponent(mod);
      try { history.replaceState({ module: mod }, '', path); } catch (e) { /* ignore */ }
    }

    renderModule(mod);
    if (mod === 'sitac') ensureMap();
    schedulePolls();
  }

  function openDrawer() {
    var d = document.getElementById('am-drawer');
    if (d) d.hidden = false;
  }
  function closeDrawer() {
    var d = document.getElementById('am-drawer');
    if (d) d.hidden = true;
  }
  function openSheet(html) {
    var s = document.getElementById('am-sheet');
    var body = document.getElementById('am-sheet-body');
    if (!s || !body) return;
    body.innerHTML = html;
    s.hidden = false;
  }
  function closeSheet() {
    var s = document.getElementById('am-sheet');
    if (s) s.hidden = true;
  }

  function renderDrawer() {
    var grid = document.getElementById('am-drawer-grid');
    if (!grid) return;
    grid.innerHTML = DRAWER_MODULES.map(function (m) {
      return '<button type="button" class="am-drawer__item" data-go="' + esc(m.id) + '">' +
        '<strong>' + esc(m.title) + '</strong><span>' + esc(m.sub) + '</span></button>';
    }).join('');
  }

  /* ---------- Polling ---------- */
  function clearPolls() {
    Object.keys(state.pollTimers).forEach(function (k) {
      clearInterval(state.pollTimers[k]);
    });
    state.pollTimers = {};
  }

  function every(key, ms, fn) {
    if (state.pollTimers[key]) clearInterval(state.pollTimers[key]);
    var run = function () {
      if (document.hidden) return;
      try { fn(); } catch (e) { /* ignore */ }
    };
    run();
    state.pollTimers[key] = setInterval(run, ms);
  }

  function schedulePolls() {
    clearPolls();
    var mod = state.module;
    every('units', 3500, loadUnits);
    every('stats', 10000, loadStats);
    if (mod === 'c2' || mod === 'status') every('activity', 8000, loadActivity);
    if (mod === 'chat' || mod === 'c2') every('chat', 3000, loadChat);
    if (mod === 'pings' || mod === 'sitac' || mod === 'c2') every('pings', 4000, loadPings);
    if (mod === 'sitac') every('markers', 5000, loadMarkers);
    if (mod === 'jtac' || mod === 'c2' || mod === 'sitac') every('nine', 6000, loadNineLines);
    if (mod === 'air' || mod === 'sitac') every('air', 7000, loadAir);
    if (mod === 'sigint' || mod === 'sitac') every('sigint', 8000, loadSigint);
    if (mod === 'intel') every('intel', 9000, loadIntel);
    if (mod === 'status' || mod === 'c2') every('medical', 8000, loadMedical);
    if (mod === 'orders') every('orders', 7000, loadOrders);
    if (mod === 'explosives') every('explosives', 5000, loadExplosives);
    if (mod === 'jtac') every('laser', 15000, loadLaserCodes);
  }

  function loadUnits() {
    return api(qMap('/api/atak/units')).then(function (rows) {
      state.units = Array.isArray(rows) ? rows : [];
      state.unitsById = {};
      state.units.forEach(function (u) {
        if (u && u.id != null) state.unitsById[String(u.id)] = u;
      });
      state.lastUnitsAt = Date.now();
      if (state.module === 'c2') renderC2();
      if (state.module === 'bft') renderBft();
      if (state.module === 'sitac') syncMapUnits();
      if (state.followId && state.map) followUnit(state.followId, false);
    }).catch(function () { /* keep last */ });
  }

  function loadStats() {
    return api(qMap('/api/atak/stats')).then(function (s) {
      state.stats = s && typeof s === 'object' ? s : null;
      if (state.module === 'c2') renderC2();
      if (state.module === 'status') renderStatus();
    }).catch(function () {});
  }

  function loadActivity() {
    return api(qMap('/api/atak/activity') + '&limit=40').then(function (rows) {
      state.activity = Array.isArray(rows) ? rows : (rows && rows.items) || [];
      if (state.module === 'c2') renderC2();
      if (state.module === 'status') renderStatus();
    }).catch(function () {});
  }

  function loadChat() {
    return api(qMap('/api/chat') + '&limit=80').then(function (rows) {
      state.chat = Array.isArray(rows) ? rows : [];
      if (state.module === 'chat') renderChat();
      if (state.module === 'c2') renderC2();
    }).catch(function () {});
  }

  function loadPings() {
    return api(qMap('/api/pings') + '&limit=80').then(function (rows) {
      state.pings = Array.isArray(rows) ? rows : [];
      if (state.module === 'pings') renderPings();
      if (state.module === 'c2') renderC2();
      if (state.module === 'sitac') syncMapUnits();
    }).catch(function () {});
  }

  function loadMarkers() {
    return api(qMap('/api/atak/markers')).then(function (rows) {
      state.markers = Array.isArray(rows) ? rows : [];
      if (state.module === 'sitac') syncMapUnits();
    }).catch(function () {});
  }

  function loadNineLines() {
    return api(qMap('/api/nine-line')).then(function (rows) {
      state.nineLines = Array.isArray(rows) ? rows : [];
      if (state.module === 'jtac') renderJtac();
      if (state.module === 'c2') renderC2();
    }).catch(function () {});
  }

  function loadAir() {
    return api(qMap('/api/atak/air-assets')).then(function (rows) {
      state.air = Array.isArray(rows) ? rows : (rows && rows.assets) || [];
      if (state.module === 'air') renderAir();
    }).catch(function () {});
  }

  function loadSigint() {
    return api(qMap('/api/atak/sigint/zones')).then(function (rows) {
      state.sigint = Array.isArray(rows) ? rows : [];
      if (state.module === 'sigint') renderSigint();
    }).catch(function () {});
  }

  function loadIntel() {
    return Promise.all([
      api(qMap('/api/atak/intel-view')).catch(function () { return []; }),
      api(qMap('/api/atak/reports') + '&limit=40').catch(function () { return []; }),
      api(qMap('/api/atak/photo-nights')).catch(function () { return []; })
    ]).then(function (parts) {
      var photos = [];
      [parts[0], parts[2]].forEach(function (block) {
        if (Array.isArray(block)) photos = photos.concat(block);
        else if (block && Array.isArray(block.photos)) photos = photos.concat(block.photos);
        else if (block && Array.isArray(block.items)) photos = photos.concat(block.items);
      });
      state.intelPhotos = photos.slice(0, 60);
      state.reports = Array.isArray(parts[1]) ? parts[1] : (parts[1] && parts[1].items) || [];
      if (state.module === 'intel') renderIntel();
    });
  }

  function loadMedical() {
    return api(qMap('/api/atak/medical-alerts')).then(function (rows) {
      state.medical = Array.isArray(rows) ? rows : (rows && rows.alerts) || [];
      if (state.module === 'c2' || state.module === 'status') {
        if (state.module === 'c2') renderC2();
        else renderStatus();
      }
    }).catch(function () {});
  }

  function loadOrders() {
    return api(qMap('/api/atak/orders') + '&limit=60').then(function (rows) {
      state.orders = Array.isArray(rows) ? rows : (rows && rows.orders) || [];
      if (state.module === 'orders') renderOrders();
    }).catch(function () {});
  }

  function loadExplosives() {
    return api(qMap('/api/atak/explosive-timers')).then(function (rows) {
      state.explosives = Array.isArray(rows) ? rows : [];
      if (state.module === 'explosives') renderExplosives();
    }).catch(function () {});
  }

  function loadLaserCodes() {
    return api(qMap('/api/atak/laser-codes')).then(function (rows) {
      state.laserCodes = Array.isArray(rows) ? rows : [];
      if (state.module === 'jtac') renderJtac();
    }).catch(function () {});
  }

  /* ---------- Screens ---------- */
  function renderModule(mod) {
    switch (mod) {
      case 'c2': return renderC2();
      case 'sitac': return renderSitacChrome();
      case 'chat': return renderChat();
      case 'bft': return renderBft();
      case 'status': return renderStatus();
      case 'pings': return renderPings();
      case 'intel': return renderIntel();
      case 'jtac': return renderJtac();
      case 'air': return renderAir();
      case 'sigint': return renderSigint();
      case 'orders': return renderOrders();
      case 'explosives': return renderExplosives();
      default: return renderC2();
    }
  }

  function liveUnits() {
    return state.units.filter(function (u) {
      var st = String(u.status || '').toLowerCase();
      return st === 'linked' || st === 'delayed';
    });
  }

  function renderC2() {
    var el = document.getElementById('am-screen-c2');
    if (!el) return;
    var stats = state.stats || {};
    var live = liveUnits().length;
    var unread = state.chat.length ? Math.min(state.chat.length, 99) : 0;
    var alerts = (state.medical || []).length + (state.pings || []).length;
    var nine = (state.nineLines || []).filter(function (n) {
      var s = String(n.status || 'active').toLowerCase();
      return s === 'active' || s === 'pending' || s === 'on_station';
    }).length;
    var preview = liveUnits().slice(0, 5);
    var timeline = (state.activity || []).slice(0, 12);

    el.innerHTML =
      '<div class="am-pad">' +
      '<div class="am-card">' +
      '<h3>Opération</h3>' +
      '<p style="margin:0;font-weight:700">' + esc(CFG.tenantName || 'COMSPEC') + '</p>' +
      '<p class="am-muted" style="margin:.25rem 0 0;font-family:var(--am-mono);font-size:.72rem">' +
      esc(MAP_CFG.title || MAP_CFG.slug || 'Théâtre') + ' · carte #' + MAP_ID + ' · ' + esc(zuluNow()) + '</p>' +
      '<p class="am-muted" style="margin:.35rem 0 0;font-size:.72rem">Réseau · dernière trame Arma : ' +
      (stats.lastArmaActivityAgo != null ? fmtAge(stats.lastArmaActivityAgo) : '—') + '</p>' +
      '</div>' +
      '<div class="am-kpis">' +
      kpi(live, 'Unités live') +
      kpi(unread, 'Messages') +
      kpi(alerts, 'Alertes') +
      kpi(nine, '9-Line') +
      '</div>' +
      '<div class="am-card">' +
      '<h3>Mini SITAC</h3>' +
      '<div class="am-list">' +
      (preview.length ? preview.map(function (u) {
        var st = unitStatus(u);
        return '<button type="button" class="am-row" data-unit="' + esc(u.id) + '">' +
          '<div><strong>' + esc(u.call_sign || '—') + '</strong>' +
          '<small>' + esc(gridLabel(u.pos_x, u.pos_y)) + ' · ' + esc(st) + '</small></div>' +
          '<span class="am-badge am-badge--' + (st === 'LIVE' ? 'live' : st === 'STALE' ? 'stale' : 'off') + '">' + esc(st) + '</span></button>';
      }).join('') : '<p class="am-muted" style="margin:0">Aucune unité live.</p>') +
      '</div>' +
      '<div class="am-actions" style="margin-top:.65rem"><button type="button" class="primary" data-go="sitac">Ouvrir SITAC</button></div>' +
      '</div>' +
      '<div class="am-card">' +
      '<h3>Activité</h3>' +
      '<div class="am-list">' +
      (timeline.length ? timeline.map(function (a) {
        var t = a.created_at || a.at || a.time || '';
        var msg = a.message || a.label || a.type || 'Événement';
        return '<div class="am-row"><div><strong style="font-size:.78rem;font-weight:600">' + esc(msg) + '</strong>' +
          '<small>' + esc(t) + (a.author ? ' · ' + esc(a.author) : '') + '</small></div></div>';
      }).join('') : '<p class="am-muted" style="margin:0">Pas d’activité récente.</p>') +
      '</div></div></div>';
  }

  function kpi(v, l) {
    return '<div class="am-kpi"><div class="am-kpi__v">' + esc(v) + '</div><div class="am-kpi__l">' + esc(l) + '</div></div>';
  }

  /* ---------- SITAC ---------- */
  function renderSitacChrome() {
    var chips = document.getElementById('am-map-chips');
    var tools = document.getElementById('am-map-tools');
    if (chips) {
      chips.innerHTML = ['bft', 'markers', 'intel', 'air', 'sigint', 'jtac'].map(function (k) {
        return '<button type="button" class="am-chip' + (state.filters[k] ? ' is-on' : '') + '" data-filter="' + k + '">' +
          k.toUpperCase() + '</button>';
      }).join('');
    }
    if (tools) {
      tools.innerHTML =
        '<button type="button" id="am-center" title="Centrer">⌖</button>' +
        '<button type="button" id="am-layers" title="Layers">☰</button>' +
        '<button type="button" id="am-ping-btn" title="Ping">◎</button>';
    }
  }

  function ensureMap() {
    if (state.map || typeof L === 'undefined') {
      if (state.map) setTimeout(function () { state.map.invalidateSize(); }, 80);
      return;
    }
    var el = document.getElementById('am-map');
    if (!el) return;
    var crsRaw = MAP_CFG.crs || {};
    var factorx = Number(crsRaw.factorx) || 0.006839;
    var factory = Number(crsRaw.factory) || 0.006836;
    var tileWidth = Number(crsRaw.tileWidth || MAP_CFG.tileSize) || 212;
    var CRS = typeof window.MGRS_CRS === 'function'
      ? window.MGRS_CRS(factorx, factory, tileWidth)
      : L.CRS.Simple;
    var center = MAP_CFG.center || [15000, 15000];
    var lat = Number(center[1] != null ? center[1] : center.y) || 15000;
    var lng = Number(center[0] != null ? center[0] : center.x) || 15000;
    state.map = L.map(el, {
      crs: CRS,
      center: [lat, lng],
      zoom: Number(MAP_CFG.defaultZoom) || 3,
      minZoom: Number(MAP_CFG.minZoom) || 0,
      maxZoom: Number(MAP_CFG.maxZoom) || 6,
      zoomControl: false,
      attributionControl: false
    });
    var pattern = MAP_CFG.tilePattern || '';
    if (pattern) {
      L.tileLayer(pattern, {
        tileSize: Number(MAP_CFG.tileSize) || 212,
        noWrap: true,
        bounds: L.latLngBounds([0, 0], [Number(MAP_CFG.worldSize) || 30720, Number(MAP_CFG.worldSize) || 30720])
      }).addTo(state.map);
    }
    state.unitLayer = L.layerGroup().addTo(state.map);
    state.markerLayer = L.layerGroup().addTo(state.map);
    state.sitacReady = true;
    syncMapUnits();
    setTimeout(function () { state.map.invalidateSize(); }, 100);
  }

  function syncMapUnits() {
    if (!state.map || !state.unitLayer) return;
    var seen = {};
    if (state.filters.bft) {
      state.units.forEach(function (u) {
        var x = parseFloat(u.pos_x);
        var y = parseFloat(u.pos_y);
        if (isNaN(x) || isNaN(y)) return;
        var id = String(u.id);
        seen[id] = true;
        var hostile = isHostileUnit(u);
        var st = unitStatus(u);
        var color = hostile ? 'var(--am-danger)' : (st === 'LIVE' ? '#35d6a1' : (st === 'STALE' ? '#f0a202' : '#64748b'));
        var html = '<span class="am-unit-dot' + (hostile ? ' am-unit-dot--hostile' : '') + '" style="background:' + color + '"></span>';
        var icon = L.divIcon({ className: 'am-leaflet-unit', html: html, iconSize: [14, 14], iconAnchor: [7, 7] });
        if (state.unitMarkers[id]) {
          state.unitMarkers[id].setLatLng([y, x]);
          state.unitMarkers[id].setIcon(icon);
        } else {
          var m = L.marker([y, x], { icon: icon });
          m.on('click', function () { showUnitSheet(u); });
          m.addTo(state.unitLayer);
          state.unitMarkers[id] = m;
        }
      });
    }
    Object.keys(state.unitMarkers).forEach(function (id) {
      if (!seen[id]) {
        try { state.unitLayer.removeLayer(state.unitMarkers[id]); } catch (e) {}
        delete state.unitMarkers[id];
      }
    });

    if (state.markerLayer) {
      state.markerLayer.clearLayers();
      if (state.filters.markers || state.filters.intel) {
        (state.pings || []).forEach(function (p) {
          var x = parseFloat(p.pos_x);
          var y = parseFloat(p.pos_y);
          if (isNaN(x) || isNaN(y)) return;
          var dot = L.circleMarker([y, x], {
            radius: 6, color: '#f0a202', fillColor: '#f0a202', fillOpacity: 0.85, weight: 1
          });
          dot.on('click', function () {
            openSheet('<h3 style="margin:0 0 .5rem">Ping</h3><p>' + esc(p.message || '') + '</p>' +
              '<p class="am-muted" style="font-family:var(--am-mono)">' + esc(gridLabel(x, y)) + '</p>' +
              '<div class="am-actions"><button type="button" class="primary" data-center-xy="' + x + ',' + y + '">Centrer</button></div>');
          });
          dot.addTo(state.markerLayer);
        });
      }
    }
  }

  function showUnitSheet(u) {
    var ex = unitExtra(u);
    var st = unitStatus(u);
    var health = ex.health || u.health || 'ok';
    openSheet(
      '<div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start">' +
      '<div><p class="am-muted" style="margin:0;font-size:.65rem;letter-spacing:.12em">UNITÉ</p>' +
      '<h3 style="margin:.15rem 0 0">' + esc(u.call_sign || '—') + '</h3>' +
      '<p class="am-muted" style="margin:.2rem 0 0;font-size:.75rem">' + esc(ex.role || u.role || '—') + '</p></div>' +
      '<span class="am-badge am-badge--' + (st === 'LIVE' ? 'live' : st === 'STALE' ? 'stale' : 'off') + '">' + esc(st) + '</span></div>' +
      '<div class="am-list" style="margin-top:.75rem">' +
      sheetRow('Grid', gridLabel(u.pos_x, u.pos_y)) +
      sheetRow('Altitude', (ex.alt != null ? ex.alt : (u.pos_z != null ? u.pos_z : '—')) + (ex.alt != null || u.pos_z != null ? ' m' : '')) +
      sheetRow('Vitesse', ex.speed != null ? (Math.round(ex.speed) + ' km/h') : '—') +
      sheetRow('Médical', String(health)) +
      sheetRow('Radio', ex.radio_freq || ex.radio || '—') +
      sheetRow('MàJ', u.updated_at || u.last_seen || '—') +
      '</div>' +
      '<div class="am-actions">' +
      '<button type="button" data-go="chat">Message</button>' +
      '<button type="button" class="primary" data-center-unit="' + esc(u.id) + '">Centrer</button>' +
      '<button type="button" data-follow-unit="' + esc(u.id) + '">Suivre</button>' +
      '<button type="button" data-go="pings">Ping</button>' +
      '</div>'
    );
  }

  function sheetRow(k, v) {
    return '<div class="am-row"><span class="am-muted">' + esc(k) + '</span><strong style="font-family:var(--am-mono);font-size:.78rem">' + esc(v) + '</strong></div>';
  }

  function followUnit(id, toggle) {
    if (toggle !== false && state.followId === String(id)) {
      state.followId = null;
      return;
    }
    state.followId = String(id);
    var u = state.unitsById[String(id)];
    if (!u || !state.map) return;
    var x = parseFloat(u.pos_x);
    var y = parseFloat(u.pos_y);
    if (!isNaN(x) && !isNaN(y)) state.map.panTo([y, x]);
  }

  function centerOn(x, y) {
    ensureMap();
    if (!state.map) return;
    state.map.setView([Number(y), Number(x)], Math.max(state.map.getZoom(), 4));
  }

  /* ---------- Chat ---------- */
  function visibleChatMessages() {
    return state.chat.slice().reverse().filter(function (m) {
      var body = String(m.body || m.message || '');
      var ch = state.chatChannel;
      if (ch === 'C2') return true;
      return body.toUpperCase().indexOf('[' + ch) >= 0 || String(m.channel || '').toUpperCase() === ch;
    }).slice(-80);
  }

  function chatFingerprint(msgs) {
    return state.chatChannel + '#' + msgs.map(function (m) {
      return String(m.id != null ? m.id : '') + ':' + String(m.body || m.message || '').length;
    }).join('|');
  }

  function updateChatChannels() {
    var el = document.getElementById('am-chat-channels');
    if (!el) return;
    var channels = ['C2', 'ALPHA', 'BRAVO', 'JTAC', 'LOG'];
    if (!el.dataset.ready) {
      el.innerHTML = channels.map(function (c) {
        return '<button type="button" class="am-chip" data-channel="' + c + '">' + c + '</button>';
      }).join('');
      el.dataset.ready = '1';
    }
    el.querySelectorAll('[data-channel]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-channel') === state.chatChannel);
    });
  }

  function resizeChatInput(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(Math.max(el.scrollHeight, 44), 140) + 'px';
  }

  function restoreChatDraft() {
    var input = document.getElementById('am-chat-input');
    if (!input) return;
    if (state.chatDraft && input.value === '') input.value = state.chatDraft;
    resizeChatInput(input);
  }

  function renderChat(force) {
    var list = document.getElementById('am-chat-list');
    if (!list) return;
    updateChatChannels();
    restoreChatDraft();
    var msgs = visibleChatMessages();
    var fp = chatFingerprint(msgs);
    if (!force && fp === state.chatSignature) return;
    state.chatSignature = fp;

    var nearBottom = (list.scrollHeight - list.scrollTop - list.clientHeight) < 96;
    var prevScroll = list.scrollTop;
    list.innerHTML = msgs.length
      ? msgs.map(renderChatMsg).join('')
      : '<p class="am-muted">Aucun message pour le moment.</p>';
    if (nearBottom || force) list.scrollTop = list.scrollHeight;
    else list.scrollTop = prevScroll;
  }

  function linkGrids(html) {
    return html
      .replace(/(GRID\s*)(\d{2,4})\s+(\d{2,4})/gi, function (_, p, a, b) {
        var x = parseInt(a, 10) * 100;
        var y = parseInt(b, 10) * 100;
        return '<a href="#" data-center-xy="' + x + ',' + y + '">' + p + a + ' ' + b + '</a>';
      })
      .replace(/(Grille\s+)(\d{6})/gi, function (_, p, g) {
        var x = parseInt(g.slice(0, 3), 10) * 100;
        var y = parseInt(g.slice(3, 6), 10) * 100;
        return '<a href="#" data-center-xy="' + x + ',' + y + '">' + p + g + '</a>';
      });
  }

  function chatTime(m) {
    var ts = m.created_at || m.time || '';
    var tShort = ts ? String(ts).slice(11, 19) : '';
    return tShort ? (tShort + 'Z') : '';
  }

  function parseGroupMsg(m, body) {
    if (m && m.group && (m.group.text || m.group.call_sign)) return m.group;
    var raw = String(body || '');
    var cut = raw.replace(/^(\[[^\]]+\]){3,4}\s*/, '');
    if (cut.toUpperCase().indexOf('GROUPE|') !== 0) return null;
    var parts = cut.split('|');
    if (parts.length < 5) return null;
    return {
      group_id: parts[1] || '',
      call_sign: parts[2] || '',
      grid: parts[3] || '',
      text: parts.slice(4).join('|')
    };
  }

  function parseMedicalMsg(body) {
    var raw = String(body || '').replace(/^(\[[^\]]+\]){3,4}\s*/, '');
    if (!/ALERTE\s+M[ÉE]DICALE/i.test(raw) && raw.toUpperCase().indexOf('WIA|') !== 0) return null;
    var parts = raw.split('|').map(function (s) { return String(s || '').trim(); });
    if (raw.toUpperCase().indexOf('WIA|') === 0) {
      return {
        title: 'Alerte médicale',
        call_sign: '',
        label: parts[1] || 'Blessé',
        extras: parts.slice(2)
      };
    }
    return {
      title: 'Alerte médicale',
      call_sign: parts[1] || '',
      label: parts[2] || '',
      extras: parts.slice(3)
    };
  }

  function parseMpMsg(m, body) {
    if (m && m.mp && (m.mp.text || m.mp.body)) return m.mp;
    var raw = String(body || '');
    var up = raw.toUpperCase();
    if (up.indexOf('MP|') !== 0 && up.indexOf('PRIVÉ|') !== 0 && up.indexOf('PRIVE|') !== 0) return null;
    var parts = raw.split('|');
    return {
      from: parts[1] || '',
      to: parts[2] || '',
      text: parts.slice(Math.max(parts.length - 1, 3)).join('|')
    };
  }

  function renderChatMsg(m) {
    var body = String(m.body || m.message || '');
    var author = String(m.author || '—');
    var when = chatTime(m);
    var group = parseGroupMsg(m, body);
    var medical = parseMedicalMsg(body);
    var mp = parseMpMsg(m, body);
    var cls = 'am-msg';
    var tag = '';
    var text = body;
    var facts = [];

    if (medical) {
      cls += ' am-msg--med';
      tag = 'Médical';
      author = medical.call_sign || author;
      text = [medical.call_sign, medical.label].filter(Boolean).join(' — ') || medical.title;
      facts = medical.extras.slice();
    } else if (group) {
      cls += ' am-msg--group';
      tag = 'Groupe';
      author = group.call_sign || author;
      text = group.text || 'Message de groupe';
      if (group.group_id) facts.push(group.group_id);
      if (group.grid) facts.push('Grille ' + group.grid);
    } else if (mp) {
      cls += ' am-msg--mp';
      tag = 'Privé';
      author = mp.from || author;
      text = mp.text || '';
      if (mp.to) facts.push('Pour ' + mp.to);
    }

    return '<article class="' + cls + '">' +
      '<div class="am-msg__meta">' +
        '<strong>' + esc(author) + '</strong>' +
        (tag ? '<span class="am-msg__tag">' + esc(tag) + '</span>' : '') +
        '<span>' + esc(when) + '</span>' +
      '</div>' +
      '<div class="am-msg__body">' + linkGrids(esc(text)) + '</div>' +
      (facts.length ? '<div class="am-msg__facts">' + facts.map(function (f) {
        return '<span>' + linkGrids(esc(f)) + '</span>';
      }).join('') + '</div>' : '') +
      '</article>';
  }

  function sendChat(text) {
    text = String(text || '').trim();
    if (!text) return Promise.resolve();
    var body = state.chatChannel !== 'C2' ? ('[' + state.chatChannel + '] ' + text) : text;
    return api('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ mapId: MAP_ID, author: authorLabel(), body: body, message: body })
    }).then(function () {
      state.chatDraft = '';
      return loadChat();
    });
  }

  /* ---------- BFT ---------- */
  function renderBft() {
    var el = document.getElementById('am-screen-bft');
    if (!el) return;
    var list = state.units.slice();
    if (state.bftFilter === 'LIVE') {
      list = list.filter(function (u) {
        var s = String(u.status || '').toLowerCase();
        return s === 'linked' || s === 'delayed';
      });
    }
    list.sort(function (a, b) {
      return String(a.call_sign || '').localeCompare(String(b.call_sign || ''));
    });

    el.innerHTML =
      '<div class="am-pad">' +
      '<div class="am-chat__channels" style="margin:-.75rem -.75rem .25rem;border-radius:0">' +
      ['LIVE', 'TOUS', 'GROUPES'].map(function (f) {
        return '<button type="button" class="am-chip' + (state.bftFilter === f ? ' is-on' : '') + '" data-bft-filter="' + f + '">' + f + '</button>';
      }).join('') +
      '</div>' +
      '<div class="am-list">' +
      (list.length ? list.map(function (u) {
        var st = unitStatus(u);
        var ex = unitExtra(u);
        var badge = st === 'LIVE' ? 'live' : (st === 'STALE' || st === 'MEDICAL' ? 'stale' : 'off');
        if (isHostileUnit(u)) badge = 'hostile';
        return '<button type="button" class="am-row" data-unit="' + esc(u.id) + '">' +
          '<div><strong>' + esc(u.call_sign || '—') + '</strong>' +
          '<small>' + esc(ex.role || u.role || '—') + ' · ' + esc(gridLabel(u.pos_x, u.pos_y)) +
          (u.pos_z != null ? ' · ' + Math.round(u.pos_z) + 'm' : '') + '</small></div>' +
          '<span class="am-badge am-badge--' + badge + '">' + esc(st) + '</span></button>';
      }).join('') : '<p class="am-muted">Aucune unité.</p>') +
      '</div></div>';
  }

  /* ---------- Status ---------- */
  function renderStatus() {
    var el = document.getElementById('am-screen-status');
    if (!el) return;
    var s = state.stats || {};
    var ago = s.lastArmaActivityAgo;
    var armaOk = ago != null && ago < 30;
    var apiOk = state.lastOkAt && ageSec(state.lastOkAt) < 15;
    var score = (apiOk ? 40 : 0) + (armaOk ? 40 : (ago != null && ago < 120 ? 20 : 0)) + (liveUnits().length ? 20 : 0);

    function row(name, st, detail) {
      var tone = st === 'OK' ? 'live' : (st === 'DEGRADED' ? 'stale' : 'off');
      return '<div class="am-row"><div><strong>' + esc(name) + '</strong><small>' + esc(detail || '') + '</small></div>' +
        '<span class="am-badge am-badge--' + tone + '">' + esc(st) + '</span></div>';
    }

    el.innerHTML =
      '<div class="am-pad">' +
      '<div class="am-card"><h3>Score global</h3>' +
      '<div class="am-kpi__v">' + score + '/100</div>' +
      '<p class="am-muted" style="margin:.35rem 0 0;font-size:.72rem">' + (score >= 80 ? 'OK' : score >= 50 ? 'DEGRADED' : 'ERROR') + '</p></div>' +
      '<div class="am-list">' +
      row('API C2', apiOk ? 'OK' : 'DEGRADED', 'Dernière réponse ' + fmtAge(ageSec(state.lastOkAt))) +
      row('COMSPEC Overwatch', armaOk ? 'OK' : (ago != null ? 'DEGRADED' : 'OFFLINE'), 'Dernière trame ' + (ago != null ? fmtAge(ago) : '—')) +
      row('BFT', liveUnits().length ? 'OK' : 'DEGRADED', liveUnits().length + ' unités live') +
      row('Alertes médicales', (state.medical || []).length ? 'DEGRADED' : 'OK', (state.medical || []).length + ' actives') +
      '</div>' +
      '<div class="am-card"><h3>Console</h3><div class="am-list">' +
      (state.activity || []).slice(0, 8).map(function (a) {
        var lvl = String(a.level || a.severity || 'INFO').toUpperCase();
        return '<div class="am-row"><div><strong style="font-size:.72rem">' + esc(lvl) + '</strong>' +
          '<small>' + esc(a.message || a.label || a.type || '') + '</small></div>' +
          '<span class="am-muted" style="font-family:var(--am-mono);font-size:.65rem">' + esc(String(a.created_at || '').slice(11, 19)) + '</span></div>';
      }).join('') +
      '</div></div></div>';
  }

  /* ---------- Pings ---------- */
  function renderPings() {
    var el = document.getElementById('am-screen-pings');
    if (!el) return;
    var list = state.pings || [];
    el.innerHTML =
      '<div class="am-pad">' +
      '<div class="am-actions" style="grid-template-columns:1fr"><button type="button" class="primary" id="am-new-ping">NOUVEAU PING</button></div>' +
      '<div class="am-list">' +
      (list.length ? list.map(function (p) {
        var age = fmtAge(ageSec(p.created_at));
        return '<button type="button" class="am-row" data-ping-id="' + esc(p.id) + '">' +
          '<div><strong>' + esc((p.message || '').slice(0, 60) || 'Ping') + '</strong>' +
          '<small>' + esc(p.author || '—') + ' · ' + esc(age) + ' · ' + esc(gridLabel(p.pos_x, p.pos_y)) + '</small></div>' +
          '<span class="am-badge am-badge--stale">ACTIF</span></button>';
      }).join('') : '<p class="am-muted">Aucun ping.</p>') +
      '</div></div>';
  }

  function showPingCreate() {
    openSheet(
      '<h3 style="margin:0 0 .65rem">Nouveau ping</h3>' +
      '<label class="am-muted" style="font-size:.7rem">Type</label>' +
      '<select id="am-ping-type" style="width:100%;min-height:42px;margin:.25rem 0 .55rem;background:#0a1016;color:inherit;border:1px solid var(--am-line);border-radius:.4rem">' +
      '<option>CONTACT</option><option>HOSTILE</option><option>MEDICAL</option><option>OBJECTIF</option><option>ALERTE</option></select>' +
      '<label class="am-muted" style="font-size:.7rem">Message</label>' +
      '<input id="am-ping-msg" style="width:100%;min-height:42px;margin:.25rem 0 .55rem;background:#0a1016;color:inherit;border:1px solid var(--am-line);border-radius:.4rem;padding:0 .65rem" placeholder="Description">' +
      '<label class="am-muted" style="font-size:.7rem">Position (X Y monde)</label>' +
      '<input id="am-ping-xy" style="width:100%;min-height:42px;margin:.25rem 0 .55rem;background:#0a1016;color:inherit;border:1px solid var(--am-line);border-radius:.4rem;padding:0 .65rem;font-family:var(--am-mono)" placeholder="15000 15000">' +
      '<div class="am-actions"><button type="button" class="primary" id="am-ping-submit">Transmettre</button><button type="button" data-am-close-sheet>Annuler</button></div>'
    );
  }

  function submitPing() {
    var type = (document.getElementById('am-ping-type') || {}).value || 'CONTACT';
    var msg = (document.getElementById('am-ping-msg') || {}).value || '';
    var xy = String((document.getElementById('am-ping-xy') || {}).value || '').trim().split(/\s+/);
    var x = parseFloat(xy[0]);
    var y = parseFloat(xy[1]);
    if (isNaN(x) || isNaN(y)) {
      if (state.map) {
        var c = state.map.getCenter();
        x = c.lng; y = c.lat;
      } else {
        x = 15000; y = 15000;
      }
    }
    return api('/api/pings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        mapId: MAP_ID,
        author: authorLabel(),
        pos_x: x,
        pos_y: y,
        message: '[' + type + '] ' + msg
      })
    }).then(function () {
      closeSheet();
      return loadPings();
    });
  }

  /* ---------- Intel / JTAC / Air / SIGINT / Orders / Explosives ---------- */
  function renderIntel() {
    var el = document.getElementById('am-screen-intel');
    if (!el) return;
    var photos = state.intelPhotos || [];
    var reports = state.reports || [];
    el.innerHTML =
      '<div class="am-pad">' +
      '<div class="am-card"><h3>Photos</h3>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem">' +
      (photos.length ? photos.slice(0, 12).map(function (p) {
        var src = p.thumb_url || p.url || p.path || '';
        return '<button type="button" class="am-row" style="flex-direction:column;align-items:stretch;padding:.4rem" data-intel-photo="' + esc(p.id || '') + '">' +
          (src ? '<img src="' + esc(src) + '" alt="" style="width:100%;height:72px;object-fit:cover;border-radius:.3rem;background:#111">' : '<div style="height:72px;background:#111;border-radius:.3rem"></div>') +
          '<small style="margin-top:.3rem">' + esc(p.author || p.call_sign || '—') + ' · ' + esc(String(p.created_at || '').slice(11, 16)) + '</small></button>';
      }).join('') : '<p class="am-muted">Aucune photo.</p>') +
      '</div></div>' +
      '<div class="am-card"><h3>Rapports</h3><div class="am-list">' +
      (reports.length ? reports.slice(0, 20).map(function (r) {
        return '<div class="am-row"><div><strong>' + esc(r.type || r.report_type || 'INTEL') + '</strong>' +
          '<small>' + esc(r.author || r.operator || '—') + ' · ' + esc(gridLabel(r.pos_x, r.pos_y)) + '</small></div>' +
          '<span class="am-badge">' + esc(r.confidence || r.status || '—') + '</span></div>';
      }).join('') : '<p class="am-muted">Aucun rapport.</p>') +
      '</div></div></div>';
  }

  function renderJtac() {
    var el = document.getElementById('am-screen-jtac');
    if (!el) return;
    var lines = state.nineLines || [];
    el.innerHTML =
      '<div class="am-pad">' +
      (lines.length ? lines.map(function (n) {
        var payload = n.payload || n.data || n;
        if (typeof payload === 'string') {
          try { payload = JSON.parse(payload); } catch (e) { payload = n; }
        }
        var st = String(n.status || 'active').toUpperCase();
        return '<div class="am-card">' +
          '<div style="display:flex;justify-content:space-between"><h3 style="margin:0">9-Line · ' + esc(n.author || 'JTAC') + '</h3>' +
          '<span class="am-badge am-badge--' + (st === 'ACTIVE' ? 'live' : 'stale') + '">' + esc(st) + '</span></div>' +
          '<ol style="margin:.55rem 0 0;padding-left:1.1rem;font-family:var(--am-mono);font-size:.72rem;line-height:1.55">' +
          nineItem(1, 'IP/BP', payload.ip || payload.line1 || payload.ip_bp) +
          nineItem(2, 'Heading', payload.heading || payload.line2) +
          nineItem(3, 'Distance', payload.distance || payload.line3) +
          nineItem(4, 'Elevation', payload.elevation || payload.line4) +
          nineItem(5, 'Target', payload.target || payload.line5) +
          nineItem(6, 'Location', payload.location || payload.line6 || gridLabel(n.pos_x, n.pos_y)) +
          nineItem(7, 'Mark', payload.mark || payload.line7) +
          nineItem(8, 'Friendlies', payload.friendlies || payload.line8) +
          nineItem(9, 'Egress', payload.egress || payload.line9) +
          '</ol>' +
          (payload.remarks || n.remarks ? '<p class="am-muted" style="margin:.45rem 0 0;font-size:.72rem">Remarks · ' + esc(payload.remarks || n.remarks) + '</p>' : '') +
          '<div class="am-actions">' +
          '<button type="button" data-center-xy="' + esc((n.pos_x || 0) + ',' + (n.pos_y || 0)) + '">Carte</button>' +
          '<button type="button" class="primary">Brief</button></div></div>';
      }).join('') : '<div class="am-card"><p class="am-muted" style="margin:0">Aucune 9-Line active.</p></div>') +
      '<div class="am-card"><h3>Codes laser</h3><div class="am-list">' +
      ((state.laserCodes || []).length ? state.laserCodes.map(function (c) {
        return '<div class="am-row"><strong style="font-family:var(--am-mono)">' + esc(c.code || c.laser_code || '—') + '</strong>' +
          '<span class="am-muted">' + esc(c.callsign || c.unit || '') + '</span></div>';
      }).join('') : '<p class="am-muted" style="margin:0">Aucun code.</p>') +
      '</div></div></div>';
  }

  function nineItem(num, label, val) {
    return '<li><span class="am-muted">' + num + '. ' + label + '</span> — ' + esc(val != null && val !== '' ? val : '—') + '</li>';
  }

  function renderAir() {
    var el = document.getElementById('am-screen-air');
    if (!el) return;
    var list = state.air || [];
    el.innerHTML =
      '<div class="am-pad"><div class="am-list">' +
      (list.length ? list.map(function (a) {
        var st = String(a.status || a.pilot_status || 'AVAILABLE').toUpperCase();
        return '<div class="am-card" style="padding:.65rem">' +
          '<div style="display:flex;justify-content:space-between;gap:.5rem">' +
          '<div><strong>' + esc(a.callsign || a.call_sign || '—') + '</strong>' +
          '<div class="am-muted" style="font-size:.72rem">' + esc(a.model || a.type || '—') + ' · ' + esc(a.mission || '—') + '</div></div>' +
          '<span class="am-badge am-badge--' + (st.indexOf('OFF') >= 0 ? 'off' : 'live') + '">' + esc(st) + '</span></div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;margin-top:.5rem;font-family:var(--am-mono);font-size:.68rem">' +
          '<span>ALT ' + esc(a.altitude != null ? a.altitude : '—') + '</span>' +
          '<span>SPD ' + esc(a.speed != null ? a.speed : '—') + '</span>' +
          '<span>FUEL ' + esc(a.fuel != null ? a.fuel : '—') + '</span>' +
          '<span>PLAY ' + esc(a.playtime != null ? a.playtime : '—') + '</span></div>' +
          '<div class="am-actions"><button type="button" class="primary" data-go="jtac">Assigner 9-Line</button></div></div>';
      }).join('') : '<p class="am-muted">Aucun aéronef.</p>') +
      '</div></div>';
  }

  function renderSigint() {
    var el = document.getElementById('am-screen-sigint');
    if (!el) return;
    var list = state.sigint || [];
    el.innerHTML =
      '<div class="am-pad">' +
      '<div class="am-card"><h3>Spectre</h3>' +
      '<div style="height:48px;display:flex;align-items:flex-end;gap:2px;padding:.25rem 0">' +
      Array.from({ length: 24 }).map(function (_, i) {
        var h = 20 + ((i * 17) % 28);
        return '<span style="flex:1;height:' + h + 'px;background:rgba(79,209,197,.35);border-radius:1px"></span>';
      }).join('') +
      '</div></div>' +
      '<div class="am-list">' +
      (list.length ? list.map(function (z) {
        return '<div class="am-card" style="padding:.6rem">' +
          '<div style="display:flex;justify-content:space-between"><strong>' + esc(z.label || z.id || 'Émission') + '</strong>' +
          '<span class="am-badge am-badge--hostile">' + esc(z.type || 'EW') + '</span></div>' +
          '<div style="font-family:var(--am-mono);font-size:.68rem;margin-top:.35rem;color:var(--am-muted)">' +
          esc(z.frequency || z.freq || '—') + ' · bearing ' + esc(z.bearing != null ? z.bearing : '—') +
          ' · conf ' + esc(z.confidence != null ? z.confidence : '—') + '</div>' +
          '<div class="am-actions"><button type="button" class="primary" data-center-xy="' +
          esc((z.pos_x || 0) + ',' + (z.pos_y || 0)) + '">Ouvrir zone d’incertitude</button></div></div>';
      }).join('') : '<p class="am-muted">Aucune émission.</p>') +
      '</div></div>';
  }

  function renderOrders() {
    var el = document.getElementById('am-screen-orders');
    if (!el) return;
    var list = state.orders || [];
    el.innerHTML = '<div class="am-pad"><div class="am-list">' +
      (list.length ? list.map(function (o) {
        return '<div class="am-row"><div><strong>' + esc(o.title || o.type || 'Ordre') + '</strong>' +
          '<small>' + esc(o.status || '') + ' · ' + esc(o.created_at || '') + '</small></div></div>';
      }).join('') : '<p class="am-muted">Aucun ordre.</p>') +
      '</div></div>';
  }

  function renderExplosives() {
    var el = document.getElementById('am-screen-explosives');
    if (!el) return;
    var list = state.explosives || [];
    el.innerHTML = '<div class="am-pad"><div class="am-list">' +
      (list.length ? list.map(function (c) {
        return '<div class="am-row"><div><strong>' + esc(c.label || c.charge_id || 'Charge') + '</strong>' +
          '<small>' + esc(c.status || '') + ' · ' + esc(gridLabel(c.pos_x, c.pos_y)) + '</small></div>' +
          '<span class="am-badge am-badge--' + (c.status === 'armed' ? 'stale' : 'off') + '">' + esc(String(c.status || '').toUpperCase()) + '</span></div>';
      }).join('') : '<p class="am-muted">Aucune charge.</p>') +
      '</div></div>';
  }

  /* ---------- Events ---------- */
  function bindEvents() {
    document.getElementById('am-drawer-open')?.addEventListener('click', openDrawer);
    document.getElementById('am-nav-plus')?.addEventListener('click', openDrawer);

    document.querySelectorAll('.am-bottom__btn[data-nav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var nav = btn.getAttribute('data-nav');
        if (nav === 'plus') return openDrawer();
        closeDrawer();
        setModule(nav);
      });
    });

    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-am-close-drawer]');
      if (t) return closeDrawer();
      t = e.target.closest('[data-am-close-sheet]');
      if (t) return closeSheet();
      t = e.target.closest('[data-go]');
      if (t) {
        closeDrawer();
        closeSheet();
        setModule(t.getAttribute('data-go'));
        return;
      }
      t = e.target.closest('[data-unit]');
      if (t) {
        var u = state.unitsById[t.getAttribute('data-unit')];
        if (u) {
          if (state.module !== 'sitac' && e.detail === 2) {
            setModule('sitac');
            setTimeout(function () { followUnit(u.id, true); showUnitSheet(u); }, 120);
          } else {
            showUnitSheet(u);
          }
        }
        return;
      }
      t = e.target.closest('[data-filter]');
      if (t) {
        var f = t.getAttribute('data-filter');
        state.filters[f] = !state.filters[f];
        renderSitacChrome();
        syncMapUnits();
        return;
      }
      t = e.target.closest('[data-channel]');
      if (t) {
        state.chatChannel = t.getAttribute('data-channel');
        renderChat(true);
        return;
      }
      t = e.target.closest('[data-bft-filter]');
      if (t) {
        state.bftFilter = t.getAttribute('data-bft-filter');
        renderBft();
        return;
      }
      t = e.target.closest('[data-center-unit]');
      if (t) {
        var uid = t.getAttribute('data-center-unit');
        var uu = state.unitsById[uid];
        closeSheet();
        setModule('sitac');
        setTimeout(function () {
          if (uu) centerOn(uu.pos_x, uu.pos_y);
          showUnitSheet(uu);
        }, 100);
        return;
      }
      t = e.target.closest('[data-follow-unit]');
      if (t) {
        followUnit(t.getAttribute('data-follow-unit'), true);
        closeSheet();
        setModule('sitac');
        return;
      }
      t = e.target.closest('[data-center-xy]');
      if (t) {
        e.preventDefault();
        var parts = String(t.getAttribute('data-center-xy') || '').split(',');
        closeSheet();
        setModule('sitac');
        setTimeout(function () { centerOn(parts[0], parts[1]); }, 100);
        return;
      }
      if (e.target.id === 'am-center' || e.target.closest('#am-center')) {
        var first = liveUnits()[0];
        if (first) centerOn(first.pos_x, first.pos_y);
        else if (state.map) {
          var c = MAP_CFG.center || [15000, 15000];
          centerOn(c[0] != null ? c[0] : c.x, c[1] != null ? c[1] : c.y);
        }
        return;
      }
      if (e.target.id === 'am-layers' || e.target.closest('#am-layers')) {
        openDrawer();
        return;
      }
      if (e.target.id === 'am-ping-btn' || e.target.closest('#am-ping-btn') || e.target.id === 'am-new-ping') {
        showPingCreate();
        return;
      }
      if (e.target.id === 'am-ping-submit') {
        submitPing().catch(function () {});
        return;
      }
      t = e.target.closest('[data-ping-id]');
      if (t) {
        var ping = (state.pings || []).find(function (p) { return String(p.id) === t.getAttribute('data-ping-id'); });
        if (ping) {
          openSheet('<h3 style="margin:0 0 .5rem">Ping</h3><p>' + esc(ping.message || '') + '</p>' +
            '<p class="am-muted" style="font-family:var(--am-mono)">' + esc(ping.author || '') + ' · ' + esc(gridLabel(ping.pos_x, ping.pos_y)) + '</p>' +
            '<div class="am-actions">' +
            '<button type="button" class="primary" data-center-xy="' + esc(ping.pos_x + ',' + ping.pos_y) + '">Carte</button>' +
            '<button type="button" data-am-close-sheet>Fermer</button></div>');
        }
      }
    });

    document.addEventListener('submit', function (e) {
      if (e.target && e.target.id === 'am-chat-form') {
        e.preventDefault();
        var input = document.getElementById('am-chat-input');
        var val = input ? input.value : '';
        if (!String(val || '').trim()) return;
        if (input) {
          input.value = '';
          resizeChatInput(input);
        }
        sendChat(val).then(function () {
          var again = document.getElementById('am-chat-input');
          if (again) again.focus();
        }).catch(function () {
          var again = document.getElementById('am-chat-input');
          if (again && !String(again.value || '').trim()) {
            again.value = val;
            state.chatDraft = val;
            resizeChatInput(again);
          }
        });
      }
    });

    document.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'am-chat-input') {
        state.chatDraft = e.target.value;
        resizeChatInput(e.target);
      }
    });

    document.addEventListener('visibilitychange', function () {
      updateLiveBadge();
      if (!document.hidden) schedulePolls();
    });

    setInterval(function () {
      var z = document.getElementById('am-zulu');
      if (z) z.textContent = zuluNow().slice(0, 5) + 'Z';
      updateLiveBadge();
    }, 1000);
  }

  function init() {
    renderDrawer();
    bindEvents();
    setModule(state.module, false);
    updateLiveBadge();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
