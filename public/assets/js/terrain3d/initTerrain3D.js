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
import { Terrain3DRenderer } from 'atak-terrain3d/Terrain3DRenderer.js';

let _depsPromise = null;

/**
 * Base Three.js : vendor local (CSP 'self') ou override.
 * @param {{ threeBase?: string }} [config]
 */
export function resolveThreeBase(config) {
  if (config && config.threeBase) return String(config.threeBase).replace(/\/$/, '');
  if (typeof window !== 'undefined' && window.ATAK_THREE_BASE) {
    return String(window.ATAK_THREE_BASE).replace(/\/$/, '');
  }
  try {
    const here = new URL(import.meta.url);
    return new URL('../../vendor/three', here).pathname.replace(/\/$/, '');
  } catch (e) {
    return '/public/assets/vendor/three';
  }
}

/**
 * Charge Three.js et les addons (OrbitControls, CSS2D) via le même graphe de modules.
 *
 * Critique : OrbitControls / CSS2D font `from 'three'` (import map). Si le core
 * est importé via une autre URL (ex. three.module.js sans ?v=), le navigateur
 * instancie Two.js deux fois → warning « Multiple instances of Three.js » et
 * artefacts texture (moiré / static) sur le mesh terrain.
 *
 * Nécessite un import map :
 *   "three" → …/three.module.js
 *   "three/addons/" → …/examples/jsm/
 * (voir views/atak.php et demos).
 *
 * @param {{ threeBase?: string }} [config]
 */
export async function loadThreeDeps(config) {
  if (_depsPromise) return _depsPromise;

  const base = resolveThreeBase(config);

  _depsPromise = (async function () {
    let THREE;
    let controlsMod;
    let css2dMod;

    try {
      /* Chemin nominal : une seule instance via import map. */
      const mods = await Promise.all([
        import('three'),
        import('three/addons/controls/OrbitControls.js'),
        import('three/addons/renderers/CSS2DRenderer.js'),
      ]);
      THREE = mods[0];
      controlsMod = mods[1];
      css2dMod = mods[2];
    } catch (importMapErr) {
      /* Fallback hors page ATAK : même URL de base pour core + addons. */
      console.warn(
        '[Terrain3D] Import map three/addons indisponible, repli vendor local.',
        importMapErr
      );
      const threeUrl = base + '/build/three.module.js';
      /* Enregistre un import map dynamique impossible ici — on charge le core
         une fois, puis les addons (qui résolvent encore 'three' si un import
         map partiel existe). */
      THREE = await import(/* @vite-ignore */ threeUrl);
      try {
        controlsMod = await import(/* @vite-ignore */ base + '/examples/jsm/controls/OrbitControls.js');
        css2dMod = await import(/* @vite-ignore */ base + '/examples/jsm/renderers/CSS2DRenderer.js');
      } catch (addonErr) {
        throw addonErr;
      }
    }

    if (!THREE || !controlsMod || !controlsMod.OrbitControls || !css2dMod || !css2dMod.CSS2DRenderer) {
      throw new Error('Dépendances Three.js incomplètes (OrbitControls / CSS2D).');
    }

    return {
      THREE: THREE,
      OrbitControls: controlsMod.OrbitControls,
      CSS2DRenderer: css2dMod.CSS2DRenderer,
      CSS2DObject: css2dMod.CSS2DObject,
    };
  })().catch(function (err) {
    _depsPromise = null;
    throw err;
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
export { HeightmapLoader } from 'atak-terrain3d/HeightmapLoader.js';
export { TerrainGeometryBuilder } from 'atak-terrain3d/TerrainGeometryBuilder.js';
export { TerrainMaterialFactory } from 'atak-terrain3d/TerrainMaterial.js';
export { TerrainCameraControls } from 'atak-terrain3d/TerrainCameraControls.js';
export { TerrainOverlayManager } from 'atak-terrain3d/TerrainOverlayManager.js';

/* Exposition globale pour intégration legacy (pages non-module). */
if (typeof window !== 'undefined') {
  window.initTerrain3D = initTerrain3D;
  window.Terrain3DRenderer = Terrain3DRenderer;
}
