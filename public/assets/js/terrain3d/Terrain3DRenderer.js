/**
 * Terrain3DRenderer — orchestrateur principal.
 * Drape une texture cartographique sur un mesh altimétrique Three.js.
 */
import { HeightmapLoader } from 'atak-terrain3d/HeightmapLoader.js';
import { TerrainTextureLoader } from 'atak-terrain3d/TextureLoader.js';
import { TerrainGeometryBuilder } from 'atak-terrain3d/TerrainGeometryBuilder.js';
import { TerrainMaterialFactory } from 'atak-terrain3d/TerrainMaterial.js';
import { TerrainCameraControls } from 'atak-terrain3d/TerrainCameraControls.js';
import { TerrainOverlayManager } from 'atak-terrain3d/TerrainOverlayManager.js';
import { defaults, clamp, normalizeGrid } from 'atak-terrain3d/utils.js';

const DEFAULT_OPTIONS = {
  textureUrl: null,
  heightmapUrl: null,
  heightData: null,
  width: 1024,
  height: 1024,
  heightScale: 1.8,
  minAltitude: 0,
  maxAltitude: 400,
  segments: 128,
  markers: [],
  fog: true,
  fogDensity: 0.00045,
  backgroundColor: 0x0b1220,
  cropToLand: true,
  flattenSea: true,
  tacticalGrid: true,
};

/**
 * Densité FogExp2 locale (indépendante du cache de TerrainMaterial.js).
 * Évite le crash si un vieux module Material est encore en cache navigateur.
 */
function fogDensityForWorld(worldWidth, worldDepth, baseDensity) {
  if (typeof TerrainMaterialFactory.fogDensityForWorld === 'function') {
    return TerrainMaterialFactory.fogDensityForWorld(worldWidth, worldDepth, baseDensity);
  }
  const w = Math.max(256, Number(worldWidth) || 1024);
  const d = Math.max(256, Number(worldDepth) || 1024);
  const diag = Math.sqrt(w * w + d * d);
  const maxDist = Math.max(2800, diag * 1.35);
  const targetDist = Math.max(1200, maxDist * 0.7);
  const density = 0.8 / targetDist;
  const base = baseDensity != null && Number.isFinite(baseDensity) ? Number(baseDensity) : 0.00045;
  return Math.min(base, Math.max(0.000012, density));
}

function syncLightingToWorld(lights, worldWidth, worldDepth) {
  if (typeof TerrainMaterialFactory.syncLightingToWorld === 'function') {
    TerrainMaterialFactory.syncLightingToWorld(lights, worldWidth, worldDepth);
    return;
  }
  if (!lights) return;
  const w = Math.max(256, Number(worldWidth) || 1024);
  const d = Math.max(256, Number(worldDepth) || 1024);
  const span = Math.max(w, d);
  const elev = Math.max(180, span * 0.55);
  const arm = Math.max(120, span * 0.45);
  if (lights.sun) lights.sun.position.set(-arm, elev, -arm * 0.75);
  if (lights.fill) lights.fill.position.set(arm * 0.7, elev * 0.35, arm);
}

function syncFogToWorld(scene, THREE, opts) {
  opts = opts || {};
  if (typeof TerrainMaterialFactory.syncFogToWorld === 'function') {
    TerrainMaterialFactory.syncFogToWorld(scene, THREE, opts);
    return;
  }
  if (opts.enabled === false) {
    scene.fog = null;
    return;
  }
  const density = fogDensityForWorld(opts.worldWidth, opts.worldDepth, opts.density);
  const color = opts.color != null ? opts.color : 0x0b1220;
  if (scene.fog && scene.fog.isFogExp2) {
    scene.fog.density = density;
    if (scene.fog.color && typeof scene.fog.color.setHex === 'function') {
      scene.fog.color.setHex(color);
    }
    return;
  }
  TerrainMaterialFactory.setupFog(scene, THREE, { enabled: true, density: density, color: color });
}

