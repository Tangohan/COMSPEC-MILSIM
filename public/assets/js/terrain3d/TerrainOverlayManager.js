/**
 * Overlays tactiques HTML/CSS2D — marqueurs au-dessus du relief.
 */
import { heightAtWorld } from 'atak-terrain3d/utils.js';

export class TerrainOverlayManager {
  /**
   * @param {THREE.Scene} scene
   * @param {HTMLElement} container
   * @param {object} labelRenderer — instance CSS2DRenderer Three.js
   * @param {Function} CSS2DObject — classe CSS2DObject Three.js
   */
  constructor(scene, container, labelRenderer, CSS2DObject) {
    this.scene = scene;
    this.container = container;
    this.labelRenderer = labelRenderer;
    this.CSS2DObject = CSS2DObject;
    this.markers = [];
    this.markerObjects = [];
    this.grid = null;
    this.worldWidth = 1000;
    this.worldDepth = 1000;
    this.heightScale = 1;
    this.minAltitude = 0;
    this.maxAltitude = 400;
  }

  /**
   * @param {object} grid — grille d'altitudes
   * @param {object} world — { worldWidth, worldDepth, heightScale, minAltitude, maxAltitude }
   */
  setTerrainContext(grid, world) {
    this.grid = grid;
    this.worldWidth = world.worldWidth;
    this.worldDepth = world.worldDepth;
    this.heightScale = world.heightScale;
    this.minAltitude = world.minAltitude;
    this.maxAltitude = world.maxAltitude;
    this._repositionAll();
  }

  /**
   * Met à jour la liste de marqueurs.
   * Format : { id, x, y, label, type?, color?, heading? }
   * x/y = coordonnées grille (0..worldWidth/Depth).
   * @param {Array<object>} markers
   */
  updateMarkers(markers) {
    this.markers = Array.isArray(markers) ? markers.slice() : [];
    this._syncDomMarkers();
  }

  _syncDomMarkers() {
    this._clearMarkerObjects();

    const self = this;
    this.markers.forEach(function (m) {
      const el = document.createElement('div');
      el.className = 'terrain3d-marker terrain3d-marker--' + (m.type || 'unit');
      el.dataset.markerId = m.id != null ? String(m.id) : '';
      el.innerHTML =
        '<span class="terrain3d-marker__dot"></span>' +
        (m.label ? '<span class="terrain3d-marker__label">' + escapeHtml(m.label) + '</span>' : '');

      if (m.color) {
        el.style.setProperty('--marker-color', m.color);
      }

      const obj = new self.CSS2DObject(el);
      obj.userData.marker = m;
      self._setMarkerPosition(obj, m);
      self.scene.add(obj);
      self.markerObjects.push(obj);
    });
  }

  _setMarkerPosition(obj, marker) {
    const wx = Number(marker.x) - this.worldWidth / 2;
    const wz = Number(marker.y) - this.worldDepth / 2;
    const wy = heightAtWorld(
      this.grid,
      wx,
      wz,
      this.worldWidth,
      this.worldDepth,
      this.heightScale,
      this.minAltitude,
      this.maxAltitude
    ) + 2;
    obj.position.set(wx, wy, wz);
  }

  _repositionAll() {
    const self = this;
    this.markerObjects.forEach(function (obj) {
      if (obj.userData.marker) self._setMarkerPosition(obj, obj.userData.marker);
    });
  }

  _clearMarkerObjects() {
    const self = this;
    this.markerObjects.forEach(function (obj) {
      self.scene.remove(obj);
      if (obj.element && obj.element.parentNode) {
        obj.element.parentNode.removeChild(obj.element);
      }
    });
    this.markerObjects = [];
  }

  /** Rendu des labels (appelé après le rendu WebGL principal). */
  render(scene, camera) {
    this.labelRenderer.render(scene, camera);
  }

  dispose() {
    this._clearMarkerObjects();
  }
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
