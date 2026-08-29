/**
 * Contrôles caméra tactiques — vue inclinée, zoom, rotation limitée, pan.
 */
import { clamp } from './utils.js';

export class TerrainCameraControls {
  /**
   * @param {THREE.PerspectiveCamera} perspectiveCamera
   * @param {THREE.OrthographicCamera} orthoCamera
   * @param {HTMLElement} domElement
   * @param {THREE.OrbitControls} orbitControls — importé depuis three/examples
   * @param {{ worldWidth: number, worldDepth: number }} bounds
   */
  constructor(perspectiveCamera, orthoCamera, domElement, orbitControls, bounds) {
    this.perspectiveCamera = perspectiveCamera;
    this.orthoCamera = orthoCamera;
    this.domElement = domElement;
    this.controls = orbitControls;
    this.bounds = bounds;
    this.mode = '3d';
    this._savedPerspective = null;

    this._configureOrbit();
  }

  _configureOrbit() {
    const c = this.controls;
    c.enableDamping = true;
    c.dampingFactor = 0.06;
    c.enablePan = true;
    c.screenSpacePanning = true;
    /* Distances recalées via syncToWorld() selon la taille du théâtre. */
    c.minDistance = 80;
    c.maxDistance = 2800;
    /* Vue tactique inclinée : empêcher le regard vertical pur en 3D. */
    c.minPolarAngle = THREE_ORBIT_DEG(28);
    c.maxPolarAngle = THREE_ORBIT_DEG(72);
    c.maxAzimuthAngle = THREE_ORBIT_DEG(45);
    c.minAzimuthAngle = THREE_ORBIT_DEG(-45);
    c.rotateSpeed = 0.45;
    c.zoomSpeed = 1.15;
    c.panSpeed = 0.85;
    c.target.set(0, 0, 0);
  }

  /**
   * Adapte near/far, distances orbit et cadrage au monde réel (ex. Altis ~30 km).
   * Permet un dézoom total pour voir tout le théâtre.
   */
  syncToWorld(worldWidth, worldDepth) {
    const w = Math.max(256, Number(worldWidth) || this.bounds.worldWidth || 1024);
    const d = Math.max(256, Number(worldDepth) || this.bounds.worldDepth || 1024);
    this.bounds.worldWidth = w;
    this.bounds.worldDepth = d;
    const diag = Math.sqrt(w * w + d * d);
    const far = Math.max(8000, diag * 2.4);
    const maxDist = Math.max(2800, diag * 1.35);
    const minDist = Math.max(40, Math.min(400, diag * 0.008));

    this.perspectiveCamera.near = Math.max(1, minDist * 0.05);
    this.perspectiveCamera.far = far;
    this.perspectiveCamera.updateProjectionMatrix();
    this.orthoCamera.near = 1;
    this.orthoCamera.far = far;
    this.orthoCamera.updateProjectionMatrix();

    const c = this.controls;
    c.minDistance = minDist;
    c.maxDistance = maxDist;
    if (this.mode === '3d') {
      this.setDefault3DView();
    } else {
      this.onResize(this.domElement.clientWidth, this.domElement.clientHeight);
    }
  }

  /** Position initiale type C2 incliné — cadrée sur la taille monde. */
  setDefault3DView() {
    const w = Math.max(256, this.bounds.worldWidth || 1024);
    const d = Math.max(256, this.bounds.worldDepth || 1024);
    const diag = Math.sqrt(w * w + d * d);
    const dist = Math.min(this.controls.maxDistance * 0.92, Math.max(this.controls.minDistance * 8, diag * 0.72));
    const elev = dist * 0.55;
    this.perspectiveCamera.position.set(0, elev, dist * 0.85);
    this.controls.object = this.perspectiveCamera;
    this.controls.minPolarAngle = THREE_ORBIT_DEG(28);
    this.controls.maxPolarAngle = THREE_ORBIT_DEG(72);
    this.controls.maxAzimuthAngle = THREE_ORBIT_DEG(45);
    this.controls.minAzimuthAngle = THREE_ORBIT_DEG(-45);
    this.controls.enableRotate = true;
    this.controls.target.set(0, 0, 0);
    this.controls.update();
  }

