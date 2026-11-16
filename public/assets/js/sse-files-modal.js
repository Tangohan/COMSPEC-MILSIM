/* Fiche sujet des fichiers SSE : modales d'édition (dialog natif).
 *  - une icône « Modifier » [data-fx-modal-open="id"] ouvre la modale ; Échap la ferme, le focus revient à l'icône ;
 *  - le focus reste dans la modale (Tab boucle sur ses éléments) ; « Annuler » et la croix [data-fx-modal-close] ferment ;
 *  - une ancre « #fx-m-… » dans l'adresse ouvre la modale à l'arrivée ;
 *  - si le serveur renvoie une erreur de validation, la modale du formulaire envoyé se rouvre avec le message ;
 *  - sans JavaScript, les liens (#fx-m-…) affichent la modale par :target (voir sse-files-kit.css). */
(function () {
  'use strict';
  if (typeof HTMLDialogElement === 'undefined') { return; }
  var KEY = 'fx-last-modal';
  var opener = null;

  function store(fn) { try { return fn(window.sessionStorage); } catch (e) { return null; } }

  function focusable(dlg) {
    return Array.prototype.filter.call(
      dlg.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])'),
      function (el) { return el.offsetParent !== null || el === document.activeElement; }
    );
  }

  function open(id, from) {
    var dlg = document.getElementById(id);
    if (!dlg || dlg.tagName !== 'DIALOG') { return null; }
    opener = from || document.activeElement;
    if (dlg.hasAttribute('open')) { dlg.close(); }
    if (typeof dlg.showModal === 'function') { dlg.showModal(); }
    var first = dlg.querySelector('input:not([type="hidden"]):not([type="file"]), select, textarea');
    if (first) { first.focus(); }
    return dlg;
  }

  function close(dlg) {
    if (dlg && dlg.open) { dlg.close(); }
  }

  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target : null;
    if (!t) { return; }
    var o = t.closest('[data-fx-modal-open]');
    if (o) {
      e.preventDefault();
      open(o.getAttribute('data-fx-modal-open'), o);
      return;
    }
    var c = t.closest('[data-fx-modal-close]');
    if (c) {
      e.preventDefault();
      close(c.closest('dialog.fx-modal'));
      return;
    }
    // Clic sur le fond (hors de la carte) : fermeture.
    if (t.tagName === 'DIALOG' && t.classList.contains('fx-modal')) { close(t); }
  });

  document.addEventListener('close', function (e) {
    var dlg = e.target;
    if (!dlg || !dlg.classList || !dlg.classList.contains('fx-modal')) { return; }
    if (location.hash === '#' + dlg.id) {
      history.replaceState(null, '', location.pathname + location.search);
    }
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) { opener.focus(); }
    opener = null;
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') { return; }
    var dlg = e.target && e.target.closest ? e.target.closest('dialog.fx-modal[open]') : null;
    if (!dlg) { return; }
    var items = focusable(dlg);
    if (items.length === 0) { return; }
    var first = items[0];
    var last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  // Mémorise la modale du formulaire envoyé : si le serveur répond par une erreur, on la rouvre.
  document.addEventListener('submit', function (e) {
    var dlg = e.target && e.target.closest ? e.target.closest('dialog.fx-modal') : null;
    if (dlg) { store(function (s) { s.setItem(KEY, dlg.id); }); }
  });

  function init() {
    var last = store(function (s) { var v = s.getItem(KEY); s.removeItem(KEY); return v; });
    var flash = document.querySelector('[data-fx-flash-error]');
    var hash = location.hash.replace(/^#/, '');
    var target = null;
    if (flash) {
      var id = document.getElementById(hash) && document.getElementById(hash).tagName === 'DIALOG' ? hash : last;
      var dlg = id ? open(id, null) : null;
      if (dlg) {
        var slot = dlg.querySelector('[data-fx-modal-err]');
        if (slot) { slot.textContent = flash.textContent; slot.hidden = false; flash.hidden = true; }
        target = dlg;
      }
    } else if (hash.indexOf('fx-m-') === 0) {
      target = open(hash, null);
    }
    if (target) { opener = document.querySelector('[data-fx-modal-open="' + target.id + '"]'); }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
