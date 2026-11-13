/* Éditeur de plans : calculs sans navigateur (sélection au cadre et au lasso, pile sous le curseur, alignement,
 * presse-papiers, style, temps et images clés, relief : altitude, coupe, ligne de vue, zone vue, éventail de portée,
 * arbre ORBAT, recherche). Testé par tests/Frontend/toc-plan-logic.test.cjs.
 * Coordonnées : mètres Arma [x, y], y vers le nord. Temps : minutes par rapport à l'heure H. */
(function (root, factory) {
  'use strict';
  var G = typeof module === 'object' && module.exports ? require('../toc-geo.js') : root.TocGeo;
  var api = factory(G);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TocPlanLogic = api;
})(typeof self !== 'undefined' ? self : this, function (G) {
  'use strict';

  /* ---------- Sélection ---------- */
  function normBox(a, b) {
    return [Math.min(a[0], b[0]), Math.min(a[1], b[1]), Math.max(a[0], b[0]), Math.max(a[1], b[1])];
  }
  function inBox(p, box) { return p[0] >= box[0] && p[0] <= box[2] && p[1] >= box[1] && p[1] <= box[3]; }
  function ccw(a, b, c) { return (c[1] - a[1]) * (b[0] - a[0]) > (b[1] - a[1]) * (c[0] - a[0]); }
  function segsIntersect(a, b, c, d) { return ccw(a, c, d) !== ccw(b, c, d) && ccw(a, b, c) !== ccw(a, b, d); }

  /** Contour utile à la sélection : points de l'élément, fermé si c'est une zone. */
  function outlineOf(el, n) { return el.kind === 'fan' ? G.fanOutline(el, n || 32) : G.outline(el, n || 32); }
  function shape(el) {
    var pts = outlineOf(el, 32);
    return { pts: pts, closed: el.geom !== 'line' && (el.geom !== 'point' || el.kind === 'fan') && pts.length > 2, point: el.geom === 'point' && el.kind !== 'fan' };
  }

  /** L'élément touche-t-il le polygone (cadre ou lasso) ? Un point : dedans. Trait ou zone : un sommet dedans, un bord
   *  qui coupe le polygone, ou le polygone entièrement dans la zone. */
  function polyHit(el, poly) {
    var s = shape(el);
    if (s.point) return G.pointInPolygon(el.pts[0], poly);
    var i, j;
    for (i = 0; i < s.pts.length; i++) if (G.pointInPolygon(s.pts[i], poly)) return true;
    var n = s.pts.length;
    var last = s.closed ? n : n - 1;
    for (i = 0; i < last; i++) {
      var a = s.pts[i], b = s.pts[(i + 1) % n];
      for (j = 0; j < poly.length; j++) if (segsIntersect(a, b, poly[j], poly[(j + 1) % poly.length])) return true;
    }
    return s.closed && G.pointInPolygon(poly[0], s.pts);
  }
  function boxHit(el, box) {
    return polyHit(el, [[box[0], box[1]], [box[2], box[1]], [box[2], box[3]], [box[0], box[3]]]);
  }

  /** Éléments sous un point, du dessus vers le dessous. tol(el) = tolérance en mètres. */
  function pickStack(els, p, tol) {
    var out = [];
    els.forEach(function (el) {
      var t = typeof tol === 'function' ? tol(el) : tol;
      var d = G.hitDistance(el, p);
      if (el.kind === 'fan' && G.pointInPolygon(p, outlineOf(el, 32))) d = Math.min(d, t);
      if (d <= t) out.push({ uid: el.uid, z: el.z || 0, d: d, point: el.geom === 'point' });
    });
    // Un symbole ponctuel passe devant une zone qui l'entoure ; sinon l'ordre d'affichage.
    out.sort(function (a, b) { return (b.point - a.point) || (b.z - a.z) || (a.d - b.d); });
    return out.map(function (o) { return o.uid; });
  }
  /** Alt+clic : élément suivant de la pile après celui déjà choisi. */
  function cyclePick(stack, current) {
    if (!stack.length) return null;
    var i = stack.indexOf(current);
    return stack[(i + 1) % stack.length];
  }

  function bboxOf(els) {
    var pts = [];
    els.forEach(function (el) { pts = pts.concat(outlineOf(el, 24)); });
    return G.bbox(pts);
  }

  /** Alignement : déplacement [dx, dy] de chaque élément. mode : left right top bottom hcenter vcenter. */
  function align(els, mode) {
    var all = bboxOf(els);
    var out = {};
    els.forEach(function (el) {
      var b = G.bbox(outlineOf(el, 24));
      var dx = 0, dy = 0;
      if (mode === 'left') dx = all[0] - b[0];
      if (mode === 'right') dx = all[2] - b[2];
      if (mode === 'hcenter') dx = (all[0] + all[2]) / 2 - (b[0] + b[2]) / 2;
      if (mode === 'bottom') dy = all[1] - b[1];
      if (mode === 'top') dy = all[3] - b[3];
      if (mode === 'vcenter') dy = (all[1] + all[3]) / 2 - (b[1] + b[3]) / 2;
      out[el.uid] = [dx, dy];
    });
    return out;
  }
  /** Répartition : centres à intervalles égaux entre le premier et le dernier. axis : 'h' ou 'v'. */
  function distribute(els, axis) {
    var k = axis === 'v' ? 1 : 0;
    var items = els.map(function (el) { return { uid: el.uid, c: G.centroid(el)[k] }; }).sort(function (a, b) { return a.c - b.c; });
    var out = {};
    if (items.length < 3) { items.forEach(function (i) { out[i.uid] = [0, 0]; }); return out; }
    var step = (items[items.length - 1].c - items[0].c) / (items.length - 1);
    items.forEach(function (it, i) {
      var d = items[0].c + i * step - it.c;
      out[it.uid] = k ? [0, d] : [d, 0];
    });
    return out;
  }

  /* ---------- Presse-papiers et style ---------- */
  var CLIP_FORMAT = 'athena-plan-clip';
  function clipSerialize(els, world) {
    return JSON.stringify({ format: CLIP_FORMAT, version: 1, world: world || '', elements: els.map(function (e) {
      var c = G.clone(e);
      delete c.updated_at; delete c.updated_by; delete c.deleted;
      return c;
    }) });
  }
  /** Texte du presse-papiers => { kind: 'athena' | 'plan' | 'geojson' | null, elements, text }. */
  function clipParse(text) {
    var doc = null;
    try { doc = JSON.parse(String(text || '')); } catch (e) { return { kind: null, elements: [] }; }
    if (!doc || typeof doc !== 'object') return { kind: null, elements: [] };
    var ok = function (e) { return e && typeof e === 'object' && typeof e.kind === 'string' && Array.isArray(e.pts) && e.pts.length > 0; };
    if (doc.format === CLIP_FORMAT && Array.isArray(doc.elements)) return { kind: 'athena', elements: doc.elements.filter(ok), world: doc.world || '' };
    if (doc.format === 'athena-plan' && Array.isArray(doc.elements)) return { kind: 'plan', elements: doc.elements.filter(ok), world: doc.world || '' };
    if (doc.type === 'FeatureCollection' || doc.type === 'Feature') return { kind: 'geojson', elements: [], text: String(text) };
    if (Array.isArray(doc) && doc.every(ok)) return { kind: 'athena', elements: doc, world: '' };
    return { kind: null, elements: [] };
  }
  /**
   * Copies prêtes à poser : nouveaux uid, groupes, unités mères et itinéraires suivis renumérotés quand ils sont dans
   * le lot. o : { at: [x, y] | null (centre du lot), dx, dy, layer, layerOk(uid), phaseOk(uid), z, keepLock }.
   */
  function pasteElements(els, o) {
    o = o || {};
    var b = bboxOf(els);
    var dx = o.dx || 0, dy = o.dy || 0;
    if (o.at && isFinite(b[0])) { dx = o.at[0] - (b[0] + b[2]) / 2; dy = o.at[1] - (b[1] + b[3]) / 2; }
    var ids = {}, groups = {};
    els.forEach(function (e) { ids[e.uid] = G.uid('e'); });
    var z = o.z || 0;
    return els.map(function (e) {
      var c = G.translate(e, dx, dy);
      c.uid = ids[e.uid];
      c.z = ++z;
      c.locked = o.keepLock ? !!c.locked : false;
      c.props = c.props || {};
      if (c.group) c.group = groups[c.group] || (groups[c.group] = G.uid('g'));
      if (o.layerOk && !o.layerOk(c.layer)) c.layer = o.layer || '';
      c.phases = (c.phases || []).filter(function (p) { return !o.phaseOk || o.phaseOk(p); });
      if (c.props.parent) c.props.parent = ids[c.props.parent] || (o.keepParent ? c.props.parent : '');
      if (c.props.route) c.props.route = ids[c.props.route] || c.props.route;
      if (Array.isArray(c.props.track)) c.props.track = c.props.track.map(function (k) { return { t: k.t, p: [Math.round((k.p[0] + dx) * 10) / 10, Math.round((k.p[1] + dy) * 10) / 10] }; });
      delete c.updated_at; delete c.updated_by; delete c.deleted;
      return c;
    });
  }
  var STYLE_KEYS = ['color', 'width', 'dash', 'arrow', 'fill', 'opacity', 'labelSize', 'labelPos'];
  function pickStyle(el) {
    var s = {};
    STYLE_KEYS.forEach(function (k) { if (el.props && el.props[k] !== undefined) s[k] = el.props[k]; });
    return s;
  }
  function applyStyle(el, style) {
    var c = G.clone(el);
    Object.keys(style || {}).forEach(function (k) {
      if (STYLE_KEYS.indexOf(k) < 0) return;
      if (k === 'arrow' && c.geom !== 'line') return;
      if (k === 'fill' && (c.geom === 'line' || c.geom === 'point')) return;
      if (k === 'color' && c.kind === 'unit') return;
      c.props[k] = style[k];
    });
    return c;
  }

  /* ---------- Chemins ---------- */
  function alongPath(pts, s) {
    if (!pts.length) return [0, 0];
    if (s <= 0) return [pts[0][0], pts[0][1]];
    for (var i = 1; i < pts.length; i++) {
      var d = G.dist(pts[i - 1], pts[i]);
      if (s <= d && d > 0) { var u = s / d; return [pts[i - 1][0] + (pts[i][0] - pts[i - 1][0]) * u, pts[i - 1][1] + (pts[i][1] - pts[i - 1][1]) * u]; }
      s -= d;
    }
    return [pts[pts.length - 1][0], pts[pts.length - 1][1]];
  }
  /** Abscisse curviligne du point de la polyligne le plus proche de p. */
  function projectOnPath(pts, p) {
    var best = Infinity, bestS = 0, acc = 0;
    for (var i = 1; i < pts.length; i++) {
      var a = pts[i - 1], b = pts[i];
      var dx = b[0] - a[0], dy = b[1] - a[1];
      var len2 = dx * dx + dy * dy;
      var u = len2 ? Math.max(0, Math.min(1, ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / len2)) : 0;
      var q = [a[0] + u * dx, a[1] + u * dy];
      var d = G.dist(p, q);
      var len = Math.sqrt(len2);
      if (d < best) { best = d; bestS = acc + u * len; }
      acc += len;
    }
    return bestS;
  }
  /** Morceau de polyligne entre deux abscisses. */
  function subPath(pts, s0, s1) {
    var out = [alongPath(pts, s0)];
    var acc = 0;
    for (var i = 1; i < pts.length; i++) {
      acc += G.dist(pts[i - 1], pts[i]);
      if (acc > s0 && acc < s1) out.push([pts[i][0], pts[i][1]]);
    }
    out.push(alongPath(pts, s1));
    return out;
  }
  /** Minutes cumulées à chaque sommet d'un itinéraire parcouru à vitesse constante (km/h). */
  function routeTimings(pts, speedKmh) {
    var out = [0];
    if (!(speedKmh > 0)) return pts.map(function () { return null; });
    var acc = 0;
    for (var i = 1; i < pts.length; i++) { acc += G.dist(pts[i - 1], pts[i]); out.push(acc / 1000 / speedKmh * 60); }
    return out;
  }

  /* ---------- Temps ---------- */
  function trackOf(el) {
    var t = el && el.props && el.props.track;
    return el && el.geom === 'point' && Array.isArray(t) && t.length ? t : null;
  }
  /** Position d'un élément à l'instant t (contrat : même règle que PlanTimeline côté site). */
  function positionAt(el, t, byUid) {
    var tr = trackOf(el);
    if (!tr) return [el.pts[0][0], el.pts[0][1]];
    if (t <= tr[0].t) return [tr[0].p[0], tr[0].p[1]];
    var n = tr.length;
    if (t >= tr[n - 1].t) return [tr[n - 1].p[0], tr[n - 1].p[1]];
    for (var i = 0; i < n - 1; i++) {
      var a = tr[i], b = tr[i + 1];
      if (t >= a.t && t <= b.t) {
        var u = b.t > a.t ? (t - a.t) / (b.t - a.t) : 0;
        var route = byUid && el.props.route ? byUid[el.props.route] : null;
        if (route && route.geom === 'line' && route.pts.length >= 2) {
          var s0 = projectOnPath(route.pts, a.p), s1 = projectOnPath(route.pts, b.p);
          return alongPath(route.pts, s0 + u * (s1 - s0));
        }
        return [a.p[0] + (b.p[0] - a.p[0]) * u, a.p[1] + (b.p[1] - a.p[1]) * u];
      }
    }
    return [tr[n - 1].p[0], tr[n - 1].p[1]];
  }
  function visibleAt(el, t) {
    var p = el.props || {};
    if (p.hidden || p.placed === false) return false;
    if (t == null) return true;
    if (p.t0 != null && t < p.t0) return false;
    if (p.t1 != null && t >= p.t1) return false;
    return true;
  }
  /** Élément tel qu'il est à l'instant t (copie déplacée), ou l'élément lui-même sans piste. */
  function atTime(el, t, byUid) {
    if (t == null || !trackOf(el)) return el;
    var c = G.clone(el);
    c.pts = [positionAt(el, t, byUid).map(function (v) { return Math.round(v * 10) / 10; })];
    return c;
  }
  /** Pose ou remplace l'image clé de l'instant t. Sans piste : la position actuelle devient l'image clé de départ. */
  function setKeyframe(el, t, p, startT) {
    var c = G.clone(el);
    var tr = Array.isArray(c.props.track) ? c.props.track.slice() : [];
    t = Math.round(t * 100) / 100;
    var q = [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10];
    if (!tr.length && startT != null && startT < t) tr.push({ t: Math.round(startT * 100) / 100, p: [c.pts[0][0], c.pts[0][1]] });
    tr = tr.filter(function (k) { return Math.abs(k.t - t) > 0.004; });
    tr.push({ t: t, p: q });
    tr.sort(function (a, b) { return a.t - b.t; });
    if (tr.length > 60) return null;
    c.props.track = tr;
    c.pts = [[tr[0].p[0], tr[0].p[1]]];
    return c;
  }
  function removeKeyframe(el, t) {
    var c = G.clone(el);
    var tr = (c.props.track || []).filter(function (k) { return Math.abs(k.t - t) > 0.004; });
    if (tr.length) { c.props.track = tr; c.pts = [[tr[0].p[0], tr[0].p[1]]]; } else delete c.props.track;
    return c;
  }
  function normTimeline(tl) {
    tl = tl && typeof tl === 'object' ? tl : {};
    return {
      h: typeof tl.h === 'string' ? tl.h : '', dur: Math.max(10, Math.min(14400, Math.round(Number(tl.dur) || 240))),
      start: Math.max(-1440, Math.min(0, Math.round(Number(tl.start) || 0))), zulu: !!tl.zulu,
      phases: tl.phases && typeof tl.phases === 'object' && !Array.isArray(tl.phases) ? tl.phases : {},
      events: Array.isArray(tl.events) ? tl.events : []
    };
  }
  /** Début (minutes) de chaque phase : valeur réglée, sinon répartition régulière sur la durée. */
  function phaseStarts(phases, tl) {
    tl = normTimeline(tl);
    var n = phases.length;
    var out = [];
    var prev = tl.start;
    phases.forEach(function (ph, i) {
      var v = tl.phases[ph.uid];
      var t = typeof v === 'number' && isFinite(v) ? v : tl.start + (tl.dur * i) / Math.max(1, n);
      if (i > 0 && t < prev) t = prev;
      out.push(t);
      prev = t;
    });
    return out;
  }
  /** Phase en cours à l'instant t (uid), ou null avant la première. */
  function phaseAtTime(phases, tl, t) {
    var starts = phaseStarts(phases, tl);
    var cur = null;
    for (var i = 0; i < phases.length; i++) if (t >= starts[i] - 1e-6) cur = phases[i].uid;
    return cur;
  }
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  /** « H+01:30 », « H-00:15 », « H ». */
  function fmtH(t) {
    var m = Math.round(Math.abs(t));
    if (m === 0) return 'H';
    return 'H' + (t < 0 ? '-' : '+') + pad2(Math.floor(m / 60)) + ':' + pad2(m % 60);
  }
  var MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
  /** Groupe date-heure ZULU de l'instant t, d'après l'heure H « AAAA-MM-JJTHH:MM » ; '' si H n'est pas réglée. */
  function fmtZulu(h, t) {
    var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(String(h || ''));
    if (!m) return '';
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) + Math.round(t) * 60000);
    return pad2(d.getUTCDate()) + pad2(d.getUTCHours()) + pad2(d.getUTCMinutes()) + 'Z ' + MONTHS[d.getUTCMonth()] + ' ' + String(d.getUTCFullYear()).slice(2);
  }
  /** « H+1:30 », « h-15 », « 90 », « 1h30 », « +45 » => minutes, ou null. */
  function parseH(text) {
    var s = String(text || '').trim().toLowerCase().replace(/\s+/g, '');
    if (s === 'h') return 0;
    var m = /^h?([+-])?(\d{1,3})(?:[:h](\d{1,2}))?$/.exec(s);
    if (!m) return null;
    var colon = m[3] !== undefined || /[:h]\d/.test(s.replace(/^h/, ''));
    var v = colon ? parseInt(m[2], 10) * 60 + parseInt(m[3] || '0', 10) : parseInt(m[2], 10);
    return m[1] === '-' ? -v : v;
  }

  /* ---------- Relief ---------- */
  /** Altitude (m) par interpolation bilinéaire ; null hors de la grille ou sans donnée. */
  function demSample(dem, x, y) {
    if (!dem) return null;
    var fx = (x - dem.ox) / dem.cell, fy = (y - dem.oy) / dem.cell;
    if (fx < 0 || fy < 0 || fx > dem.cols - 1 || fy > dem.rows - 1) return null;
    var c0 = Math.min(dem.cols - 2, Math.floor(fx)), r0 = Math.min(dem.rows - 2, Math.floor(fy));
    if (c0 < 0 || r0 < 0) return null;
    var u = fx - c0, v = fy - r0;
    var H = dem.heights, miss = dem.missing == null ? -32768 : dem.missing;
    var a = H[r0 * dem.cols + c0], b = H[r0 * dem.cols + c0 + 1], c = H[(r0 + 1) * dem.cols + c0], d = H[(r0 + 1) * dem.cols + c0 + 1];
    if (a === miss || b === miss || c === miss || d === miss) {
      var vals = [a, b, c, d].filter(function (z) { return z !== miss; });
      if (!vals.length) return null;
      return vals.reduce(function (s, z) { return s + z; }, 0) / vals.length;
    }
    return a * (1 - u) * (1 - v) + b * u * (1 - v) + c * (1 - u) * v + d * u * v;
  }
  /** Pente (degrés) au point, par différences centrées. */
  function slopeAt(dem, x, y) {
    var e = dem.cell;
    var zx0 = demSample(dem, x - e, y), zx1 = demSample(dem, x + e, y), zy0 = demSample(dem, x, y - e), zy1 = demSample(dem, x, y + e);
    if (zx0 == null || zx1 == null || zy0 == null || zy1 == null) return null;
    var gx = (zx1 - zx0) / (2 * e), gy = (zy1 - zy0) / (2 * e);
    return Math.atan(Math.sqrt(gx * gx + gy * gy)) * 180 / Math.PI;
  }
  /** Coupe du relief le long d'un tracé : échantillons { d, x, y, z } et bilan (min, max, montée, descente, pente max). */
  function profile(dem, pts, step) {
    var total = G.pathLength(pts);
    step = Math.max(1, step || (dem ? dem.cell / 2 : 25));
    var n = Math.max(2, Math.min(2000, Math.ceil(total / step) + 1));
    var samples = [];
    var gain = 0, loss = 0, min = Infinity, max = -Infinity, maxSlope = 0, prev = null, gaps = 0;
    for (var i = 0; i < n; i++) {
      var d = total * i / (n - 1);
      var p = alongPath(pts, d);
      var z = demSample(dem, p[0], p[1]);
      if (z == null) gaps++;
      samples.push({ d: d, x: p[0], y: p[1], z: z });
      if (z != null) {
        min = Math.min(min, z); max = Math.max(max, z);
        if (prev) {
          var dz = z - prev.z;
          if (dz > 0) gain += dz; else loss -= dz;
          var run = d - prev.d;
          if (run > 0) maxSlope = Math.max(maxSlope, Math.abs(Math.atan(dz / run) * 180 / Math.PI));
        }
        prev = { d: d, z: z };
      }
    }
    return { samples: samples, length: total, min: isFinite(min) ? min : null, max: isFinite(max) ? max : null, gain: gain, loss: loss, maxSlope: maxSlope, gaps: gaps };
  }
  /**
   * Ligne de vue entre a et b (hauteurs au-dessus du sol : œil hA, cible hB).
   * Retour : { visible, block: [x, y] | null, blockD, dist, zA, zB, clearance (marge mini en m), samples: [{ d, ground, ray }] }.
   */
  function los(dem, a, b, hA, hB, step) {
    var dist = G.dist(a, b);
    var gA = demSample(dem, a[0], a[1]), gB = demSample(dem, b[0], b[1]);
    if (gA == null || gB == null) return { visible: null, block: null, dist: dist, samples: [], unknown: true };
    var zA = gA + (hA == null ? 1.7 : hA), zB = gB + (hB == null ? 1.7 : hB);
    step = Math.max(1, step || dem.cell / 4);
    var n = Math.max(2, Math.min(4000, Math.ceil(dist / step)));
    var block = null, blockD = null, clearance = Infinity, samples = [], unknown = false;
    for (var i = 0; i <= n; i++) {
      var u = i / n;
      var x = a[0] + (b[0] - a[0]) * u, y = a[1] + (b[1] - a[1]) * u;
      var g = demSample(dem, x, y);
      var ray = zA + (zB - zA) * u;
      samples.push({ d: dist * u, ground: g, ray: ray });
      if (g == null) { unknown = true; continue; }
      if (i > 0 && i < n) {
        clearance = Math.min(clearance, ray - g);
        if (g > ray && !block) { block = [x, y]; blockD = dist * u; }
      }
    }
    return { visible: !block, block: block, blockD: blockD, dist: dist, zA: zA, zB: zB, clearance: isFinite(clearance) ? clearance : null, samples: samples, unknown: unknown };
  }
  /**
   * Zone vue depuis o : pour chaque rayon, les intervalles de distance [d0, d1] où le sol est vu.
   * opt : { radius, eye (m), target (m au-dessus du sol), rays, step, bearing, arc, minRange }.
   */
  function viewshed(dem, o, opt) {
    opt = opt || {};
    var radius = Math.max(50, opt.radius || 1000);
    var g0 = demSample(dem, o[0], o[1]);
    if (g0 == null) return { rays: [], radius: radius, origin: o, unknown: true, seenPct: 0 };
    var eye = g0 + (opt.eye == null ? 1.7 : opt.eye);
    var tgt = opt.target || 0;
    var arc = Math.max(5, Math.min(360, opt.arc || 360));
    var n = Math.max(8, Math.min(720, opt.rays || Math.round(arc / 2)));
    var step = Math.max(2, opt.step || dem.cell / 2);
    var minR = Math.max(0, opt.minRange || 0);
    var az0 = arc >= 360 ? 0 : (opt.bearing || 0) - arc / 2;
    var rays = [], seen = 0, total = 0;
    for (var i = 0; i < n; i++) {
      var az = az0 + arc * (arc >= 360 ? i / n : (n > 1 ? i / (n - 1) : 0));
      var rad = az * Math.PI / 180;
      var sx = Math.sin(rad), sy = Math.cos(rad);
      var maxSlope = -Infinity, spans = [], open = null;
      for (var d = step; d <= radius + 1e-6; d += step) {
        var z = demSample(dem, o[0] + sx * d, o[1] + sy * d);
        if (z == null) { if (open != null) { spans.push([open, d - step]); open = null; } continue; }
        var slopeGround = (z - eye) / d;
        var vis = (z + tgt - eye) / d >= maxSlope;
        if (slopeGround > maxSlope) maxSlope = slopeGround;
        if (d >= minR) {
          total++;
          if (vis) { seen++; if (open == null) open = d - step < minR ? minR : d - step; }
          else if (open != null) { spans.push([open, d - step]); open = null; }
        }
      }
      if (open != null) spans.push([open, radius]);
      rays.push({ az: az, spans: spans });
    }
    return { rays: rays, radius: radius, minRange: minR, origin: [o[0], o[1]], arc: arc, bearing: opt.bearing || 0, step: arc / (arc >= 360 ? n : Math.max(1, n - 1)), seenPct: total ? Math.round(100 * seen / total) : 0 };
  }
  /** En-têtes X-Dem-* + corps binaire => objet relief (contrat). get(nom) lit un en-tête. */
  function demFromBinary(buffer, get) {
    var cols = parseInt(get('X-Dem-Cols'), 10), rows = parseInt(get('X-Dem-Rows'), 10);
    if (!(cols > 1) || !(rows > 1) || buffer.byteLength < cols * rows * 2) return null;
    var o = String(get('X-Dem-Origin') || '0,0').split(',');
    var heights;
    // Octets petit-boutistes : lecture directe sur une machine petit-boutiste, sinon par DataView.
    if (new Uint8Array(new Uint16Array([1]).buffer)[0] === 1) heights = new Int16Array(buffer, 0, cols * rows);
    else { heights = new Int16Array(cols * rows); var dv = new DataView(buffer); for (var i = 0; i < cols * rows; i++) heights[i] = dv.getInt16(i * 2, true); }
    return {
      cols: cols, rows: rows, cell: parseFloat(get('X-Dem-Cell')) || 50, ox: parseFloat(o[0]) || 0, oy: parseFloat(o[1]) || 0, heights: heights, missing: -32768,
      minZ: parseFloat(get('X-Dem-Min')) || 0, maxZ: parseFloat(get('X-Dem-Max')) || 0, worldSize: parseFloat(get('X-Dem-World')) || 0, coverage: parseFloat(get('X-Dem-Coverage')) || 0
    };
  }

  /* ---------- ORBAT ---------- */
  var AFFILS = ['F', 'H', 'N', 'U'];
  /** Arbre des unités du plan par affiliation : { F: [nœud…], H: […] }, nœud = { uid, el, children }. Cycles défaits. */
  function orbatTree(els) {
    var units = els.filter(function (e) { return e.kind === 'unit'; });
    var by = {};
    units.forEach(function (e) { by[e.uid] = { uid: e.uid, el: e, children: [] }; });
    var roots = { F: [], H: [], N: [], U: [] };
    units.forEach(function (e) {
      var par = e.props.parent && by[e.props.parent] && e.props.parent !== e.uid ? by[e.props.parent] : null;
      // Une boucle (a sous b sous a) : l'unité repasse à la racine.
      var seen = {}, cur = par;
      while (cur) { if (seen[cur.uid] || cur.uid === e.uid) { par = null; break; } seen[cur.uid] = true; cur = cur.el.props.parent ? by[cur.el.props.parent] : null; }
      if (par) par.children.push(by[e.uid]);
      else (roots[AFFILS.indexOf(e.props.affil) >= 0 ? e.props.affil : 'F']).push(by[e.uid]);
    });
    var sortNodes = function (list) {
      list.sort(function (a, b) { return ((a.el.props.ord || 0) - (b.el.props.ord || 0)) || (a.uid < b.uid ? -1 : 1); });
      list.forEach(function (n) { sortNodes(n.children); });
    };
    AFFILS.forEach(function (a) { sortNodes(roots[a]); });
    return roots;
  }
  function descendants(els, uid) {
    var out = [], kids = {};
    els.forEach(function (e) { if (e.kind === 'unit' && e.props.parent) (kids[e.props.parent] = kids[e.props.parent] || []).push(e.uid); });
    var walk = function (u, depth) { (kids[u] || []).forEach(function (k) { if (out.indexOf(k) < 0 && depth < 40) { out.push(k); walk(k, depth + 1); } }); };
    walk(uid, 0);
    return out;
  }
  /**
   * Déplace une unité dans l'arbre : sous newParent ('' = racine), à la place index parmi ses frères.
   * Retour : { uid: élément modifié } (ordre des frères renuméroté), ou null si le déplacement ferait une boucle.
   */
  function reparent(els, uid, newParent, index, affil) {
    var by = {};
    els.forEach(function (e) { by[e.uid] = e; });
    var el = by[uid];
    if (!el || el.kind !== 'unit') return null;
    if (newParent && (newParent === uid || !by[newParent] || by[newParent].kind !== 'unit' || descendants(els, uid).indexOf(newParent) >= 0)) return null;
    var aff = newParent ? by[newParent].props.affil : (affil || el.props.affil);
    var sib = els.filter(function (e) { return e.kind === 'unit' && e.uid !== uid && (e.props.parent || '') === (newParent || '') && (newParent ? true : e.props.affil === aff); })
      .sort(function (a, b) { return ((a.props.ord || 0) - (b.props.ord || 0)) || (a.uid < b.uid ? -1 : 1); });
    var at = index == null ? sib.length : Math.max(0, Math.min(sib.length, index));
    sib.splice(at, 0, el);
    var out = {};
    sib.forEach(function (e, i) {
      var same = e.uid !== uid && (e.props.ord || 0) === i;
      if (same) return;
      var c = G.clone(e);
      c.props.ord = i;
      if (e.uid === uid) { c.props.parent = newParent || ''; if (c.props.affil !== aff) c.props.affil = aff; }
      out[c.uid] = c;
    });
    // L'affiliation suit dans toute la branche déplacée.
    if (el.props.affil !== aff) descendants(els, uid).forEach(function (k) { if (by[k].props.affil !== aff) { var c = out[k] || G.clone(by[k]); c.props.affil = aff; out[k] = c; } });
    return out;
  }

  /* ---------- Import des marqueurs du jeu ---------- */
  /**
   * État de l'import : 'mismatch' (la partie en liaison est sur un autre terrain que le plan), 'nolink' (aucune partie
   * en liaison et rien à montrer), 'empty' (partie en liaison, aucun marqueur), 'stale' (marqueurs du dernier relevé,
   * la partie n'est plus en liaison), 'ok'.
   */
  function importState(game, planWorld, count) {
    var gw = String((game && game.world) || '').toLowerCase(), pw = String(planWorld || '').toLowerCase();
    if (gw && pw && gw !== pw) return 'mismatch';
    var live = !!(game && game.live);
    if (!count) return live ? 'empty' : 'nolink';
    return live ? 'ok' : 'stale';
  }
  /** Filtre de la liste : f = { system (écarter les marqueurs de service), side ('' ou camp), box [x0,y0,x1,y1], q }. */
  function markerFilter(recs, f) {
    f = f || {};
    return recs.filter(function (r) {
      if (f.system && r.system) return false;
      if (f.side && r.side && r.side !== f.side) return false;
      if (f.box && !inBox(r.pos, f.box)) return false;
      if (f.q && fuzzyScore(f.q, (r.label || '') + ' ' + (r.type || '') + ' ' + (r.gid || '')) <= 0) return false;
      return true;
    });
  }
  function markerKind(r) {
    if (r.shape === 'ELLIPSE' || r.shape === 'RECTANGLE') return 'area';
    if (r.shape === 'POLYLINE') return 'line';
    if (r.el && r.el.kind === 'unit') return 'unit';
    if (r.source === 'point') return 'point';
    return 'icon';
  }
  /** Groupes de la liste : by = 'kind' | 'channel' | 'side'. Retour : [{ key, items }], ordre stable. */
  function markerGroups(recs, by) {
    var order = by === 'kind' ? ['unit', 'icon', 'point', 'area', 'line'] : [];
    var groups = {};
    recs.forEach(function (r) {
      var k = by === 'channel' ? (r.channel === '' ? '?' : r.channel) : (by === 'side' ? (r.side || '?') : markerKind(r));
      if (!groups[k]) { groups[k] = []; if (order.indexOf(k) < 0) order.push(k); }
      groups[k].push(r);
    });
    return order.filter(function (k) { return groups[k]; }).map(function (k) { return { key: k, items: groups[k] }; });
  }
  /**
   * Ce que l'import fera : éléments à ajouter, éléments déjà importés à mettre à jour, marqueurs ignorés.
   * o : { mode: 'update' | 'skip' | 'duplicate' (marqueur déjà dans le plan, même identifiant du jeu), link (garder
   *       le lien), layer, phase, z, color(hex) => couleur de notre palette }.
   * Retour : { add: [élément], update: { uid: élément }, skipped, known }.
   */
  function markerPlan(recs, els, o) {
    o = o || {};
    var by = {};
    (els || []).forEach(function (e) { if (e.props && e.props.gid) by[e.props.gid] = e; });
    var out = { add: [], update: {}, skipped: 0, known: 0 };
    var z = o.z || 0;
    recs.forEach(function (r) {
      if (!r.el) { out.skipped++; return; }
      var el = G.clone(r.el);
      el.props = el.props || {};
      if (el.kind !== 'unit' && o.color) el.props.color = o.color(el.props.color);
      if (r.hatch) el.props.dash = 'dash';
      el.props.gid = r.gid;
      if (o.link) el.props.glink = true;
      el.pts = el.pts.map(function (q) { return [Math.round(q[0] * 10) / 10, Math.round(q[1] * 10) / 10]; });
      var old = by[r.gid];
      if (old) out.known++;
      if (old && o.mode === 'skip') { out.skipped++; return; }
      if (old && o.mode !== 'duplicate') {
        // Mise à jour : l'élément garde son identité, son calque, ses phases, son ordre et ses réglages propres.
        var c = G.clone(old);
        c.kind = el.kind; c.geom = el.geom; c.pts = el.pts;
        Object.keys(el.props).forEach(function (k) { if (k !== 'glink') c.props[k] = el.props[k]; });
        if (o.link) c.props.glink = true;
        if (Array.isArray(c.props.track)) delete c.props.track;
        out.update[c.uid] = c;
        return;
      }
      el.uid = G.uid('e');
      el.layer = o.layer || '';
      el.phases = o.phase ? [o.phase] : [];
      el.group = '';
      el.z = ++z;
      el.locked = false;
      out.add.push(el);
    });
    return out;
  }

  /* ---------- Divers ---------- */
  /** Grille à 10 chiffres (au mètre). */
  function grid10(x, y) {
    var f = function (v) { var s = String(Math.max(0, Math.floor(v))); while (s.length < 5) s = '0' + s; return s; };
    return f(x) + ' ' + f(y);
  }
  function snapToGrid(p, step) { return [Math.round(p[0] / step) * step, Math.round(p[1] / step) * step]; }
  function fold(s) {
    return String(s || '').toLowerCase().normalize ? String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '') : String(s || '').toLowerCase();
  }
  /** Note de recherche : 0 = ne correspond pas ; plus haut = meilleur (début de mot, suite continue). */
  function fuzzyScore(query, text) {
    var q = fold(query).trim(), t = fold(text);
    if (!q) return 1;
    var at = t.indexOf(q);
    if (at >= 0) return 1000 - at * 2 - (at > 0 && /[a-z0-9]/.test(t.charAt(at - 1)) ? 200 : 0) - Math.min(200, t.length);
    var words = q.split(/\s+/);
    if (words.length > 1 && words.every(function (w) { return t.indexOf(w) >= 0; })) return 400 - Math.min(200, t.length);
    var i = 0, score = 0, last = -2;
    for (var j = 0; j < t.length && i < q.length; j++) {
      if (t.charAt(j) === q.charAt(i)) { score += j === last + 1 ? 6 : 1; last = j; i++; }
    }
    return i === q.length ? Math.max(1, score) : 0;
  }
  /** Mouvement d'une poignée de mise à l'échelle : facteur autour du pivot d'après la position du pointeur. */
  function scaleFactor(pivot, from, to) {
    var d0 = G.dist(pivot, from), d1 = G.dist(pivot, to);
    return d0 > 0 ? Math.max(0.05, Math.min(40, d1 / d0)) : 1;
  }
  /** Angle (degrés, sens horaire) entre deux positions du pointeur vues du pivot ; pas de 15° si snap. */
  function rotationAngle(pivot, from, to, snap) {
    var a = G.azimuth(pivot, to) - G.azimuth(pivot, from);
    a = ((a + 540) % 360) - 180;
    return snap ? Math.round(a / 15) * 15 : a;
  }

  return {
    outlineOf: outlineOf, normBox: normBox, inBox: inBox, segsIntersect: segsIntersect, polyHit: polyHit, boxHit: boxHit, pickStack: pickStack, cyclePick: cyclePick, bboxOf: bboxOf,
    align: align, distribute: distribute, CLIP_FORMAT: CLIP_FORMAT, clipSerialize: clipSerialize, clipParse: clipParse, pasteElements: pasteElements,
    pickStyle: pickStyle, applyStyle: applyStyle, alongPath: alongPath, projectOnPath: projectOnPath, subPath: subPath, routeTimings: routeTimings,
    positionAt: positionAt, visibleAt: visibleAt, atTime: atTime, setKeyframe: setKeyframe, removeKeyframe: removeKeyframe, normTimeline: normTimeline,
    phaseStarts: phaseStarts, phaseAtTime: phaseAtTime, fmtH: fmtH, fmtZulu: fmtZulu, parseH: parseH,
    demSample: demSample, slopeAt: slopeAt, profile: profile, los: los, viewshed: viewshed, demFromBinary: demFromBinary,
    orbatTree: orbatTree, descendants: descendants, reparent: reparent, grid10: grid10, snapToGrid: snapToGrid, fuzzyScore: fuzzyScore,
    scaleFactor: scaleFactor, rotationAngle: rotationAngle,
    importState: importState, markerFilter: markerFilter, markerKind: markerKind, markerGroups: markerGroups, markerPlan: markerPlan
  };
});
