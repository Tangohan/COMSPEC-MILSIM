const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const G = require(path.join(__dirname, '../../public/assets/js/toc/toc-geo.js'));
const L = require(path.join(__dirname, '../../public/assets/js/toc/plan/plan-logic.js'));
const T = require(path.join(__dirname, '../../public/assets/js/toc/toc-tracks.js'));

// Extrait de TocSymbols::forClient() : ce que la conversion des marqueurs lit.
const SYM = {
  kinds: {infantry: {fn: 'UCI---', dim: 'G'}, armor: {fn: 'UCA---', dim: 'G'}, rotary: {fn: 'MH----', dim: 'A'}},
  natoMarkers: {inf: 'infantry', armor: 'armor', air: 'rotary'},
  natoPrefix: {b: 'F', o: 'H', n: 'N', c: 'N', u: 'U'},
  markerIcons: {dot: 'dot', objective: 'objective', flag: 'flag', arrow: 'arrow', warning: 'warning', empty: 'label'},
};
const row = (id, d) => ({id, markerData: d, updated_at: '2026-11-12 05:00:00'});
const rec = (id, d) => T.markerRecord(row(id, d), SYM, G);

test('marqueur du jeu : icône, texte, couleur, position', () => {
  const r = rec(1, {id: '_USER_DEFINED #2/1/1', type: 'mil_objective', color: 'ColorRed', text: 'OBJ ACIER', pos: [5250, 4950, 0], shape: 'ICON', side: 'WEST', channel: 1});
  assert.equal(r.gid, '_USER_DEFINED #2/1/1');
  assert.equal(r.shape, 'ICON');
  assert.deepEqual(r.pos, [5250, 4950]);
  assert.equal(r.el.kind, 'icon');
  assert.equal(r.el.props.icon, 'target');
  assert.equal(r.el.props.label, 'OBJ ACIER');
  assert.equal(r.color, '#ff5f56');
  assert.equal(r.side, 'WEST');
  assert.equal(r.channel, '1');
  assert.equal(r.system, '');
});

test('marqueur OTAN : une unité du plan, affiliation du préfixe, code du symbole', () => {
  const f = rec(2, {id: 'm2', type: 'b_inf', text: 'ALPHA 1', pos: '[3050,3450,0]', shape: 'ICON'});
  assert.equal(f.el.kind, 'unit');
  assert.equal(f.el.props.affil, 'F');
  assert.equal(f.el.props.callsign, 'ALPHA 1');
  assert.equal(G.sidc(f.el.props, []), 'SFGPUCI--------');
  const h = rec(3, {id: 'm3', type: 'o_armor', text: 'T-72', x: 5450, y: 4750});
  assert.equal(h.el.props.affil, 'H');
  assert.equal(G.sidc(h.el.props, []).slice(0, 7), 'SHGPUCA');
  const air = rec(4, {id: 'm4', type: 'n_air', pos: [100, 200]});
  assert.equal(G.sidc(air.el.props, []).slice(0, 6), 'SNAPMH', 'la dimension aérienne suit');
});

test('zones : ellipse, rectangle tourné en polygone, pinceau en remplissage et hachures', () => {
  const e = rec(5, {id: 'ea', color: 'ColorRed', text: 'EA TIGRE', pos: [5900, 4200], shape: 'ELLIPSE', size: [320, 210], brush: 'SolidBorder', alpha: 0.5});
  assert.equal(e.el.geom, 'ellipse');
  assert.deepEqual([e.el.props.rx, e.el.props.ry], [320, 210]);
  assert.ok(Math.abs(e.el.props.fill - 0.14) < 0.001);
  assert.equal(e.hatch, '');
  const q = rec(6, {id: 'z', color: 'ColorOrange', text: 'ZONE', pos: [1000, 1000], shape: 'RECTANGLE', size: [200, 100], dir: 90, brush: 'DiagGrid'});
  assert.equal(q.el.geom, 'area');
  assert.equal(q.el.kind, 'polygon');
  assert.equal(q.el.pts.length, 4);
  const b = G.bbox(q.el.pts);
  assert.ok(Math.abs((b[2] - b[0]) - 200) < 1 && Math.abs((b[3] - b[1]) - 400) < 1, 'tourné de 90° : largeur et hauteur échangées');
  assert.equal(q.hatch, 'grid');
  const flat = rec(7, {id: 'r', pos: [0, 500], shape: 'RECTANGLE', size: [50, 20], dir: 0});
  assert.equal(flat.el.kind, 'polygon', 'un rectangle du jeu devient toujours une zone à quatre sommets');
  assert.deepEqual(G.bbox(flat.el.pts), [-50, 480, 50, 520]);
});

