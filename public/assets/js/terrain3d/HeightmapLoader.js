/**
 * Chargement et conversion des données d'altitude.
 * Sources : image niveaux de gris, tableau 2D, grille 1D.
 */
import { createFlatGrid, loadImage, normalizeGrid } from './utils.js';

export class HeightmapLoader {
  /**
   * Extrait une grille d'altitudes depuis une image en niveaux de gris.
   * Chaque pixel est converti en altitude entre minAltitude et maxAltitude.
   *
   * @param {HTMLImageElement|HTMLCanvasElement} image
   * @param {{ minAltitude?: number, maxAltitude?: number, cols?: number, rows?: number }} opts
   * @returns {{ cols: number, rows: number, data: Float32Array, normalized: boolean, minAlt: number, maxAlt: number }}
   */
  static fromImage(image, opts) {
    opts = opts || {};
    const canvas = document.createElement('canvas');
    const cols = opts.cols || image.width;
    const rows = opts.rows || image.height;
    canvas.width = cols;
    canvas.height = rows;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(image, 0, 0, cols, rows);
    const pixels = ctx.getImageData(0, 0, cols, rows).data;
    const minAlt = Number.isFinite(opts.minAltitude) ? opts.minAltitude : 0;
    const maxAlt = Number.isFinite(opts.maxAltitude) ? opts.maxAltitude : 400;
    const span = maxAlt - minAlt || 1;
    const data = new Float32Array(cols * rows);

    for (let y = 0; y < rows; y += 1) {
      for (let x = 0; x < cols; x += 1) {
        const i = (y * cols + x) * 4;
        /* Luminance perceptuelle — chaque pixel devient une altitude. */
        const lum = (pixels[i] * 0.299 + pixels[i + 1] * 0.587 + pixels[i + 2] * 0.114) / 255;
        data[y * cols + x] = minAlt + lum * span;
      }
    }

    return {
      cols: cols,
      rows: rows,
      data: data,
      normalized: false,
      minAlt: minAlt,
      maxAlt: maxAlt,
    };
  }

  /**
   * Convertit un tableau 2D [[z,…],…] ou 1D en grille interne.
   * @param {number[][]|number[]} values
   * @param {{ cols?: number, rows?: number, minAltitude?: number, maxAltitude?: number }} opts
   */
  static fromArray(values, opts) {
    opts = opts || {};
    let rows;
    let cols;
    let flat;

    if (Array.isArray(values[0])) {
      rows = values.length;
      cols = values[0].length;
      flat = new Float32Array(rows * cols);
      for (let y = 0; y < rows; y += 1) {
        for (let x = 0; x < cols; x += 1) {
          flat[y * cols + x] = Number(values[y][x]) || 0;
        }
      }
    } else {
      cols = opts.cols || Math.round(Math.sqrt(values.length));
      rows = opts.rows || Math.ceil(values.length / cols);
      flat = new Float32Array(cols * rows);
      for (let i = 0; i < flat.length; i += 1) {
        flat[i] = Number(values[i]) || 0;
      }
    }

    const grid = {
      cols: cols,
      rows: rows,
      data: flat,
      normalized: false,
    };
    return normalizeGrid(grid, opts.minAltitude, opts.maxAltitude);
  }

  /**
   * Charge une heightmap depuis une URL.
   * @param {string} url
   * @param {{ minAltitude?: number, maxAltitude?: number, segments?: number }} opts
   */
  static async fromUrl(url, opts) {
    opts = opts || {};
    const image = await loadImage(url);
    const segs = opts.segments || 256;
    return HeightmapLoader.fromImage(image, {
      minAltitude: opts.minAltitude,
      maxAltitude: opts.maxAltitude,
      cols: opts.cols || segs + 1,
      rows: opts.rows || segs + 1,
    });
  }

  /**
   * Grille plate de repli lorsque aucune source d'altitude n'est disponible.
   */
  static fallback(segments) {
    const n = (segments || 128) + 1;
    return createFlatGrid(n, n, 0);
  }
}
