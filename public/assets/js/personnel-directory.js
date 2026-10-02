/**
 * Annuaire du personnel — filtrage instantané, filtre par unité, tri, bascule fiches/liste.
 * Amélioration progressive : sans JS, la recherche serveur (GET ?q=) reste fonctionnelle.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-pd-root]');
  var list = root && root.querySelector('[data-pd-list]');
  if (!list) return;

  var cards = Array.prototype.slice.call(list.querySelectorAll('.pd-card'));
  var input = root.querySelector('[data-pd-filter]');
  var sortSel = root.querySelector('[data-pd-sort]');
  var chips = Array.prototype.slice.call(root.querySelectorAll('[data-pd-unit]'));
  var viewBtns = Array.prototype.slice.call(root.querySelectorAll('[data-pd-view]'));
  var countEl = root.querySelector('[data-pd-count]');
  var emptyEl = list.querySelector('[data-pd-empty]');
  var mirror = root.querySelector('[data-pd-mirror]');
  var STORE_KEY = 'athena.personnelDirectory.v1';

  var state = { q: '', unit: '', sort: 'grade', view: 'grid' };

  function load() {
    try {
      var s = JSON.parse(window.localStorage.getItem(STORE_KEY) || '{}');
      if (s.view === 'list' || s.view === 'grid') state.view = s.view;
      if (['grade', 'name', 'seniority', 'unit'].indexOf(s.sort) !== -1) state.sort = s.sort;
    } catch (e) { /* stockage indisponible */ }
  }
  function save() {
    try { window.localStorage.setItem(STORE_KEY, JSON.stringify({ view: state.view, sort: state.sort })); } catch (e) { /* noop */ }
  }

  function normalize(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
  }
  cards.forEach(function (c) { c._pdSearch = normalize(c.getAttribute('data-search')); });

  var comparators = {
    name: function (a, b) { return a.dataset.name.localeCompare(b.dataset.name, 'fr'); },
    grade: function (a, b) {
      return (+a.dataset.grade - +b.dataset.grade) || comparators.name(a, b);
    },
    seniority: function (a, b) { return (+b.dataset.days - +a.dataset.days) || comparators.name(a, b); },
    unit: function (a, b) {
      var ua = a.dataset.unit, ub = b.dataset.unit;
      if (ua === '__none' && ub !== '__none') return 1;
      if (ub === '__none' && ua !== '__none') return -1;
      return ua.localeCompare(ub, 'fr') || comparators.grade(a, b);
    }
  };

  function apply() {
    var terms = normalize(state.q).split(/\s+/).filter(Boolean);
    var shown = 0;

    cards.slice().sort(comparators[state.sort]).forEach(function (c) {
      list.insertBefore(c, emptyEl);
      var okUnit = !state.unit || c.dataset.unit === state.unit;
      var okText = terms.every(function (t) { return c._pdSearch.indexOf(t) !== -1; });
      var visible = okUnit && okText;
      c.hidden = !visible;
      if (visible) shown++;
    });

    if (countEl) countEl.textContent = shown + ' profil' + (shown > 1 ? 's' : '') + (shown !== cards.length ? ' sur ' + cards.length : '');
    if (emptyEl) emptyEl.hidden = shown !== 0;
    if (mirror) mirror.value = state.q;

    list.setAttribute('data-view', state.view);
    viewBtns.forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.pdView === state.view)); });
    chips.forEach(function (ch) { ch.classList.toggle('is-active', ch.dataset.pdUnit === state.unit); });
    if (sortSel) sortSel.value = state.sort;
  }

  if (input) {
    // La valeur serveur (?q=) a déjà filtré la liste : on ne la réapplique pas côté client.
    input.addEventListener('input', function () { state.q = input.value; apply(); });
  }
  chips.forEach(function (ch) {
    ch.addEventListener('click', function () { state.unit = ch.dataset.pdUnit; apply(); });
  });
  if (sortSel) sortSel.addEventListener('change', function () { state.sort = sortSel.value; save(); apply(); });
  viewBtns.forEach(function (b) {
    b.addEventListener('click', function () { state.view = b.dataset.pdView; save(); apply(); });
  });

  // Raccourci « / » pour focaliser la recherche
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    var t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    if (!input) return;
    e.preventDefault();
    input.focus();
    input.select();
  });

  load();
  apply();
})();
