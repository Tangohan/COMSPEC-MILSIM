/* COMSPEC — moteur carte opérationnelle partagé (TACMAP, hooks Overwatch) */
(function (global) {
  'use strict';

  var WORLD_SCALE = 30000;
  var ALTIS_WORLD_SIZE = 30720;
  var ALTIS_FACTOR = 212 / ALTIS_WORLD_SIZE;
  var ALTIS_CENTER = [ALTIS_WORLD_SIZE / 2, ALTIS_WORLD_SIZE / 2];
  var ALTIS_BOUNDS = [[0, 0], [ALTIS_WORLD_SIZE, ALTIS_WORLD_SIZE]];

  function buildArmaConfig(raw) {
    if (!raw || !raw.tilePattern) return null;
    var isAltis = (raw.slug || (raw.config && raw.config.title) || '').toString().toLowerCase() === 'altis';
    var crsOpt = raw.crs || {};
    var tileWidth = crsOpt.tileWidth != null ? crsOpt.tileWidth : 212;
    var factorx = isAltis ? ALTIS_FACTOR : (crsOpt.factorx != null ? crsOpt.factorx : ALTIS_FACTOR);
    var factory = isAltis ? ALTIS_FACTOR : (crsOpt.factory != null ? crsOpt.factory : ALTIS_FACTOR);
    var CRS = typeof global.MGRS_CRS === 'function' ? global.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
    var center = isAltis ? ALTIS_CENTER : (Array.isArray(raw.center) ? raw.center : (raw.config && Array.isArray(raw.config.center) ? raw.config.center : ALTIS_CENTER));
    var bounds = isAltis ? ALTIS_BOUNDS : (raw.bounds || (raw.config && raw.config.bounds) || null);
    return {
      CRS: CRS,
      tilePattern: raw.tilePattern,
      minZoom: raw.minZoom != null ? raw.minZoom : 0,
      maxZoom: raw.maxZoom != null ? raw.maxZoom : 6,
      defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 3,
      attribution: raw.attribution || '&copy; Bohemia Interactive',
      tileSize: raw.tileSize != null ? raw.tileSize : 212,
      center: center,
      bounds: bounds,
    };
  }

  function buildImageConfig(raw) {
    if (!raw || !raw.imageUrl) return null;
    var w = parseInt(raw.imageWidth, 10) || 1000;
    var h = parseInt(raw.imageHeight, 10) || 1000;
    var bounds = Array.isArray(raw.bounds) && raw.bounds.length === 2
      ? raw.bounds
      : [[0, 0], [h, w]];
    var center = Array.isArray(raw.center) ? raw.center : [h / 2, w / 2];
    return {
      CRS: L.CRS.Simple,
      imageUrl: raw.imageUrl,
      minZoom: raw.minZoom != null ? raw.minZoom : -2,
      maxZoom: raw.maxZoom != null ? raw.maxZoom : 4,
      defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 0,
      attribution: raw.attribution || 'Fond personnalisé',
      center: center,
      bounds: bounds,
      mapId: raw.mapId,
      slug: raw.slug,
      label: raw.label,
    };
  }

  function mapKindFromSlug(slug, mapsConfigs) {
    if (slug === 'world') return 'world';
    var c = mapsConfigs && mapsConfigs[slug];
    if (c && (c.type === 'image' || c.imageUrl)) return 'image';
    return 'arma';
  }

  function affiliationColor(aff) {
    var a = (aff || '').toUpperCase();
    if (a === 'ENEMY' || a === 'HOSTILE') return '#dc2626';
    if (a === 'UNKNOWN' || a === 'SUSPECT') return '#eab308';
    if (a === 'NEUTRAL') return '#22c55e';
    return '#3b82f6';
  }

  /** Préfixe …/api déjà présent (portail) ou origine seule (clients externes). */
  function tacticalMapShapesUrl(apiBase) {
    var base = String(apiBase || '').replace(/\/$/, '');
    if (base.length >= 4 && base.slice(-4) === '/api') {
      return base + '/map-shapes';
    }
    return base + '/api/map-shapes';
  }

  /**
   * @param {object} opts
   * @param {string} opts.apiBase
   * @param {number} opts.mapId
   * @param {string} [opts.missionId]
   * @param {L.Map} opts.map
   * @param {L.LayerGroup} opts.layerGroup
   * @param {boolean} opts.isWorld
   */
  function renderMapShapes(opts) {
    if (!opts || !opts.layerGroup || !opts.map) return;
    var url = tacticalMapShapesUrl(opts.apiBase) + '?mapId=' + encodeURIComponent(opts.mapId);
    if (opts.missionId) url += '&missionId=' + encodeURIComponent(opts.missionId);
    fetch(url, { credentials: opts.credentials || 'include' })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        if (!opts.layerGroup || !opts.map) return;
        opts.layerGroup.clearLayers();
        (rows || []).forEach(function (s) {
          addOneMapShape(s, opts.layerGroup, opts.isWorld);
        });
      })
      .catch(function () {});
  }

  function addOneMapShape(s, layerGroup, isWorld) {
    var geom = s.geometry || {};
    var type = (s.type || '').toString().toUpperCase();
    var color = s.color || '#3388ff';
    var fillOp = s.fill_opacity != null ? parseFloat(s.fill_opacity) : 0.15;
    var weight = s.stroke != null ? parseInt(s.stroke, 10) : 2;
    var label = (s.label || type || 'Tracé').toString();

    if (geom.center && geom.radius != null) {
      var lat, lng, radius;
      if (isWorld) {
        lat = geom.center[0] / WORLD_SCALE;
        lng = geom.center[1] / WORLD_SCALE;
        radius = Math.min((geom.radius / WORLD_SCALE) * 111000, 50000);
      } else {
        lat = geom.center[1];
        lng = geom.center[0];
        radius = geom.radius;
      }
      L.circle([lat, lng], { radius: radius, color: color, fillOpacity: fillOp, weight: weight })
        .bindPopup(label)
        .addTo(layerGroup);
      return;
    }

    var pts = geom.points || geom.vertices || geom.path;
    if (Array.isArray(pts) && pts.length >= 2) {
      var latlngs = pts.map(function (p) {
        var x = p[0];
        var y = p[1];
        if (isWorld) return [x / WORLD_SCALE, y / WORLD_SCALE];
        return [y, x];
      });
      if (type === 'POLYGON' || geom.closed) {
        L.polygon(latlngs, { color: color, fillOpacity: fillOp, weight: weight }).bindPopup(label).addTo(layerGroup);
      } else {
        L.polyline(latlngs, { color: color, weight: weight }).bindPopup(label).addTo(layerGroup);
      }
    }
  }

  function parseMarkerDataPos(pos, isWorld) {
    if (!pos || !pos.length) return null;
    var x, y;
    if (Array.isArray(pos[0])) {
      x = pos[0][0];
      y = pos[0][1];
    } else {
      x = pos[0];
      y = pos[1];
    }
    if (x == null || y == null || isNaN(x) || isNaN(y)) return null;
    if (isWorld) {
      return L.latLng(x / WORLD_SCALE, y / WORLD_SCALE);
    }
    return L.latLng(y, x);
  }

  function renderAtakMarkers(layerGroup, list, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (list || []).forEach(function (m) {
      var id = m.id;
      if (id == null) return;
      var raw = m.markerData;
      var data = typeof raw === 'string' ? (function () { try { return JSON.parse(raw || '{}'); } catch (e) { return {}; } })() : (raw || {});
      var latlng = parseMarkerDataPos(data.pos, isWorld);
      if (!latlng) return;
      var icon = L.divIcon({
        className: 'comspec-atak-marker',
        html: '<span style="width:11px;height:11px;border-radius:50%;background:#10b981;border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.2);"></span>',
        iconSize: [15, 15],
        iconAnchor: [7, 7],
      });
      L.marker(latlng, { icon: icon }).bindPopup((data.text || data.label || 'Repère') + '').addTo(layerGroup);
    });
  }

  function renderPingsLayer(layerGroup, rows, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (rows || []).forEach(function (p) {
      var x = parseFloat(p.pos_x);
      var y = parseFloat(p.pos_y);
      if (isNaN(x) || isNaN(y)) return;
      var latlng = isWorld ? L.latLng(x / WORLD_SCALE, y / WORLD_SCALE) : L.latLng(y, x);
      var icon = L.divIcon({
        className: 'comspec-ping-marker',
        html: '<span style="font-size:14px;">📍</span>',
        iconSize: [20, 20],
        iconAnchor: [10, 10],
      });
      L.marker(latlng, { icon: icon })
        .bindPopup('<strong>' + (p.author || '—') + '</strong><br/>' + (p.message || '').replace(/</g, '&lt;'))
        .addTo(layerGroup);
    });
  }

  function renderSigintLayer(layerGroup, zones, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (zones || []).forEach(function (z, i) {
      var posX = z.pos_x != null ? z.pos_x : 0;
      var posY = z.pos_y != null ? z.pos_y : 0;
      var radius = z.radius != null ? z.radius : 200;
      var latlng = isWorld ? L.latLng(posX / WORLD_SCALE, posY / WORLD_SCALE) : L.latLng(posY, posX);
      var r = isWorld ? Math.min((radius / WORLD_SCALE) * 111000, 50000) : radius;
      L.circle(latlng, { radius: r, color: '#b91c1c', fillOpacity: 0.12, weight: 2 })
        .bindPopup('Veille : zone d’incertitude (' + (z.reports || 0) + ' signalement(s))')
        .addTo(layerGroup);
    });
  }

  function renderIntelFusedMarkers(layerGroup, reports, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (reports || []).forEach(function (r) {
      var lat = isWorld ? (r.pos_y != null ? r.pos_y : 0) / WORLD_SCALE : (r.pos_y != null ? r.pos_y : 0);
      var lng = isWorld ? (r.pos_x != null ? r.pos_x : 0) / WORLD_SCALE : (r.pos_x != null ? r.pos_x : 0);
      var status = r.status || 'TEMPORARY';
      var col = status === 'CONFIRMED' ? '#dc2626' : status === 'CORROBORATED' ? '#d97706' : '#eab308';
      L.circleMarker([lat, lng], { radius: 9, color: col, fillOpacity: 0.85, weight: 2 })
        .bindPopup((r.target_type || 'Indice') + ' — ' + status)
        .addTo(layerGroup);
    });
  }

  function renderAirAssetsLayer(layerGroup, assets, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    var nato = window.NatoSidcIcons;
    (assets || []).forEach(function (a) {
      if (a.pos_x == null || a.pos_y == null) return;
      var latlng = isWorld ? L.latLng(a.pos_x / WORLD_SCALE, a.pos_y / WORLD_SCALE) : L.latLng(a.pos_y, a.pos_x);
      var side = (a.side || 'WEST').toUpperCase();
      var aff = 'friend';
      if (side === 'EAST') aff = 'hostile';
      else if (side === 'GUER' || side === 'CIV') aff = 'unknown';
      var icon = nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, {
            affiliation: aff,
            aircraftType: a.aircraft_type || 'plane',
            role: a.model || '',
            callSign: a.callsign || '',
            showLabel: true,
            size: 34,
          })
        : L.divIcon({
            className: 'comspec-air-marker',
            html: '<span style="color:#3b82f6;font-size:15px;font-weight:bold;">▲</span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
          });
      var airMarker = L.marker(latlng, { icon: icon });
      if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindAir) {
        window.ATAKUnitPopup.bindAir(airMarker, a);
      } else {
        airMarker.bindPopup('<strong>' + (a.callsign || '—') + '</strong><br/>' + (a.model || '') + '<br/>' + (a.status || ''));
      }
      airMarker.addTo(layerGroup);
    });
  }

  function statusLabelFr(status) {
    var s = (status || '').toLowerCase();
    if (s === 'linked') return 'Synchronisé';
    if (s === 'delayed') return 'Retard';
    if (s === 'offline') return 'Hors ligne';
    return status || '—';
  }

  function affiliationLabelFr(a) {
    var x = (a || '').toString().toUpperCase();
    if (x === 'FRIEND' || x === 'FRIENDLY') return 'Allié';
    if (x === 'ENEMY' || x === 'HOSTILE') return 'Hostile';
    if (x === 'NEUTRAL') return 'Neutre';
    if (x === 'UNKNOWN' || x === 'SUSPECT') return 'Incertain';
    return a ? String(a) : '';
  }

  function formatTimeAgo(iso) {
    if (!iso) return '—';
    var t = new Date(iso).getTime();
    if (isNaN(t)) return '—';
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 60) return 'Il y a ' + sec + ' s';
    if (sec < 3600) return 'Il y a ' + Math.floor(sec / 60) + ' min';
    return new Date(iso).toLocaleString('fr-FR');
  }

  /**
   * @param {object} cfg
   */
  function initTacmap(cfg) {
    var ctx = cfg.context;
    var mapsConfigs = cfg.mapsConfigs || {};
    var workspaces = cfg.workspaces || [];
    var apiBase = ctx.apiBase;
    var syncMs = ctx.syncIntervalMs || 8000;
    var tenantId = ctx.tenantId || 0;
    var els = cfg.els || {};

    var state = {
      currentMapId: ctx.defaultMapId,
      currentMapSlug: ctx.defaultMapSlug,
      currentMapType: 'arma',
      currentMissionId: ctx.defaultMissionId,
      syncStatus: 'idle',
      lastSyncAt: null,
      unitsCount: 0,
      layers: {
        units: true,
        danger: true,
        drawings: true,
        markers: true,
        pings: true,
        sigint: false,
        intel: false,
        air: true,
      },
    };

    var map = null;
    var currentBaseLayer = null;
    var layerGroups = {};
    var intervals = [];
    var mapIntervals = [];
    var lastUnits = [];
    var selectedUnitId = null;
    var syncLock = false;

    function getEl(id) {
      return typeof id === 'string' ? document.getElementById(id) : id;
    }

    function getMissionId() {
      return 'mission_' + Number(tenantId) + '_map_' + Number(state.currentMapId);
    }

    function applyLayerVisibility() {
      if (!map) return;
      var Ls = state.layers;
      [['units', layerGroups.units], ['danger', layerGroups.dangerZones], ['drawings', layerGroups.drawings],
        ['markers', layerGroups.markers], ['pings', layerGroups.pings], ['sigint', layerGroups.sigint],
        ['intel', layerGroups.intel], ['air', layerGroups.air]].forEach(function (pair) {
        var key = pair[0];
        var lg = pair[1];
        if (!lg) return;
        try {
          if (Ls[key]) map.addLayer(lg);
          else map.removeLayer(lg);
        } catch (e) {}
      });
    }

    function loadDangerZones() {
      if (!layerGroups.dangerZones || (state.currentMapType !== 'arma' && state.currentMapType !== 'image')) return;
      fetch(apiBase + '/danger-zones?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (zones) {
          layerGroups.dangerZones.clearLayers();
          (zones || []).forEach(function (z) {
            var geom = z.geometry_json || z.geometry;
            var type = z.geometry_type || 'CIRCLE';
            if (type === 'CIRCLE' && geom && geom.center && geom.radius) {
              var lat = geom.center[1];
              var lng = geom.center[0];
              var radius = geom.radius;
              L.circle([lat, lng], { radius: radius, color: z.color || '#ef4444', fillOpacity: z.fill_opacity || 0.25 })
                .bindPopup(z.label || z.zone_type || 'Zone')
                .addTo(layerGroups.dangerZones);
            }
          });
        })
        .catch(function () {});
    }

    function refreshSecondaryLayers() {
      if (state.currentMapType !== 'arma' && state.currentMapType !== 'image') return;
      var isWorld = false;
      if (state.layers.drawings) {
        renderMapShapes({
          apiBase: apiBase,
          mapId: state.currentMapId,
          missionId: getMissionId(),
          map: map,
          layerGroup: layerGroups.drawings,
          isWorld: isWorld,
          credentials: 'include',
        });
      }
      if (state.layers.markers) {
        fetch(apiBase + '/markers?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (list) { renderAtakMarkers(layerGroups.markers, list, isWorld); })
          .catch(function () {});
      }
      if (state.layers.pings) {
        fetch(apiBase + '/pings?mapId=' + encodeURIComponent(state.currentMapId) + '&limit=80', { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (rows) { renderPingsLayer(layerGroups.pings, rows, isWorld); })
          .catch(function () {});
      }
      if (state.layers.sigint) {
        fetch(apiBase + '/atak/sigint/zones?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (zones) { renderSigintLayer(layerGroups.sigint, zones, isWorld); })
          .catch(function () {});
      }
      if (state.layers.intel) {
        fetch(apiBase + '/intel/fused?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (reports) { renderIntelFusedMarkers(layerGroups.intel, reports, isWorld); })
          .catch(function () {});
      }
      if (state.layers.air) {
        fetch(apiBase + '/atak/air-assets?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (assets) { renderAirAssetsLayer(layerGroups.air, assets, isWorld); })
          .catch(function () {});
      }
      if (state.layers.danger) loadDangerZones();
    }

    function updateSyncBadge() {
      var el = getEl(els.syncBadge);
      if (!el) return;
      if (state.currentMapType === 'world') {
        el.textContent = 'Vue monde';
        el.className = 'px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-[0.2em] border-slate-200 bg-slate-50 text-slate-600';
        return;
      }
      if (state.syncStatus === 'ok') {
        el.textContent = 'Carte synchronisée';
        el.className = 'px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-[0.2em] border-emerald-200 bg-emerald-50 text-emerald-700';
      } else if (state.syncStatus === 'error') {
        el.textContent = 'Problème de liaison';
        el.className = 'px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-[0.2em] border-red-200 bg-red-50 text-red-700';
      } else if (state.syncStatus === 'syncing') {
        el.textContent = 'Mise à jour…';
        el.className = 'px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-[0.2em] border-amber-200 bg-amber-50 text-amber-800';
      } else {
        el.textContent = 'En attente';
        el.className = 'px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-[0.2em] border-slate-200 bg-slate-50 text-slate-600';
      }
    }

    function updateTheatreLabel() {
      var el = getEl(els.theatreLabel);
      if (!el) return;
      var slug = state.currentMapSlug;
      var c = mapsConfigs[slug];
      el.textContent = c && c.label ? c.label : (slug === 'world' ? 'Monde' : slug || '—');
    }

    function updateUnitCountEl() {
      var el = getEl(els.unitCount);
      if (el) el.textContent = (state.currentMapType === 'arma' || state.currentMapType === 'image') ? String(state.unitsCount) : '—';
      el = getEl(els.syncMeta);
      if (el && state.lastSyncAt && (state.currentMapType === 'arma' || state.currentMapType === 'image')) {
        el.textContent = new Date(state.lastSyncAt).toLocaleTimeString('fr-FR', { hour12: false }) + ' · ' + state.unitsCount + ' position(s)';
      } else if (el) el.textContent = state.currentMapType === 'world' ? 'Vue d’ensemble' : '—';
    }

    function selectUnit(u) {
      if (!u) {
        selectedUnitId = null;
        renderDetail(null);
        renderRosterHighlight();
        return;
      }
      selectedUnitId = u.id;
      renderDetail(u);
      renderRosterHighlight();
      var gridRef = (u.grid_ref || '').trim().split(/\s+/);
      var x = parseFloat(gridRef[0]);
      var y = parseFloat(gridRef[1]);
      if (!isNaN(x) && !isNaN(y) && map && (state.currentMapType === 'arma' || state.currentMapType === 'image')) {
        map.setView([y, x], Math.max(map.getZoom(), 4));
      }
    }

    function renderDetail(u) {
      var root = getEl(els.detailRoot);
      if (!root) return;
      if (!u) {
        root.innerHTML = '<p class="text-sm text-slate-500">Sélectionnez une unité dans la liste ou sur la carte.</p>';
        return;
      }
      var extra = {};
      try {
        if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
        else if (u.extra && typeof u.extra === 'object') extra = u.extra;
      } catch (e) {}
      var aff = extra.affiliation || extra.affil || u.affiliation || '';
      var affLabel = aff ? affiliationLabelFr(aff) : '';
      var healthRaw = extra.health != null ? extra.health : u.health;
      var healthLabel = '';
      if (window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr) {
        healthLabel = window.ATAKUnitPopup.healthLabelFr(healthRaw);
      } else if (healthRaw) {
        healthLabel = String(healthRaw);
      }
      var radio = extra.radio_freq != null && extra.radio_freq !== ''
        ? String(extra.radio_freq)
        : (extra.radio != null && extra.radio !== '' ? String(extra.radio) : '');
      var fuel = extra.fuel !== undefined && extra.fuel !== null && extra.fuel !== ''
        ? String(extra.fuel) + (String(extra.fuel).indexOf('%') >= 0 ? '' : ' %')
        : '';
      var ammo = extra.ammo != null && extra.ammo !== '' && String(extra.ammo).toLowerCase() !== 'n/a'
        ? String(extra.ammo)
        : '';
      var parent = extra.parent || extra.parent_callsign || extra.group || extra.groupe || '';
      root.innerHTML =
        '<div class="space-y-4">' +
        '<div><p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 mb-1">Indicatif</p><p class="text-xl font-black text-slate-950">' + escapeHtml(u.call_sign || '—') + '</p></div>' +
        '<dl class="space-y-2 text-sm">' +
        rowDl('Rôle', u.role || '—') +
        rowDl('Grille (approx.)', u.grid_ref || '—') +
        rowDl('Cap', u.heading != null ? String(u.heading) + '°' : '—') +
        rowDl('Liaison', statusLabelFr(u.status)) +
        (healthLabel ? rowDl('État', healthLabel) : '') +
        rowDl('Dernière mise à jour', formatTimeAgo(u.updated_at)) +
        (affLabel ? rowDl('Affiliation', affLabel) : '') +
        (parent ? rowDl('Groupe', parent) : '') +
        (radio ? rowDl('Radio', radio) : '') +
        (fuel ? rowDl('Carburant', fuel) : '') +
        (ammo ? rowDl('Munitions', ammo) : '') +
        '</dl>' +
        (extra.notes ? '<p class="text-xs text-slate-600">' + escapeHtml(String(extra.notes)) + '</p>' : '') +
        '</div>';
    }

    function rowDl(dt, dd) {
      return '<div class="flex justify-between gap-3"><dt class="text-slate-500">' + escapeHtml(dt) + '</dt><dd class="font-bold text-slate-950 text-right">' + escapeHtml(dd) + '</dd></div>';
    }

    function escapeHtml(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderRosterHighlight() {
      var roster = getEl(els.roster);
      if (!roster) return;
      roster.querySelectorAll('[data-unit-id]').forEach(function (node) {
        var id = node.getAttribute('data-unit-id');
        if (selectedUnitId && String(selectedUnitId) === id) {
          node.classList.add('ring-2', 'ring-blue-500', 'border-blue-300');
        } else {
          node.classList.remove('ring-2', 'ring-blue-500', 'border-blue-300');
        }
      });
    }

    function filterRosterQuery(q) {
      q = (q || '').toUpperCase().trim();
      var roster = getEl(els.roster);
      if (!roster) return;
      roster.querySelectorAll('[data-unit-id]').forEach(function (node) {
        var cs = (node.getAttribute('data-callsign') || '').toUpperCase();
        node.style.display = !q || cs.indexOf(q) >= 0 ? '' : 'none';
      });
    }

    function renderRosterAndTable(units) {
      lastUnits = units || [];
      var roster = getEl(els.roster);
      var tbody = getEl(els.tableBody);
      if (roster) {
        if (!lastUnits.length) {
          roster.innerHTML = '<p class="text-sm text-slate-500 px-2">Aucune position remontée pour ce théâtre. Vérifiez la liaison en jeu ou l’outil Overwatch.</p>';
        } else {
          roster.innerHTML = lastUnits.map(function (u) {
            var st = statusLabelFr(u.status);
            return '<button type="button" data-unit-id="' + u.id + '" data-callsign="' + escapeHtml(u.call_sign || '') + '" class="w-full text-left rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4 hover:bg-white transition">' +
              '<div class="flex justify-between gap-2"><span class="text-[10px] font-black uppercase text-slate-500">' + escapeHtml(u.call_sign || '—') + '</span><span class="text-[9px] font-black uppercase text-emerald-700">' + escapeHtml(st) + '</span></div>' +
              '<p class="text-sm font-bold text-slate-900 mt-1">' + escapeHtml(u.role || '—') + '</p>' +
              '<p class="text-xs text-slate-500 mt-1">Grille ' + escapeHtml(u.grid_ref || '—') + '</p></button>';
          }).join('');
          roster.querySelectorAll('[data-unit-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var id = parseInt(btn.getAttribute('data-unit-id'), 10);
              var u = lastUnits.find(function (x) { return x.id === id; });
              selectUnit(u);
            });
          });
        }
        renderRosterHighlight();
      }
      if (tbody) {
        if (!lastUnits.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Aucune unité à afficher.</td></tr>';
        } else {
          tbody.innerHTML = lastUnits.map(function (u) {
            return '<tr class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer" data-unit-id="' + u.id + '">' +
              '<td class="px-6 py-4 font-bold text-blue-700">' + escapeHtml(u.call_sign || '—') + '</td>' +
              '<td class="px-6 py-4">' + escapeHtml(u.role || '—') + '</td>' +
              '<td class="px-6 py-4 text-center"><span class="text-xs font-bold">' + escapeHtml(statusLabelFr(u.status)) + '</span></td>' +
              '<td class="px-6 py-4 text-center text-sm">' + escapeHtml(u.heading != null ? String(u.heading) + '°' : '—') + '</td>' +
              '<td class="px-6 py-4 text-right font-mono text-xs text-slate-500">' + escapeHtml(u.grid_ref || '—') + '</td></tr>';
          }).join('');
          tbody.querySelectorAll('tr[data-unit-id]').forEach(function (tr) {
            tr.addEventListener('click', function () {
              var id = parseInt(tr.getAttribute('data-unit-id'), 10);
              selectUnit(lastUnits.find(function (x) { return x.id === id; }));
            });
          });
        }
      }
      filterRosterQuery(getEl(els.rosterSearch) && getEl(els.rosterSearch).value);
    }

    function renderUnitsOnMap(units) {
      if (!layerGroups.units || !map) return;
      layerGroups.units.clearLayers();
      var nato = window.NatoSidcIcons;
      (units || []).forEach(function (u) {
        var gridRef = (u.grid_ref || '').trim().split(/\s+/);
        var x = parseFloat(gridRef[0]);
        var y = parseFloat(gridRef[1]);
        if (isNaN(x) || isNaN(y)) {
          x = u.pos_x != null ? parseFloat(u.pos_x) : NaN;
          y = u.pos_y != null ? parseFloat(u.pos_y) : NaN;
        }
        if (isNaN(x) || isNaN(y)) return;
        var latlng = L.latLng(y, x);
        var extra = {};
        try {
          if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
          else if (u.extra && typeof u.extra === 'object') extra = u.extra;
        } catch (e) {}
        var aff = extra.affiliation || extra.affil || u.affiliation || 'friend';
        var icon = nato && nato.leafletDivIcon
          ? nato.leafletDivIcon(L, {
              affiliation: aff,
              role: u.role || extra.role || '',
              callSign: u.call_sign || '',
              heading: u.heading,
              showLabel: true,
              size: 34,
            })
          : L.divIcon({
              className: 'tacmap-unit-icon',
              html: '<span style="display:inline-block;padding:2px 6px;background:' + affiliationColor(aff) + ';color:#fff;font-size:10px;">' + escapeHtml(u.call_sign || '?') + '</span>',
              iconSize: [80, 24],
              iconAnchor: [40, 12],
            });
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: 400 });
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
          window.ATAKUnitPopup.bindUnit(marker, u);
        } else {
          marker.bindPopup('<strong>' + escapeHtml(u.call_sign || '—') + '</strong><br/>' + escapeHtml(u.role || ''));
        }
        marker.on('click', function () { selectUnit(u); });
        marker.addTo(layerGroups.units);
      });
    }

    function syncUnits() {
      if ((state.currentMapType !== 'arma' && state.currentMapType !== 'image') || !state.layers.units) return;
      if (syncLock) return;
      syncLock = true;
      state.syncStatus = 'syncing';
      updateSyncBadge();
      fetch(apiBase + '/units?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
          syncLock = false;
          state.syncStatus = 'ok';
          state.lastSyncAt = Date.now();
          state.unitsCount = (rows && rows.length) ? rows.length : 0;
          renderUnitsOnMap(rows || []);
          renderRosterAndTable(rows || []);
          updateUnitCountEl();
          updateSyncBadge();
        })
        .catch(function () {
          syncLock = false;
          state.syncStatus = 'error';
          updateSyncBadge();
        });
    }

    function applyBaseLayer(slug) {
      var container = getEl(cfg.containerId);
      if (!container) return;
      var kind = mapKindFromSlug(slug, mapsConfigs);
      var isWorld = kind === 'world';
      var isImage = kind === 'image';

      if (map) {
        if (currentBaseLayer) {
          map.removeLayer(currentBaseLayer);
          currentBaseLayer = null;
        }
        var needRecreate = state.currentMapType !== kind;
        if (needRecreate) {
          mapIntervals.forEach(function (id) { clearInterval(id); });
          mapIntervals = [];
          map.remove();
          map = null;
          layerGroups = {};
        }
      }

      if (!map) {
        if (isWorld) {
          map = L.map(container, { minZoom: 2, maxZoom: 18 }).setView([0.5, 0.5], 4);
        } else if (isImage) {
          var ic0 = mapsConfigs[slug] ? buildImageConfig(mapsConfigs[slug]) : null;
          if (!ic0) return;
          map = L.map(container, { minZoom: ic0.minZoom, maxZoom: ic0.maxZoom, crs: ic0.CRS });
          map.setView(ic0.center, ic0.defaultZoom);
          if (ic0.bounds && ic0.bounds.length === 2) {
            map.setMaxBounds(L.latLngBounds(L.latLng(ic0.bounds[0][0], ic0.bounds[0][1]), L.latLng(ic0.bounds[1][0], ic0.bounds[1][1])));
          }
        } else {
          var ac = mapsConfigs[slug] ? buildArmaConfig(mapsConfigs[slug]) : null;
          if (!ac) return;
          map = L.map(container, { minZoom: ac.minZoom, maxZoom: ac.maxZoom, crs: ac.CRS });
          map.setView(ac.center, ac.defaultZoom);
          if (ac.bounds && ac.bounds.length === 2) {
            map.setMaxBounds(L.latLngBounds(L.latLng(ac.bounds[0][0], ac.bounds[0][1]), L.latLng(ac.bounds[1][0], ac.bounds[1][1])));
          }
        }
        layerGroups.units = L.layerGroup();
        layerGroups.dangerZones = L.layerGroup();
        layerGroups.drawings = L.layerGroup();
        layerGroups.markers = L.layerGroup();
        layerGroups.pings = L.layerGroup();
        layerGroups.sigint = L.layerGroup();
        layerGroups.intel = L.layerGroup();
        layerGroups.air = L.layerGroup();
        applyLayerVisibility();
      }

      if (isWorld) {
        currentBaseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' });
        currentBaseLayer.addTo(map);
        map.setView([0.5, 0.5], 4);
        state.currentMapType = 'world';
      } else if (isImage) {
        var ic = mapsConfigs[slug] ? buildImageConfig(mapsConfigs[slug]) : null;
        if (!ic) return;
        var ibounds = L.latLngBounds(L.latLng(ic.bounds[0][0], ic.bounds[0][1]), L.latLng(ic.bounds[1][0], ic.bounds[1][1]));
        currentBaseLayer = L.imageOverlay(ic.imageUrl, ibounds, { opacity: 1, interactive: false });
        currentBaseLayer.addTo(map);
        map.fitBounds(ibounds);
        state.currentMapType = 'image';
      } else {
        var cfgM = mapsConfigs[slug] ? buildArmaConfig(mapsConfigs[slug]) : null;
        if (!cfgM) return;
        currentBaseLayer = L.tileLayer(cfgM.tilePattern, { attribution: cfgM.attribution, tileSize: cfgM.tileSize });
        currentBaseLayer.addTo(map);
        map.setView(cfgM.center, cfgM.defaultZoom);
        if (cfgM.bounds && cfgM.bounds.length === 2) {
          map.setMaxBounds(L.latLngBounds(L.latLng(cfgM.bounds[0][0], cfgM.bounds[0][1]), L.latLng(cfgM.bounds[1][0], cfgM.bounds[1][1])));
        }
        state.currentMapType = 'arma';
      }
      state.currentMapSlug = isWorld ? 'world' : slug;
      updateTheatreLabel();
      updateSyncBadge();

      mapIntervals.forEach(function (id) { clearInterval(id); });
      mapIntervals = [];
      if (!isWorld) {
        mapIntervals.push(setInterval(syncUnits, syncMs));
        mapIntervals.push(setInterval(refreshSecondaryLayers, Math.max(syncMs, 12000)));
        syncUnits();
        refreshSecondaryLayers();
      } else {
        state.unitsCount = 0;
        if (layerGroups.units) layerGroups.units.clearLayers();
        renderRosterAndTable([]);
        updateUnitCountEl();
      }
      applyLayerVisibility();
    }

    function setWorkspace(mapId) {
      mapId = parseInt(mapId, 10);
      var ws = workspaces.find(function (w) { return w.mapId === mapId; });
      state.currentMapId = mapId;
      state.currentMissionId = getMissionId();
      state.currentMapSlug = ws ? ws.slug : state.currentMapSlug;
      var selMap = getEl(els.mapSelect);
      if (selMap && ws && selMap.value !== ws.slug) {
        selMap.value = ws.slug;
        applyBaseLayer(ws.slug);
        } else if ((state.currentMapType === 'arma' || state.currentMapType === 'image') && ws) {
          applyBaseLayer(ws.slug);
        } else {
          if (ws) state.currentMapSlug = ws.slug;
          state.currentMissionId = getMissionId();
          refreshSecondaryLayers();
          syncUnits();
        }
        updateTheatreLabel();
      }

    function setMap(slug) {
      if (slug === 'world') {
        state.currentMapType = 'world';
        state.currentMapSlug = 'world';
        applyBaseLayer('world');
      } else {
        var ws = workspaces.find(function (w) { return w.slug === slug; });
        if (ws) {
          state.currentMapId = ws.mapId;
          var selWs = getEl(els.workspaceSelect);
          if (selWs && selWs.value !== String(ws.mapId)) selWs.value = ws.mapId;
        }
        state.currentMissionId = getMissionId();
        state.currentMapType = mapKindFromSlug(slug, mapsConfigs);
        applyBaseLayer(slug);
      }
      updateTheatreLabel();
    }

    function bindLayerCheckbox(id, key) {
      var el = getEl(id);
      if (!el) return;
      el.checked = !!state.layers[key];
      el.addEventListener('change', function () {
        state.layers[key] = el.checked;
        applyLayerVisibility();
        if (state.currentMapType === 'arma' || state.currentMapType === 'image') {
          if (key === 'units') syncUnits();
          else refreshSecondaryLayers();
        }
      });
    }

    function refreshPlatformHealth() {
      var el = getEl(els.platformStatus);
      if (!el) return;
      fetch(apiBase + '/health', { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          el.textContent = d && d.db === 'ok' ? 'Liaison plateforme OK' : 'Plateforme indisponible';
        })
        .catch(function () { el.textContent = 'Liaison plateforme : erreur'; });
    }

    var wsSel = getEl(els.workspaceSelect);
    if (wsSel) wsSel.addEventListener('change', function () { setWorkspace(this.value); });
    var mapSel = getEl(els.mapSelect);
    if (mapSel) mapSel.addEventListener('change', function () { setMap(this.value); });

    var rs = getEl(els.rosterSearch);
    if (rs) rs.addEventListener('input', function () { filterRosterQuery(this.value); });

    bindLayerCheckbox(els.layerUnits, 'units');
    bindLayerCheckbox(els.layerDanger, 'danger');
    bindLayerCheckbox(els.layerDrawings, 'drawings');
    bindLayerCheckbox(els.layerMarkers, 'markers');
    bindLayerCheckbox(els.layerPings, 'pings');
    bindLayerCheckbox(els.layerSigint, 'sigint');
    bindLayerCheckbox(els.layerIntel, 'intel');
    bindLayerCheckbox(els.layerAir, 'air');

    function zuluTick() {
      var el = getEl(els.zulu);
      if (el) el.textContent = new Date().toISOString().substr(11, 8) + ' Z';
    }
    zuluTick();
    intervals.push(setInterval(zuluTick, 1000));

    refreshPlatformHealth();
    intervals.push(setInterval(refreshPlatformHealth, 60000));

    var initialSlug = (mapSel && mapSel.value) || ctx.defaultMapSlug || 'altis';
    applyBaseLayer(initialSlug);

    return {
      destroy: function () {
        intervals.forEach(function (id) { clearInterval(id); });
        mapIntervals.forEach(function (id) { clearInterval(id); });
        if (map) {
          try { map.remove(); } catch (e) {}
        }
        map = null;
      },
      getState: function () { return state; },
      getMap: function () { return map; },
      selectUnit: selectUnit,
      syncUnits: syncUnits,
      refreshSecondaryLayers: refreshSecondaryLayers,
    };
  }

  global.ComspecOperationalMap = {
    WORLD_SCALE: WORLD_SCALE,
    buildArmaConfig: buildArmaConfig,
    buildImageConfig: buildImageConfig,
    mapKindFromSlug: mapKindFromSlug,
    renderMapShapes: renderMapShapes,
    renderAtakMarkers: renderAtakMarkers,
    initTacmap: initTacmap,
  };
})(window);
