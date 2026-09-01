/**
 * ATAK — liste des terminaux (identifiants, liaison, santé, adresse réseau).
 */
(function () {
  'use strict';

  var timer = null;
  var lastHtml = '';

  function qs(id) {
    return document.getElementById(id);
  }

  function getApiBase() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getApiBase === 'function') {
      return window.ATAKSocket.getApiBase() || '';
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

  function parseExtra(u) {
    if (!u) return {};
    try {
      if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
      if (u.extra && typeof u.extra === 'object') return u.extra;
    } catch (e) {}
    return {};
  }

  function statusLabel(st) {
    var s = String(st || '').toLowerCase();
    if (s === 'linked' || s === 'active') return { label: 'En liaison', tone: 'ok' };
    if (s === 'pending') return { label: 'En attente', tone: 'warn' };
    if (s === 'offline' || s === 'inactive') return { label: 'Hors liaison', tone: 'danger' };
    if (s === 'compromised' || s === 'lost' || s === 'revoked') return { label: 'Compromis', tone: 'danger' };
    if (!s) return { label: 'Inconnu', tone: 'warn' };
    return { label: String(st), tone: '' };
  }

  function healthLabel(h) {
    var x = String(h || '').toLowerCase();
    if (x === 'ok' || x === 'stable' || x === 'healthy') return { label: 'Opérationnel', tone: 'ok' };
    if (x === 'wounded' || x === 'injured') return { label: 'Blessé', tone: 'warn' };
    if (x === 'unconscious') return { label: 'Inconscient', tone: 'danger' };
    if (x === 'cardiac_arrest' || x === 'cardiac-arrest' || x === 'dead' || x === 'kia' || x === 'critical') {
      return { label: 'État critique', tone: 'danger' };
    }
    if (!x) return { label: 'Non remonté', tone: '' };
    return { label: String(h), tone: '' };
  }

  function compromiseLabel(st) {
    var s = String(st || '').toLowerCase();
    if (s === 'none' || s === '') return { label: 'RAS', tone: 'ok' };
    if (s === 'suspected' || s === 'suspect') return { label: 'À vérifier', tone: 'warn' };
    if (s === 'confirmed' || s === 'compromised') return { label: 'Compromis', tone: 'danger' };
    return { label: String(st), tone: 'warn' };
  }

  function parseUtcMs(iso) {
    if (!iso) return NaN;
    var s = String(iso).trim();
    if (!s) return NaN;
    if (/[zZ]$/.test(s) || /[+-]\d{2}:?\d{2}$/.test(s)) {
      return new Date(s).getTime();
    }
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
      s = s.replace(' ', 'T');
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(s)) s += ':00';
      return new Date(s + 'Z').getTime();
    }
    return new Date(s).getTime();
  }

  function formatSeen(iso, assumeUtc) {
    if (!iso) return 'Jamais';
    var t = assumeUtc ? parseUtcMs(iso) : new Date(iso).getTime();
    if (isNaN(t)) return String(iso);
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 20) return 'À l’instant';
    if (sec < 60) return 'Il y a ' + sec + ' s';
    if (sec < 3600) return 'Il y a ' + Math.floor(sec / 60) + ' min';
    if (sec < 86400) return 'Il y a ' + Math.floor(sec / 3600) + ' h';
    try {
      return new Date(t).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return String(iso);
    }
  }

  function unitsByCallsign() {
    var map = {};
    var list = [];
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      list = window.ATAKUnits.getUnits() || [];
    }
    list.forEach(function (u) {
      var key = String(u.call_sign || u.callsign || '').toUpperCase().trim();
      if (key) map[key] = u;
    });
    return { map: map, list: list };
  }

  function row(k, v, tone) {
    var cls = tone ? ' atak-terminal-card__v--' + tone : '';
    return '<div class="atak-terminal-card__k">' + escapeHtml(k) + '</div>' +
      '<div class="atak-terminal-card__v' + cls + '">' + escapeHtml(v) + '</div>';
  }

  function firstText() {
    var i;
    for (i = 0; i < arguments.length; i++) {
      var s = String(arguments[i] == null ? '' : arguments[i]).trim();
      if (s) return s;
    }
    return '';
  }

  function packVersionFromPlatform(label) {
    var m = String(label || '').match(/COMSPEC\s+([\d]+(?:\.[\d]+)*)/i);
    return m ? m[1] : '';
  }

  function typeLabel(t) {
    var ty = String(t && t.terminal_type || '').toLowerCase();
    if (ty === 'phone') return 'Téléphone';
    var plat = String(t && t.platform_label || '').trim();
    var cleaned = plat.replace(/\s*[·•|\-]\s*COMSPEC(?:\s+[\d.]+)?/i, '').trim();
    if (cleaned) return cleaned;
    if (ty === 'tablet') return 'Arma 3';
    if (ty === 'radio') return 'Radio';
    if (ty === 'vehicle') return 'Véhicule';
    if (ty === 'desktop') return 'Poste';
    if (ty === 'web') return 'Session web';
    return ty ? String(t.terminal_type) : 'Poste';
  }

  function versionRows(t, extra) {
    extra = extra || {};
    var overwatch = firstText(
      t.mod_version,
      extra.mod_version,
      extra.overwatch_version,
      packVersionFromPlatform(t.platform_label)
    );
    var liaison = firstText(
      t.extension_version,
      extra.extension_version,
      extra.dll_version
    );
    var html = '';
    if (overwatch) html += row('Overwatch', overwatch);
    if (liaison) html += row('Liaison Athena', liaison);
    return html;
  }

  function cardHtml(t, unit) {
    var extra = parseExtra(unit);
    var call = t.operator_callsign || t.callsign || (unit && (unit.call_sign || unit.callsign)) || t.terminal_label || 'Terminal';
    var st = statusLabel((unit && unit.status) || t.status);
    var health = healthLabel(extra.health || (unit && unit.health));
    var comp = compromiseLabel(t.compromise_state);
    var idFollow = extra.bft_id || extra.military_id || t.operator_military_id || t.terminal_uid || '';
    var ip = extra.client_ip || extra.ip || extra.public_ip || extra.network || '';
    var type = typeLabel(t);
    var seenRaw = t.last_seen_at || '';
    var seenLabel = seenRaw ? formatSeen(seenRaw, true) : formatSeen(unit && unit.updated_at, false);
    return '<article class="atak-terminal-card">' +
      '<div class="atak-terminal-card__head">' +
      '<span class="atak-terminal-card__call">' + escapeHtml(call) + '</span>' +
      '<span class="atak-terminal-card__state atak-terminal-card__state--' + (st.tone || 'warn') + '">' + escapeHtml(st.label) + '</span>' +
      '</div>' +
      '<div class="atak-terminal-card__rows">' +
      row('Identifiant', idFollow || 'Non attribué') +
      row('Santé', health.label, health.tone) +
      row('Intégrité', comp.label, comp.tone) +
      row('Type', type) +
      versionRows(t, extra) +
      (t.terminal_label && t.terminal_label !== call ? row('Libellé', t.terminal_label) : '') +
      row('Dernière activité', seenLabel) +
      row('Adresse réseau', ip || 'Non remontée') +
      '</div></article>';
  }

  function render(terminals) {
    var wrap = qs('atak-terminals-list');
    var empty = qs('atak-terminals-empty');
    var badge = qs('atak-terminals-tab-badge');
    if (!wrap) return;
    var units = unitsByCallsign();
    var list = Array.isArray(terminals) ? terminals.slice() : [];
    if (!list.length) {
      wrap.innerHTML = '';
      if (empty) empty.hidden = false;
      if (badge) {
        badge.hidden = true;
        badge.textContent = '';
      }
      lastHtml = '';
      return;
    }
    if (empty) empty.hidden = true;
    var html = list.map(function (t) {
      var k = String(t.operator_callsign || t.callsign || '').toUpperCase().trim();
      return cardHtml(t, k ? units.map[k] : null);
    }).join('');
    if (html !== lastHtml) {
      wrap.innerHTML = html;
      lastHtml = html;
    }
    if (badge) {
      var n = list.length;
      badge.hidden = n < 1;
      badge.textContent = String(n);
    }
  }

  function load() {
    var base = getApiBase();
    fetch((base || '') + '/api/atak/terminals', { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : { terminals: [] }; })
      .then(function (body) {
        render((body && body.terminals) || []);
      })
      .catch(function () {
        render([]);
      });
  }

  function ensureTimer() {
    if (timer) return;
    timer = window.setInterval(load, 12000);
  }

  function onTab(tab) {
    if (tab !== 'terminaux') return;
    load();
    ensureTimer();
  }

  document.addEventListener('atak:tab-activated', function (ev) {
    var tab = ev && ev.detail ? ev.detail.tab : '';
    onTab(tab);
  });
  document.addEventListener('atak:section-change', function (ev) {
    if (ev && ev.detail && ev.detail.section === 'forces') load();
  });
  document.addEventListener('DOMContentLoaded', function () {
    var active = document.querySelector('#atak-panel-left .atak-tab.active[data-tab="terminaux"]');
    if (active) load();
  });

  window.ATAKTerminals = { refresh: load };
})();
