/**
 * Tableaux de l'accueil du back-office : chaque case s'enregistre en arrière-plan quand on la quitte (jeton CSRF,
 * conflit détecté si la case a changé ailleurs), lignes et colonnes sans rechargement, tri et filtre à l'affichage.
 * Sans ce script, le tableau est un formulaire « Enregistrer le tableau ».
 */
(function () {
  'use strict';

  if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') return;

  function token(root) {
    var i = root.querySelector('input[name="_csrf_token"]') || document.querySelector('input[name="_csrf_token"]');
    return i ? i.value : '';
  }

  function post(url, root, fields) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    fd.append('_csrf_token', token(root));
    return fetch(url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token(root) }
    }).then(function (r) {
      return r.json().then(function (d) { return d; }, function () { return { ok: false, message: 'Réponse illisible du serveur.' }; });
    });
  }

  function num(v) {
    var s = String(v || '').replace(/[\s  ]/g, '');
    return /^-?\d+(?:[.,]\d+)?$/.test(s) ? parseFloat(s.replace(',', '.')) : null;
  }

  function init(root) {
    if (root._bshReady) return;
    root._bshReady = true;
    var editable = root.getAttribute('data-editable') === '1';
    var status = root.querySelector('[data-sheet-status]');
    var form = root.querySelector('[data-sheet-form]');
    var table = root.querySelector('[data-sheet-table]');
    var struct = root.querySelector('form[id^="bsh-struct-"]');

    function say(msg, bad) {
      if (!status) return;
      status.textContent = msg || '';
      status.classList.toggle('is-bad', !!bad);
    }
    function version() { return root.getAttribute('data-version') || '0'; }
    function setVersion(v) {
      root.setAttribute('data-version', String(v));
      root.querySelectorAll('input[name="version"]').forEach(function (i) { i.value = String(v); });
    }

    root.querySelectorAll('[data-sheet-sort]').forEach(function (b) { b.hidden = false; });
    var nojs = root.querySelector('[data-sheet-nojs]');
    if (nojs) nojs.hidden = true;

    /* Confirmations (supprimer une ligne, une colonne, le tableau, importer). */
    root.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sheet-confirm]');
      if (b && !window.confirm(b.getAttribute('data-sheet-confirm'))) { e.preventDefault(); }
    });

    /* Filtre et tri : affichage seulement, les formules gardent leurs références. */
    var filter = root.querySelector('[data-sheet-filter]');
    if (filter && table) {
      filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (tr) {
          var text = Array.prototype.map.call(tr.querySelectorAll('td'), function (td) {
            var inp = td.querySelector('input');
            return (inp ? inp.getAttribute('data-shown') || inp.value : td.textContent).toLowerCase();
          }).join(' ');
          tr.hidden = q !== '' && text.indexOf(q) === -1;
        });
      });
    }
    root.querySelectorAll('[data-sheet-sort]').forEach(function (b) {
      b.addEventListener('click', function () {
        var col = parseInt(b.getAttribute('data-sheet-sort'), 10);
        var th = b.closest('th');
        var dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
        table.querySelectorAll('thead th').forEach(function (h) { h.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        var body = table.tBodies[0];
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (a, z) {
          var x = (a.cells[col + 1] && a.cells[col + 1].getAttribute('data-sort')) || '';
          var y = (z.cells[col + 1] && z.cells[col + 1].getAttribute('data-sort')) || '';
          var nx = num(x), ny = num(y);
          if (x === '' && y !== '') return 1;
          if (y === '' && x !== '') return -1;
          if (nx !== null && ny !== null) return (nx - ny) * dir;
          return x.localeCompare(y, 'fr', { numeric: true, sensitivity: 'base' }) * dir;
        });
        rows.forEach(function (r) { body.appendChild(r); });
        say(dir === 1 ? 'Trié par ordre croissant (affichage seulement).' : 'Trié par ordre décroissant (affichage seulement).');
      });
    });

    if (!editable || !table) return;

    if (form) {
      form.addEventListener('submit', function (e) { e.preventDefault(); });
    }

    var cellUrl = root.getAttribute('data-cell-url');
    var inputs = table.querySelectorAll('tbody input[data-r]');

    function showFormula(inp) {
      var raw = inp.getAttribute('data-raw') || '';
      inp.closest('td').classList.toggle('is-formula', raw.charAt(0) === '=');
      if (raw.charAt(0) === '=' && document.activeElement !== inp) {
        inp.value = inp.getAttribute('data-shown') || '';
      }
    }

    inputs.forEach(function (inp) {
      showFormula(inp);
      /* Repères : la ligne et la colonne de la case active s'éclairent ; un nombre s'aligne à droite. */
      function mark(on) {
        var tr = inp.closest('tr');
        var th = table.querySelector('thead th[data-col="' + inp.getAttribute('data-c') + '"]');
        if (tr) tr.classList.toggle('is-active', on);
        if (th) th.classList.toggle('is-active', on);
      }
      function align() { inp.closest('td').classList.toggle('is-num', num(inp.getAttribute('data-shown')) !== null); }
      align();
      inp.addEventListener('focus', function () { mark(true); });
      inp.addEventListener('blur', function () { mark(false); align(); });
      inp.addEventListener('focus', function () {
        var raw = inp.getAttribute('data-raw') || '';
        if (raw.charAt(0) === '=') inp.value = raw;
        inp.select();
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          var r = parseInt(inp.getAttribute('data-r'), 10) + (e.shiftKey ? -1 : 1);
          var next = table.querySelector('input[data-r="' + r + '"][data-c="' + inp.getAttribute('data-c') + '"]');
          if (next) { next.focus(); } else { inp.blur(); }
        } else if (e.key === 'Escape') {
          inp.value = inp.getAttribute('data-raw') || '';
          inp.blur();
        }
      });
      inp.addEventListener('blur', function () {
        var raw = inp.getAttribute('data-raw') || '';
        var value = inp.value;
        if (value === raw) { showFormula(inp); return; }
        inp.classList.add('is-saving');
        post(cellUrl, root, { r: inp.getAttribute('data-r'), c: inp.getAttribute('data-c'), value: value, seen: raw }).then(function (d) {
          inp.classList.remove('is-saving');
          if (d && d.ok) {
            inp.setAttribute('data-raw', d.value);
            inp.setAttribute('data-shown', d.value);
            inp.closest('td').setAttribute('data-sort', d.value);
            inp.classList.remove('is-conflict');
            setVersion(d.version);
            Object.keys(d.computed || {}).forEach(function (k) {
              var p = k.split(':');
              var f = table.querySelector('input[data-r="' + p[0] + '"][data-c="' + p[1] + '"]');
              if (!f) return;
              f.setAttribute('data-shown', d.computed[k]);
              f.closest('td').setAttribute('data-sort', d.computed[k]);
              showFormula(f);
            });
            if (d.value.charAt(0) === '=' && d.computed && d.computed[inp.getAttribute('data-r') + ':' + inp.getAttribute('data-c')] !== undefined) {
              inp.setAttribute('data-shown', d.computed[inp.getAttribute('data-r') + ':' + inp.getAttribute('data-c')]);
            }
            showFormula(inp);
            say('Enregistré.');
          } else if (d && d.conflict) {
            inp.value = d.value;
            inp.setAttribute('data-raw', d.value);
            inp.setAttribute('data-shown', d.value);
            inp.classList.add('is-conflict');
            setVersion(d.version);
            say(d.message, true);
          } else {
            inp.classList.add('is-conflict');
            say((d && d.message) || 'Case non enregistrée.', true);
          }
        }, function () {
          inp.classList.remove('is-saving');
          inp.classList.add('is-conflict');
          say('Hors ligne : la case n’est pas enregistrée.', true);
        });
      });
    });

    /* Lignes, colonnes, en-têtes : le serveur renvoie le tableau à jour. */
    function structure(fields) {
      fields.version = version();
      fields.compact = root.classList.contains('bsh--compact') ? '1' : '0';
      return post(root.getAttribute('data-structure-url'), root, fields).then(function (d) {
        if (d && d.html) {
          var wrap = document.createElement('div');
          wrap.innerHTML = d.html;
          var fresh = wrap.firstElementChild;
          if (fresh) {
            root.replaceWith(fresh);
            init(fresh);
            var st = fresh.querySelector('[data-sheet-status]');
            if (st) { st.textContent = d.message || ''; st.classList.toggle('is-bad', !d.ok); }
            return;
          }
        }
        say((d && d.message) || 'Action impossible.', !(d && d.ok));
      }, function () { say('Hors ligne : rien n’a changé.', true); });
    }
    if (struct) {
      struct.addEventListener('submit', function (e) {
        var btn = e.submitter;
        if (!btn) return;
        e.preventDefault();
        structure({ op: btn.value });
      });
    }
    table.querySelectorAll('[data-sheet-head]').forEach(function (h) {
      var before = h.value;
      h.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); h.blur(); } });
      h.addEventListener('blur', function () {
        if (h.value === before) return;
        before = h.value;
        structure({ op: 'rename_col', index: h.getAttribute('data-sheet-head'), name: h.value });
      });
    });
  }

  function boot() { document.querySelectorAll('[data-sheet]').forEach(init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
