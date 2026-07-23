/* COMSPEC ATAK — Détection déconnexion Arma (compte web encore ouvert) */
window.ATAKArmaOffline = (function () {
  'use strict';

  var sawLive = false;
  var promptOpen = false;
  var dismissedUntilLive = false;
  var lastSelfId = null;
  var lastCallsign = '';
  var busy = false;

  function isEligible() {
    var caps = window.ATAK_CAPS || {};
    var user = window.ATAK_USER || {};
    if (!caps.loggedIn || !user.id) return false;
    var cs = String(user.callsign || user.armaCallsign || '').trim();
    var steam = String(user.steamId || '').trim();
    return !!(cs || steam);
  }

  function findSelfUnit() {
    if (window.ATAKMapTools && typeof window.ATAKMapTools.findSelfUnit === 'function') {
      return window.ATAKMapTools.findSelfUnit();
    }
    var user = window.ATAK_USER || {};
    var callsigns = [];
    [user.callsign, user.armaCallsign].forEach(function (c) {
      var k = String(c || '').toUpperCase().trim();
      if (k && callsigns.indexOf(k) < 0) callsigns.push(k);
    });
    var steam = String(user.steamId || '').trim();
    var units = (window.ATAKUnits && window.ATAKUnits.getUnits) ? window.ATAKUnits.getUnits() : [];
    var i;
    for (i = 0; i < units.length; i++) {
      var u = units[i];
      var cs = String(u.call_sign || '').toUpperCase().trim();
      if (cs && callsigns.indexOf(cs) >= 0) return u;
    }
    if (steam) {
      for (i = 0; i < units.length; i++) {
        var u2 = units[i];
        var ex = u2.extra;
        if (typeof ex === 'string') {
          try { ex = JSON.parse(ex || '{}'); } catch (e) { ex = {}; }
        }
        var sid = String(u2.steam_id || u2.steamId || (ex && (ex.steam_uid || ex.steam_id)) || '').trim();
        if (sid && sid === steam) return u2;
      }
    }
    return null;
  }

  function isLiveStatus(status) {
    return status === 'linked' || status === 'delayed';
  }

  function resolveStatus(u) {
    if (!u) return 'offline';
    if (window.ATAKUnits && typeof window.ATAKUnits.resolveLiveStatus === 'function') {
      return window.ATAKUnits.resolveLiveStatus(u);
    }
    return String(u.status || 'offline');
  }

  function ownCallsignsUpper() {
    var out = [];
    var user = window.ATAK_USER || {};
    [user.callsign, user.armaCallsign].forEach(function (v) {
      var s = String(v || '').trim().toUpperCase();
      if (s && out.indexOf(s) < 0) out.push(s);
    });
    return out;
  }

  function eventMatchesSelf(ev) {
    if (!ev || ev.type !== 'disconnect') return false;
    var mine = ownCallsignsUpper();
    if (!mine.length) return false;
    var actor = String(ev.actor || '').toUpperCase().trim();
    if (actor && mine.indexOf(actor) >= 0) return true;
    var meta = ev.meta && typeof ev.meta === 'object' ? ev.meta : {};
    var cs = String(meta.call_sign || meta.callsign || '').toUpperCase().trim();
    return !!(cs && mine.indexOf(cs) >= 0);
  }

  function ensureModal() {
    var el = document.getElementById('atak-arma-offline-modal');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'atak-arma-offline-modal';
    el.className = 'atak-session-profile-overlay atak-arma-offline-modal';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'atak-arma-offline-title');
    el.hidden = true;
    el.innerHTML =
      '<div class="atak-session-profile-backdrop" data-arma-offline-dismiss="1"></div>' +
      '<div class="atak-session-profile-card atak-arma-offline-card">' +
        '<h2 id="atak-arma-offline-title" class="atak-session-profile-title">Liaison en jeu interrompue</h2>' +
        '<p class="atak-session-profile-lead" id="atak-arma-offline-lead">' +
          'Votre ATAK en jeu ne répond plus. Souhaitez-vous retirer votre contact de la carte et réinitialiser le profil de cette session\u00a0?' +
        '</p>' +
        '<div class="atak-session-profile-actions atak-arma-offline-actions">' +
          '<button type="button" class="atak-session-profile-btn atak-session-profile-btn--ghost" id="atak-arma-offline-keep">Rester sur la carte</button>' +
          '<button type="button" class="atak-session-profile-btn atak-session-profile-btn--primary atak-arma-offline-btn-clear" id="atak-arma-offline-clear">Nettoyer ma session</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(el);
    el.querySelectorAll('[data-arma-offline-dismiss]').forEach(function (node) {
      node.addEventListener('click', dismiss);
    });
    var keep = document.getElementById('atak-arma-offline-keep');
    var clearBtn = document.getElementById('atak-arma-offline-clear');
    if (keep) keep.addEventListener('click', dismiss);
    if (clearBtn) clearBtn.addEventListener('click', clearSession);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && promptOpen) {
        e.preventDefault();
        dismiss();
      }
    });
    return el;
  }

  function updateLead(callsign) {
    var lead = document.getElementById('atak-arma-offline-lead');
    if (!lead) return;
    var cs = String(callsign || lastCallsign || '').trim();
    if (cs) {
      lead.textContent = 'Votre ATAK en jeu (' + cs + ') ne répond plus. Souhaitez-vous retirer votre contact de la carte et réinitialiser le profil de cette session\u00a0?';
    } else {
      lead.textContent = 'Votre ATAK en jeu ne répond plus. Souhaitez-vous retirer votre contact de la carte et réinitialiser le profil de cette session\u00a0?';
    }
  }

  function showPrompt(callsign) {
    if (promptOpen || dismissedUntilLive || !isEligible()) return;
    var profileOverlay = document.getElementById('atak-session-profile-overlay');
    if (profileOverlay && !profileOverlay.hidden) return;
    ensureModal();
    updateLead(callsign);
    var el = document.getElementById('atak-arma-offline-modal');
    if (!el) return;
    el.hidden = false;
    el.setAttribute('aria-hidden', 'false');
    promptOpen = true;
    var focusBtn = document.getElementById('atak-arma-offline-clear');
    if (focusBtn) {
      try { focusBtn.focus(); } catch (e) {}
    }
  }

  function hidePrompt() {
    var el = document.getElementById('atak-arma-offline-modal');
    if (el) {
      el.hidden = true;
      el.setAttribute('aria-hidden', 'true');
    }
    promptOpen = false;
  }

  function dismiss() {
    dismissedUntilLive = true;
    hidePrompt();
  }

  function getApiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || '');
  }

  function deleteOwnUnit(unitId) {
    var base = getApiBase();
    if (!base || !unitId) return Promise.resolve(false);
    return fetch(base + '/api/units/' + encodeURIComponent(unitId), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      return r.status === 204 || r.ok;
    });
  }

  function clearSession() {
    if (busy) return;
    busy = true;
    var unit = findSelfUnit();
    var unitId = unit && unit.id ? unit.id : lastSelfId;
    var caps = window.ATAK_CAPS || {};
    var canDelete = !!caps.canDeleteOwnUnit || !!caps.canDeleteUnitStaff;

    var chain = Promise.resolve(false);
    if (canDelete && unitId) {
      chain = deleteOwnUnit(unitId);
    }

    chain.then(function (deleted) {
      if (deleted && window.ATAKUnits) {
        if (typeof window.ATAKUnits.removeUnitLocal === 'function') {
          window.ATAKUnits.removeUnitLocal(unitId);
        } else if (typeof window.ATAKUnits.fetchUnits === 'function') {
          window.ATAKUnits.fetchUnits();
        }
      }
      if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.clear === 'function') {
        window.ATAKSessionProfile.clear();
      }
      dismissedUntilLive = true;
      sawLive = false;
      lastSelfId = null;
      hidePrompt();
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification(
          deleted
            ? 'Session nettoyée — contact retiré de la carte.'
            : 'Profil de session réinitialisé.'
        );
      }
      if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.showEditor === 'function') {
        window.ATAKSessionProfile.showEditor();
      }
    }).catch(function () {
      if (window.ATAKShowError) {
        window.ATAKShowError('Impossible de retirer votre contact. Réessayez ou retirez-le depuis les effectifs.');
      }
    }).finally(function () {
      busy = false;
    });
  }

  function evaluateFromUnits() {
    if (!isEligible()) return;
    var unit = findSelfUnit();
    if (!unit) {
      if (sawLive && !dismissedUntilLive) {
        showPrompt(lastCallsign);
      }
      return;
    }
    var status = resolveStatus(unit);
    var cs = String(unit.call_sign || '').trim();
    if (cs) lastCallsign = cs;
    if (unit.id) lastSelfId = unit.id;

    if (isLiveStatus(status)) {
      sawLive = true;
      dismissedUntilLive = false;
      if (promptOpen) hidePrompt();
      return;
    }

    // offline / absent de liaison
    if (sawLive && !dismissedUntilLive) {
      showPrompt(cs || lastCallsign);
    }
  }

  function onUnitsUpdated() {
    evaluateFromUnits();
  }

  function onActivityFresh(ev) {
    if (!isEligible() || !ev || !ev.detail || !Array.isArray(ev.detail.events)) return;
    // Ignorer le chargement initial du journal (évite une fausse alerte sur une ancienne déco).
    if (!ev.detail.incremental) return;
    var list = ev.detail.events;
    for (var i = 0; i < list.length; i++) {
      if (eventMatchesSelf(list[i])) {
        // Déconnexion explicite du jeu : considérer qu’on avait bien une liaison.
        sawLive = true;
        var meta = list[i].meta && typeof list[i].meta === 'object' ? list[i].meta : {};
        var cs = String(meta.call_sign || meta.callsign || list[i].actor || lastCallsign || '').trim();
        if (cs) lastCallsign = cs;
        showPrompt(cs);
        break;
      }
    }
  }

  function init() {
    if (!isEligible()) return;
    ensureModal();
    window.addEventListener('atak:units-updated', onUnitsUpdated);
    window.addEventListener('atak:activity-fresh', onActivityFresh);
    // Premier état après chargement des unités
    setTimeout(evaluateFromUnits, 1200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    evaluate: evaluateFromUnits,
    show: showPrompt,
    dismiss: dismiss
  };
})();