export class Terrain3DRenderer {
  /**
   * @param {HTMLElement} container
   * @param {object} options
   * @param {object} deps — { THREE, OrbitControls, CSS2DRenderer, CSS2DObject }
   */
  constructor(container, options, deps) {
    if (!container) throw new Error('Terrain3DRenderer : container requis');
    if (!deps || !deps.THREE) throw new Error('Terrain3DRenderer : THREE requis');

    this.container = container;
    this.THREE = deps.THREE;
    this.options = defaults(options, DEFAULT_OPTIONS);
    this._orbitControlsClass = deps.OrbitControls;
    this._CSS2DRenderer = deps.CSS2DRenderer;
    this._CSS2DObject = deps.CSS2DObject;

    this.mode = '3d';
    this._storedHeightScale = this.options.heightScale;
    this.grid = null;
    this.tacticalGrid = null;
    this._animationId = null;
    this._disposed = false;
    this._landCrop = null;
    this.seaMesh = null;

    this._buildScene();
    this._buildRenderer();
    this._buildCameras();
    this._buildControls();
    this._buildOverlayLayer();
    this._bindResize();

    /* Chargement asynchrone des assets. */
    this._ready = this._loadAssets();
  }

  _buildScene() {
    const THREE = this.THREE;
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(this.options.backgroundColor);
    const fogDensity = fogDensityForWorld(
      this.options.width,
      this.options.height,
      this.options.fogDensity
    );
    TerrainMaterialFactory.setupFog(this.scene, THREE, {
      enabled: this.options.fog !== false,
      density: fogDensity,
      color: this.options.backgroundColor,
    });
    this.lights = TerrainMaterialFactory.setupLighting(this.scene, THREE);
    syncLightingToWorld(this.lights, this.options.width, this.options.height);
  }

  _buildRenderer() {
    const THREE = this.THREE;
    this.webglCanvas = document.createElement('canvas');
    this.webglCanvas.className = 'terrain3d-canvas';
    this.container.appendChild(this.webglCanvas);

    this.renderer = new THREE.WebGLRenderer({
      canvas: this.webglCanvas,
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
    });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.05;

    this.textureLoader = new TerrainTextureLoader(new THREE.TextureLoader(), THREE);
    this.terrainMesh = null;
    this.terrainMaterial = null;
  }

  _buildCameras() {
    const THREE = this.THREE;
    const aspect = Math.max(1, this.container.clientWidth / Math.max(1, this.container.clientHeight));
    this.perspectiveCamera = new THREE.PerspectiveCamera(42, aspect, 1, 8000);
    this.orthoCamera = new THREE.OrthographicCamera(-1, 1, 1, -1, 1, 8000);
  }

  _buildControls() {
    const OrbitControls = this._orbitControlsClass;
    this.orbit = new OrbitControls(this.perspectiveCamera, this.webglCanvas);
    this.cameraControls = new TerrainCameraControls(
      this.perspectiveCamera,
      this.orthoCamera,
      this.container,
      this.orbit,
      { worldWidth: this.options.width, worldDepth: this.options.height }
    );
    this.cameraControls.setDefault3DView();
  }

  _buildOverlayLayer() {
    const CSS2DRenderer = this._CSS2DRenderer;
    this.labelRenderer = new CSS2DRenderer();
    this.labelRenderer.domElement.className = 'terrain3d-label-layer';
    this.container.appendChild(this.labelRenderer.domElement);

    this.overlays = new TerrainOverlayManager(
      this.scene,
      this.container,
      this.labelRenderer,
      this._CSS2DObject
    );
  }

