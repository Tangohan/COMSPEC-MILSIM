const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const T = require(path.join(__dirname, '../../public/assets/js/toc/toc-tracks.js'));

// Extrait des tables du site (App\Services\AtakToc\TocSymbols::forClient).
const SYM = {
  kinds: {
    infantry: {fn: 'UCI---', dim: 'G', label: 'Infanterie'}, armor: {fn: 'UCA---', dim: 'G', label: 'Blindés'}, motorized: {fn: 'UCIM--', dim: 'G', label: 'Infanterie motorisée'},
    rotary: {fn: 'MH----', dim: 'A', label: 'Hélicoptère'}, fixed: {fn: 'MF----', dim: 'A', label: 'Avion'}, uav: {fn: 'MFQ---', dim: 'A', label: 'Drone'}
  },
  platforms: {HELICOPTER: 'rotary', PLANE: 'fixed', UAV: 'uav', TANK: 'armor', MRAP: 'motorized'},
  natoMarkers: {inf: 'infantry', armor: 'armor', air: 'rotary', plane: 'fixed', uav: 'uav'},
  natoPrefix: {b: 'F', o: 'H', n: 'N', c: 'N', u: 'U'},
  markerIcons: {dot: 'dot', objective: 'objective', flag: 'flag', warning: 'warning', respawn_air: 'heli', loc_hospital: 'medical', empty: 'label'}
};

test('fraîcheur : en direct, ancien, perdu', () => {
  assert.equal(T.freshness('linked', 5), 'live');
  assert.equal(T.freshness('linked', 90), 'old');
  assert.equal(T.freshness('delayed', 20), 'old');
  assert.equal(T.freshness('linked', 400), 'lost');
  assert.equal(T.freshness('offline', 2), 'lost');
  assert.equal(T.freshness('linked', null), 'live');
  assert.equal(T.freshness('', null), 'lost');
});

test('âge en clair et âge court', () => {
  assert.equal(T.ageLabel(10), 'à l’instant');
  assert.equal(T.ageLabel(240), 'il y a 4 min');
  assert.equal(T.ageLabel(7200), 'il y a 2 h');
  assert.equal(T.ageLabel(3 * 86400), 'il y a 3 j');
  assert.equal(T.ageLabel(null), '');
  assert.equal(T.ageShort(40), '40 s');
  assert.equal(T.ageShort(240), '4 min');
  assert.equal(T.ageShort(7200), '2 h');
  assert.equal(T.ageShort(-1), '');
  assert.equal(T.altLabel(120), '120 m · 390 ft');
  assert.equal(T.speedLabel(50), '180 km/h');
  assert.equal(T.headingLabel(7), '007°');
  assert.equal(T.headingLabel(-90), '270°');
});

test('code OTAN : dimension air pour hélicoptère, avion et drone ; cadre en tirets quand la piste est perdue', () => {
  assert.equal(T.sidc(SYM, 'infantry', 'F'), 'SFGPUCI--------');
  assert.equal(T.sidc(SYM, 'rotary', 'F'), 'SFAPMH---------');
  assert.equal(T.sidc(SYM, 'fixed', 'H'), 'SHAPMF---------');
  assert.equal(T.sidc(SYM, 'uav', 'U'), 'SUAPMFQ--------');
  assert.equal(T.sidc(SYM, 'armor', 'H', true), 'SHGAUCA--------');
  assert.equal(T.sidc(SYM, 'infantry', 'F', false, 'B'), 'SFGPUCI----B---');
  assert.equal(T.sidc(SYM, 'rotary', 'F', false, 'B'), 'SFAPMH---------');
  assert.equal(T.sidc(SYM, 'jamais-vu', 'Z'), 'SFGPUCI--------');
  assert.equal(T.isAir(SYM, 'uav'), true);
  assert.equal(T.isAir(SYM, 'armor'), false);
  assert.equal(T.kindOf(SYM, 'helicopter'), 'rotary');
  assert.equal(T.kindOf(SYM, 'fixed wing'), '');
});

test('affiliation vue du camp du poste', () => {
  assert.equal(T.affiliation('WEST', 'WEST'), 'F');
  assert.equal(T.affiliation('', 'WEST'), 'F');
  assert.equal(T.affiliation('EAST', 'WEST'), 'H');
  assert.equal(T.affiliation('WEST', 'EAST'), 'H');
  assert.equal(T.affiliation('CIV', 'WEST'), 'N');
  assert.equal(T.affiliation('GUER', 'WEST'), 'U');
  assert.equal(T.affiliation('WEST', 'WEST', true), 'H');
  assert.equal(T.affiliation('BLUFOR', 'blufor'), 'F');
});

