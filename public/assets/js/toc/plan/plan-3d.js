/*
 * Vue 3D du relief pour l'éditeur de plans (TOC).
 *
 * Module ES autonome : three.js r160 est importé par chemin relatif, donc AUCUNE import map
 * n'est nécessaire sur la page hôte. Le contrôleur de caméra est écrit ici (pas d'OrbitControls,
 * qui importe le spécificateur nu « three »).
 *
 * L'éditeur 2D reste propriétaire des données : ce module affiche et rapporte les interactions.
 *
 * Repères
 *   monde : mètres Arma [x, y], y vers le nord, altitude z ; terrain carré 0..worldSize
 *   scène : X = x - cx, Y = z (l'exagération est l'échelle Y du groupe racine), Z = cy - y
 *   pitch   : angle de la caméra au-dessus de l'horizon, en degrés (88 = presque à la verticale)
 *   bearing : cap vers lequel regarde la caméra, degrés dans le sens horaire depuis le nord
 *   mpp     : mètres par pixel CSS au point visé
 */
import * as THREE from '../../../vendor/three/build/three.module.js';

const FOV = 35;
const TAN_HALF = Math.tan((FOV * Math.PI) / 360);
const DEG = Math.PI / 180;
const PITCH_MIN = 8;
const PITCH_MAX = 88;
const DIST_MIN = 30;
const COARSE_MAX = 192;
const FINE_MAX = 512;
const FINE_MIN = 192;
const STALK_PX = 38;
const SKY_TOP = [0.027, 0.039, 0.051];      // #070a0d
const SKY_HORIZON = [0.086, 0.122, 0.157];  // un peu plus clair à l'horizon

function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
function lerp(a, b, t) { return a + (b - a) * t; }
function smoothstep(a, b, x) {
  const t = clamp((x - a) / (b - a), 0, 1);
  return t * t * (3 - 2 * t);
}

/* ------------------------------------------------------------------ */
/* Mathématiques pures (testées par la page de démonstration)          */
/* ------------------------------------------------------------------ */

// Monde -> scène et retour (h = altitude déjà exagérée).
function worldToScene(x, y, h, origin, out) {
  out = out || { x: 0, y: 0, z: 0 };
  out.x = x - origin.x;
  out.y = h;
  out.z = origin.y - y;
  return out;
}
function sceneToWorld(X, Y, Z, origin) {
  return { x: X + origin.x, y: origin.y - Z, h: Y };
}

// Lecture du DEM (contrat §4). Les cellules manquantes valent 0 ; hors emprise : 0.
function makeDemSampler(dem) {
  const ok = !!(dem && dem.heights && dem.cols >= 2 && dem.rows >= 2 && dem.cell > 0
    && dem.heights.length >= dem.cols * dem.rows);
  if (!ok) {
    return { valid: false, cell: 0, maxZ: 0, minZ: 0, heightAt: () => 0, smoothAt: () => 0, areaAt: () => 0 };
  }
  const cols = dem.cols, rows = dem.rows, cell = dem.cell;
  const ox = dem.ox || 0, oy = dem.oy || 0;
  const H = dem.heights;
  const miss = (dem.missing === undefined || dem.missing === null) ? -32768 : dem.missing;

  function at(c, r) {
    c = c < 0 ? 0 : (c > cols - 1 ? cols - 1 : c);
    r = r < 0 ? 0 : (r > rows - 1 ? rows - 1 : r);
    const v = H[r * cols + c];
    return (v === miss || v !== v || v === undefined) ? 0 : v;
  }

  // Bilinéaire.
  function heightAt(x, y) {
    const fx = (x - ox) / cell, fy = (y - oy) / cell;
    if (!(fx >= 0 && fy >= 0 && fx <= cols - 1 && fy <= rows - 1)) return 0;
    let c = Math.floor(fx), r = Math.floor(fy);
    if (c > cols - 2) c = cols - 2;
    if (r > rows - 2) r = rows - 2;
    const tx = fx - c, ty = fy - r;
    const a = at(c, r), b = at(c + 1, r), d = at(c, r + 1), e = at(c + 1, r + 1);
    return (a + (b - a) * tx) * (1 - ty) + (d + (e - d) * tx) * ty;
  }

  // Catmull-Rom : sert seulement à lisser le maillage fin quand il est plus dense que le DEM.
  function cr(p0, p1, p2, p3, t) {
    return p1 + 0.5 * t * (p2 - p0 + t * (2 * p0 - 5 * p1 + 4 * p2 - p3 + t * (3 * (p1 - p2) + p3 - p0)));
  }
  function smoothAt(x, y) {
    const fx = (x - ox) / cell, fy = (y - oy) / cell;
    if (!(fx >= 0 && fy >= 0 && fx <= cols - 1 && fy <= rows - 1)) return 0;
    let c = Math.floor(fx), r = Math.floor(fy);
    if (c > cols - 2) c = cols - 2;
    if (r > rows - 2) r = rows - 2;
    const tx = fx - c, ty = fy - r;
    let lo = Infinity, hi = -Infinity;
    const row = [0, 0, 0, 0];
    for (let j = -1; j <= 2; j++) {
      const p0 = at(c - 1, r + j), p1 = at(c, r + j), p2 = at(c + 1, r + j), p3 = at(c + 2, r + j);
      if (j === 0 || j === 1) {
        lo = Math.min(lo, p1, p2);
        hi = Math.max(hi, p1, p2);
      }
      row[j + 1] = cr(p0, p1, p2, p3, tx);
    }
    // Borné aux quatre voisins : pas de dépassement ni de pointe.
    return clamp(cr(row[0], row[1], row[2], row[3], ty), lo, hi);
  }

  // Moyenne sur un carré de côté `size` (décimation sans crénelage).
  function areaAt(x, y, size) {
    if (size <= cell * 1.5) return heightAt(x, y);
    const n = size > cell * 4 ? 4 : 3;
    let s = 0;
    for (let j = 0; j < n; j++) {
      for (let i = 0; i < n; i++) {
        s += heightAt(x + ((i + 0.5) / n - 0.5) * size, y + ((j + 0.5) / n - 0.5) * size);
      }
    }
    return s / (n * n);
  }

  let maxZ = Number(dem.maxZ), minZ = Number(dem.minZ);
  if (!isFinite(maxZ) || !isFinite(minZ)) {
    maxZ = -Infinity; minZ = Infinity;
    for (let i = 0; i < cols * rows; i++) {
      const v = H[i];
      if (v === miss) continue;
      if (v > maxZ) maxZ = v;
      if (v < minZ) minZ = v;
    }
    if (!isFinite(maxZ)) { maxZ = 0; minZ = 0; }
  }
  return { valid: true, cell, cols, rows, ox, oy, maxZ, minZ, heightAt, smoothAt, areaAt };
}

// Grille grossière de tout le terrain. raw = altitude du DEM, disp = altitude affichée (mer aplatie à 0).
function buildCoarseGrid(sampler, worldSize) {
  let n = 16;
  if (sampler.valid) n = clamp(Math.round(worldSize / sampler.cell), 16, COARSE_MAX);
  const step = worldSize / n;
  const m = n + 1;
  const raw = new Float32Array(m * m), disp = new Float32Array(m * m);
  for (let j = 0; j < m; j++) {
    for (let i = 0; i < m; i++) {
      const h = sampler.areaAt(i * step, j * step, step);
      raw[j * m + i] = h;
      disp[j * m + i] = h > 0 ? h : 0;
    }
  }
  // Interpolation exactement comme les triangles du maillage (diagonale a-d).
  function tri(arr, x, y, outside) {
    if (!(x >= 0 && y >= 0 && x <= worldSize && y <= worldSize)) return outside;
    const gx = x / step, gy = y / step;
    let i = Math.floor(gx), j = Math.floor(gy);
    if (i > n - 1) i = n - 1;
    if (j > n - 1) j = n - 1;
    const fx = gx - i, fy = gy - j;
    const a = arr[j * m + i], b = arr[j * m + i + 1], c = arr[(j + 1) * m + i], d = arr[(j + 1) * m + i + 1];
    return fx >= fy ? a + fx * (b - a) + fy * (d - b) : a + fx * (d - c) + fy * (c - a);
  }
  const z = (x, y) => tri(disp, x, y, 0);          // altitude affichée
  const rawAt = (x, y) => tri(raw, x, y, -90);     // altitude du DEM (couleurs, courbes de niveau)
  return { n, m, step, raw, disp, z, rawAt, worldSize };
}

// Zone d'intérêt rendue carrée (une seule taille de texture), calée sur les cellules du DEM si possible.
function squareAoi(aoi, sampler, worldSize) {
  let x0 = 0, y0 = 0, x1 = worldSize, y1 = worldSize;
  if (aoi && isFinite(aoi.x0) && isFinite(aoi.y0) && isFinite(aoi.x1) && isFinite(aoi.y1)) {
    x0 = Math.min(aoi.x0, aoi.x1); x1 = Math.max(aoi.x0, aoi.x1);
    y0 = Math.min(aoi.y0, aoi.y1); y1 = Math.max(aoi.y0, aoi.y1);
  }
  let size = Math.max(x1 - x0, y1 - y0, 200);
  let cx = (x0 + x1) / 2, cy = (y0 + y1) / 2;
  let ax = cx - size / 2, ay = cy - size / 2;
  let n, step;
  if (sampler.valid && size / sampler.cell <= FINE_MAX) {
    const cell = sampler.cell;
    ax = Math.floor((ax - sampler.ox) / cell) * cell + sampler.ox;
    ay = Math.floor((ay - sampler.oy) / cell) * cell + sampler.oy;
    const cells = Math.max(2, Math.ceil((size + cell) / cell));
    size = cells * cell;
    // DEM grossier sur une petite zone : on subdivise (lissage Catmull-Rom) pour un relief plus doux.
    const k = cells >= FINE_MIN ? 1 : Math.min(4, Math.ceil(FINE_MIN / cells));
    n = cells * k;
    step = cell / k;
  } else if (sampler.valid) {
    n = FINE_MAX;
    step = size / n;
  } else {
    n = 32;
    step = size / n;
  }
  return { x0: ax, y0: ay, x1: ax + size, y1: ay + size, size, n, step };
}

// Grille fine de la zone d'intérêt. Le bord est raccordé à la surface grossière : aucune fissure.
function buildFineGrid(sampler, coarse, sq) {
  const n = sq.n, m = n + 1, step = sq.step;
  const raw = new Float32Array(m * m), disp = new Float32Array(m * m);
  const margin = Math.max(2 * step, Math.min(coarse.step, sq.size * 0.06));
  const smooth = sampler.valid && step < sampler.cell * 0.99;
  for (let j = 0; j < m; j++) {
    const y = sq.y0 + j * step;
    for (let i = 0; i < m; i++) {
      const x = sq.x0 + i * step;
      const h = !sampler.valid ? 0 : (smooth ? sampler.smoothAt(x, y) : sampler.areaAt(x, y, step));
      let d = h > 0 ? h : 0;
      let rw = h;
      const edge = Math.min(i, n - i, j, n - j) * step;
      if (edge < margin) {
        // Raccord progressif : au bord, mêmes altitude et teinte que le maillage grossier.
        const w = smoothstep(0, margin, edge);
        d = lerp(coarse.z(x, y), d, w);
        rw = lerp(coarse.rawAt(x, y), rw, w);
      }
      raw[j * m + i] = rw;
      disp[j * m + i] = d;
    }
  }
  function z(x, y) {
    const gx = (x - sq.x0) / step, gy = (y - sq.y0) / step;
    let i = Math.floor(gx), j = Math.floor(gy);
    i = i < 0 ? 0 : (i > n - 1 ? n - 1 : i);
    j = j < 0 ? 0 : (j > n - 1 ? n - 1 : j);
    const fx = clamp(gx - i, 0, 1), fy = clamp(gy - j, 0, 1);
    const a = disp[j * m + i], b = disp[j * m + i + 1], c = disp[(j + 1) * m + i], d = disp[(j + 1) * m + i + 1];
    return fx >= fy ? a + fx * (b - a) + fy * (d - b) : a + fx * (d - c) + fy * (c - a);
  }
  return { n, m, step, raw, disp, z, sq };
}

