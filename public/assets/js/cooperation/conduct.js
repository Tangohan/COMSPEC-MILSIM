/**
 * Coopération — conduite par étape.
 * - Vérifie côté client le prérequis du passage d’étape (OPORD, bilan) et l’explique sous le bouton.
 * - Le bouton « Passer à » ouvre une modale récapitulative ; sans JavaScript, il soumet directement
 *   (le serveur applique les mêmes contrôles).
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-coop-conduct]');
  if (!form) return;
  var advance = form.querySelector('[data-coop-advance]');
  var dialog = document.getElementById('coop-advance-dialog');
  var message = form.querySelector('[data-prereq-message]');
  var prereq = form.getAttribute('data-prereq') || '';
  var fieldFor = { opord: 'opord_text', aar: 'aar_summary' };
  var serverBlocked = advance ? advance.disabled : false;

  function check() {
    if (!advance || serverBlocked) return;
    var name = fieldFor[prereq];
    if (!name) return;
    var field = form.querySelector('[data-conduct-field="' + name + '"]');
    var ok = !!(field && field.value.trim() !== '');
    advance.disabled = !ok;
    if (message) {
      message.classList.toggle('fr-message--error', !ok);
      message.classList.toggle('fr-message--valid', ok);
    }
  }

  form.addEventListener('input', check);
  check();

  if (advance && dialog && typeof dialog.showModal === 'function') {
    advance.addEventListener('click', function (e) {
      e.preventDefault();
      if (advance.disabled) return;
      dialog.showModal();
    });
    var cancel = dialog.querySelector('[data-coop-advance-cancel]');
    if (cancel) {
      cancel.addEventListener('click', function () { dialog.close(); });
    }
  }
})();
