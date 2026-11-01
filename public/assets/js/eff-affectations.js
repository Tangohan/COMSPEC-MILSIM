/**
 * Effectifs — page Affectations : recherche et filtres instantanés, vue par membre ou par unité.
 * Le filtre « unité » garde aussi les sous-unités (leur code commence par celui de l'unité).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-eff-aff]');
  if (!root) return;
  var form = root.querySelector('[data-eff-aff-filters]');
  var q = root.querySelector('[data-eff-aff-q]');
  var unit = root.querySelector('[data-eff-aff-unit]');
  var job = root.querySelector('[data-eff-aff-job]');
  var role = root.querySelector('[data-eff-aff-role]');
  var access = root.querySelector('[data-eff-aff-access]');
  var gaps = root.querySelectorAll('[data-eff-aff-gap]');
  var count = root.querySelector('[data-eff-aff-count]');
  var rows = Array.prototype.slice.call(root.querySelectorAll('[data-eff-aff-row]'));
  var unitRows = Array.prototype.slice.call(root.querySelectorAll('[data-eff-aff-unit-row]'));
  var noneMembers = root.querySelector('[data-eff-aff-none]');
  var noneUnits = root.querySelector('[data-eff-aff-none-units]');
  var view = (root.querySelector('[data-eff-aff-view].is-on') || {}).getAttribute
    ? root.querySelector('[data-eff-aff-view].is-on').getAttribute('data-eff-aff-view')
    : 'members';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function inUnit(code, base) {
    return !base || code === base || code.indexOf(base + '-') === 0;
  }

  function apply() {
    var terms = norm(q ? q.value : '').split(/\s+/).filter(Boolean);
    var base = unit ? unit.value : '';
    var j = job ? job.value : '';
    var r = role ? role.value : '';
    var a = access ? access.value : '';
    var needUnit = false;
    var needJob = false;
    gaps.forEach(function (g) {
      if (g.checked && g.value === 'unit') needUnit = true;
      if (g.checked && g.value === 'job') needJob = true;
    });
    if (unit) unit.classList.toggle('is-set', base !== '');

    var shown = 0;
    rows.forEach(function (row) {
      var hay = norm(row.getAttribute('data-search'));
      var code = row.getAttribute('data-code') || '';
      var jobs = row.getAttribute('data-jobs') || '||';
      var ok = terms.every(function (t) { return hay.indexOf(t) !== -1; })
        && inUnit(code, base)
        && (!j || jobs.indexOf('|' + j + '|') !== -1)
        && (!r || row.getAttribute('data-role') === r)
        && (!a || (row.getAttribute('data-access') || '').indexOf('|' + a + '|') !== -1)
        && (!needUnit || code === '')
        && (!needJob || jobs === '||');
      row.hidden = !ok;
      if (ok) shown++;
    });
    var shownUnits = 0;
    unitRows.forEach(function (row) {
      var hay = norm(row.getAttribute('data-search'));
      var ok = terms.every(function (t) { return hay.indexOf(t) !== -1; }) && inUnit(row.getAttribute('data-code') || '', base);
      row.hidden = !ok;
      if (ok) shownUnits++;
    });
    if (noneMembers) noneMembers.hidden = shown > 0 || rows.length === 0;
    if (noneUnits) noneUnits.hidden = shownUnits > 0 || unitRows.length === 0;
    if (count) {
      count.innerHTML = view === 'units'
        ? '<b>' + shownUnits + '</b> unité' + (shownUnits > 1 ? 's' : '') + ' sur ' + unitRows.length
        : '<b>' + shown + '</b> membre' + (shown > 1 ? 's' : '') + ' sur ' + rows.length;
    }
  }

  function setView(next) {
    view = next;
    root.querySelectorAll('[data-eff-aff-view]').forEach(function (b) {
      var on = b.getAttribute('data-eff-aff-view') === next;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-eff-aff-pane]').forEach(function (pane) {
      pane.hidden = pane.getAttribute('data-eff-aff-pane') !== next;
    });
    try {
      var url = new URL(window.location.href);
      if (next === 'units') url.searchParams.set('vue', 'unites'); else url.searchParams.delete('vue');
      window.history.replaceState(null, '', url.toString());
    } catch (e) { /* adresse inchangée */ }
    apply();
  }

  if (form) {
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    form.addEventListener('input', apply);
    form.addEventListener('change', apply);
  }
  // Replier une branche de l'organigramme : ses sous-unités (code qui commence par le sien) disparaissent.
  var folded = {};
  function applyFolds() {
    var codes = Object.keys(folded);
    unitRows.forEach(function (row) {
      var code = row.getAttribute('data-code') || '';
      var hiddenByFold = codes.some(function (c) { return code.indexOf(c + '-') === 0; });
      row.classList.toggle('is-folded', hiddenByFold);
    });
  }
  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-eff-ut-fold]');
    if (!btn) return;
    var code = btn.getAttribute('data-eff-ut-fold');
    if (!code) return;
    var open = btn.getAttribute('aria-expanded') !== 'true';
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('title', open ? 'Replier les sous-unités' : 'Déplier les sous-unités');
    if (open) delete folded[code]; else folded[code] = true;
    applyFolds();
  });

  root.querySelectorAll('[data-eff-aff-view]').forEach(function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-eff-aff-view')); });
  });
  apply();
  var target = root.querySelector('.eff-ut__unit.is-target, .eff-aff-unit.is-target');
  if (target) target.scrollIntoView({ block: 'center' });
})();
