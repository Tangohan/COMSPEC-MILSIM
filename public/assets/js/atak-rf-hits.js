/* COMSPEC ATAK — Fieldwatch RF hits (Lot 1) */
window.ATAKRFHITS = (function () {
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

  function bandLabel(band) {
    var b = String(band || '').toLowerCase();
    if (b === 'wifi') return 'Wi‑Fi';
    if (b === 'ble') return 'BLE';
    if (b === 'tracker') return 'Tracker';
    if (b === 'camera') return 'Caméra';
    if (b === 'phone') return 'Téléphone';
    return 'RF';
  }

  function render(rows) {
    var el = document.getElementById('atak-rf-list');
    if (!el) return;
    if (!rows.length) {
      el.innerHTML = '<p class="atak-panel-hint">Aucune détection RF pour le moment.</p>';
      return;
    }
    el.innerHTML = rows.map(function (r) {
      var label = r.label || r.emitter_uid || 'Émetteur';
      var rssi = r.signal_dbm != null && r.signal_dbm !== '' ? (Math.round(Number(r.signal_dbm)) + ' dBm') : '—';
      var x = r.pos_x != null ? Math.round(Number(r.pos_x)) : '—';
      var y = r.pos_y != null ? Math.round(Number(r.pos_y)) : '—';
      return '<button type="button" class="atak-sigint-item atak-rf-item" data-x="' + escapeHtml(r.pos_x || '') +
        '" data-y="' + escapeHtml(r.pos_y || '') + '">' +
        '<strong>' + escapeHtml(label) + '</strong> · ' + escapeHtml(bandLabel(r.band)) + ' · ' + escapeHtml(rssi) +
        '<span class="atak-sigint-meta">' + escapeHtml(x + ' / ' + y) +
        (r.sensor_callsign ? ' · ' + escapeHtml(r.sensor_callsign) : '') +
        (r.created_at ? ' · ' + escapeHtml(formatWhen(r.created_at)) : '') + '</span></button>';
    }).join('');
  }

  function refresh() {
    var base = String(apiBase() || '').replace(/\/$/, '');
    var el = document.getElementById('atak-rf-list');
    if (!base || !el) return;
    fetch(base + '/api/atak/rf-hits?mode=markers&mapId=' + mapId() + '&limit=80', {
      credentials: 'include',
      cache: 'no-store'
    }).then(function (r) { return r.ok ? r.json() : []; }).then(function (data) {
      render(Array.isArray(data) ? data : []);
    }).catch(function () {
      render([]);
    });
  }

  function onListClick(ev) {
    var btn = ev.target.closest('.atak-rf-item');
    if (!btn) return;
    var x = parseFloat(btn.getAttribute('data-x'));
    var y = parseFloat(btn.getAttribute('data-y'));
    if (isNaN(x) || isNaN(y)) return;
    if (window.ATAKMap && typeof window.ATAKMap.centerOn === 'function') {
      window.ATAKMap.centerOn(y, x);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('atak-rf-refresh');
    var list = document.getElementById('atak-rf-list');
    if (btn) btn.addEventListener('click', refresh);
    if (list) list.addEventListener('click', onListClick);
  });

  return { refresh: refresh, render: render };
})();
