/**
 * Tableur des effectifs : menu « Colonnes » (masquer ou réafficher des colonnes de la vue).
 * Le choix est gardé dans le navigateur (localStorage), par personne et par vue ; sans stockage, tout reste affiché.
 * Une colonne masquée l'est par la classe .is-col-off sur son en-tête et ses cellules (attribut data-col).
 */
(function () {
  'use strict';

  var menu = document.querySelector('[data-eff-cols]');
  var table = document.getElementById('eff-roster-table');
  if (!menu || !table) { return; }

  var key = menu.getAttribute('data-eff-cols-key') || 'eff-roster-cols';
  var boxes = Array.prototype.slice.call(menu.querySelectorAll('[data-eff-col-toggle]'));
  var count = menu.querySelector('[data-eff-cols-count]');

  function read() {
    try {
      var raw = window.localStorage.getItem(key);
      var list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list.filter(function (k) { return typeof k === 'string'; }) : [];
    } catch (e) {
      return [];
    }
  }

  function write(hidden) {
    try {
      if (hidden.length) {
        window.localStorage.setItem(key, JSON.stringify(hidden));
      } else {
        window.localStorage.removeItem(key);
      }
    } catch (e) {
      /* stockage indisponible : le choix vaut pour cette page seulement */
    }
  }

  function apply(hidden) {
    Array.prototype.forEach.call(table.querySelectorAll('[data-col]'), function (cell) {
      cell.classList.toggle('is-col-off', hidden.indexOf(cell.getAttribute('data-col')) !== -1);
    });
    boxes.forEach(function (b) { b.checked = hidden.indexOf(b.value) === -1; });
    if (count) {
      count.hidden = hidden.length === 0;
      count.textContent = hidden.length ? String(hidden.length) : '';
      count.setAttribute('aria-label', hidden.length + ' ' + (menu.getAttribute('data-eff-cols-hidden') || ''));
    }
  }

  function current() {
    return boxes.filter(function (b) { return !b.checked; }).map(function (b) { return b.value; });
  }

  boxes.forEach(function (b) {
    b.addEventListener('change', function () {
      var hidden = current();
      write(hidden);
      apply(hidden);
    });
  });

  var reset = menu.querySelector('[data-eff-cols-reset]');
  if (reset) {
    reset.addEventListener('click', function () {
      write([]);
      apply([]);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.open) {
      menu.open = false;
      var s = menu.querySelector('summary');
      if (s) { s.focus(); }
    }
  });
  document.addEventListener('click', function (e) {
    if (menu.open && !menu.contains(e.target)) { menu.open = false; }
  });

  // Ne garder que les colonnes qui existent encore dans cette vue.
  var known = boxes.map(function (b) { return b.value; });
  apply(read().filter(function (k) { return known.indexOf(k) !== -1; }));
  menu.hidden = false;
})();
