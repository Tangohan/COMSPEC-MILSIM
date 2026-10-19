/**
 * Fichiers partagés (/partage, /back-office/partage) : fenêtres, dépôt par glisser-déposer, choix du destinataire
 * d'un partage, confirmations. La page reste utilisable sans JavaScript (formulaires classiques).
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-fs-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.getAttribute('data-fs-open'));
      if (dlg && typeof dlg.showModal === 'function') {
        dlg.showModal();
        var first = dlg.querySelector('input:not([type=hidden]), textarea, select');
        if (first) first.focus();
      }
    });
  });
  document.querySelectorAll('[data-fs-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = btn.closest('dialog');
      if (dlg) dlg.close();
    });
  });
  document.querySelectorAll('dialog.fs-dlg').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg) dlg.close();
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Dépôt : choisir ou glisser des fichiers envoie le formulaire tout de suite.
  document.querySelectorAll('[data-fs-drop]').forEach(function (form) {
    var input = form.querySelector('[data-fs-drop-input]');
    var label = form.querySelector('.fs-drop__label span');
    if (!input) return;
    var send = function () {
      if (!input.files || !input.files.length) return;
      form.classList.add('is-busy');
      if (label) label.textContent = input.files.length === 1 ? 'Envoi de « ' + input.files[0].name + ' »…' : 'Envoi de ' + input.files.length + ' fichiers…';
      form.submit();
    };
    input.addEventListener('change', send);
    ['dragenter', 'dragover'].forEach(function (t) {
      form.addEventListener(t, function () { form.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      form.addEventListener(t, function () { form.classList.remove('is-over'); });
    });
  });

  // Fermer le menu « plus » d'un fichier en cliquant ailleurs.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.fs-more[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  // Formulaire de partage : un champ par type de destinataire, recherche d'un membre par son nom.
  document.querySelectorAll('[data-fs-share-form]').forEach(function (form) {
    var sync = function () {
      var checked = form.querySelector('[data-fs-target]:checked');
      var type = checked ? checked.value : 'user';
      form.querySelectorAll('[data-fs-pick]').forEach(function (p) {
        p.hidden = p.getAttribute('data-fs-pick') !== type;
      });
    };
    form.querySelectorAll('[data-fs-target]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();

    var search = form.querySelector('[data-fs-member-search]');
    var select = form.querySelector('[data-fs-member-select]');
    if (search && select) {
      search.hidden = false;
      search.addEventListener('input', function () {
        var v = search.value.trim().toLowerCase();
        var match = Array.prototype.find.call(select.options, function (o) { return o.value && o.text.toLowerCase() === v; });
        if (match) select.value = match.value;
      });
      select.addEventListener('change', function () {
        var o = select.options[select.selectedIndex];
        search.value = o && o.value ? o.text : '';
      });
    }
    form.addEventListener('submit', function (e) {
      var checked = form.querySelector('[data-fs-target]:checked');
      if (checked && checked.value === 'user' && select && !select.value) {
        e.preventDefault();
        (search || select).focus();
      }
    });
  });
})();
