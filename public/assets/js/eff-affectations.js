/**
 * Effectifs — page Affectations : recherche et filtres instantanés, vue par unité (tableau) ou par membre.
 * Le filtre « unité » garde aussi les sous-unités (leur code commence par celui de l'unité).
 * Mutation : glisser une carte vers une autre colonne, ou son bouton ⇄, ouvre la fenêtre de confirmation ;
 * l'envoi passe par le formulaire habituel (POST membres/{id}/affectation, jeton CSRF).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-eff-aff]');
  if (!root) return;
  var form = root.querySelector('[data-eff-aff-filters]');
  var q = root.querySelector('[data-eff-aff-q]');
  var unit = root.querySelector('[data-eff-aff-unit]');
  var job = root.querySelector('[data-eff-aff-job]');
  var role = root.querySelector('[data-eff-aff-role]');
  var access = root.querySelector('[data-eff-aff-access]');
  var gaps = root.querySelectorAll('[data-eff-aff-gap]');
  var count = root.querySelector('[data-eff-aff-count]');
  var tip = root.querySelector('[data-eff-aff-tip]');
  var rows = Array.prototype.slice.call(root.querySelectorAll('[data-eff-aff-row]'));
  var cards = Array.prototype.slice.call(root.querySelectorAll('[data-eff-aff-card]'));
  var cols = Array.prototype.slice.call(root.querySelectorAll('[data-eff-aff-col]'));
  var noneMembers = root.querySelector('[data-eff-aff-none]');
  var noneUnits = root.querySelector('[data-eff-aff-none-units]');
  var onBtn = root.querySelector('[data-eff-aff-view].is-on');
  var view = onBtn ? onBtn.getAttribute('data-eff-aff-view') : 'units';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function inUnit(code, base) {
    return !base || code === base || code.indexOf(base + '-') === 0;
  }

  function apply() {
    var terms = norm(q ? q.value : '').split(/\s+/).filter(Boolean);
    var base = unit ? unit.value : '';
    var j = job ? job.value : '';
    var r = role ? role.value : '';
    var a = access ? access.value : '';
    var needUnit = false;
    var needJob = false;
    gaps.forEach(function (g) {
      if (g.checked && g.value === 'unit') needUnit = true;
      if (g.checked && g.value === 'job') needJob = true;
    });
    if (unit) unit.classList.toggle('is-set', base !== '');
    var memberFilter = terms.length > 0 || j || r || a || needUnit || needJob;

    function match(el) {
      var hay = norm(el.getAttribute('data-search'));
      var code = el.getAttribute('data-code') || '';
      var jobs = el.getAttribute('data-jobs') || '||';
      return terms.every(function (t) { return hay.indexOf(t) !== -1; })
        && inUnit(code, base)
        && (!j || jobs.indexOf('|' + j + '|') !== -1)
        && (!r || el.getAttribute('data-role') === r)
        && (!a || (el.getAttribute('data-access') || '').indexOf('|' + a + '|') !== -1)
        && (!needUnit || code === '')
        && (!needJob || jobs === '||');
    }

    var shown = 0;
    rows.forEach(function (row) {
      var ok = match(row);
      row.hidden = !ok;
      if (ok) shown++;
    });

    // Tableau par unité : cartes estompées, colonnes hors filtre retirées.
    var shownCards = 0;
    var shownCols = 0;
    cols.forEach(function (col) {
      var code = col.getAttribute('data-code') || '';
      var isNoUnit = col.getAttribute('data-unit') === '0';
      var inBranch = isNoUnit ? !base : inUnit(code, base);
      var hits = 0;
      col.querySelectorAll('[data-eff-aff-card]').forEach(function (c) {
        var ok = match(c);
        c.classList.toggle('is-dim', !!memberFilter && !ok);
        if (ok) hits++;
      });
      var hay = norm(col.getAttribute('data-search'));
      var unitHit = terms.length > 0 && !j && !r && !a && !needUnit && !needJob
        && terms.every(function (t) { return hay.indexOf(t) !== -1; });
      var visible = inBranch && (!memberFilter || hits > 0 || unitHit) && !(needUnit && !isNoUnit);
      col.hidden = !visible;
      if (visible) {
        shownCols++;
        shownCards += hits;
      }
    });

    if (noneMembers) noneMembers.hidden = shown > 0 || rows.length === 0;
    if (noneUnits) noneUnits.hidden = shownCols > 0 || cols.length === 0;
    if (count) {
      count.innerHTML = view === 'units'
        ? '<b>' + shownCards + '</b> membre' + (shownCards > 1 ? 's' : '') + ' dans <b>' + shownCols + '</b> colonne' + (shownCols > 1 ? 's' : '')
        : '<b>' + shown + '</b> membre' + (shown > 1 ? 's' : '') + ' sur ' + rows.length;
    }
  }

  function setView(next) {
    view = next;
    root.querySelectorAll('[data-eff-aff-view]').forEach(function (b) {
      var on = b.getAttribute('data-eff-aff-view') === next;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-eff-aff-pane]').forEach(function (pane) {
      pane.hidden = pane.getAttribute('data-eff-aff-pane') !== next;
    });
    if (tip) tip.hidden = next !== 'units';
    try {
      var url = new URL(window.location.href);
      url.searchParams.delete('vue');
      if (next === 'members') url.searchParams.set('vue', 'membres');
      window.history.replaceState(null, '', url.toString());
    } catch (e) { /* adresse inchangée */ }
    apply();
  }

  if (form) {
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    form.addEventListener('input', apply);
    form.addEventListener('change', apply);
  }
  root.querySelectorAll('[data-eff-aff-view]').forEach(function (b) {
    b.addEventListener('click', function () { setView(b.getAttribute('data-eff-aff-view')); });
  });

  // Codes des unités : ouvert quand l'adresse y mène (retour d'enregistrement).
  var codes = document.getElementById('eff-aff-codes');
  if (codes && window.location.hash === '#eff-aff-codes') codes.open = true;

  apply();
  var target = root.querySelector('.effk-col.is-target');
  if (target) target.scrollIntoView({ block: 'center' });

  // ——— Mutation ———
  var dataEl = document.getElementById('eff-aff-data');
  var dialog = root.querySelector('[data-eff-aff-dialog]');
  var K = window.EffKit;
  if (!dataEl || !dialog || !K || typeof dialog.showModal !== 'function') return;
  var data;
  try {
    data = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    return;
  }
  var units = data.units || {};
  var members = data.members || {};
  var moveForm = dialog.querySelector('[data-eff-aff-move-form]');
  var moveUnit = dialog.querySelector('[data-eff-aff-move-unit]');
  var moveReturn = dialog.querySelector('[data-eff-aff-move-return]');
  var reason = dialog.querySelector('textarea[name="reason"]');
  var returnBase = moveReturn ? moveReturn.value.split('#')[0].split('?')[0] : '';
  var lastFocus = null;

  function unitInfo(id) {
    if (!id) return { name: 'Sans unité', color: data.noUnitColor || '#d97706', count: 0, seats: 0, chef: 0 };
    return units[id] || { name: 'Unité', color: '#64748b', count: 0, seats: 0, chef: 0 };
  }
  function note(tone, text) {
    return '<p class="effk-note is-' + tone + '">' + K.esc(text) + '</p>';
  }

  function ask(memberId, toId) {
    var m = members[memberId];
    if (!m) return;
    var fromId = m.unit || 0;
    if (fromId === toId) return;
    var from = unitInfo(fromId);
    var to = unitInfo(toId);
    var name = m.name + (m.callsign ? ' « ' + m.callsign + ' »' : '');
    dialog.querySelector('[data-eff-aff-move-title]').textContent = toId ? 'Muter ' + m.name : 'Retirer l’affectation de ' + m.name;
    dialog.querySelector('[data-eff-aff-move-who]').innerHTML = K.avatar(m.initials, m.color, 'lg')
      + '<div><b>' + K.esc((m.grade ? m.grade + ' ' : '') + name) + '</b><small>' + K.esc((m.role ? m.role + ' · ' : '') + from.name) + '</small></div>';
    var fromEl = dialog.querySelector('[data-eff-aff-move-from]');
    var toEl = dialog.querySelector('[data-eff-aff-move-to]');
    fromEl.style.setProperty('--c', from.color);
    toEl.style.setProperty('--c', to.color);
    fromEl.querySelector('b').textContent = from.name;
    toEl.querySelector('b').textContent = to.name;
    var notes = '';
    if (toId && to.seats > 0 && to.count >= to.seats) {
      notes += note('warn', to.name + ' compte déjà ' + to.count + ' titulaires pour ' + to.seats + ' postes prévus à l’ORBAT : la mutation dépassera l’effectif prévu.');
    }
    if (fromId && from.chef === memberId) {
      notes += note('warn', m.name + ' est chef de ' + from.name + ' : il le reste tant qu’un autre chef n’est pas désigné dans la chaîne de commandement.');
    }
    if (toId && m.role && m.role !== 'Membre') {
      notes += note('info', 'Son rôle dans l’unité (« ' + m.role + ' ») est conservé ; modifiez-le ensuite depuis sa fiche si besoin.');
    }
    if (!toId) {
      notes += note('info', m.name + ' restera actif, sans unité ni chef, jusqu’à sa prochaine affectation.');
    }
    dialog.querySelector('[data-eff-aff-move-notes]').innerHTML = notes;
    dialog.querySelector('[data-eff-aff-move-submit]').textContent = toId ? 'Confirmer la mutation' : 'Retirer l’affectation';
    moveForm.action = String(moveForm.getAttribute('data-action') || '').replace('__ID__', String(memberId));
    moveUnit.value = String(toId);
    if (moveReturn) moveReturn.value = returnBase + '#unite-' + toId;
    reason.value = '';
    lastFocus = document.activeElement;
    dialog.showModal();
    window.setTimeout(function () { reason.focus(); }, 30);
  }

  dialog.addEventListener('click', function (e) {
    if (e.target.closest('[data-eff-aff-close]') || e.target === dialog) dialog.close();
  });
  dialog.addEventListener('close', function () {
    if (lastFocus && document.contains(lastFocus)) lastFocus.focus();
  });
  moveForm.addEventListener('submit', function () {
    var b = moveForm.querySelector('[data-eff-aff-move-submit]');
    if (b) b.disabled = true;
  });

  // Glisser-déposer.
  var dragId = 0;
  var board = root.querySelector('[data-eff-aff-board]');
  function clearOver() {
    cols.forEach(function (c) { c.classList.remove('is-over', 'is-full'); });
  }
  if (board) {
    board.addEventListener('dragstart', function (e) {
      var c = e.target.closest('[data-eff-aff-card][draggable="true"]');
      if (!c) return;
      dragId = parseInt(c.getAttribute('data-member'), 10) || 0;
      c.classList.add('is-drag');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(dragId));
    });
    board.addEventListener('dragend', function () {
      cards.forEach(function (c) { c.classList.remove('is-drag'); });
      clearOver();
      dragId = 0;
    });
    board.addEventListener('dragover', function (e) {
      var col = e.target.closest('[data-eff-aff-col]');
      if (!col || !dragId) return;
      e.preventDefault();
      var to = parseInt(col.getAttribute('data-unit'), 10) || 0;
      cols.forEach(function (c) { if (c !== col) c.classList.remove('is-over', 'is-full'); });
      if ((members[dragId] || {}).unit !== to) {
        col.classList.add('is-over');
        col.classList.toggle('is-full', col.hasAttribute('data-full'));
      }
    });
    board.addEventListener('dragleave', function (e) {
      var col = e.target.closest('[data-eff-aff-col]');
      if (col && !col.contains(e.relatedTarget)) col.classList.remove('is-over', 'is-full');
    });
    board.addEventListener('drop', function (e) {
      var col = e.target.closest('[data-eff-aff-col]');
      if (!col || !dragId) return;
      e.preventDefault();
      clearOver();
      ask(dragId, parseInt(col.getAttribute('data-unit'), 10) || 0);
    });
  }

  // Bouton ⇄ : liste des unités de destination.
  var menu = root.querySelector('[data-eff-aff-menu]');
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-eff-aff-move]');
    if (!b || !menu) return;
    var id = parseInt(b.getAttribute('data-eff-aff-move'), 10);
    var m = members[id];
    if (!m) return;
    menu.querySelector('[data-eff-aff-menu-title]').textContent = 'Muter ' + m.name + ' vers';
    var list = menu.querySelector('[data-eff-aff-menu-list]');
    var html = '';
    cols.forEach(function (col) {
      var uid = parseInt(col.getAttribute('data-unit'), 10) || 0;
      if (uid === (m.unit || 0)) return;
      var u = unitInfo(uid);
      html += '<button type="button" class="effk-pi" data-to="' + uid + '"><span class="effk-pi__sw" style="background:' + K.esc(u.color) + '"></span><span>'
        + K.esc(u.name) + '</span><small>' + (uid ? u.count + (u.seats ? ' / ' + u.seats : '') : '') + '</small></button>';
    });
    list.innerHTML = html || '<p class="effk-pop__none">Aucune autre unité.</p>';
    list.onclick = function (ev) {
      var t = ev.target.closest('[data-to]');
      if (!t) return;
      K.closePop(false);
      ask(id, parseInt(t.getAttribute('data-to'), 10) || 0);
    };
    K.pop(menu, b);
  });
})();
