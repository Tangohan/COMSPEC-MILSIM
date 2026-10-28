/* Fenêtre « Mise à jour disponible » — toutes les pages du site.
   La page connaît l'identifiant du déploiement qui l'a servie (<meta name="athena-build">, injecté côté serveur) ;
   on interroge /api/system/version quand l'onglet est visible (toutes les 2 min, au retour sur l'onglet, au retour
   réseau). Si le site a été redéployé : fenêtre FR/EN, « Actualiser » vide les caches du service worker puis recharge.
   Une saisie en cours n'est jamais perdue sans prévenir. */
(function () {
  'use strict';

  if (window.__athenaUpdateCheck) {
    return;
  }
  window.__athenaUpdateCheck = true;

  // Page intégrée dans une autre (iframe) : la page parente s'en charge.
  try {
    if (window.top !== window.self) {
      return;
    }
  } catch (err) {
    return;
  }

  var meta = document.querySelector('meta[name="athena-build"]');
  var currentBuild = meta ? (meta.getAttribute('content') || '') : (typeof window.APP_BUILD === 'string' ? window.APP_BUILD : '');
  var currentVersion = typeof window.APP_VERSION === 'string' ? window.APP_VERSION : '';
  var currentLabel = meta ? (meta.getAttribute('data-label') || '') : '';
  var base = meta && meta.hasAttribute('data-base')
    ? (meta.getAttribute('data-base') || '')
    : (typeof window.APP_BASE_URL === 'string' ? window.APP_BASE_URL : '');
  base = base.replace(/\/$/, '');
  var endpoint = (meta && meta.getAttribute('data-endpoint')) || (base + '/api/system/version');

  var locale = (meta && meta.getAttribute('data-locale')) || (document.documentElement.getAttribute('lang') || 'fr');
  var en = String(locale).toLowerCase().indexOf('en') === 0;
  var T = en ? {
    title: 'Update available',
    lead: 'A new version of the portal is available. Reload the page to get the latest changes.',
    from: 'You are using version ',
    to: '. After reloading you will be on version ',
    dirty: 'You have unsaved input on this page. Save it first, or it will be lost when the page reloads.',
    later: 'Later',
    reload: 'Reload',
    reloadAnyway: 'Reload anyway',
    close: 'Close',
    reloading: 'Reloading…'
  } : {
    title: 'Mise à jour disponible',
    lead: 'Une nouvelle version du portail est disponible. Actualisez la page pour charger les nouveautés.',
    from: 'Vous utilisez actuellement la version ',
    to: '. Après actualisation, vous passerez à la version ',
    dirty: 'Vous avez une saisie en cours sur cette page. Enregistrez-la d’abord : elle serait perdue au rechargement.',
    later: 'Plus tard',
    reload: 'Actualiser',
    reloadAnyway: 'Actualiser quand même',
    close: 'Fermer',
    reloading: 'Actualisation…'
  };

  var modalId = 'athena-app-update-modal';
  var snoozeKey = 'athena_update_snooze';
  var SNOOZE_MS = 10 * 60 * 1000;
  var POLL_MS = 2 * 60 * 1000;
  var MIN_GAP_MS = 20 * 1000;
  var lastFocused = null;
  var keyHandler = null;
  var lastCheck = 0;
  var inFlight = false;
  var pending = null;
  var dirty = false;

  function isPreviewRequested() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      return params.get('preview_update_modal') === '1';
    } catch (err) {
      return false;
    }
  }

  // ——— Saisie en cours : tout champ modifié depuis l'ouverture (hors recherche / filtres) ———
  function isTrackedField(el) {
    if (!el || !el.tagName || (el.closest && el.closest('#' + modalId))) {
      return false;
    }
    var tag = el.tagName.toLowerCase();
    if (el.isContentEditable) {
      return true;
    }
    if (tag === 'textarea') {
      return true;
    }
    if (tag === 'select') {
      return !!(el.form && el.form.method && el.form.method.toLowerCase() === 'post');
    }
    if (tag !== 'input') {
      return false;
    }
    var type = (el.getAttribute('type') || 'text').toLowerCase();
    if (['search', 'hidden', 'button', 'submit', 'reset', 'image', 'range', 'color'].indexOf(type) !== -1) {
      return false;
    }
    if (['checkbox', 'radio', 'file'].indexOf(type) !== -1) {
      return !!(el.form && el.form.method && el.form.method.toLowerCase() === 'post');
    }
    return true;
  }

  document.addEventListener('input', function (e) {
    if (isTrackedField(e.target)) {
      dirty = true;
    }
  }, true);
  document.addEventListener('change', function (e) {
    if (isTrackedField(e.target)) {
      dirty = true;
    }
  }, true);
  document.addEventListener('submit', function () {
    dirty = false;
  }, true);

  function hasUnsavedInput() {
    return dirty;
  }

  // ——— Rechargement propre : caches du service worker (hors tuiles de carte) puis reload ———
  function purgeCaches() {
    var jobs = [];
    try {
      if (window.caches && caches.keys) {
        jobs.push(caches.keys().then(function (keys) {
          return Promise.all(keys.filter(function (k) {
            return k.indexOf('athena-overwatch-tiles') !== 0;
          }).map(function (k) {
            return caches.delete(k);
          }));
        }));
      }
    } catch (err) { /* ignore */ }
    try {
      if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
        jobs.push(navigator.serviceWorker.getRegistrations().then(function (regs) {
          return Promise.all(regs.map(function (r) {
            return r.update().catch(function () {});
          }));
        }));
      }
    } catch (err) { /* ignore */ }
    var all = Promise.all(jobs).catch(function () {});
    // Ne jamais bloquer le rechargement plus de 2 s.
    return Promise.race([all, new Promise(function (resolve) { window.setTimeout(resolve, 2000); })]);
  }

  function reloadNow() {
    dirty = false;
    try {
      sessionStorage.removeItem(snoozeKey);
    } catch (err) { /* ignore */ }
    purgeCaches().then(function () {
      window.location.reload();
    });
  }

  // ——— Fenêtre ———
  function focusables(root) {
    return Array.prototype.slice.call(root.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(function (el) {
      return el.offsetParent !== null || el.getClientRects().length > 0;
    });
  }

  function unlockPage() {
    document.documentElement.classList.remove('ds-app-update-open');
    if (keyHandler) {
      document.removeEventListener('keydown', keyHandler, true);
      keyHandler = null;
    }
    if (lastFocused && typeof lastFocused.focus === 'function') {
      try {
        lastFocused.focus();
      } catch (err) { /* ignore */ }
    }
    lastFocused = null;
  }

  function snoozed(build) {
    try {
      var raw = sessionStorage.getItem(snoozeKey);
      if (!raw) {
        return false;
      }
      var s = JSON.parse(raw);
      return s && s.build === build && (Date.now() - (s.at || 0)) < SNOOZE_MS;
    } catch (err) {
      return false;
    }
  }

  function snooze(build) {
    try {
      sessionStorage.setItem(snoozeKey, JSON.stringify({ build: build, at: Date.now() }));
    } catch (err) { /* ignore */ }
  }

  function svg(path, cls) {
    return '<svg class="' + cls + '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="' + path + '"/></svg>';
  }

  function showModal(info, options) {
    var opts = options || {};
    var preview = !!opts.preview;
    if (document.getElementById(modalId) || !document.body) {
      return;
    }
    if (!preview && snoozed(info.build)) {
      return;
    }

    var unsaved = !preview && hasUnsavedInput();
    var fromLabel = currentLabel || currentVersion;
    var toLabel = info.label || info.version || '';

    lastFocused = document.activeElement;

    var root = document.createElement('div');
    root.id = modalId;
    root.className = 'ds-app-update';
    root.setAttribute('translate', 'no');
    var html = document.documentElement;
    var darkBo = html.getAttribute('data-bo-theme') === 'dark';
    var darkAtak = document.body.classList.contains('atak-page') && !document.body.classList.contains('atak-theme-light');
    if (darkBo || darkAtak) {
      root.classList.add('ds-app-update--atak');
    }

    var scrim = document.createElement('div');
    scrim.className = 'ds-app-update__scrim';
    scrim.setAttribute('aria-hidden', 'true');

    var dialog = document.createElement('div');
    dialog.className = 'ds-app-update__dialog';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'athena-app-update-title');
    dialog.setAttribute('aria-describedby', 'athena-app-update-lead');
    dialog.setAttribute('tabindex', '-1');

    var header = document.createElement('div');
    header.className = 'ds-app-update__header';

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ds-app-update__close';
    closeBtn.setAttribute('aria-label', T.close);
    closeBtn.innerHTML = T.close + ' ' + svg('M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z', 'ds-app-update__close-icon');
    header.appendChild(closeBtn);

    var body = document.createElement('div');
    body.className = 'ds-app-update__body';

    var title = document.createElement('h2');
    title.id = 'athena-app-update-title';
    title.className = 'ds-app-update__title';
    title.innerHTML = svg('M12 4a8 8 0 1 1-7.45 5.2l1.86.7A6 6 0 1 0 12 6V3l4 3.5L12 10V8a4 4 0 1 1-3.46 2l-1.73-1A6 6 0 0 1 12 4z', 'ds-app-update__title-icon');
    var titleText = document.createElement('span');
    titleText.textContent = T.title;
    title.appendChild(titleText);

    var lead = document.createElement('p');
    lead.id = 'athena-app-update-lead';
    lead.className = 'ds-app-update__lead';
    lead.textContent = T.lead;

    body.appendChild(title);
    body.appendChild(lead);

    if (fromLabel && toLabel && fromLabel !== toLabel) {
      var versions = document.createElement('p');
      versions.className = 'ds-app-update__versions';
      versions.appendChild(document.createTextNode(T.from));
      var fromStrong = document.createElement('strong');
      fromStrong.textContent = fromLabel;
      versions.appendChild(fromStrong);
      versions.appendChild(document.createTextNode(T.to));
      var toStrong = document.createElement('strong');
      toStrong.textContent = toLabel;
      versions.appendChild(toStrong);
      versions.appendChild(document.createTextNode('.'));
      body.appendChild(versions);
    }

    if (unsaved) {
      var warn = document.createElement('p');
      warn.className = 'ds-app-update__warn';
      warn.setAttribute('role', 'note');
      warn.textContent = T.dirty;
      body.appendChild(warn);
    }

    var footer = document.createElement('div');
    footer.className = 'ds-app-update__footer';

    var later = document.createElement('button');
    later.type = 'button';
    later.className = 'ds-app-update__btn ds-app-update__btn--secondary';
    later.textContent = T.later;

    var reload = document.createElement('button');
    reload.type = 'button';
    reload.className = 'ds-app-update__btn ds-app-update__btn--primary';
    reload.textContent = unsaved ? T.reloadAnyway : T.reload;

    footer.appendChild(later);
    footer.appendChild(reload);

    dialog.appendChild(header);
    dialog.appendChild(body);
    dialog.appendChild(footer);
    root.appendChild(scrim);
    root.appendChild(dialog);

    function dismiss() {
      if (!preview) {
        snooze(info.build);
      }
      root.remove();
      unlockPage();
    }

    closeBtn.addEventListener('click', dismiss);
    later.addEventListener('click', dismiss);
    scrim.addEventListener('click', dismiss);
    reload.addEventListener('click', function () {
      reload.disabled = true;
      later.disabled = true;
      reload.textContent = T.reloading;
      if (preview) {
        window.location.href = window.location.pathname;
        return;
      }
      reloadNow();
    });

    keyHandler = function (e) {
      if (!document.getElementById(modalId)) {
        return;
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        dismiss();
        return;
      }
      if (e.key !== 'Tab') {
        return;
      }
      var list = focusables(dialog);
      if (!list.length) {
        e.preventDefault();
        dialog.focus();
        return;
      }
      var first = list[0];
      var last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };
    document.addEventListener('keydown', keyHandler, true);

    document.documentElement.classList.add('ds-app-update-open');
    document.body.appendChild(root);
    window.setTimeout(function () {
      // Saisie en cours : focus sur « Plus tard » pour ne pas recharger par erreur avec Entrée.
      (unsaved ? later : reload).focus();
    }, 0);
  }

  // ——— Détection ———
  function isNewer(data) {
    var build = data && typeof data.build === 'string' ? data.build : '';
    if (build && currentBuild) {
      return build !== currentBuild;
    }
    var version = data && typeof data.version === 'string' ? data.version : '';
    return !!(version && currentVersion && version !== currentVersion);
  }

  function check(force) {
    if (inFlight || document.visibilityState === 'hidden') {
      return;
    }
    if (!force && Date.now() - lastCheck < MIN_GAP_MS) {
      return;
    }
    if (typeof navigator.onLine === 'boolean' && !navigator.onLine) {
      return;
    }
    lastCheck = Date.now();
    inFlight = true;
    var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
    fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        var data = payload && payload.data ? payload.data : payload;
        if (data && isNewer(data)) {
          pending = {
            build: typeof data.build === 'string' && data.build ? data.build : String(data.version || ''),
            version: typeof data.version === 'string' ? data.version : '',
            label: typeof data.label === 'string' ? data.label : ''
          };
          showModal(pending);
        }
      })
      .catch(function () { /* silencieux */ })
      .then(function () {
        inFlight = false;
      });
  }

  window.__athenaShowUpdateModal = function (version) {
    showModal({ build: 'preview', version: version || '', label: version || (en ? 'new version' : 'nouvelle version') }, { preview: true });
  };

  function start() {
    if (isPreviewRequested()) {
      window.__athenaShowUpdateModal(currentVersion ? currentVersion + ' · next' : '');
      return;
    }
    if (!currentBuild && !currentVersion) {
      return;
    }
    check(true);
    window.setInterval(function () {
      // Mise à jour déjà connue mais reportée : la proposer de nouveau à la fin du report.
      if (pending && !document.getElementById(modalId) && !snoozed(pending.build) && document.visibilityState !== 'hidden') {
        showModal(pending);
        return;
      }
      check(false);
    }, POLL_MS);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        check(false);
      }
    });
    window.addEventListener('focus', function () { check(false); });
    window.addEventListener('online', function () { check(true); });
    // Page restaurée depuis le cache avant/arrière du navigateur : elle peut dater d'avant la mise à jour.
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) {
        check(true);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
