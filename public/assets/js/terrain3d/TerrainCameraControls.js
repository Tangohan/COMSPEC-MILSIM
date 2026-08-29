/**
 * Contrôles caméra tactiques — vue inclinée, zoom gradué, rotation limitée, pan.
 */
import { clamp } from 'atak-terrain3d/utils.js';

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
    this._framed = false;
    this._dollyTargetDist = null;
    this._dollyTargetZoom = null;
    this._dollyEase = 0.14;

    this._configureOrbit();
  }

  _configureOrbit() {
    const c = this.controls;
    c.enableDamping = true;
    c.dampingFactor = 0.08;
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
    /* Zoom molette plus fin — évite les sauts « pop ». */
    c.zoomSpeed = 0.72;
    c.panSpeed = 0.85;
    c.target.set(0, 0, 0);
  }

  /**
   * Adapte near/far, distances orbit au monde réel (ex. Altis ~30 km).
   * Par défaut conserve le cadrage courant (pas de reset au chargement heights).
   * @param {number} worldWidth
   * @param {number} worldDepth
   * @param {{ resetView?: boolean }} [opts]
   */
  syncToWorld(worldWidth, worldDepth, opts) {
    opts = opts || {};
    const w = Math.max(256, Number(worldWidth) || this.bounds.worldWidth || 1024);
    const d = Math.max(256, Number(worldDepth) || this.bounds.worldDepth || 1024);
    this.bounds.worldWidth = w;
    this.bounds.worldDepth = d;
    const diag = Math.sqrt(w * w + d * d);
    const far = Math.max(12000, diag * 3.2);
    const maxDist = Math.max(3200, diag * 1.55);
    const minDist = Math.max(28, Math.min(220, diag * 0.0045));

    this.perspectiveCamera.near = Math.max(1, minDist * 0.05);
    this.perspectiveCamera.far = far;
    this.perspectiveCamera.updateProjectionMatrix();
    this.orthoCamera.near = 1;
    this.orthoCamera.far = far;
    this.orthoCamera.updateProjectionMatrix();

    const c = this.controls;
    c.minDistance = minDist;
    c.maxDistance = maxDist;

    if (opts.resetView === true || !this._framed) {
      if (this.mode === '3d') {
        this.setDefault3DView();
      } else {
        this.onResize(this.domElement.clientWidth, this.domElement.clientHeight);
      }
      return;
    }

    /* Conserver la distance / cible actuelles, juste les clamper aux nouvelles bornes. */
    this._dollyTargetDist = null;
    const cam = this.getActiveCamera();
    if (this.mode === '3d' && cam && c.target) {
      const offset = cam.position.clone().sub(c.target);
      const len = offset.length();
      if (Number.isFinite(len) && len > 0) {
        offset.setLength(clamp(len, minDist, maxDist));
        cam.position.copy(c.target).add(offset);
      }
    }
    c.update();
  }

  /** Position initiale type C2 incliné — cadrée sur la taille monde. */
  setDefault3DView() {
    const w = Math.max(256, this.bounds.worldWidth || 1024);
    const d = Math.max(256, this.bounds.worldDepth || 1024);
    const diag = Math.sqrt(w * w + d * d);
    const dist = Math.min(this.controls.maxDistance * 0.92, Math.max(this.controls.minDistance * 8, diag * 0.72));
    const elev = dist * 0.55;
    this._dollyTargetDist = null;
    this.perspectiveCamera.position.set(0, elev, dist * 0.85);
    this.controls.object = this.perspectiveCamera;
    this.controls.minPolarAngle = THREE_ORBIT_DEG(28);
    this.controls.maxPolarAngle = THREE_ORBIT_DEG(72);
    this.controls.maxAzimuthAngle = THREE_ORBIT_DEG(45);
    this.controls.minAzimuthAngle = THREE_ORBIT_DEG(-45);
    this.controls.enableRotate = true;
    this.controls.target.set(0, 0, 0);
    this.controls.update();
    this._framed = true;
  }

  /**
   * Recul caméra (dézoom) — facteur > 1 = plus loin.
   * Zoom gradué : cible interpolée dans update() (pas de téléport).
   * @param {number} factor
   * @param {{ animate?: boolean }} [opts]
   */
  dolly(factor, opts) {
    const f = Number(factor);
    if (!Number.isFinite(f) || f <= 0) return;
    opts = opts || {};
    const animate = opts.animate !== false;
    const cam = this.getActiveCamera();
    if (this.mode === '2d' && this.orthoCamera) {
      const nextZoom = clamp(this.orthoCamera.zoom / f, 0.15, 12);
      if (animate) {
        this._dollyTargetZoom = nextZoom;
      } else {
        this._dollyTargetZoom = null;
        this.orthoCamera.zoom = nextZoom;
        this.orthoCamera.updateProjectionMatrix();
        this.controls.update();
      }
      return;
    }
    const target = this.controls.target;
    const offset = cam.position.clone().sub(target);
    const curLen = offset.length() || 1;
    const minD = this.controls.minDistance || 80;
    const maxD = this.controls.maxDistance || 2800;
    const nextLen = clamp(curLen * f, minD, maxD);
    if (animate) {
      this._dollyTargetDist = nextLen;
    } else {
      this._dollyTargetDist = null;
      offset.setLength(nextLen);
      cam.position.copy(target).add(offset);
      this.controls.update();
    }
  }

  /** Vue 2D orthographique — regard vertical, rotation désactivée. */
  set2DView() {
    this._savedPerspective = {
      position: this.perspectiveCamera.position.clone(),
      target: this.controls.target.clone(),
    };
    this._dollyTargetDist = null;
    this._dollyTargetZoom = null;
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
    this._dollyTargetZoom = null;
    this.controls.object = this.perspectiveCamera;
    this.controls.minPolarAngle = THREE_ORBIT_DEG(28);
    this.controls.maxPolarAngle = THREE_ORBIT_DEG(72);
    this.controls.maxAzimuthAngle = THREE_ORBIT_DEG(45);
    this.controls.minAzimuthAngle = THREE_ORBIT_DEG(-45);
    this.controls.enableRotate = true;

    if (this._savedPerspective) {
      this.perspectiveCamera.position.copy(this._savedPerspective.position);
      this.controls.target.copy(this._savedPerspective.target);
      this._framed = true;
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

  _tickDolly() {
    const ease = this._dollyEase;
    if (this.mode === '2d' && this._dollyTargetZoom != null && this.orthoCamera) {
      const cur = this.orthoCamera.zoom;
      const next = cur + (this._dollyTargetZoom - cur) * ease;
      if (Math.abs(this._dollyTargetZoom - next) < 0.002) {
        this.orthoCamera.zoom = this._dollyTargetZoom;
        this._dollyTargetZoom = null;
      } else {
        this.orthoCamera.zoom = next;
      }
      this.orthoCamera.updateProjectionMatrix();
      return;
    }
    if (this._dollyTargetDist == null || this.mode !== '3d') return;
    const cam = this.perspectiveCamera;
    const target = this.controls.target;
    const offset = cam.position.clone().sub(target);
    const curLen = offset.length() || 1;
    const nextLen = curLen + (this._dollyTargetDist - curLen) * ease;
    if (Math.abs(this._dollyTargetDist - nextLen) < Math.max(0.5, this._dollyTargetDist * 0.0015)) {
      offset.setLength(this._dollyTargetDist);
      this._dollyTargetDist = null;
    } else {
      offset.setLength(nextLen);
    }
    cam.position.copy(target).add(offset);
  }

  update() {
    /* Garde la cible orbit dans le rectangle monde — un pan trop loin + dézoom = fond noir. */
    const halfW = (this.bounds.worldWidth || 1024) * 0.55;
    const halfD = (this.bounds.worldDepth || 1024) * 0.55;
    const t = this.controls.target;
    t.x = clamp(t.x, -halfW, halfW);
    t.z = clamp(t.z, -halfD, halfD);
    this._tickDolly();
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