test('tracés : liste plate ou couples, flèche d’après le type', () => {
  const l = rec(8, {id: 'p', type: 'mil_arrow2', color: 'ColorBlue', shape: 'POLYLINE', polyline: [10, 20, 110, 220, 300, 400]});
  assert.equal(l.el.geom, 'line');
  assert.equal(l.el.kind, 'arrow');
  assert.deepEqual(l.el.pts, [[10, 20], [110, 220], [300, 400]]);
  assert.deepEqual(l.pos, [10, 20]);
  const plain = rec(9, {id: 'q', shape: 'POLYLINE', points: [[10, 20], {x: 30, y: 40}]});
  assert.equal(plain.el.kind, 'line');
  assert.equal(rec(10, {id: 'x', shape: 'POLYLINE', polyline: [5, 5]}).el.geom, 'point', 'un seul point : repère');
});

test('marqueurs écartés : supprimés, sans position, de service', () => {
  assert.equal(T.markerRecord({id: 'del:4', markerData: {pos: [1, 1]}}, SYM, G), null);
  assert.equal(rec(11, {id: 'a', suppressed: true, pos: [10, 10]}), null);
  assert.equal(rec(12, {id: 'a', type: 'comspec_del', pos: [10, 10]}), null);
  assert.equal(rec(13, {id: 'a', text: 'sans position'}), null);
  assert.equal(T.markerRecord({id: 14, markerData: '{"id":"j","pos":[40,50],"text":"JSON"}'}, SYM, G).label, 'JSON', 'markerData en texte JSON');
  assert.equal(rec(15, {id: 'respawn_west', type: 'respawn_inf', pos: [10, 10]}).system, 'respawn');
  assert.equal(rec(16, {id: 'BIS_module_3', type: 'mil_dot', text: 'x', pos: [10, 10]}).system, 'module');
  assert.equal(rec(17, {id: 'vide', type: 'Empty', pos: [10, 10]}).system, 'empty');
  assert.equal(rec(18, {id: 'titre', type: 'Empty', text: 'BASE', pos: [10, 10]}).system, '', 'un marqueur vide qui porte un texte est un libellé voulu');
  assert.equal(rec(19, {id: '_USER_DEFINED #1/2/3', type: 'mil_dot', pos: [10, 10]}).label, '', 'le nom technique d’un marqueur de joueur n’est pas un texte');
});

test('état de l’import : partie, terrain, marqueurs', () => {
  assert.equal(L.importState({live: true, world: 'Altis'}, 'altis', 4), 'ok');
  assert.equal(L.importState({live: true, world: 'Stratis'}, 'altis', 4), 'mismatch');
  assert.equal(L.importState({live: false, world: 'stratis'}, 'altis', 0), 'mismatch', 'le terrain différent se dit même sans liaison');
  assert.equal(L.importState({live: false, world: ''}, 'altis', 0), 'nolink');
  assert.equal(L.importState(null, 'altis', 0), 'nolink');
  assert.equal(L.importState({live: true, world: 'altis'}, 'altis', 0), 'empty');
  assert.equal(L.importState({live: false, world: 'altis'}, 'altis', 3), 'stale');
  assert.equal(L.importState({live: true, world: ''}, 'altis', 3), 'ok', 'terrain inconnu du jeu : pas de refus');
});

const recs = () => [
  rec(1, {id: 'a', type: 'mil_dot', text: 'ALPHA', pos: [100, 100], side: 'WEST', channel: 1}),
  rec(2, {id: 'b', type: 'b_inf', text: 'BRAVO', pos: [900, 900], side: 'EAST', channel: 3}),
  rec(3, {id: 'c', text: 'ZONE', pos: [500, 500], shape: 'ELLIPSE', size: [50, 50], brush: 'Cross'}),
  rec(4, {id: 'respawn_west', type: 'respawn_inf', pos: [10, 10]}),
  rec(5, {id: 'd', shape: 'POLYLINE', polyline: [10, 10, 50, 50]}),
];

