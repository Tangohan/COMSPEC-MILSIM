/**
 * Démo C2 Athena — carte 2D + 3D, symbologie tactique, UI refonte.
 */
import { initMapC2 } from './initMapC2.js';
import { createDemoUnits, createDemoTracks, createDemoLog } from './demo-data.js';
import { createDemoMapTexture, createDemoHeightmap } from '../terrain3d/demo-assets.js';

const WORLD = 1024;
const units = createDemoUnits(48);
const tracks = createDemoTracks(units);
const logEntries = createDemoLog();

const textureUrl = createDemoMapTexture(512);
const heightmapUrl = createDemoHeightmap(256);

/* Journal latéral */
const logEl = document.getElementById('tac-side-log');
if (logEl) {
  logEl.innerHTML = logEntries.map(function (e) {
    return '<li><time class="mono">' + e.time + '</time> ' + e.text + '</li>';
  }).join('');
}

/* Carte 2D : CRS simple + image overlay */
function build2DConfig() {
  const L = window.L;
  if (!L) return null;
  const bounds = [[0, 0], [WORLD, WORLD]];
  return {
    CRS: L.CRS.Simple,
    minZoom: -1,
    maxZoom: 4,
    defaultZoom: 0,
    center: [WORLD / 2, WORLD / 2],
    tilePattern: null,
    attribution: 'Démo Athena',
    tileSize: 256,
    imageOverlay: { url: textureUrl, bounds: bounds },
  };
}

const config = build2DConfig();

const api = await initMapC2({
  map2dEl: document.getElementById('tac-map-2d'),
  map3dEl: document.getElementById('tac-map-3d'),
  uiRoot: document.querySelector('.tac-c2-shell'),
  controlsEl: document.querySelector('.tac-map-controls'),
  contextPanelEl: document.getElementById('tac-context-panel'),
  config: config,
  markers: units,
  tracks: tracks,
  clustering: true,
  terrain: {
    textureUrl: textureUrl,
    heightmapUrl: heightmapUrl,
    width: WORLD,
    height: WORLD,
    heightScale: 2.2,
    minAltitude: 0,
    maxAltitude: 420,
    segments: 128,
    markers: units,
  },
});

/* Image overlay 2D (pas de tuiles Arma en démo) */
if (config && config.imageOverlay && api.map) {
  window.L.imageOverlay(config.imageOverlay.url, config.imageOverlay.bounds).addTo(api.map);
  api.map.fitBounds(config.imageOverlay.bounds);
}

api.setEntities(units);
api.setTracks(tracks);

/* Panneau latéral unité sélectionnée */
window.addEventListener('atak:entity-selected', function (ev) {
  const e = ev.detail;
  if (!e) return;
  const panel = document.getElementById('tac-side-unit');
  if (!panel) return;
  panel.innerHTML =
    '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Indicatif</span><span class="tac-c2-side__v mono">' + (e.callsign || '—') + '</span></div>'
    + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Équipe</span><span class="tac-c2-side__v">' + (e.role || '—') + '</span></div>'
    + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">État</span><span class="tac-c2-side__v">' + (e.status || 'ONLINE') + '</span></div>'
    + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Altitude</span><span class="tac-c2-side__v mono">' + (e.altitude != null ? e.altitude + ' m' : '—') + '</span></div>'
    + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Vitesse</span><span class="tac-c2-side__v mono">' + (e.speed != null ? e.speed + ' km/h' : '—') + '</span></div>'
    + '<div class="tac-c2-side__row"><span class="tac-c2-side__k">Grille</span><span class="tac-c2-side__v mono">' + (e.grid || '—') + '</span></div>';
});

/* Animation légère des unités */
let tick = 0;
setInterval(function () {
  tick += 1;
  units.forEach(function (u, i) {
    if (i >= 12) return;
    u.x += Math.sin(tick / 30 + i) * 0.4;
    u.y += Math.cos(tick / 35 + i) * 0.35;
    u.heading = ((u.heading || 0) + 1) % 360;
  });
  api.setEntities(units);
}, 200);

console.info('[Athena C2] Demo prête —', units.length, 'symboles tactiques');
