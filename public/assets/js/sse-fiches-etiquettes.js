/*
 * Bureau SSE — étiquettes des fiches de renseignement : choix par famille (recherche, sélection multiple, suggestions selon
 * le type de fiche), liste filtrable de la fiche, filtres de la liste des fiches, tableaux de « Gestion des fiches »
 * (tri, filtre, remplissage des fenêtres), trame du rédacteur. Aucun texte ici : les libellés viennent du HTML.
 */
(function () {
  'use strict';

  function all(sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); }
  function fold(text) {
    var s = String(text || '').toLowerCase();
    return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
  }
  function parse(json, fallback) {
    try { return JSON.parse(json) || fallback; } catch (e) { return fallback; }
  }

  // —— Choix des étiquettes ——
  all('[data-fnp]').forEach(function (root) {
    var max = parseInt(root.getAttribute('data-fnp-max'), 10) || 16;
    var suggestions = parse(root.getAttribute('data-fnp-suggest') || '{}', {});
    var kind = root.getAttribute('data-fnp-kind') || '';
    var selected = root.querySelector('[data-fnp-selected]');
    var template = root.querySelector('template[data-fnp-chip]');
    var search = root.querySelector('[data-fnp-search]');
    var options = all('[data-fnp-option]', root);
    var fams = all('[data-fnp-fam]', root);
    var empty = root.querySelector('[data-fnp-empty]');
    var none = root.querySelector('[data-fnp-none]');
    var count = root.querySelector('[data-fnp-count]');
    var sugBox = root.querySelector('[data-fnp-suggest-box]');
    var sugList = root.querySelector('[data-fnp-suggest-list]');
    var hint = root.querySelector('[data-fnp-hint]');
    var family = fams.length ? fams[0].getAttribute('data-fnp-fam') : '';
    if (!selected || !template) { return; }

    function chips() { return all('[data-fnp-key]', selected); }
    function has(key) { return chips().some(function (c) { return c.getAttribute('data-fnp-key') === key; }); }
    function optionOf(key) {
      for (var i = 0; i < options.length; i++) { if (options[i].getAttribute('data-key') === key) { return options[i]; } }
      return null;
    }

    function refresh() {
      var term = fold(search ? search.value : '').trim();
      var shown = 0;
      options.forEach(function (li) {
        var on = term !== '' ? fold(li.getAttribute('data-search')).indexOf(term) !== -1 : li.getAttribute('data-family') === family;
        li.hidden = !on;
        if (on) { shown++; }
        var btn = li.querySelector('[data-fnp-add]');
        if (btn) { btn.setAttribute('aria-pressed', has(li.getAttribute('data-key')) ? 'true' : 'false'); }
      });
      fams.forEach(function (f) {
        var active = term === '' && f.getAttribute('data-fnp-fam') === family;
        f.setAttribute('aria-selected', active ? 'true' : 'false');
        if (active && hint) { hint.textContent = f.getAttribute('data-fnp-fam-hint') || ''; }
      });
      if (hint) { hint.hidden = term !== ''; }
      if (empty) { empty.hidden = shown > 0; }
      var n = chips().length;
      if (none) { none.hidden = n > 0; }
      if (count) { count.textContent = String(n); }
      if (sugBox && sugList) {
        sugList.textContent = '';
        (suggestions[kind] || []).forEach(function (key) {
          var li = optionOf(key);
          if (!li || has(key)) { return; }
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'fnp-sug';
          b.textContent = li.getAttribute('data-code') || key;
          b.addEventListener('click', function () { add(key); });
          sugList.appendChild(b);
        });
        sugBox.hidden = sugList.childNodes.length === 0;
      }
    }

    function bind(chip) {
      var x = chip.querySelector('[data-fnp-remove]');
      if (x) { x.addEventListener('click', function () { chip.parentNode.removeChild(chip); refresh(); }); }
    }

    function add(key, quiet) {
      var li = optionOf(key);
      if (!li || has(key) || chips().length >= max) { return; }
      var chip = template.content.firstElementChild.cloneNode(true);
      chip.setAttribute('data-fnp-key', key);
      chip.style.setProperty('--fnt', li.getAttribute('data-hex') || '#64748b');
      chip.querySelector('[data-fnp-chip-key]').value = key;
      chip.querySelector('[data-fnp-chip-code]').textContent = li.getAttribute('data-code') || key;
      var q = chip.querySelector('[data-fnp-chip-q]');
      var kindOfQ = li.getAttribute('data-qualifier') || '';
      if (kindOfQ === '') {
        q.parentNode.removeChild(q);
      } else {
        q.name = 'tag_qualifier[' + key + ']';
        q.className += ' fnp-q--' + kindOfQ;
        q.placeholder = root.getAttribute('data-fnp-ph-' + kindOfQ) || '';
        q.setAttribute('aria-label', (li.getAttribute('data-code') || key) + ' : ' + (root.getAttribute('data-fnp-ph-' + kindOfQ) || ''));
        if (kindOfQ === 'date') { q.type = 'datetime-local'; }
        if (kindOfQ === 'text' && key === 'rel-to') { q.value = root.getAttribute('data-fnp-relto') || ''; }
      }
      selected.appendChild(chip);
      bind(chip);
      if (quiet) { return; }
      refresh();
      if (q && q.parentNode) { q.focus(); }
    }

    // Gestion des fiches : la ligne du tableau donne la sélection à la fenêtre.
    root.fnpSet = function (keys) {
      chips().forEach(function (c) { c.parentNode.removeChild(c); });
      (keys || []).forEach(function (key) { add(key, true); });
      refresh();
    };

    chips().forEach(bind);
    options.forEach(function (li) {
      var btn = li.querySelector('[data-fnp-add]');
      if (!btn) { return; }
      btn.addEventListener('click', function () {
        var key = li.getAttribute('data-key');
        if (has(key)) {
          chips().forEach(function (c) { if (c.getAttribute('data-fnp-key') === key) { c.parentNode.removeChild(c); } });
          refresh();
        } else {
          add(key);
        }
      });
    });
    fams.forEach(function (f) {
      f.addEventListener('click', function () {
        family = f.getAttribute('data-fnp-fam');
        if (search) { search.value = ''; }
        refresh();
      });
    });
    if (search) {
      search.addEventListener('input', refresh);
      search.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
    }
    // Rédacteur : les suggestions suivent le type de fiche choisi.
    var form = root.closest('form');
    if (form) {
      all('input[name="note_kind"]', form).forEach(function (input) {
        input.addEventListener('change', function () { if (input.checked) { kind = input.value; refresh(); } });
        if (input.checked) { kind = input.value; }
      });
    }
    refresh();
  });

  // —— Listes filtrables (colonne de la fiche) ——
  all('[data-fnt-filter]').forEach(function (input) {
    var scope = document.getElementById(input.getAttribute('data-fnt-filter'));
    if (!scope) { return; }
    function run() {
      var term = fold(input.value).trim();
      var shown = 0;
      all('[data-fnt-group]', scope).forEach(function (group) {
        var inGroup = 0;
        all('[data-fnt-item]', group).forEach(function (item) {
          var on = term === '' || fold(item.getAttribute('data-search')).indexOf(term) !== -1;
          item.hidden = !on;
          if (on) { inGroup++; }
        });
        group.hidden = inGroup === 0;
        shown += inGroup;
      });
      var empty = scope.querySelector('[data-fnt-empty]');
      if (empty) { empty.hidden = shown > 0; }
    }
    input.addEventListener('input', run);
  });

  // —— Liste des fiches : le choix d'une famille réduit la liste des étiquettes ——
  all('[data-fnt-family-select]').forEach(function (familySelect) {
    var tagSelect = document.getElementById(familySelect.getAttribute('data-fnt-family-select'));
    if (!tagSelect) { return; }
    function run() {
      var family = familySelect.value;
      all('optgroup', tagSelect).forEach(function (group) {
        var on = family === '' || group.getAttribute('data-family') === family;
        group.hidden = !on;
        group.disabled = !on;
        if (!on && group.contains(tagSelect.options[tagSelect.selectedIndex])) { tagSelect.value = ''; }
      });
    }
    familySelect.addEventListener('change', run);
    run();
  });

  // —— Gestion des fiches : tableaux triables et filtrables ——
  all('[data-fng-table]').forEach(function (table) {
    var id = table.getAttribute('data-fng-table');
    var body = table.tBodies[0];
    if (!body) { return; }
    var rows = all('tr', body);
    var filters = all('[data-fng-filter="' + id + '"]');
    var counter = document.querySelector('[data-fng-count="' + id + '"]');
    var none = document.querySelector('[data-fng-none="' + id + '"]');

    function apply() {
      var shown = 0;
      rows.forEach(function (tr) {
        var on = true;
        filters.forEach(function (f) {
          var attr = f.getAttribute('data-fng-attr');
          if (f.value === '') { return; }
          if (attr) {
            if (tr.getAttribute('data-' + attr) !== f.value) { on = false; }
          } else if (fold(tr.getAttribute('data-search')).indexOf(fold(f.value).trim()) === -1) {
            on = false;
          }
        });
        tr.hidden = !on;
        if (on) { shown++; }
      });
      if (counter) { counter.textContent = String(shown); }
      if (none) { none.hidden = shown > 0; }
    }
    filters.forEach(function (f) { f.addEventListener(f.tagName === 'SELECT' ? 'change' : 'input', apply); });

    all('th', table).forEach(function (th, index) {
      var btn = th.querySelector('[data-fng-sort]');
      if (!btn) { return; }
      btn.addEventListener('click', function () {
        var dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
        all('th', table).forEach(function (o) { o.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        var numeric = btn.getAttribute('data-fng-sort') === 'num';
        rows.sort(function (a, b) {
          var x = a.cells[index] ? (a.cells[index].getAttribute('data-sort') || a.cells[index].textContent) : '';
          var y = b.cells[index] ? (b.cells[index].getAttribute('data-sort') || b.cells[index].textContent) : '';
          if (numeric) { return ((parseFloat(x) || 0) - (parseFloat(y) || 0)) * dir; }
          return fold(x).trim().localeCompare(fold(y).trim()) * dir;
        });
        rows.forEach(function (tr) { body.appendChild(tr); });
      });
    });
    apply();
  });

  // —— Gestion des fiches : une fenêtre sert à créer et à modifier, remplie depuis le bouton qui l'ouvre ——
  all('[data-fng-fill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = document.getElementById(btn.getAttribute('data-fnr-open') || '');
      if (!dlg) { return; }
      var source = btn.getAttribute('data-fng-fill') || '{}';
      var row = source === '@row' ? btn.closest('[data-fng-row]') : null;
      var data = parse(row ? row.getAttribute('data-fng-row') : source, {});
      var modes = String(data._mode || '').split(' ');
      all('[data-fng-when]', dlg).forEach(function (el) {
        var on = el.getAttribute('data-fng-when').split(' ').some(function (m) { return modes.indexOf(m) !== -1; });
        el.hidden = !on;
        var own = /^(INPUT|SELECT|TEXTAREA|OPTION|BUTTON)$/.test(el.tagName) ? [el] : [];
        own.concat(all('input, select, textarea', el)).forEach(function (field) { field.disabled = !on; });
      });
      all('[data-fng-out]', dlg).forEach(function (out) {
        var v = data[out.getAttribute('data-fng-out')];
        out.textContent = v === undefined || v === null ? '' : String(v);
      });
      Object.keys(data).forEach(function (name) {
        if (name.charAt(0) === '_') { return; }
        var value = data[name];
        all('[name="' + name + '"], [name="' + name + '[]"]', dlg).forEach(function (field) {
          if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = Array.isArray(value) ? value.indexOf(field.value) !== -1 : String(value) === field.value;
          } else if (field.multiple) {
            all('option', field).forEach(function (o) { o.selected = Array.isArray(value) && value.indexOf(o.value) !== -1; });
          } else {
            field.value = value === null || value === undefined ? '' : String(value);
          }
        });
      });
      all('[data-fnp][data-fnp-name]', dlg).forEach(function (picker) {
        var value = data[picker.getAttribute('data-fnp-name')];
        if (Array.isArray(value) && typeof picker.fnpSet === 'function') { picker.fnpSet(value); }
      });
      // Fusion : la liste des cibles ne garde que la famille de l'étiquette à fusionner.
      all('[data-fng-same-family]', dlg).forEach(function (select) {
        all('option', select).forEach(function (o) {
          var on = o.value === '' || (o.getAttribute('data-family') === data._family && o.value !== data.from);
          o.hidden = !on;
          o.disabled = !on;
        });
        select.value = '';
      });
    });
  });

  // —— Rédacteur : trame du type de fiche ——
  all('[data-fn-templates]').forEach(function (box) {
    var templates = parse(box.getAttribute('data-fn-templates') || '{}', {});
    var form = box.closest('form');
    var body = form ? form.querySelector('textarea[name="body"]') : null;
    var btn = box.querySelector('[data-fn-template-insert]');
    if (!form || !body || !btn) { return; }
    function current() {
      var input = form.querySelector('input[name="note_kind"]:checked');
      return input ? (templates[input.value] || '') : '';
    }
    function run() { box.hidden = current() === ''; }
    all('input[name="note_kind"]', form).forEach(function (input) { input.addEventListener('change', run); });
    btn.addEventListener('click', function () {
      var text = current();
      if (text === '') { return; }
      body.value = body.value.trim() === '' ? text : body.value.replace(/\s+$/, '') + '\n\n' + text;
      body.dispatchEvent(new Event('input', { bubbles: true }));
    });
    run();
  });
})();
