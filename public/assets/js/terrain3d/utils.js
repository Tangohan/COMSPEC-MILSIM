/**
 * Utilitaires partagés — module Terrain3D Athena/ATAK.
 */

/** Valeur par défaut si une clé d'options est absente. */
export function defaults(options, fallback) {
  const out = Object.assign({}, fallback);
  if (options && typeof options === 'object') {
    Object.keys(options).forEach(function (key) {
      if (options[key] !== undefined) out[key] = options[key];
    });
  }
  return out;
}

/** Clamp numérique. */
export function clamp(value, min, max) {
  return Math.max(min, Math.min(max, Number(value) || min));
}

/** Interpolation bilinéaire sur une grille d'altitudes. */
export function sampleHeightGrid(grid, u, v) {
  if (!grid || !grid.data || !grid.cols || !grid.rows) return 0;
  const cols = grid.cols;
  const rows = grid.rows;
  const x = clamp(u, 0, 1) * (cols - 1);
  const y = clamp(v, 0, 1) * (rows - 1);
  const x0 = Math.floor(x);
  const y0 = Math.floor(y);
  const x1 = Math.min(cols - 1, x0 + 1);
  const y1 = Math.min(rows - 1, y0 + 1);
  const tx = x - x0;
  const ty = y - y0;
  const h00 = grid.data[y0 * cols + x0];
  const h10 = grid.data[y0 * cols + x1];
  const h01 = grid.data[y1 * cols + x0];
  const h11 = grid.data[y1 * cols + x1];
  const top = h00 + (h10 - h00) * tx;
  const bottom = h01 + (h11 - h01) * tx;
  return top + (bottom - top) * ty;
}

/** Convertit des coordonnées monde (x, z) en indices normalisés 0..1. */
export function worldToUV(x, z, worldWidth, worldDepth) {
  return {
    u: clamp(x / worldWidth + 0.5, 0, 1),
    v: clamp(z / worldDepth + 0.5, 0, 1),
  };
}

/** Hauteur terrain interpolée à partir d'une position monde. */
export function heightAtWorld(grid, x, z, worldWidth, worldDepth, heightScale, minAlt, maxAlt) {
  if (!grid || !grid.data) return minAlt || 0;
  const uv = worldToUV(x, z, worldWidth, worldDepth);
  const normalized = sampleHeightGrid(grid, uv.u, uv.v);
  if (grid.normalized) {
    return (minAlt + normalized * (maxAlt - minAlt)) * heightScale;
  }
  return normalized * heightScale;
}

/** Attend le chargement d'une image (URL ou HTMLImageElement). */
export function loadImage(src) {
  return new Promise(function (resolve, reject) {
    if (src instanceof HTMLImageElement) {
      if (src.complete && src.naturalWidth) resolve(src);
      else {
        src.onload = function () { resolve(src); };
        src.onerror = reject;
      }
      return;
    }
    const img = new Image();
    if (typeof src === 'string' && !src.startsWith('data:')) img.crossOrigin = 'anonymous';
    img.onload = function () { resolve(img); };
    img.onerror = function () { reject(new Error('Impossible de charger l\'image : ' + src)); };
    img.src = src;
  });
}

/** Crée une grille plate de repli (aucune heightmap). */
export function createFlatGrid(cols, rows, altitude) {
  const data = new Float32Array(cols * rows);
  data.fill(altitude || 0);
  return { cols: cols, rows: rows, data: data, normalized: false, isFallback: true };
}

/** Normalise une grille brute vers 0..1. */
export function normalizeGrid(grid, minAlt, maxAlt) {
  if (!grid || !grid.data) return grid;
  const data = grid.data;
  let min = minAlt;
  let max = maxAlt;
  if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) {
    min = Infinity;
    max = -Infinity;
    for (let i = 0; i < data.length; i += 1) {
      if (data[i] < min) min = data[i];
      if (data[i] > max) max = data[i];
    }
    if (!Number.isFinite(min) || max <= min) {
      min = 0;
      max = 1;
    }
  }
  const out = new Float32Array(data.length);
  const span = max - min || 1;
  for (let i = 0; i < data.length; i += 1) {
    out[i] = (data[i] - min) / span;
  }
  return {
    cols: grid.cols,
    rows: grid.rows,
    data: out,
    normalized: true,
    minAlt: min,
    maxAlt: max,
    isFallback: !!grid.isFallback,
  };
}
