/* Dessin d'un élément de plan (et des graphismes du jeu convertis) : symbole OTAN (milsymbol du site), traits avec
 * pointe de flèche, axes avec leur largeur, zones, cercles et éventails de portée, textes, repères.
 * Un élément est d'abord décomposé en primitives (traits, polygones, libellés), puis posé soit en couches Leaflet
 * (carte 2D du TOC web et de l'éditeur), soit sur un canvas (export image, vue 3D). Partagé par le TOC web et l'éditeur. */
window.TocPlanRender = (function () {
  'use strict';
  var G = window.TocGeo;
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  var DASH = { solid: null, dash: '10 7', dot: '2 6' };
  var DASH_PX = { solid: [], dash: [10, 7], dot: [2, 6] };
  var AFFIL_COLOR = { F: '#6cb8ff', H: '#ff6b6b', N: '#7ee08a', U: '#f2d36b' };
  var ICON_PATHS = {
    flag: 'M6 3v18M6 4h11l-3 4 3 4H6', star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
    cross: 'M5 5l14 14M19 5L5 19', target: 'M12 3v4M12 17v4M3 12h4M17 12h4M12 8a4 4 0 1 0 0 8 4 4 0 1 0 0-8',
    triangle: 'M12 4l9 16H3z', dot: 'M12 8a4 4 0 1 0 0 8 4 4 0 1 0 0-8', square: 'M5 5h14v14H5z', diamond: 'M12 3l9 9-9 9-9-9z'
  };
  var POINT_TAGS = { checkpoint: 'CP', rally: 'RP', trp: 'TRP', objpt: 'OBJ', cleared: '✓', uncleared: '?', medevac: '+', ccp: 'CCP', supply: 'RAV', op: 'OP', target: 'TGT', decon: 'DEC' };
  var ECHELON_MARK = { team: 'Ø', squad: '●', section: '●●', platoon: '●●●', company: 'I', battalion: 'II', regiment: 'III', brigade: 'X' };
  var BOTH_ENDS = { phase_line: 1, ld: 1, loa: 1, fscl: 1, cfl: 1, rfl: 1, flot: 1 };
  var symCache = {};

  /** Symbole OTAN d'une unité : { svg, canvas(), anchor: {x, y}, w, h }. Mis en mémoire par apparence. */
  function unitSymbol(props, catalog, size, stale) {
    var code = G.sidc(props, catalog && catalog.unitTypes);
    var extra = G.symbolOptions ? G.symbolOptions(props) : {};
    var key = code + '|' + (size || 26) + '|' + (stale ? 1 : 0) + '|' + JSON.stringify(extra);
    if (symCache[key]) return symCache[key];
    var out = null;
    if (window.ms && window.ms.Symbol) {
      try {
        // Cadre au trait (comme les écrans du poste en jeu), couleur OTAN ; gris quand le nœud est perdu.
        var aff = code.charAt(1);
        var col = AFFIL_COLOR[aff] || AFFIL_COLOR[props.affil] || AFFIL_COLOR.F;
        var opt = { size: size || 26, frame: true, fill: false, iconColor: col, outlineWidth: 3, outlineColor: 'rgba(7,10,13,0.85)', strokeWidth: 4, infoColor: '#e8eef2', infoSize: 34 };
        Object.keys(extra).forEach(function (k) { opt[k] = extra[k]; });
        if (stale) opt.monoColor = '#98a4ae';
        var sym = new window.ms.Symbol(code, opt);
        var an = sym.getAnchor(), sz = sym.getSize();
        out = { svg: sym.asSVG(), anchor: { x: an.x, y: an.y }, w: sz.width, h: sz.height, valid: typeof sym.isValid === 'function' ? sym.isValid() : true, canvas: function () { return sym.asCanvas(); } };
      } catch (e) { out = null; }
    }
    if (!out) {
      var c = AFFIL_COLOR[props.affil] || '#6cb8ff';
      out = { svg: '<svg width="30" height="22" viewBox="0 0 30 22"><rect x="1" y="1" width="28" height="20" fill="' + c + '" fill-opacity=".35" stroke="' + c + '" stroke-width="2"/><path d="M1 1L29 21M29 1L1 21" stroke="' + c + '" stroke-width="2"/></svg>', anchor: { x: 15, y: 11 }, w: 30, h: 22, valid: false, canvas: null };
    }
    symCache[key] = out;
    return out;
  }
  function unitSvg(props, catalog, size, stale) { return unitSymbol(props, catalog, size, stale).svg; }

  /**
   * Décomposition d'un élément en primitives, en mètres Arma :
   *   { t: 'line', pts, closed, color, width, dash, fill, opacity, under }   trait ou polygone
   *   { t: 'head', a, b, size, color, opacity }                              pointe de flèche (de a vers b)
   *   { t: 'label', p, text, size, color, align: 'left' | 'center', box }    libellé
   * o : { mpp (mètres par pixel), catalog }. Les éléments ponctuels (unité, repère, texte) ne sont pas décomposés.
   */
  function primitives(el, o) {
    o = o || {};
    var p = el.props || {};
    var color = p.color || '#f2f2f2';
    var weight = Math.max(1, Number(p.width) || 2);
    var op = p.opacity != null ? Math.max(0.1, Math.min(1, Number(p.opacity))) : 1;
    var mpp = o.mpp || 5;
    var out = [];
    var label = p.label || '';
    var lsize = Math.max(10, Math.min(32, Number(p.labelSize) || 14));
    var lpos = p.labelPos || 'auto';
    var line = function (pts, extra) {
      var l = { t: 'line', pts: pts, closed: false, color: color, width: weight, dash: p.dash || 'solid', fill: 0, opacity: op };
      Object.keys(extra || {}).forEach(function (k) { l[k] = extra[k]; });
      out.push(l);
    };
    var text = function (pt, txt, extra) {
      if (!txt) return;
      var l = { t: 'label', p: pt, text: String(txt), size: lsize, color: color, align: 'left', opacity: op };
      Object.keys(extra || {}).forEach(function (k) { l[k] = extra[k]; });
      out.push(l);
    };
    var headSize = Math.max(25, Math.min(400, 14 * mpp + weight * mpp));
    if (el.kind === 'fan') {
      line(G.fanOutline(el, 64), { closed: true, fill: p.fill != null ? Number(p.fill) : 0.1 });
      var b = (Number(p.bearing) || 0) * Math.PI / 180, r = Number(p.radius) || 1000;
      if (lpos !== 'none') text([el.pts[0][0] + Math.sin(b) * r * 0.55, el.pts[0][1] + Math.cos(b) * r * 0.55], (label ? label + ' · ' : '') + G.fmtDist(r), { align: 'center' });
      return out;
    }
    if (el.geom === 'line') {
      var pts = el.pts, n = pts.length;
      if (el.kind === 'textbox') {
        line([pts[0], pts[1]], { width: Math.max(1, weight - 1), dash: 'solid' });
        out.push({ t: 'dot', p: pts[0], r: 3, color: color, opacity: op });
        text(pts[1], label, { box: true, align: 'center', size: Math.max(lsize, 13) });
        return out;
      }
      if ((el.kind === 'axis' || el.kind === 'attack') && Number(p.awidth) > 0 && n >= 2) {
        line(G.axisOutline(pts, Number(p.awidth)));
      } else {
        line(pts);
        if ((p.arrow === 'end' || p.arrow === 'both') && n >= 2) out.push({ t: 'head', a: pts[n - 2], b: pts[n - 1], size: headSize, color: color, opacity: op, width: weight });
        if (p.arrow === 'both' && n >= 2) out.push({ t: 'head', a: pts[1], b: pts[0], size: headSize, color: color, opacity: op, width: weight });
        // Axe / attaque sans largeur réglée : halo large sous le tracé.
        if (el.kind === 'axis' || el.kind === 'attack') line(pts, { width: weight * 4, opacity: op * 0.22, dash: 'solid', under: true });
      }
      if (el.kind === 'obstacle') {
        // Ligne d'obstacles : petites dents régulières d'un côté du trait.
        var step = Math.max(20, 26 * mpp), total = G.pathLength(pts), tooth = Math.max(8, 9 * mpp);
        for (var s = step / 2, k = 0; s < total && k < 160; s += step, k++) {
          var a0 = pointAt(pts, s), a1 = pointAt(pts, Math.min(total, s + 1));
          var dx = a1[0] - a0[0], dy = a1[1] - a0[1], dl = Math.sqrt(dx * dx + dy * dy) || 1;
          line([a0, [a0[0] - dy / dl * tooth, a0[1] + dx / dl * tooth]], { dash: 'solid' });
        }
      }
      var mid = pointAt(pts, G.pathLength(pts) / 2);
      if (el.kind === 'boundary' && ECHELON_MARK[p.echelon]) text(mid, ECHELON_MARK[p.echelon], { align: 'center', box: true, size: Math.max(lsize, 14) });
      if (el.kind === 'route' && p.cp && n > 2) {
        var times = Number(p.speed) > 0 ? cumulative(pts, Number(p.speed)) : null;
        for (var i = 1; i < n - 1; i++) text(pts[i], 'CP' + i + (times ? ' · +' + Math.round(times[i]) + ' min' : ''), { size: 13, box: true });
        if (times) text(pts[n - 1], '+' + Math.round(times[n - 1]) + ' min', { size: 13, box: true });
      }
      if (label && lpos !== 'none') {
        var auto = lpos === 'auto';
        if ((auto && BOTH_ENDS[el.kind]) || lpos === 'start') text(pts[0], label);
        if ((auto && BOTH_ENDS[el.kind]) || lpos === 'end') text(pts[n - 1], label);
        if (auto || lpos === 'middle') {
          var m0 = pts[Math.floor((n - 1) / 2)], m1 = pts[Math.min(n - 1, Math.floor((n - 1) / 2) + 1)];
          text([(m0[0] + m1[0]) / 2, (m0[1] + m1[1]) / 2], label);
        }
      }
      return out;
    }
    if (el.geom === 'point') return out;
    var ring = G.outline(el, 64);
    line(ring, { closed: true, fill: Number(p.fill) || 0 });
    var c = G.centroid(el);
    if (el.kind === 'ring' && el.geom === 'circle') {
      var rings = Math.max(1, Math.min(5, parseInt(p.rings, 10) || 1)), rad = Number(p.radius) || 100;
      for (var j = 1; j <= rings; j++) {
        var rr = rad * j / rings;
        if (j < rings) line(G.outline({ geom: 'circle', pts: el.pts, props: { radius: rr } }, 64), { closed: true, dash: 'dash', width: Math.max(1, weight - 1) });
        text([c[0], c[1] + rr], G.fmtDist(rr), { align: 'center', size: 13, box: true });
      }
      out.push({ t: 'dot', p: c, r: 3, color: color, opacity: op });
    }
    if (el.kind === 'minefield') text(c, 'o o o', { align: 'center', size: 16 });
    if (label && lpos !== 'none') {
      var at = el.kind === 'minefield' ? [c[0], c[1] - 22 * mpp] : (el.kind === 'ring' ? [c[0], c[1] - 16 * mpp] : c);
      // Zone d'opération : le nom se lit sur le bord nord, pas au milieu du plan.
      if (el.kind === 'ao') { var bb = G.bbox(ring); at = [c[0], bb[3] - 14 * mpp]; }
      text(at, label, { align: 'center', size: Math.max(lsize, 15) });
    }
    return out;
  }
  function pointAt(pts, s) {
    for (var i = 1; i < pts.length; i++) {
      var d = G.dist(pts[i - 1], pts[i]);
      if (s <= d && d > 0) return [pts[i - 1][0] + (pts[i][0] - pts[i - 1][0]) * s / d, pts[i - 1][1] + (pts[i][1] - pts[i - 1][1]) * s / d];
      s -= d;
    }
    return pts[pts.length - 1];
  }
  function cumulative(pts, kmh) {
    var out = [0], acc = 0;
    for (var i = 1; i < pts.length; i++) { acc += G.dist(pts[i - 1], pts[i]); out.push(acc / 1000 / kmh * 60); }
    return out;
  }
  function headPoints(a, b, sizeM) {
    var az = Math.atan2(b[0] - a[0], b[1] - a[1]);
    return [[b[0] - sizeM * Math.sin(az - 0.45), b[1] - sizeM * Math.cos(az - 0.45)], b, [b[0] - sizeM * Math.sin(az + 0.45), b[1] - sizeM * Math.cos(az + 0.45)]];
  }

  function labelHtml(l) {
    var base = 'font:700 ' + l.size + 'px var(--sans);color:' + l.color + ';white-space:nowrap;text-transform:uppercase;opacity:' + (l.opacity == null ? 1 : l.opacity) + ';';
    if (l.box) return '<span style="' + base + 'background:rgba(7,10,13,.82);padding:1px 6px;border:1px solid ' + l.color + ';display:inline-block;' + (l.align === 'center' ? 'transform:translate(-50%,-50%)' : 'transform:translateY(-50%)') + '">' + esc(l.text) + '</span>';
    return '<span style="' + base + 'text-shadow:0 0 3px #070a0d,0 0 3px #070a0d,0 0 4px #070a0d;display:inline-block;' + (l.align === 'center' ? 'transform:translate(-50%,-50%)' : 'transform:translateY(-50%)') + '">' + esc(l.text) + '</span>';
  }

  /** Contenu HTML d'un élément ponctuel (unité, texte, repère) et son point d'ancrage en pixels. */
  function pointHtml(el, opts) {
    var p = el.props || {};
    var color = p.color || '#f2f2f2';
    var op = (opts.opacity != null ? opts.opacity : 1) * (opts.ghost ? 0.25 : 1) * (p.opacity != null ? Number(p.opacity) : 1);
    var label = el.kind === 'unit' ? (p.callsign || p.label || '') : (p.label || '');
    if (el.kind === 'unit') {
      var sym = unitSymbol(p, opts.catalog, p.size || 26, opts.stale);
      return { html: '<span class="toc-unit-icon' + (opts.stale ? ' is-lost' : '') + '" style="opacity:' + op + '">' + sym.svg + (label ? '<span class="toc-plate' + (p.affil === 'H' ? ' is-hostile' : '') + (opts.stale ? ' is-lost' : '') + (opts.plateClass ? ' ' + opts.plateClass : '') + '"' + (p.affil === 'N' || p.affil === 'U' ? ' style="border-color:' + AFFIL_COLOR[p.affil] + '"' : '') + '>' + esc(label) + '</span>' : '') + '</span>', anchor: [sym.anchor.x, sym.anchor.y], w: sym.w, h: sym.h };
    }
    if (el.kind === 'text') {
      var ts = p.size || 18;
      return { html: '<span style="display:inline-block;opacity:' + op + ';transform:rotate(' + (Number(p.rotation) || 0) + 'deg);transform-origin:0 50%;font:700 ' + ts + 'px var(--sans);color:' + color + ';white-space:nowrap;text-shadow:0 0 3px #070a0d,0 0 3px #070a0d,0 0 3px #070a0d">' + esc(p.label) + '</span>', anchor: [0, ts / 2], w: Math.max(20, String(p.label || '').length * ts * 0.5), h: ts * 1.3 };
    }
    var sz = p.size || 24;
    var tag = el.kind !== 'icon' ? POINT_TAGS[el.kind] : null;
    var icon = tag
      ? '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="rgba(7,10,13,.6)" stroke="' + color + '" stroke-width="2"/><text x="12" y="' + (tag.length === 1 ? 17 : 16) + '" text-anchor="middle" font-size="' + (tag.length > 2 ? 7 : (tag.length === 1 ? 14 : 9)) + '" font-weight="700" fill="' + color + '" style="stroke:none">' + esc(tag) + '</text></svg>'
      : '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24"><path d="' + (ICON_PATHS[p.icon] || ICON_PATHS.dot) + '" fill="none" stroke="' + color + '" stroke-width="2.2" stroke-linejoin="round"/></svg>';
    return { html: '<span class="toc-unit-icon" style="opacity:' + op + '">' + icon + (label ? '<span class="toc-plate" style="border-color:' + color + '">' + esc(label) + '</span>' : '') + '</span>', anchor: [sz / 2, sz / 2], w: sz, h: sz };
  }

  /**
   * Couche Leaflet d'un élément. opts : catalog, opacity, pane, markerPane, interactive, ghost (hors phase), onClick(el, ev), tooltip.
   */
  function render(tm, el, opts) {
    opts = opts || {};
    var op = (opts.opacity != null ? opts.opacity : 1) * (opts.ghost ? 0.25 : 1);
    var pane = opts.pane || 'overlayPane';
    var markerPane = opts.markerPane || 'markerPane';
    var inter = opts.interactive !== false;
    var grp = L.featureGroup();
    var mpp = tm.metersPerPixel ? tm.metersPerPixel() : 5;
    if (el.geom === 'point' && el.kind !== 'fan') {
      var ph = pointHtml(el, opts);
      grp.addLayer(L.marker(tm.toLL(el.pts[0]), { pane: markerPane, interactive: inter, keyboard: false, icon: L.divIcon({ className: '', html: ph.html, iconSize: null, iconAnchor: ph.anchor }) }));
    } else {
      var prims = primitives(el, { mpp: mpp, catalog: opts.catalog });
      // Le halo passe sous le tracé.
      prims.sort(function (a, b) { return (b.under ? 1 : 0) - (a.under ? 1 : 0); });
      prims.forEach(function (q) {
        if (q.t === 'line') {
          var st = { color: q.color, weight: q.width, opacity: q.opacity * op, dashArray: DASH[q.dash] || null, fillColor: q.color, fillOpacity: q.fill * op, fill: q.closed, interactive: inter && !q.under, pane: pane, bubblingMouseEvents: false };
          grp.addLayer(q.closed ? L.polygon(q.pts.map(tm.toLL), st) : L.polyline(q.pts.map(tm.toLL), st));
        } else if (q.t === 'head') {
          grp.addLayer(L.polygon(headPoints(q.a, q.b, q.size).map(tm.toLL), { color: q.color, weight: q.width, opacity: q.opacity * op, fillColor: q.color, fillOpacity: q.opacity * op, interactive: false, pane: pane }));
        } else if (q.t === 'dot') {
          grp.addLayer(L.circleMarker(tm.toLL(q.p), { radius: q.r, color: q.color, fillColor: q.color, fillOpacity: op, opacity: op, weight: 1, interactive: false, pane: pane }));
        } else if (q.t === 'label') {
          q.opacity = (q.opacity == null ? 1 : q.opacity) * op;
          grp.addLayer(L.marker(tm.toLL(q.p), { interactive: false, pane: markerPane, keyboard: false, icon: L.divIcon({ className: '', iconSize: null, iconAnchor: [0, 0], html: labelHtml(q) }) }));
        }
      });
    }
    if (opts.onClick) grp.on('click', function (ev) { opts.onClick(el, ev); });
    if (opts.tooltip) grp.bindTooltip(opts.tooltip, { className: 'toc-tip', sticky: true });
    return grp;
  }

  /* ---------- Canvas (export image, vue 3D) ---------- */
  function canvasLabel(ctx, x, y, l, scale) {
    var size = l.size * (scale || 1);
    ctx.save();
    ctx.globalAlpha = l.opacity == null ? 1 : l.opacity;
    ctx.font = '700 ' + size + 'px "Roboto Condensed", "Arial Narrow", Arial, sans-serif';
    ctx.textBaseline = 'middle';
    var txt = String(l.text).toUpperCase();
    var w = ctx.measureText(txt).width;
    var x0 = l.align === 'center' ? x - w / 2 : x;
    ctx.setLineDash([]);
    if (l.box) {
      var padX = 6 * (scale || 1), hh = size * 0.72;
      ctx.fillStyle = 'rgba(7,10,13,.82)';
      ctx.fillRect(x0 - padX, y - hh, w + padX * 2, hh * 2);
      ctx.strokeStyle = l.color; ctx.lineWidth = Math.max(1, scale || 1);
      ctx.strokeRect(x0 - padX, y - hh, w + padX * 2, hh * 2);
    } else {
      ctx.lineJoin = 'round'; ctx.lineWidth = Math.max(3, size / 4); ctx.strokeStyle = 'rgba(7,10,13,.9)';
      ctx.strokeText(txt, x0, y);
    }
    ctx.fillStyle = l.color;
    ctx.fillText(txt, x0, y);
    ctx.restore();
  }

  /**
   * Trace sur un canvas les graphismes plats d'un élément (traits, zones, libellés). project([x, y]) => [px, py].
   * o : { mpp, catalog, scale (épaisseurs et textes), opacity, noLabels }. Les éléments ponctuels : voir pointCanvas.
   */
  function drawCanvas(ctx, project, el, o) {
    o = o || {};
    var scale = o.scale || 1;
    var op = o.opacity == null ? 1 : o.opacity;
    var prims = primitives(el, { mpp: o.mpp, catalog: o.catalog });
    prims.sort(function (a, b) { return (b.under ? 1 : 0) - (a.under ? 1 : 0); });
    prims.forEach(function (q) {
      if (q.t === 'line') {
        ctx.save();
        ctx.beginPath();
        q.pts.forEach(function (pt, i) { var s = project(pt); if (i) ctx.lineTo(s[0], s[1]); else ctx.moveTo(s[0], s[1]); });
        if (q.closed) ctx.closePath();
        if (q.closed && q.fill > 0) { ctx.globalAlpha = q.fill * op; ctx.fillStyle = q.color; ctx.fill(); }
        ctx.globalAlpha = q.opacity * op;
        ctx.lineJoin = 'round'; ctx.lineCap = 'round';
        // Liseré sombre sous le trait : lisible sur la photo satellite.
        if (!q.under && o.halo !== false) { ctx.setLineDash([]); ctx.strokeStyle = 'rgba(7,10,13,.55)'; ctx.lineWidth = (q.width + 2) * scale; ctx.stroke(); }
        ctx.setLineDash((DASH_PX[q.dash] || []).map(function (v) { return v * scale; }));
        ctx.strokeStyle = q.color; ctx.lineWidth = q.width * scale;
        ctx.stroke();
        ctx.restore();
      } else if (q.t === 'head') {
        var h = headPoints(q.a, q.b, q.size).map(project);
        ctx.save();
        ctx.globalAlpha = q.opacity * op; ctx.fillStyle = q.color;
        ctx.beginPath(); ctx.moveTo(h[0][0], h[0][1]); ctx.lineTo(h[1][0], h[1][1]); ctx.lineTo(h[2][0], h[2][1]); ctx.closePath(); ctx.fill();
        ctx.restore();
      } else if (q.t === 'dot') {
        var d = project(q.p);
        ctx.save(); ctx.globalAlpha = op; ctx.fillStyle = q.color; ctx.beginPath(); ctx.arc(d[0], d[1], q.r * scale, 0, Math.PI * 2); ctx.fill(); ctx.restore();
      } else if (q.t === 'label' && !o.noLabels) {
        var s2 = project(q.p);
        canvasLabel(ctx, s2[0], s2[1], { text: q.text, size: q.size, color: q.color, align: q.align, box: q.box, opacity: (q.opacity == null ? 1 : q.opacity) * op }, scale);
      }
    });
  }

  /**
   * Élément ponctuel sur un canvas à lui : { canvas, anchorX, anchorY } (point au sol), symbole et plaque du libellé.
   * Sert aux étiquettes dressées de la vue 3D et à l'export image.
   */
  function pointCanvas(el, o) {
    o = o || {};
    var p = el.props || {};
    var color = p.color || '#f2f2f2';
    var label = el.kind === 'unit' ? (p.callsign || p.label || '') : (el.kind === 'text' ? '' : (p.label || ''));
    var cv = document.createElement('canvas');
    var ctx = cv.getContext('2d');
    var font = '700 14px "Roboto Condensed", "Arial Narrow", Arial, sans-serif';
    ctx.font = font;
    var lw = label ? Math.ceil(ctx.measureText(label.toUpperCase()).width) + 14 : 0;
    var symC = null, sw = 0, sh = 0, ax = 0, ay = 0;
    if (el.kind === 'unit') {
      var sym = unitSymbol(p, o.catalog, p.size || 26, false);
      symC = sym.canvas ? sym.canvas() : null;
      sw = symC ? symC.width : 30; sh = symC ? symC.height : 22;
      // milsymbol rend son canvas à la densité de l'écran : ramené en pixels CSS.
      var ratio = symC && sym.w ? symC.width / sym.w : 1;
      sw = Math.ceil(sw / ratio); sh = Math.ceil(sh / ratio);
      ax = sym.anchor.x; ay = sym.anchor.y;
    } else if (el.kind === 'text') {
      var ts = p.size || 18;
      ctx.font = '700 ' + ts + 'px "Roboto Condensed", "Arial Narrow", Arial, sans-serif';
      sw = Math.ceil(ctx.measureText(String(p.label || '')).width) + 8; sh = Math.ceil(ts * 1.5); ax = 0; ay = sh / 2;
    } else {
      sw = sh = p.size || 24; ax = sw / 2; ay = sh / 2;
    }
    var pad = 4;
    cv.width = sw + (lw ? lw + 6 : 0) + pad * 2;
    cv.height = Math.max(sh, 22) + pad * 2;
    ctx = cv.getContext('2d');
    var oy = pad + (cv.height - pad * 2 - sh) / 2;
    if (o.opacity != null) ctx.globalAlpha = o.opacity;
    if (el.kind === 'unit') {
      if (symC) ctx.drawImage(symC, pad, oy, sw, sh);
      else { ctx.strokeStyle = AFFIL_COLOR[p.affil] || '#6cb8ff'; ctx.lineWidth = 2; ctx.strokeRect(pad + 1, oy + 1, sw - 2, sh - 2); }
    } else if (el.kind === 'text') {
      canvasLabel(ctx, pad + 4, cv.height / 2, { text: p.label || '', size: p.size || 18, color: color, align: 'left' }, 1);
    } else {
      var tag = el.kind !== 'icon' ? POINT_TAGS[el.kind] : null;
      ctx.save();
      ctx.translate(pad, oy); ctx.scale(sw / 24, sh / 24);
      ctx.strokeStyle = color; ctx.lineWidth = 2.2; ctx.lineJoin = 'round';
      if (tag) {
        ctx.fillStyle = 'rgba(7,10,13,.6)'; ctx.beginPath(); ctx.arc(12, 12, 9, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        ctx.fillStyle = color; ctx.font = '700 ' + (tag.length > 2 ? 7 : (tag.length === 1 ? 14 : 9)) + 'px Arial, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(tag, 12, 12.5);
      } else {
        try { ctx.stroke(new Path2D(ICON_PATHS[p.icon] || ICON_PATHS.dot)); } catch (e) { ctx.strokeRect(5, 5, 14, 14); }
      }
      ctx.restore();
    }
    if (lw) {
      var lx = pad + sw + 6, lh = 20, ly = cv.height / 2 - lh / 2;
      ctx.fillStyle = 'rgba(7,10,13,.85)'; ctx.fillRect(lx, ly, lw, lh);
      ctx.fillStyle = el.kind === 'unit' ? (AFFIL_COLOR[p.affil] || AFFIL_COLOR.F) : color; ctx.fillRect(lx, ly, 3, lh);
      ctx.fillStyle = '#e8eef2'; ctx.font = font; ctx.textBaseline = 'middle'; ctx.textAlign = 'left';
      ctx.fillText(label.toUpperCase(), lx + 8, cv.height / 2 + 1);
    }
    return { canvas: cv, anchorX: pad + ax, anchorY: oy + ay, w: cv.width, h: cv.height };
  }

  return { render: render, unitSvg: unitSvg, unitSymbol: unitSymbol, esc: esc, primitives: primitives, drawCanvas: drawCanvas, pointCanvas: pointCanvas, pointHtml: pointHtml, headPoints: headPoints, AFFIL_COLOR: AFFIL_COLOR, ECHELON_MARK: ECHELON_MARK };
})();
