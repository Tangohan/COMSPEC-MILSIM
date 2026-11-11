/* Éditeur de plans d'opération (/atak/toc/plans/{id}), cœur : état du plan, enregistrement différé et plusieurs
 * éditeurs (le dernier enregistré gagne, élément par élément), carte 2D, sélection (clic, Maj, cadre, lasso, pile
 * sous le curseur), poignées (sommets, rayons, échelle, rotation), dessin, presse-papiers, clavier, envoi en jeu,
 * versions, partage et exports. Les panneaux (plan/plan-panels.js), le temps (plan/plan-timeline.js) et le relief /
 * la vue 3D (plan/plan-terrain.js) s'y branchent par window.TocPlanEditor.
 * Tablette et bureau ; téléphone en lecture seule. */
window.TocPlanEditor = (function () {
  'use strict';
  var BOOT = window.TOC_BOOT || {};
  var G = window.TocGeo;
  var R = window.TocPlanRender;
  var LG = window.TocPlanLogic;
  var esc = R.esc;
  var CAT = BOOT.catalog || { kinds: {} };
  var KINDS = CAT.kinds || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var SIDES = { WEST: 'BLUFOR', EAST: 'OPFOR', GUER: 'INDFOR', CIV: 'CIVILS' };
  var AFFIL_COLOR = { F: '#6cb8ff', H: '#ff6b6b', N: '#7ee08a', U: '#f2d36b' };
  var ERR = {
    csrf: 'Session expirée : rechargez la page.', rate_limited: 'Trop de changements d’affilée : envoi repris dans un instant.', read_only: 'Ce plan est en lecture seule pour vous.',
    not_found: 'Plan introuvable.', schema_not_ready: 'Les plans ne sont pas encore installés sur le site.', nothing_to_send: 'Rien à envoyer : plan ou phase vide.',
    too_many_for_game: 'Plus de 300 éléments : envoyez phase par phase.', queue_unavailable: 'File des commandes du jeu indisponible.', side_invalid: 'Camp inconnu.',
    forbidden: 'Action réservée à l’auteur du plan ou à la configuration du poste.', no_unit: 'Vous n’êtes rattaché à aucune unité.',
    import_format: 'Fichier non reconnu (JSON du site, GeoJSON ou KML attendus).', import_too_large: 'Fichier trop lourd (2 Mo au plus).', import_too_many: 'Trop d’éléments dans ce fichier (1 500 au plus).'
  };

  var P = {
    id: Number(BOOT.planId) || 0, plan: null, canEdit: false, canAdmin: false, els: {}, phases: [], meta: { layers: [], notes: {}, legend: [], timeline: {} },
    serverMs: 0, pending: {}, deletes: {}, base: {}, sel: [], tool: 'select', armed: null, phase: null, briefing: false, layer: 'base',
    hist: new G.History(250), presence: [], comments: [], snap: true, snapGrid: false, draft: null, metaDirty: false, phasesDirty: false,
    readonlyLink: !!BOOT.readOnlyLink, saving: false, saveError: '', loaded: false, time: null, autoKey: false, grid: true,
    lib: { affil: 'F', echelon: 'squad' }, assignPhase: true, shown: [], shownBy: {}, lastWorld: null, mode: '2d', styleClip: null
  };
  var tm = null, layers = null, planLayer = null, draftLayer = null, toolLayer = null, overlay = null;
  var flushTimer = null, metaTimer = null, phaseTimer = null, pollTimer = null;
  var mobile = window.matchMedia && window.matchMedia('(max-width: 640px)').matches;
  var listeners = {};
  var E = {};

  function on(name, fn) { (listeners[name] = listeners[name] || []).push(fn); }
  function emit(name, a, b) { (listeners[name] || []).forEach(function (fn) { try { fn(a, b); } catch (e) { if (window.console) console.error(e); } }); }

  /* ---------- Réseau ---------- */
  function api(path, body, extra) {
    var init = { method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-cache', headers: { Accept: 'application/json' } };
    if (body) { init.headers['Content-Type'] = 'application/json'; init.headers['X-CSRF-Token'] = BOOT.csrf || ''; init.body = JSON.stringify(body); }
    if (extra) Object.keys(extra).forEach(function (k) { init[k] = extra[k]; });
    return fetch(BOOT.base + path, init).then(function (r) {
      if (r.status === 304) return { notModified: true };
      return r.json().catch(function () { return { ok: false, error: 'http_' + r.status }; });
    });
  }
  var msgTimer = null;
  function say(msg, isError) {
    var el = $('#plan-msg');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('is-error', !!isError);
    el.hidden = !msg;
    clearTimeout(msgTimer);
    msgTimer = setTimeout(function () { el.textContent = ''; el.hidden = true; }, isError ? 9000 : 5000);
  }
  function setSaveState() {
    var el = $('#plan-save');
    if (!el) return;
    var n = Object.keys(P.pending).length + Object.keys(P.deletes).length;
    var state = P.readonlyLink || !P.canEdit ? 'ro' : (P.saveError ? 'err' : (n || P.saving || P.metaDirty || P.phasesDirty ? 'busy' : 'ok'));
    el.textContent = P.readonlyLink ? 'Lecture seule (lien)' : (!P.canEdit ? 'Lecture seule' : (P.saveError ? 'Non enregistré : ' + P.saveError : (state === 'busy' ? 'Enregistrement…' : 'Enregistré · révision ' + (P.plan ? P.plan.rev : 0))));
    el.setAttribute('data-state', state);
  }

  /* ---------- Éléments ---------- */
  /** Éléments dans l'ordre du dessin : calque (le premier de la liste est dessous), puis ordre dans le calque. */
  function list() {
    var rank = {};
    (P.meta.layers || []).forEach(function (l, i) { rank[l.uid] = i; });
    return Object.keys(P.els).map(function (k) { return P.els[k]; }).sort(function (a, b) { return ((rank[a.layer] || 0) - (rank[b.layer] || 0)) || (a.z || 0) - (b.z || 0) || (a.uid < b.uid ? -1 : 1); });
  }
  function layerOf(uid) {
    return (P.meta.layers || []).filter(function (x) { return x.uid === uid; })[0] || null;
  }
  /** Affiché sur la carte : calque visible, élément non masqué, posé, présent à l'instant affiché, dans la phase en briefing. */
  function visible(el) {
    var l = layerOf(el.layer);
    if (l && l.visible === false) return false;
    if (!LG.visibleAt(el, P.time)) return false;
    if (P.briefing && P.phase && !G.inPhase(el, P.phase)) return false;
    return true;
  }
  function editable(el) {
    if (!P.canEdit || el.locked) return false;
    var l = layerOf(el.layer);
    return !(l && l.locked);
  }
  function maxZ() { var z = 0; list().forEach(function (e) { z = Math.max(z, e.z || 0); }); return z; }
  function selected() { return P.sel.map(function (u) { return P.els[u]; }).filter(Boolean); }
  /** Élément tel qu'il est affiché (déplacé à l'instant de la frise). */
  function shownOf(uid) { return P.shownBy[uid] || P.els[uid] || null; }

  /** Change des éléments (null = suppression), avec historique et envoi différé. */
  function commit(after, label, noHistory) {
    var before = {};
    Object.keys(after).forEach(function (uid) { before[uid] = P.els[uid] ? G.clone(P.els[uid]) : null; });
    if (!Object.keys(after).length) return;
    if (!noHistory) P.hist.push(before, after);
    applyMap(after);
    if (label) say(label);
  }
  function applyMap(map) {
    Object.keys(map).forEach(function (uid) {
      var el = map[uid];
      if (el) {
        P.els[uid] = G.clone(el);
        P.pending[uid] = true;
        delete P.deletes[uid];
      } else {
        delete P.els[uid];
        delete P.pending[uid];
        P.deletes[uid] = true;
        P.sel = P.sel.filter(function (s) { return s !== uid; });
      }
    });
    renderAll();
    scheduleFlush();
  }
  function scheduleFlush() {
    setSaveState();
    clearTimeout(flushTimer);
    flushTimer = setTimeout(flush, 700);
  }
  function requeue(sentPending, sentDeletes) {
    Object.keys(sentPending).forEach(function (u) { if (P.els[u]) P.pending[u] = true; });
    Object.keys(sentDeletes).forEach(function (u) { if (!P.els[u]) P.deletes[u] = true; });
  }
  function flush() {
    if (P.saving || !P.canEdit) return;
    var ups = Object.keys(P.pending).filter(function (u) { return P.els[u]; }).map(function (u) {
      var e = G.clone(P.els[u]);
      delete e.updated_at; delete e.updated_by; delete e.deleted;
      return e;
    });
    var dels = Object.keys(P.deletes);
    if (!ups.length && !dels.length) { setSaveState(); return; }
    var sentPending = P.pending, sentDeletes = P.deletes;
    P.pending = {}; P.deletes = {};
    P.saving = true;
    setSaveState();
    var base = {};
    ups.forEach(function (e) { if (P.base[e.uid]) base[e.uid] = P.base[e.uid]; });
    api('/atak/toc/api/plans/' + P.id + '/elements', { upserts: ups, deletes: dels, base: base }).then(function (j) {
      P.saving = false;
      if (!j || !j.ok) {
        // Échec : les changements restent à envoyer.
        requeue(sentPending, sentDeletes);
        P.saveError = ERR[j && j.error] || 'réseau';
        setSaveState();
        if (j && j.error !== 'read_only') setTimeout(scheduleFlush, 4000);
        return;
      }
      P.saveError = '';
      if (j.rev) P.plan.rev = j.rev;
      Object.keys(j.saved || {}).forEach(function (u) { P.base[u] = j.saved[u]; if (P.els[u]) P.els[u].updated_at = j.saved[u]; P.serverMs = Math.max(P.serverMs, j.saved[u]); });
      Object.keys(j.deleted || {}).forEach(function (u) { P.serverMs = Math.max(P.serverMs, j.deleted[u]); delete P.base[u]; });
      if ((j.overwritten || []).length) say(j.overwritten.length + ' élément(s) venaient d’être changés par un autre membre : votre version a été gardée (la dernière enregistrée gagne).');
      if ((j.refused || []).length) { say(j.refused.length + ' élément(s) verrouillé(s) : changement refusé.', true); poll(true); }
      if ((j.rejected || []).length) say(j.rejected.length + ' élément(s) refusé(s) par le site (' + j.rejected[0].reason + ').', true);
      setSaveState();
      if (Object.keys(P.pending).length || Object.keys(P.deletes).length) scheduleFlush();
    }).catch(function () {
      P.saving = false;
      requeue(sentPending, sentDeletes);
      P.saveError = 'réseau';
      setSaveState();
      setTimeout(scheduleFlush, 4000);
    });
  }

  function round1(q) { return [Math.round(q[0] * 10) / 10, Math.round(q[1] * 10) / 10]; }
  function newElement(kind, geom, pts, extraProps) {
    var def = KINDS[kind] || { color: '#f2f2f2' };
    var props = { color: def.color, width: 2, dash: def.dash || 'solid', arrow: def.arrow || 'none', fill: def.fill || 0, label: def.text || '', size: kind === 'text' ? 18 : 24, rotation: 0, note: '' };
    if (kind === 'unit') { props.affil = P.lib.affil; props.unitType = 'infantry'; props.echelon = P.lib.echelon; props.callsign = ''; props.label = ''; props.size = 26; }
    if (kind === 'icon') props.icon = 'flag';
    if (kind === 'text') props.label = 'TEXTE';
    if (kind === 'textbox') props.label = 'Texte';
    if (kind === 'fan') { props.radius = 1000; props.bearing = 0; props.arc = 60; props.minRange = 0; props.fill = 0.1; }
    if (kind === 'ring') props.rings = 1;
    Object.keys(extraProps || {}).forEach(function (k) { props[k] = extraProps[k]; });
    if (kind === 'unit') props.color = AFFIL_COLOR[props.affil] || AFFIL_COLOR.F;
    return { uid: G.uid('e'), kind: kind, geom: geom, pts: pts.map(round1), props: props, phases: P.phase && P.assignPhase ? [P.phase] : [], layer: activeLayer(), group: '', z: maxZ() + 1, locked: false };
  }
  function activeLayer() {
    var l = layerOf(P.layer);
    if (l && !l.locked) return P.layer;
    var free = (P.meta.layers || []).filter(function (x) { return !x.locked; })[0];
    return free ? free.uid : P.layer;
  }

  /* ---------- Rendu 2D ---------- */
  function renderAll() {
    if (!tm) return;
    planLayer.clearLayers();
    var by = P.els;
    P.shown = [];
    P.shownBy = {};
    list().forEach(function (el) {
      if (!visible(el)) return;
      var d = LG.atTime(el, P.time, by);
      var lay = layerOf(el.layer);
      var ghost = !P.briefing && P.phase && !G.inPhase(el, P.phase);
      P.shown.push(d);
      P.shownBy[el.uid] = d;
      planLayer.addLayer(R.render(tm, d, { catalog: CAT, ghost: ghost, opacity: lay && lay.opacity != null ? lay.opacity : 1, pane: 'planPane', markerPane: 'planMarkers', interactive: false }));
    });
    P.sel = P.sel.filter(function (u) { return P.els[u]; });
    renderOverlay();
    emit('render');
  }

  /* ---------- Surcouche : contours de sélection, poignées, cadre, lasso ---------- */
  function px(p) { var c = tm.map.latLngToContainerPoint(tm.toLL(p)); return [c.x, c.y]; }
  function worldOf(ev) { return tm.toWorld(tm.map.mouseEventToLatLng(ev)); }
  function pathD(pts, closed) {
    return pts.map(function (q, i) { return (i ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1); }).join('') + (closed ? 'Z' : '');
  }
  function selBox() {
    var els = P.sel.map(shownOf).filter(Boolean);
    if (!els.length) return null;
    return LG.bboxOf(els);
  }
  function handle(x, y, kind, title, cls) {
    return '<rect class="pe-h' + (cls ? ' ' + cls : '') + '" x="' + (x - 6).toFixed(1) + '" y="' + (y - 6).toFixed(1) + '" width="12" height="12" data-h="' + kind + '"><title>' + esc(title) + '</title></rect>';
  }
  function renderOverlay() {
    if (!overlay || !tm) return;
    var h = '';
    var sel = P.sel.map(shownOf).filter(Boolean);
    if (P.mode === '2d' && !P.briefing) {
      sel.forEach(function (d) {
        if (d.geom === 'point' && d.kind !== 'fan') {
          var ph = R.pointHtml(d, { catalog: CAT });
          var c = px(d.pts[0]);
          var x = c[0] - ph.anchor[0] - 5, y = c[1] - ph.anchor[1] - 5;
          h += '<rect class="pe-selpt" x="' + x.toFixed(1) + '" y="' + y.toFixed(1) + '" width="' + (ph.w + 10).toFixed(1) + '" height="' + (ph.h + 10).toFixed(1) + '"/>';
          // Trajectoire de l'unité choisie : images clés et chemin.
          var tr = P.els[d.uid] && P.els[d.uid].props.track;
          if (tr && tr.length > 1) {
            var path = [];
            var t0 = tr[0].t, t1 = tr[tr.length - 1].t;
            for (var k = 0; k <= 40; k++) path.push(px(LG.positionAt(P.els[d.uid], t0 + (t1 - t0) * k / 40, P.els)));
            h += '<path class="pe-traj" d="' + pathD(path) + '"/>';
            tr.forEach(function (kf) { var q = px(kf.p); h += '<path class="pe-key' + (P.time != null && Math.abs(kf.t - P.time) < 0.01 ? ' is-on' : '') + '" d="M' + q[0] + ' ' + (q[1] - 6) + 'l6 6-6 6-6-6z"><title>' + esc(LG.fmtH(kf.t)) + '</title></path>'; });
          }
        } else {
          var o = LG.outlineOf(d, 48).map(px);
          var closed = d.geom !== 'line';
          h += '<path class="pe-selout-bg" d="' + pathD(o, closed) + '"/><path class="pe-selout" d="' + pathD(o, closed) + '"/>';
        }
      });
      var canEdit = sel.length && sel.every(function (d) { return editable(P.els[d.uid]); });
      if (sel.length) {
        var b = selBox();
        var a = px([b[0], b[3]]), c2 = px([b[2], b[1]]);
        var pad = 9;
        var x0 = a[0] - pad, y0 = a[1] - pad, x1 = c2[0] + pad, y1 = c2[1] + pad;
        var multi = sel.length > 1 || (sel[0].geom !== 'point' || sel[0].kind === 'fan');
        if (multi) {
          h += '<rect class="pe-selbox" x="' + x0.toFixed(1) + '" y="' + y0.toFixed(1) + '" width="' + (x1 - x0).toFixed(1) + '" height="' + (y1 - y0).toFixed(1) + '"/>';
          if (canEdit && P.tool === 'select') {
            h += handle(x0, y0, 's:nw', 'Agrandir ou réduire', 'is-scale') + handle(x1, y0, 's:ne', 'Agrandir ou réduire', 'is-scale') + handle(x0, y1, 's:sw', 'Agrandir ou réduire', 'is-scale') + handle(x1, y1, 's:se', 'Agrandir ou réduire', 'is-scale');
            var mx = (x0 + x1) / 2;
            h += '<line class="pe-selbox" x1="' + mx + '" y1="' + y0 + '" x2="' + mx + '" y2="' + (y0 - 22) + '"/><circle class="pe-h is-rot" cx="' + mx + '" cy="' + (y0 - 26) + '" r="6" data-h="rot"><title>Pivoter (Maj : pas de 15°)</title></circle>';
          }
        }
        if (canEdit && sel.length === 1 && P.tool === 'select') {
          var d0 = sel[0];
          if (d0.geom === 'line' || d0.geom === 'area' || d0.geom === 'rect') {
            if (d0.geom !== 'rect' && d0.kind !== 'textbox') {
              var n = d0.pts.length;
              for (var i = 0; i < (d0.geom === 'area' ? n : n - 1); i++) {
                var m = px([(d0.pts[i][0] + d0.pts[(i + 1) % n][0]) / 2, (d0.pts[i][1] + d0.pts[(i + 1) % n][1]) / 2]);
                h += '<circle class="pe-h is-mid" cx="' + m[0].toFixed(1) + '" cy="' + m[1].toFixed(1) + '" r="4.5" data-h="m:' + i + '"><title>Glisser : ajouter un sommet</title></circle>';
              }
            }
            d0.pts.forEach(function (q, i2) { var s = px(q); h += handle(s[0], s[1], 'v:' + i2, 'Sommet ' + (i2 + 1) + (d0.geom === 'rect' ? '' : ' · double-clic : retirer')); });
          } else if (d0.geom === 'circle' || d0.geom === 'ellipse') {
            var c0 = d0.pts[0];
            var rx = d0.geom === 'circle' ? d0.props.radius : d0.props.rx, ry = d0.geom === 'circle' ? d0.props.radius : d0.props.ry;
            var e1 = px([c0[0] + rx, c0[1]]);
            h += handle(e1[0], e1[1], 'r:x', 'Rayon');
            if (d0.geom === 'ellipse') { var e2 = px([c0[0], c0[1] + ry]); h += handle(e2[0], e2[1], 'r:y', 'Second rayon'); }
          } else if (d0.kind === 'fan') {
            var bb = (Number(d0.props.bearing) || 0) * Math.PI / 180, rr = Number(d0.props.radius) || 1000, half = (Number(d0.props.arc) || 60) / 2 * Math.PI / 180;
            var f1 = px([d0.pts[0][0] + Math.sin(bb) * rr, d0.pts[0][1] + Math.cos(bb) * rr]);
            var f2 = px([d0.pts[0][0] + Math.sin(bb + half) * rr, d0.pts[0][1] + Math.cos(bb + half) * rr]);
            h += handle(f1[0], f1[1], 'fan:r', 'Portée et azimut') + handle(f2[0], f2[1], 'fan:a', 'Ouverture');
          }
        }
      }
    }
    if (P.marquee) {
      if (P.marquee.lasso) h += '<path class="pe-marquee" d="' + pathD(P.marquee.path.map(px), true) + '"/>';
      else { var m0 = px(P.marquee.a), m1 = px(P.marquee.b); h += '<rect class="pe-marquee" x="' + Math.min(m0[0], m1[0]) + '" y="' + Math.min(m0[1], m1[1]) + '" width="' + Math.abs(m1[0] - m0[0]) + '" height="' + Math.abs(m1[1] - m0[1]) + '"/>'; }
    }
    overlay.innerHTML = h;
  }

  /* ---------- Sélection ---------- */
  function setSel(uids) {
    P.sel = uids.filter(function (u, i) { return P.els[u] && uids.indexOf(u) === i; });
    renderOverlay();
    emit('selection');
  }
  /** Choisit des éléments ; un groupe se choisit en entier. add : ajoute ou retire (Maj / Ctrl). */
  function select(uids, add) {
    var ids = [];
    uids.forEach(function (u) {
      var el = P.els[u];
      if (!el) return;
      if (el.group) list().forEach(function (o) { if (o.group === el.group && ids.indexOf(o.uid) < 0) ids.push(o.uid); });
      else if (ids.indexOf(u) < 0) ids.push(u);
    });
    if (add) {
      var cur = P.sel.slice();
      ids.forEach(function (u) { var i = cur.indexOf(u); if (i >= 0) cur.splice(i, 1); else cur.push(u); });
      setSel(cur);
    } else setSel(ids);
  }
  function selectWhere(fn, label) {
    var ids = list().filter(visible).filter(fn).map(function (e) { return e.uid; });
    setSel(ids);
    say(ids.length + ' élément(s) choisi(s)' + (label ? ' · ' + label : ''));
  }
  function editSelected(fn, label) {
    var els = selected().filter(editable);
    if (!els.length) { if (selected().length) say('Élément verrouillé : déverrouillez-le d’abord.', true); return; }
    var m = {};
    els.forEach(function (el) { var c = fn(G.clone(el)); if (c) m[c.uid] = c; });
    commit(m, label);
  }
  function translateEl(el, dx, dy) {
    var c = G.translate(el, dx, dy);
    if (Array.isArray(c.props.track)) c.props.track = c.props.track.map(function (k) { return { t: k.t, p: round1([k.p[0] + dx, k.p[1] + dy]) }; });
    return c;
  }
  /** Déplacement : à un instant de la frise avec « clé auto », l'unité reçoit une image clé au lieu de bouger partout. */
  function moveEl(el, dx, dy) {
    if (el.geom === 'point' && P.time != null && P.autoKey && el.kind !== 'fan') {
      var cur = LG.positionAt(el, P.time, P.els);
      var k = LG.setKeyframe(el, P.time, [cur[0] + dx, cur[1] + dy], LG.normTimeline(P.meta.timeline).start);
      if (k) return k;
    }
    return translateEl(el, dx, dy);
  }
  function rotateEl(el, deg, center) {
    var c = G.rotate(el, deg, center);
    if (c.kind === 'fan') { c.props.bearing = ((((Number(c.props.bearing) || 0) + deg) % 360) + 360) % 360; c.props.rotation = 0; }
    return c;
  }
  function deleteSelected() {
    var els = selected().filter(editable);
    if (!els.length) return;
    var m = {};
    els.forEach(function (el) { m[el.uid] = null; });
    // Les unités filles remontent d'un cran dans l'ORBAT.
    list().forEach(function (o) { if (o.kind === 'unit' && o.props.parent && m[o.props.parent] === null && m[o.uid] !== null) { var c = G.clone(o); c.props.parent = P.els[o.props.parent].props.parent || ''; m[c.uid] = c; } });
    commit(m, els.length + ' élément(s) supprimé(s) · Ctrl+Z pour annuler');
  }
  function pasteOpts(extra) {
    var o = { layer: activeLayer(), layerOk: function (u) { var l = layerOf(u); return !!l && !l.locked; }, phaseOk: function (u) { return P.phases.some(function (x) { return x.uid === u; }); }, z: maxZ() };
    Object.keys(extra || {}).forEach(function (k) { o[k] = extra[k]; });
    return o;
  }
  function addCopies(copies, label) {
    if (!copies.length) return;
    var room = (CAT.limits && CAT.limits.elements || 1500) - list().length;
    if (copies.length > room) { say('Trop d’éléments : le plan en accepte ' + (CAT.limits && CAT.limits.elements || 1500) + ' au plus.', true); return; }
    var m = {};
    copies.forEach(function (c) { if (P.phase && P.assignPhase && !(c.phases || []).length) c.phases = [P.phase]; m[c.uid] = c; });
    commit(m, label);
    setSel(copies.map(function (c) { return c.uid; }));
  }
  function duplicateSelected(offset) {
    var els = selected();
    if (!els.length || !P.canEdit) return;
    var d = offset == null ? tm.metersPerPixel() * 24 : offset;
    addCopies(LG.pasteElements(els, pasteOpts({ dx: d, dy: -d, keepParent: true })), els.length + ' élément(s) dupliqué(s)');
  }
  function clipText() { return LG.clipSerialize(selected(), P.plan ? P.plan.world : ''); }
  function copySelected(cut) {
    var els = selected();
    if (!els.length) return false;
    var text = clipText();
    P.clipboard = text;
    try { window.localStorage.setItem('athena:toc:clipboard', text); } catch (e) {}
    if (cut) deleteSelected();
    say(els.length + ' élément(s) ' + (cut ? 'coupé(s)' : 'copié(s)'));
    return text;
  }
  /** Colle un texte (notre format, plan JSON, GeoJSON) ; inPlace : à la même place, sinon sous le curseur. */
  function pasteText(text, inPlace) {
    if (!P.canEdit) return;
    var parsed = LG.clipParse(text);
    if (!parsed.kind) {
      var own = P.clipboard;
      try { own = window.localStorage.getItem('athena:toc:clipboard') || own; } catch (e) {}
      parsed = LG.clipParse(own);
    }
    if (parsed.kind === 'geojson') { importText('geojson', parsed.text); return; }
    if (!parsed.kind || !parsed.elements.length) { say('Presse-papiers vide ou non reconnu.', true); return; }
    var at = inPlace ? null : (P.lastWorld && insideView(P.lastWorld) ? P.lastWorld : tm.toWorld(tm.map.getCenter()));
    addCopies(LG.pasteElements(parsed.elements, pasteOpts({ at: at })), parsed.elements.length + ' élément(s) collé(s)' + (inPlace ? ' à la même place' : ''));
  }
  function insideView(p) {
    var b = tm.map.getBounds(), ll = tm.toLL(p);
    return b.contains(ll);
  }
  /** Import par le site (GeoJSON, KML, JSON du site) : les éléments reviennent contrôlés, puis sont posés (annulable). */
  function importText(format, content) {
    if (!P.canEdit) return;
    var b = tm.map.getBounds();
    var sw = tm.toWorld(b.getSouthWest()), ne = tm.toWorld(b.getNorthEast());
    var padX = (ne[0] - sw[0]) * 0.15, padY = (ne[1] - sw[1]) * 0.15;
    say('Import en cours…');
    api('/atak/toc/api/plans/' + P.id + '/import', { format: format, content: content, fit: [sw[0] + padX, sw[1] + padY, ne[0] - padX, ne[1] - padY] }).then(function (j) {
      if (!j || !j.ok) { say(ERR[j && j.error] || 'Import refusé.', true); return; }
      var els = j.elements || [];
      if (!els.length) { say('Rien à importer dans ce fichier.', true); return; }
      addCopies(LG.pasteElements(els, pasteOpts({})), els.length + ' élément(s) importé(s)' + (j.rejected ? ' · ' + j.rejected + ' écarté(s)' : '') + ' · Ctrl+Z pour annuler');
    }).catch(function () { say('Réseau indisponible.', true); });
  }
  function importFile(file) {
    if (!file) return;
    if (file.size > 2000000) { say(ERR.import_too_large, true); return; }
    var name = String(file.name || '').toLowerCase();
    var format = /\.kml$/.test(name) ? 'kml' : (/\.(geojson)$/.test(name) ? 'geojson' : (/\.json$/.test(name) ? 'json' : ''));
    if (!format) { say(ERR.import_format, true); return; }
    var fr = new FileReader();
    fr.onload = function () {
      var text = String(fr.result || '');
      if (format === 'json') format = /"FeatureCollection"|"Feature"/.test(text.slice(0, 4000)) && !/"athena-plan"/.test(text.slice(0, 400)) ? 'geojson' : 'athena';
      importText(format, text);
    };
    fr.readAsText(file);
  }
  function undo() { var map = P.hist.undo(); if (!map) return; applyMap(map); say('Annulé'); }
  function redo() { var map = P.hist.redo(); if (!map) return; applyMap(map); say('Rétabli'); }
  function zOrder(front, step) {
    var els = selected().filter(editable);
    if (!els.length) return;
    var all = list();
    var m = {};
    if (step) {
      // D'un cran : échange avec le voisin du dessus ou du dessous.
      var ids = els.map(function (e) { return e.uid; });
      var order = all.map(function (e) { return e.uid; });
      (front ? order.slice().reverse() : order).forEach(function (u) {
        if (ids.indexOf(u) < 0) return;
        var i = order.indexOf(u), j = front ? i + 1 : i - 1;
        if (j < 0 || j >= order.length || ids.indexOf(order[j]) >= 0) return;
        order[i] = order[j]; order[j] = u;
      });
      order.forEach(function (u, i) { if ((P.els[u].z || 0) !== i + 1) { var c = G.clone(P.els[u]); c.z = i + 1; m[u] = c; } });
    } else {
      var zs = all.map(function (e) { return e.z || 0; });
      var z = front ? Math.max.apply(null, zs) : Math.min.apply(null, zs);
      els.forEach(function (el, i) { var c = G.clone(el); c.z = front ? z + 1 + i : z - els.length + i; m[c.uid] = c; });
    }
    commit(m, front ? (step ? 'Avancé d’un cran' : 'Mis au premier plan') : (step ? 'Reculé d’un cran' : 'Mis en arrière-plan'));
  }
  function groupSelected(onOff) {
    var els = selected().filter(editable);
    if (els.length < (onOff ? 2 : 1)) return;
    var g = onOff ? G.uid('g') : '';
    editSelected(function (c) { c.group = g; return c; }, onOff ? els.length + ' éléments groupés' : 'Groupe défait');
  }
  function alignSelected(mode) {
    var els = selected().filter(editable);
    if (els.length < 2) { say('Choisissez au moins deux éléments.', true); return; }
    var moves = mode === 'dh' || mode === 'dv' ? LG.distribute(els, mode === 'dv' ? 'v' : 'h') : LG.align(els, mode);
    var m = {};
    els.forEach(function (el) { var d = moves[el.uid]; if (d && (d[0] || d[1])) m[el.uid] = translateEl(el, d[0], d[1]); });
    commit(m, mode === 'dh' || mode === 'dv' ? 'Éléments répartis' : 'Éléments alignés');
  }
  function toggleLock() {
    var els = selected();
    if (!els.length || !P.canEdit) return;
    var allLocked = els.every(function (x) { return x.locked; });
    var m = {};
    els.forEach(function (x) { var c = G.clone(x); c.locked = !allLocked; m[c.uid] = c; });
    commit(m, allLocked ? 'Déverrouillé' : 'Verrouillé');
  }
  function toggleHidden(uids, hide) {
    var m = {};
    uids.forEach(function (u) { var el = P.els[u]; if (!el || !editable(el)) return; var c = G.clone(el); c.props.hidden = hide == null ? !c.props.hidden : !!hide; m[u] = c; });
    commit(m, null);
  }
  function copyStyle() { var el = selected()[0]; if (!el) return; P.styleClip = LG.pickStyle(el); say('Style copié'); }
  function pasteStyle() { if (!P.styleClip) { say('Aucun style copié.', true); return; } editSelected(function (c) { return LG.applyStyle(c, P.styleClip); }, 'Style appliqué'); }

  /* ---------- Aimantation ---------- */
  function snapPoint(p, exceptUid) {
    if (P.snap) {
      var tol = tm.metersPerPixel() * 12;
      var best = null, bestD = tol;
      P.shown.forEach(function (el) {
        if (el.uid === exceptUid) return;
        G.outline(el, el.geom === 'circle' || el.geom === 'ellipse' ? 8 : 0).forEach(function (q) {
          var d = G.dist(p, q);
          if (d < bestD) { bestD = d; best = q; }
        });
      });
      if (best) return [best[0], best[1]];
    }
    return P.snapGrid ? LG.snapToGrid(p, 10) : p;
  }

  /* ---------- Outils ---------- */
  var HINT = {
    select: 'Clic : choisir · Maj ou Ctrl : ajouter · glisser dans le vide : cadre · Alt+clic : élément du dessous · Alt+glisser : dupliquer · Espace+glisser : carte',
    lasso: 'Glisser : entourer les éléments à choisir · Maj : ajouter à la sélection',
    pan: 'Glisser : déplacer la carte · molette : rapprocher',
    point: 'Clic : poser · Échap : quitter l’outil',
    line: 'Clics : points du trait · double-clic ou Entrée : terminer · Retour arrière : dernier point · Échap : annuler',
    area: 'Clics : sommets de la zone · double-clic ou Entrée : fermer · Échap : annuler',
    circle: 'Clic : centre, puis clic : rayon (ou glisser)',
    rect: 'Clic : premier coin, puis clic : coin opposé (ou glisser)',
    ellipse: 'Clic : centre, puis clic : rayons (ou glisser)'
  };
  var VIEW_TOOLS = ['select', 'lasso', 'pan'];
  var toolHandlers = {};
  function geomFor(tool) {
    if (P.armed && P.armed.geom) return P.armed.geom;
    if (tool === 'rect') return 'rect';
    if (tool === 'ellipse') return 'ellipse';
    var d = KINDS[tool];
    return d ? d.geoms[0] : null;
  }
  /** Outil courant. armed : { props, geom } pour un symbole de la bibliothèque (pose au clic). */
  function setTool(t, armed) {
    if (!P.canEdit && VIEW_TOOLS.indexOf(t) < 0 && !toolHandlers[t]) return;
    cancelDraft();
    if (toolHandlers[P.tool] && toolHandlers[P.tool].leave && P.tool !== t) toolHandlers[P.tool].leave();
    P.tool = t;
    P.armed = armed || null;
    $$('[data-tool]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-tool') === t && !armed)); });
    var g = geomFor(t);
    var th = toolHandlers[t];
    $('#plan-hint').textContent = (th && th.hint) || HINT[t] || HINT[g] || '';
    var c = tm.map.getContainer();
    if (t === 'pan') tm.map.dragging.enable(); else tm.map.dragging.disable();
    c.style.cursor = t === 'pan' ? 'grab' : (t === 'select' ? '' : 'crosshair');
    if (th && th.enter) th.enter();
    renderOverlay();
    emit('tool');
  }

  /* ---------- Dessin ---------- */
  function cancelDraft() {
    P.draft = null;
    if (draftLayer) draftLayer.clearLayers();
    var m = $('#plan-measure'); if (m) m.textContent = '';
  }
  function preview(els, opacity) {
    draftLayer.clearLayers();
    els.forEach(function (el) { draftLayer.addLayer(R.render(tm, el, { catalog: CAT, pane: 'planHandles', markerPane: 'planHandles', interactive: false, opacity: opacity == null ? 0.85 : opacity })); });
  }
  function drawDraft(cursor) {
    draftLayer.clearLayers();
    var d = P.draft;
    if (!d) return;
    var pts = d.pts.concat(cursor ? [cursor] : []);
    var first = d.pts[0], cur = cursor || first;
    var tmp;
    var st = { color: '#34d399', dash: 'dash', fill: 0.06, width: 2 };
    if (d.geom === 'circle') tmp = { geom: 'circle', kind: 'circle', pts: [first], props: L.extend({ radius: Math.max(5, G.dist(first, cur)) }, st) };
    else if (d.geom === 'ellipse') tmp = { geom: 'ellipse', kind: 'ellipse', pts: [first], props: L.extend({ rx: Math.max(5, Math.abs(cur[0] - first[0])), ry: Math.max(5, Math.abs(cur[1] - first[1])) }, st) };
    else if (d.geom === 'rect') tmp = { geom: 'rect', kind: 'rect', pts: [first, cur], props: st };
    else tmp = { geom: pts.length >= 3 && d.geom === 'area' ? 'area' : 'line', kind: 'line', pts: pts, props: L.extend({}, st, { fill: d.geom === 'area' ? 0.06 : 0 }) };
    if (tmp.pts.length < 2 && tmp.geom === 'line') return;
    draftLayer.addLayer(R.render(tm, tmp, { pane: 'planHandles', markerPane: 'planHandles', interactive: false }));
    if (cursor && d.pts.length) {
      var last = d.pts[d.pts.length - 1];
      $('#plan-measure').textContent = G.fmtDist(G.dist(last, cursor)) + ' · ' + Math.round(G.azimuth(last, cursor)) + '°' + (d.pts.length > 1 ? ' · total ' + G.fmtDist(G.pathLength(pts)) : '');
    }
  }
  function finishDraft() {
    var d = P.draft;
    if (!d) return;
    var el = null;
    var two = d.kind === 'sbf' || d.kind === 'abf' || d.kind === 'textbox';
    if (d.geom === 'line' && d.pts.length >= 2) el = newElement(d.kind, 'line', two ? [d.pts[0], d.pts[d.pts.length - 1]] : d.pts, d.props);
    if (d.geom === 'area' && d.pts.length >= 3) el = newElement(d.kind, 'area', d.pts, d.props);
    cancelDraft();
    if (el) addNew(el);
  }
  function addNew(el, keepTool) {
    var m = {}; m[el.uid] = el;
    commit(m, (KINDS[el.kind] ? KINDS[el.kind].label : el.kind) + ' ajouté');
    setSel([el.uid]);
    emit('added', el);
    if (!keepTool && el.geom !== 'point') setTool('select');
  }
  /** Pose un élément ponctuel (bibliothèque, ORBAT, glisser-déposer). */
  function placePoint(kind, p, props) {
    if (!P.canEdit) return null;
    var el = newElement(kind, 'point', [snapPoint(p)], props);
    addNew(el, true);
    return el;
  }
  /** Pose un élément à forme (zone, cercle…) à sa taille par défaut autour d'un point : glisser-déposer de la bibliothèque. */
  function placeShape(kind, p, props) {
    if (!P.canEdit) return null;
    var def = KINDS[kind];
    if (!def) return null;
    var g = def.geoms[0];
    var s = tm.metersPerPixel() * 70;
    var el;
    if (g === 'point') return placePoint(kind, p, props);
    if (g === 'circle') el = newElement(kind, 'circle', [p], L.extend({ radius: Math.round(s) }, props));
    else if (g === 'rect') el = newElement(kind, 'rect', [[p[0] - s, p[1] - s * 0.6], [p[0] + s, p[1] + s * 0.6]], props);
    else if (g === 'ellipse') el = newElement(kind, 'ellipse', [p], L.extend({ rx: Math.round(s), ry: Math.round(s * 0.6) }, props));
    else if (g === 'area') el = newElement(kind, 'area', [[p[0] - s, p[1] - s * 0.6], [p[0] + s, p[1] - s * 0.6], [p[0] + s, p[1] + s * 0.6], [p[0] - s, p[1] + s * 0.6]], props);
    else el = newElement(kind, 'line', kind === 'textbox' ? [p, [p[0] + s, p[1] + s * 0.7]] : [[p[0] - s, p[1]], [p[0] + s, p[1]]], props);
    addNew(el);
    return el;
  }
  function onMapClick(p, ev, dbl) {
    var raw = p;
    p = snapPoint(p);
    var th = toolHandlers[P.tool];
    if (th) { if (th.click) th.click(raw, ev, dbl); return; }
    var kind = P.armed && P.armed.kind ? P.armed.kind : P.tool;
    var g = geomFor(P.tool);
    if (!g || !KINDS[kind]) return;
    var props = P.armed ? P.armed.props : null;
    if (g === 'point') { placePoint(kind, p, props); if (!ev.shiftKey && kind !== 'unit') setTool('select'); return; }
    if (dbl && P.draft) { P.draft.pts.pop(); finishDraft(); return; }
    if (!P.draft) P.draft = { kind: kind, geom: g, pts: [], props: props };
    P.draft.pts.push(p);
    var d = P.draft;
    if (d.pts.length === 2 && (g === 'circle' || g === 'ellipse' || g === 'rect')) { finishShape(d.pts[0], d.pts[1]); return; }
    if ((kind === 'sbf' || kind === 'abf' || kind === 'textbox') && d.pts.length === 2) { finishDraft(); return; }
    drawDraft(null);
  }
  function finishShape(a, b) {
    var d = P.draft;
    if (!d) return;
    var el = null;
    if (d.geom === 'circle') el = newElement(d.kind, 'circle', [a], L.extend({ radius: Math.max(5, Math.round(G.dist(a, b))) }, d.props));
    if (d.geom === 'ellipse') el = newElement(d.kind, 'ellipse', [a], L.extend({ rx: Math.max(5, Math.round(Math.abs(b[0] - a[0]))), ry: Math.max(5, Math.round(Math.abs(b[1] - a[1]))) }, d.props));
    if (d.geom === 'rect') el = newElement(d.kind, 'rect', [a, b], d.props);
    cancelDraft();
    if (el) addNew(el);
  }

  /* ---------- Pointeur : sélection, déplacement, cadre, lasso, poignées, dessin ---------- */
  var ptr = null, lastClick = { t: 0, x: 0, y: 0 }, hoverRaf = 0;
  function tolFor(d) {
    var mpp = tm.metersPerPixel();
    if (d.geom === 'point' && d.kind !== 'fan') return mpp * Math.max(15, (d.props.size || 24) * 0.75);
    return mpp * 8;
  }
  function stackAt(w) { return LG.pickStack(P.shown, w, tolFor); }
  function onDown(ev) {
    if (!tm || P.mode !== '2d') return;
    if (ev.target.closest && ev.target.closest('.leaflet-control, .leaflet-popup, .pe-float, .toc-layers')) return;
    var hEl = ev.target.closest && ev.target.closest('[data-h]');
    var w = worldOf(ev);
    P.lastWorld = w;
    if (ev.button === 1 || (ev.button === 0 && P.space)) { ptr = { type: 'pan', x: ev.clientX, y: ev.clientY }; ev.preventDefault(); track(); return; }
    if (ev.button !== 0) return;
    if (P.briefing) return;
    if (hEl && P.canEdit) { startHandle(ev, hEl.getAttribute('data-h'), w); return; }
    var tool = P.tool;
    if (tool === 'pan') return;
    var base = { x: ev.clientX, y: ev.clientY, w0: w, moved: false, add: ev.shiftKey || ev.ctrlKey || ev.metaKey, alt: ev.altKey };
    if (tool === 'select' || tool === 'lasso') {
      var stack = stackAt(w);
      var hit = null;
      if (stack.length) {
        var inSel = stack.filter(function (u) { return P.sel.indexOf(u) >= 0; })[0];
        hit = inSel && !ev.altKey ? inSel : stack[0];
      }
      if (hit && tool === 'select') {
        ptr = L.extend(base, { type: 'el', hit: hit, stack: stack, was: P.sel.indexOf(hit) >= 0 });
        if (!ptr.add && !ptr.alt && !ptr.was) select([hit]);
      } else ptr = L.extend(base, { type: 'marquee', lasso: tool === 'lasso', path: [w] });
    } else ptr = L.extend(base, { type: 'draw' });
    track();
  }
  function track() {
    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onUp);
  }
  function untrack() {
    document.removeEventListener('pointermove', onMove);
    document.removeEventListener('pointerup', onUp);
    document.removeEventListener('pointercancel', onUp);
  }
  function onHover(ev) {
    if (!tm || P.mode !== '2d') return;
    var w = worldOf(ev);
    P.lastWorld = w;
    if (ptr) return;
    if (P.draft) drawDraft(snapPoint(w));
    var th = toolHandlers[P.tool];
    if (th && th.move) th.move(w, ev);
    if (P.tool !== 'select' || P.briefing) return;
    cancelAnimationFrame(hoverRaf);
    hoverRaf = requestAnimationFrame(function () {
      var s = stackAt(w);
      tm.map.getContainer().style.cursor = s.length ? (P.sel.indexOf(s[0]) >= 0 && P.canEdit ? 'move' : 'pointer') : '';
    });
  }
  function onMove(ev) {
    if (!ptr) return;
    var w = worldOf(ev);
    P.lastWorld = w;
    if (ptr.type === 'pan') { tm.map.panBy([ptr.x - ev.clientX, ptr.y - ev.clientY], { animate: false }); ptr.x = ev.clientX; ptr.y = ev.clientY; return; }
    if (!ptr.moved && Math.abs(ev.clientX - ptr.x) + Math.abs(ev.clientY - ptr.y) < 5) return;
    ptr.moved = true;
    if (ptr.type === 'el') {
      if (!ptr.started) {
        ptr.started = true;
        if (ptr.add && !ptr.was) select([ptr.hit], true);
        ptr.els = selected();
        ptr.ok = P.canEdit && ptr.els.length && ptr.els.every(editable);
        if (ptr.ok && ptr.alt) {
          // Alt+glisser : on déplace des copies, les originaux restent en place.
          var copies = LG.pasteElements(ptr.els, pasteOpts({ keepParent: true }));
          var m = {}; copies.forEach(function (c) { m[c.uid] = c; });
          commit(m, null);
          P.sel = copies.map(function (c) { return c.uid; });
          ptr.els = copies; ptr.dup = true;
        }
      }
      if (!ptr.ok) return;
      var d = dragDelta(w);
      ptr.dx = d[0]; ptr.dy = d[1];
      preview(ptr.els.map(function (el) { return LG.atTime(moveEl(el, d[0], d[1]), P.time, P.els); }));
      $('#plan-measure').textContent = G.fmtDist(Math.sqrt(d[0] * d[0] + d[1] * d[1])) + ' · ' + Math.round(G.azimuth([0, 0], d)) + '°';
    } else if (ptr.type === 'marquee') {
      if (ptr.lasso) { ptr.path.push(w); P.marquee = { lasso: true, path: ptr.path }; }
      else P.marquee = { a: ptr.w0, b: w };
      renderOverlay();
    } else if (ptr.type === 'handle') {
      var res = applyHandle(w, ev);
      if (res) { ptr.result = res; preview(Object.keys(res).map(function (u) { return res[u]; })); }
    } else if (ptr.type === 'draw') {
      var g = geomFor(P.tool);
      if (g === 'circle' || g === 'ellipse' || g === 'rect') {
        if (!P.draft) P.draft = { kind: P.armed && P.armed.kind ? P.armed.kind : P.tool, geom: g, pts: [snapPoint(ptr.w0)], props: P.armed ? P.armed.props : null };
        ptr.dragShape = true;
        drawDraft(snapPoint(w));
      } else if (P.draft) drawDraft(snapPoint(w));
    }
  }
  /** Déplacement en cours : un seul point choisi s'aimante (sommets, grille). */
  function dragDelta(w) {
    var dx = w[0] - ptr.w0[0], dy = w[1] - ptr.w0[1];
    if (ptr.els.length === 1 && ptr.els[0].geom === 'point') {
      var cur = LG.positionAt(ptr.els[0], P.time == null ? -Infinity : P.time, P.els);
      if (P.time == null) cur = ptr.els[0].pts[0];
      var s = snapPoint([cur[0] + dx, cur[1] + dy], ptr.els[0].uid);
      return [s[0] - cur[0], s[1] - cur[1]];
    }
    return [dx, dy];
  }
  function onUp(ev) {
    untrack();
    var p = ptr;
    ptr = null;
    if (!p) return;
    draftLayerClearIfNoDraft();
    if (p.type === 'pan') return;
    var w = ev && ev.clientX != null ? worldOf(ev) : p.w0;
    if (p.type === 'el') {
      if (p.moved && p.ok && (p.dx || p.dy)) {
        var m = {};
        p.els.forEach(function (el) { m[el.uid] = moveEl(el, p.dx, p.dy); });
        var keyed = P.time != null && P.autoKey && p.els.some(function (el) { return el.geom === 'point'; });
        commit(m, p.els.length + ' élément(s) ' + (p.dup ? 'dupliqué(s) et ' : '') + 'déplacé(s) de ' + G.fmtDist(Math.sqrt(p.dx * p.dx + p.dy * p.dy)) + (keyed ? ' · image clé à ' + LG.fmtH(P.time) : ''));
      } else if (!p.moved) {
        if (p.alt) { var nx = LG.cyclePick(p.stack, P.sel.length === 1 ? P.sel[0] : null); if (nx) select([nx]); }
        else if (p.add) select([p.hit], true);
        else {
          if (P.sel.length !== 1 || P.sel[0] !== p.hit) select([p.hit]);
          if (isDouble(ev)) emit('edit', p.hit);
        }
      }
      $('#plan-measure').textContent = '';
      return;
    }
    if (p.type === 'marquee') {
      P.marquee = null;
      if (p.moved) {
        var box = p.lasso ? null : LG.normBox(p.w0, w);
        var ids = P.shown.filter(function (d) { return p.lasso ? (p.path.length > 2 && LG.polyHit(d, p.path)) : LG.boxHit(d, box); }).map(function (d) { return d.uid; });
        // Un groupe touché vient en entier.
        var full = [];
        ids.forEach(function (u) { var el = P.els[u]; if (el.group) list().forEach(function (o) { if (o.group === el.group && full.indexOf(o.uid) < 0) full.push(o.uid); }); else if (full.indexOf(u) < 0) full.push(u); });
        setSel(p.add ? P.sel.concat(full) : full);
      } else if (!p.add) setSel([]);
      else renderOverlay();
      return;
    }
    if (p.type === 'handle') {
      if (p.moved && p.result) commit(p.result, null);
      else if (!p.moved && p.h === 'v' && isDouble(ev)) removeVertex(p.i);
      return;
    }
    if (p.type === 'draw') {
      if (p.dragShape && P.draft) { finishShape(P.draft.pts[0], snapPoint(w)); return; }
      onMapClick(w, ev || {}, isDouble(ev));
    }
  }
  function draftLayerClearIfNoDraft() { if (!P.draft && draftLayer) draftLayer.clearLayers(); }
  function isDouble(ev) {
    if (!ev || ev.clientX == null) return false;
    var now = Date.now();
    var dbl = now - lastClick.t < 380 && Math.abs(ev.clientX - lastClick.x) + Math.abs(ev.clientY - lastClick.y) < 8;
    lastClick = dbl ? { t: 0, x: 0, y: 0 } : { t: now, x: ev.clientX, y: ev.clientY };
    return dbl;
  }
  function startHandle(ev, code, w) {
    var parts = code.split(':');
    var b = selBox();
    ptr = { type: 'handle', h: parts[0], i: parts[1], x: ev.clientX, y: ev.clientY, w0: w, moved: false, els: selected().filter(editable), box: b, center: b ? [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2] : w };
    if (ptr.h === 's' && b) ptr.pivot = [ptr.i.charAt(1) === 'w' ? b[2] : b[0], ptr.i.charAt(0) === 'n' ? b[1] : b[3]];
    if (ptr.h === 'v' || ptr.h === 'm') ptr.i = parseInt(ptr.i, 10);
    ev.preventDefault();
    ev.stopPropagation();
    track();
  }
  function applyHandle(w, ev) {
    var el = ptr.els[0];
    if (!el) return null;
    var out = {};
    var c;
    if (ptr.h === 'm') {
      // Poignée de milieu : un sommet naît là, puis se déplace comme les autres.
      c = G.clone(el);
      c.pts.splice(ptr.i + 1, 0, round1(w));
      ptr.els = [c]; ptr.h = 'v'; ptr.i = ptr.i + 1;
      el = c;
    }
    if (ptr.h === 'v') {
      c = G.clone(el);
      c.pts[ptr.i] = round1(snapPoint(w, el.uid));
      out[c.uid] = c;
    } else if (ptr.h === 'r') {
      c = G.clone(el);
      var c0 = el.pts[0];
      if (c.geom === 'circle') c.props.radius = Math.max(5, Math.round(G.dist(c0, w)));
      else if (ptr.i === 'x') c.props.rx = Math.max(5, Math.round(Math.abs(w[0] - c0[0])));
      else c.props.ry = Math.max(5, Math.round(Math.abs(w[1] - c0[1])));
      out[c.uid] = c;
      $('#plan-measure').textContent = 'rayon ' + G.fmtDist(c.geom === 'circle' ? c.props.radius : (ptr.i === 'x' ? c.props.rx : c.props.ry));
    } else if (ptr.h === 'fan') {
      c = G.clone(el);
      var az = G.azimuth(el.pts[0], w);
      if (ptr.i === 'r') { c.props.radius = Math.max(50, Math.round(G.dist(el.pts[0], w))); c.props.bearing = ev.shiftKey ? Math.round(az / 5) * 5 : Math.round(az); c.props.minRange = Math.min(c.props.minRange || 0, c.props.radius); }
      else { var da = Math.abs(((az - (Number(el.props.bearing) || 0) + 540) % 360) - 180); c.props.arc = Math.max(5, Math.min(360, Math.round(da * 2))); }
      out[c.uid] = c;
      $('#plan-measure').textContent = G.fmtDist(c.props.radius) + ' · azimut ' + Math.round(c.props.bearing) + '° · ouverture ' + Math.round(c.props.arc) + '°';
    } else if (ptr.h === 's') {
      var f = LG.scaleFactor(ptr.pivot, ptr.w0, w);
      ptr.els.forEach(function (e) { var s = G.scale(e, f, ptr.pivot); out[s.uid] = s; });
      $('#plan-measure').textContent = '× ' + f.toFixed(2).replace('.', ',');
    } else if (ptr.h === 'rot') {
      var a = LG.rotationAngle(ptr.center, ptr.w0, w, ev.shiftKey);
      ptr.els.forEach(function (e) { var r = rotateEl(e, a, ptr.center); out[r.uid] = r; });
      $('#plan-measure').textContent = (a > 0 ? '+' : '') + Math.round(a) + '°';
    }
    return out;
  }
  function removeVertex(i) {
    var el = selected()[0];
    if (!el || !editable(el) || (el.geom !== 'line' && el.geom !== 'area')) return;
    if (el.pts.length <= (el.geom === 'line' ? 2 : 3)) { say(el.geom === 'line' ? 'Un trait garde au moins deux points.' : 'Une zone garde au moins trois sommets.', true); return; }
    var c = G.clone(el);
    c.pts.splice(i, 1);
    var m = {}; m[c.uid] = c;
    commit(m, 'Sommet retiré');
  }

  /* ---------- Phases et réglages ---------- */
  function phasesChanged() {
    P.phasesDirty = true;
    setSaveState();
    clearTimeout(phaseTimer);
    phaseTimer = setTimeout(function () {
      api('/atak/toc/api/plans/' + P.id + '/phases', { phases: P.phases.map(function (p, i) { return { uid: p.uid, ord: i, name: p.name || ('Phase ' + (i + 1)), note: p.note || '', time_label: p.time_label || '' }; }) }).then(function (j) {
        P.phasesDirty = false;
        setSaveState();
        if (!j || !j.ok) say(ERR[j && j.error] || 'Phases non enregistrées.', true);
      });
    }, 700);
  }
  function metaChanged() {
    P.metaDirty = true;
    setSaveState();
    clearTimeout(metaTimer);
    metaTimer = setTimeout(saveMeta, 900);
  }
  function saveMeta(extra) {
    var body = { meta: P.meta };
    if (extra) Object.keys(extra).forEach(function (k) { body[k] = extra[k]; });
    return api('/atak/toc/api/plans/' + P.id, body).then(function (j) {
      P.metaDirty = false;
      if (j && j.ok) { if (j.rev) P.plan.rev = j.rev; setSaveState(); } else say(ERR[j && j.error] || 'Réglages non enregistrés.', true);
      return j;
    });
  }
  /** Phase affichée (null = plan entier). La frise suit : l'instant passe au début de la phase. */
  function setPhase(uid, keepTime) {
    P.phase = uid || null;
    if (!keepTime) {
      if (!P.phase) P.time = null;
      else {
        var i = P.phases.map(function (p) { return p.uid; }).indexOf(P.phase);
        P.time = i >= 0 ? LG.phaseStarts(P.phases, P.meta.timeline)[i] : null;
      }
    }
    renderAll();
    emit('time');
  }
  function setTime(t, silent) {
    P.time = t;
    if (t != null) P.phase = LG.phaseAtTime(P.phases, P.meta.timeline, t);
    renderAll();
    if (!silent) emit('time');
  }

  /* ---------- Synchronisation ---------- */
  function applyServer(j) {
    if (j.plan) {
      var keepTitle = P.plan && document.activeElement === $('#plan-title');
      P.plan = L.extend(P.plan || {}, j.plan);
      if (!P.metaDirty && j.plan.meta) P.meta = normMeta(j.plan.meta);
      if (!keepTitle) $('#plan-title').value = P.plan.title;
    }
    if (j.can_edit !== undefined) {
      P.canEdit = !!j.can_edit && !mobile && !P.readonlyLink;
      P.canAdmin = !!j.can_admin && !P.readonlyLink;
    }
    if (Array.isArray(j.elements)) {
      if (j.full) {
        var keep = {};
        Object.keys(P.pending).forEach(function (u) { if (P.els[u]) keep[u] = P.els[u]; });
        P.els = {};
        j.elements.forEach(function (e) { P.els[e.uid] = e; P.base[e.uid] = e.updated_at; });
        Object.keys(keep).forEach(function (u) { P.els[u] = keep[u]; });
      } else if (j.elements.length) {
        var changed = G.mergeRemote(P.els, j.elements, P.pending);
        j.elements.forEach(function (e) { if (!e.deleted) P.base[e.uid] = e.updated_at; else delete P.base[e.uid]; });
        if (changed.length) say(changed.length + ' changement(s) d’un autre membre.');
      }
    }
    var typingPhase = document.activeElement && document.activeElement.hasAttribute && document.activeElement.hasAttribute('data-ph');
    if (Array.isArray(j.phases) && !P.phasesDirty && !typingPhase) {
      P.phases = j.phases.map(function (p) { return { uid: p.uid, name: p.name, note: p.note, time_label: p.time_label }; });
      if (P.phase && !P.phases.some(function (p) { return p.uid === P.phase; })) P.phase = null;
    }
    if (Array.isArray(j.presence)) {
      P.presence = j.presence;
      emit('presence');
    }
    if (j.server_ms) P.serverMs = Math.max(P.serverMs, j.server_ms);
  }
  function normMeta(m) {
    m = m || {};
    if (!Array.isArray(m.layers) || !m.layers.length) m.layers = [{ uid: 'base', name: 'Plan', visible: true, locked: false, opacity: 1 }];
    m.notes = m.notes || {};
    m.legend = Array.isArray(m.legend) ? m.legend : [];
    m.side = m.side || 'WEST';
    m.timeline = LG.normTimeline(m.timeline);
    if (!m.layers.some(function (l) { return l.uid === P.layer; })) P.layer = m.layers[0].uid;
    return m;
  }
  function poll(full) {
    if (P.readonlyLink) return Promise.resolve();
    var since = full ? 0 : P.serverMs;
    return api('/atak/toc/api/plans/' + P.id + '?since=' + since + '&tool=' + encodeURIComponent(P.tool)).then(function (j) {
      if (!j || j.notModified) return;
      if (!j.ok) { if (j.error === 'not_found') { say(ERR.not_found, true); clearInterval(pollTimer); } return; }
      var wasEdit = P.canEdit;
      var hadChange = (Array.isArray(j.elements) && j.elements.length) || j.full || Array.isArray(j.phases);
      applyServer(j);
      if (wasEdit !== P.canEdit) applyRights();
      if (hadChange && !ptr) renderAll();
      setSaveState();
    }).catch(function () {});
  }
  function loadComments() {
    if (P.readonlyLink) return;
    api('/atak/toc/api/plans/' + P.id + '/comments').then(function (j) { if (j && j.ok) { P.comments = j.comments; emit('comments'); } });
  }

  /* ---------- Boîtes de dialogue : envoi en jeu, import, versions, partage ---------- */
  function openDialog(id) { var d = $('#' + id); d.hidden = false; var f = $('select, input, button:not([data-close])', d); if (f) f.focus(); }
  function closeDialogs() { $$('.toc-overlay').forEach(function (o) { o.hidden = true; }); }
  function openPublish() {
    $('#pub-side').value = P.meta.side || 'WEST';
    $('#pub-phase').innerHTML = '<option value="">Plan entier</option>' + P.phases.map(function (p, i) { return '<option value="' + p.uid + '"' + (P.phase === p.uid ? ' selected' : '') + '>' + (i + 1) + '. ' + esc(p.name) + '</option>'; }).join('');
    var all = list().filter(function (e) { return LG.visibleAt(e, null); });
    var degraded = all.filter(function (e) { return ['circle', 'ellipse', 'rect'].indexOf(e.geom) >= 0 || (KINDS[e.kind] && ['NOTE', 'ZONE', 'FREE'].indexOf(KINDS[e.kind].game) >= 0 && ['text', 'polygon', 'line', 'arrow'].indexOf(e.kind) < 0) || (e.kind === 'unit' && e.props.affil !== 'F' && e.props.affil !== 'H'); }).length;
    var moving = all.filter(function (e) { return e.props.track && e.props.track.length > 1; }).length;
    $('#pub-info').textContent = all.length + ' élément(s) à envoyer' + (degraded ? ' · ' + degraded + ' adapté(s) au jeu (formes en polygones, repères en notes, couleur la plus proche des 9 du jeu)' : '') + (moving ? ' · ' + moving + ' unité(s) en mouvement : une phase part avec leurs positions au début de cette phase' : '') + '.';
    openDialog('dlg-publish');
    $('#pub-go').focus();
  }
  function doPublish() {
    var btn = $('#pub-go');
    btn.disabled = true;
    flush();
    api('/atak/toc/api/plans/' + P.id + '/publish', { side: $('#pub-side').value, phase: $('#pub-phase').value || null }).then(function (j) {
      btn.disabled = false;
      if (j && j.ok) {
        closeDialogs();
        say(j.sent + ' élément(s) envoyé(s) dans le plan du camp ' + SIDES[$('#pub-side').value] + (j.skipped ? ' · ' + j.skipped + ' trop détaillé(s), écarté(s)' : '') + '. Ils apparaissent sur les téléphones et le PC du poste dès que le jeu les relève.');
      } else say(ERR[j && (j.reason || j.error)] || 'Envoi refusé.', true);
    }).catch(function () { btn.disabled = false; say('Réseau indisponible.', true); });
  }
  var importPreview = [];
  function openImport() {
    $('#imp-side').value = P.meta.side || 'WEST';
    openDialog('dlg-import');
    loadImportPreview();
  }
  function loadImportPreview() {
    $('#imp-info').textContent = 'Lecture du plan du camp…';
    $('#imp-go').disabled = true;
    api('/atak/toc/api/plans/' + P.id + '/game-import?side=' + $('#imp-side').value).then(function (j) {
      importPreview = (j && j.ok && j.elements) || [];
      $('#imp-info').textContent = importPreview.length ? importPreview.length + ' élément(s) dessiné(s) en jeu' + (j.updated_at ? ' (dernier envoi des téléphones : ' + String(j.updated_at).slice(11, 16) + ')' : '') + '. Ils seront ajoutés dans un calque « Jeu ».' : 'Le plan du camp est vide ou n’a pas encore été envoyé par un téléphone relié à Athena (moins de 12 h).';
      $('#imp-go').disabled = !importPreview.length || !P.canEdit;
    });
  }
  function doImport() {
    var uid = 'jeu_' + $('#imp-side').value.toLowerCase();
    if (!layerOf(uid)) { P.meta.layers.push({ uid: uid, name: 'Jeu ' + SIDES[$('#imp-side').value], visible: true, locked: false, opacity: 1 }); metaChanged(); }
    var m = {}, z = maxZ();
    importPreview.forEach(function (el) { var c = G.clone(el); c.layer = uid; c.z = ++z; c.phases = []; delete c.updated_at; m[c.uid] = c; });
    commit(m, importPreview.length + ' élément(s) du jeu ajoutés (Ctrl+Z pour annuler). Un nouvel import met à jour les mêmes éléments.');
    closeDialogs();
  }
  function addFromGameLayer(conv) {
    if (!P.canEdit) { say('Lecture seule.', true); return; }
    var el = G.clone(conv);
    var def = KINDS[el.kind];
    if (!def || def.geoms.indexOf(el.geom) < 0) { el.kind = el.geom === 'point' ? 'icon' : (el.geom === 'line' ? 'line' : 'polygon'); if (el.geom === 'circle') el.kind = 'circle'; if (el.geom === 'ellipse') el.kind = 'ellipse'; }
    var full = newElement(el.kind, el.geom, el.pts, el.props || {});
    if (full.kind === 'icon' && !full.props.icon) full.props.icon = 'flag';
    addNew(full, true);
  }
  function openVersions() {
    openDialog('dlg-versions');
    $('#ver-list').innerHTML = '<p class="toc-note">Chargement…</p>';
    api('/atak/toc/api/plans/' + P.id + '/versions').then(function (j) {
      var vs = (j && j.versions) || [];
      $('#ver-list').innerHTML = vs.length ? '<table class="toc-table"><tbody>' + vs.map(function (v) {
        return '<tr><td class="mono" style="width:150px">' + esc(String(v.created_at).slice(0, 16)) + '</td><td>' + esc(v.label || 'Version') + ' <span class="toc-muted">· rév. ' + v.rev + '</span></td><td class="num" style="width:120px">' + (P.canEdit ? '<button type="button" class="toc-btn is-small" data-restore="' + v.id + '">Restaurer</button>' : '') + '</td></tr>';
      }).join('') + '</tbody></table>' : '<div class="toc-empty"><p>Aucune version enregistrée : une version est gardée à chaque envoi en jeu, avant une restauration, ou quand vous l’enregistrez.</p></div>';
    });
  }
  function saveVersion() {
    var label = $('#ver-label').value.trim();
    flush();
    api('/atak/toc/api/plans/' + P.id + '/versions', { label: label }).then(function (j) {
      if (j && j.ok) { $('#ver-label').value = ''; say('Version enregistrée.'); openVersions(); } else say(ERR[j && j.error] || 'Version refusée.', true);
    });
  }
  function restore(vid) {
    if (!window.confirm('Remettre le plan dans cet état ? L’état actuel est gardé dans une version.')) return;
    api('/atak/toc/api/plans/' + P.id + '/versions/' + vid + '/restore', {}).then(function (j) {
      if (j && j.ok) { closeDialogs(); P.pending = {}; P.deletes = {}; P.hist = new G.History(250); poll(true); say('Plan restauré.'); } else say(ERR[j && j.error] || 'Restauration refusée.', true);
    });
  }
  function openShare() {
    openDialog('dlg-share');
    $('#sh-sharing').value = P.plan.sharing || 'private';
    $('#sh-lock').checked = !!P.plan.locked;
    $('#sh-link').value = P.plan.share_token ? BOOT.base + '/atak/toc/plans/lien/' + P.plan.share_token : '';
    $('#sh-template').hidden = !BOOT.canManage;
  }
  function saveShare() {
    saveMeta({ sharing: $('#sh-sharing').value, locked: $('#sh-lock').checked }).then(function (j) {
      if (j && j.ok) { P.plan.sharing = $('#sh-sharing').value; P.plan.locked = $('#sh-lock').checked; say('Partage enregistré.'); closeDialogs(); }
    });
  }
  function shareLink(create) {
    api('/atak/toc/api/plans/' + P.id + '/share-link', { create: create }).then(function (j) {
      if (j && j.ok) { P.plan.share_token = j.token; $('#sh-link').value = j.url || ''; if (j.url) { $('#sh-link').select(); say('Lien de lecture créé : membres de la communauté seulement.'); } else say('Lien retiré.'); }
      else say(ERR[j && j.error] || 'Refusé.', true);
    });
  }
  function asTemplate() {
    api('/atak/toc/api/plans/' + P.id + '/duplicate', { as_template: true, title: P.plan.title }).then(function (j) {
      if (j && j.ok) say('Modèle créé dans la communauté.'); else say(ERR[j && j.error] || 'Refusé.', true);
    });
  }

  /* ---------- Export image ---------- */
  /** Trace les éléments affichés sur un canvas. project([x, y]) => pixels ; mpp : mètres par pixel du canvas. */
  function drawPlan(ctx, project, mpp, o) {
    o = o || {};
    var pointsLater = [];
    P.shown.forEach(function (d) {
      if (P.phase && !G.inPhase(d, P.phase) && !o.withGhosts) return;
      var lay = layerOf(d.layer);
      var op = lay && lay.opacity != null ? lay.opacity : 1;
      if (d.geom === 'point' && d.kind !== 'fan') { pointsLater.push([d, op]); return; }
      R.drawCanvas(ctx, project, d, { mpp: mpp, catalog: CAT, scale: o.scale || 1, opacity: op });
    });
    if (o.noPoints) return;
    pointsLater.forEach(function (it) {
      var pc = R.pointCanvas(it[0], { catalog: CAT, opacity: it[1] });
      var s = project(it[0].pts[0]);
      ctx.drawImage(pc.canvas, s[0] - pc.anchorX, s[1] - pc.anchorY);
    });
  }
  /** Image de la carte telle qu'affichée : tuiles + éléments (canvas). Fond perdu si une tuile refuse le partage (CORS). */
  function renderCanvas() {
    var mapEl = tm.map.getContainer();
    var rect = mapEl.getBoundingClientRect();
    var scale = 2;
    var cv = document.createElement('canvas');
    cv.width = Math.round(rect.width * scale);
    cv.height = Math.round(rect.height * scale);
    var ctx = cv.getContext('2d');
    ctx.scale(scale, scale);
    ctx.fillStyle = '#070a0d';
    ctx.fillRect(0, 0, rect.width, rect.height);
    var bgOk = true;
    $$('.leaflet-tile-pane img, .toc-photo-tile', mapEl).forEach(function (img) {
      if (!img.complete || !img.naturalWidth || getComputedStyle(img).opacity === '0' || getComputedStyle(img.parentNode).opacity === '0') return;
      var r = img.getBoundingClientRect();
      try { ctx.drawImage(img, r.left - rect.left, r.top - rect.top, r.width, r.height); } catch (e) { bgOk = false; }
    });
    ctx.fillStyle = 'rgba(7,10,13,.25)';
    ctx.fillRect(0, 0, rect.width, rect.height);
    drawPlan(ctx, px, tm.metersPerPixel(), {});
    ctx.setLineDash([]);
    ctx.fillStyle = 'rgba(7,10,13,.85)'; ctx.fillRect(0, rect.height - 34, rect.width, 34);
    ctx.fillStyle = '#e8eef2'; ctx.font = '700 15px "Roboto Condensed", Arial'; ctx.textBaseline = 'alphabetic'; ctx.textAlign = 'left';
    var ph = P.phases.filter(function (x) { return x.uid === P.phase; })[0];
    ctx.fillText((P.plan.title + (ph ? ' · ' + ph.name : '') + (P.time != null ? ' · ' + LG.fmtH(P.time) : '')).toUpperCase(), 12, rect.height - 12);
    ctx.textAlign = 'right';
    ctx.font = '400 13px "JetBrains Mono", monospace';
    var center = tm.toWorld(tm.map.getCenter());
    ctx.fillText('CENTRE ' + G.grid(center[0], center[1], 8) + ' · ATHENA', rect.width - 12, rect.height - 12);
    return { canvas: cv, bgOk: bgOk };
  }
  function canvasUrl(type, quality) {
    var r = renderCanvas();
    try { return { url: r.canvas.toDataURL(type, quality), bgOk: r.bgOk, canvas: r.canvas }; } catch (e) { return { url: null, bgOk: false }; }
  }
  function download(href, name) {
    var a = document.createElement('a');
    a.href = href; a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
  }
  function exportPng() {
    var r = canvasUrl('image/png');
    if (!r.url) { say('Export de l’image impossible : le fond de carte refuse la copie. Choisissez le fond « Sombre » puis recommencez.', true); return; }
    download(r.url, 'plan-' + P.id + (P.phase ? '-phase' : '') + '.png');
    say('Image exportée.');
  }
  function copyImage() {
    var r = canvasUrl('image/png');
    if (!r.url || !navigator.clipboard || !window.ClipboardItem) { say('Copie de l’image impossible dans ce navigateur : utilisez Exporter › Image PNG.', true); return; }
    r.canvas.toBlob(function (blob) {
      navigator.clipboard.write([new window.ClipboardItem({ 'image/png': blob })]).then(function () { say('Image de la vue copiée.'); }, function () { say('Copie de l’image refusée par le navigateur.', true); });
    });
  }
  function exportPdf() {
    flush();
    var r = canvasUrl('image/jpeg', 0.86);
    var fd = new FormData();
    fd.append('_csrf_token', BOOT.csrf || '');
    if (r.url) fd.append('image', r.url);
    say('Fiche de briefing en préparation…');
    fetch(BOOT.base + '/atak/toc/plans/' + P.id + '/export.pdf', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': BOOT.csrf || '' }, body: fd }).then(function (resp) {
      if (!resp.ok) throw new Error('pdf');
      return resp.blob();
    }).then(function (blob) {
      var href = URL.createObjectURL(blob);
      download(href, 'plan-' + P.id + '.pdf');
      setTimeout(function () { URL.revokeObjectURL(href); }, 5000);
      say(r.url ? 'Fiche PDF exportée.' : 'Fiche PDF exportée (tracé sans fond de carte).');
    }).catch(function () { say('Fiche PDF indisponible pour le moment.', true); });
  }

  /* ---------- Droits ---------- */
  function applyRights() {
    $$('[data-needs-edit]').forEach(function (b) { b.hidden = !P.canEdit; });
    $$('[data-needs-admin]').forEach(function (b) { b.hidden = !P.canAdmin; });
    $$('[data-needs-api]').forEach(function (b) { b.hidden = P.readonlyLink; });
    $('#plan-title').disabled = !P.canEdit;
    $('#plan-ro').hidden = P.canEdit;
    $('#plan-ro').textContent = mobile ? 'Lecture seule sur téléphone : l’éditeur s’ouvre sur tablette ou ordinateur.' : (P.readonlyLink ? 'Lien de lecture : plan en lecture seule.' : 'Lecture seule : ce plan n’est pas modifiable par vous.');
    document.body.classList.toggle('is-readonly', !P.canEdit);
    emit('rights');
  }

  /* ---------- Actions (barre, palette de commandes, menus, clavier) ---------- */
  var ACTIONS = {};
  function action(id, label, fn, o) { ACTIONS[id] = L.extend({ id: id, label: label, run: fn }, o || {}); }
  function run(id, arg) {
    var a = ACTIONS[id];
    if (!a) return false;
    if (a.edit && !P.canEdit) { say('Lecture seule.', true); return false; }
    a.run(arg);
    return true;
  }
  function nudge(dx, dy) { editSelected(function (c) { return moveEl(c, dx, dy); }); }
  function fitPlan() {
    var all = [];
    P.shown.forEach(function (el) { all = all.concat(LG.outlineOf(el, 8)); });
    if (all.length) tm.fitWorld(G.bbox(all)); else say('Le plan est vide.');
  }
  function nextBackground() {
    var bgs = tm.backgrounds, i = bgs.indexOf(tm.currentBackground());
    var nx = bgs[(i + 1) % bgs.length];
    setBackground(nx.id);
  }
  function setBackground(id) {
    tm.setBackground(id);
    $('#plan-bg').value = id;
    try { window.localStorage.setItem('athena:toc:bg', id); } catch (e) {}
    emit('background');
  }
  function registerActions() {
    var tool = function (id, label, key) { action('tool.' + id, 'Outil : ' + label, function () { setTool(id); }, { key: key, edit: VIEW_TOOLS.indexOf(id) < 0, group: 'Outils' }); };
    tool('select', 'sélection', 'V'); tool('lasso', 'lasso', 'Q'); tool('pan', 'main (déplacer la carte)', 'H');
    tool('unit', 'unité', 'U'); tool('text', 'texte', 'T'); tool('icon', 'repère', 'I'); tool('line', 'trait', 'L'); tool('arrow', 'flèche', 'A');
    tool('polygon', 'polygone', 'P'); tool('rect', 'rectangle', 'R'); tool('ellipse', 'ellipse', 'E'); tool('circle', 'cercle', 'C');
    action('undo', 'Annuler', undo, { key: 'Ctrl+Z', edit: true, group: 'Édition' });
    action('redo', 'Rétablir', redo, { key: 'Ctrl+Y', edit: true, group: 'Édition' });
    action('copy', 'Copier', function () { var t = copySelected(false); if (t && navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).catch(function () {}); }, { key: 'Ctrl+C', group: 'Édition' });
    action('cut', 'Couper', function () { var t = copySelected(true); if (t && navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).catch(function () {}); }, { key: 'Ctrl+X', edit: true, group: 'Édition' });
    action('paste', 'Coller sous le curseur', function () { pasteFromSystem(false); }, { key: 'Ctrl+V', edit: true, group: 'Édition' });
    action('pasteInPlace', 'Coller à la même place', function () { pasteFromSystem(true); }, { key: 'Ctrl+Maj+V', edit: true, group: 'Édition' });
    action('duplicate', 'Dupliquer', function () { duplicateSelected(); }, { key: 'Ctrl+D', edit: true, group: 'Édition' });
    action('delete', 'Supprimer la sélection', deleteSelected, { key: 'Suppr', edit: true, group: 'Édition' });
    action('copyStyle', 'Copier le style', copyStyle, { key: 'Ctrl+Alt+C', group: 'Édition' });
    action('pasteStyle', 'Coller le style', pasteStyle, { key: 'Ctrl+Alt+V', edit: true, group: 'Édition' });
    action('copyImage', 'Copier l’image de la vue', copyImage, { group: 'Édition' });
    action('selectAll', 'Tout choisir', function () { selectWhere(function () { return true; }); }, { key: 'Ctrl+A', group: 'Sélection' });
    action('selectNone', 'Ne rien choisir', function () { setSel([]); }, { key: 'Échap', group: 'Sélection' });
    action('selectLayer', 'Choisir tout le calque de la sélection', function () { var el = selected()[0]; var lay = el ? el.layer : P.layer; selectWhere(function (e) { return e.layer === lay; }, 'même calque'); }, { group: 'Sélection' });
    action('selectPhase', 'Choisir tout ce qui est dans la phase affichée', function () { if (!P.phase) { say('Aucune phase affichée.', true); return; } selectWhere(function (e) { return (e.phases || []).indexOf(P.phase) >= 0; }, 'phase affichée'); }, { group: 'Sélection' });
    action('selectKind', 'Choisir tous les éléments du même type', function () { var el = selected()[0]; if (!el) { say('Choisissez d’abord un élément.', true); return; } selectWhere(function (e) { return e.kind === el.kind && (el.kind !== 'unit' || e.props.affil === el.props.affil); }, 'même type'); }, { group: 'Sélection' });
    action('group', 'Grouper', function () { groupSelected(true); }, { key: 'Ctrl+G', edit: true, group: 'Disposition' });
    action('ungroup', 'Dégrouper', function () { groupSelected(false); }, { key: 'Ctrl+Maj+G', edit: true, group: 'Disposition' });
    action('front', 'Mettre au premier plan', function () { zOrder(true); }, { key: 'Ctrl+Maj+]', edit: true, group: 'Disposition' });
    action('back', 'Mettre en arrière-plan', function () { zOrder(false); }, { key: 'Ctrl+Maj+[', edit: true, group: 'Disposition' });
    action('forward', 'Avancer d’un cran', function () { zOrder(true, true); }, { key: 'Ctrl+]', edit: true, group: 'Disposition' });
    action('backward', 'Reculer d’un cran', function () { zOrder(false, true); }, { key: 'Ctrl+[', edit: true, group: 'Disposition' });
    [['left', 'Aligner à gauche'], ['hcenter', 'Centrer horizontalement'], ['right', 'Aligner à droite'], ['top', 'Aligner en haut'], ['vcenter', 'Centrer verticalement'], ['bottom', 'Aligner en bas'], ['dh', 'Répartir horizontalement'], ['dv', 'Répartir verticalement']].forEach(function (a) {
      action('align.' + a[0], a[1], function () { alignSelected(a[0]); }, { edit: true, group: 'Disposition' });
    });
    action('lock', 'Verrouiller ou déverrouiller', toggleLock, { key: 'K', edit: true, group: 'Disposition' });
    action('hide', 'Masquer la sélection', function () { toggleHidden(P.sel, true); setSel([]); }, { key: 'Ctrl+H', edit: true, group: 'Disposition' });
    action('showAll', 'Afficher les éléments masqués', function () { toggleHidden(list().filter(function (e) { return e.props.hidden; }).map(function (e) { return e.uid; }), false); }, { edit: true, group: 'Disposition' });
    action('rotateLeft', 'Pivoter de −15°', function () { var b = selBox(); editSelected(function (c) { return rotateEl(c, -15, b && P.sel.length > 1 ? [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2] : undefined); }); }, { key: '[', edit: true, group: 'Disposition' });
    action('rotateRight', 'Pivoter de +15°', function () { var b = selBox(); editSelected(function (c) { return rotateEl(c, 15, b && P.sel.length > 1 ? [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2] : undefined); }); }, { key: ']', edit: true, group: 'Disposition' });
    action('scaleUp', 'Agrandir', function () { editSelected(function (c) { return G.scale(c, 1.15); }); }, { key: '+', edit: true, group: 'Disposition' });
    action('scaleDown', 'Réduire', function () { editSelected(function (c) { return G.scale(c, 1 / 1.15); }); }, { key: '−', edit: true, group: 'Disposition' });
    action('snap', 'Aimantation aux sommets', function () { P.snap = !P.snap; say(P.snap ? 'Aimantation aux sommets activée' : 'Aimantation aux sommets coupée'); emit('tool'); }, { key: 'S', group: 'Carte' });
    action('snapGrid', 'Aimantation à la grille de 10 m', function () { P.snapGrid = !P.snapGrid; say(P.snapGrid ? 'Aimantation à la grille de 10 m activée' : 'Aimantation à la grille coupée'); emit('tool'); }, { key: 'Maj+S', group: 'Carte' });
    action('grid', 'Quadrillage', function () { P.grid = !P.grid; tm.setGrid(P.grid); emit('tool'); }, { key: 'G', group: 'Carte' });
    action('background', 'Fond de carte suivant', nextBackground, { key: 'B', group: 'Carte' });
    action('zoomIn', 'Rapprocher', function () { tm.map.zoomIn(); }, { group: 'Carte' });
    action('zoomOut', 'Éloigner', function () { tm.map.zoomOut(); }, { group: 'Carte' });
    action('fit', 'Cadrer tout le plan', fitPlan, { key: 'F', group: 'Carte' });
    action('fitSel', 'Cadrer la sélection', function () { var b = selBox(); if (b) tm.fitWorld(b); }, { key: 'Maj+F', group: 'Carte' });
    action('publish', 'Envoyer en jeu', openPublish, { edit: true, group: 'Plan' });
    action('importGame', 'Importer le plan dessiné en jeu', openImport, { edit: true, group: 'Plan' });
    action('importFile', 'Importer un fichier (JSON, GeoJSON, KML)', function () { $('#plan-file').click(); }, { edit: true, group: 'Plan' });
    action('versions', 'Versions du plan', openVersions, { group: 'Plan' });
    action('share', 'Partage du plan', openShare, { group: 'Plan' });
    action('exportPng', 'Exporter l’image PNG de la vue', exportPng, { group: 'Plan' });
    action('exportPdf', 'Exporter la fiche de briefing PDF', exportPdf, { group: 'Plan' });
    ['json', 'geojson', 'kml'].forEach(function (f) { action('export.' + f, 'Exporter en ' + f.toUpperCase(), function () { flush(); window.location.href = BOOT.base + '/atak/toc/plans/' + P.id + '/export.' + f; }, { group: 'Plan' }); });
    action('save', 'Enregistrer tout de suite', flush, { key: 'Ctrl+S', edit: true, group: 'Plan' });
    action('help', 'Raccourcis clavier', function () { $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); }, { key: '?', group: 'Aide' });
  }
  function pasteFromSystem(inPlace) {
    if (navigator.clipboard && navigator.clipboard.readText) navigator.clipboard.readText().then(function (t) { pasteText(t, inPlace); }, function () { pasteText('', inPlace); });
    else pasteText('', inPlace);
  }

  /* ---------- Clavier ---------- */
  var KEY_TOOLS = { v: 'select', q: 'lasso', h: 'pan', u: 'unit', t: 'text', i: 'icon', l: 'line', a: 'arrow', p: 'polygon', r: 'rect', e: 'ellipse', c: 'circle' };
  function typingIn(t) { return t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable); }
  function onKey(e) {
    var typing = typingIn(e.target);
    var k = e.key;
    var mod = e.ctrlKey || e.metaKey;
    if (mod && (k === 'k' || k === 'K')) { e.preventDefault(); emit('palette'); return; }
    if (k === 'Escape') {
      if (E.escape && E.escape()) return;
      if ($$('.toc-overlay:not([hidden])').length) { closeDialogs(); return; }
      if (P.briefing) { emit('briefing', false); return; }
      if (typing) { e.target.blur(); return; }
      if (P.draft) { cancelDraft(); return; }
      if (toolHandlers[P.tool] && toolHandlers[P.tool].cancel && toolHandlers[P.tool].cancel()) return;
      if (P.tool !== 'select') { setTool('select'); return; }
      setSel([]);
      return;
    }
    if (typing) return;
    if (k === ' ' && !P.briefing) { if (!P.space) { P.space = true; tm.map.getContainer().style.cursor = 'grab'; } e.preventDefault(); return; }
    if (k === '?') { e.preventDefault(); run('help'); return; }
    if (E.keyHook && E.keyHook(e)) return;
    if (P.briefing) return;
    var low = k.length === 1 ? k.toLowerCase() : k;
    if (mod) {
      var map = { z: e.shiftKey ? 'redo' : 'undo', y: 'redo', d: 'duplicate', a: 'selectAll', g: e.shiftKey ? 'ungroup' : 'group', s: 'save', h: 'hide', ']': e.shiftKey ? 'front' : 'forward', '[': e.shiftKey ? 'back' : 'backward', '}': 'front', '{': 'back' };
      if (e.altKey && low === 'c') { e.preventDefault(); run('copyStyle'); return; }
      if (e.altKey && low === 'v') { e.preventDefault(); run('pasteStyle'); return; }
      // Copier, couper, coller : événements du presse-papiers (plus bas), pour lire et écrire le presse-papiers du système.
      if (map[low]) { e.preventDefault(); run(map[low]); }
      return;
    }
    if (k === 'Delete' || (k === 'Backspace' && !P.draft)) { e.preventDefault(); run('delete'); return; }
    if (k === 'Backspace' && P.draft) { e.preventDefault(); P.draft.pts.pop(); if (!P.draft.pts.length) cancelDraft(); else drawDraft(null); return; }
    if (k === 'Enter' && P.draft) { e.preventDefault(); finishDraft(); return; }
    if (k === '[') { run('rotateLeft'); return; }
    if (k === ']') { run('rotateRight'); return; }
    if (k === '+' || k === '=') { run('scaleUp'); return; }
    if (k === '-') { run('scaleDown'); return; }
    if (low === 'k') { run('lock'); return; }
    if (low === 's') { run(e.shiftKey ? 'snapGrid' : 'snap'); return; }
    if (low === 'g') { run('grid'); return; }
    if (low === 'b') { run('background'); return; }
    if (low === 'f') { run(e.shiftKey ? 'fitSel' : 'fit'); return; }
    if (k.indexOf('Arrow') === 0 && P.sel.length) {
      e.preventDefault();
      var step = e.shiftKey ? 100 : (e.altKey ? 1 : 10);
      nudge(k === 'ArrowLeft' ? -step : (k === 'ArrowRight' ? step : 0), k === 'ArrowDown' ? -step : (k === 'ArrowUp' ? step : 0));
      return;
    }
    var tool = KEY_TOOLS[low];
    if (tool && !e.altKey) run('tool.' + tool);
  }
  function onKeyUp(e) {
    if (e.key === ' ' && P.space) { P.space = false; if (tm) tm.map.getContainer().style.cursor = P.tool === 'pan' ? 'grab' : (P.tool === 'select' ? '' : 'crosshair'); }
    if (e.key === 'Shift') P.shift = false;
  }
  function onClipboard(e) {
    if (typingIn(e.target) || !P.loaded || P.briefing) return;
    if (window.getSelection && String(window.getSelection()) && e.type !== 'paste') return;
    if (e.type === 'paste') {
      if (!P.canEdit) return;
      e.preventDefault();
      pasteText(e.clipboardData ? e.clipboardData.getData('text') : '', !!P.shift);
      return;
    }
    if (!P.sel.length) return;
    if (e.type === 'cut' && !P.canEdit) return;
    var text = copySelected(e.type === 'cut');
    if (text && e.clipboardData) { e.clipboardData.setData('text/plain', text); e.preventDefault(); }
  }

  /* ---------- Démarrage ---------- */
  function terrainFor(mapId) {
    var t = (BOOT.terrains || []).filter(function (x) { return x.id === mapId; })[0];
    return t || (BOOT.terrains || [])[0] || null;
  }
  function start(j) {
    applyServer(j);
    P.loaded = true;
    var t = terrainFor(P.plan.map_id);
    if (!t) { $('#plan-loading').textContent = 'Aucun terrain configuré sur le site : la carte ne peut pas s’afficher.'; return; }
    P.terrain = t;
    $('#plan-terrain').textContent = t.label;
    tm = window.TocMap.create($('#plan-map'), t, {
      onCursor: function (p) {
        $('#plan-cursor').textContent = G.grid(p[0], p[1], 8);
        emit('cursor', p);
      },
      onBackground: function (bg) { $('#plan-bg-note').textContent = bg.note; }
    });
    E.tm = tm;
    tm.map.boxZoom.disable();
    tm.map.doubleClickZoom.disable();
    tm.map.createPane('planPane').style.zIndex = 430;
    tm.map.createPane('planMarkers').style.zIndex = 640;
    tm.map.createPane('planHandles').style.zIndex = 660;
    tm.map.getPane('planPane').style.pointerEvents = 'none';
    tm.map.getPane('planMarkers').style.pointerEvents = 'none';
    tm.map.getPane('planHandles').style.pointerEvents = 'none';
    planLayer = L.layerGroup().addTo(tm.map);
    draftLayer = L.layerGroup().addTo(tm.map);
    toolLayer = L.layerGroup().addTo(tm.map);
    E.toolLayer = toolLayer;
    overlay = $('#plan-overlay');
    var bg = 'sat';
    try { bg = window.localStorage.getItem('athena:toc:bg') || 'sat'; } catch (e) {}
    $('#plan-bg').innerHTML = tm.backgrounds.map(function (b) { return '<option value="' + b.id + '">' + esc(b.label) + '</option>'; }).join('');
    $('#plan-bg').value = bg;
    tm.setBackground(bg);
    layers = window.TocLayers.create(tm, { base: BOOT.base, mapId: P.plan.map_id, side: P.meta.side || 'WEST', panel: $('#plan-game-layers'), mode: 'editor', catalog: CAT, onAddToPlan: addFromGameLayer, fetchPlan: function () { return Promise.resolve(null); } });
    E.layers = layers;
    var v = P.meta.view || {};
    var all = [];
    list().forEach(function (el) { if (LG.visibleAt(el, null)) all = all.concat(LG.outlineOf(el, 8)); });
    if (v.x != null && v.y != null) tm.centerOn([v.x, v.y], v.z != null ? v.z : undefined);
    else if (all.length) tm.fitWorld(G.bbox(all));
    var mapEl = tm.map.getContainer();
    mapEl.addEventListener('pointerdown', onDown);
    overlay.addEventListener('pointerdown', onDown);
    mapEl.addEventListener('pointermove', onHover);
    mapEl.addEventListener('contextmenu', function (ev) {
      ev.preventDefault();
      var w = worldOf(ev);
      var s = stackAt(w);
      if (s.length && P.sel.indexOf(s[0]) < 0) select([s[0]]);
      emit('context', { x: ev.clientX, y: ev.clientY, world: w, uid: s[0] || null });
    });
    tm.map.on('move zoom', renderOverlay);
    tm.map.on('zoomanim', function () { overlay.style.visibility = 'hidden'; });
    tm.map.on('zoomend', function () { overlay.style.visibility = ''; renderAll(); });
    tm.map.on('moveend', function () {
      emit('view');
      if (!P.canEdit) return;
      clearTimeout(P.viewTimer);
      P.viewTimer = setTimeout(function () { var c = tm.toWorld(tm.map.getCenter()); P.meta.view = { x: Math.round(c[0]), y: Math.round(c[1]), z: Math.round(tm.map.getZoom()) }; metaChanged(); }, 4000);
    });
    applyRights();
    renderAll();
    setTool('select');
    setSaveState();
    $('#plan-loading').hidden = true;
    document.title = P.plan.title + ' · Athena';
    emit('loaded');
    if (!P.readonlyLink) {
      pollTimer = setInterval(function () { if (!document.hidden) poll(false); }, 2500);
      loadComments();
      setInterval(function () { if (!document.hidden) loadComments(); }, 20000);
    }
  }

  function init() {
    registerActions();
    document.addEventListener('keydown', function (e) { if (e.key === 'Shift') P.shift = true; onKey(e); });
    document.addEventListener('keyup', onKeyUp);
    window.addEventListener('blur', function () { P.space = false; P.shift = false; });
    ['copy', 'cut', 'paste'].forEach(function (n) { document.addEventListener(n, onClipboard); });
    $('#plan-title').addEventListener('change', function () { var v = this.value.trim(); if (v) saveMeta({ title: v }).then(function () { P.plan.title = v; document.title = v + ' · Athena'; }); });
    $('#plan-bg').addEventListener('change', function () { setBackground(this.value); });
    $('#plan-file').addEventListener('change', function () { importFile(this.files[0]); this.value = ''; });
    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target : null;
      if (!t) return;
      var b = t.closest('[data-run]');
      if (b && !b.disabled) {
        var dm = b.closest('details');
        if (dm) dm.open = false;
        run(b.getAttribute('data-run'), b.getAttribute('data-arg'));
      }
      var tb = t.closest('[data-tool]');
      if (tb && !tb.disabled) setTool(tb.getAttribute('data-tool'));
      if (t.closest('[data-close]') || t.id === 'toc-help-close') closeDialogs();
      if (t.closest('#pub-go')) doPublish();
      if (t.closest('#imp-go')) doImport();
      if (t.closest('#ver-save')) saveVersion();
      var rs = t.closest('[data-restore]');
      if (rs) restore(Number(rs.getAttribute('data-restore')));
      if (t.closest('#sh-save')) saveShare();
      if (t.closest('#sh-create')) shareLink(true);
      if (t.closest('#sh-revoke')) shareLink(false);
      if (t.closest('#sh-template')) asTemplate();
      // Un menu déroulant se referme quand on clique ailleurs.
      $$('details.toc-menu[open]').forEach(function (d) { if (!d.contains(t)) d.open = false; });
    });
    $('#imp-side').addEventListener('change', loadImportPreview);
    window.addEventListener('beforeunload', function (e) {
      if (Object.keys(P.pending).length || Object.keys(P.deletes).length) { flush(); e.preventDefault(); e.returnValue = ''; }
    });
    emit('init');
    if (P.readonlyLink && BOOT.sharedDoc) {
      var d = BOOT.sharedDoc;
      start({ plan: { id: P.id, title: d.title, map_id: d.map_id, rev: d.rev, meta: d.meta, sharing: 'community', world: d.world || '' }, can_edit: false, can_admin: false, elements: d.elements, phases: d.phases, full: true, presence: [] });
      return;
    }
    api('/atak/toc/api/plans/' + P.id).then(function (j) {
      if (!j || !j.ok) { $('#plan-loading').textContent = ERR[j && j.error] || 'Plan indisponible pour le moment.'; return; }
      start(j);
    }).catch(function () { $('#plan-loading').textContent = 'Réseau indisponible : rechargez la page.'; });
  }

  E = {
    P: P, BOOT: BOOT, G: G, R: R, LG: LG, CAT: CAT, KINDS: KINDS, SIDES: SIDES, ERR: ERR, AFFIL_COLOR: AFFIL_COLOR, ACTIONS: ACTIONS, $: $, $$: $$, esc: esc,
    on: on, emit: emit, api: api, say: say, list: list, layerOf: layerOf, visible: visible, editable: editable, selected: selected, shownOf: shownOf,
    commit: commit, flush: flush, select: select, setSel: setSel, editSelected: editSelected, deleteSelected: deleteSelected, duplicateSelected: duplicateSelected,
    newElement: newElement, addNew: addNew, placePoint: placePoint, placeShape: placeShape, setTool: setTool, toolHandlers: toolHandlers, snapPoint: snapPoint,
    renderAll: renderAll, renderOverlay: renderOverlay, preview: preview, clearPreview: function () { if (draftLayer) draftLayer.clearLayers(); },
    metaChanged: metaChanged, phasesChanged: phasesChanged, saveMeta: saveMeta, setPhase: setPhase, setTime: setTime, moveEl: moveEl, translateEl: translateEl,
    toggleHidden: toggleHidden, action: action, run: run, drawPlan: drawPlan, maxZ: maxZ, activeLayer: activeLayer, pasteOpts: pasteOpts, addCopies: addCopies,
    importFile: importFile, importText: importText, worldOf: function (ev) { return worldOf(ev); }, px: function (p) { return px(p); }, setBackground: setBackground,
    mapClick: function (w, ev) { onMapClick(w, ev || {}, false); }, rotateEl: rotateEl, closeDialogs: closeDialogs, openDialog: openDialog, setSaveState: setSaveState, stackAt: stackAt, selBox: selBox, typingIn: typingIn
  };
  // Démarrage une fois tous les scripts différés chargés (panneaux, frise, relief) : jamais avant DOMContentLoaded.
  var started = false;
  function boot() { if (started) return; started = true; init(); }
  if (document.readyState === 'complete') setTimeout(boot, 0);
  else { document.addEventListener('DOMContentLoaded', boot); window.addEventListener('load', boot); }
  return E;
})();
