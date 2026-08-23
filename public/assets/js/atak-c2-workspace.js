/**
 * ATAK C2 — onglets de travail (suivi / émettre) et aside réglages.
 */
(function () {
  'use strict';

  var STORAGE_WORK = 'atak-c2-orders-work-v1';
  var currentWork = 'suivi';

  function qs(id) {
    return document.getElementById(id);
  }

  function canIssue() {
    if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.canIssueOrders === 'function') {
      return !!window.ATAKSessionProfile.canIssueOrders();
    }
    return !!window.ATAK_CAN_ISSUE_ORDERS;
  }

  function getApiBase() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getApiBase === 'function') {
      return window.ATAKSocket.getApiBase() || '';
    }
    return '';
  }

  function getMapId() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getMapId === 'function') {
      return window.ATAKSocket.getMapId();
    }
    return 1;
  }

  function setTheatreHint(text) {
    var hint = qs('atak-theatre-reset-hint');
    if (!hint) return;
    if (!text) {
      hint.hidden = true;
      hint.textContent = '';
      return;
    }
    hint.hidden = false;
    hint.textContent = text;
  }

  function runTheatreReset() {
    if (!canIssue()) {
      if (window.ATAKShowError) {
        window.ATAKShowError('Profil commandement requis pour vider la carte.');
      }
      return;
    }
    var ok = window.confirm(
      'Vider la carte pour tout le monde ?\n\nMarqueurs, ordres, messages, positions et tracés disparaissent. Les photos restent, classées par soirée.'
    );
    if (!ok) return;
    var typed = window.prompt('Pour confirmer, saisissez VIDER LA CARTE');
    if (String(typed || '').trim().toUpperCase() !== 'VIDER LA CARTE') {
      setTheatreHint('Annulé — la phrase de confirmation ne correspond pas.');
      return;
    }
    var base = getApiBase();
    var btn = qs('atak-theatre-reset-btn');
    if (btn) btn.disabled = true;
    setTheatreHint('Vidage de la carte…');
    fetch((base || '') + '/api/atak/theatre/reset', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        mapId: getMapId(),
        confirm: 'VIDER LA CARTE'
      })
    })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (body) {
          return { ok: r.ok, body: body || {} };
        });
      })
      .then(function (res) {
        if (!res.ok) {
          var msg = (res.body && res.body.message) || 'Impossible de vider la carte pour le moment.';
          setTheatreHint(msg);
          if (window.ATAKShowError) window.ATAKShowError(msg);
          return;
        }
        setTheatreHint((res.body && res.body.message) || 'Carte vidée.');
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification((res.body && res.body.message) || 'Carte vidée. Les photos sont conservées.');
        }
        window.setTimeout(function () { window.location.reload(); }, 500);
      })
      .catch(function () {
        setTheatreHint('Liaison interrompue. Réessayez.');
        if (window.ATAKShowError) window.ATAKShowError('Impossible de vider la carte pour le moment.');
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function setSettingsOpen(open) {
    var aside = qs('atak-settings-aside');
    if (!aside) return;
    if (open && window.ATAKSectionNav && typeof window.ATAKSectionNav.setLeftCollapsed === 'function') {
      window.ATAKSectionNav.setLeftCollapsed(false);
    }
    aside.hidden = !open;
    document.querySelectorAll('.js-atak-settings-toggle').forEach(function (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.classList.toggle('is-active', !!open);
    });
    document.body.classList.toggle('atak-settings-open', !!open);
  }

  function toggleSettings() {
    var aside = qs('atak-settings-aside');
    setSettingsOpen(aside ? aside.hidden : true);
  }

  function setWork(work, opts) {
    opts = opts || {};
    if (work !== 'emettre') work = 'suivi';
    if (work === 'emettre' && !canIssue()) work = 'suivi';
    currentWork = work;

    document.querySelectorAll('.atak-c2-worktab[data-c2-work]').forEach(function (btn) {
      var on = btn.getAttribute('data-c2-work') === work;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('[data-c2-workpane]').forEach(function (pane) {
      var on = pane.getAttribute('data-c2-workpane') === work;
      pane.classList.toggle('is-active', on);
      pane.hidden = !on;
    });

    if (!opts.skipStore) {
      try {
        localStorage.setItem(STORAGE_WORK, work);
      } catch (e) { /* ignore */ }
    }
  }

  function syncIssueAccess() {
    var allowed = canIssue();
    var emitTab = document.querySelector('.atak-c2-worktab[data-c2-work="emettre"]');
    var gotoBtn = qs('atak-c2-goto-emit');
    var tabs = document.querySelector('#tab-orders .atak-c2-worktabs');
    if (emitTab) emitTab.hidden = !allowed;
    if (gotoBtn) gotoBtn.hidden = !allowed;
    if (tabs) tabs.hidden = !allowed;
    if (!allowed && currentWork === 'emettre') {
      setWork('suivi', { skipStore: true });
    }
    syncResetAccess();
  }

  function initSettings() {
    var closeBtn = qs('atak-settings-close');
    var aside = qs('atak-settings-aside');
    document.querySelectorAll('.js-atak-settings-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSettings();
      });
    });
    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        setSettingsOpen(false);
      });
    }
    var resetBtn = qs('atak-theatre-reset-btn');
    if (resetBtn) {
      resetBtn.addEventListener('click', function (e) {
        e.preventDefault();
        runTheatreReset();
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (aside && !aside.hidden) {
        setSettingsOpen(false);
      }
    });
    setSettingsOpen(false);
    syncResetAccess();
  }

  function syncResetAccess() {
    var resetBtn = qs('atak-theatre-reset-btn');
    var hint = qs('atak-theatre-reset-noperm');
    var allowed = canIssue();
    if (resetBtn) {
      resetBtn.disabled = !allowed;
      resetBtn.title = allowed
        ? 'Retirer les marqueurs, ordres, messages et positions pour tout le monde'
        : 'Réservé au commandement';
    }
    if (hint) hint.hidden = allowed;
  }

  function initWorktabs() {
    var nav = document.querySelector('#tab-orders .atak-c2-worktabs');
    if (nav) {
      nav.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-c2-work]') : null;
        if (!btn || btn.hidden) return;
        setWork(btn.getAttribute('data-c2-work'));
      });
    }
    var gotoBtn = qs('atak-c2-goto-emit');
    if (gotoBtn) {
      gotoBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!canIssue()) return;
        setWork('emettre');
        window.setTimeout(function () {
          var typeEl = qs('atak-order-type');
          if (typeEl && typeof typeEl.focus === 'function') typeEl.focus();
        }, 40);
      });
    }

    var stored = 'suivi';
    try {
      stored = localStorage.getItem(STORAGE_WORK) || 'suivi';
    } catch (e) { /* ignore */ }
    setWork(stored, { skipStore: true });
    syncIssueAccess();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSettings();
    initWorktabs();
  });

  document.addEventListener('atak:session-profile', function () {
    syncIssueAccess();
  });

  window.ATAKC2Workspace = {
    setWork: setWork,
    setSettingsOpen: setSettingsOpen,
    syncIssueAccess: syncIssueAccess
  };
})();
