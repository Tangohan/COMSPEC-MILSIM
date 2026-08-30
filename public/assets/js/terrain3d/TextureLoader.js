/**
 * Chargement de la texture diffuse (carte topo / satellite).
 * Important : les canvas d’overview Altis font 848×848 (4×212) — NPOT.
 * Les mipmaps sur NPOT produisent du bruit/static sur de nombreux GPU →
 * copie vers une taille puissance de 2, ou filtre linéaire sans mipmaps.
 */
import { loadImage } from 'atak-terrain3d/utils.js';

function nextPowerOfTwo(n) {
  let v = Math.max(1, n | 0);
  v -= 1;
  v |= v >> 1;
  v |= v >> 2;
  v |= v >> 4;
  v |= v >> 8;
  v |= v >> 16;
  return v + 1;
}

function isPowerOfTwo(n) {
  const v = n | 0;
  return v > 0 && (v & (v - 1)) === 0;
}

/**
 * Copie une source canvas/image vers une toile puissance de 2 (mipmaps sûrs).
 * @param {HTMLCanvasElement|HTMLImageElement} source
 * @returns {HTMLCanvasElement|HTMLImageElement}
 */
export function ensurePowerOfTwoSource(source) {
  const w = source.width || source.naturalWidth || 0;
  const h = source.height || source.naturalHeight || 0;
  if (w < 1 || h < 1) return source;
  if (isPowerOfTwo(w) && isPowerOfTwo(h) && source instanceof HTMLCanvasElement) {
    return source;
  }
  const tw = Math.min(2048, nextPowerOfTwo(w));
  const th = Math.min(2048, nextPowerOfTwo(h));
  const canvas = document.createElement('canvas');
  canvas.width = tw;
  canvas.height = th;
  const ctx = canvas.getContext('2d');
  if (!ctx) return source;
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(source, 0, 0, tw, th);
  return canvas;
}

export class TerrainTextureLoader {
  /**
   * @param {THREE.TextureLoader} threeLoader — instance Three.js TextureLoader
   * @param {THREE} THREE — namespace Three.js (constantes wrap/filter)
   */
  constructor(threeLoader, THREE) {
    this.threeLoader = threeLoader;
    this.THREE = THREE;
    this.currentUrl = null;
    this.texture = null;
    if (this.threeLoader && typeof this.threeLoader.setCrossOrigin === 'function') {
      this.threeLoader.setCrossOrigin('anonymous');
    }
  }

  /**
   * Configure filtres / wrap pour une texture diffuse carte.
   * @param {THREE.Texture} texture
   * @param {{ pot?: boolean }} [opts]
   */
  configureDiffuse(texture, opts) {
    const THREE = this.THREE;
    opts = opts || {};
    texture.wrapS = texture.wrapT = THREE.ClampToEdgeWrapping;
    texture.magFilter = THREE.LinearFilter;
    texture.flipY = true;
    /* Anisotropie modérée : trop haute sur certains GPU accentue le moiré. */
    texture.anisotropy = 4;
    if (opts.pot) {
      texture.minFilter = THREE.LinearMipmapLinearFilter;
      texture.generateMipmaps = true;
    } else {
      /* NPOT : pas de mipmaps (sinon bruit / static WebGL). */
      texture.minFilter = THREE.LinearFilter;
      texture.generateMipmaps = false;
    }
    if (THREE.SRGBColorSpace != null) {
      texture.colorSpace = THREE.SRGBColorSpace;
    }
    texture.needsUpdate = true;
    return texture;
  }

  /**
   * Charge une texture depuis une URL.
   * @param {string} url
   * @returns {Promise<THREE.Texture>}
   */
  load(url) {
    const self = this;
    if (!url) return Promise.reject(new Error('textureUrl requis'));
    if (this.currentUrl === url && this.texture) {
      return Promise.resolve(this.texture);
    }
    return new Promise(function (resolve, reject) {
      self.threeLoader.load(
        url,
        function (texture) {
          const img = texture.image;
          const w = img && (img.width || img.naturalWidth) ? (img.width || img.naturalWidth) : 0;
          const h = img && (img.height || img.naturalHeight) ? (img.height || img.naturalHeight) : 0;
          const pot = isPowerOfTwo(w) && isPowerOfTwo(h);
          if (!pot && img) {
            /* Upscale 212² Altis → 256² pour mipmaps propres. */
            const potSource = ensurePowerOfTwoSource(img);
            texture.image = potSource;
            self.configureDiffuse(texture, { pot: true });
          } else {
            self.configureDiffuse(texture, { pot: pot });
          }
          self.texture = texture;
          self.currentUrl = url;
          resolve(texture);
        },
        undefined,
        reject
      );
    });
  }

  /**
   * Crée une texture Three.js depuis un canvas ou une image HTML.
   * @param {HTMLCanvasElement|HTMLImageElement} source
   * @param {THREE} THREE
   * @returns {THREE.Texture}
   */
  fromSource(source, THREE) {
    const potSource = ensurePowerOfTwoSource(source);
    /* CanvasTexture signale correctement une source canvas (update / color space). */
    const texture = typeof THREE.CanvasTexture === 'function'
      ? new THREE.CanvasTexture(potSource)
      : new THREE.Texture(potSource);
    this.configureDiffuse(texture, { pot: true });
    this.texture = texture;
    this.currentUrl = null;
    return texture;
  }

  dispose() {
    if (this.texture) {
      this.texture.dispose();
      this.texture = null;
    }
    this.currentUrl = null;
  }
}

/** Charge une image HTML (sans passer par Three TextureLoader). */
export async function loadMapImage(url) {
  return loadImage(url);
}
