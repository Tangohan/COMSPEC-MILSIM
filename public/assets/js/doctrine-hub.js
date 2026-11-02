/**
 * Back-office > Doctrine et SOP (views/admin/organization/doctrine.php) : filtres de la liste (rail des types et de la
 * nomenclature, état, destinataires, échéance, lecture, recherche), volet de détail, fenêtre de relance groupée et
 * confirmation des actions (attribut data-confirm, traduit à l’affichage). Sans JavaScript, le détail reste lisible
 * sous chaque ligne.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-dct]');
  if (!root) return;

  var docs = Array.prototype.slice.call(root.querySelectorAll('[data-dct-doc]'));
  var q = root.querySelector('[data-dct-q]');
  var selects = Array.prototype.slice.call(root.querySelectorAll('[data-dct-f]'));
  var rails = Array.prototype.slice.call(root.querySelectorAll('[data-dct-rail]'));
  var stats = Array.prototype.slice.call(root.querySelectorAll('[data-dct-stat]'));
  var countEl = root.querySelector('[data-dct-count]');
  var noMatch = root.querySelector('[data-dct-nomatch]');
  var rail = '';

  var STATE_GROUPS = {
    '': function (s) { return s !== 'archived' && s !== 'obsolete'; },
    all: function () { return true; },
    live: function (s) { return s === 'published' || s === 'expired'; },
    draft: function (s) { return s === 'draft'; },
    attention: function (s) { return s === 'review' || s === 'expired' || s === 'suspended'; },
    archived: function (s) { return s === 'archived' || s === 'obsolete'; }
  };

  function val(name) {
    for (var i = 0; i < selects.length; i++) {
      if (selects[i].getAttribute('data-dct-f') === name) return selects[i].value;
    }
    return '';
  }

  function apply() {
    var term = q ? q.value.trim().toLowerCase() : '';
    var state = val('state');
    var unit = val('unit');
    var deadline = val('deadline');
    var read = val('read');
    var stateOk = STATE_GROUPS[state] || STATE_GROUPS[''];
    var shown = 0;
    docs.forEach(function (el) {
      var ok = stateOk(el.getAttribute('data-state'));
      if (ok && rail) {
        var parts = rail.split(':');
        ok = el.getAttribute('data-' + parts[0]) === parts[1];
      }
      if (ok && unit) {
        ok = unit === 'all'
          ? el.getAttribute('data-all') === '1'
          : (' ' + el.getAttribute('data-units') + ' ').indexOf(' ' + unit + ' ') !== -1;
      }
      if (ok && deadline) ok = el.getAttribute('data-deadline') === deadline;
      if (ok && read) ok = el.getAttribute('data-read') === read;
      if (ok && term) ok = (el.getAttribute('data-q') || '').indexOf(term) !== -1;
      el.hidden = !ok;
      if (ok) shown++;
    });
    if (countEl) countEl.textContent = String(shown);
    if (noMatch) noMatch.hidden = shown > 0 || docs.length === 0;
    selects.forEach(function (s) { s.classList.toggle('is-set', s.value !== ''); });
    stats.forEach(function (b) {
      var on = b.getAttribute('data-dct-stat') === state;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  rails.forEach(function (b) {
    b.addEventListener('click', function () {
      rail = b.getAttribute('data-dct-rail') || '';
      rails.forEach(function (o) {
        var on = o === b;
        o.classList.toggle('is-on', on);
        o.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  });
  stats.forEach(function (b) {
    b.addEventListener('click', function () {
      var key = b.getAttribute('data-dct-stat');
      var sel = root.querySelector('[data-dct-f="state"]');
      if (sel) sel.value = sel.value === key ? '' : key;
      apply();
    });
  });
  selects.forEach(function (s) { s.addEventListener('change', apply); });
  if (q) q.addEventListener('input', apply);
  var reset = root.querySelector('[data-dct-reset]');
  if (reset) {
    reset.addEventListener('click', function () {
      if (q) q.value = '';
      selects.forEach(function (s) { s.value = ''; });
      var all = root.querySelector('[data-dct-rail=""]');
      if (all) all.click(); else apply();
      apply();
    });
  }

  /* Volet de détail */
  var drawer = root.querySelector('[data-dct-drawer]');
  var body = root.querySelector('[data-dct-drawer-body]');
  var current = null;
  var opener = null;

  function closeDrawer() {
    if (!drawer || !drawer.open) return;
    drawer.classList.remove('is-on');
    drawer.close();
  }

  function openDoc(el, from) {
    var detail = el.querySelector('[data-dct-detail]');
    if (!drawer || !body || !detail || typeof drawer.showModal !== 'function') return;
    if (current) current.classList.remove('is-act');
    current = el;
    opener = from || null;
    el.classList.add('is-act');
    body.innerHTML = detail.innerHTML;
    if (!drawer.open) drawer.showModal();
    requestAnimationFrame(function () { drawer.classList.add('is-on'); });
    var x = body.querySelector('[data-dct-close]');
    if (x) x.focus();
    if (history.replaceState) history.replaceState(null, '', '#' + el.id);
  }

  if (drawer) {
    drawer.addEventListener('close', function () {
      drawer.classList.remove('is-on');
      if (current) current.classList.remove('is-act');
      current = null;
      if (history.replaceState && /^#doc-/.test(location.hash)) history.replaceState(null, '', location.pathname + location.search);
      if (opener && document.contains(opener)) opener.focus();
    });
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer || e.target.closest('[data-dct-close]')) closeDrawer();
    });
  }

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-dct-open]');
    if (btn) {
      var row = btn.closest('[data-dct-doc]');
      if (row) openDoc(row, btn);
      return;
    }
    var row2 = e.target.closest('[data-dct-doc]');
    if (row2 && !e.target.closest('a, button, form, [data-dct-detail]')) openDoc(row2, row2.querySelector('[data-dct-open]'));
  });

  /* Fenêtre de relance groupée */
  var md = root.querySelector('[data-dct-remind]');
  root.querySelectorAll('[data-dct-remind-all]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (md && typeof md.showModal === 'function') md.showModal();
    });
  });
  if (md) {
    md.addEventListener('click', function (e) {
      if (e.target === md || e.target.closest('[data-dct-md-close]')) md.close();
    });
  }

  /* Confirmation des actions (formulaires du volet compris : délégation sur le document) */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.hasAttribute('data-confirm') || !f.closest('[data-dct]')) return;
    if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  });

  apply();

  var hash = location.hash;
  if (/^#doc-\d+$/.test(hash)) {
    var target = document.getElementById(hash.slice(1));
    if (target && target.hasAttribute('data-dct-doc')) {
      if (target.hidden) {
        var sel = root.querySelector('[data-dct-f="state"]');
        if (sel) { sel.value = 'all'; apply(); }
      }
      openDoc(target, target.querySelector('[data-dct-open]'));
    }
  } else if (hash === '#sop') {
    var sop = document.getElementById('sop');
    if (sop) sop.open = true;
  }
})();
