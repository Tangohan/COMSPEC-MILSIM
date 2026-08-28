/**
 * Chargement de la texture diffuse (carte topo / satellite).
 */
import { loadImage } from './utils.js';

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
  }

  /**
   * Charge une texture depuis une URL.
   * @param {string} url
   * @returns {Promise<THREE.Texture>}
   */
  load(url) {
    const self = this;
    const THREE = this.THREE;
    if (!url) return Promise.reject(new Error('textureUrl requis'));
    if (this.currentUrl === url && this.texture) {
      return Promise.resolve(this.texture);
    }
    return new Promise(function (resolve, reject) {
      self.threeLoader.load(
        url,
        function (texture) {
          texture.wrapS = texture.wrapT = THREE.ClampToEdgeWrapping;
          texture.minFilter = THREE.LinearMipmapLinearFilter;
          texture.magFilter = THREE.LinearFilter;
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
    const texture = new THREE.Texture(source);
    texture.wrapS = texture.wrapT = THREE.ClampToEdgeWrapping;
    texture.minFilter = THREE.LinearMipmapLinearFilter;
    texture.magFilter = THREE.LinearFilter;
    texture.needsUpdate = true;
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
