const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const G = require(path.join(__dirname, '../../public/assets/js/toc/toc-geo.js'));
const L = require(path.join(__dirname, '../../public/assets/js/toc/plan/plan-logic.js'));

const pt = (uid, x, y, props = {}, extra = {}) => Object.assign({uid, kind: 'unit', geom: 'point', pts: [[x, y]], props: Object.assign({affil: 'F'}, props), z: 1, phases: [], layer: 'base', group: ''}, extra);
const line = (uid, pts, props = {}) => ({uid, kind: 'line', geom: 'line', pts, props, z: 2, phases: [], layer: 'base', group: ''});
const area = (uid, pts, props = {}) => ({uid, kind: 'polygon', geom: 'area', pts, props, z: 0, phases: [], layer: 'base', group: ''});
const near = (a, b, tol = 0.01) => assert.ok(Math.abs(a - b) <= tol, `${a} ≈ ${b}`);

test('cadre de sélection : point dedans, trait qui traverse, zone qui entoure le cadre', () => {
  const box = L.normBox([100, 100], [0, 0]);
  assert.deepEqual(box, [0, 0, 100, 100]);
  assert.equal(L.boxHit(pt('a', 50, 50), box), true);
  assert.equal(L.boxHit(pt('b', 150, 50), box), false);
  assert.equal(L.boxHit(line('l', [[-50, 50], [150, 50]]), box), true, 'traverse sans sommet dedans');
  assert.equal(L.boxHit(line('m', [[-50, 150], [150, 150]]), box), false);
  assert.equal(L.boxHit(area('z', [[-500, -500], [500, -500], [500, 500], [-500, 500]]), box), true, 'la zone contient le cadre');
  assert.equal(L.boxHit({uid: 'c', kind: 'circle', geom: 'circle', pts: [[300, 300]], props: {radius: 50}}, box), false);
});

test('lasso : polygone quelconque', () => {
  const lasso = [[0, 0], [200, 0], [100, 150]];
  assert.equal(L.polyHit(pt('a', 100, 50), lasso), true);
  assert.equal(L.polyHit(pt('b', 10, 140), lasso), false);
  assert.equal(L.polyHit(line('l', [[90, 40], [110, 60]]), lasso), true);
});

test('pile sous le curseur : un symbole passe devant la zone, Alt+clic fait tourner', () => {
  const els = [area('zone', [[0, 0], [100, 0], [100, 100], [0, 100]], {fill: 0.2}), pt('u1', 50, 50, {}, {z: 5}), pt('u2', 52, 50, {}, {z: 9})];
  const stack = L.pickStack(els, [51, 50], 10);
  assert.deepEqual(stack, ['u2', 'u1', 'zone']);
  assert.equal(L.cyclePick(stack, 'u2'), 'u1');
  assert.equal(L.cyclePick(stack, 'zone'), 'u2');
  assert.equal(L.cyclePick(stack, null), 'u2');
  assert.equal(L.cyclePick([], 'x'), null);
});

test('aligner et répartir', () => {
  const els = [pt('a', 0, 0), pt('b', 40, 100), pt('c', 100, 30)];
  const left = L.align(els, 'left');
  assert.deepEqual([left.a, left.b, left.c], [[0, 0], [-40, 0], [-100, 0]]);
  const top = L.align(els, 'top');
  assert.deepEqual(top.a, [0, 100]);
  const d = L.distribute(els, 'h');
  assert.deepEqual(d.b, [10, 0], 'le milieu va à 50');
  assert.deepEqual(d.a, [0, 0]);
  assert.deepEqual(L.distribute(els.slice(0, 2), 'h').a, [0, 0], 'moins de trois éléments : rien ne bouge');
});

