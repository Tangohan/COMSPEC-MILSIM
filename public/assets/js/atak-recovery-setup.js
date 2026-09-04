/* Modal d’ouverture : codes de secours absents → page Compte. */
(function () {
  'use strict';

  var cfg = window.ATAK_DEVICE_SECURITY || {};
  var STORAGE_PREFIX = 'atak_recovery_setup_later_v1_';

  function storageKey() {
    var user = window.ATAK_USER || {};
    return STORAGE_PREFIX + String(user.tenantId || 0) + '_' + String(user.id || 0);
  }

  function skippedThisVisit() {
    try {
      return sessionStorage.getItem(storageKey()) === '1';
    } catch (e) {
      return false;
    }
  }

  function markSkipped() {
    try {
      sessionStorage.setItem(storageKey(), '1');
    } catch (e) {}
  }

  function modal() {
    return document.getElementById('atak-recovery-setup-modal');
  }

  function hide() {
    var el = modal();
    if (!el) return;
    el.hidden = true;
    el.setAttribute('aria-hidden', 'true');
  }

  function show() {
    if (!cfg.needsRecoveryCodes) return;
    if (window.ATAK_POPOUT || window.ATAK_DEVICE_EMBED) return;
    if (window.ATAK_CAPS && window.ATAK_CAPS.phoneSession) return;
    if (skippedThisVisit()) return;
    var el = modal();
    if (!el) return;
    el.hidden = false;
    el.setAttribute('aria-hidden', 'false');
    var go = document.getElementById('atak-recovery-setup-go');
    if (go && typeof go.focus === 'function') {
      try {
        go.focus();
      } catch (e) {}
    }
  }

  function bind() {
    var go = document.getElementById('atak-recovery-setup-go');
    if (go && cfg.setupUrl) {
      go.setAttribute('href', cfg.setupUrl);
    }
    var later = document.getElementById('atak-recovery-setup-later');
    if (later) {
      later.addEventListener('click', function () {
        markSkipped();
        hide();
      });
    }
    var el = modal();
    if (!el) return;
    el.querySelectorAll('[data-recovery-setup-dismiss]').forEach(function (node) {
      node.addEventListener('click', function () {
        markSkipped();
        hide();
      });
    });
  }

  bind();
  if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.onReady === 'function') {
    window.ATAKSessionProfile.onReady(show);
  } else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', show);
  } else {
    show();
  }
})();