test('pistes : lecture des trois sources', () => {
  const u = T.unitTrack({id: 3, call_sign: 'TA1', group_name: 'TA1', pos_x: 100, pos_y: 200, status: 'linked', extra: {side: 'WEST'}, toc: {uid: 's:1', kind: 'rotary', air: true, alt: 120, speed: 40, age: 4}}, SYM);
  assert.deepEqual([u.id, u.uid, u.kind, u.air, u.fresh, u.alt, u.speed], ['u3', 's:1', 'rotary', true, 'live', 120, 40]);
  const lost = T.unitTrack({id: 4, call_sign: 'X', pos_x: 0, pos_y: 0, status: 'offline', extra: {}, toc: {age: 9000}}, SYM);
  assert.deepEqual([lost.pos, lost.fresh, lost.kind], [null, 'lost', 'infantry']);
  const v = T.vehicleTrack({id: 1, vehicle_callsign: 'SABRE 2', vehicle_class: 'TANK', pos_x: 5, pos_y: 5, crew_count: 3, status: 'ACTIVE', toc: {uid: 'v:1', age: 700}}, SYM);
  assert.deepEqual([v.kind, v.src, v.crew, v.fresh, v.air], ['armor', 'vehicle', 3, 'lost', false]);
  const a = T.airTrack({callsign: 'VIPER 1', aircraft_type: 'helicopter', status: 'AVAILABLE', pos_x: 5, pos_y: 5, alt: 0, toc: {age: 3}}, SYM);
  assert.deepEqual([a.id, a.kind, a.air, a.ground, a.fresh], ['aVIPER_1', 'rotary', true, true, 'live']);
});

test('pas de doublon : par identifiant stable, puis entre sources par indicatif', () => {
  const mk = (id, uid, callsign, fresh, age, src) => ({id, uid, callsign, fresh, age, src, group: ''});
  const d = T.dedupe([mk('u1', 's:1', 'ALPHA 2-4', 'lost', 9000), mk('u2', 's:1', 'TA1', 'live', 4), mk('u3', 's:1', 'YA1', 'lost', 600), mk('u4', 's:2', 'B', 'old', 90)]);
  assert.deepEqual(d.map(t => t.callsign), ['TA1', 'B']);
  const same = T.dedupe([mk('u1', 's:1', 'A', 'old', 100), mk('u2', 's:1', 'A2', 'old', 70)]);
  assert.equal(same[0].callsign, 'A2');
  const merged = T.merge(
    [mk('u1', 's:1', 'Viper 1', 'live', 3, 'unit'), mk('u2', 's:2', 'TA1', 'live', 3, 'unit'), mk('u3', 's:3', 'SABRE 2', 'live', 3, 'unit')],
    [mk('v1', 'v:1', 'SABRE 2', 'live', 5, 'vehicle')],
    [mk('a1', 'a:viper 1', 'VIPER 1', 'live', 2, 'air')]
  );
  assert.deepEqual(merged.map(t => t.id), ['a1', 'v1', 'u2']);
  const ids = merged.map(t => t.callsign.toUpperCase());
  assert.equal(new Set(ids).size, ids.length);
});

test('groupes : en jeu d’abord, membres triés, un seul membre = une ligne', () => {
  const mk = (callsign, group, fresh, pos) => ({id: callsign, uid: callsign, callsign, group, fresh, age: fresh === 'live' ? 3 : 500, pos, ai: false, kind: 'infantry'});
  const gs = T.groups([mk('Z1', 'ZULU', 'lost', [1, 1]), mk('A1-2', 'ALPHA 1', 'old', [2, 2]), mk('A1-1', 'ALPHA 1', 'live', null), mk('A1-10', 'ALPHA 1', 'live', [3, 3]), mk('TA1', '', 'live', [4, 4])]);
  assert.deepEqual(gs.map(g => g.name), ['ALPHA 1', 'TA1', 'ZULU']);
  assert.deepEqual(gs[0].members.map(m => m.callsign), ['A1-1', 'A1-10', 'A1-2']);
  assert.deepEqual([gs[0].live, gs[0].old, gs[0].lost, gs[0].state, gs[0].single], [2, 1, 0, 'live', false]);
  assert.deepEqual(gs[0].pos, [3, 3]);
  assert.equal(gs[1].single, true);
  assert.equal(gs[2].state, 'lost');
});