test('presse-papiers : aller-retour, plan JSON, GeoJSON, texte quelconque', () => {
  const els = [pt('a', 10, 20, {callsign: 'ALPHA'}), line('r', [[0, 0], [10, 0]])];
  els[0].updated_at = 5;
  const text = L.clipSerialize(els, 'altis');
  const back = L.clipParse(text);
  assert.equal(back.kind, 'athena');
  assert.equal(back.elements.length, 2);
  assert.equal(back.elements[0].updated_at, undefined);
  assert.equal(L.clipParse('{"format":"athena-plan","version":1,"elements":[{"kind":"text","pts":[[1,2]]}]}').kind, 'plan');
  assert.equal(L.clipParse('{"type":"FeatureCollection","features":[]}').kind, 'geojson');
  assert.equal(L.clipParse('bonjour').kind, null);
  assert.equal(L.clipParse('{"format":"athena-plan-clip","elements":[{"kind":"x"}]}').elements.length, 0, 'élément sans points écarté');
});

test('coller : nouveaux uid, groupe, unité mère et itinéraire suivis, sous le curseur', () => {
  const els = [
    pt('chef', 0, 0, {}, {group: 'g1'}),
    pt('sub', 10, 0, {parent: 'chef', route: 'rte', track: [{t: 0, p: [10, 0]}, {t: 5, p: [20, 0]}]}, {group: 'g1', locked: true, phases: ['p1', 'px']}),
    line('rte', [[0, 0], [20, 0]])
  ];
  const out = L.pasteElements(els, {at: [110, 100], layer: 'L2', layerOk: () => false, phaseOk: (u) => u === 'p1', z: 50});
  assert.equal(out.length, 3);
  assert.ok(out.every((e, i) => e.uid !== els[i].uid));
  assert.equal(out[1].props.parent, out[0].uid);
  assert.equal(out[1].props.route, out[2].uid);
  assert.equal(out[0].group, out[1].group);
  assert.notEqual(out[0].group, 'g1');
  assert.equal(out[1].locked, false);
  assert.deepEqual(out[1].phases, ['p1']);
  assert.equal(out[0].layer, 'L2');
  assert.deepEqual(out[0].pts[0], [100, 100], 'le centre du lot arrive sous le curseur');
  assert.deepEqual(out[1].props.track[1].p, [120, 100]);
  assert.deepEqual(out.map((e) => e.z), [51, 52, 53]);
  const alone = L.pasteElements([els[1]], {dx: 5, dy: 5});
  assert.equal(alone[0].props.parent, '', 'unité mère hors du lot : lien retiré');
});

test('copier et coller le style', () => {
  const s = L.pickStyle(line('l', [[0, 0], [1, 1]], {color: '#ff0000', width: 4, dash: 'dash', arrow: 'end', label: 'X'}));
  assert.deepEqual(s, {color: '#ff0000', width: 4, dash: 'dash', arrow: 'end'});
  const z = L.applyStyle(area('z', [[0, 0], [1, 0], [1, 1]], {color: '#000000', fill: 0.1}), Object.assign({fill: 0.3}, s));
  assert.equal(z.props.color, '#ff0000');
  assert.equal(z.props.arrow, undefined, 'pas de flèche sur une zone');
  assert.equal(z.props.fill, 0.3);
  assert.equal(L.applyStyle(pt('u', 0, 0, {color: '#6cb8ff'}), s).props.color, '#6cb8ff', 'la couleur d’une unité suit son affiliation');
});

test('images clés : interpolation droite, le long d’un itinéraire, avant et après', () => {
  const u = pt('u', 0, 0, {track: [{t: 0, p: [0, 0]}, {t: 10, p: [100, 0]}, {t: 20, p: [100, 100]}]});
  assert.deepEqual(L.positionAt(u, -5, {}), [0, 0]);
  assert.deepEqual(L.positionAt(u, 5, {}), [50, 0]);
  assert.deepEqual(L.positionAt(u, 15, {}), [100, 50]);
  assert.deepEqual(L.positionAt(u, 99, {}), [100, 100]);
  assert.deepEqual(L.positionAt(pt('fixe', 7, 8), 12, {}), [7, 8]);
  const route = line('rte', [[0, 0], [100, 0], [100, 100]]);
  const v = pt('v', 0, 0, {route: 'rte', track: [{t: 0, p: [0, 0]}, {t: 10, p: [100, 100]}]});
  const mid = L.positionAt(v, 5, {rte: route});
  near(mid[0], 100); near(mid[1], 0);
  const q = L.positionAt(v, 2.5, {rte: route});
  near(q[0], 50); near(q[1], 0);
  assert.deepEqual(L.positionAt(v, 5, {}), [50, 50], 'itinéraire absent : ligne droite');
  assert.deepEqual(L.atTime(u, 5, {}).pts, [[50, 0]]);
  assert.equal(L.atTime(u, null, {}), u);
});

