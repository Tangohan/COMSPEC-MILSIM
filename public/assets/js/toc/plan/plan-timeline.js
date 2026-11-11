/* Éditeur de plans : frise du temps. Les phases gardent leur rôle ; la frise leur donne un début (minutes par rapport
 * à l'heure H), montre les repères (heure H, événements, lignes EXCHECK du poste) et les images clés des unités
 * choisies. Lecture, pause, vitesse ; « clé auto » : déplacer une unité à un instant lui pose une image clé.
 * Briefing : plein écran, phase par phase, les unités glissent d'une position à la suivante.
 * Se branche sur window.TocPlanEditor (toc-plan-editor.js). */
(function () {
  'use strict';
  var E = window.TocPlanEditor;
  if (!E) return;
  var P = E.P, G = E.G, LG = E.LG, $ = E.$, $$ = E.$$, esc = E.esc;
  var play = { on: false, speed: 2, raf: 0, last: 0, lastDraw: 0 };
  var anim = null;
  var SPEEDS = [[0.5, '0,5 min/s'], [1, '1 min/s'], [2, '2 min/s'], [5, '5 min/s'], [15, '15 min/s'], [60, '1 h/s']];
  // La frise repliée (plan-layout.js) n'est pas redessinée.
  function folded() { return document.body.classList.contains('pe-hide-time'); }

  function tl() { return P.meta.timeline; }
  function range() { var t = tl(); return [t.start, t.start + t.dur]; }
  function trackEl() { return $('#pe-track'); }
  function xOf(t) { var r = range(); var w = trackEl() ? trackEl().clientWidth : 1; return (t - r[0]) / (r[1] - r[0]) * w; }
  function tOf(clientX) {
    var el = trackEl();
    var b = el.getBoundingClientRect();
    var r = range();
    return Math.max(r[0], Math.min(r[1], r[0] + (clientX - b.left) / Math.max(1, b.width) * (r[1] - r[0])));
  }
  function snapT(t, ev) {
    // Pas d'une minute ; Maj : libre ; proche d'un début de phase ou d'un repère : s'y accroche.
    if (ev && ev.shiftKey) return Math.round(t * 100) / 100;
    var r = range();
    var tolT = (r[1] - r[0]) * 6 / Math.max(1, trackEl().clientWidth);
    var marks = LG.phaseStarts(P.phases, tl()).concat(tl().events.map(function (e) { return e.t; }), [0]);
    var best = null;
    marks.forEach(function (m) { if (Math.abs(m - t) < tolT && (best == null || Math.abs(m - t) < Math.abs(best - t))) best = m; });
    return best != null ? best : Math.round(t);
  }
  function label(t) {
    var z = tl().zulu ? LG.fmtZulu(tl().h, t) : '';
    return z || LG.fmtH(t);
  }
  function tickStep(span, width) {
    var steps = [1, 2, 5, 10, 15, 30, 60, 120, 240, 360, 720, 1440];
    for (var i = 0; i < steps.length; i++) if (steps[i] / span * width >= 74) return steps[i];
    return 1440;
  }

  /* ---------- Rendu ---------- */
  function setHtml(el, html) { if (el && el._html !== html) { el._html = html; el.innerHTML = html; } }
  function renderBar() {
    var bar = $('#pe-timebar');
    if (!bar || !P.loaded) return;
    var t = P.time;
    var ph = P.phases.filter(function (x) { return x.uid === P.phase; })[0];
    var sub = t == null ? 'toutes les phases' : (ph ? (P.phases.indexOf(ph) + 1) + '. ' + ph.name : 'avant la première phase');
    var other = t != null ? (tl().zulu ? LG.fmtH(t) : LG.fmtZulu(tl().h, t)) : '';
    // Rien n'est réécrit sans changement : un bouton redessiné sous le pointeur pendant la lecture perdrait le clic.
    setHtml($('#pe-clock'), (t == null ? 'PLAN ENTIER' : esc(label(t))) + '<small>' + esc(sub + (other ? ' · ' + other : '')) + '</small>');
    var pb = $('#pe-play');
    if (pb.getAttribute('data-on') !== String(play.on)) {
      pb.setAttribute('data-on', String(play.on));
      pb.innerHTML = E.icon(play.on ? 'pause' : 'play', 16);
      pb.setAttribute('aria-label', play.on ? 'Pause' : 'Lecture');
      pb.title = play.on ? 'Pause (Espace)' : 'Lecture (Espace)';
    }
    $('#pe-whole').classList.toggle('is-on', t == null);
    $('#pe-autokey').classList.toggle('is-on', !!P.autoKey);
    $('#pe-autokey').setAttribute('aria-pressed', String(!!P.autoKey));
    $$('[data-edit-only]', bar).forEach(function (b) { b.hidden = !P.canEdit; });
  }
  function renderTrack() {
    var el = trackEl();
    if (!el || !P.loaded || folded()) return;
    var w = el.clientWidth;
    if (!w) return;
    var r = range();
    var span = r[1] - r[0];
    var step = tickStep(span, w);
    var h = '<div class="pe-row is-ruler" data-row="ruler">';
    for (var t = Math.ceil(r[0] / step) * step; t <= r[1]; t += step) {
      h += '<span class="pe-tick' + (t === 0 ? ' is-h' : '') + '" style="left:' + xOf(t).toFixed(1) + 'px">' + esc(label(t)) + '</span>';
    }
    h += '</div><div class="pe-row is-phases" data-row="phases">';
    var starts = LG.phaseStarts(P.phases, tl());
    P.phases.forEach(function (ph, i) {
      var x0 = xOf(starts[i]), x1 = i + 1 < starts.length ? xOf(starts[i + 1]) : w;
      h += '<div class="pe-ph' + (P.phase === ph.uid && P.time != null ? ' is-on' : '') + '" data-ph-block="' + ph.uid + '" style="left:' + x0.toFixed(1) + 'px;width:' + Math.max(8, x1 - x0 - 1).toFixed(1) + 'px" title="' + esc((i + 1) + '. ' + ph.name + ' · ' + LG.fmtH(starts[i]) + (ph.note ? ' · ' + ph.note : '')) + '">' +
        (P.canEdit ? '<span class="pe-ph-grip" data-ph-grip="' + ph.uid + '" title="Glisser : début de la phase"></span>' : '') + (i + 1) + '. ' + esc(ph.name) + '</div>';
    });
    if (!P.phases.length) h += '<span class="pe-rowlabel" style="left:8px;right:auto">Pas de phase : « + Phase » pour découper l’opération</span>';
    h += '</div><div class="pe-row is-events" data-row="events">';
    tl().events.forEach(function (ev) {
      h += '<span class="pe-ev' + (ev.kind === 'excheck' ? ' is-x' : '') + '" data-ev="' + esc(ev.uid) + '" style="left:' + xOf(ev.t).toFixed(1) + 'px" title="' + esc(LG.fmtH(ev.t) + ' · ' + ev.label + (ev.kind === 'excheck' ? ' (EXCHECK)' : '')) + '">' + esc(ev.label) + '</span>';
    });
    h += '<span class="pe-rowlabel">Repères</span></div><div class="pe-row is-keys" data-row="keys">';
    var keyed = E.selected().filter(function (e) { return e.props.track && e.props.track.length; });
    keyed.forEach(function (e) {
      e.props.track.forEach(function (k) { h += '<span class="pe-kf" data-kf="' + e.uid + '|' + k.t + '" style="left:' + xOf(k.t).toFixed(1) + 'px" title="' + esc((E.unitLabel ? E.unitLabel(e) : '') + ' · ' + LG.fmtH(k.t)) + '"></span>'; });
    });
    h += '<span class="pe-rowlabel">' + (keyed.length ? 'Images clés de la sélection' : 'Images clés : choisissez une unité en mouvement') + '</span></div>';
    h += '<div class="pe-head" id="pe-head"' + (P.time == null ? ' hidden' : ' style="left:' + xOf(P.time).toFixed(1) + 'px"') + '></div>';
    el.innerHTML = h;
  }
  function moveHead() {
    var hd = $('#pe-head');
    if (!hd) return;
    hd.hidden = P.time == null;
    if (P.time != null) hd.style.left = xOf(P.time).toFixed(1) + 'px';
  }
  function render() { renderBar(); renderTrack(); renderBrief(); }

  /* ---------- Lecture ---------- */
  function stop() { play.on = false; cancelAnimationFrame(play.raf); renderBar(); }
  function tick(now) {
    if (!play.on) return;
    var dt = Math.min(0.25, (now - play.last) / 1000);
    play.last = now;
    var r = range();
    var t = (P.time == null ? r[0] : P.time) + dt * play.speed;
    if (t >= r[1]) { E.setTime(r[1]); stop(); return; }
    var before = P.phase;
    // La carte est redessinée 20 fois par seconde au plus ; la tête de lecture suit chaque image.
    if (now - play.lastDraw > 50) { play.lastDraw = now; E.setTime(t, true); if (before !== P.phase) render(); else { renderBar(); moveHead(); } }
    else { P.time = t; moveHead(); }
    play.raf = requestAnimationFrame(tick);
  }
  function toggle() {
    if (play.on) { stop(); return; }
    var r = range();
    if (P.time == null || P.time >= r[1] - 0.01) E.setTime(r[0]);
    play.on = true; play.last = performance.now(); play.lastDraw = 0;
    E.emit('play', true);
    renderBar();
    play.raf = requestAnimationFrame(tick);
  }
  /** Glisse de l'instant affiché à un autre (pas à pas du briefing). */
  function animateTo(target, ms) {
    stop();
    if (anim) cancelAnimationFrame(anim.raf);
    var from = P.time == null ? target : P.time;
    if (Math.abs(target - from) < 0.01 || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) { E.setTime(target); return; }
    var t0 = performance.now();
    anim = { raf: 0 };
    var stepFn = function (now) {
      var u = Math.min(1, (now - t0) / ms);
      var e = u < 0.5 ? 2 * u * u : 1 - Math.pow(-2 * u + 2, 2) / 2;
      if (u >= 1) { anim = null; E.setTime(target); return; }
      E.setTime(from + (target - from) * e, true);
      moveHead();
      anim.raf = requestAnimationFrame(stepFn);
    };
    anim.raf = requestAnimationFrame(stepFn);
  }
  function stepPhase(d, animated) {
    if (!P.phases.length) { E.say('Pas de phase dans ce plan.'); return; }
    var i = P.phases.map(function (p) { return p.uid; }).indexOf(P.phase);
    if (P.time == null) i = -1;
    var n = Math.max(-1, Math.min(P.phases.length - 1, i + d));
    if (n < 0) { if (P.briefing) n = 0; else { E.setPhase(null); return; } }
    var starts = LG.phaseStarts(P.phases, tl());
    if (animated && P.time != null) { P.phase = P.phases[n].uid; animateTo(starts[n], 1600); } else E.setPhase(P.phases[n].uid);
  }

  /* ---------- Briefing ---------- */
  function renderBrief() {
    var card = $('#plan-brief-card');
    if (!card) return;
    card.hidden = !P.briefing;
    if (!P.briefing) return;
    var ph = P.phases.filter(function (x) { return x.uid === P.phase; })[0];
    var i = ph ? P.phases.indexOf(ph) : -1;
    card.innerHTML = '<div class="toc-sec"><h2>' + (ph ? (i + 1) + ' / ' + P.phases.length + ' · ' + esc(ph.name) : esc(P.plan.title)) + '</h2><span class="mono">' + esc(P.time != null ? label(P.time) : '') + '</span></div><p>' + esc(ph ? (ph.note || '') : 'Plan entier') + '</p>' +
      '<div class="pe-stack is-row" style="margin-top:8px"><button type="button" class="toc-btn is-small" data-brief="-1"' + (i <= 0 ? ' disabled' : '') + '>← Précédente</button><button type="button" class="toc-btn is-small is-primary" data-brief="1"' + (i >= P.phases.length - 1 ? ' disabled' : '') + '>Suivante →</button><span class="pe-grow"></span><button type="button" class="toc-btn is-small" data-brief="0">Quitter <kbd>Échap</kbd></button></div>';
  }
  function setBriefing(on) {
    on = on === undefined ? !P.briefing : !!on;
    if (on === P.briefing) return;
    P.briefing = on;
    stop();
    document.body.classList.toggle('is-briefing', on);
    if (on) {
      E.setSel([]);
      E.setTool('select');
      if (P.phases.length && (P.time == null || !P.phase)) E.setPhase(P.phases[0].uid);
      var root = document.documentElement;
      if (root.requestFullscreen && !document.fullscreenElement) root.requestFullscreen().catch(function () {});
    } else if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
    setTimeout(function () { if (E.tm) E.tm.map.invalidateSize(); if (E.view3d) E.view3d.resize(); E.renderAll(); render(); }, 80);
    E.renderAll();
    render();
  }

  /* ---------- Repères et réglages ---------- */
  function addEvent(t, labelText, kind, ref) {
    if (tl().events.length >= 60) { E.say('60 repères au plus sur la frise.', true); return; }
    tl().events.push({ uid: G.uid('v'), t: Math.round(t * 100) / 100, label: String(labelText).slice(0, 60), kind: kind || 'event', ref: ref || '' });
    tl().events.sort(function (a, b) { return a.t - b.t; });
    E.metaChanged();
    renderTrack();
  }
  function eventMenu(ev, x, y) {
    var items = [{ label: 'Aller à ' + LG.fmtH(ev.t), run: function () { E.setTime(ev.t); } }];
    if (P.canEdit) items.push({ label: 'Renommer…', run: function () { var v = window.prompt('Nom du repère :', ev.label); if (v && v.trim()) { ev.label = v.trim().slice(0, 60); E.metaChanged(); renderTrack(); } } },
      { label: 'Retirer le repère', run: function () { tl().events = tl().events.filter(function (x2) { return x2.uid !== ev.uid; }); E.metaChanged(); renderTrack(); } });
    E.openMenu(x, y, items);
  }
  function importExcheck() {
    E.say('Lecture de l’EXCHECK du poste…');
    E.api('/atak/toc/api/state?mapId=' + P.plan.map_id + '&side=' + (P.meta.side || 'WEST')).then(function (j) {
      var rows = (j && j.room && j.room.excheck) || [];
      if (!rows.length) { E.say('L’EXCHECK du poste est vide pour ce camp.', true); return; }
      var have = {};
      tl().events.forEach(function (e) { if (e.kind === 'excheck') have[e.ref] = e; });
      var added = 0, skipped = 0;
      rows.forEach(function (r) {
        var t = LG.parseH(String(r.at || '').replace(/\s/g, ''));
        if (t == null) { skipped++; return; }
        var ref = String(r.id || r.n || '').slice(0, 32);
        var text = String((r.cw ? r.cw + ' · ' : '') + (r.ev || '')).slice(0, 60) || 'EXCHECK';
        if (have[ref]) { have[ref].t = t; have[ref].label = text; return; }
        if (tl().events.length < 60) { tl().events.push({ uid: G.uid('v'), t: t, label: text, kind: 'excheck', ref: ref }); added++; }
      });
      tl().events.sort(function (a, b) { return a.t - b.t; });
      E.metaChanged();
      renderTrack();
      E.say(added + ' ligne(s) EXCHECK posée(s) sur la frise' + (skipped ? ' · ' + skipped + ' sans heure en H+ lisible, laissée(s)' : '') + '.');
    }).catch(function () { E.say('EXCHECK du poste indisponible.', true); });
  }
  function openSettings() {
    var t = tl();
    $('#time-h').value = t.h || '';
    $('#time-dur').value = t.dur;
    $('#time-start').value = -t.start;
    $('#time-zulu').checked = !!t.zulu;
    $('#time-h').disabled = $('#time-dur').disabled = $('#time-start').disabled = $('#time-zulu').disabled = !P.canEdit;
    $('#time-save').hidden = !P.canEdit;
    E.openDialog('dlg-time');
  }
  function saveSettings() {
    var t = tl();
    var hv = $('#time-h').value;
    t.h = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(hv) ? hv.slice(0, 16) : '';
    t.dur = Math.max(10, Math.min(14400, Math.round(Number($('#time-dur').value) || 240)));
    t.start = -Math.max(0, Math.min(1440, Math.round(Number($('#time-start').value) || 0)));
    t.zulu = $('#time-zulu').checked && !!t.h;
    if ($('#time-zulu').checked && !t.h) E.say('Réglez l’heure H pour afficher les heures en ZULU.', true);
    E.metaChanged();
    E.closeDialogs();
    render();
  }

  /* ---------- Gestes sur la frise ---------- */
  var drag = null;
  function onTrackDown(ev) {
    if (ev.button != null && ev.button !== 0) return;
    var t = ev.target;
    var grip = t.closest('[data-ph-grip]');
    var kf = t.closest('[data-kf]');
    var evEl = t.closest('[data-ev]');
    var block = t.closest('[data-ph-block]');
    stop();
    if (grip && P.canEdit) drag = { type: 'phase', uid: grip.getAttribute('data-ph-grip'), moved: false };
    else if (kf && P.canEdit) { var parts = kf.getAttribute('data-kf').split('|'); drag = { type: 'key', uid: parts[0], t: Number(parts[1]), moved: false }; }
    else if (evEl) drag = { type: 'event', uid: evEl.getAttribute('data-ev'), moved: false, x: ev.clientX, y: ev.clientY };
    else if (block && !t.closest('.is-ruler')) drag = { type: 'block', uid: block.getAttribute('data-ph-block'), moved: false };
    else { drag = { type: 'scrub', moved: true }; E.setTime(snapT(tOf(ev.clientX), ev), true); renderBar(); moveHead(); }
    drag.x0 = ev.clientX;
    ev.preventDefault();
    document.addEventListener('pointermove', onTrackMove);
    document.addEventListener('pointerup', onTrackUp);
    document.addEventListener('pointercancel', onTrackUp);
  }
  function onTrackMove(ev) {
    if (!drag) return;
    if (!drag.moved && Math.abs(ev.clientX - drag.x0) < 4) return;
    drag.moved = true;
    var t = snapT(tOf(ev.clientX), ev);
    if (drag.type === 'scrub' || drag.type === 'block') { drag.type = 'scrub'; E.setTime(t, true); renderBar(); moveHead(); if (P.phase !== drag.lastPhase) { drag.lastPhase = P.phase; renderTrack(); } }
    if (drag.type === 'phase') {
      var i = P.phases.map(function (p) { return p.uid; }).indexOf(drag.uid);
      var starts = LG.phaseStarts(P.phases, tl());
      var lo = i > 0 ? starts[i - 1] : range()[0], hi = i + 1 < starts.length ? starts[i + 1] : range()[1];
      // Les autres débuts, jusque-là répartis automatiquement, sont figés là où ils sont.
      P.phases.forEach(function (ph, k) { if (typeof tl().phases[ph.uid] !== 'number') tl().phases[ph.uid] = Math.round(starts[k]); });
      tl().phases[drag.uid] = Math.max(lo, Math.min(hi, Math.round(tOf(ev.clientX))));
      P.phases[i].time_label = LG.fmtH(tl().phases[drag.uid]);
      drag.changed = true;
      // Dès le premier pas du glisser : un relevé du site arrivant avant le relâchement ne doit pas remettre l'ancien début.
      P.metaDirty = true; P.phasesDirty = true;
      renderTrack();
    }
    if (drag.type === 'event' && P.canEdit) {
      var e2 = tl().events.filter(function (x) { return x.uid === drag.uid; })[0];
      if (e2) { e2.t = Math.round(tOf(ev.clientX)); drag.changed = true; P.metaDirty = true; renderTrack(); }
    }
    if (drag.type === 'key') { drag.newT = Math.round(tOf(ev.clientX)); var k = $('[data-kf="' + drag.uid + '|' + drag.t + '"]'); if (k) k.style.left = xOf(drag.newT).toFixed(1) + 'px'; }
  }
  function onTrackUp(ev) {
    document.removeEventListener('pointermove', onTrackMove);
    document.removeEventListener('pointerup', onTrackUp);
    document.removeEventListener('pointercancel', onTrackUp);
    var d = drag;
    drag = null;
    if (!d) return;
    if (d.type === 'scrub') { E.setTime(P.time); return; }
    if (d.type === 'block' && !d.moved) { E.setPhase(d.uid); return; }
    if (d.type === 'phase' && d.changed) { E.metaChanged(); E.phasesChanged(); E.emit('phases'); if (P.time != null) E.setTime(P.time); else render(); return; }
    if (d.type === 'event') {
      var e2 = tl().events.filter(function (x) { return x.uid === d.uid; })[0];
      if (!e2) return;
      if (d.changed) { tl().events.sort(function (a, b) { return a.t - b.t; }); E.metaChanged(); renderTrack(); } else eventMenu(e2, ev.clientX, ev.clientY);
      return;
    }
    if (d.type === 'key') {
      var el = P.els[d.uid];
      if (!el) return;
      if (!d.moved || d.newT == null || d.newT === d.t) { E.setTime(d.t); return; }
      var kf = (el.props.track || []).filter(function (k) { return Math.abs(k.t - d.t) < 0.004; })[0];
      if (!kf || !E.editable(el)) return;
      var c = LG.setKeyframe(LG.removeKeyframe(el, d.t), d.newT, kf.p, null);
      if (c) { var m = {}; m[c.uid] = c; E.commit(m, 'Image clé déplacée à ' + LG.fmtH(d.newT)); }
    }
  }

  /* ---------- Branchement ---------- */
  E.action('briefing', 'Briefing : présenter en plein écran', function () { setBriefing(); }, { group: 'Temps' });
  E.action('play', 'Lecture ou pause de la frise', toggle, { group: 'Temps' });
  E.action('phaseNext', 'Phase suivante', function () { stepPhase(1); }, { key: 'Page suiv.', group: 'Temps' });
  E.action('phasePrev', 'Phase précédente', function () { stepPhase(-1); }, { key: 'Page préc.', group: 'Temps' });
  E.action('wholePlan', 'Afficher le plan entier (hors du temps)', function () { stop(); E.setPhase(null); }, { group: 'Temps' });
  E.action('autoKey', 'Clé auto : déplacer une unité pose une image clé', function () { P.autoKey = !P.autoKey; if (P.autoKey && P.time == null) E.setTime(range()[0]); E.say(P.autoKey ? 'Clé auto : déplacer une unité à un instant de la frise lui pose une image clé.' : 'Clé auto coupée : déplacer une unité déplace toute sa trajectoire.'); renderBar(); E.emit('autokey', P.autoKey); }, { edit: true, group: 'Temps' });
  E.action('addPhase', 'Ajouter une phase', function () { E.addPhase(); render(); }, { edit: true, group: 'Temps' });
  E.action('addEvent', 'Ajouter un repère sur la frise', function () { var t = P.time == null ? 0 : P.time; var v = window.prompt('Nom du repère à ' + LG.fmtH(t) + ' :', ''); if (v && v.trim()) addEvent(t, v.trim(), 'event'); }, { edit: true, group: 'Temps' });
  E.action('excheck', 'Poser les lignes EXCHECK du poste sur la frise', importExcheck, { edit: true, group: 'Temps' });
  E.action('timeSettings', 'Réglages de la frise (heure H, durée)', openSettings, { group: 'Temps' });

  E.on('init', function () {
    var foot = $('#pe-time');
    foot.innerHTML = '<div class="pe-div is-row" data-div="time" role="separator" aria-orientation="horizontal" title="Glisser : hauteur de la frise · double-clic : replier ou déplier"></div><div class="pe-timebar" id="pe-timebar">' +
      '<button type="button" class="pe-tbtn" data-run="phasePrev" title="Phase précédente (Page préc.)" aria-label="Phase précédente">' + E.icon('prev', 16) + '</button>' +
      '<button type="button" class="pe-tbtn" id="pe-play" data-run="play"></button>' +
      '<button type="button" class="pe-tbtn" data-run="phaseNext" title="Phase suivante (Page suiv.)" aria-label="Phase suivante">' + E.icon('next', 16) + '</button>' +
      '<label class="toc-sr" for="pe-speed">Vitesse de lecture</label><select id="pe-speed" class="toc-select" title="Vitesse de lecture">' + SPEEDS.map(function (s) { return '<option value="' + s[0] + '"' + (s[0] === play.speed ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') + '</select>' +
      '<button type="button" class="pe-clock" id="pe-clock" data-run="timeSettings" title="Réglages de la frise : heure H, durée, ZULU"></button>' +
      '<button type="button" class="toc-btn" id="pe-whole" data-run="wholePlan" title="Tout le plan, hors du temps">Plan entier</button>' +
      '<button type="button" class="toc-btn" id="pe-autokey" data-run="autoKey" data-edit-only aria-pressed="false" title="Déplacer une unité à un instant de la frise lui pose une image clé">' + E.icon('key', 14) + 'Clé auto</button>' +
      '<span class="pe-grow"></span>' +
      '<button type="button" class="toc-btn" data-run="addPhase" data-edit-only>' + E.icon('plus', 14) + 'Phase</button>' +
      '<button type="button" class="toc-btn" data-run="addEvent" data-edit-only>' + E.icon('flag', 14) + 'Repère</button>' +
      '<button type="button" class="toc-btn" data-run="excheck" data-edit-only title="Poser les lignes EXCHECK du poste TOC sur la frise">EXCHECK</button>' +
      '<button type="button" class="pe-tbtn" id="pe-time-fold" data-collapse="time" aria-expanded="true" aria-controls="pe-track" title="Replier ou déplier la frise (9)" aria-label="Replier ou déplier la frise">' + E.icon('caret', 16) + '</button>' +
      '</div><div class="pe-track" id="pe-track"></div>';
    $('#pe-speed').addEventListener('change', function () { play.speed = Number(this.value) || 2; });
    $('#pe-track').addEventListener('pointerdown', onTrackDown);
    document.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-brief]') : null;
      if (b) { var d = Number(b.getAttribute('data-brief')); if (d === 0) setBriefing(false); else stepPhase(d, true); }
      if (e.target.closest && e.target.closest('#time-save')) saveSettings();
      if (e.target.closest && e.target.closest('#pe-palbtn')) E.emit('palette');
    });
    document.addEventListener('fullscreenchange', function () { if (!document.fullscreenElement && P.briefing) setBriefing(false); });
    window.addEventListener('resize', function () { renderTrack(); });
    var prevHook = E.keyHook;
    E.keyHook = function (e) {
      if (prevHook && !P.briefing && prevHook(e)) return true;
      var k = e.key;
      if (P.briefing) {
        if (k === 'ArrowRight' || k === 'PageDown' || k === ' ' || k === 'Enter') { e.preventDefault(); stepPhase(1, true); return true; }
        if (k === 'ArrowLeft' || k === 'PageUp') { e.preventDefault(); stepPhase(-1, true); return true; }
        return true;
      }
      if (k === 'PageDown') { e.preventDefault(); stepPhase(1); return true; }
      if (k === 'PageUp') { e.preventDefault(); stepPhase(-1); return true; }
      return false;
    };
  });
  // La barre d'espace sert à déplacer la carte ; elle lance la lecture quand la frise a la main.
  document.addEventListener('keydown', function (e) {
    if (e.key !== ' ' || P.briefing) return;
    var a = document.activeElement;
    if (a && a.closest && a.closest('#pe-time') && a.tagName !== 'SELECT') { e.preventDefault(); e.stopPropagation(); toggle(); }
  }, true);
  E.on('loaded', render);
  E.on('time', render);
  E.on('phases', render);
  E.on('selection', renderTrack);
  E.on('render', function () { if (!play.on && !anim) renderTrack(); });
  E.on('rights', renderBar);
  E.on('layout', renderTrack);
  E.on('briefing', function (on) { setBriefing(on); });
  E.setBriefing = setBriefing;
})();
