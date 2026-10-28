/* COMSPEC ATAK — relèvements radio (SIGINT) */
window.ATAKSIGINT = (function () {
  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatWhen(raw) {
    if (!raw) return '';
    return String(raw).replace('T', ' ').slice(0, 19);
  }

  function bearingLabel(b) {
    if (b == null || b === '') return 'Gisement inconnu';
    var n = Number(b);
    if (isNaN(n)) return String(b);
    return Math.round(n) + '°';
  }

  /* Lignes de relèvement (goniométrie GE et détecteur de drones de l'ATAK natif : position du récepteur + gisement),
     3 km, sur les 40 derniers signalements. Deux lignes du même émetteur se recoupent sur la carte. */
  var bearingLayer = null;
  var BEARING_LEN_M = 3000;

  function drawBearings(rows) {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || typeof L === 'undefined' || !window.ATAKMap.latLngFromWorld) return;
    if (!bearingLayer) bearingLayer = L.layerGroup().addTo(map);
    bearingLayer.clearLayers();
    rows.forEach(function (r) {
      var b = Number(r.bearing);
      var x = Number(r.pos_x);
      var y = Number(r.pos_y);
      if (r.bearing == null || r.bearing === '' || isNaN(b) || isNaN(x) || isNaN(y)) return;
      var rad = b * Math.PI / 180;
      var drone = String(r.call_sign || '').indexOf('DRONE-') === 0;
      var color = drone ? '#f97316' : '#ef4444';
      var from = window.ATAKMap.latLngFromWorld(x, y);
      var to = window.ATAKMap.latLngFromWorld(x + Math.sin(rad) * BEARING_LEN_M, y + Math.cos(rad) * BEARING_LEN_M);
      L.polyline([from, to], { color: color, weight: 2, opacity: 0.75, dashArray: '8 6' })
        .bindTooltip(escapeHtml((r.call_sign || 'Émetteur') + ' · ' + bearingLabel(r.bearing) + (r.created_at ? ' · ' + formatWhen(r.created_at) : '')))
        .addTo(bearingLayer);
      L.circleMarker(from, { radius: 3, color: color, weight: 1, fillOpacity: 0.8 }).addTo(bearingLayer);
    });
  }

  function render(rows) {
    drawBearings(rows);
    var el = document.getElementById('atak-sigint-list');
    if (!el) return;
    if (!rows.length) {
      el.innerHTML = '<p class="atak-panel-hint">Aucun relèvement pour le moment.</p>';
      return;
    }
    el.innerHTML = rows.map(function (r) {
      var cs = r.call_sign || r.callsign || 'Sans indicatif';
      var x = r.pos_x != null ? Math.round(Number(r.pos_x)) : '—';
      var y = r.pos_y != null ? Math.round(Number(r.pos_y)) : '—';
      return '<button type="button" class="atak-sigint-item" data-x="' + escapeHtml(r.pos_x || '') +
        '" data-y="' + escapeHtml(r.pos_y || '') + '">' +
        '<strong>' + escapeHtml(cs) + '</strong> · ' + escapeHtml(bearingLabel(r.bearing)) +
        '<span class="atak-sigint-meta">' + escapeHtml(x + ' / ' + y) +
        (r.created_at ? ' · ' + escapeHtml(formatWhen(r.created_at)) : '') + '</span></button>';
    }).join('');
  }

  function refresh() {
    var base = String(apiBase() || '').replace(/\/$/, '');
    var el = document.getElementById('atak-sigint-list');
    if (!base || !el) return;
    fetch(base + '/api/atak/sigint?mapId=' + mapId() + '&limit=40', {
      credentials: 'include',
      cache: 'no-store'
    }).then(function (r) { return r.ok ? r.json() : []; }).then(function (data) {
      render(Array.isArray(data) ? data : []);
    }).catch(function () {
      render([]);
    });
  }

  function onListClick(ev) {
    var btn = ev.target.closest('.atak-sigint-item');
    if (!btn) return;
    var x = parseFloat(btn.getAttribute('data-x'));
    var y = parseFloat(btn.getAttribute('data-y'));
    if (isNaN(x) || isNaN(y)) return;
    if (window.ATAKMap && typeof window.ATAKMap.centerOn === 'function') {
      window.ATAKMap.centerOn(y, x);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('atak-sigint-refresh');
    var list = document.getElementById('atak-sigint-list');
    if (btn) btn.addEventListener('click', refresh);
    if (list) list.addEventListener('click', onListClick);
    // Lignes de relèvement tenues à jour sur la carte (toutes les 30 s, onglet visible seulement).
    if (list) {
      setTimeout(refresh, 5000);
      setInterval(function () { if (!document.hidden) refresh(); }, 30000);
    }
  });

  return { refresh: refresh };
})();
