/* Calques SSE des cartes (poste TOC web, ATAK web / Overwatch, TACMAP) : contrôles d'identité, lieux, pistes de téléphones.
 *
 * Un seul module pour les trois cartes. Il lit GET /api/sse/v1/calques (GeoJSON en mètres du monde Arma, [x, y]) et dessine
 * sur une carte Leaflet fournie par la page. Chaque page donne seulement :
 *   map        la carte Leaflet ;
 *   mapId      numéro de la carte du site (nombre ou fonction) ;
 *   toLatLng   (x, y) -> L.LatLng : la conversion de la page (décalage propre à la carte compris) ;
 *   toWorld    L.LatLng -> [x, y] (facultatif : sert à ne demander que la zone vue) ;
 *   base       préfixe d'URL du site.
 * Les textes affichés viennent de la page (blocs [data-sse-ml-texts] [data-k], traduits avec la page) : aucun texte dans ce
 * fichier. Calques désactivés au départ, jamais mémorisés comme actifs d'une visite à l'autre. Aucune requête quand l'onglet
 * est masqué, ni quand aucun calque n'est coché. Données lues en texte (textContent), jamais injectées en HTML.
 */
(function (global) {
  'use strict';

  var ENDPOINT = '/api/sse/v1/calques';
  var TYPES = ['controles', 'lieux', 'pistes'];
  var REFRESH_MS = 60000;
  var MIN_GAP_MS = 4000;
  var CLUSTER_PX = 46;
  var PERIOD_KEY = 'athena:sse-ml:period';
  var DAY = 86400000;
  var TRACK_COLORS = ['#4aa3ff', '#c58bff', '#ff7ab8', '#5ce0a0', '#ffd24a', '#ff9a5c'];
  var AGE_COLORS = { fresh: '#ff8a3d', week: '#e3c04a', old: '#8397aa' };
  var TONE_RING = { ok: '#3ecf8e', warn: '#f5b841', bad: '#ff5f56', none: '#0b1218' };
  var PLACE_COLOR = '#3fc1b0';
  var SAFE_PATH = /^atak\/sse\/[a-z0-9\/_-]{1,120}$/;

  function safeStore() {
    try { return global.localStorage; } catch (e) { return null; }
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function fmt(s, a, b) {
    return String(s || '').replace('%1', a == null ? '' : a).replace('%2', b == null ? '' : b);
  }

  function parseIso(s) {
    var t = Date.parse(s || '');
    return isFinite(t) ? t : null;
  }

  function pad(n) { return n < 10 ? '0' + n : String(n); }

  function localStamp(t) {
    var d = new Date(t);
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function bearing(a, b) {
    return Math.atan2(b.x - a.x, -(b.y - a.y)) * 180 / Math.PI;
  }

  function mount(root, opts) {
    if (!root || !opts || !opts.map || typeof opts.toLatLng !== 'function' || !global.L) return null;
    if (root.__sseMl) root.__sseMl.destroy();

    var L = global.L;
    var map = opts.map;
    var base = String(opts.base != null ? opts.base : (root.getAttribute('data-base') || '')).replace(/\/+$/, '');
    var T = {};
    [].forEach.call(root.querySelectorAll('[data-sse-ml-texts] [data-k]'), function (n) { T[n.getAttribute('data-k')] = n.textContent; });
    function tr(k) { return T[k] != null ? T[k] : ''; }

    var kept = root.__sseMlState || (root.__sseMlState = { on: {}, period: null, cutoff: 1000 });
    var store = safeStore();
    if (!kept.period) {
      try { kept.period = (store && store.getItem(PERIOD_KEY)) || '7j'; } catch (e) { kept.period = '7j'; }
    }
    if (['24h', '7j', '30j', 'tout'].indexOf(kept.period) < 0) kept.period = '7j';

    var removers = [];
    function on(target, ev, fn, o) {
      target.addEventListener(ev, fn, o);
      removers.push(function () { target.removeEventListener(ev, fn, o); });
    }
    function mapOn(ev, fn) {
      map.on(ev, fn);
      removers.push(function () { map.off(ev, fn); });
    }
    function q(sel) { return root.querySelector(sel); }
    function qa(sel) { return [].slice.call(root.querySelectorAll(sel)); }

    var groups = {
      controles: L.layerGroup(),
      lieux: L.layerGroup(),
      pistes: L.layerGroup()
    };
    var canvas = L.canvas({ padding: 0.4 });
    var data = { controles: [], lieux: [], pistes: [] };
    var metaTypes = {};
    var access = { controles: false, lieux: false, pistes: false, noms: false };
    var dead = false;
    var inflight = null;
    var lastFetchAt = 0;
    var fetchedBox = null;
    var fetchedKey = '';
    var timer = null;
    var redrawTimer = null;
    var retryTimer = null;
    var selectedTrack = null;
    var trackLayers = {};
    var cutoffMs = null;
    var span = { min: null, max: null };

    function mapId() {
      var v = typeof opts.mapId === 'function' ? opts.mapId() : opts.mapId;
      v = Number(v);
      return isFinite(v) && v > 0 ? Math.floor(v) : 0;
    }

    function msg(text, kind) {
      var n = q('[data-sse-ml-msg]');
      if (!n) return;
      n.textContent = text || '';
      n.hidden = !text;
      n.setAttribute('data-kind', kind || '');
    }

    function anyOn() {
      return TYPES.some(function (t) { return !!kept.on[t] && access[t]; });
    }

    function currentTypes() {
      return TYPES.filter(function (t) { return !!kept.on[t] && access[t]; });
    }

    function url(types, box) {
      var p = ['types=' + encodeURIComponent(types.join(',')), 'carte=' + mapId(), 'periode=' + encodeURIComponent(kept.period)];
      if (box) p.push('bbox=' + box.map(function (v) { return String(Math.round(v)); }).join(','));
      return base + ENDPOINT + '?' + p.join('&');
    }

    function viewBox() {
      if (typeof opts.toWorld !== 'function') return null;
      try {
        var b = map.getBounds().pad(0.5);
        var a = opts.toWorld(b.getSouthWest());
        var c = opts.toWorld(b.getNorthEast());
        var box = [Math.min(a[0], c[0]), Math.min(a[1], c[1]), Math.max(a[0], c[0]), Math.max(a[1], c[1])];
        var step = 500;
        box = [Math.floor(box[0] / step) * step, Math.floor(box[1] / step) * step, Math.ceil(box[2] / step) * step, Math.ceil(box[3] / step) * step];
        return box.every(isFinite) ? box : null;
      } catch (e) { return null; }
    }

    function insideFetched() {
      if (fetchedBox === 'all') return true;
      if (!fetchedBox || typeof opts.toWorld !== 'function') return false;
      try {
        var b = map.getBounds();
        var a = opts.toWorld(b.getSouthWest());
        var c = opts.toWorld(b.getNorthEast());
        return Math.min(a[0], c[0]) >= fetchedBox[0] && Math.min(a[1], c[1]) >= fetchedBox[1] && Math.max(a[0], c[0]) <= fetchedBox[2] && Math.max(a[1], c[1]) <= fetchedBox[3];
      } catch (e) { return false; }
    }

    /* ----------------------------------------------------------- Réseau */

    function probe() {
      if (!mapId()) { applyAccess(null); return; }
      fetch(base + ENDPOINT + '?types=&carte=' + mapId(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (!dead) applyAccess(j && j.meta ? j.meta.access : null); })
        .catch(function () { if (!dead) applyAccess(null); });
    }

    function applyAccess(a) {
      access = a ? { controles: !!a.controles, lieux: !!a.lieux, pistes: !!a.pistes, noms: !!a.noms } : { controles: false, lieux: false, pistes: false, noms: false };
      var any = access.controles || access.lieux || access.pistes;
      TYPES.forEach(function (t) {
        var row = q('[data-sse-ml-row="' + t + '"]');
        if (row) row.hidden = !access[t];
        var cb = q('[data-sse-ml-toggle="' + t + '"]');
        if (cb) cb.checked = !!(kept.on[t] && access[t]);
      });
      if (opts.supported === false) any = false;
      if (opts.floating) {
        root.setAttribute('data-sse-ml-available', any ? '1' : '0');
      } else {
        root.hidden = !any;
      }
      if (typeof opts.onAccess === 'function') opts.onAccess(any);
      syncPeriodUi();
      if (any && anyOn()) refresh(true);
    }

    function refresh(force) {
      if (dead || document.hidden) return;
      var types = currentTypes();
      if (!types.length) { clearAll(); updateCounts(); return; }
      var now = Date.now();
      if (inflight) return;
      if (!force && now - lastFetchAt < MIN_GAP_MS) {
        // Trop tôt : une seule relecture est reportée à la fin du délai, plutôt que perdue.
        if (!retryTimer) retryTimer = setTimeout(function () { retryTimer = null; refresh(false); }, MIN_GAP_MS - (now - lastFetchAt) + 50);
        return;
      }
      var box = viewBox();
      var key = types.join(',') + '|' + mapId() + '|' + kept.period;
      var ctl = typeof AbortController === 'function' ? new AbortController() : null;
      inflight = ctl || true;
      lastFetchAt = now;
      fetch(url(types, box), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ctl ? ctl.signal : undefined })
        .then(function (r) {
          if (r.status === 403) { applyAccess(null); return null; }
          if (!r.ok) throw new Error('http');
          return r.json();
        })
        .then(function (j) {
          inflight = null;
          if (dead || !j) return;
          fetchedBox = box || 'all';
          fetchedKey = key;
          ingest(j, types);
        })
        .catch(function () {
          inflight = null;
          if (!dead) msg(tr('load_error'), 'error');
        });
    }

    function schedule() {
      if (timer) { clearInterval(timer); timer = null; }
      timer = setInterval(function () { if (!document.hidden && anyOn()) refresh(false); }, REFRESH_MS);
      removers.push(function () { if (timer) { clearInterval(timer); timer = null; } });
    }

    /* --------------------------------------------------------- Données */

    function ingest(j, types) {
      metaTypes = (j.meta && j.meta.types) || {};
      if (j.meta && j.meta.access) access = {
        controles: !!j.meta.access.controles, lieux: !!j.meta.access.lieux, pistes: !!j.meta.access.pistes, noms: !!j.meta.access.noms
      };
      types.forEach(function (t) { data[t] = []; });
      (j.features || []).forEach(function (f) {
        if (!f || !f.geometry || !f.properties) return;
        var kind = f.properties.type;
        if (kind === 'controle' && data.controles && types.indexOf('controles') >= 0) data.controles.push(f);
        else if (kind === 'lieu' && types.indexOf('lieux') >= 0) data.lieux.push(f);
        else if (kind === 'piste' && types.indexOf('pistes') >= 0) data.pistes.push(f);
      });
      computeSpan();
      redraw();
    }

    function computeSpan() {
      var min = null;
      var max = null;
      data.controles.concat(data.pistes).forEach(function (f) {
        var t = parseIso(f.properties.at || f.properties.recorded_at);
        if (t == null) return;
        min = min == null ? t : Math.min(min, t);
        max = max == null ? t : Math.max(max, t);
      });
      span.min = min;
      span.max = max;
      var s = q('[data-sse-ml-slider]');
      if (s) s.disabled = !(min != null && max != null && max > min);
      applyCutoff();
    }

    function applyCutoff() {
      var v = Number(kept.cutoff);
      if (!isFinite(v)) v = 1000;
      var lab = q('[data-sse-ml-cutoff]');
      if (span.min == null || span.max == null || span.max <= span.min || v >= 1000) {
        cutoffMs = null;
        if (lab) lab.textContent = tr('cutoff_all');
        return;
      }
      cutoffMs = span.min + (span.max - span.min) * v / 1000;
      if (lab) lab.textContent = fmt(tr('cutoff_until'), localStamp(cutoffMs));
    }

    function visible(f) {
      if (cutoffMs == null) return true;
      var t = parseIso(f.properties.at || f.properties.recorded_at);
      return t == null || t <= cutoffMs;
    }

    function ageColor(at) {
      var t = parseIso(at);
      if (t == null) return AGE_COLORS.old;
      var age = Date.now() - t;
      return age < DAY ? AGE_COLORS.fresh : (age < 7 * DAY ? AGE_COLORS.week : AGE_COLORS.old);
    }

    function ll(c) { return opts.toLatLng(Number(c[0]), Number(c[1])); }

    /* --------------------------------------------------------- Dessin */

    function clearAll() {
      TYPES.forEach(function (t) { groups[t].clearLayers(); if (map.hasLayer(groups[t])) map.removeLayer(groups[t]); });
      trackLayers = {};
    }

    function scheduleRedraw() {
      if (redrawTimer) clearTimeout(redrawTimer);
      redrawTimer = setTimeout(function () { redrawTimer = null; if (!dead) redraw(); }, 120);
    }

    function redraw() {
      if (dead) return;
      TYPES.forEach(function (t) {
        groups[t].clearLayers();
        var show = !!kept.on[t] && access[t];
        if (show && !map.hasLayer(groups[t])) groups[t].addTo(map);
        if (!show && map.hasLayer(groups[t])) map.removeLayer(groups[t]);
      });
      trackLayers = {};
      if (kept.on.controles && access.controles) drawPoints('controles', data.controles.filter(visible), controlIcon, controlPopup);
      if (kept.on.lieux && access.lieux) drawPoints('lieux', data.lieux, placeIcon, placePopup);
      if (kept.on.pistes && access.pistes) drawTracks(data.pistes.filter(visible));
      updateCounts();
    }

    function controlIcon(f) {
      var p = f.properties;
      var ring = TONE_RING[p.tone] || TONE_RING.none;
      var html = '<span class="sse-ml-pt sse-ml-pt--check" style="background:' + ageColor(p.at) + ';box-shadow:0 0 0 2px ' + ring + '"></span>';
      return L.divIcon({ className: 'sse-ml-icon', html: html, iconSize: [16, 16], iconAnchor: [8, 8] });
    }

    function placeIcon() {
      return L.divIcon({ className: 'sse-ml-icon', html: '<span class="sse-ml-pt sse-ml-pt--place" style="background:' + PLACE_COLOR + '"></span>', iconSize: [18, 18], iconAnchor: [9, 9] });
    }

    function clusterIcon(n, type) {
      var color = type === 'lieux' ? PLACE_COLOR : AGE_COLORS.fresh;
      var s = n > 99 ? 38 : 32;
      return L.divIcon({
        className: 'sse-ml-icon',
        html: '<span class="sse-ml-cluster" style="border-color:' + color + '">' + n + '</span>',
        iconSize: [s, s], iconAnchor: [s / 2, s / 2]
      });
    }

    function row(label, value) {
      if (value == null || value === '') return null;
      var p = el('p', 'sse-ml-pop__row');
      p.appendChild(el('span', 'sse-ml-pop__k', label));
      p.appendChild(document.createTextNode(' '));
      p.appendChild(el('span', 'sse-ml-pop__v', value));
      return p;
    }

    function link(path, label) {
      if (!path || !SAFE_PATH.test(path)) return null;
      var a = el('a', 'sse-ml-pop__link', label);
      a.href = base + '/' + path;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      return a;
    }

    function put(box, node) { if (node) box.appendChild(node); }

    function controlPopup(f) {
      var p = f.properties;
      var box = el('div', 'sse-ml-pop');
      box.appendChild(el('strong', 'sse-ml-pop__title', p.name || tr('anon_check')));
      put(box, row(tr('ref'), p.ref));
      put(box, row(tr('result'), p.verdict_label));
      put(box, row(tr('when'), p.at ? localStamp(parseIso(p.at)) : ''));
      put(box, row(tr('game_time'), p.game_time));
      put(box, row(tr('grid'), p.grid));
      put(box, row(tr('source'), tr('src_check')));
      var links = el('p', 'sse-ml-pop__links');
      put(links, link(p.sheet, tr('open_sheet')));
      put(links, link(p.detail, tr('open_check')));
      if (links.childNodes.length) box.appendChild(links);
      return box;
    }

    function placePopup(f) {
      var p = f.properties;
      var box = el('div', 'sse-ml-pop');
      box.appendChild(el('strong', 'sse-ml-pop__title', p.name || tr('place')));
      put(box, row(tr('kind'), p.kind_label));
      put(box, row(tr('state'), p.status_label));
      put(box, row(tr('grid'), p.grid));
      put(box, row(tr('source'), tr('src_site')));
      var links = el('p', 'sse-ml-pop__links');
      put(links, link(p.detail, tr('open_place')));
      if (links.childNodes.length) box.appendChild(links);
      return box;
    }

    /* Regroupement par cases de l'écran : un point seul reste un symbole, les autres deviennent un compteur. */
    function drawPoints(type, feats, iconFor, popupFor) {
      var cells = {};
      var order = [];
      var zoom = map.getZoom();
      feats.forEach(function (f) {
        if (!f.geometry || f.geometry.type !== 'Point') return;
        // Cases fixées à la carte (pas à l'écran) : un déplacement ne rebat pas les regroupements.
        var p = map.project(ll(f.geometry.coordinates), zoom);
        var key = Math.floor(p.x / CLUSTER_PX) + ':' + Math.floor(p.y / CLUSTER_PX);
        if (!cells[key]) { cells[key] = []; order.push(key); }
        cells[key].push(f);
      });
      order.forEach(function (key) {
        var list = cells[key];
        if (list.length === 1) {
          var f = list[0];
          var m = L.marker(ll(f.geometry.coordinates), { icon: iconFor(f), keyboard: true, title: String(f.properties.name || f.properties.ref || ''), alt: String(f.properties.name || f.properties.ref || tr(type === 'lieux' ? 'place' : 'anon_check')) });
          m.bindPopup(function () { return popupFor(f); }, { maxWidth: 280 });
          m.addTo(groups[type]);
          return;
        }
        var b = L.latLngBounds(list.map(function (f) { return ll(f.geometry.coordinates); }));
        var c = L.marker(b.getCenter(), { icon: clusterIcon(list.length, type), keyboard: true, title: fmt(tr('cluster'), list.length), alt: fmt(tr('cluster'), list.length) });
        c.on('click', function () {
          if (map.getZoom() >= map.getMaxZoom() - 1) { map.setView(b.getCenter(), map.getZoom() + 1); return; }
          map.fitBounds(b.pad(0.25), { maxZoom: map.getMaxZoom() });
        });
        c.addTo(groups[type]);
      });
    }

    function arrowIcon(deg, color) {
      return L.divIcon({
        className: 'sse-ml-icon sse-ml-arrow',
        html: '<svg width="14" height="14" viewBox="0 0 14 14" style="transform:rotate(' + deg.toFixed(0) + 'deg)" aria-hidden="true"><path d="M7 1 L12 12 L7 9.200 L2 12 Z" fill="' + color + '" stroke="#0b1218" stroke-width="1"/></svg>',
        iconSize: [14, 14], iconAnchor: [7, 7]
      });
    }

    function drawTracks(feats) {
      var arrowLayer = L.layerGroup();
      feats.forEach(function (f, i) {
        if (!f.geometry || f.geometry.type !== 'LineString' || f.geometry.coordinates.length < 2) return;
        var color = TRACK_COLORS[i % TRACK_COLORS.length];
        var pts = f.geometry.coordinates.map(ll);
        var id = f.properties.extraction_id || i;
        var line = L.polyline(pts, { color: color, weight: 3, opacity: 0.9, renderer: canvas, interactive: true });
        line.on('click', function (e) { selectTrack(f, id, color, e.latlng); });
        line.addTo(groups.pistes);
        trackLayers[id] = { f: f, color: color, line: line, pts: pts, extra: L.layerGroup().addTo(groups.pistes) };
        // Début (cercle creux) et fin (carré plein) : le sens se lit sans survoler.
        var start = L.marker(pts[0], { icon: L.divIcon({ className: 'sse-ml-icon', html: '<span class="sse-ml-pt sse-ml-pt--start" style="border-color:' + color + '"></span>', iconSize: [14, 14], iconAnchor: [7, 7] }), keyboard: true, title: tr('track_start'), alt: tr('track_start') });
        var end = L.marker(pts[pts.length - 1], { icon: L.divIcon({ className: 'sse-ml-icon', html: '<span class="sse-ml-pt sse-ml-pt--end" style="background:' + color + '"></span>', iconSize: [16, 16], iconAnchor: [8, 8] }), keyboard: true, title: tr('track_end'), alt: tr('track_end'), zIndexOffset: 400 });
        [start, end].forEach(function (m) { m.on('click', function () { selectTrack(f, id, color, m.getLatLng()); }); m.addTo(groups.pistes); });
        // Flèches de sens : une par segment assez long à l'écran (au plus 40 par piste).
        var drawn = 0;
        for (var k = 1; k < pts.length && drawn < 40; k++) {
          var a = map.latLngToContainerPoint(pts[k - 1]);
          var b = map.latLngToContainerPoint(pts[k]);
          if (Math.abs(b.x - a.x) + Math.abs(b.y - a.y) < 36) continue;
          var mid = L.latLng((pts[k - 1].lat + pts[k].lat) / 2, (pts[k - 1].lng + pts[k].lng) / 2);
          L.marker(mid, { icon: arrowIcon(bearing(a, b), color), interactive: false, keyboard: false }).addTo(arrowLayer);
          drawn++;
        }
      });
      arrowLayer.addTo(groups.pistes);
      if (selectedTrack && trackLayers[selectedTrack.id]) showVertices(selectedTrack.id);
    }

    function trackPopup(f) {
      var p = f.properties;
      var box = el('div', 'sse-ml-pop');
      box.appendChild(el('strong', 'sse-ml-pop__title', p.label || p.ref || tr('track')));
      put(box, row(tr('ref'), p.ref));
      put(box, row(tr('recorded'), p.recorded_at ? localStamp(parseIso(p.recorded_at)) : ''));
      var times = p.times || [];
      put(box, row(tr('positions'), fmt(tr('positions_count'), f.geometry.coordinates.length, p.vertices_total)));
      put(box, row(tr('track_start'), times[0] || ''));
      put(box, row(tr('track_end'), times[times.length - 1] || ''));
      box.appendChild(el('p', 'sse-ml-pop__hint', tr('track_hint')));
      var links = el('p', 'sse-ml-pop__links');
      put(links, link(p.detail, tr('open_extraction')));
      if (links.childNodes.length) box.appendChild(links);
      return box;
    }

    function selectTrack(f, id, color, at) {
      if (selectedTrack && selectedTrack.id !== id) hideVertices(selectedTrack.id);
      selectedTrack = { id: id };
      showVertices(id);
      L.popup({ maxWidth: 280 }).setLatLng(at).setContent(trackPopup(f)).openOn(map);
    }

    function hideVertices(id) {
      var t = trackLayers[id];
      if (t && t.extra) t.extra.clearLayers();
    }

    /* Sommets datés de la piste choisie seulement (les autres restent légères). */
    function showVertices(id) {
      var t = trackLayers[id];
      if (!t) return;
      t.extra.clearLayers();
      var times = t.f.properties.times || [];
      t.pts.forEach(function (pt, i) {
        var m = L.circleMarker(pt, { radius: 4, weight: 1.5, color: '#0b1218', fillColor: t.color, fillOpacity: 1, renderer: canvas });
        var label = fmt(tr('vertex'), i + 1, t.pts.length) + (times[i] ? ' · ' + times[i] : '');
        m.bindTooltip(label, { direction: 'top' });
        m.addTo(t.extra);
      });
    }

    /* --------------------------------------------------------- Compteurs */

    function updateCounts() {
      var total = 0;
      TYPES.forEach(function (t) {
        var shown = 0;
        if (kept.on[t] && access[t]) {
          shown = t === 'lieux' ? data.lieux.length : (t === 'controles' ? data.controles.filter(visible).length : data.pistes.filter(visible).length);
        }
        total += shown;
        var c = q('[data-sse-ml-count="' + t + '"]');
        if (c) c.textContent = String(shown);
        var s = q('[data-sse-ml-state="' + t + '"]');
        if (!s) return;
        var info = metaTypes[t] || null;
        var text = '';
        if (kept.on[t] && access[t] && info) {
          if (info.allowed === false) text = tr(info.empty === 'audit_unavailable' ? 'audit_unavailable' : 'forbidden');
          else if (!shown && info.empty && info.empty !== 'none') text = tr('empty_' + info.empty) || tr('empty_none_' + t);
          else if (!shown && info.empty === 'none') text = tr('empty_none_' + t);
          else if (info.truncated) text = tr('truncated');
        }
        s.textContent = text;
        s.hidden = !text;
      });
      msg('', '');
      var tot = q('[data-sse-ml-total]');
      if (tot) tot.textContent = fmt(tr('total'), total);
      if (typeof opts.onCount === 'function') opts.onCount(total);
    }

    /* --------------------------------------------------------- Panneau */

    function syncPeriodUi() {
      var sel = q('[data-sse-ml-period]');
      if (sel) sel.value = kept.period;
    }

    on(root, 'change', function (e) {
      var t = e.target;
      if (!t || !t.getAttribute) return;
      var type = t.getAttribute('data-sse-ml-toggle');
      if (type && access[type]) {
        kept.on[type] = !!t.checked;
        if (!t.checked) data[type] = [];
        if (t.checked) { refresh(true); } else { redraw(); }
        if (!anyOn()) { msg('', ''); }
        return;
      }
      if (t.hasAttribute('data-sse-ml-period')) {
        kept.period = t.value;
        try { if (store) store.setItem(PERIOD_KEY, kept.period); } catch (err) { /* stockage indisponible */ }
        refresh(true);
      }
    });
    on(root, 'input', function (e) {
      var t = e.target;
      if (t && t.hasAttribute && t.hasAttribute('data-sse-ml-slider')) {
        kept.cutoff = Number(t.value);
        applyCutoff();
        scheduleRedraw();
      }
    });
    on(root, 'keydown', function (e) {
      if (e.key === 'Escape' && typeof opts.onClose === 'function') { e.stopPropagation(); opts.onClose(); }
    });
    var closeBtn = q('[data-sse-ml-close]');
    if (closeBtn) on(closeBtn, 'click', function () { if (typeof opts.onClose === 'function') opts.onClose(); });

    mapOn('zoomend', scheduleRedraw);
    mapOn('moveend', function () {
      if (!anyOn()) return;
      if (!insideFetched()) refresh(false);
      else scheduleRedraw();
    });
    on(document, 'visibilitychange', function () {
      if (!document.hidden && anyOn() && Date.now() - lastFetchAt > REFRESH_MS / 2) refresh(true);
    });

    var slider = q('[data-sse-ml-slider]');
    if (slider) { slider.value = String(kept.cutoff); }
    syncPeriodUi();
    schedule();
    probe();

    var inst = {
      refresh: function () { refresh(true); },
      setOn: function (type, v) { if (access[type]) { kept.on[type] = !!v; var cb = q('[data-sse-ml-toggle="' + type + '"]'); if (cb) cb.checked = !!v; if (v) refresh(true); else redraw(); } },
      access: function () { return access; },
      feed: function (geojson) {
        // Injection directe d'un GeoJSON (essais, rendu hors ligne) : même chemin de dessin que la lecture réseau.
        var types = TYPES.slice();
        access = { controles: true, lieux: true, pistes: true, noms: true };
        applyAccess(access);
        ingest(geojson, types);
      },
      destroy: function () {
        dead = true;
        if (redrawTimer) clearTimeout(redrawTimer);
        if (retryTimer) clearTimeout(retryTimer);
        if (inflight && inflight.abort) { try { inflight.abort(); } catch (e) { /* déjà terminé */ } }
        removers.forEach(function (fn) { try { fn(); } catch (e) { /* écouteur déjà retiré */ } });
        removers = [];
        clearAll();
        TYPES.forEach(function (t) { groups[t].clearLayers(); });
        if (map.hasLayer(canvas)) map.removeLayer(canvas);
        root.__sseMl = null;
      }
    };
    root.__sseMl = inst;

    return inst;
  }

  /* ------------------------------------------------------------------ Pages hôtes */

  function mountFloating(root, toggleBtn, hostOpts) {
    function setOpen(open) {
      root.hidden = !open;
      if (toggleBtn) { toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); toggleBtn.classList.toggle('is-on', open); }
      if (open) { var f = root.querySelector('input:not([disabled]), select, button'); if (f) f.focus(); }
    }
    hostOpts.floating = true;
    hostOpts.onAccess = function (any) { if (toggleBtn) toggleBtn.hidden = !any; if (!any) root.hidden = true; };
    hostOpts.onClose = function () { setOpen(false); if (toggleBtn) toggleBtn.focus(); };
    if (toggleBtn && !toggleBtn.__sseMlBound) {
      toggleBtn.__sseMlBound = true;
      toggleBtn.addEventListener('click', function () { setOpen(root.hidden); });
    }
    root.hidden = true;
    return mount(root, hostOpts);
  }

  /** Poste TOC web : TocMap.create(...) donne toLL / toWorld (décalage du terrain compris). */
  function mountToc(tm, mapId) {
    var root = document.getElementById('toc-sse-ml');
    if (!root || !tm) return null;
    return mountFloating(root, document.getElementById('toc-sse-ml-btn'), {
      map: tm.map, mapId: mapId, base: (global.TOC_BOOT && global.TOC_BOOT.base) || '',
      toLatLng: function (x, y) { return tm.toLL([x, y]); },
      toWorld: function (latlng) { return tm.toWorld(latlng); }
    });
  }

  /** ATAK web / Overwatch : ATAKMap fournit la carte et le décalage ; ATAKSocket, le numéro de carte. */
  function mountAtak() {
    var root = document.getElementById('ow-sse-ml');
    var A = global.ATAKMap;
    if (!root || !A || !A.getMap || !A.getMap() || !A.latLngFromWorld) return null;
    return mount(root, {
      map: A.getMap(),
      mapId: function () { return global.ATAKSocket && global.ATAKSocket.getMapId ? global.ATAKSocket.getMapId() : 0; },
      base: root.getAttribute('data-base') || '',
      toLatLng: function (x, y) { return A.latLngFromWorld(x, y); },
      toWorld: function (latlng) { var w = A.worldFromLatLng(latlng); return [w.x, w.y]; }
    });
  }

  /** TACMAP : cartes du jeu seulement (latlng = [y, x]) ; les fonds « monde » et « image » n'ont pas de mètres Arma. */
  function mountTacmap(detail) {
    var root = document.getElementById('tacmap-sse-ml');
    if (!root || !detail || !detail.map) return null;
    var supported = detail.type === 'arma';
    var inst = mountFloating(root, document.getElementById('tacmap-sse-ml-btn'), {
      map: detail.map,
      mapId: function () { return detail.getMapId ? detail.getMapId() : detail.mapId; },
      base: root.getAttribute('data-base') || '',
      supported: supported,
      toLatLng: function (x, y) { return global.L.latLng(y, x); },
      toWorld: function (latlng) { return [latlng.lng, latlng.lat]; }
    });
    return inst;
  }

  var host = { instance: null };

  function boot() {
    global.addEventListener('atak:mapready', function () {
      if (host.instance) host.instance.destroy();
      host.instance = mountAtak();
    });
    global.addEventListener('comspec:tacmap-ready', function (e) {
      if (host.instance) host.instance.destroy();
      host.instance = mountTacmap(e.detail);
    });
    if (global.ATAKMap && global.ATAKMap.getMap && global.ATAKMap.getMap()) host.instance = mountAtak();
  }

  global.SseMapLayers = { mount: mount, mountToc: mountToc, mountAtak: mountAtak, mountTacmap: mountTacmap, boot: boot };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})(window);
