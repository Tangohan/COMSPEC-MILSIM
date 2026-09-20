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
    fetch(url).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
      if (!Array.isArray(data)) return;
      assets = data;
      render();
      if (window.ATAKMap && window.ATAKMap.setAirAssets) window.ATAKMap.setAirAssets(assets);
      try { window.dispatchEvent(new CustomEvent('atak:units-updated')); } catch (e3) {}
    }).catch(function () {});
  }

  function getAssets() {
    return assets;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function statusClass(s) {
    if (!s) return '';
    s = (s || '').toUpperCase();
    if (s === 'SUSPECT') return 'atak-air-status-suspect';
    if (s === 'OFFLINE') return 'atak-air-status-offline';
    if (s === 'AVAILABLE') return 'atak-air-status-available';
    return 'atak-air-status-inflight';
  }

  function statusLabel(s) {
    s = String(s || '').toUpperCase();
    if (s === 'SUSPECT') return 'À vérifier';
    if (s === 'OFFLINE') return 'Hors liaison';
    if (s === 'AVAILABLE') return 'Au sol';
    return 'En vol';
  }

  function pilotLabel(s) {
    s = String(s || '').toUpperCase();
    if (s === 'ROGER') return 'Reçu';
    if (s === 'INBOUND') return 'En approche';
    if (s === 'ONSTA') return 'À poste';
    if (s === 'ENGAGED') return 'Engagé';
    if (s === 'RTB') return 'Retour';
    return '';
  }

  function roleLabel(s) {
    s = String(s || '').toLowerCase();
    if (s === 'transport') return 'Transport';
    if (s === 'cas') return 'Appui aérien';
    if (s === 'recon') return 'Reconnaissance';
    if (s === 'medevac') return 'Évacuation sanitaire';
    if (s === 'resupply') return 'Ravitaillement';
    if (s === 'escort') return 'Escorte';
    return '';
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
      var pilot = pilotLabel(a.pilot_status || '');
      var role = roleLabel(a.mission_id || '');
      var dest = a.station || '';
      var notes = a.checklist || '';
      if (typeof notes === 'string' && notes.charAt(0) === '{') notes = '';
      var occ = a.occupants || a.crew;
      if (typeof occ === 'string') {
        try { occ = JSON.parse(occ); } catch (e2) { occ = []; }
      }
      var n = Array.isArray(occ) ? occ.length : 0;
      var bits = [];
      if (a.model) bits.push(esc(a.model) + (a.aircraft_count > 1 ? ' ×' + a.aircraft_count : ''));
      if (role) bits.push(esc(role));
      if (dest) bits.push(esc(dest));
      var html = '<div class="atak-air-asset-card ' + statusClass(status) + '" data-callsign="' + esc(a.callsign || '') + '" data-x="' + (a.pos_x != null ? a.pos_x : '') + '" data-y="' + (a.pos_y != null ? a.pos_y : '') + '">' +
        '<div class="atak-air-asset-callsign">' + esc(a.callsign || '—') + '</div>' +
        (bits.length ? '<div class="atak-air-asset-model">' + bits.join(' · ') + '</div>' : '') +
        (a.freq ? '<div class="atak-air-asset-freq">Fréquence ' + esc(a.freq) + '</div>' : '') +
        (a.laser ? '<div class="atak-air-asset-laser">Code laser ' + esc(a.laser) + '</div>' : '') +
        (a.auth_code || a.auth ? '<div class="atak-air-asset-auth">Auth. ' + esc(a.auth_code || a.auth) + '</div>' : '') +
        (n ? '<div class="atak-air-asset-crew">' + n + ' à bord</div>' : '') +
        (a.ordnance ? '<div class="atak-air-asset-ordnance">' + esc(a.ordnance) + '</div>' : '') +
        (a.eta_minutes != null && a.eta_minutes !== '' ? '<div class="atak-air-asset-eta">Arrivée estimée ' + esc(String(a.eta_minutes)) + ' min</div>' : '') +
        (a.bingo_fuel ? '<div class="atak-air-asset-play">Autonomie ' + esc(String(a.bingo_fuel)) + '</div>' : '') +
        (a.fuel_pct != null && a.fuel_pct !== '' ? '<div class="atak-air-asset-fuel">Carburant ' + esc(String(a.fuel_pct)) + ' %</div>' : '') +
        (notes ? '<div class="atak-air-asset-notes">' + esc(notes) + '</div>' : '') +
        '<span class="atak-air-asset-status ' + statusClass(status) + '">' + esc(statusLabel(status)) + '</span>' +
        (pilot ? '<div class="atak-air-asset-pilot">' + esc(pilot) + '</div>' : '') +
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