// Surface réellement affichée (fine dans la zone d'intérêt, grossière ailleurs, 0 hors du terrain).
function makeSurface(coarse, fine) {
  const sq = fine.sq;
  let maxZ = 0;
  for (let i = 0; i < coarse.disp.length; i++) if (coarse.disp[i] > maxZ) maxZ = coarse.disp[i];
  for (let i = 0; i < fine.disp.length; i++) if (fine.disp[i] > maxZ) maxZ = fine.disp[i];
  return {
    maxZ,
    z(x, y) {
      if (x >= sq.x0 && x <= sq.x1 && y >= sq.y0 && y <= sq.y1) return fine.z(x, y);
      return coarse.z(x, y);
    },
  };
}

// Intersection rayon / surface. o = [x, y, h] et d = [dx, dy, dh] en repère monde, h exagéré.
// Renvoie { x, y, z (altitude réelle, non exagérée), t } ou null.
function raycastSurface(surface, o, d, exag, maxDist) {
  const len = Math.hypot(d[0], d[1], d[2]);
  if (!(len > 0)) return null;
  const dx = d[0] / len, dy = d[1] / len, dh = d[2] / len;
  const top = surface.maxZ * exag + 1;
  let t = 0;
  if (o[2] > top) {
    if (dh >= 0) return null;
    t = (top - o[2]) / dh;           // saute directement sous le plafond du relief
  }
  if (o[2] - surface.z(o[0], o[1]) * exag < 0) return null;
  let prev = t;
  for (let it = 0; it < 6000 && t <= maxDist; it++) {
    const x = o[0] + dx * t, y = o[1] + dy * t, h = o[2] + dh * t;
    const gap = h - surface.z(x, y) * exag;
    if (gap <= 0) {
      let a = prev, b = t;
      for (let k = 0; k < 24; k++) {
        const mid = (a + b) / 2;
        const g = (o[2] + dh * mid) - surface.z(o[0] + dx * mid, o[1] + dy * mid) * exag;
        if (g > 0) a = mid; else b = mid;
      }
      const tt = (a + b) / 2;
      const hx = o[0] + dx * tt, hy = o[1] + dy * tt;
      return { x: hx, y: hy, z: surface.z(hx, hy), t: tt };
    }
    if (dh > 0 && h > top) return null;
    prev = t;
    t += clamp(gap * 0.5, Math.max(1.5, t * 0.003), 600);
  }
  return null;
}

// Direction du soleil (repère scène : X est, Y haut, Z sud) pour une heure solaire locale.
function sunDirection(hour) {
  const lat = 38 * DEG, dec = 12 * DEG;
  const ha = ((((Number(hour) % 24) + 24) % 24) - 12) * 15 * DEG;
  const e = -Math.cos(dec) * Math.sin(ha);
  const n = Math.cos(lat) * Math.sin(dec) - Math.sin(lat) * Math.cos(dec) * Math.cos(ha);
  const u = Math.sin(lat) * Math.sin(dec) + Math.cos(lat) * Math.cos(dec) * Math.cos(ha);
  return { x: e, y: u, z: -n, elevation: Math.asin(clamp(u, -1, 1)) / DEG };
}

function distToMpp(dist, cssH) { return (dist * 2 * TAN_HALF) / Math.max(1, cssH); }
function mppToDist(mpp, cssH) { return (mpp * Math.max(1, cssH)) / (2 * TAN_HALF); }

// Position de la caméra (monde, h exagéré) pour un état { x, y, dist, bearing, pitch } et un point visé à l'altitude tz.
function cameraPosition(s, tz) {
  const b = s.bearing * DEG, p = s.pitch * DEG;
  return [
    s.x - Math.sin(b) * Math.cos(p) * s.dist,
    s.y - Math.cos(b) * Math.cos(p) * s.dist,
    tz + Math.sin(p) * s.dist,
  ];
}

export const __plan3dInternals = {
  worldToScene, sceneToWorld, makeDemSampler, buildCoarseGrid, squareAoi, buildFineGrid, makeSurface,
  raycastSurface, sunDirection, distToMpp, mppToDist, cameraPosition,
};

/* ------------------------------------------------------------------ */
/* WebGL                                                               */
/* ------------------------------------------------------------------ */

let webglProbe = null;
export function isWebGLAvailable() {
  if (webglProbe !== null) return webglProbe;
  try {
    const c = document.createElement('canvas');
    const gl = c.getContext('webgl2') || c.getContext('webgl');
    webglProbe = !!gl;
    const lose = gl && gl.getExtension('WEBGL_lose_context');
    if (lose) lose.loseContext();
  } catch (e) {
    webglProbe = false;
  }
  return webglProbe;
}

/* ------------------------------------------------------------------ */
/* Shaders (couleurs écrites telles quelles en sRGB, sans conversion)  */
/* ------------------------------------------------------------------ */

const GLSL_FOG = /* glsl */`
uniform vec3 uFogCol;
uniform vec2 uFog;
float fogAmount(float d) { float f = smoothstep(uFog.x, uFog.y, d); return f * f * 0.96; }
`;

const GLSL_LIGHT = /* glsl */`
uniform vec3 uSunDir;
uniform vec3 uSunCol;
uniform vec3 uSky;
uniform vec3 uGround;
vec3 lightAt(vec3 n) {
  float nl = max(dot(n, uSunDir), 0.0);
  return mix(uGround, uSky, n.y * 0.5 + 0.5) + uSunCol * nl;
}
`;

const TERRAIN_VERT = /* glsl */`
attribute float aH;
uniform vec2 uOrigin;
uniform float uExag;
uniform float uPush;
varying vec2 vW;
varying float vH;
varying float vSlope;
varying vec3 vN;
varying float vDist;
void main() {
  vW = vec2(position.x + uOrigin.x, uOrigin.y - position.z);
  vH = aH;
  vSlope = 1.0 - normal.y;
  vN = normalize(vec3(normal.x * uExag, normal.y, normal.z * uExag));
  vec4 mv = modelViewMatrix * vec4(position, 1.0);
  vDist = length(mv.xyz);
  mv.xyz *= uPush;              // biais de profondeur le long du rayon : aucun décalage à l'écran
  gl_Position = projectionMatrix * mv;
}
`;

const TERRAIN_FRAG = /* glsl */`
precision highp float;
uniform vec4 uAoi;
uniform float uCoarse;
uniform float uInset;
uniform float uDimBand;
uniform sampler2D uBase;
uniform float uHasBase;
uniform vec4 uBaseRect;
uniform float uHmax;
uniform float uContour;
uniform float uFlat;
varying vec2 vW;
varying float vH;
varying float vSlope;
varying vec3 vN;
varying float vDist;
${GLSL_FOG}
${GLSL_LIGHT}

vec3 hypso(float h) {
  float t = clamp(h / uHmax, 0.0, 1.0);
  vec3 c = mix(vec3(0.31, 0.41, 0.26), vec3(0.45, 0.50, 0.30), smoothstep(0.0, 0.20, t));
  c = mix(c, vec3(0.62, 0.55, 0.36), smoothstep(0.16, 0.46, t));
  c = mix(c, vec3(0.57, 0.51, 0.45), smoothstep(0.44, 0.74, t));
  c = mix(c, vec3(0.72, 0.71, 0.68), smoothstep(0.74, 1.0, t));
  return c;
}

void main() {
  vec2 o = max(uAoi.xy - vW, vW - uAoi.zw);
  float outside = max(o.x, o.y);                 // > 0 hors de la zone d'intérêt
  if (uCoarse > 0.5 && outside < -uInset) discard;

  vec3 n = normalize(vN);
  if (!gl_FrontFacing) n = -n;
  float water = (1.0 - smoothstep(-1.0, 0.4, vH)) * (1.0 - uFlat * step(-0.5, vH));

  vec3 col;
  vec2 uv = (vW - uBaseRect.xy) / uBaseRect.zw;
  bool hasBase = uHasBase > 0.5 && uv.x >= 0.0 && uv.x <= 1.0 && uv.y >= 0.0 && uv.y <= 1.0;
  if (hasBase) {
    col = texture2D(uBase, uv).rgb;
  } else if (uFlat > 0.5 && vH > -0.5) {
    col = vec3(0.33, 0.40, 0.29);                 // pas de relief : teinte unie
  } else {
    col = hypso(max(vH, 0.0));
    col = mix(col, vec3(0.44, 0.42, 0.40), smoothstep(0.08, 0.34, vSlope) * 0.65);   // pentes raides : roche
    col = mix(vec3(0.74, 0.70, 0.54), col, smoothstep(0.5, 5.0, vH));               // liseré de plage
    // Courbes de niveau : fines tous les uContour mètres, maîtresses toutes les cinq.
    // Décalées de 0,5 m : un DEM en mètres entiers a des paliers exactement sur les multiples.
    float g = (vH + 0.5) / uContour;
    float w = fwidth(g);
    float l1 = 1.0 - smoothstep(0.7, 1.5, abs(fract(g - 0.5) - 0.5) / max(w, 1e-5));
    float g5 = g / 5.0;
    float w5 = fwidth(g5);
    float l5 = 1.0 - smoothstep(1.0, 2.0, abs(fract(g5 - 0.5) - 0.5) / max(w5, 1e-5));
    float lines = max(l1 * 0.20 * (1.0 - smoothstep(0.25, 0.6, w)), l5 * 0.34 * (1.0 - smoothstep(0.25, 0.6, w5)));
    col *= 1.0 - lines * (1.0 - water);
    vec3 sea = mix(vec3(0.035, 0.115, 0.175), vec3(0.085, 0.26, 0.33), smoothstep(-60.0, 0.0, vH));
    col = mix(col, sea, water);
  }

  n = normalize(mix(n, vec3(0.0, 1.0, 0.0), water));
  vec3 lit = lightAt(n);
  if (hasBase) lit = mix(vec3(1.0), lit, 0.8);
  col *= lit;
  // Épaule douce : les sommets clairs en plein soleil ne saturent pas.
  vec3 over = max(col - 0.72, 0.0);
  col = min(col, vec3(0.72)) + 0.28 * (1.0 - exp(-over / 0.28));

  // Hors de la zone d'intérêt : plus sombre et désaturé, progressivement.
  float dim = uCoarse * smoothstep(0.0, uDimBand, outside);
  float luma = dot(col, vec3(0.299, 0.587, 0.114));
  col = mix(col, mix(col, vec3(luma), 0.55) * 0.6, dim);

  col = mix(col, uFogCol, fogAmount(vDist));
  gl_FragColor = vec4(col, 1.0);
}
`;

const OVERLAY_VERT = /* glsl */`
uniform vec2 uOrigin;
uniform vec4 uRect;
varying vec2 vUv;
varying float vDist;
void main() {
  vUv = (vec2(position.x + uOrigin.x, uOrigin.y - position.z) - uRect.xy) / uRect.zw;
  vec4 mv = modelViewMatrix * vec4(position, 1.0);
  vDist = length(mv.xyz);
  mv.xyz *= 0.9985;             // légèrement devant le terrain, sans décalage à l'écran
  gl_Position = projectionMatrix * mv;
}
`;

const OVERLAY_FRAG = /* glsl */`
precision highp float;
uniform sampler2D uMap;
uniform float uOpacity;
varying vec2 vUv;
varying float vDist;
${GLSL_FOG}
void main() {
  vec4 t = texture2D(uMap, vUv);                  // alpha prémultiplié
  if (t.a < 0.004) discard;
  float f = fogAmount(vDist) * 0.85;
  gl_FragColor = vec4(mix(t.rgb, uFogCol * t.a, f), t.a) * uOpacity;
}
`;

const SKY_VERT = /* glsl */`
varying vec3 vDir;
void main() {
  vDir = position;
  gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
}
`;
const SKY_FRAG = /* glsl */`
precision highp float;
uniform vec3 uTop;
uniform vec3 uFogCol;
varying vec3 vDir;
void main() {
  float e = normalize(vDir).y;
  gl_FragColor = vec4(mix(uFogCol, uTop, smoothstep(0.0, 0.42, e)), 1.0);
}
`;

// Symboles : rectangle face à l'écran, taille constante en pixels, posé au sommet d'une tige verticale
// dont la hauteur monde est proportionnelle à la distance (donc constante à l'écran vue de côté).
const GLSL_STALK_TOP = /* glsl */`
uniform float uK;       // mètres par pixel CSS à une distance de 1
vec4 stalkTop(vec4 mv, float px) {
  vec3 up = normalize((viewMatrix * vec4(0.0, 1.0, 0.0, 0.0)).xyz);
  mv.xyz += up * (px * uK * max(-mv.z, 0.0));
  return mv;
}
`;