test('images clés : poser, remplacer, retirer, limite', () => {
  const u = pt('u', 10, 10);
  const a = L.setKeyframe(u, 30, [50, 60], 0);
  assert.deepEqual(a.props.track, [{t: 0, p: [10, 10]}, {t: 30, p: [50, 60]}]);
  const b = L.setKeyframe(a, 30, [70, 80], 0);
  assert.equal(b.props.track.length, 2);
  assert.deepEqual(b.props.track[1].p, [70, 80]);
  const c = L.setKeyframe(b, 10, [20, 20], 0);
  assert.deepEqual(c.props.track.map((k) => k.t), [0, 10, 30]);
  const d = L.removeKeyframe(c, 0);
  assert.deepEqual(d.pts, [[20, 20]], 'la position de base suit la première image clé');
  assert.equal(L.removeKeyframe(L.removeKeyframe(d, 10), 30).props.track, undefined);
  let full = u;
  for (let i = 0; i < 60; i++) full = L.setKeyframe(full, i, [i, i], null);
  assert.equal(L.setKeyframe(full, 99, [1, 1], null), null, '60 images clés au plus');
});

test('présence dans le temps et phases sur la frise', () => {
  assert.equal(L.visibleAt(pt('a', 0, 0, {t0: 10, t1: 20}), 5), false);
  assert.equal(L.visibleAt(pt('a', 0, 0, {t0: 10, t1: 20}), 10), true);
  assert.equal(L.visibleAt(pt('a', 0, 0, {t0: 10, t1: 20}), 20), false);
  assert.equal(L.visibleAt(pt('a', 0, 0, {t0: 10}), null), true, 'plan entier : tout ce qui est posé');
  assert.equal(L.visibleAt(pt('a', 0, 0, {hidden: true}), null), false);
  assert.equal(L.visibleAt(pt('a', 0, 0, {placed: false}), 3), false);
  const phases = [{uid: 'p1'}, {uid: 'p2'}, {uid: 'p3'}];
  const tl = {start: -10, dur: 90, phases: {p1: 0, p2: 30}};
  assert.deepEqual(L.phaseStarts(phases, tl), [0, 30, 50], 'début non réglé : réparti, jamais avant la phase précédente');
  assert.equal(L.phaseAtTime(phases, tl, -5), null);
  assert.equal(L.phaseAtTime(phases, tl, 0), 'p1');
  assert.equal(L.phaseAtTime(phases, tl, 31), 'p2');
  assert.deepEqual(L.normTimeline({phases: [], dur: 5}).phases, {});
  assert.equal(L.normTimeline({dur: 5}).dur, 10);
});

test('heures : H+, ZULU, saisie', () => {
  assert.equal(L.fmtH(0), 'H');
  assert.equal(L.fmtH(90), 'H+01:30');
  assert.equal(L.fmtH(-15), 'H-00:15');
  assert.equal(L.fmtZulu('2026-11-12T05:00', 90), '120630Z NOV 26');
  assert.equal(L.fmtZulu('2026-11-12T00:10', -20), '112350Z NOV 26');
  assert.equal(L.fmtZulu('', 10), '');
  assert.equal(L.parseH('H+1:30'), 90);
  assert.equal(L.parseH('h-15'), -15);
  assert.equal(L.parseH('90'), 90);
  assert.equal(L.parseH('1h30'), 90);
  assert.equal(L.parseH('H'), 0);
  assert.equal(L.parseH('H+00:30'), 30);
  assert.equal(L.parseH('bientôt'), null);
});

