/* Éditeur de plans d'opération (/atak/toc/plans/{id}) : dessin sur le terrain Arma (symboles OTAN, graphismes
 * tactiques, traits, zones, textes), phases, calques du plan, ordre d'opération, commentaires, versions, plusieurs
 * éditeurs à la fois (le dernier enregistré gagne, élément par élément), export PNG / PDF / JSON, envoi en jeu et
 * import depuis le jeu. Tablette et bureau ; téléphone en lecture seule. */
(function () {
  'use strict';
  var BOOT = window.TOC_BOOT || {};
  var G = window.TocGeo;
  var R = window.TocPlanRender;
  var esc = R.esc;
  var CAT = BOOT.catalog || { kinds: {} };
  var KINDS = CAT.kinds || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var SIDES = { WEST: 'BLUFOR', EAST: 'OPFOR', GUER: 'INDFOR', CIV: 'CIVILS' };
  var ERR = {
    csrf: 'Session expirée : rechargez la page.', rate_limited: 'Trop de changements d’affilée : envoi repris dans un instant.', read_only: 'Ce plan est en lecture seule pour vous.',
    not_found: 'Plan introuvable.', schema_not_ready: 'Les plans ne sont pas encore installés sur le site.', nothing_to_send: 'Rien à envoyer : plan ou phase vide.',
    too_many_for_game: 'Plus de 300 éléments : envoyez phase par phase.', queue_unavailable: 'File des commandes du jeu indisponible.', side_invalid: 'Camp inconnu.',
    forbidden: 'Action réservée à l’auteur du plan ou à la configuration du poste.', no_unit: 'Vous n’êtes rattaché à aucune unité.'
  };

  var P = {
    id: Number(BOOT.planId) || 0, plan: null, canEdit: false, canAdmin: false, els: {}, phases: [], meta: { layers: [], notes: {}, legend: [] },
    serverMs: 0, pending: {}, deletes: {}, base: {}, sel: [], tool: 'select', phase: null, briefing: false, layer: 'base',
    hist: new G.History(250), presence: [], comments: [], snap: true, draft: null, measure: null, metaDirty: false, phasesDirty: false,
    readonlyLink: !!BOOT.readOnlyLink, saving: false, saveError: '', loaded: false, sideTab: 'element'
  };
  var tm = null, layers = null, planLayer = null, handleLayer = null, draftLayer = null, measureLayer = null;
  var flushTimer = null, metaTimer = null, phaseTimer = null, pollTimer = null;
  var mobile = window.matchMedia && window.matchMedia('(max-width: 640px)').matches;

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
    clearTimeout(msgTimer);
    msgTimer = setTimeout(function () { el.textContent = ''; }, 7000);
  }
  function setSaveState() {
    var el = $('#plan-save');
    if (!el) return;
    var n = Object.keys(P.pending).length + Object.keys(P.deletes).length;
    el.textContent = P.readonlyLink ? 'Lecture seule (lien)' : (!P.canEdit ? 'Lecture seule' : (P.saveError ? 'Non enregistré : ' + P.saveError : (n || P.saving ? 'Enregistrement…' : 'Enregistré · révision ' + (P.plan ? P.plan.rev : 0))));
    el.classList.toggle('is-error', !!P.saveError);
  }

  /* ---------- Éléments ---------- */
  function list() {
    return Object.keys(P.els).map(function (k) { return P.els[k]; }).sort(function (a, b) { return (a.z || 0) - (b.z || 0) || (a.uid < b.uid ? -1 : 1); });
  }
  function layerOf(uid) {
    var l = (P.meta.layers || []).filter(function (x) { return x.uid === uid; })[0];
    return l || null;
  }
  function visible(el) {
    var l = layerOf(el.layer);
    if (l && l.visible === false) return false;
    if (P.briefing && P.phase && !G.inPhase(el, P.phase)) return false;
    return true;
  }
  function editable(el) {
    if (!P.canEdit || el.locked) return false;
    var l = layerOf(el.layer);
    return !(l && l.locked);
  }
  function maxZ() { var z = 0; list().forEach(function (e) { z = Math.max(z, e.z || 0); }); return z; }

  /** Change des éléments (null = suppression), avec historique et envoi différé. */
  function commit(after, label, noHistory) {
    var before = {};
    Object.keys(after).forEach(function (uid) { before[uid] = P.els[uid] ? G.clone(P.els[uid]) : null; });
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
        Object.keys(sentPending).forEach(function (u) { if (P.els[u]) P.pending[u] = true; });
        Object.keys(sentDeletes).forEach(function (u) { P.deletes[u] = true; });
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
      Object.keys(sentPending).forEach(function (u) { if (P.els[u]) P.pending[u] = true; });
      Object.keys(sentDeletes).forEach(function (u) { P.deletes[u] = true; });
      P.saveError = 'réseau';
      setSaveState();
      setTimeout(scheduleFlush, 4000);
    });
  }

  function newElement(kind, geom, pts, extraProps) {
    var def = KINDS[kind] || { color: '#f2f2f2' };
    var props = { color: def.color, width: 2, dash: def.dash || 'solid', arrow: def.arrow || 'none', fill: def.fill || 0, label: def.text || '', size: kind === 'text' ? 18 : 24, rotation: 0, note: '' };
    if (kind === 'unit') { props.affil = $('#tool-affil') ? $('#tool-affil').value : 'F'; props.unitType = $('#tool-utype') ? $('#tool-utype').value : 'infantry'; props.echelon = $('#tool-echelon') ? $('#tool-echelon').value : 'squad'; props.callsign = ''; props.label = ''; props.color = { F: '#6cb8ff', H: '#ff6b6b', N: '#7ee08a', U: '#f2d36b' }[props.affil]; }
    if (kind === 'icon') props.icon = $('#tool-icon') ? $('#tool-icon').value : 'flag';
    Object.keys(extraProps || {}).forEach(function (k) { props[k] = extraProps[k]; });
    return { uid: G.uid('e'), kind: kind, geom: geom, pts: pts.map(function (q) { return [Math.round(q[0] * 10) / 10, Math.round(q[1] * 10) / 10]; }), props: props, phases: P.phase && $('#opt-phase-assign') && $('#opt-phase-assign').checked ? [P.phase] : [], layer: P.layer, group: '', z: maxZ() + 1, locked: false };
  }

  /* ---------- Rendu ---------- */
  function renderAll() {
    if (!tm) return;
    planLayer.clearLayers();
    list().forEach(function (el) {
      if (!visible(el)) return;
      var ghost = !P.briefing && P.phase && !G.inPhase(el, P.phase);
      var layer = R.render(tm, el, { catalog: CAT, ghost: ghost, pane: 'planPane', markerPane: 'planMarkers', interactive: true });
      layer.on('click', function (ev) { onElementClick(el.uid, ev); });
      layer.on('mousedown', function (ev) { onElementDown(el.uid, ev); });
      planLayer.addLayer(layer);
    });
    renderSelection();
    renderSide();
    renderLegend();
  }
  function renderSelection() {
    handleLayer.clearLayers();
    var sel = P.sel.map(function (u) { return P.els[u]; }).filter(Boolean);
    if (!sel.length || P.briefing) return;
    var pts = [];
    sel.forEach(function (el) { pts = pts.concat(G.outline(el, 24)); });
    var b = G.bbox(pts);
    var pad = tm.metersPerPixel() * 8;
    L.rectangle([tm.toLL([b[0] - pad, b[1] - pad]), tm.toLL([b[2] + pad, b[3] + pad])], { pane: 'planHandles', className: 'sel-box', color: '#34d399', weight: 1.5, dashArray: '4 3', fill: false, interactive: false }).addTo(handleLayer);
    if (sel.length !== 1 || !editable(sel[0])) return;
    var el = sel[0];
    var mk = function (p, onMove, onEnd, title) {
      var h = L.marker(tm.toLL(p), { pane: 'planHandles', draggable: true, keyboard: false, title: title || '', icon: L.divIcon({ className: '', html: '<span style="display:block;width:12px;height:12px;background:#070a0d;border:2px solid #34d399"></span>', iconSize: [12, 12], iconAnchor: [6, 6] }) });
      h.on('drag', function (e) { onMove(snapPoint(tm.toWorld(e.latlng), el.uid), false); });
      h.on('dragend', function (e) { onEnd(snapPoint(tm.toWorld(e.target.getLatLng()), el.uid)); });
      h.addTo(handleLayer);
    };
    var preview = function (copy) {
      draftLayer.clearLayers();
      draftLayer.addLayer(R.render(tm, copy, { catalog: CAT, pane: 'planHandles', interactive: false }));
    };
    if (el.geom === 'line' || el.geom === 'area' || el.geom === 'rect') {
      el.pts.forEach(function (p, i) {
        mk(p, function (q) { var c = G.clone(el); c.pts[i] = q; preview(c); }, function (q) {
          draftLayer.clearLayers();
          var c = G.clone(el); c.pts[i] = [Math.round(q[0] * 10) / 10, Math.round(q[1] * 10) / 10];
          var m = {}; m[el.uid] = c; commit(m);
        }, 'Sommet ' + (i + 1));
      });
    } else if (el.geom === 'circle' || el.geom === 'ellipse') {
      var c0 = el.pts[0];
      var rx = el.geom === 'circle' ? el.props.radius : el.props.rx;
      var ry = el.geom === 'circle' ? el.props.radius : el.props.ry;
      mk([c0[0] + rx, c0[1]], function (q) { var c = G.clone(el); if (c.geom === 'circle') c.props.radius = Math.max(5, G.dist(c0, q)); else c.props.rx = Math.max(5, Math.abs(q[0] - c0[0])); preview(c); }, function (q) {
        draftLayer.clearLayers();
        var c = G.clone(el); if (c.geom === 'circle') c.props.radius = Math.round(Math.max(5, G.dist(c0, q))); else c.props.rx = Math.round(Math.max(5, Math.abs(q[0] - c0[0])));
        var m = {}; m[el.uid] = c; commit(m);
      }, 'Rayon');
      if (el.geom === 'ellipse') {
        mk([c0[0], c0[1] + ry], function (q) { var c = G.clone(el); c.props.ry = Math.max(5, Math.abs(q[1] - c0[1])); preview(c); }, function (q) {
          draftLayer.clearLayers();
          var c = G.clone(el); c.props.ry = Math.round(Math.max(5, Math.abs(q[1] - c0[1])));
          var m = {}; m[el.uid] = c; commit(m);
        }, 'Second rayon');
      }
    }
  }

  /* ---------- Sélection et déplacement ---------- */
  function select(uids, add) {
    var ids = [];
    uids.forEach(function (u) {
      var el = P.els[u];
      if (!el) return;
      // Un groupe se choisit en entier.
      if (el.group) list().forEach(function (o) { if (o.group === el.group && ids.indexOf(o.uid) < 0) ids.push(o.uid); });
      else if (ids.indexOf(u) < 0) ids.push(u);
    });
    if (add) {
      ids.forEach(function (u) { var i = P.sel.indexOf(u); if (i >= 0) P.sel.splice(i, 1); else P.sel.push(u); });
    } else P.sel = ids;
    renderSelection();
    renderSide();
  }
  function onElementClick(uid, ev) {
    if (P.tool !== 'select') return;
    L.DomEvent.stopPropagation(ev);
    if (P.moved) { P.moved = false; return; }
    select([uid], ev.originalEvent && ev.originalEvent.shiftKey);
    if (P.sideTab !== 'element') setSideTab('element');
  }
  function onElementDown(uid, ev) {
    if (P.tool !== 'select' || !P.canEdit || P.briefing) return;
    if (P.sel.indexOf(uid) < 0) return;
    var els = P.sel.map(function (u) { return P.els[u]; }).filter(Boolean);
    if (els.some(function (e) { return !editable(e); })) return;
    L.DomEvent.stopPropagation(ev);
    tm.map.dragging.disable();
    var start = tm.toWorld(ev.latlng);
    var moving = true;
    P.moved = false;
    var onMove = function (e) {
      if (!moving) return;
      var p = tm.toWorld(e.latlng);
      var dx = p[0] - start[0], dy = p[1] - start[1];
      if (Math.abs(dx) + Math.abs(dy) > tm.metersPerPixel() * 3) P.moved = true;
      draftLayer.clearLayers();
      els.forEach(function (el) { draftLayer.addLayer(R.render(tm, G.translate(el, dx, dy), { catalog: CAT, pane: 'planHandles', interactive: false, opacity: 0.8 })); });
    };
    var onUp = function (e) {
      moving = false;
      tm.map.off('mousemove', onMove);
      tm.map.off('mouseup', onUp);
      document.removeEventListener('mouseup', docUp);
      tm.map.dragging.enable();
      draftLayer.clearLayers();
      if (!P.moved || !e || !e.latlng) return;
      var p = tm.toWorld(e.latlng);
      var dx = p[0] - start[0], dy = p[1] - start[1];
      var m = {};
      els.forEach(function (el) { m[el.uid] = G.translate(el, dx, dy); });
      commit(m, els.length + ' élément(s) déplacé(s) de ' + G.fmtDist(Math.sqrt(dx * dx + dy * dy)));
      setTimeout(function () { P.moved = false; }, 0);
    };
    var docUp = function () { if (moving) onUp(null); };
    tm.map.on('mousemove', onMove);
    tm.map.on('mouseup', onUp);
    document.addEventListener('mouseup', docUp);
  }
  function selected() { return P.sel.map(function (u) { return P.els[u]; }).filter(Boolean); }
  function editSelected(fn, label) {
    var els = selected().filter(editable);
    if (!els.length) { if (selected().length) say('Élément verrouillé : déverrouillez-le d’abord.', true); return; }
    var m = {};
    els.forEach(function (el) { m[el.uid] = fn(G.clone(el)); });
    commit(m, label);
  }
  function deleteSelected() {
    var els = selected().filter(editable);
    if (!els.length) return;
    var m = {};
    els.forEach(function (el) { m[el.uid] = null; });
    commit(m, els.length + ' élément(s) supprimé(s) · Ctrl+Z pour annuler');
  }
  function duplicateSelected(offset) {
    var els = selected();
    if (!els.length || !P.canEdit) return;
    var d = offset == null ? tm.metersPerPixel() * 24 : offset;
    var m = {}, ids = [], groupMap = {};
    var z = maxZ();
    els.forEach(function (el) {
      var c = G.translate(el, d, -d);
      c.uid = G.uid('e');
      c.locked = false;
      c.z = ++z;
      if (c.group) c.group = groupMap[c.group] || (groupMap[c.group] = G.uid('g'));
      m[c.uid] = c;
      ids.push(c.uid);
    });
    commit(m, els.length + ' élément(s) dupliqué(s)');
    P.sel = ids;
    renderSelection();
    renderSide();
  }
  function copySelected() {
    var els = selected();
    if (!els.length) return;
    try { window.localStorage.setItem('athena:toc:clipboard', JSON.stringify(els)); } catch (e) {}
    P.clipboard = G.clone(els);
    say(els.length + ' élément(s) copié(s)');
  }
  function paste() {
    if (!P.canEdit) return;
    var els = P.clipboard;
    try { els = JSON.parse(window.localStorage.getItem('athena:toc:clipboard') || 'null') || els; } catch (e) {}
    if (!els || !els.length) return;
    var center = tm.toWorld(tm.map.getCenter());
    var all = [];
    els.forEach(function (e) { all = all.concat(G.outline(e, 8)); });
    var b = G.bbox(all);
    var dx = center[0] - (b[0] + b[2]) / 2, dy = center[1] - (b[1] + b[3]) / 2;
    var m = {}, ids = [], z = maxZ(), groupMap = {};
    els.forEach(function (el) {
      var c = G.translate(el, dx, dy);
      c.uid = G.uid('e');
      c.z = ++z;
      c.locked = false;
      c.layer = layerOf(c.layer) ? c.layer : P.layer;
      c.phases = (c.phases || []).filter(function (ph) { return P.phases.some(function (x) { return x.uid === ph; }); });
      if (c.group) c.group = groupMap[c.group] || (groupMap[c.group] = G.uid('g'));
      m[c.uid] = c;
      ids.push(c.uid);
    });
    commit(m, els.length + ' élément(s) collé(s) au centre de la carte');
    P.sel = ids;
    renderSelection();
    renderSide();
  }
  function undo() {
    var map = P.hist.undo();
    if (!map) return;
    applyMap(map);
    say('Annulé');
  }
  function redo() {
    var map = P.hist.redo();
    if (!map) return;
    applyMap(map);
    say('Rétabli');
  }
  function zOrder(front) {
    var els = selected().filter(editable);
    if (!els.length) return;
    var zs = list().map(function (e) { return e.z || 0; });
    var z = front ? Math.max.apply(null, zs) : Math.min.apply(null, zs);
    var m = {};
    els.forEach(function (el, i) { var c = G.clone(el); c.z = front ? z + 1 + i : z - els.length + i; m[c.uid] = c; });
    commit(m, front ? 'Mis au premier plan' : 'Mis en arrière-plan');
  }
  function groupSelected(on) {
    var els = selected().filter(editable);
    if (els.length < (on ? 2 : 1)) return;
    var g = on ? G.uid('g') : '';
    editSelected(function (c) { c.group = g; return c; }, on ? els.length + ' éléments groupés' : 'Groupe défait');
  }

  /* ---------- Aimantation ---------- */
  function snapPoint(p, exceptUid) {
    if (!P.snap) return p;
    var tol = tm.metersPerPixel() * 12;
    var best = null, bestD = tol;
    list().forEach(function (el) {
      if (el.uid === exceptUid || !visible(el)) return;
      G.outline(el, el.geom === 'circle' || el.geom === 'ellipse' ? 8 : 0).forEach(function (q) {
        var d = G.dist(p, q);
        if (d < bestD) { bestD = d; best = q; }
      });
    });
    return best ? [best[0], best[1]] : p;
  }

  /* ---------- Outils ---------- */
  var TOOLS = [
    { group: 'Outils', items: [['select', 'Sélection', 'V'], ['pan', 'Main (déplacer la carte)', 'H'], ['measure', 'Mesure (distance, azimut)', 'M']] },
    { group: 'Unités et points', items: [['unit', 'Unité (symbole OTAN)', 'U'], ['text', 'Texte', 'T'], ['icon', 'Repère', 'I'], ['checkpoint', 'Point de contrôle (CP)'], ['rally', 'Point de ralliement (RP)'], ['trp', 'Référence de tir (TRP)'], ['objpt', 'Objectif (point)'], ['cleared', 'Bâtiment fouillé'], ['uncleared', 'Bâtiment non fouillé']] },
    { group: 'Traits et flèches', items: [['line', 'Trait', 'L'], ['arrow', 'Flèche', 'A'], ['route', 'Itinéraire'], ['axis', 'Axe de progression'], ['attack', 'Direction d’attaque'], ['support', 'Direction d’appui'], ['move', 'Mouvement'], ['phase_line', 'Ligne de coordination (PL)'], ['ld', 'Ligne de débouché (LD)'], ['loa', 'Limite d’avance (LOA)'], ['boundary', 'Limite (BDY)'], ['sbf', 'Appui par le feu (SBF)'], ['abf', 'Attaque par le feu (ABF)']] },
    { group: 'Zones', items: [['polygon', 'Polygone', 'P'], ['rect', 'Rectangle', 'R'], ['ellipse', 'Ellipse', 'E'], ['circle', 'Cercle', 'C'], ['objective', 'Objectif (zone)'], ['ao', 'Zone d’opération (AO)'], ['nai', 'Zone d’intérêt (NAI)'], ['ea', 'Zone d’engagement (EA)'], ['aa', 'Zone de regroupement (AA)'], ['enemy', 'Zone ennemie'], ['lz', 'Zone de poser (HLZ)'], ['infil', 'Infiltration'], ['exfil', 'Exfiltration']] },
    { group: 'Appuis feux et obstacles', items: [['nfa', 'Interdiction de tir (NFA)'], ['ffa', 'Tir libre (FFA)'], ['rfa', 'Tir restreint (RFA)'], ['minefield', 'Champ de mines']] }
  ];
  var HINT = {
    select: 'Clic : choisir (Maj+clic : ajouter) · glisser un élément choisi : déplacer · poignées : sommets et rayons',
    pan: 'Glisser : déplacer la carte · molette : rapprocher',
    measure: 'Clics successifs : distance et azimut · Échap : effacer',
    point: 'Clic : poser',
    line: 'Clics : points du trait · double-clic ou Entrée : terminer · Retour arrière : dernier point · Échap : annuler',
    area: 'Clics : sommets de la zone · double-clic ou Entrée : fermer · Échap : annuler',
    circle: 'Clic : centre, puis clic : rayon',
    rect: 'Clic : premier coin, puis clic : coin opposé',
    ellipse: 'Clic : centre, puis clic : rayons (largeur et hauteur)'
  };
  function geomFor(tool) {
    if (tool === 'rect') return 'rect';
    if (tool === 'ellipse') return 'ellipse';
    var d = KINDS[tool];
    return d ? d.geoms[0] : null;
  }
  function renderTools() {
    var h = '';
    TOOLS.forEach(function (grp, gi) {
      h += '<details' + (gi < 3 || window.innerHeight > 900 ? ' open' : '') + '><summary>' + esc(grp.group) + '</summary>';
      grp.items.forEach(function (t) {
        h += '<button type="button" class="plan-tool" data-tool="' + t[0] + '" aria-pressed="' + (P.tool === t[0]) + '"' + (!P.canEdit && ['select', 'pan', 'measure'].indexOf(t[0]) < 0 ? ' disabled' : '') + '><span>' + esc(t[1]) + '</span>' + (t[2] ? '<kbd>' + t[2] + '</kbd>' : '') + '</button>';
      });
      h += '</details>';
    });
    h += '<div class="toc-form" style="margin-top:8px"><label>Unité : affiliation<select class="toc-select" id="tool-affil"><option value="F">Ami</option><option value="H">Hostile</option><option value="N">Neutre</option><option value="U">Inconnu</option></select></label>' +
      '<label>Fonction<select class="toc-select" id="tool-utype">' + (CAT.unitTypes || []).map(function (t) { return '<option value="' + t.id + '">' + esc(t.label) + '</option>'; }).join('') + '</select></label>' +
      '<label>Échelon<select class="toc-select" id="tool-echelon">' + [['', 'Aucun'], ['team', 'Équipe'], ['squad', 'Groupe'], ['section', 'Section'], ['platoon', 'Peloton'], ['company', 'Compagnie'], ['battalion', 'Bataillon'], ['regiment', 'Régiment'], ['brigade', 'Brigade']].map(function (e) { return '<option value="' + e[0] + '"' + (e[0] === 'squad' ? ' selected' : '') + '>' + e[1] + '</option>'; }).join('') + '</select></label>' +
      '<label>Repère<select class="toc-select" id="tool-icon">' + [['flag', 'Drapeau'], ['star', 'Étoile'], ['cross', 'Croix'], ['target', 'Cible'], ['triangle', 'Triangle'], ['dot', 'Point'], ['square', 'Carré'], ['diamond', 'Losange']].map(function (e) { return '<option value="' + e[0] + '">' + e[1] + '</option>'; }).join('') + '</select></label>' +
      '<label><span><input type="checkbox" id="opt-snap" checked> Aimanter aux sommets <kbd>S</kbd></span></label>' +
      '<label><span><input type="checkbox" id="opt-phase-assign" checked> Nouveaux éléments dans la phase affichée</span></label></div>';
    $('#plan-tools').innerHTML = '<div class="toc-sec"><h2>Outils</h2><span></span></div>' + h;
  }
  function setTool(t) {
    if (!P.canEdit && ['select', 'pan', 'measure'].indexOf(t) < 0) return;
    cancelDraft();
    P.tool = t;
    document.querySelectorAll('[data-tool]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-tool') === t)); });
    var g = geomFor(t);
    $('#plan-hint').textContent = HINT[t] || HINT[g] || '';
    if (t === 'pan' || t === 'select') tm.map.doubleClickZoom.enable(); else tm.map.doubleClickZoom.disable();
    tm.map.getContainer().style.cursor = t === 'pan' ? 'grab' : (t === 'select' ? '' : 'crosshair');
    if (t !== 'measure') clearMeasure();
  }

  /* ---------- Dessin ---------- */
  function cancelDraft() {
    P.draft = null;
    if (draftLayer) draftLayer.clearLayers();
  }
  function drawDraft(cursor) {
    draftLayer.clearLayers();
    var d = P.draft;
    if (!d) return;
    var pts = d.pts.concat(cursor ? [cursor] : []);
    var tmp;
    if (d.geom === 'circle') tmp = { geom: 'circle', kind: d.kind, pts: [d.pts[0]], props: { radius: Math.max(5, G.dist(d.pts[0], cursor || d.pts[0])), color: '#34d399', dash: 'dash', fill: 0.05 } };
    else if (d.geom === 'ellipse') tmp = { geom: 'ellipse', kind: d.kind, pts: [d.pts[0]], props: { rx: Math.max(5, Math.abs((cursor || d.pts[0])[0] - d.pts[0][0])), ry: Math.max(5, Math.abs((cursor || d.pts[0])[1] - d.pts[0][1])), color: '#34d399', dash: 'dash', fill: 0.05 } };
    else if (d.geom === 'rect') tmp = { geom: 'rect', kind: 'rect', pts: [d.pts[0], cursor || d.pts[0]], props: { color: '#34d399', dash: 'dash', fill: 0.05 } };
    else tmp = { geom: pts.length >= 3 && d.geom === 'area' ? 'area' : 'line', kind: 'line', pts: pts, props: { color: '#34d399', dash: 'dash', fill: d.geom === 'area' ? 0.05 : 0 } };
    if (tmp.pts.length < 2 && tmp.geom === 'line') return;
    draftLayer.addLayer(R.render(tm, tmp, { pane: 'planHandles', interactive: false }));
    if (cursor && d.pts.length) {
      var last = d.pts[d.pts.length - 1];
      $('#plan-measure').textContent = G.fmtDist(G.dist(last, cursor)) + ' · ' + Math.round(G.azimuth(last, cursor)) + '°' + (d.pts.length > 1 ? ' · total ' + G.fmtDist(G.pathLength(pts)) : '');
    }
  }
  function finishDraft() {
    var d = P.draft;
    if (!d) return;
    var el = null;
    if (d.geom === 'line' && d.pts.length >= 2) el = newElement(d.kind, 'line', d.kind === 'sbf' || d.kind === 'abf' ? [d.pts[0], d.pts[d.pts.length - 1]] : d.pts);
    if (d.geom === 'area' && d.pts.length >= 3) el = newElement(d.kind, 'area', d.pts);
    cancelDraft();
    if (el) addNew(el);
  }
  function addNew(el) {
    var m = {}; m[el.uid] = el;
    commit(m, (KINDS[el.kind] ? KINDS[el.kind].label : el.kind) + ' ajouté');
    P.sel = [el.uid];
    renderSelection();
    renderSide();
    if (el.kind === 'text' || el.kind === 'unit') {
      setSideTab('element');
      setTimeout(function () { var i = $('#prop-label') || $('#prop-callsign'); if (i) i.focus(); }, 30);
    }
  }
  function onMapClick(e) {
    var p = snapPoint(tm.toWorld(e.latlng));
    if (P.tool === 'select') { if (!P.moved) { P.sel = []; renderSelection(); renderSide(); } return; }
    if (P.tool === 'pan') return;
    if (P.tool === 'measure') { addMeasure(p); return; }
    var g = geomFor(P.tool);
    if (!g) return;
    if (g === 'point') {
      var el = newElement(P.tool, 'point', [p]);
      if (P.tool === 'text') {
        var txt = window.prompt('Texte à poser sur la carte :', '');
        if (!txt) return;
        el.props.label = txt.slice(0, 120);
      }
      addNew(el);
      return;
    }
    if (!P.draft) P.draft = { kind: P.tool, geom: g, pts: [] };
    P.draft.pts.push(p);
    if (g === 'circle' && P.draft.pts.length === 2) { var c = P.draft.pts; cancelDraft(); addNew(newElement(P.tool, 'circle', [c[0]], { radius: Math.max(5, Math.round(G.dist(c[0], c[1]))) })); return; }
    if (g === 'ellipse' && P.draft.pts.length === 2) { var c2 = P.draft.pts; cancelDraft(); addNew(newElement(P.tool, 'ellipse', [c2[0]], { rx: Math.max(5, Math.round(Math.abs(c2[1][0] - c2[0][0]))), ry: Math.max(5, Math.round(Math.abs(c2[1][1] - c2[0][1]))) })); return; }
    if (g === 'rect' && P.draft.pts.length === 2) { var c3 = P.draft.pts; cancelDraft(); addNew(newElement(P.tool, 'rect', [c3[0], c3[1]])); return; }
    if ((P.tool === 'sbf' || P.tool === 'abf') && P.draft.pts.length === 2) { finishDraft(); return; }
    drawDraft(null);
  }

  /* ---------- Mesure ---------- */
  function addMeasure(p) {
    P.measure = P.measure || [];
    P.measure.push(p);
    drawMeasure(null);
  }
  function drawMeasure(cursor) {
    measureLayer.clearLayers();
    var pts = (P.measure || []).concat(cursor ? [cursor] : []);
    if (!pts.length) return;
    L.polyline(pts.map(tm.toLL), { pane: 'planHandles', color: '#f5b841', weight: 2, dashArray: '6 4', interactive: false }).addTo(measureLayer);
    pts.forEach(function (q) { L.circleMarker(tm.toLL(q), { pane: 'planHandles', radius: 3, color: '#f5b841', interactive: false }).addTo(measureLayer); });
    if (pts.length >= 2) {
      var a = pts[pts.length - 2], b = pts[pts.length - 1];
      var txt = G.fmtDist(G.dist(a, b)) + ' · azimut ' + Math.round(G.azimuth(a, b)) + '° (' + Math.round(G.azimuth(a, b) * 17.7778) + ' mils)' + (pts.length > 2 ? ' · total ' + G.fmtDist(G.pathLength(pts)) : '');
      $('#plan-measure').textContent = txt;
      L.marker(tm.toLL(b), { pane: 'planHandles', interactive: false, icon: L.divIcon({ className: '', html: '<span class="toc-plate" style="border-color:#f5b841">' + esc(txt) + '</span>', iconSize: null, iconAnchor: [-10, 10] }) }).addTo(measureLayer);
    }
  }
  function clearMeasure() { P.measure = null; if (measureLayer) measureLayer.clearLayers(); $('#plan-measure').textContent = ''; }

  /* ---------- Panneau de droite ---------- */
  var SIDE_TABS = [['element', 'Élément'], ['phases', 'Phases'], ['calques', 'Calques'], ['ordre', 'Ordre'], ['notes', 'Échanges']];
  function setSideTab(t) {
    P.sideTab = t;
    document.querySelectorAll('[data-side]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-side') === t)); });
    renderSide();
  }
  function sec(t, s) { return '<div class="toc-sec"><h2>' + esc(t) + '</h2><span>' + esc(s || '') + '</span></div>'; }
  function renderSide() {
    var body = $('#plan-side');
    if (!body || !P.loaded) return;
    if (body.contains(document.activeElement) && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) && !P.forceSide) return;
    P.forceSide = false;
    var fn = { element: sideElement, phases: sidePhases, calques: sideLayers, ordre: sideOrder, notes: sideNotes }[P.sideTab] || sideElement;
    body.innerHTML = fn();
  }
  function swatches(cur) {
    return '<div class="plan-swatches">' + Object.keys(G.GAME_COLORS).map(function (k) {
      var c = G.GAME_COLORS[k];
      return '<button type="button" data-color="' + c + '" title="' + k + ' (couleur du jeu)" aria-label="' + k + '" aria-pressed="' + (cur === c) + '" style="background:' + c + '"></button>';
    }).join('') + '<input type="color" data-color-input value="' + esc(cur || '#f2f2f2') + '" aria-label="Autre couleur" title="Autre couleur (la plus proche des 9 couleurs du jeu part en jeu)" style="width:34px;height:26px;padding:0;border:1px solid var(--toc-line);background:none"></div>';
  }
  function sideElement() {
    var els = selected();
    if (!els.length) {
      var n = list().length;
      return sec('Élément', n + ' élément(s) dans le plan') + (n ? '<p class="toc-note">Choisissez un élément sur la carte (outil Sélection) pour le régler, ou un outil à gauche pour dessiner.</p>' : '<div class="toc-empty"><p>Le plan est vide : choisissez un outil à gauche et cliquez sur la carte.</p>' + (P.canEdit ? '<button type="button" class="toc-btn is-primary" data-act="tool-unit">Poser une unité</button>' : '') + '</div>');
    }
    var el = els[0];
    var one = els.length === 1;
    var can = els.every(editable);
    var dis = can ? '' : ' disabled';
    var p = el.props;
    var def = KINDS[el.kind] || { label: el.kind };
    var h = sec(one ? def.label : els.length + ' éléments', one ? G.grid(G.centroid(el)[0], G.centroid(el)[1], 8) : '');
    if (!can) h += '<p class="toc-banner">' + (P.canEdit ? 'Verrouillé : déverrouillez-le pour le changer.' : 'Lecture seule.') + '</p>';
    h += '<div class="toc-form">';
    if (one && el.kind === 'unit') {
      h += '<label>Indicatif<input class="toc-input" id="prop-callsign" data-prop="callsign" maxlength="40" value="' + esc(p.callsign || '') + '"' + dis + '></label>';
      h += '<div class="toc-row"><label>Affiliation<select class="toc-select" data-prop="affil"' + dis + '>' + [['F', 'Ami'], ['H', 'Hostile'], ['N', 'Neutre'], ['U', 'Inconnu']].map(function (o) { return '<option value="' + o[0] + '"' + (p.affil === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
        '<label>Fonction<select class="toc-select" data-prop="unitType"' + dis + '>' + (CAT.unitTypes || []).map(function (t) { return '<option value="' + t.id + '"' + (p.unitType === t.id ? ' selected' : '') + '>' + esc(t.label) + '</option>'; }).join('') + '</select></label>' +
        '<label>Échelon<select class="toc-select" data-prop="echelon"' + dis + '>' + [['', 'Aucun'], ['team', 'Équipe'], ['squad', 'Groupe'], ['section', 'Section'], ['platoon', 'Peloton'], ['company', 'Compagnie'], ['battalion', 'Bataillon'], ['regiment', 'Régiment'], ['brigade', 'Brigade']].map(function (o) { return '<option value="' + o[0] + '"' + (p.echelon === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label></div>';
    } else if (one) {
      h += '<label>' + (el.kind === 'text' ? 'Texte' : 'Libellé') + '<input class="toc-input" id="prop-label" data-prop="label" maxlength="' + (el.kind === 'text' ? 120 : 60) + '" value="' + esc(p.label || '') + '"' + dis + '></label>';
    }
    h += '<label>Couleur</label>' + (can ? swatches(p.color) : '');
    if (el.geom !== 'point' || el.kind === 'text') h += '<label>Épaisseur ' + (el.kind === 'text' ? '(taille du texte)' : '') + '<input type="range" min="' + (el.kind === 'text' ? 10 : 1) + '" max="' + (el.kind === 'text' ? 48 : 8) + '" data-prop="' + (el.kind === 'text' ? 'size' : 'width') + '" data-num value="' + (el.kind === 'text' ? p.size : p.width) + '"' + dis + '></label>';
    if (el.geom === 'point' && el.kind !== 'text') h += '<label>Taille<input type="range" min="12" max="56" data-prop="size" data-num value="' + (p.size || 24) + '"' + dis + '></label>';
    if (el.geom !== 'point') h += '<div class="toc-row" style="--cols:2"><label>Trait<select class="toc-select" data-prop="dash"' + dis + '><option value="solid"' + (p.dash === 'solid' ? ' selected' : '') + '>Plein</option><option value="dash"' + (p.dash === 'dash' ? ' selected' : '') + '>Tirets</option><option value="dot"' + (p.dash === 'dot' ? ' selected' : '') + '>Pointillé</option></select></label>' +
      (el.geom === 'line' ? '<label>Flèche<select class="toc-select" data-prop="arrow"' + dis + '><option value="none"' + (p.arrow === 'none' ? ' selected' : '') + '>Sans</option><option value="end"' + (p.arrow === 'end' ? ' selected' : '') + '>Au bout</option><option value="both"' + (p.arrow === 'both' ? ' selected' : '') + '>Aux deux bouts</option></select></label>' : '<label>Remplissage<input type="range" min="0" max="0.6" step="0.02" data-prop="fill" data-num value="' + (p.fill || 0) + '"' + dis + '></label>') + '</div>';
    if (one && (el.geom === 'circle')) h += '<label>Rayon (m)<input class="toc-input mono" type="number" min="5" max="20000" data-prop="radius" data-num value="' + Math.round(p.radius) + '"' + dis + '></label>';
    if (one && (el.geom === 'ellipse')) h += '<div class="toc-row" style="--cols:2"><label>Rayon est-ouest (m)<input class="toc-input mono" type="number" min="5" max="20000" data-prop="rx" data-num value="' + Math.round(p.rx) + '"' + dis + '></label><label>Rayon nord-sud (m)<input class="toc-input mono" type="number" min="5" max="20000" data-prop="ry" data-num value="' + Math.round(p.ry) + '"' + dis + '></label></div>';
    if (one && (el.geom === 'point' || el.geom === 'ellipse' || el.geom === 'circle')) h += '<label>Rotation (°)<input class="toc-input mono" type="number" min="-360" max="360" data-prop="rotation" data-num value="' + Math.round(p.rotation || 0) + '"' + dis + '></label>';
    if (one) h += '<label>Note<textarea class="toc-textarea" rows="2" maxlength="400" data-prop="note"' + dis + '>' + esc(p.note || '') + '</textarea></label>';
    h += '<label>Phases (aucune cochée : toutes)</label>' + (P.phases.length ? P.phases.map(function (ph, i) {
      var on = els.every(function (e) { return (e.phases || []).indexOf(ph.uid) >= 0; });
      return '<label><span><input type="checkbox" data-phase="' + ph.uid + '"' + (on ? ' checked' : '') + dis + '> ' + (i + 1) + '. ' + esc(ph.name) + '</span></label>';
    }).join('') : '<p class="toc-note">Pas encore de phase (onglet Phases).</p>');
    h += '<label>Calque<select class="toc-select" data-layer-sel' + dis + '>' + (P.meta.layers || []).map(function (l) { return '<option value="' + l.uid + '"' + (el.layer === l.uid ? ' selected' : '') + '>' + esc(l.name) + '</option>'; }).join('') + '</select></label>';
    h += '</div>';
    if (P.canEdit) {
      h += '<div class="toc-buttons" style="--cols:3">' +
        '<button type="button" class="toc-btn" data-act="rot-">Pivoter −15°</button><button type="button" class="toc-btn" data-act="rot+">Pivoter +15°</button><button type="button" class="toc-btn" data-act="lock">' + (els.every(function (e) { return e.locked; }) ? 'Déverrouiller' : 'Verrouiller') + '</button>' +
        '<button type="button" class="toc-btn" data-act="scale-">Réduire</button><button type="button" class="toc-btn" data-act="scale+">Agrandir</button><button type="button" class="toc-btn" data-act="dup">Dupliquer</button>' +
        '<button type="button" class="toc-btn" data-act="front">Devant</button><button type="button" class="toc-btn" data-act="back">Derrière</button><button type="button" class="toc-btn" data-act="' + (els.length > 1 ? 'group' : 'ungroup') + '"' + (els.length > 1 || el.group ? '' : ' disabled') + '>' + (els.length > 1 ? 'Grouper' : 'Dégrouper') + '</button>' +
        '</div><button type="button" class="toc-btn is-danger is-block" data-act="delete">Supprimer <kbd>Suppr</kbd></button>';
    }
    if (one && P.comments.length) {
      var cs = P.comments.filter(function (c) { return c.element_uid === el.uid && !c.resolved; });
      if (cs.length) h += '<p class="toc-note">' + cs.length + ' commentaire(s) sur cet élément (onglet Échanges).</p>';
    }
    return h;
  }
  function sidePhases() {
    var h = sec('Phases', P.phases.length + ' phase(s)');
    if (!P.phases.length) h += '<div class="toc-empty"><p>Pas de phase : le plan se présente d’un bloc. Ajoutez des phases pour briefer pas à pas.</p></div>';
    h += P.phases.map(function (ph, i) {
      var count = list().filter(function (e) { return (e.phases || []).indexOf(ph.uid) >= 0 || !(e.phases || []).length; }).length;
      return '<div class="toc-card" data-phase-card="' + ph.uid + '"><div class="toc-row" style="--cols:4;grid-template-columns:auto 1fr 110px auto;align-items:center;display:grid;gap:4px"><b class="mono">' + (i + 1) + '.</b><input class="toc-input" data-ph="name" maxlength="80" value="' + esc(ph.name) + '" aria-label="Nom de la phase"' + (P.canEdit ? '' : ' disabled') + '><input class="toc-input mono" data-ph="time_label" maxlength="40" placeholder="H+0:30" value="' + esc(ph.time_label || '') + '" aria-label="Heure"' + (P.canEdit ? '' : ' disabled') + '><span class="toc-muted">' + count + ' él.</span></div>' +
        '<textarea class="toc-textarea" rows="2" data-ph="note" maxlength="2000" placeholder="Ce qui se passe dans cette phase…" aria-label="Note de la phase"' + (P.canEdit ? '' : ' disabled') + '>' + esc(ph.note || '') + '</textarea>' +
        (P.canEdit ? '<div class="toc-buttons" style="--cols:4"><button type="button" class="toc-btn is-small" data-ph-act="show">Afficher</button><button type="button" class="toc-btn is-small" data-ph-act="assign"' + (P.sel.length ? '' : ' disabled') + '>Y mettre la sélection</button><button type="button" class="toc-btn is-small" data-ph-act="up"' + (i ? '' : ' disabled') + '>Monter</button><button type="button" class="toc-btn is-small" data-ph-act="del">Retirer</button></div>' : '') + '</div>';
    }).join('');
    if (P.canEdit) h += '<button type="button" class="toc-btn is-primary is-block" data-act="phase-add">Ajouter une phase</button>';
    return h + '<p class="toc-note">Barre du bas : choisir la phase affichée. « Briefing » : présentation phase par phase (← →).</p>';
  }
  function sideLayers() {
    var ls = P.meta.layers || [];
    var h = sec('Calques du plan', ls.length + ' calque(s)');
    h += ls.map(function (l) {
      var n = list().filter(function (e) { return e.layer === l.uid; }).length;
      return '<div class="toc-layer" data-layer-row="' + l.uid + '"><input type="checkbox" data-lv="' + l.uid + '"' + (l.visible !== false ? ' checked' : '') + ' aria-label="Afficher"><input class="toc-input" data-ln="' + l.uid + '" value="' + esc(l.name) + '" maxlength="40" aria-label="Nom du calque"' + (P.canEdit ? '' : ' disabled') + '><button type="button" class="toc-btn is-small" data-ll="' + l.uid + '">' + (l.locked ? 'Verrouillé' : 'Libre') + '</button><small>' + n + ' élément(s)' + (P.layer === l.uid ? ' · calque actif' : '') + (P.canEdit ? ' · <a href="#" data-la="' + l.uid + '">rendre actif</a>' + (ls.length > 1 ? ' · <a href="#" data-ld="' + l.uid + '">retirer</a>' : '') : '') + '</small></div>';
    }).join('');
    if (P.canEdit) h += '<button type="button" class="toc-btn is-block" data-act="layer-add">Ajouter un calque</button>';
    h += sec('Calques du jeu', 'superposés à la carte') + '<p class="toc-note">Pistes, marqueurs, formes, plan du camp, zones… : « Ajouter au plan » sur chaque élément copie un élément du jeu dans ce plan.</p><button type="button" class="toc-btn is-block" data-act="game-layers">Ouvrir les calques du jeu <kbd>J</kbd></button>';
    return h;
  }
  var NOTE_TITLES = [['situation', '1. Situation'], ['mission', '2. Mission'], ['execution', '3. Exécution'], ['support', '4. Soutien'], ['command', '5. Commandement et transmissions'], ['free', 'Notes libres']];
  function sideOrder() {
    var n = P.meta.notes || {};
    var dis = P.canEdit ? '' : ' disabled';
    var h = sec('Ordre d’opération', 'SMEAC, facultatif');
    h += '<div class="toc-form"><div class="toc-row" style="--cols:2"><label>Camp du plan<select class="toc-select" data-meta="side"' + dis + '>' + Object.keys(SIDES).map(function (s) { return '<option value="' + s + '"' + (P.meta.side === s ? ' selected' : '') + '>' + SIDES[s] + '</option>'; }).join('') + '</select></label><label>Groupe date-heure<input class="toc-input mono" data-meta="dtg" maxlength="40" placeholder="111800ZNOV26" value="' + esc(P.meta.dtg || '') + '"' + dis + '></label></div>';
    NOTE_TITLES.forEach(function (t) { h += '<label>' + esc(t[1]) + '<textarea class="toc-textarea" rows="3" maxlength="6000" data-note="' + t[0] + '"' + dis + '>' + esc(n[t[0]] || '') + '</textarea></label>'; });
    h += '</div>' + sec('Légende', 'imprimée sur la fiche');
    h += '<div id="plan-legend-auto" class="toc-note"></div>';
    h += (P.meta.legend || []).map(function (l, i) { return '<div class="toc-row" style="display:grid;grid-template-columns:34px 1fr auto;gap:4px;margin:4px 0"><input type="color" data-lg-c="' + i + '" value="' + esc(l.color) + '"' + dis + ' aria-label="Couleur"><input class="toc-input" data-lg-l="' + i + '" value="' + esc(l.label) + '" maxlength="60"' + dis + ' aria-label="Libellé"><button type="button" class="toc-btn is-small" data-lg-d="' + i + '"' + dis + '>Retirer</button></div>'; }).join('');
    if (P.canEdit) h += '<button type="button" class="toc-btn is-block" data-act="legend-add">Ajouter une ligne de légende</button>';
    return h;
  }
  function sideNotes() {
    var h = sec('Échanges', P.presence.length + ' membre(s) sur le plan');
    h += '<div class="plan-presence">' + P.presence.map(function (p) { return '<span>' + esc(p.label) + (p.tool ? ' · ' + esc(p.tool) : '') + '</span>'; }).join('') + '</div>';
    if (!P.readonlyLink) {
      h += '<form class="toc-form" data-form="comment"><textarea class="toc-textarea" name="body" rows="2" maxlength="2000" placeholder="Commentaire' + (P.sel.length === 1 ? ' sur l’élément choisi' : ' sur le plan') + '…" aria-label="Commentaire"></textarea><button class="toc-btn is-primary is-block" type="submit">Publier</button></form>';
    }
    if (!P.comments.length) return h + '<div class="toc-empty"><p>Aucun commentaire sur ce plan.</p></div>';
    return h + P.comments.map(function (c) {
      return '<div class="toc-card" style="' + (c.resolved ? 'opacity:.6' : '') + '"><p><b>' + esc(c.author) + '</b> · <span class="mono">' + esc(String(c.created_at).slice(5, 16)) + '</span>' + (c.element_uid ? ' · <a href="#" data-goto="' + esc(c.element_uid) + '">élément</a>' : '') + '</p><p>' + esc(c.body) + '</p>' + (P.readonlyLink ? '' : '<button type="button" class="toc-btn is-small" data-resolve="' + c.id + '" data-state="' + (c.resolved ? 0 : 1) + '">' + (c.resolved ? 'Rouvrir' : 'Réglé') + '</button>') + '</div>';
    }).join('');
  }
  function renderLegend() {
    var el = $('#plan-legend-auto');
    if (!el) return;
    var seen = {};
    list().forEach(function (e) { var k = (KINDS[e.kind] || {}).label || e.kind; seen[k] = e.props.color; });
    el.innerHTML = Object.keys(seen).length ? 'Types utilisés : ' + Object.keys(seen).map(function (k) { return '<span style="color:' + esc(seen[k]) + '">■</span> ' + esc(k); }).join(' · ') : 'Le plan est vide.';
  }

  function onSideInput(e) {
    var t = e.target;
    if (t.hasAttribute('data-prop')) {
      var k = t.getAttribute('data-prop');
      var v = t.hasAttribute('data-num') ? Number(t.value) : t.value;
      if (e.type === 'input' && t.type !== 'range' && t.tagName !== 'TEXTAREA' && t.type !== 'text' && t.type !== 'number') return;
      clearTimeout(P.propTimer);
      P.propTimer = setTimeout(function () {
        editSelected(function (c) {
          c.props[k] = v;
          if (k === 'affil' && c.kind === 'unit') c.props.color = { F: '#6cb8ff', H: '#ff6b6b', N: '#7ee08a', U: '#f2d36b' }[v];
          return c;
        });
      }, e.type === 'input' ? 250 : 0);
      return;
    }
    if (t.hasAttribute('data-color-input')) { editSelected(function (c) { c.props.color = t.value; return c; }); return; }
    if (t.hasAttribute('data-phase') && e.type === 'change') {
      var ph = t.getAttribute('data-phase');
      editSelected(function (c) { c.phases = (c.phases || []).filter(function (x) { return x !== ph; }); if (t.checked) c.phases.push(ph); return c; });
      return;
    }
    if (t.hasAttribute('data-layer-sel') && e.type === 'change') { editSelected(function (c) { c.layer = t.value; return c; }); return; }
    if (t.hasAttribute('data-ph')) {
      var card = t.closest('[data-phase-card]');
      var ph2 = P.phases.filter(function (x) { return x.uid === card.getAttribute('data-phase-card'); })[0];
      if (ph2) { ph2[t.getAttribute('data-ph')] = t.value; phasesChanged(); renderPhaseBar(); }
      return;
    }
    if (t.hasAttribute('data-note')) { P.meta.notes = P.meta.notes || {}; P.meta.notes[t.getAttribute('data-note')] = t.value; metaChanged(); return; }
    if (t.hasAttribute('data-meta')) { P.meta[t.getAttribute('data-meta')] = t.value; metaChanged(); return; }
    if (t.hasAttribute('data-lv') && e.type === 'change') { var l = layerOf(t.getAttribute('data-lv')); if (l) { l.visible = t.checked; metaChanged(); renderAll(); } return; }
    if (t.hasAttribute('data-ln')) { var l2 = layerOf(t.getAttribute('data-ln')); if (l2 && t.value.trim()) { l2.name = t.value.trim(); metaChanged(); } return; }
    if (t.hasAttribute('data-lg-c') || t.hasAttribute('data-lg-l')) {
      var i = Number(t.getAttribute('data-lg-c') != null ? t.getAttribute('data-lg-c') : t.getAttribute('data-lg-l'));
      var row = P.meta.legend[i];
      if (row) { if (t.hasAttribute('data-lg-c')) row.color = t.value; else row.label = t.value; metaChanged(); }
    }
  }
  function onSideClick(e) {
    var t = e.target.closest('button, a');
    if (!t) return;
    if (t.tagName === 'A') e.preventDefault();
    if (t.hasAttribute('data-color')) { editSelected(function (c) { c.props.color = t.getAttribute('data-color'); return c; }); return; }
    if (t.hasAttribute('data-goto')) { var el = P.els[t.getAttribute('data-goto')]; if (el) { tm.centerOn(G.centroid(el)); select([el.uid]); } return; }
    if (t.hasAttribute('data-resolve')) { api('/atak/toc/api/plans/' + P.id + '/comments', { resolve: Number(t.getAttribute('data-resolve')), resolved: t.getAttribute('data-state') === '1' }).then(function (j) { if (j && j.ok) { P.comments = j.comments; P.forceSide = true; renderSide(); } }); return; }
    if (t.hasAttribute('data-ll')) { var l = layerOf(t.getAttribute('data-ll')); if (l && P.canEdit) { l.locked = !l.locked; metaChanged(); P.forceSide = true; renderAll(); } return; }
    if (t.hasAttribute('data-la')) { P.layer = t.getAttribute('data-la'); P.forceSide = true; renderSide(); return; }
    if (t.hasAttribute('data-ld')) { removeLayer(t.getAttribute('data-ld')); return; }
    if (t.hasAttribute('data-lg-d')) { P.meta.legend.splice(Number(t.getAttribute('data-lg-d')), 1); metaChanged(); P.forceSide = true; renderSide(); return; }
    if (t.hasAttribute('data-ph-act')) { phaseAction(t.getAttribute('data-ph-act'), t.closest('[data-phase-card]').getAttribute('data-phase-card')); return; }
    var a = t.getAttribute('data-act');
    if (!a) return;
    P.forceSide = true;
    if (a === 'tool-unit') setTool('unit');
    if (a === 'rot-' || a === 'rot+') editSelected(function (c) { return G.rotate(c, a === 'rot+' ? 15 : -15); });
    if (a === 'scale-' || a === 'scale+') editSelected(function (c) { return G.scale(c, a === 'scale+' ? 1.15 : 1 / 1.15); });
    if (a === 'lock') { var allLocked = selected().every(function (x) { return x.locked; }); var m = {}; selected().forEach(function (x) { var c = G.clone(x); c.locked = !allLocked; m[c.uid] = c; }); if (Object.keys(m).length) commit(m, allLocked ? 'Déverrouillé' : 'Verrouillé'); }
    if (a === 'dup') duplicateSelected();
    if (a === 'front') zOrder(true);
    if (a === 'back') zOrder(false);
    if (a === 'group') groupSelected(true);
    if (a === 'ungroup') groupSelected(false);
    if (a === 'delete') deleteSelected();
    if (a === 'phase-add') { P.phases.push({ uid: G.uid('p'), name: 'Phase ' + (P.phases.length + 1), note: '', time_label: '' }); phasesChanged(); renderPhaseBar(); renderSide(); }
    if (a === 'layer-add') { P.meta.layers.push({ uid: G.uid('l'), name: 'Calque ' + (P.meta.layers.length + 1), visible: true, locked: false }); metaChanged(); renderSide(); }
    if (a === 'legend-add') { P.meta.legend = P.meta.legend || []; P.meta.legend.push({ color: '#f2f2f2', label: 'Nouvelle ligne' }); metaChanged(); renderSide(); }
    if (a === 'game-layers') layers.openPanel(true);
  }
  function onSideSubmit(e) {
    var f = e.target.closest('form[data-form="comment"]');
    if (!f) return;
    e.preventDefault();
    var body = f.elements.body.value.trim();
    if (!body) return;
    api('/atak/toc/api/plans/' + P.id + '/comments', { body: body, element_uid: P.sel.length === 1 ? P.sel[0] : null }).then(function (j) {
      if (j && j.ok) { P.comments = j.comments; P.forceSide = true; renderSide(); say('Commentaire publié.'); } else say(ERR[j && j.error] || 'Commentaire refusé.', true);
    });
  }
  function removeLayer(uid) {
    var rest = (P.meta.layers || []).filter(function (l) { return l.uid !== uid; });
    if (!rest.length) return;
    var target = rest[0].uid;
    var m = {};
    list().forEach(function (el) { if (el.layer === uid) { var c = G.clone(el); c.layer = target; m[c.uid] = c; } });
    if (Object.keys(m).length) commit(m, 'Éléments passés dans « ' + rest[0].name + ' »');
    P.meta.layers = rest;
    if (P.layer === uid) P.layer = target;
    metaChanged();
    P.forceSide = true;
    renderSide();
  }
  function phaseAction(act, uid) {
    var i = P.phases.map(function (p) { return p.uid; }).indexOf(uid);
    if (i < 0) return;
    if (act === 'show') { setPhase(uid); return; }
    if (act === 'assign') { editSelected(function (c) { c.phases = (c.phases || []).filter(function (x) { return x !== uid; }); c.phases.push(uid); return c; }, 'Sélection mise dans « ' + P.phases[i].name + ' »'); P.forceSide = true; renderSide(); return; }
    if (act === 'up' && i > 0) { var t = P.phases[i - 1]; P.phases[i - 1] = P.phases[i]; P.phases[i] = t; }
    if (act === 'del') {
      if (!window.confirm('Retirer la phase « ' + P.phases[i].name + ' » ? Ses éléments restent dans le plan.')) return;
      P.phases.splice(i, 1);
      if (P.phase === uid) P.phase = null;
    }
    phasesChanged();
    renderPhaseBar();
    P.forceSide = true;
    renderSide();
  }
  function phasesChanged() {
    P.phasesDirty = true;
    clearTimeout(phaseTimer);
    phaseTimer = setTimeout(function () {
      api('/atak/toc/api/plans/' + P.id + '/phases', { phases: P.phases.map(function (p, i) { return { uid: p.uid, ord: i, name: p.name || ('Phase ' + (i + 1)), note: p.note || '', time_label: p.time_label || '' }; }) }).then(function (j) {
        P.phasesDirty = false;
        if (!j || !j.ok) say(ERR[j && j.error] || 'Phases non enregistrées.', true);
      });
    }, 700);
  }
  function metaChanged() {
    P.metaDirty = true;
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

  /* ---------- Phases : barre du bas et briefing ---------- */
  function renderPhaseBar() {
    var bar = $('#plan-phases');
    var h = '<button type="button" class="toc-btn' + (!P.phase ? ' is-on' : '') + '" data-phase-pick="">Plan entier</button>';
    P.phases.forEach(function (ph, i) { h += '<button type="button" class="toc-btn' + (P.phase === ph.uid ? ' is-on' : '') + '" data-phase-pick="' + ph.uid + '" title="' + esc(ph.note || '') + '">' + (i + 1) + '. ' + esc(ph.name) + (ph.time_label ? ' · ' + esc(ph.time_label) : '') + '</button>'; });
    h += '<span style="flex:1"></span><button type="button" class="toc-btn' + (P.briefing ? ' is-on' : '') + '" id="plan-brief-btn" title="Présenter phase par phase (← →)">' + (P.briefing ? 'Quitter le briefing' : 'Briefing') + '</button>';
    bar.innerHTML = h;
    var card = $('#plan-brief-card');
    var ph = P.phases.filter(function (x) { return x.uid === P.phase; })[0];
    card.hidden = !P.briefing;
    if (P.briefing) card.innerHTML = '<div class="toc-sec"><h2>' + (ph ? (P.phases.indexOf(ph) + 1) + ' / ' + P.phases.length + ' · ' + esc(ph.name) : esc(P.plan.title)) + '</h2><span class="mono">' + esc(ph ? ph.time_label : '') + '</span></div><p>' + esc(ph ? ph.note : 'Plan entier') + '</p>';
  }
  function setPhase(uid) {
    P.phase = uid || null;
    renderPhaseBar();
    renderAll();
  }
  function stepPhase(d) {
    if (!P.phases.length) return;
    var i = P.phases.map(function (p) { return p.uid; }).indexOf(P.phase);
    i = Math.max(-1, Math.min(P.phases.length - 1, i + d));
    setPhase(i < 0 ? null : P.phases[i].uid);
  }
  function toggleBriefing(on) {
    P.briefing = on === undefined ? !P.briefing : on;
    document.body.classList.toggle('is-briefing', P.briefing);
    if (P.briefing && !P.phase && P.phases.length) P.phase = P.phases[0].uid;
    $('#plan-tools').hidden = P.briefing;
    $('.plan-side').hidden = P.briefing;
    $('.plan-main').style.gridTemplateColumns = P.briefing ? '1fr' : '';
    setTimeout(function () { tm.map.invalidateSize(); }, 50);
    renderPhaseBar();
    renderAll();
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
    if (Array.isArray(j.phases) && !P.phasesDirty && document.activeElement && !document.activeElement.hasAttribute('data-ph')) {
      P.phases = j.phases.map(function (p) { return { uid: p.uid, name: p.name, note: p.note, time_label: p.time_label }; });
    }
    if (Array.isArray(j.presence)) {
      P.presence = j.presence;
      var others = j.presence.filter(function (p) { return p.label !== BOOT.operator; });
      $('#plan-presence').textContent = others.length ? others.map(function (p) { return p.label; }).join(', ') + ' sur le plan' : 'Seul sur le plan';
    }
    if (j.server_ms) P.serverMs = Math.max(P.serverMs, j.server_ms);
  }
  function normMeta(m) {
    m = m || {};
    if (!Array.isArray(m.layers) || !m.layers.length) m.layers = [{ uid: 'base', name: 'Plan', visible: true, locked: false }];
    m.notes = m.notes || {};
    m.legend = Array.isArray(m.legend) ? m.legend : [];
    m.side = m.side || 'WEST';
    if (!layerOfIn(m.layers, P.layer)) P.layer = m.layers[0].uid;
    return m;
  }
  function layerOfIn(ls, uid) { return ls.some(function (l) { return l.uid === uid; }); }
  function poll(full) {
    if (P.readonlyLink) return Promise.resolve();
    var since = full ? 0 : P.serverMs;
    return api('/atak/toc/api/plans/' + P.id + '?since=' + since + '&tool=' + encodeURIComponent(P.tool)).then(function (j) {
      if (!j || j.notModified) return;
      if (!j.ok) { if (j.error === 'not_found') { say(ERR.not_found, true); clearInterval(pollTimer); } return; }
      var wasEdit = P.canEdit;
      applyServer(j);
      if (wasEdit !== P.canEdit) { renderTools(); renderTopButtons(); }
      renderPhaseBar();
      renderAll();
      setSaveState();
    }).catch(function () {});
  }
  function loadComments() {
    if (P.readonlyLink) return;
    api('/atak/toc/api/plans/' + P.id + '/comments').then(function (j) { if (j && j.ok) { P.comments = j.comments; if (P.sideTab === 'notes') renderSide(); } });
  }

  /* ---------- Envoi en jeu, import depuis le jeu ---------- */
  function openDialog(id) { $('#' + id).hidden = false; var f = $('#' + id + ' select, #' + id + ' input, #' + id + ' button'); if (f) f.focus(); }
  function closeDialogs() { document.querySelectorAll('.toc-overlay').forEach(function (o) { o.hidden = true; }); }
  function openPublish() {
    var dlg = $('#dlg-publish');
    $('#pub-side').value = P.meta.side || 'WEST';
    $('#pub-phase').innerHTML = '<option value="">Plan entier</option>' + P.phases.map(function (p, i) { return '<option value="' + p.uid + '"' + (P.phase === p.uid ? ' selected' : '') + '>' + (i + 1) + '. ' + esc(p.name) + '</option>'; }).join('');
    var degraded = list().filter(function (e) { return ['circle', 'ellipse', 'rect'].indexOf(e.geom) >= 0 || ['icon', 'ffa', 'rfa', 'minefield'].indexOf(e.kind) >= 0 || (e.kind === 'unit' && e.props.affil !== 'F' && e.props.affil !== 'H'); }).length;
    $('#pub-info').textContent = list().length + ' élément(s) dans le plan' + (degraded ? ' · ' + degraded + ' adapté(s) au jeu (cercles et rectangles en polygones, repères en notes, couleur la plus proche des 9 du jeu)' : '') + '.';
    dlg.hidden = false;
    $('#pub-go').focus();
  }
  function doPublish() {
    var btn = $('#pub-go');
    btn.disabled = true;
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
    $('#dlg-import').hidden = false;
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
    if (!layerOf(uid)) { P.meta.layers.push({ uid: uid, name: 'Jeu ' + SIDES[$('#imp-side').value], visible: true, locked: false }); metaChanged(); }
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
    addNew(full);
  }

  /* ---------- Versions, partage, export ---------- */
  function openVersions() {
    $('#dlg-versions').hidden = false;
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
    $('#dlg-share').hidden = false;
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
    mapEl.querySelectorAll('.leaflet-tile-pane img, .toc-photo-tile').forEach(function (img) {
      if (!img.complete || !img.naturalWidth || getComputedStyle(img).opacity === '0' || getComputedStyle(img.parentNode).opacity === '0') return;
      var r = img.getBoundingClientRect();
      try { ctx.drawImage(img, r.left - rect.left, r.top - rect.top, r.width, r.height); } catch (e) { bgOk = false; }
    });
    ctx.fillStyle = 'rgba(7,10,13,.25)';
    ctx.fillRect(0, 0, rect.width, rect.height);
    var pt = function (q) { var c = tm.map.latLngToContainerPoint(tm.toLL(q)); return [c.x, c.y]; };
    list().filter(visible).forEach(function (el) {
      if (P.phase && !G.inPhase(el, P.phase)) return;
      var p = el.props;
      ctx.strokeStyle = p.color; ctx.fillStyle = p.color; ctx.lineWidth = p.width || 2;
      ctx.setLineDash(p.dash === 'dash' ? [10, 7] : (p.dash === 'dot' ? [2, 6] : []));
      if (el.geom === 'point') {
        var c = pt(el.pts[0]);
        if (el.kind === 'unit' && window.ms) {
          try { var sym = new window.ms.Symbol(G.sidc(p, CAT.unitTypes), { size: p.size || 26 }); var sc = sym.asCanvas(); var an = sym.getAnchor(); ctx.drawImage(sc, c[0] - an.x, c[1] - an.y); } catch (e) {}
        } else if (el.kind !== 'text') {
          ctx.beginPath(); ctx.arc(c[0], c[1], (p.size || 24) / 3, 0, Math.PI * 2); ctx.stroke();
        }
        var t = el.kind === 'unit' ? (p.callsign || '') : (p.label || '');
        if (t) { ctx.font = '700 ' + (el.kind === 'text' ? (p.size || 18) : 14) + 'px "Roboto Condensed", sans-serif'; ctx.lineWidth = 3; ctx.strokeStyle = '#070a0d'; ctx.setLineDash([]); var dx = el.kind === 'text' ? 0 : 18; ctx.strokeText(t, c[0] + dx, c[1] + 5); ctx.fillText(t, c[0] + dx, c[1] + 5); }
        return;
      }
      var poly = G.outline(el, 64).map(pt);
      ctx.beginPath();
      poly.forEach(function (q, i) { if (i) ctx.lineTo(q[0], q[1]); else ctx.moveTo(q[0], q[1]); });
      if (el.geom !== 'line') { ctx.closePath(); if (p.fill) { ctx.globalAlpha = p.fill; ctx.fill(); ctx.globalAlpha = 1; } }
      ctx.stroke();
      if (el.geom === 'line' && p.arrow !== 'none' && poly.length > 1) {
        var heads = p.arrow === 'both' ? [[poly[poly.length - 2], poly[poly.length - 1]], [poly[1], poly[0]]] : [[poly[poly.length - 2], poly[poly.length - 1]]];
        heads.forEach(function (ab) { var a = Math.atan2(ab[1][1] - ab[0][1], ab[1][0] - ab[0][0]); ctx.setLineDash([]); ctx.beginPath(); ctx.moveTo(ab[1][0], ab[1][1]); ctx.lineTo(ab[1][0] - 16 * Math.cos(a - 0.45), ab[1][1] - 16 * Math.sin(a - 0.45)); ctx.lineTo(ab[1][0] - 16 * Math.cos(a + 0.45), ab[1][1] - 16 * Math.sin(a + 0.45)); ctx.closePath(); ctx.fill(); });
      }
      if (p.label) { var cc = pt(G.centroid(el)); ctx.setLineDash([]); ctx.font = '700 15px "Roboto Condensed", sans-serif'; ctx.lineWidth = 3; ctx.strokeStyle = '#070a0d'; ctx.strokeText(p.label, cc[0], cc[1]); ctx.fillText(p.label, cc[0], cc[1]); }
    });
    ctx.setLineDash([]);
    ctx.fillStyle = 'rgba(7,10,13,.85)'; ctx.fillRect(0, rect.height - 34, rect.width, 34);
    ctx.fillStyle = '#e8eef2'; ctx.font = '700 15px "Roboto Condensed", sans-serif';
    var ph = P.phases.filter(function (x) { return x.uid === P.phase; })[0];
    ctx.fillText((P.plan.title + (ph ? ' · ' + ph.name : '')).toUpperCase(), 12, rect.height - 12);
    ctx.textAlign = 'right';
    ctx.font = '400 13px "JetBrains Mono", monospace';
    var center = tm.toWorld(tm.map.getCenter());
    ctx.fillText('CENTRE ' + G.grid(center[0], center[1], 8) + ' · ATHENA', rect.width - 12, rect.height - 12);
    return { canvas: cv, bgOk: bgOk };
  }
  function canvasUrl(type, quality) {
    var r = renderCanvas();
    try { return { url: r.canvas.toDataURL(type, quality), bgOk: r.bgOk }; } catch (e) {
      // Tuile sans autorisation de partage : image refaite sans fond.
      document.querySelectorAll('.leaflet-tile-pane img, .toc-photo-tile').forEach(function (i) { i.dataset.skip = '1'; });
      return { url: null, bgOk: false };
    }
  }
  function exportPng() {
    var r = canvasUrl('image/png');
    if (!r.url) { say('Export de l’image impossible : le fond de carte refuse la copie. Choisissez le fond « Sombre » puis recommencez.', true); return; }
    var a = document.createElement('a');
    a.href = r.url;
    a.download = 'plan-' + P.id + (P.phase ? '-phase' : '') + '.png';
    document.body.appendChild(a); a.click(); a.remove();
    say('Image exportée.');
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
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'plan-' + P.id + '.pdf';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 5000);
      say(r.url ? 'Fiche PDF exportée.' : 'Fiche PDF exportée (tracé sans fond de carte).');
    }).catch(function () { say('Fiche PDF indisponible pour le moment.', true); });
  }

  /* ---------- Barre du haut ---------- */
  function renderTopButtons() {
    document.querySelectorAll('[data-needs-edit]').forEach(function (b) { b.hidden = !P.canEdit; });
    document.querySelectorAll('[data-needs-admin]').forEach(function (b) { b.hidden = !P.canAdmin; });
    document.querySelectorAll('[data-needs-api]').forEach(function (b) { b.hidden = P.readonlyLink; });
    $('#plan-title').disabled = !P.canEdit;
    $('#plan-ro').hidden = P.canEdit;
    $('#plan-ro').textContent = mobile ? 'Lecture seule sur téléphone : l’éditeur s’ouvre sur tablette ou ordinateur.' : (P.readonlyLink ? 'Lien de lecture : plan en lecture seule.' : 'Lecture seule : ce plan n’est pas modifiable par vous.');
  }

  /* ---------- Clavier ---------- */
  var KEYS = { v: 'select', h: 'pan', m: 'measure', u: 'unit', t: 'text', i: 'icon', l: 'line', a: 'arrow', p: 'polygon', r: 'rect', e: 'ellipse', c: 'circle' };
  function onKey(e) {
    var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
    var k = e.key;
    if (k === 'Escape') {
      if (!document.querySelectorAll('.toc-overlay:not([hidden])').length && P.briefing) { toggleBriefing(false); return; }
      closeDialogs(); cancelDraft(); clearMeasure(); if (!typing) { P.sel = []; renderSelection(); renderSide(); }
      layers.openPanel(false);
      return;
    }
    if (typing) return;
    var mod = e.ctrlKey || e.metaKey;
    if (k === '?') { e.preventDefault(); $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); return; }
    if (P.briefing) {
      if (k === 'ArrowRight' || k === 'PageDown' || k === ' ') { e.preventDefault(); stepPhase(1); }
      if (k === 'ArrowLeft' || k === 'PageUp') { e.preventDefault(); stepPhase(-1); }
      return;
    }
    if (k === 'PageDown') { e.preventDefault(); stepPhase(1); return; }
    if (k === 'PageUp') { e.preventDefault(); stepPhase(-1); return; }
    if (mod && (k === 'z' || k === 'Z')) { e.preventDefault(); if (e.shiftKey) redo(); else undo(); return; }
    if (mod && (k === 'y' || k === 'Y')) { e.preventDefault(); redo(); return; }
    if (mod && (k === 'c' || k === 'C')) { e.preventDefault(); copySelected(); return; }
    if (mod && (k === 'v' || k === 'V')) { e.preventDefault(); paste(); return; }
    if (mod && (k === 'd' || k === 'D')) { e.preventDefault(); duplicateSelected(); return; }
    if (mod && (k === 'a' || k === 'A')) { e.preventDefault(); P.sel = list().filter(visible).map(function (x) { return x.uid; }); renderSelection(); renderSide(); return; }
    if (mod && (k === 'g' || k === 'G')) { e.preventDefault(); groupSelected(!e.shiftKey); return; }
    if (mod && k === ']') { e.preventDefault(); zOrder(true); return; }
    if (mod && k === '[') { e.preventDefault(); zOrder(false); return; }
    if (mod && (k === 's' || k === 'S')) { e.preventDefault(); flush(); return; }
    if (mod) return;
    if (k === 'Delete' || (k === 'Backspace' && !P.draft)) { e.preventDefault(); deleteSelected(); return; }
    if (k === 'Backspace' && P.draft) { e.preventDefault(); P.draft.pts.pop(); if (!P.draft.pts.length) cancelDraft(); else drawDraft(null); return; }
    if (k === 'Enter' && P.draft) { e.preventDefault(); finishDraft(); return; }
    if (k === '[' || k === ']') { editSelected(function (c) { return G.rotate(c, k === ']' ? 15 : -15); }); return; }
    if (k === '+' || k === '-') { editSelected(function (c) { return G.scale(c, k === '+' ? 1.15 : 1 / 1.15); }); return; }
    if (k === 'k' || k === 'K') { $('[data-act="lock"]') && $('[data-act="lock"]').click(); return; }
    if (k === 's' || k === 'S') { P.snap = !P.snap; var sn = $('#opt-snap'); if (sn) sn.checked = P.snap; say(P.snap ? 'Aimantation activée' : 'Aimantation coupée'); return; }
    if (k === 'g' || k === 'G') { P.grid = !P.grid; tm.setGrid(P.grid); return; }
    if (k === 'b' || k === 'B') { var bgs = tm.backgrounds, i = bgs.indexOf(tm.currentBackground()); var nx = bgs[(i + 1) % bgs.length]; tm.setBackground(nx.id); $('#plan-bg').value = nx.id; return; }
    if (k === 'j' || k === 'J') { layers.openPanel(); return; }
    if (k.indexOf('Arrow') === 0 && P.sel.length) {
      e.preventDefault();
      var step = e.shiftKey ? 100 : 10;
      var dx = k === 'ArrowLeft' ? -step : (k === 'ArrowRight' ? step : 0), dy = k === 'ArrowDown' ? -step : (k === 'ArrowUp' ? step : 0);
      editSelected(function (c) { return G.translate(c, dx, dy); });
      return;
    }
    var tool = KEYS[k.toLowerCase()];
    if (tool) setTool(tool);
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
    $('#plan-terrain').textContent = t.label;
    tm = window.TocMap.create($('#plan-map'), t, {
      onCursor: function (p) {
        $('#plan-cursor').textContent = G.grid(p[0], p[1], 8);
        if (P.draft) drawDraft(snapPoint(p));
        if (P.tool === 'measure' && P.measure && P.measure.length) drawMeasure(p);
      },
      onBackground: function (bg) { $('#plan-bg-note').textContent = bg.note; }
    });
    tm.map.createPane('planPane').style.zIndex = 430;
    tm.map.createPane('planMarkers').style.zIndex = 640;
    tm.map.createPane('planHandles').style.zIndex = 660;
    planLayer = L.layerGroup().addTo(tm.map);
    handleLayer = L.layerGroup().addTo(tm.map);
    draftLayer = L.layerGroup().addTo(tm.map);
    measureLayer = L.layerGroup().addTo(tm.map);
    var bg = 'sat';
    try { bg = window.localStorage.getItem('athena:toc:bg') || 'sat'; } catch (e) {}
    $('#plan-bg').innerHTML = tm.backgrounds.map(function (b) { return '<option value="' + b.id + '">' + esc(b.label) + '</option>'; }).join('');
    $('#plan-bg').value = bg;
    tm.setBackground(bg);
    P.grid = true;
    layers = window.TocLayers.create(tm, { base: BOOT.base, mapId: P.plan.map_id, side: P.meta.side || 'WEST', panel: $('#plan-game-layers'), mode: 'editor', catalog: CAT, onAddToPlan: addFromGameLayer, fetchPlan: function () { return Promise.resolve(null); } });
    var v = P.meta.view || {};
    var all = [];
    list().forEach(function (el) { all = all.concat(G.outline(el, 8)); });
    if (v.x != null && v.y != null) tm.centerOn([v.x, v.y], v.z != null ? v.z : undefined);
    else if (all.length) tm.fitWorld(G.bbox(all));
    tm.map.on('click', onMapClick);
    tm.map.on('dblclick', function (e) { if (P.draft) { L.DomEvent.stop(e); finishDraft(); } });
    tm.map.on('moveend', function () {
      if (!P.canEdit) return;
      clearTimeout(P.viewTimer);
      P.viewTimer = setTimeout(function () { var c = tm.toWorld(tm.map.getCenter()); P.meta.view = { x: Math.round(c[0]), y: Math.round(c[1]), z: Math.round(tm.map.getZoom()) }; metaChanged(); }, 4000);
      renderSelection();
    });
    renderTools();
    renderTopButtons();
    renderPhaseBar();
    renderAll();
    setTool('select');
    setSaveState();
    $('#plan-loading').hidden = true;
    if (!P.readonlyLink) {
      pollTimer = setInterval(function () { if (!document.hidden) poll(false); }, 2500);
      loadComments();
      setInterval(function () { if (!document.hidden) loadComments(); }, 20000);
    }
  }

  function init() {
    document.addEventListener('keydown', onKey);
    $('#plan-tools').addEventListener('click', function (e) { var b = e.target.closest('[data-tool]'); if (b) setTool(b.getAttribute('data-tool')); });
    $('#plan-tools').addEventListener('change', function (e) { if (e.target.id === 'opt-snap') P.snap = e.target.checked; });
    var side = $('#plan-side');
    side.addEventListener('input', onSideInput);
    side.addEventListener('change', onSideInput);
    side.addEventListener('click', onSideClick);
    side.addEventListener('submit', onSideSubmit);
    $('#plan-side-tabs').innerHTML = SIDE_TABS.map(function (t) { return '<button type="button" class="toc-tab" role="tab" data-side="' + t[0] + '" aria-selected="' + (P.sideTab === t[0]) + '">' + esc(t[1]) + '</button>'; }).join('');
    $('#plan-side-tabs').addEventListener('click', function (e) { var b = e.target.closest('[data-side]'); if (b) setSideTab(b.getAttribute('data-side')); });
    $('#plan-phases').addEventListener('click', function (e) {
      var b = e.target.closest('[data-phase-pick]');
      if (b) setPhase(b.getAttribute('data-phase-pick'));
      if (e.target.closest('#plan-brief-btn')) toggleBriefing();
    });
    $('#plan-title').addEventListener('change', function () { var v = this.value.trim(); if (v) saveMeta({ title: v }).then(function () { P.plan.title = v; document.title = v + ' · Athena'; }); });
    $('#plan-bg').addEventListener('change', function () { tm.setBackground(this.value); try { window.localStorage.setItem('athena:toc:bg', this.value); } catch (e) {} });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-top]');
      if (b) {
        var a = b.getAttribute('data-top');
        if (a === 'publish') openPublish();
        if (a === 'import') openImport();
        if (a === 'versions') openVersions();
        if (a === 'share') openShare();
        if (a === 'png' || a === 'pdf') { var dm = b.closest('details'); if (dm) dm.open = false; }
        if (a === 'png') exportPng();
        if (a === 'pdf') exportPdf();
        if (a === 'layers') layers.openPanel();
        if (a === 'help') { $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); }
        if (a === 'undo') undo();
        if (a === 'redo') redo();
      }
      if (e.target.closest('[data-close]') || e.target.id === 'toc-help-close') closeDialogs();
      if (e.target.closest('#pub-go')) doPublish();
      if (e.target.closest('#imp-go')) doImport();
      if (e.target.closest('#ver-save')) saveVersion();
      var rs = e.target.closest('[data-restore]');
      if (rs) restore(Number(rs.getAttribute('data-restore')));
      if (e.target.closest('#sh-save')) saveShare();
      if (e.target.closest('#sh-create')) shareLink(true);
      if (e.target.closest('#sh-revoke')) shareLink(false);
      if (e.target.closest('#sh-template')) asTemplate();
    });
    $('#imp-side').addEventListener('change', loadImportPreview);
    window.addEventListener('beforeunload', function (e) {
      if (Object.keys(P.pending).length || Object.keys(P.deletes).length) { flush(); e.preventDefault(); e.returnValue = ''; }
    });
    if (P.readonlyLink && BOOT.sharedDoc) {
      var d = BOOT.sharedDoc;
      start({ plan: { id: P.id, title: d.title, map_id: d.map_id, rev: d.rev, meta: d.meta, sharing: 'community' }, can_edit: false, can_admin: false, elements: d.elements, phases: d.phases, full: true, presence: [] });
      return;
    }
    api('/atak/toc/api/plans/' + P.id).then(function (j) {
      if (!j || !j.ok) { $('#plan-loading').textContent = ERR[j && j.error] || 'Plan indisponible pour le moment.'; return; }
      start(j);
    }).catch(function () { $('#plan-loading').textContent = 'Réseau indisponible : rechargez la page.'; });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
