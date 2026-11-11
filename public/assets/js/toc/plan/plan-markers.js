/* Éditeur de plans : Importer › Marqueurs présents en jeu. Lit les marqueurs que le site connaît pour le terrain du
 * plan (les mêmes que le calque « Marqueurs » du poste TOC web : /api/atak/markers, et les points ATAK de
 * /api/atak/waypoints), avec la même conversion marqueur Arma -> symbole (TocTracks.markerRecord, source unique).
 * Volet posé à droite, la carte reste visible : liste groupée, recherche, filtres, aperçu sur la carte, calque et phase
 * de destination, marqueurs déjà importés (mettre à jour, ignorer, dupliquer), lien avec le jeu facultatif.
 * Tout l'import est une seule opération (un seul Ctrl+Z). Se branche sur window.TocPlanEditor. */
(function () {
  'use strict';
  var E = window.TocPlanEditor;
  if (!E) return;
  var P = E.P, G = E.G, R = E.R, LG = E.LG, $ = E.$, esc = E.esc;
  var T = window.TocTracks;
  var KIND_LABEL = { unit: 'Unités (marqueurs OTAN)', icon: 'Icônes', point: 'Points ATAK', area: 'Zones', line: 'Tracés' };
  var CHANNEL_LABEL = { 0: 'Canal global', 1: 'Canal du camp', 2: 'Canal de commandement', 3: 'Canal du groupe', 4: 'Canal du véhicule', 5: 'Canal direct', '?': 'Canal non précisé par le jeu' };
  var SIDE_LABEL = { WEST: 'BLUFOR', EAST: 'OPFOR', GUER: 'INDFOR', CIV: 'Civils', '?': 'Camp non précisé par le jeu' };
  var SYSTEM_LABEL = { respawn: 'réapparition', module: 'module de mission', empty: 'marqueur vide' };
  var WP_KIND = { RALLY: 'rally', CHECKPOINT: 'checkpoint', OBJECTIVE: 'objpt', TARGET: 'target' };
  var layer = null;
  var M = { open: false, state: 'loading', recs: [], game: null, checked: {}, by: 'kind', q: '', system: true, side: false, view: false, mode: 'update', link: false, layer: '', phase: '' };

  function get(path) {
    return fetch(E.BOOT.base + path, { credentials: 'same-origin', cache: 'no-cache', headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }
  function sym() { return (E.BOOT && E.BOOT.symbols) || {}; }
  function planWorld() { return (P.terrain && P.terrain.world) || (P.plan && P.plan.world) || ''; }
  /** Couleur du jeu ramenée à notre palette ; le noir d'Arma, illisible sur la console sombre, devient blanc. */
  function palette(hex) { var k = G.nearestGameColor(hex); return G.GAME_COLORS[k === 'BLACK' ? 'WHITE' : k] || hex; }

  /** Points ATAK (waypoints) : mêmes fiches que les marqueurs. */
  function pointRecord(w) {
    var p = T.posOf(w.pos_x, w.pos_y);
    if (!p) return null;
    var type = String(w.waypoint_type || 'CHECKPOINT').toUpperCase();
    var kind = WP_KIND[type] || 'checkpoint';
    var def = E.KINDS[kind] || {};
    var label = String(w.label || '').trim();
    return { id: 'wp' + w.id, gid: 'atak-wp-' + w.id, shape: 'ICON', label: label, type: type.toLowerCase(), color: def.color || '#f2f2f2', side: '', source: 'point', channel: '', system: '', pos: p, ts: w.updated_at || null, hatch: '',
      el: { geom: 'point', kind: kind, pts: [p], props: { color: def.color || '#f2f2f2', label: label.slice(0, 60) || def.text || '', size: 24 } } };
  }
  function records(markers, points) {
    var recs = [];
    (Array.isArray(markers) ? markers : []).forEach(function (row) { var r = T.markerRecord(row, sym(), G); if (r) recs.push(r); });
    ((points && points.waypoints) || []).forEach(function (w) { var r = pointRecord(w); if (r) recs.push(r); });
    return recs;
  }
  function load() {
    M.state = 'loading';
    render();
    var mapId = P.plan.map_id;
    Promise.all([get('/atak/toc/api/state?mapId=' + mapId + '&side=' + (P.meta.side || 'WEST')), get('/api/atak/markers?mapId=' + mapId), get('/api/atak/waypoints?mapId=' + mapId)]).then(function (res) {
      if (!M.open) return;
      if (res[1] === null && res[0] === null) { M.state = 'error'; render(); return; }
      M.game = (res[0] && res[0].game) || null;
      M.recs = records(res[1], res[2]);
      M.state = LG.importState(M.game, planWorld(), M.recs.length);
      // Au départ : tout ce que les filtres montrent est coché.
      M.checked = {};
      shown().forEach(function (r) { M.checked[r.gid] = true; });
      render();
      preview();
    });
  }
  function filter() {
    var f = { system: M.system, side: M.side ? (P.meta.side || 'WEST') : '', q: M.q };
    if (M.view && E.tm) { var b = E.tm.map.getBounds(); var sw = E.tm.toWorld(b.getSouthWest()), ne = E.tm.toWorld(b.getNorthEast()); f.box = [sw[0], sw[1], ne[0], ne[1]]; }
    return f;
  }
  function shown() { return LG.markerFilter(M.recs, filter()); }
  function picked() { return shown().filter(function (r) { return M.checked[r.gid]; }); }
  function known() {
    var by = {};
    E.list().forEach(function (e) { if (e.props.gid) by[e.props.gid] = e; });
    return by;
  }
  function plan() {
    return LG.markerPlan(picked(), E.list(), { mode: M.mode, link: M.link, layer: M.layer || E.activeLayer(), phase: M.phase, z: E.maxZ(), color: palette });
  }
  function preview() {
    if (!layer || !M.open || P.mode !== '2d' || M.state === 'loading') return;
    var pl = plan();
    layer.clearLayers();
    pl.add.concat(Object.keys(pl.update).map(function (u) { return pl.update[u]; })).forEach(function (el) {
      layer.addLayer(R.render(E.tm, el, { catalog: E.CAT, pane: 'planHandles', markerPane: 'planHandles', interactive: false, opacity: 0.92 }));
    });
  }
  function thumb(r) {
    if (!r.el) return '';
    if (r.el.geom === 'point') {
      var props = {};
      Object.keys(r.el.props).forEach(function (k) { props[k] = r.el.props[k]; });
      props.label = ''; props.callsign = ''; props.size = r.el.kind === 'unit' ? 20 : 22;
      if (r.el.kind !== 'unit') props.color = palette(props.color);
      return R.pointHtml({ kind: r.el.kind, geom: 'point', pts: [[0, 0]], props: props }, { catalog: E.CAT }).html;
    }
    var c = palette(r.color);
    if (r.el.geom === 'line') return '<svg width="34" height="24" viewBox="0 0 34 24" fill="none" stroke="' + c + '" stroke-width="2" stroke-linecap="round"><path d="M3 19C10 19 12 6 ' + (r.el.kind === 'arrow' ? '25 6' : '31 6') + '"/>' + (r.el.kind === 'arrow' ? '<path d="M24 2l7 4-7 4z" fill="' + c + '"/>' : '') + '</svg>';
    var fill = ' fill="' + c + '" fill-opacity="' + Math.max(0.12, Math.min(0.6, (r.el.props.fill || 0) + 0.1)) + '"';
    var dash = r.hatch ? ' stroke-dasharray="4 3"' : '';
    return '<svg width="34" height="24" viewBox="0 0 34 24" stroke="' + c + '" stroke-width="2">' + (r.shape === 'ELLIPSE' ? '<ellipse cx="17" cy="12" rx="14" ry="8"' + fill + dash + '/>' : '<rect x="3" y="4" width="28" height="16"' + fill + dash + '/>') + '</svg>';
  }
  function groupLabel(key) {
    if (M.by === 'channel') return CHANNEL_LABEL[key] || ('Canal ' + key);
    if (M.by === 'side') return SIDE_LABEL[key] || key;
    return KIND_LABEL[key] || key;
  }
  function stateBox(title, text) {
    return '<div class="toc-empty pe-mk-state"><h3>' + esc(title) + '</h3><p>' + esc(text) + '</p><button type="button" class="toc-btn" data-mk="reload">Relire les marqueurs</button></div>';
  }
  function render() {
    var box = $('#pe-markers');
    if (!box) return;
    box.hidden = !M.open;
    if (!M.open) return;
    var keep = box.querySelector('.pe-mk-list') ? box.querySelector('.pe-mk-list').scrollTop : 0;
    var h = '<div class="toc-sec"><h2 id="pe-mk-title">Marqueurs présents en jeu</h2><span><button type="button" class="toc-btn is-small" data-mk="close">Fermer</button></span></div>';
    var body = '';
    if (M.state === 'loading') body = '<div class="pe-mk-state"><div class="pe-skel"></div><div class="pe-skel"></div><div class="pe-skel"></div><p class="toc-note" role="status">Lecture des marqueurs du jeu…</p></div>';
    else if (M.state === 'error') body = stateBox('Marqueurs indisponibles', 'Le site n’a pas répondu. Réessayez dans un instant.');
    else if (M.state === 'mismatch') body = stateBox('Terrain de la partie différent de celui du plan', 'La partie en liaison se joue sur « ' + (M.game.world || '?') + ' », ce plan est dessiné sur « ' + planWorld() + ' ». Ouvrez un plan du même terrain pour importer ses marqueurs.');
    else if (M.state === 'nolink') body = stateBox('Aucune partie en liaison', 'Aucune partie reliée à Athena n’envoie de marqueurs pour ce terrain en ce moment. Les marqueurs arrivent dès qu’un joueur du camp est en liaison.');
    else if (M.state === 'empty') body = stateBox('Aucun marqueur', 'La partie est en liaison, mais aucun marqueur n’est posé sur la carte du jeu pour ce terrain.');
    else body = listHtml();
    box.innerHTML = h + body;
    var list = box.querySelector('.pe-mk-list');
    if (list) list.scrollTop = keep;
    var q = $('#pe-mk-q');
    if (q && M.focusQ) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
    M.focusQ = false;
  }
  function listHtml() {
    var all = M.recs, vis = shown(), by = known();
    var nChecked = picked().length;
    var nSystem = all.filter(function (r) { return r.system; }).length;
    var h = '<p class="pe-mk-link"><span class="toc-dot ' + (M.state === 'stale' ? 'is-old' : 'is-live') + '"></span>' + (M.state === 'stale' ? 'Aucune partie en liaison : marqueurs du dernier relevé reçu.' : 'Partie en liaison sur ce terrain.') + ' <b class="mono">' + all.length + '</b> marqueur(s) connu(s), <b class="mono">' + vis.length + '</b> affiché(s).</p>';
    h += '<div class="pe-mk-tools"><label class="pe-search">' + E.icon('search', 15) + '<span class="toc-sr">Chercher un marqueur</span><input type="search" id="pe-mk-q" class="toc-input" placeholder="Chercher un marqueur…" value="' + esc(M.q) + '" autocomplete="off"></label>' +
      '<span class="pe-seg" role="group" aria-label="Grouper par">' + [['kind', 'Genre'], ['channel', 'Canal'], ['side', 'Camp']].map(function (o) { return '<button type="button" data-mk-by="' + o[0] + '" aria-pressed="' + (M.by === o[0]) + '">' + o[1] + '</button>'; }).join('') + '</span></div>';
    h += '<div class="pe-mk-filters"><label class="pe-check"><input type="checkbox" data-mk-f="system"' + (M.system ? ' checked' : '') + '><span>Ignorer les marqueurs de service (' + nSystem + ')</span></label>' +
      '<label class="pe-check"><input type="checkbox" data-mk-f="side"' + (M.side ? ' checked' : '') + '><span>Mon camp seulement (' + esc(E.SIDES[P.meta.side || 'WEST']) + ')</span></label>' +
      '<label class="pe-check"><input type="checkbox" data-mk-f="view"' + (M.view ? ' checked' : '') + '><span>Dans la vue affichée seulement</span></label></div>';
    h += '<div class="pe-mk-all"><label class="pe-check"><input type="checkbox" data-mk="all"' + (vis.length && nChecked === vis.length ? ' checked' : '') + (vis.length ? '' : ' disabled') + '><span>Tout cocher</span></label><span class="toc-muted"><b class="mono">' + nChecked + '</b> coché(s)</span></div>';
    h += '<div class="pe-mk-list" role="group" aria-label="Marqueurs trouvés">';
    if (!vis.length) h += '<div class="toc-empty"><p>Aucun marqueur ne correspond à ces filtres.</p></div>';
    LG.markerGroups(vis, M.by).forEach(function (g) {
      var on = g.items.filter(function (r) { return M.checked[r.gid]; }).length;
      h += '<div class="pe-mk-group"><label class="pe-check"><input type="checkbox" data-mk-group="' + esc(g.key) + '"' + (on === g.items.length ? ' checked' : '') + '><span>' + esc(groupLabel(g.key)) + '</span></label><small class="mono">' + on + ' / ' + g.items.length + '</small></div>';
      g.items.forEach(function (r) {
        var sub = [r.type || (r.shape === 'ELLIPSE' ? 'ellipse' : (r.shape === 'RECTANGLE' ? 'rectangle' : 'tracé')), G.grid(r.pos[0], r.pos[1], 8)];
        if (r.source === 'web') sub.push('posé sur le web');
        if (r.system) sub.push(SYSTEM_LABEL[r.system] || 'service');
        h += '<div class="pe-mk-row' + (by[r.gid] ? ' is-known' : '') + '"><label class="pe-mk-pick"><input type="checkbox" data-mk-id="' + esc(r.gid) + '"' + (M.checked[r.gid] ? ' checked' : '') + '><span class="pe-mk-ic">' + thumb(r) + '</span><span class="pe-mk-txt"><b>' + esc(r.label || 'Sans texte') + '</b><small class="mono">' + esc(sub.join(' · ')) + '</small></span></label>' +
          (by[r.gid] ? '<span class="toc-pill is-amber" title="Ce marqueur a déjà été importé dans ce plan">déjà importé</span>' : '') +
          '<button type="button" class="pe-node-btn is-keep" data-mk-go="' + esc(r.gid) + '" title="Voir sur la carte" aria-label="Voir sur la carte">' + E.icon('pin', 15) + '</button></div>';
      });
    });
    h += '</div>';
    var pl = plan();
    var nUpd = Object.keys(pl.update).length;
    h += '<div class="pe-mk-foot"><div class="pe-row2"><label class="pe-f"><span>Calque de destination</span><select class="toc-select" data-mk-set="layer">' + (P.meta.layers || []).map(function (l) { return '<option value="' + l.uid + '"' + ((M.layer || E.activeLayer()) === l.uid ? ' selected' : '') + (l.locked ? ' disabled' : '') + '>' + esc(l.name) + '</option>'; }).join('') + '</select></label>' +
      '<label class="pe-f"><span>Phase</span><select class="toc-select" data-mk-set="phase"><option value="">Toutes les phases</option>' + P.phases.map(function (ph, i) { return '<option value="' + ph.uid + '"' + (M.phase === ph.uid ? ' selected' : '') + '>' + (i + 1) + '. ' + esc(ph.name) + '</option>'; }).join('') + '</select></label></div>';
    if (pl.known) h += '<label class="pe-f"><span>' + pl.known + ' marqueur(s) coché(s) déjà importé(s) dans ce plan</span><select class="toc-select" data-mk-set="mode"><option value="update"' + (M.mode === 'update' ? ' selected' : '') + '>Mettre à jour l’élément déjà importé</option><option value="skip"' + (M.mode === 'skip' ? ' selected' : '') + '>Ne pas y toucher</option><option value="duplicate"' + (M.mode === 'duplicate' ? ' selected' : '') + '>Importer en double</option></select></label>';
    h += '<label class="pe-check"><input type="checkbox" data-mk-f="link"' + (M.link ? ' checked' : '') + '><span>Garder le lien avec le jeu</span></label>';
    h += '<p class="toc-note">Sans lien, c’est une copie : elle ne bouge plus quand le marqueur change en jeu. Avec le lien, « Actualiser les marqueurs liés » (Ctrl+K) la remet à jour. L’aperçu est sur la carte ; l’import s’annule d’un seul Ctrl+Z.</p>';
    h += '<div class="pe-stack is-row"><button type="button" class="toc-btn" data-mk="fit"' + (nChecked ? '' : ' disabled') + '>Voir sur la carte</button><span class="pe-grow"></span><button type="button" class="toc-btn is-primary" data-mk="go"' + (pl.add.length + nUpd ? '' : ' disabled') + '>Importer ' + (pl.add.length + nUpd) + ' marqueur(s)</button></div></div>';
    return h;
  }
  function open() {
    if (!P.canEdit) { E.say('Lecture seule.', true); return; }
    if (P.mode === '3d' && E.close3d) E.close3d();
    M.open = true;
    M.layer = E.activeLayer();
    M.phase = P.phase && P.assignPhase ? P.phase : '';
    document.body.classList.add('pe-markers-open');
    load();
    setTimeout(function () { var b = $('#pe-markers [data-mk="close"]'); if (b) b.focus(); }, 30);
  }
  function close() {
    if (!M.open) return;
    M.open = false;
    document.body.classList.remove('pe-markers-open');
    if (layer) layer.clearLayers();
    render();
  }
  function doImport() {
    var pl = plan();
    var map = {};
    pl.add.forEach(function (e) { map[e.uid] = e; });
    Object.keys(pl.update).forEach(function (u) { map[u] = pl.update[u]; });
    var n = Object.keys(map).length;
    if (!n) return;
    var max = (E.CAT.limits && E.CAT.limits.elements) || 1500;
    if (pl.add.length > max - E.list().length) { E.say('Trop d’éléments : le plan en accepte ' + max + ' au plus.', true); return; }
    var nUpd = Object.keys(pl.update).length;
    close();
    E.commit(map, pl.add.length + ' marqueur(s) du jeu importé(s)' + (nUpd ? ', ' + nUpd + ' mis à jour' : '') + (pl.skipped ? ', ' + pl.skipped + ' laissé(s)' : '') + ' · Ctrl+Z pour tout annuler');
    E.setSel(Object.keys(map));
    E.emit('markersImported', n);
  }
  /** Remet à jour, depuis le jeu, les éléments importés avec le lien gardé. */
  function sync() {
    var linked = E.list().filter(function (e) { return e.props.gid && e.props.glink; });
    if (!linked.length) { E.say('Aucun élément de ce plan n’a gardé le lien avec le jeu.'); return; }
    E.say('Lecture des marqueurs du jeu…');
    Promise.all([get('/api/atak/markers?mapId=' + P.plan.map_id), get('/api/atak/waypoints?mapId=' + P.plan.map_id)]).then(function (res) {
      var ids = {};
      linked.forEach(function (e) { ids[e.props.gid] = true; });
      var pl = LG.markerPlan(records(res[0], res[1]).filter(function (r) { return ids[r.gid]; }), linked.filter(E.editable), { mode: 'update', link: true, color: palette });
      var changed = {};
      Object.keys(pl.update).forEach(function (u) { if (JSON.stringify(pl.update[u].pts) !== JSON.stringify(P.els[u].pts) || JSON.stringify(pl.update[u].props) !== JSON.stringify(P.els[u].props)) changed[u] = pl.update[u]; });
      var n = Object.keys(changed).length;
      if (!n) { E.say('Les ' + linked.length + ' élément(s) lié(s) sont déjà à jour.'); return; }
      E.commit(changed, n + ' élément(s) lié(s) remis à jour depuis le jeu · Ctrl+Z pour annuler');
    });
  }

  E.action('importMarkers', 'Importer les marqueurs présents en jeu', open, { edit: true, group: 'Plan' });
  E.action('syncMarkers', 'Actualiser les marqueurs liés au jeu', sync, { edit: true, group: 'Plan' });
  E.on('init', function () {
    var box = document.createElement('aside');
    box.className = 'pe-markers';
    box.id = 'pe-markers';
    box.hidden = true;
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-labelledby', 'pe-mk-title');
    $('#pe').appendChild(box);
    box.addEventListener('click', function (e) {
      var t = e.target.closest('button');
      if (!t) return;
      var a = t.getAttribute('data-mk');
      if (a === 'close') close();
      if (a === 'reload') load();
      if (a === 'go') doImport();
      if (a === 'fit') { var pts = picked().map(function (r) { return r.pos; }); if (pts.length) { var b = G.bbox(pts); E.tm.fitWorld([b[0] - 150, b[1] - 150, b[2] + 150, b[3] + 150]); } }
      if (t.hasAttribute('data-mk-by')) { M.by = t.getAttribute('data-mk-by'); render(); }
      if (t.hasAttribute('data-mk-go')) { var r = M.recs.filter(function (x) { return x.gid === t.getAttribute('data-mk-go'); })[0]; if (r) E.tm.centerOn(r.pos); }
    });
    box.addEventListener('change', function (e) {
      var t = e.target;
      if (t.hasAttribute('data-mk-id')) M.checked[t.getAttribute('data-mk-id')] = t.checked;
      else if (t.getAttribute('data-mk') === 'all') shown().forEach(function (r) { M.checked[r.gid] = t.checked; });
      else if (t.hasAttribute('data-mk-group')) LG.markerGroups(shown(), M.by).forEach(function (g) { if (g.key === t.getAttribute('data-mk-group')) g.items.forEach(function (r) { M.checked[r.gid] = t.checked; }); });
      else if (t.hasAttribute('data-mk-f')) { var k = t.getAttribute('data-mk-f'); M[k] = t.checked; if (k !== 'link') shown().forEach(function (r) { if (M.checked[r.gid] === undefined) M.checked[r.gid] = true; }); }
      else if (t.hasAttribute('data-mk-set')) M[t.getAttribute('data-mk-set')] = t.value;
      else return;
      render();
      preview();
    });
    box.addEventListener('input', function (e) {
      if (e.target.id !== 'pe-mk-q') return;
      M.q = e.target.value;
      M.focusQ = true;
      render();
      preview();
    });
    var prevEsc = E.escape;
    E.escape = function () {
      if (prevEsc && prevEsc()) return true;
      if (M.open) { close(); return true; }
      return false;
    };
  });
  E.on('loaded', function () { layer = L.layerGroup().addTo(E.tm.map); });
  E.on('view', function () { if (M.open && M.view) render(); if (M.open) preview(); });
  E.markers = { open: open, close: close, state: M };
})();
