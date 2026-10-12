/* COMSPEC ATAK — contacts vus par drone (pistes « DRN- » de la caméra des drones de l'ATAK natif, télémétrie obs / bda) */
window.ATAKDroneContacts = (function () {
  var layer = null;
  var timer = null;
  var POLL_MS = 15000;
  var MAX_AGE_MS = 30 * 60 * 1000;

  function apiBase() {
    return String(window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '')).replace(/\/$/, '');
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

  function enabled() {
    var box = document.getElementById('atak-show-drone-contacts');
    return !box || box.checked;
  }

  function parseMs(raw) {
    var ms = Date.parse(String(raw || '').replace(' ', 'T') + 'Z');
    return isNaN(ms) ? 0 : ms;
  }

  function render(tracks) {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || typeof L === 'undefined' || !window.ATAKMap.latLngFromWorld) return;
    if (!layer) layer = L.layerGroup().addTo(map);
    layer.clearLayers();
    if (!enabled() || (window.ATAKMap.isReplayActive && window.ATAKMap.isReplayActive())) return;
    var now = Date.now();
    tracks.forEach(function (t) {
      if (t.pos_x == null || t.pos_y == null) return;
      var seen = parseMs(t.updated_at || t.last_seen_at);
      if (seen && now - seen > MAX_AGE_MS) return;
      var meta = t.meta || {};
      var destroyed = meta.kind === 'bda' || String(meta.assessment || '').toUpperCase() === 'DESTROYED';
      var hostile = String(t.affiliation || '').toUpperCase().indexOf('HOSTILE') >= 0;
      var color = destroyed ? '#9ca3af' : (hostile ? '#f97316' : '#facc15');
      var html = '<span class="atak-drone-contact" style="display:block;width:12px;height:12px;transform:rotate(45deg);'
        + 'border:2px solid ' + color + ';background:' + (destroyed ? 'transparent' : color + '55') + '">'
        + (destroyed ? '<span style="display:block;transform:rotate(-45deg);color:' + color + ';font:700 11px/8px sans-serif;text-align:center">×</span>' : '')
        + '</span>';
      var m = L.marker(window.ATAKMap.latLngFromWorld(Number(t.pos_x), Number(t.pos_y)), {
        icon: L.divIcon({ className: '', html: html, iconSize: [16, 16], iconAnchor: [8, 8] }),
        zIndexOffset: 380
      });
      m.bindTooltip(escapeHtml((t.label || t.track_uid) + (destroyed ? ' · détruit (BDA à confirmer)' : '')
        + (t.call_sign_ref ? ' · ' + t.call_sign_ref : '') + (t.updated_at ? ' · ' + String(t.updated_at).slice(11, 19) + ' Z' : '')));
      m.addTo(layer);
    });
  }

  function refresh() {
    var base = apiBase();
    if (!base || document.hidden) return;
    fetch(base + '/api/atak/tracks?mapId=' + encodeURIComponent(mapId()) + '&prefix=DRN-&limit=60', {
      credentials: 'include',
      cache: 'no-store'
    }).then(function (r) { return r.ok ? r.json() : { tracks: [] }; }).then(function (data) {
      render(Array.isArray(data && data.tracks) ? data.tracks : []);
    }).catch(function () { /* garder les contacts affichés */ });
  }

  function start() {
    if (timer) return;
    refresh();
    timer = setInterval(refresh, POLL_MS);
    var box = document.getElementById('atak-show-drone-contacts');
    if (box) box.addEventListener('change', refresh);
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Carte prête après l'init d'ATAKMap : premier relevé différé.
    setTimeout(start, 4000);
  });

  return { refresh: refresh };
})();
