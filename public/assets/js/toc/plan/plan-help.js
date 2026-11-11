/* Aide de l'éditeur de plans : tutoriel guidé sur l'écran réel, plan d'exemple, tiroir d'aide (modes d'emploi,
 * raccourcis, graphismes) et astuces d'outil. Rien ici ne bloque l'éditeur : le voile du tutoriel laisse passer la
 * souris, le tiroir n'est pas modal. Mémoire par membre dans le navigateur. Page écrite : docs/TOC-PLANS-TUTORIEL.md.
 * Les textes sont des phrases entières (traduction anglaise par dictionnaire, public/assets/js/i18n_dom.js). */
(function () {
  'use strict';
  var E = window.TocPlanEditor;
  if (!E) return;
  var P = E.P, $ = E.$, $$ = E.$$, esc = E.esc;
  var calm = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };

  /* ---------- Mémoire du membre (navigateur) ---------- */
  var KEY = 'athena:toc:plan-help:' + (E.BOOT.operator || 'membre');
  var mem = { step: 0, active: false, seen: false, done: false, unit: '', hints: {}, hintsOff: false };
  function loadMem() {
    try {
      var j = JSON.parse(window.localStorage.getItem(KEY) || 'null');
      if (j && typeof j === 'object') Object.keys(mem).forEach(function (k) { if (j[k] !== undefined && typeof j[k] === typeof mem[k]) mem[k] = j[k]; });
    } catch (e) {}
    if (!mem.hints || typeof mem.hints !== 'object') mem.hints = {};
  }
  function saveMem() { try { window.localStorage.setItem(KEY, JSON.stringify(mem)); } catch (e) {} }
  loadMem();

  function el(tag, cls, html) { var n = document.createElement(tag); if (cls) n.className = cls; if (html != null) n.innerHTML = html; return n; }
  function shown(n) { return !!(n && n.getClientRects && n.getClientRects().length); }
  function dialogOpen() { return $$('.toc-overlay:not([hidden])').length > 0 || !$('#pe-palette').hidden; }

  /* ---------- Volets : API de mise en page si elle existe, sinon les boutons de repli ---------- */
  function showPanel(name, on) {
    if (E.layout && E.layout.show) { try { E.layout.show(name, on); return; } catch (e) {} }
    if (name === 'left' || name === 'right') {
      var hidden = document.body.classList.contains('pe-hide-' + name);
      var b = $('[data-collapse="' + name + '"]');
      if (b && hidden === on) b.click();
    }
    if (name === 'time') {
      var f = $('#pe-time-fold');
      if (f && (f.getAttribute('aria-expanded') === 'false') === on) f.click();
    }
  }
  function tile(id) { var t = $('.pe-tile[data-lib="' + id + '"]'); return shown(t) ? t : null; }
  /** Rend visible une tuile de la bibliothèque : volet ouvert, onglet, recherche vidée, famille dépliée. */
  function revealTile(id) {
    showPanel('left', true);
    if (E.setLeftTab && $('[data-left-body="lib"]') && $('[data-left-body="lib"]').hidden) E.setLeftTab('lib');
    var s = $('#pe-lib-search');
    if (s && s.value) { s.value = ''; s.dispatchEvent(new Event('input', { bubbles: true })); }
    var t = $('.pe-tile[data-lib="' + id + '"]');
    if (!t) return;
    var d = t.closest('details');
    if (d && !d.open) d.open = true;
    if (t.scrollIntoView) t.scrollIntoView({ block: 'nearest' });
  }

  /* ====================================================================================================
   * Tutoriel guidé
   * ==================================================================================================== */
  var tour = { on: false, end: false, raf: 0, sig: '', pos: null, manual: false, ctx: {}, checks: '', card: null, veil: null, rings: [], live: null };

  function tourUnit() {
    var u = mem.unit && P.els[mem.unit];
    if (u && u.kind === 'unit' && u.props.placed !== false) return u;
    var all = E.list().filter(function (e) { return e.kind === 'unit' && e.props.placed !== false; });
    return all[0] || null;
  }
  /** Zone de l'écran autour de l'unité du tutoriel (la carte entière si elle n'est pas à l'écran). */
  function unitHole() {
    var wrap = $('#plan-mapwrap');
    var u = tourUnit();
    if (!u || P.mode !== '2d' || !E.tm) return wrap;
    try {
      var d = E.shownOf(u.uid) || u;
      var r = $('#plan-map').getBoundingClientRect();
      var p = E.px(d.pts[0]);
      if (p[0] < 0 || p[1] < 0 || p[0] > r.width || p[1] > r.height) return wrap;
      return { x: r.left + p[0] - 46, y: r.top + p[1] - 40, w: 92, h: 80 };
    } catch (e) { return wrap; }
  }
  function moved(a, b) { return a && b && a.kind === 'unit' && b.kind === 'unit' && JSON.stringify(a.pts) !== JSON.stringify(b.pts); }
  function keyCount(e) { return e && e.props && Array.isArray(e.props.track) ? e.props.track.length : 0; }
  function importMenu() { var b = $('[data-run="importMarkers"]'); return b ? b.closest('details') : null; }

  var STEPS = [
    {
      id: 'unit', title: 'Posez une unité',
      text: 'Glissez « Infanterie » de la bibliothèque jusqu’à la carte. Vous pouvez aussi cliquer la tuile, puis cliquer sur la carte.',
      prep: function () { revealTile('u:infantry'); },
      targets: function () { return [tile('u:infantry') || $('#pe-lib-body'), $('#plan-mapwrap')]; },
      on: { added: function (e) { if (e && e.kind === 'unit') { mem.unit = e.uid; return true; } return false; } }
    },
    {
      id: 'move', need: 'unit', title: 'Déplacez l’unité',
      text: 'Glissez l’unité vers un autre endroit de la carte. Si un outil de pose est encore actif, appuyez d’abord sur Échap pour reprendre la sélection.',
      targets: function () { return P.tool !== 'select' ? [unitHole(), $('[data-tool="select"]')] : [unitHole()]; },
      on: { commit: function (c) {
        return Object.keys(c.after).some(function (uid) { if (moved(c.after[uid], c.before[uid])) { mem.unit = uid; return true; } return false; });
      } }
    },
    {
      id: 'axis', title: 'Tracez un axe de progression',
      text: 'Cliquez « Axe de progression » dans la bibliothèque, puis cliquez sur la carte à chaque point du tracé. Terminez par un double-clic ou par Entrée.',
      prep: function () { revealTile('k:axis'); },
      targets: function () { return [tile('k:axis') || $('#pe-lib-body'), $('#plan-mapwrap')]; },
      on: { added: function (e) { return !!e && e.kind === 'axis'; } }
    },
    {
      id: 'phase', title: 'Créez une phase',
      text: 'Une phase est un temps de l’opération : mise en place, appui, assaut. Cliquez « Phase » dans la frise du bas pour en ajouter une.',
      prep: function () { showPanel('time', true); },
      enter: function (ctx) { ctx.n = P.phases.length; },
      targets: function () { return [$('#pe-time [data-run="addPhase"]')]; },
      on: { phases: function () { return P.phases.length > tour.ctx.n; } }
    },
    {
      id: 'key', need: 'unit', title: 'Déplacez l’unité à H+10',
      text: '« Clé auto » retient où se trouve une unité à chaque instant : la déplacer à H+10 lui donne une position pour H+10, sans toucher à son point de départ.',
      prep: function () { showPanel('time', true); },
      checks: [
        ['Activez « Clé auto » dans la frise.', function () { return !!P.autoKey; }],
        ['Cliquez la frise vers H+10.', function () { return P.time != null && Math.abs(P.time - 10) <= 4; }],
        ['Glissez l’unité vers sa nouvelle position.', function () { return keyCount(tourUnit()) >= 2; }]
      ],
      targets: function () { return [$('#pe-autokey'), $('#pe-track'), unitHole()]; },
      on: { commit: function (c) {
        return Object.keys(c.after).some(function (uid) {
          var a = c.after[uid], b = c.before[uid];
          if (a && a.kind === 'unit' && keyCount(a) >= 2 && JSON.stringify(a.props.track) !== JSON.stringify(b && b.props.track)) { mem.unit = uid; return true; }
          return false;
        });
      } }
    },
    {
      id: 'play', title: 'Lisez la frise',
      text: 'Lancez la lecture : l’unité glisse d’une position à la suivante. Un nouveau clic met en pause.',
      prep: function () { showPanel('time', true); },
      targets: function () { return [$('#pe-play')]; },
      on: { play: function () { return true; } }
    },
    {
      id: '3d', title: 'Passez en 3D',
      text: 'Cliquez « 3D » pour voir le plan posé sur le relief. Clic droit ou Ctrl + glisser tourne et incline la vue, la molette rapproche.',
      targets: function () { return [$('[data-view="3d"]')]; },
      check: function () { return P.mode === '3d'; }
    },
    {
      id: '2d', title: 'Revenez en 2D',
      text: 'Cliquez « 2D » pour retrouver la carte à plat. Les traits et les zones se tracent en 2D.',
      targets: function () { return [$('[data-view="2d"]')]; },
      // Étape sans objet quand la carte est déjà à plat (étape 3D passée) : elle est sautée dans les deux sens.
      skip: function () { return P.mode === '2d'; },
      check: function () { return P.mode === '2d'; }
    },
    {
      id: 'markers', manual: true, title: 'Importez les marqueurs du jeu',
      text: 'Ouvrez le menu « Importer », puis « Marqueurs présents en jeu ». Cochez les marqueurs voulus et validez l’import en bas du volet. Sans partie en liaison avec Athena, passez cette étape.',
      targets: function () {
        var go = $('#pe-markers [data-mk="go"]');
        if (shown(go)) return [go];
        var d = importMenu();
        if (!d) return [];
        return d.open ? [$('[data-run="importMarkers"]', d)] : [$('summary', d)];
      },
      avoid: function () { var d = importMenu(); return [d && d.open ? $('.toc-menu-list', d) : null, $('#pe-markers')]; },
      on: { markersImported: function () { return true; } }
    },
    {
      id: 'publish', title: 'Envoyez le plan en jeu',
      text: 'Cliquez « Envoyer en jeu » pour ouvrir la fenêtre d’envoi. Rien ne part tant que vous n’avez pas appuyé sur « Envoyer ».',
      targets: function () { return [$('[data-run="publish"]')]; },
      check: function () { var d = $('#dlg-publish'); return !!d && !d.hidden; }
    }
  ];
  var END = {
    title: 'Tutoriel terminé',
    text: 'Rien n’est envoyé en jeu tant que vous n’avez pas appuyé sur « Envoyer » : fermez la fenêtre pour ne rien envoyer. L’aide (touche ?) garde tous les modes d’emploi.'
  };

  function buildTour() {
    if (tour.card) return;
    var veil = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    veil.setAttribute('class', 'ph-veil');
    veil.setAttribute('aria-hidden', 'true');
    veil.innerHTML = '<path fill-rule="evenodd" d=""/>';
    veil.style.display = 'none';
    document.body.appendChild(veil);
    tour.veil = veil;
    var c = el('section', 'ph-card');
    c.id = 'ph-card';
    c.hidden = true;
    c.tabIndex = -1;
    c.setAttribute('role', 'dialog');
    c.setAttribute('aria-modal', 'false');
    c.setAttribute('aria-labelledby', 'ph-card-title');
    c.setAttribute('aria-describedby', 'ph-card-text');
    c.innerHTML = '<div class="ph-card-head" title="Glisser pour déplacer cette fiche"><h2 id="ph-card-title"></h2><span class="ph-card-n mono" id="ph-card-n"></span></div>' +
      '<p id="ph-card-text"></p><ul class="ph-card-checks" id="ph-card-checks" hidden></ul>' +
      '<div class="ph-card-bar" aria-hidden="true"><i id="ph-card-bar"></i></div>' +
      '<div class="ph-card-acts">' +
      '<button type="button" class="toc-btn is-small" data-ph="prev">Précédent</button>' +
      '<button type="button" class="toc-btn is-small" data-ph="skip" title="Passer cette étape">Passer</button>' +
      '<button type="button" class="toc-btn is-small is-primary" data-ph="did" hidden>Je l’ai fait</button>' +
      '<button type="button" class="toc-btn is-small is-primary" data-ph="finish" hidden>Terminer</button>' +
      '<button type="button" class="toc-btn is-small" data-ph="openhelp" hidden>Ouvrir l’aide</button>' +
      '<button type="button" class="toc-btn is-small ph-card-quit" data-ph="quit" title="Quitter le tutoriel (Échap) : il reprendra à cette étape">Quitter</button>' +
      '</div>' +
      '<button type="button" class="ph-link" data-ph="example" hidden>Voir d’abord un plan d’exemple</button>';
    document.body.appendChild(c);
    tour.card = c;
    var live = el('div', 'toc-sr');
    live.id = 'ph-live';
    live.setAttribute('aria-live', 'polite');
    live.innerHTML = '<span></span> <span></span>';
    document.body.appendChild(live);
    tour.live = live;
    c.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ph]');
      if (!b) return;
      var a = b.getAttribute('data-ph');
      if (a === 'prev') { var i = mem.step - 1; while (i > 0 && STEPS[i].skip && STEPS[i].skip()) i--; showStep(Math.max(0, i), true); }
      if (a === 'skip' || a === 'did') advance(true);
      if (a === 'quit') leaveTour();
      if (a === 'finish') finishTour();
      if (a === 'openhelp') { finishTour(); openDrawer(); }
      if (a === 'example') examplePlan();
    });
    // La barre d'espace sert à déplacer la carte dans l'éditeur : ici elle doit rester celle des boutons.
    c.addEventListener('keydown', function (e) { if ((e.key === ' ' || e.key === 'Enter') && e.target.closest('button')) e.stopPropagation(); });
    $('.ph-card-head', c).addEventListener('pointerdown', dragCard);
  }
  function dragCard(e) {
    if (e.button !== 0) return;
    var c = tour.card, r = c.getBoundingClientRect();
    var dx = e.clientX - r.left, dy = e.clientY - r.top;
    e.preventDefault();
    function mv(ev) {
      tour.manual = true;
      tour.pos = clampCard(ev.clientX - dx, ev.clientY - dy);
      c.classList.add('is-dragged');
      applyCard();
    }
    function up() {
      document.removeEventListener('pointermove', mv);
      document.removeEventListener('pointerup', up);
      document.removeEventListener('pointercancel', up);
      c.classList.remove('is-dragged');
    }
    document.addEventListener('pointermove', mv);
    document.addEventListener('pointerup', up);
    document.addEventListener('pointercancel', up);
  }
  function clampCard(x, y) {
    var c = tour.card, m = 8;
    return [Math.max(m, Math.min(window.innerWidth - c.offsetWidth - m, x)), Math.max(m, Math.min(window.innerHeight - c.offsetHeight - m, y))];
  }
  function applyCard() { tour.card.style.left = Math.round(tour.pos[0]) + 'px'; tour.card.style.top = Math.round(tour.pos[1]) + 'px'; }

  function rectOf(t, pad) {
    if (!t) return null;
    var r;
    if (t.getBoundingClientRect) {
      if (!shown(t)) return null;
      var b = t.getBoundingClientRect();
      r = { x: b.left, y: b.top, w: b.width, h: b.height };
      // Écran étroit : le volet de droite passe par-dessus la carte ; la carte utile s'arrête à son bord.
      if (t.id === 'plan-mapwrap') {
        var pr = $('#pe-right');
        if (shown(pr) && window.getComputedStyle(pr).position === 'fixed') {
          var rb = pr.getBoundingClientRect();
          if (rb.left > r.x && rb.left < r.x + r.w) r.w = rb.left - r.x;
        }
      }
    } else r = { x: t.x, y: t.y, w: t.w, h: t.h };
    pad = pad || 0;
    var x0 = Math.max(0, r.x - pad), y0 = Math.max(0, r.y - pad);
    var x1 = Math.min(window.innerWidth, r.x + r.w + pad), y1 = Math.min(window.innerHeight, r.y + r.h + pad);
    if (x1 - x0 < 4 || y1 - y0 < 4) return null;
    return { x: Math.round(x0), y: Math.round(y0), w: Math.round(x1 - x0), h: Math.round(y1 - y0) };
  }
  function overlap(x, y, w, h, r) {
    var ox = Math.min(x + w, r.x + r.w) - Math.max(x, r.x), oy = Math.min(y + h, r.y + r.h) - Math.max(y, r.y);
    return ox > 0 && oy > 0 ? ox * oy : 0;
  }
  function gap(x, y, w, h, r) {
    var dx = Math.max(r.x - (x + w), x - (r.x + r.w), 0), dy = Math.max(r.y - (y + h), y - (r.y + r.h), 0);
    return Math.sqrt(dx * dx + dy * dy);
  }
  /** Place la fiche au plus près de la cible sans la couvrir (ni une fenêtre ouverte) ; garde sa place tant qu'elle
   *  convient. Faute de place libre à sa largeur, la fiche se resserre (volets étroits, petit écran). */
  function placeCard(holes, avoid) {
    var c = tour.card, vw = window.innerWidth, vh = window.innerHeight, m = 8;
    if (tour.manual && tour.pos) { tour.pos = clampCard(tour.pos[0], tour.pos[1]); applyCard(); return; }
    var all = holes.concat(avoid), anchor = holes[0] || null;
    var widths = ['', '248px', '216px'], best = null, bestK = Infinity, cw = 0, ch = 0;
    function cost(x, y) {
      var k = 0;
      all.forEach(function (r) { var o = overlap(x, y, cw, ch, r); if (o) k += 1 + o / Math.max(1, r.w * r.h) + o / (cw * ch); });
      return k;
    }
    for (var wi = 0; wi < widths.length; wi++) {
      if (c.style.width !== widths[wi]) c.style.width = widths[wi];
      cw = c.offsetWidth; ch = c.offsetHeight;
      if (tour.pos) {
        var kept = clampCard(tour.pos[0], tour.pos[1]);
        if (kept[0] === tour.pos[0] && kept[1] === tour.pos[1] && cost(kept[0], kept[1]) === 0) return;
      }
      best = null; bestK = Infinity;
      var free = false;
      if (!all.length) { best = [vw - cw - 24, vh - ch - 24]; free = true; }
      else {
        for (var y = m; y <= Math.max(m, vh - ch - m); y += 12) {
          for (var x = m; x <= Math.max(m, vw - cw - m); x += 12) {
            var k0 = cost(x, y), k = k0 * 1e6 + (anchor ? gap(x, y, cw, ch, anchor) : 0);
            if (k < bestK) { bestK = k; best = [x, y]; free = k0 === 0; }
          }
        }
      }
      if (free) break;
    }
    tour.pos = clampCard(best[0], best[1]);
    applyCard();
  }
  function drawVeil(holes) {
    var vw = window.innerWidth, vh = window.innerHeight;
    var d = 'M0 0H' + vw + 'V' + vh + 'H0Z';
    holes.forEach(function (r) { d += 'M' + r.x + ' ' + r.y + 'h' + r.w + 'v' + r.h + 'h' + (-r.w) + 'Z'; });
    tour.veil.setAttribute('viewBox', '0 0 ' + vw + ' ' + vh);
    tour.veil.firstChild.setAttribute('d', d);
    tour.veil.style.display = holes.length ? '' : 'none';
    while (tour.rings.length < holes.length) { var g = el('div', 'ph-ring'); g.setAttribute('aria-hidden', 'true'); document.body.appendChild(g); tour.rings.push(g); }
    tour.rings.forEach(function (g, i) {
      var r = holes[i];
      g.hidden = !r;
      if (r) { g.style.left = r.x + 'px'; g.style.top = r.y + 'px'; g.style.width = r.w + 'px'; g.style.height = r.h + 'px'; }
    });
  }
  /** Suit la cible à chaque image ; une minuterie prend le relais quand la page ne reçoit plus d'images (onglet masqué). */
  function loop() {
    if (!tour.on) return;
    tour.raf = window.requestAnimationFrame(loop);
    frame();
  }
  function frame() {
    if (!tour.on) return;
    if (!P.canEdit) { leaveTour(true); return; }
    var hide = !!P.briefing;
    if (tour.card.hidden !== hide) { tour.card.hidden = hide; tour.sig = ''; }
    if (hide) { drawVeil([]); return; }
    var st = tour.end ? null : STEPS[mem.step];
    if (st && st.check && st.check()) { advance(); return; }
    var holes = [], avoid = [];
    if (st) {
      (st.targets() || []).forEach(function (t) { var r = rectOf(t, 5); if (r) holes.push(r); });
      if (st.avoid) st.avoid().forEach(function (t) { var r = rectOf(t, 2); if (r) avoid.push(r); });
      if (st.checks) {
        var s = st.checks.map(function (k) { return k[1]() ? '1' : '0'; }).join('');
        if (s !== tour.checks) {
          tour.checks = s;
          $$('#ph-card-checks li').forEach(function (li, i) { li.classList.toggle('is-done', s.charAt(i) === '1'); $('.toc-sr', li).hidden = s.charAt(i) !== '1'; });
        }
      }
    }
    $$('.toc-overlay:not([hidden]) .toc-dialog, #pe-palette:not([hidden]) .pe-pal, #pe-menu:not([hidden]), #ph-drawer:not([hidden])').forEach(function (n) { var r = rectOf(n, 6); if (r) avoid.push(r); });
    var sig = JSON.stringify([holes, avoid, window.innerWidth, window.innerHeight, tour.card.offsetWidth, tour.card.offsetHeight]);
    if (sig === tour.sig) return;
    tour.sig = sig;
    drawVeil(holes);
    placeCard(holes, avoid);
  }
  function setText(node, t) { if (node.textContent !== t) node.textContent = t; }
  function renderCard() {
    var c = tour.card, i = mem.step, st = tour.end ? END : STEPS[i];
    setText($('#ph-card-title'), st.title);
    setText($('#ph-card-text'), st.text);
    setText($('#ph-card-n'), tour.end ? '' : 'Étape ' + (i + 1) + ' sur ' + STEPS.length);
    $('#ph-card-bar').style.width = (tour.end ? 100 : Math.round(i / STEPS.length * 100)) + '%';
    var ul = $('#ph-card-checks');
    ul.hidden = !st.checks;
    ul.innerHTML = (st.checks || []).map(function (k) { return '<li><i aria-hidden="true"></i><span>' + esc(k[0]) + '</span><span class="toc-sr" hidden>Fait.</span></li>'; }).join('');
    tour.checks = '';
    $('[data-ph="prev"]', c).hidden = tour.end;
    $('[data-ph="prev"]', c).disabled = i === 0;
    $('[data-ph="skip"]', c).hidden = tour.end;
    $('[data-ph="quit"]', c).hidden = tour.end;
    $('[data-ph="did"]', c).hidden = tour.end || !st.manual;
    $('[data-ph="finish"]', c).hidden = !tour.end;
    $('[data-ph="openhelp"]', c).hidden = !tour.end;
    $('[data-ph="example"]', c).hidden = tour.end || i !== 0 || (P.plan && P.plan.title === EX_TITLE);
    var sp = tour.live.children;
    setText(sp[0], st.title);
    setText(sp[1], st.text);
  }
  function showStep(i, focus) {
    buildTour();
    var st = STEPS[i];
    if (st.need === 'unit' && !tourUnit()) { i = 0; st = STEPS[0]; }
    mem.step = i; mem.active = true; mem.seen = true; mem.done = false;
    saveMem();
    tour.end = false; tour.ctx = {}; tour.manual = false; tour.pos = null; tour.sig = '';
    if (st.enter) st.enter(tour.ctx);
    if (st.prep) {
      st.prep();
      setTimeout(function () { if (tour.on && !tour.end && STEPS[mem.step] === st) { st.prep(); tour.sig = ''; } }, 350);
    }
    hideHint();
    renderCard();
    tour.card.hidden = false;
    if (!tour.on) { tour.on = true; tour.raf = window.requestAnimationFrame(loop); tour.timer = setInterval(frame, 300); }
    if (focus) tour.card.focus({ preventScroll: true });
    syncDrawerFoot();
  }
  function advance(focus) {
    if (!tour.on || tour.end) return;
    if (!calm.matches) { tour.card.classList.remove('is-ok'); void tour.card.offsetWidth; tour.card.classList.add('is-ok'); }
    var nx = mem.step + 1;
    while (nx < STEPS.length && STEPS[nx].skip && STEPS[nx].skip()) nx++;
    if (nx < STEPS.length) { showStep(nx, focus); return; }
    tour.end = true; tour.manual = false; tour.pos = null; tour.sig = '';
    mem.done = true; mem.active = false; mem.step = 0;
    saveMem();
    renderCard();
    if (focus) tour.card.focus({ preventScroll: true });
    syncDrawerFoot();
  }
  function stopTour() {
    var had = tour.card && tour.card.contains(document.activeElement);
    tour.on = false; tour.end = false;
    window.cancelAnimationFrame(tour.raf);
    clearInterval(tour.timer);
    if (tour.card) { tour.card.hidden = true; drawVeil([]); }
    if (had) { var h = $('[data-run="help"]'); if (h) h.focus(); }
    syncDrawerFoot();
  }
  function leaveTour(silent) {
    if (!tour.on) return;
    if (tour.end) { finishTour(); return; }
    mem.active = false;
    saveMem();
    stopTour();
    if (!silent) E.say('Tutoriel en pause : reprenez-le depuis l’aide (touche ?).');
  }
  function finishTour() {
    mem.done = true; mem.active = false; mem.step = 0;
    saveMem();
    stopTour();
  }
  function canTour() { return P.loaded && P.canEdit && !P.readonlyLink && !P.briefing; }
  function startTour(fromStart) {
    if (!canTour()) {
      E.say(P.briefing ? 'Quittez le briefing pour suivre le tutoriel.' : 'Le tutoriel demande un plan que vous pouvez modifier : ouvrez un plan d’exemple depuis l’aide.', true);
      return;
    }
    if (tour.on && !tour.end && !fromStart) { tour.card.focus({ preventScroll: true }); return; }
    showStep(fromStart || mem.done ? 0 : Math.min(mem.step, STEPS.length - 1), true);
  }
  function tourSignal(name, arg) {
    if (!tour.on || tour.end) return;
    var st = STEPS[mem.step];
    var ok = false;
    try { ok = !!(st.on && st.on[name] && st.on[name](arg)); } catch (e) { ok = false; }
    if (ok) advance();
  }
  ['added', 'commit', 'phases', 'play', 'mode', 'markersImported'].forEach(function (n) { E.on(n, function (a) { tourSignal(n, a); }); });

  /* ====================================================================================================
   * Plan d'exemple : un plan privé du membre, posé autour du centre de la vue, sur 3 km
   * ==================================================================================================== */
  var EX = {
    layers: [{ uid: 'base', name: 'Manœuvre', visible: true, locked: false, opacity: 1 }, { uid: 'feux', name: 'Appuis feux', visible: true, locked: false, opacity: 1 }],
    notes: {
      situation: 'Section ennemie retranchée sur OBJ ACIER, un élément de reconnaissance à l’est.',
      mission: 'S’emparer de OBJ ACIER pour H+1:10.',
      execution: 'ALPHA 3 appuie depuis le sud-ouest ; ALPHA 1 et ALPHA 2 donnent l’assaut par AXE NORD et ITI JAUNE.'
    },
    timeline: { h: '', dur: 90, start: -10, zulu: false, phases: { p1: 0, p2: 30, p3: 55 }, events: [{ uid: 'v1', t: 0, label: 'Heure H', kind: 'h', ref: '' }, { uid: 'v2', t: 50, label: 'Tirs d’appui', kind: 'event', ref: '' }] },
    phases: [
      { uid: 'p1', ord: 0, name: 'Infiltration', note: 'Mise en place sur la ligne de débouché, discrétion maximale.', time_label: 'H' },
      { uid: 'p2', ord: 1, name: 'Appui', note: 'ALPHA 3 en appui par le feu depuis le mouvement de terrain au sud-ouest de l’objectif.', time_label: 'H+00:30' },
      { uid: 'p3', ord: 2, name: 'Assaut', note: 'ALPHA 1 et ALPHA 2 s’emparent de OBJ ACIER.', time_label: 'H+00:55' }
    ],
    // uid, type, géométrie, points (mètres autour du centre), propriétés, phases, calque.
    els: [
      ['ao', 'ao', 'area', [[-1500, -1190], [1360, -1260], [1500, 1190], [-1360, 1260]], { label: 'AO TRIDENT', dash: 'dash' }, [], 'base'],
      ['obj', 'objective', 'circle', [[560, 520]], { radius: 230, color: '#f24d47', fill: 0.14, label: 'OBJ ACIER' }, ['p2', 'p3'], 'base'],
      ['pl1', 'phase_line', 'line', [[-520, 980], [-420, 280], [-560, -420], [-450, -980]], { label: 'PL BLEU', color: '#62b0f5' }, [], 'base'],
      ['pl2', 'phase_line', 'line', [[100, 1050], [180, 350], [50, -350], [130, -910]], { label: 'PL ROUGE', color: '#f24d47' }, [], 'base'],
      ['axe', 'axis', 'line', [[-1080, -490], [-450, -210], [100, 210], [420, 450]], { color: '#5999ff', arrow: 'end', awidth: 180, label: 'AXE NORD' }, [], 'base'],
      ['rte', 'route', 'line', [[-1190, 350], [-800, 520], [-320, 660], [180, 740], [490, 630]], { color: '#fae04d', arrow: 'end', label: 'ITI JAUNE', cp: true, speed: 12 }, [], 'base'],
      ['sbf', 'sbf', 'line', [[-70, -380], [450, 350]], { color: '#5999ff', arrow: 'end', label: 'SBF 1' }, ['p2'], 'base'],
      ['fan', 'fan', 'point', [[-70, -380]], { color: '#ff9433', radius: 910, bearing: 38, arc: 50, minRange: 0, fill: 0.1, label: 'MAG', mask: true }, ['p2'], 'base'],
      ['nai', 'nai', 'area', [[800, -210], [1220, -180], [1190, 180], [840, 140]], { color: '#fae04d', fill: 0.08, label: 'NAI 3' }, [], 'base'],
      ['hlz', 'lz', 'circle', [[-1050, -880]], { radius: 130, color: '#73d973', fill: 0.1, label: 'HLZ MERLE' }, [], 'base'],
      ['mines', 'minefield', 'rect', [[740, 800], [1120, 980]], { color: '#ff9433', fill: 0.12, dash: 'dash', label: 'MINES' }, [], 'base'],
      ['ring', 'ring', 'circle', [[-1050, -210]], { radius: 630, rings: 3, color: '#ff9433', label: 'MO 81' }, [], 'feux'],
      ['cp1', 'checkpoint', 'point', [[-450, -210]], { color: '#f2f2f2', label: 'CP 1' }, [], 'base'],
      ['med', 'medevac', 'point', [[-980, -800]], { color: '#f24d47', label: 'MEDEVAC' }, [], 'base'],
      ['cdu', 'unit', 'point', [[-1050, -420]], { affil: 'F', unitType: 'hq', echelon: 'company', callsign: 'ALPHA 6', hq: true, higher: '2 REI', ord: 0 }, [], 'base'],
      ['a1', 'unit', 'point', [[-980, -520]], { affil: 'F', unitType: 'infantry', echelon: 'section', callsign: 'ALPHA 1', parent: 'cdu', ord: 0, track: [{ t: 0, p: [-980, -520] }, { t: 30, p: [-380, -180] }, { t: 55, p: [320, 380] }, { t: 70, p: [520, 490] }] }, [], 'base'],
      ['a2', 'unit', 'point', [[-1190, 350]], { affil: 'F', unitType: 'infantry', echelon: 'section', callsign: 'ALPHA 2', parent: 'cdu', ord: 1, route: 'rte', track: [{ t: 0, p: [-1190, 350] }, { t: 60, p: [490, 630] }] }, [], 'base'],
      ['a3', 'unit', 'point', [[-880, -630]], { affil: 'F', unitType: 'mech', echelon: 'section', callsign: 'ALPHA 3', parent: 'cdu', ord: 2, mod: 'reinforced', track: [{ t: 0, p: [-880, -630] }, { t: 25, p: [-70, -380] }] }, [], 'base'],
      ['mo', 'unit', 'point', [[-1050, -210]], { affil: 'F', unitType: 'mortar', echelon: 'squad', callsign: 'MORTIERS', parent: 'cdu', ord: 3, info: '2 x 81' }, [], 'base'],
      ['eni1', 'unit', 'point', [[600, 560]], { affil: 'H', unitType: 'infantry', echelon: 'platoon', callsign: 'ENI 1', ord: 0 }, [], 'base'],
      ['eni2', 'unit', 'point', [[1010, 0]], { affil: 'H', unitType: 'recon', echelon: 'squad', callsign: 'ENI 2', ord: 1, status: 'planned' }, [], 'base']
    ]
  };
  var EX_TITLE = 'Plan d’exemple (tutoriel)';
  var exBusy = false;
  function exampleDoc() {
    var map = E.tm.map;
    var c = E.tm.toWorld(map.getCenter());
    var size = Number(P.terrain && P.terrain.config && P.terrain.config.worldSize) || 0;
    var cx = Math.round(c[0]), cy = Math.round(c[1]);
    // Le plan tient dans le terrain : le centre est ramené à l'intérieur s'il le faut.
    if (size > 3600) { cx = Math.max(1700, Math.min(size - 1700, cx)); cy = Math.max(1500, Math.min(size - 1500, cy)); }
    var at = function (p) { return [cx + p[0], cy + p[1]]; };
    var z = Math.round(map.getZoom() + Math.log(Math.max(1, map.getSize().x * E.tm.metersPerPixel()) / 4600) / Math.LN2);
    z = Math.max(map.getMinZoom(), Math.min(map.getMaxZoom(), z));
    var meta = { layers: EX.layers, notes: EX.notes, legend: [], side: P.meta.side || 'WEST', view: { x: cx, y: cy, z: z }, timeline: EX.timeline };
    var els = EX.els.map(function (r, i) {
      var props = JSON.parse(JSON.stringify(r[4]));
      if (props.track) props.track.forEach(function (k) { k.p = at(k.p); });
      return { uid: r[0], kind: r[1], geom: r[2], pts: r[3].map(at), props: props, phases: r[5], layer: r[6], z: i + 1 };
    });
    return { meta: meta, phases: EX.phases, elements: els };
  }
  function examplePlan() {
    if (exBusy) return;
    if (!P.loaded || !E.tm || !P.plan) { E.say('Le plan d’exemple se crée une fois la carte chargée.', true); return; }
    exBusy = true;
    var doc = exampleDoc();
    var fail = function (j) {
      exBusy = false;
      E.say((j && E.ERR[j.error]) || 'Plan d’exemple non créé : le site a refusé la demande. Réessayez dans un instant.', true);
    };
    E.say('Création du plan d’exemple…');
    if (E.flush) E.flush();
    var base = '/atak/toc/api/plans';
    E.api(base, { title: EX_TITLE, world: P.plan.world || '', mapId: P.plan.map_id, side: doc.meta.side }).then(function (j) {
      if (!j || !j.ok || !j.id) { fail(j); return null; }
      var id = j.id;
      // Refus en cours de route : le plan vide qui vient d'être créé est retiré.
      var undo = function (e) { E.api(base + '/' + id + '/delete', {}).catch(function () {}); throw e; };
      return E.api(base + '/' + id, { meta: doc.meta }).then(function (a) {
        if (!a || !a.ok) throw a || {};
        return E.api(base + '/' + id + '/phases', { phases: doc.phases });
      }).then(function (b) {
        if (!b || !b.ok) throw b || {};
        return E.api(base + '/' + id + '/elements', { upserts: doc.elements, deletes: [], base: {} });
      }).then(function (d) {
        if (!d || !d.ok) throw d || {};
        window.location.href = E.BOOT.base + '/atak/toc/plans/' + id + '?tutoriel=1';
      }, undo);
    }).catch(function (j) {
      if (j && typeof j === 'object' && 'error' in j) { fail(j); return; }
      exBusy = false;
      E.say('Plan d’exemple non créé : réseau indisponible.', true);
    });
  }

  /* ====================================================================================================
   * Illustrations des modes d'emploi : SVG animés par la feuille de style (aucune image, aucun texte)
   * ==================================================================================================== */
  function U(x, y, c) { return '<g transform="translate(' + x + ' ' + y + ')" class="ph-i-' + (c || 'f') + '"><rect x="-11" y="-7" width="22" height="14"/><path d="M-11-7L11 7M11-7L-11 7"/></g>'; }
  function CUR(x, y) { return '<path class="ph-i-cur" transform="translate(' + x + ' ' + y + ')" d="M0 0l11 6-5 1.6L4.4 13z"/>'; }
  function A(cls, inner, style) { return '<g class="' + cls + '"' + (style ? ' style="' + style + '"' : '') + '>' + inner + '</g>'; }
  function MAP(x, y, w, h) {
    return '<rect class="ph-i-map" x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '"/>' +
      '<path class="ph-i-ctr" d="M' + (x + 6) + ' ' + (y + h * 0.7) + 'q' + (w * 0.25) + ' ' + (-h * 0.5) + ' ' + (w * 0.5) + ' ' + (-h * 0.2) + 't' + (w * 0.42) + ' ' + (-h * 0.25) + '"/>';
  }
  function PAN(x, y, w, h) { return '<rect class="ph-i-pan" x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '"/>'; }
  function go(dx, dy, inner, delay) { return A('ph-a-go', inner, '--dx:' + dx + 'px;--dy:' + dy + 'px' + (delay ? ';animation-delay:' + delay + 's' : '')); }
  var ILL = {
    place: function () {
      return PAN(1, 1, 52, 88) + '<path class="ph-i-dim" d="M7 10h40M7 62h16v14H7zM31 62h16v14H31zM31 36h16v14H31z"/>' + MAP(56, 1, 143, 88) + '<g opacity=".35">' + U(15, 43) + '</g>' + go(100, 4, U(15, 43) + CUR(18, 46));
    },
    draw: function () {
      return MAP(1, 1, 198, 88) + '<path class="ph-i-acc ph-a-draw" pathLength="1" d="M24 70L70 34l48 22 52-34"/><path class="ph-i-accf ph-a-on" d="M170 22l-12 2 7 9z"/>' +
        '<g class="ph-i-dot"><circle cx="24" cy="70" r="3"/><circle class="ph-a-on" style="animation-delay:-3.2s" cx="70" cy="34" r="3"/><circle class="ph-a-on" style="animation-delay:-2.2s" cx="118" cy="56" r="3"/></g>';
    },
    select: function () {
      return MAP(1, 1, 198, 88) + U(66, 32) + U(108, 52) + U(146, 34) + U(30, 72, 'h') +
        '<rect class="ph-i-marq ph-a-grow" x="46" y="16" width="120" height="50"/><path class="ph-i-sel ph-a-on" d="M52 22h28v20H52zM94 42h28v20H94zM132 24h28v20h-28z"/>' + go(120, 50, CUR(46, 16));
    },
    style: function () {
      return MAP(1, 1, 130, 88) + PAN(134, 1, 65, 88) + '<path class="ph-i-dim" d="M141 12h50M141 24h34"/>' +
        '<g class="ph-a-s3"><path class="ph-i-f" d="M24 62l22-38 40 8 22 34z"/><rect class="ph-i-ff" x="141" y="38" width="14" height="14"/></g>' +
        '<g class="ph-a-s3" style="animation-delay:-4s"><path class="ph-i-h" d="M24 62l22-38 40 8 22 34z"/><rect class="ph-i-hf" x="159" y="38" width="14" height="14"/></g>' +
        '<g class="ph-a-s3" style="animation-delay:-2s"><path class="ph-i-amb" stroke-dasharray="5 4" d="M24 62l22-38 40 8 22 34z"/><rect class="ph-i-ambf" x="177" y="38" width="14" height="14"/></g>' +
        '<path class="ph-i-dim" d="M141 38h14v14h-14zM159 38h14v14h-14zM177 38h14v14h-14z"/><g class="ph-i-dot"><circle cx="24" cy="62" r="3"/><circle cx="46" cy="24" r="3"/><circle cx="86" cy="32" r="3"/><circle cx="108" cy="66" r="3"/></g>';
    },
    copy: function () {
      return MAP(1, 10, 88, 70) + MAP(111, 10, 88, 70) + '<path class="ph-i-dim" d="M1 10h88M111 10h88"/><path class="ph-i-acc" d="M93 45h12M100 40l6 5-6 5"/>' + U(45, 48) + go(110, 0, '<g stroke-dasharray="4 3">' + U(45, 48) + '</g>');
    },
    orbat: function () {
      return PAN(1, 1, 66, 88) + '<path class="ph-i-dim" d="M10 14v56M10 26h10M10 48h10M10 70h10M26 14h30M26 26h24M26 70h24"/><g transform="scale(.6)">' + U(26, 23) + '</g>' +
        MAP(70, 1, 129, 88) + '<g opacity=".35"><g transform="translate(33 48) scale(.6)">' + U(0, 0) + '</g></g>' + go(92, -8, U(38, 48) + CUR(41, 51));
    },
    layers: function () {
      return PAN(1, 1, 198, 88) + '<path class="ph-i-f" d="M70 66l40-14 40 14-40 14z"/><path class="ph-i-amb ph-a-blink" d="M70 48l40-14 40 14-40 14z"/><path class="ph-i-acc" d="M70 30l40-14 40 14-40 14z"/>' +
        '<path class="ph-i-dim" d="M20 30h30M20 48h30M20 66h30"/><g class="ph-i-eye"><path d="M162 30q8-9 16 0q-8 9-16 0zM162 66q8-9 16 0q-8 9-16 0z"/><path class="ph-a-blink" d="M162 48q8-9 16 0q-8 9-16 0z"/></g>';
    },
    phases: function () {
      return MAP(1, 1, 198, 56) + '<path class="ph-i-dim" stroke-dasharray="3 4" d="M36 30h120"/>' + A('ph-a-slide', U(36, 30), '--dx:120px') + PAN(1, 61, 198, 28) +
        '<path class="ph-i-ph" d="M8 66h56v10H8zM68 66h60v10H68zM132 66h60v10h-60z"/><path class="ph-i-accf" d="M36 79l4 4-4 4-4-4zM156 79l4 4-4 4-4-4z"/>' + A('ph-a-slide', '<path class="ph-i-acc" d="M36 61v28"/>', '--dx:120px');
    },
    briefing: function () {
      var s = MAP(1, 1, 198, 88) + '<path class="ph-i-dim" d="M8 8h10M8 8v10M192 8h-10M192 8v10M8 82h10M8 82V72M192 82h-10M192 82V72"/>' + PAN(50, 62, 100, 20);
      [[40, 30, 72], [96, 42, 100], [150, 28, 128]].forEach(function (p, i) {
        s += '<g class="ph-a-s3" style="animation-delay:' + (-6 + i * 2) % 6 + 's">' + U(p[0], p[1]) + '<circle class="ph-i-accf" cx="' + p[2] + '" cy="72" r="4"/></g>';
      });
      return s + '<g class="ph-i-dim"><circle cx="72" cy="72" r="4"/><circle cx="100" cy="72" r="4"/><circle cx="128" cy="72" r="4"/></g>';
    },
    terrain: function () {
      return PAN(1, 1, 198, 88) + '<path class="ph-i-gnd" d="M1 89V60q26-34 52-18t44 4q20-34 44-6t28 22q14 6 30 4v23z"/><g class="ph-i-dot"><circle cx="38" cy="32" r="3.5"/><circle cx="176" cy="60" r="3.5"/></g>' +
        '<path class="ph-i-acc ph-a-draw" pathLength="1" d="M38 32L118 48"/><path class="ph-i-h ph-a-on" stroke-dasharray="4 4" d="M118 48l58 12"/>';
    },
    view3d: function () {
      return PAN(1, 1, 198, 88) + '<g class="ph-a-tilt"><rect class="ph-i-map" x="40" y="8" width="120" height="74"/><path class="ph-i-ctr" d="M40 26h120M40 45h120M40 64h120M70 8v74M100 8v74M130 8v74"/>' +
        '<path class="ph-i-acc" d="M56 68q30-40 52-18t38-22"/>' + U(120, 34) + '</g>';
    },
    importGame: function () {
      var s = PAN(4, 12, 70, 50) + '<path class="ph-i-dim" d="M30 62v12M18 76h42"/>' + MAP(110, 1, 89, 88) + '<path class="ph-i-acc" d="M82 37h18M94 31l7 6-7 6"/>';
      [[24, 28, 0, 'f'], [50, 44, -1.4, 'h'], [38, 36, -2.8, 'amb']].forEach(function (m) { s += go(104, 6, '<circle class="ph-i-' + m[3] + 'f" cx="' + m[0] + '" cy="' + m[1] + '" r="4.5"/>', m[2]); });
      return s;
    },
    importFile: function () {
      return MAP(60, 1, 139, 88) + '<rect class="ph-i-marq ph-a-on" x="68" y="8" width="123" height="74"/>' + go(98, 6, '<path class="ph-i-file" d="M10 26h16l8 8v26H10zM26 26v8h8"/><path class="ph-i-dim" d="M15 42h14M15 49h14"/>' + CUR(26, 50));
    },
    exportPlan: function () {
      var s = MAP(1, 1, 110, 88) + '<path class="ph-i-f" d="M18 62l24-34 30 8 22 30z"/><path class="ph-i-acc" d="M118 45h14M126 39l7 6-7 6"/>';
      [0, 1, 2].forEach(function (i) {
        s += '<g class="ph-a-pop" style="animation-delay:' + (-4.5 + i * 0.5) + 's"><path class="ph-i-file" d="M' + (140 + i * 19) + ' 30h9l5 5v24h-14zM' + (149 + i * 19) + ' 30v5h5"/></g>';
      });
      return s;
    },
    publish: function () {
      return MAP(1, 1, 96, 88) + '<path class="ph-i-f" d="M14 62l22-32 26 8 20 28z"/>' + U(36, 30) +
        '<g class="ph-i-acc"><path class="ph-a-wave" d="M106 34q8 11 0 22"/><path class="ph-a-wave" style="animation-delay:-.5s" d="M116 28q13 17 0 34"/><path class="ph-a-wave" style="animation-delay:-1s" d="M126 22q18 23 0 46"/></g>' +
        '<rect class="ph-i-pan" x="150" y="8" width="42" height="74" rx="5"/><path class="ph-i-dim" d="M165 76h12"/><path class="ph-i-f ph-a-on" d="M157 52l9-18 12 5 8 15z"/>';
    },
    panels: function () {
      return MAP(1, 1, 198, 88) + '<g class="ph-a-fold" style="transform-origin:1px 45px">' + PAN(1, 1, 46, 62) + '<path class="ph-i-dim" d="M8 12h30M8 22h22M8 32h30"/></g>' +
        '<g class="ph-a-fold" style="transform-origin:199px 45px;animation-delay:-1.2s">' + PAN(153, 1, 46, 62) + '<path class="ph-i-dim" d="M160 12h30M160 22h22"/></g>' + PAN(1, 65, 198, 24) + '<path class="ph-i-ph" d="M8 72h60v10H8zM72 72h70v10H72z"/>';
    },
    undo: function () {
      return MAP(1, 1, 198, 88) + '<path class="ph-i-dim" stroke-dasharray="3 4" d="M52 50h96"/>' + A('ph-a-back', U(52, 50), '--dx:96px') + '<path class="ph-i-amb ph-a-late" d="M70 26q30-18 60 0M70 26l2-10M70 26l10 2"/>';
    },
    palette: function () {
      var s = PAN(1, 1, 198, 88) + '<rect class="ph-i-map" x="30" y="8" width="140" height="74"/><path class="ph-i-dim" d="M30 26h140M56 17h40M40 38h70M40 52h90M40 66h56"/><circle class="ph-i-dim" cx="42" cy="17" r="4"/><path class="ph-i-acc ph-a-blink" d="M100 12v10"/>';
      [32, 46, 60].forEach(function (y, i) { s += '<rect class="ph-i-sel ph-a-s3" style="animation-delay:' + (-6 + i * 2) % 6 + 's" x="34" y="' + y + '" width="132" height="12"/>'; });
      return s;
    }
  };
  function illustration(id) { return '<svg class="ph-ill" viewBox="0 0 200 90" aria-hidden="true" focusable="false">' + ILL[id]() + '</svg>'; }

  /* ---------- Modes d'emploi : identifiant de l'illustration, titre, mots de recherche, étapes ---------- */
  var HOW = [
    ['place', 'Poser et déplacer un symbole', 'unité bibliothèque glisser ajouter bouger', [
      'Ouvrez l’onglet Bibliothèque du volet de gauche et cherchez le symbole voulu.',
      'Glissez-le sur la carte, ou cliquez-le puis cliquez sur la carte.',
      'Pour le déplacer, reprenez l’outil Sélection (V) et glissez-le.',
      'Les flèches du clavier le décalent de 10 m (Maj : 100 m, Alt : 1 m).']],
    ['draw', 'Tracer un trait, une flèche ou une zone', 'dessiner ligne polygone rectangle cercle ellipse axe', [
      'Choisissez l’outil dans la barre du haut, ou un graphisme dans la bibliothèque.',
      'Cliquez sur la carte à chaque point du tracé.',
      'Terminez par un double-clic ou par Entrée ; Retour arrière retire le dernier point.',
      'Rectangle, cercle et ellipse se tracent en deux clics ou en glissant.']],
    ['select', 'Choisir plusieurs éléments : clic, cadre, lasso', 'sélection multiple sélectionner grouper', [
      'Cliquez un élément, puis Maj ou Ctrl + clic pour en ajouter ou en retirer.',
      'Glissez dans le vide pour tracer un cadre autour des éléments.',
      'Le lasso (Q) entoure une forme libre.',
      'Alt + clic choisit l’élément du dessous ; Ctrl + A choisit tout.']],
    ['style', 'Changer l’apparence d’un élément', 'couleur épaisseur tirets remplissage propriétés poignées style', [
      'Choisissez l’élément : ses réglages s’ouvrent dans l’onglet Propriétés, à droite.',
      'Changez son nom, sa couleur, son épaisseur, ses tirets ou son remplissage.',
      'Les poignées sur la carte changent sa forme, sa taille et son orientation.',
      'Ctrl + Alt + C puis Ctrl + Alt + V copient le style d’un élément vers un autre.']],
    ['copy', 'Copier-coller, aussi d’un plan à l’autre', 'presse-papiers dupliquer couper', [
      'Choisissez les éléments, puis Ctrl + C.',
      'Ouvrez l’autre plan, dans le même onglet du navigateur ou dans un autre.',
      'Ctrl + V colle sous le curseur ; Ctrl + Maj + V colle à la même place.',
      'Alt + glisser duplique un élément en le déplaçant.']],
    ['orbat', 'Organiser les unités dans l’ORBAT', 'arbre ordre de bataille rattacher unité mère', [
      'Ouvrez l’onglet ORBAT du volet de gauche : chaque unité du plan y figure.',
      'Glissez une unité sur une autre pour la rattacher, ou entre deux pour la ranger.',
      'Glissez une unité de l’arbre vers la carte pour la poser ou la déplacer.',
      '« ORBAT du site » reprend les unités de la communauté ; « Depuis le jeu » ajoute les groupes en liaison.']],
    ['layers', 'Ranger le plan en calques', 'calque masquer verrouiller opacité ordre', [
      'Ouvrez l’onglet Calques du volet de gauche.',
      'L’œil masque un calque, le cadenas le protège, le curseur règle son opacité.',
      'Glissez un calque pour changer l’ordre : celui du haut est dessiné devant.',
      'Le calque actif reçoit les nouveaux éléments.']],
    ['phases', 'Phases et images clés : faire bouger le plan dans le temps', 'frise temps clé auto lecture mouvement H+', [
      'Cliquez « Phase » dans la frise pour ajouter une phase ; glissez son début pour le régler.',
      'Activez « Clé auto », cliquez la frise à l’instant voulu, puis déplacez l’unité.',
      'L’unité garde une position par instant et glisse de l’une à l’autre.',
      'Lancez la lecture pour vérifier le déroulé.']],
    ['briefing', 'Présenter le plan en briefing', 'plein écran présentation phase par phase', [
      'Cliquez « Briefing » dans la barre du haut : le plan passe en plein écran.',
      'Les flèches gauche et droite passent d’une phase à l’autre.',
      'Les unités glissent d’une position à la suivante.',
      'Échap quitte le briefing.']],
    ['terrain', 'Relief : mesure, ligne de vue, zone vue', 'altitude distance azimut coupe pente visibilité', [
      'L’outil Mesure (M) donne la distance, l’azimut et la surface.',
      'Ligne de vue (O) : cliquez l’observateur, puis la cible ; la coupe montre ce qui masque.',
      'Zone vue (W) : cliquez un point pour voir ce qu’il découvre.',
      'Ces outils demandent le relief du terrain ; ils ne comptent ni les bâtiments ni la végétation.']],
    ['view3d', 'Voir le plan en 3D', 'vue 3D relief tourner incliner', [
      'Cliquez « 3D » dans la barre des outils, ou appuyez sur 3.',
      'Glissez pour déplacer la vue ; clic droit ou Ctrl + glisser pour tourner et incliner ; molette pour rapprocher.',
      'En 3D, on choisit, on déplace et on pose des symboles.',
      'Le tracé des traits et des zones se fait en 2D.']],
    ['importGame', 'Importer depuis le jeu : marqueurs et plan dessiné', 'importer marqueurs jeu ATAK téléphone', [
      'Ouvrez le menu « Importer » de la barre du haut.',
      '« Marqueurs présents en jeu » reprend les marqueurs posés sur la carte du jeu.',
      '« Plan dessiné en jeu » reprend le plan du camp tracé sur les téléphones.',
      'Ctrl + Z annule un import.']],
    ['importFile', 'Importer un fichier', 'importer JSON GeoJSON KML déposer', [
      'Déposez un fichier JSON, GeoJSON ou KML sur la carte.',
      'Ou ouvrez le menu « Importer », puis « Fichier JSON, GeoJSON ou KML ».',
      'Les éléments s’ajoutent au plan ; Ctrl + Z annule l’import.']],
    ['exportPlan', 'Exporter le plan', 'exporter PNG PDF image fiche JSON GeoJSON KML', [
      'Ouvrez le menu « Exporter » de la barre du haut.',
      '« Image PNG » enregistre la vue affichée ; « Fiche de briefing PDF » prépare un document complet.',
      'Le fichier JSON rouvre le plan sur Athena ; GeoJSON et KML servent aux autres outils de cartographie.']],
    ['publish', 'Envoyer le plan en jeu', 'envoyer jeu camp téléphone publier', [
      'Cliquez « Envoyer en jeu » : une fenêtre demande le camp et le contenu.',
      'Choisissez le plan entier ou une seule phase.',
      'Appuyez sur « Envoyer » : rien ne part avant.',
      'Le jeu reçoit le plan dans les secondes qui suivent s’il est en liaison avec Athena.']],
    ['panels', 'Replier les volets pour agrandir la carte', 'volet replier déplier place écran frise', [
      'Les languettes sur les bords de la carte replient le volet de gauche ou celui de droite.',
      'Le chevron au bout de la frise la replie.',
      'Un nouveau clic les rouvre.']],
    ['undo', 'Annuler, rétablir, retrouver une version', 'annuler rétablir versions restaurer historique enregistrer', [
      'Ctrl + Z annule la dernière action ; Ctrl + Y la rétablit.',
      '« Versions » garde des états du plan : enregistrez-en un avant un gros changement.',
      'Restaurer une version remet le plan dans cet état.',
      'Le plan s’enregistre tout seul : son état s’affiche sous le titre.']],
    ['palette', 'Trouver une commande avec la palette', 'chercher commande Ctrl K grille heure', [
      'Appuyez sur Ctrl + K.',
      'Tapez quelques lettres : action, symbole, unité, calque ou phase.',
      'Une grille (0425 1169) centre la carte ; une heure (H+0:30) place la frise.',
      'Entrée lance le choix surligné.']]
  ];

  /* ---------- Graphismes : une phrase par type du catalogue (un type inconnu garde son seul libellé) ---------- */
  var MEANING = {
    unit: 'Une unité, dessinée selon son camp, sa fonction et son échelon.',
    text: 'Un texte libre posé sur la carte.',
    icon: 'Un repère simple pour marquer un endroit.',
    checkpoint: 'Point où une unité rend compte de son passage.',
    rally: 'Point où l’unité se regroupe après une action ou une dispersion.',
    trp: 'Point connu de tous qui sert à désigner et à régler les tirs.',
    objpt: 'Objectif limité à un point : bâtiment, carrefour, pièce.',
    cleared: 'Bâtiment déjà fouillé et tenu pour sûr.',
    uncleared: 'Bâtiment qui reste à fouiller.',
    sbf: 'Position d’où une unité appuie par ses tirs la manœuvre d’une autre.',
    abf: 'Position d’où une unité détruit l’ennemi par ses tirs, sans donner l’assaut.',
    route: 'Chemin à suivre, avec ses points de passage et ses délais.',
    axis: 'Direction générale de la progression d’une unité, avec sa largeur.',
    attack: 'Direction précise de l’assaut.',
    support: 'Direction dans laquelle une unité apporte son appui.',
    move: 'Déplacement prévu d’une unité.',
    phase_line: 'Ligne qui sert à suivre et à coordonner la progression.',
    ld: 'Ligne franchie à l’heure H pour lancer l’attaque.',
    loa: 'Ligne à ne pas dépasser sans ordre.',
    boundary: 'Limite entre les zones de deux unités voisines.',
    objective: 'Zone à prendre ou à tenir.',
    ao: 'Zone confiée à l’unité pour son opération.',
    nai: 'Zone à surveiller pour y déceler l’ennemi.',
    ea: 'Zone où l’ennemi doit être arrêté et détruit par les tirs.',
    aa: 'Zone où l’unité se regroupe et se prépare avant l’action.',
    enemy: 'Zone tenue ou occupée par l’ennemi.',
    lz: 'Zone où les hélicoptères se posent.',
    infil: 'Endroit par lequel l’unité entre dans la zone.',
    exfil: 'Endroit par lequel l’unité quitte la zone.',
    nfa: 'Zone où aucun tir n’est permis sans accord.',
    ffa: 'Zone où le tir est libre, sans coordination de plus.',
    rfa: 'Zone où le tir est soumis à des restrictions.',
    minefield: 'Zone minée, connue ou supposée.',
    line: 'Un trait libre.',
    arrow: 'Une flèche libre.',
    polygon: 'Une zone libre à plusieurs côtés.',
    rect: 'Un rectangle libre.',
    ellipse: 'Une ellipse libre.',
    circle: 'Un cercle libre.',
    medevac: 'Point de ramassage des blessés pour leur évacuation.',
    ccp: 'Point où les blessés sont rassemblés et reçoivent les premiers soins.',
    supply: 'Point où l’unité reçoit munitions, eau et vivres.',
    op: 'Poste d’où l’on observe une zone.',
    target: 'Cible repérée pour un tir.',
    decon: 'Point où le personnel et le matériel sont décontaminés.',
    fan: 'Secteur que couvre une arme, selon sa portée et son ouverture.',
    fscl: 'Ligne au-delà de laquelle les tirs d’appui n’ont plus à être coordonnés avec l’unité au sol.',
    cfl: 'Ligne au-delà de laquelle l’artillerie tire sans coordination de plus.',
    rfl: 'Ligne entre deux unités qui convergent : aucun tir ne la franchit sans accord.',
    flot: 'Ligne des unités amies les plus avancées.',
    obstacle: 'Suite d’obstacles : barbelés, fossés, abattis.',
    textbox: 'Un texte relié par un trait à l’endroit qu’il commente.',
    bp: 'Position préparée d’où une unité combat.',
    tai: 'Zone où l’on prévoit de frapper l’ennemi quand il s’y présente.',
    pz: 'Zone où les hélicoptères viennent prendre l’unité.',
    dz: 'Zone où le personnel ou le matériel est largué.',
    cbrn: 'Zone contaminée, à éviter ou à traverser protégé.',
    obstacle_belt: 'Zone où les obstacles sont regroupés pour freiner l’ennemi.',
    ring: 'Cercles de portée autour d’une arme ou d’un poste.'
  };

  /* ====================================================================================================
   * Tiroir d'aide (non modal)
   * ==================================================================================================== */
  var drawer = { el: null, open: false, tab: 'how', back: null };
  var TABS = [['how', 'Comment faire'], ['keys', 'Raccourcis'], ['gfx', 'Graphismes']];
  function fold(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[’`]/g, '\''); }
  function buildDrawer() {
    if (drawer.el) return;
    var d = el('aside', 'toc-drawer ph-drawer');
    d.id = 'ph-drawer';
    d.hidden = true;
    d.tabIndex = -1;
    d.setAttribute('aria-labelledby', 'ph-drawer-title');
    var h = '<div class="toc-drawer-head"><h2 id="ph-drawer-title">Aide de l’éditeur</h2><button type="button" class="toc-btn is-small" data-ph="close">Fermer <kbd>Échap</kbd></button></div>' +
      '<div class="ph-drawer-tools"><label class="pe-search">' + E.icon('search', 15) + '<span class="toc-sr">Chercher dans l’aide</span><input type="search" id="ph-search" class="toc-input" placeholder="Chercher dans l’aide…" autocomplete="off" spellcheck="false"></label>' +
      '<div class="ph-tabs" role="tablist" aria-label="Rubriques de l’aide">' + TABS.map(function (t) {
        return '<button type="button" class="toc-tab" role="tab" id="ph-tab-' + t[0] + '" data-ph-tab="' + t[0] + '" aria-controls="ph-pane-' + t[0] + '" aria-selected="false"><span>' + t[1] + '</span><span class="ph-count mono" hidden></span></button>';
      }).join('') + '</div></div><div class="toc-drawer-body" id="ph-body">';
    // Comment faire.
    h += '<div class="ph-pane" role="tabpanel" id="ph-pane-how" aria-labelledby="ph-tab-how" data-ph-pane="how">' + HOW.map(function (w, i) {
      return '<details class="ph-how" data-ph-item data-kw="' + esc(w[2]) + '"' + (i === 0 ? ' open' : '') + '><summary>' + E.icon('caret', 14) + '<span>' + esc(w[1]) + '</span></summary>' +
        '<div class="ph-how-body">' + illustration(w[0]) + '<ol>' + w[3].map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ol></div></details>';
    }).join('') + '<p class="toc-note ph-none" hidden>Aucun mode d’emploi pour cette recherche.</p></div>';
    // Raccourcis : la feuille de la page (#toc-help-overlay) reste la seule source.
    h += '<div class="ph-pane" role="tabpanel" id="ph-pane-keys" aria-labelledby="ph-tab-keys" data-ph-pane="keys" hidden><div class="ph-keys" id="ph-keys"></div><p class="toc-note ph-none" hidden>Aucun raccourci pour cette recherche.</p></div>';
    // Graphismes.
    h += '<div class="ph-pane" role="tabpanel" id="ph-pane-gfx" aria-labelledby="ph-tab-gfx" data-ph-pane="gfx" hidden>' + Object.keys(E.KINDS).map(function (k) {
      var def = E.KINDS[k] || {};
      return '<div class="ph-gl" data-ph-item><i style="background:' + esc(def.color || '#f2f2f2') + '" aria-hidden="true"></i><b>' + esc(def.label || k) + '</b>' + (MEANING[k] ? '<span>' + esc(MEANING[k]) + '</span>' : '') + '</div>';
    }).join('') + '<p class="toc-note ph-none" hidden>Aucun graphisme pour cette recherche.</p></div>';
    h += '</div><div class="toc-drawer-foot ph-foot">' +
      '<button type="button" class="toc-btn is-small is-primary" data-ph="tour"></button>' +
      '<button type="button" class="toc-btn is-small" data-ph="restart" hidden>Recommencer</button>' +
      '<button type="button" class="toc-btn is-small" data-ph="example">Plan d’exemple</button>' +
      '<a class="toc-btn is-small" id="ph-doc" target="_blank" rel="noopener" hidden>Guide écrit</a></div>';
    d.innerHTML = h;
    document.body.appendChild(d);
    drawer.el = d;
    var keys = $('#ph-keys');
    var src = $$('#toc-help-overlay .toc-keys > kbd');
    src.forEach(function (k) {
      var row = el('div', 'ph-key');
      row.setAttribute('data-ph-item', '');
      row.appendChild(k.cloneNode(true));
      if (k.nextElementSibling) row.appendChild(k.nextElementSibling.cloneNode(true));
      keys.appendChild(row);
    });
    if (E.BOOT.helpUrl) { $('#ph-doc').href = E.BOOT.helpUrl; $('#ph-doc').hidden = false; }
    d.addEventListener('click', function (e) {
      var t = e.target.closest('[data-ph-tab]');
      if (t) { setTab(t.getAttribute('data-ph-tab')); return; }
      var b = e.target.closest('[data-ph]');
      if (!b) return;
      var a = b.getAttribute('data-ph');
      if (a === 'close') closeDrawer();
      if (a === 'tour') { closeDrawer(); startTour(false); }
      if (a === 'restart') { closeDrawer(); startTour(true); }
      if (a === 'example') examplePlan();
    });
    d.addEventListener('keydown', function (e) {
      if ((e.key === ' ' || e.key === 'Enter') && e.target.closest('button, summary, a')) { e.stopPropagation(); return; }
      var t = e.target.closest('[data-ph-tab]');
      if (t && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
        e.preventDefault(); e.stopPropagation();
        var i = TABS.map(function (x) { return x[0]; }).indexOf(t.getAttribute('data-ph-tab'));
        var nx = TABS[(i + (e.key === 'ArrowRight' ? 1 : TABS.length - 1)) % TABS.length][0];
        setTab(nx);
        $('#ph-tab-' + nx).focus();
      }
    });
    $('#ph-search').addEventListener('input', filterDrawer);
    setTab('how');
    syncDrawerFoot();
  }
  function setTab(t) {
    drawer.tab = t;
    $$('[data-ph-tab]', drawer.el).forEach(function (b) { var on = b.getAttribute('data-ph-tab') === t; b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; });
    $$('[data-ph-pane]', drawer.el).forEach(function (p) { p.hidden = p.getAttribute('data-ph-pane') !== t; });
    $('#ph-body').scrollTop = 0;
  }
  /** Recherche sans accents ni majuscules, sur les trois rubriques à la fois ; ouvre la première rubrique qui répond. */
  function filterDrawer() {
    var words = fold($('#ph-search').value).split(/\s+/).filter(Boolean);
    var first = null, counts = {};
    $$('[data-ph-pane]', drawer.el).forEach(function (pane) {
      var id = pane.getAttribute('data-ph-pane'), n = 0;
      $$('[data-ph-item]', pane).forEach(function (it) {
        var txt = fold(it.textContent + ' ' + (it.getAttribute('data-kw') || ''));
        var ok = words.every(function (w) { return txt.indexOf(w) >= 0; });
        it.hidden = !ok;
        if (ok) n++;
      });
      counts[id] = n;
      $('.ph-none', pane).hidden = n > 0;
      var c = $('#ph-tab-' + id + ' .ph-count');
      c.hidden = !words.length;
      c.textContent = String(n);
      if (n && !first) first = id;
    });
    if (words.length && !counts[drawer.tab] && first) setTab(first);
  }
  function syncDrawerFoot() {
    if (!drawer.el) return;
    var b = $('[data-ph="tour"]', drawer.el);
    var resume = !mem.done && mem.seen && mem.step > 0;
    b.textContent = tour.on && !tour.end ? 'Tutoriel en cours' : (resume ? 'Reprendre le tutoriel' : 'Tutoriel');
    $('[data-ph="restart"]', drawer.el).hidden = !resume;
  }
  function openDrawer() {
    buildDrawer();
    if (drawer.open) { $('#ph-search').focus(); return; }
    drawer.back = document.activeElement;
    drawer.open = true;
    drawer.el.hidden = false;
    document.body.classList.add('ph-drawer-open');
    syncDrawerFoot();
    var s = $('#ph-search');
    s.focus();
    s.select();
    var hb = $('[data-run="help"]');
    if (hb) hb.setAttribute('aria-expanded', 'true');
  }
  function closeDrawer() {
    if (!drawer.open) return;
    var inside = drawer.el.contains(document.activeElement);
    drawer.open = false;
    drawer.el.hidden = true;
    document.body.classList.remove('ph-drawer-open');
    var hb = $('[data-run="help"]');
    if (hb) hb.setAttribute('aria-expanded', 'false');
    // Le champ masqué garderait le clavier un instant : la touche « ? » suivante serait prise pour du texte.
    if (inside) { document.activeElement.blur(); var b = drawer.back && document.body.contains(drawer.back) && shown(drawer.back) ? drawer.back : hb; if (b && b.focus) b.focus(); }
  }

  /* ====================================================================================================
   * Astuces : une ligne au premier usage de chaque outil
   * ==================================================================================================== */
  var HINTS = {
    select: 'Sélection : cliquez un élément pour le choisir et glissez-le pour le déplacer ; glissez dans le vide pour tracer un cadre.',
    lasso: 'Lasso : entourez en glissant les éléments à choisir ; Maj les ajoute à la sélection.',
    pan: 'Main : glissez pour déplacer la carte ; Espace + glisser fait de même avec tout autre outil.',
    unit: 'Unité : cliquez sur la carte pour la poser, puis tapez son indicatif dans le volet de droite.',
    text: 'Texte : cliquez sur la carte, puis tapez le texte dans le volet de droite.',
    point: 'Cliquez sur la carte pour poser le symbole ; Échap quitte l’outil.',
    line: 'Trait ou flèche : un clic par point, double-clic ou Entrée pour terminer, Retour arrière pour retirer le dernier point.',
    shape: 'Zone : un clic par sommet, double-clic pour fermer ; rectangle, cercle et ellipse se tracent en deux clics ou en glissant.',
    measure: 'Mesure : cliquez les points du trajet ; la distance et l’azimut se lisent au fur et à mesure.',
    los: 'Ligne de vue : cliquez l’observateur, puis la cible ; la coupe montre ce qui masque la vue.',
    profile: 'Coupe : tracez un trajet et terminez par un double-clic pour voir le relief traversé.',
    viewshed: 'Zone vue : cliquez un point d’observation pour voir ce qu’il découvre.',
    view3d: 'Vue 3D : glissez pour déplacer la vue, clic droit ou Ctrl + glisser pour tourner et incliner, molette pour rapprocher.',
    time: 'Frise : le plan s’affiche tel qu’il est à cet instant ; avec « Clé auto », déplacer une unité lui donne une position pour cet instant.'
  };
  var hint = { el: null, timer: 0, key: '', ready: false };
  function hintKey(tool) {
    if (HINTS[tool] && tool !== 'point' && tool !== 'shape' && tool !== 'time' && tool !== 'view3d') return tool;
    if (tool === 'textbox') return 'text';
    if (tool === 'arrow') return 'line';
    if (tool === 'polygon' || tool === 'rect' || tool === 'circle' || tool === 'ellipse') return 'shape';
    var def = E.KINDS[tool];
    var g = def && def.geoms ? def.geoms[0] : '';
    return g === 'point' ? 'point' : (g === 'line' ? 'line' : (g ? 'shape' : ''));
  }
  function buildHint() {
    if (hint.el) return;
    var wrap = $('#plan-mapwrap');
    if (!wrap) return;
    var h = el('div', 'ph-hint');
    h.id = 'ph-hint';
    h.hidden = true;
    h.setAttribute('role', 'status');
    h.innerHTML = '<span id="ph-hint-text"></span><button type="button" class="ph-link" data-ph="hints-off">Ne plus afficher les astuces</button>' +
      '<button type="button" class="pe-ibtn" data-ph="hint-close" title="Fermer l’astuce" aria-label="Fermer l’astuce">' + E.icon('close', 15) + '</button>';
    wrap.appendChild(h);
    hint.el = h;
    h.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ph]');
      if (!b) return;
      if (b.getAttribute('data-ph') === 'hints-off') { mem.hintsOff = true; saveMem(); E.say('Astuces coupées : la palette (Ctrl + K) permet de les réafficher.'); }
      hideHint();
    });
    h.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
    h.addEventListener('keydown', function (e) { if (e.key === ' ' || e.key === 'Enter') e.stopPropagation(); });
    // L'astuce se tient au-dessus du message de l'éditeur, quelle que soit sa hauteur.
    var toast = $('#plan-msg');
    if (toast && window.MutationObserver) {
      new MutationObserver(function () { h.style.setProperty('--ph-toast', (toast.hidden ? 0 : toast.offsetHeight + 8) + 'px'); }).observe(toast, { attributes: true, childList: true, characterData: true, subtree: true });
    }
  }
  function hideHint() {
    if (!hint.el) return;
    clearTimeout(hint.timer);
    hint.el.hidden = true;
    hint.key = '';
  }
  function showHint(key) {
    if (hint.key && hint.key !== key) hideHint();
    if (!key || !HINTS[key] || !hint.ready || mem.hintsOff || mem.hints[key] || tour.on || P.briefing) return;
    buildHint();
    if (!hint.el) return;
    mem.hints[key] = 1;
    saveMem();
    hint.key = key;
    $('#ph-hint-text').textContent = HINTS[key];
    var toast = $('#plan-msg');
    hint.el.style.setProperty('--ph-toast', (toast && !toast.hidden ? toast.offsetHeight + 8 : 0) + 'px');
    hint.el.hidden = false;
    clearTimeout(hint.timer);
    hint.timer = setTimeout(hideHint, 16000);
  }
  E.on('tool', function () {
    // Tutoriel : la tuile cliquée garde le clavier, et Entrée la réarmerait au lieu de terminer le tracé annoncé.
    var a = document.activeElement;
    if (tour.on && P.armed && a && a.classList && a.classList.contains('pe-tile')) a.blur();
    showHint(hintKey(P.tool));
  });
  E.on('mode', function (m) { if (m === '3d') showHint('view3d'); else if (hint.key === 'view3d') hideHint(); });
  E.on('time', function () { if (P.time != null) showHint('time'); });
  E.on('briefing', hideHint);

  /* ====================================================================================================
   * Branchement
   * ==================================================================================================== */
  E.on('init', function () {
    // Remplace l'action « help » du cœur : le bouton de la barre et la touche « ? » ouvrent le tiroir.
    E.action('help', 'Aide : modes d’emploi, raccourcis, graphismes', function () { if (drawer.open) closeDrawer(); else openDrawer(); }, { key: '?', group: 'Aide' });
    E.action('tutorial', 'Tutoriel : apprendre l’éditeur pas à pas', function () { startTour(false); }, { group: 'Aide' });
    E.action('tutorialRestart', 'Tutoriel : recommencer du début', function () { startTour(true); }, { group: 'Aide' });
    E.action('examplePlan', 'Ouvrir un plan d’exemple (tutoriel)', examplePlan, { group: 'Aide' });
    E.action('hintsReset', 'Réafficher les astuces des outils', function () { mem.hints = {}; mem.hintsOff = false; saveMem(); E.say('Les astuces des outils s’afficheront de nouveau.'); }, { group: 'Aide' });
    var hb = $('[data-run="help"]');
    if (hb) { hb.setAttribute('aria-expanded', 'false'); hb.setAttribute('aria-controls', 'ph-drawer'); }
    // Échap : d'abord ce que l'éditeur ferme lui-même (menu, palette, fenêtre, tracé en cours), puis l'aide.
    var prev = E.escape;
    E.escape = function () {
      if (prev && prev()) return true;
      if ($$('.toc-overlay:not([hidden])').length) return false;
      var menus = $$('details.toc-menu[open]');
      if (menus.length) { menus.forEach(function (d) { d.open = false; }); return true; }
      if (drawer.open && drawer.el.contains(document.activeElement)) { closeDrawer(); return true; }
      if (P.briefing) return false;
      if (E.typingIn(document.activeElement)) return false;
      if (P.draft || P.tool !== 'select') return false;
      if (tour.on) { leaveTour(); return true; }
      if (drawer.open) { closeDrawer(); return true; }
      return false;
    };
  });
  E.on('loaded', function () {
    hint.ready = true;
    var forced = /[?&]tutoriel=1(?:&|$)/.test(window.location.search);
    if (forced && window.history && window.history.replaceState) {
      // Le paramètre ne sert qu'une fois : un rechargement reprend là où le membre en est.
      var q = window.location.search.replace(/([?&])tutoriel=1(&|$)/, function (m, a, b) { return b ? a : ''; });
      window.history.replaceState(null, '', window.location.pathname + q + window.location.hash);
    }
    if (!forced && mem.seen && !mem.active) return;
    setTimeout(function () {
      if (tour.on || !canTour() || dialogOpen()) return;
      showStep(forced ? 0 : Math.min(mem.step, STEPS.length - 1), !document.activeElement || document.activeElement === document.body);
    }, 500);
  });
  E.on('rights', function () { if (tour.on && !P.canEdit) leaveTour(true); });

  E.help = { open: openDrawer, close: closeDrawer, startTour: startTour, leaveTour: leaveTour, examplePlan: examplePlan, steps: STEPS.map(function (s) { return s.id; }), state: function () { return { on: tour.on, end: tour.end, step: mem.step, id: tour.end ? 'end' : STEPS[mem.step].id, drawer: drawer.open, tab: drawer.tab, hint: hint.key }; } };
})();
