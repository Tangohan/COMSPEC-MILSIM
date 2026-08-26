/* COMSPEC ATAK — LOT 5 calques SSE (dossiers, PIR, ordres, photos, tracés, historique) */
(function () {
  'use strict';

    var LAYER_IDS = ['cases', 'pir', 'taskings', 'photos', 'tracks', 'ghost_tracks', 'history', 'intel'];
  var sseLayerGroups = {};
  var trailBuffers = {};
  var ghostBuffers = {};
  var trailLayer = null;
  var ghostTrailLayer = null;
  var lossLayer = null;
  var reachLayer = null;
  var pollTimer = null;
  var TRAIL_MAX = 48;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : '';
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId
      ? window.ATAKSocket.getMapId()
      : 1;
  }

  function prefs() {
    return window.ATAKMap && window.ATAKMap.getDisplayPrefs
      ? window.ATAKMap.getDisplayPrefs()
      : {};
  }

  function applyOffset(lat, lng) {
    if (window.ATAKMap && window.ATAKMap.applyOffset) {
      return window.ATAKMap.applyOffset(lat, lng);
    }
    return [lat, lng];
  }

  function ensureGroups(map) {
    LAYER_IDS.forEach(function (id) {
      if (!sseLayerGroups[id]) {
        sseLayerGroups[id] = L.layerGroup();
      }
      if (map && !map.hasLayer(sseLayerGroups[id])) {
        sseLayerGroups[id].addTo(map);
      }
    });
    if (!trailLayer) trailLayer = L.layerGroup();
    if (!ghostTrailLayer) ghostTrailLayer = L.layerGroup();
    if (!lossLayer) lossLayer = L.layerGroup();
    if (!reachLayer) reachLayer = L.layerGroup();
    if (map && !map.hasLayer(trailLayer)) trailLayer.addTo(map);
    if (map && !map.hasLayer(ghostTrailLayer)) ghostTrailLayer.addTo(map);
    if (map && !map.hasLayer(lossLayer)) lossLayer.addTo(map);
    if (map && !map.hasLayer(reachLayer)) reachLayer.addTo(map);
  }

  function layerVisible(id) {
    var p = prefs();
    var key = 'showSseLayer_' + id;
    if (Object.prototype.hasOwnProperty.call(p, key)) {
      return !!p[key];
    }
    // Défauts : dossiers + photos + PIR + taskings on ; tracks/ghost/history off sauf tracks
    if (id === 'tracks') return p.showSseTracks !== false;
    if (id === 'ghost_tracks') return !!p.showSseGhostTracks;
    if (id === 'history') return !!p.showSseHistory;
    return p['showSseLayer_' + id] !== false;
  }

  function pointMarker(p) {
    var color = p.color || '#34d399';
    var S = window.ATAKMarkerSizes;
    var html = '<span class="atak-sse-layer-marker__dot" style="background:' + color + '"></span>';
    var icon = S && S.divIcon
      ? S.divIcon(L, html, 'small', { className: 'atak-sse-layer-marker atak-compact-marker' })
      : L.divIcon({
          className: 'atak-sse-layer-marker atak-compact-marker',
          html: html,
          iconSize: [14, 14],
          iconAnchor: [7, 7]
        });
    var applied = applyOffset(p.pos_y, p.pos_x);
    var latlng = L.latLng(applied[0], applied[1]);
    var popup = '<div class="atak-marker-popup__kind">' + String(p.layer || 'SSE').replace(/</g, '&lt;') + '</div>'
      + '<strong>' + String(p.case_ref || '').replace(/</g, '&lt;') + '</strong>'
      + (p.case_title ? '<br/>' + String(p.case_title).replace(/</g, '&lt;') : '')
      + '<br/><em>' + String(p.label || '').replace(/</g, '&lt;') + '</em>'
      + (p.note ? '<p style="margin:.4rem 0 0">' + String(p.note).replace(/</g, '&lt;') + '</p>' : '');
    if (p.photo_url) {
      var url = String(p.photo_url);
      if (url.indexOf('http') !== 0 && url.charAt(0) !== '/') url = '/' + url;
      if (url.indexOf('http') !== 0) url = apiBase() + url;
      popup += '<p style="margin:.5rem 0 0"><img src="' + url.replace(/"/g, '&quot;')
        + '" alt="Photo terrain" style="max-width:220px;max-height:160px;display:block;" /></p>';
    }
    var marker = L.marker(latlng, { icon: icon }).bindPopup(popup);
    if (window.ATAKMarkerSizes && window.ATAKMarkerSizes.bindHoverTip) {
      window.ATAKMarkerSizes.bindHoverTip(marker, window.ATAKMarkerSizes.hoverTipHtml(p.label || 'SSE', [
        p.case_ref || '',
        p.case_title || ''
      ]));
      window.ATAKMarkerSizes.bindSelectVisual(marker);
    }
    return marker;
  }

  function renderPayload(payload) {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || !window.L) return;
    ensureGroups(map);

    LAYER_IDS.forEach(function (id) {
      if (sseLayerGroups[id]) sseLayerGroups[id].clearLayers();
    });

    var layers = (payload && payload.layers) ? payload.layers : [];
    if (!layers.length && payload && payload.points) {
      layers = [{ id: 'cases', points: payload.points, polylines: [] }];
    }

    layers.forEach(function (layer) {
      var id = layer.id || 'cases';
      var group = sseLayerGroups[id];
      if (!group) return;
      if (!layerVisible(id)) return;
      (layer.points || []).forEach(function (p) {
        var x = parseFloat(p.pos_x);
        var y = parseFloat(p.pos_y);
        if (isNaN(x) || isNaN(y)) return;
        pointMarker(p).addTo(group);
      });
      (layer.polylines || []).forEach(function (line) {
        var pts = (line.points || []).map(function (pt) {
          var applied = applyOffset(pt.pos_y, pt.pos_x);
          return [applied[0], applied[1]];
        }).filter(function (ll) {
          return !isNaN(ll[0]) && !isNaN(ll[1]);
        });
        if (pts.length < 2) return;
        L.polyline(pts, {
          color: line.color || '#67e8f9',
          weight: line.dashed ? 2 : 2.5,
          opacity: line.dashed ? 0.55 : 0.8,
          dashArray: line.dashed ? '6 8' : null,
          interactive: true
        }).bindPopup(String(line.label || 'Tracé').replace(/</g, '&lt;')).addTo(group);
      });
    });
  }

  function refresh() {
    var p = prefs();
    if (p.showSseOverlay === false) {
      LAYER_IDS.forEach(function (id) {
        if (sseLayerGroups[id]) sseLayerGroups[id].clearLayers();
      });
      return;
    }
    var base = apiBase();
    if (!base) return;
    fetch(base + '/api/atak/sse-case-overlay?mapId=' + encodeURIComponent(mapId()), {
      credentials: 'include',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(renderPayload)
      .catch(function () { /* silencieux */ });
  }

  function pushTrail(unitKey, sample, isGhost) {
    if (!unitKey || !sample) return;
    var bag = isGhost ? ghostBuffers : trailBuffers;
    var buf = bag[unitKey] || [];
    var last = buf.length ? buf[buf.length - 1] : null;
    if (last && last.lat === sample.lat && last.lng === sample.lng && sample.live === last.live) return;
    if (last && last.live === 'linked' && (sample.live === 'delayed' || sample.live === 'offline')) {
      sample.loss = true;
    }
    if (sample.live === 'offline') sample.loss = true;
    buf.push(sample);
    if (buf.length > TRAIL_MAX) buf = buf.slice(buf.length - TRAIL_MAX);
    bag[unitKey] = buf;
  }

  function lossIcon() {
    var html = '<span class="atak-trail-loss__x" title="Perte de liaison">✕</span>';
    var S = window.ATAKMarkerSizes;
    if (S && S.divIcon) {
      return S.divIcon(L, html, 'small', { className: 'atak-trail-loss atak-compact-marker' });
    }
    return L.divIcon({
      className: 'atak-trail-loss',
      html: html,
      iconSize: [18, 18],
      iconAnchor: [9, 9]
    });
  }

  function drawReach(latlng, kind, elapsedSec, lastSpeed, heading) {
    if (!reachLayer || !latlng) return;
    var M = window.ATAKMotion;
    var r = M && M.reachRadiusM ? M.reachRadiusM(kind, elapsedSec, lastSpeed) : 0;
    if (!(r > 12)) return;
    var color = M && M.trackColor ? M.trackColor(kind, false) : '#fbbf24';
    L.circle(latlng, {
      radius: r,
      color: color,
      weight: 1.4,
      opacity: 0.55,
      dashArray: '5 7',
      fillColor: color,
      fillOpacity: 0.07,
      interactive: false,
      className: 'atak-trail-reach'
    }).addTo(reachLayer);
    if (heading != null && isFinite(Number(heading))) {
      var rad = (Number(heading) * Math.PI) / 180;
      var to = L.latLng(latlng.lat + Math.cos(rad) * r, latlng.lng + Math.sin(rad) * r);
      L.polyline([latlng, to], {
        color: color,
        weight: 1.5,
        opacity: 0.45,
        dashArray: '2 6',
        interactive: false
      }).addTo(reachLayer);
    }
  }

  function renderColoredTrail(pts) {
    if (!pts || pts.length < 2 || !trailLayer) return;
    var M = window.ATAKMotion;
    var i;
    for (i = 1; i < pts.length; i++) {
      var a = pts[i - 1];
      var b = pts[i];
      var dt = ((b.t || 0) - (a.t || 0)) / 1000;
      var kind = b.kind || a.kind || 'infantry';
      var gapLimit = M && M.gapSecFor ? M.gapSecFor(kind) : 15;
      var lost = a.live === 'offline' || b.live === 'offline';
      var doubt = lost || a.live === 'delayed' || b.live === 'delayed' || dt >= gapLimit * 0.55;
      var color = M && M.trackColor ? M.trackColor(kind, lost) : (lost ? '#f87171' : '#22d3ee');
      L.polyline([[a.lat, a.lng], [b.lat, b.lng]], {
        color: color,
        weight: lost ? 2.2 : 2.4,
        opacity: doubt ? 0.62 : 0.88,
        dashArray: doubt ? '5 7' : null,
        lineCap: 'round',
        interactive: false,
        className: doubt ? 'atak-unit-trail atak-unit-trail--doubt' : 'atak-unit-trail'
      }).addTo(trailLayer);
    }
  }

  function renderUnitTrails() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map) return;
    ensureGroups(map);
    var p = prefs();
    if (trailLayer) trailLayer.clearLayers();
    if (ghostTrailLayer) ghostTrailLayer.clearLayers();
    if (lossLayer) lossLayer.clearLayers();
    if (reachLayer) reachLayer.clearLayers();
    if (p.showUnitTrails !== false) {
      Object.keys(trailBuffers).forEach(function (key) {
        var pts = trailBuffers[key];
        if (!pts || !pts.length) return;
        renderColoredTrail(pts);
        var last = pts[pts.length - 1];
        var now = Date.now();
        var elapsed = last.t ? (now - last.t) / 1000 : 0;
        var kind = last.kind || 'infantry';
        pts.forEach(function (pt) {
          if (!pt.loss) return;
          L.marker(L.latLng(pt.lat, pt.lng), {
            icon: lossIcon(),
            interactive: false,
            zIndexOffset: 420
          }).addTo(lossLayer);
        });
        if (last.live === 'delayed' || last.live === 'offline') {
          var heading = last.heading;
          drawReach(L.latLng(last.lat, last.lng), kind, elapsed, last.speed, heading);
          if (!last.loss) {
            L.marker(L.latLng(last.lat, last.lng), {
              icon: lossIcon(),
              interactive: false,
              zIndexOffset: 420
            }).addTo(lossLayer);
          }
        }
      });
    }
    if (p.showSseGhostTracks || p.showUnitGhostTrails) {
      Object.keys(ghostBuffers).forEach(function (key) {
        var pts = ghostBuffers[key];
        if (!pts || pts.length < 2) return;
        var latlngs = pts.map(function (s) { return [s.lat, s.lng]; });
        L.polyline(latlngs, {
          color: '#94a3b8', weight: 2, opacity: 0.5, dashArray: '5 7', interactive: false
        }).addTo(ghostTrailLayer);
      });
    }
    var legend = document.getElementById('atak-trail-legend');
    if (legend) legend.hidden = p.showUnitTrails === false;
  }

  function onUnits(list) {
    if (!Array.isArray(list)) return;
    var p = prefs();
    if (p.showUnitTrails === false && !p.showUnitGhostTrails && !p.showSseGhostTracks) return;
    var M = window.ATAKMotion;
    list.forEach(function (u) {
      var live = 'live';
      if (window.ATAKUnits && window.ATAKUnits.resolveLiveStatus) {
        live = window.ATAKUnits.resolveLiveStatus(u);
      } else {
        live = String((u && u.status) || '').toLowerCase();
      }
      var id = 'unit_' + (u.id != null ? u.id : (u.call_sign || ''));
      var x = u.pos_x != null ? parseFloat(u.pos_x) : NaN;
      var y = u.pos_y != null ? parseFloat(u.pos_y) : NaN;
      if (isNaN(x) || isNaN(y)) return;
      if (Math.abs(x) < 0.5 && Math.abs(y) < 0.5) return;
      var applied = applyOffset(y, x);
      var kind = M && M.trackKind ? M.trackKind(u) : 'infantry';
      var heading = M ? M.num(u.movement_heading) : null;
      if (heading == null && M && M.isPhone && !M.isPhone(u)) heading = M.num(u.heading);
      var speed = M ? M.num((u.motion && u.motion.speed_current) || u.speed) : null;
      var sample = {
        lat: applied[0],
        lng: applied[1],
        t: Date.now(),
        live: live,
        kind: kind,
        heading: heading,
        speed: speed
      };
      if (live === 'offline' && (p.showUnitGhostTrails || p.showSseGhostTracks)) {
        pushTrail(id, sample, true);
      }
      pushTrail(id, sample, false);
    });
    renderUnitTrails();
  }

  function startPolling() {
    if (pollTimer) return;
    refresh();
    pollTimer = setInterval(refresh, 20000);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  window.addEventListener('atak:mapready', function () {
    ensureGroups(window.ATAKMap.getMap());
    startPolling();
    renderUnitTrails();
  });

  window.addEventListener('atak:display-prefs-changed', function () {
    refresh();
    renderUnitTrails();
  });

  // Hook léger : après setUnitsMarkers via événement optionnel, ou interception.
  var origSetUnits = null;
  function hookUnits() {
    if (!window.ATAKMap || !window.ATAKMap.setUnitsMarkers || origSetUnits) return;
    origSetUnits = window.ATAKMap.setUnitsMarkers;
    window.ATAKMap.setUnitsMarkers = function (list, opts) {
      var ret = origSetUnits(list, opts);
      try { onUnits(list); } catch (e) { /* ignore */ }
      return ret;
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hookUnits);
  } else {
    hookUnits();
  }
  setTimeout(hookUnits, 500);

  function clearTrails() {
    trailBuffers = {};
    ghostBuffers = {};
    renderUnitTrails();
  }

  window.ATAKSseLayers = {
    refresh: refresh,
    startPolling: startPolling,
    stopPolling: stopPolling,
    renderPayload: renderPayload,
    clearTrails: clearTrails
  };
})();