const SPRITE_VERT = /* glsl */`
uniform vec2 uViewport;   // pixels CSS
uniform float uRatio;     // pixels écran par pixel CSS
uniform vec2 uSize;       // taille du symbole en pixels CSS
uniform vec2 uAnchor;     // point d'ancrage dans le symbole (pixels CSS, depuis le coin haut gauche)
uniform float uStalkPx;
varying vec2 vUv;
${GLSL_STALK_TOP}
void main() {
  vUv = uv;
  vec4 mv = stalkTop(modelViewMatrix * vec4(0.0, 0.0, 0.0, 1.0), uStalkPx);
  mv.xyz *= 0.995;
  vec4 clip = projectionMatrix * mv;
  vec2 px = (clip.xy / clip.w * 0.5 + 0.5) * uViewport;
  px = floor(px * uRatio + 0.5) / uRatio;                                   // calé sur les pixels : symbole net
  px += vec2(position.x * uSize.x - uAnchor.x, uAnchor.y - (1.0 - position.y) * uSize.y);
  clip.xy = (px / uViewport * 2.0 - 1.0) * clip.w;
  gl_Position = clip;
}
`;
const SPRITE_FRAG = /* glsl */`
precision highp float;
uniform sampler2D uMap;
uniform float uOpacity;
varying vec2 vUv;
void main() {
  vec4 t = texture2D(uMap, vUv);
  if (t.a < 0.004) discard;
  gl_FragColor = t * uOpacity;
}
`;

const STALK_VERT = /* glsl */`
attribute float aTop;
attribute vec4 aColor;
varying vec4 vColor;
${GLSL_STALK_TOP}
void main() {
  vColor = aColor;
  vec4 mv = modelViewMatrix * vec4(position, 1.0);
  if (aTop > 0.0) mv = stalkTop(mv, aTop);
  mv.xyz *= 0.995;
  gl_Position = projectionMatrix * mv;
}
`;
const STALK_FRAG = /* glsl */`
precision highp float;
uniform float uOpacity;
varying vec4 vColor;
void main() { gl_FragColor = vec4(vColor.rgb, 1.0) * (vColor.a * uOpacity); }
`;

const DOT_VERT = /* glsl */`
attribute float aSel;
attribute vec4 aColor;
uniform float uRatio;
varying vec4 vColor;
varying float vSel;
void main() {
  vColor = aColor;
  vSel = aSel;
  vec4 mv = modelViewMatrix * vec4(position, 1.0);
  mv.xyz *= 0.992;
  gl_Position = projectionMatrix * mv;
  gl_PointSize = (aSel > 0.5 ? 22.0 : 9.0) * uRatio;
}
`;
const DOT_FRAG = /* glsl */`
precision highp float;
uniform float uOpacity;
uniform vec3 uAccent;
varying vec4 vColor;
varying float vSel;
void main() {
  float r = length(gl_PointCoord - 0.5) * 2.0;
  float a;
  vec3 c = vColor.rgb;
  if (vSel > 0.5) {
    // Sélection : point plein et anneau de couleur d'accent.
    float ring = smoothstep(0.62, 0.70, r) * (1.0 - smoothstep(0.88, 0.98, r));
    float core = 1.0 - smoothstep(0.26, 0.34, r);
    a = max(ring, core);
    c = uAccent;
  } else {
    a = 1.0 - smoothstep(0.72, 0.98, r);
    c = mix(c, vec3(0.03, 0.05, 0.06), smoothstep(0.42, 0.62, r));   // liseré sombre
  }
  if (a < 0.01) discard;
  a *= vColor.a * uOpacity;
  gl_FragColor = vec4(c * a, a);
}
`;

// Lignes aériennes épaisses (largeur en pixels), un rectangle par segment.
const AIR_VERT = /* glsl */`
attribute vec3 aOther;
attribute float aSide;
attribute float aLen;
attribute vec4 aColor;    // rvb + tirets (0 ou 1)
attribute float aWidth;
uniform vec2 uViewport;
uniform float uNear;
varying vec4 vColor;
varying float vLen;
void main() {
  vColor = aColor;
  vLen = aLen;
  vec4 a = modelViewMatrix * vec4(position, 1.0);
  vec4 b = modelViewMatrix * vec4(aOther, 1.0);
  float zn = -uNear;
  if (a.z > zn && b.z < zn) a = mix(a, b, (a.z - zn) / (a.z - b.z));   // ramène le bout devant le plan proche
  if (b.z > zn && a.z < zn) b = mix(b, a, (b.z - zn) / (b.z - a.z));
  vec4 ca = projectionMatrix * a;
  vec4 cb = projectionMatrix * b;
  vec2 sa = ca.xy / ca.w * uViewport * 0.5;
  vec2 sb = cb.xy / cb.w * uViewport * 0.5;
  vec2 dir = sb - sa;
  float l = length(dir);
  dir = l > 1e-4 ? dir / l : vec2(1.0, 0.0);
  vec2 nrm = vec2(-dir.y, dir.x) * aSide;
  ca.xy += nrm * (aWidth * 0.5) / (uViewport * 0.5) * ca.w;
  gl_Position = ca;
}
`;
const AIR_FRAG = /* glsl */`
precision highp float;
uniform float uOpacity;
uniform float uDash;
varying vec4 vColor;
varying float vLen;
void main() {
  if (vColor.a > 0.5 && mod(vLen, uDash) > uDash * 0.55) discard;
  gl_FragColor = vec4(vColor.rgb, 1.0) * uOpacity;
}
`;

const BUILDING_VERT = /* glsl */`
uniform float uExag;
varying vec3 vN;
varying float vDist;
varying float vUp;
void main() {
  vN = normalize(vec3(normal.x * uExag, normal.y, normal.z * uExag));
  vUp = normal.y;
  vec4 mv = modelViewMatrix * vec4(position, 1.0);
  vDist = length(mv.xyz);
  gl_Position = projectionMatrix * mv;
}
`;
const BUILDING_FRAG = /* glsl */`
precision highp float;
varying vec3 vN;
varying float vDist;
varying float vUp;
${GLSL_FOG}
${GLSL_LIGHT}
void main() {
  vec3 n = normalize(vN);
  if (!gl_FrontFacing) n = -n;
  vec3 col = mix(vec3(0.52, 0.55, 0.58), vec3(0.66, 0.68, 0.70), step(0.5, vUp)) * lightAt(n);
  gl_FragColor = vec4(mix(col, uFogCol, fogAmount(vDist)), 1.0);
}
`;

/* ------------------------------------------------------------------ */
/* Géométries                                                          */
/* ------------------------------------------------------------------ */

function gridNormals(grid, out, offset) {
  const m = grid.m, n = grid.n, d = grid.disp, step = grid.step;
  for (let j = 0; j < m; j++) {
    for (let i = 0; i < m; i++) {
      const i0 = i > 0 ? i - 1 : i, i1 = i < n ? i + 1 : i;
      const j0 = j > 0 ? j - 1 : j, j1 = j < n ? j + 1 : j;
      const dzdx = (d[j * m + i1] - d[j * m + i0]) / ((i1 - i0) * step);
      const dzdy = (d[j1 * m + i] - d[j0 * m + i]) / ((j1 - j0) * step);
      const inv = 1 / Math.hypot(dzdx, 1, dzdy);
      const k = (offset + j * m + i) * 3;
      out[k] = -dzdx * inv;
      out[k + 1] = inv;
      out[k + 2] = dzdy * inv;     // Z scène = -y monde
    }
  }
}

// Indices du périmètre d'une grille (m x m), dans l'ordre, sans répéter le premier.
function perimeter(m) {
  const n = m - 1, out = [];
  for (let i = 0; i < n; i++) out.push(i);
  for (let j = 0; j < n; j++) out.push(j * m + n);
  for (let i = n; i > 0; i--) out.push(n * m + i);
  for (let j = n; j > 0; j--) out.push(j * m);
  return out;
}

// Maillage d'une grille + anneaux de jupe. rings = [(indice du bord, sortie x/y/z/h) => void, ...].
function buildGridGeometry(grid, x0, y0, origin, rings) {
  const m = grid.m, n = grid.n, step = grid.step;
  const per = perimeter(m);
  const base = m * m;
  const total = base + per.length * rings.length;
  const pos = new Float32Array(total * 3), nor = new Float32Array(total * 3), aH = new Float32Array(total);
  for (let j = 0; j < m; j++) {
    for (let i = 0; i < m; i++) {
      const v = j * m + i;
      pos[v * 3] = x0 + i * step - origin.x;
      pos[v * 3 + 1] = grid.disp[v];
      pos[v * 3 + 2] = origin.y - (y0 + j * step);
      aH[v] = grid.raw[v];
    }
  }
  gridNormals(grid, nor, 0);
  const surfaceCount = n * n * 6;
  const idx = new Uint32Array(surfaceCount + per.length * rings.length * 6);
  let k = 0;
  for (let j = 0; j < n; j++) {
    for (let i = 0; i < n; i++) {
      const a = j * m + i, b = a + 1, c = a + m, d = c + 1;
      idx[k++] = a; idx[k++] = b; idx[k++] = d;
      idx[k++] = a; idx[k++] = d; idx[k++] = c;
    }
  }
  const tmp = { x: 0, y: 0, z: 0, h: 0 };
  let prev = per;
  for (let r = 0; r < rings.length; r++) {
    const start = base + r * per.length;
    const cur = new Array(per.length);
    for (let p = 0; p < per.length; p++) {
      const src = per[p], v = start + p;
      tmp.x = pos[src * 3]; tmp.y = pos[src * 3 + 1]; tmp.z = pos[src * 3 + 2]; tmp.h = aH[src];
      rings[r](tmp);
      pos[v * 3] = tmp.x; pos[v * 3 + 1] = tmp.y; pos[v * 3 + 2] = tmp.z;
      aH[v] = tmp.h;
      nor[v * 3] = 0; nor[v * 3 + 1] = 1; nor[v * 3 + 2] = 0;
      cur[p] = v;
    }
    for (let p = 0; p < per.length; p++) {
      const q = (p + 1) % per.length;
      idx[k++] = prev[p]; idx[k++] = cur[p]; idx[k++] = cur[q];
      idx[k++] = prev[p]; idx[k++] = cur[q]; idx[k++] = prev[q];
    }
    prev = cur;
  }
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  geo.setAttribute('normal', new THREE.BufferAttribute(nor, 3));
  geo.setAttribute('aH', new THREE.BufferAttribute(aH, 1));
  geo.setIndex(new THREE.BufferAttribute(idx, 1));
  geo.computeBoundingSphere();
  return { geo, surfaceCount };
}

function cssColor(el, name, fallback) {
  try {
    const v = getComputedStyle(el).getPropertyValue(name).trim();
    if (v) return v;
  } catch (e) { /* élément détaché */ }
  return fallback;
}
function rgb01(css, fallback) {
  const c = new THREE.Color();
  try { c.set(css); } catch (e) { c.set(fallback); }
  // THREE.Color convertit en linéaire : on revient au sRGB, nos shaders écrivent les couleurs telles quelles.
  const out = { r: 0, g: 0, b: 0 };
  c.getRGB(out, THREE.SRGBColorSpace);
  return [out.r, out.g, out.b];
}
function pow2ceil(v) { let p = 1; while (p < v) p *= 2; return p; }

/* ------------------------------------------------------------------ */
/* createPlan3D                                                        */
/* ------------------------------------------------------------------ */

