/**
 * Traces de mouvement — lignes fines (1–2 px), fade temporel.
 */
export class TrackRenderer {
  /**
   * @param {object} opts
   * @param {L.Map} [opts.leafletMap] — carte Leaflet (mode 2D)
   * @param {object} [opts.threeScene] — scène Three.js (mode 3D)
   */
  constructor(opts) {
    opts = opts || {};
    this.leafletMap = opts.leafletMap || null;
    this.threeScene = opts.threeScene || null;
    this.tracks = new Map();
    this.layerGroup = null;
    this.maxPoints = opts.maxPoints || 48;
    this.lineWeight = opts.lineWeight || 1.5;
  }

  /** @param {L.LayerGroup} layerGroup */
  setLeafletLayer(layerGroup) {
    this.layerGroup = layerGroup;
  }

  /**
   * Met à jour les traces.
   * @param {Array<{ id: string, points: Array<{x:number,y:number,t:number,extrapolated?:boolean}>, color?: string }>} trackData
   */
  updateTracks(trackData) {
    if (!this.leafletMap || !window.L || !this.layerGroup) return;
    const L = window.L;
    const self = this;
    const seen = new Set();

    (trackData || []).forEach(function (track) {
      seen.add(track.id);
      if (!track.points || track.points.length < 2) return;

      let polyline = self.tracks.get(track.id);
      const latlngs = track.points.map(function (p) {
        return self._toLatLng(p);
      }).filter(Boolean);

      if (latlngs.length < 2) return;

      const color = track.color || '#3ecfb4';
      const segments = self._buildSegments(track.points, color);

      if (polyline) {
        self.layerGroup.removeLayer(polyline);
      }

      const group = L.layerGroup();
      segments.forEach(function (seg) {
        L.polyline(seg.latlngs, {
          color: seg.color,
          weight: self.lineWeight,
          opacity: seg.opacity,
          dashArray: seg.dashed ? '4 6' : null,
          interactive: false,
          className: 'tac-track',
        }).addTo(group);
      });
      group.addTo(self.layerGroup);
      self.tracks.set(track.id, group);
    });

    self.tracks.forEach(function (layer, id) {
      if (!seen.has(id)) {
        self.layerGroup.removeLayer(layer);
        self.tracks.delete(id);
      }
    });
  }

  _toLatLng(p) {
    if (this.leafletMap && window.ATAKMap && window.ATAKMap.latLngFromWorld) {
      return window.ATAKMap.latLngFromWorld(p.x, p.y);
    }
    /* Démo : coords directes */
    return window.L ? window.L.latLng(p.y, p.x) : null;
  }

  /**
   * Segments avec opacité décroissante et pointillés si extrapolé.
   */
  _buildSegments(points, baseColor) {
    const segs = [];
    const n = points.length;
    for (let i = 1; i < n; i += 1) {
      const age = (n - i) / n;
      const opacity = 0.15 + (1 - age) * 0.65;
      segs.push({
        latlngs: [this._toLatLng(points[i - 1]), this._toLatLng(points[i])],
        color: baseColor,
        opacity: opacity,
        dashed: !!(points[i].extrapolated || points[i - 1].extrapolated),
      });
    }
    return segs;
  }

  clear() {
    const self = this;
    this.tracks.forEach(function (layer) {
      if (self.layerGroup) self.layerGroup.removeLayer(layer);
    });
    this.tracks.clear();
  }

  dispose() {
    this.clear();
  }
}
