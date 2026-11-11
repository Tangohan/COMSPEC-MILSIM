/* Banc de rendu de l'éditeur de plans (plan-editor-harness.php) : remplace les appels au site par un plan d'exemple,
 * un relief synthétique (plan-dem-fixture.js) et des réponses toutes faites, puis joue une scène selon ?shot=…
 * Rien ici n'est servi par le site. */
(function () {
  'use strict';
  var Q = {};
  location.search.slice(1).split('&').forEach(function (kv) { var p = kv.split('='); if (p[0]) Q[decodeURIComponent(p[0])] = decodeURIComponent(p[1] || ''); });
  var DEM = window.PlanDemFixture.make(8192, 32);
  var NOW = 1000;
  function el(uid, kind, geom, pts, props, extra) {
    var base = { color: '#f2f2f2', width: 2, dash: 'solid', arrow: 'none', fill: 0, label: '', note: '', size: 24, rotation: 0 };
    Object.keys(props || {}).forEach(function (k) { base[k] = props[k]; });
    var e = { uid: uid, kind: kind, geom: geom, pts: pts, props: base, phases: [], layer: 'base', group: '', z: ++NOW, locked: false, updated_at: NOW };
    Object.keys(extra || {}).forEach(function (k) { e[k] = extra[k]; });
    return e;
  }
  function unit(uid, callsign, at, p, extra) {
    var props = { affil: 'F', unitType: 'infantry', echelon: 'squad', callsign: callsign, size: 26, color: '#6cb8ff' };
    Object.keys(p || {}).forEach(function (k) { props[k] = p[k]; });
    if (props.affil === 'H') props.color = '#ff6b6b';
    return el(uid, 'unit', 'point', [at], props, extra);
  }
  var ELEMENTS = [
    el('ao', 'ao', 'area', [[2300, 2500], [6400, 2400], [6600, 5900], [2500, 6000]], { label: 'AO TRIDENT', dash: 'dash' }),
    el('obj', 'objective', 'circle', [[5250, 4950]], { radius: 330, color: '#f24d47', fill: 0.14, label: 'OBJ ACIER' }, { phases: ['p2', 'p3'] }),
    el('pl1', 'phase_line', 'line', [[3700, 5600], [3850, 4600], [3650, 3600], [3800, 2800]], { label: 'PL BLEU', color: '#62b0f5' }),
    el('pl2', 'phase_line', 'line', [[4600, 5700], [4700, 4700], [4520, 3700], [4640, 2900]], { label: 'PL ROUGE', color: '#f24d47' }),
    el('axe', 'axis', 'line', [[2900, 3500], [3800, 3900], [4600, 4500], [5050, 4850]], { color: '#5999ff', arrow: 'end', awidth: 260, label: 'AXE NORD' }),
    el('rte', 'route', 'line', [[2750, 4700], [3300, 4950], [4000, 5150], [4700, 5250], [5150, 5100]], { color: '#fae04d', arrow: 'end', label: 'ITI JAUNE', cp: true, speed: 12 }),
    el('sbf', 'sbf', 'line', [[4350, 3650], [5100, 4700]], { color: '#5999ff', arrow: 'end', label: 'SBF 1' }, { phases: ['p2'] }),
    el('fan', 'fan', 'point', [[4350, 3650]], { color: '#ff9433', radius: 1300, bearing: 38, arc: 50, minRange: 0, fill: 0.1, label: 'MAG', mask: true }, { phases: ['p2'] }),
    el('nai', 'nai', 'area', [[5600, 3900], [6200, 3950], [6150, 4450], [5650, 4400]], { color: '#fae04d', fill: 0.08, label: 'NAI 3' }),
    el('hlz', 'lz', 'circle', [[2950, 2950]], { radius: 180, color: '#73d973', fill: 0.1, label: 'HLZ MERLE' }),
    el('mines', 'minefield', 'rect', [[5500, 5350], [6050, 5600]], { color: '#ff9433', fill: 0.12, dash: 'dash', label: 'MINES' }),
    el('ring', 'ring', 'circle', [[2950, 3900]], { radius: 900, rings: 3, color: '#ff9433', label: 'MO 81' }, { layer: 'feux' }),
    el('cp1', 'checkpoint', 'point', [[3800, 3900]], { color: '#f2f2f2', label: 'CP 1' }),
    el('med', 'medevac', 'point', [[3050, 3050]], { color: '#f24d47', label: 'MEDEVAC' }),
    el('txt', 'textbox', 'line', [[5250, 5280], [5800, 6050]], { color: '#f2f2f2', label: 'Assaut à H+0:50' }),
    unit('cdu', 'ALPHA 6', [2950, 3600], { echelon: 'company', hq: true, unitType: 'hq', higher: '2 REI', ord: 0 }),
    unit('a1', 'ALPHA 1', [3050, 3450], { echelon: 'section', parent: 'cdu', ord: 0, track: [{ t: 0, p: [3050, 3450] }, { t: 30, p: [3900, 3950] }, { t: 55, p: [4900, 4750] }, { t: 70, p: [5200, 4900] }] }),
    unit('a2', 'ALPHA 2', [2900, 4650], { echelon: 'section', parent: 'cdu', ord: 1, route: 'rte', track: [{ t: 0, p: [2750, 4700] }, { t: 60, p: [5150, 5100] }] }),
    unit('a3', 'ALPHA 3', [4350, 3650], { echelon: 'section', parent: 'cdu', ord: 2, unitType: 'mech', mod: 'reinforced', track: [{ t: 0, p: [3200, 3300] }, { t: 25, p: [4350, 3650] }] }),
    unit('mo', 'MORTIERS', [2950, 3900], { echelon: 'squad', parent: 'cdu', ord: 3, unitType: 'mortar', info: '2 x 81' }),
    unit('res', 'RÉSERVE', [0, 0], { echelon: 'section', parent: 'cdu', ord: 4, placed: false, status: 'planned' }),
    unit('eni1', 'ENI 1', [5300, 5000], { affil: 'H', echelon: 'platoon', ord: 0 }),
    unit('eni2', 'ENI 2', [5900, 4200], { affil: 'H', echelon: 'squad', unitType: 'recon', ord: 1, status: 'planned' }),
    unit('ch', 'CHAR', [5450, 4750], { affil: 'H', echelon: '', sidc: 'S*G*EVAT--', unitType: 'unknown', ord: 2 })
  ];
  var PLAN = {
    id: 1, title: 'Opération Trident', world: 'essai', map_id: 1, sharing: 'unit', locked: false, rev: 42, share_token: null,
    meta: {
      layers: [{ uid: 'base', name: 'Manœuvre', visible: true, locked: false, opacity: 1 }, { uid: 'feux', name: 'Appuis feux', visible: true, locked: false, opacity: 1 }],
      notes: { situation: 'Section ennemie retranchée sur OBJ ACIER.', mission: 'S’emparer de OBJ ACIER pour H+1:10.', execution: '', support: '', command: '', free: '' },
      legend: [], side: 'WEST', dtg: '120500ZNOV26', view: { x: 4400, y: 4250, z: 5 },
      timeline: { h: '2026-11-12T05:00', dur: 90, start: -10, zulu: false, phases: { p1: 0, p2: 30, p3: 55 }, events: [{ uid: 'v1', t: 0, label: 'Heure H', kind: 'h', ref: '' }, { uid: 'v2', t: 25, label: 'MERLE · posé', kind: 'excheck', ref: 'E2' }, { uid: 'v3', t: 50, label: 'Tirs d’appui', kind: 'event', ref: '' }] }
    }
  };
  var PHASES = [
    { uid: 'p1', name: 'Infiltration', note: 'Mise en place sur la ligne de débouché, discrétion maximale.', time_label: 'H' },
    { uid: 'p2', name: 'Appui', note: 'ALPHA 3 en appui par le feu depuis le mouvement de terrain au sud-ouest de l’objectif.', time_label: 'H+00:30' },
    { uid: 'p3', name: 'Assaut', note: 'ALPHA 1 et ALPHA 2 s’emparent de OBJ ACIER.', time_label: 'H+00:55' }
  ];
  var readonly = Q.shot === 'ro';
  var saved = 2000;
  function json(obj, status) { return Promise.resolve(new Response(JSON.stringify(obj), { status: status || 200, headers: { 'Content-Type': 'application/json' } })); }
  var realFetch = window.fetch.bind(window);
  window.fetch = function (url, init) {
    url = String(url);
    var body = null;
    try { body = init && init.body && typeof init.body === 'string' ? JSON.parse(init.body) : null; } catch (e) {}
    if (/\/atak\/toc\/api\/terrain\/\d+\/dem/.test(url)) {
      if (Q.nodem) return json({ ok: false, error: 'relief_unavailable' }, 404);
      return Promise.resolve(new Response(DEM.heights.buffer.slice(0), { status: 200, headers: { 'Content-Type': 'application/octet-stream', 'X-Dem-Cols': String(DEM.cols), 'X-Dem-Rows': String(DEM.rows), 'X-Dem-Cell': String(DEM.cell), 'X-Dem-Origin': DEM.ox + ',' + DEM.oy, 'X-Dem-Min': String(DEM.minZ), 'X-Dem-Max': String(DEM.maxZ), 'X-Dem-World': '8192', 'X-Dem-Coverage': '100' } }));
    }
    if (/\/atak\/toc\/api\/plans\/1\/elements/.test(url)) { var s = {}; ((body && body.upserts) || []).forEach(function (e) { s[e.uid] = ++saved; }); var d = {}; ((body && body.deletes) || []).forEach(function (u) { d[u] = ++saved; }); return json({ ok: true, rev: ++PLAN.rev, saved: s, deleted: d }); }
    if (/\/atak\/toc\/api\/plans\/1\/comments/.test(url)) return json({ ok: true, comments: [{ id: 1, author: 'VIKING', created_at: '2026-11-11 18:42:00', body: 'ALPHA 3 voit-il vraiment l’objectif depuis sa position d’appui ?', element_uid: 'a3', resolved: false }] });
    if (/\/atak\/toc\/api\/plans\/1\/(phases|versions|share-link|publish|import|duplicate)/.test(url)) return json({ ok: true, versions: [], elements: [] });
    if (/\/atak\/toc\/api\/plans\/1(\?|$)/.test(url)) {
      if (body) return json({ ok: true, rev: ++PLAN.rev });
      if (/since=[1-9]/.test(url)) return json({ ok: true, elements: [], presence: [{ label: 'TTRD', tool: 'select' }, { label: 'VIKING', tool: 'line' }, { label: 'MERLIN', tool: 'select' }], server_ms: saved });
      return json({ ok: true, plan: PLAN, can_edit: !readonly, can_admin: !readonly, elements: ELEMENTS, phases: PHASES, full: true, presence: [{ label: 'TTRD', tool: 'select' }, { label: 'VIKING', tool: 'line' }, { label: 'MERLIN', tool: 'select' }], server_ms: saved });
    }
    if (/\/atak\/toc\/api\/orbat/.test(url)) return json({ ok: true, available: true, units: [{ id: 1, parent_id: null, name: '1re compagnie', short: '1 CIE', echelon: 'company' }, { id: 2, parent_id: 1, name: '1re section', short: '1 SEC', echelon: 'section' }, { id: 3, parent_id: 1, name: '2e section', short: '2 SEC', echelon: 'section' }] });
    if (/\/atak\/toc\/api\/state/.test(url)) return json({ ok: true, room: { excheck: [{ id: 'E1', n: 1, ev: 'Franchissement LD', cw: 'AIGLE', el: 'ALPHA', at: 'H+5', done: '' }] } });
    if (/\/api\/atak\/|\/api\/map-shapes|\/api\/recon/.test(url)) return json([]);
    return realFetch(url, init);
  };

  /* Fond de carte d'essai : le relief synthétique teinté, posé sous le plan. */
  function hypso() {
    var n = 768;
    var cv = document.createElement('canvas');
    cv.width = cv.height = n;
    var ctx = cv.getContext('2d');
    var img = ctx.createImageData(n, n);
    var LG = window.TocPlanLogic;
    for (var py = 0; py < n; py++) for (var px = 0; px < n; px++) {
      var x = px / n * 8192, y = (1 - py / n) * 8192;
      var z = LG.demSample(DEM, x, y) || 0;
      var zx = LG.demSample(DEM, Math.min(8150, x + 30), y) || 0, zy = LG.demSample(DEM, x, Math.min(8150, y + 30)) || 0;
      var shade = Math.max(0.55, Math.min(1.3, 1 + ((z - zx) + (zy - z)) / 30 * 0.55));
      var c = z <= 0 ? [22, 44, 62] : (z < 60 ? [74, 88, 58] : (z < 160 ? [96, 98, 64] : (z < 260 ? [112, 102, 80] : [140, 134, 122])));
      var k = (py * n + px) * 4;
      var s = z <= 0 ? 1 : shade;
      img.data[k] = c[0] * s; img.data[k + 1] = c[1] * s; img.data[k + 2] = c[2] * s; img.data[k + 3] = 255;
    }
    ctx.putImageData(img, 0, 0);
    return cv.toDataURL('image/jpeg', 0.9);
  }
  function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  document.addEventListener('DOMContentLoaded', function () {
    var E = window.TocPlanEditor;
    E.on('loaded', function () {
      var tm = E.tm;
      L.imageOverlay(hypso(), L.latLngBounds(tm.toLL([0, 0]), tm.toLL([8192, 8192])), { pane: 'tocPhoto', interactive: false }).addTo(tm.map);
      tm.setVeil(0.12);
      var shot = Q.shot || 'edit';
      wait(1300).then(function () {
        if (shot === 'edit') { E.setLeftTab('lib'); E.setSel(['a3']); }
        if (shot === 'select') { E.setLeftTab('orbat'); E.setSel(['axe', 'pl1', 'cp1', 'a1', 'cdu', 'mo']); E.$('[data-ptab="pos"]') && E.$('[data-ptab="pos"]').click(); }
        if (shot === 'shape') { E.setLeftTab('layers'); E.setSel(['nai']); E.$('[data-ptab="style"]') && E.$('[data-ptab="style"]').click(); }
        if (shot === 'timeline') { E.setLeftTab('orbat'); E.P.autoKey = true; E.setTime(42); E.setSel(['a1']); E.$('[data-ptab="time"]').click(); }
        if (shot === 'palette') { E.setSel(['obj']); E.openPalette('alp'); }
        if (shot === 'menu') { E.setSel(['obj']); E.emit('context', { x: 900, y: 420, world: [5250, 4950], uid: 'obj' }); }
        if (shot === 'profile') { E.setTool('profile'); [[2950, 3600], [3900, 3950], [4900, 4750], [5250, 4950]].forEach(function (p) { E.mapClick(p, {}); }); }
        if (shot === 'viewshed') { E.terrainTool('viewshed', [4350, 3650]); }
        if (shot === 'los2d') { E.setTool('los'); E.mapClick([4350, 3650], {}); E.mapClick([5250, 4950], {}); }
        if (shot === 'brief') { E.setBriefing(true); E.setPhase('p2'); }
        if (shot === '3d' || shot === 'los') {
          E.setPhase('p2');
          E.open3d();
          return wait(2500).then(function () {
            if (!E.view3d) return;
            E.view3d.setView({ x: Number(Q.x) || 4500, y: Number(Q.y) || 4300, mpp: Number(Q.mpp) || 2.6, bearing: Q.bearing != null ? Number(Q.bearing) : 28, pitch: Q.pitch != null ? Number(Q.pitch) : 34 });
            if (shot === 'los') { E.setTool('los'); E.mapClick([4350, 3650], {}); E.mapClick([5250, 4950], {}); }
            else E.setSel(['a3']);
            return wait(400);
          });
        }
      }).then(function () { return wait(600); }).then(function () { document.title = 'PRET ' + document.title; });
    });
  });
  window.__harness = { DEM: DEM, ELEMENTS: ELEMENTS, Q: Q };
})();
