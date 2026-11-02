/* Academy — file « à traiter » de l'encadrement (views/academy/queue_*.php).
   Fenêtres de validation rapide, filtre par type, tri par colonne et actions groupées. Les actions groupées envoient,
   une ligne après l'autre, le même formulaire que le bouton de la ligne ; le serveur revérifie chaque droit.
   Aucun texte ici : les messages viennent des attributs data-q-msg-* de la vue (FR/EN). */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var open = event.target.closest('[data-q-open]');
    if (open) {
      var dialog = document.getElementById(open.getAttribute('data-q-open'));
      if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
        var field = dialog.querySelector('textarea');
        if (field) field.focus();
      }
      return;
    }
    var close = event.target.closest('[data-q-close]');
    if (close) {
      var parent = close.closest('dialog');
      if (parent) parent.close();
      return;
    }
    // Clic sur le fond de la fenêtre : on la ferme.
    if (event.target.tagName === 'DIALOG' && event.target.classList.contains('q-dialog')) {
      event.target.close();
    }
  });

  document.querySelectorAll('[data-q-root]').forEach(function (root) {
    var body = root.querySelector('.q-table tbody');
    if (!body) return;
    var rows = Array.prototype.slice.call(body.querySelectorAll('[data-q-row]'));
    var empty = body.querySelector('[data-q-empty]');
    var picks = function () { return rows.filter(function (r) { return !r.hidden; }).map(function (r) { return r.querySelector('[data-q-pick]'); }).filter(Boolean); };
    var counter = root.querySelector('[data-q-selected]');
    var refreshCount = function () {
      if (counter) counter.textContent = String(picks().filter(function (p) { return p.checked; }).length);
    };

    // Filtre par type.
    var applyFilter = function (type) {
      root.querySelectorAll('[data-q-filter]').forEach(function (chip) {
        chip.setAttribute('aria-pressed', chip.getAttribute('data-q-filter') === type ? 'true' : 'false');
      });
      var shown = 0;
      rows.forEach(function (row) {
        row.hidden = type !== '' && row.getAttribute('data-type') !== type;
        if (row.hidden) {
          var box = row.querySelector('[data-q-pick]');
          if (box) box.checked = false;
        } else {
          shown++;
        }
      });
      if (empty) empty.hidden = shown > 0;
      refreshCount();
    };
    root.querySelectorAll('[data-q-filter]').forEach(function (chip) {
      chip.addEventListener('click', function () { applyFilter(chip.getAttribute('data-q-filter')); });
    });
    applyFilter(root.getAttribute('data-q-filter-initial') || '');

    // Tri : type (ordre de la file), cours, stagiaire, ancienneté ; second clic = ordre inverse.
    root.querySelectorAll('[data-q-sort]').forEach(function (button) {
      button.addEventListener('click', function () {
        var key = button.getAttribute('data-q-sort');
        var th = button.closest('th');
        var asc = th.getAttribute('aria-sort') !== 'ascending';
        root.querySelectorAll('.q-table th[aria-sort]').forEach(function (other) { other.setAttribute('aria-sort', 'none'); });
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
        var value = function (row) {
          if (key === 'since') return Number(row.getAttribute('data-since')) || 0;
          if (key === 'type') return Number(row.getAttribute('data-type-order')) * 1e12 + (Number(row.getAttribute('data-since')) || 0);
          return row.getAttribute('data-' + key) || '';
        };
        rows.sort(function (a, b) {
          var x = value(a), y = value(b);
          var d = typeof x === 'number' ? x - y : String(x).localeCompare(String(y));
          return asc ? d : -d;
        });
        rows.forEach(function (row) { body.insertBefore(row, empty); });
      });
    });

    // Sélection.
    var all = root.querySelector('[data-q-all]');
    if (all) {
      all.addEventListener('change', function () {
        picks().forEach(function (p) { p.checked = all.checked; });
        refreshCount();
      });
    }
    body.addEventListener('change', function (event) {
      if (event.target.matches('[data-q-pick]')) refreshCount();
    });

    // Actions groupées.
    var status = root.querySelector('[data-q-status]');
    var comment = root.querySelector('[data-q-comment]');
    root.querySelectorAll('[data-q-run]').forEach(function (button) {
      button.addEventListener('click', function () {
        var mode = button.getAttribute('data-q-run');
        var chosen = picks().filter(function (p) { return p.checked && p.getAttribute('data-' + mode) && p.getAttribute('data-' + mode) !== 'null'; });
        if (chosen.length === 0) {
          if (status) status.textContent = root.getAttribute('data-q-msg-none') || '';
          return;
        }
        var text = comment ? comment.value.trim() : '';
        var needsComment = mode === 'approve' && chosen.some(function (p) { return p.getAttribute('data-kind') === 'chapter'; });
        if (needsComment && text.length < 10) {
          if (status) status.textContent = root.getAttribute('data-q-msg-comment') || '';
          if (comment) comment.focus();
          return;
        }
        if (mode === 'refuse' && !window.confirm(root.getAttribute('data-q-msg-refuse') || '')) return;
        root.querySelectorAll('[data-q-run]').forEach(function (b) { b.disabled = true; });
        if (status) status.textContent = root.getAttribute('data-q-msg-working') || '';
        var ok = 0, ko = 0;
        var next = function (index) {
          if (index >= chosen.length) {
            window.location.href = window.location.pathname + '?bulk=1&ok=' + ok + '&ko=' + ko + '#file';
            return;
          }
          var spec = JSON.parse(chosen[index].getAttribute('data-' + mode));
          var data = new FormData();
          data.append('_csrf_token', root.getAttribute('data-q-csrf') || '');
          Object.keys(spec.fields || {}).forEach(function (name) { data.append(name, spec.fields[name]); });
          if (chosen[index].getAttribute('data-kind') === 'chapter') data.append('comment', text);
          fetch(spec.url, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) { if (response.ok) { ok++; } else { ko++; } })
            .catch(function () { ko++; })
            .then(function () { next(index + 1); });
        };
        next(0);
      });
    });
  });
})();
