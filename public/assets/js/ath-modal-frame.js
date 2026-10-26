/**
 * Sous-pages en fenêtre sur la même page (moins de rechargements).
 *
 * Dans le back-office, les liens vers une page de création / modification (…/create, …/nouveau,
 * …/edit, …/modifier) et vers une fiche membre s'ouvrent dans une fenêtre par-dessus la page,
 * au lieu de remplacer la page. Ailleurs, un lien s'y ouvre avec l'attribut data-ath-modal ;
 * data-ath-modal="off" garde un lien en pleine page. Ctrl/Cmd-clic et clic molette restent
 * des ouvertures normales.
 *
 * La sous-page est chargée dans un cadre (même site) : ses formulaires et scripts fonctionnent
 * tels quels, sans menu ni bandeau (html.ath-in-modal, posé dans <head> par le layout).
 *  - Enregistrement qui revient à la page d'origine : la fenêtre se ferme, la page se met à jour
 *    et le message de confirmation s'affiche en toast.
 *  - Annuler / retour vers la page d'origine : la fenêtre se ferme, sans rechargement.
 *  - Fermeture après un enregistrement resté dans la fenêtre : la page se met à jour.
 */
(function () {
  'use strict';
  var inModalFrame = false;
  try { inModalFrame = !!(window.frameElement && window.frameElement.hasAttribute('data-ath-modal-frame')); } catch (e) {}
  if (inModalFrame || window.athModalFrame) return;

  var STASH_KEY = 'ath.modalFrame.toasts';

  var AUTO_PATTERNS = [
    /\/back-office\/.+\/(create|nouveau|ajouter|edit|modifier|edit-presentation)\/?$/,
    /\/back-office\/ressources\/effectifs\/membres\/\d+\/?$/,
    /\/back-office\/users\/\d+\/?$/
  ];
  // Éditeurs plein écran (mise en page, rédaction longue, studio) : restent en pleine page.
  var AUTO_EXCLUDE = /\/back-office\/(articles|atak\/comptes-rendus|dashboard-tenues|dashboard-pins|formation|training)(\/|$)/;

  function isBackOffice() {
    return !!document.getElementById('back-office-sidebar') || !!document.getElementById('platform-admin-sidebar');
  }

  function wantsModal(a, ev) {
    if (!a || !a.href || ev.defaultPrevented) return false;
    if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return false;
    if (a.hasAttribute('download')) return false;
    var target = (a.getAttribute('target') || '').toLowerCase();
    if (target && target !== '_self') return false;
    var mode = a.getAttribute('data-ath-modal');
    if (mode === 'off') return false;
    var u;
    try { u = new URL(a.href, window.location.href); } catch (e) { return false; }
    if (u.origin !== window.location.origin) return false;
    if (u.pathname === window.location.pathname) return false;
    if (mode !== null) return true;
    if (!isBackOffice() || AUTO_EXCLUDE.test(u.pathname)) return false;
    for (var i = 0; i < AUTO_PATTERNS.length; i++) {
      if (AUTO_PATTERNS[i].test(u.pathname)) return true;
    }
    return false;
  }

  /** Messages affichés par la sous-page (toasts du coin et bandeaux du back-office). */
  function readMessages(doc) {
    var out = [];
    try {
      doc.querySelectorAll('[data-flash-toast]').forEach(function (el) {
        var ps = el.querySelectorAll('p');
        var msg = ps.length ? ps[ps.length - 1].textContent.trim() : '';
        if (!msg) return;
        var c = el.className;
        var v = /bg-red/.test(c) ? 'error' : /bg-emerald/.test(c) ? 'success' : /bg-amber/.test(c) ? 'warning' : 'info';
        out.push({ v: v, m: msg });
      });
      doc.querySelectorAll('.ath-main__flashes .ds-alert').forEach(function (el) {
        var ps = el.querySelectorAll('p');
        var msg = ps.length > 1 ? ps[1].textContent.trim() : el.textContent.trim();
        if (!msg) return;
        var m = /ds-alert--(success|warning|info|error)/.exec(el.className);
        out.push({ v: m ? m[1] : 'error', m: msg });
      });
    } catch (e) {}
    return out;
  }

  function stash(messages) {
    if (!messages.length) return;
    try { sessionStorage.setItem(STASH_KEY, JSON.stringify(messages)); } catch (e) {}
  }

  function replayStash() {
    var raw = null;
    try { raw = sessionStorage.getItem(STASH_KEY); sessionStorage.removeItem(STASH_KEY); } catch (e) {}
    if (!raw || typeof window.athToast !== 'function') return;
    try {
      JSON.parse(raw).forEach(function (t) { window.athToast(t.v, t.m); });
    } catch (e) {}
  }

  var current = null;

  function open(url) {
    if (current) current.close(false);
    var startPath = window.location.pathname;
    var dialog = document.createElement('dialog');
    dialog.className = 'ath-mf';
    dialog.setAttribute('aria-label', 'Fenêtre');
    dialog.innerHTML =
      '<div class="ath-mf__head">' +
        '<p class="ath-mf__title">Chargement…</p>' +
        '<a class="ath-mf__full" href="#">Ouvrir en pleine page</a>' +
        '<button type="button" class="ath-mf__close" aria-label="Fermer la fenêtre">' +
          '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
        '</button>' +
      '</div>' +
      '<div class="ath-mf__body"><div class="ath-mf__loading" aria-hidden="true"></div></div>';
    var frame = document.createElement('iframe');
    frame.className = 'ath-mf__frame';
    frame.setAttribute('data-ath-modal-frame', '');
    frame.setAttribute('title', 'Contenu de la fenêtre');
    var title = dialog.querySelector('.ath-mf__title');
    var full = dialog.querySelector('.ath-mf__full');
    full.href = url;

    var state = { submitted: false, dirty: false };
    var api = {
      close: function (reload) {
        if (!dialog.isConnected) return;
        if (dialog.open) dialog.close();
        dialog.remove();
        document.documentElement.classList.remove('ath-mf-open');
        current = null;
        if (reload) window.location.reload();
      }
    };

    frame.addEventListener('load', function () {
      dialog.classList.add('is-ready');
      var win, doc, loc;
      try {
        win = frame.contentWindow;
        doc = frame.contentDocument;
        loc = win.location;
        void loc.pathname;
      } catch (e) {
        // Page hors du site : on y va en pleine page.
        window.location.href = url;
        return;
      }
      if (!doc || loc.href === 'about:blank') return;
      var landedSubmit = state.submitted;
      state.submitted = false;
      if (landedSubmit) state.dirty = true;

      // Retour à la page d'origine : enregistrement terminé (mise à jour + toast) ou annulation.
      if (loc.pathname === startPath) {
        if (state.dirty) {
          stash(readMessages(doc));
          api.close(true);
        } else {
          api.close(false);
        }
        return;
      }
      // Session expirée, accès refusé sans page précédente… : la page entière suit.
      if (/^(\/[^/]+)?\/(login|dashboard)\/?$/.test(loc.pathname)) {
        window.location.href = loc.href;
        return;
      }

      title.textContent = (doc.title || '').replace(/\s+[—-]\s+Athena\s*$/, '') || 'Fenêtre';
      dialog.setAttribute('aria-label', title.textContent);
      full.href = loc.href;
      doc.addEventListener('submit', function (ev) {
        var form = ev.target;
        var t = form && form.getAttribute ? (form.getAttribute('target') || '').toLowerCase() : '';
        if (t && t !== '_self') return;
        setTimeout(function () { if (!ev.defaultPrevented) state.submitted = true; else state.dirty = true; }, 0);
      });
      // Un lien de la sous-page qui vise toute la fenêtre (_top / _parent) ouvre la page entière.
      doc.addEventListener('click', function (ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('a[target="_top"], a[target="_parent"]') : null;
        if (!a) return;
        ev.preventDefault();
        window.location.href = a.href;
      });
    });

    dialog.querySelector('.ath-mf__close').addEventListener('click', function () { api.close(state.dirty); });
    dialog.addEventListener('cancel', function (ev) { ev.preventDefault(); api.close(state.dirty); });
    dialog.addEventListener('click', function (ev) { if (ev.target === dialog) api.close(state.dirty); });
    full.addEventListener('click', function (ev) {
      ev.preventDefault();
      window.location.href = full.href;
    });

    dialog.querySelector('.ath-mf__body').appendChild(frame);
    document.body.appendChild(dialog);
    document.documentElement.classList.add('ath-mf-open');
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      window.location.href = url;
      return;
    }
    frame.src = url;
    current = api;
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!wantsModal(a, ev)) return;
    ev.preventDefault();
    open(a.href);
  });

  window.athModalFrame = { open: open };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', replayStash);
  } else {
    replayStash();
  }
})();
