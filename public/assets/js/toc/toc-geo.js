/* Poste TOC web : calculs sans navigateur (grille Arma, mesures, géométrie des éléments de plan, phases, historique,
 * fusion des changements de plusieurs éditeurs, symbole OTAN). Testé par tests/Frontend/toc-geo.test.cjs.
 * Coordonnées : mètres Arma [x, y], y vers le nord. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TocGeo = api;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function pad(n, w) {
    var s = String(Math.max(0, Math.floor(n)));
    while (s.length < w) s = '0' + s;
    return s;
  }

  /** Grille Arma : 6 chiffres (cases de 100 m) ou 8 chiffres (10 m). */
  function grid(x, y, digits) {
    var d = digits === 6 ? 100 : 10;
    var w = digits === 6 ? 3 : 4;
    return pad(x / d, w) + ' ' + pad(y / d, w);
  }

  /** « 042 116 », « 0421 1163 », « 042116 » ou « 04211163 » => centre de la case [x, y], ou null. */
  function parseGrid(text) {
    var s = String(text || '').replace(/[^0-9]/g, '');
    if (s.length !== 6 && s.length !== 8 && s.length !== 10) return null;
    var h = s.length / 2;
    var unit = h === 3 ? 100 : (h === 4 ? 10 : 1);
    var x = parseInt(s.slice(0, h), 10) * unit + unit / 2;
    var y = parseInt(s.slice(h), 10) * unit + unit / 2;
    return [x, y];
  }

  function dist(a, b) {
    var dx = b[0] - a[0], dy = b[1] - a[1];
    return Math.sqrt(dx * dx + dy * dy);
  }

  /** Azimut en degrés (0 = nord, sens horaire). */
  function azimuth(a, b) {
    var deg = Math.atan2(b[0] - a[0], b[1] - a[1]) * 180 / Math.PI;
    return (deg + 360) % 360;
  }

  function fmtDist(m) {
    if (!isFinite(m)) return '';
    return m >= 1000 ? (m / 1000).toFixed(m >= 10000 ? 1 : 2).replace('.', ',') + ' km' : Math.round(m) + ' m';
  }

  function pathLength(pts) {
    var t = 0;
    for (var i = 1; i < pts.length; i++) t += dist(pts[i - 1], pts[i]);
    return t;
  }

  function bbox(pts) {
    var b = [Infinity, Infinity, -Infinity, -Infinity];
    for (var i = 0; i < pts.length; i++) {
      b[0] = Math.min(b[0], pts[i][0]); b[1] = Math.min(b[1], pts[i][1]);
      b[2] = Math.max(b[2], pts[i][0]); b[3] = Math.max(b[3], pts[i][1]);
    }
    return b;
  }

  function distToSegment(p, a, b) {
    var dx = b[0] - a[0], dy = b[1] - a[1];
    var len2 = dx * dx + dy * dy;
    var t = len2 ? Math.max(0, Math.min(1, ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / len2)) : 0;
    return dist(p, [a[0] + t * dx, a[1] + t * dy]);
  }

  function pointInPolygon(p, poly) {
    var inside = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      var xi = poly[i][0], yi = poly[i][1], xj = poly[j][0], yj = poly[j][1];
      if (((yi > p[1]) !== (yj > p[1])) && (p[0] < (xj - xi) * (p[1] - yi) / (yj - yi) + xi)) inside = !inside;
    }
    return inside;
  }

  /** Contour d'un élément (cercle, ellipse et rectangle en polygone ; le reste tel quel). */
  function outline(el, segments) {
    var n = segments || 48;
    var p = el.props || {};
    if (el.geom === 'circle' || el.geom === 'ellipse') {
      var c = el.pts[0];
      var rx = el.geom === 'circle' ? (p.radius || 100) : (p.rx || p.radius || 100);
      var ry = el.geom === 'circle' ? (p.radius || 100) : (p.ry || p.radius || 100);
      var rot = (p.rotation || 0) * Math.PI / 180;
      var out = [];
      for (var i = 0; i < n; i++) {
        var a = 2 * Math.PI * i / n;
        var x = rx * Math.cos(a), y = ry * Math.sin(a);
        out.push([c[0] + x * Math.cos(rot) - y * Math.sin(rot), c[1] + x * Math.sin(rot) + y * Math.cos(rot)]);
      }
      return out;
    }
    if (el.geom === 'rect') {
      var a0 = el.pts[0], b0 = el.pts[1];
      return [[a0[0], a0[1]], [b0[0], a0[1]], [b0[0], b0[1]], [a0[0], b0[1]]];
    }
    return el.pts.slice();
  }

  /** Distance (m) entre un point et un élément ; 0 à l'intérieur d'une zone. */
  function hitDistance(el, p) {
    var poly = outline(el, 36);
    if (el.geom === 'point') return dist(p, el.pts[0]);
    var best = Infinity;
    var closed = el.geom !== 'line';
    for (var i = 1; i < poly.length; i++) best = Math.min(best, distToSegment(p, poly[i - 1], poly[i]));
    if (closed && poly.length > 2) {
      best = Math.min(best, distToSegment(p, poly[poly.length - 1], poly[0]));
      if ((el.props && el.props.fill > 0) && pointInPolygon(p, poly)) return 0;
    }
    return best;
  }

  function simplify(pts, tol) {
    if (pts.length < 3) return pts.slice();
    var a = pts[0], b = pts[pts.length - 1], maxD = -1, idx = 0;
    for (var i = 1; i < pts.length - 1; i++) {
      var d = distToSegment(pts[i], a, b);
      if (d > maxD) { maxD = d; idx = i; }
    }
    if (maxD <= tol) return [a, b];
    var left = simplify(pts.slice(0, idx + 1), tol);
    var right = simplify(pts.slice(idx), tol);
    return left.slice(0, -1).concat(right);
  }

  function centroid(el) {
    var b = bbox(outline(el, 24));
    return [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2];
  }

  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  function translate(el, dx, dy) {
    var c = clone(el);
    c.pts = c.pts.map(function (q) { return [Math.round((q[0] + dx) * 10) / 10, Math.round((q[1] + dy) * 10) / 10]; });
    return c;
  }

  function rotate(el, deg, center) {
    var c = clone(el);
    if (c.geom === 'point' || c.geom === 'circle' || c.geom === 'ellipse') {
      c.props.rotation = (((c.props.rotation || 0) + deg) % 360 + 360) % 360;
      if (c.geom !== 'point') return c;
    }
    var o = center || centroid(el);
    var r = -deg * Math.PI / 180;
    if (c.geom === 'rect') {
      c.geom = 'area';
      c.pts = outline(el, 4);
    }
    c.pts = c.pts.map(function (q) {
      var x = q[0] - o[0], y = q[1] - o[1];
      return [Math.round((o[0] + x * Math.cos(r) - y * Math.sin(r)) * 10) / 10, Math.round((o[1] + x * Math.sin(r) + y * Math.cos(r)) * 10) / 10];
    });
    return c;
  }

  function scale(el, f, center) {
    var c = clone(el);
    var o = center || centroid(el);
    c.pts = c.pts.map(function (q) { return [Math.round((o[0] + (q[0] - o[0]) * f) * 10) / 10, Math.round((o[1] + (q[1] - o[1]) * f) * 10) / 10]; });
    if (c.props) {
      ['radius', 'rx', 'ry'].forEach(function (k) { if (c.props[k]) c.props[k] = Math.max(5, Math.round(c.props[k] * f * 10) / 10); });
      if (c.geom === 'point' && c.props.size) c.props.size = Math.max(10, Math.min(64, Math.round(c.props.size * f)));
    }
    return c;
  }

  /** Visible dans la phase choisie ? Phase vide (null) = tout le plan ; élément sans phase = toutes les phases. */
  function inPhase(el, phaseUid) {
    if (!phaseUid) return true;
    return !el.phases || !el.phases.length || el.phases.indexOf(phaseUid) >= 0;
  }

  /** Identifiant d'élément : lettres et chiffres, 12 caractères. */
  function uid(prefix) {
    var s = '';
    var abc = 'abcdefghijklmnopqrstuvwxyz0123456789';
    for (var i = 0; i < 10; i++) s += abc.charAt(Math.floor(Math.random() * abc.length));
    return (prefix || 'e') + '_' + s;
  }

  /**
   * Historique (annuler / rétablir) : chaque entrée garde l'état avant et après des éléments touchés.
   * entrée = { before: {uid: élément|null}, after: {uid: élément|null} }
   */
  function History(limit) {
    this.limit = limit || 200;
    this.undoStack = [];
    this.redoStack = [];
  }
  History.prototype.push = function (before, after) {
    this.undoStack.push({ before: clone(before), after: clone(after) });
    if (this.undoStack.length > this.limit) this.undoStack.shift();
    this.redoStack = [];
  };
  History.prototype.undo = function () {
    var e = this.undoStack.pop();
    if (!e) return null;
    this.redoStack.push(e);
    return e.before;
  };
  History.prototype.redo = function () {
    var e = this.redoStack.pop();
    if (!e) return null;
    this.undoStack.push(e);
    return e.after;
  };
  History.prototype.canUndo = function () { return this.undoStack.length > 0; };
  History.prototype.canRedo = function () { return this.redoStack.length > 0; };

  /**
   * Changements reçus du serveur : le dernier enregistré gagne, sauf pour un élément que ce poste a modifié et pas
   * encore envoyé (pending), qui garde sa version locale jusqu'à l'envoi.
   * Retour : liste des uid changés.
   */
  function mergeRemote(local, remote, pending) {
    var changed = [];
    (remote || []).forEach(function (r) {
      if (pending && pending[r.uid]) return;
      var cur = local[r.uid];
      if (r.deleted) {
        if (cur) { delete local[r.uid]; changed.push(r.uid); }
        return;
      }
      if (!cur || (r.updated_at || 0) >= (cur.updated_at || 0)) {
        local[r.uid] = r;
        changed.push(r.uid);
      }
    });
    return changed;
  }

  var GAME_COLORS = {
    RED: '#f24d47', CYAN: '#4dccf2', GREEN: '#73d973', YELLOW: '#fae04d', BLUE: '#5999ff',
    WHITE: '#f2f2f2', ORANGE: '#ff9433', PURPLE: '#c780f2', BLACK: '#141414'
  };

  function nearestGameColor(hex) {
    var m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(String(hex || ''));
    if (!m) return 'WHITE';
    var r = parseInt(m[1], 16), g = parseInt(m[2], 16), b = parseInt(m[3], 16);
    var best = 'WHITE', bestD = Infinity;
    Object.keys(GAME_COLORS).forEach(function (k) {
      var h = GAME_COLORS[k];
      var d = Math.pow(parseInt(h.slice(1, 3), 16) - r, 2) + Math.pow(parseInt(h.slice(3, 5), 16) - g, 2) + Math.pow(parseInt(h.slice(5, 7), 16) - b, 2);
      if (d < bestD) { bestD = d; best = k; }
    });
    return best;
  }

  var ECHELON_CODE = { team: 'A', squad: 'B', section: 'C', platoon: 'D', company: 'E', battalion: 'F', regiment: 'G', brigade: 'H' };

  /**
   * SIDC 2525C (15 caractères) d'une unité : affiliation, statut (présent / prévu), fonction, PC ou groupement, échelon.
   * props.sidc (équipements, fonctions hors liste) sert de base : « * » y est remplacé par l'affiliation ou le statut.
   */
  function sidc(props, unitTypes) {
    props = props || {};
    var aff = /^[FHNU]$/.test(props.affil) ? props.affil : 'F';
    var mod = props.hq && props.tf ? 'B' : (props.hq ? 'A' : (props.tf ? 'E' : null));
    var status = props.status === 'planned' ? 'A' : null;
    if (props.sidc && /^[A-Z0-9*-]{10,30}$/.test(props.sidc)) {
      var plain = !/\*/.test(props.sidc) && !mod && !status;
      if (plain) return props.sidc;
      var ch = props.sidc.split('');
      while (ch.length < 15) ch.push('-');
      if (ch[1] === '*') ch[1] = aff;
      if (ch[3] === '*') ch[3] = 'P';
      if (status) ch[3] = status;
      if (mod) ch[10] = mod;
      if (ECHELON_CODE[props.echelon] && ch[0] === 'S' && ch[2] === 'G' && ch[4] === 'U') ch[11] = ECHELON_CODE[props.echelon];
      return ch.join('').replace(/\*/g, '-');
    }
    var fn = '------';
    (unitTypes || []).forEach(function (t) { if (t.id === props.unitType) fn = t.fn; });
    var ech = ECHELON_CODE[props.echelon] || '-';
    return 'S' + aff + 'G' + (status || 'P') + fn + (mod || '-') + ech + '---';
  }

  /** Champs de texte et modificateurs du symbole (milsymbol) : formation supérieure, informations, renforcé / réduit, direction. */
  function symbolOptions(props) {
    props = props || {};
    var o = {};
    if (props.higher) o.higherFormation = String(props.higher);
    if (props.info) o.additionalInformation = String(props.info);
    if (props.mod === 'reinforced') o.reinforcedReduced = '+';
    if (props.mod === 'reduced') o.reinforcedReduced = '-';
    if (props.mod === 'both') o.reinforcedReduced = '±';
    if (props.dir != null && props.dir !== '' && isFinite(Number(props.dir))) o.direction = Number(props.dir);
    return o;
  }

  /** Ligne décalée de d mètres (à gauche si d > 0), onglets limités. */
  function offsetPath(pts, d) {
    var n = pts.length;
    var out = [];
    for (var i = 0; i < n; i++) {
      var a = pts[Math.max(0, i - 1)], b = pts[i], c = pts[Math.min(n - 1, i + 1)];
      var n1 = segNormal(a, b), n2 = segNormal(b, c);
      if (i === 0) n1 = n2;
      if (i === n - 1) n2 = n1;
      var mx = n1[0] + n2[0], my = n1[1] + n2[1];
      var ml = Math.sqrt(mx * mx + my * my);
      if (ml < 1e-6) { mx = n1[0]; my = n1[1]; ml = 1; }
      mx /= ml; my /= ml;
      var k = Math.min(3, 1 / Math.max(0.2, mx * n1[0] + my * n1[1]));
      out.push([b[0] + mx * d * k, b[1] + my * d * k]);
    }
    return out;
  }
  function segNormal(a, b) {
    var dx = b[0] - a[0], dy = b[1] - a[1];
    var l = Math.sqrt(dx * dx + dy * dy);
    return l < 1e-9 ? [0, 0] : [-dy / l, dx / l];
  }
  function pointAlong(pts, s) {
    for (var i = 1; i < pts.length; i++) {
      var d = dist(pts[i - 1], pts[i]);
      if (s <= d && d > 0) return [pts[i - 1][0] + (pts[i][0] - pts[i - 1][0]) * s / d, pts[i - 1][1] + (pts[i][1] - pts[i - 1][1]) * s / d];
      s -= d;
    }
    return pts[pts.length - 1].slice();
  }

  /**
   * Axe de progression avec sa largeur (APP-6) : deux bords parallèles, ouverts à l'arrière, et une pointe plus large
   * que le corps. Retour : polyligne du contour (bord gauche, pointe, bord droit).
   */
  function axisOutline(pts, width) {
    var total = pathLength(pts);
    var half = Math.max(1, width / 2);
    if (pts.length < 2 || total < 1) return pts.slice();
    var head = Math.min(total * 0.6, Math.max(width * 1.2, half * 2));
    var bodyEnd = total - head;
    var body = [];
    var acc = 0;
    body.push(pts[0].slice());
    for (var i = 1; i < pts.length; i++) {
      acc += dist(pts[i - 1], pts[i]);
      if (acc < bodyEnd - 0.5) body.push(pts[i].slice());
    }
    var neck = pointAlong(pts, bodyEnd);
    body.push(neck);
    var left = offsetPath(body, half), right = offsetPath(body, -half);
    var tip = pts[pts.length - 1];
    var nrm = segNormal(neck, tip);
    var wl = [neck[0] + nrm[0] * half * 2, neck[1] + nrm[1] * half * 2], wr = [neck[0] - nrm[0] * half * 2, neck[1] - nrm[1] * half * 2];
    return left.concat([wl, tip.slice(), wr]).concat(right.reverse());
  }

  /** Éventail de portée : secteur (azimut, ouverture) entre la portée minimale et la portée, en polygone. */
  function fanOutline(el, segments) {
    var p = el.props || {};
    var c = el.pts[0];
    var r = Math.max(1, Number(p.radius) || 1000), r0 = Math.max(0, Math.min(r, Number(p.minRange) || 0));
    var arc = Math.max(5, Math.min(360, Number(p.arc) || 60));
    var b = Number(p.bearing) || 0;
    var n = Math.max(6, Math.round((segments || 48) * arc / 360));
    var out = [], i, a;
    for (i = 0; i <= n; i++) {
      a = (b - arc / 2 + arc * i / n) * Math.PI / 180;
      out.push([c[0] + r * Math.sin(a), c[1] + r * Math.cos(a)]);
    }
    if (arc >= 360) {
      out.pop();
      return out;
    }
    if (r0 > 0) {
      for (i = n; i >= 0; i--) {
        a = (b - arc / 2 + arc * i / n) * Math.PI / 180;
        out.push([c[0] + r0 * Math.sin(a), c[1] + r0 * Math.cos(a)]);
      }
    } else out.push([c[0], c[1]]);
    return out;
  }

  /** Échelle : longueur ronde (m) la plus proche de la cible. */
  function niceScale(meters) {
    var steps = [10, 20, 50, 100, 200, 250, 500, 1000, 2000, 2500, 5000, 10000, 20000];
    var best = steps[0];
    for (var i = 0; i < steps.length; i++) if (steps[i] <= meters) best = steps[i];
    return best;
  }

  /** Pas du quadrillage (m) selon la largeur affichée. */
  function gridStep(widthMeters) {
    if (widthMeters > 30000) return 10000;
    if (widthMeters > 2500) return 1000;
    return 100;
  }

  return {
    grid: grid, parseGrid: parseGrid, dist: dist, azimuth: azimuth, fmtDist: fmtDist, pathLength: pathLength,
    bbox: bbox, distToSegment: distToSegment, pointInPolygon: pointInPolygon, outline: outline, hitDistance: hitDistance,
    simplify: simplify, centroid: centroid, translate: translate, rotate: rotate, scale: scale, inPhase: inPhase,
    uid: uid, History: History, mergeRemote: mergeRemote, nearestGameColor: nearestGameColor, GAME_COLORS: GAME_COLORS,
    sidc: sidc, niceScale: niceScale, gridStep: gridStep, clone: clone,
    symbolOptions: symbolOptions, offsetPath: offsetPath, axisOutline: axisOutline, fanOutline: fanOutline, ECHELON_CODE: ECHELON_CODE
  };
});