export function createPlan3D(container, opts) {
  opts = opts || {};
  if (!container || !isWebGLAvailable()) throw new Error('webgl_unavailable');

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'high-performance' });
  } catch (e) {
    throw new Error('webgl_unavailable');
  }
  if (!renderer.getContext()) throw new Error('webgl_unavailable');

  const worldSize = opts.worldSize > 0 ? Number(opts.worldSize)
    : (opts.dem && opts.dem.worldSize > 0 ? Number(opts.dem.worldSize) : 8192);
  const origin = { x: worldSize / 2, y: worldSize / 2 };
  const DIST_MAX = worldSize * 1.7;

  let disposed = false;
  let contextLost = false;
  let exag = clamp(Number(opts.exaggeration) || 1.5, 0.2, 8);
  let sunHour = isFinite(Number(opts.sunHour)) && opts.sunHour !== null && opts.sunHour !== undefined ? Number(opts.sunHour) : 10;
  let cssW = 1, cssH = 1, ratio = 1;

  /* ---------- DOM ---------- */
  const canvas = renderer.domElement;
  canvas.style.cssText = 'position:absolute;left:0;top:0;width:100%;height:100%;display:block;touch-action:none;outline:none;cursor:grab;';
  container.appendChild(canvas);
  const hadTabIndex = container.hasAttribute('tabindex');
  if (!hadTabIndex) container.setAttribute('tabindex', '0');

  const compass = document.createElement('button');
  compass.type = 'button';
  compass.setAttribute('aria-label', 'N');
  compass.style.cssText = 'position:absolute;top:12px;right:12px;width:46px;height:46px;padding:0;margin:0;border-radius:50%;'
    + 'border:1px solid var(--toc-lineStrong,#3a4a57);background:var(--toc-bg1,#0d1318);color:var(--toc-text,#e8eef2);'
    + 'cursor:pointer;z-index:2;display:block;box-shadow:0 2px 10px rgba(0,0,0,.45);touch-action:manipulation;';
  compass.innerHTML = '<svg viewBox="-23 -23 46 46" width="44" height="44" style="display:block;margin:0 auto;pointer-events:none" aria-hidden="true">'
    + '<g data-rose>'
    + '<circle r="19.5" fill="none" stroke="var(--toc-lineStrong,#3a4a57)" stroke-width="1"/>'
    + '<path d="M-19.5 0 h3.5 M16 0 h3.5 M0 16 v3.5" stroke="var(--toc-text,#e8eef2)" stroke-opacity=".5" stroke-width="1.2"/>'
    + '<path d="M0 -9.5 L4.4 3 L0 0.6 L-4.4 3 Z" fill="var(--toc-accent,#34d399)"/>'
    + '<path d="M0 12 L3.4 3.6 L0 5.4 L-3.4 3.6 Z" fill="var(--toc-lineStrong,#3a4a57)"/>'
    + '<text x="0" y="-11.4" text-anchor="middle" font-family="system-ui,Segoe UI,sans-serif" font-size="8" font-weight="700" fill="var(--toc-text,#e8eef2)">N</text>'
    + '</g></svg>';
  container.appendChild(compass);
  const rose = compass.querySelector('[data-rose]');

  /* ---------- Scène ---------- */
  renderer.setClearColor(new THREE.Color().setRGB(SKY_TOP[0], SKY_TOP[1], SKY_TOP[2], THREE.SRGBColorSpace), 1);
  renderer.sortObjects = true;
  const maxTex = renderer.capabilities.maxTextureSize || 2048;
  const maxAniso = Math.min(8, renderer.capabilities.getMaxAnisotropy ? renderer.capabilities.getMaxAnisotropy() : 1);

  const scene = new THREE.Scene();
  const root = new THREE.Group();           // tout ce qui est en mètres : l'exagération est son échelle Y
  root.scale.set(1, exag, 1);
  scene.add(root);
  const camera = new THREE.PerspectiveCamera(FOV, 1, 1, 100000);
  const goalCamera = new THREE.PerspectiveCamera(FOV, 1, 1, 100000);   // caméra « cible » pour les gestes

  // Uniformes partagés (mêmes objets dans tous les matériaux).
  const U = {
    uOrigin: { value: new THREE.Vector2(origin.x, origin.y) },
    uExag: { value: exag },
    uFogCol: { value: new THREE.Vector3(SKY_HORIZON[0], SKY_HORIZON[1], SKY_HORIZON[2]) },
    uFog: { value: new THREE.Vector2(1000, 50000) },
    uSunDir: { value: new THREE.Vector3(0, 1, 0) },
    uSunCol: { value: new THREE.Vector3(1, 1, 1) },
    uSky: { value: new THREE.Vector3(0.5, 0.5, 0.5) },
    uGround: { value: new THREE.Vector3(0.3, 0.3, 0.3) },
    uAoi: { value: new THREE.Vector4(0, 0, worldSize, worldSize) },
    uHmax: { value: 300 },
    uContour: { value: 20 },
    uFlat: { value: 0 },
    uViewport: { value: new THREE.Vector2(1, 1) },
    uRatio: { value: 1 },
    uK: { value: 0.001 },
    uNear: { value: 1 },
    uDash: { value: 50 },
    uAccent: { value: new THREE.Vector3(0.204, 0.827, 0.6) },
  };
  let accentCss = '#34d399', textCss = '#e8eef2';
  function readTheme() {
    accentCss = cssColor(container, '--toc-accent', '#34d399');
    textCss = cssColor(container, '--toc-text', '#e8eef2');
    const a = rgb01(accentCss, '#34d399');
    U.uAccent.value.set(a[0], a[1], a[2]);
  }
  readTheme();

  // Ciel : sphère centrée sur la caméra, dégradé du zénith sombre vers l'horizon.
  const skyMat = new THREE.ShaderMaterial({
    vertexShader: SKY_VERT, fragmentShader: SKY_FRAG, side: THREE.BackSide, depthTest: false, depthWrite: false,
    uniforms: { uTop: { value: new THREE.Vector3(SKY_TOP[0], SKY_TOP[1], SKY_TOP[2]) }, uFogCol: U.uFogCol },
  });
  const sky = new THREE.Mesh(new THREE.SphereGeometry(1, 24, 12), skyMat);
  sky.renderOrder = -100;
  sky.frustumCulled = false;
  scene.add(sky);

  /* ---------- Terrain ---------- */
  const blankTex = new THREE.DataTexture(new Uint8Array([0, 0, 0, 0]), 1, 1);
  blankTex.needsUpdate = true;

  function terrainMaterial(coarseFlag) {
    return new THREE.ShaderMaterial({
      vertexShader: TERRAIN_VERT, fragmentShader: TERRAIN_FRAG, side: THREE.DoubleSide,
      extensions: { derivatives: true },
      uniforms: {
        uOrigin: U.uOrigin, uExag: U.uExag, uFogCol: U.uFogCol, uFog: U.uFog,
        uSunDir: U.uSunDir, uSunCol: U.uSunCol, uSky: U.uSky, uGround: U.uGround,
        uAoi: U.uAoi, uHmax: U.uHmax, uContour: U.uContour, uFlat: U.uFlat,
        uCoarse: { value: coarseFlag ? 1 : 0 },
        uPush: { value: coarseFlag ? 1.0008 : 1.0 },   // le maillage grossier passe derrière le fin là où ils se recouvrent
        uInset: { value: 1 },
        uDimBand: { value: 500 },
        uBase: { value: blankTex },
        uHasBase: { value: 0 },
        uBaseRect: { value: new THREE.Vector4(0, 0, 1, 1) },
      },
    });
  }
  const coarseMat = terrainMaterial(true);
  const fineMat = terrainMaterial(false);
  const overlayCanvas = document.createElement('canvas');
  overlayCanvas.width = overlayCanvas.height = 2;
  let overlayTex = null;
  const overlayMat = new THREE.ShaderMaterial({
    vertexShader: OVERLAY_VERT, fragmentShader: OVERLAY_FRAG, transparent: true, depthWrite: false,
    blending: THREE.CustomBlending, blendSrc: THREE.OneFactor, blendDst: THREE.OneMinusSrcAlphaFactor,
    blendSrcAlpha: THREE.OneFactor, blendDstAlpha: THREE.OneMinusSrcAlphaFactor,
    uniforms: {
      uOrigin: U.uOrigin, uFogCol: U.uFogCol, uFog: U.uFog,
      uRect: { value: new THREE.Vector4(0, 0, 1, 1) }, uMap: { value: blankTex }, uOpacity: { value: 1 },
    },
  });

  let sampler = makeDemSampler(opts.dem || null);
  let aoiInput = opts.aoi || null;
  let coarse = null, fine = null, surface = null, sq = null;
  let coarseMesh = null, fineMesh = null, overlayMesh = null;
  let overlayDirty = true;
  let overlaySize = 0;
  let baseToken = 0;
  const baseTex = { fine: null, coarse: null };

  function disposeMesh(mesh) {
    if (!mesh) return;
    root.remove(mesh);
    if (mesh.geometry) mesh.geometry.dispose();
  }

  function buildCoarse() {
    disposeMesh(coarseMesh);
    coarse = buildCoarseGrid(sampler, worldSize);
    const far = 9;      // la mer continue bien au-delà du terrain
    const { geo } = buildGridGeometry(coarse, 0, 0, origin, [
      (v) => { v.y = 0; v.h = Math.min(v.h, -1); },                       // falaise du bord jusqu'au niveau de la mer
      (v) => { v.x *= far; v.z *= far; v.y = 0; v.h = -90; },             // mer plate jusqu'à l'horizon
    ]);
    coarseMesh = new THREE.Mesh(geo, coarseMat);
    coarseMesh.frustumCulled = false;
    coarseMesh.renderOrder = 1;
    root.add(coarseMesh);
  }

  function buildFine() {
    disposeMesh(fineMesh);
    if (overlayMesh) { root.remove(overlayMesh); overlayMesh.geometry.dispose(); overlayMesh = null; }
    sq = squareAoi(aoiInput, sampler, worldSize);
    fine = buildFineGrid(sampler, coarse, sq);
    surface = makeSurface(coarse, fine);
    // Pas de jupe : le bord coïncide avec la surface grossière, qui continue un peu sous le maillage fin.
    const { geo, surfaceCount } = buildGridGeometry(fine, sq.x0, sq.y0, origin, []);
    fineMesh = new THREE.Mesh(geo, fineMat);
    fineMesh.renderOrder = 2;
    root.add(fineMesh);

    // Le calque drapé réutilise les mêmes sommets, sans la jupe.
    const og = new THREE.BufferGeometry();
    og.setAttribute('position', geo.getAttribute('position'));
    og.setIndex(new THREE.BufferAttribute(geo.index.array.subarray(0, surfaceCount), 1));
    og.boundingSphere = geo.boundingSphere.clone();
    overlayMesh = new THREE.Mesh(og, overlayMat);
    overlayMesh.renderOrder = 5;
    root.add(overlayMesh);

    U.uAoi.value.set(sq.x0, sq.y0, sq.x1, sq.y1);
    coarseMat.uniforms.uInset.value = Math.min(sq.step * 0.5, 6);
    coarseMat.uniforms.uDimBand.value = Math.max(150, sq.size * 0.1);
    overlayMat.uniforms.uRect.value.set(sq.x0, sq.y0, sq.size, sq.size);
    U.uHmax.value = Math.max(150, sampler.maxZ);
    U.uFlat.value = sampler.valid ? 0 : 1;
    U.uContour.value = sampler.maxZ < 120 ? 5 : (sampler.maxZ < 700 ? 10 : 20);

    // Taille de la texture du calque : environ 1,5 m par pixel, entre 1024 et 4096.
    const cap = Math.min(4096, maxTex, opts.overlayMaxSize > 0 ? opts.overlayMaxSize : 4096);
    const want = clamp(pow2ceil(sq.size / 1.5), 1024, cap);
    if (want !== overlaySize) {
      overlaySize = want;
      overlayCanvas.width = overlayCanvas.height = want;
      if (overlayTex) overlayTex.dispose();
      overlayTex = new THREE.CanvasTexture(overlayCanvas);
      overlayTex.premultiplyAlpha = true;
      overlayTex.generateMipmaps = true;
      overlayTex.minFilter = THREE.LinearMipmapLinearFilter;
      overlayTex.magFilter = THREE.LinearFilter;
      overlayTex.anisotropy = maxAniso;
      overlayMat.uniforms.uMap.value = overlayTex;
    }
    overlayDirty = true;
  }

  function aoiBounds() { return { x0: sq.x0, y0: sq.y0, x1: sq.x1, y1: sq.y1 }; }

  function redrawOverlay() {
    overlayDirty = false;
    const ctx = overlayCanvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, overlaySize, overlaySize);
    if (typeof opts.drawOverlay === 'function') {
      ctx.save();
      try { opts.drawOverlay(ctx, aoiBounds(), overlaySize); } catch (e) { console.error('[plan-3d] drawOverlay', e); }
      ctx.restore();
    }
    if (overlayTex) overlayTex.needsUpdate = true;
  }

  // Fond (satellite ou carte) fourni par l'éditeur ; sinon teinte hypsométrique du shader.
  function applyBase(which, cnv, rect) {
    const mat = which === 'fine' ? fineMat : coarseMat;
    if (baseTex[which]) { baseTex[which].dispose(); baseTex[which] = null; }
    if (cnv && cnv.width > 0 && cnv.height > 0) {
      const t = new THREE.CanvasTexture(cnv);
      t.generateMipmaps = true;
      t.minFilter = THREE.LinearMipmapLinearFilter;
      t.magFilter = THREE.LinearFilter;
      t.anisotropy = maxAniso;
      baseTex[which] = t;
      mat.uniforms.uBase.value = t;
      mat.uniforms.uBaseRect.value.set(rect.x0, rect.y0, rect.x1 - rect.x0, rect.y1 - rect.y0);
      mat.uniforms.uHasBase.value = 1;
    } else {
      mat.uniforms.uBase.value = blankTex;
      mat.uniforms.uHasBase.value = 0;
    }
    invalidate();
  }
  function requestBase() {
    const token = ++baseToken;
    if (typeof opts.getBaseCanvas !== 'function') {
      applyBase('fine', null);
      applyBase('coarse', null);
      return;
    }
    const jobs = [
      ['fine', aoiBounds(), Math.min(overlaySize, maxTex)],
      ['coarse', { x0: 0, y0: 0, x1: worldSize, y1: worldSize }, Math.min(2048, maxTex)],
    ];
    jobs.forEach(([which, rect, size]) => {
      let p;
      try { p = Promise.resolve(opts.getBaseCanvas(rect, size)); } catch (e) { p = Promise.resolve(null); }
      p.then((cnv) => cnv, () => null).then((cnv) => {
        if (disposed || token !== baseToken) return;
        applyBase(which, cnv || null, rect);
      });
    });
  }

  /* ---------- Soleil ---------- */
  function applySun() {
    const s = sunDirection(sunHour);
    const day = smoothstep(-0.06, 0.16, s.y);          // 0 la nuit, 1 de jour
    const v = new THREE.Vector3(s.x, Math.max(s.y, 0.06), s.z).normalize();
    U.uSunDir.value.copy(v);
    const warm = smoothstep(0.0, 0.5, s.y);
    U.uSunCol.value.set(lerp(1.0, 0.98, warm), lerp(0.72, 0.94, warm), lerp(0.46, 0.84, warm)).multiplyScalar(0.92 * day);
    U.uSky.value.set(lerp(0.17, 0.50, day), lerp(0.22, 0.54, day), lerp(0.34, 0.60, day));
    U.uGround.value.set(lerp(0.07, 0.27, day), lerp(0.09, 0.26, day), lerp(0.14, 0.25, day));
  }

  /* ---------- Caméra ---------- */
  const cur = { x: worldSize / 2, y: worldSize / 2, dist: worldSize, bearing: 0, pitch: 55, tz: 0 };
  const goal = { x: cur.x, y: cur.y, dist: cur.dist, bearing: 0, pitch: 55 };
  let animating = false;
  let slowUntil = 0;

  function clampGoal() {
    goal.x = clamp(goal.x, 0, worldSize);
    goal.y = clamp(goal.y, 0, worldSize);
    goal.dist = clamp(goal.dist, DIST_MIN, DIST_MAX);
    goal.pitch = clamp(goal.pitch, PITCH_MIN, PITCH_MAX);
  }

  function poseCamera(cam, s, tz) {
    const p = cameraPosition(s, tz);
    // Jamais sous le sol.
    const floor = surface.z(p[0], p[1]) * exag + Math.max(2, s.dist * 0.02);
    if (p[2] < floor) p[2] = floor;
    cam.position.set(p[0] - origin.x, p[2], origin.y - p[1]);
    cam.up.set(0, 1, 0);
    cam.lookAt(s.x - origin.x, tz, origin.y - s.y);
    cam.updateMatrixWorld(true);
  }

  function updateCamera() {
    const near = Math.max(0.5, cur.dist * 0.03);
    const fogEnd = Math.min(cur.dist * 9 + 22000, worldSize * 14);
    const far = Math.max(fogEnd * 1.05, cur.dist * 3);
    camera.near = near;
    camera.far = far;
    camera.updateProjectionMatrix();
    poseCamera(camera, cur, cur.tz);
    U.uNear.value = near;
    U.uFog.value.set(cur.dist * 1.6 + 2500, fogEnd);
    U.uDash.value = Math.max(4, cur.dist * 0.022);
    sky.position.copy(camera.position);
    sky.scale.setScalar(far * 0.9);
    rose.setAttribute('transform', 'rotate(' + (-cur.bearing).toFixed(2) + ')');
  }
  function updateGoalCamera() {
    goalCamera.near = camera.near;
    goalCamera.far = camera.far;
    goalCamera.aspect = camera.aspect;
    goalCamera.updateProjectionMatrix();
    poseCamera(goalCamera, goal, surface.z(goal.x, goal.y) * exag);
  }

  // Amortissement : l'état courant rejoint l'état visé. Renvoie true tant que ça bouge.
  function stepCamera(dt) {
    const rate = performance.now() < slowUntil ? 7 : 16;
    const k = 1 - Math.exp(-dt * rate);
    const gtz = surface.z(goal.x, goal.y) * exag;
    cur.x = lerp(cur.x, goal.x, k);
    cur.y = lerp(cur.y, goal.y, k);
    cur.dist = Math.exp(lerp(Math.log(cur.dist), Math.log(goal.dist), k));
    cur.bearing = lerp(cur.bearing, goal.bearing, k);
    cur.pitch = lerp(cur.pitch, goal.pitch, k);
    cur.tz = lerp(cur.tz, gtz, k);
    const eps = cur.dist * 0.0004;
    const moving = Math.abs(cur.x - goal.x) > eps || Math.abs(cur.y - goal.y) > eps
      || Math.abs(Math.log(cur.dist / goal.dist)) > 0.0004
      || Math.abs(cur.bearing - goal.bearing) > 0.02 || Math.abs(cur.pitch - goal.pitch) > 0.02
      || Math.abs(cur.tz - gtz) > Math.max(0.05, eps);
    if (!moving) snapCamera();
    return moving;
  }
  function snapCamera() {
    // Cap ramené dans [0, 360[ une fois l'animation terminée.
    goal.bearing = ((goal.bearing % 360) + 360) % 360;
    cur.x = goal.x; cur.y = goal.y; cur.dist = goal.dist; cur.bearing = goal.bearing; cur.pitch = goal.pitch;
    cur.tz = surface.z(goal.x, goal.y) * exag;
  }

  function viewOf(s) {
    return {
      x: s.x, y: s.y, mpp: distToMpp(s.dist, cssH),
      bearing: ((s.bearing % 360) + 360) % 360, pitch: s.pitch,
    };
  }

  /* ---------- Boucle de rendu à la demande ---------- */
  let raf = 0;
  let dirty = true;
  let lastFrame = 0;
  let fps = 0;
  let lastRender = 0;
  let lastViewEmit = 0;
  let viewPending = false;
  let viewTimer = 0;
  const info = { triangles: 0, calls: 0 };

  function invalidate() {
    dirty = true;
    if (!raf && !disposed && !contextLost) raf = requestAnimationFrame(frame);
  }

  function emitView(force) {
    if (typeof opts.onView !== 'function') return;
    const now = performance.now();
    if (!force && now - lastViewEmit < 110) {
      if (!viewTimer) viewTimer = setTimeout(() => { viewTimer = 0; if (!disposed && viewPending) emitView(true); }, 130);
      return;
    }
    lastViewEmit = now;
    viewPending = false;
    try { opts.onView(viewOf(cur)); } catch (e) { console.error('[plan-3d] onView', e); }
  }

  function renderNow() {
    if (disposed || contextLost) return;
    if (overlayDirty) redrawOverlay();
    updateCamera();
    renderer.render(scene, camera);
    // Cadence : moyenne glissante sur les images consécutives (pas de mesure au repos).
    const t = performance.now();
    if (lastRender && t - lastRender < 250 && t > lastRender) {
      const inst = 1000 / (t - lastRender);
      fps = fps ? fps * 0.85 + inst * 0.15 : inst;
    }
    lastRender = t;
    info.triangles = renderer.info.render.triangles;
    info.calls = renderer.info.render.calls;
    dirty = false;
  }

  function frame(now) {
    raf = 0;
    if (disposed || contextLost) return;
    const dt = lastFrame ? Math.min(0.1, (now - lastFrame) / 1000) : 1 / 60;
    lastFrame = now;
    const wasMoving = animating;
    if (animating) animating = stepCamera(dt);
    if (wasMoving) viewPending = true;
    renderNow();
    if (viewPending) emitView(!animating);
    if (animating || dirty) raf = requestAnimationFrame(frame);
    else lastFrame = 0;                       // repos : plus aucune image tant que rien ne change
  }

  function moveCamera(slow) {
    clampGoal();
    if (slow) slowUntil = performance.now() + 900;
    animating = true;
    invalidate();
  }

  /* ---------- Rayons et sélection ---------- */
  const ndc = new THREE.Vector3();
  const tmpV = new THREE.Vector3();
  const tmpUp = new THREE.Vector3();

  function clientToLocal(clientX, clientY) {
    const r = canvas.getBoundingClientRect();
    return [clientX - r.left, clientY - r.top, r.width || 1, r.height || 1];
  }
  function rayFrom(cam, clientX, clientY) {
    const l = clientToLocal(clientX, clientY);
    ndc.set((l[0] / l[2]) * 2 - 1, 1 - (l[1] / l[3]) * 2, 0.5).unproject(cam);
    const o = cam.position;
    const w = sceneToWorld(o.x, o.y, o.z, origin);
    const d = [ndc.x - o.x, -(ndc.z - o.z), ndc.y - o.y];
    return { o: [w.x, w.y, w.h], d };
  }
  function pickTerrain(cam, clientX, clientY) {
    if (dirty && cam === camera) updateCamera();
    const r = rayFrom(cam, clientX, clientY);
    return raycastSurface(surface, r.o, r.d, exag, cam.far * 1.2);
  }
  // Point du plan horizontal d'altitude exagérée h sous le pointeur (pour le glisser de la carte).
  function pickPlane(cam, clientX, clientY, h) {
    const r = rayFrom(cam, clientX, clientY);
    const len = Math.hypot(r.d[0], r.d[1], r.d[2]);
    if (!(r.d[2] / len < -0.035)) return null;
    const t = (h - r.o[2]) / r.d[2];
    return t > 0 ? [r.o[0] + r.d[0] * t, r.o[1] + r.d[1] * t] : null;
  }

  /* ---------- Symboles ---------- */
  const quadGeo = new THREE.BufferGeometry();
  quadGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array([0, 0, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0]), 3));
  quadGeo.setAttribute('uv', new THREE.BufferAttribute(new Float32Array([0, 0, 1, 0, 1, 1, 0, 1]), 2));
  quadGeo.setIndex([0, 1, 2, 0, 2, 3]);

  const spriteGroup = new THREE.Group();
  root.add(spriteGroup);
  let spriteList = [];
  let texCache = new Map();        // canvas => { tex, sel (texture de la version surlignée), selCanvas }
  let stalkLines = null, stalkGhost = null, dotPoints = null;

  const premultBlend = {
    transparent: true, depthWrite: false, blending: THREE.CustomBlending,
    blendSrc: THREE.OneFactor, blendDst: THREE.OneMinusSrcAlphaFactor,
    blendSrcAlpha: THREE.OneFactor, blendDstAlpha: THREE.OneMinusSrcAlphaFactor,
  };
  function lineMaterial(vs, fs, opacity, ghost) {
    return new THREE.ShaderMaterial(Object.assign({
      vertexShader: vs, fragmentShader: fs,
      depthFunc: ghost ? THREE.GreaterDepth : THREE.LessEqualDepth,
      uniforms: {
        uK: U.uK, uRatio: U.uRatio, uViewport: U.uViewport, uNear: U.uNear, uDash: U.uDash, uAccent: U.uAccent,
        uOpacity: { value: opacity },
      },
    }, premultBlend));
  }
  const stalkMat = lineMaterial(STALK_VERT, STALK_FRAG, 1, false);
  const stalkGhostMat = lineMaterial(STALK_VERT, STALK_FRAG, 0.3, true);
  const dotMat = lineMaterial(DOT_VERT, DOT_FRAG, 1, false);
  const sharedMats = new Set([stalkMat, stalkGhostMat, dotMat]);     // libérés seulement dans dispose()

  function spriteTexture(cnv) {
    const t = new THREE.CanvasTexture(cnv);
    t.premultiplyAlpha = true;
    t.generateMipmaps = false;
    t.minFilter = THREE.LinearFilter;
    t.magFilter = THREE.LinearFilter;
    return t;
  }
  // Version surlignée : halo de la couleur d'accent qui suit la silhouette du symbole.
  const SEL_PAD = 10;
  function selectedCanvas(cnv) {
    const c = document.createElement('canvas');
    c.width = cnv.width + SEL_PAD * 2;
    c.height = cnv.height + SEL_PAD * 2;
    const ctx = c.getContext('2d');
    ctx.shadowColor = accentCss;
    ctx.shadowBlur = 8;
    for (let i = 0; i < 3; i++) ctx.drawImage(cnv, SEL_PAD, SEL_PAD);
    ctx.shadowBlur = 2.5;                       // liseré net au ras de la silhouette
    for (let i = 0; i < 4; i++) ctx.drawImage(cnv, SEL_PAD, SEL_PAD);
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;
    ctx.drawImage(cnv, SEL_PAD, SEL_PAD);
    return c;
  }
  function spriteMaterial(tex, w, h, ax, ay, stalkPx, opacity, ghost) {
    return new THREE.ShaderMaterial(Object.assign({
      vertexShader: SPRITE_VERT, fragmentShader: SPRITE_FRAG,
      depthFunc: ghost ? THREE.GreaterDepth : THREE.LessEqualDepth,
      uniforms: {
        uViewport: U.uViewport, uRatio: U.uRatio, uK: U.uK,
        uMap: { value: tex }, uSize: { value: new THREE.Vector2(w, h) }, uAnchor: { value: new THREE.Vector2(ax, ay) },
        uStalkPx: { value: stalkPx }, uOpacity: { value: opacity },
      },
    }, premultBlend));
  }

  function clearSpriteObjects() {
    for (let i = spriteGroup.children.length - 1; i >= 0; i--) {
      const m = spriteGroup.children[i];
      spriteGroup.remove(m);
      if (m.material && !sharedMats.has(m.material)) m.material.dispose();
      if (m.geometry && m.geometry !== quadGeo) m.geometry.dispose();
    }
    stalkLines = stalkGhost = dotPoints = null;
  }

  // Reconstruit les symboles. Une texture est conservée tant que le MÊME canvas est fourni ;
  // refreshSprites({ redraw: true }) force le renvoi de toutes les textures (canvas redessinés sur place).
  function refreshSprites(o) {
    if (disposed) return;
    const redraw = !!(o && o.redraw);
    let list = [];
    if (typeof opts.sprites === 'function') {
      try { list = opts.sprites() || []; } catch (e) { console.error('[plan-3d] sprites', e); list = []; }
    }
    clearSpriteObjects();
    const used = new Set();
    const next = [];
    const text = rgb01(textCss, '#e8eef2');
    const acc = [U.uAccent.value.x, U.uAccent.value.y, U.uAccent.value.z];
    const pos = [], top = [], col = [], dpos = [], dsel = [], dcol = [];

    for (let i = 0; i < list.length; i++) {
      const s = list[i];
      if (!s || !isFinite(s.x) || !isFinite(s.y)) continue;
      const cnv = s.canvas && s.canvas.width > 0 && s.canvas.height > 0 ? s.canvas : null;
      const r = s.ratio > 0 ? s.ratio : 1;                     // pixels du canvas par pixel CSS (1 par défaut)
      const z = surface.z(s.x, s.y);
      const sel = !!s.selected, ghost = !!s.ghost;
      const stalkPx = s.stalk >= 0 ? Number(s.stalk) : STALK_PX;
      const alpha = ghost ? 0.5 : 1;
      const rec = {
        uid: s.uid, x: s.x, y: s.y, z, selected: sel, ghost, stalkPx,
        w: cnv ? cnv.width / r : 0, h: cnv ? cnv.height / r : 0,
        ax: (isFinite(s.anchorX) ? s.anchorX : (cnv ? cnv.width / 2 : 0)) / r,
        ay: (isFinite(s.anchorY) ? s.anchorY : (cnv ? cnv.height : 0)) / r,
      };
      next.push(rec);
      const sx = s.x - origin.x, sz = origin.y - s.y;
      const c = sel ? acc : text;
      pos.push(sx, z, sz, sx, z, sz);
      top.push(0, stalkPx);
      col.push(c[0], c[1], c[2], (sel ? 1 : 0.8) * alpha, c[0], c[1], c[2], (sel ? 1 : 0.8) * alpha);
      dpos.push(sx, z, sz);
      dsel.push(sel ? 1 : 0);
      dcol.push(text[0], text[1], text[2], alpha);

      if (!cnv) continue;
      used.add(cnv);
      let entry = texCache.get(cnv);
      if (entry && (redraw || entry.w !== cnv.width || entry.h !== cnv.height)) {
        entry.tex.dispose();
        if (entry.sel) entry.sel.dispose();
        entry = null;
      }
      if (!entry) {
        entry = { tex: spriteTexture(cnv), sel: null, w: cnv.width, h: cnv.height, accent: accentCss };
        texCache.set(cnv, entry);
      }
      let tex = entry.tex, w = rec.w, h = rec.h, ax = rec.ax, ay = rec.ay;
      if (sel) {
        if (!entry.sel || entry.accent !== accentCss) {
          if (entry.sel) entry.sel.dispose();
          entry.sel = spriteTexture(selectedCanvas(cnv));
          entry.accent = accentCss;
        }
        tex = entry.sel;
        w += (SEL_PAD * 2) / r; h += (SEL_PAD * 2) / r; ax += SEL_PAD / r; ay += SEL_PAD / r;
      }
      // Passe normale + passe « fantôme » atténuée là où le relief masque le symbole.
      const mesh = new THREE.Mesh(quadGeo, spriteMaterial(tex, w, h, ax, ay, stalkPx, alpha, false));
      const hidden = new THREE.Mesh(quadGeo, spriteMaterial(tex, w, h, ax, ay, stalkPx, alpha * 0.32, true));
      mesh.position.set(sx, z, sz);
      hidden.position.set(sx, z, sz);
      mesh.frustumCulled = hidden.frustumCulled = false;
      mesh.renderOrder = sel ? 31 : 30;
      hidden.renderOrder = 20;
      spriteGroup.add(hidden);
      spriteGroup.add(mesh);
    }

    // Textures des canvas qui ne sont plus utilisés.
    texCache.forEach((entry, cnv) => {
      if (used.has(cnv)) return;
      entry.tex.dispose();
      if (entry.sel) entry.sel.dispose();
      texCache.delete(cnv);
    });

    if (next.length) {
      const lg = new THREE.BufferGeometry();
      lg.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
      lg.setAttribute('aTop', new THREE.Float32BufferAttribute(top, 1));
      lg.setAttribute('aColor', new THREE.Float32BufferAttribute(col, 4));
      stalkLines = new THREE.LineSegments(lg, stalkMat);
      stalkGhost = new THREE.LineSegments(lg, stalkGhostMat);
      stalkLines.renderOrder = 12;
      stalkGhost.renderOrder = 11;
      const dg = new THREE.BufferGeometry();
      dg.setAttribute('position', new THREE.Float32BufferAttribute(dpos, 3));
      dg.setAttribute('aSel', new THREE.Float32BufferAttribute(dsel, 1));
      dg.setAttribute('aColor', new THREE.Float32BufferAttribute(dcol, 4));
      dotPoints = new THREE.Points(dg, dotMat);
      dotPoints.renderOrder = 13;
      // Rangés dans le groupe des symboles pour être libérés avec eux (leurs matériaux sont partagés).
      [stalkLines, stalkGhost, dotPoints].forEach((m) => { m.frustumCulled = false; spriteGroup.add(m); });
    }
    spriteList = next;
    invalidate();
  }

  // Position écran (pixels CSS) du point d'ancrage d'un symbole : même calcul que le shader.
  function spriteScreen(rec) {
    tmpV.set(rec.x - origin.x, rec.z * exag, origin.y - rec.y).applyMatrix4(camera.matrixWorldInverse);
    const d = -tmpV.z;
    if (d <= camera.near) return null;
    tmpUp.set(0, 1, 0).transformDirection(camera.matrixWorldInverse);
    tmpV.addScaledVector(tmpUp, rec.stalkPx * U.uK.value * d);
    tmpV.applyMatrix4(camera.projectionMatrix);
    return { x: (tmpV.x * 0.5 + 0.5) * cssW, y: (1 - (tmpV.y * 0.5 + 0.5)) * cssH, d };
  }
  function pickSprite(clientX, clientY) {
    if (dirty) updateCamera();
    const l = clientToLocal(clientX, clientY);
    let best = null, bestD = Infinity;
    for (let i = 0; i < spriteList.length; i++) {
      const rec = spriteList[i];
      if (!rec.w) continue;
      const p = spriteScreen(rec);
      if (!p) continue;
      const left = p.x - rec.ax, topY = p.y - rec.ay;
      const pad = 3;
      if (l[0] >= left - pad && l[0] <= left + rec.w + pad && l[1] >= topY - pad && l[1] <= topY + rec.h + pad) {
        const d = p.d - (rec.selected ? 1e-3 : 0);
        if (d < bestD) { bestD = d; best = rec; }
      }
    }
    return best;
  }

  /* ---------- Lignes aériennes ---------- */
  let airMesh = null, airGhost = null;
  const airMat = lineMaterial(AIR_VERT, AIR_FRAG, 1, false);
  const airGhostMat = lineMaterial(AIR_VERT, AIR_FRAG, 0.28, true);
  function setAirLines(lines) {
    if (airMesh) { root.remove(airMesh); root.remove(airGhost); airMesh.geometry.dispose(); airMesh = airGhost = null; }
    const pos = [], other = [], side = [], len = [], col = [], wid = [], idx = [];
    let v = 0;
    (Array.isArray(lines) ? lines : []).forEach((ln) => {
      if (!ln || !Array.isArray(ln.pts) || ln.pts.length < 2) return;
      const c = rgb01(ln.color || accentCss, accentCss);
      const w = clamp(Number(ln.width) || 2, 1, 16);
      const dash = ln.dashed ? 1 : 0;
      let acc = 0;
      for (let i = 0; i + 1 < ln.pts.length; i++) {
        const p = ln.pts[i], q = ln.pts[i + 1];
        if (!p || !q || !isFinite(p[0]) || !isFinite(p[1]) || !isFinite(q[0]) || !isFinite(q[1])) continue;
        const a = [p[0] - origin.x, Number(p[2]) || 0, origin.y - p[1]];
        const b = [q[0] - origin.x, Number(q[2]) || 0, origin.y - q[1]];
        const l = Math.hypot(b[0] - a[0], b[1] - a[1], b[2] - a[2]);
        // Quatre sommets : deux à chaque bout ; le côté est inversé au bout « b » car la direction l'est aussi.
        pos.push(...a, ...a, ...b, ...b);
        other.push(...b, ...b, ...a, ...a);
        side.push(1, -1, -1, 1);
        len.push(acc, acc, acc + l, acc + l);
        for (let k = 0; k < 4; k++) { col.push(c[0], c[1], c[2], dash); wid.push(w); }
        idx.push(v, v + 1, v + 2, v + 1, v + 3, v + 2);
        v += 4;
        acc += l;
      }
    });
    if (v) {
      const g = new THREE.BufferGeometry();
      g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
      g.setAttribute('aOther', new THREE.Float32BufferAttribute(other, 3));
      g.setAttribute('aSide', new THREE.Float32BufferAttribute(side, 1));
      g.setAttribute('aLen', new THREE.Float32BufferAttribute(len, 1));
      g.setAttribute('aColor', new THREE.Float32BufferAttribute(col, 4));
      g.setAttribute('aWidth', new THREE.Float32BufferAttribute(wid, 1));
      g.setIndex(idx);
      airMesh = new THREE.Mesh(g, airMat);
      airGhost = new THREE.Mesh(g, airGhostMat);
      airMat.side = airGhostMat.side = THREE.DoubleSide;
      airMesh.frustumCulled = airGhost.frustumCulled = false;
      airMesh.renderOrder = 16;
      airGhost.renderOrder = 15;
      root.add(airGhost);
      root.add(airMesh);
    }
    invalidate();
  }

  /* ---------- Bâtiments ---------- */
  let buildingMesh = null;
  let buildingSpec = [];
  const buildingMat = new THREE.ShaderMaterial({
    vertexShader: BUILDING_VERT, fragmentShader: BUILDING_FRAG, side: THREE.DoubleSide,
    uniforms: {
      uExag: U.uExag, uFogCol: U.uFogCol, uFog: U.uFog,
      uSunDir: U.uSunDir, uSunCol: U.uSunCol, uSky: U.uSky, uGround: U.uGround,
    },
  });
  function setBuildings(list) {
    buildingSpec = Array.isArray(list) ? list : [];
    if (buildingMesh) { root.remove(buildingMesh); buildingMesh.geometry.dispose(); buildingMesh = null; }
    const pos = [], nor = [], idx = [];
    let v = 0;
    buildingSpec.forEach((b) => {
      if (!b || !Array.isArray(b.poly) || b.poly.length < 3) return;
      let pts = b.poly.filter((p) => p && isFinite(p[0]) && isFinite(p[1])).map((p) => new THREE.Vector2(p[0], p[1]));
      if (pts.length > 3 && pts[0].equals(pts[pts.length - 1])) pts.pop();
      if (pts.length < 3) return;
      if (THREE.ShapeUtils.isClockWise(pts)) pts = pts.reverse();          // sens direct : l'extérieur est à droite
      let lo = Infinity, hi = -Infinity;
      pts.forEach((p) => { const g = surface.z(p.x, p.y); lo = Math.min(lo, g); hi = Math.max(hi, g); });
      const given = isFinite(b.base) && b.base !== null && b.base !== undefined;
      const z0 = (given ? Math.min(Number(b.base), lo) : lo) - 0.5;
      const z1 = (given ? Number(b.base) : hi) + Math.max(1, Number(b.height) || 3);
      for (let i = 0; i < pts.length; i++) {
        const p = pts[i], q = pts[(i + 1) % pts.length];
        const ex = q.x - p.x, ey = q.y - p.y, l = Math.hypot(ex, ey) || 1;
        const nx = ey / l, nz = ex / l;                                    // normale monde (ey, -ex) => scène (ey, 0, ex)
        pos.push(p.x - origin.x, z0, origin.y - p.y, q.x - origin.x, z0, origin.y - q.y,
          q.x - origin.x, z1, origin.y - q.y, p.x - origin.x, z1, origin.y - p.y);
        for (let k = 0; k < 4; k++) nor.push(nx, 0, nz);
        idx.push(v, v + 1, v + 2, v, v + 2, v + 3);
        v += 4;
      }
      const tris = THREE.ShapeUtils.triangulateShape(pts, []);
      pts.forEach((p) => { pos.push(p.x - origin.x, z1, origin.y - p.y); nor.push(0, 1, 0); });
      tris.forEach((t) => idx.push(v + t[0], v + t[1], v + t[2]));
      v += pts.length;
    });
    if (v) {
      const g = new THREE.BufferGeometry();
      g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
      g.setAttribute('normal', new THREE.Float32BufferAttribute(nor, 3));
      g.setIndex(idx);
      buildingMesh = new THREE.Mesh(g, buildingMat);
      buildingMesh.renderOrder = 3;
      root.add(buildingMesh);
    }
    invalidate();
  }

  /* ---------- Interactions ---------- */
  function call(name, a, b) {
    const fn = opts[name];
    if (typeof fn !== 'function') return undefined;
    try { return fn(a, b); } catch (e) { console.error('[plan-3d] ' + name, e); return undefined; }
  }
  function makeInfo(ev, clientX, clientY, withSprite) {
    const rec = withSprite ? pickSprite(clientX, clientY) : null;
    const hit = pickTerrain(camera, clientX, clientY);
    return {
      uid: rec ? rec.uid : null,
      world: hit ? [hit.x, hit.y] : null,
      z: hit ? hit.z : 0,
      originalEvent: ev, clientX, clientY,
    };
  }

  const pointers = new Map();
  let gesture = null;          // { mode: 'pending' | 'pan' | 'rotate' | 'claimed' | 'touch2', ... }
  let longPress = 0;
  let hoverXY = null, hoverQueued = false;

  function cancelLongPress() { if (longPress) { clearTimeout(longPress); longPress = 0; } }

  function startPan(clientX, clientY) {
    updateGoalCamera();
    const hit = pickTerrain(goalCamera, clientX, clientY);
    const h = hit ? hit.z * exag : surface.z(goal.x, goal.y) * exag;
    const p = hit ? [hit.x, hit.y] : pickPlane(goalCamera, clientX, clientY, h);
    return p ? { anchor: p, h } : null;
  }
  // Déplace le point visé pour que le point `anchor` (altitude exagérée h) revienne sous le pointeur.
  // Quelques itérations : la caméra suit le relief, sa hauteur change donc quand le point visé bouge.
  function anchorUnder(anchor, h, clientX, clientY) {
    let budget = goal.dist * 2;
    for (let it = 0; it < 5; it++) {
      updateGoalCamera();
      const q = pickPlane(goalCamera, clientX, clientY, h);
      if (!q) return;
      let dx = anchor[0] - q[0], dy = anchor[1] - q[1];
      const l = Math.hypot(dx, dy);
      if (l < 0.02) return;
      if (l > budget) { dx *= budget / l; dy *= budget / l; }
      budget -= Math.min(l, budget);
      goal.x = clamp(goal.x + dx, 0, worldSize);
      goal.y = clamp(goal.y + dy, 0, worldSize);
      if (budget <= 0) return;
    }
  }
  function doPan(pan, clientX, clientY) {
    if (!pan) return;
    anchorUnder(pan.anchor, pan.h, clientX, clientY);
    moveCamera(false);
  }
  // Zoom centré sur le point du terrain sous le pointeur.
  function zoomAt(factor, clientX, clientY) {
    const before = goal.dist;
    const after = clamp(before * factor, DIST_MIN, DIST_MAX);
    const f = after / before;
    if (f === 1) return;
    updateGoalCamera();
    const hit = clientX === null ? null : pickTerrain(goalCamera, clientX, clientY);
    goal.dist = after;
    if (hit) {
      goal.x = clamp(hit.x + (goal.x - hit.x) * f, 0, worldSize);
      goal.y = clamp(hit.y + (goal.y - hit.y) * f, 0, worldSize);
      anchorUnder([hit.x, hit.y], hit.z * exag, clientX, clientY);
    }
    moveCamera(false);
  }

  function touchState() {
    const p = Array.from(pointers.values());
    const dx = p[1].x - p[0].x, dy = p[1].y - p[0].y;
    return {
      cx: (p[0].x + p[1].x) / 2, cy: (p[0].y + p[1].y) / 2,
      d: Math.max(1, Math.hypot(dx, dy)), a: Math.atan2(dy, dx),
      y0: p[0].y, y1: p[1].y,
    };
  }

  function onPointerDown(ev) {
    if (disposed) return;
    if (ev.pointerType === 'mouse' && ev.button > 2) return;
    try { canvas.setPointerCapture(ev.pointerId); } catch (e) { /* pointeur déjà relâché */ }
    try { container.focus({ preventScroll: true }); } catch (e) { /* non focalisable */ }
    pointers.set(ev.pointerId, { x: ev.clientX, y: ev.clientY });
    cancelLongPress();

    if (pointers.size === 2) {
      // Deuxième doigt : le geste en cours devient un geste de caméra à deux doigts.
      if (gesture && gesture.mode === 'claimed') call('onDragEnd', Object.assign(makeInfo(ev, gesture.x, gesture.y, false), { uid: gesture.uid, cancelled: true }));
      gesture = { mode: 'touch2', last: touchState() };
      return;
    }
    if (pointers.size > 2) return;

    const rotate = ev.button === 2 || ev.button === 1 || ev.ctrlKey || ev.metaKey;
    gesture = {
      mode: 'pending', id: ev.pointerId, rotate, button: ev.button,
      x0: ev.clientX, y0: ev.clientY, x: ev.clientX, y: ev.clientY,
      slop: ev.pointerType === 'mouse' ? 4 : 9, downEvent: ev, uid: null, pan: null,
    };
    if (ev.pointerType !== 'mouse') {
      longPress = setTimeout(() => {
        longPress = 0;
        if (!gesture || gesture.mode !== 'pending' || disposed) return;
        const g = gesture;
        gesture = { mode: 'done' };
        call('onContext', makeInfo(g.downEvent, g.x0, g.y0, true));
      }, 560);
    }
    if (ev.button === 1) ev.preventDefault();
  }

  function onPointerMove(ev) {
    if (disposed) return;
    const p = pointers.get(ev.pointerId);
    if (!p) {
      // Survol sans bouton : position du curseur, au plus une fois par image.
      hoverXY = [ev.clientX, ev.clientY];
      if (!hoverQueued) {
        hoverQueued = true;
        requestAnimationFrame(() => {
          hoverQueued = false;
          if (disposed || !hoverXY || pointers.size) return;
          const rec = pickSprite(hoverXY[0], hoverXY[1]);
          canvas.style.cursor = rec ? 'pointer' : 'grab';
          const hit = pickTerrain(camera, hoverXY[0], hoverXY[1]);
          call('onCursor', hit ? [hit.x, hit.y] : null, hit ? hit.z : 0);
        });
      }
      return;
    }
    const px = p.x, py = p.y;
    p.x = ev.clientX;
    p.y = ev.clientY;
    if (!gesture) return;

    if (gesture.mode === 'touch2') {
      if (pointers.size < 2) return;
      const t = touchState(), l = gesture.last;
      // Pincement : zoom autour du milieu des doigts.
      if (Math.abs(t.d - l.d) > 0.01) zoomAt(clamp(l.d / t.d, 0.5, 2), t.cx, t.cy);
      // Torsion : rotation.
      let da = t.a - l.a;
      if (da > Math.PI) da -= 2 * Math.PI;
      if (da < -Math.PI) da += 2 * Math.PI;
      if (Math.abs(da) < 0.5) goal.bearing -= da / DEG;
      // Les deux doigts glissent verticalement dans le même sens : inclinaison.
      const d0 = t.y0 - l.y0, d1 = t.y1 - l.y1;
      if (d0 * d1 > 0 || (Math.abs(d0) + Math.abs(d1) > 0 && d0 * d1 === 0 && Math.abs(t.d - l.d) < 1)) {
        goal.pitch += ((d0 + d1) / 2) * 0.3;
      }
      gesture.last = t;
      moveCamera(false);
      return;
    }
    if (ev.pointerId !== gesture.id) return;
    gesture.x = ev.clientX;
    gesture.y = ev.clientY;

    if (gesture.mode === 'pending') {
      if (Math.hypot(ev.clientX - gesture.x0, ev.clientY - gesture.y0) < gesture.slop) return;
      cancelLongPress();
      if (gesture.rotate) {
        gesture.mode = 'rotate';
        canvas.style.cursor = 'move';
      } else {
        const start = makeInfo(gesture.downEvent, gesture.x0, gesture.y0, true);
        if (call('onDragStart', start) === true) {
          gesture.mode = 'claimed';           // l'éditeur déplace un objet : la caméra ne bouge pas
          gesture.uid = start.uid;
          canvas.style.cursor = 'grabbing';
        } else {
          gesture.mode = 'pan';
          gesture.pan = startPan(gesture.x0, gesture.y0);
          canvas.style.cursor = 'grabbing';
        }
      }
    }
    if (gesture.mode === 'claimed') {
      call('onDrag', Object.assign(makeInfo(ev, ev.clientX, ev.clientY, false), { uid: gesture.uid }));
    } else if (gesture.mode === 'pan') {
      doPan(gesture.pan, ev.clientX, ev.clientY);
    } else if (gesture.mode === 'rotate') {
      goal.bearing -= (ev.clientX - px) * 0.28;
      goal.pitch += (ev.clientY - py) * 0.22;
      moveCamera(false);
    }
  }

  function endPointer(ev, cancelled) {
    if (disposed) return;
    if (!pointers.has(ev.pointerId)) return;
    pointers.delete(ev.pointerId);
    try { canvas.releasePointerCapture(ev.pointerId); } catch (e) { /* déjà relâché */ }
    cancelLongPress();
    const g = gesture;
    if (!g) return;
    if (g.mode === 'touch2') {
      // Il reste un doigt : on ne reprend pas de glisser, il faut relever puis reposer.
      gesture = pointers.size ? { mode: 'done' } : null;
      return;
    }
    if (pointers.size) return;
    gesture = null;
    canvas.style.cursor = 'grab';
    if (g.mode === 'claimed') {
      call('onDragEnd', Object.assign(makeInfo(ev, ev.clientX, ev.clientY, false), { uid: g.uid, cancelled: !!cancelled }));
    } else if (g.mode === 'pending' && !cancelled) {
      const inf = makeInfo(ev, g.x0, g.y0, true);
      if (g.button === 2) call('onContext', inf);
      else if (!g.rotate || g.button === 0) call('onPick', inf);
    }
  }
  function onPointerUp(ev) { endPointer(ev, false); }
  function onPointerCancel(ev) { endPointer(ev, true); }
  function onPointerLeave() {
    hoverXY = null;
    if (!pointers.size) call('onCursor', null, 0);
  }
  function onWheel(ev) {
    if (disposed) return;
    ev.preventDefault();
    let dy = ev.deltaY;
    if (ev.deltaMode === 1) dy *= 32;
    else if (ev.deltaMode === 2) dy *= 400;
    dy = clamp(dy, -400, 400);
    zoomAt(Math.exp(dy * (ev.ctrlKey ? 0.006 : 0.0016)), ev.clientX, ev.clientY);
  }
  function onContextMenu(ev) { ev.preventDefault(); }

  function onKeyDown(ev) {
    if (disposed || ev.ctrlKey || ev.metaKey || ev.altKey) return;
    if (ev.target !== container && ev.target !== canvas) return;
    const b = goal.bearing * DEG;
    const stepM = goal.dist * 0.14;
    const fx = Math.sin(b), fy = Math.cos(b);          // vers l'avant
    let used = true;
    switch (ev.key) {
      case 'ArrowUp': goal.x += fx * stepM; goal.y += fy * stepM; break;
      case 'ArrowDown': goal.x -= fx * stepM; goal.y -= fy * stepM; break;
      case 'ArrowLeft': goal.x -= fy * stepM; goal.y += fx * stepM; break;
      case 'ArrowRight': goal.x += fy * stepM; goal.y -= fx * stepM; break;
      case '+': case '=': goal.dist /= 1.3; break;
      case '-': case '_': goal.dist *= 1.3; break;
      case 'q': case 'Q': goal.bearing -= 15; break;
      case 'e': case 'E': goal.bearing += 15; break;
      case 'r': case 'R': goal.pitch += 6; break;
      case 'f': case 'F': goal.pitch -= 6; break;
      case 'n': case 'N': resetNorth(); break;
      default: used = false;
    }
    if (!used) return;
    ev.preventDefault();
    moveCamera(false);
  }

  function onContextLost(ev) {
    ev.preventDefault();
    contextLost = true;
    if (raf) { cancelAnimationFrame(raf); raf = 0; }
  }
  function onContextRestored() {
    contextLost = false;
    lastFrame = 0;
    overlayDirty = true;
    invalidate();
  }
  function onCompassClick(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    resetNorth();
  }

  canvas.addEventListener('pointerdown', onPointerDown);
  canvas.addEventListener('pointermove', onPointerMove);
  canvas.addEventListener('pointerup', onPointerUp);
  canvas.addEventListener('pointercancel', onPointerCancel);
  canvas.addEventListener('pointerleave', onPointerLeave);
  canvas.addEventListener('wheel', onWheel, { passive: false });
  canvas.addEventListener('contextmenu', onContextMenu);
  canvas.addEventListener('webglcontextlost', onContextLost);
  canvas.addEventListener('webglcontextrestored', onContextRestored);
  container.addEventListener('keydown', onKeyDown);
  compass.addEventListener('click', onCompassClick);

  /* ---------- Taille ---------- */
  function resize() {
    if (disposed) return;
    const w = Math.max(1, Math.floor(container.clientWidth || 0));
    const h = Math.max(1, Math.floor(container.clientHeight || 0));
    const r = Math.min(window.devicePixelRatio || 1, 2);
    if (w === cssW && h === cssH && r === ratio) return;
    const keepMpp = cssH > 1 ? distToMpp(goal.dist, cssH) : 0;
    cssW = w; cssH = h; ratio = r;
    renderer.setPixelRatio(r);
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    U.uViewport.value.set(w, h);
    U.uRatio.value = r;
    U.uK.value = (2 * TAN_HALF) / h;
    // Même cadrage (mètres par pixel) quand le conteneur change de hauteur.
    if (keepMpp > 0) {
      goal.dist = clamp(mppToDist(keepMpp, h), DIST_MIN, DIST_MAX);
      cur.dist = goal.dist;
    }
    invalidate();
  }
  let resizeObs = null;
  if (typeof ResizeObserver === 'function') {
    resizeObs = new ResizeObserver(() => resize());
    resizeObs.observe(container);
  }
  window.addEventListener('resize', resize);

  /* ---------- API ---------- */
  function setView(v, o) {
    if (!v) return;
    if (isFinite(v.x)) goal.x = Number(v.x);
    if (isFinite(v.y)) goal.y = Number(v.y);
    if (v.mpp > 0) goal.dist = mppToDist(Number(v.mpp), cssH > 1 ? cssH : 720);
    if (isFinite(v.bearing) && v.bearing !== null) goal.bearing = Number(v.bearing);
    if (isFinite(v.pitch) && v.pitch !== null) goal.pitch = Number(v.pitch);
    clampGoal();
    if (o && o.animate) {
      // Rotation par le plus court chemin.
      let d = (goal.bearing - cur.bearing) % 360;
      if (d > 180) d -= 360;
      if (d < -180) d += 360;
      goal.bearing = cur.bearing + d;
      moveCamera(true);
    } else {
      snapCamera();
      animating = false;
      viewPending = true;
      invalidate();
    }
  }
  function getView() { return viewOf(goal); }
  function resetNorth() {
    goal.bearing = Math.round(cur.bearing / 360) * 360;      // plus court chemin vers le nord
    moveCamera(true);
  }
  function setPitch(deg) {
    if (!isFinite(deg)) return;
    goal.pitch = Number(deg);
    moveCamera(true);
  }
  function setExaggeration(n) {
    n = Number(n);
    if (!(n > 0)) return;
    exag = clamp(n, 0.2, 8);
    root.scale.set(1, exag, 1);
    U.uExag.value = exag;
    cur.tz = surface.z(cur.x, cur.y) * exag;
    invalidate();
  }
  function setSunHour(h) {
    if (!isFinite(h)) return;
    sunHour = Number(h);
    applySun();
    invalidate();
  }
  function rebuildTerrain(all) {
    if (all) buildCoarse();
    buildFine();
    cur.tz = surface.z(cur.x, cur.y) * exag;
    setBuildings(buildingSpec);
    refreshSprites();
    requestBase();
    invalidate();
  }
  function setDem(dem) {
    if (disposed) return;
    sampler = makeDemSampler(dem || null);
    rebuildTerrain(true);
  }
  function setAoi(aoi) {
    if (disposed) return;
    aoiInput = aoi || null;
    rebuildTerrain(false);
  }
  function invalidateOverlay() {
    overlayDirty = true;
    invalidate();
  }
  function heightAt(x, y) { return sampler.heightAt(Number(x), Number(y)); }
  function pickWorld(clientX, clientY) {
    if (disposed) return null;
    const hit = pickTerrain(camera, clientX, clientY);
    return hit ? [hit.x, hit.y] : null;
  }
  // Position écran (clientX, clientY) d'un point du monde ; z absolu facultatif (sinon : au sol).
  function worldToClient(x, y, z) {
    if (disposed) return null;
    if (dirty) updateCamera();
    const h = (isFinite(z) && z !== null && z !== undefined ? Number(z) : surface.z(Number(x), Number(y))) * exag;
    tmpV.set(x - origin.x, h, origin.y - y).applyMatrix4(camera.matrixWorldInverse);
    if (-tmpV.z <= camera.near) return null;
    tmpV.applyMatrix4(camera.projectionMatrix);
    const r = canvas.getBoundingClientRect();
    return [r.left + (tmpV.x * 0.5 + 0.5) * r.width, r.top + (1 - (tmpV.y * 0.5 + 0.5)) * r.height];
  }
  function stats() {
    return { fps: Math.round(fps * 10) / 10, triangles: info.triangles, drawCalls: info.calls };
  }

  function dispose() {
    if (disposed) return;
    disposed = true;
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
    cancelLongPress();
    if (viewTimer) clearTimeout(viewTimer);
    if (resizeObs) resizeObs.disconnect();
    window.removeEventListener('resize', resize);
    canvas.removeEventListener('pointerdown', onPointerDown);
    canvas.removeEventListener('pointermove', onPointerMove);
    canvas.removeEventListener('pointerup', onPointerUp);
    canvas.removeEventListener('pointercancel', onPointerCancel);
    canvas.removeEventListener('pointerleave', onPointerLeave);
    canvas.removeEventListener('wheel', onWheel);
    canvas.removeEventListener('contextmenu', onContextMenu);
    canvas.removeEventListener('webglcontextlost', onContextLost);
    canvas.removeEventListener('webglcontextrestored', onContextRestored);
    container.removeEventListener('keydown', onKeyDown);
    compass.removeEventListener('click', onCompassClick);

    clearSpriteObjects();
    texCache.forEach((e) => { e.tex.dispose(); if (e.sel) e.sel.dispose(); });
    texCache.clear();
    sharedMats.forEach((m) => m.dispose());
    [coarseMesh, fineMesh, airMesh, buildingMesh].forEach((m) => { if (m) { root.remove(m); m.geometry.dispose(); } });
    if (overlayMesh) { root.remove(overlayMesh); overlayMesh.geometry.dispose(); }
    [coarseMat, fineMat, overlayMat, skyMat, airMat, airGhostMat, buildingMat].forEach((m) => m.dispose());
    sky.geometry.dispose();
    quadGeo.dispose();
    blankTex.dispose();
    if (overlayTex) overlayTex.dispose();
    if (baseTex.fine) baseTex.fine.dispose();
    if (baseTex.coarse) baseTex.coarse.dispose();
    overlayCanvas.width = overlayCanvas.height = 1;
    try { renderer.dispose(); renderer.forceContextLoss(); } catch (e) { /* contexte déjà perdu */ }
    if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
    if (compass.parentNode) compass.parentNode.removeChild(compass);
    if (!hadTabIndex) container.removeAttribute('tabindex');
    spriteList = [];
  }

  /* ---------- Démarrage ---------- */
  applySun();
  buildCoarse();
  buildFine();
  resize();
  {
    const v = opts.view || {};
    const fit = (sq.size / Math.max(200, Math.min(cssW, cssH))) * 1.15;
    goal.x = isFinite(v.x) ? Number(v.x) : (sq.x0 + sq.x1) / 2;
    goal.y = isFinite(v.y) ? Number(v.y) : (sq.y0 + sq.y1) / 2;
    goal.dist = mppToDist(v.mpp > 0 ? Number(v.mpp) : fit, cssH > 1 ? cssH : 720);
    goal.bearing = isFinite(v.bearing) && v.bearing !== null && v.bearing !== undefined ? Number(v.bearing) : 0;
    goal.pitch = isFinite(v.pitch) && v.pitch !== null && v.pitch !== undefined ? Number(v.pitch) : 52;
    clampGoal();
    snapCamera();
  }
  refreshSprites();
  requestBase();
  invalidate();

  return {
    invalidateOverlay, refreshSprites, setDem, setAoi, setBase: requestBase,
    setExaggeration, setSunHour, setView, getView, resetNorth, setPitch,
    setAirLines, setBuildings, heightAt, pickWorld, resize, dispose, stats,
    // Compléments : bornes carrées réellement drapées, altitude de la surface affichée, rendu immédiat (tests).
    getAoiBounds: () => aoiBounds(),
    surfaceAt: (x, y) => surface.z(Number(x), Number(y)),
    worldToClient,
    renderNow: () => { renderNow(); },
    refreshTheme: () => { readTheme(); refreshSprites(); },
  };
}

export default { createPlan3D, isWebGLAvailable };
