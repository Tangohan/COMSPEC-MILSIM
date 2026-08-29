/**
 * Compose une texture d’aperçu carte pour le mesh 3D (tuiles bas zoom, CORS).
 * Évite le repli hillshade (ombrage gris) qui donnait un relief sans carte.
 */
import { loadImage } from './utils.js';

/**
 * @param {string} pattern — URL avec {z}/{x}/{y}
 * @param {number} [zoom=1]
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function stitchTileOverview(pattern, zoom) {
  const z = Math.max(0, Math.min(3, zoom == null ? 1 : zoom | 0));
  const n = Math.pow(2, z);
  const tileSize = 256;
  const canvas = document.createElement('canvas');
  canvas.width = n * tileSize;
  canvas.height = n * tileSize;
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Canvas 2D indisponible');
  ctx.fillStyle = '#1a2330';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  let loaded = 0;
  const jobs = [];
  for (let y = 0; y < n; y += 1) {
    for (let x = 0; x < n; x += 1) {
      const url = pattern
        .replace('{z}', String(z))
        .replace('{x}', String(x))
        .replace('{y}', String(y));
      jobs.push(
        loadImage(url)
          .then(function (img) {
            ctx.drawImage(img, x * tileSize, y * tileSize, tileSize, tileSize);
            loaded += 1;
          })
          .catch(function () { /* tuile manquante : fond sombre */ })
      );
    }
  }
  await Promise.all(jobs);
  if (loaded < 1) throw new Error('Aucune tuile overview chargée');
  return canvas;
}

/**
 * Aperçu de secours (grille tactique) — jamais le hillshade.
 * @param {number} [size=1024]
 * @param {string} [label='CARTE']
 * @returns {HTMLCanvasElement}
 */
export function createFallbackMapCanvas(size, label) {
  const s = size || 1024;
  const canvas = document.createElement('canvas');
  canvas.width = s;
  canvas.height = s;
  const ctx = canvas.getContext('2d');
  if (!ctx) return canvas;

  const g = ctx.createLinearGradient(0, 0, s, s);
  g.addColorStop(0, '#243044');
  g.addColorStop(1, '#15202e');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, s, s);

  ctx.strokeStyle = 'rgba(148, 163, 184, 0.28)';
  ctx.lineWidth = 1;
  const step = Math.max(32, Math.floor(s / 16));
  for (let i = 0; i <= s; i += step) {
    ctx.beginPath();
    ctx.moveTo(i, 0);
    ctx.lineTo(i, s);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(0, i);
    ctx.lineTo(s, i);
    ctx.stroke();
  }

  ctx.fillStyle = 'rgba(226, 232, 240, 0.75)';
  ctx.font = 'bold ' + Math.max(14, Math.floor(s / 28)) + 'px ui-monospace, monospace';
  ctx.textAlign = 'center';
  ctx.fillText(label || 'CARTE', s / 2, s / 2);

  return canvas;
}
