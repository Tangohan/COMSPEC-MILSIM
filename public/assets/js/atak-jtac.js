/* COMSPEC ATAK - 9-Line CAS JTAC (format OTAN) */
window.ATAKJTAC = (function () {
  var lineLabels = [
    'IP',
    'Cap',
    'Distance',
    'Altitude',
    'Objectif',
    'Position',
    'Marquage',
    'Amis',
    'Sortie'
  ];

  function getApiBase() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getApiBase === 'function') {
      var fromSocket = String(window.ATAKSocket.getApiBase() || '').replace(/\/$/, '');
      if (fromSocket) return fromSocket;
    }
    return String(window.ATAK_API_BASE || '').replace(/\/$/, '');
  }
  function isNodeConfigured() {
    return !!getApiBase();
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function emptyHtml() {
    return '<div class="atak-empty-state atak-empty-state--compact" id="atak-jtac-empty">' +
      '<p class="atak-empty-state-title">Aucun appui en cours</p>' +
      '<p class="atak-empty-state-text">Les 9-Line du terrain apparaîtront ici. Vous pouvez aussi en créer une.</p>' +
      '</div>';
  }

  function renderList(htmlItems) {
    var el = document.getElementById('atak-jtac-list');
    if (!el) return;
    el.innerHTML = htmlItems && htmlItems.length ? htmlItems.join('') : emptyHtml();
  }

  function parseList(data) {
    return Array.isArray(data) ? data : (data && Array.isArray(data.items) ? data.items : []);
  }

  function fetchJsonList(url) {
    return fetch(url, { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(parseList)
      .catch(function () { return []; });
  }

  function lineValue(c, key) {
    if (!c) return '';
    if (c[key]) return String(c[key]);
    if (c.lines && c.lines[key]) {
      return typeof c.lines[key] === 'object' ? String(c.lines[key].value || '') : String(c.lines[key]);
    }
    return '';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatLines(c) {
    var keys = ['line1', 'line2', 'line3', 'line4', 'line5', 'line6', 'line7', 'line8', 'line9'];
    return '<ol class="atak-nine-line-nato">' + keys.map(function (k, i) {
      var v = lineValue(c, k) || '—';
      return '<li><span class="atak-nine-line-n">' + (i + 1) + '. ' + escapeHtml(lineLabels[i]) + '</span> ' +
        escapeHtml(v) + '</li>';
    }).join('') + '</ol>';
  }

  function formatCas(c) {
    var status = String(c.status || 'SUBMITTED').toUpperCase();
    var assigned = c.assignedAircraft || c.assigned_aircraft || '—';
    return '<div class="atak-nine-line-item atak-cas-item" data-id="' + escapeHtml(c.id || '') + '">' +
      '<strong>CAS #' + escapeHtml(c.id || '') + '</strong> <span class="atak-cas-status">' + escapeHtml(status) +
      '</span> <span class="atak-cas-assigned">' + escapeHtml(assigned) + '</span>' + formatLines(c) + '</div>';
  }

  function formatNineLine(n) {
    var status = n.status || 'active';
    return '<div class="atak-nine-line-item" data-id="' + escapeHtml(n.id || '') + '">' +
      '<strong>9-Line #' + escapeHtml(n.id || '') + '</strong> ' + escapeHtml(status) + formatLines(n) + '</div>';
  }

  function refresh() {
    if (!isNodeConfigured()) {
      renderList([]);
      return;
    }
    var map = getMapId();
    var base = getApiBase();
    Promise.all([
      fetchJsonList(base + '/api/cas?mapId=' + map),
      fetchJsonList(base + '/api/nine-line?mapId=' + map)
    ]).then(function (parts) {
      renderList(parts[0].map(formatCas).concat(parts[1].map(formatNineLine)));
    });
  }

  function fetchCas() {
    refresh();
  }

  function fetchNineLines() {
    refresh();
  }

  function appendNineLine(nineLine) {
    var el = document.getElementById('atak-jtac-list');
    if (!el) return;
    var empty = document.getElementById('atak-jtac-empty');
    if (empty) empty.remove();
    el.insertAdjacentHTML('beforeend', formatNineLine(nineLine));
  }

  function fillLaserSelect() {
    var sel = document.getElementById('atak-jtac-laser');
    if (!sel) return;
    var current = sel.value;
    var codes = (window.ATAKLaserCodes && window.ATAKLaserCodes.getCodes)
      ? window.ATAKLaserCodes.getCodes()
      : [];
    sel.innerHTML = '<option value="">Aucun code ami</option>';
    codes.forEach(function (c) {
      var code = String(c.laser_code || c.code || '').trim();
      if (!code) return;
      var opt = document.createElement('option');
      opt.value = code;
      opt.textContent = (c.call_sign || c.callsign || 'Ami') + ' — ' + code;
      sel.appendChild(opt);
    });
    if (current) sel.value = current;
  }

  function syncMarkVisibility() {
    var mark = document.getElementById('atak-jtac-mark');
    var wrap = document.getElementById('atak-jtac-laser-wrap');
    if (!wrap) return;
    wrap.hidden = !mark || mark.value !== 'LASER';
  }

  function showForm() {
    var wrap = document.getElementById('atak-jtac-form-fields');
    if (wrap) wrap.hidden = false;
    if (window.ATAKLaserCodes && window.ATAKLaserCodes.fetchLaserCodes) {
      window.ATAKLaserCodes.fetchLaserCodes();
    }
    fillLaserSelect();
    syncMarkVisibility();
  }

  function fieldVal(name) {
    var input = document.querySelector('#atak-jtac-form-fields [name="' + name + '"]');
    return input ? String(input.value || '').trim() : '';
  }

  function buildLine7() {
    var mark = fieldVal('line7_mark') || 'NONE';
    var laser = fieldVal('laser_code');
    if (mark === 'LASER') {
      return laser ? ('LASER ' + laser) : 'LASER';
    }
    if (mark === 'SMOKE') return 'Fumigène';
    if (mark === 'IR') return 'Infrarouge';
    return 'Aucun';
  }

  function submitNineLine() {
    if (!isNodeConfigured()) {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer la 9-Line. Vérifiez la liaison du poste.');
      return;
    }
    var author = (window.ATAK_USER && (window.ATAK_USER.callsign || window.ATAK_USER.displayName)) || 'JTAC';
    var remarks = fieldVal('remarks');
    var payload = {
      mapId: getMapId(),
      author: author,
      assigned_aircraft: fieldVal('assigned_aircraft'),
      line1: fieldVal('line1'),
      line2: fieldVal('line2'),
      line3: fieldVal('line3'),
      line4: fieldVal('line4'),
      line5: fieldVal('line5'),
      line6: fieldVal('line6'),
      line7: buildLine7(),
      line8: fieldVal('line8'),
      line9: remarks ? (fieldVal('line9') + (fieldVal('line9') ? ' — ' : '') + remarks) : fieldVal('line9')
    };
    payload.lines = {
      line1: payload.line1,
      line2: payload.line2,
      line3: payload.line3,
      line4: payload.line4,
      line5: payload.line5,
      line6: payload.line6,
      line7: payload.line7,
      line8: payload.line8,
      line9: payload.line9
    };
    fetch(getApiBase() + '/api/cas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) throw new Error('cas');
      return r.json();
    }).then(function () {
      refresh();
      var wrap = document.getElementById('atak-jtac-form-fields');
      if (wrap) {
        wrap.hidden = true;
        wrap.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
        var mark = document.getElementById('atak-jtac-mark');
        if (mark) mark.value = 'LASER';
        fillLaserSelect();
        syncMarkVisibility();
      }
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer la 9-Line. Vérifiez la liaison du poste.');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btnNew = document.getElementById('atak-jtac-new');
    var btnSubmit = document.getElementById('atak-jtac-submit');
    var mark = document.getElementById('atak-jtac-mark');
    if (btnNew) btnNew.addEventListener('click', showForm);
    if (btnSubmit) btnSubmit.addEventListener('click', submitNineLine);
    if (mark) mark.addEventListener('change', syncMarkVisibility);
  });

  return {
    appendNineLine: appendNineLine,
    fetchNineLines: fetchNineLines,
    fetchCas: fetchCas,
    refresh: refresh,
    fillLaserSelect: fillLaserSelect
  };
})();
