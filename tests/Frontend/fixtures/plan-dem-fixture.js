/*
 * Relief de démonstration déterministe pour l'éditeur de plans (vue 3D et bancs d'essai).
 * Script classique : définit window.PlanDemFixture. Aucun Math.random : bruit à graine fixe.
 *
 * make(worldSize = 8192, cell = 32) renvoie un objet DEM conforme au contrat §4 :
 *   { cols, rows, cell, ox, oy, heights: Int16Array (r = 0 au SUD), missing, minZ, maxZ, worldSize, coverage }
 * Paysage : mer au sud-ouest (z <= 0), crête principale d'environ 300 m avec un col,
 * deux collines, une vallée qui descend du col vers la mer, plaine douce ailleurs.
 */
(function (root) {
  'use strict';

  // Hachage entier => [0, 1[ (graine fixe, identique sur toutes les machines).
  function hash(ix, iy, seed) {
    var h = (Math.imul(ix | 0, 374761393) + Math.imul(iy | 0, 668265263) + Math.imul(seed | 0, 1274126177)) | 0;
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    h ^= h >>> 16;
    return (h >>> 0) / 4294967296;
  }

  function smooth(t) { return t * t * (3 - 2 * t); }
  function sstep(a, b, x) {
    var t = Math.min(1, Math.max(0, (x - a) / (b - a)));
    return smooth(t);
  }

  // Bruit de valeur lissé, puis somme d'octaves.
  function vnoise(x, y, seed) {
    var ix = Math.floor(x), iy = Math.floor(y);
    var fx = smooth(x - ix), fy = smooth(y - iy);
    var a = hash(ix, iy, seed), b = hash(ix + 1, iy, seed);
    var c = hash(ix, iy + 1, seed), d = hash(ix + 1, iy + 1, seed);
    return a + (b - a) * fx + (c - a) * fy + (a - b - c + d) * fx * fy;
  }
  function fbm(x, y, seed) {
    var s = 0, amp = 0.5, f = 1;
    for (var o = 0; o < 4; o++) {
      s += amp * (vnoise(x * f, y * f, seed + o * 17) - 0.5);
      amp *= 0.5;
      f *= 2;
    }
    return s; // environ -0.5..0.5
  }

  // Distance d'un point au segment [a, b] et abscisse relative t sur ce segment.
  function segDist(px, py, ax, ay, bx, by) {
    var dx = bx - ax, dy = by - ay;
    var t = ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy);
    var tc = Math.min(1, Math.max(0, t));
    var qx = ax + dx * tc, qy = ay + dy * tc;
    return { d: Math.hypot(px - qx, py - qy), t: tc };
  }
  function gauss(d, sigma) { return Math.exp(-(d * d) / (sigma * sigma)); }

  // Altitude en mètres pour des coordonnées normalisées u (est) et v (nord) dans 0..1.
  function altitude(u, v) {
    // Plaine douce.
    var h = 26 + 14 * Math.sin(u * 5.1 + 0.4) * Math.cos(v * 4.3 - 0.8) + 10 * v;

    // Crête principale, d'ouest-nord-ouest vers l'est, avec un col vers 55 % de sa longueur.
    var r = segDist(u, v, 0.24, 0.76, 0.88, 0.60);
    var profil = 0.72 + 0.28 * Math.sin(r.t * Math.PI);          // plus haute au milieu
    var col = 1 - 0.55 * gauss(r.t - 0.55, 0.075);                // le col
    var ends = sstep(0, 0.08, r.t) * sstep(1, 0.9, r.t);          // extrémités adoucies
    h += 300 * gauss(r.d, 0.075) * profil * col * (0.35 + 0.65 * ends);

    // Contreforts : petites arêtes perpendiculaires à la crête.
    h += 38 * gauss(r.d, 0.16) * (0.5 + 0.5 * Math.sin(r.t * 38)) * ends;

    // Deux collines.
    h += 165 * gauss(Math.hypot(u - 0.63, v - 0.29), 0.075);
    h += 115 * gauss(Math.hypot(u - 0.36, v - 0.43), 0.06);

    // Vallée : part du col et descend vers la mer au sud-ouest.
    var v1 = segDist(u, v, 0.59, 0.67, 0.47, 0.40);
    var v2 = segDist(u, v, 0.47, 0.40, 0.22, 0.20);
    var val = Math.max(gauss(v1.d, 0.035), gauss(v2.d, 0.04));
    h -= Math.min(34, Math.max(0, h - 6) * 0.7) * val;          // jamais sous le niveau de la mer

    // Bruit de détail, plus marqué en altitude.
    h += fbm(u * 9, v * 9, 7) * (16 + h * 0.16);
    h += fbm(u * 34, v * 34, 23) * 5;

    // Mer au sud-ouest : côte irrégulière.
    var dc = Math.hypot(u, v) + 0.07 * fbm(u * 6 + 3, v * 6 + 1, 41) + 0.03 * Math.sin(Math.atan2(v, u) * 7);
    var mer = sstep(0.40, 0.20, dc);
    h = h * (1 - mer) - 55 * mer * (0.4 + 0.6 * sstep(0.34, 0.05, dc));
    h -= 14 * sstep(0.44, 0.30, dc) * (1 - mer);                  // plage en pente douce
    return h;
  }

  function make(worldSize, cell) {
    worldSize = worldSize > 0 ? worldSize : 8192;
    cell = cell > 0 ? cell : 32;
    var cols = Math.floor(worldSize / cell) + 1;
    var rows = cols;
    var heights = new Int16Array(cols * rows);
    var minZ = Infinity, maxZ = -Infinity;
    for (var r = 0; r < rows; r++) {
      for (var c = 0; c < cols; c++) {
        var z = Math.round(altitude((c * cell) / worldSize, (r * cell) / worldSize));
        heights[r * cols + c] = z;
        if (z < minZ) minZ = z;
        if (z > maxZ) maxZ = z;
      }
    }
    return {
      cols: cols, rows: rows, cell: cell, ox: 0, oy: 0, heights: heights,
      missing: -32768, minZ: minZ, maxZ: maxZ, worldSize: worldSize, coverage: 100
    };
  }

  // Quelques graphismes de démonstration, en fractions de la taille du monde (multiplier par worldSize).
  function samplePlan(worldSize) {
    var w = worldSize > 0 ? worldSize : 8192;
    function p(u, v) { return [u * w, v * w]; }
    return {
      // Axe d'effort qui franchit la crête par le col.
      axis: { pts: [p(0.44, 0.33), p(0.50, 0.47), p(0.575, 0.60), p(0.60, 0.74)], width: 0.022 * w, color: '#5999ff', label: 'AXE BLEU' },
      // Objectif sur la colline est.
      objective: { center: p(0.63, 0.29), radius: 0.07 * w, color: '#f24d47', label: 'OBJ FAUCON' },
      // Ligne de phase au pied de la crête.
      phaseLine: { pts: [p(0.20, 0.55), p(0.36, 0.54), p(0.52, 0.50), p(0.70, 0.46), p(0.90, 0.47)], color: '#f2f2f2', label: 'PL ACIER' },
      // Cercle de portée (appui).
      ring: { center: p(0.36, 0.43), radius: 0.16 * w, color: '#ff9433', label: 'MO 81' },
      // Teinte de type « vue dégagée » depuis un poste d'observation.
      viewshed: { center: p(0.36, 0.43), radius: 0.24 * w, bearing: 35, arc: 70, color: '#34d399' },
      units: [
        { uid: 'u1', pos: p(0.44, 0.33), sidc: 'SFGPUCI----E---', label: '1/CIE A' },
        { uid: 'u2', pos: p(0.49, 0.30), sidc: 'SFGPUCI----E---', label: '2/CIE A' },
        { uid: 'u3', pos: p(0.36, 0.43), sidc: 'SFGPUCF----D---', label: 'MO 81', selected: true },
        { uid: 'u4', pos: p(0.40, 0.27), sidc: 'SFGPUCA----F---', label: 'PC GTIA' },
        { uid: 'u5', pos: p(0.63, 0.29), sidc: 'SHGPUCI----E---', label: 'ENI 1' },
        { uid: 'u6', pos: p(0.60, 0.755), sidc: 'SHGPUCA----D---', label: 'ENI BLD' },
        { uid: 'u7', pos: p(0.585, 0.655), sidc: 'SFGPUCR----C---', label: 'ECL', ghost: true },
        { uid: 'u8', pos: p(0.74, 0.50), sidc: 'SNGPU------D---', label: 'NEUTRE' }
      ],
      // Ligne de visée : du poste d'observation vers l'objectif puis au-delà (masquée ensuite).
      los: { from: p(0.36, 0.43), mid: p(0.60, 0.305), to: p(0.80, 0.20), eye: 4 },
      buildings: [
        { poly: [p(0.455, 0.345), p(0.468, 0.345), p(0.468, 0.353), p(0.455, 0.353)], height: 18 },
        { poly: [p(0.472, 0.338), p(0.480, 0.341), p(0.477, 0.350), p(0.469, 0.347)].reverse(), height: 28 },
        { poly: [p(0.447, 0.357), p(0.452, 0.357), p(0.452, 0.368), p(0.459, 0.368), p(0.459, 0.373), p(0.447, 0.373)], height: 12 }
      ]
    };
  }

  root.PlanDemFixture = { make: make, samplePlan: samplePlan, altitude: altitude };
})(typeof window !== 'undefined' ? window : globalThis);
