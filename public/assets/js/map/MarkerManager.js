/**
 * MarkerManager — symboles tactiques 2D (Leaflet) avec LOD, statut, clustering.
 */
import { renderMarkerHtml, renderClusterHtml, markerHoverLines } from './TacticalSymbol.js';
import { computeLOD, applyDisplaySize } from './MarkerLOD.js';
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
    this.clusterManager = new MarkerClusterManager({ cellSize: 96, minCluster: 6 });
    this.clusteringEnabled = opts.clustering === true;
    this._lastZoom = this.map ? this.map.getZoom() : 3;
    this._bindZoom();
  }

  _bindZoom() {
    const self = this;
    if (!this.map || this.map._tacMarkerZoomBound) return;
    this.map._tacMarkerZoomBound = true;
    /* Pan seul : pas de rebuild (évite le pop des symboles). Le zoom change le LOD. */
    this.map.on('zoomend', function () {
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
    const size = this.map.getSize ? this.map.getSize() : null;
    if (!size || size.x < 8 || size.y < 8) return;
    const self = this;
    const zoom = this._lastZoom;
    const lod = computeLOD(zoom);
    const prefs = this._readPrefs();
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
      self._upsertMarker(e, lod, e._latlng, prefs);
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

  _readPrefs() {
    try {
      if (window.ATAKMap && typeof window.ATAKMap.getDisplayPrefs === 'function') {
        return window.ATAKMap.getDisplayPrefs() || {};
      }
    } catch (e) { /* ignore */ }
    return {};
  }

  _preferAvatar(prefs) {
    if (!prefs || prefs.styleMode !== 'nato') return false;
    try {
      return !!(window.ATAKMap && window.ATAKMap.getUnitMarkerPriority
        && window.ATAKMap.getUnitMarkerPriority() === 'avatar');
    } catch (e) {
      return false;
    }
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

  _upsertMarker(entity, lod, latlng, prefs) {
    const id = String(entity.id);
    prefs = prefs || {};
    const size = applyDisplaySize(lod, prefs.iconSize);
    const labelPx = Math.max(9, Math.min(16, Math.round(Number(prefs.labelSize) || 11)));
    const styleMode = prefs.styleMode || 'nato';
    const showHeading = prefs.showMotionArrows !== false;
    const showFt = prefs.showFtFrame !== false;
    const preferAvatar = this._preferAvatar(prefs) && !!entity.avatarUrl;
    const headingRounded = showHeading && entity.heading != null && entity.heading !== ''
      ? Math.round(Number(entity.heading) / 15) * 15
      : '';

    const lodOpts = Object.assign({}, lod, {
      size: size,
      showCallsign: lod.showCallsign !== false,
      showRole: false,
      showStatus: false,
      styleMode: styleMode,
      showHeading: showHeading && headingRounded !== '',
      showFtFrame: showFt,
      preferAvatar: preferAvatar,
    });
    const entityForIcon = Object.assign({}, entity, {
      heading: headingRounded === '' ? null : headingRounded,
    });
    const html = '<div class="tac-marker-wrap" style="--atak-unit-label-size:' + labelPx + 'px">'
      + renderMarkerHtml(entityForIcon, lodOpts) + '</div>';
    const extraH = lodOpts.showCallsign ? Math.round(labelPx * 1.8 + 12) : 8;
    const labelW = lodOpts.showCallsign ? Math.max(size + 24, Math.round(labelPx * 8)) : size;
    const sig = html + '|' + size + '|' + extraH + '|' + labelW;
    const posSig = Math.round(latlng.lat * 10) / 10 + ',' + Math.round(latlng.lng * 10) / 10;

    let marker = this.markers.get(id);

    if (marker && marker._tacSig === sig) {
      if (marker._tacPosSig !== posSig) {
        marker.setLatLng(latlng);
        marker._tacPosSig = posSig;
      }
      marker._tacEntity = entity;
      this._bindHover(marker, entity);
      return;
    }

    const icon = this.L.divIcon({
      className: 'tac-leaflet-marker atak-compact-marker',
      html: html,
      iconSize: [labelW, size + extraH],
      iconAnchor: [labelW / 2, size / 2],
    });

    if (marker) {
      marker.setLatLng(latlng);
      marker.setIcon(icon);
    } else {
      marker = this.L.marker(latlng, { icon: icon, interactive: true, zIndexOffset: 850 });
      marker.on('click', () => {
        this._emitSelect(entity);
      });
      marker.addTo(this.layerGroup);
      this.markers.set(id, marker);
    }
    marker._tacSig = sig;
    marker._tacPosSig = posSig;
    marker._tacEntity = entity;
    this._bindHover(marker, entity);
    if (window.ATAKMarkerSizes && window.ATAKMarkerSizes.bindSelectVisual) {
      window.ATAKMarkerSizes.bindSelectVisual(marker);
    }
  }

  _bindHover(marker, entity) {
    if (!marker || !window.ATAKMarkerSizes || !window.ATAKMarkerSizes.bindHoverTip) return;
    const title = entity.callsign || entity.id || '';
    const html = window.ATAKMarkerSizes.hoverTipHtml(title, markerHoverLines(entity));
    window.ATAKMarkerSizes.bindHoverTip(marker, html);
  }

  _upsertCluster(cluster) {
    const id = String(cluster.id);
    const latlng = this.map.containerPointToLatLng([cluster.screenX, cluster.screenY]);
    const html = cluster.html || renderClusterHtml(cluster.count, cluster.breakdown);
    const sig = 'cl|' + html;
    const icon = this.L.divIcon({
      className: 'tac-leaflet-cluster',
      html: html,
      iconSize: [40, 40],
      iconAnchor: [20, 20],
    });
    let marker = this.markers.get(id);
    if (marker && marker._tacSig === sig) {
      marker.setLatLng(latlng);
      return;
    }
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
    marker._tacSig = sig;
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
