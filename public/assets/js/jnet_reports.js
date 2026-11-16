/**
 * JNET — comptes rendus (/jnet/comptes-rendus) : fenêtres de détail et de traitement (<dialog>), filtres appliqués au
 * changement. Sans ce script, les filtres restent envoyés par le bouton « Filtrer ».
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-jr]');
  if (!root) {
    return;
  }

  var opener = null;

  root.addEventListener('click', function (event) {
    var open = event.target.closest('[data-jr-open]');
    if (open) {
      var dialog = document.getElementById(open.getAttribute('data-jr-open'));
      if (dialog && typeof dialog.showModal === 'function') {
        opener = open;
        dialog.showModal();
        var first = dialog.querySelector('[data-jr-close]');
        if (first) {
          first.focus();
        }
      }
      return;
    }
    var close = event.target.closest('[data-jr-close]');
    if (close) {
      var parent = close.closest('dialog');
      if (parent) {
        parent.close();
      }
      return;
    }
    // Clic sur le voile, hors de la fenêtre : on ferme.
    if (event.target.tagName === 'DIALOG') {
      event.target.close();
    }
  });

  root.querySelectorAll('dialog').forEach(function (dialog) {
    dialog.addEventListener('close', function () {
      if (opener) {
        opener.focus();
        opener = null;
      }
    });
  });

  root.querySelectorAll('[data-jr-autosubmit]').forEach(function (field) {
    field.addEventListener('change', function () {
      if (field.form) {
        field.form.requestSubmit ? field.form.requestSubmit() : field.form.submit();
      }
    });
  });
})();
