/*
 * Bureau SSE — lecture d'une fiche de renseignement : onglets de la colonne d'information, fenêtres des formulaires,
 * copie (référence, lien, position), menu des autres actions, impression. Aucun texte ici : les libellés viennent du HTML.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-fnr]');
  if (!root) { return; }

  function each(sel, fn, scope) { Array.prototype.forEach.call((scope || root).querySelectorAll(sel), fn); }
  function notify(kind, text) {
    if (text && window.SseNotify && typeof window.SseNotify[kind] === 'function') { window.SseNotify[kind](text); }
  }

  // —— Onglets ——
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"][data-fnr-tab]'));
  function select(name, focus) {
    var found = false;
    tabs.forEach(function (t) { if (t.getAttribute('data-fnr-tab') === name) { found = true; } });
    if (!found) { return false; }
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-fnr-tab') === name;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      if (on && focus) { t.focus(); }
      if (on && t.scrollIntoView && t.parentNode.scrollWidth > t.parentNode.clientWidth) {
        t.parentNode.scrollLeft = Math.max(0, t.offsetLeft - 24);
      }
    });
    each('[data-fnr-panel]', function (p) { p.hidden = p.getAttribute('data-fnr-panel') !== name; });
    return true;
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () {
      var name = t.getAttribute('data-fnr-tab');
      select(name, false);
      try { window.history.replaceState(null, '', '#' + name); } catch (e) {}
    });
    t.addEventListener('keydown', function (ev) {
      var to = -1;
      if (ev.key === 'ArrowRight') { to = (i + 1) % tabs.length; }
      else if (ev.key === 'ArrowLeft') { to = (i - 1 + tabs.length) % tabs.length; }
      else if (ev.key === 'Home') { to = 0; }
      else if (ev.key === 'End') { to = tabs.length - 1; }
      if (to < 0) { return; }
      ev.preventDefault();
      select(tabs[to].getAttribute('data-fnr-tab'), true);
    });
  });
  if (window.location.hash.length > 1) { select(window.location.hash.slice(1), false); }

  // —— Fenêtres ——
  function closeMenu() { each('[data-fnr-more][open]', function (d) { d.open = false; }); }
  each('[data-fnr-open]', function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.getAttribute('data-fnr-open'));
      if (!dlg) { return; }
      closeMenu();
      var action = btn.getAttribute('data-fnr-action');
      if (action) {
        var form = dlg.querySelector('[data-fnr-target-form]');
        if (form) { form.setAttribute('action', action); }
        var out = dlg.querySelector('[data-fnr-subject-out]');
        if (out) { out.textContent = btn.getAttribute('data-fnr-subject') || ''; }
      }
      if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    });
  });
  each('dialog.fnr-dialog', function (dlg) {
    each('[data-fnr-close]', function (b) {
      b.addEventListener('click', function () { if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); } });
    }, dlg);
    // Un clic sur le fond ferme la fenêtre ; un clic dans la carte ne la ferme pas.
    dlg.addEventListener('click', function (ev) { if (ev.target === dlg && typeof dlg.close === 'function') { dlg.close(); } });
  });
  each('form[data-fnr-files-max]', function (form) {
    form.addEventListener('submit', function (ev) {
      var input = form.querySelector('input[type="file"]');
      var max = parseInt(form.getAttribute('data-fnr-files-max'), 10) || 0;
      if (input && input.files && max > 0 && input.files.length > max) {
        ev.preventDefault();
        var dlg = form.closest('dialog');
        if (dlg && typeof dlg.close === 'function') { dlg.close(); }
        notify('error', form.getAttribute('data-fnr-files-message'));
      }
    });
  });

  // —— Copie ——
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
  each('[data-fnr-copy]', function (btn) {
    btn.addEventListener('click', function () {
      closeMenu();
      copy(btn.getAttribute('data-fnr-copy')).then(function () {
        notify('success', root.getAttribute('data-fnr-copied'));
      }, function () {
        notify('error', root.getAttribute('data-fnr-copy-failed'));
      });
    });
  });

  // —— Menu des autres actions, impression ——
  document.addEventListener('click', function (ev) {
    each('[data-fnr-more][open]', function (d) { if (!d.contains(ev.target)) { d.open = false; } });
  });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { closeMenu(); } });
  each('[data-fnr-print]', function (btn) { btn.addEventListener('click', function () { window.print(); }); });
})();
