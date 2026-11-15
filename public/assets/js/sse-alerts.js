/*
 * Bureau SSE — signalements d'alertes : page de signalement (compteur de caractères, « Maintenant », photo, envoi), panneau et liste
 * (copie du lien, fenêtres de traitement, de décision et de révocation). Aucun texte ici : les libellés viennent du HTML.
 */
(function () {
  'use strict';

  function each(sel, fn, scope) { Array.prototype.forEach.call((scope || document).querySelectorAll(sel), fn); }
  function msg(root, key) {
    var el = root.querySelector('[data-sal-msg="' + key + '"]') || document.querySelector('[data-sal-msg="' + key + '"]');
    return el ? el.textContent.trim() : '';
  }

  // —— Copie du lien ou du numéro ——
  function copy(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) { return navigator.clipboard.writeText(text); }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      if (ok) { resolve(); } else { reject(); }
    });
  }
  each('[data-sal-copy]', function (btn) {
    btn.addEventListener('click', function () {
      var root = btn.closest('[data-sal]') || document.body;
      var live = root.querySelector('[data-sal-live]');
      copy(btn.getAttribute('data-sal-copy')).then(function () {
        if (live) { live.textContent = msg(root, 'copied'); live.classList.remove('is-error'); }
      }, function () {
        if (live) { live.textContent = msg(root, 'failed'); live.classList.add('is-error'); }
      });
    });
  });

  // —— Fenêtres ——
  function syncNote(dlg) {
    var note = dlg.querySelector('[data-sal-note]');
    var need = dlg.querySelector('[data-sal-note-need]');
    if (!note) { return; }
    var checked = dlg.querySelector('[data-sal-status-radio]:checked');
    var req = !!checked && checked.value === 'dismissed';
    note.required = req;
    if (need) { need.hidden = !req; }
  }
  each('[data-sal-open]', function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.getAttribute('data-sal-open'));
      if (!dlg) { return; }
      var form = dlg.querySelector('[data-sal-target-form]');
      var action = btn.getAttribute('data-sal-action');
      if (form && action) { form.setAttribute('action', action); }
      var out = dlg.querySelector('[data-sal-subject-out]');
      if (out) { out.textContent = btn.getAttribute('data-sal-subject') || ''; }
      var status = btn.getAttribute('data-sal-status');
      if (status) {
        each('[data-sal-status-radio]', function (r) { r.checked = r.value === status; }, dlg);
        syncNote(dlg);
      }
      var unknown = dlg.querySelector('[data-sal-unknown-field]');
      if (unknown) {
        var is = btn.getAttribute('data-sal-unknown') === '1';
        unknown.hidden = !is;
        var input = unknown.querySelector('input');
        if (input) { input.required = is; }
      }
      if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    });
  });
  each('dialog.sal-dialog', function (dlg) {
    each('[data-sal-close]', function (b) {
      b.addEventListener('click', function () { if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); } });
    }, dlg);
    dlg.addEventListener('click', function (ev) { if (ev.target === dlg && typeof dlg.close === 'function') { dlg.close(); } });
    each('[data-sal-status-radio]', function (r) { r.addEventListener('change', function () { syncNote(dlg); }); }, dlg);
  });

  // —— Page de signalement ——
  var form = document.querySelector('[data-sal-form]');
  if (form) {
    var area = form.querySelector('[data-sal-text]');
    var count = form.querySelector('[data-sal-count]');
    var max = parseInt(form.getAttribute('data-sal-max'), 10) || 600;
    function recount() { if (area && count) { count.textContent = area.value.length + ' / ' + max; } }
    if (area) { area.addEventListener('input', recount); recount(); }

    var photo = form.querySelector('#sal-photo');
    var photoName = form.querySelector('[data-sal-photo-name]');
    var photoErr = form.querySelector('[data-sal-photo-error]');
    var photoMax = parseInt(form.getAttribute('data-sal-photo-max'), 10) || 4194304;
    var photoDefault = photoName ? photoName.textContent : '';
    if (photo) {
      photo.addEventListener('change', function () {
        var f = photo.files && photo.files[0];
        if (photoErr) { photoErr.hidden = true; }
        if (f && f.size > photoMax) {
          photo.value = '';
          if (photoName) { photoName.textContent = photoDefault; }
          if (photoErr) { photoErr.hidden = false; }
          return;
        }
        if (photoName) { photoName.textContent = f ? f.name : photoDefault; }
        var drop = form.querySelector('[data-sal-drop]');
        if (drop) { drop.classList.toggle('has-file', !!f); }
      });
    }

    var submit = form.querySelector('[data-sal-submit]');
    form.addEventListener('submit', function () {
      // Un seul envoi à la fois : le bouton se verrouille après la validation du navigateur.
      if (submit) { window.setTimeout(function () { submit.disabled = true; submit.classList.add('is-busy'); }, 0); }
    });
    var errors = form.querySelector('[data-sal-errors]');
    if (errors) { errors.focus(); }
  }

  // —— Volets de la colonne d'information : fermés sur téléphone ——
  var folds = document.querySelectorAll('[data-sal-fold]');
  if (folds.length && window.matchMedia && window.matchMedia('(max-width: 899px)').matches) {
    Array.prototype.forEach.call(folds, function (d) { d.removeAttribute('open'); });
  }
  each('[data-sal-brief]', function (a) {
    a.addEventListener('click', function () {
      var first = document.querySelector('[data-sal-fold]');
      if (first) { first.setAttribute('open', ''); }
    });
  });
})();
