/**
 * MarkerManager3D — marqueurs tactiques billboards avec ancrage terrain.
 * Extension du système 2D pour Terrain3DRenderer.
 */
import { renderMarker3DHtml } from './TacticalSymbol.js';
import { computeLODFromDistance, clampSymbolSize } from './MarkerLOD.js';

export class MarkerManager3D {
  /**
   * @param {THREE.Scene} scene
   * @param {Function} CSS2DObject
   * @param {object} terrainContext — { grid, worldWidth, worldDepth, heightScale, minAltitude, maxAltitude, heightAtWorld }
   */
  constructor(scene, CSS2DObject, terrainContext) {
    this.scene = scene;
    this.CSS2DObject = CSS2DObject;
    this.terrain = terrainContext || {};
    this.markers = new Map();
    this.entities = [];
    this.cameraDistance = 0.5;
  }

  setTerrainContext(ctx) {
    this.terrain = ctx || {};
    this._repositionAll();
  }

  setCameraDistance(normalized) {
    this.cameraDistance = Number(normalized) || 0.5;
  }

  setEntities(entities) {
    this.entities = Array.isArray(entities) ? entities.slice() : [];
    this._sync();
  }

  updateMarkers(entities) {
    this.setEntities(entities);
  }

  _sync() {
    const self = this;
    const lod = computeLODFromDistance(this.cameraDistance);
    const size = clampSymbolSize(lod.size);
    const seen = new Set();

    this.entities.forEach(function (e) {
      const id = String(e.id);
      seen.add(id);
      let obj = self.markers.get(id);
      const el = document.createElement('div');
      el.innerHTML = renderMarker3DHtml(e, Object.assign({}, lod, { size: size }));
      const root = el.firstElementChild;

      if (obj) {
        if (obj.element && obj.element.parentNode) {
          obj.element.parentNode.replaceChild(root, obj.element);
        }
        obj.element = root;
      } else {
        obj = new self.CSS2DObject(root);
        self.scene.add(obj);
        self.markers.set(id, obj);
      }
      obj.userData.entity = e;
      self._position(obj, e);
    });

    self.markers.forEach(function (obj, id) {
      if (!seen.has(id)) {
        self.scene.remove(obj);
        if (obj.element && obj.element.parentNode) obj.element.parentNode.removeChild(obj.element);
        self.markers.delete(id);
      }
    });
  }

  _position(obj, entity) {
    const t = this.terrain;
    const wx = Number(entity.x != null ? entity.x : entity.pos_x) - (t.worldWidth || 1024) / 2;
    const wz = Number(entity.y != null ? entity.y : entity.pos_y) - (t.worldDepth || 1024) / 2;
    let wy = 0;
    if (typeof t.heightAtWorld === 'function') {
      wy = t.heightAtWorld(wx, wz) + 1;
    }
    obj.position.set(wx, wy, wz);
  }

  _repositionAll() {
    const self = this;
    this.markers.forEach(function (obj) {
      if (obj.userData.entity) self._position(obj, obj.userData.entity);
    });
  }

  dispose() {
    const self = this;
    this.markers.forEach(function (obj) {
      self.scene.remove(obj);
    });
    this.markers.clear();
  }
}

export { MarkerManager3D as default };
