/**
 * ATAK — vue topo premium (Three.js Terrain3DRenderer).
 * Remplace le maillage CSS-pitch de atak-terrain-3d.js lorsque
 * window.ATAK_TERRAIN3D_PREMIUM === true.
 */
import { initTerrain3D } from './terrain3d/initTerrain3D.js';

if (typeof window !== 'undefined' && window.ATAK_TERRAIN3D_PREMIUM) {
  const KEY = 'atak_terrain3d_premium';
  const state = { enabled: false, verticalExaggeration: 2.5, pitch: 48 };
  let stage = null;
  let host = null;
  let mapEl = null;
  let button = null;
  let nav = null;
  let settings = null;
  let modeSelect = null;
  let pitchInput = null;
  let exaggerationInput = null;
  let renderer = null;
  let initPromise = null;
  let heightsLoading = false;
  let heightsMapId = null;
  let meshOrigin = { x: 0, y: 0, width: 30720, depth: 30720 };
  let lastUnits = [];

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, Number(value) || min));
  }

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || window.ATAK_BASE_URL || '');
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId
      ? window.ATAKSocket.getMapId()
      : (window.ATAK_DEFAULT_MAP_ID || 1);
  }

  function worldSize() {
    const cfg = window.ATAK_MAP_CONFIG || {};
    const size = Number(cfg.worldSize);
    if (Number.isFinite(size) && size > 0) return size;
    try {
      const maps = window.Arma3Map && window.Arma3Map.Maps;
      if (maps) {
        const slug = cfg.slug || Object.keys(maps)[0];
        if (slug && maps[slug] && maps[slug].worldSize) return Number(maps[slug].worldSize) || 30720;
      }
    } catch (e) { /* ignore */ }
    return 30720;
  }

  function tilePattern() {
    const cfg = window.ATAK_MAP_CONFIG || {};
    if (cfg.tilePattern) return String(cfg.tilePattern);
    try {
      const maps = window.Arma3Map && window.Arma3Map.Maps;
      if (maps) {
        const slug = cfg.slug || Object.keys(maps)[0];
        if (slug && maps[slug] && maps[slug].tilePattern) return String(maps[slug].tilePattern);
      }
    } catch (e) { /* ignore */ }
    return '';
  }

  function overviewTextureUrl() {
    const pattern = tilePattern();
    if (pattern) return pattern.replace('{z}', '0').replace('{x}', '0').replace('{y}', '0');
    return apiBase() + '/api/atak/terrain/hillshade?mapId=' + encodeURIComponent(mapId());
  }

  function restore() {
    try {
      const saved = JSON.parse(localStorage.getItem(KEY) || '{}');
      state.enabled = !!saved.enabled;
      state.verticalExaggeration = clamp(saved.verticalExaggeration == null ? 2.5 : saved.verticalExaggeration, 1, 4);
      state.pitch = clamp(saved.pitch == null ? 48 : saved.pitch, 25, 65);
    } catch (e) { /* ignore */ }
  }

  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
  }

  function ensureHost() {
    stage = document.querySelector('.atak-map-stage');
    mapEl = document.getElementById('atak-map');
    if (!stage) return null;
    host = document.getElementById('terrain3d-container');
    if (!host) {
      host = document.createElement('div');
      host.id = 'terrain3d-container';
      host.className = 'terrain3d-host';
      host.setAttribute('aria-label', 'Carte terrain 3D premium');
      host.hidden = true;
      stage.insertBefore(host, stage.firstChild);
    }
    return host;
  }

  function decodeHeights(data) {
    if (!data || !data.heights || data.encoding !== 'int16le_b64') return null;
    const raw = atob(data.heights);
    const values = new Int16Array(Math.floor(raw.length / 2));
    for (let i = 0; i < values.length; i += 1) {
      const v = raw.charCodeAt(i * 2) | (raw.charCodeAt(i * 2 + 1) << 8);
      values[i] = v > 32767 ? v - 65536 : v;
    }
    let minZ = Number(data.min_z);
    let maxZ = Number(data.max_z);
    if (!Number.isFinite(minZ) || !Number.isFinite(maxZ) || maxZ <= minZ) {
      let mn = 32767;
      let mx = -32768;
      for (let h = 0; h < values.length; h += 1) {
        const hz = values[h];
        if (hz === -32768) continue;
        if (hz < mn) mn = hz;
        if (hz > mx) mx = hz;
      }
      if (mx > mn) {
        minZ = mn;
        maxZ = mx;
      } else {
        minZ = 0;
        maxZ = 1;
      }
    }
    const fill = minZ;
    const flat = new Float32Array(values.length);
    for (let j = 0; j < values.length; j += 1) {
      flat[j] = values[j] === -32768 ? fill : values[j];
    }
    return {
      cols: Number(data.cols) || 0,
      rows: Number(data.rows) || 0,
      origin_x: Number(data.origin_x) || 0,
      origin_y: Number(data.origin_y) || 0,
      cell_m: Number(data.cell_m) || 1,
      min_z: minZ,
      max_z: maxZ,
      data: flat,
    };
  }

  function applyHeights(decoded) {
    if (!renderer || !decoded || !decoded.cols || !decoded.rows) return;
    const w = Math.max(1, decoded.cols * decoded.cell_m);
    const d = Math.max(1, decoded.rows * decoded.cell_m);
    meshOrigin = {
      x: decoded.origin_x,
      y: decoded.origin_y,
      width: w,
      depth: d,
    };
    renderer.options.width = w;
    renderer.options.height = d;
    renderer.options.heightCols = decoded.cols;
    renderer.options.heightRows = decoded.rows;
    renderer.options.minAltitude = decoded.min_z;
    renderer.options.maxAltitude = decoded.max_z;
    if (renderer.cameraControls && renderer.cameraControls.bounds) {
      renderer.cameraControls.bounds.worldWidth = w;
      renderer.cameraControls.bounds.worldDepth = d;
    }
    renderer.setHeightData(decoded.data);
    syncMarkersFromCache();
  }

  function loadHeights() {
    if (!renderer || heightsLoading) return;
    const activeMapId = mapId();
    heightsLoading = true;
    heightsMapId = activeMapId;
    fetch(apiBase() + '/api/atak/terrain?mapId=' + encodeURIComponent(activeMapId) + '&include=heights', {
      credentials: 'same-origin',
    })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        heightsLoading = false;
        if (heightsMapId !== activeMapId) return;
        const decoded = decodeHeights(data);
        if (decoded) applyHeights(decoded);
      })
      .catch(function () { heightsLoading = false; });
  }

  function unitsToMarkers(units) {
    const list = Array.isArray(units) ? units : [];
    const out = [];
    for (let i = 0; i < list.length; i += 1) {
      const u = list[i];
      if (!u) continue;
      const x = u.pos_x != null ? Number(u.pos_x) : (u.x != null ? Number(u.x) : NaN);
      const y = u.pos_y != null ? Number(u.pos_y) : (u.y != null ? Number(u.y) : NaN);
      if (!Number.isFinite(x) || !Number.isFinite(y)) continue;
      const mx = x - meshOrigin.x;
      const my = y - meshOrigin.y;
      if (mx < -50 || my < -50 || mx > meshOrigin.width + 50 || my > meshOrigin.depth + 50) continue;
      const side = String(u.side || u.affiliation || '').toUpperCase();
      let type = 'unit';
      if (side === 'EAST' || side === 'HOSTILE' || side === 'OPFOR') type = 'hostile';
      else if (side === 'CIV' || side === 'CIVILIAN' || side === 'NEUTRAL') type = 'neutral';
      out.push({
        id: u.id != null ? String(u.id) : ('u_' + i),
        x: mx,
        y: my,
        label: u.call_sign || u.callsign || '',
        type: type,
      });
    }
    return out;
  }

  function syncMarkersFromCache() {
    if (!renderer || !state.enabled) return;
    renderer.updateMarkers(unitsToMarkers(lastUnits));
  }

  function ensureRenderer() {
    if (renderer) return Promise.resolve(renderer);
    if (initPromise) return initPromise;
    const container = ensureHost();
    if (!container) return Promise.reject(new Error('terrain3d host missing'));

    const size = worldSize();
    meshOrigin = { x: 0, y: 0, width: size, depth: size };

    initPromise = initTerrain3D(container, {
      textureUrl: overviewTextureUrl(),
      width: size,
      height: size,
      heightScale: state.verticalExaggeration,
      minAltitude: 0,
      maxAltitude: 900,
      segments: 160,
      fog: true,
      markers: [],
    }).then(function (terrain) {
      renderer = terrain;
      window.ATAKTerrainThree = terrain;
      loadHeights();
      syncMarkersFromCache();
      applyPitchToCamera();
      return terrain;
    }).catch(function (err) {
      initPromise = null;
      console.warn('[ATAK Terrain3D premium] init échoué', err);
      throw err;
    });
    return initPromise;
  }

  function applyPitchToCamera() {
    if (!renderer || !renderer.orbit) return;
    const polar = ((90 - state.pitch) * Math.PI) / 180;
    renderer.orbit.minPolarAngle = Math.max(0.2, polar - 0.15);
    renderer.orbit.maxPolarAngle = Math.min(1.45, polar + 0.15);
    if (renderer.perspectiveCamera && renderer.orbit.target) {
      const dist = renderer.perspectiveCamera.position.distanceTo(renderer.orbit.target) || 800;
      const t = renderer.orbit.target;
      renderer.perspectiveCamera.position.set(
        t.x,
        t.y + Math.cos(polar) * dist,
        t.z + Math.sin(polar) * dist
      );
      renderer.orbit.update();
    }
  }

  function setMapHidden(hidden) {
    if (!mapEl) mapEl = document.getElementById('atak-map');
    if (!stage) stage = document.querySelector('.atak-map-stage');
    if (!host) host = document.getElementById('terrain3d-container');

    if (hidden) {
      /* Afficher d’abord l’hôte 3D, puis masquer Leaflet — évite le flash gris vide. */
      if (host) {
        host.hidden = false;
        host.classList.add('is-active', 'is-booting');
      }
      if (stage) {
        stage.classList.add('atak-map-stage--premium-3d', 'atak-map-stage--3d');
      }
      window.requestAnimationFrame(function () {
        if (!state.enabled) return;
        if (mapEl) {
          mapEl.classList.add('atak-map-2d-fallback');
          mapEl.hidden = true;
          mapEl.setAttribute('aria-hidden', 'true');
        }
        if (host) host.classList.remove('is-booting');
      });
      return;
    }

    if (mapEl) {
      mapEl.classList.remove('atak-map-2d-fallback');
      mapEl.hidden = false;
      mapEl.setAttribute('aria-hidden', 'false');
    }
    if (host) {
      host.classList.remove('is-active', 'is-booting');
      host.hidden = true;
    }
    if (stage) {
      stage.classList.remove('atak-map-stage--premium-3d', 'atak-map-stage--3d');
    }
  }

  function invalidateLeafletSoon() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || !map.invalidateSize) return;
    try { map.invalidateSize(false); } catch (e) { /* ignore */ }
    window.setTimeout(function () {
      try {
        if (map && map.invalidateSize) map.invalidateSize(false);
      } catch (e2) { /* ignore */ }
    }, 220);
  }

  function renderChrome() {
    if (button) {
      button.classList.toggle('is-active', state.enabled);
      button.setAttribute('aria-pressed', state.enabled ? 'true' : 'false');
      button.textContent = state.enabled ? '3D actif' : '3D';
      button.title = state.enabled
        ? 'Revenir à la carte 2D'
        : 'Vue topo premium (relief Three.js)';
    }
    if (nav) nav.hidden = !state.enabled;
    if (settings) {
      settings.removeAttribute('hidden');
      settings.hidden = false;
      settings.classList.toggle('is-inclined', state.enabled);
      settings.classList.add('is-premium');
    }
    if (modeSelect) modeSelect.value = state.enabled ? 'inclined' : 'flat';
    if (pitchInput) pitchInput.value = String(state.pitch);
    if (exaggerationInput) exaggerationInput.value = String(state.verticalExaggeration);
    const pitchValue = document.getElementById('atak-terrain-pitch-val');
    if (pitchValue) pitchValue.textContent = state.pitch + '°';
    const exaggerationValue = document.getElementById('atak-terrain-exaggeration-val');
    if (exaggerationValue) exaggerationValue.textContent = state.verticalExaggeration.toFixed(1) + '×';
    const hint = document.querySelector('.atak-terrain-3d-hint');
    if (hint && !hint.dataset.premiumHint) {
      hint.dataset.premiumHint = '1';
      hint.textContent =
        'Vue topo premium : mesh Three.js drapé sur le relevé d’altitudes. Amplifiez le relief, ajustez l’inclinaison, orientez avec la souris. Le rendu CSS-pitch legacy est désactivé.';
    }
    window.dispatchEvent(new CustomEvent('atak:terrain3dchange', {
      detail: { enabled: state.enabled, premium: true },
    }));
  }

  var toggleLock = false;

  function setEnabled(enabled) {
    enabled = !!enabled;
    if (toggleLock && enabled === state.enabled) return;
    state.enabled = enabled;
    ensureHost();
    renderChrome();
    save();

    if (!state.enabled) {
      setMapHidden(false);
      if (renderer && renderer.getMode && renderer.getMode() === '3d') {
        try { renderer.toggle2D3D('2d'); } catch (e) { /* ignore */ }
      }
      invalidateLeafletSoon();
      return;
    }

    toggleLock = true;
    ensureRenderer()
      .then(function () {
        if (!state.enabled) return;
        setMapHidden(true);
        if (renderer.getMode() !== '3d') renderer.toggle2D3D('3d');
        renderer.setHeightScale(state.verticalExaggeration);
        applyPitchToCamera();
        loadHeights();
        syncMarkersFromCache();
        if (renderer && typeof renderer.resize === 'function') {
          try { renderer.resize(); } catch (e) { /* ignore */ }
        }
      })
      .catch(function () {
        state.enabled = false;
        setMapHidden(false);
        renderChrome();
        save();
        invalidateLeafletSoon();
      })
      .finally(function () {
        toggleLock = false;
      });
  }

  function onUnitsUpdated(ev) {
    const detail = (ev && ev.detail) || {};
    if (Array.isArray(detail.units)) lastUnits = detail.units;
    else if (window.ATAKMap && typeof window.ATAKMap.getLastUnitsList === 'function') {
      lastUnits = window.ATAKMap.getLastUnitsList() || [];
    }
    syncMarkersFromCache();
  }

  function bindUi() {
    button = document.getElementById('atak-view-3d');
    nav = document.getElementById('atak-map-3d-nav');
    settings = document.getElementById('atak-terrain-3d-settings');
    modeSelect = document.getElementById('atak-terrain-3d-mode');
    pitchInput = document.getElementById('atak-terrain-pitch');
    exaggerationInput = document.getElementById('atak-terrain-exaggeration');

    if (button) {
      button.addEventListener('click', function () { setEnabled(!state.enabled); });
    }
    if (modeSelect) {
      modeSelect.addEventListener('change', function () {
        setEnabled(modeSelect.value === 'inclined');
      });
    }
    const flat = document.getElementById('atak-map-3d-flat');
    if (flat) flat.addEventListener('click', function () { setEnabled(false); });

    if (pitchInput) {
      pitchInput.addEventListener('input', function () {
        state.pitch = clamp(pitchInput.value, 25, 65);
        applyPitchToCamera();
        renderChrome();
        save();
      });
    }
    if (exaggerationInput) {
      exaggerationInput.addEventListener('input', function () {
        state.verticalExaggeration = clamp(exaggerationInput.value, 1, 4);
        if (renderer) renderer.setHeightScale(state.verticalExaggeration);
        renderChrome();
        save();
      });
    }

    const compass = document.getElementById('atak-map-3d-compass');
    if (compass) {
      compass.addEventListener('click', function () {
        if (!renderer || !renderer.cameraControls) return;
        try {
          renderer.cameraControls.setDefault3DView();
          applyPitchToCamera();
        } catch (e) { /* ignore */ }
      });
    }

    window.addEventListener('atak:units-updated', onUnitsUpdated);
    window.addEventListener('atak:mapready', function () {
      if (state.enabled) setEnabled(true);
    });
    window.addEventListener('atak:terrain-ready', function () {
      if (state.enabled) loadHeights();
    });
  }

  function init() {
    restore();
    ensureHost();
    bindUi();
    renderChrome();
    if (state.enabled) setEnabled(true);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  window.ATAKTerrain3DPremium = {
    setEnabled: setEnabled,
    getState: function () { return Object.assign({}, state); },
    getRenderer: function () { return renderer; },
    reloadHeights: loadHeights,
  };
}