test('regroupement à petite échelle et éclatement de près', () => {
  const items = [
    {id: 'a', x: 100, y: 100, group: 'BRAVO', prio: 5}, {id: 'b', x: 110, y: 104, group: 'BRAVO', prio: 4}, {id: 'c', x: 96, y: 92, group: 'BRAVO', prio: 3},
    {id: 'd', x: 400, y: 400, group: 'ALPHA', prio: 2}, {id: 'e', x: 104, y: 98, group: 'X', prio: 9, solo: true}
  ];
  const cl = T.cluster(items, 40);
  const big = cl.filter(c => c.ids.length > 1);
  assert.equal(big.length, 1);
  assert.deepEqual(big[0].ids.slice().sort(), ['a', 'b', 'c']);
  assert.deepEqual([big[0].group, big[0].groups, big[0].lead], ['BRAVO', 1, 'a']);
  assert.equal(cl.filter(c => c.ids[0] === 'e')[0].ids.length, 1);
  assert.equal(cl.length, 3);
  assert.equal(T.cluster(items, 0).length, 5);
  const mixed = T.cluster([{id: 'a', x: 0, y: 0, group: 'A', prio: 1}, {id: 'b', x: 5, y: 5, group: 'B', prio: 2}], 30);
  assert.deepEqual([mixed[0].group, mixed[0].groups, mixed[0].lead], ['', 2, 'b']);
  assert.equal(T.clusterRadius(0.2), 0);
  assert.ok(T.clusterRadius(1) > 0 && T.clusterRadius(20) > T.clusterRadius(1));
  assert.deepEqual([T.labelMode(1), T.labelMode(8), T.labelMode(80)], ['full', 'name', 'none']);
});

test('plaques : ni chevauchement entre elles ni sur un symbole, priorité à la piste choisie', () => {
  const hit = (a, b) => a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  const items = [];
  for (let i = 0; i < 40; i++) items.push({id: 'i' + i, x: 200 + (i % 8) * 46, y: 200 + Math.floor(i / 8) * 40, r: 12, w: 90, h: 22, prio: i});
  const out = T.placeLabels(items, {gap: 6});
  const rects = [];
  items.forEach(it => { const p = out[it.id]; if (p) rects.push({id: it.id, x: it.x + p.dx, y: it.y + p.dy, w: it.w, h: it.h}); });
  assert.ok(rects.length >= 10, 'des plaques sont posées');
  assert.ok(rects.length < items.length, 'les autres sont masquées plutôt que superposées');
  for (let i = 0; i < rects.length; i++) {
    for (let j = i + 1; j < rects.length; j++) assert.equal(hit(rects[i], rects[j]), false, rects[i].id + ' / ' + rects[j].id);
    items.forEach(it => assert.equal(hit(rects[i], {x: it.x - it.r, y: it.y - it.r, w: it.r * 2, h: it.r * 2}), false, rects[i].id + ' sur le symbole ' + it.id));
  }
  // Seule sur la carte : à droite du symbole, sans trait de rappel superflu.
  const alone = T.placeLabels([{id: 'a', x: 50, y: 50, r: 10, w: 80, h: 20, prio: 1}], {gap: 6});
  assert.deepEqual([alone.a.slot, alone.a.dx, alone.a.dy], [0, 16, -10]);
  assert.equal(T.leader(alone.a, 80, 20, 10), null);
  // La piste choisie garde sa plaque même au milieu d'un paquet serré.
  const tight = [];
  for (let i = 0; i < 30; i++) tight.push({id: 't' + i, x: 300 + (i % 6) * 14, y: 300 + Math.floor(i / 6) * 14, r: 10, w: 120, h: 38, prio: 50 - i});
  tight.push({id: 'sel', x: 328, y: 328, r: 10, w: 120, h: 38, prio: 100000, must: true});
  assert.ok(T.placeLabels(tight, {gap: 6}).sel);
  assert.equal(T.placeLabels([{id: 'z', x: 0, y: 0, r: 8, w: 0, h: 0}]).z, null);
  const ln = T.leader({dx: 60, dy: -40}, 80, 20, 10);
  assert.ok(ln && ln.x2 === 60 && ln.y2 === -20 && Math.hypot(ln.x1, ln.y1) > 10);
});

