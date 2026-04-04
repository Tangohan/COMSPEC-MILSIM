/* COMSPEC ATAK - 9-Line CAS JTAC + CAS API */
window.ATAKJTAC = (function () {
  var lineNames = ['line1', 'line2', 'line3', 'line4', 'line5', 'line6', 'line7', 'line8', 'line9'];

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function isNodeConfigured() {
    var b = getApiBase();
    return b && b.trim() !== '';
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchCas() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/cas?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-jtac-list');
      if (el) el.innerHTML = list.map(formatCas).join('');
    }).catch(function () {});
  }

  function formatCas(c) {
    var status = (c.status || c.status || 'SUBMITTED').toUpperCase();
    var assigned = c.assignedAircraft || c.assigned_aircraft || '—';
    var lines = lineNames.map(function (k) { return (c[k] || c.lines && c.lines[k] && c.lines[k].value) || '—'; }).join(' | ');
    return '<div class="atak-nine-line-item atak-cas-item" data-id="' + (c.id || '') + '">' +
      '<strong>CAS #' + (c.id || '') + '</strong> <span class="atak-cas-status">' + status + '</span> <span class="atak-cas-assigned">' + assigned + '</span><br/>' + lines + '</div>';
  }

  function fetchNineLines() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/nine-line?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = Array.isArray(data) ? data : [];
      var el = document.getElementById('atak-jtac-list');
      if (el) el.innerHTML = list.map(formatNineLine).join('');
    }).catch(function () {});
  }

  function formatNineLine(n) {
    var lines = lineNames.map(function (k) { return n[k] || '—'; }).join(' | ');
    return '<div class="atak-nine-line-item" data-id="' + (n.id || '') + '">' +
      '<strong>9-Line #' + (n.id || '') + '</strong> ' + (n.status || 'active') + '<br/>' + lines + '</div>';
  }

  function appendNineLine(nineLine) {
    var el = document.getElementById('atak-jtac-list');
    if (el) el.insertAdjacentHTML('beforeend', formatNineLine(nineLine));
  }

  function showForm() {
    var wrap = document.getElementById('atak-jtac-form-fields');
    if (wrap) wrap.style.display = 'block';
  }

  function submitNineLine() {
    if (!isNodeConfigured()) {
      if (window.ATAKShowError) window.ATAKShowError('Configurez l\'URL du nœud ATAK dans Admin → Configuration ATAK.');
      return;
    }
    var author = (window.ATAK_USER && (window.ATAK_USER.callsign || window.ATAK_USER.displayName)) || 'JTAC';
    var payload = { mapId: getMapId(), author: author, missionId: 'op_1', lines: {} };
    lineNames.forEach(function (k) {
      var input = document.querySelector('#atak-jtac-form-fields [name="' + k + '"]');
      if (input) payload.lines[k] = payload[k] = input.value || '';
    });
    fetch(getApiBase() + '/api/cas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function () {
      fetchCas();
      var wrap = document.getElementById('atak-jtac-form-fields');
      if (wrap) { wrap.style.display = 'none'; wrap.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; }); }
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Erreur envoi CAS.');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btnNew = document.getElementById('atak-jtac-new');
    var btnSubmit = document.getElementById('atak-jtac-submit');
    if (btnNew) btnNew.addEventListener('click', showForm);
    if (btnSubmit) btnSubmit.addEventListener('click', submitNineLine);
  });

  return { appendNineLine: appendNineLine, fetchNineLines: fetchNineLines, fetchCas: fetchCas };
})();