  async _loadAssets() {
    const opts = this.options;
    let grid = null;

    try {
      if (opts.heightData) {
        grid = HeightmapLoader.fromArray(opts.heightData, {
          cols: opts.heightCols,
          rows: opts.heightRows,
          minAltitude: opts.minAltitude,
          maxAltitude: opts.maxAltitude,
        });
      } else if (opts.heightmapUrl) {
        grid = await HeightmapLoader.fromUrl(opts.heightmapUrl, {
          minAltitude: opts.minAltitude,
          maxAltitude: opts.maxAltitude,
          segments: opts.segments,
        });
      }
    } catch (err) {
      console.warn('[Terrain3D] Heightmap indisponible, repli plat.', err);
    }

    if (!grid) {
      grid = HeightmapLoader.fallback(opts.segments);
      grid = normalizeGrid(grid, opts.minAltitude, opts.maxAltitude);
    }

    this.grid = grid;
    this._buildTerrainMesh();

    if (opts.textureUrl) {
      try {
        await this.setTexture(opts.textureUrl);
      } catch (err) {
        console.warn('[Terrain3D] Texture indisponible.', err);
      }
    }

    if (opts.markers && opts.markers.length) {
      this.updateMarkers(opts.markers);
    }

    this._syncOverlayContext();
    this._resize();
    this._startLoop();
    return this;
  }

  _buildTerrainMesh() {
    const THREE = this.THREE;
    if (this.terrainMesh) {
      this.scene.remove(this.terrainMesh);
      this.terrainMesh.geometry.dispose();
      if (this.terrainMaterial) this.terrainMaterial.dispose();
    }

    const built = TerrainGeometryBuilder.build(THREE, this._geomParams());
    const geometry = built.geometry || built;
    this._landCrop = built.crop || null;

    this.terrainMaterial = TerrainMaterialFactory.create(THREE, this.textureLoader.texture);
    this.terrainMesh = new THREE.Mesh(geometry, this.terrainMaterial);
    this.terrainMesh.name = 'atak-terrain-mesh';
    if (this._landCrop) {
      this.terrainMesh.position.set(this._landCrop.offsetX || 0, 0, this._landCrop.offsetZ || 0);
    }
    this.scene.add(this.terrainMesh);
    this._syncSeaPlane();
    this._syncTacticalGrid();
  }

  /** Grille kilomètre, collée au relief (pas de tuiles CSS en perspective). */
  _syncTacticalGrid() {
    const THREE = this.THREE;
    if (this.tacticalGrid) {
      if (this.tacticalGrid.parent) this.tacticalGrid.parent.remove(this.tacticalGrid);
      if (this.tacticalGrid.geometry) this.tacticalGrid.geometry.dispose();
      if (this.tacticalGrid.material) this.tacticalGrid.material.dispose();
      this.tacticalGrid = null;
    }
    if (!this.terrainMesh || !this.grid || this.options.tacticalGrid === false) return;
    const built = TerrainGeometryBuilder.buildTacticalGrid(THREE, this._geomParams());
    if (!built) return;
    this.tacticalGrid = new THREE.LineSegments(built.geometry, built.material);
    this.tacticalGrid.name = 'atak-terrain-grid';
    this.tacticalGrid.renderOrder = 2;
    this.terrainMesh.add(this.tacticalGrid);
  }

  /** Plan d’eau calé sur l’emprise cropée — ne dépasse plus le mesh terre. */
  _syncSeaPlane() {
    const THREE = this.THREE;
    if (!this.scene || !THREE) return;
    if (this.seaMesh) {
      this.scene.remove(this.seaMesh);
      if (this.seaMesh.geometry) this.seaMesh.geometry.dispose();
      if (this.seaMesh.material) this.seaMesh.material.dispose();
      this.seaMesh = null;
    }
    if (this.options.flattenSea === false) return;
    const crop = this._landCrop;
    const w = crop && crop.width ? crop.width : this.options.width;
    const d = crop && crop.depth ? crop.depth : this.options.height;
    const y = crop && Number.isFinite(crop.seaLevelY)
      ? crop.seaLevelY
      : (Number(this.options.minAltitude) || 0) * (this.mode === '2d' ? 0 : this.options.heightScale);
    const geo = new THREE.PlaneGeometry(w, d, 1, 1);
    geo.rotateX(-Math.PI / 2);
    const mat = new THREE.MeshStandardMaterial({
      color: 0x14384a,
      roughness: 0.92,
      metalness: 0.05,
      transparent: true,
      opacity: 0.72,
      depthWrite: false,
    });
    this.seaMesh = new THREE.Mesh(geo, mat);
    this.seaMesh.name = 'atak-terrain-sea';
    this.seaMesh.position.set(
      crop && crop.offsetX != null ? crop.offsetX : 0,
      y - 0.35,
      crop && crop.offsetZ != null ? crop.offsetZ : 0
    );
    this.seaMesh.renderOrder = -1;
    this.scene.add(this.seaMesh);
  }

