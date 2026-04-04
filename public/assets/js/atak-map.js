/* COMSPEC ATAK - Carte Leaflet + marqueurs temps réel */
window.ATAKMap = (function () {
  var map;
  var layerGroups = {};
  var markersById = {};
  var intelLayer = null;
  var intelMarkersById = {};
  var designatorLayer = null;
  var designatorMarkersById = {};
  var sigintLayer = null;
  var sigintCirclesById = {};
  var pingTempLayer = null;
  var pingTempMarkersById = {};
  var airAssetsLayer = null;
  var airAssetsById = {};
  var config;

  function buildConfigFromAtakMapConfig(raw) {
    if (!raw || !raw.tilePattern) return null;
    var crsOpt = raw.crs || {};
    var factorx = crsOpt.factorx != null ? crsOpt.factorx : 0.006839;
    var factory = crsOpt.factory != null ? crsOpt.factory : 0.006836;
    var tileWidth = crsOpt.tileWidth != null ? crsOpt.tileWidth : 212;
    var CRS = typeof window.MGRS_CRS === 'function' ? window.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
    return {
      CRS: CRS,
      tilePattern: raw.tilePattern,
      minZoom: raw.minZoom != null ? raw.minZoom : 0,
      maxZoom: raw.maxZoom != null ? raw.maxZoom : 6,
      defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 3,
      attribution: raw.attribution || '&copy; Bohemia Interactive',
      tileSize: raw.tileSize != null ? raw.tileSize : 212,
      center: Array.isArray(raw.center) ? raw.center : [15000, 15000],
      offsetX: raw.offsetX != null ? parseFloat(raw.offsetX) : 0,
      offsetY: raw.offsetY != null ? parseFloat(raw.offsetY) : 0
    };
  }

  function destroy() {
    if (!map) return;
    map.remove();
    map = null;
    config = null;
    layerGroups = {};
    markersById = {};
    intelLayer = null;
    intelMarkersById = {};
    designatorLayer = null;
    designatorMarkersById = {};
    sigintLayer = null;
    sigintCirclesById = {};
    pingTempLayer = null;
    pingTempMarkersById = {};
    airAssetsLayer = null;
    airAssetsById = {};
  }

  function init(mapId) {
    destroy();
    config = null;
    if (window.ATAK_MAP_CONFIG) {
      config = buildConfigFromAtakMapConfig(window.ATAK_MAP_CONFIG);
    }
    if (!config && window.Arma3Map && window.Arma3Map.Maps && window.Arma3Map.Maps.altis) {
      config = window.Arma3Map.Maps.altis;
    }
    if (!config) {
      console.error('ATAKMap: no map config (set window.ATAK_MAP_CONFIG or load a map script)');
      return null;
    }
    var el = document.getElementById('atak-map');
    if (!el) return null;

    map = L.map('atak-map', {
      minZoom: config.minZoom,
      maxZoom: config.maxZoom,
      crs: config.CRS
    });

    var tileLayer = L.tileLayer(config.tilePattern, {
      attribution: config.attribution,
      tileSize: config.tileSize
    });
    tileLayer.on('tileerror', function () {
      if (!window._atakTileErrorShown) {
        window._atakTileErrorShown = true;
        if (window.ATAKShowError) window.ATAKShowError('Certaines tuiles de la carte sont indisponibles.');
      }
    });
    tileLayer.addTo(map);

    intelLayer = L.layerGroup().addTo(map);
    intelMarkersById = {};
    designatorLayer = L.layerGroup().addTo(map);
    designatorMarkersById = {};

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

  function applyOffset(lat, lng) {
    if (!config) return [lat, lng];
    var ox = config.offsetX != null ? config.offsetX : 0;
    var oy = config.offsetY != null ? config.offsetY : 0;
    return [lat + oy, lng + ox];
  }

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
    var applied = applyOffset(lat, lng);
    var latlng = L.latLng(applied[0], applied[1]);

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
    var applied = applyOffset(lat, lng);
    map.setView(L.latLng(applied[0], applied[1]), map.getZoom());
  }

  function endPointMap(userId) {}

  function centerOn(lat, lng) {
    if (!map) return;
    var applied = applyOffset(lat, lng);
    map.setView(L.latLng(applied[0], applied[1]), map.getZoom());
  }

  function ensureIntelLayer() {
    if (!map) return null;
    if (!intelLayer) intelLayer = L.layerGroup().addTo(map);
    return intelLayer;
  }

  function addIntelPhotoMarker(id, posY, posX, photoUrl) {
    if (posY == null || posX == null || !photoUrl) return;
    var applied = applyOffset(posY, posX);
    var latlng = L.latLng(applied[0], applied[1]);
    var layer = ensureIntelLayer();
    if (!layer) return;
    if (intelMarkersById[id]) {
      intelMarkersById[id].setLatLng(latlng);
      return;
    }
    var fullUrl = photoUrl.indexOf('http') === 0 || photoUrl.indexOf('//') === 0 ? photoUrl : (window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '') + (photoUrl.charAt(0) === '/' ? photoUrl : '/' + photoUrl);
    var icon = L.divIcon({
      className: 'atak-intel-marker',
      html: '<span style="font-size:18px;" title="Photo CTAB">📷</span>',
      iconSize: [24, 24],
      iconAnchor: [12, 12]
    });
    var marker = L.marker(latlng, { icon: icon });
    marker.bindPopup('<img src="' + fullUrl + '" alt="Intel" style="max-width:280px;max-height:200px;display:block;" />');
    marker._atakIntelId = id;
    marker.addTo(layer);
    intelMarkersById[id] = marker;
  }

  function removeIntelPhotoMarker(id) {
    var m = intelMarkersById[id];
    if (m && intelLayer) {
      intelLayer.removeLayer(m);
      delete intelMarkersById[id];
    }
  }

  function clearIntelMarkers() {
    if (!intelLayer) return;
    Object.keys(intelMarkersById).forEach(function (k) {
      intelLayer.removeLayer(intelMarkersById[k]);
    });
    intelMarkersById = {};
  }

  function addOrUpdateDesignator(row) {
    if (!map || !row) return;
    var id = 'designator_' + (row.call_sign || row.id || '');
    var posX = row.pos_x != null ? row.pos_x : 0;
    var posY = row.pos_y != null ? row.pos_y : 0;
    var applied = applyOffset(posY, posX);
    var latlng = L.latLng(applied[0], applied[1]);
    if (!designatorLayer) designatorLayer = L.layerGroup().addTo(map);
    if (designatorMarkersById[id]) {
      designatorMarkersById[id].setLatLng(latlng);
      return;
    }
    var icon = L.divIcon({
      className: 'atak-designator-marker',
      html: '<span style="font-size:20px;color:#ef4444;line-height:1;" title="JTAC Designator: ' + (row.call_sign || '') + '">&#10010;</span>',
      iconSize: [24, 24],
      iconAnchor: [12, 12]
    });
    var marker = L.marker(latlng, { icon: icon });
    marker.bindPopup('<strong>JTAC</strong> ' + (row.call_sign || '') + '<br/>Cible designee');
    marker._atakDesignatorId = id;
    marker.addTo(designatorLayer);
    designatorMarkersById[id] = marker;
  }

  function addTemporaryPingMarker(posX, posY, author, message) {
    if (!map) return;
    var applied = applyOffset(parseFloat(posY), parseFloat(posX));
    var latlng = L.latLng(applied[0], applied[1]);
    if (!pingTempLayer) pingTempLayer = L.layerGroup().addTo(map);
    var id = 'ping_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    var icon = L.divIcon({
      className: 'atak-ping-temp-icon',
      html: '<span style="width:14px;height:14px;border-radius:50%;background:#ef4444;border:2px solid #fca5a5;"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9]
    });
    var marker = L.marker(latlng, { icon: icon });
    marker.bindPopup('<b>PING de ' + (author || '?') + '</b><br/>' + (message || '')).openPopup();
    marker._atakPingTempId = id;
    marker.addTo(pingTempLayer);
    pingTempMarkersById[id] = marker;
    setTimeout(function () {
      if (pingTempMarkersById[id] && pingTempLayer) {
        pingTempLayer.removeLayer(pingTempMarkersById[id]);
        delete pingTempMarkersById[id];
      }
    }, 30000);
  }

  function pollMarkers() {
    if (!map) return;
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var mapId = window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
    var url = (base || '') + '/api/atak/markers?mapId=' + mapId;
    fetch(url).then(function (r) { return r.json(); }).then(function (list) {
      if (!map || !Array.isArray(list)) return;
      var seen = {};
      list.forEach(function (m) {
        var id = m.id;
        if (id == null) return;
        seen[id] = true;
        var data = (typeof m.markerData === 'string' ? (function () { try { return JSON.parse(m.markerData); } catch (e) { return {}; } })() : (m.markerData || {}));
        addOrUpdateMarker({ id: id, layerId: m.layerId != null ? m.layerId : 0, data: data });
      });
      Object.keys(markersById).forEach(function (k) {
        if (!seen[k]) removeMarker({ id: k });
      });
    }).catch(function () {});
  }

  function refreshSigintZones() {
    if (!map) return;
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var mapId = window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
    var url = (base || '') + '/api/atak/sigint/zones?mapId=' + mapId;
    fetch(url).then(function (r) { return r.json(); }).then(function (zones) {
      if (!map) return;
      if (!sigintLayer) sigintLayer = L.layerGroup().addTo(map);
      var seen = {};
      zones.forEach(function (z, i) {
        var id = 'sigint_' + i;
        seen[id] = true;
        var posX = z.pos_x != null ? z.pos_x : 0;
        var posY = z.pos_y != null ? z.pos_y : 0;
        var radius = z.radius != null ? z.radius : 200;
        var applied = applyOffset(posY, posX);
        var latlng = L.latLng(applied[0], applied[1]);
        if (sigintCirclesById[id]) {
          sigintCirclesById[id].setLatLng(latlng);
          sigintCirclesById[id].setRadius(radius);
        } else {
          var circle = L.circle(latlng, { radius: radius, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 0.15, weight: 2 });
          circle._atakSigintId = id;
          circle.addTo(sigintLayer);
          circle.bindPopup('SIGINT: zone d\'incertitude (' + (z.reports || 0) + ' rapports)');
          sigintCirclesById[id] = circle;
        }
      });
      Object.keys(sigintCirclesById).forEach(function (k) {
        if (!seen[k]) {
          sigintLayer.removeLayer(sigintCirclesById[k]);
          delete sigintCirclesById[k];
        }
      });
    }).catch(function () {});
  }

  function setAirAssets(assets) {
    if (!map || !Array.isArray(assets)) return;
    if (!airAssetsLayer) airAssetsLayer = L.layerGroup().addTo(map);
    var seen = {};
    assets.forEach(function (a) {
      var id = 'air_' + (a.callsign || '').replace(/\s/g, '_');
      seen[id] = true;
      var posX = a.pos_x != null ? a.pos_x : 0;
      var posY = a.pos_y != null ? a.pos_y : 0;
      var applied = applyOffset(posY, posX);
      var latlng = L.latLng(applied[0], applied[1]);
      var side = (a.side || 'WEST').toUpperCase();
      var status = (a.status || 'IN-FLIGHT').toUpperCase();
      var color = '#3b82f6';
      if (side === 'EAST') color = '#ef4444';
      else if (side === 'GUER' || side === 'CIV' || status === 'SUSPECT') color = '#eab308';
      if (status === 'SUSPECT') color = '#f97316';
      var type = (a.aircraft_type || 'plane').toLowerCase();
      var sym = '▲';
      if (type === 'helicopter') sym = 'H';
      if (type === 'uav') sym = '◆';
      if (!airAssetsById[id]) {
        var icon = L.divIcon({
          className: 'atak-air-asset-marker',
          html: '<span style="color:' + color + ';font-size:16px;font-weight:bold;text-shadow:0 0 2px #000;">' + sym + '</span>',
          iconSize: [20, 20],
          iconAnchor: [10, 10]
        });
        var marker = L.marker(latlng, { icon: icon });
        marker._atakAirId = id;
        marker.addTo(airAssetsLayer);
        marker.bindPopup('<strong>' + (a.callsign || '—') + '</strong><br/>' + (a.model || '') + '<br/>FREQ ' + (a.freq || '—') + ' LASER ' + (a.laser || '1688') + '<br/>' + status);
        airAssetsById[id] = marker;
      } else {
        airAssetsById[id].setLatLng(latlng);
        airAssetsById[id].setIcon(L.divIcon({
          className: 'atak-air-asset-marker',
          html: '<span style="color:' + color + ';font-size:16px;font-weight:bold;text-shadow:0 0 2px #000;">' + sym + '</span>',
          iconSize: [20, 20],
          iconAnchor: [10, 10]
        }));
        airAssetsById[id].setPopupContent('<strong>' + (a.callsign || '—') + '</strong><br/>' + (a.model || '') + '<br/>FREQ ' + (a.freq || '—') + ' LASER ' + (a.laser || '1688') + '<br/>' + status);
      }
    });
    Object.keys(airAssetsById).forEach(function (k) {
      if (!seen[k]) {
        airAssetsLayer.removeLayer(airAssetsById[k]);
        delete airAssetsById[k];
      }
    });
  }

  return {
    init: init,
    destroy: destroy,
    getMap: getMap,
    getConfig: getConfig,
    applyOffset: applyOffset,
    addIntelPhotoMarker: addIntelPhotoMarker,
    removeIntelPhotoMarker: removeIntelPhotoMarker,
    clearIntelMarkers: clearIntelMarkers,
    addOrUpdateMarker: addOrUpdateMarker,
    addOrUpdateDesignator: addOrUpdateDesignator,
    refreshSigintZones: refreshSigintZones,
    pollMarkers: pollMarkers,
    setAirAssets: setAirAssets,
    removeMarker: removeMarker,
    addOrUpdateLayer: addOrUpdateLayer,
    removeLayer: removeLayer,
    pointMap: pointMap,
    endPointMap: endPointMap,
    centerOn: centerOn,
    addTemporaryPingMarker: addTemporaryPingMarker
  };
})();