test('chemins : abscisse, projeté, délais d’un itinéraire', () => {
  const path2 = [[0, 0], [100, 0], [100, 100]];
  assert.deepEqual(L.alongPath(path2, 150), [100, 50]);
  assert.deepEqual(L.alongPath(path2, 999), [100, 100]);
  near(L.projectOnPath(path2, [50, 20]), 50);
  near(L.projectOnPath(path2, [130, 40]), 140);
  assert.deepEqual(L.subPath(path2, 50, 150), [[50, 0], [100, 0], [100, 50]]);
  const t = L.routeTimings([[0, 0], [1000, 0], [1000, 2000]], 6);
  near(t[1], 10); near(t[2], 30);
  assert.deepEqual(L.routeTimings(path2, 0), [null, null, null]);
});

// Relief d'essai : plan incliné vers l'est (1 m par mètre), ou terrain plat avec un mur nord-sud en x = 500.
function dem(wall) {
  const cols = 11, rows = 11, cell = 100;
  const heights = new Int16Array(cols * rows);
  for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) heights[r * cols + c] = wall ? (c === 5 ? 200 : 0) : c * 100;
  return {cols, rows, cell, ox: 0, oy: 0, heights, missing: -32768, minZ: 0, maxZ: 1000};
}

test('relief : altitude bilinéaire, hors grille, cellule sans donnée, pente', () => {
  const d = dem(false);
  near(L.demSample(d, 250, 480), 250);
  near(L.demSample(d, 1000, 1000), 1000);
  assert.equal(L.demSample(d, -1, 0), null);
  assert.equal(L.demSample(d, 1001, 0), null);
  assert.equal(L.demSample(null, 1, 1), null);
  near(L.slopeAt(d, 500, 500), 45, 0.01);
  d.heights[0] = -32768;
  near(L.demSample(d, 50, 50), (100 + 0 + 100) / 3, 0.01);
});

test('relief : coupe le long d’un tracé', () => {
  const p = L.profile(dem(false), [[0, 500], [1000, 500]], 50);
  near(p.min, 0); near(p.max, 1000); near(p.gain, 1000); near(p.loss, 0);
  near(p.maxSlope, 45, 0.5);
  assert.equal(p.samples.length, 21);
  assert.equal(p.gaps, 0);
  const w = L.profile(dem(true), [[0, 500], [1000, 500]], 25);
  near(w.gain, 200); near(w.loss, 200);
});

test('relief : ligne de vue dégagée, masquée par un mur, observateur surélevé', () => {
  const flat = dem(true);
  const hidden = L.los(flat, [100, 500], [900, 500], 1.7, 1.7);
  assert.equal(hidden.visible, false);
  assert.ok(hidden.block[0] > 400 && hidden.block[0] < 500, 'le mur masque avant sa crête');
  const sameSide = L.los(flat, [50, 500], [350, 500], 1.7, 1.7);
  assert.equal(sameSide.visible, true);
  assert.ok(sameSide.clearance > 1.6);
  const tower = L.los(flat, [100, 500], [900, 500], 300, 300);
  assert.equal(tower.visible, true);
  assert.equal(L.los(flat, [100, 500], [5000, 500], 1.7, 1.7).visible, null, 'cible hors du relief');
});

