/* Poste TOC web : carte d'un terrain Arma (Leaflet du site, repère en mètres Arma), fonds (Satellite = nos tuiles
 * « Photo COMSPEC », Carte du jeu, fond sombre), quadrillage numéroté, échelle, nord et grille sous le curseur.
 * Fond Satellite / Carte : pyramide de tuiles posées en mètres Arma, chargées à la demande ; une tuile absente laisse
 * voir la carte du jeu dessous (repli). Dépend de Leaflet, atak-map-crs.js et toc-geo.js. */
window.TocMap = (function () {
  'use strict';
  var G = window.TocGeo;

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function findFond(cfg, id) {
    var list = (cfg && cfg.fondLayers) || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id && list[i].spec) return list[i];
    return null;
  }

  /** Fonds proposés : Satellite (nos tuiles, sinon photo aérienne), Carte du jeu (plan capturé, sinon tuiles du jeu), Sombre. */
  function backgrounds(cfg) {
    var sat = findFond(cfg, 'photo-comspec');
    var satFallback = sat ? null : findFond(cfg, 'aerial');
    var topo = findFond(cfg, 'plan-comspec');
    return [
      { id: 'sat', label: 'Satellite', spec: sat ? sat.spec : (satFallback ? satFallback.spec : null), ours: !!sat,
        note: sat ? 'Photo COMSPEC (nos tuiles)' : (satFallback ? 'Photo aérienne : nos tuiles ne sont pas encore publiées pour ce terrain' : 'Nos tuiles ne sont pas encore publiées pour ce terrain : carte du jeu affichée') },
      { id: 'topo', label: 'Carte du jeu', spec: topo ? topo.spec : null, ours: !!topo, note: topo ? 'Plan COMSPEC (capture du jeu)' : 'Tuiles de la carte du jeu' },
      { id: 'dark', label: 'Sombre', spec: null, ours: false, note: 'Fond neutre, quadrillage seul' }
    ];
  }

  function create(container, terrain, opts) {
    opts = opts || {};
    var cfg = terrain.config || {};
    var crsCfg = cfg.crs || { factorx: 0.006839, factory: 0.006836, tileWidth: 212 };
    var ox = Number(cfg.offsetX) || 0, oy = Number(cfg.offsetY) || 0;
    var world = Number(cfg.worldSize) || 30720;
    var crs = window.MGRS_CRS(Number(crsCfg.factorx), Number(crsCfg.factory), Number(crsCfg.tileWidth));
    var maxZoom = (Number(cfg.maxZoom) || 6) + 3;
    var map = L.map(container, {
      crs: crs, zoomControl: false, attributionControl: true, minZoom: Number(cfg.minZoom) || 0, maxZoom: maxZoom,
      zoomSnap: 0.25, zoomDelta: 0.5, wheelPxPerZoomLevel: 90, preferCanvas: false, keyboard: true, boxZoom: true
    });
    map.attributionControl.setPrefix(false);
    L.control.zoom({ position: 'bottomright', zoomInTitle: 'Rapprocher', zoomOutTitle: 'Éloigner' }).addTo(map);
    function toLL(p) { return L.latLng(p[1] + oy, p[0] + ox); }
    function toWorld(ll) { return [ll.lng - ox, ll.lat - oy]; }

    var base = L.tileLayer(cfg.tilePattern, {
      tileSize: Number(cfg.tileSize) || 212, minZoom: Number(cfg.minZoom) || 0, maxNativeZoom: Number(cfg.maxZoom) || 6, maxZoom: maxZoom,
      noWrap: true, attribution: cfg.attribution || '&copy; Bohemia Interactive', className: 'toc-base-tiles', keepBuffer: 2
    }).addTo(map);

    map.createPane('tocPhoto');
    map.getPane('tocPhoto').style.zIndex = 240;
    map.getPane('tocPhoto').style.pointerEvents = 'none';
    map.createPane('tocVeil');
    map.getPane('tocVeil').style.zIndex = 260;
    map.getPane('tocVeil').style.pointerEvents = 'none';
    map.createPane('tocGrid');
    map.getPane('tocGrid').style.zIndex = 300;
    map.getPane('tocGrid').style.pointerEvents = 'none';
    var veil = L.rectangle([toLL([-5000, -5000]), toLL([world + 5000, world + 5000])], { pane: 'tocVeil', stroke: false, fillColor: '#070a0d', fillOpacity: 0.35, interactive: false }).addTo(map);

    /* Pyramide de tuiles en mètres Arma (côté = tileSize / factor), chargée à la demande. */
    var photo = { spec: null, tiles: {}, timer: null };
    function photoWorld(spec) { return (Number(spec.tileSize) || 256) / (Number(spec.factorX) || 1); }
    function photoRefresh() {
      var spec = photo.spec;
      if (!spec) return;
      var W = photoWorld(spec);
      var b = map.getBounds();
      var west = b.getWest() - ox, east = b.getEast() - ox, south = b.getSouth() - oy, north = b.getNorth() - oy;
      var px = map.getSize().x || 1000;
      var mpp = (east - west) / px;
      // Niveau dont un pixel de tuile vaut à peu près un pixel d'écran, borné à la pyramide publiée.
      var z = Math.round(Math.log(W / ((Number(spec.tileSize) || 256) * Math.max(0.05, mpp))) / Math.LN2);
      z = Math.max(Number(spec.minZoom) || 0, Math.min(Number(spec.maxZoom) || 0, z));
      var n = Math.pow(2, z);
      var x0 = Math.max(0, Math.floor(west / W * n)), x1 = Math.min(n - 1, Math.floor(east / W * n));
      var y0 = Math.max(0, Math.floor((1 - north / W) * n)), y1 = Math.min(n - 1, Math.floor((1 - south / W) * n));
      var keep = {};
      if ((x1 - x0 + 1) * (y1 - y0 + 1) <= 160) {
        for (var x = x0; x <= x1; x++) {
          for (var y = y0; y <= y1; y++) {
            var key = z + '/' + x + '/' + y;
            keep[key] = true;
            if (photo.tiles[key]) continue;
            var url = String(spec.tilePattern).replace('{z}', z).replace('{x}', x).replace('{y}', y);
            var bounds = L.latLngBounds(toLL([x * W / n, W * (1 - (y + 1) / n)]), toLL([(x + 1) * W / n, W * (1 - y / n)]));
            var ov = L.imageOverlay(url, bounds, { pane: 'tocPhoto', interactive: false, className: 'toc-photo-tile' });
            ov.on('error', function () { /* tuile absente : la carte du jeu dessous reste visible */ });
            ov.addTo(map);
            var img = ov.getElement();
            if (img) { img.loading = 'lazy'; img.decoding = 'async'; img.alt = ''; }
            photo.tiles[key] = ov;
          }
        }
      }
      Object.keys(photo.tiles).forEach(function (k) {
        if (keep[k]) return;
        map.removeLayer(photo.tiles[k]);
        delete photo.tiles[k];
      });
    }
    function photoSchedule() {
      clearTimeout(photo.timer);
      photo.timer = setTimeout(photoRefresh, 80);
    }
    function photoClear() {
      Object.keys(photo.tiles).forEach(function (k) { map.removeLayer(photo.tiles[k]); });
      photo.tiles = {};
    }
    map.on('moveend zoomend resize', photoSchedule);

    var bgs = backgrounds(cfg);
    var current = null;
    function setBackground(id) {
      var bg = null;
      bgs.forEach(function (b) { if (b.id === id) bg = b; });
      if (!bg) bg = bgs[0];
      current = bg;
      photoClear();
      photo.spec = bg.spec || null;
      base.setOpacity(bg.id === 'dark' ? 0 : 1);
      container.classList.toggle('is-dark-bg', bg.id === 'dark');
      if (photo.spec) photoRefresh();
      if (opts.onBackground) opts.onBackground(bg);
      return bg;
    }
    function setVeil(alpha) { veil.setStyle({ fillOpacity: Math.max(0, Math.min(0.9, alpha)) }); }

    /* Quadrillage numéroté (plaques en monospace). */
    var gridLayer = L.layerGroup().addTo(map);
    var gridOn = true;
    function drawGrid() {
      gridLayer.clearLayers();
      if (!gridOn) return;
      var b = map.getBounds();
      var west = Math.max(0, b.getWest() - ox), east = Math.min(world, b.getEast() - ox);
      var south = Math.max(0, b.getSouth() - oy), north = Math.min(world, b.getNorth() - oy);
      var step = G.gridStep(east - west);
      var lineStyle = { pane: 'tocGrid', color: '#3a4a57', weight: 1, opacity: 0.75, interactive: false };
      // Plaques à 3 chiffres (grille à 6 chiffres, cases de 100 m), comme le PC du poste en jeu.
      var lbl = function (v) { var s = String(Math.floor(v / 100)); while (s.length < 3) s = '0' + s; return s; };
      var count = 0;
      for (var gx = Math.ceil(west / step) * step; gx <= east && count < 80; gx += step, count++) {
        gridLayer.addLayer(L.polyline([toLL([gx, south]), toLL([gx, north])], lineStyle));
        gridLayer.addLayer(L.marker(toLL([gx, north]), { pane: 'tocGrid', interactive: false, icon: L.divIcon({ className: '', html: '<span class="toc-gridlbl">' + lbl(gx) + '</span>', iconSize: null, iconAnchor: [-4, -6] }) }));
      }
      count = 0;
      for (var gy = Math.ceil(south / step) * step; gy <= north && count < 80; gy += step, count++) {
        gridLayer.addLayer(L.polyline([toLL([west, gy]), toLL([east, gy])], lineStyle));
        gridLayer.addLayer(L.marker(toLL([west, gy]), { pane: 'tocGrid', interactive: false, icon: L.divIcon({ className: '', html: '<span class="toc-gridlbl">' + lbl(gy) + '</span>', iconSize: null, iconAnchor: [-6, 22] }) }));
      }
    }
    map.on('moveend zoomend', drawGrid);

    /* Échelle et nord. */
    var scaleEl = document.createElement('div');
    scaleEl.className = 'toc-scale';
    scaleEl.setAttribute('aria-hidden', 'true');
    container.parentNode.appendChild(scaleEl);
    var northEl = document.createElement('div');
    northEl.className = 'toc-north';
    northEl.setAttribute('aria-hidden', 'true');
    northEl.innerHTML = '<svg width="18" height="20" viewBox="0 0 18 20"><path d="M9 1 L16 18 L9 13 L2 18 Z" fill="none" stroke="#e8eef2" stroke-width="2"/></svg><span>N</span>';
    container.parentNode.appendChild(northEl);
    function metersPerPixel() {
      var b = map.getBounds();
      return (b.getEast() - b.getWest()) / Math.max(1, map.getSize().x);
    }
    function updateScale() {
      var mpp = metersPerPixel();
      var m = G.niceScale(mpp * 140);
      scaleEl.innerHTML = '<i style="width:' + Math.round(m / mpp) + 'px"></i><span>' + esc(G.fmtDist(m)) + '</span>';
    }
    map.on('zoomend moveend resize', updateScale);

    map.on('mousemove', function (e) {
      if (opts.onCursor) opts.onCursor(toWorld(e.latlng));
    });

    var c = cfg.center || [world / 2, world / 2];
    map.setView(toLL([Number(c[0]) || world / 2, Number(c[1]) || world / 2]), Number(cfg.defaultZoom) || 3);
    setTimeout(function () { map.invalidateSize(); drawGrid(); updateScale(); photoSchedule(); }, 0);

    return {
      map: map,
      terrain: terrain,
      worldSize: world,
      toLL: toLL,
      toWorld: toWorld,
      backgrounds: bgs,
      setBackground: setBackground,
      currentBackground: function () { return current; },
      setVeil: setVeil,
      setGrid: function (on) { gridOn = !!on; drawGrid(); },
      metersPerPixel: metersPerPixel,
      centerOn: function (p, zoom) { map.setView(toLL(p), zoom != null ? zoom : Math.max(map.getZoom(), (Number(cfg.maxZoom) || 6))); },
      fitWorld: function (bb) {
        if (!bb || !isFinite(bb[0])) return;
        map.fitBounds(L.latLngBounds(toLL([bb[0], bb[1]]), toLL([bb[2], bb[3]])), { padding: [40, 40], maxZoom: maxZoom - 1 });
      },
      destroy: function () { map.remove(); scaleEl.remove(); northEl.remove(); }
    };
  }

  return { create: create, backgrounds: backgrounds, esc: esc };
})();
