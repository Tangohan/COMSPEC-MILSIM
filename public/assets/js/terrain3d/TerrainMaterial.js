/**
 * Matériau et éclairage du terrain — relief lisible, sobre, tactique.
 */
export class TerrainMaterialFactory {
  /**
   * @param {THREE} THREE
   * @param {THREE.Texture|null} mapTexture
   * @param {{ wireframe?: boolean }} opts
   * @returns {THREE.MeshStandardMaterial}
   */
  static create(THREE, mapTexture, opts) {
    opts = opts || {};
    return new THREE.MeshStandardMaterial({
      map: mapTexture || null,
      color: mapTexture ? 0xffffff : 0x4a6b45,
      roughness: 0.88,
      metalness: 0.02,
      flatShading: false,
      wireframe: !!opts.wireframe,
      polygonOffset: true,
      polygonOffsetFactor: 1,
      polygonOffsetUnits: 1,
    });
  }

  /**
   * Configure l'éclairage directionnel + ambiant pour un relief discret mais lisible.
   * @param {THREE.Scene} scene
   * @param {THREE} THREE
   * @returns {{ ambient: THREE.AmbientLight, sun: THREE.DirectionalLight, fill: THREE.DirectionalLight }}
   */
  static setupLighting(scene, THREE) {
    /* Ambiance neutre-chaude : laisse parler les couleurs carte (pas de voile bleu-gris). */
    const ambient = new THREE.AmbientLight(0xc8d2c4, 0.32);
    scene.add(ambient);

    /* Soleil NW — relief lisible sans laver la diffuse. */
    const sun = new THREE.DirectionalLight(0xfff2d6, 0.95);
    sun.position.set(-120, 180, -90);
    sun.castShadow = false;
    scene.add(sun);

    const fill = new THREE.DirectionalLight(0xa8c4b0, 0.28);
    fill.position.set(80, 60, 120);
    scene.add(fill);

    return { ambient: ambient, sun: sun, fill: fill };
  }

  /**
   * Recale les lumières pour un théâtre large (sinon le soleil « local » disparaît au dézoom).
   * @param {{ ambient?: THREE.AmbientLight, sun?: THREE.DirectionalLight, fill?: THREE.DirectionalLight }} lights
   * @param {number} worldWidth
   * @param {number} worldDepth
   */
  static syncLightingToWorld(lights, worldWidth, worldDepth) {
    if (!lights) return;
    const w = Math.max(256, Number(worldWidth) || 1024);
    const d = Math.max(256, Number(worldDepth) || 1024);
    const span = Math.max(w, d);
    const elev = Math.max(180, span * 0.55);
    const arm = Math.max(120, span * 0.45);
    if (lights.sun) lights.sun.position.set(-arm, elev, -arm * 0.75);
    if (lights.fill) lights.fill.position.set(arm * 0.7, elev * 0.35, arm);
  }

  /**
   * Densité FogExp2 adaptée à la taille monde : au dézoom théâtre entier le mesh reste visible.
   * @param {number} worldWidth
   * @param {number} worldDepth
   * @param {number} [baseDensity]
   * @returns {number}
   */
  static fogDensityForWorld(worldWidth, worldDepth, baseDensity) {
    const w = Math.max(256, Number(worldWidth) || 1024);
    const d = Math.max(256, Number(worldDepth) || 1024);
    const diag = Math.sqrt(w * w + d * d);
    const maxDist = Math.max(2800, diag * 1.35);
    /* exp(-density * dist) ≈ 0.45 à ~70 % du dézoom max → pas de noir total. */
    const targetDist = Math.max(1200, maxDist * 0.7);
    const density = 0.8 / targetDist;
    const base = baseDensity != null && Number.isFinite(baseDensity) ? Number(baseDensity) : 0.00045;
    return Math.min(base, Math.max(0.000012, density));
  }

  /**
   * Brouillard léger pour la profondeur tactique.
   * @param {THREE.Scene} scene
   * @param {THREE} THREE
   * @param {{ color?: number, density?: number, enabled?: boolean }} opts
   */
  static setupFog(scene, THREE, opts) {
    opts = opts || {};
    if (opts.enabled === false) {
      scene.fog = null;
      return;
    }
    scene.fog = new THREE.FogExp2(
      opts.color != null ? opts.color : 0x0b1220,
      opts.density != null ? opts.density : 0.00045
    );
  }

  /**
   * Recale le brouillard après sync caméra / hauteur.
   * @param {THREE.Scene} scene
   * @param {THREE} THREE
   * @param {{ color?: number, density?: number, enabled?: boolean, worldWidth?: number, worldDepth?: number }} opts
   */
  static syncFogToWorld(scene, THREE, opts) {
    opts = opts || {};
    if (opts.enabled === false) {
      scene.fog = null;
      return;
    }
    const density = this.fogDensityForWorld(opts.worldWidth, opts.worldDepth, opts.density);
    const color = opts.color != null ? opts.color : 0x0b1220;
    if (scene.fog && scene.fog.isFogExp2) {
      scene.fog.density = density;
      if (scene.fog.color && typeof scene.fog.color.setHex === 'function') {
        scene.fog.color.setHex(color);
      }
      return;
    }
    this.setupFog(scene, THREE, { enabled: true, density: density, color: color });
  }

  /**
   * Applique une nouvelle texture diffuse au matériau existant.
   * @param {THREE.MeshStandardMaterial} material
   * @param {THREE.Texture} texture
   */
  static setMap(material, texture) {
    if (material.map && material.map !== texture) {
      material.map.dispose();
    }
    material.map = texture;
    material.color.setHex(texture ? 0xffffff : 0x4a6b45);
    material.needsUpdate = true;
  }
}
