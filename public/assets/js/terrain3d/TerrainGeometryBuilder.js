/**
 * Génération du mesh terrain : PlaneGeometry subdivisé + déplacement vertical.
 * Option cropToLand : découpe la mer « hors carte » (slab océan trop large).
 */
import { sampleHeightGrid } from 'atak-terrain3d/utils.js';

export class TerrainGeometryBuilder {
  /**
   * Fenêtre UV (0..1) englobant la terre ferme + marge.
   * @param {{ cols: number, rows: number, data: Float32Array|number[], normalized?: boolean }} grid
   * @param {number} minAlt
   * @param {number} maxAlt
   * @param {{ seaSlack?: number, pad?: number, minLandRatio?: number }} [opts]
   * @returns {{ u0: number, u1: number, v0: number, v1: number, landRatio: number }|null}
   */
  static computeLandUvWindow(grid, minAlt, maxAlt, opts) {
    opts = opts || {};
    if (!grid || !grid.data || !grid.cols || !grid.rows) return null;
    const cols = grid.cols | 0;
    const rows = grid.rows | 0;
    if (cols < 2 || rows < 2) return null;

    const span = (maxAlt - minAlt) || 1;
    const seaSlack = opts.seaSlack != null ? Number(opts.seaSlack) : Math.max(1.5, span * 0.02);
    const pad = opts.pad != null ? Number(opts.pad) : 0.06;
    const minLandRatio = opts.minLandRatio != null ? Number(opts.minLandRatio) : 0.12;
    const seaCeil = minAlt + seaSlack;

    let c0 = cols;
    let c1 = -1;
    let r0 = rows;
    let r1 = -1;
    let landCells = 0;
    const total = cols * rows;

    for (let r = 0; r < rows; r += 1) {
      for (let c = 0; c < cols; c += 1) {
        let h = grid.data[r * cols + c];
        if (grid.normalized) h = minAlt + h * span;
        if (!(h > seaCeil)) continue;
        landCells += 1;
        if (c < c0) c0 = c;
        if (c > c1) c1 = c;
        if (r < r0) r0 = r;
        if (r > r1) r1 = r;
      }
    }

    const landRatio = total > 0 ? landCells / total : 0;
    if (landCells < 8 || c1 < c0 || r1 < r0 || landRatio < minLandRatio) {
      return null;
    }

    let u0 = c0 / (cols - 1);
    let u1 = c1 / (cols - 1);
    let v0 = r0 / (rows - 1);
    let v1 = r1 / (rows - 1);
    const du = Math.max(0.02, u1 - u0);
    const dv = Math.max(0.02, v1 - v0);
    u0 = Math.max(0, u0 - du * pad);
    u1 = Math.min(1, u1 + du * pad);
    v0 = Math.max(0, v0 - dv * pad);
    v1 = Math.min(1, v1 + dv * pad);
    if (u1 - u0 < 0.08 || v1 - v0 < 0.08) return null;

    return { u0: u0, u1: u1, v0: v0, v1: v1, landRatio: landRatio };
  }

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
   *   segmentsY?: number,
   *   cropToLand?: boolean,
   *   landPad?: number,
   *   seaSlack?: number,
   *   flattenSea?: boolean
   * }} params
   * @returns {{ geometry: THREE.BufferGeometry, crop: { u0:number,u1:number,v0:number,v1:number, width:number, depth:number, offsetX:number, offsetZ:number, seaLevelY:number }|null }}
   */
  static build(THREE, params) {
    const grid = params.grid;
    const worldWidth = params.worldWidth;
    const worldDepth = params.worldDepth;
    const heightScale = params.heightScale != null ? params.heightScale : 1;
    const minAlt = Number.isFinite(params.minAltitude) ? params.minAltitude : (grid.minAlt || 0);
    const maxAlt = Number.isFinite(params.maxAltitude) ? params.maxAltitude : (grid.maxAlt || 400);
    const span = maxAlt - minAlt || 1;
    const flattenSea = params.flattenSea !== false;
    const seaSlack = params.seaSlack != null ? Number(params.seaSlack) : Math.max(1.5, span * 0.02);
    const seaCeil = minAlt + seaSlack;
    const seaLevelY = minAlt * heightScale;

    let u0 = 0;
    let u1 = 1;
    let v0 = 0;
    let v1 = 1;
    let cropMeta = null;

    if (params.cropToLand !== false) {
      const win = this.computeLandUvWindow(grid, minAlt, maxAlt, {
        seaSlack: seaSlack,
        pad: params.landPad != null ? params.landPad : 0.07,
      });
      if (win) {
        u0 = win.u0;
        u1 = win.u1;
        v0 = win.v0;
        v1 = win.v1;
      }
    }

    const cropW = Math.max(64, (u1 - u0) * worldWidth);
    const cropD = Math.max(64, (v1 - v0) * worldDepth);
    const offsetX = ((u0 + u1) / 2 - 0.5) * worldWidth;
    const offsetZ = ((v0 + v1) / 2 - 0.5) * worldDepth;
    const cropped = u0 > 0.001 || u1 < 0.999 || v0 > 0.001 || v1 < 0.999;

    const segX = params.segmentsX != null ? params.segmentsX : Math.max(1, grid.cols - 1);
    const segY = params.segmentsY != null ? params.segmentsY : Math.max(1, grid.rows - 1);

    /* PlaneGeometry par défaut est dans le plan XY ; on le pose sur XZ (Y = altitude). */
    const geometry = new THREE.PlaneGeometry(cropW, cropD, segX, segY);
    geometry.rotateX(-Math.PI / 2);

    const positions = geometry.attributes.position;
    const uvs = geometry.attributes.uv;
    const vertexCount = positions.count;

    for (let i = 0; i < vertexCount; i += 1) {
      const x = positions.getX(i);
      const z = positions.getZ(i);
      const lu = x / cropW + 0.5;
      const lv = z / cropD + 0.5;
      const u = u0 + lu * (u1 - u0);
      const v = v0 + lv * (v1 - v0);
      let h = sampleHeightGrid(grid, u, v);
      if (grid.normalized) {
        h = minAlt + h * span;
      }
      if (flattenSea && h <= seaCeil) {
        h = minAlt;
      }
      positions.setY(i, h * heightScale);
      if (uvs) {
        uvs.setXY(i, u, v);
      }
    }

    positions.needsUpdate = true;
    if (uvs) uvs.needsUpdate = true;
    geometry.computeVertexNormals();
    geometry.computeBoundingBox();
    geometry.computeBoundingSphere();

    if (cropped) {
      cropMeta = {
        u0: u0,
        u1: u1,
        v0: v0,
        v1: v1,
        width: cropW,
        depth: cropD,
        offsetX: offsetX,
        offsetZ: offsetZ,
        seaLevelY: seaLevelY,
      };
    } else {
      cropMeta = {
        u0: 0,
        u1: 1,
        v0: 0,
        v1: 1,
        width: worldWidth,
        depth: worldDepth,
        offsetX: 0,
        offsetZ: 0,
        seaLevelY: seaLevelY,
      };
    }

    return { geometry: geometry, crop: cropMeta };
  }

  /**
   * Met à jour les sommets existants (changement d'exagération verticale).
   * Conserve la fenêtre UV de crop si fournie via params.crop.
   * @param {THREE.BufferGeometry} geometry
   * @param {object} grid
   * @param {object} params — mêmes clés que build() + crop optionnel
   */
  static updateHeights(geometry, grid, params) {
    const worldWidth = params.worldWidth;
    const worldDepth = params.worldDepth;
    const heightScale = params.heightScale != null ? params.heightScale : 1;
    const minAlt = Number.isFinite(params.minAltitude) ? params.minAltitude : (grid.minAlt || 0);
    const maxAlt = Number.isFinite(params.maxAltitude) ? params.maxAltitude : (grid.maxAlt || 400);
    const span = maxAlt - minAlt || 1;
    const flattenSea = params.flattenSea !== false;
    const seaSlack = params.seaSlack != null ? Number(params.seaSlack) : Math.max(1.5, span * 0.02);
    const seaCeil = minAlt + seaSlack;
    const crop = params.crop || null;
    const u0 = crop ? crop.u0 : 0;
    const u1 = crop ? crop.u1 : 1;
    const v0 = crop ? crop.v0 : 0;
    const v1 = crop ? crop.v1 : 1;
    const cropW = crop && crop.width ? crop.width : worldWidth;
    const cropD = crop && crop.depth ? crop.depth : worldDepth;
    const positions = geometry.attributes.position;

    for (let i = 0; i < positions.count; i += 1) {
      const x = positions.getX(i);
      const z = positions.getZ(i);
      const lu = x / cropW + 0.5;
      const lv = z / cropD + 0.5;
      const u = u0 + lu * (u1 - u0);
      const v = v0 + lv * (v1 - v0);
      let h = sampleHeightGrid(grid, u, v);
      if (grid.normalized) {
        h = minAlt + h * span;
      }
      if (flattenSea && h <= seaCeil) {
        h = minAlt;
      }
      positions.setY(i, h * heightScale);
    }

    positions.needsUpdate = true;
    geometry.computeVertexNormals();
    geometry.computeBoundingSphere();
  }
}
