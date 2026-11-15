/**
 * Fenêtre « Aligner sur l'état civil » (views/partials/sse_civil_align.php) : ouverture, fermeture, envoi unique.
 * Aucun libellé ici : tout vient de la vue.
 */
(function () {
  'use strict';

  document.addEventListener('click', function (ev) {
    var open = ev.target.closest('[data-civil-open]');
    if (open) {
      var dialog = document.getElementById(open.getAttribute('data-civil-open'));
      if (dialog && typeof dialog.showModal === 'function') {
        ev.preventDefault();
        dialog.showModal();
      }
      return;
    }
    var close = ev.target.closest('[data-civil-close]');
    if (close) {
      ev.preventDefault();
      var parent = close.closest('dialog');
      if (parent) {
        parent.close();
      }
      return;
    }
    // Un clic sur le fond (hors de la carte) ferme la fenêtre.
    if (ev.target instanceof HTMLDialogElement && ev.target.classList.contains('civil-align')) {
      ev.target.close();
    }
  });

  // Un seul envoi : le bouton se désactive dès la confirmation.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('civil-align__card')) {
      return;
    }
    var confirm = form.querySelector('.civil-align__confirm');
    if (confirm) {
      confirm.disabled = true;
    }
  });
})();
