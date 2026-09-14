(function () {
  'use strict';

  if (!window.ATAK_OVERWATCH_BETA || !window.L || window.__OVERWATCH_STANDALONE__) return;
  window.__OVERWATCH_STANDALONE__ = true;

  var config = window.ATAK_MAP_CONFIG || {};
  var apiBase = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
  var mapId = Number(window.ATAK_DEFAULT_MAP_ID || 1);
  var markers = {};
  var units = [];
  var selected = null;
  var lastRx = 0;
  var requestStarted = 0;

  var map = L.map('ow-map', {
    crs: L.CRS.Simple,
    zoomControl: false,
    attributionControl: true,
    minZoom: Number(config.minZoom || 0),
    maxZoom: Number(config.maxZoom || 6)
  });

  function worldSize() { return Number(config.worldSize || 30720); }
  function worldToLatLng(x, y) {
    return L.latLng(Number(y) + Number(config.offsetY || 0), Number(x) + Number(config.offsetX || 0));
  }

  var southWest = worldToLatLng(0, 0);
  var northEast = worldToLatLng(worldSize(), worldSize());
  var bounds = L.latLngBounds(southWest, northEast);
  if (config.tilePattern) {
    L.tileLayer(config.tilePattern, {
      tileSize: Number(config.tileSize || 212), minZoom: Number(config.minZoom || 0),
      maxZoom: Number(config.maxZoom || 6), noWrap: true, bounds: bounds,
      attribution: config.attribution || '&copy; Bohemia Interactive'
    }).addTo(map);
  }
  map.fitBounds(bounds);
  if (Number.isFinite(Number(config.defaultZoom))) map.setZoom(Number(config.defaultZoom));

  function clean(value, fallback) {
    var text = String(value == null ? '' : value).trim();
    return text || fallback || '—';
  }
  function callsign(unit) { return clean(unit.call_sign || unit.callsign || unit.name || unit.label, 'CONTACT'); }
  function group(unit) { return clean(unit.fire_team_label || unit.group_name || unit.group, 'UNITÉ NON AFFECTÉE'); }
  function unitId(unit) { return String(unit.id || unit.uuid || callsign(unit)); }
  function point(unit) {
    var x = Number(unit.pos_x != null ? unit.pos_x : unit.x);
    var y = Number(unit.pos_y != null ? unit.pos_y : unit.y);
    return Number.isFinite(x) && Number.isFinite(y) && (Math.abs(x) > .5 || Math.abs(y) > .5) ? worldToLatLng(x, y) : null;
  }
  function initials(value) { return value.replace(/[^a-z0-9]/gi, '').slice(0, 2).toUpperCase() || '•'; }
  function markerIcon(unit) {
    return L.divIcon({className: 'ow-marker', iconSize: [140, 28], iconAnchor: [7, 14],
      html: '<div><i></i><span>' + escapeHtml(callsign(unit)) + '</span></div>'});
  }
  function escapeHtml(value) {
    var node = document.createElement('span'); node.textContent = String(value); return node.innerHTML;
  }

  function selectUnit(unit) {
    selected = unit;
    document.getElementById('ow-drawer-title').textContent = callsign(unit);
    var rows = [['TYPE', clean(unit.type || unit.role, 'BFT')], ['GROUPE', group(unit)],
      ['POSITION', clean(unit.grid || unit.mgrs, Math.round(Number(unit.pos_x || 0)) + ' / ' + Math.round(Number(unit.pos_y || 0)))],
      ['STATUT', unit.status === 'delayed' ? 'DIFFÉRÉ' : clean(unit.status, 'ACTIF').toUpperCase()], ['SOURCE', clean(unit.source, 'ATHENA LINK')]];
    document.getElementById('ow-drawer-data').innerHTML = rows.map(function (row) {
      return '<div><dt>' + escapeHtml(row[0]) + '</dt><dd>' + escapeHtml(row[1]) + '</dd></div>';
    }).join('');
    document.getElementById('ow-drawer').hidden = false;
  }

  function renderMap() {
    var alive = {};
    units.forEach(function (unit) {
      var location = point(unit); if (!location) return;
      var id = unitId(unit); alive[id] = true;
      if (!markers[id]) {
        markers[id] = L.marker(location, {icon: markerIcon(unit)}).addTo(map).on('click', function () { selectUnit(unit); });
      } else {
        markers[id].setLatLng(location).setIcon(markerIcon(unit));
        markers[id].off('click').on('click', function () { selectUnit(unit); });
      }
    });
    Object.keys(markers).forEach(function (id) { if (!alive[id]) { map.removeLayer(markers[id]); delete markers[id]; } });
  }

  function renderList() {
    var query = document.getElementById('ow-search').value.trim().toLowerCase();
    var visible = units.filter(function (unit) { return (callsign(unit) + ' ' + group(unit)).toLowerCase().indexOf(query) !== -1; });
    document.getElementById('ow-contact-list').innerHTML = visible.map(function (unit) {
      var stale = unit.status === 'delayed' || unit.status === 'offline';
      return '<button class="ow-contact" data-unit-id="' + escapeHtml(unitId(unit)) + '"><span class="ow-contact-icon">' + initials(callsign(unit)) + '</span><span><strong>' + escapeHtml(callsign(unit)) + '</strong><small>' + escapeHtml(group(unit)) + '</small></span><em class="ow-contact-state' + (stale ? ' is-stale' : '') + '">' + (unit.status === 'offline' ? 'OFFLINE' : (stale ? 'DELAY' : 'LIVE')) + '</em></button>';
    }).join('');
    document.getElementById('ow-contact-count').textContent = String(units.length);
    document.getElementById('ow-empty').hidden = units.some(point);
  }

  function applyPayload(payload) {
    var list = Array.isArray(payload) ? payload : (payload.units || payload.data || []);
    units = Array.isArray(list) ? list : [];
    lastRx = Date.now();
    renderMap(); renderList(); syncStatus(true);
    window.dispatchEvent(new CustomEvent('overwatch:units-updated', {detail: {units: units}}));
  }

  function refresh() {
    requestStarted = Date.now();
    fetch(apiBase + '/api/units?mapId=' + encodeURIComponent(mapId) + '&include_gateway=1', {credentials: 'include', headers: {'Accept': 'application/json'}})
      .then(function (response) { if (!response.ok) throw new Error(String(response.status)); return response.json(); })
      .then(applyPayload).catch(function () { syncStatus(false); });
  }
  function syncStatus(ok) {
    var session = document.querySelector('.ow-session');
    session.classList.toggle('is-live', ok); session.classList.toggle('is-offline', !ok);
    document.getElementById('ow-link-label').textContent = ok ? 'ATHENA LINKED' : 'RECONNEXION';
    document.getElementById('ow-footer-link').textContent = ok ? 'ATHENA ● LINKED' : 'ATHENA ● DÉGRADÉ';
    if (requestStarted) document.getElementById('ow-latency').textContent = 'RX ' + Math.max(0, Date.now() - requestStarted) + ' MS';
  }

  document.getElementById('ow-contact-list').addEventListener('click', function (event) {
    var row = event.target.closest('[data-unit-id]'); if (!row) return;
    var unit = units.find(function (item) { return unitId(item) === row.dataset.unitId; });
    if (unit) { selectUnit(unit); var location = point(unit); if (location) map.panTo(location); }
  });
  document.getElementById('ow-search').addEventListener('input', renderList);
  document.querySelector('[data-close-drawer]').addEventListener('click', function () { document.getElementById('ow-drawer').hidden = true; });
  document.querySelector('[data-center-selected]').addEventListener('click', function () { var location = selected && point(selected); if (location) map.setView(location, Math.max(map.getZoom(), 4)); });
  document.querySelectorAll('[data-toggle-roster]').forEach(function (button) { button.addEventListener('click', function () { document.getElementById('ow-contacts').classList.toggle('is-open'); }); });
  document.querySelectorAll('[data-tool]').forEach(function (button) { button.addEventListener('click', function () {
    var tool = button.dataset.tool;
    if (tool === 'zoom-in') map.zoomIn(); if (tool === 'zoom-out') map.zoomOut();
    if (tool === 'center') map.fitBounds(bounds); if (tool === 'refresh') refresh();
  }); });
  map.on('mousemove', function (event) { document.getElementById('ow-coordinate').textContent = 'MAP ' + event.latlng.lng.toFixed(4) + ' / ' + event.latlng.lat.toFixed(4) + ' · LIVE'; });

  var palette = document.getElementById('ow-palette');
  function togglePalette(show) { palette.hidden = !show; if (show) document.getElementById('ow-command-input').focus(); }
  document.querySelector('[data-command]').addEventListener('click', function () { togglePalette(true); });
  palette.addEventListener('click', function (event) { if (event.target === palette) togglePalette(false); });
  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); togglePalette(palette.hidden); }
    if (event.key === 'Escape') togglePalette(false);
  });
  document.getElementById('ow-command-input').addEventListener('input', function () { document.getElementById('ow-search').value = this.value; renderList(); });

  refresh();
  window.setInterval(refresh, 5000);
  window.setInterval(function () { if (lastRx && Date.now() - lastRx > 15000) syncStatus(false); }, 3000);
}());