  _geomParams() {
    return {
      grid: this.grid,
      worldWidth: this.options.width,
      worldDepth: this.options.height,
      heightScale: this.mode === '2d' ? 0 : this.options.heightScale,
      minAltitude: this.options.minAltitude,
      maxAltitude: this.options.maxAltitude,
      segmentsX: this.options.segments,
      segmentsY: this.options.segments,
      cropToLand: this.options.cropToLand !== false,
      flattenSea: this.options.flattenSea !== false,
      landPad: this.options.landPad,
      seaSlack: this.options.seaSlack,
      crop: this._landCrop,
    };
  }

  _syncOverlayContext() {
    this.overlays.setTerrainContext(this.grid, {
      worldWidth: this.options.width,
      worldDepth: this.options.height,
      heightScale: this.mode === '2d' ? 0 : this.options.heightScale,
      minAltitude: this.options.minAltitude,
      maxAltitude: this.options.maxAltitude,
    });
  }

  /** @returns {Promise<Terrain3DRenderer>} */
  ready() {
    return this._ready;
  }

  /** Charge ou remplace la texture diffuse. */
  async setTexture(url) {
    const THREE = this.THREE;
    this.options.textureUrl = url;
    try {
      const tex = await this.textureLoader.load(url);
      tex.colorSpace = THREE.SRGBColorSpace;
      tex.wrapS = tex.wrapT = THREE.ClampToEdgeWrapping;
      TerrainMaterialFactory.setMap(this.terrainMaterial, tex);
    } catch (err) {
      /* Tentative via canvas/image pour data URLs locales. */
      const { loadMapImage } = await import('atak-terrain3d/TextureLoader.js');
      const img = await loadMapImage(url);
      const tex = this.textureLoader.fromSource(img, THREE);
      tex.colorSpace = THREE.SRGBColorSpace;
      TerrainMaterialFactory.setMap(this.terrainMaterial, tex);
    }
  }

  /** Applique une texture depuis un canvas (overview stitchée). */
  setTextureFromCanvas(canvas) {
    if (!canvas) return;
    const THREE = this.THREE;
    if (!this.terrainMaterial) {
      /* Mesh pas encore prêt : stocke pour le prochain _buildTerrainMesh. */
      const tex = this.textureLoader.fromSource(canvas, THREE);
      tex.colorSpace = THREE.SRGBColorSpace;
      tex.needsUpdate = true;
      return;
    }
    const tex = this.textureLoader.fromSource(canvas, THREE);
    tex.colorSpace = THREE.SRGBColorSpace;
    tex.needsUpdate = true;
    TerrainMaterialFactory.setMap(this.terrainMaterial, tex);
  }

  /**
   * Recale orbit / far plane / fog / lumières sur la taille réelle du théâtre.
   * @param {number} [worldWidth]
   * @param {number} [worldDepth]
   * @param {{ resetView?: boolean }} [opts] — resetView:false conserve le zoom/cadrage (défaut).
   */
  syncCameraToWorld(worldWidth, worldDepth, opts) {
    const w = worldWidth != null ? worldWidth : this.options.width;
    const d = worldDepth != null ? worldDepth : this.options.height;
    if (this.cameraControls && typeof this.cameraControls.syncToWorld === 'function') {
      this.cameraControls.syncToWorld(w, d, opts || {});
    }
    syncFogToWorld(this.scene, this.THREE, {
      enabled: this.options.fog !== false,
      density: this.options.fogDensity,
      color: this.options.backgroundColor,
      worldWidth: w,
      worldDepth: d,
    });
    syncLightingToWorld(this.lights, w, d);
    if (this.scene && this.scene.background && typeof this.scene.background.setHex === 'function') {
      this.scene.background.setHex(this.options.backgroundColor != null ? this.options.backgroundColor : 0x0b1220);
    }
  }

