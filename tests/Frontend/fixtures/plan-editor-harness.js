/* Banc d'essai de l'éditeur de plans (plan-backend.php) : le plan est tenu par les vrais services du site sur une base
 * jetable ; ici ne sont remplacés que les appels qui demandent le jeu ou le reste du site (relief synthétique de
 * plan-dem-fixture.js, salle du poste, marqueurs et calques du jeu, ORBAT de la communauté) et le fond de carte.
 * Paramètres de la page : ?nodem=1 (terrain sans relief), ?nolink=1 (aucune partie en liaison), ?world=autre (partie
 * sur un autre terrain), ?nomarkers=1. Rien ici n'est servi par le site. */
(function () {
  'use strict';
  var Q = {};
  location.search.slice(1).split('&').forEach(function (kv) { var p = kv.split('='); if (p[0]) Q[decodeURIComponent(p[0])] = decodeURIComponent(p[1] || ''); });
  var DEM = window.PlanDemFixture.make(8192, 32);
  function json(obj, status) { return Promise.resolve(new Response(JSON.stringify(obj), { status: status || 200, headers: { 'Content-Type': 'application/json' } })); }
  var now = function () { return new Date().toISOString().slice(0, 19).replace('T', ' '); };
  // Marqueurs tels que /api/atak/markers les rend : icônes Arma, marqueurs OTAN, zones avec pinceau, tracés, marqueurs système.
  var MARKERS = [
    { id: 101, d: { id: '_USER_DEFINED #2/1/1', type: 'mil_objective', color: 'ColorRed', text: 'OBJ ACIER', pos: [5250, 4950, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'WEST', channel: 1 } },
    { id: 102, d: { id: '_USER_DEFINED #2/2/1', type: 'b_inf', color: 'ColorBLUFOR', text: 'ALPHA 1', pos: [3050, 3450, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'WEST', channel: 1 } },
    { id: 103, d: { id: '_USER_DEFINED #2/3/1', type: 'o_armor', color: 'ColorOPFOR', text: 'T-72 x2', pos: [5450, 4750, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'WEST', channel: 1 } },
    { id: 104, d: { id: 'zone_nord', type: '', color: 'ColorOrange', text: 'ZONE MINÉE', pos: [5780, 5480, 0], shape: 'RECTANGLE', size: [280, 130], dir: 12, brush: 'DiagGrid', alpha: 0.8, side: 'WEST' } },
    { id: 105, d: { id: 'ea_1', type: '', color: 'ColorRed', text: 'EA TIGRE', pos: [5900, 4200, 0], shape: 'ELLIPSE', size: [320, 210], dir: 0, brush: 'SolidBorder', alpha: 0.6, side: 'WEST' } },
    { id: 106, d: { id: '_USER_DEFINED #3/1/1', type: 'mil_arrow2', color: 'ColorBlue', text: '', pos: [2900, 3500, 0], shape: 'POLYLINE', polyline: [2900, 3500, 3400, 3700, 3800, 3900, 4300, 4250], side: 'WEST', channel: 1 } },
    { id: 107, d: { id: '_USER_DEFINED #4/1/3', type: 'hd_warning', color: 'ColorYellow', text: 'IED ?', pos: [4050, 5120, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'WEST', channel: 3 } },
    { id: 108, d: { id: '_USER_DEFINED #5/1/1', type: 'mil_flag', color: 'ColorGreen', text: 'PRV', pos: [2700, 2800, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'EAST', channel: 1 } },
    { id: 109, d: { id: 'respawn_west', type: 'respawn_inf', color: 'ColorBLUFOR', text: '', pos: [2600, 2700, 0], shape: 'ICON', size: [1, 1], dir: 0 } },
    { id: 110, d: { id: 'BIS_module_3', type: 'Empty', color: 'Default', text: '', pos: [4100, 4100, 0], shape: 'ICON', size: [1, 1], dir: 0 } },
    { id: 111, d: { id: 'web_7', source: 'web', type: 'mil_dot', color: 'ColorWhite', text: 'PC AVANT', pos: [3320, 3180, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'WEST', owner_user_id: 1 } },
    { id: 112, d: { id: 'loin', type: 'mil_dot', color: 'ColorBlack', text: 'DÉPÔT', pos: [7600, 900, 0], shape: 'ICON', size: [1, 1], dir: 0, side: 'WEST' } }
  ];
  var realFetch = window.fetch.bind(window);
  window.__calls = [];
  window.fetch = function (url, init) {
    url = String(url);
    window.__calls.push((init && init.method || 'GET') + ' ' + url.replace(/^https?:\/\/[^/]+/, ''));
    if (/\/atak\/toc\/api\/terrain\/\d+\/dem/.test(url)) {
      if (Q.nodem) return json({ ok: false, error: 'relief_unavailable' }, 404);
      return Promise.resolve(new Response(DEM.heights.buffer.slice(0), { status: 200, headers: { 'Content-Type': 'application/octet-stream', 'X-Dem-Cols': String(DEM.cols), 'X-Dem-Rows': String(DEM.rows), 'X-Dem-Cell': String(DEM.cell), 'X-Dem-Origin': DEM.ox + ',' + DEM.oy, 'X-Dem-Min': String(DEM.minZ), 'X-Dem-Max': String(DEM.maxZ), 'X-Dem-World': '8192', 'X-Dem-Coverage': '100' } }));
    }
    if (/\/atak\/toc\/api\/orbat/.test(url)) return json({ ok: true, available: true, units: [{ id: 1, parent_id: null, name: '1re compagnie', short: '1 CIE', echelon: 'company' }, { id: 2, parent_id: 1, name: '1re section', short: '1 SEC', echelon: 'section' }, { id: 3, parent_id: 1, name: '2e section', short: '2 SEC', echelon: 'section' }] });
    if (/\/atak\/toc\/api\/state/.test(url)) return json({ ok: true, mapId: 1, side: 'WEST', now: Math.floor(Date.now() / 1000), state: { excheck: [{ id: 'E1', n: 1, ev: 'Franchissement LD', cw: 'AIGLE', el: 'ALPHA', at: 'H+5', done: '' }, { id: 'E2', n: 2, ev: 'Posé', cw: 'MERLE', el: 'HELO', at: '06:10', done: '' }] }, game: { live: !Q.nolink, seen_at: Q.nolink ? null : Math.floor(Date.now() / 1000) - 4, world: Q.nolink ? '' : (Q.world || 'essai'), mission_key: 'essai', rev: 12, pending: 0 } });
    if (/\/api\/atak\/markers/.test(url)) return json(Q.nomarkers || Q.nolink ? [] : MARKERS.map(function (m) { return { id: m.id, markerData: m.d, updated_at: now() }; }));
    if (/\/api\/atak\/waypoint-routes/.test(url)) return json({ routes: [] });
    if (/\/api\/atak\/waypoints/.test(url)) return json({ waypoints: Q.nolink || Q.nomarkers ? [] : [{ id: 31, route_id: 0, sequence_number: 1, pos_x: 3600, pos_y: 4400, label: 'RV 1', waypoint_type: 'RALLY', updated_at: now() }] });
    if (/\/api\/atak\/(zones|briefing-plan)/.test(url)) return json({ zones: [], plans: [] });
    if (/\/api\/atak\/|\/api\/map-shapes|\/api\/recon|\/api\/pings|\/api\/chat/.test(url)) return json([]);
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
  window.addEventListener('unhandledrejection', function (e) { if (window.console) console.error('promesse rejetée : ' + (e.reason && (e.reason.stack || e.reason.message) || e.reason)); });
  document.addEventListener('DOMContentLoaded', function () {
    var E = window.TocPlanEditor;
    if (!E) return;
    E.on('loaded', function () {
      L.imageOverlay(hypso(), L.latLngBounds(E.tm.toLL([0, 0]), E.tm.toLL([8192, 8192])), { pane: 'tocPhoto', interactive: false }).addTo(E.tm.map);
      E.tm.setVeil(0.12);
      window.__ready = true;
    });
  });
  window.__harness = { DEM: DEM, MARKERS: MARKERS, Q: Q };
})();
