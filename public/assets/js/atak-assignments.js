/* Affectation unité → destination (mode visée + menus). */
window.ATAKAssignments = (function () {
  'use strict';

  var pickUnit = null;
  var banner = null;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
  }
  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function csrf() {
    return window.ATAK_CSRF_TOKEN || '';
  }

  function headers() {
    return {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      'Accept': 'application/json'
    };
  }

  function notify(msg) {
    if (window.ATAKShowNotification) window.ATAKShowNotification(msg);
  }
  function err(msg) {
    if (window.ATAKShowError) window.ATAKShowError(msg);
  }

  function callsignOf(u) {
    return String((u && (u.call_sign || u.callsign)) || '').trim();
  }
  function kindOf(u) {
    if (!u) return 'ground';
    if (u.aircraft_type || (u.callsign && !u.call_sign && u.model != null)) return 'air';
    var cat = String((u.motion && u.motion.category) || '').toUpperCase();
    if (cat === 'HELICOPTER' || cat === 'FIXED_WING' || cat === 'UAV') {
      if (u.callsign && !u.id) return 'air';
    }
    return 'ground';
  }

  function liveUnits() {
    return (window.ATAKUnits && window.ATAKUnits.getUnits) ? (window.ATAKUnits.getUnits() || []) : [];
  }
  function liveAir() {
    return (window.ATAKAirAssets && window.ATAKAirAssets.getAssets) ? (window.ATAKAirAssets.getAssets() || []) : [];
  }

  function postAssign(payload) {
    return fetch(apiBase() + '/api/atak/assignments', {
      method: 'POST',
      headers: headers(),
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mapId: mapId(), _csrf_token: csrf() }, payload))
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });
  }

  function detach(id) {
    return fetch(apiBase() + '/api/atak/assignments/' + encodeURIComponent(id) + '/detach', {
      method: 'POST',
      headers: headers(),
      credentials: 'same-origin',
      body: JSON.stringify({ mapId: mapId(), _csrf_token: csrf() })
    }).then(function (r) { return r.json(); });
  }

  function refreshUnits() {
    if (window.ATAKUnits && window.ATAKUnits.fetchUnits) window.ATAKUnits.fetchUnits();
    else if (window.ATAKUnits && window.ATAKUnits.refresh) window.ATAKUnits.refresh();
    if (window.ATAKAirAssets && window.ATAKAirAssets.fetchAirAssets) window.ATAKAirAssets.fetchAirAssets();
  }

  function ensureBanner() {
    if (banner) return banner;
    banner = document.createElement('div');
    banner.id = 'atak-assign-banner';
    banner.className = 'atak-assign-banner';
    banner.hidden = true;
    banner.innerHTML = '<span class="atak-assign-banner__txt"></span><button type="button" class="atak-assign-banner__cancel">Annuler</button>';
    document.body.appendChild(banner);
    banner.querySelector('.atak-assign-banner__cancel').addEventListener('click', cancelPick);
    return banner;
  }

  function beginPick(unit) {
    pickUnit = unit;
    ensureBanner();
    var cs = callsignOf(unit) || 'cette unité';
    banner.querySelector('.atak-assign-banner__txt').textContent =
      'Choisissez la destination de ' + cs + ' — cliquez un repère, une unité ou un point. Échap pour annuler.';
    banner.hidden = false;
    var mapEl = document.getElementById('atak-map');
    if (mapEl) mapEl.classList.add('atak-map--pick-dest');
    notify('Cliquez la destination pour ' + cs);
  }

  function cancelPick() {
    pickUnit = null;
    if (banner) banner.hidden = true;
    var mapEl = document.getElementById('atak-map');
    if (mapEl) mapEl.classList.remove('atak-map--pick-dest');
  }

  function assignTo(dest) {
    if (!pickUnit && !dest.sourceUnit) return Promise.resolve();
    var unit = dest.sourceUnit || pickUnit;
    var payload = {
      unit_kind: kindOf(unit),
      unit_id: unit.id || null,
      unit_ref: callsignOf(unit),
      destination_type: dest.type,
      destination_id: dest.id != null ? String(dest.id) : null,
      destination_label: dest.label || '',
      destination_x: dest.x,
      destination_y: dest.y,
      assignment_mode: dest.mode || 'DIRECT'
    };
    return postAssign(payload).then(function (res) {
      if (!res.ok || !res.body || !res.body.ok) {
        err((res.body && res.body.error) || 'Impossible d’assigner la destination.');
        return;
      }
      notify((callsignOf(unit) || 'Unité') + ' → ' + (dest.label || 'destination'));
      cancelPick();
      refreshUnits();
    }).catch(function () {
      err('Impossible d’assigner la destination.');
    });
  }

  function worldFromLatLng(ll) {
    if (window.ATAKMap && window.ATAKMap.worldFromLatLng) {
      return window.ATAKMap.worldFromLatLng(ll);
    }
    var cfg = window.ATAKMap && window.ATAKMap.getConfig ? window.ATAKMap.getConfig() : {};
    var ox = cfg && cfg.offsetX != null ? cfg.offsetX : 0;
    var oy = cfg && cfg.offsetY != null ? cfg.offsetY : 0;
    return { x: ll.lng - ox, y: ll.lat - oy };
  }

  function hitUnitNear(x, y) {
    var best = null;
    var bestD = 25;
    function scan(list, isAir) {
      (list || []).forEach(function (u) {
        var px = Number(u.pos_x);
        var py = Number(u.pos_y);
        if (!isFinite(px) || !isFinite(py)) return;
        var d = Math.hypot(px - x, py - y);
        if (d < bestD) {
          bestD = d;
          best = { unit: u, air: isAir, d: d };
        }
      });
    }
    scan(liveUnits(), false);
    scan(liveAir(), true);
    return best;
  }

  function hitMarkerNear(x, y) {
    if (!window.ATAKMap || !window.ATAKMap.listMarkers) return null;
    var best = null;
    var bestD = 40;
    window.ATAKMap.listMarkers().forEach(function (item) {
      var mx = item.gridLng != null ? Number(item.gridLng) : NaN;
      var my = item.gridLat != null ? Number(item.gridLat) : NaN;
      if (!isFinite(mx) || !isFinite(my)) return;
      var d = Math.hypot(mx - x, my - y);
      if (d < bestD) {
        bestD = d;
        var data = item.data || {};
        var label = data.label || data.text || data.name || 'Repère';
        best = { id: item.id, label: label, x: mx, y: y, data: data, d: d };
        best.y = my;
      }
    });
    return best;
  }

  function onMapClick(e) {
    if (!pickUnit || !e || !e.latlng) return;
    L.DomEvent.stop(e);
    var w = worldFromLatLng(e.latlng);
    var uHit = hitUnitNear(w.x, w.y);
    if (uHit && callsignOf(uHit.unit) !== callsignOf(pickUnit)) {
      assignTo({
        type: 'unit',
        id: callsignOf(uHit.unit),
        label: callsignOf(uHit.unit),
        x: Number(uHit.unit.pos_x),
        y: Number(uHit.unit.pos_y)
      });
      return;
    }
    var mHit = hitMarkerNear(w.x, w.y);
    if (mHit) {
      assignTo({
        type: 'marker',
        id: mHit.id,
        label: mHit.label,
        x: mHit.x,
        y: mHit.y
      });
      return;
    }
    assignTo({
      type: 'custom',
      id: null,
      label: 'Point ' + Math.round(w.x) + ' / ' + Math.round(w.y),
      x: w.x,
      y: w.y
    });
  }

  function bindMap() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || map._atakAssignBound) return;
    map._atakAssignBound = true;
    map.on('click', function (e) {
      if (!pickUnit) return;
      onMapClick(e);
    });
    map.on('popupopen', function (e) {
      var el = e && e.popup && e.popup.getElement ? e.popup.getElement() : null;
      if (!el) return;
      var root = el.querySelector('[data-atak-marker-id]');
      if (root) fillMarkerArrivals(root, root.getAttribute('data-atak-marker-id'));
    });
  }

  function fillMarkerArrivals(root, markerId) {
    if (!root || markerId == null) return;
    var el = root.querySelector('[data-atak-arrivals]');
    if (!el) return;
    fetch(apiBase() + '/api/atak/assignments/arrivals?mapId=' + encodeURIComponent(mapId()) +
      '&destination_type=marker&destination_id=' + encodeURIComponent(markerId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var list = (j && j.arrivals) || [];
        if (!list.length) {
          el.innerHTML = '';
          return;
        }
        var M = window.ATAKMotion;
        var rows = list.map(function (a, i) {
          var eta = M ? M.formatEta(a.eta_seconds, a.course_status === 'ARRIVED') : '';
          return '<div class="atak-arrival-row"><span class="atak-arrival-row__n">' + (a.arrival_order || (i + 1)) +
            '</span><span class="atak-arrival-row__u">' + (M ? M.esc(a.call_sign) : a.call_sign) +
            '</span><span class="atak-arrival-row__eta">' + eta + '</span></div>';
        }).join('');
        el.innerHTML = '<div class="atak-arrival-block"><div class="atak-arrival-block__t">Ordre d’arrivée</div>' + rows + '</div>';
      }).catch(function () { el.innerHTML = ''; });
  }

  function bind() {
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && pickUnit) cancelPick();
    });
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-atak-assign]') : null;
      if (!btn) return;
      e.preventDefault();
      var action = btn.getAttribute('data-atak-assign');
      var cs = btn.getAttribute('data-unit-ref') || '';
      var kind = btn.getAttribute('data-unit-kind') || 'ground';
      var unit = null;
      if (kind === 'air') {
        liveAir().some(function (a) { if (callsignOf(a) === cs) { unit = a; return true; } return false; });
      } else {
        liveUnits().some(function (u) { if (callsignOf(u) === cs) { unit = u; return true; } return false; });
      }
      if (action === 'pick') {
        if (unit) beginPick(unit);
        return;
      }
      if (action === 'detach') {
        var aid = btn.getAttribute('data-assign-id');
        if (!aid) return;
        detach(aid).then(function () {
          notify('Destination détachée');
          refreshUnits();
        });
      }
    });
    window.addEventListener('atak:mapready', bindMap);
    bindMap();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }

  return {
    beginPick: beginPick,
    cancelPick: cancelPick,
    assignTo: assignTo,
    detach: detach,
    isPicking: function () { return !!pickUnit; },
    pickUnit: function () { return pickUnit; },
    fillMarkerArrivals: fillMarkerArrivals,
    liveUnits: liveUnits
  };
})();
