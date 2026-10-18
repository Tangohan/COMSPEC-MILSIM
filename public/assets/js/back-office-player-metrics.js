/**
 * Back-office — Métriques joueurs : recherche dans le tableau des membres, tri appliqué dès le choix.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-pm-table]');
  if (!root) {
    return;
  }
  var q = root.querySelector('[data-pm-q]');
  var count = root.querySelector('[data-pm-count]');
  var none = root.querySelector('[data-pm-none]');
  var rows = Array.prototype.slice.call(root.querySelectorAll('tr[data-pm-name]'));
  var sort = root.querySelector('[data-pm-sort]');

  function apply() {
    var needle = (q && q.value ? q.value : '').trim().toLowerCase();
    var shown = 0;
    rows.forEach(function (tr) {
      var ok = needle === '' || (tr.getAttribute('data-pm-name') || '').indexOf(needle) !== -1;
      tr.hidden = !ok;
      if (ok) {
        shown++;
      }
    });
    if (count) {
      count.textContent = shown + ' membre(s)';
    }
    if (none) {
      none.hidden = shown !== 0;
    }
  }

  if (q) {
    q.addEventListener('input', apply);
  }
  if (sort && sort.form) {
    sort.addEventListener('change', function () {
      sort.form.submit();
    });
  }
})();
