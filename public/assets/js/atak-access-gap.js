/* COMSPEC ATAK — Écarts d’accès (liaison OK, vues fermées par le profil) */
window.ATAKAccessGap = (function () {
  'use strict';

  var STORAGE_PREFIX = 'atak_access_gap_dismiss_v1_';
  var POLL_MS = 4000;
  var shown = false;
  var busy = false;
  var pollTimer = null;

  function payload() {
    var p = window.ATAK_ACCESS_GAP;
    return p && typeof p === 'object' ? p : { offer: false, pending: false, gaps: [] };
  }

  function isEligiblePage() {
    if (window.ATAK_POPOUT || window.ATAK_DEVICE_EMBED) return false;
    var caps = window.ATAK_CAPS || {};
    var user = window.ATAK_USER || {};
    if (caps.phoneSession) return false;
    if (!caps.loggedIn || !user.id) return false;
    var data = payload();
    return !!(data.offer && data.gaps && data.gaps.length);
  }

  function gapKey() {
    var user = window.ATAK_USER || {};
    var tid = user.tenantId != null ? String(user.tenantId) : '0';
    var uid = user.id != null ? String(user.id) : 'anon';
    var ids = (payload().gaps || []).map(function (g) { return String(g.id || ''); }).join(',');
    return STORAGE_PREFIX + tid + '_' + uid + '_' + ids;
  }

  function wasDismissed() {
    try {
      var raw = localStorage.getItem(gapKey());
      if (!raw) return false;
      var until = parseInt(raw, 10);
      if (!until) return true;
      return Date.now() < until;
    } catch (e) {
      return false;
    }
  }

  function markDismissed() {
    try {
      localStorage.setItem(gapKey(), String(Date.now() + 7 * 24 * 60 * 60 * 1000));
    } catch (e) {}
  }

  function findSelfUnit() {
    if (window.ATAKMapTools && typeof window.ATAKMapTools.findSelfUnit === 'function') {
      return window.ATAKMapTools.findSelfUnit();
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

  function isLiveInGame() {
    var unit = findSelfUnit();
    return !!(unit && isLiveStatus(resolveStatus(unit)));
  }

  function hubBusy() {
    var overlay = document.getElementById('atak-session-profile-overlay');
    if (overlay && !overlay.hidden) return true;
    var offline = document.getElementById('atak-arma-offline-modal');
    if (offline && !offline.hidden) return true;
    return false;
  }

  function fillList() {
    var list = document.getElementById('atak-access-gap-list');
    if (!list) return;
    list.innerHTML = '';
    (payload().gaps || []).forEach(function (gap) {
      var li = document.createElement('li');
      var strong = document.createElement('strong');
      strong.textContent = gap.label || 'Vue indisponible';
      li.appendChild(strong);
      if (gap.hint) {
        var hint = document.createElement('span');
        hint.textContent = gap.hint;
        li.appendChild(hint);
      }
      list.appendChild(li);
    });
  }

  function setStatus(text, tone) {
    var el = document.getElementById('atak-access-gap-status');
    if (!el) return;
    el.textContent = text || '';
    el.className = 'atak-access-gap-status' + (tone ? ' atak-access-gap-status--' + tone : '');
  }

  function setPendingCopy() {
    var follow = document.getElementById('atak-access-gap-follow');
    var btn = document.getElementById('atak-access-gap-request');
    if (follow) {
      follow.textContent = 'Une demande est déjà en cours d’examen. L’encadrement la traite depuis le bureau effectifs.';
    }
    if (btn) {
      btn.hidden = true;
    }
  }

  function show() {
    var el = document.getElementById('atak-access-gap-modal');
    if (!el || shown) return;
    fillList();
    if (payload().pending) {
      setPendingCopy();
    }
    setStatus('');
    el.hidden = false;
    shown = true;
    var requestBtn = document.getElementById('atak-access-gap-request');
    if (requestBtn && !requestBtn.hidden) {
      requestBtn.focus();
    }
  }

  function hide() {
    var el = document.getElementById('atak-access-gap-modal');
    if (el) el.hidden = true;
  }

  function dismiss() {
    markDismissed();
    hide();
  }

  function requestUrl() {
    var url = String(payload().requestUrl || '').trim();
    if (url) return url;
    var base = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    return base + '/atak/demande-acces';
  }

  function sendRequest() {
    if (busy) return;
    var csrf = String(window.ATAK_CSRF_TOKEN || '').trim();
    var btn = document.getElementById('atak-access-gap-request');
    if (!csrf) {
      setStatus('Session expirée : rechargez la page.', 'warn');
      return;
    }
    busy = true;
    if (btn) btn.disabled = true;
    setStatus('Envoi de la demande…', 'muted');
    fetch(requestUrl(), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      credentials: 'include',
      body: JSON.stringify({
        _csrf_token: csrf,
        live: true
      })
    })
      .then(function (r) {
        return r.json().then(function (d) {
          return { ok: r.ok, data: d };
        });
      })
      .then(function (res) {
        var msg = (res.data && res.data.message) ? String(res.data.message) : '';
        if (res.ok) {
          setStatus(msg || 'Demande transmise à l’encadrement.', 'ok');
          markDismissed();
          if (window.ATAKShowNotification) {
            window.ATAKShowNotification(msg || 'Demande transmise à l’encadrement.');
          }
          window.setTimeout(hide, 1600);
          return;
        }
        setStatus(msg || 'La demande n’a pas pu être envoyée.', 'warn');
      })
      .catch(function () {
        setStatus('Impossible d’envoyer la demande pour le moment.', 'warn');
      })
      .then(function () {
        busy = false;
        if (btn) btn.disabled = false;
      });
  }

  function maybeShow() {
    if (shown || !isEligiblePage() || wasDismissed() || hubBusy()) return;
    if (!isLiveInGame()) return;
    show();
  }

  function bind() {
    var el = document.getElementById('atak-access-gap-modal');
    if (!el) return;
    el.querySelectorAll('[data-access-gap-dismiss]').forEach(function (node) {
      node.addEventListener('click', dismiss);
    });
    var dismissBtn = document.getElementById('atak-access-gap-dismiss');
    var requestBtn = document.getElementById('atak-access-gap-request');
    if (dismissBtn) dismissBtn.addEventListener('click', dismiss);
    if (requestBtn) requestBtn.addEventListener('click', sendRequest);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && el && !el.hidden) {
        e.preventDefault();
        dismiss();
      }
    });
  }

  function start() {
    if (!isEligiblePage()) return;
    bind();
    var kick = function () {
      maybeShow();
      if (shown || pollTimer) return;
      pollTimer = window.setInterval(function () {
        maybeShow();
        if (shown && pollTimer) {
          window.clearInterval(pollTimer);
          pollTimer = null;
        }
      }, POLL_MS);
    };
    if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.onReady === 'function') {
      window.ATAKSessionProfile.onReady(function () {
        window.setTimeout(kick, 1200);
      });
    } else {
      window.setTimeout(kick, 1800);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  return {
    maybeShow: maybeShow
  };
})();
