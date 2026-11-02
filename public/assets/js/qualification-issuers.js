/**
 * Référentiels > Qualifications > Émetteurs : recherche et filtres de la liste en cartes, ouverture du
 * formulaire replié visé par un lien (#nouvel-emetteur, #modifier). Aucun texte affiché : rien à traduire ici.
 */
(function () {
  'use strict';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function openFold(id) {
    var el = id ? document.getElementById(id) : null;
    if (!el || el.tagName !== 'DETAILS') return;
    el.open = true;
    var first = el.querySelector('input:not([type=hidden]), select, textarea');
    if (first) first.focus({ preventScroll: true });
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[href^="#"]');
    if (!a) return;
    var id = a.getAttribute('href').slice(1);
    var el = id ? document.getElementById(id) : null;
    if (el && el.tagName === 'DETAILS') {
      ev.preventDefault();
      openFold(id);
      if (history.replaceState) history.replaceState(null, '', '#' + id);
    }
  });

  if (location.hash) openFold(location.hash.slice(1));

  // Émetteurs réels : « Tout cocher » d'un pays (les émetteurs déjà présents restent cochés et désactivés).
  document.addEventListener('change', function (ev) {
    var all = ev.target.closest('[data-qis-real-all]');
    if (!all) return;
    var group = all.closest('[data-qis-real-group]');
    if (!group) return;
    group.querySelectorAll('input[name="keys[]"]:not(:disabled)').forEach(function (cb) { cb.checked = all.checked; });
  });

  var root = document.querySelector('[data-qis]');
  if (!root) return;
  var q = root.querySelector('[data-qis-q]');
  var kind = root.querySelector('[data-qis-kind]');
  var status = root.querySelector('[data-qis-status]');
  var count = root.querySelector('[data-qis-count]');
  var none = root.querySelector('[data-qis-none]');
  var rows = Array.prototype.slice.call(root.querySelectorAll('[data-qis-row]'));

  function apply() {
    var term = norm(q ? q.value.trim() : '');
    var k = kind ? kind.value : '';
    var s = status ? status.value : 'active';
    var shown = 0;
    rows.forEach(function (r) {
      var ok = (!term || norm(r.getAttribute('data-search')).indexOf(term) !== -1)
        && (!k || r.getAttribute('data-kind') === k)
        && (!s || r.getAttribute('data-status') === s);
      r.hidden = !ok;
      if (ok) shown++;
    });
    if (count) count.textContent = String(shown);
    if (none) none.hidden = shown > 0;
    [kind, status].forEach(function (sel) {
      if (sel) sel.classList.toggle('is-set', sel === status ? sel.value !== 'active' : sel.value !== '');
    });
  }

  [q, kind, status].forEach(function (el) {
    if (el) el.addEventListener(el === q ? 'input' : 'change', apply);
  });
})();
