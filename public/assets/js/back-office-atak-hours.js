/**
 * Back-office ATAK — Heures de jeu (tableur) : tri par en-tête, filtres instantanés et ligne de total.
 * Vue : views/admin/atak/hours.php (attributs data-hours-*). Aucun HTML injecté : textContent uniquement.
 */
(function () {
  'use strict';

  function init(root) {
    var table = root.querySelector('[data-hours-table]');
    if (!table || !table.tBodies.length) {
      return;
    }
    var tbody = table.tBodies[0];
    var rows = Array.prototype.slice.call(tbody.rows);
    var filters = Array.prototype.slice.call(root.querySelectorAll('[data-hours-filter]'));
    var countEl = root.querySelector('[data-hours-count]');
    var emptyEl = root.querySelector('[data-hours-empty]');
    var totals = Array.prototype.slice.call(table.querySelectorAll('tfoot [data-total]'));
    var totalKeys = [];
    totals.forEach(function (cell) {
      var key = cell.getAttribute('data-total');
      if (totalKeys.indexOf(key) === -1) {
        totalKeys.push(key);
      }
    });

    function clock(sec) {
      var m = Math.floor(sec / 60);
      var mm = String(m % 60);
      return Math.floor(m / 60) + ':' + (mm.length < 2 ? '0' + mm : mm);
    }

    function decimal(sec) {
      return (sec / 3600).toFixed(2).replace('.', ',');
    }

    function apply() {
      var want = {};
      filters.forEach(function (f) {
        want[f.getAttribute('data-hours-filter')] = f.value.trim().toLowerCase();
      });
      var shown = 0;
      var sums = {};
      rows.forEach(function (tr) {
        var ok = (!want.q || (tr.getAttribute('data-text') || '').indexOf(want.q) !== -1)
          && (!want.type || (tr.getAttribute('data-type') || '').toLowerCase() === want.type)
          && (!want.detection || (tr.getAttribute('data-detection') || '').toLowerCase() === want.detection)
          && (!want.source || (tr.getAttribute('data-source') || '').toLowerCase() === want.source);
        tr.hidden = !ok;
        if (!ok) {
          return;
        }
        shown++;
        totalKeys.forEach(function (key) {
          sums[key] = (sums[key] || 0) + (parseInt(tr.getAttribute('data-sum-' + key) || '0', 10) || 0);
        });
      });
      totals.forEach(function (cell) {
        var v = sums[cell.getAttribute('data-total')] || 0;
        var fmt = cell.getAttribute('data-total-format');
        cell.textContent = fmt === 'decimal' ? decimal(v) : (fmt === 'int' ? String(v) : clock(v));
      });
      if (countEl) {
        countEl.textContent = String(shown);
      }
      if (emptyEl) {
        emptyEl.hidden = shown > 0;
      }
    }

    function cellValue(tr, idx, numeric) {
      var cell = tr.cells[idx];
      if (!cell) {
        return numeric ? -Infinity : '';
      }
      var raw = cell.hasAttribute('data-sort') ? cell.getAttribute('data-sort') : cell.textContent.trim().toLowerCase();
      if (!numeric) {
        return raw;
      }
      if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
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
      th.setAttribute('aria-sort', 'none');
      btn.addEventListener('click', function () {
        var numeric = type === 'num';
        var cur = th.getAttribute('aria-sort');
        // Nombres : du plus grand au plus petit au premier clic, comme on lit un classement ; texte : de A à Z.
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
          return String(va).localeCompare(String(vb), 'fr', { numeric: true, sensitivity: 'base' }) * dir;
        });
        rows.forEach(function (tr) {
          tbody.appendChild(tr);
        });
      });
    });

    filters.forEach(function (f) {
      f.addEventListener(f.tagName === 'SELECT' ? 'change' : 'input', apply);
    });
    apply();
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-hours-sheet]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