test('marqueurs Arma : icône, symbole OTAN, couleur, position, tracé, pinceau', () => {
  assert.deepEqual(T.markerSpec(SYM, 'mil_objective'), {icon: 'objective', nato: '', affil: ''});
  assert.equal(T.markerSpec(SYM, 'hd_flag').icon, 'flag');
  assert.equal(T.markerSpec(SYM, 'flag_NATO').icon, 'flag');
  assert.equal(T.markerSpec(SYM, 'respawn_air').icon, 'heli');
  assert.equal(T.markerSpec(SYM, 'respawn_autre').icon, 'respawn');
  assert.equal(T.markerSpec(SYM, 'loc_Hospital').icon, 'medical');
  assert.equal(T.markerSpec(SYM, 'jamais_vu').icon, 'dot');
  assert.deepEqual(T.markerSpec(SYM, 'o_armor'), {icon: '', nato: 'armor', affil: 'H'});
  assert.deepEqual(T.markerSpec(SYM, 'b_air'), {icon: '', nato: 'rotary', affil: 'F'});
  assert.deepEqual(T.markerSpec(SYM, 'n_uav'), {icon: '', nato: 'uav', affil: 'N'});
  assert.equal(T.markerColor('ColorRed'), '#ff5f56');
  assert.equal(T.markerColor('colorBLUFOR'), '#6cb8ff');
  assert.equal(T.markerColor('#123456'), '#123456');
  assert.equal(T.markerColor('', '#abcdef'), '#abcdef');
  assert.deepEqual(T.markerPos({pos: [1234.5, 6789, 0]}), [1234.5, 6789]);
  assert.deepEqual(T.markerPos({pos: '[1234.5,6789,0]'}), [1234.5, 6789]);
  assert.deepEqual(T.markerPos({x: 10, y: 20}), [10, 20]);
  assert.deepEqual(T.markerPos({pos: [[5, 6], [7, 8]]}), [5, 6]);
  assert.equal(T.markerPos({pos: [0, 0]}), null);
  assert.equal(T.markerPos({}), null);
  assert.deepEqual(T.markerPath({polyline: [1, 2, 3, 4, 5, 6]}), [[1, 2], [3, 4], [5, 6]]);
  assert.deepEqual(T.markerPath({points: [[1, 2], {x: 3, y: 4}]}), [[1, 2], [3, 4]]);
  assert.equal(T.brush('Border').fill, 0);
  assert.equal(T.brush('FDiagonal').hatch, 'diag');
  assert.equal(T.brush('Cross').hatch, 'cross');
  assert.ok(T.brush('SolidFull').fill > T.brush('Solid').fill);
  assert.ok(T.brush('Solid', 0.5).fill < T.brush('Solid', 1).fill);
  const svg = T.iconSvg('checkpoint', '#f2d36b', 24, '3');
  assert.ok(svg.indexOf('<svg') === 0 && svg.indexOf('>3</text>') > 0 && svg.indexOf('#f2d36b') > 0);
  assert.ok(T.iconSvg('inconnue', '#fff').indexOf(T.ICONS.dot.d) > 0);
});

test('luminosité du fond : voile décroissant, photo lisible au réglage par défaut', () => {
  assert.equal(T.veilFor(100), 0);
  assert.ok(T.veilFor(55) > 0.12 && T.veilFor(55) < 0.26);
  assert.ok(T.veilFor(0) > 0.6 && T.veilFor(0) <= 0.72);
  let prev = 1;
  for (let b = 0; b <= 100; b += 5) { const v = T.veilFor(b); assert.ok(v <= prev); prev = v; }
  assert.equal(T.veilFor('x'), T.veilFor(55));
});

test('recherche : grille en tête, débuts de mot avant le reste', () => {
  const entries = [
    {label: 'BRAVO 2', sub: '× 4', kind: 'group', pos: [1, 1], id: 'g'}, {label: 'ALPHA 1-2', sub: 'ALPHA 1', kind: 'track', pos: [2, 2], id: 'a'},
    {label: 'Zébra', sub: 'BRAVO 2', kind: 'track', pos: [3, 3], id: 'z'}, {label: 'OBJ FAUCON', sub: '156 174', kind: 'marker', pos: [4, 4], id: 'm'}
  ];
  const grid = s => (/^\d{3}\s?\d{3}$/.test(s) ? [100, 200] : null);
  assert.deepEqual(T.suggest('br', entries, grid).map(e => e.id), ['g', 'z']);
  assert.deepEqual(T.suggest('fau', entries, grid).map(e => e.id), ['m']);
  assert.deepEqual(T.suggest('zebra', entries, grid).map(e => e.id), ['z']);
  assert.deepEqual(T.suggest('042 116', entries, grid)[0], {label: '042 116', sub: '', kind: 'grid', pos: [100, 200], id: 'grid'});
  assert.deepEqual(T.suggest('', entries, grid), []);
  assert.equal(T.suggest('a', entries, grid, 1).length, 1);
});
