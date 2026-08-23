/* Calques cinématique : flèche, projection, trace, ligne de destination. */
window.ATAKMotionMap = (function () {
  'use strict';

  var layer = null;
  var byId = {};
  var lastUnits = [];
  var lastAir = [];

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function prefs() {
    var p = window.ATAKMap && window.ATAKMap.getDisplayPrefs ? window.ATAKMap.getDisplayPrefs() : {};
    return {
      arrows: p.showMotionArrows !== false,
      projection: p.showMotionProjection !== false,
      lines: p.showAssignmentLines !== false,
      trail: p.showMotionTrail !== false,
      eta: !!p.showEtaLabels
    };
  }

  function worldToLatLng(x, y) {
    if (window.ATAKMap && window.ATAKMap.latLngFromWorld) {
      return window.ATAKMap.latLngFromWorld(x, y);
    }
    if (window.ATAKMap && window.ATAKMap.applyOffset) {
      var a = window.ATAKMap.applyOffset(y, x);
      return window.L ? window.L.latLng(a[0], a[1]) : null;
    }
    return window.L ? window.L.latLng(y, x) : null;
  }

  function destFrom(u) {
    var a = window.ATAKMotion ? window.ATAKMotion.assignmentOf(u) : null;
    if (!a) return null;
    var x = Number(a.destination_x);
    var y = Number(a.destination_y);
    if (!isFinite(x) || !isFinite(y)) return null;
    return { x: x, y: y, a: a };
  }

  function posOf(u) {
    var x = u && u.pos_x != null ? Number(u.pos_x) : NaN;
    var y = u && u.pos_y != null ? Number(u.pos_y) : NaN;
    if (!isFinite(x) || !isFinite(y)) return null;
    if (Math.abs(x) < 0.5 && Math.abs(y) < 0.5) return null;
    return { x: x, y: y };
  }

  function headingRad(h) {
    return (Number(h) * Math.PI) / 180;
  }

  function offsetPoint(x, y, headingDeg, meters) {
    var r = headingRad(headingDeg);
    return { x: x + Math.sin(r) * meters, y: y + Math.cos(r) * meters };
  }

  function arrowLenM(u, map) {
    var zoom = map && map.getZoom ? map.getZoom() : 3;
    var base = 18 + Math.max(0, (4 - zoom) * 8);
    var spd = window.ATAKMotion ? window.ATAKMotion.num((u.motion && u.motion.speed_current) || u.speed) : 0;
    var kmh = (spd || 0) * 3.6;
    var scale = 1 + Math.min(0.7, kmh / 120);
    return Math.max(12, Math.min(42, base * scale));
  }

  function ensureLayer(map) {
    if (layer && map.hasLayer && map.hasLayer(layer)) return layer;
    if (!window.L) return null;
    layer = L.layerGroup();
    layer.addTo(map);
    return layer;
  }

  function clearId(id) {
    var pack = byId[id];
    if (!pack || !layer) return;
    ['arrow', 'proj', 'trail', 'dest', 'eta'].forEach(function (k) {
      if (pack[k]) {
        try { layer.removeLayer(pack[k]); } catch (e) {}
      }
    });
    delete byId[id];
  }

  function setLine(pack, key, latlngs, opts) {
    if (!window.L || !layer) return;
    if (!latlngs || latlngs.length < 2) {
      if (pack[key]) {
        try { layer.removeLayer(pack[key]); } catch (e) {}
        pack[key] = null;
      }
      return;
    }
    if (!pack[key]) {
      pack[key] = L.polyline(latlngs, opts).addTo(layer);
    } else {
      pack[key].setLatLngs(latlngs);
      pack[key].setStyle(opts);
    }
  }

  function renderOne(id, u, map, pr) {
    var pos = posOf(u);
    if (!pos) {
      clearId(id);
      return;
    }
    var M = window.ATAKMotion;
    var pack = byId[id] || {};
    byId[id] = pack;
    var start = worldToLatLng(pos.x, pos.y);
    if (!start) return;

    var moving = M && M.isMoving(u);
    var heading = M ? M.num(u.movement_heading) : null;
    var m = M ? M.motionOf(u) : {};

    if (pr.arrows && moving && heading != null) {
      var len = arrowLenM(u, map);
      var tip = offsetPoint(pos.x, pos.y, heading, len);
      setLine(pack, 'arrow', [start, worldToLatLng(tip.x, tip.y)], {
        color: '#cbd5e1',
        weight: 1.2,
        opacity: 0.42,
        lineCap: 'round',
        interactive: false,
        className: 'atak-motion-arrow-line'
      });
    } else {
      setLine(pack, 'arrow', null);
    }

    var proj = m.projection || {};
    if (pr.projection && moving && heading != null && proj.visible && proj.length_m > 8) {
      var pTip = offsetPoint(pos.x, pos.y, heading, Number(proj.length_m));
      setLine(pack, 'proj', [start, worldToLatLng(pTip.x, pTip.y)], {
        color: '#94a3b8',
        weight: 1,
        opacity: 0.28,
        dashArray: '3 7',
        interactive: false
      });
    } else {
      setLine(pack, 'proj', null);
    }

    if (pr.trail && Array.isArray(m.trail) && m.trail.length >= 2) {
      var pts = m.trail.map(function (s) {
        return worldToLatLng(Number(s.x), Number(s.y));
      }).filter(Boolean);
      setLine(pack, 'trail', pts, {
        color: '#64748b',
        weight: 1,
        opacity: 0.18,
        lineCap: 'round',
        interactive: false,
        className: 'atak-motion-trail'
      });
    } else {
      setLine(pack, 'trail', null);
    }

    var dest = destFrom(u);
    if (pr.lines && dest) {
      var end = worldToLatLng(dest.x, dest.y);
      var course = String(dest.a.course_status || '').toUpperCase();
      var col = course === 'DIVERGING' ? '#d4a017' : '#7dd3c0';
      setLine(pack, 'dest', [start, end], {
        color: col,
        weight: 1,
        opacity: course === 'DIVERGING' ? 0.4 : 0.28,
        dashArray: '5 6',
        interactive: false
      });
    } else {
      setLine(pack, 'dest', null);
    }

    if (pr.eta && dest && dest.a.eta && dest.a.eta.seconds != null && dest.a.course_status !== 'ARRIVED') {
      var html = '<span class="atak-motion-eta">' + (M ? M.formatEta(dest.a.eta.seconds, dest.a.eta.arrived) : '') + '</span>';
      var ll = worldToLatLng(pos.x + 8, pos.y + 8);
      if (!pack.eta) {
        pack.eta = L.marker(ll, {
          icon: L.divIcon({ className: 'atak-motion-eta-wrap', html: html, iconSize: [36, 12], iconAnchor: [-6, 8] }),
          interactive: false,
          zIndexOffset: 250
        }).addTo(layer);
      } else {
        pack.eta.setLatLng(ll);
        pack.eta.setIcon(L.divIcon({ className: 'atak-motion-eta-wrap', html: html, iconSize: [36, 12], iconAnchor: [-6, 8] }));
      }
    } else if (pack.eta) {
      try { layer.removeLayer(pack.eta); } catch (e) {}
      pack.eta = null;
    }
  }

  function sync(units, air) {
    var map = getMap();
    if (!map || !window.L) return;
    ensureLayer(map);
    lastUnits = Array.isArray(units) ? units : lastUnits;
    lastAir = Array.isArray(air) ? air : lastAir;
    var pr = prefs();
    var seen = {};
    function walk(list, prefix, refKey) {
      (list || []).forEach(function (u) {
        var ref = u && (u[refKey] || u.call_sign || u.callsign);
        if (!ref) return;
        var id = prefix + ref;
        seen[id] = true;
        renderOne(id, u, map, pr);
      });
    }
    walk(lastUnits, 'u:', 'call_sign');
    walk(lastAir, 'a:', 'callsign');
    Object.keys(byId).forEach(function (id) {
      if (!seen[id]) clearId(id);
    });
  }

  function bind() {
    window.addEventListener('atak:units-markers-updated', function (ev) {
      var list = ev && ev.detail && ev.detail.units;
      sync(list, lastAir);
    });
    window.addEventListener('atak:air-markers-updated', function (ev) {
      var list = ev && ev.detail && ev.detail.assets;
      sync(lastUnits, list);
    });
    window.addEventListener('atak:display-prefs-changed', function () {
      sync(lastUnits, lastAir);
    });
    window.addEventListener('atak:mapready', function () {
      sync(lastUnits, lastAir);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }

  return { sync: sync };
})();
