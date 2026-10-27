(function () {
  var root = document.querySelector('[data-pa-sheet]');
  if (!root) {
    return;
  }
  var table = root.querySelector('table');
  var tbody = table.querySelector('tbody');
  var query = root.querySelector('[data-pa-sheet-q]');
  var filters = root.querySelectorAll('[data-pa-sheet-filter]');
  var count = root.querySelector('[data-pa-sheet-count]');
  var empty = root.querySelector('[data-pa-sheet-empty]');
  var reset = root.querySelector('[data-pa-sheet-reset]');
  var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-pa-row]'));

  function fold(value) {
    return (value || '').toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function word(total) {
    return total > 1 ? 'communautés' : 'communauté';
  }

  function apply() {
    var needle = fold((query.value || '').trim());
    var active = {};
    filters.forEach(function (field) {
      active[field.getAttribute('data-pa-sheet-filter')] = field.value;
    });
    var shown = 0;
    rows.forEach(function (row) {
      var ok = needle === '' || fold(row.getAttribute('data-haystack') || '').indexOf(needle) !== -1;
      Object.keys(active).forEach(function (key) {
        if (active[key] !== '' && row.getAttribute('data-' + key) !== active[key]) {
          ok = false;
        }
      });
      row.hidden = !ok;
      if (ok) {
        shown += 1;
      }
    });
    if (count) {
      count.textContent = shown === rows.length
        ? rows.length + ' ' + word(rows.length)
        : shown + ' sur ' + rows.length;
    }
    if (empty) {
      empty.hidden = shown !== 0 || rows.length === 0;
    }
  }

  function compare(left, right, type) {
    if (type === 'num') {
      return (parseFloat(left) || 0) - (parseFloat(right) || 0);
    }
    if (type === 'date') {
      if (left === right) {
        return 0;
      }
      if (left === '') {
        return 1;
      }
      if (right === '') {
        return -1;
      }
      return left.localeCompare(right);
    }
    return left.localeCompare(right, 'fr', { sensitivity: 'base', numeric: true });
  }

  table.querySelectorAll('thead [data-sort]').forEach(function (button) {
    button.addEventListener('click', function () {
      var head = button.closest('th');
      var index = parseInt(button.getAttribute('data-col') || '0', 10);
      var type = button.getAttribute('data-sort') || 'text';
      var descending = head.getAttribute('aria-sort') === 'ascending';
      table.querySelectorAll('thead th').forEach(function (cell) {
        cell.removeAttribute('aria-sort');
      });
      head.setAttribute('aria-sort', descending ? 'descending' : 'ascending');
      rows.sort(function (a, b) {
        var left = a.children[index] ? (a.children[index].getAttribute('data-sort-value') || '') : '';
        var right = b.children[index] ? (b.children[index].getAttribute('data-sort-value') || '') : '';
        var result = compare(left, right, type);
        if (type === 'date' && (left === '' || right === '')) {
          return result;
        }
        return descending ? -result : result;
      });
      rows.forEach(function (row) {
        tbody.appendChild(row);
      });
    });
  });

  if (query) {
    query.addEventListener('input', apply);
  }
  filters.forEach(function (field) {
    field.addEventListener('change', apply);
  });
  if (reset) {
    reset.addEventListener('click', function () {
      if (query) {
        query.value = '';
      }
      filters.forEach(function (field) {
        field.value = '';
      });
      apply();
      if (query) {
        query.focus();
      }
    });
  }
})();
