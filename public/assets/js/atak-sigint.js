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

  function render(rows) {
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
  });

  return { refresh: refresh };
})();
