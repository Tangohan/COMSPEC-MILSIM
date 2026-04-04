/* COMSPEC ATAK - Panneau droit Unités / Groupes */
window.ATAKUnits = (function () {
  var units = [];
  var filterLive = true;
  var filterText = '';

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : (window.location.protocol + '//' + window.location.hostname + ':3001');
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchUnits() {
    var url = getApiBase() + '/api/units?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      units = Array.isArray(data) ? data : (data.units || []);
      render();
    }).catch(function () { render(); });
  }

  function setUnits(list) {
    units = Array.isArray(list) ? list : [];
    render();
  }

  var emptyStateHtml = '<div class="atak-units-empty" id="atak-units-empty">' +
    '<div class="atak-units-empty-icon" aria-hidden="true">' +
    '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>' +
    '</div><span>No contacts connected</span></div>';

  function render() {
    var listEl = document.getElementById('atak-units-list');
    if (!listEl) return;
    var filtered = units.filter(function (u) {
      if (filterLive && u.status !== 'linked') return false;
      if (filterText) {
        var t = filterText.toLowerCase();
        return (u.call_sign && u.call_sign.toLowerCase().indexOf(t) >= 0) ||
          (u.role && u.role.toLowerCase().indexOf(t) >= 0);
      }
      return true;
    });
    if (filtered.length === 0) {
      listEl.innerHTML = emptyStateHtml;
      return;
    }
    listEl.innerHTML = filtered.map(function (u) {
      var statusClass = (u.status || 'linked').toLowerCase();
      var cardClass = 'atak-unit-card ' + (statusClass === 'delayed' ? 'delayed' : 'linked');
      var grid = u.grid_ref || '—';
      var heading = u.heading != null ? (Math.round(u.heading) + '°') : '—';
      return '<div class="' + cardClass + '" data-unit-id="' + (u.id || '') + '" data-grid="' + (u.grid_ref || '') + '" data-x="' + (u.pos_x || '') + '" data-y="' + (u.pos_y || '') + '">' +
        '<div class="atak-unit-callsign">' + (u.call_sign || '—') + '</div>' +
        '<div class="atak-unit-role">' + (u.role || '—') + '</div>' +
        '<span class="atak-unit-status ' + statusClass + '">' + (u.status || 'Linked') + '</span>' +
        '<div class="atak-unit-grid">Grid ' + grid + '</div>' +
        '<div class="atak-unit-heading">Heading ' + heading + '</div>' +
        '</div>';
    }).join('');

    listEl.querySelectorAll('.atak-unit-card').forEach(function (card) {
      card.addEventListener('click', function () {
        var x = this.getAttribute('data-x');
        var y = this.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      });
    });
  }

  function initFilters() {
    var filterEl = document.getElementById('atak-units-filter');
    var btnLive = document.getElementById('atak-filter-live');
    var btnAll = document.getElementById('atak-filter-all');
    if (filterEl) filterEl.addEventListener('input', function () { filterText = this.value; render(); });
    if (btnLive) btnLive.addEventListener('click', function () { filterLive = true; btnLive.classList.add('active'); if (btnAll) btnAll.classList.remove('active'); render(); });
    if (btnAll) btnAll.addEventListener('click', function () { filterLive = false; btnAll.classList.add('active'); if (btnLive) btnLive.classList.remove('active'); render(); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFilters);
  } else {
    initFilters();
  }

  return { setUnits: setUnits, fetchUnits: fetchUnits };
})();
