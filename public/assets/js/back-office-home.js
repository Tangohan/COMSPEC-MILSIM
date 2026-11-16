/**
 * Accueil du back-office, mode « Personnaliser » (views/admin/organization/_home_widgets.php).
 *
 * Réordonner : poignée à glisser (souris, stylet, doigt) ou au clavier (Entrée/Espace pour saisir, flèches pour
 * déplacer, Entrée pour déposer, Échap pour annuler) ; boutons monter/descendre sur téléphone. Redimensionner : coin
 * à tirer ou boutons +/- de largeur et de hauteur, par pas de grille, bornés par le widget. Masquer, ajouter (tiroir
 * avec recherche), réinitialiser, annuler, enregistrer (JSON v2 validé par le serveur, jeton CSRF en en-tête).
 * Placement : même calcul que App\Services\BackOffice\Home\HomeLayout (placement dense puis comblement, repli en
 * bandes) : la grille reste sans trou pendant l'édition. Tous les textes viennent de la page ([data-dw-msgs]).
 */
(function () {
  'use strict';

  var grid = document.querySelector('[data-dw-edit]');
  if (!grid) return;
  var bar = document.querySelector('[data-dw-bar]');
  var live = bar ? bar.querySelector('[data-dw-live]') : null;
  var drawer = document.getElementById('dw-add');
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var COLS = 12, COLS_TABLET = 6, COLS_MOBILE = 2;
  var dirty = false;

  function msg(key, args) {
    var el = bar ? bar.querySelector('[data-msg="' + key + '"]') : null;
    var text = el ? el.textContent.trim() : key;
    (args || []).forEach(function (a, i) { text = text.split('%' + (i + 1)).join(String(a)); });
    return text;
  }
  function say(text) {
    if (!live) return;
    live.textContent = '';
    window.setTimeout(function () { live.textContent = text; }, 30);
  }

  // ——— Modèle : ordre choisi par la personne (data-order), tailles en colonnes de la grille de 12 ———
  var items = Array.prototype.slice.call(grid.querySelectorAll('.dw[data-widget]')).map(function (el) {
    var n = function (k) { return parseInt(el.getAttribute('data-' + k), 10) || 1; };
    return {
      el: el, id: el.getAttribute('data-widget'), label: el.getAttribute('data-label') || '',
      w: n('w'), h: n('h'), minW: n('min-w'), minH: n('min-h'), maxW: n('max-w'), maxH: n('max-h'),
      hidden: el.hasAttribute('hidden'), order: parseInt(el.getAttribute('data-order'), 10) || 0
    };
  }).sort(function (a, b) { return a.order - b.order; });
  function find(id) { for (var i = 0; i < items.length; i++) if (items[i].id === id) return items[i]; return null; }
  function visible() { return items.filter(function (it) { return !it.hidden; }); }
  function posOf(it) { return visible().indexOf(it) + 1; }

  // Contenu des widgets inerte pendant l'édition : seuls la barre d'outils et le coin de redimensionnement réagissent.
  items.forEach(function (it) {
    Array.prototype.forEach.call(it.el.children, function (c) {
      if (!c.classList.contains('dw-edit') && !c.classList.contains('dw-resize')) c.setAttribute('inert', '');
    });
  });

  // ——— Placement (port de HomeLayout::pack / fill / strips / region) ———
  function free(g, x, y, w, h) {
    for (var r = y; r < y + h; r++) for (var c = x; c < x + w; c++) if (g[r] && g[r][c]) return false;
    return true;
  }
  function occupancy(placed) {
    var g = [];
    placed.forEach(function (p) {
      for (var r = p.y - 1; r < p.y - 1 + p.h; r++) {
        g[r] = g[r] || [];
        for (var c = p.x - 1; c < p.x - 1 + p.w; c++) g[r][c] = true;
      }
    });
    return g;
  }
  function pack(list, cols) {
    var g = [], out = [];
    list.forEach(function (it) {
      var w = Math.max(1, Math.min(cols, it.w)), h = Math.max(1, it.h);
      for (var y = 0; ; y++) {
        for (var x = 0; x + w <= cols; x++) {
          if (free(g, x, y, w, h)) {
            for (var r = y; r < y + h; r++) { g[r] = g[r] || []; for (var c = x; c < x + w; c++) g[r][c] = true; }
            out.push({ id: it.id, x: x + 1, y: y + 1, w: w, h: h });
            return;
          }
        }
      }
    });
    return out;
  }
  function fill(placed, cols, vertical) {
    var rows = 0;
    placed.forEach(function (p) { rows = Math.max(rows, p.y + p.h - 1); });
    for (var guard = 0; guard < 5000; guard++) {
      var g = occupancy(placed), changed = false;
      for (var r = 0; r < rows && !changed; r++) {
        for (var c = 0; c < cols && !changed; c++) {
          if (g[r] && g[r][c]) continue;
          for (var i = 0; i < placed.length; i++) {
            var p = placed[i], x0 = p.x - 1, y0 = p.y - 1;
            var spansRow = r >= y0 && r < y0 + p.h, spansCol = c >= x0 && c < x0 + p.w;
            if (spansRow && x0 + p.w === c && free(g, c, y0, 1, p.h)) { p.w++; }
            else if (spansRow && x0 === c + 1 && free(g, c, y0, 1, p.h)) { p.x--; p.w++; }
            else if (vertical && spansCol && y0 + p.h === r && free(g, x0, r, p.w, 1)) { p.h++; }
            else if (vertical && spansCol && y0 === r + 1 && free(g, x0, r, p.w, 1)) { p.y--; p.h++; }
            else continue;
            changed = true;
            break;
          }
        }
      }
      if (!changed) break;
    }
    return placed;
  }
  function holes(placed, cols) {
    var g = occupancy(placed), n = 0;
    for (var r = 0; r < g.length; r++) for (var c = 0; c < cols; c++) if (!(g[r] && g[r][c])) n++;
    return n;
  }
  function strips(list, cols) {
    var out = [], y = 1, row = [], used = 0;
    function flush() {
      if (!row.length) return;
      var height = Math.max.apply(null, row.map(function (it) { return it.h; })), x = 1;
      row.forEach(function (it, k) {
        var w = k === row.length - 1 ? cols - x + 1 : it.w;
        out.push({ id: it.id, x: x, y: y, w: w, h: height });
        x += w;
      });
      y += height; row = []; used = 0;
    }
    list.forEach(function (it) {
      var w = Math.max(1, Math.min(cols, it.w));
      if (used + w > cols) flush();
      row.push({ id: it.id, w: w, h: Math.max(1, it.h) });
      used += w;
    });
    flush();
    return out;
  }
  function region(list, cols, vertical) {
    var placed = fill(pack(list, cols), cols, vertical);
    return holes(placed, cols) === 0 ? placed : strips(list, cols);
  }
  function scaled(cols) {
    return visible().map(function (it) {
      var w = it.w, h = it.h;
      if (cols === COLS_TABLET) w = w <= 3 ? 2 : (w <= 6 ? 3 : 6);
      if (cols === COLS_MOBILE) { w = 2; h = 1; }
      return { id: it.id, w: w, h: h };
    });
  }

  function layout() {
    var big = region(scaled(COLS), COLS, true);
    var tab = {}, mob = {};
    region(scaled(COLS_TABLET), COLS_TABLET, true).forEach(function (p) { tab[p.id] = p; });
    region(scaled(COLS_MOBILE), COLS_MOBILE, false).forEach(function (p) { mob[p.id] = p; });
    big.sort(function (a, b) { return a.y - b.y || a.x - b.x; });
    big.forEach(function (p) {
      var it = find(p.id), t = tab[p.id], m = mob[p.id], s = it.el.style;
      s.setProperty('--x', p.x); s.setProperty('--y', p.y); s.setProperty('--w', p.w); s.setProperty('--h', p.h);
      s.setProperty('--x6', t.x); s.setProperty('--y6', t.y); s.setProperty('--w6', t.w); s.setProperty('--h6', t.h);
      s.setProperty('--x2', m.x); s.setProperty('--y2', m.y); s.setProperty('--w2', m.w);
      it.el.classList.toggle('is-row', p.h === 1);
    });
    // Ordre du document = ordre de lecture (tabulation et lecteurs d'écran suivent la grille).
    var active = document.activeElement;
    big.forEach(function (p) { grid.appendChild(find(p.id).el); });
    items.forEach(function (it) { if (it.hidden) grid.appendChild(it.el); });
    if (active && grid.contains(active) && document.activeElement !== active) active.focus({ preventScroll: true });
    refresh();
  }

  function refresh() {
    var vis = visible();
    items.forEach(function (it) {
      var el = it.el, i = vis.indexOf(it);
      var size = el.querySelector('[data-dw-size]');
      if (size) size.textContent = it.w + ' × ' + it.h;
      var ow = el.querySelector('[data-dw-out="w"]'), oh = el.querySelector('[data-dw-out="h"]');
      if (ow) ow.textContent = it.w;
      if (oh) oh.textContent = it.h;
      var set = function (act, off) { var b = el.querySelector('[data-dw-act="' + act + '"]'); if (b) b.disabled = off; };
      set('w-', it.w <= it.minW); set('w+', it.w >= it.maxW);
      set('h-', it.h <= it.minH); set('h+', it.h >= it.maxH);
      set('up', i <= 0); set('down', i < 0 || i >= vis.length - 1);
    });
    if (drawer) {
      items.forEach(function (it) {
        var b = drawer.querySelector('[data-dw-add="' + it.id + '"]');
        if (b) { b.disabled = !it.hidden; b.textContent = msg(it.hidden ? 'add' : 'shown'); }
      });
    }
  }

  function change() { dirty = true; layout(); }

  // Déplace un widget de « delta » places parmi les widgets affichés.
  function move(it, delta) {
    var vis = visible(), i = vis.indexOf(it), j = i + delta;
    if (i < 0 || j < 0 || j >= vis.length) return false;
    var target = vis[j];
    items.splice(items.indexOf(it), 1);
    var t = items.indexOf(target);
    items.splice(delta > 0 ? t + 1 : t, 0, it);
    change();
    return true;
  }
  function moveBefore(it, target, after) {
    if (it === target) return;
    items.splice(items.indexOf(it), 1);
    var t = items.indexOf(target);
    items.splice(after ? t + 1 : t, 0, it);
    change();
  }
  function resize(it, dw, dh) {
    var w = Math.max(it.minW, Math.min(it.maxW, it.w + dw)), h = Math.max(it.minH, Math.min(it.maxH, it.h + dh));
    if (w === it.w && h === it.h) { say(msg('limit', [it.label])); return; }
    it.w = w; it.h = h;
    change();
    say(msg('size', [it.label, w, COLS, h]));
  }
  function focusGrip(it) { var g = it && it.el.querySelector('[data-dw-grip]'); if (g) g.focus(); }

  // ——— Boutons de la barre d'outils de chaque widget ———
  grid.addEventListener('click', function (e) {
    var sizer = e.target.closest('[data-dw-sizer]');
    if (sizer) {
      var pop = document.getElementById(sizer.getAttribute('aria-controls'));
      var open = sizer.getAttribute('aria-expanded') !== 'true';
      closeSizers();
      sizer.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (pop) pop.hidden = !open;
      return;
    }
    var b = e.target.closest('[data-dw-act]');
    if (!b) return;
    var it = find(b.closest('.dw').getAttribute('data-widget'));
    var act = b.getAttribute('data-dw-act');
    if (act === 'w-') resize(it, -1, 0);
    else if (act === 'w+') resize(it, 1, 0);
    else if (act === 'h-') resize(it, 0, -1);
    else if (act === 'h+') resize(it, 0, 1);
    else if (act === 'up' || act === 'down') {
      if (move(it, act === 'up' ? -1 : 1)) say(msg('moved', [it.label, posOf(it), visible().length]));
      if (!b.disabled) b.focus(); else focusGrip(it);
    } else if (act === 'hide') {
      var vis = visible(), next = vis[vis.indexOf(it) + 1] || vis[vis.indexOf(it) - 1];
      it.hidden = true;
      it.el.hidden = true;
      closeSizers();
      change();
      say(msg('hidden', [it.label]));
      if (next) focusGrip(next); else { var add = document.querySelector('[data-dw-open-add]'); if (add) add.focus(); }
    }
  });
  function closeSizers() {
    grid.querySelectorAll('[data-dw-sizer][aria-expanded="true"]').forEach(function (s) {
      s.setAttribute('aria-expanded', 'false');
      var pop = document.getElementById(s.getAttribute('aria-controls'));
      if (pop) pop.hidden = true;
    });
  }
  document.addEventListener('click', function (e) { if (!e.target.closest('.dw-edit')) closeSizers(); });

  // ——— Clavier : saisir, déplacer, déposer, annuler ———
  var grabbed = null, grabFrom = -1;
  grid.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !grabbed) {
      var open = grid.querySelector('[data-dw-sizer][aria-expanded="true"]');
      if (open) { closeSizers(); open.focus(); e.preventDefault(); }
      return;
    }
    var grip = e.target.closest('[data-dw-grip]');
    if (!grip) return;
    var it = find(grip.closest('.dw').getAttribute('data-widget'));
    var total = visible().length;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      if (grabbed === it) {
        drop(false);
      } else {
        if (grabbed) drop(false);
        grabbed = it; grabFrom = posOf(it);
        it.el.classList.add('is-grabbed');
        grip.setAttribute('aria-pressed', 'true');
        say(msg('grab', [it.label, grabFrom, total]));
      }
    } else if (grabbed === it && /^Arrow(Up|Down|Left|Right)$/.test(e.key)) {
      e.preventDefault();
      if (move(it, e.key === 'ArrowUp' || e.key === 'ArrowLeft' ? -1 : 1)) {
        focusGrip(it);
        say(msg('moved', [it.label, posOf(it), total]));
      }
    } else if (grabbed === it && e.key === 'Escape') {
      e.preventDefault();
      drop(true);
    }
  });
  grid.addEventListener('focusout', function (e) {
    if (grabbed && e.relatedTarget && e.target === grabbed.el.querySelector('[data-dw-grip]') && !grabbed.el.contains(e.relatedTarget)) drop(false);
  });
  function drop(cancel) {
    var it = grabbed;
    if (!it) return;
    grabbed = null;
    if (cancel) {
      var delta = grabFrom - posOf(it);
      while (delta !== 0 && move(it, delta > 0 ? 1 : -1)) delta += delta > 0 ? -1 : 1;
      say(msg('cancel', [it.label, posOf(it)]));
    } else {
      say(msg('drop', [it.label, posOf(it), visible().length]));
    }
    it.el.classList.remove('is-grabbed');
    var grip = it.el.querySelector('[data-dw-grip]');
    if (grip) { grip.removeAttribute('aria-pressed'); grip.focus(); }
  }

  // ——— Pointeur : glisser par la poignée, redimensionner par le coin ———
  var drag = null;
  grid.addEventListener('pointerdown', function (e) {
    if (e.button !== 0) return;
    var grip = e.target.closest('[data-dw-grip]');
    var corner = e.target.closest('[data-dw-resize]');
    if (!grip && !corner) return;
    var el = (grip || corner).closest('.dw');
    var it = find(el.getAttribute('data-widget'));
    e.preventDefault();
    closeSizers();
    var rect = el.getBoundingClientRect();
    var cs = getComputedStyle(grid);
    var cols = cs.gridTemplateColumns.split(' ').length;
    var gap = parseFloat(cs.columnGap) || 16;
    var rowH = parseFloat(cs.gridAutoRows) || rect.height / Math.max(1, it.h);
    drag = { it: it, el: el, mode: grip ? 'move' : 'size', x: e.clientX, y: e.clientY, rect: rect, cols: cols, gap: gap,
      colW: (grid.clientWidth - (cols - 1) * gap) / cols, rowH: rowH, last: null, from: posOf(it) };
    el.classList.add('is-dragging');
    (grip || corner).setPointerCapture(e.pointerId);
  });
  grid.addEventListener('pointermove', function (e) {
    if (!drag) return;
    if (drag.mode === 'size') {
      var unit = COLS / drag.cols;
      var w = Math.round((drag.rect.width + e.clientX - drag.x + drag.gap) / (drag.colW + drag.gap)) * unit;
      var h = Math.round((drag.rect.height + e.clientY - drag.y + drag.gap) / (drag.rowH + drag.gap));
      w = Math.max(drag.it.minW, Math.min(drag.it.maxW, w));
      h = Math.max(drag.it.minH, Math.min(drag.it.maxH, h));
      if (w !== drag.it.w || h !== drag.it.h) { drag.it.w = w; drag.it.h = h; change(); }
      return;
    }
    var under = document.elementsFromPoint(e.clientX, e.clientY);
    var target = null;
    for (var i = 0; i < under.length; i++) {
      var d = under[i].closest && under[i].closest('.dw[data-widget]');
      if (d && d !== drag.el && grid.contains(d)) { target = d; break; }
    }
    if (!target || target === drag.last) return;
    drag.last = target;
    var r = target.getBoundingClientRect();
    moveBefore(drag.it, find(target.getAttribute('data-widget')), e.clientX > r.left + r.width / 2 || e.clientY > r.top + r.height * 0.75);
  });
  function endDrag() {
    if (!drag) return;
    var it = drag.it;
    drag.el.classList.remove('is-dragging');
    if (drag.mode === 'size') say(msg('size', [it.label, it.w, COLS, it.h]));
    else if (posOf(it) !== drag.from) say(msg('drop', [it.label, posOf(it), visible().length]));
    drag = null;
  }
  grid.addEventListener('pointerup', endDrag);
  grid.addEventListener('pointercancel', endDrag);
  grid.addEventListener('lostpointercapture', endDrag);

  // ——— Tiroir « Ajouter un widget » ———
  if (drawer) {
    var q = drawer.querySelector('[data-dw-search]');
    var none = drawer.querySelector('[data-dw-none]');
    var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    if (q) {
      q.addEventListener('input', function () {
        var needle = norm(q.value.trim()), shown = 0;
        drawer.querySelectorAll('[data-dw-cat]').forEach(function (li) {
          var ok = needle === '' || norm(li.textContent).indexOf(needle) >= 0;
          li.hidden = !ok;
          if (ok) shown++;
        });
        if (none) none.hidden = shown > 0;
      });
    }
    drawer.addEventListener('click', function (e) {
      var b = e.target.closest('[data-dw-add]');
      if (!b || b.disabled) return;
      var it = find(b.getAttribute('data-dw-add'));
      if (!it) return;
      it.hidden = false;
      it.el.hidden = false;
      items.splice(items.indexOf(it), 1);
      items.push(it);
      change();
      if (typeof drawer.close === 'function') drawer.close();
      it.el.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
      focusGrip(it);
      say(msg('added', [it.label]));
    });
  }

  // ——— Enregistrer, réinitialiser, annuler ———
  var save = document.querySelector('[data-dw-save]');
  if (save) {
    save.addEventListener('click', function () {
      var payload = { v: 2, items: items.map(function (it) { return { id: it.id, w: it.w, h: it.h, hidden: it.hidden }; }) };
      var fd = new FormData();
      fd.append('layout', JSON.stringify(payload));
      fd.append('_csrf_token', grid.getAttribute('data-dw-csrf') || '');
      save.disabled = true;
      say(msg('saving'));
      fetch(grid.getAttribute('data-dw-url'), {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': grid.getAttribute('data-dw-csrf') || '' }
      }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); }).then(function (res) {
        if (res && res.ok) {
          dirty = false;
          say(msg('saved'));
          var cancel = document.querySelector('[data-dw-cancel]');
          window.location.href = cancel ? cancel.href : window.location.pathname;
        } else {
          save.disabled = false;
          say(res && res.message ? res.message : msg('error'));
        }
      }, function () { save.disabled = false; say(msg('error')); });
    });
  }
  var reset = document.querySelector('[data-dw-reset-form]');
  if (reset) {
    reset.addEventListener('submit', function (e) {
      if (!window.confirm(msg('reset'))) { e.preventDefault(); return; }
      dirty = false;
    });
  }
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = msg('leave'); }
  });

  // Lien direct vers le tiroir (…?personnaliser=1#ajouter).
  if (location.hash === '#ajouter' && drawer && typeof drawer.showModal === 'function') drawer.showModal();

  layout();
  dirty = false;
})();