  /** Recul caméra (dézoom) — facteur > 1 = plus loin. */
  dolly(factor) {
    const f = Number(factor);
    if (!Number.isFinite(f) || f <= 0) return;
    const cam = this.getActiveCamera();
    if (this.mode === '2d' && this.orthoCamera) {
      this.orthoCamera.zoom = clamp(this.orthoCamera.zoom / f, 0.15, 12);
      this.orthoCamera.updateProjectionMatrix();
      this.controls.update();
      return;
    }
    const target = this.controls.target;
    const offset = cam.position.clone().sub(target);
    offset.multiplyScalar(f);
    const nextLen = offset.length();
    const minD = this.controls.minDistance || 80;
    const maxD = this.controls.maxDistance || 2800;
    if (nextLen < minD || nextLen > maxD) {
      offset.setLength(clamp(nextLen, minD, maxD));
    }
    cam.position.copy(target).add(offset);
    this.controls.update();
  }

  /** Vue 2D orthographique — regard vertical, rotation désactivée. */
  set2DView() {
    this._savedPerspective = {
      position: this.perspectiveCamera.position.clone(),
      target: this.controls.target.clone(),
    };
    const maxDim = Math.max(this.bounds.worldWidth, this.bounds.worldDepth);
    const aspect = this.domElement.clientWidth / Math.max(1, this.domElement.clientHeight);
    this.orthoCamera.left = (-maxDim / 2) * aspect;
    this.orthoCamera.right = (maxDim / 2) * aspect;
    this.orthoCamera.top = maxDim / 2;
    this.orthoCamera.bottom = -maxDim / 2;
    this.orthoCamera.position.set(0, 800, 0);
    this.orthoCamera.lookAt(0, 0, 0);
    this.orthoCamera.zoom = 1;
    this.orthoCamera.updateProjectionMatrix();

    this.controls.object = this.orthoCamera;
    this.controls.minPolarAngle = THREE_ORBIT_DEG(0);
    this.controls.maxPolarAngle = THREE_ORBIT_DEG(0.01);
    this.controls.maxAzimuthAngle = Infinity;
    this.controls.minAzimuthAngle = -Infinity;
    this.controls.enableRotate = false;
    this.controls.target.set(0, 0, 0);
    this.controls.update();
    this.mode = '2d';
  }

  /** Retour vue 3D perspective. */
  set3DView() {
    this.controls.object = this.perspectiveCamera;
    this.controls.minPolarAngle = THREE_ORBIT_DEG(28);
    this.controls.maxPolarAngle = THREE_ORBIT_DEG(72);
    this.controls.maxAzimuthAngle = THREE_ORBIT_DEG(45);
    this.controls.minAzimuthAngle = THREE_ORBIT_DEG(-45);
    this.controls.enableRotate = true;

    if (this._savedPerspective) {
      this.perspectiveCamera.position.copy(this._savedPerspective.position);
      this.controls.target.copy(this._savedPerspective.target);
    } else {
      this.setDefault3DView();
    }
    this.controls.update();
    this.mode = '3d';
  }

  /** Caméra active selon le mode. */
  getActiveCamera() {
    return this.mode === '2d' ? this.orthoCamera : this.perspectiveCamera;
  }

  /**
   * Centre la vue sur une coordonnée grille (x, y) — y devient z monde.
   * @param {number} x
   * @param {number} y — profondeur (axe Z Three.js)
   * @param {number} [worldWidth]
   * @param {number} [worldDepth]
   */
  focusOnGrid(x, y, worldWidth, worldDepth) {
    const wx = x - (worldWidth || this.bounds.worldWidth) / 2;
    const wz = y - (worldDepth || this.bounds.worldDepth) / 2;
    this.controls.target.set(wx, 0, wz);
    this.controls.update();
  }

  /** Met à jour les dimensions ortho après resize. */
  onResize(width, height) {
    const aspect = width / Math.max(1, height);
    const maxDim = Math.max(this.bounds.worldWidth, this.bounds.worldDepth);
    this.orthoCamera.left = (-maxDim / 2) * aspect;
    this.orthoCamera.right = (maxDim / 2) * aspect;
    this.orthoCamera.top = maxDim / 2;
    this.orthoCamera.bottom = -maxDim / 2;
    this.orthoCamera.updateProjectionMatrix();
  }

  update() {
    this.controls.update();
  }

  dispose() {
    this.controls.dispose();
  }
}

/** Convertit des degrés en radians pour OrbitControls. */
function THREE_ORBIT_DEG(deg) {
  return (deg * Math.PI) / 180;
}

export { THREE_ORBIT_DEG };
