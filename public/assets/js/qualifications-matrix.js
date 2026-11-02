/*
 * Effectifs > Qualifications : filtres de la matrice, mise en évidence par état et fenêtre d'édition d'une case
 * (délivrer, mettre en formation, retirer). Les textes viennent du gabarit de la page (traduits côté serveur).
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-qmx]');
  if (!root) return;
  var table = root.querySelector('[data-qmx-table]');
  var q = root.querySelector('[data-qmx-q]');
  var unitSel = root.querySelector('[data-qmx-unit]');
  var catSel = root.querySelector('[data-qmx-cat]');
  var hint = root.querySelector('[data-qmx-hint]');
  var none = root.querySelector('[data-qmx-none]');
  var tpl = root.querySelector('[data-qmx-pop]');
  var mode = '';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function filter() {
    if (!table) return;
    var term = norm(q ? q.value.trim() : '');
    var unit = unitSel ? unitSel.value : '';
    var cat = catSel ? catSel.value : '';
    var shownByGroup = {};
    var shown = 0;
    Array.prototype.forEach.call(table.querySelectorAll('[data-qmx-row]'), function (tr) {
      var ok = (!term || norm(tr.getAttribute('data-search')).indexOf(term) !== -1) && (!unit || tr.getAttribute('data-unit') === unit);
      tr.hidden = !ok;
      if (ok) {
        shown++;
        shownByGroup[tr.getAttribute('data-unit')] = true;
      }
    });
    Array.prototype.forEach.call(table.querySelectorAll('[data-qmx-group]'), function (tr) {
      tr.hidden = !shownByGroup[tr.getAttribute('data-qmx-group')];
    });
    Array.prototype.forEach.call(table.querySelectorAll('th[data-cat], td[data-cat]'), function (c) {
      c.hidden = !!cat && c.getAttribute('data-cat') !== cat;
    });
    if (none) none.hidden = shown !== 0;
  }

  Array.prototype.forEach.call(root.querySelectorAll('[data-qmx-mode]'), function (b) {
    b.addEventListener('click', function () {
      var m = b.getAttribute('data-qmx-mode');
      mode = mode === m ? '' : m;
      Array.prototype.forEach.call(root.querySelectorAll('[data-qmx-mode]'), function (o) {
        o.setAttribute('aria-pressed', o.getAttribute('data-qmx-mode') === mode ? 'true' : 'false');
        o.classList.toggle('on', o.getAttribute('data-qmx-mode') === mode);
      });
      if (table) {
        if (mode) table.setAttribute('data-mode', mode); else table.removeAttribute('data-mode');
      }
      if (hint) {
        hint.hidden = !mode;
        Array.prototype.forEach.call(hint.querySelectorAll('[data-qmx-hint-for]'), function (s) {
          s.hidden = s.getAttribute('data-qmx-hint-for') !== mode;
        });
      }
    });
  });
  if (q) q.addEventListener('input', filter);
  if (unitSel) unitSel.addEventListener('change', filter);
  if (catSel) catSel.addEventListener('change', filter);

  /* ——— Fenêtre d'édition d'une case ——— */
  var pop = null;
  var anchor = null;

  function closePop() {
    if (pop) pop.classList.remove('on');
    anchor = null;
  }

  function setTarget(form, target) {
    Array.prototype.forEach.call(form.querySelectorAll('[data-qp-when]'), function (part) {
      var on = part.getAttribute('data-qp-when') === target;
      part.hidden = !on;
      Array.prototype.forEach.call(part.querySelectorAll('input, textarea, select'), function (f) { f.disabled = !on; });
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="target"]'), function (r) {
      r.checked = r.value === target;
      r.closest('label').classList.toggle('on', r.value === target);
    });
  }

  function place() {
    if (!pop || !anchor) return;
    var r = anchor.getBoundingClientRect();
    var w = Math.min(320, window.innerWidth - 16);
    pop.style.width = w + 'px';
    var top = r.bottom + 6;
    var ph = pop.offsetHeight;
    if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 6);
    pop.style.top = top + 'px';
    pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
  }

  function openPop(cell) {
    if (!tpl) return;
    if (!pop) {
      pop = document.createElement('div');
      pop.className = 'qmx-pop';
      document.body.appendChild(pop);
    }
    pop.innerHTML = '';
    pop.appendChild(tpl.content.cloneNode(true));
    anchor = cell;
    var form = pop.querySelector('form');
    var row = cell.closest('tr');
    var th = table.querySelector('th[data-col="' + cell.getAttribute('data-def') + '"]');
    var state = cell.getAttribute('data-state');
    var manual = !th || th.getAttribute('data-manual') === '1';
    var av = row.querySelector('.qmx-av');
    var avOut = form.querySelector('[data-qp-av]');
    avOut.textContent = av ? av.textContent : '';
    if (av) avOut.style.background = av.style.background;
    var cs = row.querySelector('[data-qmx-cs]');
    form.querySelector('[data-qp-title]').textContent = (cs ? cs.textContent : '') + ' · ' + (th ? th.getAttribute('data-code') : '');
    var name = th ? th.querySelector('[data-qmx-name]') : null;
    form.querySelector('[data-qp-sub]').textContent = name ? name.textContent : '';
    form.querySelector('[data-qp-status]').textContent = cell.getAttribute('title') || '';
    form.querySelector('[data-qp-user]').value = cell.getAttribute('data-user');
    form.querySelector('[data-qp-def]').value = cell.getAttribute('data-def');
    var fiche = form.querySelector('[data-qp-fiche]');
    if (fiche && th) fiche.href = th.querySelector('a').href;
    var valid = th ? th.querySelector('[data-qmx-valid]') : null;
    if (valid) {
      form.querySelector('[data-qp-valid]').hidden = false;
      form.querySelector('[data-qp-valid-v]').textContent = valid.textContent;
    }
    var date = form.querySelector('[data-qp-date]');
    var today = new Date();
    date.value = cell.getAttribute('data-obtained') && (state === 'ok') ? cell.getAttribute('data-obtained') : today.toISOString().slice(0, 10);
    if (!manual) {
      Array.prototype.forEach.call(form.querySelectorAll('[data-qp-manual]'), function (l) { l.hidden = true; });
      form.querySelector('[data-qp-manual-off]').hidden = false;
    }
    var initial = state === 'ok' || state === 'soon' || state === 'exp' ? 'ok' : (state === 'train' ? 'train' : 'none');
    if (!manual) initial = 'none';
    else if (state === 'none' || state === 'off') initial = 'ok';
    setTarget(form, initial);
    form.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'target') setTarget(form, e.target.value);
    });
    pop.classList.add('on');
    place();
    var first = form.querySelector('input[name="target"]:checked');
    if (first) first.focus();
  }

  if (table) {
    table.addEventListener('click', function (e) {
      var cell = e.target.closest('[data-qmx-cell]');
      if (!cell) return;
      if (anchor === cell) { closePop(); return; }
      openPop(cell);
    });
  }
  document.addEventListener('mousedown', function (e) {
    if (pop && pop.classList.contains('on') && !pop.contains(e.target) && !e.target.closest('[data-qmx-cell]')) closePop();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePop();
  });
  window.addEventListener('resize', place);
  window.addEventListener('scroll', place, true);
  filter();
})();
