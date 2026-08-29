/**
 * MapRenderer — encapsule Leaflet, tuiles, HUD, événements carte.
 */
export class MapRenderer {
  /**
   * @param {HTMLElement} container
   * @param {object} config — config carte (Arma3Map.Maps.altis)
   * @param {object} [opts]
   */
  constructor(container, config, opts) {
    opts = opts || {};
    this.container = container;
    this.config = config;
    this.map = null;
    this.baseTileLayer = null;
    this.markerManager = null;
    this.trackRenderer = null;
    this._opts = opts;
  }

  init() {
    const L = window.L;
    if (!L) throw new Error('Leaflet requis');
    const config = this.config;
    if (!config) throw new Error('Config carte manquante');

    this.map = L.map(this.container, {
      minZoom: config.minZoom,
      maxZoom: config.maxZoom,
      crs: config.CRS,
      zoomControl: false,
      attributionControl: false,
    });

    this.baseTileLayer = null;
    if (config.tilePattern) {
      this.baseTileLayer = L.tileLayer(config.tilePattern, {
        attribution: config.attribution,
        tileSize: config.tileSize,
      }).addTo(this.map);
    }

    this.map.setView(config.center, config.defaultZoom);

    if (window.ATAKMarkerSizes && window.ATAKMarkerSizes.bindZoom) {
      window.ATAKMarkerSizes.bindZoom(this.map);
    }

    this.container.classList.add('tac-map-root');

    try {
      window.dispatchEvent(new CustomEvent('atak:mapready', { detail: { map: this.map } }));
    } catch (e) { /* ignore */ }

    return this.map;
  }

  getMap() {
    return this.map;
  }

  getBaseTileLayer() {
    return this.baseTileLayer;
  }

  worldFromLatLng(latlng) {
    if (window.ATAKMap && window.ATAKMap.worldFromLatLng) {
      return window.ATAKMap.worldFromLatLng(latlng);
    }
    return { x: latlng.lng, y: latlng.lat };
  }

  latLngFromWorld(x, y) {
    if (window.ATAKMap && window.ATAKMap.latLngFromWorld) {
      return window.ATAKMap.latLngFromWorld(x, y);
    }
    return window.L.latLng(y, x);
  }

  setMarkerManager(manager) {
    this.markerManager = manager;
  }

  setTrackRenderer(renderer) {
    this.trackRenderer = renderer;
  }

  invalidateSize() {
    if (this.map) this.map.invalidateSize({ animate: false });
  }

  dispose() {
    if (this.markerManager) this.markerManager.dispose();
    if (this.trackRenderer) this.trackRenderer.dispose();
    if (this.map) {
      this.map.remove();
      this.map = null;
    }
  }
}

/* Exposition globale */
if (typeof window !== 'undefined') {
  window.MapRenderer = MapRenderer;
}
