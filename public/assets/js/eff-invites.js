/**
 * Effectifs › liens « compléter son profil » (partials/invite_rows.php, member_invites.php, invitations.php).
 * Chaque bouton est un formulaire : ici il part sans recharger, le résultat s'affiche, « Copier le lien »
 * met l'adresse dans le presse-papiers (elle n'est jamais écrite dans la page).
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-eff-invites]').forEach(function (root) {
    if (root.getAttribute('data-inv-ready') === '1') {
      return;
    }
    root.setAttribute('data-inv-ready', '1');
    var status = root.querySelector('[data-inv-status]');

    function say(ok, text, url) {
      if (!status) {
        return;
      }
      status.className = 'eff-inv-status ' + (ok ? 'is-ok' : 'is-bad');
      status.textContent = text;
      if (url) {
        var field = document.createElement('input');
        field.type = 'text';
        field.readOnly = true;
        field.value = url;
        field.className = 'eff-inv-status__url';
        field.setAttribute('aria-label', 'Lien d’invitation');
        status.appendChild(document.createElement('br'));
        status.appendChild(field);
        field.addEventListener('focus', function () { field.select(); });
      }
    }

    function copy(text) {
      if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return false; });
      }
      return Promise.resolve(false);
    }

    root.querySelectorAll('form[data-inv-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.fetch) {
          return;
        }
        var ask = form.getAttribute('data-inv-confirm');
        if (ask && !window.confirm(ask)) {
          e.preventDefault();
          return;
        }
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; }
        fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Réponse inattendue. Rechargez la page.' }; }); })
          .then(function (data) {
            if (btn) { btn.disabled = false; }
            var ok = !!(data && data.ok);
            var msg = (data && data.message) || (ok ? 'C’est fait.' : 'Action impossible.');
            if (ok && data.url && (form.hasAttribute('data-inv-copy') || form.hasAttribute('data-inv-reload'))) {
              copy(data.url).then(function (copied) {
                if (form.hasAttribute('data-inv-reload')) {
                  try { window.sessionStorage.setItem('eff-inv-last', JSON.stringify({ url: data.url, copied: copied, msg: msg })); } catch (err) { /* stockage indisponible */ }
                  window.location.reload();
                  return;
                }
                say(true, copied ? 'Lien copié : collez-le où vous voulez (message privé, salon…).' : 'Copiez le lien ci-dessous :', copied ? '' : data.url);
              });
              return;
            }
            if (ok && form.hasAttribute('data-inv-reload')) {
              try { window.sessionStorage.setItem('eff-inv-last', JSON.stringify({ msg: msg })); } catch (err) { /* stockage indisponible */ }
              window.location.reload();
              return;
            }
            say(ok, msg, '');
          })
          .catch(function () {
            if (btn) { btn.disabled = false; }
            say(false, 'Connexion perdue. Réessayez.', '');
          });
      });
    });

    // Après création ou régénération : le nouveau lien reste visible une fois, s'il n'a pas pu être copié.
    try {
      var last = JSON.parse(window.sessionStorage.getItem('eff-inv-last') || 'null');
      window.sessionStorage.removeItem('eff-inv-last');
      if (last && last.msg) {
        if (last.url && !last.copied) {
          say(true, last.msg + ' Copiez le lien ci-dessous :', last.url);
        } else {
          say(true, last.msg + (last.url ? ' Le lien est dans votre presse-papiers.' : ''), '');
        }
      }
    } catch (err) { /* stockage indisponible */ }
  });
})();
