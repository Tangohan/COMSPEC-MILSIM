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
      color: mapTexture ? 0xffffff : 0x3d4f3a,
      roughness: 0.92,
      metalness: 0.02,
      flatShading: false,
      wireframe: !!opts.wireframe,
    });
  }

  /**
   * Configure l'éclairage directionnel + ambiant pour un relief discret mais lisible.
   * @param {THREE.Scene} scene
   * @param {THREE} THREE
   * @returns {{ ambient: THREE.AmbientLight, sun: THREE.DirectionalLight, fill: THREE.DirectionalLight }}
   */
  static setupLighting(scene, THREE) {
    const ambient = new THREE.AmbientLight(0x4a5568, 0.42);
    scene.add(ambient);

    /* Lumière rasante nord-ouest — accentue les pentes sans effet cartoon. */
    const sun = new THREE.DirectionalLight(0xdce6f0, 0.88);
    sun.position.set(-120, 180, -90);
    sun.castShadow = false;
    scene.add(sun);

    const fill = new THREE.DirectionalLight(0x6b8cae, 0.22);
    fill.position.set(80, 60, 120);
    scene.add(fill);

    return { ambient: ambient, sun: sun, fill: fill };
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
      opts.color != null ? opts.color : 0x070a0e,
      opts.density != null ? opts.density : 0.00045
    );
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
    material.color.setHex(texture ? 0xffffff : 0x3d4f3a);
    material.needsUpdate = true;
  }
}
