/* Poste TOC web : symboles et plaques de la carte. Une piste ou un repère = un symbole (OTAN par milsymbol, ou icône du
 * poste) et une plaque (indicatif, ligne secondaire), reliés par un trait de rappel. Les plaques sont placées sans se
 * recouvrir (TocTracks.placeLabels), leur détail suit l'échelle, les pistes proches se regroupent à petite échelle
 * (« Bravo · 5 »). La mise en page ne tourne que sur un changement (données, zoom, déplacement, sélection).
 * Dépend de Leaflet, milsymbol, toc-tracks.js. */
window.TocOverlay = (function () {
  'use strict';
  var T = window.TocTracks;
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };

  var symCache = {};
  /** Symbole OTAN : cadre et pictogramme de la couleur d'affiliation sur fond sombre, liseré sombre. */
  function natoSymbol(sym, kind, affil, opts) {
    opts = opts || {};
    var size = opts.size || 20;
    var color = opts.lost ? T.LOST_COLOR : (T.AFFIL_COLOR[affil] || T.AFFIL_COLOR.F);
    var code = T.sidc(sym, kind, affil, !!opts.lost, opts.echelon);
    var key = code + '|' + size + '|' + color;
    if (symCache[key]) return symCache[key];
    var out = null;
    if (window.ms && window.ms.Symbol) {
      try {
        var all = { Civilian: color, Friend: color, Hostile: color, Neutral: color, Unknown: color };
        var dark = { Civilian: '#0d1318', Friend: '#0d1318', Hostile: '#0d1318', Neutral: '#0d1318', Unknown: '#0d1318' };
        var s = new window.ms.Symbol(code, { size: size, frame: true, fill: true, fillOpacity: 0.86, colorMode: dark, frameColor: all, iconColor: all, strokeWidth: opts.stroke || 9, outlineWidth: 5, outlineColor: 'rgba(7,10,13,.92)' });
        var sz = s.getSize(), an = s.getAnchor();
        if (sz.width > 0) out = { html: s.asSVG(), w: sz.width, h: sz.height, ax: an.x, ay: an.y };
      } catch (e) { out = null; }
    }
    if (!out) {
      var w = Math.round(size * 1.5), h = size;
      out = { html: '<svg width="' + w + '" height="' + h + '" viewBox="0 0 30 20"><rect x="1.5" y="1.5" width="27" height="17" fill="rgba(13,19,24,.82)" stroke="' + color + '" stroke-width="2.5"' + (opts.lost ? ' stroke-dasharray="4 3"' : '') + '/><path d="M1.5 1.5L28.5 18.5M28.5 1.5L1.5 18.5" stroke="' + color + '" stroke-width="2"/></svg>', w: w, h: h, ax: w / 2, ay: h / 2 };
    }
    symCache[key] = out;
    return out;
  }

  function create(tm, opts) {
    opts = opts || {};
    var map = tm.map;
    var sym = opts.sym || {};
    var items = {};
    var byLayer = {};
    var layerState = {};
    var markers = {};
    var clusterMarkers = [];
    var selectedId = null;
    var followId = null;
    var timer = null;
    var dead = false;
    var lastStats = { shown: 0, hidden: 0, clusters: 0 };

    if (!map.getPane('tocFeatures')) {
      map.createPane('tocFeatures');
      map.getPane('tocFeatures').style.zIndex = 620;
    }
    var group = L.layerGroup().addTo(map);
    var tip = document.createElement('div');
    tip.className = 'toc-ft-tip';
    tip.hidden = true;
    tip.setAttribute('role', 'tooltip');
    map.getContainer().parentNode.appendChild(tip);

    // Ligne secondaire : liste de morceaux (chacun traduit seul par le dictionnaire du site), séparés par « · ».
    function subParts(it) { return (Array.isArray(it.sub) ? it.sub : (it.sub ? [it.sub] : [])).filter(Boolean); }
    function subText(it) { return subParts(it).join(' · '); }
    function symbolHtml(it) {
      var s = it.symbol;
      var h = '<span class="toc-ft-sym" style="width:' + Math.ceil(s.w) + 'px;height:' + Math.ceil(s.h) + 'px;margin:-' + Math.round(s.ay) + 'px 0 0 -' + Math.round(s.ax) + 'px">' + s.html + '</span>';
      if (it.heading != null) h += '<i class="toc-ft-hdg" style="transform:rotate(' + Math.round(it.heading) + 'deg) translateY(-' + Math.round(it.r + 9) + 'px)"></i>';
      return h;
    }
    function itemHtml(it) {
      return '<div class="toc-ft is-' + (it.tone || 'live') + (it.kind === 'marker' ? ' is-marker' : '') + (it.me ? ' is-me' : '') + (it.id === selectedId ? ' is-sel' : '') + '" data-ft="' + esc(it.id) + '" style="--c:' + esc(it.color || '#e8eef2') + '">' +
        '<svg class="toc-ft-lead" width="1" height="1" aria-hidden="true"><line class="h"/><line class="l"/></svg>' + symbolHtml(it) +
        '<span class="toc-ft-ring" style="width:' + Math.round(it.r * 2 + 12) + 'px;height:' + Math.round(it.r * 2 + 12) + 'px;margin:-' + Math.round(it.r + 6) + 'px 0 0 -' + Math.round(it.r + 6) + 'px"></span>' +
        '<span class="toc-ft-plate" hidden><b>' + esc(it.title || '') + '</b>' + (it.badge ? '<em>' + esc(it.badge) + '</em>' : '') + (subParts(it).length ? '<small>' + subParts(it).map(function (p) { return '<span>' + esc(p) + '</span>'; }).join('') + '</small>' : '') + '</span></div>';
    }
    function sig(it) {
      return [it.symbol.html.length, it.symbol.w, it.title, subText(it), it.badge, it.tone, it.color, it.heading != null ? Math.round(it.heading / 5) : '', it.me ? 1 : 0, it.count || 0].join('|');
    }
    function makeMarker(it) {
      return L.marker(tm.toLL(it.pos), { pane: 'tocFeatures', interactive: false, keyboard: false, zIndexOffset: it.prio || 0, icon: L.divIcon({ className: 'toc-ft-icon', html: itemHtml(it), iconSize: [0, 0], iconAnchor: [0, 0] }) });
    }

    function layerOn(id) { return !layerState[id] || layerState[id].on !== false; }
    function layerOpacity(id) { return layerState[id] && layerState[id].opacity != null ? layerState[id].opacity : 1; }

    /** Remplace les éléments d'un calque. Les marqueurs inchangés sont gardés (seule la position bouge). */
    function setLayer(layerId, list) {
      var old = byLayer[layerId] || {};
      var next = {};
      (list || []).forEach(function (it) {
        if (!it || !it.pos || !it.symbol) return;
        it.layer = layerId;
        it.r = it.r || Math.max(8, Math.min(22, Math.max(it.symbol.w, it.symbol.h) / 2));
        it._sig = sig(it);
        next[it.id] = it;
        var m = markers[it.id];
        if (m && old[it.id] && old[it.id]._sig === it._sig) {
          var p = old[it.id].pos;
          if (p[0] !== it.pos[0] || p[1] !== it.pos[1]) m.setLatLng(tm.toLL(it.pos));
        } else {
          if (m) { group.removeLayer(m); delete markers[it.id]; }
          markers[it.id] = makeMarker(it);
          if (layerOn(layerId)) group.addLayer(markers[it.id]);
        }
        items[it.id] = it;
      });
      Object.keys(old).forEach(function (id) {
        if (next[id]) return;
        if (markers[id]) { group.removeLayer(markers[id]); delete markers[id]; }
        delete items[id];
        if (selectedId === id) selectedId = null;
      });
      byLayer[layerId] = next;
      if (followId && items[followId] && items[followId].pos) map.panTo(tm.toLL(items[followId].pos), { animate: true });
      schedule();
    }

    function setLayerState(layerId, on, opacity) {
      layerState[layerId] = { on: on !== false, opacity: opacity != null ? opacity : 1 };
      Object.keys(byLayer[layerId] || {}).forEach(function (id) {
        var m = markers[id];
        if (!m) return;
        if (on !== false && !group.hasLayer(m)) group.addLayer(m);
        if (on === false && group.hasLayer(m)) group.removeLayer(m);
      });
      schedule();
    }

    function el(id) { var m = markers[id]; return m && m.getElement() ? m.getElement().firstChild : null; }

    function applyPlace(node, it, mode, place, size) {
      var plate = node.querySelector('.toc-ft-plate');
      var lead = node.querySelector('.toc-ft-lead');
      node.style.opacity = layerOpacity(it.layer) * (it.tone === 'lost' ? 0.72 : 1);
      node.classList.toggle('is-sel', it.id === selectedId);
      if (!place || !size.w) {
        plate.hidden = true;
        lead.style.display = 'none';
        return false;
      }
      plate.hidden = false;
      plate.style.visibility = '';
      plate.style.transform = 'translate(' + place.dx + 'px,' + place.dy + 'px)';
      var ln = T.leader(place, size.w, size.h, it.r);
      if (!ln) { lead.style.display = 'none'; return true; }
      lead.style.display = '';
      [lead.firstChild, lead.lastChild].forEach(function (l) {
        l.setAttribute('x1', ln.x1); l.setAttribute('y1', ln.y1); l.setAttribute('x2', ln.x2); l.setAttribute('y2', ln.y2);
      });
      return true;
    }

    /** Mise en page : regroupement, puis placement des plaques des éléments visibles à l'écran. */
    function layout() {
      timer = null;
      if (dead) return;
      clusterMarkers.forEach(function (m) { group.removeLayer(m); });
      clusterMarkers = [];
      var mpp = tm.metersPerPixel();
      var size = map.getSize();
      var pad = 160;
      var mode = T.labelMode(mpp);
      var radius = T.clusterRadius(mpp);
      var view = [];
      var clusterable = [];
      Object.keys(items).forEach(function (id) {
        var it = items[id];
        var node = el(id);
        if (!node || !layerOn(it.layer)) return;
        var p = map.latLngToContainerPoint(tm.toLL(it.pos));
        it._x = p.x; it._y = p.y;
        node.parentNode.style.display = '';
        if (p.x < -pad || p.y < -pad || p.x > size.x + pad || p.y > size.y + pad) { applyPlace(node, it, 'none', null, { w: 0 }); return; }
        var solo = it.solo || id === selectedId || it.me;
        if (it.clusterable && !solo && radius > 0) clusterable.push({ id: id, x: p.x, y: p.y, group: it.group || '', prio: it.prio || 0 });
        else view.push(it);
      });
      var nClusters = 0;
      T.cluster(clusterable, radius).forEach(function (c) {
        if (c.ids.length === 1) { view.push(items[c.ids[0]]); return; }
        nClusters++;
        var mem = c.ids.map(function (id) { return items[id]; });
        mem.forEach(function (m) { var n = el(m.id); if (n) n.parentNode.style.display = 'none'; });
        var lead = items[c.lead];
        var tone = mem.some(function (m) { return m.tone === 'live'; }) ? 'live' : (mem.some(function (m) { return m.tone === 'old'; }) ? 'old' : 'lost');
        var cl = opts.clusterItem ? opts.clusterItem(c, mem, lead, tone) : null;
        if (!cl) return;
        cl.pos = tm.toWorld(map.containerPointToLatLng([c.x, c.y]));
        cl.layer = lead.layer;
        cl.r = Math.max(8, Math.min(24, Math.max(cl.symbol.w, cl.symbol.h) / 2));
        cl._x = c.x; cl._y = c.y;
        cl._members = c.ids;
        var m = makeMarker(cl);
        m._tocItem = cl;
        group.addLayer(m);
        clusterMarkers.push(m);
        view.push(cl);
      });
      // Passe 1 : chaque plaque prend son niveau de détail (hors écran) ; passe 2 : sa taille réelle est lue (un seul
      // recalcul de mise en page du navigateur) ; passe 3 : placement sans chevauchement.
      var nodeOf = function (it) {
        if (!it._members) return el(it.id);
        for (var i = 0; i < clusterMarkers.length; i++) if (clusterMarkers[i]._tocItem === it) return clusterMarkers[i].getElement().firstChild;
        return null;
      };
      view.forEach(function (it) {
        var must = it.id === selectedId || it.me;
        var md = must ? 'full' : (it.kind === 'marker' ? (it.labelMax != null && mpp > it.labelMax ? 'none' : 'name') : (it.kind === 'cluster' && mode === 'none' ? 'name' : mode));
        it._mode = md; it._must = must;
        it._node = nodeOf(it);
        var plate = it._node ? it._node.querySelector('.toc-ft-plate') : null;
        it._plate = plate;
        if (!plate) return;
        if (md === 'none' || !it.title) { plate.hidden = true; return; }
        plate.hidden = false;
        plate.className = 'toc-ft-plate is-' + md;
        plate.style.visibility = 'hidden';
      });
      var req = view.map(function (it) {
        var pl = it._plate;
        it._size = pl && !pl.hidden ? { w: pl.offsetWidth, h: pl.offsetHeight } : { w: 0, h: 0 };
        return { id: it.id, x: it._x, y: it._y, r: it.r + 2, w: it._size.w, h: it._size.h, prio: (it._must ? 100000 : 0) + (it.prio || 0), must: it._must };
      });
      var places = T.placeLabels(req, { gap: 7 });
      var shown = 0, hidden = 0;
      view.forEach(function (it) {
        if (!it._node) return;
        if (applyPlace(it._node, it, it._mode, places[it.id], it._size)) shown++;
        else if (it._size.w) hidden++;
        it._node = null; it._plate = null;
      });
      lastStats = { shown: shown, hidden: hidden, clusters: nClusters, mode: mode };
      if (opts.onLayout) opts.onLayout(lastStats);
    }
    function schedule() {
      if (timer || dead) return;
      timer = setTimeout(layout, 90);
    }

    function itemOf(target) {
      var node = target && target.closest ? target.closest('[data-ft]') : null;
      if (!node) return null;
      var id = node.getAttribute('data-ft');
      if (items[id]) return items[id];
      for (var i = 0; i < clusterMarkers.length; i++) if (clusterMarkers[i]._tocItem.id === id) return clusterMarkers[i]._tocItem;
      return null;
    }
    var container = map.getContainer();
    function onOver(e) {
      var it = itemOf(e.target);
      if (!it || !it.tip) { tip.hidden = true; return; }
      tip.innerHTML = it.tip(it);
      tip.hidden = false;
      var box = container.getBoundingClientRect();
      var x = e.clientX - box.left + 16, y = e.clientY - box.top + 16;
      var tw = tip.offsetWidth, th = tip.offsetHeight;
      if (x + tw > box.width - 8) x = Math.max(8, e.clientX - box.left - tw - 12);
      if (y + th > box.height - 8) y = Math.max(8, e.clientY - box.top - th - 12);
      tip.style.left = x + 'px';
      tip.style.top = y + 'px';
    }
    function onOut(e) { if (!itemOf(e.relatedTarget)) tip.hidden = true; }
    function onClick(e) {
      var it = itemOf(e.target);
      if (!it) return;
      e.stopPropagation();
      tip.hidden = true;
      if (it._members) {
        // Regroupement : rapprocher jusqu'à séparer ses pistes.
        var pts = it._members.map(function (id) { return items[id] ? items[id].pos : null; }).filter(Boolean);
        var bb = window.TocGeo.bbox(pts);
        var padM = Math.max(40, (bb[2] - bb[0] + bb[3] - bb[1]) * 0.25);
        if (bb[2] - bb[0] < 2 && bb[3] - bb[1] < 2) map.setView(tm.toLL(pts[0]), map.getMaxZoom());
        else tm.fitWorld([bb[0] - padM, bb[1] - padM, bb[2] + padM, bb[3] + padM]);
        return;
      }
      if (opts.onSelect) opts.onSelect(it);
    }
    container.addEventListener('mouseover', onOver);
    container.addEventListener('mousemove', function (e) { if (!tip.hidden) onOver(e); });
    container.addEventListener('mouseout', onOut);
    container.addEventListener('click', onClick, true);
    map.on('zoomend moveend resize', schedule);
    map.on('movestart', function () { tip.hidden = true; });
    map.on('dragstart', function () { followId = null; if (opts.onFollow) opts.onFollow(null); });
    // Les polices du site arrivent après le premier dessin : les plaques changent de largeur, nouvelle mise en page.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);

    return {
      setLayer: setLayer,
      setLayerState: setLayerState,
      relayout: schedule,
      layoutNow: layout,
      itemAt: itemOf,
      get: function (id) { return items[id] || null; },
      all: function (layerId) { return Object.keys(byLayer[layerId] || {}).map(function (id) { return byLayer[layerId][id]; }); },
      count: function (layerId) { return Object.keys(byLayer[layerId] || {}).length; },
      stats: function () { return lastStats; },
      selected: function () { return selectedId; },
      select: function (id) { selectedId = id && items[id] ? id : null; schedule(); },
      follow: function (id) { followId = id && items[id] ? id : null; if (followId) map.panTo(tm.toLL(items[followId].pos)); if (opts.onFollow) opts.onFollow(followId); },
      following: function () { return followId; },
      destroy: function () {
        dead = true;
        clearTimeout(timer);
        container.removeEventListener('mouseover', onOver);
        container.removeEventListener('mouseout', onOut);
        container.removeEventListener('click', onClick, true);
        if (map.hasLayer(group)) map.removeLayer(group);
        tip.remove();
      }
    };
  }

  return { create: create, natoSymbol: natoSymbol, esc: esc };
})();
