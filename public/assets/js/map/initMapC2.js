/**
 * initMapC2 — point d'entrée : assemble MapRenderer, MarkerManager, UI, 2D/3D.
 */
import { MapRenderer } from './MapRenderer.js';
import { MarkerManager } from './MarkerManager.js';
import { MarkerManager3D } from './MarkerManager3D.js';
import { TrackRenderer } from './TrackRenderer.js';
import { MapControls } from './MapControls.js';
import { MapUI } from './MapUI.js';
import { SelectedEntityPanel } from './SelectedEntityPanel.js';
import { MapLayerManager } from './MapLayerManager.js';
import { initTerrain3D } from '../terrain3d/initTerrain3D.js';
import { heightAtWorld } from '../terrain3d/utils.js';

/**
 * @param {object} options
 * @returns {Promise<object>} api
 */
export async function initMapC2(options) {
  options = options || {};
  const map2dEl = options.map2dEl || document.getElementById('tac-map-2d');
  const map3dEl = options.map3dEl || document.getElementById('tac-map-3d');
  const uiRoot = options.uiRoot || document.querySelector('.tac-c2-shell');
  const config = options.config || (window.Arma3Map && window.Arma3Map.Maps && window.Arma3Map.Maps.altis);

  const layers = new MapLayerManager();
  const mapRenderer = new MapRenderer(map2dEl, config, options);
  const map = mapRenderer.init();

  const markerManager = new MarkerManager({ map: map, clustering: options.clustering !== false });
  mapRenderer.setMarkerManager(markerManager);

  const trackLayer = window.L.layerGroup().addTo(map);
  const trackRenderer = new TrackRenderer({ leafletMap: map });
  trackRenderer.setLeafletLayer(trackLayer);
  mapRenderer.setTrackRenderer(trackRenderer);

  let terrain3d = null;
  let marker3d = null;
  let viewMode = '2d';

  if (map3dEl && options.terrain) {
    terrain3d = await initTerrain3D(map3dEl, options.terrain);
    const ctx = terrain3d.getMarkerContext();
    marker3d = new MarkerManager3D(
      ctx.scene,
      ctx.CSS2DObject,
      buildTerrainCtx(terrain3d)
    );
    if (options.markers) marker3d.setEntities(normalizeEntities(options.markers));
  }

  const controls = new MapControls(
    options.controlsEl || document.querySelector('.tac-map-controls'),
    {
      onZoomIn: function () { map.zoomIn(); },
      onZoomOut: function () { map.zoomOut(); },
      onNorth: function () { /* rotation si plugin */ },
      onRecenter: function () {
        if (config && config.center) map.setView(config.center, config.defaultZoom);
      },
      onToggle23d: function (mode) {
        viewMode = mode;
        if (map2dEl) map2dEl.classList.toggle('tac-map-view--hidden', mode === '3d');
        if (map3dEl) map3dEl.classList.toggle('tac-map-view--hidden', mode === '2d');
        if (terrain3d) {
          if (mode === '3d') terrain3d.toggle2D3D('3d');
          else terrain3d.toggle2D3D('2d');
        }
        setTimeout(function () { mapRenderer.invalidateSize(); }, 180);
      },
      onFollow: function (on) {
        options.onFollow && options.onFollow(on);
      },
    }
  );

  const ui = uiRoot ? new MapUI(uiRoot) : null;
  const entityPanel = new SelectedEntityPanel(
    options.contextPanelEl || document.getElementById('tac-context-panel')
  );

  function setEntities(list) {
    const normalized = normalizeEntities(list);
    markerManager.setEntities(normalized);
    if (marker3d) marker3d.setEntities(normalized);
  }

  function setTracks(tracks) {
    if (layers.isVisible('tracks')) trackRenderer.updateTracks(tracks);
  }

  window.addEventListener('atak:entity-focus', function (ev) {
    const e = ev.detail;
    if (!e) return;
    const latlng = markerManager._entityLatLng(e);
    if (latlng) map.setView(latlng, map.getZoom());
    if (terrain3d && e.x != null) terrain3d.focusOnGrid(e.x, e.y);
  });

  if (options.markers) setEntities(options.markers);
  if (options.tracks) setTracks(options.tracks);

  return {
    map: map,
    mapRenderer: mapRenderer,
    markerManager: markerManager,
    marker3d: marker3d,
    terrain3d: terrain3d,
    trackRenderer: trackRenderer,
    controls: controls,
    ui: ui,
    entityPanel: entityPanel,
    layers: layers,
    setEntities: setEntities,
    setTracks: setTracks,
    setViewMode: function (mode) {
      controls.setMode(mode);
      if (map2dEl) map2dEl.classList.toggle('tac-map-view--hidden', mode === '3d');
      if (map3dEl) map3dEl.classList.toggle('tac-map-view--hidden', mode === '2d');
    },
  };
}

function normalizeEntities(list) {
  return (list || []).map(function (u) {
    return {
      id: u.id,
      callsign: u.callsign || u.call_sign || u.label,
      role: u.role || u.team,
      affiliation: normalizeAff(u.affiliation || u.side),
      type: u.type || u.unitType || 'INFANTRY',
      status: u.status || u.linkStatus || 'ONLINE',
      heading: u.heading,
      speed: u.speed,
      altitude: u.altitude || u.asl_z,
      x: u.x != null ? u.x : (u.pos && u.pos[0]),
      y: u.y != null ? u.y : (u.pos && u.pos[1]),
      lat: u.lat,
      lng: u.lng,
      grid: u.grid,
    };
  });
}

function normalizeAff(side) {
  const s = String(side || 'FRIENDLY').toUpperCase();
  if (s === 'EAST' || s === 'HOSTILE' || s === 'ENEMY') return 'HOSTILE';
  if (s === 'GUER' || s === 'NEUTRAL' || s === 'CIV') return 'NEUTRAL';
  if (s === 'UNKNOWN') return 'UNKNOWN';
  if (s === 'WEST' || s === 'FRIENDLY' || s === 'FRIEND') return 'FRIENDLY';
  return s;
}

function buildTerrainCtx(terrain3d) {
  const o = terrain3d.options;
  return {
    grid: terrain3d.grid,
    worldWidth: o.width,
    worldDepth: o.height,
    heightScale: o.heightScale,
    minAltitude: o.minAltitude,
    maxAltitude: o.maxAltitude,
    heightAtWorld: function (wx, wz) {
      return heightAtWorld(
        terrain3d.grid, wx, wz, o.width, o.height,
        terrain3d.mode === '2d' ? 0 : o.heightScale,
        o.minAltitude, o.maxAltitude
      );
    },
  };
}

if (typeof window !== 'undefined') {
  window.initMapC2 = initMapC2;
}