  /** Zoom UI (±) — facteur > 1 = dézoom. */
  dolly(factor) {
    if (this.cameraControls && typeof this.cameraControls.dolly === 'function') {
      this.cameraControls.dolly(factor);
    }
  }

  /** Charge une heightmap image et reconstruit la géométrie. */
  async setHeightmap(url) {
    this.options.heightmapUrl = url;
    try {
      this.grid = await HeightmapLoader.fromUrl(url, {
        minAltitude: this.options.minAltitude,
        maxAltitude: this.options.maxAltitude,
        segments: this.options.segments,
      });
    } catch (err) {
      console.warn('[Terrain3D] setHeightmap échoué, conservation du relief actuel.', err);
      return;
    }
    this._buildTerrainMesh();
    this._syncOverlayContext();
  }

  /**
   * Injecte directement un tableau 2D d'altitudes.
   * @param {number[][]|number[]} data
   */
  setHeightData(data) {
    this.grid = HeightmapLoader.fromArray(data, {
      cols: this.options.heightCols,
      rows: this.options.heightRows,
      minAltitude: this.options.minAltitude,
      maxAltitude: this.options.maxAltitude,
    });
    this.options.heightData = data;
    this._buildTerrainMesh();
    /* Ré-applique la diffuse si elle a été perdue au rebuild (évite mesh nu + normals « static »). */
    if (this.textureLoader && this.textureLoader.texture && this.terrainMaterial) {
      TerrainMaterialFactory.setMap(this.terrainMaterial, this.textureLoader.texture);
    }
    this._syncOverlayContext();
  }

  /** Alias public — appelé après affichage du host (évite TypeError resize). */
  resize() {
    this._resize();
  }

  /** Modifie l'exagération verticale sans recharger les assets. */
  setHeightScale(value) {
    this._storedHeightScale = clamp(value, 0, 12);
    if (this.mode === '2d') {
      this.options.heightScale = this._storedHeightScale;
      return;
    }
    this.options.heightScale = this._storedHeightScale;
    if (this.terrainMesh && this.grid) {
      TerrainGeometryBuilder.updateHeights(this.terrainMesh.geometry, this.grid, this._geomParams());
      if (this._landCrop) {
        this._landCrop.seaLevelY = (Number(this.options.minAltitude) || 0) * this.options.heightScale;
      }
      this._syncSeaPlane();
      this._syncTacticalGrid();
    }
    this._syncOverlayContext();
  }

  /** Bascule entre vue 3D inclinée et vue 2D orthographique. */
  toggle2D3D(forceMode) {
    const next = forceMode || (this.mode === '3d' ? '2d' : '3d');
    if (next === this.mode) return this.mode;

    if (next === '2d') {
      this.mode = '2d';
      this.cameraControls.set2DView();
      if (this.terrainMesh && this.grid) {
        TerrainGeometryBuilder.updateHeights(this.terrainMesh.geometry, this.grid, this._geomParams());
        this._syncSeaPlane();
        this._syncTacticalGrid();
      }
    } else {
      this.mode = '3d';
      this.options.heightScale = this._storedHeightScale;
      this.cameraControls.set3DView();
      if (this.terrainMesh && this.grid) {
        TerrainGeometryBuilder.updateHeights(this.terrainMesh.geometry, this.grid, this._geomParams());
        if (this._landCrop) {
          this._landCrop.seaLevelY = (Number(this.options.minAltitude) || 0) * this.options.heightScale;
        }
        this._syncSeaPlane();
        this._syncTacticalGrid();
      }
    }

    this._syncOverlayContext();
    this.container.classList.toggle('terrain3d--mode-2d', this.mode === '2d');
    this.container.classList.toggle('terrain3d--mode-3d', this.mode === '3d');
    return this.mode;
  }

