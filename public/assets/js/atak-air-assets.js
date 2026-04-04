/* COMSPEC ATAK - Air Support Assets (Flight Manifest) */
window.ATAKAirAssets = (function () {
  var assets = [];

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchAirAssets() {
    var base = getApiBase();
    var url = (base || '') + '/api/atak/air-assets?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      assets = Array.isArray(data) ? data : [];
      render();
      if (window.ATAKMap && window.ATAKMap.setAirAssets) window.ATAKMap.setAirAssets(assets);
    }).catch(function () {
      assets = [];
      render();
    });
  }

  function getAssets() {
    return assets;
  }

  function statusClass(s) {
    if (!s) return '';
    s = (s || '').toUpperCase();
    if (s === 'SUSPECT') return 'atak-air-status-suspect';
    if (s === 'OFFLINE') return 'atak-air-status-offline';
    return 'atak-air-status-inflight';
  }

  function render() {
    var listEl = document.getElementById('atak-air-assets-list');
    var emptyEl = document.getElementById('atak-air-assets-empty');
    if (!listEl) return;
    if (assets.length === 0) {
      if (emptyEl) emptyEl.style.display = '';
      listEl.querySelectorAll('.atak-air-asset-card').forEach(function (n) { n.remove(); });
      return;
    }
    if (emptyEl) emptyEl.style.display = 'none';
    listEl.querySelectorAll('.atak-air-asset-card').forEach(function (n) { n.remove(); });
    assets.forEach(function (a) {
      var status = (a.status || 'IN-FLIGHT').toUpperCase();
      var pilotStatus = (a.pilot_status || '').toUpperCase();
      var html = '<div class="atak-air-asset-card ' + statusClass(status) + '" data-callsign="' + (a.callsign || '').replace(/"/g, '&quot;') + '" data-x="' + (a.pos_x != null ? a.pos_x : '') + '" data-y="' + (a.pos_y != null ? a.pos_y : '') + '">' +
        '<div class="atak-air-asset-callsign">' + (a.callsign || '—') + '</div>' +
        '<div class="atak-air-asset-model">' + (a.model || '—') + (a.aircraft_count > 1 ? ' ×' + a.aircraft_count : '') + '</div>' +
        '<div class="atak-air-asset-freq">FREQ ' + (a.freq || '—') + '</div>' +
        '<div class="atak-air-asset-laser">LASER ' + (a.laser || '1688') + '</div>' +
        '<span class="atak-air-asset-status ' + statusClass(status) + '">' + (status === 'SUSPECT' ? 'SUSPECT' : status === 'OFFLINE' ? 'OFFLINE' : 'IN-FLIGHT') + '</span>' +
        (pilotStatus ? '<div class="atak-air-asset-pilot">' + pilotStatus + '</div>' : '') +
        '</div>';
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      if (emptyEl) listEl.insertBefore(wrap.firstElementChild, emptyEl);
      else listEl.appendChild(wrap.firstElementChild);
    });
  }

  return {
    fetchAirAssets: fetchAirAssets,
    getAssets: getAssets
  };
})();
