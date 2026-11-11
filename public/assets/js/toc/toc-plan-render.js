/* Dessin d'un élément de plan (et des graphismes du jeu convertis) en couches Leaflet : symbole OTAN (milsymbol du
 * site), traits avec pointe de flèche, zones, cercles, ellipses, textes, repères. Partagé par le TOC web et l'éditeur. */
window.TocPlanRender = (function () {
  'use strict';
  var G = window.TocGeo;
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  var DASH = { solid: null, dash: '10 7', dot: '2 6' };
  var AFFIL_COLOR = { F: '#6cb8ff', H: '#ff6b6b', N: '#7ee08a', U: '#f2d36b' };
  var ICON_PATHS = {
    flag: 'M6 3v18M6 4h11l-3 4 3 4H6', star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
    cross: 'M5 5l14 14M19 5L5 19', target: 'M12 3v4M12 17v4M3 12h4M17 12h4M12 8a4 4 0 1 0 0 8 4 4 0 1 0 0-8',
    triangle: 'M12 4l9 16H3z', dot: 'M12 8a4 4 0 1 0 0 8 4 4 0 1 0 0-8', square: 'M5 5h14v14H5z', diamond: 'M12 3l9 9-9 9-9-9z'
  };

  function unitSvg(props, catalog, size, stale) {
    var code = G.sidc(props, catalog && catalog.unitTypes);
    if (window.ms && window.ms.Symbol) {
      try {
        // Cadre au trait (comme les écrans du poste en jeu), couleur OTAN ; gris quand le nœud est perdu.
        var col = AFFIL_COLOR[props.affil] || AFFIL_COLOR.F;
        var opt = { size: size || 26, frame: true, fill: false, iconColor: col, outlineWidth: 3, outlineColor: 'rgba(7,10,13,0.85)', strokeWidth: 4 };
        if (stale) opt.monoColor = '#98a4ae';
        return new window.ms.Symbol(code, opt).asSVG();
      } catch (e) {}
    }
    var c = AFFIL_COLOR[props.affil] || '#6cb8ff';
    return '<svg width="30" height="22" viewBox="0 0 30 22"><rect x="1" y="1" width="28" height="20" fill="' + c + '" fill-opacity=".35" stroke="' + c + '" stroke-width="2"/><path d="M1 1L29 21M29 1L1 21" stroke="' + c + '" stroke-width="2"/></svg>';
  }

  function arrowHead(tm, a, b, sizeM, color, weight, pane) {
    var az = Math.atan2(b[0] - a[0], b[1] - a[1]);
    var l = [b[0] - sizeM * Math.sin(az - 0.45), b[1] - sizeM * Math.cos(az - 0.45)];
    var r = [b[0] - sizeM * Math.sin(az + 0.45), b[1] - sizeM * Math.cos(az + 0.45)];
    return L.polygon([tm.toLL(l), tm.toLL(b), tm.toLL(r)], { color: color, weight: weight, fillColor: color, fillOpacity: 1, interactive: false, pane: pane });
  }

  function labelMarker(tm, p, text, color, size, pane) {
    return L.marker(tm.toLL(p), {
      interactive: false, pane: pane, keyboard: false,
      icon: L.divIcon({ className: '', iconSize: null, iconAnchor: [0, 10], html: '<span style="font:700 ' + (size || 14) + 'px var(--sans);color:' + color + ';white-space:nowrap;text-transform:uppercase;text-shadow:0 0 3px #070a0d,0 0 3px #070a0d">' + esc(text) + '</span>' })
    });
  }

  /**
   * Couche Leaflet d'un élément. opts : catalog, opacity, pane, interactive, ghost (hors phase), onClick(el, ev).
   */
  function render(tm, el, opts) {
    opts = opts || {};
    var p = el.props || {};
    var color = p.color || '#f2f2f2';
    var weight = Math.max(1, Number(p.width) || 2);
    var op = (opts.opacity != null ? opts.opacity : 1) * (opts.ghost ? 0.25 : 1);
    var pane = opts.pane || 'overlayPane';
    var markerPane = opts.markerPane || 'markerPane';
    var grp = L.featureGroup();
    var style = { color: color, weight: weight, opacity: op, dashArray: DASH[p.dash] || null, fillColor: color, fillOpacity: (Number(p.fill) || 0) * op, interactive: opts.interactive !== false, pane: pane, bubblingMouseEvents: false };
    var label = el.kind === 'unit' ? (p.callsign || p.label || '') : (p.label || '');
    var mpp = tm.metersPerPixel ? tm.metersPerPixel() : 5;
    if (el.geom === 'point') {
      var html;
      var anchor;
      if (el.kind === 'unit') {
        html = '<span class="toc-unit-icon' + (opts.stale ? ' is-lost' : '') + '" style="opacity:' + op + '">' + unitSvg(p, opts.catalog, p.size || 26, opts.stale) + (label ? '<span class="toc-plate' + (p.affil === 'H' ? ' is-hostile' : '') + (opts.stale ? ' is-lost' : '') + (opts.plateClass ? ' ' + opts.plateClass : '') + '">' + esc(label) + '</span>' : '') + '</span>';
        anchor = [14, 14];
      } else if (el.kind === 'text') {
        html = '<span style="display:inline-block;opacity:' + op + ';transform:rotate(' + (Number(p.rotation) || 0) + 'deg);transform-origin:0 50%;font:700 ' + (p.size || 18) + 'px var(--sans);color:' + color + ';white-space:nowrap;text-shadow:0 0 3px #070a0d,0 0 3px #070a0d,0 0 3px #070a0d">' + esc(p.label) + '</span>';
        anchor = [0, (p.size || 18) / 2];
      } else {
        var path = ICON_PATHS[p.icon] || ICON_PATHS.dot;
        var sz = p.size || 24;
        var tag = { checkpoint: 'CP', rally: 'RP', trp: 'TRP', objpt: 'OBJ', cleared: '✓', uncleared: '?' }[el.kind];
        var icon = tag && el.kind !== 'icon'
          ? '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="rgba(7,10,13,.6)" stroke="' + color + '" stroke-width="2"/><text x="12" y="16" text-anchor="middle" font-size="' + (tag.length > 2 ? 7 : 9) + '" fill="' + color + '" style="stroke:none">' + esc(tag) + '</text></svg>'
          : '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24"><path d="' + path + '" fill="none" stroke="' + color + '" stroke-width="2.2" stroke-linejoin="round"/></svg>';
        html = '<span class="toc-unit-icon" style="opacity:' + op + '">' + icon + (label ? '<span class="toc-plate" style="border-color:' + color + '">' + esc(label) + '</span>' : '') + '</span>';
        anchor = [sz / 2, sz / 2];
      }
      var m = L.marker(tm.toLL(el.pts[0]), { pane: markerPane, interactive: opts.interactive !== false, keyboard: false, icon: L.divIcon({ className: '', html: html, iconSize: null, iconAnchor: anchor }) });
      grp.addLayer(m);
    } else if (el.geom === 'line') {
      var lls = el.pts.map(tm.toLL);
      grp.addLayer(L.polyline(lls, style));
      var head = Math.max(25, Math.min(400, 14 * mpp + weight * mpp));
      var n = el.pts.length;
      if ((p.arrow === 'end' || p.arrow === 'both') && n >= 2) grp.addLayer(arrowHead(tm, el.pts[n - 2], el.pts[n - 1], head, color, weight, pane));
      if (p.arrow === 'both' && n >= 2) grp.addLayer(arrowHead(tm, el.pts[1], el.pts[0], head, color, weight, pane));
      if (el.kind === 'axis' || el.kind === 'attack') {
        // Axe / attaque : double trait (deux bords) en plus du tracé central atténué.
        grp.addLayer(L.polyline(lls, L.extend({}, style, { weight: weight * 4, opacity: op * 0.22, interactive: false })));
      }
      if (label) {
        var mid = el.pts[Math.floor((n - 1) / 2)];
        var nxt = el.pts[Math.min(n - 1, Math.floor((n - 1) / 2) + 1)];
        grp.addLayer(labelMarker(tm, [(mid[0] + nxt[0]) / 2, (mid[1] + nxt[1]) / 2], label, color, 14, markerPane));
      }
      if (el.kind === 'phase_line' || el.kind === 'ld' || el.kind === 'loa') {
        if (label) grp.addLayer(labelMarker(tm, el.pts[0], label, color, 14, markerPane));
      }
    } else {
      var ring = G.outline(el, 64).map(tm.toLL);
      grp.addLayer(L.polygon(ring, style));
      if (el.kind === 'minefield') {
        var c0 = G.centroid(el);
        grp.addLayer(labelMarker(tm, c0, 'o o o', color, 16, markerPane));
      }
      if (label) grp.addLayer(labelMarker(tm, G.centroid(el), label, color, 15, markerPane));
    }
    if (opts.onClick) grp.on('click', function (ev) { opts.onClick(el, ev); });
    if (opts.tooltip) grp.bindTooltip(opts.tooltip, { className: 'toc-tip', sticky: true });
    return grp;
  }

  return { render: render, unitSvg: unitSvg, esc: esc };
})();
