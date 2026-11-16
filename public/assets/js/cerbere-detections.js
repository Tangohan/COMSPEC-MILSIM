/*
 * Cerbère-Lecture : fenêtres des propositions (« Pourquoi ? », « Mauvaise détection ») et page « Détections Cerbère »
 * (onglets, fenêtre d'édition d'une règle remplie depuis la ligne, test en direct sans recharger la page).
 * Fenêtres : <dialog> modal, focus gardé dans la fenêtre (Tab / Maj+Tab), Échap et clic sur le fond ferment, le focus revient
 * au bouton d'ouverture. Aucun texte ici : les libellés viennent du HTML.
 */
(function () {
  'use strict';

  function all(sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); }
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

  // —— Fenêtres ——
  var opener = null;
  function openDialog(id, from) {
    var dlg = document.getElementById(id);
    if (!dlg || dlg.tagName !== 'DIALOG') { return null; }
    opener = from || document.activeElement;
    if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    var first = dlg.querySelector('[autofocus]') || dlg.querySelector('input:not([type="hidden"]), textarea, select') || dlg.querySelector(FOCUSABLE);
    if (first) { first.focus(); }
    return dlg;
  }
  function closeDialog(dlg) {
    if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); }
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-cdx-open],[data-cdx-close]') : null;
    if (!t) { return; }
    if (t.hasAttribute('data-cdx-open')) {
      var dlg = openDialog(t.getAttribute('data-cdx-open'), t);
      if (dlg && t.hasAttribute('data-cdx-fill')) { fill(dlg, t.getAttribute('data-cdx-fill')); }
      return;
    }
    var d = t.closest('dialog');
    if (d) { closeDialog(d); }
  });
  all('dialog.cdx-dialog').forEach(function (dlg) {
    dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { closeDialog(dlg); } });
    dlg.addEventListener('close', function () {
      if (opener && typeof opener.focus === 'function' && document.contains(opener)) { opener.focus(); }
      opener = null;
    });
    dlg.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Tab') { return; }
      var items = all(FOCUSABLE, dlg).filter(function (el) { return el.offsetParent !== null || el === document.activeElement; });
      if (items.length === 0) { return; }
      var first = items[0];
      var last = items[items.length - 1];
      if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
      else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
    });
  });

  // —— Fenêtre d'édition remplie depuis la ligne de la règle ——
  function fill(dlg, json) {
    var data;
    try { data = JSON.parse(json || '{}'); } catch (e) { return; }
    Object.keys(data).forEach(function (name) {
      all('[name="' + name + '"]', dlg).forEach(function (field) {
        if (field.type === 'checkbox') { field.checked = !!data[name]; }
        else { field.value = data[name]; }
      });
      all('[data-cdx-out="' + name + '"]', dlg).forEach(function (out) { out.textContent = data[name]; });
    });
  }

  // —— Onglets de la page ——
  var tabs = all('[role="tab"][data-cdx-tab]');
  function select(name, focus) {
    var found = tabs.some(function (t) { return t.getAttribute('data-cdx-tab') === name; });
    if (!found) { return; }
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-cdx-tab') === name;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      if (on && focus) { t.focus(); }
    });
    all('[data-cdx-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-cdx-panel') !== name; });
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () {
      select(t.getAttribute('data-cdx-tab'), false);
      try { window.history.replaceState(null, '', '#' + t.getAttribute('data-cdx-tab')); } catch (e) {}
    });
    t.addEventListener('keydown', function (ev) {
      var to = -1;
      if (ev.key === 'ArrowRight') { to = (i + 1) % tabs.length; }
      else if (ev.key === 'ArrowLeft') { to = (i - 1 + tabs.length) % tabs.length; }
      else if (ev.key === 'Home') { to = 0; }
      else if (ev.key === 'End') { to = tabs.length - 1; }
      if (to < 0) { return; }
      ev.preventDefault();
      select(tabs[to].getAttribute('data-cdx-tab'), true);
    });
  });
  if (tabs.length) {
    var hash = window.location.hash.slice(1);
    if (hash.indexOf('regle-') === 0) { select('regles', false); }
    else if (hash) { select(hash, false); }
  }

  // —— Test en direct : le formulaire marche sans script ; avec, le résultat arrive sans recharger ——
  all('form[data-cdx-live]').forEach(function (form) {
    var out = document.getElementById(form.getAttribute('data-cdx-live'));
    if (!out || !window.fetch || !window.FormData) { return; }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; }
      out.setAttribute('aria-busy', 'true');
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && typeof data.html === 'string') { out.innerHTML = data.html; }
          var heading = out.querySelector('[tabindex="-1"]');
          if (heading) { heading.focus(); }
        })
        .catch(function () { form.submit(); })
        .then(function () { if (btn) { btn.disabled = false; } out.removeAttribute('aria-busy'); });
    });
  });
})();
