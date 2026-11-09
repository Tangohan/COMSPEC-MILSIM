/**
 * Tableaux de données (effectifs) et listes triables du back-office, JavaScript progressif.
 *
 * - [data-dt] : aperçu en direct (le formulaire GET est rejoué en arrière-plan et seul le plan de travail est remplacé),
 *   tri et filtre à l'affichage, colonnes masquables et redimensionnables, lignes sélectionnables, copie en TSV,
 *   totaux recalculés sur les lignes visibles. Rien n'est écrit : export, enregistrement et partage restent des
 *   formulaires du serveur (jeton CSRF, droits par source, neutralisation CSV).
 * - [data-grid-table] : tri par en-tête et filtre instantané d'une liste (page « Sans compte »).
 * Sans ce script, la page est un formulaire « Afficher le tableau » entièrement fonctionnel.
 */
(function () {
  'use strict';

  if (typeof window.fetch !== 'function' || !window.URLSearchParams || !window.DOMParser) return;

  function num(v) {
    var s = String(v || '').replace(/[\s  ]/g, '');
    return /^-?\d+(?:[.,]\d+)?$/.test(s) ? parseFloat(s.replace(',', '.')) : null;
  }

  function fmt(n) {
    return String(Math.round(n * 100) / 100).replace('.', ',');
  }

  function cmp(a, b, numeric, dir) {
    if (a === '' && b !== '') return 1;
    if (b === '' && a !== '') return -1;
    var na = num(a), nb = num(b);
    if (numeric && na !== null && nb !== null) return (na - nb) * dir;
    return a.localeCompare(b, 'fr', { numeric: true, sensitivity: 'base' }) * dir;
  }

  /** Cellule destinée à un tableur : une valeur qui commencerait par = + - @ et n'est pas un nombre est neutralisée. */
  function safe(v) {
    v = String(v).replace(/[\t\r\n]+/g, ' ');
    return /^[=+\-@]/.test(v) && num(v) === null ? "'" + v : v;
  }

  /** Tri par clic sur les en-têtes ; `offset` = nombre de colonnes de tête sans tri (numéros de ligne). */
  function makeSortable(table, offset, onDone) {
    var head = table.tHead && table.tHead.rows[0];
    if (!head) return;
    Array.prototype.forEach.call(head.cells, function (th, idx) {
      if (idx < offset || th.hasAttribute('data-nosort')) return;
      th.classList.add('is-sortable');
      th.tabIndex = 0;
      th.setAttribute('role', 'columnheader');
      function go() {
        var dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
        Array.prototype.forEach.call(head.cells, function (h) {
          h.removeAttribute('aria-sort');
          var ar = h.querySelector('.dt-grid__arrow');
          if (ar) ar.textContent = '';
        });
        th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        var arrow = th.querySelector('.dt-grid__arrow');
        if (arrow) arrow.textContent = dir === 1 ? '▲' : '▼';
        var body = table.tBodies[0];
        var numeric = th.hasAttribute('data-num');
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (x, y) {
          return cmp(x.cells[idx].textContent.trim(), y.cells[idx].textContent.trim(), numeric, dir);
        });
        rows.forEach(function (r) { body.appendChild(r); });
        if (onDone) onDone();
      }
      th.addEventListener('click', function (e) { if (!e.target.closest('.dt-rs')) go(); });
      th.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
      });
    });
  }

  function rowText(tr) {
    return Array.prototype.map.call(tr.cells, function (c) { return c.textContent; }).join(' ').toLowerCase();
  }

  /* ---------- Listes simples (Sans compte) ---------- */
  function initGrid(table) {
    if (table._dtReady) return;
    table._dtReady = true;
    makeSortable(table, 0, null);
    var wrap = table.closest('[data-grid]') || document;
    var input = document.querySelector('[data-grid-filter]');
    var none = wrap.querySelector('[data-grid-none]');
    if (!input) return;
    input.hidden = false;
    var label = input.closest('label');
    if (label) label.hidden = false;
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var shown = 0;
      Array.prototype.forEach.call(table.tBodies[0].rows, function (tr) {
        var hide = q !== '' && rowText(tr).indexOf(q) === -1;
        tr.hidden = hide;
        if (!hide) shown++;
      });
      if (none) none.hidden = shown !== 0;
    });
  }

  /* ---------- Constructeur de tableaux ---------- */
  function initMain(root) {
    var main = root.querySelector('[data-dt-main]');
    if (!main) return;
    main.querySelectorAll('[data-dt-js]').forEach(function (e) { e.hidden = false; });
    main.querySelectorAll('[data-dt-confirm]').forEach(function (b) {
      b.addEventListener('click', function (e) {
        if (!window.confirm(b.getAttribute('data-dt-confirm'))) e.preventDefault();
      });
    });
    var table = main.querySelector('[data-dt-table]');
    if (!table) return;
    var body = table.tBodies[0];
    var status = main.querySelector('[data-dt-status]');
    var count = main.querySelector('[data-dt-rowcount]');
    var filter = main.querySelector('[data-dt-filter]');
    var baseStatus = status ? status.textContent : '';

    function say(msg, bad) {
      if (!status) return;
      status.textContent = msg || '';
      status.classList.toggle('is-bad', !!bad);
    }

    function visibleRows() {
      return Array.prototype.filter.call(body.rows, function (r) { return !r.hidden; });
    }

    /* Numéros de ligne, compteur et totaux suivent ce qui est affiché. */
    function refresh() {
      var rows = visibleRows();
      rows.forEach(function (r, i) { r.cells[0].textContent = String(i + 1); });
      if (count) {
        count.textContent = rows.length === body.rows.length ? count.getAttribute('data-total') + ' ligne(s)' : rows.length + ' / ' + body.rows.length + ' ligne(s)';
      }
      table.querySelectorAll('thead th[data-sum]').forEach(function (th) {
        var i = parseInt(th.getAttribute('data-i'), 10);
        var cell = table.querySelector('tfoot td[data-foot="' + i + '"]');
        if (!cell) return;
        var t = 0;
        rows.forEach(function (r) { var n = num(r.cells[i + 1].textContent); if (n !== null) t += n; });
        cell.textContent = fmt(t);
      });
    }

    makeSortable(table, 1, refresh);

    if (filter) {
      filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        Array.prototype.forEach.call(body.rows, function (tr) { tr.hidden = q !== '' && rowText(tr).indexOf(q) === -1; });
        refresh();
        say(q !== '' && visibleRows().length === 0 ? "Aucune ligne affichée ne contient ce texte." : baseStatus, false);
      });
    }

    /* Colonnes masquées. */
    main.querySelectorAll('[data-dt-vis]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var i = parseInt(cb.getAttribute('data-dt-vis'), 10) + 1;
        Array.prototype.forEach.call(table.rows, function (r) {
          if (r.cells[i]) r.cells[i].classList.toggle('is-hidden-col', !cb.checked);
        });
      });
    });

    var dens = main.querySelector('[data-dt-density]');
    if (dens) {
      dens.addEventListener('click', function () {
        var on = table.classList.toggle('is-dense');
        dens.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    /* Sélection de lignes (clic sur le numéro, ou sur la ligne). */
    var last = null;
    body.addEventListener('click', function (e) {
      if (e.target.closest('a,button,input')) return;
      var tr = e.target.closest('tr');
      if (!tr) return;
      if (window.getSelection && String(window.getSelection()) !== '') return;
      if (e.shiftKey && last) {
        var rows = Array.prototype.slice.call(body.rows);
        var a = rows.indexOf(last), b = rows.indexOf(tr);
        rows.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (r) { if (!r.hidden) r.classList.add('is-sel'); });
      } else if (e.ctrlKey || e.metaKey || e.target.closest('th')) {
        tr.classList.toggle('is-sel');
      } else {
        var only = tr.classList.contains('is-sel') && body.querySelectorAll('.is-sel').length === 1;
        body.querySelectorAll('.is-sel').forEach(function (r) { r.classList.remove('is-sel'); });
        if (!only) tr.classList.add('is-sel');
      }
      last = tr;
    });

    /* Copie : lignes sélectionnées, sinon toutes les lignes affichées ; colonnes visibles seulement. */
    function tsv() {
      var picked = Array.prototype.filter.call(body.rows, function (r) { return r.classList.contains('is-sel') && !r.hidden; });
      var rows = picked.length ? picked : visibleRows();
      var cols = [];
      Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, i) {
        if (i > 0 && !th.classList.contains('is-hidden-col')) cols.push(i);
      });
      var out = [cols.map(function (i) { return safe(table.tHead.rows[0].cells[i].textContent.trim()); }).join('\t')];
      rows.forEach(function (r) { out.push(cols.map(function (i) { return safe(r.cells[i].textContent.trim()); }).join('\t')); });
      return { text: out.join('\n'), selected: picked.length > 0 };
    }
    function copy() {
      var t = tsv();
      var done = function () { say(t.selected ? "Sélection copiée, prête à coller dans un tableur." : "Tableau copié, prêt à coller dans un tableur.", false); };
      var fail = function () { say("Copie impossible : sélectionnez le texte à la main.", true); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t.text).then(done, function () { legacy(t.text) ? done() : fail(); });
      } else {
        legacy(t.text) ? done() : fail();
      }
    }
    function legacy(text) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(ta);
      return ok;
    }
    var cbtn = main.querySelector('[data-dt-copy]');
    if (cbtn) cbtn.addEventListener('click', copy);
    table.closest('.dt-scroll').addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C') && body.querySelector('.is-sel')) {
        e.preventDefault();
        copy();
      }
    });

    /* Redimensionnement des colonnes (poignée à droite de l'en-tête). */
    Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, idx) {
      if (idx === 0) return;
      var h = document.createElement('span');
      h.className = 'dt-rs';
      h.setAttribute('aria-hidden', 'true');
      th.appendChild(h);
      h.addEventListener('mousedown', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var x0 = e.clientX, w0 = th.getBoundingClientRect().width;
        h.classList.add('is-on');
        function move(ev) {
          var w = Math.max(60, Math.round(w0 + ev.clientX - x0)) + 'px';
          Array.prototype.forEach.call(table.rows, function (r) {
            var c = r.cells[idx];
            if (c) { c.style.width = w; c.style.minWidth = w; c.style.maxWidth = w; }
          });
        }
        function up() {
          h.classList.remove('is-on');
          document.removeEventListener('mousemove', move);
          document.removeEventListener('mouseup', up);
        }
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', up);
      });
      h.addEventListener('dblclick', function () {
        Array.prototype.forEach.call(table.rows, function (r) {
          var c = r.cells[idx];
          if (c) { c.style.width = ''; c.style.minWidth = ''; c.style.maxWidth = ''; }
        });
      });
    });
  }

  function initBuilder(root) {
    if (root._dtReady) return;
    root._dtReady = true;
    root.classList.add('is-js');
    var form = root.querySelector('[data-dt-form]');
    var base = root.getAttribute('data-dt-base') || '';
    if (!form) { initMain(root); return; }
    var timer = null;
    var seq = 0;

    function main() { return root.querySelector('[data-dt-main]'); }

    function updateCols() {
      var boxes = form.querySelectorAll('[data-dt-col]');
      var n = form.querySelectorAll('[data-dt-col]:checked').length;
      var out = form.querySelector('[data-dt-colcount]');
      var max = out ? parseInt(out.getAttribute('data-max'), 10) : 12;
      if (out) out.textContent = n + '/' + max;
      boxes.forEach(function (b) { b.disabled = !b.checked && n >= max; });
    }

    function markFilters() {
      form.querySelectorAll('.dt-frow').forEach(function (r) {
        var sel = r.querySelector('select');
        r.classList.toggle('is-set', !!(sel && sel.value));
      });
    }

    function preview() {
      updateCols();
      markFilters();
      var m = main();
      if (!m) return;
      if (form.querySelectorAll('[data-dt-col]:checked').length === 0) {
        m.classList.remove('is-busy');
        m.innerHTML = '<div class="dt-empty" data-dt-idle><b>Aucune colonne choisie</b><p>Cochez au moins une colonne pour voir le tableau.</p></div>';
        return;
      }
      var qs = new URLSearchParams(new FormData(form)).toString();
      var mine = ++seq;
      m.classList.add('is-busy');
      fetch(base + '?' + qs, { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) throw new Error('http'); return r.text(); })
        .then(function (html) {
          if (mine !== seq) return;
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var fresh = doc.querySelector('[data-dt-main]');
          if (!fresh) throw new Error('shape');
          var cur = main();
          cur.innerHTML = fresh.innerHTML;
          cur.classList.remove('is-busy');
          if (window.history && history.replaceState) { history.replaceState(null, '', base + '?' + qs); }
          initMain(root);
        })
        .catch(function () {
          if (mine !== seq) return;
          var cur = main();
          cur.classList.remove('is-busy');
          cur.innerHTML = '<div class="dt-error" role="alert"><span>Aperçu impossible pour le moment. Vos réglages sont conservés.</span><button type="button" class="ath-btn" data-dt-retry>Réessayer</button></div>';
        });
    }

    function later() {
      clearTimeout(timer);
      timer = setTimeout(preview, 350);
      updateCols();
      markFilters();
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); preview(); });
    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-dt-source]')) {
        window.location.href = base + '?run=1&source=' + encodeURIComponent(e.target.value);
        return;
      }
      later();
    });
    form.addEventListener('input', function (e) {
      if (e.target.matches('input[type="text"]')) later();
    });
    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-dt-retry]')) preview();
    });

    updateCols();
    markFilters();
    initMain(root);
    if (root.querySelector('[data-dt-idle]') && form.querySelectorAll('[data-dt-col]:checked').length) preview();
  }

  function boot() {
    document.querySelectorAll('[data-dt]').forEach(initBuilder);
    document.querySelectorAll('[data-grid-table]').forEach(initGrid);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
