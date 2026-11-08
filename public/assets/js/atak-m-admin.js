/*
 * Espace d'administration du téléphone (/m/admin) : formulaires envoyés en POST vers /m/admin/action avec le jeton CSRF.
 * Le site relit les droits du compte à chaque action et la journalise ; ce script ne décide de rien.
 */
(function () {
  'use strict';
  var BASE = (document.body.getAttribute('data-base') || '').replace(/\/$/, '');
  var meta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = meta ? meta.getAttribute('content') : '';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function toast(msg) {
    var t = document.getElementById('mm-toast');
    if (!t) return;
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { t.hidden = true; }, 3500);
  }
  function post(path, params) {
    var data = params instanceof URLSearchParams ? params : new URLSearchParams(params || {});
    data.set('_csrf_token', CSRF);
    return fetch(BASE + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' },
      body: data
    }).then(function (r) {
      return r.json().then(function (j) { j._status = r.status; return j; });
    });
  }

  // Choix en gros boutons (un seul allumé, valeur dans le champ caché du groupe).
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.mm-choice');
    if (!b) return;
    var group = b.closest('.mm-choices');
    if (!group) return;
    $all('.mm-choice', group).forEach(function (x) { x.classList.remove('is-on'); });
    b.classList.add('is-on');
    var input = $('input[type="hidden"]', group);
    if (input) input.value = b.getAttribute('data-choice');
  });

  // Une seule action ouverte à la fois (repli pour les navigateurs sans <details name>).
  $all('details.mm-acc').forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) return;
      $all('details.mm-acc').forEach(function (o) { if (o !== d) o.open = false; });
    });
  });

  $all('form[data-admin-form]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var ask = form.getAttribute('data-confirm');
      if (ask && !window.confirm(ask)) return;
      var btn = $('button[type="submit"]', form);
      var out = $('[data-admin-result]', form);
      var data = new URLSearchParams(new FormData(form));
      data.set('action', form.getAttribute('data-admin-form'));
      if (btn) btn.disabled = true;
      if (out) out.textContent = '';
      post('/m/admin/action', data).then(function (j) {
        var msg = (j && j.message) || (j && j.ok ? 'Fait.' : 'Action impossible, réessayez.');
        if (out) out.textContent = msg;
        toast(msg);
        if (j && j.ok) {
          if (form.hasAttribute('data-reset')) form.reset();
          if (form.hasAttribute('data-reload')) setTimeout(function () { window.location.reload(); }, 900);
        }
        if (j && j._status === 401) setTimeout(function () { window.location.reload(); }, 1500);
      }).catch(function () {
        toast('Action impossible : vérifiez la connexion du téléphone.');
      }).then(function () {
        if (btn) btn.disabled = false;
      });
    });
  });

  var logout = $('[data-admin-logout]');
  if (logout) {
    logout.addEventListener('click', function () {
      if (!window.confirm('Fermer l’espace d’administration ? Il faudra scanner un nouveau QR code dans le jeu.')) return;
      post('/m/admin/quitter').then(function () { window.location.reload(); });
    });
  }
})();
