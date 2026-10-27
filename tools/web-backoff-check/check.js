// Contrôle de la pause d'envoi du client web Overwatch (public/assets/js/atak-socket.js), NET-006.
//
// Charge le script dans un bac à sable (module vm de Node) avec un faux window, une horloge simulée et un fetch
// simulé, puis rejoue les suites d'échecs et de succès que la page /atak rencontre : panne (Athena injoignable),
// maintenance 503 avec Retry-After, frein 429, 403. Aucune requête réseau, aucune dépendance.
//   node tools/web-backoff-check/check.js [chemin de atak-socket.js]
// Sortie : lignes PASS/FAIL ; code de sortie 0 si tout passe, 1 sinon. Sur le script d'avant le correctif, les
// contrôles A, B2, B3, E, G et I échouent (c'est le défaut corrigé) ; U, B1, C, D, F, H, T et M passent sur les deux
// (le frein 429 / 403 doit rester exactement comme avant).
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const file = process.argv[2] || path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'atak-socket.js');
const src = fs.readFileSync(file, 'utf8');
const BASE = 'https://athena.test';
const ROSTER = BASE + '/api/units?mapId=1&include_gateway=1';
const PING = BASE + '/api/atak/ping';
const CHAT = BASE + '/api/chat';
let failures = 0;

function check(name, ok, detail) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name + ' : ' + detail);
  if (!ok) failures += 1;
}

/** Nouvelle page : le script est rechargé, son état repart de zéro. */
function page() {
  let now = 1000000;
  const queue = [];
  let fetchCalls = 0;
  class FakeDate extends Date {}
  FakeDate.now = () => now;
  const win = {
    ATAK_API_BASE: BASE,
    location: { origin: BASE, pathname: '/atak' },
    setTimeout: () => 0,
    fetch: () => {
      fetchCalls += 1;
      const r = queue.shift();
      if (!r || r.reject) return Promise.reject(new TypeError('Failed to fetch'));
      const headers = new Headers();
      if (r.retryAfter) headers.set('Retry-After', String(r.retryAfter));
      return Promise.resolve(new Response(r.body || '{"ok":true}', { status: r.status, headers }));
    },
  };
  const ctx = vm.createContext({ window: win, Date: FakeDate, Math, Promise, Response, Headers, JSON });
  vm.runInContext(src, ctx, { filename: file });
  const api = win.ATAKSocket;
  return {
    api,
    calls: () => fetchCalls,
    /** Une requête, et la réponse que le faux Athena lui fait ({status, retryAfter} ou {reject: true}). */
    async send(url, method, response) {
      queue.push(response);
      try {
        await win.fetch(url, { method });
      } catch (e) { /* le rejet est attendu pour une panne */ }
    },
    /** Durée de la pause en cours, au dixième de seconde, sans faire avancer l'horloge du test. */
    pause() {
      const t0 = now;
      while (api.isApiPaused() && now - t0 < 700000) now += 100;
      const len = (now - t0) / 1000;
      now = t0;
      return len;
    },
    /** Laisse passer la pause en cours (la page ne relit rien pendant une pause). */
    wait() {
      while (api.isApiPaused()) now += 100;
      now += 100;
    },
  };
}

const ok = { status: 200 };
const down = { reject: true };
const fmt = (s) => s.toFixed(1);

