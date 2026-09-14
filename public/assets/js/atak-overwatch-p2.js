/* Overwatch P2 — portabilité mission et accès aux outils d'analyse ATAK existants. */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__ATAK_OVERWATCH_P2_BOOTSTRAPPED__) return;
  window.__ATAK_OVERWATCH_P2_BOOTSTRAPPED__ = true;

  var FORMAT = 'athena-overwatch-mission';
  var VERSION = 1;
  var MAX_IMPORT_SHAPES = 50;

  function notify(message, error) {
    var fn = error ? window.ATAKShowError : window.ATAKShowNotification;
    if (typeof fn === 'function') fn(message);
  }

  function currentMapId() {
    return window.ATAKSocket && typeof window.ATAKSocket.getMapId === 'function'
      ? window.ATAKSocket.getMapId() : 1;
  }

  function safeUnits() {
    var rows = window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function' ? window.ATAKUnits.getUnits() : [];
    return rows.map(function (unit) {
      return {
        id: unit.id,
        call_sign: unit.call_sign || unit.callsign || '',
        role: unit.role || '',
        status: unit.status || '',
        pos_x: unit.pos_x,
        pos_y: unit.pos_y,
        altitude: unit.altitude != null ? unit.altitude : unit.pos_z,
        group_name: unit.group_name || unit.group || '',
        captured_at: unit.updated_at || unit.last_seen_at || null
      };
    });
  }

  function safeShapes() {
    var rows = window.ATAKMapShapes && typeof window.ATAKMapShapes.getShapes === 'function' ? window.ATAKMapShapes.getShapes() : [];
    return rows.map(function (shape) {
      return {
        type: shape.type,
        geometry: shape.geometry,
        style: shape.style || {},
        meta: shape.meta || {},
        label: shape.label || shape.name || ''
      };
    });
  }

  function missionPackage() {
    return {
      format: FORMAT,
      version: VERSION,
      exported_at: new Date().toISOString(),
      tenant_id: Number(window.ATAK_TENANT_ID || 0),
      map_id: currentMapId(),
      // Snapshot d'observation uniquement : les unités seront ignorées à l'import.
      units: safeUnits(),
      shapes: safeShapes()
    };
  }

  function downloadPackage() {
    var payload = missionPackage();
    var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'athena-mission-' + payload.map_id + '-' + new Date().toISOString().replace(/[:.]/g, '-') + '.json';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(link.href); }, 0);
    notify('Paquet mission exporté : unités en lecture seule et tracés partageables.');
  }

  function validatePackage(payload) {
    if (!payload || payload.format !== FORMAT || Number(payload.version) !== VERSION) throw new Error('Format de paquet non reconnu.');
    if (!Array.isArray(payload.shapes)) throw new Error('Liste de tracés absente.');
    if (payload.shapes.length > MAX_IMPORT_SHAPES) throw new Error('Le paquet dépasse la limite de ' + MAX_IMPORT_SHAPES + ' tracés.');
    payload.shapes.forEach(function (shape) {
      if (!shape || typeof shape.type !== 'string' || !shape.geometry || typeof shape.geometry !== 'object') {
        throw new Error('Un tracé du paquet est invalide.');
      }
    });
    return payload;
  }

  function importPackage(file) {
    if (!file || file.size > 2 * 1024 * 1024) { notify('Paquet refusé : taille maximale 2 Mio.', true); return; }
    file.text().then(function (raw) {
      var payload = validatePackage(JSON.parse(raw));
      if (!window.confirm('Importer ' + payload.shapes.length + ' tracé(s) sur la carte active ? Les unités du paquet restent en lecture seule.')) return null;
      if (!window.ATAKMapShapes || typeof window.ATAKMapShapes.createShape !== 'function') throw new Error('Module de tracés indisponible.');
      var chain = Promise.resolve();
      var imported = 0;
      payload.shapes.forEach(function (shape) {
        chain = chain.then(function () {
          return window.ATAKMapShapes.createShape({
            type: shape.type,
            geometry: shape.geometry,
            style: shape.style || {},
            meta: Object.assign({}, shape.meta || {}, { imported_from: payload.exported_at || null }),
            label: shape.label || ''
          }).then(function (row) { if (row) imported += 1; });
        }).then(function () { return new Promise(function (resolve) { window.setTimeout(resolve, 150); }); });
      });
      return chain.then(function () { notify(imported + ' tracé(s) importé(s), après validation serveur.'); });
    }).catch(function (error) { notify(error && error.message ? error.message : 'Import mission impossible.', true); });
  }

  function bind() {
    var input = document.getElementById('overwatch-mission-import');
    var exportButton = document.querySelector('[data-overwatch-export]');
    var importButton = document.querySelector('[data-overwatch-import]');
    var printButton = document.querySelector('[data-overwatch-print]');
    if (exportButton) exportButton.addEventListener('click', downloadPackage);
    if (importButton && input) importButton.addEventListener('click', function () { input.click(); });
    if (input) input.addEventListener('change', function () { importPackage(input.files && input.files[0]); input.value = ''; });
    if (printButton) printButton.addEventListener('click', function () { window.print(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
}());
