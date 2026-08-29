/**
 * ATAK — calques villes/routes + planification road-aware sur /public/atak.
 */
(function () {
  'use strict';

  var geoNetwork = null;
  var routePlanner = null;
  var geoLayerGroup = null;
  var lastBboxKey = '';
  var prefs = { places: false, roads: false };

  function siteBase() {
    return (window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '')).replace(/\/$/, '');
  }

  /** Base attendue par AtakGeoNetwork / AtakRoutePlanner : …/api */
  function geoApiBase() {
    var base = siteBase();
    if (base.slice(-4) !== '/api') base += '/api';
    return base;
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId
      ? window.ATAKSocket.getMapId()
      : (window.ATAK_DEFAULT_MAP_ID || 1);
  }

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function ensureCheckboxes() {
    var settings = document.getElementById('atak-terrain-3d-settings');
    var host = document.getElementById('atak-settings-map') || document.getElementById('atak-map-look-prefs');
    var mount = settings || host;
    if (!mount || document.getElementById('atak-geo-places')) return;

    var wrap = document.createElement('div');
    wrap.className = 'atak-geo-live-prefs';
    wrap.innerHTML =
      '<label class="atak-map-look__check" for="atak-geo-places">' +
      '<input type="checkbox" id="atak-geo-places" />' +
      '<span>Villes (réseau geo)</span></label>' +
      '<label class="atak-map-look__check" for="atak-geo-roads">' +
      '<input type="checkbox" id="atak-geo-roads" />' +
      '<span>Routes (réseau geo)</span></label>' +
      '<p class="atak-terrain-3d-hint atak-geo-live-hint">Calques issus du relevé Arma (modules pont geo_places / geo_roads). L’outil Itinéraire utilise le graphe routier quand disponible.</p>';

    var hint = mount.querySelector('.atak-terrain-3d-hint');
    if (hint && hint.parentNode === mount) mount.insertBefore(wrap, hint);
    else mount.appendChild(wrap);

    document.getElementById('atak-geo-places').addEventListener('change', function (e) {
      prefs.places = !!e.target.checked;
      refreshGeo();
    });
    document.getElementById('atak-geo-roads').addEventListener('change', function (e) {
      prefs.roads = !!e.target.checked;
      refreshGeo();
    });
  }

  function refreshGeo() {
    if (!geoNetwork) return;
    geoNetwork.setVisible('places', prefs.places);
    geoNetwork.setVisible('roads', prefs.roads);
    if (!prefs.places && !prefs.roads) {
      geoNetwork.render({ places: [], roads: [] });
      lastBboxKey = '';
      return;
    }
    var map = getMap();
    if (!map) return;
    var b = map.getBounds();
    var sw = b.getSouthWest();
    var ne = b.getNorthEast();
    var bbox = [sw.lng, sw.lat, ne.lng, ne.lat];
    var key = bbox.map(function (n) { return n.toFixed(0); }).join(',') + '|' + prefs.places + '|' + prefs.roads;
    if (key === lastBboxKey) return;
    lastBboxKey = key;
    geoNetwork.loadBbox(bbox).then(function (data) {
      geoNetwork.render(data || { places: [], roads: [] });
    });
  }

  function bindMapEvents(map) {
    if (!map || map._atakGeoLiveBound) return;
    map._atakGeoLiveBound = true;
    map.on('moveend', function () {
      if (prefs.places || prefs.roads) {
        lastBboxKey = '';
        refreshGeo();
      }
    });
  }

  function wireRoutePlanner() {
    window.ATAKGeoLive = {
      planRoadRoute: function (start, end, via, mode) {
        if (!routePlanner) return Promise.resolve({ ok: false });
        if (geoNetwork && !geoNetwork.isGeoReady()) {
          return geoNetwork.loadCoverage().then(function () {
            if (!geoNetwork.isGeoReady()) return { ok: false, error: 'geo_not_ready' };
            return routePlanner.planRoute(start, end, via, mode);
          });
        }
        return routePlanner.planRoute(start, end, via, mode);
      },
      isReady: function () {
        return !!(geoNetwork && geoNetwork.isGeoReady());
      },
      refresh: refreshGeo,
    };
  }

  function boot() {
    if (!window.AtakGeoNetwork || !window.L) return;
    var map = getMap();
    if (!map) return;

    if (!geoLayerGroup) {
      geoLayerGroup = L.layerGroup().addTo(map);
    }
    if (!geoNetwork) {
      geoNetwork = window.AtakGeoNetwork.create(geoApiBase(), mapId(), geoLayerGroup);
      geoNetwork.loadCoverage();
    }
    if (!routePlanner && window.AtakRoutePlanner) {
      routePlanner = window.AtakRoutePlanner.create(geoApiBase(), mapId());
    }

    ensureCheckboxes();
    bindMapEvents(map);
    wireRoutePlanner();
  }

  function init() {
    ensureCheckboxes();
    window.addEventListener('atak:mapready', boot);
    if (getMap()) boot();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
