/* COMSPEC ATAK — Zone possible depuis la dernière position connue (à pied / véhicule). */
window.ATAKReachOverlay = (function () {
  var FOOT_KPH = 5;
  var VEHICLE_KPH = 40;
  var MAX_AGE_SEC = 15 * 60;
  var MIN_RADIUS_M = 25;
  var TICK_MS = 1000;
  var RING_STEPS = 72;

  var selectedKey = '';
  var selectedUnit = null;
  var layer = null;
  var footRing = null;
  var vehicleRing = null;
  var lastKnownDot = null;
  var legendEl = null;
  var tickTimer = null;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function unitKey(u) {
    if (!u) return '';
    if (u.id != null && String(u.id) !== '') return 'id:' + String(u.id);
    var cs = String(u.call_sign || u.callsign || '').trim().toUpperCase();
    return cs ? 'cs:' + cs : '';
  }

  function parseCoords(u) {
    if (window.ATAKUnits && typeof window.ATAKUnits.parseCoords === 'function') {
      return window.ATAKUnits.parseCoords(u);
    }
    var x = u && u.pos_x != null && u.pos_x !== '' ? parseFloat(u.pos_x) : NaN;
    var y = u && u.pos_y != null && u.pos_y !== '' ? parseFloat(u.pos_y) : NaN;
    if (isNaN(x) || isNaN(y)) {
      var parts = String((u && u.grid_ref) || '').trim().split(/\s+/);
      if (parts.length >= 2) {
        x = parseFloat(parts[0]);
        y = parseFloat(parts[1]);
      }
    }
    return { x: x, y: y };
  }

  function latLngOf(u) {
    var c = parseCoords(u);
    if (isNaN(c.x) || isNaN(c.y)) return null;
    if (window.ATAKMap && typeof window.ATAKMap.latLngFromWorld === 'function') {
      return window.ATAKMap.latLngFromWorld(c.x, c.y);
    }
    return window.L ? window.L.latLng(c.y, c.x) : null;
  }

  function radiusM(kph, elapsedSec) {
    var age = Math.max(0, Math.min(MAX_AGE_SEC, Number(elapsedSec) || 0));
    var r = (Number(kph) / 3.6) * age;
    var cap = (Number(kph) / 3.6) * MAX_AGE_SEC;
    if (!isFinite(r) || r < MIN_RADIUS_M) return MIN_RADIUS_M;
    return Math.min(cap, r);
  }

  function formatDist(m) {
    if (!isFinite(m) || m < 0) return '—';
    if (m < 1000) return Math.round(m) + ' m';
    return (m / 1000).toFixed(m < 10000 ? 2 : 1).replace('.', ',') + ' km';
  }

  function ringLatLngs(ll, radiusMVal) {
    var pts = [];
    var n = RING_STEPS;
    var r = Number(radiusMVal) || 0;
    for (var i = 0; i <= n; i++) {
      var a = (i / n) * Math.PI * 2;
      pts.push([ll.lat + Math.sin(a) * r, ll.lng + Math.cos(a) * r]);
    }
    return pts;
  }

  function ensureLayer() {
    var map = getMap();
    if (!map || !window.L) return null;
    if (!layer) {
      layer = window.L.layerGroup().addTo(map);
    }
    return layer;
  }

  function ensureLegend() {
    if (legendEl && legendEl.isConnected) return legendEl;
    var wrap = document.querySelector('.atak-map-wrap');
    if (!wrap) return null;
    legendEl = document.createElement('div');
    legendEl.id = 'atak-reach-legend';
    legendEl.className = 'atak-reach-legend';
    legendEl.hidden = true;
    legendEl.addEventListener('click', function (ev) {
      if (!ev.target.closest('[data-atak-reach-clear]')) return;
      ev.preventDefault();
      ev.stopPropagation();
      clear(true);
    });
    wrap.appendChild(legendEl);
    return legendEl;
  }

  function liveStatus(u) {
    if (window.ATAKUnits && typeof window.ATAKUnits.resolveLiveStatus === 'function') {
      return window.ATAKUnits.resolveLiveStatus(u);
    }
    return String((u && u.status) || '').toLowerCase();
  }

  function displayName(u) {
    var ex = {};
    try {
      ex = typeof u.extra === 'string' ? JSON.parse(u.extra) : (u.extra || {});
    } catch (e) {
      ex = {};
    }
    var P = window.ATAKUnitPopup;
    if (P && P.phoneDisplayName) return P.phoneDisplayName(u, ex);
    return u.display_call_sign || u.call_sign || u.callsign || 'Contact';
  }

  function ageLabel(u) {
    if (window.ATAKUnits && typeof window.ATAKUnits.formatAgeFr === 'function') {
      return window.ATAKUnits.formatAgeFr(window.ATAKUnits.unitAgeSeconds(u));
    }
    return '';
  }

  function refreshFromRoster() {
    if (!selectedKey) return;
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnitByKey === 'function') {
      var fresh = window.ATAKUnits.getUnitByKey(selectedKey);
      if (fresh) selectedUnit = fresh;
    } else if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      var list = window.ATAKUnits.getUnits() || [];
      for (var i = 0; i < list.length; i++) {
        if (unitKey(list[i]) === selectedKey) {
          selectedUnit = list[i];
          break;
        }
      }
    }
    paint(false);
  }

  function paint(center) {
    if (!selectedUnit) return;
    var map = getMap();
    var lg = ensureLayer();
    if (!map || !lg || !window.L) return;
    var ll = latLngOf(selectedUnit);
    if (!ll) {
      syncSelectionClass();
      return;
    }
    var age = window.ATAKUnits && typeof window.ATAKUnits.unitAgeSeconds === 'function'
      ? window.ATAKUnits.unitAgeSeconds(selectedUnit)
      : 0;
    var footR = radiusM(FOOT_KPH, age);
    var vehR = radiusM(VEHICLE_KPH, age);
    var footPts = ringLatLngs(ll, footR);
    var vehPts = ringLatLngs(ll, vehR);
    if (!vehicleRing) {
      vehicleRing = window.L.polygon(vehPts, {
        color: '#f59e0b',
        weight: 2,
        dashArray: '7 5',
        fillColor: '#f59e0b',
        fillOpacity: 0.08,
        interactive: false,
        className: 'atak-reach-ring atak-reach-ring--vehicle'
      }).addTo(lg);
    } else {
      vehicleRing.setLatLngs(vehPts);
    }
    if (!footRing) {
      footRing = window.L.polygon(footPts, {
        color: '#4ade80',
        weight: 2,
        dashArray: '4 4',
        fillColor: '#22c55e',
        fillOpacity: 0.14,
        interactive: false,
        className: 'atak-reach-ring atak-reach-ring--foot'
      }).addTo(lg);
    } else {
      footRing.setLatLngs(footPts);
    }
    if (!lastKnownDot) {
      lastKnownDot = window.L.circleMarker(ll, {
        radius: 5,
        color: '#e2e8f0',
        weight: 2,
        fillColor: '#94a3b8',
        fillOpacity: 0.95,
        interactive: false,
        className: 'atak-reach-last-known'
      }).addTo(lg);
    } else {
      lastKnownDot.setLatLng(ll);
    }

    var status = liveStatus(selectedUnit);
    var statusFr = (window.ATAKUnitPopup && window.ATAKUnitPopup.statusLabelFr)
      ? window.ATAKUnitPopup.statusLabelFr(status)
      : status;
    var seen = ageLabel(selectedUnit);
    var grid = '';
    if (window.ATAKUnits && typeof window.ATAKUnits.formatGrid === 'function') {
      grid = window.ATAKUnits.formatGrid(selectedUnit);
    } else {
      grid = String(selectedUnit.grid_ref || '').trim();
    }
    var legend = ensureLegend();
    if (legend) {
      legend.hidden = false;
      legend.innerHTML =
        '<div class="atak-reach-legend__head">' +
          '<strong>' + esc(displayName(selectedUnit)) + '</strong>' +
          '<button type="button" class="atak-reach-legend__close" data-atak-reach-clear title="Retirer la zone">Fermer</button>' +
        '</div>' +
        '<p class="atak-reach-legend__status">' + esc(statusFr) + (seen ? ' · ' + esc(seen) : '') + '</p>' +
        (grid ? '<p class="atak-reach-legend__grid">Dernière position connue · ' + esc(grid) + '</p>' : '') +
        '<ul class="atak-reach-legend__rings">' +
          '<li><span class="atak-reach-swatch atak-reach-swatch--foot"></span>À pied (5 km/h) · ' + esc(formatDist(footR)) + '</li>' +
          '<li><span class="atak-reach-swatch atak-reach-swatch--vehicle"></span>Véhicule (40 km/h) · ' + esc(formatDist(vehR)) + '</li>' +
        '</ul>' +
        '<p class="atak-reach-legend__hint">La zone s’agrandit tant que le contact n’a pas repris la liaison, jusqu’à quinze minutes.</p>';
    }

    if (center && window.ATAKMap) {
      var c = parseCoords(selectedUnit);
      var map = getMap();
      if (map && vehicleRing && typeof map.fitBounds === 'function') {
        try {
          map.fitBounds(vehicleRing.getBounds(), { padding: [48, 48], maxZoom: 5, animate: true });
        } catch (eFit) {
          if (typeof window.ATAKMap.centerOn === 'function') window.ATAKMap.centerOn(c.y, c.x);
        }
      } else if (typeof window.ATAKMap.centerOn === 'function') {
        window.ATAKMap.centerOn(c.y, c.x);
      }
    }
    syncSelectionClass();
  }

  function startTick() {
    stopTick();
    tickTimer = setInterval(function () {
      refreshFromRoster();
    }, TICK_MS);
  }

  function stopTick() {
    if (tickTimer) {
      clearInterval(tickTimer);
      tickTimer = null;
    }
  }

  function syncSelectionClass() {
    var key = selectedKey;
    document.querySelectorAll('.atak-drawer-row[data-unit-id], .atak-unit-card[data-unit-id], .atak-drawer-row[data-callsign], .atak-unit-card[data-callsign]').forEach(function (el) {
      var id = el.getAttribute('data-unit-id') || '';
      var cs = String(el.getAttribute('data-callsign') || '').trim().toUpperCase();
      var match = false;
      if (key) {
        if (id && key === 'id:' + id) match = true;
        if (cs && key === 'cs:' + cs) match = true;
      }
      el.classList.toggle('is-selected', match);
    });
  }

  function select(u, opts) {
    opts = opts || {};
    if (!u) {
      clear(true);
      return;
    }
    selectedUnit = u;
    selectedKey = unitKey(u);
    if (!selectedKey) {
      clear(true);
      return;
    }
    paint(opts.center !== false);
    startTick();
  }

  function toggleFromUnit(u, opts) {
    if (!u) return;
    var key = unitKey(u);
    if (key && key === selectedKey) {
      clear(true);
      return;
    }
    select(u, opts || { center: true });
  }

  function selectByEntity(entity) {
    if (!entity) return;
    var id = entity.id != null ? String(entity.id) : '';
    var cs = String(entity.callsign || entity.call_sign || '').trim();
    var u = null;
    if (window.ATAKUnits) {
      if (id && typeof window.ATAKUnits.getUnitById === 'function') {
        u = window.ATAKUnits.getUnitById(id);
      }
      if (!u && typeof window.ATAKUnits.getUnitByKey === 'function') {
        u = window.ATAKUnits.getUnitByKey(id ? 'id:' + id : (cs ? 'cs:' + cs.toUpperCase() : ''));
      }
      if (!u && typeof window.ATAKUnits.getUnits === 'function') {
        var list = window.ATAKUnits.getUnits() || [];
        for (var i = 0; i < list.length; i++) {
          if ((id && String(list[i].id) === id) || (cs && String(list[i].call_sign || '').toUpperCase() === cs.toUpperCase())) {
            u = list[i];
            break;
          }
        }
      }
    }
    if (u) select(u, { center: false });
  }

  function clear(syncDom) {
    selectedKey = '';
    selectedUnit = null;
    stopTick();
    if (layer) {
      try { layer.clearLayers(); } catch (e) {}
    }
    footRing = null;
    vehicleRing = null;
    lastKnownDot = null;
    if (legendEl) {
      legendEl.hidden = true;
      legendEl.innerHTML = '';
    }
    if (syncDom !== false) syncSelectionClass();
  }

  function selectedKeyFn() {
    return selectedKey;
  }

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && selectedKey) {
      clear(true);
    }
  });
  window.addEventListener('atak:units-updated', function () {
    if (selectedKey) refreshFromRoster();
  });
  window.addEventListener('atak:entity-selected', function (ev) {
    if (ev && ev.detail) selectByEntity(ev.detail);
  });

  return {
    select: select,
    toggleFromUnit: toggleFromUnit,
    clear: clear,
    selectedKey: selectedKeyFn,
    syncSelectionClass: syncSelectionClass,
    refresh: refreshFromRoster
  };
})();
