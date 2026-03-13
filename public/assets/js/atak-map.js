/* COMSPEC ATAK - Carte Leaflet + marqueurs temps réel */
window.ATAKMap = (function () {
  var map;
  var layerGroups = {};
  var markersById = {};
  var config;

  function init(mapId) {
    config = window.Arma3Map && window.Arma3Map.Maps && window.Arma3Map.Maps.altis;
    if (!config) {
      console.error('ATAKMap: Arma3Map.Maps.altis not found');
      return null;
    }
    var el = document.getElementById('atak-map');
    if (!el) return null;

    map = L.map('atak-map', {
      minZoom: config.minZoom,
      maxZoom: config.maxZoom,
      crs: config.CRS
    });

    L.tileLayer(config.tilePattern, {
      attribution: config.attribution,
      tileSize: config.tileSize
    }).addTo(map);

    map.setView(config.center, config.defaultZoom);
    L.control.scale({ maxWidth: 200, imperial: false }).addTo(map);

    var gridEl = L.DomUtil.create('div', 'leaflet-grid-mouseposition');
    gridEl.style.cssText = 'position:absolute;top:10px;right:10px;padding:6px 8px;background:rgba(18,18,26,0.95);color:#e8e8ed;font-size:11px;border-radius:4px;z-index:1000;border:1px solid #2a2a35;';
    gridEl.textContent = '0 - 0';
    map.getContainer().appendChild(gridEl);
    map.on('mousemove', function (e) {
      var lat = Math.round(e.latlng.lat);
      var lng = Math.round(e.latlng.lng);
      gridEl.textContent = lng + ' - ' + lat;
    });

    window.ATAKMap._map = map;
    return map;
  }

  function getMap() { return map; }

  function getConfig() { return config; }

  function ensureLayer(layerId) {
    if (!layerGroups[layerId]) {
      layerGroups[layerId] = L.layerGroup().addTo(map);
    }
    return layerGroups[layerId];
  }

  function addOrUpdateMarker(payload) {
    var id = payload.id;
    var layerId = payload.layerId;
    var data = payload.data || {};
    var pos = data.pos;
    if (!pos || !pos.length) return;
    var lat, lng;
    if (Array.isArray(pos[0])) {
      lat = pos[0][1];
      lng = pos[0][0];
    } else {
      lat = pos[1];
      lng = pos[0];
    }
    if (lat == null || lng == null) return;
    var latlng = L.latLng(lat, lng);

    if (markersById[id]) {
      markersById[id].setLatLng(latlng);
      return;
    }
    var layer = ensureLayer(layerId);
    var icon = L.divIcon({
      className: 'atak-marker-icon',
      html: '<span style="width:12px;height:12px;border-radius:50%;background:#34d399;border:2px solid #0a0a0f;"></span>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });
    var marker = L.marker(latlng, { icon: icon });
    marker._atakId = id;
    marker._atakLayerId = layerId;
    marker.addTo(layer);
    markersById[id] = marker;
  }

  function removeMarker(payload) {
    var id = payload.id;
    var m = markersById[id];
    if (m) {
      var lg = layerGroups[m._atakLayerId];
      if (lg) lg.removeLayer(m);
      delete markersById[id];
    }
  }

  function removeLayer(payload) {
    var id = payload.id;
    var lg = layerGroups[id];
    if (lg) {
      map.removeLayer(lg);
      delete layerGroups[id];
    }
    Object.keys(markersById).forEach(function (k) {
      if (markersById[k]._atakLayerId === id) delete markersById[k];
    });
  }

  function addOrUpdateLayer(payload) {
    ensureLayer(payload.id);
  }

  function pointMap(userId, pos) {
    if (!map || !pos || !pos.length) return;
    var lat = pos[0];
    var lng = pos.length > 1 ? pos[1] : pos[0];
    map.setView(L.latLng(lat, lng), map.getZoom());
  }

  function endPointMap(userId) {}

  function centerOn(lat, lng) {
    if (map) map.setView(L.latLng(lat, lng), map.getZoom());
  }

  return {
    init: init,
    getMap: getMap,
    getConfig: getConfig,
    addOrUpdateMarker: addOrUpdateMarker,
    removeMarker: removeMarker,
    addOrUpdateLayer: addOrUpdateLayer,
    removeLayer: removeLayer,
    pointMap: pointMap,
    endPointMap: endPointMap,
    centerOn: centerOn
  };
})();