  /** Centre la caméra sur une coordonnée grille. */
  focusOnGrid(x, y) {
    this.cameraControls.focusOnGrid(x, y, this.options.width, this.options.height);
  }

  /**
   * Cadrage partagé avec Leaflet (x/y mètres Arma dans l’emprise du mesh).
   * @param {{ x?: number, y?: number, zoom?: number, pitch?: number, bearing?: number }} view
   */
  setTacticalView(view) {
    if (this.cameraControls && typeof this.cameraControls.setTacticalView === 'function') {
      this.cameraControls.setTacticalView(view);
    }
  }

  /** @returns {{ x: number, y: number, zoom: number, pitch: number, bearing: number }|null} */
  getTacticalView() {
    if (this.cameraControls && typeof this.cameraControls.getTacticalView === 'function') {
      return this.cameraControls.getTacticalView();
    }
    return null;
  }

  /** Met à jour les marqueurs tactiques. */
  updateMarkers(markers) {
    this.options.markers = Array.isArray(markers) ? markers.slice() : [];
    this.overlays.updateMarkers(this.options.markers);
  }

  getMode() {
    return this.mode;
  }

  /** Contexte pour MarkerManager3D. */
  getMarkerContext() {
    return {
      scene: this.scene,
      CSS2DObject: this._CSS2DObject,
      grid: this.grid,
      worldWidth: this.options.width,
      worldDepth: this.options.height,
      heightScale: this.options.heightScale,
      minAltitude: this.options.minAltitude,
      maxAltitude: this.options.maxAltitude,
    };
  }

  _startLoop() {
    const self = this;
    function tick() {
      if (self._disposed) return;
      self._animationId = requestAnimationFrame(tick);
      self.cameraControls.update();
      const cam = self.cameraControls.getActiveCamera();
      self.renderer.render(self.scene, cam);
      self.overlays.render(self.scene, cam);
    }
    tick();
  }

  _bindResize() {
    const self = this;
    this._onResize = function () { self._resize(); };
    window.addEventListener('resize', this._onResize);
    if (typeof ResizeObserver !== 'undefined') {
      this._resizeObserver = new ResizeObserver(this._onResize);
      this._resizeObserver.observe(this.container);
    }
  }

  _resize() {
    const w = this.container.clientWidth;
    const h = this.container.clientHeight;
    if (w < 1 || h < 1) return;

    this.renderer.setSize(w, h, false);
    this.labelRenderer.setSize(w, h);

    const aspect = w / h;
    this.perspectiveCamera.aspect = aspect;
    this.perspectiveCamera.updateProjectionMatrix();
    this.cameraControls.onResize(w, h);
  }

  dispose() {
    this._disposed = true;
    if (this._animationId) cancelAnimationFrame(this._animationId);
    window.removeEventListener('resize', this._onResize);
    if (this._resizeObserver) this._resizeObserver.disconnect();

    this.overlays.dispose();
    this.cameraControls.dispose();
    this.textureLoader.dispose();

    if (this.tacticalGrid) {
      if (this.tacticalGrid.parent) this.tacticalGrid.parent.remove(this.tacticalGrid);
      if (this.tacticalGrid.geometry) this.tacticalGrid.geometry.dispose();
      if (this.tacticalGrid.material) this.tacticalGrid.material.dispose();
      this.tacticalGrid = null;
    }
    if (this.terrainMesh) {
      this.scene.remove(this.terrainMesh);
      this.terrainMesh.geometry.dispose();
      this.terrainMaterial.dispose();
    }
    if (this.seaMesh) {
      this.scene.remove(this.seaMesh);
      if (this.seaMesh.geometry) this.seaMesh.geometry.dispose();
      if (this.seaMesh.material) this.seaMesh.material.dispose();
      this.seaMesh = null;
    }

    this.renderer.dispose();
    if (this.webglCanvas.parentNode) this.webglCanvas.parentNode.removeChild(this.webglCanvas);
    if (this.labelRenderer.domElement.parentNode) {
      this.labelRenderer.domElement.parentNode.removeChild(this.labelRenderer.domElement);
    }
  }
}
