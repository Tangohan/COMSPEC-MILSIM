/**
 * Assistant de création de tenue : fenêtre de choix des objets d'un emplacement (recherche, filtres, vignettes,
 * sélection multiple, résumé, validation) et de l'emploi de départ (choix unique). Même composant visuel que les
 * référentiels d'emplois et de grades (.jobcat de job-catalog.css) : <dialog> natif, donc focus gardé dans la fenêtre,
 * Échap pour fermer, retour du focus sur le bouton d'origine.
 *
 * Sans JavaScript, la page reste utilisable : chaque emplacement garde sa liste déroulante (un objet) ; ce script la
 * remplace par un résumé et un bouton. Plusieurs objets pour un emplacement donnent plusieurs tenues (variantes), au
 * plus `max` en tout ; la page se recharge alors pour recalculer l'aperçu côté serveur.
 *
 * Les noms d'objets viennent du jeu : ils ne sont jamais insérés comme du HTML (textContent seulement).
 */
(function () {
  'use strict';

  var dialog = document.querySelector('[data-eqp-dialog]');
  var dataEl = document.getElementById('eqp-data');
  if (!dialog || !dataEl || typeof dialog.showModal !== 'function') return;

  var data;
  try {
    data = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    return;
  }
  var maxVariants = parseInt(data.max, 10) || 6;
  var MAX_ROWS = 80;

  function label(key) {
    var el = document.querySelector('[data-eqp-label="' + key + '"]');
    return el ? el.textContent.trim() : '';
  }
  function $(sel) {
    return dialog.querySelector(sel);
  }
  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text != null) node.textContent = text;
    return node;
  }
  function fold(text) {
    var out = '';
    var s = String(text || '');
    for (var i = 0; i < s.length; i++) {
      var d = s.charAt(i).normalize ? s.charAt(i).normalize('NFD').charAt(0) : s.charAt(i);
      out += d.toLowerCase();
    }
    return out;
  }

  var titleEl = $('[data-eqp-title]');
  var leadEl = $('#eqp-lead');
  var searchEl = $('[data-eqp-search]');
  var modEl = $('[data-eqp-mod]');
  var modLabelEl = modEl ? modEl.parentNode.querySelector('span') : null;
  var sortEl = $('[data-eqp-sort]');
  var prefEl = $('[data-eqp-pref]');
  var onlySelEl = $('[data-eqp-onlysel]');
  var countEl = $('[data-eqp-count]');
  var resultsEl = $('[data-eqp-results]');
  var summaryEl = $('[data-eqp-summary]');
  var summaryListEl = $('[data-eqp-summary-list]');
  var frameEl = $('.jobcat__frame');
  var filtersToggle = $('[data-eqp-filters-toggle]');
  var leadSlotText = leadEl ? leadEl.textContent : '';
  var searchSlotText = searchEl ? searchEl.getAttribute('placeholder') || '' : '';
  var modLabelSlotText = modLabelEl ? modLabelEl.textContent : '';

  // Choix validés par emplacement (copie de travail de la page ; la source de vérité reste le serveur au rechargement).
  var committed = {};
  Object.keys(data.slots || {}).forEach(function (k) {
    committed[k] = (data.slots[k].chosen || []).slice();
  });

  var cur = null; // { kind: 'slot'|'role', key, items, selected: [], opener, single }
  var notice = '';

  function itemByClass(items, cls) {
    var low = String(cls).toLowerCase();
    for (var i = 0; i < items.length; i++) {
      if (String(items[i].c).toLowerCase() === low) return items[i];
    }
    return null;
  }

  function othersProduct(exceptKey) {
    var product = 1;
    Object.keys(committed).forEach(function (k) {
      if (k !== exceptKey) product *= Math.max(1, committed[k].length);
    });
    return product;
  }

  /* ---------- Vignettes ---------- */
  function thumb(item) {
    var box = el('span', 'eqp-thumb');
    if (item && item.i) {
      var img = document.createElement('img');
      img.src = item.i;
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      box.appendChild(img);
    } else {
      var letter = (item && item.n ? String(item.n).replace(/[^A-Za-z0-9]/g, '').charAt(0) : '') || '?';
      box.appendChild(el('span', 'eqp-thumb__ph', letter.toUpperCase()));
    }
    return box;
  }

  /* ---------- Emplacements de la page ---------- */
  var form = document.querySelector('[data-eqp-form]');
  var slotBoxes = {};

  function renderSlot(key) {
    var box = slotBoxes[key];
    if (!box) return;
    var info = data.slots[key];
    var list = box.chosenList;
    list.innerHTML = '';
    var chosen = committed[key] || [];
    if (!chosen.length) {
      list.appendChild(el('li', 'eqp-chosen__none', label('none')));
    }
    chosen.forEach(function (cls) {
      var it = itemByClass(info.items, cls) || { c: cls, n: cls, i: '' };
      var li = el('li', 'eqp-chosen__item');
      li.appendChild(thumb(it));
      li.appendChild(el('span', 'eqp-chosen__name', it.n));
      list.appendChild(li);
    });
    box.inputs.innerHTML = '';
    (chosen.length ? chosen : ['']).forEach(function (cls) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'slot[' + key + '][]';
      input.value = cls;
      box.inputs.appendChild(input);
    });
    box.openBtn.firstChild.nodeValue = chosen.length ? label('change') : label('choose');
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-eqp-slot]'), function (slotEl) {
    var key = slotEl.getAttribute('data-eqp-slot');
    var info = data.slots && data.slots[key];
    var chooser = slotEl.querySelector('[data-eqp-chooser]');
    var select = slotEl.querySelector('[data-eqp-fallback]');
    if (!info || !chooser || !select || !info.items.length) return;
    // La liste déroulante de repli est remplacée par le résumé et le bouton. Le nom de l'emplacement est lu dans la page
    // (déjà traduit), pas dans les données.
    select.disabled = true;
    select.hidden = true;
    var lab = slotEl.querySelector('label[for]');
    if (lab) {
      var first = lab.firstChild;
      if (first && first.nodeType === 3 && first.textContent.trim()) info.label = first.textContent.trim();
      lab.removeAttribute('for');
    }
    chooser.hidden = false;
    var openBtn = chooser.querySelector('[data-eqp-open]');
    var hiddenName = el('span', 'visually-hidden', ' : ' + info.label);
    openBtn.textContent = label('choose');
    openBtn.appendChild(hiddenName);
    slotBoxes[key] = {
      chosenList: chooser.querySelector('[data-eqp-chosen]'),
      inputs: slotEl.querySelector('[data-eqp-inputs]'),
      openBtn: openBtn,
    };
    renderSlot(key);
    openBtn.addEventListener('click', function () {
      openPicker({ kind: 'slot', key: key, opener: openBtn });
    });
  });

  /* ---------- Emploi de départ (choix unique) ---------- */
  var roleSelect = document.querySelector('[data-eqp-role]');
  var roleForm = document.querySelector('[data-eqp-role-form]');
  var roleBtn = document.querySelector('[data-eqp-role-open]');
  if (roleSelect && roleForm && roleBtn && data.role && data.role.items.length) {
    roleBtn.hidden = false;
    roleSelect.hidden = true;
    // L'intitulé de la liste reste affiché au-dessus du bouton (lu dans la page, déjà traduit).
    var roleLab = roleSelect.closest('label');
    if (roleLab) {
      var cap = el('span', 'eqp-rolecap', (roleLab.firstChild && roleLab.firstChild.nodeType === 3 ? roleLab.firstChild.textContent : '').trim());
      cap.id = 'eqp-role-cap';
      roleBtn.id = 'eqp-role-btn';
      roleBtn.setAttribute('aria-labelledby', 'eqp-role-cap eqp-role-btn');
      roleBtn.parentNode.insertBefore(cap, roleBtn);
      roleLab.hidden = true;
    }
    var syncRoleBtn = function () {
      var it = roleSelect.value && roleSelect.value !== '0' ? itemByClass(data.role.items, roleSelect.value) : null;
      roleBtn.textContent = it ? it.n : label('role-choose');
      roleBtn.setAttribute('translate', it ? 'no' : 'yes');
    };
    syncRoleBtn();
    roleBtn.addEventListener('click', function () {
      openPicker({ kind: 'role', key: 'role', opener: roleBtn });
    });
  }

  /* ---------- Fenêtre de choix ---------- */
  function openPicker(spec) {
    var isRole = spec.kind === 'role';
    var info = isRole ? data.role : data.slots[spec.key];
    cur = {
      kind: spec.kind,
      key: spec.key,
      items: info.items,
      single: isRole,
      opener: spec.opener,
      selected: isRole
        ? (roleSelect && roleSelect.value && roleSelect.value !== '0' ? [roleSelect.value] : [])
        : (committed[spec.key] || []).slice(),
      limit: isRole ? 1 : Math.max(1, Math.floor(maxVariants / othersProduct(spec.key))),
    };
    notice = '';
    titleEl.textContent = isRole ? label('title-role') : label('title-slot') + ' ' + info.label;
    if (leadEl) leadEl.textContent = isRole ? label('lead-role') : leadSlotText;
    if (modLabelEl) modLabelEl.textContent = isRole ? label('category') : modLabelSlotText;
    searchEl.setAttribute('placeholder', isRole ? label('search-role') : searchSlotText);
    // Filtres : mods ou catégories présents, tri, « conseillés » (emplacements seulement).
    modEl.innerHTML = '';
    var all = document.createElement('option');
    all.value = '';
    all.textContent = label('all-mods');
    modEl.appendChild(all);
    var seen = {};
    info.items.forEach(function (it) {
      var m = it.m || '';
      if (m && !seen[m]) seen[m] = true;
    });
    Object.keys(seen).sort(function (a, b) { return a.localeCompare(b); }).forEach(function (m) {
      var o = document.createElement('option');
      o.value = m;
      o.textContent = m;
      modEl.appendChild(o);
    });
    modEl.value = '';
    sortEl.value = isRole ? 'name' : 'players';
    sortEl.querySelector('option[value="players"]').hidden = isRole;
    prefEl.checked = false;
    onlySelEl.checked = false;
    prefEl.parentNode.hidden = isRole || !info.items.some(function (it) { return it.r; });
    onlySelEl.parentNode.hidden = isRole;
    searchEl.value = '';
    if (frameEl) frameEl.classList.remove('is-filters-open');
    if (filtersToggle) filtersToggle.setAttribute('aria-expanded', 'false');
    renderList();
    renderSummary();
    dialog.showModal();
    searchEl.focus();
  }

  function visibleItems() {
    var q = fold(searchEl.value.trim());
    var mod = modEl.value;
    var rows = cur.items.filter(function (it) {
      if (mod && it.m !== mod) return false;
      if (!cur.single && prefEl.checked && !it.r) return false;
      if (!cur.single && onlySelEl.checked && cur.selected.map(String).indexOf(String(it.c)) < 0) return false;
      if (q && fold(it.n + ' ' + it.c + ' ' + it.m).indexOf(q) < 0) return false;
      return true;
    });
    if (sortEl.value === 'name') {
      rows = rows.slice().sort(function (a, b) { return String(a.n).localeCompare(String(b.n)); });
    }
    return rows;
  }

  function highlight(node, text, q) {
    var start = q ? fold(text).indexOf(q) : -1;
    if (start < 0) {
      node.textContent = text;
      return;
    }
    node.appendChild(document.createTextNode(text.slice(0, start)));
    node.appendChild(el('mark', '', text.slice(start, start + q.length)));
    node.appendChild(document.createTextNode(text.slice(start + q.length)));
  }

  function isSelected(cls) {
    return cur.selected.map(String).indexOf(String(cls)) >= 0;
  }

  function renderList() {
    var rows = visibleItems();
    var q = fold(searchEl.value.trim());
    resultsEl.innerHTML = '';
    rows.slice(0, MAX_ROWS).forEach(function (it, idx) {
      var li = el('li', 'eqp-row');
      var lab = el('label', 'eqp-row__label');
      var input = document.createElement('input');
      input.type = cur.single ? 'radio' : 'checkbox';
      input.name = 'eqp-choice';
      input.value = it.c;
      input.checked = isSelected(it.c);
      input.setAttribute('data-eqp-row', String(idx));
      lab.appendChild(input);
      lab.appendChild(thumb(it));
      var text = el('span', 'eqp-row__text');
      var name = el('strong');
      highlight(name, String(it.n), q);
      text.appendChild(name);
      var meta = el('small');
      var bits = [];
      if (it.p > 0) bits.push(it.p + ' ' + (it.p > 1 ? label('players') : label('player')));
      if (it.m) bits.push(it.m);
      meta.textContent = bits.join(' · ');
      text.appendChild(meta);
      lab.appendChild(text);
      if (it.r) lab.appendChild(el('span', 'jobcat__chip is-abbr', label('recommended')));
      li.appendChild(lab);
      li.__item = it;
      resultsEl.appendChild(li);
    });
    if (!rows.length) {
      resultsEl.appendChild(el('li', 'eqp-empty', label('empty')));
    }
    var more = rows.length - MAX_ROWS;
    countEl.textContent = rows.length + ' ' + (rows.length > 1 ? label('results') : label('result')) + (more > 0 ? ' · ' + more + ' ' + label('more') : '');
  }

  function renderSummary() {
    var n = cur.selected.length;
    var text;
    if (cur.single) {
      text = n ? '' : label('none-role');
    } else {
      var total = othersProduct(cur.key) * Math.max(1, n);
      text = n + ' ' + label('chosen') + ' · ' + (total > 1 ? label('variants').replace('%n', String(total)) : label('one'));
    }
    if (notice) text = notice + ' ' + text;
    summaryEl.textContent = text.trim();
    summaryListEl.innerHTML = '';
    cur.selected.forEach(function (cls) {
      var it = itemByClass(cur.items, cls) || { c: cls, n: cls, i: '' };
      var li = el('li', 'eqp-pill');
      li.appendChild(thumb(it));
      li.appendChild(el('span', 'eqp-pill__name', it.n));
      var rm = el('button', 'eqp-pill__x', '×');
      rm.type = 'button';
      rm.setAttribute('aria-label', label('remove') + ' ' + it.n);
      rm.addEventListener('click', function () {
        cur.selected = cur.selected.filter(function (c) { return String(c) !== String(cls); });
        notice = '';
        renderList();
        renderSummary();
      });
      li.appendChild(rm);
      summaryListEl.appendChild(li);
    });
  }

  resultsEl.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.name !== 'eqp-choice' || !cur) return;
    var cls = input.value;
    notice = '';
    if (cur.single) {
      cur.selected = [cls];
    } else if (input.checked) {
      if (cur.selected.length >= cur.limit) {
        input.checked = false;
        notice = label('limit').replace('%n', String(cur.limit)).replace('%max', String(maxVariants));
      } else {
        cur.selected.push(cls);
      }
    } else {
      cur.selected = cur.selected.filter(function (c) { return String(c) !== String(cls); });
    }
    renderSummary();
  });

  var refilter = function () {
    if (cur) renderList();
  };
  var timer = null;
  searchEl.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(refilter, 120);
  });
  [modEl, sortEl, prefEl, onlySelEl].forEach(function (c) {
    c.addEventListener('change', refilter);
  });
  if (filtersToggle) {
    filtersToggle.addEventListener('click', function () {
      var open = !frameEl.classList.contains('is-filters-open');
      frameEl.classList.toggle('is-filters-open', open);
      filtersToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  function closePicker() {
    if (dialog.open) dialog.close();
  }
  $('[data-eqp-close]').addEventListener('click', closePicker);
  $('[data-eqp-cancel]').addEventListener('click', closePicker);
  $('[data-eqp-clear]').addEventListener('click', function () {
    if (!cur) return;
    cur.selected = [];
    notice = '';
    renderList();
    renderSummary();
  });
  dialog.addEventListener('click', function (e) {
    if (e.target === dialog) closePicker();
  });
  dialog.addEventListener('close', function () {
    var opener = cur && cur.opener;
    cur = null;
    if (opener && opener.focus) opener.focus();
  });

  $('[data-eqp-validate]').addEventListener('click', function () {
    if (!cur) return;
    var spec = cur;
    var changed;
    if (spec.kind === 'role') {
      var next = spec.selected[0] || '0';
      changed = next !== (roleSelect.value || '0');
      if (changed) roleSelect.value = next;
    } else {
      var before = (committed[spec.key] || []).join('|');
      committed[spec.key] = spec.selected.slice();
      changed = before !== committed[spec.key].join('|');
      renderSlot(spec.key);
    }
    closePicker();
    // Le serveur recalcule l'aperçu, ce qui manque et le rangement : la page se recharge sur la même étape.
    if (changed) {
      var target = spec.kind === 'role' ? roleForm : form;
      if (target) {
        if (target.requestSubmit) target.requestSubmit();
        else target.submit();
      }
    }
  });
})();