async function main() {
  console.log('Script : ' + file);

  // [U] Avant l'échelle (1 échec réseau) : petite pause de 8 s, comme avant (gigue de 20 % au plus).
  let p = page();
  await p.send(ROSTER, 'GET', down);
  const u = p.pause();
  check('U  pause avant l\'échelle', p.api.getBackoffSec() === 0 && u >= 7.9 && u <= 9.7, 'pause ' + fmt(u) + ' s (attendu 8 à 9,6 s)');

  // [A] Panne : entrée au 3e échec sur 10 s (+ gigue 3 à 5 s), sortie au premier succès (effectifs ou ping).
  for (const [label, url] of [['effectifs', ROSTER], ['ping', PING]]) {
    p = page();
    for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', down); if (i < 2) p.wait(); }
    const a1 = p.pause();
    const rung = p.api.getBackoffSec();
    if (label === 'effectifs') {
      check('A1 panne : entrée au 3e échec sur 10 s', rung === 10 && a1 >= 9.9 && a1 <= 15.1, 'palier ' + rung + ' s, pause ' + fmt(a1) + ' s');
    }
    await p.send(url, 'GET', ok);
    check('A2 panne : sortie au premier succès (' + label + ')', !p.api.isApiPaused() && !p.api.isDeferred() && p.api.getBackoffSec() === 0,
      'après 1 succès : pause ' + fmt(p.pause()) + ' s, différé ' + p.api.isDeferred());
  }

  // [B] Maintenance 503 + Retry-After 120 : délai respecté (gigue 15 s au plus), puis sortie au premier succès.
  p = page();
  for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', { status: 503, retryAfter: 120 }); if (i < 2) p.wait(); }
  const b1 = p.pause();
  check('B1 maintenance : Retry-After 120 respecté', b1 >= 119.9 && b1 <= 135.1, 'pause ' + fmt(b1) + ' s');
  await p.send(PING, 'GET', ok);
  check('B2 maintenance : sortie au premier succès après la fin', !p.api.isApiPaused() && p.api.getBackoffSec() === 0,
    'après 1 succès : pause ' + fmt(p.pause()) + ' s');
  const bs = [];
  for (let n = 0; n < 100; n++) {
    const q = page();
    for (let i = 0; i < 3; i++) { await q.send(ROSTER, 'GET', { status: 503, retryAfter: 120 }); if (i < 2) q.wait(); }
    bs.push(q.pause());
  }
  check('B3 maintenance : gigue du Retry-After bornée à 15 s', Math.min(...bs) >= 119.9 && Math.max(...bs) <= 135.1 && Math.max(...bs) - Math.min(...bs) >= 2,
    '100 tirages : de ' + fmt(Math.min(...bs)) + ' à ' + fmt(Math.max(...bs)) + ' s (attendu 120 à 135 s, écart ≥ 2 s)');

  // [C] Frein 429 + Retry-After 60 : 60 s exactement, puis un palier de moins tous les deux succès.
  p = page();
  for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', { status: 429, retryAfter: 60 }); if (i < 2) p.wait(); }
  const c1 = p.pause();
  await p.send(PING, 'GET', ok);
  const c2 = p.pause();
  await p.send(PING, 'GET', ok);
  check('C  429 : 60 s exactement, deux succès pour sortir', c1 >= 59.9 && c1 <= 60.1 && c2 >= 44.9 && c2 <= 45.1 && !p.api.isApiPaused(),
    'pause ' + fmt(c1) + ' s, après 1 succès ' + fmt(c2) + ' s, après 2 : ' + fmt(p.pause()) + ' s');

  // [D] 403 : échelle longue (45 s exactement), deux succès pour sortir.
  p = page();
  for (let i = 0; i < 3; i++) { await p.send(CHAT, 'POST', { status: 403 }); if (i < 2) p.wait(); }
  const d1 = p.pause();
  await p.send(PING, 'GET', ok);
  const dStill = p.api.isApiPaused();
  await p.send(PING, 'GET', ok);
  check('D  403 : échelle longue (45 s exactement), deux succès pour sortir', d1 >= 44.9 && d1 <= 45.1 && dStill && !p.api.isApiPaused(),
    'pause ' + fmt(d1) + ' s, encore en pause après 1 succès : ' + dStill);

  // [E] Panne longue : paliers 10, 20, 30, 45 s, jamais plus de 60 s de pause avec la gigue ; sortie au 1er succès.
  p = page();
  const rungs = [];
  let maxPause = 0;
  for (let i = 0; i < 7; i++) {
    await p.send(ROSTER, 'GET', down);
    if (i >= 2) { rungs.push(p.api.getBackoffSec()); maxPause = Math.max(maxPause, p.pause()); }
    p.wait();
  }
  check('E1 panne longue : paliers courts plafonnés', rungs.join(',') === '10,20,30,45,45' && maxPause <= 60.1,
    'paliers ' + rungs.join(',') + ' s, pause la plus longue ' + fmt(maxPause) + ' s');
  await p.send(ROSTER, 'GET', down);
  await p.send(PING, 'GET', ok);
  check('E2 panne longue : sortie au premier succès, même au dernier palier', !p.api.isApiPaused() && p.api.getBackoffSec() === 0,
    'après 1 succès : pause ' + fmt(p.pause()) + ' s');

  // [F] 429 répété : échelle longue inchangée.
  p = page();
  const tr = [];
  for (let i = 0; i < 8; i++) {
    await p.send(ROSTER, 'GET', { status: 429 });
    if (i >= 2) tr.push(p.api.getBackoffSec());
    p.wait();
  }
  check('F  429 répété : échelle longue inchangée', tr.join(',') === '45,75,150,300,600,600', 'paliers ' + tr.join(',') + ' s');

  // [H] Panne puis 429 dans le même épisode : échelle longue, deux succès par palier.
  p = page();
  for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', down); p.wait(); }
  await p.send(ROSTER, 'GET', { status: 429 });
  const hRung = p.api.getBackoffSec();
  let hOk = 0;
  while (p.api.isApiPaused() && hOk < 20) { await p.send(PING, 'GET', ok); hOk += 1; }
  check('H  panne puis 429 : échelle longue, deux succès par palier', hRung === 75 && hOk === 4,
    'palier ' + hRung + ' s, succès pour sortir : ' + hOk);

  // [T] Frein (429, 403) : délais exacts, sans gigue, comme avant (Athena limite chaque compte ou chaque adresse à part).
  const t429 = [];
  const t403 = [];
  const tLadder = [];
  for (let n = 0; n < 30; n++) {
    let q = page();
    await q.send(ROSTER, 'GET', { status: 429, retryAfter: 20 });
    t429.push(q.pause());
    q = page();
    await q.send(CHAT, 'POST', { status: 403 });
    t403.push(q.pause());
    q = page();
    for (let i = 0; i < 3; i++) { await q.send(ROSTER, 'GET', { status: 429, retryAfter: 60 }); if (i < 2) q.wait(); }
    tLadder.push(q.pause());
  }
  const exact = (l, v) => Math.min(...l) >= v - 0.1 && Math.max(...l) <= v + 0.1;
  check('T  frein sans gigue', exact(t429, 20) && exact(t403, 20) && exact(tLadder, 60),
    '30 tirages : 429 isolé ' + fmt(Math.min(...t429)) + ' à ' + fmt(Math.max(...t429)) + ' s, 403 isolé ' + fmt(Math.min(...t403))
    + ' à ' + fmt(Math.max(...t403)) + ' s, 429 au 3e échec ' + fmt(Math.min(...tLadder)) + ' à ' + fmt(Math.max(...tLadder))
    + ' s (attendu 20, 20 et 60 s)');

  // [I] Un frein terminé est oublié : une panne qui suit, sans succès entre les deux, repart sur l'échelle courte.
  p = page();
  for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', { status: 429 }); if (i < 2) p.wait(); }
  await p.send(PING, 'GET', ok);
  await p.send(PING, 'GET', ok);
  const iOut = !p.api.isApiPaused();
  for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', down); if (i < 2) p.wait(); }
  check('I  après un frein, une panne repart sur l\'échelle courte', iOut && p.api.getBackoffSec() === 10,
    'sorti du frein : ' + iOut + ', palier de la panne suivante ' + p.api.getBackoffSec() + ' s');

  // [G] Gigue : 100 pages coupées en même temps ne reprennent plus à la même seconde.
  const gs = [];
  for (let n = 0; n < 100; n++) {
    const q = page();
    for (let i = 0; i < 3; i++) { await q.send(ROSTER, 'GET', down); if (i < 2) q.wait(); }
    gs.push(q.pause());
  }
  check('G  gigue de la pause de panne', Math.min(...gs) >= 9.9 && Math.max(...gs) <= 15.1 && Math.max(...gs) - Math.min(...gs) >= 3,
    '100 tirages : de ' + fmt(Math.min(...gs)) + ' à ' + fmt(Math.max(...gs)) + ' s (attendu 10 à 15 s, écart ≥ 3 s)');

  // [M] Pendant une pause, un envoi (POST) reçoit une réponse locale sans partir vers Athena, comme avant.
  p = page();
  for (let i = 0; i < 3; i++) { await p.send(ROSTER, 'GET', down); if (i < 2) p.wait(); }
  const before = p.calls();
  await p.send(CHAT, 'POST', ok);
  check('M  envoi pendant la pause : réponse locale, rien ne part', p.calls() === before,
    'appels réseau avant ' + before + ', après ' + p.calls());

  console.log('\n=== BILAN : ' + (failures === 0 ? 'aucun échec' : failures + ' ÉCHEC(S)') + ' ===');
  process.exit(failures === 0 ? 0 : 1);
}

main().catch((e) => { console.error(e); process.exit(2); });
