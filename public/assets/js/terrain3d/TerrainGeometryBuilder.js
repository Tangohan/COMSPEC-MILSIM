/**
 * Génération du mesh terrain : PlaneGeometry subdivisé + déplacement vertical.
 */
import { sampleHeightGrid } from './utils.js';

export class TerrainGeometryBuilder {
  /**
   * Construit une BufferGeometry Three.js drapée sur une grille d'altitudes.
   *
   * @param {THREE} THREE
   * @param {{
   *   grid: { cols: number, rows: number, data: Float32Array, normalized?: boolean, minAlt?: number, maxAlt?: number },
   *   worldWidth: number,
   *   worldDepth: number,
   *   heightScale: number,
   *   minAltitude: number,
   *   maxAltitude: number,
   *   segmentsX?: number,
   *   segmentsY?: number
   * }} params
   * @returns {THREE.BufferGeometry}
   */
  static build(THREE, params) {
    const grid = params.grid;
    const worldWidth = params.worldWidth;
    const worldDepth = params.worldDepth;
    const heightScale = params.heightScale != null ? params.heightScale : 1;
    const minAlt = Number.isFinite(params.minAltitude) ? params.minAltitude : (grid.minAlt || 0);
    const maxAlt = Number.isFinite(params.maxAltitude) ? params.maxAltitude : (grid.maxAlt || 400);
    const span = maxAlt - minAlt || 1;

    const segX = params.segmentsX != null ? params.segmentsX : Math.max(1, grid.cols - 1);
    const segY = params.segmentsY != null ? params.segmentsY : Math.max(1, grid.rows - 1);

    /* PlaneGeometry par défaut est dans le plan XY ; on le pose sur XZ (Y = altitude). */
    const geometry = new THREE.PlaneGeometry(worldWidth, worldDepth, segX, segY);
    geometry.rotateX(-Math.PI / 2);

    const positions = geometry.attributes.position;
    const vertexCount = positions.count;

    for (let i = 0; i < vertexCount; i += 1) {
      const x = positions.getX(i);
      const z = positions.getZ(i);
      const u = x / worldWidth + 0.5;
      const v = z / worldDepth + 0.5;
      let h = sampleHeightGrid(grid, u, v);
      if (grid.normalized) {
        h = minAlt + h * span;
      }
      positions.setY(i, h * heightScale);
    }

    positions.needsUpdate = true;
    geometry.computeVertexNormals();
    geometry.computeBoundingBox();
    geometry.computeBoundingSphere();

    return geometry;
  }

  /**
   * Met à jour les sommets existants (changement d'exagération verticale).
   * @param {THREE.BufferGeometry} geometry
   * @param {object} grid
   * @param {object} params — mêmes clés que build()
   */
  static updateHeights(geometry, grid, params) {
    const worldWidth = params.worldWidth;
    const worldDepth = params.worldDepth;
    const heightScale = params.heightScale != null ? params.heightScale : 1;
    const minAlt = Number.isFinite(params.minAltitude) ? params.minAltitude : (grid.minAlt || 0);
    const maxAlt = Number.isFinite(params.maxAltitude) ? params.maxAltitude : (grid.maxAlt || 400);
    const span = maxAlt - minAlt || 1;
    const positions = geometry.attributes.position;

    for (let i = 0; i < positions.count; i += 1) {
      const x = positions.getX(i);
      const z = positions.getZ(i);
      const u = x / worldWidth + 0.5;
      const v = z / worldDepth + 0.5;
      let h = sampleHeightGrid(grid, u, v);
      if (grid.normalized) {
        h = minAlt + h * span;
      }
      positions.setY(i, h * heightScale);
    }

    positions.needsUpdate = true;
    geometry.computeVertexNormals();
    geometry.computeBoundingSphere();
  }
}