test('relief : zone vue, secteur et portée minimale', () => {
  const vs = L.viewshed(dem(true), [200, 500], {radius: 700, eye: 1.7, rays: 72});
  assert.equal(vs.rays.length, 72);
  const east = vs.rays.filter((r) => Math.abs(r.az - 90) < 0.01)[0];
  const west = vs.rays.filter((r) => Math.abs(r.az - 270) < 0.01)[0];
  assert.ok(east.spans.length >= 1 && east.spans[east.spans.length - 1][1] <= 320, 'à l’est le mur arrête la vue');
  assert.deepEqual(west.spans, [[0, 200]], 'à l’ouest tout est vu jusqu’au bord du relief');
  assert.ok(vs.seenPct > 10 && vs.seenPct < 90);
  const fan = L.viewshed(dem(true), [200, 500], {radius: 150, eye: 1.7, bearing: 270, arc: 60, minRange: 50, rays: 13});
  assert.equal(fan.rays.length, 13);
  near(fan.rays[0].az, 240); near(fan.rays[12].az, 300);
  assert.deepEqual(fan.rays[6].spans, [[50, 150]]);
  assert.equal(L.viewshed(dem(true), [-50, 0], {}).unknown, true);
});

test('relief binaire : en-têtes X-Dem-* et octets petit-boutistes', () => {
  const buf = new ArrayBuffer(8);
  new DataView(buf).setInt16(0, 12, true);
  new DataView(buf).setInt16(2, -3, true);
  new DataView(buf).setInt16(4, 300, true);
  new DataView(buf).setInt16(6, -32768, true);
  const h = {'X-Dem-Cols': '2', 'X-Dem-Rows': '2', 'X-Dem-Cell': '50', 'X-Dem-Origin': '10,20', 'X-Dem-Min': '-3', 'X-Dem-Max': '300', 'X-Dem-World': '8192', 'X-Dem-Coverage': '75'};
  const d = L.demFromBinary(buf, (n) => h[n]);
  assert.deepEqual(Array.from(d.heights), [12, -3, 300, -32768]);
  assert.deepEqual([d.cols, d.rows, d.cell, d.ox, d.oy, d.worldSize, d.coverage], [2, 2, 50, 10, 20, 8192, 75]);
  assert.equal(L.demFromBinary(new ArrayBuffer(2), (n) => h[n]), null);
});

test('ORBAT : arbre par affiliation, boucle défaite, rattachement', () => {
  const els = [
    pt('a', 0, 0, {ord: 1}), pt('b', 0, 0, {parent: 'a', ord: 1}), pt('c', 0, 0, {parent: 'a', ord: 0}), pt('d', 0, 0, {parent: 'b'}),
    pt('h', 0, 0, {affil: 'H'}), pt('x', 0, 0, {parent: 'y'}), pt('y', 0, 0, {parent: 'x'}), line('l', [[0, 0], [1, 1]])
  ];
  const tree = L.orbatTree(els);
  assert.deepEqual(tree.H.map((n) => n.uid), ['h']);
  const a = tree.F.filter((n) => n.uid === 'a')[0];
  assert.deepEqual(a.children.map((n) => n.uid), ['c', 'b']);
  assert.deepEqual(a.children[1].children.map((n) => n.uid), ['d']);
  assert.ok(tree.F.some((n) => n.uid === 'x' || n.uid === 'y'), 'une boucle remonte à la racine');
  assert.deepEqual(L.descendants(els, 'a').sort(), ['b', 'c', 'd']);
  assert.equal(L.reparent(els, 'a', 'd', null), null, 'pas sous une subordonnée');
  assert.equal(L.reparent(els, 'a', 'a', null), null);
  const moved = L.reparent(els, 'd', 'a', 0);
  assert.equal(moved.d.props.parent, 'a');
  assert.equal(moved.d.props.ord, 0);
  assert.equal(moved.c.props.ord, 1);
  const toHostile = L.reparent(els, 'b', 'h', null);
  assert.equal(toHostile.b.props.affil, 'H');
  assert.equal(toHostile.d.props.affil, 'H', 'l’affiliation suit dans la branche');
  const root = L.reparent(els, 'b', '', null, 'N');
  assert.equal(root.b.props.parent, '');
  assert.equal(root.b.props.affil, 'N');
});

