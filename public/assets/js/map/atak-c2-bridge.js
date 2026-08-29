/**
 * Pont live ATAK → refonte C2 (symbologie, rail, contrôles, panneau unité).
 * Activé par window.ATAK_MAP_C2_V2 = true (défini dans views/atak.php).
 *
 * Ne recrée pas la carte Leaflet : réutilise ATAKMap + flux WebSocket/polling
 * via l’interception de setUnitsMarkers (alimenté par atak-units.js / socket).
 */
(function () {
  'use strict';

  if (!window.ATAK_MAP_C2_V2) return;

  var assetBase = (window.ATAK_C2_ASSET_BASE || '').replace(/\/$/, '');
  if (!assetBase) {
    try {
      var scripts = document.querySelectorAll('script[src*="atak-c2-bridge"]');
      if (scripts.length) {
        assetBase = scripts[scripts.length - 1].src.replace(/\/assets\/js\/map\/atak-c2-bridge\.js.*$/, '');
      }
    } catch (e) { /* ignore */ }
  }
  if (!assetBase) assetBase = '';

  document.documentElement.classList.add('atak-map-c2-v2');
  document.body && document.body.classList.add('atak-map-c2-v2');

  ensureCss(assetBase + '/assets/css/atak-map-c2-v2.css');

  var state = {
    ready: false,
    manager: null,
    tracks: null,
    controls: null,
    entityPanel: null,
    ui: null,
    lastUnits: [],
  };

  window.addEventListener('atak:mapready', boot);
  if (window.ATAKMap && typeof window.ATAKMap.getMap === 'function' && window.ATAKMap.getMap()) {
    boot();
  }

  function boot() {
    if (state.ready) return;
    var map = window.ATAKMap && window.ATAKMap.getMap && window.ATAKMap.getMap();
    if (!map || !window.L) return;
    state.ready = true;

    Promise.all([
      import(assetBase + '/assets/js/map/MarkerManager.js'),
      import(assetBase + '/assets/js/map/TrackRenderer.js'),
      import(assetBase + '/assets/js/map/MapControls.js'),
      import(assetBase + '/assets/js/map/MapUI.js'),
      import(assetBase + '/assets/js/map/SelectedEntityPanel.js'),
    ]).then(function (mods) {
      var MarkerManager = mods[0].MarkerManager || window.MarkerManager;
      var TrackRenderer = mods[1].TrackRenderer || window.TrackRenderer;
      var MapControls = mods[2].MapControls || window.MapControls;
      var MapUI = mods[3].MapUI || window.MapUI;
      var SelectedEntityPanel = mods[4].SelectedEntityPanel || window.SelectedEntityPanel;

      state.manager = new MarkerManager({ map: map, clustering: true });
      window.ATAKMarkerManagerC2 = state.manager;

      var trackLayer = window.L.layerGroup().addTo(map);
      state.tracks = new TrackRenderer({ leafletMap: map });
      state.tracks.setLeafletLayer(trackLayer);
      window.ATAKTrackRendererC2 = state.tracks;

      wireUnitFeed();
      wireControls(MapControls, map);
      wireToolRail(MapUI);
      wireEntityPanel(SelectedEntityPanel);
      wireEntityFocus(map);
      hideLegacyToolbarChrome();

      /* Premier sync si unités déjà en mémoire */
      if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
        pushUnits(window.ATAKUnits.getUnits());
      }

      window.ATAKMapC2 = {
        setEntities: function (list) { pushUnits(list); },
        setTracks: function (tr) {
          if (state.tracks) state.tracks.updateTracks(tr || []);
        },
        manager: state.manager,
        tracks: state.tracks,
      };

      try {
        window.dispatchEvent(new CustomEvent('atak:c2-ready', { detail: window.ATAKMapC2 }));
      } catch (e) { /* ignore */ }
    }).catch(function (err) {
      console.warn('[ATAK C2 live]', err);
      state.ready = false;
    });
  }

  function wireUnitFeed() {
    window.addEventListener('atak:units-updated', function (ev) {
      var units = (ev.detail && ev.detail.units) || [];
      pushUnits(units);
    });

    if (!window.ATAKMap || typeof window.ATAKMap.setUnitsMarkers !== 'function') return;
    if (window.ATAKMap._setUnitsMarkersC2Wrapped) return;
    window.ATAKMap._setUnitsMarkersC2Wrapped = true;
    window.ATAKMap._setUnitsMarkersOrig = window.ATAKMap.setUnitsMarkers;

    window.ATAKMap.setUnitsMarkers = function (units, opts) {
      /* atak-map.js (mode C2) stocke la liste + émet atak:units-updated ; on pousse aussi ici
         au cas où le gestionnaire C2 est déjà prêt avant l’événement. */
      pushUnits(units);
      return window.ATAKMap._setUnitsMarkersOrig(units, opts);
    };
  }

  function pushUnits(units) {
    state.lastUnits = Array.isArray(units) ? units : [];
    if (!state.manager) return;
    var entities = state.lastUnits.map(normalizeUnit).filter(function (e) {
      return e && e.id != null && (e.x != null || e.lat != null);
    });
    state.manager.setEntities(entities);

    if (state.tracks && window.ATAKUnits && typeof window.ATAKUnits.getTrailBuffers === 'function') {
      try {
        state.tracks.updateTracks(window.ATAKUnits.getTrailBuffers() || []);
      } catch (e) { /* optional */ }
    }
  }

  function normalizeUnit(u) {
    u = u || {};
    var extra = {};
    try {
      if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}') || {};
      else if (u.extra && typeof u.extra === 'object') extra = u.extra;
    } catch (e) { extra = {}; }

    var live = 'ONLINE';
    if (window.ATAKUnits && window.ATAKUnits.resolveLiveStatus) {
      var st = String(window.ATAKUnits.resolveLiveStatus(u) || '').toLowerCase();
      if (st === 'offline') live = 'LOST';
      else if (st === 'delayed') live = 'STALE';
      else if (st === 'online') live = 'ONLINE';
    }
    var health = String(extra.health || u.health || '').toLowerCase();
    if (health === 'dead' || health === 'kia') live = 'KIA';
    else if (health === 'wounded' || health === 'injured' || health === 'unconscious') live = 'DEGRADED';

    var x = u.pos_x != null ? parseFloat(u.pos_x) : (u.x != null ? parseFloat(u.x) : NaN);
    var y = u.pos_y != null ? parseFloat(u.pos_y) : (u.y != null ? parseFloat(u.y) : NaN);
    if ((isNaN(x) || isNaN(y)) && u.grid_ref) {
      var parts = String(u.grid_ref).trim().split(/\s+/);
      x = parseFloat(parts[0]);
      y = parseFloat(parts[1]);
    }

    var id = u.id != null && String(u.id) !== ''
      ? String(u.id)
      : String(u.call_sign || u.callsign || u.steam_uid || '').trim();

    return {
      id: id,
      callsign: u.call_sign || u.callsign || extra.callsign || id,
      role: u.role || extra.role || u.roleDescription || '',
      affiliation: extra.affiliation || extra.affil || u.affiliation || u.side || 'FRIENDLY',
      type: mapPlatformType(u, extra),
      status: live,
      heading: u.heading != null ? u.heading : (u.movement_heading != null ? u.movement_heading : extra.heading),
      speed: u.speed != null ? u.speed : extra.speed,
      altitude: u.asl_z != null ? u.asl_z : (u.altitude != null ? u.altitude : extra.asl_z),
      x: isNaN(x) ? null : x,
      y: isNaN(y) ? null : y,
      grid: u.grid_ref || '',
    };
  }

  function mapPlatformType(u, extra) {
    var raw = String(u.platform || u.unitType || extra.platform || extra.vehicle_type || '').toUpperCase();
    if (raw.indexOf('UAV') >= 0 || raw.indexOf('DRONE') >= 0) return 'UAV';
    if (raw.indexOf('AIR') >= 0 || raw.indexOf('HELI') >= 0 || raw.indexOf('PLANE') >= 0 || raw.indexOf('JET') >= 0) return 'AIR';
    if (raw.indexOf('TANK') >= 0 || raw.indexOf('MBT') >= 0) return 'VEHICLE';
    if (raw.indexOf('APC') >= 0 || raw.indexOf('IFV') >= 0 || raw.indexOf('AFV') >= 0) return 'VEHICLE';
    if (raw.indexOf('TRUCK') >= 0 || raw.indexOf('MRAP') >= 0 || raw.indexOf('CAR') >= 0 || raw.indexOf('VEH') >= 0) return 'VEHICLE';
    if (raw.indexOf('ARTILLERY') >= 0 || raw.indexOf('ARTY') >= 0 || raw.indexOf('MORTAR') >= 0) return 'VEHICLE';
    if (raw.indexOf('LIGHT_VEHICLE') >= 0 || raw.indexOf('SHIP') >= 0 || raw.indexOf('BOAT') >= 0) return 'VEHICLE';
    if (raw.indexOf('MED') >= 0) return 'MEDICAL';
    if (raw.indexOf('CMD') >= 0 || raw.indexOf('HQ') >= 0) return 'COMMAND';
    var role = String(u.role || extra.role || '').toLowerCase();
    if (role.indexOf('medic') >= 0 || role.indexOf('médecin') >= 0) return 'MEDICAL';
    if (role.indexOf('jtac') >= 0 || role.indexOf('sl') >= 0 || role.indexOf('leader') >= 0) return 'COMMAND';
    return 'INFANTRY';
  }

  function wireControls(MapControls, map) {
    var el = document.querySelector('.atak-map-wrap .tac-map-controls')
      || document.getElementById('atak-c2-map-controls');
    if (!el || !MapControls) return;
    state.controls = new MapControls(el, {
      onZoomIn: function () {
        var stage3d = document.querySelector('.atak-map-stage--premium-3d');
        if (stage3d && window.ATAKTerrainThree && typeof window.ATAKTerrainThree.dolly === 'function') {
          window.ATAKTerrainThree.dolly(0.82);
          return;
        }
        map.zoomIn();
      },
      onZoomOut: function () {
        var stage3dOut = document.querySelector('.atak-map-stage--premium-3d');
        if (stage3dOut && window.ATAKTerrainThree && typeof window.ATAKTerrainThree.dolly === 'function') {
          window.ATAKTerrainThree.dolly(1.22);
          return;
        }
        map.zoomOut();
      },
      onNorth: function () {
        try {
          if (typeof map.setBearing === 'function') map.setBearing(0);
        } catch (e) { /* ignore */ }
        var flat = document.getElementById('atak-map-3d-flat');
        if (flat) flat.click();
      },
      onRecenter: function () {
        var cfg = window.ATAK_MAP_CONFIG;
        if (cfg && cfg.center && window.ATAKMap.latLngFromWorld) {
          var ll = window.ATAKMap.latLngFromWorld(cfg.center[0], cfg.center[1]);
          if (ll) map.setView(ll, cfg.defaultZoom || map.getZoom());
        } else if (cfg && cfg.center) {
          map.setView([cfg.center[1], cfg.center[0]], cfg.defaultZoom || map.getZoom());
        }
      },
      onToggle23d: function (mode) {
        var btn3d = document.getElementById('atak-view-3d');
        if (!btn3d) return;
        var pressed = btn3d.getAttribute('aria-pressed') === 'true';
        if (mode === '3d' && !pressed) btn3d.click();
        if (mode === '2d' && pressed) btn3d.click();
        /* Resync si l’init 3D échoue (CSP / vendor) — le bouton legacy peut rester à plat. */
        window.setTimeout(function () {
          if (!state.controls || typeof state.controls.setMode !== 'function') return;
          var still = document.getElementById('atak-view-3d');
          var on = still && still.getAttribute('aria-pressed') === 'true';
          state.controls.setMode(on ? '3d' : '2d');
        }, 700);
      },
      onFollow: function (on) {
        triggerLegacyTool('follow', on);
      },
    });

    window.addEventListener('atak:terrain3dchange', function (ev) {
      if (!state.controls || typeof state.controls.setMode !== 'function') return;
      var enabled = !!(ev.detail && ev.detail.enabled);
      state.controls.setMode(enabled ? '3d' : '2d');
    });
  }

  function wireToolRail(MapUI) {
    var root = document.getElementById('atak-c2-live-shell') || document.querySelector('.atak-map-wrap');
    if (MapUI && root) {
      state.ui = new MapUI(root);
    }
    window.addEventListener('atak:tool-changed', function (ev) {
      var tool = ev.detail;
      mapC2ToolToLegacy(tool);
    });
    /* Rail buttons also carry data-c2-legacy for direct mapping */
    document.querySelectorAll('#atak-c2-rail [data-tool]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('#atak-c2-rail [data-tool]').forEach(function (b) {
          b.classList.remove('is-active');
        });
        btn.classList.add('is-active');
        mapC2ToolToLegacy(btn.getAttribute('data-tool'));
      });
    });
  }

  function mapC2ToolToLegacy(tool) {
    var map = {
      select: null,
      pan: null,
      measure: 'measure',
      draw: 'line',
      perimeter: 'perimeter',
      marker: 'note',
      search: 'search-zone',
      layers: 'cop',
      goto: 'goto',
      follow: 'follow',
      route: 'route',
      los: 'los',
      nvg: 'nvg',
    };
    var legacy = map[tool];
    if (legacy) triggerLegacyTool(legacy);
  }

  function triggerLegacyTool(tool, forceOn) {
    var btn = document.querySelector('#atak-map-tools [data-tool="' + tool + '"]');
    if (!btn) return;
    if (forceOn === true && btn.getAttribute('aria-pressed') === 'true') return;
    if (forceOn === false && btn.getAttribute('aria-pressed') !== 'true') return;
    btn.click();
  }

  function wireEntityPanel(SelectedEntityPanel) {
    var el = document.getElementById('atak-c2-context-panel');
    if (!el || !SelectedEntityPanel) return;
    state.entityPanel = new SelectedEntityPanel(el);

    window.addEventListener('atak:entity-selected', function (ev) {
      var entity = ev.detail;
      var side = document.getElementById('atak-c2-side');
      var body = document.getElementById('atak-c2-side-unit');
      if (!side || !body) return;
      if (!entity) {
        side.hidden = true;
        body.innerHTML = '<p class="tac-ctx__muted">Cliquez un symbole sur la carte</p>';
        return;
      }
      side.hidden = false;
      var cs = entity.callsign || entity.id || '—';
      var role = entity.role || '—';
      var status = entity.status || 'ONLINE';
      body.innerHTML =
        '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Indicatif</span><span class="tac-c2-side__v mono">' + escHtml(cs) + '</span></div>'
        + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Rôle</span><span class="tac-c2-side__v">' + escHtml(role) + '</span></div>'
        + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Statut</span><span class="tac-c2-side__v">' + escHtml(status) + '</span></div>'
        + (entity.grid ? '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Grille</span><span class="tac-c2-side__v mono">' + escHtml(entity.grid) + '</span></div>' : '');
    });
  }

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function wireEntityFocus(map) {
    window.addEventListener('atak:entity-focus', function (ev) {
      var e = ev.detail;
      if (!e || !state.manager) return;
      var latlng = state.manager._entityLatLng(e);
      if (latlng) map.setView(latlng, Math.max(map.getZoom(), 4));
    });
    window.addEventListener('atak:entity-follow', function () {
      triggerLegacyTool('follow', true);
    });
  }

  function hideLegacyToolbarChrome() {
    var tools = document.getElementById('atak-map-tools');
    if (tools) {
      tools.classList.add('atak-map-tools--c2-legacy');
      tools.setAttribute('aria-hidden', 'true');
    }
    var fab = document.getElementById('atak-map-tools-fab');
    if (fab) fab.hidden = true;
  }

  function ensureCss(href) {
    if (!href) return;
    if (document.querySelector('link[data-atak-c2-css]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href + (href.indexOf('?') >= 0 ? '&' : '?') + 'v=c2live';
    link.setAttribute('data-atak-c2-css', '1');
    document.head.appendChild(link);
  }
})();
