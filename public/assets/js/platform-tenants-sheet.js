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
  var flags = root.querySelectorAll('[data-pa-flag]');
  var colFilters = root.querySelectorAll('[data-col-filter]');
  var page = document.querySelector('[data-corr-url]');
  var drawer = document.querySelector('[data-pa-drawer]');
  var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-pa-row]'));
  var flag = '';

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
      if (flag === 'archived') {
        ok = ok && row.getAttribute('data-situation') === 'archivee';
      } else if (flag !== '') {
        ok = ok && row.getAttribute('data-idle') === flag;
      }
      colFilters.forEach(function (field) {
        var q = fold(field.value.trim());
        if (q === '') {
          return;
        }
        var index = parseInt(field.getAttribute('data-col-filter') || '0', 10);
        var cell = row.children[index];
        var text = fold(cell ? cell.textContent : '');
        if (text.indexOf(q) === -1) {
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
  flags.forEach(function (button) {
    button.addEventListener('click', function () {
      flag = button.getAttribute('data-pa-flag') || '';
      flags.forEach(function (other) {
        other.classList.toggle('is-on', other === button);
      });
      apply();
    });
  });
  colFilters.forEach(function (field) {
    field.addEventListener('input', apply);
  });

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function openCorr(id) {
    if (!drawer || !page) {
      return;
    }
    drawer.hidden = false;
    var title = drawer.querySelector('[data-pa-drawer-title]');
    var body = drawer.querySelector('[data-pa-drawer-body]');
    if (title) {
      title.textContent = 'Corrélations';
    }
    if (body) {
      body.innerHTML = '<p class="adr-empty">Recherche de tout ce qui est rattaché…</p>';
    }
    var url = new URL(page.getAttribute('data-corr-url'), location.origin);
    url.searchParams.set('type', 'tenant');
    url.searchParams.set('id', id);
    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (response) { return response.json().then(function (json) { return { ok: response.ok, json: json }; }); })
      .then(function (result) {
        if (!result.ok || !result.json.ok) {
          throw new Error((result.json && result.json.message) || 'Introuvable');
        }
        renderCorr(result.json);
      })
      .catch(function (error) {
        if (body) {
          body.innerHTML = '<p class="adr-empty">' + esc(error.message) + '</p>';
        }
      });
  }

  function renderCorr(payload) {
    var title = drawer.querySelector('[data-pa-drawer-title]');
    var kicker = drawer.querySelector('[data-pa-drawer-kicker]');
    var body = drawer.querySelector('[data-pa-drawer-body]');
    var entity = payload.entity || {};
    if (title) {
      title.textContent = entity.label || 'Corrélations';
    }
    if (kicker) {
      kicker.textContent = entity.kind || 'Communauté';
    }
    var facts = (entity.facts || []).map(function (fact) {
      return '<dt>' + esc(fact[0]) + '</dt><dd>' + esc(fact[1] == null || fact[1] === '' ? '—' : fact[1]) + '</dd>';
    }).join('');
    var total = 0;
    var tables = (payload.tables || []).map(function (row) {
      total += Number(row.count || 0);
      var when = row.last ? new Date(String(row.last).replace(' ', 'T') + 'Z').toLocaleString('fr-FR') : '—';
      return '<tr><td>' + esc(row.label) + '</td><td class="pa-sheet__num">' + esc(row.count) + '</td><td>' + esc(when) + '</td></tr>';
    }).join('');
    var links = (payload.open || []).map(function (link) {
      return '<a class="pa-btn pa-btn--line" href="' + esc(link.href) + '">' + esc(link.label) + '</a>';
    }).join(' ');
    if (body) {
      body.innerHTML = '<dl class="adr-facts">' + facts + '</dl>'
        + '<h4>Ce qui est rattaché (' + total + ')</h4>'
        + '<div class="ath-table-wrap"><table class="pa-sheet__grid"><thead><tr><th>Élément</th><th>Nombre</th><th>Dernière trace</th></tr></thead><tbody>'
        + (tables || '<tr><td colspan="3">Aucune trace.</td></tr>')
        + '</tbody></table></div>'
        + (links ? '<p class="adr-open">' + links + '</p>' : '');
    }
  }

  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-corr-id]');
    if (button) {
      openCorr(button.getAttribute('data-corr-id'));
    }
  });
  if (drawer) {
    drawer.addEventListener('click', function (event) {
      if (event.target === drawer || event.target.closest('[data-pa-drawer-close]')) {
        drawer.hidden = true;
      }
    });
  }

  if (reset) {
    reset.addEventListener('click', function () {
      if (query) {
        query.value = '';
      }
      filters.forEach(function (field) {
        field.value = '';
      });
      colFilters.forEach(function (field) {
        field.value = '';
      });
      flag = '';
      flags.forEach(function (button) {
        button.classList.toggle('is-on', button.getAttribute('data-pa-flag') === '');
      });
      apply();
      if (query) {
        query.focus();
      }
    });
  }
})();
