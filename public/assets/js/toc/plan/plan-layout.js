/* Éditeur de plans : disposition de l'écran. Chaque volet (gauche, droite), la frise du bas et la barre d'outils se
 * replient ; un volet replié laisse un rail d'icônes. Les largeurs des volets et la hauteur de la frise se règlent en
 * glissant leur bord (double-clic : replier ou déplier). Les sections des volets et les groupes de la barre d'outils se
 * replient aussi. Tout est gardé par membre dans le navigateur. Sur un petit écran, le volet de droite reste replié
 * tant que rien n'est choisi. Touches : [ et ] (volets, quand rien n'est choisi), 9 (frise), 0 (carte seule).
 * Se branche sur window.TocPlanEditor (toc-plan-editor.js). */
(function () {
  'use strict';
  var E = window.TocPlanEditor;
  if (!E) return;
  var P = E.P, $ = E.$, $$ = E.$$;
  var LIMITS = { left: [240, 520], right: [280, 560], time: [58, 220] };
  var KEY = 'athena:toc:layout:' + String((E.BOOT && E.BOOT.operator) || '').toLowerCase();
  var S = { left: { open: true, w: 0 }, right: { open: true, w: 0, auto: null }, time: { open: true, h: 0 }, tools: { open: true }, folds: {}, groups: {}, focus: false };
  var small = function () { return window.innerWidth <= 1366; };

  function load() {
    try {
      var raw = JSON.parse(window.localStorage.getItem(KEY) || 'null');
      if (raw && typeof raw === 'object') {
        ['left', 'right', 'time', 'tools'].forEach(function (k) { if (raw[k] && typeof raw[k] === 'object') Object.keys(raw[k]).forEach(function (f) { S[k][f] = raw[k][f]; }); });
        S.folds = raw.folds && typeof raw.folds === 'object' ? raw.folds : {};
        S.groups = raw.groups && typeof raw.groups === 'object' ? raw.groups : {};
      }
    } catch (e) {}
    // Petit écran : le volet de droite suit la sélection tant que le membre ne l'a pas réglé lui-même.
    if (S.right.auto == null) S.right.auto = true;
  }
  function save() {
    try { window.localStorage.setItem(KEY, JSON.stringify({ left: S.left, right: S.right, time: S.time, tools: S.tools, folds: S.folds, groups: S.groups })); } catch (e) {}
  }
  function isOpen(name) {
    if (S.focus && name !== 'map') return false;
    if (name === 'right' && S.right.auto && small()) return !!S.right.open && P.sel.length > 0;
    return !!(S[name] && S[name].open);
  }
  var relayoutTimer = null;
  function apply() {
    var root = $('#pe');
    if (!root) return;
    var b = document.body;
    ['left', 'right', 'time', 'tools'].forEach(function (n) { b.classList.toggle('pe-hide-' + n, !isOpen(n)); });
    b.classList.toggle('pe-focus', !!S.focus);
    root.style.setProperty('--pe-lw', S.left.w ? S.left.w + 'px' : '');
    root.style.setProperty('--pe-rw', S.right.w ? S.right.w + 'px' : '');
    root.style.setProperty('--pe-th', S.time.h ? S.time.h + 'px' : '');
    $$('[data-collapse]').forEach(function (btn) {
      var n = btn.getAttribute('data-collapse');
      var open = isOpen(n);
      btn.setAttribute('aria-expanded', String(open));
    });
    var fb = $('#pe-focus-exit');
    if (fb) fb.hidden = !S.focus;
    clearTimeout(relayoutTimer);
    relayoutTimer = setTimeout(function () {
      if (E.tm) E.tm.map.invalidateSize({ animate: false });
      if (E.view3d && P.mode === '3d') E.view3d.resize();
      E.renderOverlay();
      E.emit('layout');
    }, 30);
  }
  function show(name, on, byUser) {
    if (!S[name]) return;
    if (S.focus) S.focus = false;
    var open = on == null ? !isOpen(name) : !!on;
    S[name].open = open;
    if (name === 'right' && byUser) S.right.auto = false;
    save();
    apply();
  }
  function focusMap(on) {
    S.focus = on == null ? !S.focus : !!on;
    apply();
    if (S.focus) E.say('Carte seule : touche 0 ou Échap pour retrouver les volets.');
  }

  /* ---------- Bords à glisser ---------- */
  var drag = null;
  function onDivDown(ev) {
    var d = ev.target.closest('[data-div]');
    if (!d || (ev.button != null && ev.button !== 0)) return;
    var name = d.getAttribute('data-div');
    var box = name === 'time' ? $('#pe-track') : $('#pe-' + name);
    var r = box.getBoundingClientRect();
    drag = { name: name, x: ev.clientX, y: ev.clientY, size: name === 'time' ? r.height : r.width, moved: false, open: isOpen(name) };
    ev.preventDefault();
    document.body.classList.add('is-resizing', name === 'time' ? 'is-resizing-row' : 'is-resizing-col');
    document.addEventListener('pointermove', onDivMove);
    document.addEventListener('pointerup', onDivUp);
    document.addEventListener('pointercancel', onDivUp);
  }
  function onDivMove(ev) {
    if (!drag) return;
    var d = drag.name === 'time' ? drag.y - ev.clientY : (drag.name === 'left' ? ev.clientX - drag.x : drag.x - ev.clientX);
    if (!drag.moved && Math.abs(d) < 4) return;
    drag.moved = true;
    var lim = LIMITS[drag.name];
    var base = drag.open ? drag.size : 0;
    var v = base + d;
    // Tiré sous la moitié du minimum : le volet se replie ; au-delà : il se déplie à la taille tirée.
    if (v < lim[0] / 2) { if (S[drag.name].open) { S[drag.name].open = false; apply(); } return; }
    v = Math.round(Math.max(lim[0], Math.min(lim[1], v)));
    S[drag.name].open = true;
    if (drag.name === 'right') S.right.auto = false;
    if (drag.name === 'time') S.time.h = v; else S[drag.name].w = v;
    apply();
  }
  function onDivUp() {
    document.removeEventListener('pointermove', onDivMove);
    document.removeEventListener('pointerup', onDivUp);
    document.removeEventListener('pointercancel', onDivUp);
    document.body.classList.remove('is-resizing', 'is-resizing-row', 'is-resizing-col');
    if (drag && drag.moved) save();
    drag = null;
  }

  /* ---------- Sections et groupes repliables ---------- */
  /** Applique l'état gardé aux sections <details data-fold="…"> d'un conteneur qui vient d'être redessiné. */
  function restoreFolds(root) {
    $$('details[data-fold]', root || document).forEach(function (d) {
      var k = d.getAttribute('data-fold');
      if (S.folds[k] != null) d.open = !!S.folds[k];
    });
  }
  function applyGroups() {
    $$('.pe-tgroup[data-group]').forEach(function (g) {
      var k = g.getAttribute('data-group');
      var folded = !!S.groups[k];
      g.classList.toggle('is-folded', folded);
      var t = g.querySelector('.pe-tgroup-fold');
      if (t) { t.setAttribute('aria-expanded', String(!folded)); t.title = folded ? 'Déplier ce groupe d’outils' : 'Replier ce groupe d’outils'; }
      // Replié : le groupe garde l'outil en cours, sinon son premier bouton.
      var btns = $$('.pe-tbtn:not(.pe-tgroup-fold)', g);
      var keep = btns.filter(function (x) { return x.getAttribute('aria-pressed') === 'true' && x.hasAttribute('data-tool'); })[0] || btns[0];
      btns.forEach(function (x) { x.classList.toggle('is-kept', folded && x === keep); });
    });
  }

  /* ---------- Branchement ---------- */
  E.action('toggleLeft', 'Replier ou déplier le volet de gauche', function () { show('left', null, true); }, { key: '[', group: 'Affichage' });
  E.action('toggleRight', 'Replier ou déplier le volet de droite', function () { show('right', null, true); }, { key: ']', group: 'Affichage' });
  E.action('toggleTime', 'Replier ou déplier la frise', function () { show('time', null, true); }, { key: '9', group: 'Affichage' });
  E.action('toggleTools', 'Replier ou déplier la barre d’outils', function () { show('tools', null, true); }, { group: 'Affichage' });
  E.action('focusMap', 'Carte seule (tout replier)', function () { focusMap(); }, { key: '0', group: 'Affichage' });
  E.action('layoutReset', 'Remettre la disposition d’origine', function () {
    S.left = { open: true, w: 0 }; S.right = { open: true, w: 0, auto: true }; S.time = { open: true, h: 0 }; S.tools = { open: true }; S.folds = {}; S.groups = {}; S.focus = false;
    save(); apply(); applyGroups(); E.renderAll(); E.say('Disposition d’origine.');
  }, { group: 'Affichage' });

  E.on('init', function () {
    load();
    document.addEventListener('pointerdown', onDivDown);
    document.addEventListener('dblclick', function (ev) {
      var d = ev.target.closest ? ev.target.closest('[data-div]') : null;
      if (d) show(d.getAttribute('data-div'), null, true);
    });
    document.addEventListener('click', function (ev) {
      var t = ev.target.closest ? ev.target : null;
      if (!t) return;
      var c = t.closest('[data-collapse]');
      if (c) { show(c.getAttribute('data-collapse'), null, true); return; }
      var rail = t.closest('[data-rail]');
      if (rail) {
        var parts = rail.getAttribute('data-rail').split(':');
        show(parts[0], true, true);
        if (parts[0] === 'left' && E.setLeftTab) E.setLeftTab(parts[1]);
        if (parts[0] === 'right' && E.setRightTab) E.setRightTab(parts[1]);
        return;
      }
      var gf = t.closest('.pe-tgroup-fold');
      if (gf) { var k = gf.parentNode.getAttribute('data-group'); S.groups[k] = !S.groups[k]; save(); applyGroups(); return; }
      if (t.closest('#pe-focus-exit')) focusMap(false);
    });
    // Une section repliée ou dépliée à la main est retenue.
    document.addEventListener('toggle', function (ev) {
      var d = ev.target;
      if (d && d.hasAttribute && d.hasAttribute('data-fold')) { S.folds[d.getAttribute('data-fold')] = d.open; save(); }
    }, true);
    window.addEventListener('resize', apply);
    var prevHook = E.keyHook;
    E.keyHook = function (e) {
      if (prevHook && prevHook(e)) return true;
      if (e.ctrlKey || e.metaKey || e.altKey || P.briefing) return false;
      if (e.key === '0') { focusMap(); return true; }
      if (e.key === '9') { show('time', null, true); return true; }
      // [ et ] font pivoter la sélection ; sans sélection, ils replient les volets.
      if ((e.key === '[' || e.key === ']') && !P.sel.length) { show(e.key === '[' ? 'left' : 'right', null, true); return true; }
      return false;
    };
    var prevEsc = E.escape;
    E.escape = function () {
      if (prevEsc && prevEsc()) return true;
      if (S.focus) { focusMap(false); return true; }
      return false;
    };
    apply();
    applyGroups();
  });
  E.on('selection', function () { if (S.right.auto && small()) apply(); });
  E.on('tool', applyGroups);
  E.on('render', function () { restoreFolds($('#pe-left')); restoreFolds($('#pe-right')); });
  E.on('briefing', function () { setTimeout(apply, 0); });
  E.layout = { show: function (n, on) { show(n, on, false); }, isOpen: isOpen, focusMap: focusMap, restoreFolds: restoreFolds, state: S };
})();
