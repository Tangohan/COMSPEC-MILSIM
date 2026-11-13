/* Éditeur de plans : relief et vue 3D. Le relief d'un terrain est celui que le site connaît déjà (relevé du théâtre
 * fait en jeu, cellule de 50 m), lu une fois en binaire (/atak/toc/api/terrain/{carte}/dem). Il sert à l'altitude sous
 * le curseur, aux outils (mesure, ligne de vue, coupe, zone vue, teinte des pentes, masque des éventails de portée) et
 * à la vue 3D (plan/plan-3d.js, three.js du site, chargé seulement quand la vue 3D est ouverte).
 * Sans relief relevé : les outils le disent, la vue 3D reste à plat. Se branche sur window.TocPlanEditor. */
(function () {
  'use strict';
  var E = window.TocPlanEditor;
  if (!E) return;
  var P = E.P, G = E.G, R = E.R, LG = E.LG, $ = E.$, $$ = E.$$, esc = E.esc;
  var NO_RELIEF = 'Relief non disponible pour ce terrain : lancez le relevé du théâtre en jeu (voir l’aide du poste TOC).';
  var demState = 'idle';
  var A = { kind: null, pts: [], result: null, eye: 1.7, target: 1.7, radius: 1500 };
  var slopeOn = false, slopeCanvas = null, slopeLayer = null;
  var view3d = null, loading3d = false, drag3 = null, overlayTimer = null, lastOverlayMpp = 0;
  var maskCache = {}, maskLayer = null, anaLayer = null;
  var spriteCache = {};

  /* ---------- Relief ---------- */
  function loadDem() {
    if (demState !== 'idle' || !P.plan) return;
    demState = 'loading';
    fetch(E.BOOT.base + '/atak/toc/api/terrain/' + P.plan.map_id + '/dem', { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) { demState = r.status === 404 || r.status === 403 ? 'none' : 'error'; E.emit('dem'); return null; }
      return r.arrayBuffer().then(function (buf) {
        var dem = LG.demFromBinary(buf, function (n) { return r.headers.get(n); });
        if (!dem) { demState = 'none'; E.emit('dem'); return; }
        if (!dem.worldSize) dem.worldSize = E.tm ? E.tm.worldSize : dem.cols * dem.cell;
        E.dem = dem;
        demState = 'ok';
        E.emit('dem');
        if (view3d) { view3d.setDem(dem); note3d(); }
        E.renderAll();
      });
    }).catch(function () { demState = 'error'; E.emit('dem'); });
  }
  /** Pour les essais et la page de démonstration : pose un relief sans passer par le site. */
  E.setDem = function (dem) { E.dem = dem; demState = dem ? 'ok' : 'none'; E.emit('dem'); if (view3d) view3d.setDem(dem); note3d(); E.renderAll(); };
  function needDem() {
    if (E.dem) return true;
    E.say(demState === 'loading' ? 'Relief en cours de chargement : réessayez dans un instant.' : NO_RELIEF, true);
    return false;
  }

  /* ---------- Dessin des analyses (canvas : carte 2D et vue 3D) ---------- */
  function drawViewshed(ctx, project, vs, alpha) {
    var o = vs.origin;
    var half = (vs.step || 2) / 2 + 0.15;
    var wedge = function (az, d0, d1) {
      var a0 = (az - half) * Math.PI / 180, a1 = (az + half) * Math.PI / 180;
      var p = [[o[0] + Math.sin(a0) * d0, o[1] + Math.cos(a0) * d0], [o[0] + Math.sin(a0) * d1, o[1] + Math.cos(a0) * d1], [o[0] + Math.sin(a1) * d1, o[1] + Math.cos(a1) * d1], [o[0] + Math.sin(a1) * d0, o[1] + Math.cos(a1) * d0]].map(project);
      ctx.moveTo(p[0][0], p[0][1]); ctx.lineTo(p[1][0], p[1][1]); ctx.lineTo(p[2][0], p[2][1]); ctx.lineTo(p[3][0], p[3][1]); ctx.closePath();
    };
    ctx.save();
    ctx.globalAlpha = alpha == null ? 1 : alpha;
    ctx.fillStyle = 'rgba(255,95,86,.26)';
    ctx.beginPath();
    vs.rays.forEach(function (r) { wedge(r.az, vs.minRange || 0, vs.radius); });
    ctx.fill();
    ctx.globalCompositeOperation = 'destination-out';
    ctx.fillStyle = '#000';
    ctx.beginPath();
    vs.rays.forEach(function (r) { r.spans.forEach(function (s) { wedge(r.az, s[0], s[1]); }); });
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = 'rgba(52,211,153,.34)';
    ctx.beginPath();
    vs.rays.forEach(function (r) { r.spans.forEach(function (s) { wedge(r.az, s[0], s[1]); }); });
    ctx.fill();
    ctx.restore();
  }
  function viewshedOverlay(vs, pane) {
    var o = vs.origin, r = vs.radius;
    var size = Math.max(128, Math.min(900, Math.round(2 * r / Math.max(0.5, E.tm.metersPerPixel()))));
    var cv = document.createElement('canvas');
    cv.width = cv.height = size;
    var ctx = cv.getContext('2d');
    drawViewshed(ctx, function (p) { return [(p[0] - (o[0] - r)) / (2 * r) * size, ((o[1] + r) - p[1]) / (2 * r) * size]; }, vs);
    return L.imageOverlay(cv.toDataURL(), L.latLngBounds(E.tm.toLL([o[0] - r, o[1] - r]), E.tm.toLL([o[0] + r, o[1] + r])), { pane: pane || 'planAnalysis', interactive: false });
  }
  function buildSlope() {
    var dem = E.dem;
    var cv = document.createElement('canvas');
    cv.width = dem.cols; cv.height = dem.rows;
    var ctx = cv.getContext('2d');
    var img = ctx.createImageData(dem.cols, dem.rows);
    var H = dem.heights, c2 = 2 * dem.cell;
    for (var r = 1; r < dem.rows - 1; r++) {
      for (var c = 1; c < dem.cols - 1; c++) {
        var i = r * dem.cols + c;
        var a = H[i - 1], b = H[i + 1], d = H[i - dem.cols], e = H[i + dem.cols];
        if (a === dem.missing || b === dem.missing || d === dem.missing || e === dem.missing || H[i] <= 0) continue;
        var gx = (b - a) / c2, gy = (e - d) / c2;
        var deg = Math.atan(Math.sqrt(gx * gx + gy * gy)) * 57.2958;
        if (deg < 10) continue;
        var k = ((dem.rows - 1 - r) * dem.cols + c) * 4;
        var col = deg < 20 ? [250, 224, 77, 70] : (deg < 30 ? [255, 148, 51, 105] : [255, 95, 86, 135]);
        img.data[k] = col[0]; img.data[k + 1] = col[1]; img.data[k + 2] = col[2]; img.data[k + 3] = col[3];
      }
    }
    ctx.putImageData(img, 0, 0);
    return cv;
  }
  function toggleSlope() {
    if (!slopeOn && !needDem()) return;
    slopeOn = !slopeOn;
    var b = $('[data-run="slope"]');
    if (b) b.setAttribute('aria-pressed', String(slopeOn));
    if (slopeLayer) { E.tm.map.removeLayer(slopeLayer); slopeLayer = null; }
    if (slopeOn) {
      slopeCanvas = slopeCanvas || buildSlope();
      var d = E.dem;
      slopeLayer = L.imageOverlay(slopeCanvas.toDataURL(), L.latLngBounds(E.tm.toLL([d.ox - d.cell / 2, d.oy - d.cell / 2]), E.tm.toLL([d.ox + (d.cols - 0.5) * d.cell, d.oy + (d.rows - 0.5) * d.cell])), { pane: 'planAnalysis', interactive: false, className: 'pe-slope' }).addTo(E.tm.map);
      E.say('Teinte des pentes : jaune 10 à 20°, orange 20 à 30°, rouge au-delà de 30°.');
    }
    invalidate3d();
  }

  /* ---------- Outils : mesure, ligne de vue, coupe, zone vue ---------- */
  function panel(html) {
    var el = $('#pe-analysis');
    el.hidden = !html;
    el.innerHTML = html ? html + '<div class="pe-stack is-row" style="margin-top:8px"><button type="button" class="toc-btn is-small" data-ana-clear>Effacer</button><button type="button" class="toc-btn is-small" data-ana-close>Fermer l’outil</button></div>' : '';
  }
  function head(t, s) { return '<div class="toc-sec"><h2>' + esc(t) + '</h2><span>' + esc(s || '') + '</span></div>'; }
  function polyArea(pts) { var a = 0; for (var i = 0, j = pts.length - 1; i < pts.length; j = i++) a += (pts[j][0] + pts[i][0]) * (pts[j][1] - pts[i][1]); return Math.abs(a / 2); }
  function fmtArea(m2) { return m2 >= 1e6 ? (m2 / 1e6).toFixed(2).replace('.', ',') + ' km²' : (m2 >= 1e4 ? (m2 / 1e4).toFixed(1).replace('.', ',') + ' ha' : Math.round(m2) + ' m²'); }
  function clearAnalysis(keepKind) {
    A.pts = []; A.result = null;
    if (!keepKind) A.kind = null;
    if (anaLayer) anaLayer.clearLayers();
    if (view3d) view3d.setAirLines([]);
    renderAnalysis(null);
    invalidate3d();
  }
  function line2d(pts, color, dash) { return L.polyline(pts.map(E.tm.toLL), { pane: 'planHandles', color: color, weight: 3, dashArray: dash || null, interactive: false }); }
  function dot2d(p, color) { return L.circleMarker(E.tm.toLL(p), { pane: 'planHandles', radius: 5, color: '#070a0d', weight: 2, fillColor: color, fillOpacity: 1, interactive: false }); }
  function plate2d(p, text, color) { return L.marker(E.tm.toLL(p), { pane: 'planHandles', interactive: false, icon: L.divIcon({ className: '', html: '<span class="toc-plate" style="border-color:' + color + '">' + esc(text) + '</span>', iconSize: null, iconAnchor: [-10, 10] }) }); }
  /** Redessine l'analyse en cours (carte 2D, panneau, lignes aériennes de la vue 3D). cursor : point provisoire. */
  function renderAnalysis(cursor) {
    if (!anaLayer) return;
    anaLayer.clearLayers();
    var pts = A.pts.concat(cursor ? [cursor] : []);
    var k = A.kind;
    if (!k) { panel(''); return; }
    var h = '';
    if (k === 'measure') {
      h = head('Mesure', pts.length + ' point(s)');
      if (pts.length) {
        anaLayer.addLayer(line2d(pts, '#f5b841', '6 4'));
        pts.forEach(function (q) { anaLayer.addLayer(dot2d(q, '#f5b841')); });
      }
      if (pts.length >= 2) {
        var a = pts[pts.length - 2], b = pts[pts.length - 1];
        var az = G.azimuth(a, b);
        var txt = G.fmtDist(G.dist(a, b)) + ' · ' + Math.round(az) + '°';
        anaLayer.addLayer(plate2d(b, txt, '#f5b841'));
        h += '<p>Dernier tronçon <b class="mono">' + G.fmtDist(G.dist(a, b)) + '</b> · azimut <b class="mono">' + Math.round(az) + '°</b> (<span class="mono">' + Math.round(az * 17.7778) + ' mils</span>)</p>';
        h += '<p>Total <b class="mono">' + G.fmtDist(G.pathLength(pts)) + '</b>' + (pts.length >= 3 ? ' · surface fermée <b class="mono">' + fmtArea(polyArea(pts)) + '</b>' : '') + '</p>';
        if (E.dem) { var za = LG.demSample(E.dem, a[0], a[1]), zb = LG.demSample(E.dem, b[0], b[1]); if (za != null && zb != null) h += '<p>Dénivelé <b class="mono">' + (zb - za >= 0 ? '+' : '') + Math.round(zb - za) + ' m</b> · pente moyenne <b class="mono">' + (G.dist(a, b) > 0 ? Math.round(Math.atan((zb - za) / G.dist(a, b)) * 57.2958) : 0) + '°</b></p>'; }
      } else h += '<p>Cliquez les points à mesurer.</p>';
    }
    if (k === 'los') {
      h = head('Ligne de vue', E.dem ? 'cellule de ' + E.dem.cell + ' m' : '');
      h += '<div class="pe-row2"><label class="pe-f"><span>Hauteur de l’observateur (m)</span><input class="toc-input mono" type="number" min="0" max="500" step="0.1" value="' + A.eye + '" data-ana="eye"></label><label class="pe-f"><span>Hauteur de la cible (m)</span><input class="toc-input mono" type="number" min="0" max="500" step="0.1" value="' + A.target + '" data-ana="target"></label></div>';
      if (pts.length >= 2 && E.dem) {
        var res = LG.los(E.dem, pts[0], pts[1], A.eye, A.target);
        A.result = cursor ? null : res;
        if (res.visible == null) h += '<p class="is-ko">Hors du relief relevé : pas de réponse sur ce tronçon.</p>';
        else {
          if (res.block) { anaLayer.addLayer(line2d([pts[0], res.block], '#34d399')); anaLayer.addLayer(line2d([res.block, pts[1]], '#ff5f56', '6 5')); anaLayer.addLayer(dot2d(res.block, '#ff5f56')); }
          else anaLayer.addLayer(line2d(pts, '#34d399'));
          h += '<p class="' + (res.visible ? 'is-ok' : 'is-ko') + '"><b>' + (res.visible ? 'Vue dégagée' : 'Vue masquée par le relief') + '</b>' + (res.block ? ' à ' + G.fmtDist(res.blockD) + ' de l’observateur (grille ' + G.grid(res.block[0], res.block[1], 8) + ')' : '') + '</p>';
          h += '<p>Distance <b class="mono">' + G.fmtDist(res.dist) + '</b> · azimut <b class="mono">' + Math.round(G.azimuth(pts[0], pts[1])) + '°</b> · marge au-dessus du sol <b class="mono">' + (res.clearance != null ? Math.round(res.clearance) + ' m' : '–') + '</b></p>';
          h += chart(res.samples.map(function (s) { return { d: s.d, z: s.ground }; }), { ray: [res.zA, res.zB], block: res.blockD });
          if (res.unknown) h += '<p>Une partie du tronçon sort du relief relevé.</p>';
          if (view3d) view3d.setAirLines(res.block ? [{ pts: [[pts[0][0], pts[0][1], res.zA], [res.block[0], res.block[1], res.zA + (res.zB - res.zA) * res.blockD / res.dist]], color: '#34d399', dashed: false, width: 3 }, { pts: [[res.block[0], res.block[1], res.zA + (res.zB - res.zA) * res.blockD / res.dist], [pts[1][0], pts[1][1], res.zB]], color: '#ff5f56', dashed: true, width: 3 }] : [{ pts: [[pts[0][0], pts[0][1], res.zA], [pts[1][0], pts[1][1], res.zB]], color: '#34d399', dashed: false, width: 3 }]);
        }
        anaLayer.addLayer(dot2d(pts[0], '#fff')); anaLayer.addLayer(dot2d(pts[1], res.visible ? '#34d399' : '#ff5f56'));
        h += '<p>Le relief seul est pris en compte : ni bâtiments ni végétation.</p>';
      } else { if (pts.length) anaLayer.addLayer(dot2d(pts[0], '#fff')); h += '<p>' + (pts.length ? 'Cliquez la cible.' : 'Cliquez l’observateur, puis la cible.') + '</p>'; }
    }
    if (k === 'profile') {
      h = head('Coupe du relief', pts.length >= 2 ? G.fmtDist(G.pathLength(pts)) : '');
      if (pts.length) { anaLayer.addLayer(line2d(pts, '#62b0f5')); pts.forEach(function (q) { anaLayer.addLayer(dot2d(q, '#62b0f5')); }); }
      if (pts.length >= 2 && E.dem) {
        var pr = LG.profile(E.dem, pts);
        A.result = pr;
        if (pr.min == null) h += '<p class="is-ko">Hors du relief relevé.</p>';
        else {
          h += chart(pr.samples, {});
          h += '<p>Altitude <b class="mono">' + Math.round(pr.min) + ' à ' + Math.round(pr.max) + ' m</b> · montée <b class="mono">+' + Math.round(pr.gain) + ' m</b> · descente <b class="mono">−' + Math.round(pr.loss) + ' m</b> · pente max <b class="mono">' + Math.round(pr.maxSlope) + '°</b></p>';
        }
        h += '<p>Clics : points de la coupe · Échap : effacer.</p>';
      } else h += '<p>Cliquez le début puis la suite du tracé.</p>';
    }
    if (k === 'viewshed') {
      h = head('Zone vue', A.result ? A.result.seenPct + ' % du disque' : '');
      h += '<div class="pe-row2"><label class="pe-f"><span>Rayon (m)</span><input class="toc-input mono" type="number" min="100" max="10000" step="100" value="' + A.radius + '" data-ana="radius"></label><label class="pe-f"><span>Hauteur de l’observateur (m)</span><input class="toc-input mono" type="number" min="0" max="500" step="0.1" value="' + A.eye + '" data-ana="eye"></label></div>';
      if (A.pts.length && E.dem) {
        var vs = LG.viewshed(E.dem, A.pts[0], { radius: A.radius, eye: A.eye, rays: 240 });
        A.result = vs;
        if (vs.unknown) h += '<p class="is-ko">Ce point est hors du relief relevé.</p>';
        else {
          anaLayer.addLayer(viewshedOverlay(vs, 'planAnalysis'));
          anaLayer.addLayer(dot2d(A.pts[0], '#fff'));
          h += '<p><span class="is-ok"><b>Vert</b></span> : sol vu depuis le point (grille ' + G.grid(A.pts[0][0], A.pts[0][1], 8) + '). <span class="is-ko"><b>Rouge</b></span> : masqué par le relief. <b class="mono">' + vs.seenPct + ' %</b> du disque est vu.</p>';
        }
        h += '<p>Le relief seul est pris en compte : ni bâtiments ni végétation.</p>';
      } else h += '<p>Cliquez le point d’observation.</p>';
    }
    panel(h);
  }
  /** Coupe en SVG : sol, et rayon de la ligne de vue s'il y en a un. */
  function chart(samples, o) {
    var pts = samples.filter(function (s) { return s.z != null; });
    if (pts.length < 2) return '';
    var W = 400, H = 130, padL = 34, padB = 16, padT = 8;
    var d1 = samples[samples.length - 1].d || 1;
    var zs = pts.map(function (s) { return s.z; }).concat(o.ray || []);
    var z0 = Math.min.apply(null, zs), z1 = Math.max.apply(null, zs);
    if (z1 - z0 < 10) { z1 += 5; z0 -= 5; }
    var x = function (d) { return padL + d / d1 * (W - padL - 4); };
    var y = function (z) { return padT + (1 - (z - z0) / (z1 - z0)) * (H - padT - padB); };
    var path = pts.map(function (s, i) { return (i ? 'L' : 'M') + x(s.d).toFixed(1) + ' ' + y(s.z).toFixed(1); }).join('');
    var s = '<svg class="pe-chart" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" aria-label="Coupe du relief"><path d="' + path + 'L' + x(pts[pts.length - 1].d).toFixed(1) + ' ' + (H - padB) + 'L' + x(pts[0].d).toFixed(1) + ' ' + (H - padB) + 'Z" fill="rgba(98,176,245,.22)"/><path d="' + path + '" fill="none" stroke="#62b0f5" stroke-width="1.6" vector-effect="non-scaling-stroke"/>';
    if (o.ray) {
      var bx = o.block != null ? x(o.block) : null;
      var by = o.block != null ? y(o.ray[0] + (o.ray[1] - o.ray[0]) * o.block / d1) : null;
      if (bx != null) s += '<path d="M' + x(0) + ' ' + y(o.ray[0]) + 'L' + bx + ' ' + by + '" stroke="#34d399" stroke-width="1.6" vector-effect="non-scaling-stroke"/><path d="M' + bx + ' ' + by + 'L' + x(d1) + ' ' + y(o.ray[1]) + '" stroke="#ff5f56" stroke-width="1.6" stroke-dasharray="5 4" vector-effect="non-scaling-stroke"/>';
      else s += '<path d="M' + x(0) + ' ' + y(o.ray[0]) + 'L' + x(d1) + ' ' + y(o.ray[1]) + '" stroke="#34d399" stroke-width="1.6" vector-effect="non-scaling-stroke"/>';
    }
    s += '<text x="3" y="' + (padT + 9) + '" fill="#aab7c1" font-size="11" font-family="monospace">' + Math.round(z1) + ' m</text><text x="3" y="' + (H - padB) + '" fill="#aab7c1" font-size="11" font-family="monospace">' + Math.round(z0) + ' m</text><text x="' + (W - 4) + '" y="' + (H - 3) + '" fill="#aab7c1" font-size="11" font-family="monospace" text-anchor="end">' + esc(G.fmtDist(d1)) + '</text></svg>';
    return s;
  }
  function analysisTool(kind, hint, maxPts, demNeeded) {
    E.toolHandlers[kind] = {
      hint: hint,
      enter: function () { if (demNeeded && !E.dem) { if (demState === 'idle') loadDem(); E.say(demState === 'ok' ? '' : (demState === 'loading' || demState === 'idle' ? 'Relief en cours de chargement…' : NO_RELIEF), demState === 'none' || demState === 'error'); } if (A.kind !== kind) { clearAnalysis(); A.kind = kind; } renderAnalysis(null); },
      leave: function () {},
      click: function (p, ev, dbl) {
        if (demNeeded && !needDem()) return;
        if (dbl && kind !== 'measure' && kind !== 'profile') return;
        if (dbl) return;
        if (maxPts && A.pts.length >= maxPts) A.pts = [];
        A.pts.push(p);
        renderAnalysis(null);
        invalidate3d();
      },
      move: function (p) { if (A.pts.length && (!maxPts || A.pts.length < maxPts) && kind !== 'viewshed') renderAnalysis(p); },
      cancel: function () { if (A.pts.length) { clearAnalysis(true); return true; } return false; }
    };
    E.action('tool.' + kind, 'Outil : ' + hint.split(' : ')[0].toLowerCase(), function () { E.setTool(kind); }, { group: 'Outils' });
  }
  /** Lance un outil depuis un point donné (menu du clic droit). */
  E.terrainTool = function (kind, from) {
    E.setTool(kind);
    A.pts = [from];
    renderAnalysis(null);
    invalidate3d();
  };

  /* ---------- Éventails de portée masqués par le relief ---------- */
  function renderMasks() {
    if (!maskLayer) return;
    maskLayer.clearLayers();
    if (!E.dem) return;
    P.shown.forEach(function (d) {
      if (d.kind !== 'fan' || !d.props.mask) return;
      var key = JSON.stringify([d.pts, d.props.radius, d.props.bearing, d.props.arc, d.props.minRange, Math.round(E.tm.metersPerPixel() * 4)]);
      var vs = maskCache[key];
      if (!vs) {
        if (Object.keys(maskCache).length > 40) maskCache = {};
        vs = maskCache[key] = LG.viewshed(E.dem, d.pts[0], { radius: d.props.radius, bearing: d.props.bearing, arc: d.props.arc, minRange: d.props.minRange, eye: 1.7, rays: Math.max(24, Math.round((d.props.arc || 60) / 1.5)) });
      }
      if (!vs.unknown) maskLayer.addLayer(viewshedOverlay(vs, 'planAnalysis'));
    });
  }
  function fanMasks() {
    var out = [];
    if (!E.dem) return out;
    P.shown.forEach(function (d) {
      if (d.kind !== 'fan' || !d.props.mask) return;
      var vs = LG.viewshed(E.dem, d.pts[0], { radius: d.props.radius, bearing: d.props.bearing, arc: d.props.arc, minRange: d.props.minRange, eye: 1.7, rays: Math.max(24, Math.round((d.props.arc || 60) / 1.5)) });
      if (!vs.unknown) out.push(vs);
    });
    return out;
  }

  /* ---------- Vue 3D ---------- */
  function planAoi() {
    var world = E.tm.worldSize;
    var pts = [];
    P.shown.forEach(function (el) { pts = pts.concat(LG.outlineOf(el, 8)); });
    A.pts.forEach(function (p) { pts.push(p); });
    var b;
    if (pts.length) b = G.bbox(pts);
    else { var mb = E.tm.map.getBounds(); var sw = E.tm.toWorld(mb.getSouthWest()), ne = E.tm.toWorld(mb.getNorthEast()); b = [sw[0], sw[1], ne[0], ne[1]]; }
    var cx = (b[0] + b[2]) / 2, cy = (b[1] + b[3]) / 2;
    var size = Math.max(3000, Math.min(12000, Math.max(b[2] - b[0], b[3] - b[1]) * 1.5));
    size = Math.min(size, world);
    var x0 = Math.max(0, Math.min(world - size, cx - size / 2)), y0 = Math.max(0, Math.min(world - size, cy - size / 2));
    return { x0: x0, y0: y0, x1: x0 + size, y1: y0 + size };
  }
  /** Photo ou carte de la zone, assemblée depuis les tuiles du fond affiché ; null : le module teinte le relief lui-même. */
  function baseCanvas(bounds, sizePx) {
    var bg = E.tm.currentBackground();
    var spec = bg && bg.spec;
    if (!spec || !spec.tilePattern) return Promise.resolve(null);
    var ts = Number(spec.tileSize) || 256;
    var W = ts / (Number(spec.factorX) || 1);
    var mpp = (bounds.x1 - bounds.x0) / sizePx;
    var z = Math.ceil(Math.log(W / (ts * mpp)) / Math.LN2);
    z = Math.max(Number(spec.minZoom) || 0, Math.min(Number(spec.maxZoom) || 0, z));
    var tiles = function (zz) {
      var n = Math.pow(2, zz);
      var x0 = Math.max(0, Math.floor(bounds.x0 / W * n)), x1 = Math.min(n - 1, Math.floor(bounds.x1 / W * n));
      var y0 = Math.max(0, Math.floor((1 - bounds.y1 / W) * n)), y1 = Math.min(n - 1, Math.floor((1 - bounds.y0 / W) * n));
      return { n: n, x0: x0, x1: x1, y0: y0, y1: y1, count: Math.max(0, x1 - x0 + 1) * Math.max(0, y1 - y0 + 1) };
    };
    var t = tiles(z);
    while (t.count > 144 && z > (Number(spec.minZoom) || 0)) { z--; t = tiles(z); }
    if (!t.count) return Promise.resolve(null);
    var cv = document.createElement('canvas');
    cv.width = cv.height = sizePx;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = '#10161b'; ctx.fillRect(0, 0, sizePx, sizePx);
    var jobs = [], ok = 0;
    var scale = sizePx / (bounds.x1 - bounds.x0);
    for (var x = t.x0; x <= t.x1; x++) for (var y = t.y0; y <= t.y1; y++) (function (tx, ty) {
      jobs.push(new Promise(function (resolve) {
        var img = new Image();
        var timer = setTimeout(resolve, 9000);
        img.onload = function () {
          clearTimeout(timer);
          var wx0 = tx * W / t.n, wy1 = W * (1 - ty / t.n), side = W / t.n;
          try { ctx.drawImage(img, (wx0 - bounds.x0) * scale, (bounds.y1 - wy1) * scale, side * scale + 0.6, side * scale + 0.6); ok++; } catch (e) {}
          resolve();
        };
        img.onerror = function () { clearTimeout(timer); resolve(); };
        img.src = String(spec.tilePattern).replace('{z}', z).replace('{x}', tx).replace('{y}', ty);
      }));
    })(x, y);
    return Promise.all(jobs).then(function () {
      if (!ok) return null;
      try { cv.getContext('2d').getImageData(0, 0, 1, 1); } catch (e) { return null; }
      return cv;
    });
  }
  function drawOverlay3d(ctx, bounds, sizePx) {
    var sx = sizePx / (bounds.x1 - bounds.x0), sy = sizePx / (bounds.y1 - bounds.y0);
    var project = function (p) { return [(p[0] - bounds.x0) * sx, (bounds.y1 - p[1]) * sy]; };
    var ompp = (bounds.x1 - bounds.x0) / sizePx;
    var vmpp = view3d ? view3d.getView().mpp : ompp * 2;
    lastOverlayMpp = vmpp;
    var scale = Math.max(1.4, Math.min(5, vmpp / ompp));
    if (slopeOn && slopeCanvas && E.dem) {
      var d = E.dem;
      var a = project([d.ox - d.cell / 2, d.oy + (d.rows - 0.5) * d.cell]), b = project([d.ox + (d.cols - 0.5) * d.cell, d.oy - d.cell / 2]);
      ctx.imageSmoothingEnabled = true;
      ctx.drawImage(slopeCanvas, a[0], a[1], b[0] - a[0], b[1] - a[1]);
    }
    if (P.grid) {
      ctx.save(); ctx.strokeStyle = 'rgba(232,238,242,.22)'; ctx.lineWidth = Math.max(1, scale * 0.6);
      ctx.beginPath();
      for (var gx = Math.ceil(bounds.x0 / 1000) * 1000; gx <= bounds.x1; gx += 1000) { var q = project([gx, 0])[0]; ctx.moveTo(q, 0); ctx.lineTo(q, sizePx); }
      for (var gy = Math.ceil(bounds.y0 / 1000) * 1000; gy <= bounds.y1; gy += 1000) { var q2 = project([0, gy])[1]; ctx.moveTo(0, q2); ctx.lineTo(sizePx, q2); }
      ctx.stroke(); ctx.restore();
    }
    fanMasks().forEach(function (vs) { drawViewshed(ctx, project, vs); });
    if (A.kind === 'viewshed' && A.result && !A.result.unknown && A.result.rays) drawViewshed(ctx, project, A.result);
    // Éléments déplacés en cours de glisser : dessinés à leur place provisoire.
    var saved = null;
    if (drag3 && drag3.preview) { saved = P.shown; P.shown = P.shown.map(function (s) { return drag3.preview[s.uid] || s; }); }
    E.drawPlan(ctx, project, ompp * scale, { noPoints: true, scale: scale });
    if (saved) P.shown = saved;
    // Contour des éléments choisis.
    P.sel.forEach(function (u) {
      var el = (drag3 && drag3.preview && drag3.preview[u]) || E.shownOf(u);
      if (!el || (el.geom === 'point' && el.kind !== 'fan')) return;
      var o = LG.outlineOf(el, 48).map(project);
      ctx.save(); ctx.lineJoin = 'round';
      ctx.beginPath(); o.forEach(function (s, i) { if (i) ctx.lineTo(s[0], s[1]); else ctx.moveTo(s[0], s[1]); }); if (el.geom !== 'line') ctx.closePath();
      ctx.strokeStyle = 'rgba(7,10,13,.8)'; ctx.lineWidth = 6 * scale; ctx.stroke();
      ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.6 * scale; ctx.setLineDash([7 * scale, 5 * scale]); ctx.stroke();
      ctx.restore();
    });
    // Analyse en cours (mesure, coupe, pied de la ligne de vue).
    if (A.pts.length && A.kind !== 'viewshed') {
      var col = A.kind === 'measure' ? '#f5b841' : (A.kind === 'profile' ? '#62b0f5' : '#e8eef2');
      ctx.save(); ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.2 * scale; ctx.setLineDash([6 * scale, 5 * scale]);
      ctx.beginPath(); A.pts.forEach(function (p, i) { var s = project(p); if (i) ctx.lineTo(s[0], s[1]); else ctx.moveTo(s[0], s[1]); }); ctx.stroke();
      A.pts.forEach(function (p) { var s = project(p); ctx.beginPath(); ctx.arc(s[0], s[1], 4 * scale, 0, 6.3); ctx.fill(); });
      ctx.restore();
    }
    if (A.kind === 'viewshed' && A.pts.length) { var o2 = project(A.pts[0]); ctx.save(); ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(o2[0], o2[1], 4 * scale, 0, 6.3); ctx.fill(); ctx.restore(); }
  }
  function sprites3d() {
    var out = [];
    var live = {};
    P.shown.forEach(function (s) {
      var d = (drag3 && drag3.preview && drag3.preview[s.uid]) || s;
      if (d.geom !== 'point' || d.kind === 'fan') return;
      var key = d.uid + '|' + d.kind + '|' + JSON.stringify(d.props, function (k, v) { return k === 'track' || k === 'note' ? undefined : v; });
      var pc = spriteCache[key] || (spriteCache[key] = R.pointCanvas(d, { catalog: E.CAT }));
      live[key] = true;
      out.push({ uid: d.uid, x: d.pts[0][0], y: d.pts[0][1], canvas: pc.canvas, anchorX: pc.anchorX, anchorY: pc.anchorY, selected: P.sel.indexOf(d.uid) >= 0, ghost: !!(P.phase && !P.briefing && !G.inPhase(d, P.phase)) });
    });
    Object.keys(spriteCache).forEach(function (k) { if (!live[k]) delete spriteCache[k]; });
    return out;
  }
  function invalidate3d(now) {
    if (!view3d || P.mode !== '3d') return;
    view3d.refreshSprites();
    clearTimeout(overlayTimer);
    overlayTimer = setTimeout(function () { if (view3d) view3d.invalidateOverlay(); }, now ? 0 : 90);
  }
  function stack3d(world) {
    var mpp = view3d.getView().mpp;
    return LG.pickStack(P.shown.filter(function (d) { return d.geom !== 'point' || d.kind === 'fan'; }), world, mpp * 9);
  }
  function note3d() {
    var n = $('#pe-3dnote');
    if (!n) return;
    var show = P.mode === '3d' && !E.dem && demState !== 'loading' && demState !== 'idle';
    n.hidden = !show;
    if (show) n.textContent = NO_RELIEF + ' La vue 3D reste à plat.';
  }
  function open3d() {
    if (loading3d) return;
    var box = $('#plan-3d');
    var show = function () {
      P.mode = '3d';
      box.hidden = false;
      $('#pe-3dctl').hidden = false;
      $$('[data-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === '3d')); });
      var c = E.tm.toWorld(E.tm.map.getCenter());
      view3d.resize();
      view3d.setAoi(planAoi());
      view3d.setView({ x: c[0], y: c[1], mpp: E.tm.metersPerPixel() });
      E.setTool('select');
      E.renderOverlay();
      invalidate3d(true);
      note3d();
      $('#plan-hint').textContent = 'Glisser : déplacer la vue · clic droit ou Ctrl+glisser : tourner et incliner · molette : rapprocher · clic : choisir · glisser un élément choisi : le déplacer sur le relief';
      box.focus({ preventScroll: true });
      E.emit('mode', '3d');
    };
    if (view3d) { show(); return; }
    if (demState === 'idle') loadDem();
    loading3d = true;
    E.say('Ouverture de la vue 3D…');
    import(E.BOOT.plan3dUrl).then(function (mod) {
      loading3d = false;
      if (!mod.isWebGLAvailable()) throw new Error('webgl_unavailable');
      box.hidden = false;
      view3d = mod.createPlan3D(box, {
        dem: E.dem || null, worldSize: E.tm.worldSize, aoi: planAoi(), getBaseCanvas: baseCanvas, drawOverlay: drawOverlay3d, sprites: sprites3d,
        view: (function () { var c = E.tm.toWorld(E.tm.map.getCenter()); return { x: c[0], y: c[1], mpp: E.tm.metersPerPixel(), pitch: 42 }; })(),
        exaggeration: Number($('#pe-3d-exag').value) || 1.5, sunHour: Number($('#pe-3d-sun').value) || 10,
        onPick: function (info) {
          var ev = info.originalEvent || {};
          if (P.briefing) return;
          if (P.tool !== 'select' && P.tool !== 'lasso' && P.tool !== 'pan') {
            if (!info.world) return;
            var g = (E.KINDS[P.armed && P.armed.kind ? P.armed.kind : P.tool] || {}).geoms;
            if (!E.toolHandlers[P.tool] && g && g[0] !== 'point') { E.say('Le tracé des traits et des zones se fait en vue 2D : la vue 3D pose les symboles, choisit et déplace.', true); return; }
            E.mapClick(info.world, ev);
            invalidate3d();
            return;
          }
          var uid = info.uid || (info.world ? stack3d(info.world)[0] : null);
          if (uid) E.select([uid], ev.shiftKey || ev.ctrlKey || ev.metaKey); else if (!ev.shiftKey) E.setSel([]);
        },
        onDragStart: function (info) {
          if (!P.canEdit || P.briefing || P.tool !== 'select' || !info.world) return false;
          var uid = info.uid || stack3d(info.world)[0];
          if (!uid) return false;
          if (P.sel.indexOf(uid) < 0) E.select([uid]);
          var els = E.selected();
          if (!els.length || !els.every(E.editable)) return false;
          drag3 = { els: els, w0: info.world, preview: null, last: 0 };
          return true;
        },
        onDrag: function (info) {
          if (!drag3 || !info.world) return;
          var dx = info.world[0] - drag3.w0[0], dy = info.world[1] - drag3.w0[1];
          drag3.dx = dx; drag3.dy = dy;
          drag3.preview = {};
          var shapes = false;
          drag3.els.forEach(function (el) { drag3.preview[el.uid] = LG.atTime(E.moveEl(el, dx, dy), P.time, P.els); if (el.geom !== 'point' || el.kind === 'fan') shapes = true; });
          view3d.refreshSprites();
          var now = Date.now();
          if (shapes && now - drag3.last > 160) { drag3.last = now; view3d.invalidateOverlay(); }
          $('#plan-measure').textContent = G.fmtDist(Math.sqrt(dx * dx + dy * dy));
        },
        onDragEnd: function (info) {
          var d = drag3;
          drag3 = null;
          $('#plan-measure').textContent = '';
          if (!d) return;
          if (info && info.cancelled || !(d.dx || d.dy)) { invalidate3d(true); return; }
          var m = {};
          d.els.forEach(function (el) { m[el.uid] = E.moveEl(el, d.dx, d.dy); });
          E.commit(m, d.els.length + ' élément(s) déplacé(s) de ' + G.fmtDist(Math.sqrt(d.dx * d.dx + d.dy * d.dy)));
        },
        onCursor: function (world, z) {
          if (!world) return;
          P.lastWorld = world;
          $('#plan-cursor').textContent = G.grid(world[0], world[1], 8);
          $('#plan-alt').textContent = E.dem && z != null ? 'alt. ' + Math.round(E.dem ? (LG.demSample(E.dem, world[0], world[1]) || 0) : z) + ' m' : '';
          var th = E.toolHandlers[P.tool];
          if (th && th.move) th.move(world, {});
        },
        onContext: function (info) {
          var uid = info.uid || (info.world ? stack3d(info.world)[0] : null);
          if (uid && P.sel.indexOf(uid) < 0) E.select([uid]);
          var ev = info.originalEvent || {};
          E.emit('context', { x: info.clientX != null ? info.clientX : ev.clientX, y: info.clientY != null ? info.clientY : ev.clientY, world: info.world, uid: uid || null });
        },
        onView: function (v) { if (lastOverlayMpp && (v.mpp > lastOverlayMpp * 1.7 || v.mpp < lastOverlayMpp / 1.7)) invalidate3d(); }
      });
      E.view3d = view3d;
      E.pick3d = function (x, y) { return view3d.pickWorld(x, y); };
      show();
    }).catch(function (err) {
      loading3d = false;
      box.hidden = true;
      P.mode = '2d';
      E.say(err && err.message === 'webgl_unavailable' ? 'Vue 3D indisponible : ce navigateur n’a pas WebGL. La carte reste en 2D.' : 'Vue 3D indisponible pour le moment. La carte reste en 2D.', true);
      if (window.console) console.error(err);
    });
  }
  function close3d() {
    if (P.mode !== '3d') return;
    var v = view3d ? view3d.getView() : null;
    P.mode = '2d';
    $('#plan-3d').hidden = true;
    $('#pe-3dctl').hidden = true;
    $('#pe-3dnote').hidden = true;
    $$('[data-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === '2d')); });
    if (v) {
      var cur = E.tm.metersPerPixel();
      E.tm.map.setView(E.tm.toLL([v.x, v.y]), E.tm.map.getZoom() + Math.log(cur / Math.max(0.05, v.mpp)) / Math.LN2, { animate: false });
    }
    E.setTool(P.tool);
    E.renderAll();
    renderAnalysis(null);
    E.emit('mode', '2d');
  }
  function toggle3d() { if (P.mode === '3d') close3d(); else open3d(); }

  /* ---------- Branchement ---------- */
  analysisTool('measure', 'Mesure : clics successifs pour la distance, l’azimut et la surface · Échap : effacer', 0, false);
  analysisTool('los', 'Ligne de vue : cliquez l’observateur, puis la cible', 2, true);
  analysisTool('profile', 'Coupe du relief : cliquez les points du tracé · Échap : effacer', 0, true);
  analysisTool('viewshed', 'Zone vue : cliquez le point d’observation', 1, true);
  E.action('slope', 'Teinte des pentes', toggleSlope, { group: 'Carte' });
  E.action('view3d', 'Vue 3D ou 2D', toggle3d, { key: '3', group: 'Carte' });
  E.action('north3d', 'Vue 3D : nord en haut', function () { if (view3d && P.mode === '3d') view3d.resetNorth(); }, { key: 'N', group: 'Carte' });

  E.on('init', function () {
    var zoom = function (f) { return function () { if (P.mode === '3d' && view3d) { var v = view3d.getView(); view3d.setView({ x: v.x, y: v.y, mpp: v.mpp * f }, { animate: true }); } else if (f < 1) E.tm.map.zoomIn(); else E.tm.map.zoomOut(); }; };
    E.ACTIONS.zoomIn.run = zoom(0.6);
    E.ACTIONS.zoomOut.run = zoom(1.65);
    var fit2d = E.ACTIONS.fit.run;
    E.ACTIONS.fit.run = function () {
      if (P.mode !== '3d' || !view3d) { fit2d(); return; }
      var a = planAoi();
      view3d.setAoi(a);
      view3d.setView({ x: (a.x0 + a.x1) / 2, y: (a.y0 + a.y1) / 2, mpp: (a.x1 - a.x0) / Math.max(400, $('#plan-3d').clientWidth) * 0.8 }, { animate: true });
    };
    $$('[data-view]').forEach(function (b) { b.addEventListener('click', function () { if (b.getAttribute('data-view') === '3d') open3d(); else close3d(); }); });
    $('#pe-3d-exag').addEventListener('input', function () { $('#pe-3d-exag-out').textContent = '×' + Number(this.value).toFixed(1).replace('.', ','); if (view3d) view3d.setExaggeration(Number(this.value)); });
    $('#pe-3d-sun').addEventListener('input', function () { var h = Number(this.value); $('#pe-3d-sun-out').textContent = (h < 10 ? '0' : '') + Math.floor(h) + ':' + (h % 1 ? String(Math.round(h % 1 * 60)) : '00'); if (view3d) view3d.setSunHour(h); });
    $('#pe-3d-north').addEventListener('click', function () { if (view3d) view3d.resetNorth(); });
    var ana = $('#pe-analysis');
    ana.addEventListener('click', function (e) {
      if (e.target.closest('[data-ana-clear]')) clearAnalysis(true);
      if (e.target.closest('[data-ana-close]')) { clearAnalysis(); E.setTool('select'); }
    });
    ana.addEventListener('change', function (e) {
      var k = e.target.getAttribute('data-ana');
      if (!k) return;
      var v = Number(e.target.value);
      if (!isFinite(v)) return;
      A[k] = k === 'radius' ? Math.max(100, Math.min(10000, v)) : Math.max(0, Math.min(500, v));
      renderAnalysis(null);
      invalidate3d();
    });
    document.addEventListener('click', function (e) {
      var ob = e.target.closest ? e.target.closest('[data-orbat-top]') : null;
      if (ob) { var d = ob.closest('details'); if (d) d.open = false; var b = E.$('#pe-left [data-orbat="' + ob.getAttribute('data-orbat-top') + '"]'); E.setLeftTab('orbat'); if (b) b.click(); }
    });
    var prevHook = E.keyHook;
    E.keyHook = function (e) {
      if (prevHook && prevHook(e)) return true;
      if (e.ctrlKey || e.metaKey || e.altKey) return false;
      var k = e.key.toLowerCase();
      if (k === '3') { toggle3d(); return true; }
      if (k === 'm') { E.setTool('measure'); return true; }
      if (k === 'o') { E.setTool('los'); return true; }
      if (k === 'w') { E.setTool('viewshed'); return true; }
      if (k === 'n' && P.mode === '3d' && view3d) { view3d.resetNorth(); return true; }
      return false;
    };
    window.addEventListener('resize', function () { if (view3d && P.mode === '3d') view3d.resize(); });
  });
  E.on('loaded', function () {
    var pane = E.tm.map.createPane('planAnalysis');
    pane.style.zIndex = 425;
    pane.style.pointerEvents = 'none';
    maskLayer = L.layerGroup().addTo(E.tm.map);
    anaLayer = L.layerGroup().addTo(E.tm.map);
    // Le relief ne presse pas : il est demandé une fois la carte affichée.
    setTimeout(loadDem, E.BOOT.demDelay != null ? E.BOOT.demDelay : 900);
  });
  E.on('cursor', function (p) {
    var el = $('#plan-alt');
    if (!el) return;
    var z = E.dem ? LG.demSample(E.dem, p[0], p[1]) : null;
    el.textContent = z != null ? 'alt. ' + Math.round(z) + ' m' : '';
  });
  E.on('render', function () { renderMasks(); if (P.mode === '3d') invalidate3d(); });
  E.on('selection', function () { if (P.mode === '3d') invalidate3d(); });
  E.on('background', function () { if (view3d) view3d.setBase(); });
  E.on('dem', function () { slopeCanvas = null; maskCache = {}; note3d(); if (A.kind) renderAnalysis(null); });
  E.on('tool', function () { if (!E.toolHandlers[P.tool] && A.kind && !A.pts.length) { A.kind = null; panel(''); } });
  E.open3d = open3d;
  E.close3d = close3d;
  E.analysis = A;
})();