test('filtres et groupes de la liste', () => {
  const all = recs();
  assert.equal(L.markerFilter(all, {}).length, 5);
  assert.deepEqual(L.markerFilter(all, {system: true}).map((r) => r.gid), ['a', 'b', 'c', 'd']);
  assert.deepEqual(L.markerFilter(all, {side: 'WEST'}).map((r) => r.gid), ['a', 'c', 'respawn_west', 'd'], 'un marqueur sans camp reste');
  assert.deepEqual(L.markerFilter(all, {box: [0, 0, 600, 600], system: true}).map((r) => r.gid), ['a', 'c', 'd']);
  assert.deepEqual(L.markerFilter(all, {q: 'brav'}).map((r) => r.gid), ['b']);
  assert.deepEqual(L.markerGroups(all, 'kind').map((g) => [g.key, g.items.length]), [['unit', 1], ['icon', 2], ['area', 1], ['line', 1]]);
  assert.deepEqual(L.markerGroups(all, 'channel').map((g) => g.key), ['1', '3', '?']);
  assert.deepEqual(L.markerGroups(all, 'side').map((g) => g.key), ['WEST', 'EAST', '?']);
});

test('plan d’import : ajout, palette, lien, calque et phase', () => {
  const pl = L.markerPlan(L.markerFilter(recs(), {system: true}), [], {layer: 'L1', phase: 'p2', z: 10, link: false, color: () => '#abcdef'});
  assert.equal(pl.add.length, 4);
  assert.equal(pl.known, 0);
  assert.deepEqual(pl.add.map((e) => e.z), [11, 12, 13, 14]);
  assert.ok(pl.add.every((e) => e.layer === 'L1' && e.phases[0] === 'p2' && e.uid && !e.locked));
  assert.equal(pl.add[0].props.color, '#abcdef');
  assert.equal(pl.add[1].kind, 'unit');
  assert.notEqual(pl.add[1].props.color, '#abcdef', 'la couleur d’une unité suit son affiliation');
  assert.equal(pl.add[2].props.dash, 'dash', 'pinceau hachuré : bord en tirets');
  assert.ok(pl.add.every((e) => e.props.gid && e.props.glink === undefined));
  assert.equal(L.markerPlan(recs().slice(0, 1), [], {link: true}).add[0].props.glink, true);
});

test('plan d’import : marqueur déjà importé (mettre à jour, ignorer, dupliquer)', () => {
  const first = L.markerPlan(recs().slice(0, 2), [], {layer: 'L1', z: 0}).add;
  first[0].layer = 'perso'; first[0].phases = ['p1']; first[0].props.note = 'gardée'; first[0].z = 77;
  const moved = [rec(1, {id: 'a', type: 'mil_dot', text: 'ALPHA BIS', pos: [300, 400]}), recs()[2]];
  const up = L.markerPlan(moved, first, {mode: 'update', layer: 'L1', z: 100});
  assert.equal(up.known, 1);
  assert.equal(up.add.length, 1, 'le marqueur nouveau est ajouté');
  const u = up.update[first[0].uid];
  assert.deepEqual(u.pts, [[300, 400]]);
  assert.equal(u.props.label, 'ALPHA BIS');
  assert.deepEqual([u.layer, u.phases, u.props.note, u.z, u.uid], ['perso', ['p1'], 'gardée', 77, first[0].uid], 'l’élément garde son identité et ses réglages');
  const skip = L.markerPlan(moved, first, {mode: 'skip'});
  assert.deepEqual([skip.add.length, Object.keys(skip.update).length, skip.skipped], [1, 0, 1]);
  const dup = L.markerPlan(moved, first, {mode: 'duplicate'});
  assert.deepEqual([dup.add.length, Object.keys(dup.update).length], [2, 0]);
  assert.notEqual(dup.add[0].uid, first[0].uid);
});
