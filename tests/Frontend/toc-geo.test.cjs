const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const G = require(path.join(__dirname, '../../public/assets/js/toc/toc-geo.js'));

test('grille Arma à 6 et 8 chiffres, et lecture d’une grille saisie', () => {
  assert.equal(G.grid(4250, 11690, 6), '042 116');
  assert.equal(G.grid(4250, 11690, 8), '0425 1169');
  assert.equal(G.grid(-5, 20, 6), '000 000');
  assert.deepEqual(G.parseGrid('042 116'), [4250, 11650]);
  assert.deepEqual(G.parseGrid('04251169'), [4255, 11695]);
  assert.equal(G.parseGrid('12'), null);
});

test('distance, azimut (0 = nord, sens horaire) et format', () => {
  assert.equal(G.dist([0, 0], [300, 400]), 500);
  assert.equal(Math.round(G.azimuth([0, 0], [0, 100])), 0);
  assert.equal(Math.round(G.azimuth([0, 0], [100, 0])), 90);
  assert.equal(Math.round(G.azimuth([0, 0], [-100, 0])), 270);
  assert.equal(G.fmtDist(950), '950 m');
  assert.equal(G.fmtDist(1500), '1,50 km');
});

test('contours, sélection au clic et simplification', () => {
  const circle = {geom: 'circle', pts: [[100, 100]], props: {radius: 50, fill: 0.1}};
  assert.equal(G.outline(circle, 12).length, 12);
  assert.equal(G.hitDistance(circle, [100, 100]), 0);
  const line = {geom: 'line', pts: [[0, 0], [100, 0]], props: {}};
  assert.equal(G.hitDistance(line, [50, 10]), 10);
  assert.equal(G.outline({geom: 'rect', pts: [[0, 0], [10, 20]], props: {}}).length, 4);
  const many = [];
  for (let i = 0; i <= 100; i++) many.push([i, 0]);
  assert.deepEqual(G.simplify(many, 0.5), [[0, 0], [100, 0]]);
});

test('déplacer, pivoter, agrandir sans changer l’élément d’origine', () => {
  const el = {uid: 'e_a', geom: 'line', pts: [[0, 0], [100, 0]], props: {width: 2}};
  const moved = G.translate(el, 10, 5);
  assert.deepEqual(moved.pts, [[10, 5], [110, 5]]);
  assert.deepEqual(el.pts, [[0, 0], [100, 0]]);
  const rot = G.rotate(el, 90, [0, 0]);
  assert.deepEqual(rot.pts.map(p => p.map(Math.round)), [[0, 0], [0, -100]]);
  const big = G.scale({geom: 'circle', pts: [[0, 0]], props: {radius: 100}}, 2);
  assert.equal(big.props.radius, 200);
});

test('phases : élément sans phase visible partout', () => {
  assert.equal(G.inPhase({phases: []}, 'p_1'), true);
  assert.equal(G.inPhase({phases: ['p_2']}, 'p_1'), false);
  assert.equal(G.inPhase({phases: ['p_2']}, null), true);
});

test('annuler et rétablir', () => {
  const h = new G.History(2);
  h.push({a: null}, {a: {uid: 'a'}});
  h.push({b: null}, {b: {uid: 'b'}});
  h.push({c: null}, {c: {uid: 'c'}});
  assert.deepEqual(h.undo(), {c: null});
  assert.deepEqual(h.redo(), {c: {uid: 'c'}});
  h.undo(); h.undo();
  assert.equal(h.canUndo(), false, 'historique borné');
});

test('changements d’un autre éditeur : le dernier enregistré gagne, sauf changement local pas encore envoyé', () => {
  const local = {a: {uid: 'a', updated_at: 5, v: 1}, b: {uid: 'b', updated_at: 5, v: 1}, c: {uid: 'c', updated_at: 5}};
  const changed = G.mergeRemote(local, [{uid: 'a', updated_at: 9, v: 2}, {uid: 'b', updated_at: 9, v: 2}, {uid: 'c', deleted: true, updated_at: 9}, {uid: 'd', updated_at: 9}], {b: true});
  assert.deepEqual(changed.sort(), ['a', 'c', 'd']);
  assert.equal(local.a.v, 2);
  assert.equal(local.b.v, 1);
  assert.equal(local.c, undefined);
});

test('couleur du jeu la plus proche et symbole OTAN', () => {
  assert.equal(G.nearestGameColor('#ff0000'), 'RED');
  assert.equal(G.nearestGameColor('n’importe quoi'), 'WHITE');
  const types = [{id: 'infantry', fn: 'UCI---'}];
  assert.equal(G.sidc({affil: 'H', unitType: 'infantry', echelon: 'platoon'}, types), 'SHGPUCI----D---');
  assert.equal(G.sidc({affil: 'X'}, types).charAt(1), 'F');
  assert.equal(G.sidc({affil: 'F', unitType: 'infantry', echelon: 'squad'}, types).length, 15);
});
