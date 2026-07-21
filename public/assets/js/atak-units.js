/* COMSPEC ATAK - Panneau droit Effectifs / contacts */
window.ATAKUnits = (function () {
  var units = [];
  var filterLive = true;
  var filterText = '';

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

  function fetchUnits() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/units?mapId=' + getMapId();
    fetch(url, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (data) {
      units = Array.isArray(data) ? data : (data.units || []);
      render();
      if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
        window.ATAKMap.setUnitsMarkers(units);
      }
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de charger les unités.');
      render();
    });
  }

  function setUnits(list) {
    units = Array.isArray(list) ? list : [];
    render();
    if (window.ATAKMap && window.ATAKMap.setUnitsMarkers) {
      window.ATAKMap.setUnitsMarkers(units);
    }
  }

  function parseExtra(u) {
    try {
      return typeof u.extra === 'string' ? JSON.parse(u.extra) : (u.extra || {});
    } catch (e) {
      return {};
    }
  }

  function vitalTone(kind, value) {
    if (kind === 'health') {
      var h = String(value || '').toLowerCase();
      if (h === 'ok' || h === 'stable' || h === 'healthy') return 'ok';
      if (h === 'wounded' || h === 'injured') return 'warn';
      return 'crit';
    }
    if (kind === 'fuel' || kind === 'battery') {
      var n = Number(value);
      if (isNaN(n)) return '';
      if (n <= 15) return 'crit';
      if (n <= 35) return 'warn';
      return 'ok';
    }
    return '';
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  var emptyStateHtml = '<div class="atak-units-empty" id="atak-units-empty">' +
    '<div class="atak-units-empty-icon" aria-hidden="true">' +
    '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>' +
    '</div>' +
    '<p class="atak-units-empty-title">Aucun contact en liaison</p>' +
    '<p class="atak-units-empty-text">Les positions remontées depuis Arma s’affichent ici. Vérifiez la liaison du mod, ou générez un code via <strong>Connexion en jeu</strong>.</p>' +
    '</div>';

  function updateSummary() {
    var linked = 0;
    var delayed = 0;
    units.forEach(function (u) {
      var s = (u.status || '').toLowerCase();
      if (s === 'linked') linked++;
      else if (s === 'delayed') delayed++;
    });
    var countEl = document.getElementById('atak-units-count');
    if (countEl) countEl.textContent = String(units.length);
    var liveEl = document.getElementById('atak-units-sum-live');
    if (liveEl) liveEl.textContent = linked + ' en liaison';
    var delayedEl = document.getElementById('atak-units-sum-delayed');
    if (delayedEl) delayedEl.textContent = delayed + ' en retard';
    var chipEl = document.getElementById('atak-chip-contacts-value');
    if (chipEl) chipEl.textContent = String(linked);
  }

  function render() {
    var listEl = document.getElementById('atak-units-list');
    if (!listEl) return;
    updateSummary();
    var filtered = units.filter(function (u) {
      if (filterLive && u.status !== 'linked') return false;
      if (filterText) {
        var t = filterText.toLowerCase();
        var ex = parseExtra(u);
        var role = (u.role || ex.role || '').toLowerCase();
        return (u.call_sign && u.call_sign.toLowerCase().indexOf(t) >= 0) || role.indexOf(t) >= 0;
      }
      return true;
    });
    if (filtered.length === 0) {
      listEl.innerHTML = emptyStateHtml;
      return;
    }
    listEl.innerHTML = filtered.map(function (u) {
      var ex = parseExtra(u);
      var health = ex.health || u.health || 'ok';
      var statusClass = (u.status || 'linked').toLowerCase();
      var statusLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.statusLabelFr)
        ? window.ATAKUnitPopup.statusLabelFr(u.status || 'linked')
        : (u.status || 'En liaison');
      var cardClass = 'atak-unit-card ' + (statusClass === 'delayed' ? 'delayed' : (statusClass === 'offline' ? 'delayed' : 'linked'));
      var healthNorm = String(health || '').toLowerCase();
      if (healthNorm === 'wounded' || healthNorm === 'injured') cardClass += ' atak-unit-bft-wounded';
      if (healthNorm === 'unconscious' || healthNorm === 'cardiac_arrest' || healthNorm === 'cardiac-arrest' || healthNorm === 'dead' || healthNorm === 'kia') {
        cardClass += ' atak-unit-bft-critical';
      }
      var grid = u.grid_ref || '—';
      var heading = u.heading != null ? (Math.round(u.heading) + '°') : '—';
      var roleText = u.role || ex.role || '—';
      var healthLabel = (window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr)
        ? window.ATAKUnitPopup.healthLabelFr(health)
        : health;
      var battery = ex.battery != null ? ex.battery : (u.battery != null ? u.battery : null);
      var fuel = ex.fuel !== undefined && ex.fuel !== '' ? ex.fuel : null;
      var ammo = ex.ammo !== undefined && ex.ammo !== '' && ex.ammo !== 'n/a' ? ex.ammo : null;
      var radio = ex.radio_freq !== undefined && ex.radio_freq !== '' ? ex.radio_freq : null;

      var vitals = [];
      var hTone = vitalTone('health', health);
      if (healthNorm !== 'ok' && healthNorm !== 'stable' && healthNorm !== 'healthy') {
        vitals.push('<span class="atak-unit-vital atak-unit-vital--' + hTone + '">État ' + esc(healthLabel) + '</span>');
      } else {
        vitals.push('<span class="atak-unit-vital atak-unit-vital--ok">État stable</span>');
      }
      if (battery != null && battery !== '') {
        var bTone = vitalTone('battery', battery);
        vitals.push('<span class="atak-unit-vital' + (bTone ? ' atak-unit-vital--' + bTone : '') + '">Batt. ' + esc(battery) + '%</span>');
      }
      if (fuel != null) {
        var fTone = vitalTone('fuel', fuel);
        vitals.push('<span class="atak-unit-vital' + (fTone ? ' atak-unit-vital--' + fTone : '') + '">Carb. ' + esc(fuel) + '%</span>');
      }
      if (ammo != null) {
        vitals.push('<span class="atak-unit-vital">Mun. ' + esc(ammo) + '</span>');
      }
      if (radio != null) {
        vitals.push('<span class="atak-unit-vital">Radio ' + esc(radio) + '</span>');
      }

      var tooltipParts = [];
      if (healthNorm !== 'ok' && healthNorm !== 'stable') tooltipParts.push('État : ' + healthLabel);
      if (fuel != null) tooltipParts.push('Carburant ' + fuel + '%');
      if (ammo != null) tooltipParts.push(String(ammo));
      if (radio != null) tooltipParts.push('Radio ' + radio);
      var tooltip = tooltipParts.join(' · ');

      var callsignKey = (u.call_sign || '').toUpperCase().trim();
      var userLink = (window.ATAK_CALLSIGN_TO_USER && callsignKey && window.ATAK_CALLSIGN_TO_USER[callsignKey])
        ? '<a href="' + (window.ATAK_CALLSIGN_TO_USER[callsignKey].url || '') + '" class="atak-unit-fiche-link" onclick="event.stopPropagation();" title="Ouvrir la fiche personnel">Fiche</a>'
        : '';
      var natoBadge = '';
      if (window.NatoSidcIcons && window.NatoSidcIcons.listBadgeHtml) {
        natoBadge = window.NatoSidcIcons.listBadgeHtml({
          affiliation: ex.affiliation || ex.affil || u.affiliation || 'friend',
          role: roleText,
          size: 20,
        });
      }
      return '<div class="' + cardClass + '" data-unit-id="' + esc(u.id || '') + '" data-grid="' + esc(u.grid_ref || '') + '" data-x="' + esc(u.pos_x || '') + '" data-y="' + esc(u.pos_y || '') + '" title="' + esc(tooltip) + '">' +
        '<div class="atak-unit-callsign-wrap">' +
        '<div class="atak-unit-callsign">' + natoBadge + esc(u.call_sign || '—') + '</div>' +
        '<span class="atak-unit-status ' + statusClass + '">' + esc(statusLabel) + '</span>' +
        (userLink ? userLink : '') +
        '</div>' +
        '<div class="atak-unit-role">' + esc(roleText !== '—' ? roleText : 'Rôle non renseigné') + '</div>' +
        '<div class="atak-unit-vitals">' + vitals.join('') + '</div>' +
        '<div class="atak-unit-meta-row">' +
        '<div class="atak-unit-grid">Coord. ' + esc(grid) + '</div>' +
        '<div class="atak-unit-heading">Cap ' + esc(heading) + '</div>' +
        '</div>' +
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
