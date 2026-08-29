/**
 * MapLayerManager — visibilité des calques (unités, traces, zones, intel…).
 */
export class MapLayerManager {
  constructor() {
    this.layers = {
      units: { visible: true, label: 'Unités' },
      tracks: { visible: true, label: 'Traces' },
      markers: { visible: true, label: 'Marqueurs' },
      zones: { visible: true, label: 'Zones' },
      intel: { visible: true, label: 'Intel' },
      contours: { visible: false, label: 'Courbes' },
      hillshade: { visible: true, label: 'Relief' },
    };
    this._listeners = [];
  }

  isVisible(key) {
    return !!(this.layers[key] && this.layers[key].visible);
  }

  setVisible(key, visible) {
    if (!this.layers[key]) return;
    this.layers[key].visible = !!visible;
    this._emit();
  }

  toggle(key) {
    if (!this.layers[key]) return;
    this.layers[key].visible = !this.layers[key].visible;
    this._emit();
  }

  onChange(fn) {
    this._listeners.push(fn);
  }

  _emit() {
    const snapshot = this.getState();
    this._listeners.forEach(function (fn) { fn(snapshot); });
    try {
      window.dispatchEvent(new CustomEvent('atak:layers-changed', { detail: snapshot }));
    } catch (e) { /* ignore */ }
  }

  getState() {
    const out = {};
    Object.keys(this.layers).forEach(function (k) {
      out[k] = this.layers[k].visible;
    }, this);
    return out;
  }
}

if (typeof window !== 'undefined') {
  window.MapLayerManager = MapLayerManager;
}
