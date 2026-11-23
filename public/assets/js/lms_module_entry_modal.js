/**
 * Modale d’entrée module LMS — intercept liens data-lms-module-entry + accueil à l’arrivée.
 * Clés localStorage / sessionStorage : athena.lms.moduleEntry.*
 * Fermer ou continuer mémorise durablement la version vue (localStorage) ; seul un bouton
 * « Revoir le tutoriel » ou une nouvelle version (LmsModuleEntry::TUTORIAL_VERSION) le rejoue.
 */
(function () {
  'use strict';

  var DISMISS_KEY = 'athena.lms.moduleEntry.dismiss.v1';
  var WELCOMED_KEY = 'athena.lms.moduleEntry.welcomed.v1';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function readMap(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) {
        return {};
      }
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function writeMap(key, map) {
    try {
      localStorage.setItem(key, JSON.stringify(map || {}));
    } catch (e) {
      /* ignore quota / private mode */
    }
  }

  function readSessionMap(key) {
    try {
      var raw = sessionStorage.getItem(key);
      if (!raw) {
        return {};
      }
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function writeSessionMap(key, map) {
    try {
      sessionStorage.setItem(key, JSON.stringify(map || {}));
    } catch (e) {
      /* ignore */
    }
  }

  // Version courante du tutoriel de chaque module (cfg.modules[x].version, définie côté PHP).
  // L’état « vu » mémorise la version vue : l’ancien format `true` vaut version 1.
  function currentVersion(moduleKey) {
    var cfg = window.__lmsModuleEntry || {};
    var mod = cfg.modules && cfg.modules[moduleKey];
    var v = mod ? parseInt(mod.version, 10) : 1;
    return v > 0 ? v : 1;
  }

  function isDismissed(moduleKey) {
    var seen = readMap(DISMISS_KEY)[moduleKey];
    if (seen === true) {
      seen = 1;
    }
    return typeof seen === 'number' && seen >= currentVersion(moduleKey);
  }

  function isWelcomed(moduleKey) {
    return !!readSessionMap(WELCOMED_KEY)[moduleKey];
  }

  function markWelcomed(moduleKey) {
    var map = readSessionMap(WELCOMED_KEY);
    map[moduleKey] = true;
    writeSessionMap(WELCOMED_KEY, map);
  }

  function markDismissed(moduleKey) {
    var map = readMap(DISMISS_KEY);
    map[moduleKey] = currentVersion(moduleKey);
    writeMap(DISMISS_KEY, map);
  }

  ready(function () {
    var root = document.querySelector('[data-lms-module-entry-modal]');
    var cfg = window.__lmsModuleEntry;
    if (!root || !cfg || typeof cfg !== 'object') {
      return;
    }
    if (root.getAttribute('data-lms-entry-init') === '1') {
      return;
    }
    root.setAttribute('data-lms-entry-init', '1');

    var modules = cfg.modules || {};
    var profile = cfg.profile || {};
    var pendingHref = null;
    var pendingModule = null;
    var lastFocus = null;

    var titleEl = root.querySelector('[data-lms-entry-title]');
    var kickerEl = root.querySelector('[data-lms-entry-kicker]');
    var leadEl = root.querySelector('[data-lms-entry-lead]');
    var nameEl = root.querySelector('[data-lms-entry-profile-name]');
    var metaEl = root.querySelector('[data-lms-entry-profile-meta]');
    var rightsEl = root.querySelector('[data-lms-entry-rights]');
    var continueBtn = root.querySelector('[data-lms-entry-continue]');
    var secondaryBtn = root.querySelector('[data-lms-entry-secondary]');
    var accentEl = root.querySelector('[data-lms-entry-accent]');
    var panelEl = root.querySelector('[data-lms-entry-panel]');

    function fillProfile() {
      var name = profile.display_name || 'Opérateur';
      if (nameEl) {
        nameEl.textContent = name;
      }
      var bits = [];
      if (profile.callsign) {
        bits.push('Indicatif ' + profile.callsign);
      }
      if (profile.role) {
        bits.push(profile.role);
      }
      if (profile.community) {
        bits.push(profile.community);
      }
      if (metaEl) {
        metaEl.textContent = bits.join(' · ');
      }
    }

    function fillModule(moduleKey) {
      var mod = modules[moduleKey];
      if (!mod) {
        return false;
      }
      if (kickerEl) {
        kickerEl.textContent = mod.kicker || 'Passage portail → module';
      }
      if (titleEl) {
        titleEl.textContent = mod.title || 'Module';
      }
      if (leadEl) {
        leadEl.textContent = mod.lead || '';
      }
      if (continueBtn) {
        continueBtn.textContent = mod.cta || 'Continuer';
      }
      if (accentEl) {
        accentEl.setAttribute('data-accent', mod.accent || 'emerald');
      }
      if (panelEl) {
        panelEl.setAttribute('data-accent', mod.accent || 'emerald');
      }
      if (rightsEl) {
        rightsEl.innerHTML = '';
        var rights = Array.isArray(mod.rights) ? mod.rights : [];
        if (rights.length === 0) {
          var empty = document.createElement('li');
          empty.textContent = 'Accès selon les droits de votre compte';
          rightsEl.appendChild(empty);
        } else {
          rights.forEach(function (label) {
            var li = document.createElement('li');
            li.textContent = String(label);
            rightsEl.appendChild(li);
          });
        }
      }
      fillProfile();
      return true;
    }

    function openModal(moduleKey, href) {
      if (!fillModule(moduleKey)) {
        if (href) {
          window.location.href = href;
        }
        return;
      }
      pendingModule = moduleKey;
      pendingHref = href || null;
      if (secondaryBtn) {
        secondaryBtn.textContent = pendingHref ? 'Rester sur le portail' : 'Fermer';
      }
      lastFocus = document.activeElement;
      root.classList.remove('hidden');
      root.setAttribute('aria-hidden', 'false');
      document.body.classList.add('lms-entry-modal-open');
      if (continueBtn && typeof continueBtn.focus === 'function') {
        try {
          continueBtn.focus({ preventScroll: true });
        } catch (e) {
          continueBtn.focus();
        }
      }
    }

    function closeModal(opts) {
      opts = opts || {};
      if (opts.markSeen && pendingModule) {
        markWelcomed(pendingModule);
        markDismissed(pendingModule);
      }
      root.classList.add('hidden');
      root.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('lms-entry-modal-open');
      pendingHref = null;
      pendingModule = null;
      if (lastFocus && typeof lastFocus.focus === 'function') {
        try {
          lastFocus.focus({ preventScroll: true });
        } catch (e) {
          lastFocus.focus();
        }
      }
      lastFocus = null;
    }

    function finishContinue() {
      var moduleKey = pendingModule;
      var href = pendingHref;
      if (moduleKey) {
        markWelcomed(moduleKey);
        markDismissed(moduleKey);
      }
      closeModal();
      if (href) {
        window.location.href = href;
      }
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-lms-entry-dismiss]'), function (el) {
      el.addEventListener('click', function (ev) {
        ev.preventDefault();
        // À l’arrivée LMS (pas de navigation en attente), fermer = déjà accueilli pour la session.
        closeModal({ markSeen: !pendingHref });
      });
    });

    if (continueBtn) {
      continueBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        finishContinue();
      });
    }

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !root.classList.contains('hidden')) {
        closeModal({ markSeen: !pendingHref });
      }
    });

    document.addEventListener('click', function (ev) {
      var target = ev.target;
      if (!target || typeof target.closest !== 'function') {
        return;
      }
      var link = target.closest('[data-lms-module-entry]');
      if (!link) {
        return;
      }
      var moduleKey = link.getAttribute('data-lms-module-entry');
      if (!moduleKey || !modules[moduleKey]) {
        return;
      }
      if (isDismissed(moduleKey) || isWelcomed(moduleKey)) {
        markWelcomed(moduleKey);
        return;
      }
      var href = link.getAttribute('href');
      if (!href || href === '#' || link.getAttribute('target') === '_blank') {
        return;
      }
      if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
        return;
      }
      ev.preventDefault();
      openModal(moduleKey, href);
    }, true);

    // « Revoir le tutoriel » : rouvre la modale à la demande, sans tenir compte de l’état « vu ».
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      var btn = t && typeof t.closest === 'function' ? t.closest('[data-lms-entry-replay]') : null;
      if (!btn) {
        return;
      }
      ev.preventDefault();
      openModal(btn.getAttribute('data-lms-entry-replay'), null);
    });

    var autoOpen = cfg.autoOpen;
    if (autoOpen && modules[autoOpen] && !isDismissed(autoOpen) && !isWelcomed(autoOpen)) {
      window.setTimeout(function () {
        if (!isDismissed(autoOpen) && !isWelcomed(autoOpen)) {
          openModal(autoOpen, null);
        }
      }, 380);
    }
  });
})();
