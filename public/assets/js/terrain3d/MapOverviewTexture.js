/**
 * Compose une texture d’aperçu carte pour le mesh 3D (tuiles bas zoom, CORS).
 * Évite le repli hillshade (ombrage gris) qui donnait un relief sans carte.
 * Remplit les trous : retry + upscale parent (z-1) + teinte mer (jamais noir).
 */
import { loadImage } from 'atak-terrain3d/utils.js';

/**
 * @param {string} url
 * @param {number} [retries=2]
 * @returns {Promise<HTMLImageElement>}
 */
function loadImageRetry(url, retries) {
  const max = retries == null ? 2 : retries;
  let attempt = 0;
  function once() {
    return loadImage(url).catch(function (err) {
      attempt += 1;
      if (attempt > max) throw err;
      return new Promise(function (resolve) {
        window.setTimeout(resolve, 120 * attempt);
      }).then(once);
    });
  }
  return once();
}

/**
 * @param {string} pattern
 * @param {number} z
 * @param {number} x
 * @param {number} y
 */
function tileUrl(pattern, z, x, y) {
  return pattern
    .replace('{z}', String(z))
    .replace('{x}', String(x))
    .replace('{y}', String(y));
}

/**
 * @param {string} pattern — URL avec {z}/{x}/{y}
 * @param {number} [zoom=1]
 * @param {{ tileSize?: number, minCoverage?: number, fillColor?: string }} [opts]
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function stitchTileOverview(pattern, zoom, opts) {
  opts = opts || {};
  const z = Math.max(0, Math.min(3, zoom == null ? 1 : zoom | 0));
  const n = Math.pow(2, z);
  const tileSize = Math.max(64, Math.min(512, Number(opts.tileSize) || 256));
  const minCoverage = opts.minCoverage != null ? Number(opts.minCoverage) : 0.85;
  const fillColor = opts.fillColor || '#1e4a5c';
  const canvas = document.createElement('canvas');
  canvas.width = n * tileSize;
  canvas.height = n * tileSize;
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Canvas 2D indisponible');
  ctx.fillStyle = fillColor;
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  const missing = [];
  let loaded = 0;
  const jobs = [];
  for (let y = 0; y < n; y += 1) {
    for (let x = 0; x < n; x += 1) {
      const url = tileUrl(pattern, z, x, y);
      jobs.push(
        loadImageRetry(url, 2)
          .then(function (img) {
            ctx.drawImage(img, x * tileSize, y * tileSize, tileSize, tileSize);
            loaded += 1;
          })
          .catch(function () {
            missing.push({ x: x, y: y });
          })
      );
    }
  }
  await Promise.all(jobs);

  /* Comble les trous avec la tuile parent (z-1) étirée — évite les rectangles noirs. */
  if (missing.length && z > 0) {
    const parentJobs = missing.map(function (cell) {
      const px = Math.floor(cell.x / 2);
      const py = Math.floor(cell.y / 2);
      const url = tileUrl(pattern, z - 1, px, py);
      return loadImageRetry(url, 1)
        .then(function (img) {
          const half = tileSize;
          const sx = (cell.x % 2) * (img.naturalWidth / 2);
          const sy = (cell.y % 2) * (img.naturalHeight / 2);
          const sw = img.naturalWidth / 2;
          const sh = img.naturalHeight / 2;
          ctx.drawImage(
            img,
            sx, sy, sw, sh,
            cell.x * half, cell.y * half, half, half
          );
          loaded += 1;
        })
        .catch(function () { /* laisse le fill mer */ });
    });
    await Promise.all(parentJobs);
  }

  const total = n * n;
  const coverage = total > 0 ? loaded / total : 0;
  if (loaded < 1 || coverage < minCoverage) {
    throw new Error(
      'Overview incomplete (' + loaded + '/' + total + ' @z' + z + ')'
    );
  }
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
  g.addColorStop(0, '#2a5a4a');
  g.addColorStop(0.45, '#1e4a5c');
  g.addColorStop(1, '#163848');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, s, s);

  ctx.strokeStyle = 'rgba(180, 210, 190, 0.22)';
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

  ctx.fillStyle = 'rgba(236, 245, 240, 0.82)';
  ctx.font = 'bold ' + Math.max(14, Math.floor(s / 28)) + 'px ui-monospace, monospace';
  ctx.textAlign = 'center';
  ctx.fillText(label || 'CARTE', s / 2, s / 2);

  return canvas;
}
