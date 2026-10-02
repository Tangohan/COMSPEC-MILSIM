/**
 * Autorisation de partage :
 * - « Tout sélectionner » par catégorie (état intermédiaire si sélection partielle) ;
 * - justification affichée et exigée dès qu’une famille sensible est cochée (vérifiée aussi côté serveur) ;
 * - au moins une famille requise, message sous le champ ;
 * - compte à rebours avant de pouvoir renvoyer le code.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-coop-consent]');
  if (form) {
    // Les contrôles ci-dessous remplacent la validation native (message placé sous le champ).
    form.noValidate = true;
    var justifWrap = form.querySelector('[data-consent-justif]');
    var justif = form.querySelector('#justification_sensitive');
    var justifMsg = form.querySelector('#justif-msg');
    var boxes = function () { return Array.prototype.slice.call(form.querySelectorAll('input[type=checkbox][name^="share_"]')); };

    function syncGroups() {
      form.querySelectorAll('[data-consent-group]').forEach(function (g) {
        var all = g.querySelector('[data-consent-all]');
        var items = Array.prototype.slice.call(g.querySelectorAll('input[name^="share_"]'));
        var n = items.filter(function (i) { return i.checked; }).length;
        all.checked = n === items.length;
        all.indeterminate = n > 0 && n < items.length;
      });
      var sensitive = boxes().some(function (b) { return b.checked && b.getAttribute('data-sensitive') === '1'; });
      if (justifWrap) justifWrap.hidden = !sensitive;
      if (justif) justif.required = sensitive;
    }

    form.querySelectorAll('[data-consent-all]').forEach(function (all) {
      all.addEventListener('change', function () {
        var g = all.closest('[data-consent-group]');
        g.querySelectorAll('input[name^="share_"]').forEach(function (i) { i.checked = all.checked; });
        syncGroups();
      });
    });
    form.addEventListener('change', syncGroups);
    syncGroups();

    form.addEventListener('submit', function (e) {
      var old = form.querySelector('.coop-field-error');
      if (old) old.remove();
      if (!boxes().some(function (b) { return b.checked; })) {
        e.preventDefault();
        var msg = document.createElement('p');
        msg.className = 'fr-message fr-message--error coop-field-error';
        msg.setAttribute('role', 'alert');
        msg.textContent = 'Cochez au moins une famille de données.';
        form.querySelector('[data-consent-group]').insertAdjacentElement('beforebegin', msg);
        return;
      }
      if (justif && justif.required && justif.value.trim().length < 10) {
        e.preventDefault();
        justif.classList.add('fr-input--error');
        justif.setAttribute('aria-invalid', 'true');
        if (justifMsg) {
          justifMsg.classList.add('fr-message--error');
          justifMsg.textContent = 'Justifiez le partage des données sensibles (10 caractères au moins).';
        }
        justif.focus();
      }
    });
  }

  var resend = document.querySelector('[data-consent-resend]');
  if (resend) {
    var btn = resend.querySelector('[data-resend-btn]');
    var count = resend.querySelector('[data-resend-count]');
    var wait = parseInt(resend.getAttribute('data-wait') || '0', 10);
    var tick = function () {
      if (wait <= 0) {
        btn.disabled = false;
        if (count) count.textContent = '';
        return;
      }
      btn.disabled = true;
      if (count) count.textContent = ' (possible dans ' + wait + ' s)';
      wait--;
      setTimeout(tick, 1000);
    };
    tick();
  }

  var otp = document.getElementById('otp_code');
  if (otp) {
    otp.addEventListener('input', function () { otp.value = otp.value.replace(/\D/g, '').slice(0, 6); });
  }
})();
