/**
 * Registre des droits (Effectifs › Registre des droits), JavaScript progressif.
 * Colonnes affichées (gardées sur l'appareil et reprises dans l'export CSV), tri par en-tête, recherche instantanée,
 * sélection pour la revue et le retrait, plein écran. Sans ce script, la page reste entièrement utilisable.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-areg]');
  if (!root) return;
  var view = root.getAttribute('data-areg-view') || 'vigueur';
  var table = root.querySelector('[data-areg-table]');
  var body = table ? table.tBodies[0] : null;
  var storeKey = 'areg-cols-' + view;

  function fold(s) {
    s = String(s || '').toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
  }

  function read() {
    try { return JSON.parse(window.localStorage.getItem(storeKey) || 'null'); } catch (e) { return null; }
  }

  function write(v) {
    try { window.localStorage.setItem(storeKey, JSON.stringify(v)); } catch (e) { /* stockage indisponible */ }
  }

  // Colonnes
  var toggles = Array.prototype.slice.call(root.querySelectorAll('[data-areg-col-toggle]'));
  var csv = root.querySelector('[data-areg-csv]');
  var csvBase = csv ? csv.getAttribute('href') : '';

  function applyCols() {
    var on = [];
    toggles.forEach(function (box) {
      var key = box.getAttribute('data-areg-col-toggle');
      if (box.checked) on.push(key);
      root.querySelectorAll('[data-col="' + key + '"]').forEach(function (cell) { cell.hidden = !box.checked; });
    });
    if (csv) {
      var extra = on.map(function (k) { return 'cols[]=' + encodeURIComponent(k); }).join('&');
      csv.setAttribute('href', csvBase + (csvBase.indexOf('?') === -1 ? '?' : '&') + extra);
    }
    return on;
  }

  var saved = read();
  if (Array.isArray(saved) && saved.length) {
    toggles.forEach(function (box) { box.checked = saved.indexOf(box.getAttribute('data-areg-col-toggle')) !== -1; });
  }
  applyCols();
  toggles.forEach(function (box) {
    box.addEventListener('change', function () { write(applyCols()); });
  });

  // Filtres : un changement de liste recharge la vue filtrée.
  root.querySelectorAll('[data-areg-autosubmit]').forEach(function (sel) {
    sel.addEventListener('change', function () { if (sel.form) sel.form.submit(); });
  });

  // Recherche instantanée sur les lignes chargées ; Entrée relance la recherche côté serveur.
  var search = root.querySelector('[data-areg-search]');
  var count = root.querySelector('[data-areg-shown]');
  var nodata = root.querySelector('[data-areg-nodata]');

  function filterRows() {
    if (!body) return;
    var words = fold(search ? search.value : '').split(/\s+/).filter(Boolean);
    var shown = 0;
    Array.prototype.forEach.call(body.rows, function (tr) {
      var hay = fold(tr.textContent);
      var ok = words.every(function (w) { return hay.indexOf(w) !== -1; });
      tr.hidden = !ok;
      if (ok) shown++;
    });
    if (count) count.textContent = String(shown);
    if (nodata) nodata.hidden = shown > 0;
  }

  if (search) {
    var t = null;
    search.addEventListener('input', function () {
      window.clearTimeout(t);
      t = window.setTimeout(filterRows, 120);
    });
  }

  // Tri par en-tête (dates AAAA-MM-JJ et texte, ordre français).
  if (table && body) {
    var head = table.tHead.rows[0];
    Array.prototype.forEach.call(head.cells, function (th, idx) {
      if (th.hasAttribute('data-nosort')) return;
      th.classList.add('is-sortable');
      th.tabIndex = 0;
      function go() {
        var dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
        Array.prototype.forEach.call(head.cells, function (h) {
          h.removeAttribute('aria-sort');
          var a = h.querySelector('.areg-arrow');
          if (a) a.textContent = '';
        });
        th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        var arrow = th.querySelector('.areg-arrow');
        if (arrow) arrow.textContent = dir === 1 ? ' ▲' : ' ▼';
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (a, b) {
          var x = (a.cells[idx] ? a.cells[idx].textContent : '').trim();
          var y = (b.cells[idx] ? b.cells[idx].textContent : '').trim();
          if (x === '—') x = '';
          if (y === '—') y = '';
          if (x === '' && y !== '') return 1;
          if (y === '' && x !== '') return -1;
          return x.localeCompare(y, 'fr', { numeric: true, sensitivity: 'base' }) * dir;
        });
        rows.forEach(function (r) { body.appendChild(r); });
      }
      th.addEventListener('click', go);
      th.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
      });
    });
  }

  // Sélection
  var all = root.querySelector('[data-areg-checkall]');
  var selCount = root.querySelector('[data-areg-selcount]');
  var bulk = root.querySelector('[data-areg-bulk]');

  function boxes() {
    return Array.prototype.slice.call(root.querySelectorAll('[data-areg-row]')).filter(function (b) {
      return !b.disabled && !b.closest('tr').hidden;
    });
  }

  function syncSel() {
    var n = root.querySelectorAll('[data-areg-row]:checked').length;
    if (selCount) selCount.textContent = String(n);
    if (bulk) {
      bulk.classList.toggle('is-active', n > 0);
      bulk.querySelectorAll('button').forEach(function (b) { b.disabled = n === 0; });
    }
  }

  if (all) {
    all.addEventListener('change', function () {
      boxes().forEach(function (b) { b.checked = all.checked; });
      syncSel();
    });
  }
  root.addEventListener('change', function (e) {
    if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-areg-row')) syncSel();
  });
  syncSel();

  // Confirmation avant un retrait.
  root.querySelectorAll('[data-areg-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (!window.confirm(btn.getAttribute('data-areg-confirm'))) e.preventDefault();
    });
  });

  // Plein écran
  var fs = root.querySelector('[data-areg-fullscreen]');
  if (fs && root.requestFullscreen) {
    fs.hidden = false;
    fs.addEventListener('click', function () {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else {
        root.requestFullscreen().catch(function () { root.classList.toggle('is-full'); });
      }
    });
    document.addEventListener('fullscreenchange', function () {
      var on = document.fullscreenElement === root;
      root.classList.toggle('is-full', on);
      fs.textContent = on ? 'Quitter le plein écran' : 'Plein écran';
    });
  }
})();
