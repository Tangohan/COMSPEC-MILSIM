/**
 * Back-office — Métriques joueurs : recherche dans le tableau des membres et tri par en-tête (comme le tableur des heures).
 * Vue : views/admin/organization/player_metrics.php (attributs data-pm-*). Aucun HTML injecté : textContent uniquement.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-pm-table]');
  if (!root) {
    return;
  }
  var table = root.querySelector('[data-pm-grid]');
  if (!table || !table.tBodies.length) {
    return;
  }
  var tbody = table.tBodies[0];
  var q = root.querySelector('[data-pm-q]');
  var count = root.querySelector('[data-pm-count]');
  var none = root.querySelector('[data-pm-none]');
  var rows = Array.prototype.slice.call(tbody.rows);

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
      count.textContent = String(shown);
    }
    if (none) {
      none.hidden = shown !== 0;
    }
  }

  function cellValue(tr, idx, numeric) {
    var cell = tr.cells[idx];
    var raw = cell ? (cell.hasAttribute('data-sort') ? cell.getAttribute('data-sort') : cell.textContent.trim().toLowerCase()) : '';
    if (!numeric) {
      return raw;
    }
    if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
      return Date.parse(raw) || 0;
    }
    var n = parseFloat(raw);
    return isNaN(n) ? -Infinity : n;
  }

  var headers = Array.prototype.slice.call(table.tHead ? table.tHead.rows[0].cells : []);
  headers.forEach(function (th, idx) {
    var type = th.getAttribute('data-sort-type');
    if (!type) {
      return;
    }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'akhr__sort';
    while (th.firstChild) {
      btn.appendChild(th.firstChild);
    }
    th.appendChild(btn);
    // L'ordre initial vient du serveur (?tri=) : on l'indique sur la colonne correspondante.
    th.setAttribute('aria-sort', th.getAttribute('data-sort-initial') || 'none');
    btn.addEventListener('click', function () {
      var numeric = type === 'num';
      var cur = th.getAttribute('aria-sort');
      // Nombres : du plus grand au plus petit au premier clic ; texte : de A à Z.
      var dir = numeric ? (cur === 'descending' ? 1 : -1) : (cur === 'ascending' ? -1 : 1);
      headers.forEach(function (h) {
        if (h.hasAttribute('aria-sort')) {
          h.setAttribute('aria-sort', 'none');
        }
      });
      th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
      rows.sort(function (a, b) {
        var va = cellValue(a, idx, numeric);
        var vb = cellValue(b, idx, numeric);
        if (va === vb) {
          return 0;
        }
        if (numeric) {
          return (va < vb ? -1 : 1) * dir;
        }
        return String(va).localeCompare(String(vb), 'fr', { numeric: true }) * dir;
      });
      rows.forEach(function (tr) {
        tbody.appendChild(tr);
      });
    });
  });

  if (q) {
    q.addEventListener('input', apply);
  }
})();