test('poignées : facteur d’échelle et angle de rotation', () => {
  near(L.scaleFactor([0, 0], [10, 0], [20, 0]), 2);
  near(L.scaleFactor([0, 0], [10, 0], [0, 5]), 0.5);
  near(L.rotationAngle([0, 0], [0, 10], [10, 0], false), 90);
  near(L.rotationAngle([0, 0], [0, 10], [-10, 0], false), -90);
  assert.equal(L.rotationAngle([0, 0], [0, 10], [3, 10], true), 15);
});

test('grille à 10 chiffres, aimantation, recherche', () => {
  assert.equal(L.grid10(4251, 11693.7), '04251 11693');
  assert.deepEqual(L.snapToGrid([1234, 5678], 10), [1230, 5680]);
  assert.ok(L.fuzzyScore('infan', 'Infanterie mécanisée') > L.fuzzyScore('infan', 'Point de ravitaillement infanterie'));
  assert.ok(L.fuzzyScore('mecanisee', 'Infanterie mécanisée') > 0, 'sans les accents');
  assert.ok(L.fuzzyScore('inf mec', 'Infanterie mécanisée') > 0, 'plusieurs mots');
  assert.equal(L.fuzzyScore('zzz', 'Infanterie'), 0);
  assert.ok(L.fuzzyScore('', 'tout') > 0);
});

test('symbole OTAN : statut prévu, PC, groupement, surcharge de code', () => {
  const types = [{id: 'infantry', fn: 'UCI---'}];
  assert.equal(G.sidc({affil: 'F', unitType: 'infantry', echelon: 'squad'}, types), 'SFGPUCI----B---');
  assert.equal(G.sidc({affil: 'H', unitType: 'infantry', echelon: 'company', status: 'planned', hq: true}, types), 'SHGAUCI---AE---');
  assert.equal(G.sidc({affil: 'F', unitType: 'infantry', tf: true, hq: true}, types).charAt(10), 'B');
  assert.equal(G.sidc({affil: 'H', sidc: 'S*G*EVAT--'}, types), 'SHGPEVAT-------');
  assert.equal(G.sidc({affil: 'F', sidc: 'SFGPUCA---'}, types), 'SFGPUCA---', 'code complet sans modificateur : inchangé');
  assert.deepEqual(G.symbolOptions({higher: '2 REI', mod: 'reinforced', dir: 90}), {higherFormation: '2 REI', reinforcedReduced: '+', direction: 90});
  assert.deepEqual(G.symbolOptions({}), {});
});

test('graphismes : axe avec largeur et éventail de portée', () => {
  const o = G.axisOutline([[0, 0], [1000, 0]], 200);
  assert.equal(o.length, 7);
  near(o[0][1], 100); near(o[6][1], -100);
  assert.deepEqual(o[3], [1000, 0], 'la pointe');
  assert.ok(Math.abs(o[2][1]) > 150, 'la pointe est plus large que le corps');
  const fan = G.fanOutline({pts: [[0, 0]], props: {radius: 1000, bearing: 90, arc: 60}}, 48);
  assert.deepEqual(fan[fan.length - 1], [0, 0], 'sans portée minimale : le secteur revient au centre');
  assert.ok(fan.slice(0, -1).every((p) => p[0] > 800 && Math.abs(G.dist([0, 0], p) - 1000) < 0.01));
  const ringed = G.fanOutline({pts: [[0, 0]], props: {radius: 1000, minRange: 400, bearing: 0, arc: 90}}, 48);
  assert.ok(ringed.every((p) => G.dist([0, 0], p) >= 399.9));
  assert.equal(G.fanOutline({pts: [[0, 0]], props: {radius: 500, arc: 360}}, 36).length, 36);
  assert.equal(L.outlineOf({kind: 'fan', geom: 'point', pts: [[0, 0]], props: {radius: 100}}).length > 3, true);
  assert.equal(L.boxHit({kind: 'fan', geom: 'point', pts: [[0, 0]], props: {radius: 1000, bearing: 90, arc: 60}}, [400, -50, 500, 50]), true);
});
