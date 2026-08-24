(function () {
  'use strict';

  var current = typeof window.APP_VERSION === 'string' ? window.APP_VERSION : '';
  var endpoint = (typeof window.APP_BASE_URL === 'string' ? window.APP_BASE_URL.replace(/\/$/, '') : '')
    + '/api/system/version';
  var modalId = 'athena-app-update-modal';
  var dismissedKey = 'athena_dismissed_version';
  var lastFocused = null;
  var keyHandler = null;

  function isPreviewRequested() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      return params.get('preview_update_modal') === '1';
    } catch (err) {
      return false;
    }
  }

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

  function closeModal(root, version, persistDismiss) {
    if (!root) {
      return;
    }
    if (persistDismiss && version) {
      try {
        sessionStorage.setItem(dismissedKey, version);
      } catch (err) { /* ignore */ }
    }
    root.remove();
    unlockPage();
  }

  function showModal(newVersion, options) {
    var opts = options || {};
    var preview = !!opts.preview;
    if (document.getElementById(modalId)) {
      return;
    }
    if (!preview) {
      try {
        if (sessionStorage.getItem(dismissedKey) === newVersion) {
          return;
        }
      } catch (err) { /* ignore */ }
    }

    var fromLabel = current;
    var toLabel = newVersion;

    lastFocused = document.activeElement;

    var root = document.createElement('div');
    root.id = modalId;
    root.className = 'ds-app-update';
    if (document.body && document.body.classList.contains('atak-page') && !document.body.classList.contains('atak-theme-light')) {
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
    dialog.setAttribute('aria-describedby', 'athena-app-update-lead athena-app-update-versions');
    dialog.setAttribute('tabindex', '-1');

    var header = document.createElement('div');
    header.className = 'ds-app-update__header';

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ds-app-update__close';
    closeBtn.setAttribute('aria-label', 'Fermer');
    closeBtn.innerHTML = 'Fermer <svg class="ds-app-update__close-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"/></svg>';

    header.appendChild(closeBtn);

    var body = document.createElement('div');
    body.className = 'ds-app-update__body';

    var title = document.createElement('h2');
    title.id = 'athena-app-update-title';
    title.className = 'ds-app-update__title';
    title.innerHTML = '<svg class="ds-app-update__title-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 4a8 8 0 1 1-7.45 5.2l1.86.7A6 6 0 1 0 12 6V3l4 3.5L12 10V8a4 4 0 1 1-3.46 2l-1.73-1A6 6 0 0 1 12 4z"/></svg><span>Mise à jour</span>';

    var lead = document.createElement('p');
    lead.id = 'athena-app-update-lead';
    lead.className = 'ds-app-update__lead';
    lead.textContent = 'Une nouvelle version du portail est disponible. Actualisez la page pour charger les nouveautés.';

    var versions = document.createElement('p');
    versions.id = 'athena-app-update-versions';
    versions.className = 'ds-app-update__versions';
    versions.appendChild(document.createTextNode('Vous utilisez actuellement la version '));
    var fromStrong = document.createElement('strong');
    fromStrong.textContent = fromLabel;
    versions.appendChild(fromStrong);
    versions.appendChild(document.createTextNode('. Après actualisation, vous passerez à la version '));
    var toStrong = document.createElement('strong');
    toStrong.textContent = toLabel;
    versions.appendChild(toStrong);
    versions.appendChild(document.createTextNode('.'));

    body.appendChild(title);
    body.appendChild(lead);
    if (fromLabel && toLabel) {
      body.appendChild(versions);
    } else {
      versions.removeAttribute('id');
      dialog.setAttribute('aria-describedby', 'athena-app-update-lead');
    }

    var footer = document.createElement('div');
    footer.className = 'ds-app-update__footer';

    var later = document.createElement('button');
    later.type = 'button';
    later.className = 'ds-app-update__btn ds-app-update__btn--secondary';
    later.textContent = 'Plus tard';

    var reload = document.createElement('button');
    reload.type = 'button';
    reload.className = 'ds-app-update__btn ds-app-update__btn--primary';
    reload.textContent = 'Actualiser';

    footer.appendChild(later);
    footer.appendChild(reload);

    dialog.appendChild(header);
    dialog.appendChild(body);
    dialog.appendChild(footer);
    root.appendChild(scrim);
    root.appendChild(dialog);

    function dismiss() {
      closeModal(root, newVersion, !preview);
    }

    closeBtn.addEventListener('click', dismiss);
    later.addEventListener('click', dismiss);
    scrim.addEventListener('click', dismiss);
    reload.addEventListener('click', function () {
      window.location.reload();
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
      reload.focus();
    }, 0);
  }

  function check() {
    fetch(endpoint, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        var data = payload && payload.data ? payload.data : payload;
        var version = data && typeof data.version === 'string' ? data.version : '';
        if (version && version !== current) {
          showModal(version);
        }
      })
      .catch(function () { /* silencieux */ });
  }

  window.__athenaShowUpdateModal = function (version) {
    showModal(version || 'aperçu', { preview: true });
  };

  if (!current && !isPreviewRequested()) {
    return;
  }

  if (isPreviewRequested()) {
    var previewTo = current;
    if (current && /^\d+\.\d+\.\d+/.test(current)) {
      previewTo = current.replace(/\d+$/, function (n) {
        return String(parseInt(n, 10) + 1);
      });
    } else if (!previewTo) {
      previewTo = 'nouvelle version';
    }
    showModal(previewTo, { preview: true });
    return;
  }

  check();
  setInterval(check, 60000);
})();
