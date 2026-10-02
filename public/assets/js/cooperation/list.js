/**
 * Coopérations — filtres par état (tags) et recherche instantanée sur la liste.
 * Sans JavaScript, la liste complète reste affichée.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-coop-list]');
  if (!root) return;
  var rows = Array.prototype.slice.call(root.querySelectorAll('.coop-row'));
  var tags = Array.prototype.slice.call(root.querySelectorAll('[data-coop-filter]'));
  var search = root.querySelector('[data-coop-search]');
  var empty = root.querySelector('[data-coop-empty]');
  var counter = root.querySelector('[data-coop-count]');
  var filter = 'all';
  var timer = null;

  function norm(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function apply() {
    var q = norm(search ? search.value.trim() : '');
    var shown = 0;
    rows.forEach(function (row) {
      var okFilter = filter === 'all'
        || (filter === 'todo' ? row.getAttribute('data-todo') === '1' : row.getAttribute('data-filter') === filter);
      var okSearch = !q || norm(row.getAttribute('data-search')).indexOf(q) !== -1;
      row.hidden = !(okFilter && okSearch);
      if (!row.hidden) shown++;
    });
    if (empty) empty.hidden = shown !== 0;
    if (counter) counter.textContent = shown + (shown > 1 ? ' coopérations affichées' : ' coopération affichée');
  }

  tags.forEach(function (tag) {
    tag.addEventListener('click', function () {
      filter = tag.getAttribute('data-coop-filter') || 'all';
      tags.forEach(function (t) {
        var on = t === tag;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  });

  if (search) {
    search.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(apply, 150);
    });
  }
})();
