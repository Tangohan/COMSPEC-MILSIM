/**
 * MarkerManager — symboles tactiques 2D (Leaflet) avec LOD, statut, clustering.
 */
import { renderMarkerHtml, renderClusterHtml } from './TacticalSymbol.js';
import { computeLOD, clampSymbolSize } from './MarkerLOD.js';
import { MarkerClusterManager } from './MarkerClusterManager.js';

export class MarkerManager {
  /**
   * @param {object} opts
   * @param {L.Map} opts.map — instance Leaflet
   * @param {L.LayerGroup} [opts.layerGroup]
   */
  constructor(opts) {
    opts = opts || {};
    this.map = opts.map;
    this.L = opts.L || window.L;
    this.layerGroup = opts.layerGroup || this.L.layerGroup().addTo(this.map);
    this.markers = new Map();
    this.entities = [];
    this.clusterManager = new MarkerClusterManager({ cellSize: 72, minCluster: 4 });
    this.clusteringEnabled = opts.clustering !== false;
    this._lastZoom = this.map ? this.map.getZoom() : 3;
    this._bindZoom();
  }

  _bindZoom() {
    const self = this;
    if (!this.map || this.map._tacMarkerZoomBound) return;
    this.map._tacMarkerZoomBound = true;
    this.map.on('zoomend moveend', function () {
      self._lastZoom = self.map.getZoom();
      self.render(self.entities);
    });
  }

  /**
   * Met à jour les entités affichées.
   * @param {Array<object>} entities
   */
  setEntities(entities) {
    this.entities = Array.isArray(entities) ? entities.slice() : [];
    this.render(this.entities);
  }

  /** Alias compatibilité ATAK. */
  updateMarkers(entities) {
    this.setEntities(entities);
  }

  render(entities) {
    if (!this.map || !this.L) return;
    const self = this;
    const zoom = this._lastZoom;
    const lod = computeLOD(zoom);
    const withScreen = [];

    (entities || []).forEach(function (e) {
      if (e.isCluster) return;
      const latlng = self._entityLatLng(e);
      if (!latlng) return;
      const pt = self.map.latLngToContainerPoint(latlng);
      withScreen.push(Object.assign({}, e, { screenX: pt.x, screenY: pt.y, _latlng: latlng }));
    });

    let toDraw = withScreen;
    let clusters = [];

    if (this.clusteringEnabled) {
      const result = this.clusterManager.cluster(withScreen, zoom);
      toDraw = result.singles;
      clusters = result.clusters;
    }

    const seen = new Set();

    toDraw.forEach(function (e) {
      seen.add(String(e.id));
      self._upsertMarker(e, lod, e._latlng);
    });

    clusters.forEach(function (c) {
      seen.add(String(c.id));
      self._upsertCluster(c);
    });

    self.markers.forEach(function (marker, id) {
      if (!seen.has(id)) {
        self.layerGroup.removeLayer(marker);
        self.markers.delete(id);
      }
    });
  }

  _entityLatLng(e) {
    if (e.lat != null && e.lng != null) return this.L.latLng(e.lat, e.lng);
    if (e.latlng) return e.latlng;
    if (e.y != null && e.x != null) {
      if (window.ATAKMap && window.ATAKMap.latLngFromWorld) {
        return window.ATAKMap.latLngFromWorld(e.x, e.y);
      }
      return this.L.latLng(e.y, e.x);
    }
    if (e.pos && e.pos.length >= 2) {
      return this.L.latLng(e.pos[1], e.pos[0]);
    }
    return null;
  }

  _upsertMarker(entity, lod, latlng) {
    const id = String(entity.id);
    const size = clampSymbolSize(lod.size);
    const html = renderMarkerHtml(entity, Object.assign({}, lod, { size: size }));
    let marker = this.markers.get(id);

    const icon = this.L.divIcon({
      className: 'tac-leaflet-marker',
      html: html,
      iconSize: [size, size + (lod.showCallsign ? 28 : 8)],
      iconAnchor: [size / 2, size / 2],
    });

    if (marker) {
      marker.setLatLng(latlng);
      marker.setIcon(icon);
    } else {
      marker = this.L.marker(latlng, { icon: icon, interactive: true });
      marker.on('click', () => {
        this._emitSelect(entity);
      });
      marker.addTo(this.layerGroup);
      this.markers.set(id, marker);
    }
    marker._tacEntity = entity;
  }

  _upsertCluster(cluster) {
    const id = String(cluster.id);
    const latlng = this.map.containerPointToLatLng([cluster.screenX, cluster.screenY]);
    const html = cluster.html || renderClusterHtml(cluster.count, cluster.breakdown);
    const icon = this.L.divIcon({
      className: 'tac-leaflet-cluster',
      html: html,
      iconSize: [40, 40],
      iconAnchor: [20, 20],
    });
    let marker = this.markers.get(id);
    if (marker) {
      marker.setLatLng(latlng);
      marker.setIcon(icon);
    } else {
      marker = this.L.marker(latlng, { icon: icon, interactive: true });
      marker.on('click', () => {
        if (this.map) this.map.setZoom(Math.min(this.map.getMaxZoom(), this.map.getZoom() + 1));
      });
      marker.addTo(this.layerGroup);
      this.markers.set(id, marker);
    }
  }

  _emitSelect(entity) {
    try {
      window.dispatchEvent(new CustomEvent('atak:entity-selected', { detail: entity }));
    } catch (e) { /* ignore */ }
  }

  getSelected() {
    return this._selected || null;
  }

  dispose() {
    const self = this;
    this.markers.forEach(function (m) {
      self.layerGroup.removeLayer(m);
    });
    this.markers.clear();
  }
}
