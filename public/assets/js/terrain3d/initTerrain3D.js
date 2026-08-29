/**
 * Point d'entrée public — initTerrain3D(container, options)
 *
 * Usage (module ES) :
 *   import { initTerrain3D } from '/public/assets/js/terrain3d/initTerrain3D.js';
 *   const terrain = await initTerrain3D(document.getElementById('map'), { ... });
 *
 * Usage (global, après import map) :
 *   const terrain = await window.initTerrain3D(container, options);
 */
import { Terrain3DRenderer } from './Terrain3DRenderer.js';

let _depsPromise = null;

/**
 * Charge Three.js et les dépendances depuis le CDN jsDelivr (ou deps injectés).
 * @param {{ threeBase?: string }} [config]
 */
export async function loadThreeDeps(config) {
  if (_depsPromise) return _depsPromise;

  config = config || {};
  const base = config.threeBase || 'https://cdn.jsdelivr.net/npm/three@0.160.0';

  _depsPromise = Promise.all([
    import(/* @vite-ignore */ base + '/build/three.module.js'),
    import(/* @vite-ignore */ base + '/examples/jsm/controls/OrbitControls.js'),
    import(/* @vite-ignore */ base + '/examples/jsm/renderers/CSS2DRenderer.js'),
  ]).then(function (mods) {
    return {
      THREE: mods[0],
      OrbitControls: mods[1].OrbitControls,
      CSS2DRenderer: mods[2].CSS2DRenderer,
      CSS2DObject: mods[2].CSS2DObject,
    };
  });

  return _depsPromise;
}

/**
 * Initialise le renderer terrain 3D.
 *
 * @param {HTMLElement} container — élément hôte (position relative recommandée)
 * @param {object} [options]
 * @param {string} [options.textureUrl] — PNG/JPG carte
 * @param {string} [options.heightmapUrl] — image niveaux de gris
 * @param {number[][]|number[]} [options.heightData] — grille Z alternative
 * @param {number} [options.width=1024] — largeur monde
 * @param {number} [options.height=1024] — profondeur monde
 * @param {number} [options.heightScale=1.8] — exagération verticale
 * @param {number} [options.minAltitude=0]
 * @param {number} [options.maxAltitude=400]
 * @param {number} [options.segments=128] — subdivisions du mesh
 * @param {Array} [options.markers] — marqueurs tactiques
 * @param {object} [threeDeps] — dépendances Three pré-chargées (tests / bundler)
 * @returns {Promise<Terrain3DRenderer>}
 */
export async function initTerrain3D(container, options, threeDeps) {
  const deps = threeDeps || (await loadThreeDeps(options && options.threeBase ? { threeBase: options.threeBase } : undefined));
  const renderer = new Terrain3DRenderer(container, options || {}, deps);
  await renderer.ready();
  return renderer;
}

export { Terrain3DRenderer };
export { HeightmapLoader } from './HeightmapLoader.js';
export { TerrainGeometryBuilder } from './TerrainGeometryBuilder.js';
export { TerrainMaterialFactory } from './TerrainMaterial.js';
export { TerrainCameraControls } from './TerrainCameraControls.js';
export { TerrainOverlayManager } from './TerrainOverlayManager.js';

/* Exposition globale pour intégration legacy (pages non-module). */
if (typeof window !== 'undefined') {
  window.initTerrain3D = initTerrain3D;
  window.Terrain3DRenderer = Terrain3DRenderer;
}
