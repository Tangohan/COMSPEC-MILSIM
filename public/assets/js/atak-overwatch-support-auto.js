/**
 * Overwatch Beta — fil automatique Support (crash, versions, écarts de pack).
 */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__OW_SUPPORT_AUTO__) return;
  window.__OW_SUPPORT_AUTO__ = true;

  var POLL_MS = 12000;
  var COLLAPSE_AT = 2;
  var seenKeys = {};
  var lastHtml = '';
  var liveWatch = {};
  var localAlerts = [];
  /** null = auto (ouvert si peu d’alertes) ; true/false = choix opérateur */
  var foldOpen = null;

  function apiBase() {
    return String(window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function mapId() {
    if (window.OverwatchBeta && window.OverwatchBeta.mapId != null) return window.OverwatchBeta.mapId;
    return window.ATAK_DEFAULT_MAP_ID || 1;
  }

  function esc(value) {
    var node = document.createElement('span');
    node.textContent = String(value == null ? '' : value);
    return node.innerHTML;
  }

  function alertKey(a) {
    return [a.code || '', a.call_sign || '', a.terminal_uid || '', a.title || ''].join('|');
  }

  function severityClass(sev) {
    if (sev === 'critical' || sev === 'error') return 'is-critical';
    if (sev === 'info') return 'is-info';
    return 'is-warn';
  }

  function kindLabel(a) {
    var code = String(a.code || '');
    if (code.indexOf('crash') === 0) return 'Crash';
    if (code.indexOf('version') === 0) return 'Version';
    if (code === 'offline') return 'Liaison';
    if (code === 'jammed') return 'Liaison';
    if (code === 'damaged' || code.indexOf('terminal') === 0 || code === 'bad_certificate') return 'Appareil';
    return 'Alerte';
  }

  function mergeAlerts(serverAlerts) {
    var map = {};
    (serverAlerts || []).forEach(function (a) {
      if (!a) return;
      map[alertKey(a)] = a;
    });
    localAlerts.forEach(function (a) {
      var key = alertKey(a);
      if (!map[key]) map[key] = a;
    });
    return Object.keys(map).map(function (k) { return map[k]; });
  }

  function isFoldOpen(count) {
    if (foldOpen === true) return true;
    if (foldOpen === false) return false;
    return count <= COLLAPSE_AT;
  }

  function bindFold(host) {
    var details = host.querySelector('details.ow-support-auto-fold');
    if (!details || details.dataset.bound === '1') return;
    details.dataset.bound = '1';
    details.addEventListener('toggle', function () {
      foldOpen = !!details.open;
    });
  }

  function render(alerts) {
    var host = document.getElementById('ow-support-auto');
    if (!host) return;
    var rows = Array.isArray(alerts) ? alerts.slice() : [];
    rows.sort(function (a, b) {
      var sa = a.severity === 'critical' || a.severity === 'error' ? 0 : 1;
      var sb = b.severity === 'critical' || b.severity === 'error' ? 0 : 1;
      return sa - sb;
    });
    if (!rows.length) {
      var empty = '<p class="ow-help">Surveillance automatique active. Aucun incident détecté pour le moment.</p>';
      if (lastHtml !== empty) {
        host.innerHTML = empty;
        lastHtml = empty;
      }
      return;
    }
    var critical = rows.filter(function (a) {
      return a.severity === 'critical' || a.severity === 'error';
    }).length;
    var open = isFoldOpen(rows.length);
    var summaryBits = rows.length + ' alerte' + (rows.length > 1 ? 's' : '');
    if (critical > 0) {
      summaryBits += ' · ' + critical + ' critique' + (critical > 1 ? 's' : '');
    }
    var list = rows.map(function (a) {
      return '<article class="ow-support-auto-row ' + severityClass(a.severity) + '">' +
        '<header><span class="ow-support-auto-kind">' + esc(kindLabel(a)) + '</span>' +
        '<strong>' + esc(a.title || 'Alerte') + '</strong></header>' +
        '<p>' + esc(a.message || '') + '</p></article>';
    }).join('');
    var html = '<details class="ow-support-auto-fold"' + (open ? ' open' : '') + '>' +
      '<summary class="ow-support-auto-summary">' +
      '<span class="ow-support-auto-summary-title">Surveillance automatique</span>' +
      '<span class="ow-support-auto-summary-count">' + esc(summaryBits) + '</span>' +
      '</summary>' +
      '<div class="ow-support-auto-list">' + list + '</div>' +
      '</details>';
    if (html !== lastHtml) {
      host.innerHTML = html;
      lastHtml = html;
      bindFold(host);
    }
    var fresh = 0;
    rows.forEach(function (a) {
      var key = alertKey(a);
      if (!seenKeys[key]) {
        seenKeys[key] = true;
        fresh += 1;
      }
    });
    if (fresh > 0) {
      try {
        window.dispatchEvent(new CustomEvent('overwatch:support-auto', { detail: { count: fresh, alerts: rows } }));
      } catch (e) {}
    }
  }

  function pushLocal(alert) {
    if (!alert || !alert.code) return;
    var key = alertKey(alert);
    var exists = localAlerts.some(function (a) { return alertKey(a) === key; });
    if (exists) return;
    localAlerts.push(alert);
    if (localAlerts.length > 40) localAlerts = localAlerts.slice(-40);
  }

  function watchUnits(units) {
    var list = Array.isArray(units) ? units : [];
    var now = Date.now();
    list.forEach(function (unit) {
      if (!unit) return;
      var id = String(unit.id || unit.uuid || unit.call_sign || unit.callsign || '');
      if (!id) return;
      var extra = unit.extra;
      if (typeof extra === 'string') {
        try { extra = JSON.parse(extra); } catch (e) { extra = {}; }
      }
      if (!extra || typeof extra !== 'object') extra = {};
      var link = String(unit.link_state || extra.link_state || unit.status || '').toLowerCase();
      var offline = link === 'offline' || link === 'disconnected' || link === 'lost' || String(unit.status || '').toLowerCase() === 'offline';
      var prev = liveWatch[id];
      var call = String(unit.call_sign || unit.callsign || 'Opérateur');
      if (prev && prev.live && offline && (now - prev.ts) < 90000) {
        pushLocal({
          code: 'crash_suspect',
          severity: 'warn',
          title: 'Coupure brutale suspectée',
          message: call + ' — était en liaison il y a moins de 90 secondes, puis plus de contact.',
          call_sign: call,
          terminal_uid: String(extra.terminal_uid || ''),
          at: new Date().toISOString(),
          kind: 'crash'
        });
      }
      if (extra.device_crashed === true || extra.device_crashed === 1 || extra.device_crashed === '1') {
        pushLocal({
          code: 'crash',
          severity: 'critical',
          title: 'Crash / gel terminal',
          message: call + ' — le téléphone est gelé.',
          call_sign: call,
          terminal_uid: String(extra.terminal_uid || ''),
          at: new Date().toISOString(),
          kind: 'crash'
        });
      }
      liveWatch[id] = { live: !offline, ts: now };
    });
  }

  function poll() {
    var url = apiBase() + '/api/atak/device-alerts?mapId=' + encodeURIComponent(mapId());
    fetch(url, { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        var server = data && Array.isArray(data.alerts) ? data.alerts : [];
        render(mergeAlerts(server));
      })
      .catch(function () {
        render(mergeAlerts([]));
      });
  }

  function boot() {
    if (!document.getElementById('ow-support-auto')) return;
    poll();
    window.setInterval(poll, POLL_MS);
    window.addEventListener('overwatch:units-updated', function (ev) {
      watchUnits(ev && ev.detail && ev.detail.units);
    });
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest && ev.target.closest('[data-chat-tab="support"]');
      if (btn) poll();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.OverwatchSupportAuto = {
    poll: poll,
    render: render,
    watchUnits: watchUnits
  };
})();
