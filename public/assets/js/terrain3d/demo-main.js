/**
 * Script principal de la démo Terrain 3D Athena/ATAK.
 */
import { initTerrain3D } from './initTerrain3D.js';
import { createDemoMapTexture, createDemoHeightmap, createDemoMarkers } from './demo-assets.js';

const WORLD = 1024;
const container = document.getElementById('terrain3d-container');
const logEl = document.getElementById('t3d-log');
const hudMode = document.getElementById('hud-mode');
const btnToggle = document.getElementById('btn-toggle-view');
const btnFocus = document.getElementById('btn-focus-alpha');
const btnReset = document.getElementById('btn-reset-cam');
const sliderExag = document.getElementById('slider-exaggeration');
const valExag = document.getElementById('val-exaggeration');
const selectMode = document.getElementById('select-mode');

function log(msg) {
  if (!logEl) return;
  const li = document.createElement('li');
  const t = new Date();
  const time = [t.getHours(), t.getMinutes(), t.getSeconds()]
    .map(function (n) { return String(n).padStart(2, '0'); })
    .join(':');
  li.innerHTML = '<time class="mono">' + time + '</time> ' + msg;
  logEl.prepend(li);
  while (logEl.children.length > 8) logEl.removeChild(logEl.lastChild);
}

function updateToggleLabel(mode) {
  const is3d = mode === '3d';
  if (btnToggle) btnToggle.textContent = is3d ? 'Vue 2D' : 'Vue 3D';
  if (hudMode) hudMode.textContent = is3d ? '3D' : '2D';
  if (selectMode) selectMode.value = is3d ? '3d' : '2d';
}

/* Assets procéduraux — aucun fichier externe requis. */
const textureUrl = createDemoMapTexture(512);
const heightmapUrl = createDemoHeightmap(256);
const markers = createDemoMarkers(WORLD);

log('Génération texture + heightmap procédurales');

const terrain = await initTerrain3D(container, {
  textureUrl: textureUrl,
  heightmapUrl: heightmapUrl,
  width: WORLD,
  height: WORLD,
  heightScale: 1.8,
  minAltitude: 0,
  maxAltitude: 420,
  segments: 128,
  markers: markers,
  fog: true,
  fogDensity: 0.00042,
});

log('Terrain chargé — mesh ' + (128 * 128 * 2) + ' triangles approx.');

/* —— UI —— */
if (btnToggle) {
  btnToggle.addEventListener('click', function () {
    const mode = terrain.toggle2D3D();
    updateToggleLabel(mode);
    log('Bascule vue ' + mode.toUpperCase());
  });
}

if (selectMode) {
  selectMode.addEventListener('change', function () {
    const target = selectMode.value;
    if (terrain.getMode() !== target) {
      terrain.toggle2D3D(target);
      updateToggleLabel(target);
      log('Mode sélectionné : ' + target.toUpperCase());
    }
  });
}

if (sliderExag) {
  sliderExag.addEventListener('input', function () {
    const v = parseFloat(sliderExag.value);
    terrain.setHeightScale(v);
    if (valExag) valExag.textContent = v.toFixed(1) + '×';
  });
}

if (btnFocus) {
  btnFocus.addEventListener('click', function () {
    terrain.focusOnGrid(WORLD * 0.48, WORLD * 0.52);
    log('Focus grille ALPHA-1');
  });
}

if (btnReset) {
  btnReset.addEventListener('click', function () {
    if (terrain.getMode() === '3d') {
      terrain.cameraControls.setDefault3DView();
    }
    log('Caméra réinitialisée');
  });
}

/* Simulation mouvement marqueur */
let tick = 0;
setInterval(function () {
  tick += 1;
  const m = markers.slice();
  const alpha = m.find(function (x) { return x.id === 'alpha'; });
  if (alpha) {
    alpha.x = WORLD * 0.48 + Math.sin(tick / 40) * 18;
    alpha.y = WORLD * 0.52 + Math.cos(tick / 55) * 12;
  }
  terrain.updateMarkers(m);
}, 120);

updateToggleLabel('3d');
