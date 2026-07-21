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
  var unitsLayer = null;
  var unitsById = {};
  var config;
  var baseTileLayer = null;
  var tileFailCount = 0;

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
    unitsLayer = null;
    unitsById = {};
    baseTileLayer = null;
    tileFailCount = 0;
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
      tileSize: config.tileSize,
      errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
    });
    tileFailCount = 0;
    tileLayer.on('tileerror', function () {
      tileFailCount += 1;
      if (tileFailCount === 8 && !window._atakTileErrorShown) {
        window._atakTileErrorShown = true;
        if (window.ATAKShowError) {
          window.ATAKShowError('Fond de carte indisponible (tuiles). Vérifiez le CDN ou basculez de théâtre.');
        }
      }
    });
    tileLayer.on('load', function () {
      tileFailCount = 0;
    });
    tileLayer.addTo(map);
    baseTileLayer = tileLayer;

    intelLayer = L.layerGroup().addTo(map);
    intelMarkersById = {};
    designatorLayer = L.layerGroup().addTo(map);
    designatorMarkersById = {};
    unitsLayer = L.layerGroup().addTo(map);
    unitsById = {};
    airAssetsLayer = L.layerGroup().addTo(map);
    airAssetsById = {};

    map.setView(config.center, config.defaultZoom);
    L.control.scale({ maxWidth: 200, imperial: false }).addTo(map);

    var gridEl = L.DomUtil.create('div', 'leaflet-grid-mouseposition atak-map-hud');
    gridEl.innerHTML = '<div class="atak-map-hud__row"><span class="atak-map-hud__k">GRID</span> <span class="atak-map-hud__v" data-hud-grid>0 0</span></div>'
      + '<div class="atak-map-hud__row"><span class="atak-map-hud__k">NET</span> <span class="atak-map-hud__v atak-map-hud__ok">LINK</span></div>';
    map.getContainer().appendChild(gridEl);
    map.on('mousemove', function (e) {
      var lat = Math.round(e.latlng.lat);
      var lng = Math.round(e.latlng.lng);
      var v = gridEl.querySelector('[data-hud-grid]');
      if (v) v.textContent = lng + ' ' + lat;
    });

    window.ATAKMap._map = map;
    try {
      window.dispatchEvent(new CustomEvent('atak:mapready', { detail: { map: map, mapId: mapId } }));
    } catch (e) {}
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

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function markerSizePx(size) {
    if (size === 'sm' || size === 'small') return 10;
    if (size === 'lg' || size === 'large') return 18;
    if (typeof size === 'number' && size > 0) return size;
    return 14;
  }

  function buildManualMarkerIcon(data) {
    var color = data.color || '#34d399';
    var kind = data.icon || data.symbol || 'dot';
    var px = markerSizePx(data.size);
    var html;
    if (kind === 'pin') {
      html = '<span class="atak-micon atak-micon--pin" style="--m-color:' + color + ';--m-size:' + px + 'px"></span>';
    } else if (kind === 'flag') {
      html = '<span class="atak-micon atak-micon--flag" style="--m-color:' + color + ';--m-size:' + px + 'px"></span>';
    } else if (kind === 'warning') {
      html = '<span class="atak-micon atak-micon--warning" style="--m-color:' + color + ';--m-size:' + px + 'px">!</span>';
    } else if (kind === 'target') {
      html = '<span class="atak-micon atak-micon--target" style="--m-color:' + color + ';--m-size:' + px + 'px"></span>';
    } else {
      html = '<span class="atak-micon atak-micon--dot" style="--m-color:' + color + ';--m-size:' + px + 'px"></span>';
    }
    var box = Math.max(px + 8, 22);
    return L.divIcon({
      className: 'atak-marker-icon',
      html: html,
      iconSize: [box, box],
      iconAnchor: [box / 2, box / 2]
    });
  }

  function markerPopupHtml(data, lng, lat) {
    var label = data.label || data.text || data.message || data.name || 'Marqueur';
    var author = data.author || data.createdBy || '';
    var desc = data.description || data.desc || '';
    var gx = Math.round(Number(lng));
    var gy = Math.round(Number(lat));
    var html = '<div class="atak-marker-popup">';
    html += '<strong>' + escapeHtml(label) + '</strong>';
    html += '<div class="atak-marker-popup__coords">Grille ' + gx + ' / ' + gy + '</div>';
    if (desc) html += '<p class="atak-marker-popup__desc">' + escapeHtml(desc) + '</p>';
    if (author) html += '<span class="atak-marker-popup__author">' + escapeHtml(author) + '</span>';
    html += '</div>';
    return html;
  }

  function emitFeatureContextMenu(detail, e) {
    if (!e || !e.originalEvent) return;
    L.DomEvent.preventDefault(e);
    L.DomEvent.stopPropagation(e);
    try { e.originalEvent.stopImmediatePropagation(); } catch (err) {}
    var oe = e.originalEvent;
    window.dispatchEvent(new CustomEvent('atak:feature-contextmenu', {
      detail: Object.assign({}, detail, {
        latlng: e.latlng || null,
        clientX: oe.clientX,
        clientY: oe.clientY
      })
    }));
  }

  function bindMarkerContextMenu(marker, id) {
    if (!marker || marker._atakCtxBound) return;
    marker._atakCtxBound = true;
    marker.on('contextmenu', function (e) {
      var data = marker._atakData || {};
      emitFeatureContextMenu({
        featureType: 'marker',
        id: id,
        layerId: marker._atakLayerId,
        data: data,
        label: data.label || data.text || data.name || 'Marqueur'
      }, e);
    });
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
    var popupHtml = markerPopupHtml(data, lng, lat);
    var isManual = (data.type === 'manual') || data.color || data.icon || data.size || data.description;

    if (markersById[id]) {
      markersById[id].setLatLng(latlng);
      markersById[id]._atakData = data;
      markersById[id]._atakGrid = { lng: lng, lat: lat };
      if (isManual) {
        try { markersById[id].setIcon(buildManualMarkerIcon(data)); } catch (e) {}
      }
      if (markersById[id].getPopup && markersById[id].getPopup()) {
        markersById[id].setPopupContent(popupHtml);
      } else {
        markersById[id].bindPopup(popupHtml);
      }
      bindMarkerContextMenu(markersById[id], id);
      return;
    }
    var layer = ensureLayer(layerId);
    var icon;
    if (isManual) {
      icon = buildManualMarkerIcon(data);
    } else {
      var nato = window.NatoSidcIcons;
      var label = data.label || data.text || data.message || data.name || '';
      if (nato && nato.leafletDivIcon) {
        icon = nato.leafletDivIcon(L, {
          affiliation: 'friend',
          role: 'point',
          roleKey: 'recon',
          callSign: label ? String(label).substring(0, 12) : '',
          showLabel: !!label,
          size: 28,
        });
      } else {
        icon = buildManualMarkerIcon(data);
      }
    }
    var marker = L.marker(latlng, { icon: icon });
    marker._atakId = id;
    marker._atakLayerId = layerId;
    marker._atakData = data;
    marker._atakGrid = { lng: lng, lat: lat };
    marker.bindPopup(popupHtml);
    bindMarkerContextMenu(marker, id);
    marker.addTo(layer);
    markersById[id] = marker;
  }

  function getMarkerById(id) {
    var m = markersById[id];
    if (!m) return null;
    var data = m._atakData || {};
    var grid = m._atakGrid || {};
    return {
      id: id,
      layerId: m._atakLayerId,
      data: data,
      gridLng: grid.lng,
      gridLat: grid.lat
    };
  }

  function updateMarkerById(id, markerData, layerId) {
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var existing = getMarkerById(id);
    var data = Object.assign({}, (existing && existing.data) || {}, markerData || {});
    var lid = layerId != null ? layerId : (existing ? existing.layerId : 1);
    addOrUpdateMarker({ id: id, layerId: lid, data: data });
    if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
    if (!base || String(id).indexOf('local_') === 0) {
      return Promise.resolve({ id: id, layerId: lid, markerData: data });
    }
    return fetch(base + '/api/markers/' + encodeURIComponent(id), {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ markerData: data, layerId: lid })
    }).then(function (r) {
      if (!r.ok) throw new Error('update');
      return r.json();
    }).then(function (row) {
      if (!row) return null;
      var parsed = typeof row.markerData === 'string'
        ? (function () { try { return JSON.parse(row.markerData); } catch (e) { return data; } })()
        : (row.markerData || data);
      addOrUpdateMarker({
        id: row.id,
        layerId: row.layerId != null ? row.layerId : lid,
        data: parsed
      });
      if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
      return row;
    });
  }

  function listMarkers() {
    return Object.keys(markersById).map(function (k) {
      var m = markersById[k];
      var data = m._atakData || {};
      var grid = m._atakGrid || {};
      var ll = m.getLatLng ? m.getLatLng() : null;
      return {
        id: k,
        layerId: m._atakLayerId,
        data: data,
        gridLng: grid.lng != null ? grid.lng : (ll ? ll.lng : null),
        gridLat: grid.lat != null ? grid.lat : (ll ? ll.lat : null)
      };
    });
  }

  function focusMarker(id) {
    var m = markersById[id];
    if (!m || !map) return;
    var ll = m.getLatLng();
    if (ll) {
      map.setView(ll, Math.max(map.getZoom(), 4));
      if (m.openPopup) m.openPopup();
    }
  }

  function deleteMarkerById(id) {
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    if (!base || String(id).indexOf('local_') === 0) {
      removeMarker({ id: id });
      return Promise.resolve(true);
    }
    return fetch(base + '/api/markers/' + encodeURIComponent(id), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      if (!r.ok && r.status !== 204) throw new Error('delete');
      removeMarker({ id: id });
      return true;
    });
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
    if (!marker._atakCtxBound) {
      marker._atakCtxBound = true;
      marker.on('contextmenu', function (e) {
        emitFeatureContextMenu({
          featureType: 'ping',
          id: id,
          label: 'Ping',
          data: { author: author, message: message || '' }
        }, e);
      });
    }
    marker.addTo(pingTempLayer);
    pingTempMarkersById[id] = marker;
    setTimeout(function () {
      if (pingTempMarkersById[id] && pingTempLayer) {
        pingTempLayer.removeLayer(pingTempMarkersById[id]);
        delete pingTempMarkersById[id];
      }
    }, 30000);
  }

  function removeTemporaryPingMarker(id) {
    if (!id || !pingTempMarkersById[id]) return false;
    if (pingTempLayer) {
      try { pingTempLayer.removeLayer(pingTempMarkersById[id]); } catch (e) {}
    }
    delete pingTempMarkersById[id];
    return true;
  }

  function pollMarkers() {
    if (!map) return;
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var mapId = window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
    var url = (base || '') + '/api/atak/markers?mapId=' + mapId;
    fetch(url, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (list) {
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
        if (!seen[k] && String(k).indexOf('local_') !== 0) removeMarker({ id: k });
      });
      if (window.ATAKMarkers && window.ATAKMarkers.renderFromMap) {
        window.ATAKMarkers.renderFromMap();
      }
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

  function setUnitsMarkers(list) {
    if (!map) return;
    if (!unitsLayer) unitsLayer = L.layerGroup().addTo(map);
    var nato = window.NatoSidcIcons;
    var seen = {};
    (Array.isArray(list) ? list : []).forEach(function (u) {
      var id = 'unit_' + (u.id != null ? u.id : (u.call_sign || Math.random()));
      seen[id] = true;
      var x = u.pos_x != null ? parseFloat(u.pos_x) : NaN;
      var y = u.pos_y != null ? parseFloat(u.pos_y) : NaN;
      if (isNaN(x) || isNaN(y)) {
        var gridRef = String(u.grid_ref || '').trim().split(/\s+/);
        x = parseFloat(gridRef[0]);
        y = parseFloat(gridRef[1]);
      }
      if (isNaN(x) || isNaN(y)) return;
      var applied = applyOffset(y, x);
      var latlng = L.latLng(applied[0], applied[1]);
      var extra = {};
      try {
        if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
        else if (u.extra && typeof u.extra === 'object') extra = u.extra;
      } catch (e) {}
      var aff = extra.affiliation || extra.affil || u.affiliation || 'friend';
      var health = String(extra.health || u.health || '').toLowerCase();
      var healthClass = '';
      if (health === 'wounded' || health === 'injured') healthClass = 'nato-sidc--wounded';
      if (health === 'unconscious' || health === 'cardiac_arrest' || health === 'cardiac-arrest' || health === 'dead' || health === 'kia') {
        healthClass = 'nato-sidc--critical';
      }
      var iconOpts = {
        affiliation: aff,
        role: u.role || extra.role || '',
        callSign: u.call_sign || '',
        heading: u.heading,
        showLabel: true,
        size: 34,
        health: health,
        className: healthClass,
      };
      var icon = nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, iconOpts)
        : L.divIcon({
            className: 'atak-unit-fallback ' + healthClass,
            html: '<span style="background:#3b82f6;color:#fff;padding:2px 5px;font-size:10px;border-radius:2px;">' + (u.call_sign || '?') + '</span>',
            iconSize: [70, 20],
            iconAnchor: [35, 10],
          });
      if (!unitsById[id]) {
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: 400 });
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
          window.ATAKUnitPopup.bindUnit(marker, u);
        } else {
          marker.bindPopup('<strong>' + (u.call_sign || '—') + '</strong><br/>' + (u.role || '') + '<br/>' + (u.grid_ref || ''));
        }
        marker.addTo(unitsLayer);
        unitsById[id] = marker;
      } else {
        unitsById[id].setLatLng(latlng);
        unitsById[id].setIcon(icon);
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
          window.ATAKUnitPopup.bindUnit(unitsById[id], u);
        }
      }
    });
    Object.keys(unitsById).forEach(function (k) {
      if (!seen[k]) {
        unitsLayer.removeLayer(unitsById[k]);
        delete unitsById[k];
      }
    });
  }

  function setAirAssets(assets) {
    if (!map || !Array.isArray(assets)) return;
    if (!airAssetsLayer) airAssetsLayer = L.layerGroup().addTo(map);
    var nato = window.NatoSidcIcons;
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
      var aff = 'friend';
      if (side === 'EAST') aff = 'hostile';
      else if (side === 'GUER' || side === 'CIV' || status === 'SUSPECT') aff = 'unknown';
      var icon = nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, {
            affiliation: aff,
            aircraftType: a.aircraft_type || 'plane',
            role: a.model || a.aircraft_type || '',
            callSign: a.callsign || '',
            showLabel: true,
            size: 36,
          })
        : L.divIcon({
            className: 'atak-air-asset-marker',
            html: '<span style="color:#3b82f6;font-size:16px;font-weight:bold;">▲</span>',
            iconSize: [20, 20],
            iconAnchor: [10, 10],
          });
      if (!airAssetsById[id]) {
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: 500 });
        marker._atakAirId = id;
        marker.addTo(airAssetsLayer);
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindAir) {
          window.ATAKUnitPopup.bindAir(marker, a);
        } else {
          marker.bindPopup('<strong>' + (a.callsign || '—') + '</strong><br/>' + (a.model || '') + '<br/>' + status);
        }
        airAssetsById[id] = marker;
      } else {
        airAssetsById[id].setLatLng(latlng);
        airAssetsById[id].setIcon(icon);
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindAir) {
          window.ATAKUnitPopup.bindAir(airAssetsById[id], a);
        }
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
    listMarkers: listMarkers,
    getMarkerById: getMarkerById,
    focusMarker: focusMarker,
    deleteMarkerById: deleteMarkerById,
    updateMarkerById: updateMarkerById,
    setAirAssets: setAirAssets,
    setUnitsMarkers: setUnitsMarkers,
    removeMarker: removeMarker,
    addOrUpdateLayer: addOrUpdateLayer,
    removeLayer: removeLayer,
    pointMap: pointMap,
    endPointMap: endPointMap,
    centerOn: centerOn,
    addTemporaryPingMarker: addTemporaryPingMarker,
    removeTemporaryPingMarker: removeTemporaryPingMarker
  };
})();
