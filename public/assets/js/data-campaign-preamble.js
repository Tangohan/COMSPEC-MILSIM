/* Préambule de la fiabilisation : ouvert à la première visite d'une campagne, replié ensuite ; le choix du membre est gardé
   dans le navigateur, par campagne. Sans script, le préambule reste ouvert. */
(function () {
  'use strict';
  var box = document.querySelector('[data-dcm-pre]');
  if (!box) { return; }
  var key = 'dcm-pre:' + box.getAttribute('data-dcm-pre');
  var saved = null;
  try { saved = window.localStorage.getItem(key); } catch (e) { saved = null; }
  if (saved === 'closed') {
    box.removeAttribute('open');
  } else if (saved === null) {
    // Première visite : ouvert, et replié aux suivantes sauf si le membre le rouvre.
    try { window.localStorage.setItem(key, 'closed'); } catch (e) { /* stockage indisponible : reste ouvert */ }
  }
  box.addEventListener('toggle', function () {
    try { window.localStorage.setItem(key, box.open ? 'open' : 'closed'); } catch (e) { /* ignoré */ }
  });
})();
