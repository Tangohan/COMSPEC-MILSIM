/*
 * Overwatch — messages du poste (colonne Réglages) : demande d'accès aux vues fermées par le profil,
 * et rappel « Lier mon jeu » masqué dès que le contact du compte est reçu sur la carte.
 */
(function () {
  'use strict';

  var notices = document.getElementById('ow-session-notices');
  if (!notices) return;

  var gap = document.getElementById('ow-access-gap');
  if (gap) {
    var btn = gap.querySelector('[data-ow-access-request]');
    var status = gap.querySelector('[data-ow-access-status]');
    if (btn) {
      btn.addEventListener('click', function () {
        var csrf = String(window.ATAK_CSRF || '');
        btn.disabled = true;
        if (status) status.textContent = 'Envoi de la demande…';
        fetch(gap.getAttribute('data-request-url'), {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ _csrf_token: csrf, live: true })
        }).then(function (r) {
          return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, data: d || {} }; });
        }).then(function (res) {
          if (status) status.textContent = res.data.message || (res.ok ? 'Demande transmise à l’encadrement.' : 'La demande n’a pas pu être envoyée.');
          if (!res.ok) btn.disabled = false;
        }).catch(function () {
          if (status) status.textContent = 'La demande n’a pas pu être envoyée.';
          btn.disabled = false;
        });
      });
    }
  }

  var linkNotice = notices.querySelector('.ow-session-notice.is-quiet');
  function ownUnitOnMap() {
    var ow = window.OverwatchBeta;
    var user = window.ATAK_USER || {};
    var mine = [user.callsign, user.armaCallsign].map(function (v) { return String(v || '').trim().toUpperCase(); }).filter(Boolean);
    if (!ow || !mine.length || typeof ow.getUnits !== 'function') return false;
    return (ow.getUnits() || []).some(function (unit) {
      return mine.indexOf(String(ow.callsign(unit) || '').trim().toUpperCase()) >= 0;
    });
  }
  function syncLinkNotice() {
    if (linkNotice) linkNotice.hidden = ownUnitOnMap();
  }
  window.addEventListener('atak:units-updated', syncLinkNotice);
  window.setTimeout(syncLinkNotice, 3000);
})();
