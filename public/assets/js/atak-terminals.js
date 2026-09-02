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
    if (s === 'linked') return { label: 'En liaison', tone: 'ok' };
    if (s === 'delayed') return { label: 'Signal différé', tone: 'warn' };
    if (s === 'pending') return { label: 'En attente', tone: 'warn' };
    if (s === 'offline' || s === 'inactive' || s === 'lost') return { label: 'Hors liaison', tone: 'danger' };
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
    var byMid = {};
    var list = [];
    if (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function') {
      list = window.ATAKUnits.getUnits() || [];
    }
    list.forEach(function (u) {
      var key = String(u.call_sign || u.callsign || '').toUpperCase().trim();
      if (key) map[key] = u;
      var extra = parseExtra(u);
      var mid = String(u.military_id || u.bft_id || extra.bft_id || extra.military_id || '').toUpperCase().trim();
      if (mid) byMid[mid] = u;
    });
    return { map: map, byMid: byMid, list: list };
  }

  function findUnitForTerminal(t, units) {
    var k = String(t.operator_callsign || t.callsign || '').toUpperCase().trim();
    if (k && units.map[k]) return units.map[k];
    var mid = String(t.operator_military_id || '').toUpperCase().trim();
    if (mid && units.byMid[mid]) return units.byMid[mid];
    return null;
  }

  var TERMINAL_LIVE_MS = 120 * 1000;

  function terminalLiveStatus(t, unit) {
    if (unit && window.ATAKUnits && typeof window.ATAKUnits.resolveLiveStatus === 'function') {
      var us = String(window.ATAKUnits.resolveLiveStatus(unit) || '').toLowerCase();
      if (us === 'linked' || us === 'delayed' || us === 'offline') return us;
    } else if (unit) {
      var raw = String(unit.status || '').toLowerCase();
      if (raw === 'linked' || raw === 'delayed' || raw === 'offline') return raw;
    }
    var seen = t && t.last_seen_at ? parseUtcMs(t.last_seen_at) : NaN;
    if (isNaN(seen)) return 'offline';
    var age = Date.now() - seen;
    if (age < 0) age = 0;
    if (age > TERMINAL_LIVE_MS) return 'offline';
    if (age > TERMINAL_LIVE_MS * 0.6) return 'delayed';
    return 'linked';
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

  function canManageCertificates() {
    var caps = window.ATAK_CAPS || {};
    if (caps.phoneSession) return false;
    if (typeof caps.canManageCertificates === 'boolean') return !!caps.canManageCertificates;
    return !!caps.loggedIn;
  }

  function mapId() {
    if (window.OverwatchState && window.OverwatchState.currentMapId != null) {
      return window.OverwatchState.currentMapId;
    }
    return window.ATAK_DEFAULT_MAP_ID || 1;
  }

  function certStatus(t) {
    var s = String(t && t.certificate_status || '').toLowerCase();
    var expires = String(t && t.certificate_expires_at || '').trim();
    var expired = false;
    if (expires) {
      var ms = parseUtcMs(expires);
      expired = !isNaN(ms) && ms < Date.now();
    }
    if (s === 'revoked') return { label: 'Révoqué', tone: 'danger' };
    if (s === 'expired' || expired) return { label: 'Expiré', tone: 'danger' };
    if (s === 'active' || s === 'issued') return { label: 'Actif', tone: 'ok' };
    if (String(t && t.certificate_ref || '').trim()) return { label: 'Émis', tone: 'warn' };
    return { label: 'Aucun', tone: 'warn' };
  }

  function formatExpiry(iso) {
    if (!iso) return '';
    var t = parseUtcMs(iso);
    if (isNaN(t)) return String(iso);
    try {
      return new Date(t).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (e) {
      return String(iso);
    }
  }

  function certRows(t) {
    var st = certStatus(t);
    var ref = String(t && t.certificate_ref || '').trim();
    var html = row('Certificat', st.label, st.tone);
    if (ref) html += row('Référence', ref);
    var exp = formatExpiry(t && t.certificate_expires_at);
    if (exp && st.label !== 'Aucun') html += row('Échéance', exp, st.tone === 'danger' ? 'danger' : '');
    var auth = String(t && t.certificate_authority || '').trim();
    if (auth) html += row('Autorité', auth);
    var net = String(t && t.crypto_domain_label || '').trim();
    if (net) html += row('Réseau', net);
    return html;
  }

  function certActions(t, call) {
    if (!canManageCertificates()) return '';
    var id = Number(t && t.id || 0);
    if (!(id > 0)) return '';
    var has = String(t && t.certificate_ref || '').trim() !== '' && certStatus(t).label !== 'Aucun';
    var label = has ? 'Renouveler le certificat' : 'Émettre un certificat';
    return '<div class="atak-terminal-card__actions">' +
      '<button type="button" class="atak-ops-btn atak-ops-btn--sm" data-regen-cert="' + id + '" data-regen-call="' + escapeHtml(call) + '" data-regen-has="' + (has ? '1' : '0') + '">' + escapeHtml(label) + '</button>' +
      '</div>';
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
    var st = statusLabel(terminalLiveStatus(t, unit));
    var health = healthLabel(extra.health || (unit && unit.health));
    var comp = compromiseLabel(t.compromise_state);
    var idFollow = extra.bft_id || extra.military_id || t.operator_military_id || t.terminal_uid || '';
    var ip = extra.client_ip || extra.ip || extra.public_ip || extra.network || t.last_client_ip || '';
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
      certRows(t) +
      (t.terminal_label && t.terminal_label !== call ? row('Libellé', t.terminal_label) : '') +
      row('Dernière activité', seenLabel) +
      row('Adresse réseau', ip || 'Non remontée') +
      '</div>' +
      certActions(t, call) +
      '</article>';
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
      return cardHtml(t, findUnitForTerminal(t, units));
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

  function notify(ok, msg) {
    if (ok && window.ATAKShowNotification) window.ATAKShowNotification(msg);
    else if (!ok && window.ATAKShowError) window.ATAKShowError(msg);
  }

  function regenerate(btn) {
    var id = Number(btn.getAttribute('data-regen-cert') || 0);
    if (!(id > 0) || btn.disabled) return;
    var call = btn.getAttribute('data-regen-call') || 'cet appareil';
    var has = btn.getAttribute('data-regen-has') === '1';
    var question = has
      ? 'Renouveler le certificat de ' + call + ' ? L’ancien ne sera plus accepté.'
      : 'Émettre un certificat pour ' + call + ' ?';
    if (!window.confirm(question)) return;
    btn.disabled = true;
    var csrf = window.ATAK_CSRF_TOKEN || '';
    fetch((getApiBase() || '') + '/api/atak/terminals/' + id + '/certificate/regenerate', {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({ _csrf_token: csrf, map_id: mapId() })
    })
      .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body || {} }; }); })
      .then(function (res) {
        var msg = String((res.body && (res.body.message || res.body.error)) || '');
        if (res.ok && res.body && res.body.ok) {
          notify(true, msg || 'Certificat mis à jour.');
          load();
        } else {
          notify(false, msg || 'Impossible de renouveler le certificat.');
          btn.disabled = false;
        }
      })
      .catch(function () {
        notify(false, 'Liaison Athena indisponible.');
        btn.disabled = false;
      });
  }

  function bindList() {
    var wrap = qs('atak-terminals-list');
    if (!wrap || wrap.getAttribute('data-cert-bound') === '1') return;
    wrap.setAttribute('data-cert-bound', '1');
    wrap.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-regen-cert]') : null;
      if (!btn || !wrap.contains(btn)) return;
      regenerate(btn);
    });
  }

  function load() {
    bindList();
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
    bindList();
    var active = document.querySelector('#atak-panel-left .atak-tab.active[data-tab="terminaux"]');
    if (active) load();
  });

  window.ATAKTerminals = { refresh: load };
})();
