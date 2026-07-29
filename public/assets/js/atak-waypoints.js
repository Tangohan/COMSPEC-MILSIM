/* COMSPEC ATAK — Points de mission (ordre MOVE + coordonnées + itinéraire / ETA) */
window.ATAKWaypoints = (function () {
  'use strict';

  var META_PREFIX = '@WP:';
  var modalEl = null;
  var previewLine = null;
  var previewMarker = null;
  var pendingLatLng = null;

  function getApiBase() {
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) return window.ATAKSocket.getApiBase();
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function getMapId() {
    var mid = 1;
    if (window.ATAKSocket && window.ATAKSocket.getMapId) mid = window.ATAKSocket.getMapId();
    else if (window.ATAK_DEFAULT_MAP_ID != null) mid = window.ATAK_DEFAULT_MAP_ID;
    mid = parseInt(mid, 10);
    return (!mid || mid < 1 || isNaN(mid)) ? 1 : mid;
  }

  function getAuthor() {
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'Commandement';
  }

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function gridLabelFromLatLng(latlng) {
    if (!latlng) return '—';
    var x = Math.round(latlng.lng);
    var y = Math.round(latlng.lat);
    return x + ' / ' + y;
  }

  function parseWaypoint(payload) {
    payload = String(payload || '');
    var idx = payload.indexOf(META_PREFIX);
    if (idx < 0) return null;
    var text = payload.slice(0, idx).trim();
    var meta = payload.slice(idx + META_PREFIX.length);
    var parts = meta.split('|');
    if (parts.length < 2) return null;
    var posX = parseFloat(parts[0]);
    var posY = parseFloat(parts[1]);
    if (!isFinite(posX) || !isFinite(posY) || (posX === 0 && posY === 0)) return null;
    var wp = {
      text: text,
      pos_x: posX,
      pos_y: posY,
      grid_reference: '',
      eta_min: null,
      distance_m: null,
      speed_kph: null,
      label: ''
    };
    for (var i = 2; i < parts.length; i++) {
      var p = parts[i];
      if (p.indexOf('GRID:') === 0) wp.grid_reference = p.slice(5);
      else if (p.indexOf('ETA:') === 0) wp.eta_min = parseInt(p.slice(4), 10);
      else if (p.indexOf('DIST:') === 0) wp.distance_m = parseInt(p.slice(5), 10);
      else if (p.indexOf('SPD:') === 0) wp.speed_kph = parseFloat(p.slice(4));
      else if (p.indexOf('LBL:') === 0) wp.label = p.slice(4);
    }
    return wp;
  }

  function buildPayload(text, posX, posY, opts) {
    opts = opts || {};
    var grid = (opts.grid_reference || '').trim() || (Math.round(posX) + ' / ' + Math.round(posY));
    var meta = META_PREFIX + posX.toFixed(2) + '|' + posY.toFixed(2) + '|GRID:' + grid;
    if (opts.eta_min != null && !isNaN(opts.eta_min)) meta += '|ETA:' + Math.round(opts.eta_min);
    if (opts.distance_m != null && !isNaN(opts.distance_m)) meta += '|DIST:' + Math.round(opts.distance_m);
    if (opts.speed_kph != null && !isNaN(opts.speed_kph)) meta += '|SPD:' + opts.speed_kph;
    if (opts.label) meta += '|LBL:' + String(opts.label).trim();
    text = String(text || '').trim();
    return text ? (text + ' ' + meta) : meta;
  }

  function displayPayload(order) {
    if (!order) return '';
    if (order.payload_display) return order.payload_display;
    if (order.waypoint) {
      if (order.waypoint.label) return order.waypoint.label;
      if (order.payload) {
        var wp = parseWaypoint(order.payload);
        if (wp && wp.text) return wp.text;
      }
    }
    var wp2 = parseWaypoint(order.payload || '');
    if (wp2) {
      if (wp2.text) return wp2.text;
      if (wp2.label) return wp2.label;
      return 'Point de mission';
    }
    return order.payload || '';
  }

  function waypointFromOrder(order) {
    if (order && order.waypoint) return order.waypoint;
    return parseWaypoint(order && order.payload ? order.payload : '');
  }

  function formatDistance(m) {
    m = Number(m) || 0;
    if (m >= 1000) return (m / 1000).toFixed(1).replace('.', ',') + ' km';
    return Math.round(m) + ' m';
  }

  function euclideanDistance(x1, y1, x2, y2) {
    var dx = x2 - x1;
    var dy = y2 - y1;
    return Math.sqrt(dx * dx + dy * dy);
  }

  function etaMinutes(distanceM, speedKph) {
    speedKph = Math.max(Number(speedKph) || 5, 0.5);
    var speedMs = speedKph / 3.6;
    return Math.max(1, Math.round(distanceM / speedMs / 60));
  }

  function resolveOriginPos(targetType, targetRef) {
    var units = (window.ATAKUnits && window.ATAKUnits.getUnits) ? (window.ATAKUnits.getUnits() || []) : [];
    if (targetType === 'solo' && targetRef) {
      var ref = String(targetRef).trim().toLowerCase();
      for (var i = 0; i < units.length; i++) {
        var u = units[i];
        var id = String(u.id != null ? u.id : '').toLowerCase();
        var cs = String(u.callsign || u.displayName || '').toLowerCase();
        if (id === ref || cs === ref) {
          if (u.pos_x != null && u.pos_y != null) {
            return { pos_x: parseFloat(u.pos_x), pos_y: parseFloat(u.pos_y), label: u.callsign || u.displayName || 'Opérateur' };
          }
        }
      }
    }
    if (units.length) {
      for (var j = 0; j < units.length; j++) {
        var u2 = units[j];
        if (window.ATAKUnits.hasValidPosition && !window.ATAKUnits.hasValidPosition(u2)) continue;
        if (u2.pos_x != null && u2.pos_y != null) {
          return { pos_x: parseFloat(u2.pos_x), pos_y: parseFloat(u2.pos_y), label: u2.callsign || 'Effectif' };
        }
      }
    }
    return null;
  }

  function clearPreview() {
    var map = getMap();
    if (previewLine && map) {
      map.removeLayer(previewLine);
      previewLine = null;
    }
    if (previewMarker && map) {
      map.removeLayer(previewMarker);
      previewMarker = null;
    }
  }

  function showPreview(from, toLatLng) {
    clearPreview();
    var map = getMap();
    if (!map || !toLatLng || typeof L === 'undefined') return;
    var to = L.latLng(toLatLng.lat, toLatLng.lng);
    var fromLl = from
      ? L.latLng(from.pos_y, from.pos_x)
      : map.getCenter();
    previewLine = L.polyline([fromLl, to], {
      color: '#38bdf8',
      weight: 3,
      dashArray: '8 6',
      opacity: 0.85
    }).addTo(map);
    previewMarker = L.circleMarker(to, {
      radius: 8,
      color: '#eab308',
      fillColor: '#eab308',
      fillOpacity: 0.9,
      weight: 2
    }).addTo(map);
  }

  function updateMetricsPanel() {
    var panel = document.getElementById('atak-wp-metrics');
    if (!panel || !pendingLatLng) return;
    var speedEl = document.getElementById('atak-wp-speed');
    var targetTypeEl = document.getElementById('atak-wp-target-type');
    var targetRefEl = document.getElementById('atak-wp-target-ref');
    var speedKph = speedEl ? parseFloat(speedEl.value) || 5 : 5;
    var origin = resolveOriginPos(
      targetTypeEl ? targetTypeEl.value : 'all',
      targetRefEl ? targetRefEl.value : ''
    );
    var dist = origin
      ? euclideanDistance(origin.pos_x, origin.pos_y, pendingLatLng.lng, pendingLatLng.lat)
      : 0;
    var eta = dist > 0 ? etaMinutes(dist, speedKph) : null;
    showPreview(origin, pendingLatLng);
    var grid = gridLabelFromLatLng(pendingLatLng);
    var lines = ['Grille objectif : ' + grid];
    if (origin) {
      lines.push('Depuis ' + origin.label + ' · ' + formatDistance(dist));
      if (eta != null) lines.push('Arrivée estimée ~' + eta + ' min (' + speedKph + ' km/h)');
    } else {
      lines.push('Choisissez un destinataire pour estimer la distance et l’heure d’arrivée.');
    }
    panel.textContent = lines.join(' · ');
  }

  function fillTargetOptions(preserve) {
    var typeEl = document.getElementById('atak-wp-target-type');
    var refWrap = document.getElementById('atak-wp-recipient-wrap');
    var refEl = document.getElementById('atak-wp-target-ref');
    var labelEl = document.getElementById('atak-wp-target-label');
    if (!typeEl || !refEl) return;
    var type = typeEl.value || 'all';
    var prev = preserve ? String(refEl.value || '') : '';
    if (window.ATAKOrders && window.ATAKOrders.loadRecipients) {
      window.ATAKOrders.loadRecipients();
    }
    if (type === 'all') {
      if (refWrap) refWrap.hidden = true;
      refEl.innerHTML = '<option value="">—</option>';
      updateMetricsPanel();
      return;
    }
    if (refWrap) refWrap.hidden = false;
    if (labelEl && window.ATAKOrders && window.ATAKOrders.targetTypeLabel) {
      labelEl.textContent = window.ATAKOrders.targetTypeLabel(type);
    }
    var list = [];
    if (window.ATAKOrders && window.ATAKOrders.recipientsForType) {
      list = window.ATAKOrders.recipientsForType(type);
    }
    refEl.innerHTML = '<option value="">Choisir…</option>';
    list.forEach(function (item) {
      var opt = document.createElement('option');
      opt.value = String(item.id == null ? '' : item.id);
      opt.textContent = String(item.label == null ? item.id : item.label);
      refEl.appendChild(opt);
    });
    if (prev) refEl.value = prev;
    updateMetricsPanel();
  }

  function ensureModal() {
    if (modalEl) return modalEl;
    modalEl = document.createElement('div');
    modalEl.id = 'atak-waypoint-modal';
    modalEl.className = 'atak-input-modal atak-waypoint-modal';
    modalEl.hidden = true;
    modalEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-wp-close></div>' +
      '<div class="atak-input-modal__box atak-waypoint-modal__box">' +
        '<h2 class="atak-input-modal__title">Point de mission</h2>' +
        '<p class="atak-input-modal__hint">L’ordre est transmis sur l’ATAK du destinataire. Il pourra confirmer ou refuser ; le point apparaît sur sa carte après acceptation.</p>' +
        '<label class="atak-input-modal__field-label">Nom du point</label>' +
        '<input type="text" class="atak-input-modal__field" id="atak-wp-label" maxlength="80" placeholder="Ex. Phase nord — RV" />' +
        '<label class="atak-input-modal__field-label">Consignes</label>' +
        '<textarea class="atak-input-modal__field atak-input-modal__field--area" id="atak-wp-text" rows="3" maxlength="400" placeholder="Ex. Couverture au nord, attente 5 min avant d’entrer."></textarea>' +
        '<div class="atak-waypoint-modal__grid">' +
          '<label class="atak-input-modal__field-label">Priorité</label>' +
          '<select class="atak-input-modal__field" id="atak-wp-priority">' +
            '<option value="ROUTINE">Routine</option>' +
            '<option value="IMPORTANT" selected>Important</option>' +
            '<option value="URGENT">Urgent</option>' +
            '<option value="CONTACT">Contact</option>' +
          '</select>' +
          '<label class="atak-input-modal__field-label">Destinataires</label>' +
          '<select class="atak-input-modal__field" id="atak-wp-target-type">' +
            '<option value="solo">Un opérateur</option>' +
            '<option value="fire_team">Équipe feu</option>' +
            '<option value="group">Groupe</option>' +
            '<option value="channel">Canal radio</option>' +
            '<option value="all">Toute l’équipe</option>' +
          '</select>' +
        '</div>' +
        '<div id="atak-wp-recipient-wrap">' +
          '<label class="atak-input-modal__field-label" id="atak-wp-target-label">Opérateur</label>' +
          '<select class="atak-input-modal__field" id="atak-wp-target-ref"></select>' +
        '</div>' +
        '<label class="atak-input-modal__field-label">Vitesse de référence</label>' +
        '<select class="atak-input-modal__field" id="atak-wp-speed">' +
            '<option value="5" selected>À pied — 5 km/h</option>' +
            '<option value="12">Trot — 12 km/h</option>' +
            '<option value="40">Véhicule — 40 km/h</option>' +
            '<option value="80">Route — 80 km/h</option>' +
          '</select>' +
        '<p class="atak-waypoint-modal__metrics" id="atak-wp-metrics" role="status" aria-live="polite"></p>' +
        '<label class="atak-orders-check atak-waypoint-modal__radio">' +
          '<input type="checkbox" id="atak-wp-radio-sim" checked /> Simuler le délai radio' +
        '</label>' +
        '<div class="atak-input-modal__actions">' +
          '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-wp-close>Annuler</button>' +
          '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-wp-send">Transmettre l’ordre</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modalEl);

    modalEl.querySelectorAll('[data-wp-close]').forEach(function (btn) {
      btn.addEventListener('click', closeModal);
    });
    ['atak-wp-target-type', 'atak-wp-target-ref', 'atak-wp-speed'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        if (id === 'atak-wp-target-type') fillTargetOptions(true);
        else updateMetricsPanel();
      });
    });
    var sendBtn = document.getElementById('atak-wp-send');
    if (sendBtn) sendBtn.addEventListener('click', submitWaypoint);
    return modalEl;
  }

  function canIssue() {
    return !!window.ATAK_CAN_ISSUE_ORDERS;
  }

  function openAt(latlng) {
    if (!canIssue()) {
      if (window.ATAKShowError) window.ATAKShowError('Profil commandement requis pour émettre un point de mission.');
      return;
    }
    pendingLatLng = latlng;
    ensureModal();
    var labelEl = document.getElementById('atak-wp-label');
    var textEl = document.getElementById('atak-wp-text');
    if (labelEl) labelEl.value = '';
    if (textEl) textEl.value = '';
    modalEl.hidden = false;
    document.body.classList.add('atak-waypoint-open');
    if (window.ATAKOrders && window.ATAKOrders.loadRecipients) {
      window.ATAKOrders.loadRecipients().then(function () {
        fillTargetOptions(false);
      });
    } else {
      fillTargetOptions(false);
    }
    updateMetricsPanel();
    if (labelEl) setTimeout(function () { labelEl.focus(); }, 40);
  }

  function closeModal() {
    clearPreview();
    pendingLatLng = null;
    if (modalEl) modalEl.hidden = true;
    document.body.classList.remove('atak-waypoint-open');
  }

  function submitWaypoint() {
    if (!pendingLatLng) return;
    var label = (document.getElementById('atak-wp-label') || {}).value || '';
    var text = (document.getElementById('atak-wp-text') || {}).value || '';
    var priority = (document.getElementById('atak-wp-priority') || {}).value || 'IMPORTANT';
    var targetType = (document.getElementById('atak-wp-target-type') || {}).value || 'solo';
    var targetRef = (document.getElementById('atak-wp-target-ref') || {}).value || '';
    var speedKph = parseFloat((document.getElementById('atak-wp-speed') || {}).value) || 5;
    var radioSim = !!(document.getElementById('atak-wp-radio-sim') || {}).checked;

    label = String(label).trim();
    text = String(text).trim();
    if (!label && !text) {
      if (window.ATAKShowError) window.ATAKShowError('Indiquez un nom ou des consignes pour ce point.');
      return;
    }
    if (targetType !== 'all' && !targetRef) {
      if (window.ATAKShowError) window.ATAKShowError('Choisissez le destinataire de l’ordre.');
      return;
    }

    var posX = pendingLatLng.lng;
    var posY = pendingLatLng.lat;
    var grid = gridLabelFromLatLng(pendingLatLng);
    var origin = resolveOriginPos(targetType, targetRef);
    var dist = origin ? euclideanDistance(origin.pos_x, origin.pos_y, posX, posY) : null;
    var eta = dist != null ? etaMinutes(dist, speedKph) : null;

    var human = text || ('Rendez-vous — ' + (label || grid));
    var payload = buildPayload(human, posX, posY, {
      grid_reference: grid,
      eta_min: eta,
      distance_m: dist,
      speed_kph: speedKph,
      label: label || ('Objectif ' + grid)
    });

    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible.');
      return;
    }

    var sendBtn = document.getElementById('atak-wp-send');
    if (sendBtn) sendBtn.disabled = true;
    var savedLatLng = { lng: posX, lat: posY };

    fetch(base + '/api/atak/orders', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mapId: getMapId(),
        type: 'MOVE',
        priority: priority,
        target_type: targetType,
        target_ref: targetRef,
        payload: payload,
        issuer: getAuthor(),
        radio_sim: radioSim
      })
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
      .then(function (res) {
        if (sendBtn) sendBtn.disabled = false;
        if (!res.ok) {
          var msg = (res.data && res.data.message) ? res.data.message : 'Impossible de transmettre l’ordre.';
          if (window.ATAKShowError) window.ATAKShowError(msg);
          return;
        }
        closeModal();
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Point de mission transmis — en attente de confirmation.');
        }
        if (window.ATAKContextMenu && window.ATAKContextMenu.createMarkerAt) {
          window.ATAKContextMenu.createMarkerAt(savedLatLng, {
            label: label || ('Objectif ' + grid),
            color: '#eab308',
            symbolName: 'Objectif',
            description: human + (eta != null ? ' · ETA ~' + eta + ' min' : '')
          });
        }
        if (window.ATAKOrders && window.ATAKOrders.fetchOrders) {
          window.ATAKOrders.fetchOrders();
        }
      })
      .catch(function () {
        if (sendBtn) sendBtn.disabled = false;
        if (window.ATAKShowError) window.ATAKShowError('Erreur réseau lors de la transmission.');
      });
  }

  function focusOrderWaypoint(order) {
    var wp = waypointFromOrder(order);
    if (!wp || wp.pos_x == null || wp.pos_y == null) return;
    if (window.ATAKMap && window.ATAKMap.centerOn) {
      window.ATAKMap.centerOn(wp.pos_y, wp.pos_x);
    }
  }

  function renderWaypointMetaHtml(order) {
    var wp = waypointFromOrder(order);
    if (!wp) return '';
    var parts = [];
    if (wp.grid_reference) parts.push('Grille ' + escapeHtml(wp.grid_reference));
    if (wp.distance_m != null) parts.push(escapeHtml(formatDistance(wp.distance_m)));
    if (wp.eta_min != null) parts.push('ETA ~' + escapeHtml(String(wp.eta_min)) + ' min');
    if (!parts.length) return '';
    return '<div class="atak-order-waypoint-meta">' + parts.join(' · ') + '</div>';
  }

  return {
    parseWaypoint: parseWaypoint,
    buildPayload: buildPayload,
    displayPayload: displayPayload,
    waypointFromOrder: waypointFromOrder,
    openAt: openAt,
    closeModal: closeModal,
    focusOrderWaypoint: focusOrderWaypoint,
    renderWaypointMetaHtml: renderWaypointMetaHtml,
    formatDistance: formatDistance
  };
})();
