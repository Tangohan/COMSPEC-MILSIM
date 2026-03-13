/* COMSPEC ATAK - 9-Line CAS JTAC */
window.ATAKJTAC = (function () {
  var lineNames = ['line1', 'line2', 'line3', 'line4', 'line5', 'line6', 'line7', 'line8', 'line9'];

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : (window.location.protocol + '//' + window.location.hostname + ':3001');
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchNineLines() {
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
    var payload = { mapId: getMapId(), author: 'JTAC' };
    lineNames.forEach(function (k) {
      var input = document.querySelector('#atak-jtac-form-fields [name="' + k + '"]');
      if (input) payload[k] = input.value || '';
    });
    fetch(getApiBase() + '/api/nine-line', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function () {
      fetchNineLines();
      var wrap = document.getElementById('atak-jtac-form-fields');
      if (wrap) { wrap.style.display = 'none'; wrap.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; }); }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btnNew = document.getElementById('atak-jtac-new');
    var btnSubmit = document.getElementById('atak-jtac-submit');
    if (btnNew) btnNew.addEventListener('click', showForm);
    if (btnSubmit) btnSubmit.addEventListener('click', submitNineLine);
  });

  return { appendNineLine: appendNineLine, fetchNineLines: fetchNineLines };
})();
