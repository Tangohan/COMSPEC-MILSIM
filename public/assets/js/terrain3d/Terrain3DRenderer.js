/**
 * Terrain3DRenderer — orchestrateur principal.
 * Drape une texture cartographique sur un mesh altimétrique Three.js.
 */
import { HeightmapLoader } from './HeightmapLoader.js';
import { TerrainTextureLoader } from './TextureLoader.js';
import { TerrainGeometryBuilder } from './TerrainGeometryBuilder.js';
import { TerrainMaterialFactory } from './TerrainMaterial.js';
import { TerrainCameraControls } from './TerrainCameraControls.js';
import { TerrainOverlayManager } from './TerrainOverlayManager.js';
import { defaults, clamp, normalizeGrid } from './utils.js';

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
  backgroundColor: 0x070a0e,
};

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
    this._animationId = null;
    this._disposed = false;

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
    TerrainMaterialFactory.setupFog(this.scene, THREE, {
      enabled: this.options.fog !== false,
      density: this.options.fogDensity,
      color: this.options.backgroundColor,
    });
    this.lights = TerrainMaterialFactory.setupLighting(this.scene, THREE);
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

    const geometry = TerrainGeometryBuilder.build(THREE, {
      grid: this.grid,
      worldWidth: this.options.width,
      worldDepth: this.options.height,
      heightScale: this.mode === '2d' ? 0 : this.options.heightScale,
      minAltitude: this.options.minAltitude,
      maxAltitude: this.options.maxAltitude,
      segmentsX: this.options.segments,
      segmentsY: this.options.segments,
    });

    this.terrainMaterial = TerrainMaterialFactory.create(THREE, this.textureLoader.texture);
    this.terrainMesh = new THREE.Mesh(geometry, this.terrainMaterial);
    this.terrainMesh.name = 'atak-terrain-mesh';
    this.scene.add(this.terrainMesh);
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
    };
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
      const { loadMapImage } = await import('./TextureLoader.js');
      const img = await loadMapImage(url);
      const tex = this.textureLoader.fromSource(img, THREE);
      tex.colorSpace = THREE.SRGBColorSpace;
      TerrainMaterialFactory.setMap(this.terrainMaterial, tex);
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
    if (this.terrainMesh) {
      this.terrainMesh.geometry.dispose();
      this.terrainMesh.geometry = TerrainGeometryBuilder.build(this.THREE, this._geomParams());
    } else {
      this._buildTerrainMesh();
    }
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
    if (this.terrainMesh) {
      this.terrainMesh.geometry.dispose();
      this.terrainMesh.geometry = TerrainGeometryBuilder.build(this.THREE, this._geomParams());
    }
    this._syncOverlayContext();
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
      }
    } else {
      this.mode = '3d';
      this.options.heightScale = this._storedHeightScale;
      this.cameraControls.set3DView();
      if (this.terrainMesh && this.grid) {
        TerrainGeometryBuilder.updateHeights(this.terrainMesh.geometry, this.grid, this._geomParams());
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

    if (this.terrainMesh) {
      this.scene.remove(this.terrainMesh);
      this.terrainMesh.geometry.dispose();
      this.terrainMaterial.dispose();
    }

    this.renderer.dispose();
    if (this.webglCanvas.parentNode) this.webglCanvas.parentNode.removeChild(this.webglCanvas);
    if (this.labelRenderer.domElement.parentNode) {
      this.labelRenderer.domElement.parentNode.removeChild(this.labelRenderer.domElement);
    }
  }
}
