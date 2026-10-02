/**
 * JNET — retours visuels : barre de chargement à la navigation, état « en cours » des formulaires,
 * mémorisation du guide replié.
 */
(function () {
  'use strict';

  var root = document.querySelector('.jnet-embed');
  if (!root) {
    return;
  }

  var bar = document.createElement('div');
  bar.className = 'jn-progress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);

  var live = document.createElement('p');
  live.className = 'sr-only';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  root.appendChild(live);

  function startLoading(message) {
    bar.classList.remove('is-done');
    bar.classList.add('is-loading');
    root.setAttribute('aria-busy', 'true');
    live.textContent = message || 'Chargement…';
  }

  function stopLoading() {
    bar.classList.remove('is-loading');
    bar.classList.add('is-done');
    root.removeAttribute('aria-busy');
    live.textContent = '';
    root.querySelectorAll('[data-jn-busy]').forEach(function (btn) {
      btn.disabled = false;
      btn.removeAttribute('data-jn-busy');
      if (btn.dataset.jnLabel) {
        btn.textContent = btn.dataset.jnLabel;
      }
    });
  }

  root.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
      return;
    }
    if (a.target && a.target !== '_self') {
      return;
    }
    var url;
    try {
      url = new URL(a.href, window.location.href);
    } catch (err) {
      return;
    }
    if (url.origin !== window.location.origin) {
      return;
    }
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
      return;
    }
    startLoading('Ouverture de ' + (a.textContent || '').trim().slice(0, 60) + '…');
  });

  root.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || e.defaultPrevented) {
      return;
    }
    var btn = e.submitter || form.querySelector('button[type="submit"]');
    if (btn) {
      btn.dataset.jnLabel = btn.textContent;
      btn.setAttribute('data-jn-busy', '1');
      btn.textContent = btn.dataset.loading || 'Envoi…';
      // Désactivé après l'envoi pour ne pas retirer la valeur du bouton du formulaire.
      window.setTimeout(function () { btn.disabled = true; }, 0);
    }
    startLoading('Envoi en cours…');
  });

  // Retour arrière (cache navigateur) : on remet l'interface au repos.
  window.addEventListener('pageshow', stopLoading);

  // Guide : mémoriser l'état replié.
  var guide = root.querySelector('[data-jn-guide]');
  if (guide) {
    var key = 'jnet.guide.closed';
    try {
      if (window.localStorage.getItem(key) === '1') {
        guide.removeAttribute('open');
      }
    } catch (err) { /* stockage indisponible : guide ouvert */ }
    guide.addEventListener('toggle', function () {
      try {
        window.localStorage.setItem(key, guide.open ? '0' : '1');
      } catch (err) { /* ignore */ }
    });
  }
})();
