/* Calque des mesures de contrôle du plan de mission. */
(function () {
  'use strict';

  var group = null;
  var lastSig = '';

  function prefs() {
    return window.ATAKMap && window.ATAKMap.getDisplayPrefs
      ? window.ATAKMap.getDisplayPrefs()
      : {};
  }
  function styleFor(state) {
    if (state === 'current') {
      return { color: '#38bdf8', weight: 3, opacity: 1, dashArray: null };
    }
    if (state === 'completed') {
      return { color: '#64748b', weight: 2, opacity: 0.35, dashArray: null };
    }
    if (state === 'modified') {
      return { color: '#fbbf24', weight: 3, opacity: 0.95, dashArray: '6,4,2,4' };
    }
    return { color: '#94a3b8', weight: 2, opacity: 0.45, dashArray: '8,6' };
  }
  function toLatLng(x, y) {
    if (x == null || y == null || !window.ATAKMap || !window.ATAKMap.latLngFromWorld) return null;
    return window.ATAKMap.latLngFromWorld(x, y);
  }
  function ensureGroup(map) {
    if (!group) group = L.layerGroup();
    if (map && !map.hasLayer(group)) group.addTo(map);
    return group;
  }
  function labelMarker(ll, code, state) {
    return L.marker(ll, {
      interactive: false,
      keyboard: false,
      icon: L.divIcon({
        className: 'atak-mission-overlay-label atak-mission-overlay-label--' + state + ' atak-compact-marker',
        html: '<span>' + String(code || '').replace(/</g, '') + '</span>',
        iconSize: [88, 20],
        iconAnchor: [44, 10]
      })
    });
  }
  function draw(detail) {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map) return;
    var g = ensureGroup(map);
    g.clearLayers();
    if (prefs().showMissionOverlay === false) return;
    if (!detail || !detail.plan) return;
    var graphics = (detail.overlay && detail.overlay.graphics) || [];
    var sig = JSON.stringify(graphics) + JSON.stringify((detail.overlay && detail.overlay.routes) || []);
    lastSig = sig;
    graphics.forEach(function (item) {
      if (!item || !item.placed) return;
      var st = styleFor(item.draw_state);
      var opts = { color: st.color, weight: st.weight, opacity: st.opacity, dashArray: st.dashArray, interactive: false };
      if (item.geom_type === 'line' && item.path && item.path.length >= 2) {
        var latlngs = [];
        item.path.forEach(function (pt) {
          var ll = toLatLng(pt.x, pt.y);
          if (ll) latlngs.push(ll);
        });
        if (latlngs.length >= 2) {
          L.polyline(latlngs, opts).addTo(g);
          labelMarker(latlngs[0], item.code, item.draw_state).addTo(g);
        }
        return;
      }
      var pt = toLatLng(item.x, item.y);
      if (!pt) return;
      L.circleMarker(pt, {
        radius: 5,
        color: st.color,
        weight: 2,
        opacity: st.opacity,
        fillOpacity: st.opacity * 0.35,
        dashArray: st.dashArray,
        interactive: false
      }).addTo(g);
      labelMarker(pt, item.code, item.draw_state).addTo(g);
    });
    ((detail.overlay && detail.overlay.routes) || []).forEach(function (route) {
      if (!route) return;
      if (route.planned && route.planned.length >= 2) {
        var p = [];
        route.planned.forEach(function (pt) {
          var ll = toLatLng(pt.x, pt.y);
          if (ll) p.push(ll);
        });
        if (p.length >= 2) {
          L.polyline(p, { color: '#94a3b8', weight: 2, opacity: 0.4, dashArray: '8,6', interactive: false }).addTo(g);
        }
      }
      if (route.actual && route.actual.length >= 2) {
        var a = [];
        route.actual.forEach(function (pt) {
          var ll = toLatLng(pt.x, pt.y);
          if (ll) a.push(ll);
        });
        if (a.length >= 2) {
          L.polyline(a, { color: '#34d399', weight: 3, opacity: 0.85, interactive: false }).addTo(g);
        }
      }
    });
  }

  window.addEventListener('atak:mapready', function () {
    var map = window.ATAKMap.getMap();
    ensureGroup(map);
    if (window.ATAKMissionPlan && window.ATAKMissionPlan.snapshot) {
      draw(window.ATAKMissionPlan.snapshot());
    }
  });
  window.addEventListener('atak:mission-plan-updated', function (ev) {
    draw(ev.detail);
  });
  window.addEventListener('atak:display-prefs-changed', function () {
    if (window.ATAKMissionPlan && window.ATAKMissionPlan.snapshot) {
      draw(window.ATAKMissionPlan.snapshot());
    }
  });
})();
