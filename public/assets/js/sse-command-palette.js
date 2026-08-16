/**
 * Command Palette Ctrl+K — Intelligence Workspace.
 */
(function () {
  'use strict';
  var cfg = window.SSE_IW || {};
  var dialog = document.getElementById('iw-command-palette');
  var input = document.getElementById('iw-palette-q');
  var results = document.getElementById('iw-palette-results');
  var actions = document.getElementById('iw-palette-actions');
  var openBtn = document.querySelector('[data-iw-palette-open]');
  if (!dialog || !input) return;

  var items = [];
  var active = -1;
  var timer = null;

  function open() {
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', 'open');
    input.value = '';
    if (results) results.innerHTML = '';
    active = -1;
    input.focus();
  }

  function close() {
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function flattenGroups(data) {
    var out = [];
    var groups = (data && data.groups) || {};
    Object.keys(groups).forEach(function (g) {
      (groups[g] || []).forEach(function (row) {
        out.push({
          label: row.label || '',
          href: row.href || '#',
          hint: (row.type || g) + (row.ref ? ' · ' + row.ref : ''),
          group: g
        });
      });
    });
    return out;
  }

  function renderList(list) {
    items = list;
    active = list.length ? 0 : -1;
    if (!results) return;
    if (!list.length) {
      results.innerHTML = '<li class="iw-palette-empty">Aucun résultat</li>';
      return;
    }
    results.innerHTML = list.map(function (it, i) {
      return '<li role="option" data-idx="' + i + '" class="' + (i === active ? 'is-active' : '') + '">'
        + '<a href="' + esc(it.href) + '"><strong>' + esc(it.label) + '</strong><em>' + esc(it.hint || '') + '</em></a></li>';
    }).join('');
  }

  function search(q) {
    if (!q || q.length < 2 || !cfg.searchUrl) {
      renderList([]);
      return;
    }
    fetch(cfg.searchUrl + '?q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (data) { renderList(flattenGroups(data).slice(0, 12)); })
      .catch(function () { renderList([]); });
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { search(input.value.trim()); }, 180);
  }

  function move(delta) {
    if (!items.length) return;
    active = (active + delta + items.length) % items.length;
    renderList(items);
    var el = results && results.querySelector('[data-idx="' + active + '"] a');
    if (el) el.focus();
    input.focus();
  }

  if (openBtn) openBtn.addEventListener('click', function (e) { e.preventDefault(); open(); });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault();
      open();
    }
  });
  input.addEventListener('input', schedule);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
    if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    if (e.key === 'Enter') {
      e.preventDefault();
      if (active >= 0 && items[active]) {
        window.location.href = items[active].href;
      } else if (input.value.trim()) {
        window.location.href = (document.querySelector('.iw-search') && document.querySelector('.iw-search').action)
          ? (document.querySelector('.iw-search').getAttribute('action') + '?q=' + encodeURIComponent(input.value.trim()))
          : ('/atak/sse/recherche?q=' + encodeURIComponent(input.value.trim()));
      }
    }
    if (e.key === 'Escape') close();
  });

  document.addEventListener('sse-iw-open-palette', open);
})();
